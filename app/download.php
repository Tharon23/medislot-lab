<?php
require_once __DIR__ . '/includes/lookup.php';
require_once __DIR__ . '/includes/minio.php';
require_login();
// INTENTIONAL (#6): ?file used as-is, no sanitising. Reads the staging dir first, then MinIO.
// Also accepts ?ref=<base64url attachment id> (no ownership check, #3).
$file = $_GET['file'] ?? '';
if (!empty($_GET['ref'])) {
    $st = db()->prepare('SELECT object_key FROM attachments WHERE id=?');
    $st->execute([ref_decode($_GET['ref'])]);
    $file = (string)$st->fetchColumn();
}
$path = __DIR__ . '/uploads/' . $file;
$data = is_file($path) ? file_get_contents($path) : minio_get_object($file);
if ($data === null || $data === false) { http_response_code(404); echo 'Nie znaleziono'; exit; }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . strlen($data));
echo $data;
