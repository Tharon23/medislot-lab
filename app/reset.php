<?php
require_once __DIR__ . '/includes/layout.php';
$uid = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
if (!$uid || !hash_equals(md5($uid . RESET_SALT), $token)) {
    render('Reset hasła', '<div class="auth-card"><div class="alert">Nieprawidłowy link resetu.</div></div>');
    exit;
}
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['password'] ?? '') !== '') {
    // Token is not consumed: reusable (#8).
    db()->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([md5($_POST['password']), $uid]);
    $msg = '<div class="ok">Hasło zmienione. <a href="/login.php">Zaloguj się</a></div>';
}
render('Reset hasła', '<div class="auth-card"><div class="brand big">+ ' . APP_NAME . '</div>' . $msg
  . '<form method="post" action="/reset.php"><input type="hidden" name="u" value="' . $uid . '"><input type="hidden" name="token" value="' . h($token) . '">'
  . '<label>Nowe hasło<input type="password" name="password"></label><button class="btn" type="submit">Ustaw hasło</button></form></div>');
