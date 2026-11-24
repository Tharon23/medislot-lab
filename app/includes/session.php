<?php
// INTENTIONAL (#19): Redis session store, no AUTH.
// INTENTIONAL: cookie without HttpOnly / Secure.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', 'tcp://redis:6379');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => false,
    ]);
    session_start();
}
