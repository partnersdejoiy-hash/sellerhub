<?php
define('ABSPATH', __DIR__ . '/');
define('FORCE_SSL_ADMIN', false);
define('DB_NAME', 'u101086720_JaGmZ');
define('DB_USER', 'u101086720_nx47o');
define('DB_PASSWORD', 'p4fHOylyME');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wp_';
define('WP_CACHE', false);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once ABSPATH . 'wp-settings.php';
echo "Done loading WP. SERVER_PORT=" . ($_SERVER['SERVER_PORT'] ?? 'N/A');
