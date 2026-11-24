<?php
require_once __DIR__ . '/includes/layout.php';
require_login();
$u = current_user();
$pdo = db();

function data_table(array $head, array $rows): string {
    $o = '<table><thead><tr>';
    foreach ($head as $c) $o .= '<th>' . h($c) . '</th>';
    $o .= '</tr></thead><tbody>';
    foreach ($rows as $r) {
        $o .= '<tr>';
        foreach ($r as $c) $o .= '<td>' . h((string)$c) . '</td>';
        $o .= '</tr>';
    }
    if (!$rows) $o .= '<tr><td colspan="' . count($head) . '" class="muted">Nothing here yet.</td></tr>';
    return $o . '</tbody></table>';
}
function card_box(string $title, string $inner): string { return '<section class="card"><h2>' . h($title) . '</h2>' . $inner . '</section>'; }
function stat_box(string $label, $n): string { return '<div class="stat"><div class="n">' . h((string)$n) . '</div><div class="l">' . h($label) . '</div></div>'; }

switch ($u['role']) {
    case 'patient':
        $q = $pdo->prepare('SELECT a.starts_at, d.specialty, du.full_name, a.status, a.reason FROM appointments a
            JOIN patients p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id
            WHERE p.user_id=? ORDER BY a.starts_at');
        $q->execute([$u['id']]);
        $rows = array_map(fn($r) => [$r['starts_at'], $r['full_name'], $r['specialty'], $r['status'], $r['reason']], $q->fetchAll());
        $body = '<p class="lead">Welcome back, ' . h($u['full_name']) . '.</p>'
              . card_box('My appointments', data_table(['When', 'Doctor', 'Specialty', 'Status', 'Reason'], $rows));
        break;
    case 'doctor':
        $q = $pdo->prepare('SELECT pu.full_name, p.phone, p.dob FROM assignments s
            JOIN doctors d ON d.id=s.doctor_id JOIN patients p ON p.id=s.patient_id JOIN users pu ON pu.id=p.user_id
            WHERE d.user_id=? ORDER BY pu.full_name');
        $q->execute([$u['id']]);
        $rows = array_map(fn($r) => [$r['full_name'], $r['dob'], $r['phone']], $q->fetchAll());
        $body = '<div class="stats">' . stat_box('Assigned patients', count($rows)) . '</div>'
              . card_box('Assigned patients', data_table(['Name', 'Date of birth', 'Phone'], $rows));
        break;
    case 'reception':
        $rows = array_map(fn($r) => [$r['starts_at'], $r['pn'], $r['dn'], $r['status']], $pdo->query(
            'SELECT a.starts_at, pu.full_name pn, du.full_name dn, a.status FROM appointments a
             JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
             JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id ORDER BY a.starts_at')->fetchAll());
        $body = '<div class="stats">' . stat_box('Appointments', count($rows)) . '</div>'
              . card_box('Clinic schedule', data_table(['When', 'Patient', 'Doctor', 'Status'], $rows));
        break;
    case 'admin':
        $rows = array_map(fn($r) => [$r['id'], $r['email'], $r['full_name'], $r['role'], $r['is_active'] ? 'active' : 'disabled'],
            $pdo->query('SELECT id,email,full_name,role,is_active FROM users ORDER BY id')->fetchAll());
        $cnt = fn($r) => count(array_filter($rows, fn($x) => $x[3] === $r));
        $body = '<div class="stats">' . stat_box('Users', count($rows)) . stat_box('Patients', $cnt('patient')) . stat_box('Doctors', $cnt('doctor')) . '</div>'
              . card_box('Users overview', data_table(['ID', 'Email', 'Name', 'Role', 'State'], $rows));
        break;
    default:
        $body = '<p>Unknown role.</p>';
}
render(ucfirst($u['role']) . ' dashboard', $body);
