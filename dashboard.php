<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

$stats = [
    'Locations'          => (int)q('SELECT COUNT(*) FROM locations')->fetchColumn(),
    'Active clients'     => (int)q("SELECT COUNT(*) FROM locations WHERE crm_status = 'active'")->fetchColumn(),
    'Reviews to answer'  => (int)q('SELECT COUNT(*) FROM reviews WHERE reply_comment IS NULL')->fetchColumn(),
    'Avg rating'         => round((float)q('SELECT AVG(star_rating) FROM reviews')->fetchColumn(), 2) ?: '-',
    'Scheduled posts'    => (int)q("SELECT COUNT(*) FROM posts WHERE status = 'scheduled'")->fetchColumn(),
    'Failed posts'       => (int)q("SELECT COUNT(*) FROM posts WHERE status = 'failed'")->fetchColumn(),
];
$recent = q('SELECT r.*, l.title FROM reviews r JOIN locations l ON l.id = r.location_id
             WHERE r.reply_comment IS NULL ORDER BY r.review_created_at DESC LIMIT 5')->fetchAll();

page_header('Dashboard', 'dashboard');
?>
<h1>Dashboard</h1>
<?php if (!google_connected()): ?>
<div class="flash err">Google is not connected yet. <a href="locations.php">Go to Locations</a> and click Connect Google.</div>
<?php endif; ?>
<div class="grid">
<?php foreach ($stats as $label => $v): ?>
  <div class="card stat"><span class="muted"><?= e($label) ?></span><strong><?= e((string)$v) ?></strong></div>
<?php endforeach; ?>
</div>
<h2>Latest reviews needing a reply</h2>
<?php foreach ($recent as $r): ?>
<div class="card"><strong><?= e($r['reviewer_name']) ?></strong> <span class="stars"><?= stars((int)$r['star_rating']) ?></span>
  <span class="muted"><?= e($r['title']) ?></span><p><?= e(mb_strimwidth((string)$r['comment'], 0, 200, '...')) ?></p></div>
<?php endforeach; if (!$recent): ?><p class="muted">All caught up.</p><?php endif; ?>
<p><a href="reviews.php">Open reviews inbox &rarr;</a></p>
<?php page_footer();
