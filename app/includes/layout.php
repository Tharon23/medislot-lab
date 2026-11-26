<?php
require_once __DIR__ . '/auth.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Display-only Polish labels; the stored role/status enum values stay in English (code identifiers).
function role_pl(string $r): string {
    return ['patient' => 'Pacjent', 'doctor' => 'Lekarz', 'reception' => 'Recepcja', 'admin' => 'Administrator'][$r] ?? $r;
}
function status_pl(string $s): string {
    return ['scheduled' => 'zaplanowana', 'completed' => 'zakończona', 'cancelled' => 'anulowana'][$s] ?? $s;
}

function nav_items(string $role): array {
    $items = ['Pulpit' => '/dashboard.php'];
    switch ($role) {
        case 'patient':   $items += ['Moje wizyty' => '/appointment_list.php', 'Umów wizytę' => '/appointment.php', 'Moje notatki' => '/note_view.php', 'Załączniki' => '/upload.php', 'Mój profil' => '/profile_update.php']; break;
        case 'doctor':    $items += ['Wizyty' => '/appointment_list.php', 'Dodaj notatkę' => '/note.php', 'Notatki' => '/note_view.php', 'Załączniki' => '/upload.php']; break;
        case 'reception': $items += ['Grafik' => '/appointment_list.php', 'Umów wizytę' => '/appointment.php', 'Załączniki' => '/upload.php', 'Szukaj pacjenta' => '/search.php']; break;
        case 'admin':     $items += ['Użytkownicy' => '/admin_users.php', 'Role' => '/admin_role.php', 'Szukaj pacjenta' => '/search.php']; break;
    }
    return $items;
}

// Wraps $bodyHtml in the app shell. $bodyHtml is trusted HTML built by the caller.
function render(string $title, string $bodyHtml): void {
    $u = current_user();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . h($title) . ' - ' . APP_NAME . '</title>'
       . '<link rel="stylesheet" href="/assets/style.css"></head><body>';
    if ($u) {
        echo '<div class="shell"><aside class="sidebar"><div class="brand">+ ' . APP_NAME . '</div><nav>';
        foreach (nav_items($u['role']) as $label => $href) {
            echo '<a href="' . $href . '">' . h($label) . '</a>';
        }
        echo '</nav></aside><div class="main"><header class="topbar"><span class="who">'
           . h($u['full_name']) . ' <span class="badge badge-' . h($u['role']) . '">' . h(role_pl($u['role'])) . '</span></span>'
           . '<a class="btn btn-ghost" href="/logout.php">Wyloguj</a></header><main class="content"><h1>'
           . h($title) . '</h1>' . $bodyHtml . '</main></div></div>';
    } else {
        echo '<main class="auth-wrap">' . $bodyHtml . '</main>';
    }
    echo '</body></html>';
}
