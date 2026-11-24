<?php
require_once __DIR__ . '/includes/lookup.php';
require_role('doctor');
$u = current_user();
$pdo = db();
$did = doctor_id_for((int)$u['id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // INTENTIONAL (#7): body stored raw as submitted (naive filter added in Task 5).
    $pdo->prepare('INSERT INTO notes (patient_id,doctor_id,body_html) VALUES (?,?,?)')
        ->execute([(int)$_POST['patient_id'], $did, $_POST['body_html'] ?? '']);
    header('Location: /note_view.php?ref=' . ref_encode((int)$pdo->lastInsertId())); exit;
}
$st = $pdo->prepare('SELECT p.id, pu.full_name FROM assignments s JOIN patients p ON p.id=s.patient_id JOIN users pu ON pu.id=p.user_id WHERE s.doctor_id=?');
$st->execute([$did]);
$body = '<section class="card"><form class="form" method="post" action="/note.php">'
  . '<label>Patient<select name="patient_id">' . options($st->fetchAll()) . '</select></label>'
  . '<label>Note (HTML allowed)<textarea name="body_html"></textarea></label>'
  . '<button class="btn" type="submit">Save note</button></form></section>';
render('Write note', $body);
