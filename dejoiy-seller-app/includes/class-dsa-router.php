<?php
/**
 * DSA Router — serves the Seller App SPA at /seller-app/ (independent of theme/WCFM).
 */
if (!defined('ABSPATH')) exit;

class DSA_Router {

	public static function init() {
		add_action('init', [__CLASS__, 'add_rewrites']);
		add_filter('query_vars', [__CLASS__, 'query_vars']);
		add_action('template_redirect', [__CLASS__, 'maybe_render'], 1);
	}

	public static function add_rewrites() {
		add_rewrite_rule('^seller-app/assets/(.+)$', 'index.php?dsa_asset=$matches[1]', 'top');
		add_rewrite_rule('^seller-app/?$', 'index.php?dsa_app=1', 'top');
		add_rewrite_rule('^seller-app/(.+)$', 'index.php?dsa_app=1', 'top');
	}

	public static function query_vars($vars) {
		$vars[] = 'dsa_app';
		$vars[] = 'dsa_asset';
		return $vars;
	}

	/**
	 * Serve a static asset from the plugin app/assets directory.
	 */
	private static function serve_asset($rel) {
		$base = realpath(DSA_PATH . 'app/assets');
		$file = realpath(DSA_PATH . 'app/assets/' . $rel);
		if (!$base || !$file || strpos($file, $base) !== 0 || !is_file($file)) {
			status_header(404);
			nocache_headers();
			exit;
		}
		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		$map = [
			'js' => 'application/javascript', 'css' => 'text/css', 'svg' => 'image/svg+xml',
			'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
			'woff2' => 'font/woff2', 'woff' => 'font/woff', 'map' => 'application/json',
			'ico' => 'image/x-icon', 'json' => 'application/json', 'html' => 'text/html',
		];
		$mime = isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
		header('Content-Type: ' . $mime);
		// Hashed bundle names are immutable; allow long caches.
		if (preg_match('/-[A-Za-z0-9_-]{8,}\./', basename($file))) {
			header('Cache-Control: public, max-age=31536000, immutable');
		} else {
			header('Cache-Control: public, max-age=3600');
		}
		header('X-Content-Type-Options: nosniff');
		readfile($file);
		exit;
	}

	/**
	 * Render the SPA shell with boot config injected.
	 */
	private static function render_shell() {
		if (!is_user_logged_in()) {
			if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch') {
				wp_send_json_error(['code' => 'auth_required'], 401);
			}
			wp_safe_redirect(wp_login_url(home_url('/seller-app/')));
			exit;
		}
		// App entry: vendors/admins only. Customers get a clean message page.
		if (!DSA_Auth::is_admin() && !DSA_Auth::is_vendor()) {
			wp_die(
				'<h1>DEJOIY Seller App</h1><p>This application is for DEJOIY sellers. Your account (' . esc_html(wp_get_current_user()->user_email) . ') does not have a seller store yet.</p><p><a href="' . esc_url(home_url('/')) . '">&larr; Back to DEJOIY</a></p>',
				['response' => 403]
			);
		}

		$index = DSA_PATH . 'app/index.html';
		if (!is_file($index)) {
			status_header(500);
			wp_die('Seller App build missing. Re-upload the plugin package.');
		}

		$html = file_get_contents($index);
		$config = [
			'restUrl'    => esc_url_raw(rest_url('dsa/v1')),
			'wpRestUrl'  => esc_url_raw(rest_url('wp/v2')),
			'nonce'      => wp_create_nonce('wp_rest'),
			'home'       => home_url('/'),
			'user'       => [
				'id'         => get_current_user_id(),
				'name'       => wp_get_current_user()->display_name,
				'isAdmin'    => current_user_can('manage_woocommerce') || current_user_can('manage_options'),
			],
			'version'    => DSA_VERSION,
		];
		$inject = '<script>window.DSA_CONFIG=' . wp_json_encode($config) . ';</script>';
		$html = preg_replace('/<\/head>/i', $inject . '</head>', $html, 1);

		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('X-Frame-Options: SAMEORIGIN');
		echo $html;
		exit;
	}

	public static function maybe_render() {
		$asset = get_query_var('dsa_asset');
		if ($asset) {
			self::serve_asset($asset);
		}
		if (get_query_var('dsa_app')) {
			self::render_shell();
		}
	}
}
