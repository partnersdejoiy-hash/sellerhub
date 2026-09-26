<?php
/**
 * DSO Marketplace - Central Marketplace Administration Engine
 * Complete native multi-vendor control plane for DEJOIY Seller Central
 * Replaces all WCFM admin dependencies with enterprise-grade Seller OS architecture
 */
if (!defined('ABSPATH')) exit;

class DSO_Marketplace {

    /**
     * Verify administrator permissions
     */
    protected function check_admin() {
        if (!current_user_can('manage_woocommerce') && !current_user_can('administrator')) {
            wp_die('Access Denied — Marketplace administration requires platform administrator credentials.', 'Unauthorized', ['response' => 403]);
        }
    }

    /**
     * Get all registered marketplace vendors
     */
    public static function get_all_vendors() {
        global $wpdb;

        // Query users with vendor roles or vendor meta
        $vendor_users = get_users([
            'role__in' => ['wcfm_vendor', 'seller', 'vendor'],
            'orderby'  => 'registered',
            'order'    => 'ASC',
        ]);

        $vendor_ids = [];
        foreach ($vendor_users as $u) {
            $vendor_ids[] = $u->ID;
        }

        // Include any additional users with store metadata
        $meta_users = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}usermeta WHERE meta_key IN ('store_name', '_dejoiy_seller_id', 'wcfmmp_profile_settings')");
        foreach ($meta_users as $mid) {
            $mid = intval($mid);
            if ($mid > 0 && !in_array($mid, $vendor_ids)) {
                $vendor_ids[] = $mid;
            }
        }

        $list = [];
        foreach ($vendor_ids as $vid) {
            $u = get_userdata($vid);
            if (!$u) continue;

            $store = DSO_Auth::get_vendor_store($vid);
            $store_name = $store ? $store['name'] : ($u->display_name ?: $u->user_login);
            $store_slug = sanitize_title($store_name ?: $u->user_nicename);

            $is_verified = (
                get_user_meta($vid, '_wcfm_email_verified', true) === 'yes' ||
                get_user_meta($vid, 'dso_verified_seller', true) === 'yes' ||
                get_user_meta($vid, 'wcemailverified', true) === 'true'
            );

            $is_disabled = (
                get_user_meta($vid, '_disable_vendor', true) === 'yes' ||
                get_user_meta($vid, '_wcfm_store_offline', true) === 'yes' ||
                get_user_meta($vid, 'dso_store_suspended', true) === 'yes'
            );

            // Product count
            $prod_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status IN ('publish', 'draft', 'pending', 'private') AND (post_author = %d OR ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vendor_id' AND meta_value = %s))",
                $vid, strval($vid)
            ));

            // Custom commission
            $custom_comm = get_user_meta($vid, 'dso_custom_commission', true);
            if ($custom_comm === '') {
                $custom_comm = get_user_meta($vid, '_wcfm_commission_percent', true);
            }

            // Financials
            $finance = self::get_vendor_financials($vid);

            $merchant_code = get_user_meta($vid, '_dejoiy_seller_id', true) ?: sprintf('DJ-VND-%04d', $vid);
            $phone = get_user_meta($vid, 'phone', true) ?: (get_user_meta($vid, 'dso_store_phone', true) ?: ($store['phone'] ?? ''));

            $store_url = 'https://dejoiy.com/store/' . ($store_slug ?: $vid) . '/';

