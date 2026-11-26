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
      . '<p class="muted">Portal przychodni NovaMed. Zaloguj się, aby kontynuować.</p>'
      . ($error ? '<div class="alert">' . h($error) . '</div>' : '')
      . '<form method="post" action="/login.php">'
      . '<label>E-mail<input type="text" name="email" value="' . h($email) . '" autofocus></label>'
      . '<label>Hasło<input type="password" name="password"></label>'
      . '<button class="btn" type="submit">Zaloguj</button></form><p><a href="/reset_request.php">Nie pamiętasz hasła?</a></p></div>';
render('Logowanie', $body);
