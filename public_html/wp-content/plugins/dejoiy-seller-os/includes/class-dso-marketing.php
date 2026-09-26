<?php
/**
 * DSO Marketing - Coupons & Promotions
 */
if (!defined('ABSPATH')) exit;

class DSO_Marketing {

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $coupons = $this->get_vendor_coupons($vendor_id);

        // Handle coupon creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_create_coupon'])) {
            check_admin_referer('dso_coupon');

            $code = sanitize_text_field($_POST['coupon_code']);
            $discount = floatval($_POST['coupon_amount']);
            $type = sanitize_text_field($_POST['discount_type']);
            $expiry = sanitize_text_field($_POST['coupon_expiry']);

            $coupon = new WC_Coupon();
            $coupon->set_code($code);
            $coupon->set_discount_type($type);
            $coupon->set_amount($discount);
            if ($expiry) $coupon->set_date_expires(strtotime($expiry));
            $coupon->set_usage_limit(intval($_POST['usage_limit'] ?? 0));
            $coupon->save();

            // Associate with vendor
            update_post_meta($coupon->get_id(), '_vendor_id', $vendor_id);

            wp_redirect('?section=marketing&created=1');
            exit;
        }

        ?>
        <div class="dso-page dso-marketing">
            <div class="dso-page-header">
                <div>
                    <h1>Marketing</h1>
                    <p>Manage coupons and promotions for your store</p>
                </div>
            </div>

            <div class="dso-grid-2">
                <!-- Create Coupon -->
                <div class="dso-card">
                    <div class="dso-card-header"><h3>Create Coupon</h3></div>
                    <div class="dso-card-body">
                        <form method="post" class="dso-form">
                            <?php wp_nonce_field('dso_coupon'); ?>
                            <div class="dso-form-group">
                                <label for="coupon_code">Coupon Code *</label>
                                <input type="text" id="coupon_code" name="coupon_code" class="dso-input" required placeholder="e.g. SUMMER20" style="text-transform:uppercase;" />
                            </div>
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="discount_type">Type</label>
                                    <select id="discount_type" name="discount_type" class="dso-select">
                                        <option value="percent">Percentage (%)</option>
                                        <option value="fixed_cart">Fixed Amount (₹)</option>
                                    </select>
                                </div>
                                <div class="dso-form-group">
                                    <label for="coupon_amount">Value *</label>
                                    <input type="number" id="coupon_amount" name="coupon_amount" class="dso-input" step="0.01" min="0" required placeholder="0" />
                                </div>
                            </div>
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="coupon_expiry">Expiry Date</label>
                                    <input type="date" id="coupon_expiry" name="coupon_expiry" class="dso-input" />
                                </div>
                                <div class="dso-form-group">
                                    <label for="usage_limit">Usage Limit</label>
                                    <input type="number" id="usage_limit" name="usage_limit" class="dso-input" min="0" placeholder="Unlimited" />
                                </div>
                            </div>
                            <button type="submit" name="dso_create_coupon" value="1" class="dso-btn dso-btn-primary dso-btn-full">
                                Create Coupon
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Existing Coupons -->
                <div class="dso-card">
                    <div class="dso-card-header"><h3>Active Coupons</h3></div>
                    <div class="dso-card-body">
                        <?php if (empty($coupons)): ?>
                            <div class="dso-empty-inline">
                                <p>No coupons created yet</p>
                            </div>
                        <?php else: ?>
                            <div class="dso-coupon-list">
                                <?php foreach ($coupons as $c): ?>
                                    <div class="dso-coupon-item">
                                        <div class="dso-coupon-code"><?php echo esc_html($c['code']) ?></div>
                                        <div class="dso-coupon-details">
                                            <span><?php echo esc_html($c['type_label']) ?></span>
                                            <span><?php echo esc_html($c['expiry'] ?: 'No expiry') ?></span>
                                            <span>Used: <?php echo $c['usage_count'] ?> times</span>
                                        </div>
                                        <span class="dso-badge <?php echo $c['is_active'] ? 'dso-badge-green' : 'dso-badge-gray' ?>">
                                            <?php echo $c['is_active'] ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_vendor_coupons($vendor_id) {
        global $wpdb;
        if (!$vendor_id) return [];

        $coupon_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->prefix}postmeta
            WHERE meta_key = '_vendor_id' AND meta_value = %d",
            $vendor_id
        ));

        $coupons = [];
        foreach ($coupon_ids as $cid) {
            $coupon = new WC_Coupon($cid);
            $coupons[] = [
                'code' => $coupon->get_code(),
                'type' => $coupon->get_discount_type(),
                'type_label' => $coupon->get_discount_type() === 'percent' ? $coupon->get_amount() . '%' : '₹' . $coupon->get_amount(),
                'amount' => $coupon->get_amount(),
                'expiry' => $coupon->get_date_expires() ? date('M j, Y', $coupon->get_date_expires()->getTimestamp()) : '',
                'usage_count' => $coupon->get_usage_count(),
                'is_active' => $coupon->get_date_expires() === null || $coupon->get_date_expires()->getTimestamp() > time(),
            ];
        }
        return $coupons;
    }
    /**
     * Coupons and deals overview
     */
    public function coupons_deals() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Coupons &amp; Deals</h1><p class="dso-ph-sub">Create and manage coupons and promotional deals.</p></div>
            <div class="dso-ph-card"><h2>No coupons or deals yet</h2><p>Create your first coupon to start running promotions.</p></div>
        </div>
        <?php
    }

}
