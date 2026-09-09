<?php
/**
 * DSA Analytics — real metrics computed from WooCommerce orders/products.
 * No fake numbers: every value derives from actual order data.
 */
if (!defined('ABSPATH')) exit;

class DSA_Analytics {

	/**
	 * HPOS enabled?
	 */
	public static function hpos() {
		if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
			return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		}
		return false;
	}

	/**
	 * Vendor-scoped order rows in a date window: id, total, date, status.
	 */
	public static function order_rows($vendor_id, $admin, $since_gmt) {
		$statuses = array_values(wc_get_order_statuses());

		if (self::hpos()) {
			global $wpdb;
			$table = $wpdb->prefix . 'wc_orders';
			$where = ['status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')', 'date_created_gmt >= %s'];
			$params = array_merge($statuses, [$since_gmt]);
			if (!$admin && $vendor_id) {
				$ids = DSA_Orders::wcfm_vendor_order_ids($vendor_id);
				if (is_array($ids)) {
					if (empty($ids)) return [];
					$where[] = 'id IN (' . implode(',', array_map('absint', $ids)) . ')';
				}
			}
			$rows = $wpdb->get_results($wpdb->prepare(
				'SELECT id, total_amount, status, date_created_gmt FROM ' . $table . ' WHERE ' . implode(' AND ', $where),
				$params
			));
			$out = [];
			foreach ($rows as $r) {
				$out[] = ['id' => (int) $r->id, 'total' => (float) $r->total_amount, 'status' => $r->status, 'date' => $r->date_created_gmt];
			}
			return $out;
		}

		$q = new WP_Query([
			'post_type' => 'shop_order',
			'post_status' => $statuses,
			'posts_per_page' => 500,
			'fields' => 'ids',
			'orderby' => 'date',
			'order' => 'ASC',
			'date_query' => [['after' => $since_gmt, 'column' => 'post_date_gmt']],
		]);
		$out = [];
		foreach ($q->posts as $id) {
			$o = wc_get_order($id);
			if (!$o) continue;
			$out[] = [
				'id' => $o->get_id(),
				'total' => (float) $o->get_total(),
				'status' => 'wc-' . $o->get_status(),
				'date' => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i:s') : '',
			];
		}
		return $out;
	}

	/**
	 * Paid (revenue-counting) statuses.
	 */
	private static function revenue_statuses() {
		$defaults = ['wc-processing', 'wc-completed', 'wc-on-hold'];
		return apply_filters('dsa_revenue_statuses', $defaults);
	}

	/**
	 * Full analytics + dashboard payload for a range.
	 */
	public static function dashboard($vendor_id, $admin, $range = '30d') {
		$windows = ['today' => 1, 'yesterday' => 2, '7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365];
		if ('yesterday' === $range) {
			$days = 2;
		} elseif (!isset($windows[$range])) {
			$range = '30d';
			$days = 30;
		} else {
			$days = $windows[$range];
		}
		$since = gmdate('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));
		$rows = self::order_rows($vendor_id, $admin, $since);
		$rev_statuses = self::revenue_statuses();

		$revenue = 0.0; $orders_count = 0; $items_sold = 0; $refunded = 0.0;
		$product_totals = []; $customer_emails = []; $customer_orders = [];
		$series = [];

		foreach ($rows as $row) {
			$day = substr((string) $row['date'], 0, 10);
			if (!isset($series[$day])) $series[$day] = ['revenue' => 0.0, 'orders' => 0];
			if (!in_array($row['status'], $rev_statuses, true)) continue;

			$orders_count++;
			$revenue += (float) $row['total'];
			$series[$day]['revenue'] += (float) $row['total'];
			$series[$day]['orders']++;

			$order = wc_get_order($row['id']);
			if (!$order) continue;
			$refunded += (float) $order->get_total_refunded();
			$email = $order->get_billing_email();
			if ($email) {
				$customer_emails[$email] = true;
				$customer_orders[$email] = isset($customer_orders[$email]) ? $customer_orders[$email] + 1 : 1;
			}
			foreach ($order->get_items() as $item) {
				$qty = (int) $item->get_quantity();
				$items_sold += $qty;
				$pid = $item->get_product_id();
				if (!$pid) continue;
				if (!isset($product_totals[$pid])) {
					$product_totals[$pid] = ['qty' => 0, 'revenue' => 0.0];
				}
				$product_totals[$pid]['qty'] += $qty;
				$product_totals[$pid]['revenue'] += (float) $item->get_total();
			}
		}

		// Fill missing days in series.
		$full_series = [];
		for ($i = $days - 1; $i >= 0; $i--) {
			$d = gmdate('Y-m-d', strtotime('-' . $i . ' days'));
			$full_series[] = [
				'date' => $d,
				'revenue' => isset($series[$d]) ? round($series[$d]['revenue'], 2) : 0,
				'orders' => isset($series[$d]) ? (int) $series[$d]['orders'] : 0,
			];
		}

		// Best sellers.
		$best = [];
		arsort($product_totals);
		foreach (array_slice($product_totals, 0, 8, true) as $pid => $agg) {
			$p = wc_get_product($pid);
			$best[] = [
				'id' => $pid,
				'name' => $p ? $p->get_name() : '#' . $pid,
				'image' => $p ? wp_get_attachment_url($p->get_image_id()) : '',
				'qty' => $agg['qty'],
				'revenue' => round($agg['revenue'], 2),
			];
		}

		// Inventory health (vendor-scoped products).
		$inv = ['totalProducts' => 0, 'lowStock' => 0, 'outOfStock' => 0, 'stockValue' => 0.0];
		$args = ['status' => ['publish', 'draft', 'pending', 'private'], 'limit' => 300];
		if (!$admin && $vendor_id) $args['author'] = $vendor_id;
		$products = wc_get_products($args);
		$threshold = (int) get_option('woocommerce_notify_low_stock_amount', 5);
		$low_items = [];
		foreach ($products as $p) {
			$inv['totalProducts']++;
			if ($p->get_type() === 'variation') continue;
			if ($p->managing_stock()) {
				$stock = (int) $p->get_stock_quantity();
				$inv['stockValue'] += $stock * (float) $p->get_price('edit');
				if ($stock <= 0) { $inv['outOfStock']++; }
				elseif ($stock <= $threshold) { $inv['lowStock']++; $low_items[] = $p; }
			} elseif ('outofstock' === $p->get_stock_status()) {
				$inv['outOfStock']++;
			}
		}

		// Customers insight.
		$total_customers = count($customer_emails);
		$repeat = 0;
		foreach ($customer_orders as $c) { if ($c > 1) $repeat++; }

		// Needs attention list.
		$attention = [];
		$pending = DSA_Orders::query($vendor_id, $admin, ['status' => 'processing', 'per_page' => 1]);
		if ((int) $pending['total'] > 0) {
			$attention[] = ['key' => 'processing', 'label' => $pending['total'] . ' orders need processing', 'link' => '/orders?status=processing', 'count' => (int) $pending['total']];
		}
		if ($inv['outOfStock'] > 0) {
			$attention[] = ['key' => 'out', 'label' => $inv['outOfStock'] . ' products out of stock', 'link' => '/products?stock=out', 'count' => $inv['outOfStock']];
		}
		if ($inv['lowStock'] > 0) {
			$attention[] = ['key' => 'low', 'label' => $inv['lowStock'] . ' products low in stock', 'link' => '/products?stock=low', 'count' => $inv['lowStock']];
		}
		$rev = DSA_Vendor::unanswered_reviews($vendor_id, $admin, 50);
		if ($rev['unanswered'] > 0) {
			$attention[] = ['key' => 'reviews', 'label' => $rev['unanswered'] . ' reviews awaiting response', 'link' => '/reviews', 'count' => $rev['unanswered']];
		}

		$prev_revenue = self::previous_period_revenue($vendor_id, $admin, $days);

		return [
			'range' => $range,
			'summary' => [
				'revenue' => round($revenue, 2),
				'orders' => $orders_count,
				'itemsSold' => $items_sold,
				'aov' => $orders_count ? round($revenue / $orders_count, 2) : 0,
				'refunded' => round($refunded, 2),
				'customers' => $total_customers,
				'repeatCustomers' => $repeat,
			],
			'series' => $full_series,
			'bestSellers' => $best,
			'inventory' => [
				'totalProducts' => $inv['totalProducts'],
				'lowStock' => $inv['lowStock'],
				'outOfStock' => $inv['outOfStock'],
				'stockValue' => round($inv['stockValue'], 2),
			],
			'attention' => $attention,
			'prevRevenue' => round($prev_revenue, 2),
		];
	}

	/**
	 * Revenue for the equivalent previous window (growth comparison).
	 */
	private static function previous_period_revenue($vendor_id, $admin, $days) {
		$from = gmdate('Y-m-d 00:00:00', strtotime('-' . (2 * $days - 1) . ' days'));
		$to = gmdate('Y-m-d 23:59:59', strtotime('-' . $days . ' days'));
		$rev_statuses = self::revenue_statuses();
		$revenue = 0.0;

		if (self::hpos()) {
			global $wpdb;
			$table = $wpdb->prefix . 'wc_orders';
			$statuses = array_values(wc_get_order_statuses());
			$where = ['status IN (' . implode(',', array_fill(0, count($statuses), '%s')) . ')', 'date_created_gmt >= %s', 'date_created_gmt <= %s'];
			$params = array_merge($statuses, [$from, $to]);
			if (!$admin && $vendor_id) {
				$ids = DSA_Orders::wcfm_vendor_order_ids($vendor_id);
				if (is_array($ids)) {
					if (empty($ids)) return 0;
					$where[] = 'id IN (' . implode(',', array_map('absint', $ids)) . ')';
				}
			}
			$rows = $wpdb->get_results($wpdb->prepare(
				'SELECT id, total_amount, status FROM ' . $table . ' WHERE ' . implode(' AND ', $where), $params
			));
			foreach ($rows as $r) {
				if (in_array($r->status, $rev_statuses, true)) $revenue += (float) $r->total_amount;
			}
			return $revenue;
		}
		$q = new WP_Query([
			'post_type' => 'shop_order', 'post_status' => array_values(wc_get_order_statuses()),
			'posts_per_page' => 500, 'fields' => 'ids',
			'date_query' => [['after' => $from, 'before' => $to, 'column' => 'post_date_gmt']],
		]);
		foreach ($q->posts as $id) {
			$o = wc_get_order($id);
			if (!$o) continue;
			if (in_array('wc-' . $o->get_status(), $rev_statuses, true)) $revenue += (float) $o->get_total();
		}
		return $revenue;
	}
}
