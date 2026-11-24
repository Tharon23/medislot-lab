<?php
// Lab config: values come from the container env (.env).
define('DB_HOST', 'mysql');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'medislot');
define('DB_USER', getenv('MYSQL_USER') ?: 'medislot');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'medislot123');
define('APP_NAME', 'MediSlot');
