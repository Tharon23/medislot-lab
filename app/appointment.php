<?php
require_once __DIR__ . '/includes/lookup.php';
require_login();
$u = current_user();
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'cancel') {
        // INTENTIONAL (#3): ref decoded, no ownership check; no CSRF token.
        $id = ref_decode($_POST['ref'] ?? '');
        $pdo->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$id]);
        header('Location: /appointment_list.php'); exit;
    }
    if ($u['role'] === 'patient' || $u['role'] === 'reception') {
        $pid = $u['role'] === 'patient' ? patient_id_for((int)$u['id']) : (int)($_POST['patient_id'] ?? 0);
        $doctorId = (int)($_POST['doctor_id'] ?? 0);
        $when = trim((string)($_POST['starts_at'] ?? ''));
        if ($pid && $doctorId && $when !== '') {
            $pdo->prepare('INSERT INTO appointments (patient_id,doctor_id,starts_at,reason) VALUES (?,?,?,?)')
                ->execute([$pid, $doctorId, str_replace('T', ' ', $when) . ':00', $_POST['reason'] ?? '']);
            $msg = '<div class="ok">Wizyta umówiona.</div>';
        } else {
            $msg = '<div class="alert">Nie udało się umówić: brak pacjenta, lekarza lub terminu.</div>';
        }
    }
}

// Detail view by ref (IDOR #3 substrate).
if (!empty($_GET['ref'])) {
    $st = $pdo->prepare('SELECT a.*, pu.full_name pn, du.full_name dn, d.specialty FROM appointments a
        JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id
        JOIN doctors d ON d.id=a.doctor_id JOIN users du ON du.id=d.user_id WHERE a.id=?');
    $st->execute([ref_decode($_GET['ref'])]);
    $a = $st->fetch();
    if (!$a) { http_response_code(404); render('Appointment', '<p>Not found.</p>'); exit; }
    $body = '<section class="card"><p><b>Pacjent:</b> ' . h($a['pn']) . '</p><p><b>Lekarz:</b> ' . h($a['dn']) . ' (' . h($a['specialty']) . ')</p>'
          . '<p><b>Termin:</b> ' . h($a['starts_at']) . '</p><p><b>Status:</b> ' . h(status_pl($a['status'])) . '</p><p><b>Powód:</b> ' . h($a['reason']) . '</p>'
          . '<a class="btn btn-ghost" href="/appointment_list.php">Powrót</a></section>';
    render('Wizyta', $body); exit;
}

$docs = $pdo->query('SELECT d.id, CONCAT(u.full_name, " - ", d.specialty) label FROM doctors d JOIN users u ON u.id=d.user_id')->fetchAll();
$body = $msg;
if ($u['role'] === 'patient' || $u['role'] === 'reception') {
    $body .= '<section class="card"><form class="form" method="post" action="/appointment.php">'
      . ($u['role'] === 'reception' ? '<label>Pacjent<select name="patient_id">' . options(all_patients()) . '</select></label>' : '')
      . '<label>Lekarz<select name="doctor_id">' . options($docs, 'label') . '</select></label>'
      . '<label>Data i godzina<input type="datetime-local" name="starts_at" required></label>'
      . '<label>Powód<input type="text" name="reason"></label>'
      . '<button class="btn" type="submit">Umów</button></form></section>';
} else {
    $body .= '<p>Tylko pacjenci i recepcja mogą umawiać wizyty.</p>';
}
render('Umów wizytę', $body);
