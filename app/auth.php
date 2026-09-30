<?php
declare(strict_types=1);

function current_user(): ?array
{
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = q('SELECT id, email FROM users WHERE id = ?', [$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function attempt_login(string $email, string $password): bool
{
    $st = q('SELECT id, password_hash FROM users WHERE email = ?', [strtolower(trim($email))]);
    $row = $st->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$row['id'];
    return true;
}
