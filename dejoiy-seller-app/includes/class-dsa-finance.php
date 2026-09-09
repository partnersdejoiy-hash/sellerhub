<?php
/**
 * DSA Finance — real earnings from WCFM commission ledger when present,
 * otherwise order-derived revenue. Never fabricates numbers.
 */
if (!defined('ABSPATH')) exit;

class DSA_Finance {

	/**
	 * Ledger table exists?
	 */
	public static function ledger_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'wcfm_marketplace_orders';
		$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
		return ($found === $table) ? $table : '';
	}

	/**
	 * Finance summary for a vendor.
	 */
	public static function summary($vendor_id, $admin) {
		$out = [
			'source'        => 'orders',
			'revenue30'     => 0.0,
			'revenue90'     => 0.0,
			'refund30'      => 0.0,
			'commission30'  => null,
			'netEarnings30' => null,
			'totalPaid'     => null,
			'pendingPayout' => null,
			'ledger'        => false,
		];

		$admin_flag = DSA_Auth::is_admin();
		$dash30 = DSA_Analytics::dashboard($vendor_id, $admin_flag, '30d');
		$dash90 = DSA_Analytics::dashboard($vendor_id, $admin_flag, '90d');
		$out['revenue30'] = $dash30['summary']['revenue'];
		$out['revenue90'] = $dash90['summary']['revenue'];
		$out['refund30']  = $dash30['summary']['refunded'];

		$table = self::ledger_table();
		if ($table) {
			global $wpdb;
			$where = 'WHERE disabled = 0';
			$params = [];
			if (!$admin && $vendor_id) {
				$where .= ' AND vendor_id = %d';
				$params[] = (int) $vendor_id;
			}
			// Last 30 days commission.
			$since30 = gmdate('Y-m-d 00:00:00', strtotime('-29 days'));
			$row = $wpdb->get_row($wpdb->prepare(
				"SELECT SUM(total_commission) AS commission, SUM(shipping_amount) AS shipping, SUM(tax_amount) AS tax
				 FROM {$table} {$where} AND created >= %s",
				array_merge($params, [$since30])
			));
			if ($row && null !== $row->commission) {
				$out['ledger'] = true;
				$out['source'] = 'wcfm-ledger';
				$out['commission30'] = (float) $row->commission;
				$out['netEarnings30'] = (float) $row->commission + (float) $row->shipping;
			}
			// Withdrawal status (WCFM withdraw table if present).
			$wd_table = $wpdb->prefix . 'wcfm_marketplace_withdraw_requests';
			$wd_found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wd_table));
			if ($wd_found === $wd_table) {
				$paid_where = $where;
				$p_row = $wpdb->get_row("SELECT SUM(commission_amount) AS paid FROM {$table} {$paid_where} AND is_withdrawable = 1 AND withdraw_paid = 1");
				$pend_row = $wpdb->get_row("SELECT SUM(commission_amount) AS pending FROM {$table} {$paid_where} AND is_withdrawable = 1 AND withdraw_paid = 0");
				$out['totalPaid'] = $p_row && null !== $p_row->paid ? (float) $p_row->paid : 0.0;
				$out['pendingPayout'] = $pend_row && null !== $pend_row->pending ? (float) $pend_row->pending : 0.0;
			}
		}
		return $out;
	}
}
