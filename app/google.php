<?php
declare(strict_types=1);

const GOOGLE_SCOPE = 'https://www.googleapis.com/auth/business.manage openid email';

const INSIGHT_METRICS = [
    'BUSINESS_IMPRESSIONS_DESKTOP_MAPS',
    'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH',
    'BUSINESS_IMPRESSIONS_MOBILE_MAPS',
    'BUSINESS_IMPRESSIONS_MOBILE_SEARCH',
    'CALL_CLICKS',
    'WEBSITE_CLICKS',
    'BUSINESS_DIRECTION_REQUESTS',
];

/** True once real Google credentials are in config; until then the app runs in manual mode. */
function google_enabled(): bool
{
    global $config;
    $id = (string)($config['google']['client_id'] ?? '');
    return $id !== '' && !str_starts_with($id, 'xxxx');
}

function is_manual_location(array $loc): bool
{
    return ($loc['account_name'] ?? '') === 'manual';
}

/* ---------- low-level HTTP ---------- */

function http_json(string $method, string $url, ?array $json = null, array $headers = [], ?array $form = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($form !== null) {
        $opts[CURLOPT_POSTFIELDS] = http_build_query($form);
    } elseif ($json !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($json);
        $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Network error: ' . $err);
    }
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    return [$status, is_array($data) ? $data : []];
}

/* ---------- OAuth ---------- */

function google_auth_url(string $state): string
{
    global $config;
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id'     => $config['google']['client_id'],
        'redirect_uri'  => $config['google']['redirect_uri'],
        'response_type' => 'code',
        'scope'         => GOOGLE_SCOPE,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ]);
}

function google_token_request(array $form): array
{
    global $config;
    $form += [
        'client_id'     => $config['google']['client_id'],
        'client_secret' => $config['google']['client_secret'],
    ];
    [$status, $data] = http_json('POST', 'https://oauth2.googleapis.com/token', null, [], $form);
    if ($status !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('Google token error: ' . ($data['error_description'] ?? $data['error'] ?? "HTTP $status"));
    }
    return $data;
}

function google_handle_callback(string $code): void
{
    global $config;
    $t = google_token_request([
        'code'         => $code,
        'grant_type'   => 'authorization_code',
        'redirect_uri' => $config['google']['redirect_uri'],
    ]);
    $existing = q('SELECT refresh_token FROM google_tokens WHERE id = 1')->fetch();
    $refresh = $t['refresh_token'] ?? ($existing['refresh_token'] ?? null);
    if (!$refresh) {
        throw new RuntimeException('Google did not return a refresh token. Remove the app at myaccount.google.com/permissions and connect again.');
    }
    $email = null;
    if (!empty($t['id_token'])) {
        $parts = explode('.', $t['id_token']);
        $claims = json_decode((string)base64_decode(strtr($parts[1] ?? '', '-_', '+/')), true);
        $email = $claims['email'] ?? null;
    }
    q('REPLACE INTO google_tokens (id, refresh_token, access_token, expires_at, connected_email, updated_at)
       VALUES (1, ?, ?, ?, ?, NOW())', [
        $refresh,
        $t['access_token'],
        date('Y-m-d H:i:s', time() + (int)($t['expires_in'] ?? 3600) - 60),
        $email,
    ]);
}

function google_connected(): ?array
{
    return q('SELECT connected_email, updated_at FROM google_tokens WHERE id = 1')->fetch() ?: null;
}

function google_access_token(): string
{
    $row = q('SELECT * FROM google_tokens WHERE id = 1')->fetch();
    if (!$row) {
        throw new RuntimeException('Google account not connected. Use "Connect Google" on the Locations page.');
    }
    if ($row['access_token'] && $row['expires_at'] && strtotime($row['expires_at']) > time()) {
        return $row['access_token'];
    }
    $t = google_token_request([
        'refresh_token' => $row['refresh_token'],
        'grant_type'    => 'refresh_token',
    ]);
    q('UPDATE google_tokens SET access_token = ?, expires_at = ?, updated_at = NOW() WHERE id = 1', [
        $t['access_token'],
        date('Y-m-d H:i:s', time() + (int)($t['expires_in'] ?? 3600) - 60),
    ]);
    return $t['access_token'];
}

