<?php
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/mailer.php';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = db()->prepare('SELECT id FROM users WHERE email=?');
    $st->execute([trim($_POST['email'] ?? '')]);
    $uid = $st->fetchColumn();
    if ($uid) {
        $token = md5($uid . RESET_SALT);   // INTENTIONAL (#8): predictable, no expiry, reusable
        db()->prepare('INSERT INTO password_resets (user_id,token) VALUES (?,?)')->execute([$uid, $token]);
        send_mail($_POST['email'], 'MediSlot password reset',
            "Reset your password:\nhttp://localhost:8080/reset.php?u=$uid&token=$token\n");
    }
    $msg = '<div class="ok">If the account exists, a reset link was sent.</div>';
}
render('Reset password', '<div class="auth-card"><div class="brand big">+ ' . APP_NAME . '</div>' . $msg
  . '<form method="post" action="/reset_request.php"><label>Email<input type="text" name="email" autofocus></label>'
  . '<button class="btn" type="submit">Send reset link</button></form><p><a href="/login.php">Back to sign in</a></p></div>');
