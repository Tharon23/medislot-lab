<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    $st = db()->prepare('SELECT id, email, role, full_name FROM users WHERE id = ? AND is_active = 1');
    $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

function require_role(string $role): void {
    require_login();
    if (current_user()['role'] !== $role) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

// Returns [ok, message]. INTENTIONAL (#15): distinct messages; (#13): md5 compare;
// (#9): session id NOT regenerated on success.
function attempt_login(string $email, string $password): array {
    $st = db()->prepare('SELECT id, password_hash, is_active FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if (!$u) return [false, 'No account found with that email address.'];
    if ($u['password_hash'] !== md5($password)) return [false, 'Incorrect password for this account.'];
    if (!$u['is_active']) return [false, 'This account is disabled.'];
    $_SESSION['uid'] = (int)$u['id'];
    return [true, ''];
}
