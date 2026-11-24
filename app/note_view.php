<?php
require_once __DIR__ . '/includes/lookup.php';
require_login();
$u = current_user();
$pdo = db();
$q = 'SELECT n.id, n.body_html, n.created_at, pu.full_name pn, du.full_name dn FROM notes n
      JOIN patients p ON p.id=n.patient_id JOIN users pu ON pu.id=p.user_id
      JOIN doctors d ON d.id=n.doctor_id JOIN users du ON du.id=d.user_id';

if (!empty($_GET['ref'])) {
    // INTENTIONAL (#3 substrate): no ownership check. INTENTIONAL (#7): body rendered raw, no h().
    $st = $pdo->prepare($q . ' WHERE n.id=?');
    $st->execute([ref_decode($_GET['ref'])]);
    $n = $st->fetch();
    if (!$n) { http_response_code(404); render('Note', '<p>Not found.</p>'); exit; }
    render('Note', '<section class="card"><p class="muted">' . h($n['pn']) . ' / ' . h($n['dn']) . ' / ' . h($n['created_at']) . '</p>'
        . '<div class="note-body">' . $n['body_html'] . '</div><p><a class="btn btn-ghost" href="/note_view.php">Back</a></p></section>');
    exit;
}
$args = [];
if ($u['role'] === 'patient') { $q .= ' WHERE p.user_id=?'; $args[] = $u['id']; }
elseif ($u['role'] === 'doctor') { $q .= ' WHERE d.user_id=?'; $args[] = $u['id']; }
$st = $pdo->prepare($q . ' ORDER BY n.id DESC');
$st->execute($args);
$o = '<section class="card"><table><thead><tr><th>Date</th><th>Patient</th><th>Doctor</th><th></th></tr></thead><tbody>';
foreach ($st->fetchAll() as $r) {
    $o .= '<tr><td>' . h($r['created_at']) . '</td><td>' . h($r['pn']) . '</td><td>' . h($r['dn'])
        . '</td><td><a href="/note_view.php?ref=' . h(ref_encode((int)$r['id'])) . '">View</a></td></tr>';
}
render('Notes', $o . '</tbody></table></section>');
