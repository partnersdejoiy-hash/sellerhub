<?php
/**
 * DSA Vendor — seller profile, WCFM shop data, settings, notifications, support.
 */
if (!defined('ABSPATH')) exit;

class DSA_Vendor {

	/**
	 * WCFM vendor store data with sensible fallbacks.
	 */
	public static function shop($vendor_id) {
		$out = [
			'storeName'   => '',
			'description' => '',
			'logo'        => '',
			'banner'      => '',
			'phone'       => '',
			'email'       => '',
			'address1'    => '',
			'address2'    => '',
			'city'        => '',
			'zip'         => '',
			'country'     => 'IN',
			'state'       => '',
			'social'      => ['facebook' => '', 'twitter' => '', 'instagram' => '', 'youtube' => '', 'linkedin' => ''],
		];

		if ($vendor_id && function_exists('wcfm_get_vendor_store_info')) {
			$info = wcfm_get_vendor_store_info($vendor_id);
			if (is_array($info)) {
				$out['storeName']   = isset($info['store_name']) ? $info['store_name'] : '';
				$out['description'] = isset($info['about']) ? wp_strip_all_tags($info['about']) : '';
				$out['phone']       = isset($info['phone']) ? $info['phone'] : '';
				$out['email']       = isset($info['user_email']) ? $info['user_email'] : '';
				$out['address1']    = isset($info['address']['address_1']) ? $info['address']['address_1'] : '';
				$out['address2']    = isset($info['address']['address_2']) ? $info['address']['address_2'] : '';
				$out['city']        = isset($info['address']['city']) ? $info['address']['city'] : '';
				$out['zip']         = isset($info['address']['zip']) ? $info['address']['zip'] : '';
				$out['country']     = isset($info['address']['country']) ? $info['address']['country'] : 'IN';
				$out['state']       = isset($info['address']['state']) ? $info['address']['state'] : '';
			}
		}

		// WCFM logo/banner (gravatar fallback).
		$logo_id = $vendor_id ? get_user_meta($vendor_id, '_wcfm_vendor_logo', true) : '';
		$banner_id = $vendor_id ? get_user_meta($vendor_id, '_wcfm_vendor_banner', true) : '';
		if ($logo_id && (is_numeric($logo_id) || is_string($logo_id))) {
			$src = wp_get_attachment_url((int) $logo_id);
			if (!$src && is_string($logo_id) && strpos($logo_id, 'http') === 0) $src = $logo_id;
			if ($src) $out['logo'] = $src;
		}
		if ($banner_id && (is_numeric($banner_id) || is_string($banner_id))) {
			$src = wp_get_attachment_url((int) $banner_id);
			if (!$src && is_string($banner_id) && strpos($banner_id, 'http') === 0) $src = $banner_id;
			if ($src) $out['banner'] = $src;
		}

		// WCFM compound profile settings (canonical store of shop fields) — fill any
		// empty values before falling back to direct usermeta.
		if ($vendor_id) {
			$ps = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
			if (is_array($ps)) {
				if (!empty($ps['shop_name']) && empty($out['storeName'])) $out['storeName'] = $ps['shop_name'];
				if (!empty($ps['store_name']) && empty($out['storeName'])) $out['storeName'] = $ps['store_name'];
				if (!empty($ps['about']) && empty($out['description'])) $out['description'] = wp_strip_all_tags($ps['about']);
				if (!empty($ps['phone']) && empty($out['phone'])) $out['phone'] = $ps['phone'];
				$ps_addr = isset($ps['address']) && is_array($ps['address']) ? $ps['address'] : [];
				$ps_map = ['street_1' => 'address1', 'street_2' => 'address2', 'city' => 'city', 'zip' => 'zip', 'state' => 'state', 'country' => 'country'];
				foreach ($ps_map as $ps_key => $field) {
					if (!empty($ps_addr[$ps_key]) && empty($out[$field])) $out[$field] = $ps_addr[$ps_key];
				}
			}
		}

		// Direct usermeta fallbacks (same keys save_shop writes) — WCFM info array
		// can be missing/empty on some builds.
		if ($vendor_id) {
			$m = [
				'description' => 'about', 'phone' => 'phone', 'address1' => 'address_1',
				'address2' => 'address_2', 'city' => 'city', 'zip' => 'zip', 'state' => 'state', 'country' => 'country',
			];
			foreach ($m as $field => $meta_key) {
				$val = get_user_meta($vendor_id, $meta_key, true);
				if ($val && empty($out[$field])) $out[$field] = $val;
			}
		}

		$user = $vendor_id ? get_userdata($vendor_id) : wp_get_current_user();
		if ($user && !($user instanceof WP_Error)) {
			if (empty($out['storeName'])) $out['storeName'] = get_user_meta($vendor_id ? $vendor_id : $user->ID, 'store_name', true) ?: ($user->display_name ? $user->display_name . "'s Store" : 'My Store');
			if (empty($out['email'])) $out['email'] = $user->user_email;
		}
		if (empty($out['storeName'])) $out['storeName'] = 'My Store';

		// Social from WCFM social profile meta.
		foreach (['facebook', 'twitter', 'instagram', 'youtube', 'linkedin'] as $net) {
			$v = $vendor_id ? get_user_meta($vendor_id, $net, true) : '';
			if ($v) $out['social'][$net] = $v;
		}
		return $out;
	}

