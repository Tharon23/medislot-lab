<?php
require_once __DIR__ . '/includes/layout.php';

if (current_user()) { header('Location: /dashboard.php'); exit; }

$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    [$ok, $msg] = attempt_login($email, $_POST['password'] ?? '');
    if ($ok) { header('Location: /dashboard.php'); exit; }
    $error = $msg;
}

$body = '<div class="auth-card"><div class="brand big">+ ' . APP_NAME . '</div>'
      . '<p class="muted">NovaMed clinic portal. Sign in to continue.</p>'
      . ($error ? '<div class="alert">' . h($error) . '</div>' : '')
      . '<form method="post" action="/login.php">'
      . '<label>Email<input type="text" name="email" value="' . h($email) . '" autofocus></label>'
      . '<label>Password<input type="password" name="password"></label>'
      . '<button class="btn" type="submit">Sign in</button></form></div>';
render('Sign in', $body);
