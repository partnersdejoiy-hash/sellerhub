<?php
/**
 * DSA HTTP — shared helpers: caching, sanitization, JSON responses.
 */
if (!defined('ABSPATH')) exit;

class DSA_Http {

	/**
	 * Short-lived object cache wrapper (falls back to transient-free runtime cache).
	 */
	private static $runtime = [];

	public static function remember($key, $ttl, $callback) {
		$cache_key = 'dsa_' . md5($key);
		$use_ext = function_exists('wp_cache_get');
		if ($use_ext) {
			$hit = wp_cache_get($cache_key, 'dsa');
			if (false !== $hit) return $hit;
		} elseif (isset(self::$runtime[$key])) {
			return self::$runtime[$key];
		}
		$value = call_user_func($callback);
		if ($use_ext) {
			wp_cache_set($cache_key, $value, 'dsa', max(10, (int) $ttl));
		} else {
			self::$runtime[$key] = $value;
		}
		return $value;
	}

	/**
	 * Money formatting (INR-first).
	 */
	public static function money($amount, $currency = '') {
		$currency = $currency ? $currency : get_woocommerce_currency();
		return html_entity_decode(wp_strip_all_tags(wc_price($amount, ['currency' => $currency])), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Read JSON body of a request safely.
	 */
	public static function body($request) {
		$json = $request->get_json_params();
		if (is_array($json)) return $json;
		return $request->get_body_params();
	}
}
