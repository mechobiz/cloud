<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

const LABELS = [
    'BUSINESS_IMPRESSIONS_DESKTOP_MAPS' => 'Maps (desktop)', 'BUSINESS_IMPRESSIONS_DESKTOP_SEARCH' => 'Search (desktop)',
    'BUSINESS_IMPRESSIONS_MOBILE_MAPS' => 'Maps (mobile)', 'BUSINESS_IMPRESSIONS_MOBILE_SEARCH' => 'Search (mobile)',
    'CALL_CLICKS' => 'Calls', 'WEBSITE_CLICKS' => 'Website clicks', 'BUSINESS_DIRECTION_REQUESTS' => 'Directions',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $n = 0;
        foreach (q('SELECT * FROM locations')->fetchAll() as $loc) {
            $n += gbp_sync_insights($loc, 30);
        }
        flash("Synced $n data point(s).");
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('insights.php' . (isset($_GET['location']) ? '?location=' . (int)$_GET['location'] : ''));
}

$locId = (int)($_GET['location'] ?? 0);
$args = [];
$w = 'day >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
if ($locId) {
    $w .= ' AND location_id = ?';
    $args[] = $locId;
}
$totals = q("SELECT metric, SUM(value) v FROM insights_daily WHERE $w GROUP BY metric", $args)->fetchAll(PDO::FETCH_KEY_PAIR);
$daily = q("SELECT day, SUM(value) v FROM insights_daily WHERE $w AND metric LIKE 'BUSINESS_IMPRESSIONS%' GROUP BY day ORDER BY day", $args)->fetchAll(PDO::FETCH_KEY_PAIR);
$max = max(1, (int)max($daily ?: [0]));
$locs = q('SELECT id, title FROM locations ORDER BY title')->fetchAll();

page_header('Insights', 'insights');
?>
<h1>Performance (last 30 days)</h1>
<div class="bar">
  <form method="get" class="inline"><select name="location" onchange="this.form.submit()">
    <option value="0">All locations</option>
    <?php foreach ($locs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $locId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['title']) ?></option><?php endforeach; ?>
  </select></form>
  <form method="post" class="inline"><?= csrf_field() ?><button>Sync insights from Google</button></form>
</div>
<div class="grid">
<?php foreach (LABELS as $k => $label): ?>
  <div class="card stat"><span class="muted"><?= e($label) ?></span><strong><?= number_format((int)($totals[$k] ?? 0)) ?></strong></div>
<?php endforeach; ?>
</div>
<div class="card"><h2>Daily impressions</h2>
<div class="bars">
<?php foreach ($daily as $day => $v): ?>
  <div title="<?= e($day) ?>: <?= (int)$v ?>" style="height:<?= max(2, (int)round($v / $max * 100)) ?>%"></div>
<?php endforeach; if (!$daily): ?><span class="muted">No data yet. Click "Sync insights from Google".</span><?php endif; ?>
</div></div>
<?php page_footer();
