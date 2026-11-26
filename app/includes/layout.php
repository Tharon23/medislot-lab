<?php
require_once __DIR__ . '/auth.php';

function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function nav_items(string $role): array {
    $items = ['Dashboard' => '/dashboard.php'];
    switch ($role) {
        case 'patient':   $items += ['My appointments' => '/appointment_list.php', 'Book appointment' => '/appointment.php', 'My notes' => '/note_view.php', 'Attachments' => '/upload.php', 'My profile' => '/profile_update.php']; break;
        case 'doctor':    $items += ['Appointments' => '/appointment_list.php', 'Write note' => '/note.php', 'Notes' => '/note_view.php', 'Attachments' => '/upload.php']; break;
        case 'reception': $items += ['Schedule' => '/appointment_list.php', 'Attachments' => '/upload.php']; break;
        case 'admin':     $items += ['Users' => '/admin_users.php', 'Roles' => '/admin_role.php']; break;
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
           . h($u['full_name']) . ' <span class="badge badge-' . h($u['role']) . '">' . h($u['role']) . '</span></span>'
           . '<a class="btn btn-ghost" href="/logout.php">Log out</a></header><main class="content"><h1>'
           . h($title) . '</h1>' . $bodyHtml . '</main></div></div>';
    } else {
        echo '<main class="auth-wrap">' . $bodyHtml . '</main>';
    }
    echo '</body></html>';
}