	/**
	 * Save shop settings (vendor can only edit their own).
	 */
	public static function save_shop($vendor_id, $data) {
		if (!$vendor_id) {
			return new WP_Error('dsa_forbidden', 'Vendor account required to edit store settings.', ['status' => 403]);
		}
		$vendor_id = absint($vendor_id);
		if (!$vendor_id || !get_userdata($vendor_id)) {
			return new WP_Error('dsa_not_found', 'Vendor not found.', ['status' => 404]);
		}

		// Text fields.
		$strings = ['storeName', 'phone', 'address1', 'address2', 'city', 'zip', 'state', 'country'];

		// Keep WCFM's canonical profile settings in sync — wcfm_get_vendor_store_info()
		// reads the compound wcfmmp_profile_settings array, so writes MUST land there
		// too or the values never round-trip in the Seller App UI.
		$settings = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
		if (!is_array($settings)) $settings = [];
		if (!isset($settings['address']) || !is_array($settings['address'])) $settings['address'] = [];
		$settings_map = [
			'storeName' => 'shop_name', 'phone' => 'phone', 'address1' => 'street_1',
			'address2' => 'street_2', 'city' => 'city', 'zip' => 'zip',
			'state' => 'state', 'country' => 'country',
		];

		foreach ($strings as $key) {
			if (!isset($data[$key])) continue;
			$value = sanitize_text_field(wp_unslash($data[$key]));
			// Sync into WCFM profile settings.
			if ('storeName' === $key) {
				$settings['shop_name'] = $value;
				$settings['store_name'] = $value;
			} else {
				$settings[$settings_map[$key]] = $value;
			}
			switch ($key) {
				case 'storeName':
					update_user_meta($vendor_id, 'store_name', $value);
					if (function_exists('wcfm_vendor_store_name_update')) {
						wcfm_vendor_store_name_update($vendor_id, $value);
					}
					break;
				case 'phone': update_user_meta($vendor_id, 'phone', $value); break;
				case 'address1': update_user_meta($vendor_id, 'address_1', $value); break;
				case 'address2': update_user_meta($vendor_id, 'address_2', $value); break;
				case 'city': update_user_meta($vendor_id, 'city', $value); break;
				case 'zip': update_user_meta($vendor_id, 'zip', $value); break;
				case 'state': update_user_meta($vendor_id, 'state', $value); break;
				case 'country': update_user_meta($vendor_id, 'country', $value); break;
			}
		}
		if (isset($data['description'])) {
			update_user_meta($vendor_id, 'about', wp_kses_post(wp_unslash($data['description'])));
			$settings['about'] = wp_kses_post(wp_unslash($data['description']));
		}
		update_user_meta($vendor_id, 'wcfmmp_profile_settings', $settings);
		if (isset($data['social']) && is_array($data['social'])) {
			foreach (['facebook', 'twitter', 'instagram', 'youtube', 'linkedin'] as $net) {
				if (isset($data['social'][$net])) {
					update_user_meta($vendor_id, $net, esc_url_raw(wp_unslash($data['social'][$net])));
				}
			}
		}
		// Media IDs for logo/banner.
		foreach (['logo' => '_wcfm_vendor_logo', 'banner' => '_wcfm_vendor_banner'] as $field => $meta) {
			if (isset($data[$field])) {
				$mid = absint(is_array($data[$field]) ? (isset($data[$field]['id']) ? $data[$field]['id'] : 0) : $data[$field]);
				if ($mid) update_user_meta($vendor_id, $meta, $mid);
			}
		}
		return self::shop($vendor_id);
	}

