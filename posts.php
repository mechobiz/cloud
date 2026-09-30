<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

const CTA_TYPES = ['NONE' => 'No button', 'LEARN_MORE' => 'Learn more', 'BOOK' => 'Book', 'ORDER' => 'Order online',
                   'SHOP' => 'Shop', 'SIGN_UP' => 'Sign up', 'CALL' => 'Call'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $summary = trim($_POST['summary'] ?? '');
            $locIds = array_map('intval', (array)($_POST['locations'] ?? []));
            $cta = array_key_exists($_POST['cta_type'] ?? '', CTA_TYPES) ? $_POST['cta_type'] : 'NONE';
            $url = trim($_POST['cta_url'] ?? '');
            if ($summary === '' || mb_strlen($summary) > 1500 || !$locIds) {
                throw new RuntimeException('Pick at least one location and write a post (max 1500 characters).');
            }
            if (!in_array($cta, ['NONE', 'CALL'], true) && !filter_var($url, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('That button type needs a valid URL.');
            }
            $when = trim($_POST['scheduled_at'] ?? '');
            $ts = $when !== '' ? strtotime($when) : false;
            $mode = $_POST['mode'] ?? 'now';
            foreach ($locIds as $lid) {
                q('INSERT INTO posts (location_id, summary, cta_type, cta_url, scheduled_at, status) VALUES (?,?,?,?,?,?)', [
                    $lid, $summary, $cta, $url ?: null,
                    $mode === 'schedule' && $ts ? date('Y-m-d H:i:s', $ts) : null,
                    $mode === 'schedule' && $ts ? 'scheduled' : 'draft',
                ]);
                if ($mode === 'now') {
                    $id = (int)db()->lastInsertId();
                    gbp_publish_post(q('SELECT * FROM posts WHERE id = ?', [$id])->fetch());
                }
            }
            flash($mode === 'now' ? 'Published.' : ($mode === 'schedule' ? 'Scheduled.' : 'Saved as draft.'));
        } elseif ($action === 'publish') {
            gbp_publish_post(q('SELECT * FROM posts WHERE id = ?', [(int)$_POST['id']])->fetch());
            flash('Published.');
        } elseif ($action === 'delete') {
            q("DELETE FROM posts WHERE id = ? AND status <> 'published'", [(int)$_POST['id']]);
            flash('Deleted.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('posts.php');
}

$locs = q('SELECT id, title FROM locations ORDER BY title')->fetchAll();
$posts = q('SELECT p.*, l.title FROM posts p JOIN locations l ON l.id = p.location_id ORDER BY p.created_at DESC LIMIT 100')->fetchAll();

page_header('Posts', 'posts');
?>
<h1>Posts</h1>
<form method="post" class="card">
  <h2>New post</h2>
  <?= csrf_field() ?><input type="hidden" name="action" value="create">
  <fieldset><legend>Locations</legend>
    <?php foreach ($locs as $l): ?>
      <label class="check"><input type="checkbox" name="locations[]" value="<?= (int)$l['id'] ?>"> <?= e($l['title']) ?></label>
    <?php endforeach; if (!$locs): ?><span class="muted">Sync locations first.</span><?php endif; ?>
  </fieldset>
  <label>Post text<textarea name="summary" rows="4" maxlength="1500" required></textarea></label>
  <label>Button<select name="cta_type"><?php foreach (CTA_TYPES as $k => $v): ?><option value="<?= $k ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
  <label>Button URL<input name="cta_url" placeholder="https://..."></label>
  <label>Schedule for (optional)<input type="datetime-local" name="scheduled_at"></label>
  <div class="bar">
    <button name="mode" value="now">Publish now</button>
    <button name="mode" value="schedule">Schedule</button>
    <button name="mode" value="draft">Save draft</button>
  </div>
</form>

<table>
<tr><th>Location</th><th>Post</th><th>Status</th><th>When</th><th></th></tr>
<?php foreach ($posts as $p): ?>
<tr>
  <td><?= e($p['title']) ?></td>
  <td><?= e(mb_strimwidth($p['summary'], 0, 120, '...')) ?><?= $p['error'] ? '<br><span class="err-text">' . e($p['error']) . '</span>' : '' ?></td>
  <td><span class="pill <?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
  <td><?= e($p['published_at'] ?: $p['scheduled_at']) ?></td>
  <td>
    <?php if ($p['status'] !== 'published'): ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <button name="action" value="publish">Publish</button>
      <button name="action" value="delete" onclick="return confirm('Delete this post?')">Delete</button></form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php page_footer();
