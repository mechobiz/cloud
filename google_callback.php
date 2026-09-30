<?php
require __DIR__ . '/app/bootstrap.php';
require_login();
try {
    $state = $_GET['state'] ?? '';
    if (!isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], (string)$state)) {
        throw new RuntimeException('Invalid OAuth state. Try connecting again.');
    }
    unset($_SESSION['oauth_state']);
    if (!empty($_GET['error'])) {
        throw new RuntimeException('Google returned: ' . $_GET['error']);
    }
    google_handle_callback((string)($_GET['code'] ?? ''));
    flash('Google account connected. Now click "Sync locations".');
} catch (Throwable $e) {
    flash($e->getMessage(), 'err');
}
redirect('locations.php');
