<?php
// Lab config: values come from the container env (.env).
define('DB_HOST', 'mysql');
define('DB_NAME', getenv('MYSQL_DATABASE') ?: 'medislot');
define('DB_USER', getenv('MYSQL_USER') ?: 'medislot');
define('DB_PASS', getenv('MYSQL_PASSWORD') ?: 'medislot123');
define('APP_NAME', 'MediSlot');
define('MINIO_ENDPOINT', 'http://minio:9000');
define('MINIO_BUCKET', 'medislot-attachments');
define('MINIO_USER', getenv('MINIO_ROOT_USER') ?: 'minioadmin');
define('MINIO_PASS', getenv('MINIO_ROOT_PASSWORD') ?: 'minioadmin123');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'mailhog');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 1025));
// INTENTIONAL (#8): fixed salt, token = md5(user_id . salt), never expires.
define('RESET_SALT', 'medislot-static-salt-2024');
