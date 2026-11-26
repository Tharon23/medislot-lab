<?php
require_once __DIR__ . '/includes/lookup.php';
require_login();
$u = current_user();
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update own contact details.
    $pdo->prepare('UPDATE patients SET phone=?, address=? WHERE user_id=?')
        ->execute([$_POST['phone'] ?? '', $_POST['address'] ?? '', $u['id']]);
    // INTENTIONAL (#4): mass-assignment. The form below has no role field, but any posted
    // numeric `role_id` is trusted and written straight to users.role — no re-auth, no check.
    // Bypass: add `role_id=4` manually (4 = admin). The string "admin" casts to int 0 and is ignored,
    // so the attacker must discover the numeric admin id by enumeration.
    if (isset($_POST['role_id'])) {
        $roles = [1 => 'patient', 2 => 'doctor', 3 => 'reception', 4 => 'admin'];
        $rid = (int)$_POST['role_id'];
        if (isset($roles[$rid])) {
            $pdo->prepare('UPDATE users SET role=? WHERE id=?')->execute([$roles[$rid], $u['id']]);
        }
    }
    header('Location: /profile_update.php?saved=1');
    exit;
}

$st = $pdo->prepare('SELECT phone, address FROM patients WHERE user_id=?');
$st->execute([$u['id']]);
$p = $st->fetch() ?: ['phone' => '', 'address' => ''];
if (!empty($_GET['saved'])) $msg = '<div class="ok">Profile saved.</div>';
$body = $msg . '<section class="card"><form class="form" method="post" action="/profile_update.php">'
  . '<label>Full name<input type="text" value="' . h($u['full_name']) . '" disabled></label>'
  . '<label>Phone<input type="text" name="phone" value="' . h((string)$p['phone']) . '"></label>'
  . '<label>Address<input type="text" name="address" value="' . h((string)$p['address']) . '"></label>'
  . '<button class="btn" type="submit">Save</button></form></section>';
render('My profile', $body);
