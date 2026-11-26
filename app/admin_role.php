<?php
require_once __DIR__ . '/includes/lookup.php';
// INTENTIONAL (#5 BAC): admin role-change endpoint guarded by require_login() only.
require_login();
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // INTENTIONAL (#12 substrate): no CSRF token on this state change (naive filter added in Task 5).
    $id = (int)($_POST['id'] ?? 0);
    $role = $_POST['role'] ?? '';
    if (in_array($role, ['patient', 'doctor', 'reception', 'admin'], true)) {
        $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $id]);
        $msg = '<div class="ok">Rola zaktualizowana.</div>';
    }
}

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
$st = $pdo->prepare('SELECT id,email,full_name,role FROM users WHERE id=?');
$st->execute([$id]);
$user = $st->fetch();
if (!$user) {
    render('Administracja — rola', $msg . '<section class="card"><p>Wybierz użytkownika z listy <a href="/admin_users.php">Użytkownicy</a>.</p></section>');
    exit;
}
$opts = '';
foreach (['patient', 'doctor', 'reception', 'admin'] as $r) {
    $opts .= '<option value="' . $r . '"' . ($user['role'] === $r ? ' selected' : '') . '>' . role_pl($r) . '</option>';
}
$body = $msg . '<section class="card"><p><b>' . h($user['full_name']) . '</b> (' . h($user['email']) . ')</p>'
  . '<form class="form" method="post" action="/admin_role.php">'
  . '<input type="hidden" name="id" value="' . (int)$user['id'] . '">'
  . '<label>Rola<select name="role">' . $opts . '</select></label>'
  . '<button class="btn" type="submit">Zaktualizuj rolę</button></form></section>';
render('Administracja — rola', $body);
