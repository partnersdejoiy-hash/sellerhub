<?php
/**
 * DSA Orders — vendor-scoped order queries built on WooCommerce CRUD + HPOS-safe table logic.
 */
if (!defined('ABSPATH')) exit;

class DSA_Orders {

	/**
	 * True when HPOS (custom order tables) is enabled.
	 */
	public static function hpos_enabled() {
		if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * Vendor-scoped order query. Admins (vendor_id=0) see all orders.
	 * Returns ['items' => [...], 'total' => n].
	 */
	public static function query($vendor_id, $admin, $args = []) {
		$status   = isset($args['status']) && $args['status'] ? sanitize_key($args['status']) : 'all';
		$search   = isset($args['search']) ? sanitize_text_field($args['search']) : '';
		$page     = max(1, absint(isset($args['page']) ? $args['page'] : 1));
		$per_page = min(50, max(1, absint(isset($args['per_page']) ? $args['per_page'] : 20)));

		$wc_statuses = array_keys(wc_get_order_statuses());
		$status_in = ('all' === $status || 'any' === $status) ? $wc_statuses : [$status];

		if (self::hpos_enabled()) {
			// HPOS: query the dedicated orders table directly for author filtering.
			global $wpdb;
			$table = $wpdb->prefix . 'wc_orders';
			$where = ['status IN (' . implode(',', array_fill(0, count($status_in), '%s')) . ')'];
			$params = $status_in;
			if (!$admin && $vendor_id) {
				$where[] = 'customer_id = %d'; // placeholder replaced below
				// WCFM stores the vendor on order items; use the authoritative join instead.
				array_pop($where);
				$author_where = 'id IN (SELECT order_id FROM ' . $wpdb->prefix . "woocommerce_order_items WHERE order_item_type = 'line_item')";
				// Prefer the WCFM helper when available.
				$vendor_order_ids = self::wcfm_vendor_order_ids($vendor_id);
				if (is_array($vendor_order_ids)) {
					if (empty($vendor_order_ids)) {
						return ['items' => [], 'total' => 0];
					}
					$where[] = 'id IN (' . implode(',', array_map('absint', $vendor_order_ids)) . ')';
				} else {
					$where[] = $author_where;
				}
			}
			if ($search) {
				$where[] = '(id LIKE %s OR billing_email LIKE %s)';
				$like = '%' . $wpdb->esc_like($search) . '%';
				array_push($params, '#' . $wpdb->esc_like(ltrim($search, '#')), $like);
			}
			$sql = 'SELECT id FROM ' . $table . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY date_created_gmt DESC';
			$total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(id) FROM ' . $table . ' WHERE ' . implode(' AND ', $where), $params));
			$sql .= $wpdb->prepare(' LIMIT %d OFFSET %d', $per_page, ($page - 1) * $per_page);
			$ids = $wpdb->get_col($wpdb->prepare($sql, $params));
			$items = [];
			foreach ($ids as $id) {
				$order = wc_get_order((int) $id);
				if ($order) $items[] = self::serialize_list($order);
			}
			return ['items' => $items, 'total' => $total];
		}

		// Posts storage.
		$query_args = [
			'post_type'      => 'shop_order',
			'post_status'    => $status_in,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		];
		if (!$admin && $vendor_id) {
			$vendor_order_ids = self::wcfm_vendor_order_ids($vendor_id);
			if (is_array($vendor_order_ids)) {
				if (empty($vendor_order_ids)) return ['items' => [], 'total' => 0];
				$query_args['post__in'] = $vendor_order_ids;
			} else {
				// Fallback: orders containing this vendor's products.
				$query_args['meta_query'] = [[
					'key' => '_vendor_id', 'value' => $vendor_id, 'compare' => '=',
				]];
			}
		}
		if ($search) {
			$query_args['s'] = $search;
		}
		$q = new WP_Query($query_args);
		$items = [];
		foreach ($q->posts as $id) {
			$order = wc_get_order($id);
			if ($order) $items[] = self::serialize_list($order);
		}
		return ['items' => $items, 'total' => (int) $q->found_posts];
	}

	/**
	 * Use WCFM's own order association when present (order item vendor meta).
	 */
	public static function wcfm_vendor_order_ids($vendor_id) {
		if (!$vendor_id || !function_exists('wcfm_get_vendor_orders')) {
			return null;
		}
		$orders = wcfm_get_vendor_orders(['vendor_id' => $vendor_id, 'limit' => -1]);
		if (!is_array($orders)) return null;
		$ids = [];
		foreach ($orders as $o) {
			$oid = is_object($o) ? (isset($o->order_id) ? $o->order_id : (isset($o->ID) ? $o->ID : 0)) : (is_array($o) && isset($o['order_id']) ? $o['order_id'] : 0);
			if ($oid) $ids[] = (int) $oid;
		}
		return array_values(array_unique($ids));
	}

	/**
	 * Serialize an order for list views.
	 */
	public static function serialize_list($order) {
		$items = [];
		foreach ($order->get_items() as $item_id => $item) {
			$items[] = [
				'name'     => $item->get_name(),
				'qty'      => $item->get_quantity(),
				'total'    => (float) $item->get_total(),
				'productId' => $item->get_product_id(),
			];
		}
		return [
			'id'        => $order->get_id(),
			'number'    => $order->get_order_number(),
			'status'    => $order->get_status(),
			'date'      => $order->get_date_created() ? $order->get_date_created()->date_i18n('c') : '',
			'total'     => (float) $order->get_total(),
			'currency'  => $order->get_currency(),
			'customer'  => [
				'name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
			],
			'items'     => $items,
			'itemCount' => array_sum(array_map(function ($i) { return $i['qty']; }, $items)),
			'paymentMethod' => $order->get_payment_method_title(),
		];
	}

	/**
	 * Full order detail (billing, shipping, totals, notes, vendor items only when scoped).
	 */
	public static function detail($order_id, $vendor_id, $admin) {
		$order = wc_get_order($order_id);
		if (!$order) return new WP_Error('dsa_not_found', 'Order not found.', ['status' => 404]);

		if (!$admin && $vendor_id) {
			$vendor_ids = self::wcfm_vendor_order_ids($vendor_id);
			if (is_array($vendor_ids) && !in_array((int) $order_id, $vendor_ids, true)) {
				// Allow if any line item belongs to this vendor.
				$owned = false;
				foreach ($order->get_items() as $item) {
					$pid = $item->get_product_id();
					if ($pid && (int) get_post_field('post_author', $pid) === (int) $vendor_id) { $owned = true; break; }
				}
				if (!$owned) {
					return new WP_Error('dsa_forbidden', 'This order is not yours.', ['status' => 403]);
				}
			}
		}

		$data = self::serialize_list($order);
		$data['billing'] = [
			'address1' => $order->get_billing_address_1(),
			'address2' => $order->get_billing_address_2(),
			'city' => $order->get_billing_city(),
			'state' => $order->get_billing_state(),
			'zip' => $order->get_billing_postcode(),
			'country' => $order->get_billing_country(),
		];
		$data['shipping'] = [
			'address1' => $order->get_shipping_address_1(),
			'address2' => $order->get_shipping_address_2(),
			'city' => $order->get_shipping_city(),
			'state' => $order->get_shipping_state(),
			'zip' => $order->get_shipping_postcode(),
			'country' => $order->get_shipping_country(),
			'method' => $order->get_shipping_method(),
		];
		$data['totals'] = [
			'subtotal' => (float) $order->get_subtotal(),
			'shipping' => (float) $order->get_shipping_total(),
			'tax' => (float) $order->get_total_tax(),
			'discount' => (float) $order->get_discount_total(),
			'total' => (float) $order->get_total(),
		];
		$data['customerNote'] = $order->get_customer_note();
		// Timeline (admin + customer notes).
		$notes = wc_get_order_notes(['order_id' => $order_id, 'limit' => 30]);
		$data['timeline'] = array_map(function ($n) {
			return [
				'content' => wp_strip_all_tags($n->content),
				'date' => $n->date_created ? date_i18n('c', $n->date_created->getTimestamp()) : '',
				'customer' => (bool) $n->customer_note,
				'addedBy' => $n->added_by,
			];
		}, $notes);
		// Tracking (Advanced Shipment Tracking plugin).
		$data['tracking'] = [];
		if (function_exists('ast_get_tracking_items')) {
			$tracking = ast_get_tracking_items($order_id);
			if (is_array($tracking)) {
				$data['tracking'] = $tracking;
			}
		}
		// Vendor-scoped items detail with line totals.
		$items_detail = [];
		foreach ($order->get_items() as $item_id => $item) {
			$pid = $item->get_product_id();
			if (!$admin && $vendor_id && $pid && (int) get_post_field('post_author', $pid) !== (int) $vendor_id) continue;
			$items_detail[] = [
				'id' => $item_id,
				'productId' => $pid,
				'variationId' => $item->get_variation_id(),
				'name' => $item->get_name(),
				'qty' => $item->get_quantity(),
				'total' => (float) $item->get_total(),
				'tax' => (float) $item->get_total_tax(),
				'sku' => ($pid) ? (wc_get_product($pid) ? wc_get_product($pid)->get_sku() : '') : '',
			];
		}
		$data['itemsDetail'] = $items_detail;
		return $data;
	}

	/**
	 * Update order status (vendor may only update their own orders).
	 */
	public static function update_status($order_id, $new_status, $vendor_id, $admin, $note = '') {
		$check = self::detail($order_id, $vendor_id, $admin);
		if (is_wp_error($check)) return $check;

		$allowed = array_keys(wc_get_order_statuses());
		$key = 'wc-' . strtolower(preg_replace('/^wc-/', '', $new_status));
		if (!in_array($key, $allowed, true)) {
			return new WP_Error('dsa_invalid_status', 'Unknown order status.', ['status' => 400]);
		}
		$order = wc_get_order($order_id);
		$order->update_status($key, $note ? $note : 'Updated via DEJOIY Seller App.');
		return ['id' => $order->get_id(), 'status' => $order->get_status()];
	}

	/**
	 * Add an order note.
	 */
	public static function add_note($order_id, $content, $vendor_id, $admin, $customer = false) {
		$check = self::detail($order_id, $vendor_id, $admin);
		if (is_wp_error($check)) return $check;
		$order = wc_get_order($order_id);
		$note_id = $order->add_order_note(sanitize_text_field($content), (bool) $customer, true);
		return $note_id ? ['added' => true] : ['added' => false];
	}

	/**
	 * Aggregate status counts for badges/filters.
	 */
	public static function status_counts($vendor_id, $admin) {
		$counts = [];
		$all = self::query($vendor_id, $admin, ['per_page' => 1]);
		$counts['all'] = (int) $all['total'];
		foreach (array_keys(wc_get_order_statuses()) as $key) {
			$r = self::query($vendor_id, $admin, ['status' => $key, 'per_page' => 1]);
			$counts[str_replace('wc-', '', $key)] = (int) $r['total'];
		}
		return $counts;
	}
}
