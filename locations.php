<?php
require __DIR__ . '/app/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') {
                throw new RuntimeException('Enter a business name.');
            }
            q('INSERT INTO locations (account_name, location_name, title, address, phone, website, client_name) VALUES (?,?,?,?,?,?,?)', [
                'manual', 'manual/' . bin2hex(random_bytes(6)), $title, trim($_POST['address'] ?? '') ?: null,
                trim($_POST['phone'] ?? '') ?: null, trim($_POST['website'] ?? '') ?: null, trim($_POST['client_name'] ?? '') ?: null,
            ]);
            flash('Location added.');
        } elseif ($action === 'sync') {
            flash('Synced ' . gbp_sync_locations() . ' location(s).');
        } elseif ($action === 'save') {
            $status = in_array($_POST['crm_status'] ?? '', ['lead', 'active', 'paused', 'churned'], true) ? $_POST['crm_status'] : 'active';
            q('UPDATE locations SET client_name = ?, contact_email = ?, crm_status = ?, notes = ? WHERE id = ?', [
                trim($_POST['client_name'] ?? '') ?: null, trim($_POST['contact_email'] ?? '') ?: null,
                $status, trim($_POST['notes'] ?? '') ?: null, (int)($_POST['id'] ?? 0),
            ]);
            flash('Saved.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('locations.php' . (isset($_POST['id']) ? '?edit=' . (int)$_POST['id'] : ''));
}

$edit = isset($_GET['edit']) ? q('SELECT * FROM locations WHERE id = ?', [(int)$_GET['edit']])->fetch() : null;
$rows = q('SELECT l.*, (SELECT COUNT(*) FROM reviews r WHERE r.location_id = l.id AND r.reply_comment IS NULL) AS unanswered
           FROM locations l ORDER BY l.title')->fetchAll();
$g = google_connected();

page_header('Locations', 'locations');
?>
<h1>Locations &amp; clients</h1>
<div class="bar">
  <?php if (google_enabled()): ?>
  <a class="btn" href="google_connect.php"><?= $g ? 'Reconnect Google' : 'Connect Google' ?></a>
  <?php else: ?>
  <span class="muted">Manual mode: Google API not configured yet. Add credentials in app/config.php to enable sync.</span>
  <?php endif; ?>
  <?php if (google_enabled() && $g): ?>
  <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="sync"><button>Sync locations</button></form>
  <span class="muted">Connected<?= $g['connected_email'] ? ' as ' . e($g['connected_email']) : '' ?></span>
  <?php endif; ?>
</div>

<details class="card"><summary><strong>+ Add location manually</strong></summary>
<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <label>Business name<input name="title" required></label>
  <label>Client name<input name="client_name"></label>
  <label>Address<input name="address"></label>
  <label>Phone<input name="phone"></label>
  <label>Website<input name="website"></label>
  <button>Add</button></form></details>

<?php if ($edit): ?>
<form method="post" class="card">
  <h2><?= e($edit['title']) ?></h2>
  <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <label>Client name<input name="client_name" value="<?= e($edit['client_name']) ?>"></label>
  <label>Contact email<input type="email" name="contact_email" value="<?= e($edit['contact_email']) ?>"></label>
  <label>Status<select name="crm_status">
    <?php foreach (['lead', 'active', 'paused', 'churned'] as $s): ?>
      <option <?= $edit['crm_status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?></select></label>
  <label>Notes<textarea name="notes" rows="5"><?= e($edit['notes']) ?></textarea></label>
  <button>Save</button> <a href="locations.php">Cancel</a>
</form>
<?php endif; ?>

<table>
<tr><th>Location</th><th>Client</th><th>Status</th><th>Phone</th><th>Unanswered reviews</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><strong><?= e($r['title']) ?></strong><br><span class="muted"><?= e($r['address']) ?></span></td>
  <td><?= e($r['client_name']) ?><br><span class="muted"><?= e($r['contact_email']) ?></span></td>
  <td><span class="pill <?= e($r['crm_status']) ?>"><?= e($r['crm_status']) ?></span></td>
  <td><?= e($r['phone']) ?></td>
  <td><?= (int)$r['unanswered'] ?></td>
  <td><a href="locations.php?edit=<?= (int)$r['id'] ?>">Edit</a></td>
</tr>
<?php endforeach; if (!$rows): ?>
<tr><td colspan="6" class="muted">No locations yet. Add one manually above.</td></tr>
<?php endif; ?>
</table>
<?php page_footer();
