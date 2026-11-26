<?php
require_once __DIR__ . '/includes/lookup.php';
require_role('doctor');
$u = current_user();
$pdo = db();
$did = doctor_id_for((int)$u['id']);

// INTENTIONAL (#7): naive single-pass XSS blacklist. Removes the substring "script" once
// and strips whitespace-delimited on<handler>= attributes. Neutralises the obvious
// <script>alert(1)</script> and <img src=x onerror=...>. Defeated by:
//   - keyword re-forming after one removal: <scrscriptipt>...</scrscriptipt> -> <script>...</script>
//   - an event handler not preceded by whitespace: <svg/onload=alert(1)> (the "/" dodges the filter)
// The result is stored and later rendered raw by note_view.php, firing in the viewer's session.
function note_filter(string $s): string {
    $s = str_ireplace('script', '', $s);
    $s = preg_replace('/\son\w+\s*=/i', ' ', $s);
    return $s;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO notes (patient_id,doctor_id,body_html) VALUES (?,?,?)')
        ->execute([(int)$_POST['patient_id'], $did, note_filter((string)($_POST['body_html'] ?? ''))]);
    header('Location: /note_view.php?ref=' . ref_encode((int)$pdo->lastInsertId())); exit;
}
$st = $pdo->prepare('SELECT p.id, pu.full_name FROM assignments s JOIN patients p ON p.id=s.patient_id JOIN users pu ON pu.id=p.user_id WHERE s.doctor_id=?');
$st->execute([$did]);
$body = '<section class="card"><form class="form" method="post" action="/note.php">'
  . '<label>Pacjent<select name="patient_id">' . options($st->fetchAll()) . '</select></label>'
  . '<label>Notatka (dozwolony HTML)<textarea name="body_html"></textarea></label>'
  . '<button class="btn" type="submit">Zapisz notatkę</button></form></section>';
render('Dodaj notatkę', $body);