/** Authenticated call to a Google API; throws with Google's error message on failure. */
function google_api(string $method, string $url, ?array $json = null): array
{
    [$status, $data] = http_json($method, $url, $json, ['Authorization: Bearer ' . google_access_token()]);
    if ($status >= 400) {
        $msg = $data['error']['message'] ?? "HTTP $status";
        if ($status === 429) {
            $msg .= ' (rate limited - Business Profile APIs have low quotas; wait a minute and retry)';
        }
        throw new RuntimeException("Google API: $msg");
    }
    return $data;
}

/* ---------- Locations ---------- */

function gbp_sync_locations(): int
{
    $count = 0;
    $acctPage = null;
    do {
        $url = 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts?pageSize=20'
             . ($acctPage ? '&pageToken=' . urlencode($acctPage) : '');
        $accounts = google_api('GET', $url);
        foreach ($accounts['accounts'] ?? [] as $acct) {
            $locPage = null;
            do {
                $lurl = 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $acct['name']
                      . '/locations?pageSize=100&readMask=' . urlencode('name,title,storefrontAddress,phoneNumbers,websiteUri')
                      . ($locPage ? '&pageToken=' . urlencode($locPage) : '');
                $locs = google_api('GET', $lurl);
                foreach ($locs['locations'] ?? [] as $l) {
                    $addr = $l['storefrontAddress'] ?? [];
                    $address = implode(', ', array_filter([
                        implode(' ', $addr['addressLines'] ?? []),
                        $addr['locality'] ?? null,
                        $addr['administrativeArea'] ?? null,
                        $addr['postalCode'] ?? null,
                    ]));
                    q('INSERT INTO locations (account_name, location_name, title, address, phone, website)
                       VALUES (?,?,?,?,?,?)
                       ON DUPLICATE KEY UPDATE account_name = VALUES(account_name), title = VALUES(title),
                           address = VALUES(address), phone = VALUES(phone), website = VALUES(website)', [
                        $acct['name'], $l['name'], $l['title'] ?? '(untitled)', $address,
                        $l['phoneNumbers']['primaryPhone'] ?? null, $l['websiteUri'] ?? null,
                    ]);
                    $count++;
                }
                $locPage = $locs['nextPageToken'] ?? null;
            } while ($locPage);
        }
        $acctPage = $accounts['nextPageToken'] ?? null;
    } while ($acctPage);
    return $count;
}

/* ---------- Reviews (My Business API v4 path: accounts/{a}/locations/{l}) ---------- */

function gbp_v4_path(array $loc): string
{
    // location_name is "locations/123"; v4 needs "accounts/456/locations/123"
    return $loc['account_name'] . '/' . $loc['location_name'];
}

const STAR_MAP = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];

function gbp_sync_reviews(array $loc): int
{
    $count = 0;
    $page = null;
    do {
        $url = 'https://mybusiness.googleapis.com/v4/' . gbp_v4_path($loc) . '/reviews?pageSize=50'
             . ($page ? '&pageToken=' . urlencode($page) : '');
        $data = google_api('GET', $url);
        foreach ($data['reviews'] ?? [] as $r) {
            $rating = STAR_MAP[str_replace('_STAR', '', $r['starRating'] ?? '')] ?? 0;
            $reply = $r['reviewReply'] ?? null;
            q('INSERT INTO reviews (location_id, review_id, reviewer_name, star_rating, comment, review_created_at, reply_comment, reply_updated_at)
               VALUES (?,?,?,?,?,?,?,?)
               ON DUPLICATE KEY UPDATE reviewer_name = VALUES(reviewer_name), star_rating = VALUES(star_rating),
                   comment = VALUES(comment), reply_comment = VALUES(reply_comment), reply_updated_at = VALUES(reply_updated_at)', [
                $loc['id'], $r['reviewId'], $r['reviewer']['displayName'] ?? 'Anonymous', $rating,
                $r['comment'] ?? null, isset($r['createTime']) ? date('Y-m-d H:i:s', strtotime($r['createTime'])) : null,
                $reply['comment'] ?? null, isset($reply['updateTime']) ? date('Y-m-d H:i:s', strtotime($reply['updateTime'])) : null,
            ]);
            $count++;
        }
        $page = $data['nextPageToken'] ?? null;
    } while ($page);
    q('UPDATE locations SET last_synced_at = NOW() WHERE id = ?', [$loc['id']]);
    return $count;
}

