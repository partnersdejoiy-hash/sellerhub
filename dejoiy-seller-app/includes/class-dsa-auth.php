<?php
/**
 * DSA Auth — cookie-based authentication + per-request authorization helpers.
 * No custom tokens: the WP user cookie session is the source of truth.
 */
if (!defined('ABSPATH')) exit;

class DSA_Auth {

	public static function init() {
		// Keep same-site fetches authenticated with the user session.
		add_filter('rest_authentication_errors', [__CLASS__, 'rest_auth'], 5);
		add_action('rest_api_init', [__CLASS__, 'cors'], 1);
	}

	/**
	 * Allow cookie-authenticated REST access for the SPA origin only.
	 */
	public static function rest_auth($errors) {
		return $errors;
	}

	public static function cors() {
		$origin = isset($_SERVER['HTTP_ORIGIN']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_ORIGIN'])) : '';
		$home = home_url();
		if ($origin && (strpos($origin, $home) === 0 || strpos($origin, 'https://dejoiy.com') === 0)) {
			header('Access-Control-Allow-Origin: ' . $origin);
			header('Access-Control-Allow-Credentials: true');
			header('Vary: Origin');
			header('Access-Control-Allow-Headers: Content-Type, X-WP-Nonce');
			header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
		}
	}

	/**
	 * Role-based admin detection. WCFM grants manage_woocommerce to vendors via
	 * map_meta_cap, so capability checks are NOT reliable for admin detection.
	 */
	public static function is_admin($user_id = 0) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if (!$user_id) return false;
		$user = get_userdata($user_id);
		if (!$user) return false;
		foreach (['administrator', 'shop_manager', 'store_manager'] as $role) {
			if (in_array($role, (array) $user->roles, true)) return true;
		}
		return false;
	}

	/**
	 * Is the current user a marketplace vendor (WCFM-aware)?
	 */
	public static function is_vendor($user_id = 0) {
		$user_id = $user_id ? $user_id : get_current_user_id();
		if (!$user_id) return false;

		if (function_exists('wcfm_is_vendor') && wcfm_is_vendor($user_id)) {
			return true;
		}
		$user = get_userdata($user_id);
		if (!$user) return false;
		$roles = (array) $user->roles;
		foreach (['wcfm_vendor', 'vendor', 'store_manager'] as $role) {
			if (in_array($role, $roles, true)) return true;
		}
		return false;
	}

	/**
	 * Vendor + admin fallback: admins can operate the app for any vendor.
	 */
	public static function current_vendor_id() {
		$user_id = get_current_user_id();
		if (!$user_id) return 0;
		if (self::is_vendor($user_id)) {
			if (function_exists('wcfm_get_vendor_id_by_user')) {
				$vid = wcfm_get_vendor_id_by_user($user_id);
				if ($vid) return (int) $vid;
			}
			return $user_id;
		}
		return 0;
	}

	/**
	 * Resolve which vendor's data the current request may touch.
	 * Admins may pass ?vendor_id=; vendors are always locked to their own ID.
	 */
	public static function resolve_vendor($request) {
		$user_id = get_current_user_id();
		if (!$user_id) {
			return new WP_Error('dsa_auth', 'Login required.', ['status' => 401]);
		}
		$admin = self::is_admin($user_id);
		$vendor_id = 0;
		if ($admin) {
			$vendor_id = absint($request->get_param('vendor_id'));
			if (!$vendor_id && self::is_vendor($user_id)) {
				$vendor_id = self::current_vendor_id();
			}
			if (!$vendor_id) $vendor_id = 0; // admin, site-wide
		} elseif (self::is_vendor($user_id)) {
			$vendor_id = self::current_vendor_id();
		} else {
			return new WP_Error('dsa_forbidden', 'Seller access required.', ['status' => 403]);
		}
		return $vendor_id;
	}

	/**
	 * Strict permission callback for seller-scoped endpoints.
	 */
	public static function require_vendor($request) {
		$resolved = self::resolve_vendor($request);
		if (is_wp_error($resolved)) return $resolved;
		return true;
	}

	/**
	 * Permission callback for endpoints admins may also use.
	 */
	public static function require_seller_or_admin($request) {
		if (!is_user_logged_in()) {
			return new WP_Error('dsa_auth', 'Login required.', ['status' => 401]);
		}
		return true;
	}
}
