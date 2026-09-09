<?php
/**
 * Plugin Name: DEJOIY Seller App
 * Description: Independent, world-class Seller Operating System for the DEJOIY marketplace. WCFM stays behind the scenes as the data engine — sellers never need WordPress or WCFM UI.
 * Version: 1.0.0
 * Author: DEJOIY
 * Text Domain: dejoiy-seller-app
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) exit;

define('DSA_VERSION', '1.0.0');
define('DSA_PATH', plugin_dir_path(__FILE__));
define('DSA_URL', plugin_dir_url(__FILE__));
define('DSA_APP_SLUG', 'seller-app');
define('DSA_GATE_SLUG', 'dsa-gate');
define('DSA_OPT', 'dsa_settings');

require_once DSA_PATH . 'includes/class-dsa-router.php';
require_once DSA_PATH . 'includes/class-dsa-auth.php';
require_once DSA_PATH . 'includes/class-dsa-vendor.php';
require_once DSA_PATH . 'includes/class-dsa-http.php';
require_once DSA_PATH . 'includes/class-dsa-finance.php';
require_once DSA_PATH . 'includes/class-dsa-analytics.php';
require_once DSA_PATH . 'includes/class-dsa-orders.php';
require_once DSA_PATH . 'includes/class-dsa-products.php';
require_once DSA_PATH . 'api/rest-api.php';

/**
 * Main plugin class.
 */
final class DSA_Plugin {

	private static $instance = null;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		DSA_Router::init();
		DSA_Auth::init();
		add_action('rest_api_init', ['DSA_REST_API', 'register_routes']);
		add_action('init', [__CLASS__, 'flush_rewrite_if_needed']);
		register_activation_hook(__FILE__, [__CLASS__, 'activate']);
		register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);
	}

	/**
	 * Rewrite rules are added on init by the router; flag a flush once after activation.
	 */
	public static function flush_rewrite_if_needed() {
		if (get_option('dsa_flush_rewrite')) {
			flush_rewrite_rules(false);
			delete_option('dsa_flush_rewrite');
		}
	}

	public static function activate() {
		update_option('dsa_flush_rewrite', 1);
		// Default settings.
		$defaults = get_option(DSA_OPT, []);
		if (!isset($defaults['commission_rate'])) {
			$defaults['commission_rate'] = 0;
		}
		if (!isset($defaults['ai_enabled'])) {
			$defaults['ai_enabled'] = 0;
		}
		update_option(DSA_OPT, $defaults);
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}

/**
 * Global accessor for the plugin instance.
 */
function dsa() {
	return DSA_Plugin::instance();
}

dsa();