function gbp_reply_review(array $loc, string $reviewId, string $comment): void
{
    if (!is_manual_location($loc)) {
        $url = 'https://mybusiness.googleapis.com/v4/' . gbp_v4_path($loc) . '/reviews/' . rawurlencode($reviewId) . '/reply';
        google_api('PUT', $url, ['comment' => $comment]);
    }
    q('UPDATE reviews SET reply_comment = ?, reply_updated_at = NOW() WHERE location_id = ? AND review_id = ?',
      [$comment, $loc['id'], $reviewId]);
}

/* ---------- Posts ---------- */

function gbp_publish_post(array $post): void
{
    $loc = q('SELECT * FROM locations WHERE id = ?', [$post['location_id']])->fetch();
    if (!$loc) {
        throw new RuntimeException('Location no longer exists.');
    }
    if (is_manual_location($loc)) {
        q("UPDATE posts SET status = 'published', published_at = NOW(), error = NULL WHERE id = ?", [$post['id']]);
        return;
    }
    $body = ['languageCode' => 'en', 'summary' => $post['summary'], 'topicType' => 'STANDARD'];
    if (!empty($post['cta_type']) && $post['cta_type'] !== 'NONE') {
        $body['callToAction'] = ['actionType' => $post['cta_type']];
        if ($post['cta_type'] !== 'CALL' && !empty($post['cta_url'])) {
            $body['callToAction']['url'] = $post['cta_url'];
        }
    }
    try {
        $res = google_api('POST', 'https://mybusiness.googleapis.com/v4/' . gbp_v4_path($loc) . '/localPosts', $body);
        q("UPDATE posts SET status = 'published', google_post_name = ?, published_at = NOW(), error = NULL WHERE id = ?",
          [$res['name'] ?? null, $post['id']]);
    } catch (Throwable $ex) {
        q("UPDATE posts SET status = 'failed', error = ? WHERE id = ?", [$ex->getMessage(), $post['id']]);
        throw $ex;
    }
}

/* ---------- Performance insights ---------- */

function gbp_sync_insights(array $loc, int $days = 30): int
{
    $end = new DateTimeImmutable('-2 days');   // Google data lags ~2 days
    $start = $end->modify('-' . max(1, $days - 1) . ' days');
    $params = [];
    foreach (INSIGHT_METRICS as $m) {
        $params[] = 'dailyMetrics=' . $m;
    }
    foreach (['start' => $start, 'end' => $end] as $k => $d) {
        $params[] = "dailyRange.{$k}_date.year=" . $d->format('Y');
        $params[] = "dailyRange.{$k}_date.month=" . $d->format('n');
        $params[] = "dailyRange.{$k}_date.day=" . $d->format('j');
    }
    $url = 'https://businessprofileperformance.googleapis.com/v1/' . $loc['location_name']
         . ':fetchMultiDailyMetricsTimeSeries?' . implode('&', $params);
    $data = google_api('GET', $url);

    $count = 0;
    foreach ($data['multiDailyMetricTimeSeries'] ?? [] as $group) {
        foreach ($group['dailyMetricTimeSeries'] ?? [] as $series) {
            $metric = $series['dailyMetric'] ?? '';
            foreach ($series['timeSeries']['datedValues'] ?? [] as $dv) {
                $d = $dv['date'] ?? null;
                if (!$d) {
                    continue;
                }
                q('INSERT INTO insights_daily (location_id, day, metric, value) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE value = VALUES(value)', [
                    $loc['id'], sprintf('%04d-%02d-%02d', $d['year'], $d['month'], $d['day']),
                    $metric, (int)($dv['value'] ?? 0),
                ]);
                $count++;
            }
        }
    }
    return $count;
}
