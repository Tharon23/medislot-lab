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
    if (!$rows) $o .= '<tr><td colspan="' . count($head) . '" class="muted">Brak danych.</td></tr>';
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
        $rows = array_map(fn($r) => [$r['starts_at'], $r['full_name'], $r['specialty'], status_pl($r['status']), $r['reason']], $q->fetchAll());
        $body = '<p class="lead">Witaj ponownie, ' . h($u['full_name']) . '.</p>'
              . card_box('Moje wizyty', data_table(['Termin', 'Lekarz', 'Specjalizacja', 'Status', 'Powód'], $rows));
        break;
    case 'doctor':
        $q = $pdo->prepare('SELECT pu.full_name, p.phone, p.dob FROM assignments s
            JOIN doctors d ON d.id=s.doctor_id JOIN patients p ON p.id=s.patient_id JOIN users pu ON pu.id=p.user_id
            WHERE d.user_id=? ORDER BY pu.full_name');
        $q->execute([$u['id']]);
        $rows = array_map(fn($r) => [$r['full_name'], $r['dob'], $r['phone']], $q->fetchAll());
        $body = '<div class="stats">' . stat_box('Przypisani pacjenci', count($rows)) . '</div>'
              . card_box('Przypisani pacjenci', data_table(['Imię i nazwisko', 'Data urodzenia', 'Telefon'], $rows));
        break;
    case 'reception':
        $rows = array_map(fn($r) => [$r['starts_at'], $r['pn'], $r['dn'], status_pl($r['status'])], $pdo->query(
            'SELECT a.starts_at, pu.full_name pn, du.full_name dn, a.status FROM appointments a
             JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
             JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id ORDER BY a.starts_at')->fetchAll());
        $body = '<div class="stats">' . stat_box('Wizyty', count($rows)) . '</div>'
              . card_box('Grafik przychodni', data_table(['Termin', 'Pacjent', 'Lekarz', 'Status'], $rows));
        break;
    case 'admin':
        $rows = array_map(fn($r) => [$r['id'], $r['email'], $r['full_name'], role_pl($r['role']), $r['is_active'] ? 'aktywne' : 'zablokowane'],
            $pdo->query('SELECT id,email,full_name,role,is_active FROM users ORDER BY id')->fetchAll());
        $all = $pdo->query('SELECT role FROM users')->fetchAll();
        $cntRole = fn($role) => count(array_filter($all, fn($x) => $x['role'] === $role));
        $body = '<div class="stats">' . stat_box('Użytkownicy', count($rows)) . stat_box('Pacjenci', $cntRole('patient')) . stat_box('Lekarze', $cntRole('doctor')) . '</div>'
              . card_box('Przegląd użytkowników', data_table(['ID', 'E-mail', 'Imię i nazwisko', 'Rola', 'Stan'], $rows));
        break;
    default:
        $body = '<p>Nieznana rola.</p>';
}
render(role_pl($u['role']) . ' — pulpit', $body);
