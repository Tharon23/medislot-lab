<?php
require_once __DIR__ . '/config.php';

// Minimal SMTP client -> Mailhog (no auth, no TLS).
function send_mail(string $to, string $subject, string $body): bool {
    $s = @fsockopen(MAIL_HOST, MAIL_PORT, $en, $es, 5);
    if (!$s) return false;
    $rd = function () use ($s) { $l = ''; while (($r = fgets($s, 512)) !== false) { $l .= $r; if (isset($r[3]) && $r[3] === ' ') break; } return $l; };
    $wr = function (string $c) use ($s, $rd) { fwrite($s, $c . "\r\n"); return $rd(); };
    $rd();
    $wr('HELO medislot.lab');
    $wr('MAIL FROM:<no-reply@medislot.lab>');
    $wr('RCPT TO:<' . $to . '>');
    $wr('DATA');
    $msg = "From: MediSlot <no-reply@medislot.lab>\r\nTo: $to\r\nSubject: $subject\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n"
         . str_replace("\n.", "\n..", $body) . "\r\n.";
    $r = $wr($msg);
    $wr('QUIT');
    fclose($s);
    return strncmp($r, '250', 3) === 0;
}