            $list[] = [
                'id'            => $vid,
                'merchant_code' => $merchant_code,
                'name'          => $u->display_name ?: $u->user_login,
                'email'         => $u->user_email,
                'phone'         => $phone ?: '—',
                'store_name'    => $store_name,
                'store_slug'    => $store_slug,
                'store_url'     => $store_url,
                'logo'          => $store['logo'] ?? '',
                'banner'        => $store['banner'] ?? '',
                'is_verified'   => $is_verified,
                'is_active'     => !$is_disabled,
                'commission'    => $custom_comm,
                'product_count' => $prod_count,
                'gross_sales'   => $finance['total_sales'],
                'available_bal' => $finance['available_balance'],
                'pending_bal'   => $finance['pending_buffer'],
                'registered'    => date('M j, Y', strtotime($u->user_registered)),
            ];
        }

        return $list;
    }

    /**
     * Compute vendor financials without hard WCFM dependency
     */
    public static function get_vendor_financials($vendor_id) {
        global $wpdb;

        $order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta 
             WHERE meta_key IN ('_vendor_id', '_wcfm_vendor') AND meta_value = %d",
            $vendor_id
        ));

        if (empty($order_ids)) {
            $pids = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_author = %d",
                $vendor_id
            ));
            if (!empty($pids)) {
                $pid_placeholders = implode(',', array_fill(0, count($pids), '%d'));
                $order_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT DISTINCT order_id FROM {$wpdb->prefix}woocommerce_order_items 
                     WHERE order_item_type = 'line_item' AND order_item_id IN (
                         SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                         WHERE meta_key = '_product_id' AND meta_value IN ({$pid_placeholders})
                     )",
                    ...array_map('intval', $pids)
                ));
            }
        }

        $total_sales = 0.0;
        $available_balance = 0.0;
        $pending_buffer = 0.0;
        $lifetime_paid = 0.0;

        $settings = self::get_marketplace_settings();
        $comm_percent = floatval($settings['default_commission_percent'] ?? 20.0);
        $custom_comm = get_user_meta($vendor_id, 'dso_custom_commission', true);
        if ($custom_comm !== '' && is_numeric($custom_comm)) {
            $comm_percent = floatval($custom_comm);
        }
        $vendor_cut_ratio = max(0, (100.0 - $comm_percent) / 100.0);

        if (!empty($order_ids)) {
            $now = time();
            $buffer_days = intval($settings['return_buffer_days'] ?? 7);
            $buffer_threshold = $now - ($buffer_days * 86400);

            foreach ($order_ids as $oid) {
                $order = wc_get_order($oid);
                if (!$order) continue;

                $status = $order->get_status();
                if (in_array($status, ['cancelled', 'failed', 'refunded'])) {
                    continue;
                }

                $order_total = (float) $order->get_total();
                $net_vendor_credit = $order_total * $vendor_cut_ratio;
                $total_sales += $net_vendor_credit;

                if ($status === 'completed') {
                    $date_completed = $order->get_date_completed();
                    $completed_ts = $date_completed ? $date_completed->getTimestamp() : strtotime($order->get_date_created());

                    if ($completed_ts <= $buffer_threshold) {
                        $available_balance += $net_vendor_credit;
                    } else {
                        $pending_buffer += $net_vendor_credit;
                    }
                } elseif ($status === 'processing') {
                    $pending_buffer += $net_vendor_credit;
                }
            }
        }

        $withdrawals = get_user_meta($vendor_id, 'dso_withdrawal_history', true);
        if (is_array($withdrawals)) {
            foreach ($withdrawals as $w) {
                if (($w['status'] ?? '') === 'completed') {
                    $amt = (float) ($w['amount'] ?? 0);
                    $lifetime_paid += $amt;
                    $available_balance = max(0, $available_balance - $amt);
                } elseif (($w['status'] ?? '') === 'pending') {
                    $amt = (float) ($w['amount'] ?? 0);
                    $available_balance = max(0, $available_balance - $amt);
                }
            }
        }

        return [
            'total_sales'       => $total_sales,
            'available_balance' => $available_balance,
            'pending_buffer'    => $pending_buffer,
            'lifetime_paid'     => $lifetime_paid,
        ];
    }

    /**
     * Retrieve platform-wide marketplace settings
     */
    public static function get_marketplace_settings() {
        $defaults = [
            'default_commission_percent' => 20.0,
            'fixed_fee_per_order'        => 0.0,
            'gst_on_commission'          => 'yes',
            'shipping_to_vendor'         => 'yes',
            'tax_to_vendor'              => 'yes',
            'minimum_withdrawal'         => 500.0,
            'reverse_pay_limit'          => 1000.0,
            'return_buffer_days'         => 7,
            'auto_approve_vendors'       => 'yes',
            'require_gstin'              => 'no',
            'store_url_base'             => 'store',
            'show_sold_by_pdp'           => 'yes',
            'chat_confirmed_orders_only' => 'yes',
        ];

        $saved = get_option('dejoiy_marketplace_settings', []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Save marketplace settings & sync with WCFM options if present
     */
    public static function save_marketplace_settings($data) {
        $settings = [
            'default_commission_percent' => max(0, min(100, floatval($data['default_commission_percent'] ?? 10.0))),
            'fixed_fee_per_order'        => max(0, floatval($data['fixed_fee_per_order'] ?? 0.0)),
            'gst_on_commission'          => !empty($data['gst_on_commission']) ? 'yes' : 'no',
            'shipping_to_vendor'         => !empty($data['shipping_to_vendor']) ? 'yes' : 'no',
            'tax_to_vendor'              => !empty($data['tax_to_vendor']) ? 'yes' : 'no',
            'minimum_withdrawal'         => max(100, floatval($data['minimum_withdrawal'] ?? 500.0)),
            'reverse_pay_limit'          => max(0, floatval($data['reverse_pay_limit'] ?? 1000.0)),
            'return_buffer_days'         => max(0, intval($data['return_buffer_days'] ?? 7)),
            'auto_approve_vendors'       => !empty($data['auto_approve_vendors']) ? 'yes' : 'no',
            'require_gstin'              => !empty($data['require_gstin']) ? 'yes' : 'no',
            'store_url_base'             => sanitize_title($data['store_url_base'] ?? 'store') ?: 'store',
            'show_sold_by_pdp'           => !empty($data['show_sold_by_pdp']) ? 'yes' : 'no',
            'chat_confirmed_orders_only' => !empty($data['chat_confirmed_orders_only']) ? 'yes' : 'no',
        ];

        update_option('dejoiy_marketplace_settings', $settings);

        // Sync with WCFM options if active
        $wcfm_comm = get_option('wcfm_commission_options', []);
        if (is_array($wcfm_comm)) {
            $vendor_share = 100 - $settings['default_commission_percent'];
            $wcfm_comm['commission_percent'] = strval($vendor_share);
            $wcfm_comm['get_shipping'] = ($settings['shipping_to_vendor'] === 'yes') ? 'yes' : 'no';
            $wcfm_comm['get_tax'] = ($settings['tax_to_vendor'] === 'yes') ? 'yes' : 'no';
            update_option('wcfm_commission_options', $wcfm_comm);
        }

        $wcfm_with = get_option('wcfm_withdrawal_options', []);
        if (is_array($wcfm_with)) {
            $wcfm_with['withdrawal_limit'] = strval($settings['minimum_withdrawal']);
            $wcfm_with['withdrawal_thresold'] = strval($settings['return_buffer_days']);
            $wcfm_with['withdrawal_reverse_limit'] = strval($settings['reverse_pay_limit']);
            update_option('wcfm_withdrawal_options', $wcfm_with);
        }

        return $settings;
    }

    /**
     * ─── SECTION 1: VENDORS MANAGEMENT & APPROVALS ───
     */
    public function vendors() {
        $this->check_admin();

        $notice = '';
        $notice_type = 'success';

        // 1. Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_vendor_action'])) {
            check_admin_referer('dso_marketplace_admin_action');
            $action = sanitize_text_field($_POST['dso_vendor_action']);
            $target_id = intval($_POST['vendor_id'] ?? 0);

            if ($action === 'toggle_verification' && $target_id > 0) {
                $cur = get_user_meta($target_id, 'dso_verified_seller', true);
                $new = ($cur === 'yes' || get_user_meta($target_id, '_wcfm_email_verified', true) === 'yes') ? 'no' : 'yes';
                update_user_meta($target_id, 'dso_verified_seller', $new);
                update_user_meta($target_id, '_wcfm_email_verified', $new);
                update_user_meta($target_id, 'wcemailverified', ($new === 'yes' ? 'true' : 'false'));
                $notice = "Merchant #{$target_id} verification status updated to " . ($new === 'yes' ? 'Verified ✓' : 'Unverified') . ".";
            } elseif ($action === 'toggle_status' && $target_id > 0) {
                $cur_disabled = get_user_meta($target_id, '_disable_vendor', true);
                $new_disabled = ($cur_disabled === 'yes') ? 'no' : 'yes';
                update_user_meta($target_id, '_disable_vendor', $new_disabled);
                update_user_meta($target_id, '_wcfm_store_offline', $new_disabled);
                update_user_meta($target_id, 'dso_store_suspended', $new_disabled);
                $notice = "Merchant #{$target_id} account is now " . ($new_disabled === 'yes' ? 'Suspended 🔴' : 'Active 🟢') . ".";
            } elseif ($action === 'update_commission' && $target_id > 0) {
                $comm = floatval($_POST['commission_rate'] ?? 10.0);
                update_user_meta($target_id, 'dso_custom_commission', $comm);
                update_user_meta($target_id, '_wcfm_commission_percent', $comm);
                $notice = "Commission for merchant #{$target_id} updated to {$comm}%.";
            } elseif ($action === 'add_vendor') {
                $v_email = sanitize_email($_POST['vendor_email'] ?? '');
                $v_store = sanitize_text_field($_POST['vendor_store_name'] ?? '');
                $v_name  = sanitize_text_field($_POST['vendor_owner_name'] ?? '');
                $v_phone = sanitize_text_field($_POST['vendor_phone'] ?? '');
                $v_pass  = sanitize_text_field($_POST['vendor_password'] ?? '');
                $v_comm  = floatval($_POST['vendor_commission'] ?? 10.0);

                if (empty($v_email) || !is_email($v_email)) {
                    $notice = "Please enter a valid email address.";
                    $notice_type = 'error';
                } elseif (email_exists($v_email)) {
                    $notice = "A user with this email address already exists.";
                    $notice_type = 'error';
                } else {
                    $v_user_login = sanitize_user(explode('@', $v_email)[0]);
                    if (username_exists($v_user_login)) {
                        $v_user_login .= '_' . mt_rand(10, 99);
                    }
                    if (empty($v_pass)) $v_pass = wp_generate_password(12, false);

                    $new_uid = wp_create_user($v_user_login, $v_pass, $v_email);
                    if (is_wp_error($new_uid)) {
                        $notice = $new_uid->get_error_message();
                        $notice_type = 'error';
                    } else {
                        $user_obj = new WP_User($new_uid);
                        $user_obj->set_role('wcfm_vendor');
                        if (!empty($v_name)) {
                            wp_update_user(['ID' => $new_uid, 'display_name' => $v_name]);
                        }

                        $m_code = sprintf('DJ-VND-%04d', $new_uid);
                        update_user_meta($new_uid, 'store_name', $v_store ?: ($v_name ?: $v_user_login));
                        update_user_meta($new_uid, 'wcfmmp_store_name', $v_store ?: ($v_name ?: $v_user_login));
                        update_user_meta($new_uid, 'phone', $v_phone);
                        update_user_meta($new_uid, '_dejoiy_seller_id', $m_code);
                        update_user_meta($new_uid, 'dso_verified_seller', 'yes');
                        update_user_meta($new_uid, '_wcfm_email_verified', 'yes');
                        update_user_meta($new_uid, 'wcemailverified', 'true');
                        update_user_meta($new_uid, 'dso_custom_commission', $v_comm);

                        $notice = "New Seller '{$v_store}' registered successfully with Merchant Code {$m_code}!";
                    }
                }
            }
        }

        $vendors = self::get_all_vendors();
        $total_vendors = count($vendors);
        $verified_count = 0;
        $total_listings = 0;
        $total_gmv = 0.0;

        foreach ($vendors as $v) {
            if ($v['is_verified']) $verified_count++;
            $total_listings += $v['product_count'];
            $total_gmv += $v['gross_sales'];
        }

        $filter = sanitize_text_field($_GET['filter'] ?? 'all');
        $search = strtolower(sanitize_text_field($_GET['s'] ?? ''));

        if ($filter === 'verified') {
            $vendors = array_filter($vendors, function($v) { return $v['is_verified']; });
        } elseif ($filter === 'suspended') {
            $vendors = array_filter($vendors, function($v) { return !$v['is_active']; });
        }

        if (!empty($search)) {
            $vendors = array_filter($vendors, function($v) use ($search) {
                return (
                    strpos(strtolower($v['store_name']), $search) !== false ||
                    strpos(strtolower($v['name']), $search) !== false ||
                    strpos(strtolower($v['email']), $search) !== false ||
                    strpos(strtolower($v['merchant_code']), $search) !== false
                );
            });
        }
        ?>
        <div class="dso-page dso-marketplace-vendors">
            <!-- Header -->
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>👑 Marketplace Master</span>
                        <span>/</span>
                        <span>Vendors Directory</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Vendors & Merchant Operations</h1>
                    <p class="dso-page-subtitle">Manage all registered sellers, verification credentials, commission tiers, and store authority.</p>
                </div>
                <div class="dso-page-actions">
                    <button type="button" class="dso-btn dso-btn-primary" onclick="document.getElementById('dso-add-vendor-modal').style.display='flex'">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        + Add New Merchant
                    </button>
                </div>
            </div>

            <?php if (!empty($notice)): ?>
                <div class="dso-notice dso-notice-<?php echo $notice_type === 'error' ? 'error' : 'success'; ?> dso-mb-4" style="background:<?php echo $notice_type === 'error' ? '#fef2f2' : '#ecfdf5'; ?>;border:1px solid <?php echo $notice_type === 'error' ? '#ef4444' : '#10b981'; ?>;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:12px;">
                    <span style="font-size:20px;"><?php echo $notice_type === 'error' ? '⚠️' : '✓'; ?></span>
                    <strong style="color:<?php echo $notice_type === 'error' ? '#b91c1c' : '#065f46'; ?>;"><?php echo esc_html($notice); ?></strong>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Registered Merchants</span>
                    <span class="dso-stat-val"><?php echo $total_vendors; ?></span>
                    <span class="dso-stat-sub"><?php echo $verified_count; ?> Verified Stores</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Active Marketplace Listings</span>
                    <span class="dso-stat-val dso-text-primary"><?php echo number_format($total_listings); ?></span>
                    <span class="dso-stat-sub">Across all registered catalogs</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Marketplace GMV Volume</span>
                    <span class="dso-stat-val dso-text-success">₹<?php echo number_format($total_gmv, 2); ?></span>
                    <span class="dso-stat-sub">Vendor delivered sales</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Marketplace Engine</span>
                    <span class="dso-stat-val" style="color:#8b5cf6;">Native OS</span>
                    <span class="dso-stat-sub">100% WCFM-Free Ready</span>
                </div>
            </div>

            <!-- Filter & Search Toolbar -->
            <div class="dso-card dso-mb-4" style="padding:16px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="?section=marketplace-vendors&filter=all" class="dso-btn <?php echo $filter === 'all' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">All Stores (<?php echo $total_vendors; ?>)</a>
                        <a href="?section=marketplace-vendors&filter=verified" class="dso-btn <?php echo $filter === 'verified' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">Verified (<?php echo $verified_count; ?>)</a>
                        <a href="?section=marketplace-vendors&filter=suspended" class="dso-btn <?php echo $filter === 'suspended' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">Suspended</a>
                    </div>
                    <form method="get" action="" style="display:flex;gap:8px;">
                        <input type="hidden" name="section" value="marketplace-vendors" />
                        <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>" />
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search store, owner, email..." class="dso-input" style="width:240px;padding:7px 12px;font-size:13px;" />
                        <button type="submit" class="dso-btn dso-btn-outline" style="padding:7px 14px;">Search</button>
                    </form>
                </div>
            </div>

            <!-- Vendors Listing Table -->
            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Store & Merchant</th>
                                    <th>Owner & Contact</th>
                                    <th>Verification</th>
                                    <th>Status</th>
                                    <th>Products</th>
                                    <th>Commission</th>
                                    <th>Gross Sales</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vendors)): ?>
                                    <tr>
                                        <td colspan="8" class="dso-text-center dso-p-4 dso-text-muted">
                                            No sellers found matching criteria.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($vendors as $v): ?>
                                        <tr>
                                            <td>
                                                <div style="display:flex;align-items:center;gap:12px;">
                                                    <div style="width:40px;height:40px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-weight:800;color:#001553;font-size:16px;overflow:hidden;border:1px solid #e2e8f0;">
                                                        <?php if (!empty($v['logo'])): ?>
                                                            <img src="<?php echo esc_url($v['logo']); ?>" style="width:100%;height:100%;object-fit:cover;" alt="" />
                                                        <?php else: ?>
                                                            <?php echo strtoupper(substr($v['store_name'], 0, 1)); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <strong style="display:block;font-size:14px;color:#0f172a;"><?php echo esc_html($v['store_name']); ?></strong>
                                                        <div style="display:flex;gap:6px;align-items:center;margin-top:2px;">
                                                            <span style="font-family:monospace;font-size:11px;color:#64748b;background:#f1f5f9;padding:1px 5px;border-radius:4px;"><?php echo esc_html($v['merchant_code']); ?></span>
                                                            <a href="<?php echo esc_url($v['store_url']); ?>" target="_blank" rel="noopener" style="font-size:11px;color:#0066ff;text-decoration:none;" title="Open live store">Storefront ↗</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size:13px;color:#1e293b;font-weight:600;"><?php echo esc_html($v['name']); ?></div>
                                                <div style="font-size:12px;color:#64748b;"><?php echo esc_html($v['email']); ?></div>
                                                <div style="font-size:11px;color:#94a3b8;"><?php echo esc_html($v['phone']); ?></div>
                                            </td>
                                            <td>
                                                <form method="post" style="display:inline;">
                                                    <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                                                    <input type="hidden" name="dso_vendor_action" value="toggle_verification" />
                                                    <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>" />
                                                    <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;" title="Click to toggle verification status">
                                                        <?php if ($v['is_verified']): ?>
                                                            <span class="dso-badge dso-badge-green" style="cursor:pointer;">✓ Verified</span>
                                                        <?php else: ?>
                                                            <span class="dso-badge dso-badge-gray" style="cursor:pointer;">○ Unverified</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <form method="post" style="display:inline;">
                                                    <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                                                    <input type="hidden" name="dso_vendor_action" value="toggle_status" />
                                                    <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>" />
                                                    <button type="submit" style="background:none;border:none;cursor:pointer;padding:0;" title="Click to toggle account suspension">
                                                        <?php if ($v['is_active']): ?>
                                                            <span class="dso-badge dso-badge-green" style="cursor:pointer;">🟢 Active</span>
                                                        <?php else: ?>
                                                            <span class="dso-badge dso-badge-danger" style="cursor:pointer;">🔴 Suspended</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <a href="?section=products&author=<?php echo $v['id']; ?>" style="font-weight:700;color:#0066ff;text-decoration:none;">
                                                    <?php echo $v['product_count']; ?> listings
                                                </a>
                                            </td>
                                            <td>
                                                <form method="post" style="display:flex;align-items:center;gap:4px;">
                                                    <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                                                    <input type="hidden" name="dso_vendor_action" value="update_commission" />
                                                    <input type="hidden" name="vendor_id" value="<?php echo $v['id']; ?>" />
                                                    <input type="number" step="0.5" min="0" max="100" name="commission_rate" value="<?php echo esc_attr($v['commission'] !== '' ? $v['commission'] : '10'); ?>" style="width:55px;padding:3px 6px;font-size:12px;border:1px solid #cbd5e1;border-radius:6px;text-align:right;" />
                                                    <span style="font-size:12px;color:#64748b;">%</span>
                                                    <button type="submit" class="dso-btn dso-btn-outline" style="padding:2px 6px;font-size:11px;" title="Save commission rate">✓</button>
                                                </form>
                                            </td>
                                            <td>
                                                <strong style="color:#0f172a;font-size:13px;">₹<?php echo number_format($v['gross_sales'], 2); ?></strong>
                                                <small style="display:block;color:#10b981;font-size:11px;">Cleared: ₹<?php echo number_format($v['available_bal'], 2); ?></small>
                                            </td>
                                            <td style="text-align:right;">
                                                <div style="display:inline-flex;gap:6px;">
                                                    <a href="?switch_vendor=<?php echo $v['id']; ?>" class="dso-btn dso-btn-outline" style="font-size:11px;padding:4px 8px;border-color:#8b5cf6;color:#8b5cf6;" title="Operate Seller Hub as this vendor">
                                                        👑 Manage
                                                    </a>
                                                    <a href="<?php echo esc_url($v['store_url']); ?>" target="_blank" rel="noopener" class="dso-btn dso-btn-outline" style="font-size:11px;padding:4px 8px;" title="View Live Storefront">
                                                        Store ↗
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add New Vendor Modal -->
            <div id="dso-add-vendor-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:9999;align-items:center;justify-content:center;backdrop-filter:blur(3px);">
                <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;padding:28px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                        <h3 style="margin:0;font-size:18px;font-weight:800;color:#0f172a;">Register New Marketplace Merchant</h3>
                        <button type="button" onclick="document.getElementById('dso-add-vendor-modal').style.display='none'" style="background:none;border:none;font-size:20px;cursor:pointer;color:#94a3b8;">&times;</button>
                    </div>
                    <form method="post">
                        <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                        <input type="hidden" name="dso_vendor_action" value="add_vendor" />

                        <div class="dso-form-group dso-mb-3">
                            <label class="dso-label">Store / Brand Name *</label>
                            <input type="text" name="vendor_store_name" required class="dso-input" placeholder="e.g., Royal Krafts Mumbai" />
                        </div>

                        <div class="dso-grid-2 dso-mb-3">
                            <div class="dso-form-group">
                                <label class="dso-label">Owner Name</label>
                                <input type="text" name="vendor_owner_name" class="dso-input" placeholder="e.g., Rajesh Kumar" />
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">Contact Phone</label>
                                <input type="text" name="vendor_phone" class="dso-input" placeholder="e.g., 9876543210" />
                            </div>
                        </div>

                        <div class="dso-form-group dso-mb-3">
                            <label class="dso-label">Seller Email (Login User) *</label>
                            <input type="email" name="vendor_email" required class="dso-input" placeholder="merchant@brand.com" />
                        </div>

                        <div class="dso-grid-2 dso-mb-4">
                            <div class="dso-form-group">
                                <label class="dso-label">Password (Optional)</label>
                                <input type="password" name="vendor_password" class="dso-input" placeholder="Auto-generated if empty" />
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">Commission Cut (%)</label>
                                <input type="number" step="0.5" name="vendor_commission" value="10.0" class="dso-input" />
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:10px;">
                            <button type="button" class="dso-btn dso-btn-outline" onclick="document.getElementById('dso-add-vendor-modal').style.display='none'">Cancel</button>
                            <button type="submit" class="dso-btn dso-btn-primary">Register Merchant ✓</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * ─── SECTION 2: PAYOUT & WITHDRAWAL DISBURSAL CENTER ───
     */
    public function withdrawals() {
        $this->check_admin();

        $notice = '';
        $notice_type = 'success';

        // Handle payout approvals / rejections
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_withdrawal_action'])) {
            check_admin_referer('dso_marketplace_admin_action');
            $action = sanitize_text_field($_POST['dso_withdrawal_action']);
            $target_vendor = intval($_POST['vendor_id'] ?? 0);
            $wth_id = sanitize_text_field($_POST['withdrawal_id'] ?? '');

            if ($target_vendor > 0 && !empty($wth_id)) {
                $history = get_user_meta($target_vendor, 'dso_withdrawal_history', true);
                if (is_array($history)) {
                    $found = false;
                    foreach ($history as &$entry) {
                        if (($entry['id'] ?? '') === $wth_id) {
                            if ($action === 'approve_withdrawal') {
                                $entry['status'] = 'completed';
                                $entry['utr'] = sanitize_text_field($_POST['payout_utr'] ?? ('IMPS-' . mt_rand(100000000, 999999999)));
                                $entry['completed_date'] = current_time('mysql');
                                $entry['admin_note'] = sanitize_text_field($_POST['payout_note'] ?? 'Disbursed via DEJOIY Treasury RBI IMPS');
                                $notice = "Payout request #{$wth_id} approved & marked as Disbursed (UTR: {$entry['utr']}).";
                            } elseif ($action === 'reject_withdrawal') {
                                $entry['status'] = 'rejected';
                                $entry['rejected_date'] = current_time('mysql');
                                $entry['admin_note'] = sanitize_text_field($_POST['payout_note'] ?? 'Rejected by marketplace administration');
                                $notice = "Payout request #{$wth_id} rejected. Funds returned to vendor available balance.";
                            }
                            $found = true;
                            break;
                        }
                    }
                    if ($found) {
                        update_user_meta($target_vendor, 'dso_withdrawal_history', $history);
                    }
                }
            }
        }

        // Aggregate all requests across all vendors
        $vendors = self::get_all_vendors();
        $all_requests = [];
        $pending_count = 0;
        $pending_amount = 0.0;
        $lifetime_disbursed = 0.0;

        foreach ($vendors as $v) {
            $vid = $v['id'];
            $history = get_user_meta($vid, 'dso_withdrawal_history', true);
            if (is_array($history)) {
                foreach ($history as $h) {
                    $st = $h['status'] ?? 'pending';
                    $amt = floatval($h['amount'] ?? 0);
                    if ($st === 'pending') {
                        $pending_count++;
                        $pending_amount += $amt;
                    } elseif ($st === 'completed') {
                        $lifetime_disbursed += $amt;
                    }

                    $all_requests[] = [
                        'id'             => $h['id'] ?? 'WTH-UNKNOWN',
                        'vendor_id'      => $vid,
                        'vendor_name'    => $v['store_name'],
                        'merchant_code'  => $v['merchant_code'],
                        'amount'         => $amt,
                        'status'         => $st,
                        'date'           => $h['date'] ?? '—',
                        'utr'            => $h['utr'] ?? '',
                        'note'           => $h['admin_note'] ?? '',
                        'beneficiary'    => get_user_meta($vid, 'dso_bank_beneficiary', true) ?: $v['name'],
                        'bank_name'      => get_user_meta($vid, 'dso_bank_name', true) ?: 'Bank Account',
                        'account_number' => get_user_meta($vid, 'dso_bank_account_number', true) ?: '—',
                        'ifsc'           => get_user_meta($vid, 'dso_bank_ifsc', true) ?: '—',
                        'upi'            => get_user_meta($vid, 'dso_bank_upi', true) ?: '',
                        'pan'            => get_user_meta($vid, 'dso_pan', true) ?: '',
                    ];
                }
            }
        }

        usort($all_requests, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        $st_filter = sanitize_text_field($_GET['status'] ?? 'all');
        if ($st_filter !== 'all') {
            $all_requests = array_filter($all_requests, function($r) use ($st_filter) {
                return $r['status'] === $st_filter;
            });
        }
        ?>
        <div class="dso-page dso-marketplace-withdrawals">
            <!-- Header -->
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>👑 Marketplace Master</span>
                        <span>/</span>
                        <span>Treasury Payouts</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Payouts & Settlement Disbursals</h1>
                    <p class="dso-page-subtitle">Review seller withdrawal requests, verify RBI bank IFSC & UPI credentials, and authorize IMPS/NEFT transfers.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=marketplace-settings" class="dso-btn dso-btn-outline">Settlement Rules</a>
                </div>
            </div>

            <?php if (!empty($notice)): ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:10px;padding:14px 20px;">
                    <strong style="color:#065f46;"><?php echo esc_html($notice); ?></strong>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Pending Payout Queue</span>
                    <span class="dso-stat-val dso-text-warning">₹<?php echo number_format($pending_amount, 2); ?></span>
                    <span class="dso-stat-sub"><?php echo $pending_count; ?> requests awaiting disbursal</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Total Disbursed All-Time</span>
                    <span class="dso-stat-val dso-text-success">₹<?php echo number_format($lifetime_disbursed, 2); ?></span>
                    <span class="dso-stat-sub">Direct bank IMPS/NEFT transfers</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Payout Protocol</span>
                    <span class="dso-stat-val" style="color:#0066ff;">Direct IMPS</span>
                    <span class="dso-stat-sub">RBI 24x7 Banking Rail</span>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="dso-card dso-mb-4" style="padding:14px 20px;">
                <div style="display:flex;gap:8px;">
                    <a href="?section=marketplace-withdrawals&status=all" class="dso-btn <?php echo $st_filter === 'all' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">All Requests</a>
                    <a href="?section=marketplace-withdrawals&status=pending" class="dso-btn <?php echo $st_filter === 'pending' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">Pending (<?php echo $pending_count; ?>)</a>
                    <a href="?section=marketplace-withdrawals&status=completed" class="dso-btn <?php echo $st_filter === 'completed' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">Disbursed</a>
                    <a href="?section=marketplace-withdrawals&status=rejected" class="dso-btn <?php echo $st_filter === 'rejected' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="font-size:12px;padding:6px 14px;">Rejected</a>
                </div>
            </div>

            <!-- Withdrawals Queue Table -->
            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Request #</th>
                                    <th>Merchant Store</th>
                                    <th>Amount</th>
                                    <th>Banking & Payment Details</th>
                                    <th>Status</th>
                                    <th>Transfer Ref (UTR)</th>
                                    <th style="text-align:right;">Authorize Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($all_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="dso-p-4 dso-text-center dso-text-muted">
                                            No withdrawal requests recorded in this filter view.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_requests as $req): ?>
                                        <tr>
                                            <td>
                                                <strong style="font-family:monospace;font-size:13px;"><?php echo esc_html($req['id']); ?></strong>
                                                <small style="display:block;color:#94a3b8;font-size:11px;"><?php echo esc_html($req['date']); ?></small>
                                            </td>
                                            <td>
                                                <strong style="display:block;font-size:13px;"><?php echo esc_html($req['vendor_name']); ?></strong>
                                                <span style="font-family:monospace;font-size:11px;color:#64748b;"><?php echo esc_html($req['merchant_code']); ?></span>
                                            </td>
                                            <td>
                                                <strong style="font-size:15px;color:#0f172a;">₹<?php echo number_format($req['amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <div style="font-size:12px;color:#334155;">
                                                    <strong><?php echo esc_html($req['beneficiary']); ?></strong> • <?php echo esc_html($req['bank_name']); ?>
                                                </div>
                                                <div style="font-size:11px;font-family:monospace;color:#64748b;">
                                                    A/C: <?php echo esc_html($req['account_number']); ?> | IFSC: <?php echo esc_html($req['ifsc']); ?>
                                                </div>
                                                <?php if (!empty($req['upi'])): ?>
                                                    <div style="font-size:11px;color:#0066ff;">UPI: <?php echo esc_html($req['upi']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($req['status'] === 'completed'): ?>
                                                    <span class="dso-badge dso-badge-green">✓ Disbursed</span>
                                                <?php elseif ($req['status'] === 'pending'): ?>
                                                    <span class="dso-badge dso-badge-yellow">🟡 Pending</span>
                                                <?php else: ?>
                                                    <span class="dso-badge dso-badge-danger">🔴 Rejected</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!empty($req['utr'])): ?>
                                                    <span style="font-family:monospace;font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;"><?php echo esc_html($req['utr']); ?></span>
                                                <?php else: ?>
                                                    <span style="color:#94a3b8;font-size:12px;">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:right;">
                                                <?php if ($req['status'] === 'pending'): ?>
                                                    <div style="display:inline-flex;gap:6px;">
                                                        <form method="post" style="display:inline;">
                                                            <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                                                            <input type="hidden" name="dso_withdrawal_action" value="approve_withdrawal" />
                                                            <input type="hidden" name="vendor_id" value="<?php echo $req['vendor_id']; ?>" />
                                                            <input type="hidden" name="withdrawal_id" value="<?php echo esc_attr($req['id']); ?>" />
                                                            <button type="submit" class="dso-btn dso-btn-sm dso-btn-primary" style="font-size:11px;padding:4px 10px;" title="Approve & Disburse Funds">
                                                                ✓ Disburse
                                                            </button>
                                                        </form>
                                                        <form method="post" style="display:inline;">
                                                            <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                                                            <input type="hidden" name="dso_withdrawal_action" value="reject_withdrawal" />
                                                            <input type="hidden" name="vendor_id" value="<?php echo $req['vendor_id']; ?>" />
                                                            <input type="hidden" name="withdrawal_id" value="<?php echo esc_attr($req['id']); ?>" />
                                                            <button type="submit" class="dso-btn dso-btn-sm dso-btn-outline" style="font-size:11px;padding:4px 8px;color:#ef4444;border-color:#ef4444;" title="Reject and refund balance">
                                                                ✕
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="font-size:12px;color:#94a3b8;">Processed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * ─── SECTION 3: MARKETPLACE ENGINE & COMMISSION SETTINGS ───
     */
    public function settings() {
        $this->check_admin();

        $notice = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_save_marketplace_settings'])) {
            check_admin_referer('dso_marketplace_admin_action');
            self::save_marketplace_settings($_POST);
            $notice = 'Marketplace engine configurations & settlement parameters updated successfully!';
        }

        $s = self::get_marketplace_settings();
        ?>
        <div class="dso-page dso-marketplace-settings">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>👑 Marketplace Master</span>
                        <span>/</span>
                        <span>Engine Settings</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Engine & Commission Architecture</h1>
                    <p class="dso-page-subtitle">Configure marketplace commissions, withdrawal lock thresholds, seller onboarding standards, and public storefront rules.</p>
                </div>
            </div>

            <?php if (!empty($notice)): ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:10px;padding:14px 20px;">
                    <strong style="color:#065f46;">✓ <?php echo esc_html($notice); ?></strong>
                </div>
            <?php endif; ?>

            <form method="post">
                <?php wp_nonce_field('dso_marketplace_admin_action'); ?>
                <input type="hidden" name="dso_save_marketplace_settings" value="1" />

                <div class="dso-grid-2 dso-mb-4">
                    <!-- Commission Engine Card -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">💰 Commission & Marketplace Revenue</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Default Marketplace Commission Cut (%)</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" step="0.1" min="0" max="100" name="default_commission_percent" value="<?php echo esc_attr($s['default_commission_percent']); ?>" class="dso-input" style="max-width:140px;" />
                                    <span style="font-weight:700;color:#64748b;">% per order line item</span>
                                </div>
                                <small class="dso-text-muted">Applies to all products unless overridden individually on a merchant profile.</small>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Fixed Transaction Processing Fee (₹)</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" step="1" min="0" name="fixed_fee_per_order" value="<?php echo esc_attr($s['fixed_fee_per_order']); ?>" class="dso-input" style="max-width:140px;" />
                                    <span style="font-weight:700;color:#64748b;">₹ fixed platform charge</span>
                                </div>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="gst_on_commission" value="yes" <?php checked($s['gst_on_commission'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Enable 18% GST on Marketplace Commission (TCS / GST Invoicing)</span>
                                </label>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="shipping_to_vendor" value="yes" <?php checked($s['shipping_to_vendor'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Disburse shipping fees to vendor (Vendor fulfills shipment)</span>
                                </label>
                            </div>

                            <div class="dso-form-group">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="tax_to_vendor" value="yes" <?php checked($s['tax_to_vendor'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Disburse product GST/taxes to vendor for self-remittance</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Treasury & Payout Rules -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">🏦 Settlements & Treasury Rules</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Minimum Early Payout Threshold (₹)</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" step="50" min="100" name="minimum_withdrawal" value="<?php echo esc_attr($s['minimum_withdrawal']); ?>" class="dso-input" style="max-width:140px;" />
                                    <span style="font-weight:700;color:#64748b;">₹ minimum cleared balance</span>
                                </div>
                                <small class="dso-text-muted">Minimum cleared earnings required before a seller can submit a withdrawal request.</small>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Return Window Buffer Lock (Days)</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" step="1" min="0" max="30" name="return_buffer_days" value="<?php echo esc_attr($s['return_buffer_days']); ?>" class="dso-input" style="max-width:140px;" />
                                    <span style="font-weight:700;color:#64748b;">Days post-delivery</span>
                                </div>
                                <small class="dso-text-muted">Earnings remain locked in 'Pending Buffer' until the customer return window expires.</small>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Reverse Pay Balance Threshold (₹)</label>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <input type="number" step="100" min="0" name="reverse_pay_limit" value="<?php echo esc_attr($s['reverse_pay_limit']); ?>" class="dso-input" style="max-width:140px;" />
                                    <span style="font-weight:700;color:#64748b;">₹ threshold limit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dso-grid-2 dso-mb-4">
                    <!-- Seller Onboarding & Policies -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">🛡️ Seller Onboarding & Compliance</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-group dso-mb-3">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="auto_approve_vendors" value="yes" <?php checked($s['auto_approve_vendors'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Auto-Approve Seller Registrations</span>
                                </label>
                                <small class="dso-text-muted" style="display:block;margin-left:24px;">When unchecked, new merchant accounts require manual admin activation before going live.</small>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="require_gstin" value="yes" <?php checked($s['require_gstin'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Require Verified GSTIN & PAN for Product Publishing</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Storefront & Public Display -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">🌐 Storefront & PDP Integrations</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Storefront URL Base Slug</label>
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:13px;color:#64748b;">https://dejoiy.com/</span>
                                    <input type="text" name="store_url_base" value="<?php echo esc_attr($s['store_url_base']); ?>" class="dso-input" style="max-width:120px;" />
                                    <span style="font-size:13px;color:#64748b;">/[seller-name]/</span>
                                </div>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="show_sold_by_pdp" value="yes" <?php checked($s['show_sold_by_pdp'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Show 'Sold By' Badge on Product Pages (Below Buy Box)</span>
                                </label>
                            </div>

                            <div class="dso-form-group">
                                <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                                    <input type="checkbox" name="chat_confirmed_orders_only" value="yes" <?php checked($s['chat_confirmed_orders_only'], 'yes'); ?> />
                                    <span style="font-weight:600;font-size:13px;color:#1e293b;">Restrict Buyer-Seller Chat to Confirmed Order Customers (Amazon Standard)</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="dso-btn dso-btn-primary" style="padding:12px 32px;font-size:15px;font-weight:700;">
                        Save Marketplace Configuration ✓
                    </button>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * ─── SECTION 4: PLATFORM COMMISSION & REVENUE LEDGER ───
     */
    public function ledger() {
        $this->check_admin();

        $orders = wc_get_orders([
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);

        $settings = self::get_marketplace_settings();
        $comm_percent = floatval($settings['default_commission_percent'] ?? 20.0);

        $total_gmv = 0.0;
        $total_platform_fee = 0.0;
        $total_vendor_payout = 0.0;

        foreach ($orders as $ord) {
            $amt = (float) $ord->get_total();
            $fee = $amt * ($comm_percent / 100.0) * 1.18;
            $total_gmv += $amt;
            $total_platform_fee += $fee;
            $total_vendor_payout += ($amt - $fee);
        }
        ?>
        <div class="dso-page dso-marketplace-ledger">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>👑 Marketplace Master</span>
                        <span>/</span>
                        <span>Platform Revenue</span>
                    </div>
                    <h1 class="dso-page-title">Platform Revenue Ledger & Order Split Audit</h1>
                    <p class="dso-page-subtitle">Real-time marketplace revenue tracking, fee deductions, and vendor payout allocations across all transactions.</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Total Volume Audited</span>
                    <span class="dso-stat-val">₹<?php echo number_format($total_gmv, 2); ?></span>
                    <span class="dso-stat-sub">Recent 50 marketplace orders</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Marketplace Revenue Cut</span>
                    <span class="dso-stat-val dso-text-primary">₹<?php echo number_format($total_platform_fee, 2); ?></span>
                    <span class="dso-stat-sub">Platform commission earned (<?php echo $comm_percent; ?>%)</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Allocated to Sellers</span>
                    <span class="dso-stat-val dso-text-success">₹<?php echo number_format($total_vendor_payout, 2); ?></span>
                    <span class="dso-stat-sub">Net seller credit pool</span>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Gross Amount</th>
                                    <th>DEJOIY Fee (<?php echo $comm_percent; ?>%)</th>
                                    <th>Vendor Net Payout</th>
                                    <th>Order Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders)): ?>
                                    <tr>
                                        <td colspan="7" class="dso-p-4 dso-text-center dso-text-muted">No orders available.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $ord): 
                                        $gross = (float) $ord->get_total();
                                        $fee = $gross * ($comm_percent / 100.0) * 1.18;
                                        $net = $gross - $fee;
                                        $st = $ord->get_status();
                                        $badge = ($st === 'completed') ? 'dso-badge-green' : (($st === 'processing') ? 'dso-badge-blue' : 'dso-badge-gray');
                                    ?>
                                        <tr>
                                            <td><strong>#<?php echo $ord->get_id(); ?></strong></td>
                                            <td style="font-size:12px;color:#64748b;"><?php echo $ord->get_date_created() ? $ord->get_date_created()->date('M j, Y') : '—'; ?></td>
                                            <td><?php echo esc_html($ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name()); ?></td>
                                            <td><strong>₹<?php echo number_format($gross, 2); ?></strong></td>
                                            <td class="dso-text-warning">₹<?php echo number_format($fee, 2); ?></td>
                                            <td class="dso-text-success"><strong>₹<?php echo number_format($net, 2); ?></strong></td>
                                            <td><span class="dso-badge <?php echo $badge; ?>"><?php echo esc_html(ucfirst($st)); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Honest landing-page social proof (no inflated placeholders).
     */
    public static function get_active_vendor_count() {
        $vendors = self::get_all_vendors();
        $n = 0;
        if ( is_array( $vendors ) ) {
            foreach ( $vendors as $v ) {
                if ( empty( $v['is_active'] ) ) {
                    continue;
                }
                $n++;
            }
        }
        return $n;
    }

    public static function get_total_product_count() {
        $counts = wp_count_posts( 'product' );
        return isset( $counts->publish ) ? (int) $counts->publish : 0;
    }

    public static function get_total_fulfilled_orders() {
        if ( function_exists( 'wc_orders_count' ) ) {
            return (int) wc_orders_count( 'completed' ) + (int) wc_orders_count( 'processing' );
        }
        $counts = wp_count_posts( 'shop_order' );
        $n = 0;
        if ( $counts ) {
            foreach ( array( 'wc-completed', 'completed', 'wc-processing', 'processing' ) as $st ) {
                if ( isset( $counts->$st ) ) {
                    $n += (int) $counts->$st;
                }
            }
        }
        return $n;
    }
}
