<?php
// Seed synthetic data. Idempotent: wipes and reloads. Run inside the php container:
//   docker compose exec php php /srv/db/seed.php
$pdo = null;
for ($i = 0; $i < 30 && !$pdo; $i++) {
    try {
        $pdo = new PDO('mysql:host=mysql;dbname=' . getenv('MYSQL_DATABASE') . ';charset=utf8mb4',
            getenv('MYSQL_USER'), getenv('MYSQL_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) { sleep(1); }
}
if (!$pdo) { fwrite(STDERR, "cannot connect to mysql\n"); exit(1); }

$pdo->exec(file_get_contents(__DIR__ . '/init.sql'));   // ensure schema (CREATE IF NOT EXISTS)
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['password_resets','attachments','notes','appointments','assignments','doctors','patients','users'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');

function user(PDO $p, string $email, string $pw, string $role, string $name): int {
    // Finding #13: unsalted MD5
    $p->prepare('INSERT INTO users (email,password_hash,role,full_name) VALUES (?,?,?,?)')
      ->execute([$email, md5($pw), $role, $name]);
    return (int)$p->lastInsertId();
}

user($pdo, 'admin@novamed.lab', 'Admin123!', 'admin', 'Adam Admin');
user($pdo, 'recepcja@novamed.lab', 'Reception123!', 'reception', 'Renata Recepcja');

$docs = [];
foreach ([
    ['lekarz1@novamed.lab', 'Doctor123!', 'Dr Lena Lekarz', 'Medycyna rodzinna'],
    ['lekarz2@novamed.lab', 'Doctor456!', 'Dr Marek Kardiol', 'Kardiologia'],
] as [$e, $pw, $n, $sp]) {
    $uid = user($pdo, $e, $pw, 'doctor', $n);
    $pdo->prepare('INSERT INTO doctors (user_id,specialty) VALUES (?,?)')->execute([$uid, $sp]);
    $docs[] = (int)$pdo->lastInsertId();
}

$people = [
    ['pacjent1@novamed.lab', 'Patient123!', 'Piotr Pacjent'],
    ['pacjent2@novamed.lab', 'Patient234!', 'Anna Kowalska'],
    ['pacjent3@novamed.lab', 'Patient345!', 'Jan Nowak'],
    ['pacjent4@novamed.lab', 'Patient456!', 'Ewa Wisniewska'],
    ['pacjent5@novamed.lab', 'Patient567!', 'Tomasz Wojcik'],
    ['pacjent6@novamed.lab', 'Patient678!', 'Maria Kaminska'],
    ['pacjent7@novamed.lab', 'Patient789!', 'Krzysztof Lewandowski'],
    ['pacjent8@novamed.lab', 'Patient890!', 'Zofia Zielinska'],
];
$pats = [];
foreach ($people as $i => [$e, $pw, $n]) {
    $uid = user($pdo, $e, $pw, 'patient', $n);
    $dob = sprintf('%d-%02d-%02d', 1960 + $i * 4, 1 + $i, 10 + $i);
    $pdo->prepare('INSERT INTO patients (user_id,dob,phone,address,pesel_synth) VALUES (?,?,?,?,?)')
        ->execute([$uid, $dob, sprintf('+48 500 000 %03d', $i + 1),
                   'ul. Testowa ' . ($i + 1) . ', Testowo', sprintf('9900000%04d', $i + 1)]);
    $pats[] = (int)$pdo->lastInsertId();
}

// Assignments: doctor 1 -> patients 1-4, doctor 2 -> patients 5-8 (+ patient 1)
$as = $pdo->prepare('INSERT INTO assignments (doctor_id,patient_id) VALUES (?,?)');
foreach ($pats as $i => $pid) { $as->execute([$docs[$i < 4 ? 0 : 1], $pid]); }
$as->execute([$docs[1], $pats[0]]);

$ap = $pdo->prepare('INSERT INTO appointments (patient_id,doctor_id,starts_at,status,reason) VALUES (?,?,?,?,?)');
$ap->execute([$pats[0], $docs[0], date('Y-m-d 09:00:00', strtotime('+2 days')), 'scheduled', 'Kontrola roczna']);
$ap->execute([$pats[0], $docs[1], date('Y-m-d 11:30:00', strtotime('+9 days')), 'scheduled', 'Kontrola EKG']);
$ap->execute([$pats[0], $docs[0], date('Y-m-d 10:00:00', strtotime('-20 days')), 'completed', 'Objawy grypy']);
$ap->execute([$pats[1], $docs[0], date('Y-m-d 10:30:00', strtotime('+1 day')), 'scheduled', 'Ból pleców']);
$ap->execute([$pats[2], $docs[0], date('Y-m-d 13:00:00', strtotime('+3 days')), 'scheduled', 'Wyniki badań krwi']);
$ap->execute([$pats[3], $docs[0], date('Y-m-d 14:00:00', strtotime('+3 days')), 'cancelled', 'Przedłużenie recepty']);
$ap->execute([$pats[4], $docs[1], date('Y-m-d 08:30:00', strtotime('+2 days')), 'scheduled', 'Konsultacja bólu w klatce piersiowej']);
$ap->execute([$pats[5], $docs[1], date('Y-m-d 12:00:00', strtotime('+4 days')), 'scheduled', 'Kontrola ciśnienia']);

$nt = $pdo->prepare('INSERT INTO notes (patient_id,doctor_id,body_html) VALUES (?,?,?)');
$nt->execute([$pats[0], $docs[0], '<p>Pacjent z łagodnymi objawami grypy. Zalecono odpoczynek i nawadnianie.</p>']);
$nt->execute([$pats[0], $docs[1], '<p>EKG w normie. Kontrola za 6 miesięcy.</p>']);
$nt->execute([$pats[1], $docs[0], '<p>Ból dolnego odcinka pleców, <b>bez objawów alarmowych</b>. Skierowanie na fizjoterapię.</p>']);
$nt->execute([$pats[4], $docs[1], '<p>Podwyższone ciśnienie. Rozpocząć dzienniczek pomiarów.</p>']);

$at = $pdo->prepare('INSERT INTO attachments (patient_id,object_key,filename,content_type,uploaded_by) VALUES (?,?,?,?,?)');
$at->execute([$pats[0], 'pacjent1/wynik-krwi-2026.pdf', 'wynik-krwi-2026.pdf', 'application/pdf', 1]);
$at->execute([$pats[1], 'pacjent2/rtg-ledzwiowy.png', 'rtg-ledzwiowy.png', 'image/png', 1]);

// Seed the actual MinIO objects so downloads resolve and #11 (public bucket, guessable keys)
// is demonstrable on seeded data. Synthetic bytes only. Runs in the php container.
if (is_file('/var/www/html/includes/minio.php')) {
    require_once '/var/www/html/includes/minio.php';
    minio_ensure_bucket();
    minio_put_object('pacjent1/wynik-krwi-2026.pdf',
        "%PDF-1.4\n% Syntetyczny wynik badania krwi. Dane testowe, nie PHI.\n", 'application/pdf');
    minio_put_object('pacjent2/rtg-ledzwiowy.png',
        "\x89PNG\r\n\x1a\n" . str_repeat("\0", 64), 'image/png');
}

echo "Seed OK: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . " users, "
   . count($pats) . " patients, " . count($docs) . " doctors\n";
