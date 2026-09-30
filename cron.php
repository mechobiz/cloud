<?php
// Run every 15 minutes from cPanel Cron Jobs:  php /home/USER/public_html/cron.php
// (Or over HTTP:  https://yourdomain.com/cron.php?key=YOUR_CRON_KEY)
require __DIR__ . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli' && !hash_equals((string)$config['cron_key'], (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}
header('Content-Type: text/plain');

// 1) publish due scheduled posts
foreach (q("SELECT * FROM posts WHERE status = 'scheduled' AND scheduled_at <= NOW()")->fetchAll() as $p) {
    try {
        gbp_publish_post($p);
        echo "published post {$p['id']}\n";
    } catch (Throwable $e) {
        echo "post {$p['id']} failed: {$e->getMessage()}\n";
    }
}

// 2) nightly-ish sync of reviews and insights (once per hour at most)
$last = @filemtime(sys_get_temp_dir() . '/gmbcrm_sync');
if (!$last || time() - $last > 3600) {
    touch(sys_get_temp_dir() . '/gmbcrm_sync');
    foreach (q('SELECT * FROM locations')->fetchAll() as $loc) {
        try {
            echo "reviews {$loc['title']}: " . gbp_sync_reviews($loc) . "\n";
            echo "insights {$loc['title']}: " . gbp_sync_insights($loc, 7) . "\n";
        } catch (Throwable $e) {
            echo "sync {$loc['title']} failed: {$e->getMessage()}\n";
        }
        sleep(2); // stay under Google's low per-minute quotas
    }
}
