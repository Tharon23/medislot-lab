<?php
require_once __DIR__ . '/includes/lookup.php';
require_login();
$u = current_user();
$pdo = db();
$sql = 'SELECT a.id, a.starts_at, a.status, a.reason, pu.full_name pn, du.full_name dn FROM appointments a
        JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
        JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id';
$args = [];
if ($u['role'] === 'patient') { $sql .= ' WHERE p.user_id=?'; $args[] = $u['id']; }
elseif ($u['role'] === 'doctor') { $sql .= ' WHERE d.user_id=?'; $args[] = $u['id']; }
$st = $pdo->prepare($sql . ' ORDER BY a.starts_at');
$st->execute($args);
$o = '<section class="card"><table><thead><tr><th>When</th><th>Patient</th><th>Doctor</th><th>Status</th><th>Reason</th><th></th></tr></thead><tbody>';
foreach ($st->fetchAll() as $r) {
    $ref = ref_encode((int)$r['id']);
    $o .= '<tr><td><a href="/appointment.php?ref=' . h($ref) . '">' . h($r['starts_at']) . '</a></td><td>' . h($r['pn']) . '</td><td>' . h($r['dn'])
        . '</td><td>' . h($r['status']) . '</td><td>' . h($r['reason']) . '</td><td>';
    if ($r['status'] === 'scheduled') {
        $o .= '<form class="inline" method="post" action="/appointment.php"><input type="hidden" name="action" value="cancel">'
            . '<input type="hidden" name="ref" value="' . h($ref) . '"><button class="btn btn-ghost" type="submit">Cancel</button></form>';
    }
    $o .= '</td></tr>';
}
render('Appointments', $o . '</tbody></table></section>');
