<?php
require_once __DIR__ . '/includes/lookup.php';
require_login();
$pdo = db();
$rows = [];
$err = '';
$min = $_GET['min_id'] ?? '';

if ($min !== '') {
    // INTENTIONAL (#8/#10): numeric param concatenated straight into SQL. Naive filter only:
    //   - escape single quotes
    //   - strip whitespace
    //   - strip the UNION keyword (single pass, case-insensitive)
    // Blocks the obvious `' OR 1=1` and a plain `UNION SELECT`. Intended bypass (numeric context,
    // no quotes): `/**/` inline comments for spaces + keyword-doubling `UNIUNIONON` -> `UNION`, e.g.
    //   0/**/UNIUNIONON/**/SELECT/**/email,password_hash,role,full_name/**/FROM/**/users
    // Column count (4) must be discovered; password_hash then surfaces in the "Name" column.
    $f = str_replace("'", "\\'", $min);
    $f = preg_replace('/\s+/', '', $f);
    $f = str_ireplace('UNION', '', $f);
    $sql = "SELECT id, email, full_name, role FROM users WHERE id > $f ORDER BY id";
    try {
        $rows = $pdo->query($sql)->fetchAll();
    } catch (Throwable $e) {
        $err = $e->getMessage();   // verbose errors (#16) aid injection
    }
}

$body = '<section class="card"><form class="form" method="get" action="/search.php">'
  . '<label>Minimum patient id<input type="text" name="min_id" value="' . h((string)$min) . '"></label>'
  . '<button class="btn" type="submit">Search</button></form></section>';
if ($err) $body .= '<div class="alert">' . h($err) . '</div>';
if ($rows) {
    $body .= '<section class="card"><table><thead><tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $body .= '<tr><td>' . h((string)$r['id']) . '</td><td>' . h((string)$r['email'])
            . '</td><td>' . h((string)$r['full_name']) . '</td><td>' . h((string)$r['role']) . '</td></tr>';
    }
    $body .= '</tbody></table></section>';
}
render('Patient search', $body);
