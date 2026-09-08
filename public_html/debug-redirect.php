<?php
define('ABSPATH', __DIR__ . '/');
define('FORCE_SSL_ADMIN', false);
define('FORCE_SSL_LOGIN', false);
define('DB_NAME', 'u101086720_JaGmZ');
define('DB_USER', 'u101086720_nx47o');
define('DB_PASSWORD', 'p4fHOylyME');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
$table_prefix = 'wp_';
define('WP_CACHE', false);
define('WP_DEBUG', false);
require_once ABSPATH . 'wp-settings.php';

// Check what the canonical redirect would do
echo "Server Port: " . ($_SERVER['SERVER_PORT'] ?? 'N/A') . "\n";
echo "HTTP Host: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "\n";
echo "siteurl option: " . get_option('siteurl') . "\n";
echo "home option: " . get_option('home') . "\n";
echo "site_url(): " . site_url() . "\n";
echo "home_url(): " . home_url() . "\n";
echo "is_ssl(): " . (is_ssl() ? 'yes' : 'no') . "\n";
