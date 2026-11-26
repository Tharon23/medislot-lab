<?php
require_once __DIR__ . '/includes/lookup.php';
require_once __DIR__ . '/includes/minio.php';
require_login();
$u = current_user();
$pdo = db();
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $pid = $u['role'] === 'patient' ? patient_id_for((int)$u['id']) : (int)($_POST['patient_id'] ?? 0);
    $name = basename($_FILES['file']['name']);
    $type = $_FILES['file']['type'] ?: 'application/octet-stream';
    $bytes = file_get_contents($_FILES['file']['tmp_name']);
    // INTENTIONAL (#2): naive upload filter — extension blacklist + magic-byte sniff only.
    // Blocks bare `.php` and any content that does not begin with an image/PDF signature.
    // Bypass: name the file `.phtml` (not blacklisted) AND prepend a `GIF89a` magic prefix
    // (polyglot). Nginx routes `.phtml` to PHP-FPM, so the webshell executes from /uploads/.
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $sig = substr($bytes, 0, 8);
    $looksImage = strncmp($sig, 'GIF87a', 6) === 0 || strncmp($sig, 'GIF89a', 6) === 0
        || strncmp($sig, "\x89PNG\r\n\x1a\n", 8) === 0 || strncmp($sig, "\xFF\xD8\xFF", 3) === 0
        || strncmp($sig, '%PDF', 4) === 0;
    if (in_array($ext, ['php', 'php5', 'phps', 'phar'], true) || !$looksImage) {
        $msg = '<div class="alert">Odrzucono: dozwolone są tylko załączniki graficzne lub PDF.</div>';
    } else {
        // INTENTIONAL (#11): sequential, guessable keys derived from the row id.
        $pdo->prepare('INSERT INTO attachments (patient_id,object_key,filename,content_type,uploaded_by) VALUES (?,?,?,?,?)')
            ->execute([$pid, 'pending', $name, $type, $u['id']]);
        $id = (int)$pdo->lastInsertId();
        $key = sprintf('att-%06d-%s', $id, $name);
        minio_ensure_bucket();
        if (minio_put_object($key, $bytes, $type)) {
            $pdo->prepare('UPDATE attachments SET object_key=? WHERE id=?')->execute([$key, $id]);
            // Staging copy in web-reachable uploads/ (sink for later tasks).
            @file_put_contents(__DIR__ . '/uploads/' . $key, $bytes);
            $msg = '<div class="ok">Przesłano.</div>';
        } else {
            $pdo->prepare('DELETE FROM attachments WHERE id=?')->execute([$id]);
            $msg = '<div class="alert">Błąd zapisu.</div>';
        }
    }
}
$body = $msg . '<section class="card"><form class="form" method="post" action="/upload.php" enctype="multipart/form-data">'
  . ($u['role'] !== 'patient' ? '<label>Pacjent<select name="patient_id">' . options(all_patients()) . '</select></label>' : '')
  . '<label>Plik<input type="file" name="file" required></label><button class="btn" type="submit">Prześlij</button></form></section>';

$sql = 'SELECT a.id, a.object_key, a.filename, pu.full_name pn FROM attachments a JOIN patients p ON p.id=a.patient_id JOIN users pu ON pu.id=p.user_id';
$args = [];
if ($u['role'] === 'patient') { $sql .= ' WHERE p.user_id=?'; $args[] = $u['id']; }
$st = $pdo->prepare($sql . ' ORDER BY a.id DESC');
$st->execute($args);
$body .= '<section class="card"><h2>Załączniki</h2><table><thead><tr><th>Plik</th><th>Pacjent</th></tr></thead><tbody>';
foreach ($st->fetchAll() as $r) {
    $body .= '<tr><td><a href="/download.php?file=' . h(rawurlencode($r['object_key'])) . '">' . h($r['filename']) . '</a></td><td>' . h($r['pn']) . '</td></tr>';
}
render('Załączniki', $body . '</tbody></table></section>');
