<?php
require_once __DIR__ . '/includes/lookup.php';
// INTENTIONAL (#5 BAC): admin page guarded by require_login() only — no require_role('admin').
// Any logged-in user (patient included) can list every account.
require_login();
$pdo = db();
$rows = $pdo->query('SELECT id,email,full_name,role,is_active FROM users ORDER BY id')->fetchAll();
$o = '<section class="card"><h2>Użytkownicy</h2><table><thead><tr>'
   . '<th>ID</th><th>E-mail</th><th>Imię i nazwisko</th><th>Rola</th><th>Stan</th><th></th></tr></thead><tbody>';
foreach ($rows as $r) {
    $o .= '<tr><td>' . h((string)$r['id']) . '</td><td>' . h($r['email']) . '</td><td>' . h($r['full_name'])
        . '</td><td>' . h(role_pl($r['role'])) . '</td><td>' . ($r['is_active'] ? 'aktywne' : 'zablokowane')
        . '</td><td><a href="/admin_role.php?id=' . (int)$r['id'] . '">Zmień rolę</a></td></tr>';
}
render('Administracja — użytkownicy', $o . '</tbody></table></section>');
