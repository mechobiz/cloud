<?php
// First-run page: creates the admin user. Disabled once any user exists.
require __DIR__ . '/app/bootstrap.php';
$err = null;
try {
    $has = (int)q('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $e) {
    exit('Database not ready. Import database/schema.sql first. (' . e($e->getMessage()) . ')');
}
if ($has) {
    exit('Setup already done. Delete setup.php from the server.');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 10) {
        $err = 'Enter a valid email and a password of at least 10 characters.';
    } else {
        q('INSERT INTO users (email, password_hash) VALUES (?, ?)', [$email, password_hash($pass, PASSWORD_DEFAULT)]);
        redirect('login.php');
    }
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup - GMB CRM</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth">
<form method="post" class="card"><h1>Create admin</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Email<input type="email" name="email" required></label>
<label>Password (10+ chars)<input type="password" name="password" required minlength="10"></label>
<button>Create</button></form></body></html>
