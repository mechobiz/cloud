<?php
require __DIR__ . '/app/bootstrap.php';
require_login();
$_SESSION['oauth_state'] = bin2hex(random_bytes(16));
redirect(google_auth_url($_SESSION['oauth_state']));
