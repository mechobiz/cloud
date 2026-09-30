<?php
require __DIR__ . '/app/bootstrap.php';
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (attempt_login($_POST['email'] ?? '', $_POST['password'] ?? '')) {
        redirect('dashboard.php');
    }
    $err = 'Wrong email or password.';
    usleep(500000);
}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Log in - GMB CRM</title><link rel="stylesheet" href="assets/style.css"></head><body class="auth">
<form method="post" class="card"><h1>GMB CRM</h1>
<?php if ($err): ?><div class="flash err"><?= e($err) ?></div><?php endif; ?>
<?= csrf_field() ?>
<label>Email<input type="email" name="email" required autofocus></label>
<label>Password<input type="password" name="password" required></label>
<button>Log in</button></form></body></html>
