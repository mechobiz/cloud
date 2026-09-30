<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        if (($_POST['action'] ?? '') === 'sync') {
            $n = 0;
            foreach (q("SELECT * FROM locations WHERE account_name <> 'manual'")->fetchAll() as $loc) {
                $n += gbp_sync_reviews($loc);
            }
            flash("Synced $n review(s).");
        } elseif (($_POST['action'] ?? '') === 'add') {
            $rating = (int)($_POST['rating'] ?? 0);
            if (!(int)($_POST['location_id'] ?? 0) || $rating < 1 || $rating > 5) {
                throw new RuntimeException('Pick a location and a rating from 1 to 5.');
            }
            q('INSERT INTO reviews (location_id, review_id, reviewer_name, star_rating, comment, review_created_at) VALUES (?,?,?,?,?,NOW())', [
                (int)$_POST['location_id'], 'manual-' . bin2hex(random_bytes(6)),
                trim($_POST['reviewer'] ?? '') ?: 'Anonymous', $rating, trim($_POST['comment'] ?? '') ?: null,
            ]);
            flash('Review logged.');
        } elseif (($_POST['action'] ?? '') === 'reply') {
            $rev = q('SELECT r.*, l.account_name, l.location_name FROM reviews r JOIN locations l ON l.id = r.location_id WHERE r.id = ?',
                     [(int)($_POST['id'] ?? 0)])->fetch();
            $text = trim($_POST['reply'] ?? '');
            if (!$rev || $text === '') {
                throw new RuntimeException('Write a reply first.');
            }
            gbp_reply_review(['id' => $rev['location_id'], 'account_name' => $rev['account_name'], 'location_name' => $rev['location_name']],
                             $rev['review_id'], $text);
            flash('Reply posted to Google.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('reviews.php?' . http_build_query(array_intersect_key($_GET, ['filter' => 1, 'location' => 1])));
}

$filter = $_GET['filter'] ?? 'unanswered';
$locId = (int)($_GET['location'] ?? 0);
$where = [];
$args = [];
if ($filter === 'unanswered') {
    $where[] = 'r.reply_comment IS NULL';
}
if ($locId) {
    $where[] = 'r.location_id = ?';
    $args[] = $locId;
}
$sql = 'SELECT r.*, l.title FROM reviews r JOIN locations l ON l.id = r.location_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY r.review_created_at DESC LIMIT 200';
$reviews = q($sql, $args)->fetchAll();
$locs = q('SELECT id, title FROM locations ORDER BY title')->fetchAll();

page_header('Reviews', 'reviews');
?>
<h1>Reviews</h1>
<div class="bar">
  <form method="get" class="inline">
    <select name="filter" onchange="this.form.submit()">
      <option value="unanswered" <?= $filter === 'unanswered' ? 'selected' : '' ?>>Needs reply</option>
      <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All reviews</option>
    </select>
    <select name="location" onchange="this.form.submit()">
      <option value="0">All locations</option>
      <?php foreach ($locs as $l): ?><option value="<?= (int)$l['id'] ?>" <?= $locId === (int)$l['id'] ? 'selected' : '' ?>><?= e($l['title']) ?></option><?php endforeach; ?>
    </select>
  </form>
  <?php if (google_enabled()): ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button>Sync reviews from Google</button></form>
  <?php endif; ?>
</div>

<details class="card"><summary><strong>+ Log a review manually</strong></summary>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <label>Location<select name="location_id" required><option value="">Choose...</option>
    <?php foreach ($locs as $l): ?><option value="<?= (int)$l['id'] ?>"><?= e($l['title']) ?></option><?php endforeach; ?></select></label>
  <label>Reviewer name<input name="reviewer"></label>
  <label>Rating<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= stars($i) ?></option><?php endfor; ?></select></label>
  <label>Review text<textarea name="comment" rows="3"></textarea></label>
  <button>Add review</button></form></details>

<?php foreach ($reviews as $r): ?>
<div class="card">
  <div class="row"><strong><?= e($r['reviewer_name']) ?></strong>
    <span class="stars"><?= stars((int)$r['star_rating']) ?></span>
    <span class="muted"><?= e($r['title']) ?> &middot; <?= e($r['review_created_at']) ?></span></div>
  <p><?= nl2br(e($r['comment'] ?: '(no text)')) ?></p>
  <?php if ($r['reply_comment']): ?>
    <blockquote><strong>Your reply:</strong> <?= nl2br(e($r['reply_comment'])) ?></blockquote>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
    <textarea name="reply" rows="2" placeholder="<?= $r['reply_comment'] ? 'Edit reply...' : 'Write a reply...' ?>"></textarea>
    <button><?= $r['reply_comment'] ? 'Update reply' : 'Post reply' ?></button>
  </form>
</div>
<?php endforeach; if (!$reviews): ?>
<p class="muted">Nothing here yet.</p>
<?php endif;
page_footer();