	/**
	 * Central notification builder — real data only.
	 */
	public static function notifications($vendor_id, $admin = false) {
		$items = [];

		// Low stock (real products).
		$low = self::low_stock_products($vendor_id, $admin);
		foreach (array_slice($low, 0, 10) as $p) {
			$items[] = [
				'id'       => 'stock-' . $p['id'],
				'type'     => 'inventory',
				'title'    => ('outofstock' === $p['stockStatus']) ? 'Out of stock: ' . $p['name'] : 'Low stock: ' . $p['name'],
				'body'     => ('outofstock' === $p['stockStatus']) ? 'This product is out of stock.' : ('Only ' . $p['stock'] . ' left in stock.'),
				'date'     => null,
				'unread'   => true,
				'link'     => '/products/' . $p['id'],
			];
		}

		// Orders needing processing (vendor-scoped).
		$pending = DSA_Orders::query($vendor_id, $admin, ['status' => 'processing', 'per_page' => 10]);
		$count = is_array($pending) ? count($pending) : 0;
		if ($count) {
			$items[] = [
				'id' => 'orders-processing', 'type' => 'order',
				'title' => $count . ' order' . ($count > 1 ? 's' : '') . ' need processing',
				'body' => 'Pack and ship to keep your delivery promise.', 'date' => null, 'unread' => true, 'link' => '/orders?status=processing',
			];
		}

		// Unanswered reviews.
		$reviews = self::unanswered_reviews($vendor_id, $admin, 5);
		if ($reviews['count']) {
			$items[] = [
				'id' => 'reviews-unanswered', 'type' => 'review',
				'title' => $reviews['count'] . ' review' . ($reviews['count'] > 1 ? 's' : '') . ' awaiting response',
				'body' => 'Responding builds buyer trust.', 'date' => null, 'unread' => true, 'link' => '/reviews',
			];
		}

		// Store profile incomplete?
		$shop = self::shop($vendor_id);
		if (!$shop['logo'] || !$shop['banner'] || !$shop['address1']) {
			$items[] = [
				'id' => 'store-incomplete', 'type' => 'system',
				'title' => 'Store profile incomplete',
				'body' => 'Add logo, banner and address to look professional.',
				'date' => null, 'unread' => true, 'link' => '/settings/store',
			];
		}

		return ['items' => $items, 'unread' => count($items)];
	}

	public static function unanswered_reviews($vendor_id, $admin, $limit = 20) {
		$query = [
			'status' => 'approve',
			'number' => $limit,
			'type'   => 'review',
			'meta_query' => [
				['key' => 'rating', 'compare' => 'EXISTS'],
			],
		];
		if (!$admin && $vendor_id) {
			$query['post__in'] = self::vendor_product_ids($vendor_id);
			if (empty($query['post__in'])) return ['items' => [], 'count' => 0, 'unanswered' => 0];
		}
		$comments = get_comments($query);
		$items = [];
		foreach ($comments as $c) {
			$rating = (int) get_comment_meta($c->comment_ID, 'rating', true);
			$reply = get_comments(['status' => 'approve', 'parent' => $c->comment_ID, 'number' => 1]);
			$items[] = [
				'id' => (int) $c->comment_ID,
				'product' => ['id' => (int) $c->comment_post_ID, 'name' => get_the_title($c->comment_post_ID)],
				'author' => $c->comment_author,
				'rating' => $rating,
				'content' => wp_strip_all_tags($c->comment_content),
				'date' => $c->comment_date_gmt,
				'responded' => !empty($reply),
			];
		}
		$unanswered = 0;
		foreach ($items as $it) { if (!$it['responded']) $unanswered++; }
		return ['items' => $items, 'count' => count($items), 'unanswered' => $unanswered];
	}

	public static function vendor_product_ids($vendor_id) {
		if (!$vendor_id) return [];
		$ids = get_posts([
			'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1,
			'author' => $vendor_id, 'fields' => 'ids',
		]);
		return $ids ? $ids : [];
	}

	public static function low_stock_products($vendor_id, $admin, $limit = 50) {
		$args = [
			'status' => ['publish', 'draft', 'pending', 'private'],
			'limit'  => $limit,
		];
		if (!$admin && $vendor_id) $args['author'] = $vendor_id;
		$products = wc_get_products($args);
		$out = [];
		$threshold = (int) get_option('woocommerce_notify_low_stock_amount', 5);
		foreach ($products as $p) {
			if (!$p->managing_stock()) continue;
			$stock = (int) $p->get_stock_quantity();
			if ($stock <= $threshold) {
				$out[] = DSA_Products::serialize($p);
			}
		}
		return $out;
	}
}
