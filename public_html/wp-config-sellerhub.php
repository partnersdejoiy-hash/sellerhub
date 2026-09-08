<?php
/**
 * DEJOIY Seller Hub — Custom wp-config
 * Uses the same database as dejoiy.com but serves on HTTP for port 8080
 */

// Override site URL for seller hub (HTTP, no SSL redirect)
define('WP_HOME', 'http://sellerhub.dejoiy.com:8080');
define('WP_SITEURL', 'http://sellerhub.dejoiy.com:8080');

// Force no SSL
define('FORCE_SSL_ADMIN', false);
define('FORCE_SSL_LOGIN', false);

// Database settings (same as dejoiy.com)
define('DB_NAME', 'u101086720_JaGmZ');
define('DB_USER', 'u101086720_nx47o');
define('DB_PASSWORD', 'p4fHOylyME');
define('DB_HOST', '127.0.0.1');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');

// Auth keys and salts
define('AUTH_KEY',          'O1u|v+n]bUUA0qO:}TX}#JACpsQpRMZ,bZC2^(pBK<5-Hu0CB0A!^qce=9]&NIxO');
define('SECURE_AUTH_KEY',   'vrCd+L!seFV!^}uIf1!8O2sCO]1NsDo9`*c6?GznC:_U2AKz7~nXa&wjg+ApH_A-');
define('LOGGED_IN_KEY',     '.%EWa&0,/2n {@=S7{[Jv6K~Dxg$7oA%EFaZ;}zcnQ]vY=q5m&4hMAJvIB>Q++&n');
define('NONCE_KEY',         '/)enzByC*R$CB(BFoM8(_$l/NFVVXYx]o)FQ?+|Yd_4Krl)_kj}DJ]]yD4ovPH! ');
define('AUTH_SALT',         'Q[7?:OM7de6hlR7oYDc8T,!6d|{N:k-gj]~r3!uYqp5l9HL_deHCC+?(;kCD^SP}');
define('SECURE_AUTH_SALT',  '|Jkc-~YDVD%-$PJw4H;aHf&5Zo= J(kVz.V:(F(spU|7y<HnR8>xZy8-<>22Q:t_');
define('LOGGED_IN_SALT',    'YMHfTE`(d,I%voSdd@N63zk(`=x^UVLE%0>ebcq?%>|@hk:/+e(.@};UZRTl!KT)');
define('NONCE_SALT',        '!MyJVaLt++BV%):9@#/lHp`S}V<4nQKFYyP2yO;$]rk8Gz2iQMY;uW6Xhx1!PPIu');

$table_prefix = 'wp_';

define('WP_CACHE', true);
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_DEBUG_DISPLAY', false);
define('FS_METHOD', 'direct');
define('COOKIEHASH', '8f9be602ec79f9dd4320c07f75f539e7');
define('WP_AUTO_UPDATE_CORE', 'minor');

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
