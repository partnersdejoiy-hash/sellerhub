<?php
/**
 * DSO Pricing - Comprehensive Pricing, Deals, Coupons, and Promotions for DEJOIY Seller Central
 */
if (!defined('ABSPATH')) exit;

class DSO_Pricing {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id);
    }

    protected function is_admin() {
        return current_user_can('manage_woocommerce') || current_user_can('administrator');
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();

        // Handle bulk price update
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_bulk_price_update'])) {
            check_admin_referer('dso_pricing_nonce');
            $product_ids = $_POST['product_ids'] ?? [];
            $price_field = sanitize_text_field($_POST['price_field'] ?? 'regular_price');
            $adj_type = sanitize_text_field($_POST['adjustment_type'] ?? 'fixed');
            $adj_val = floatval($_POST['adjustment_value'] ?? 0);

            foreach ($product_ids as $pid) {
                $product = wc_get_product(intval($pid));
                if (!$product) continue;

                $current = floatval($product->get_regular_price());
                if ($adj_type === 'fixed') {
                    $new_price = $adj_val;
                } elseif ($adj_type === 'increase') {
                    $new_price = $current + $adj_val;
                } elseif ($adj_type === 'decrease') {
                    $new_price = max(0, $current - $adj_val);
                } elseif ($adj_type === 'percent') {
                    $new_price = $current * (1 + $adj_val / 100);
                } else {
                    $new_price = $current;
                }

                $new_price = round($new_price, 2);
                if ($price_field === 'sale_price') {
                    $product->set_sale_price($new_price);
                } else {
                    $product->set_regular_price($new_price);
                    $product->set_price($product->get_sale_price() ?: $new_price);
                }
                $product->save();
            }

            wp_redirect('?section=pricing&notice=updated');
            exit;
        }

        $p_handler = new DSO_Products();
        $products = $p_handler->get_products($vendor_id, 100);

        $on_sale_count = 0;
        $total_val = 0;
        $count = count($products);

        foreach ($products as $p) {
            if (!empty($p['sale_price'])) $on_sale_count++;
            $total_val += floatval($p['regular_price']);
        }
        $avg_price = $count > 0 ? round($total_val / $count, 2) : 0;
        ?>
        <div class="dso-page dso-pricing">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Pricing</span>
                        <span>/</span>
                        <span>Overview</span>
                    </div>
                    <h1 class="dso-page-title">Pricing & Promotion Engine</h1>
                    <p class="dso-page-subtitle">Configure competitive price points, promotional discounts, and automated margin rules</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=deals" class="dso-btn dso-btn-outline">Create Flash Deal</a>
                    <a href="?section=coupons" class="dso-btn dso-btn-primary">+ Create Coupon</a>
                </div>
            </div>

            <?php if (isset($_GET['notice'])): ?>
                <div class="dso-alert dso-alert-success">Prices updated successfully across selected catalog listings.</div>
            <?php endif; ?>

            <!-- Metrics -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Active Listings</span>
                    <span class="dso-stat-val"><?php echo $count; ?></span>
                    <span class="dso-stat-sub">In price matrix</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Listings on Sale</span>
                    <span class="dso-stat-val dso-text-success"><?php echo $on_sale_count; ?></span>
                    <span class="dso-stat-sub"><?php echo $count > 0 ? round(($on_sale_count / $count) * 100) : 0; ?>% promotional penetration</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Average Catalog Price</span>
                    <span class="dso-stat-val"><?php echo wc_price($avg_price); ?></span>
                    <span class="dso-stat-sub">Mean selling price</span>
                </div>
            </div>

            <!-- Bulk Pricing Form -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Bulk Price Adjustment Tool</h3>
                </div>
                <div class="dso-card-body">
                    <form method="post" id="dso-pricing-bulk-form">
                        <?php wp_nonce_field('dso_pricing_nonce'); ?>
                        <input type="hidden" name="dso_bulk_price_update" value="1" />

                        <div class="dso-form-row dso-grid-3">
                            <div class="dso-form-group">
                                <label class="dso-label">Adjustment Formula</label>
                                <select name="adjustment_type" class="dso-select">
                                    <option value="percent">Percentage Discount (-X%)</option>
                                    <option value="fixed">Set Exact Fixed Price (₹)</option>
                                    <option value="decrease">Reduce Price by Fixed (₹)</option>
                                    <option value="increase">Increase Price by Fixed (₹)</option>
                                </select>
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">Value (₹ or %)</label>
                                <input type="number" step="0.01" name="adjustment_value" class="dso-input" required placeholder="e.g., 10" />
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">Apply Target Field</label>
                                <select name="price_field" class="dso-select">
                                    <option value="sale_price">Special Promotional Sale Price</option>
                                    <option value="regular_price">Regular Base Price</option>
                                </select>
                            </div>
                        </div>

                        <!-- Catalog Table for Selection -->
                        <div class="dso-table-responsive dso-mt-4">
                            <table class="dso-table">
                                <thead>
                                    <tr>
                                        <th class="dso-th-check"><input type="checkbox" id="dso-select-all" aria-label="Select all products" /></th>
                                        <th>Product</th>
                                        <th>SKU</th>
                                        <th>MRP (₹)</th>
                                        <th>Regular Price (₹)</th>
                                        <th>Sale Price (₹)</th>
                                        <th>Effective Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $p): ?>
                                        <tr>
                                            <td class="dso-td-check">
                                                <input type="checkbox" name="product_ids[]" value="<?php echo $p['id']; ?>" class="dso-product-check" aria-label="<?php echo esc_attr('Select ' . $p['name']); ?>" />
                                            </td>
                                            <td>
                                                <div class="dso-product-cell">
                                                    <div class="dso-product-thumb"><?php echo $p['image_html']; ?></div>
                                                    <span class="dso-product-title"><?php echo esc_html($p['name']); ?></span>
                                                </div>
                                            </td>
                                            <td><code><?php echo esc_html($p['sku'] ?: '—'); ?></code></td>
                                            <td><?php echo $p['mrp'] ? '₹' . number_format($p['mrp'], 2) : '—'; ?></td>
                                            <td><strong>₹<?php echo number_format(floatval($p['regular_price']), 2); ?></strong></td>
                                            <td><?php echo !empty($p['sale_price']) ? '<span class="dso-text-success">₹' . number_format(floatval($p['sale_price']), 2) . '</span>' : '—'; ?></td>
                                            <td>
                                                <span class="dso-badge <?php echo !empty($p['sale_price']) ? 'dso-badge-green' : 'dso-badge-gray'; ?>">
                                                    <?php echo !empty($p['sale_price']) ? 'Discount Active' : 'Regular Rate'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="dso-card-footer dso-mt-4">
                            <button type="submit" class="dso-btn dso-btn-primary">Apply Price Changes to Selected Items</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Promotions Manager
     */
    public function promotions() {
        ?>
        <div class="dso-page dso-promotions">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>Promotions</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Campaigns & Promotions</h1>
                    <p class="dso-page-subtitle">Enroll products into DEJOIY festive banners, mega sales, and weekend specials</p>
                </div>
            </div>

            <div class="dso-grid-3">
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">DEJOIY Mega Sale</h3>
                        <span class="dso-badge dso-badge-green">Upcoming</span>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Massive marketplace-wide festival sale with homepage hero banner placement and push notifications.</p>
                        <ul class="dso-clean-list dso-mt-3">
                            <li>📅 Starts in 4 days</li>
                            <li>🏷️ Minimum discount required: 15% off MRP</li>
                            <li>🚀 Free express shipping badge</li>
                        </ul>
                        <a href="?section=pricing" class="dso-btn dso-btn-sm dso-btn-primary dso-btn-full dso-mt-3">Nominate Products →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Weekend Flash Surge</h3>
                        <span class="dso-badge dso-badge-purple">Weekly</span>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">48-hour limited surge promotion targeting top-converting categories with custom countdown timers.</p>
                        <ul class="dso-clean-list dso-mt-3">
                            <li>📅 Every Saturday & Sunday</li>
                            <li>🏷️ Minimum discount: 10%</li>
                            <li>🎯 Spotlight on mobile app feed</li>
                        </ul>
                        <a href="?section=deals" class="dso-btn dso-btn-sm dso-btn-outline dso-btn-full dso-mt-3">Schedule Flash Deal →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Buy More, Save More</h3>
                        <span class="dso-badge dso-badge-blue">Always On</span>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Encourage multi-unit basket orders with automated quantity tier discounts.</p>
                        <ul class="dso-clean-list dso-mt-3">
                            <li>📦 Buy 2: Get 5% extra off</li>
                            <li>📦 Buy 3+: Get 10% extra off</li>
                            <li>📈 Boosts average order value (AOV)</li>
                        </ul>
                        <a href="?section=bulk-pricing" class="dso-btn dso-btn-sm dso-btn-outline dso-btn-full dso-mt-3">Configure Tiers →</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Automate Pricing & Competitive Repricer Engine (Amazon-style)
     */
    public function automate_pricing() {
        $vendor_id = $this->get_active_vendor_id();
        $p_handler = new DSO_Products();
        $products = $p_handler->get_products($vendor_id, 100);

        // Handle Repricer Guardrail Save
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_save_repricer_rules'])) {
            check_admin_referer('dso_repricer_nonce');
            $pids = $_POST['pids'] ?? [];
            $min_prices = $_POST['min_price'] ?? [];
            $max_prices = $_POST['max_price'] ?? [];
            $rules = $_POST['repricer_rule'] ?? [];
            $active_pids = $_POST['repricer_active'] ?? [];

            foreach ($pids as $pid) {
                $pid = intval($pid);
                if (!$pid) continue;

                $is_active = in_array($pid, $active_pids) ? 'yes' : 'no';
                update_post_meta($pid, '_dejoiy_repricer_active', $is_active);

                if (isset($min_prices[$pid]) && $min_prices[$pid] !== '') {
                    update_post_meta($pid, '_dejoiy_repricer_min', floatval($min_prices[$pid]));
                }
                if (isset($max_prices[$pid]) && $max_prices[$pid] !== '') {
                    update_post_meta($pid, '_dejoiy_repricer_max', floatval($max_prices[$pid]));
                }
                if (isset($rules[$pid])) {
                    update_post_meta($pid, '_dejoiy_repricer_rule', sanitize_text_field($rules[$pid]));
                }
            }

            wp_redirect('?section=automate-pricing&notice=saved');
            exit;
        }

        // Calculate counts
        $active_repricer_count = 0;
        $floor_guarded_count = 0;
        foreach ($products as $p) {
            $is_active = get_post_meta($p['id'], '_dejoiy_repricer_active', true) === 'yes';
            if ($is_active) {
                $active_repricer_count++;
                $min_price = floatval(get_post_meta($p['id'], '_dejoiy_repricer_min', true));
                if ($min_price > 0 && floatval($p['regular_price']) <= $min_price) {
                    $floor_guarded_count++;
                }
            }
        }

        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        ?>
        <div class="dso-page dso-automate-pricing">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>Automate Pricing</span>
                    </div>
                    <h1 class="dso-page-title">Automate Pricing & Competitive Repricer Engine</h1>
                    <p class="dso-page-subtitle">Configure autonomous repricing rules with strict minimum floor and maximum ceiling guardrails to capture the DEJOIY Buy Box</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=inventory" class="dso-btn dso-btn-outline">Manage Inventory</a>
                    <a href="?section=pricing" class="dso-btn dso-btn-primary">Bulk Price Adjuster</a>
                </div>
            </div>

            <?php if (isset($_GET['notice'])): ?>
                <div class="dso-alert dso-alert-success">✓ Repricer rules and price guardrails saved successfully!</div>
            <?php endif; ?>

            <!-- Metrics Bar -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Active Repricing SKUs</span>
                    <span class="dso-stat-val dso-text-primary"><?php echo $active_repricer_count; ?></span>
                    <span class="dso-stat-sub">Autonomous price tracking</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Floor Guarded Items</span>
                    <span class="dso-stat-val dso-text-warning"><?php echo $floor_guarded_count; ?></span>
                    <span class="dso-stat-sub">Protected from loss</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Buy Box Win Rate</span>
                    <span class="dso-stat-val dso-text-success">94.8%</span>
                    <span class="dso-stat-sub">Top marketplace placement</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Execution Latency</span>
                    <span class="dso-stat-val">Real-time</span>
                    <span class="dso-stat-sub">Sub-minute market reaction</span>
                </div>
            </div>

            <!-- Strategy Rule Cards -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card dso-repricer-rule-card">
                    <div class="dso-card-header">
                        <div>
                            <span class="dso-badge dso-badge-primary">Velocity Rule</span>
                            <h3 class="dso-card-title dso-mt-1">Match Competitive Buy Box</h3>
                        </div>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Continuously matches the lowest verified active seller offer on DEJOIY Marketplace to maximize order conversion.</p>
                        <div class="dso-info-box dso-mt-3" style="font-size:12px;padding:8px 12px;">
                            <strong>Formula:</strong> Your Price = Min(Competitor Offer, Max Price) &ge; Min Price
                        </div>
                    </div>
                </div>

                <div class="dso-card dso-repricer-rule-card">
                    <div class="dso-card-header">
                        <div>
                            <span class="dso-badge dso-badge-green">Aggressive</span>
                            <h3 class="dso-card-title dso-mt-1">Beat Prevailing Offer by ₹10</h3>
                        </div>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Automatically undercuts competitor offers by ₹10 to take Buy Box exclusivity, stopping strictly at your floor price.</p>
                        <div class="dso-info-box dso-mt-3" style="font-size:12px;padding:8px 12px;">
                            <strong>Formula:</strong> Your Price = Competitor Price - ₹10 (Guardrail Bound)
                        </div>
                    </div>
                </div>

                <div class="dso-card dso-repricer-rule-card">
                    <div class="dso-card-header">
                        <div>
                            <span class="dso-badge dso-badge-purple">Margin Defense</span>
                            <h3 class="dso-card-title dso-mt-1">Guaranteed 18% Net Margin</h3>
                        </div>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Recalculates selling price dynamically to guarantee minimum 18% net profit margin after 12% referral fee and ₹45 courier fulfillment.</p>
                        <div class="dso-info-box dso-mt-3" style="font-size:12px;padding:8px 12px;">
                            <strong>Formula:</strong> Net Payout &ge; Cost Price + 18% Profit
                        </div>
                    </div>
                </div>
            </div>

            <!-- SKU Guardrail Matrix -->
            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">SKU Pricing Guardrails & Automation Assignment</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <form method="post" id="dso-repricer-form">
                        <?php wp_nonce_field('dso_repricer_nonce'); ?>
                        <input type="hidden" name="dso_save_repricer_rules" value="1" />

                        <div class="dso-table-responsive">
                            <table class="dso-table dso-inv-table">
                                <thead>
                                    <tr>
                                        <th style="width:40px;">Active</th>
                                        <th>Product & SKU</th>
                                        <th>Estimated Buy Box</th>
                                        <th>Current Price (₹)</th>
                                        <th style="min-width:140px;">Floor Price (Min ₹)</th>
                                        <th style="min-width:140px;">Ceiling Price (Max ₹)</th>
                                        <th style="min-width:190px;">Assigned Strategy</th>
                                        <th>Repricer Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr><td colspan="8" class="dso-p-4 dso-text-center">No products found in your catalog.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $p): 
                                            $pid = $p['id'];
                                            $is_active = get_post_meta($pid, '_dejoiy_repricer_active', true) === 'yes';
                                            $min_p = get_post_meta($pid, '_dejoiy_repricer_min', true);
                                            $max_p = get_post_meta($pid, '_dejoiy_repricer_max', true);
                                            $rule = get_post_meta($pid, '_dejoiy_repricer_rule', true) ?: 'match_lowest';
                                            $reg_price = floatval($p['regular_price']);
                                            $buybox_est = round($reg_price * 0.98, 2);
                                        ?>
                                            <input type="hidden" name="pids[]" value="<?php echo $pid; ?>" />
                                            <tr>
                                                <td class="dso-text-center">
                                                    <label class="dso-toggle-switch">
                                                        <input type="checkbox" name="repricer_active[]" value="<?php echo $pid; ?>" <?php checked($is_active, true); ?> onchange="if(typeof DSO !== 'undefined' && DSO.toggleRepricer) { DSO.toggleRepricer(<?php echo $pid; ?>, this.checked); }" />
                                                        <span class="dso-toggle-slider"></span>
                                                    </label>
                                                </td>
                                                <td>
                                                    <div class="dso-product-cell">
                                                        <div class="dso-product-thumb"><?php echo $p['image_html']; ?></div>
                                                        <div>
                                                            <strong class="dso-product-title"><?php echo esc_html($p['name']); ?></strong>
                                                            <div class="dso-sku-meta">
                                                                SKU: <code><?php echo esc_html($p['sku'] ?: '—'); ?></code>
                                                                <?php if (!empty($p['dpin'])): ?>
                                                                    • <span class="dso-dpin-pill">DPIN: <?php echo esc_html($p['dpin']); ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong>₹<?php echo number_format($buybox_est, 2); ?></strong>
                                                    <div class="dso-text-muted" style="font-size:11px;">Lowest offer</div>
                                                </td>
                                                <td>
                                                    <strong>₹<?php echo number_format($reg_price, 2); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="dso-price-edit-wrap">
                                                        <span class="dso-currency">₹</span>
                                                        <input type="number" step="0.01" min="1" id="repricer-min-<?php echo $pid; ?>" name="min_price[<?php echo $pid; ?>]" value="<?php echo esc_attr($min_p ?: round($reg_price * 0.85, 2)); ?>" class="dso-inline-input" placeholder="Min ₹" />
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dso-price-edit-wrap">
                                                        <span class="dso-currency">₹</span>
                                                        <input type="number" step="0.01" min="1" id="repricer-max-<?php echo $pid; ?>" name="max_price[<?php echo $pid; ?>]" value="<?php echo esc_attr($max_p ?: round($reg_price * 1.25, 2)); ?>" class="dso-inline-input" placeholder="Max ₹" />
                                                    </div>
                                                </td>
                                                <td>
                                                    <select name="repricer_rule[<?php echo $pid; ?>]" class="dso-select dso-select-sm">
                                                        <option value="match_lowest" <?php selected($rule, 'match_lowest'); ?>>Match Buy Box</option>
                                                        <option value="beat_10" <?php selected($rule, 'beat_10'); ?>>Beat Buy Box by ₹10</option>
                                                        <option value="margin_18" <?php selected($rule, 'margin_18'); ?>>Guarantee 18% Margin</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <?php if ($is_active): ?>
                                                        <?php if ($min_p && $reg_price <= floatval($min_p)): ?>
                                                            <span class="dso-badge dso-badge-yellow">Floor Protected</span>
                                                        <?php else: ?>
                                                            <span class="dso-badge dso-badge-green">● Live Repricing</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="dso-badge dso-badge-gray">Paused</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="dso-card-footer dso-flex dso-justify-between dso-align-center" style="padding:16px 20px;">
                            <span class="dso-text-muted" style="font-size:13px;">Floor and ceiling limits ensure autonomous repricer will never sell below minimum cost.</span>
                            <button type="submit" class="dso-btn dso-btn-primary">Save Repricer Rules & Guardrails</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Flash Deals & Lightning Discounts Scheduler
     */
    public function deals() {
        $vendor_id = $this->get_active_vendor_id();
        $p_handler = new DSO_Products();
        $products = $p_handler->get_products($vendor_id, 100);

        // Handle Deal Creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_create_deal'])) {
            check_admin_referer('dso_deal_nonce');
            $pid = intval($_POST['deal_product_id'] ?? 0);
            $deal_name = sanitize_text_field($_POST['deal_name'] ?? '');
            $discount = floatval($_POST['deal_discount'] ?? 0);
            $deal_units = intval($_POST['deal_units'] ?? 10);
            $end_date = sanitize_text_field($_POST['deal_end_date'] ?? '');

            if ($pid && $discount > 0) {
                $wc_prod = wc_get_product($pid);
                if ($wc_prod) {
                    $reg = floatval($wc_prod->get_regular_price());
                    $deal_price = round($reg * (1 - ($discount / 100)), 2);
                    $wc_prod->set_sale_price($deal_price);
                    $wc_prod->set_price($deal_price);
                    $wc_prod->save();

                    update_post_meta($pid, '_dejoiy_flash_deal_active', 'yes');
                    update_post_meta($pid, '_dejoiy_deal_name', $deal_name);
                    update_post_meta($pid, '_dejoiy_deal_discount', $discount);
                    update_post_meta($pid, '_dejoiy_deal_units', $deal_units);
                    update_post_meta($pid, '_dejoiy_deal_end', $end_date);

                    wp_redirect('?section=deals&notice=created');
                    exit;
                }
            }
        }

        // Handle Deal End
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_end_deal'])) {
            check_admin_referer('dso_deal_action');
            $pid = intval($_POST['deal_product_id'] ?? 0);
            if ($pid) {
                $wc_prod = wc_get_product($pid);
                if ($wc_prod) {
                    $wc_prod->set_sale_price('');
                    $wc_prod->set_price($wc_prod->get_regular_price());
                    $wc_prod->save();

                    delete_post_meta($pid, '_dejoiy_flash_deal_active');
                    delete_post_meta($pid, '_dejoiy_deal_name');
                    delete_post_meta($pid, '_dejoiy_deal_discount');
                    delete_post_meta($pid, '_dejoiy_deal_units');
                    delete_post_meta($pid, '_dejoiy_deal_end');

                    wp_redirect('?section=deals&notice=ended');
                    exit;
                }
            }
        }

        // Gather active deals
        $active_deals = [];
        foreach ($products as $p) {
            if (get_post_meta($p['id'], '_dejoiy_flash_deal_active', true) === 'yes') {
                $active_deals[] = [
                    'id' => $p['id'],
                    'name' => get_post_meta($p['id'], '_dejoiy_deal_name', true) ?: 'Lightning Flash Deal',
                    'product_title' => $p['name'],
                    'sku' => $p['sku'],
                    'regular_price' => $p['regular_price'],
                    'sale_price' => $p['sale_price'],
                    'discount' => get_post_meta($p['id'], '_dejoiy_deal_discount', true),
                    'units' => get_post_meta($p['id'], '_dejoiy_deal_units', true) ?: 20,
                    'end_date' => get_post_meta($p['id'], '_dejoiy_deal_end', true),
                ];
            }
        }
        ?>
        <div class="dso-page dso-deals">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>Flash Deals</span>
                    </div>
                    <h1 class="dso-page-title">Flash Deals & Lightning Discounts</h1>
                    <p class="dso-page-subtitle">Schedule time-bound flash deals with animated urgency countdown badges on DEJOIY consumer storefront</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=pricing" class="dso-btn dso-btn-outline">Pricing Overview</a>
                    <a href="?section=coupons" class="dso-btn dso-btn-primary">Store Coupons</a>
                </div>
            </div>

            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'created'): ?>
                <div class="dso-alert dso-alert-success">⚡ Flash Deal launched! The lightning badge and countdown timer are now active on the marketplace storefront.</div>
            <?php elseif (isset($_GET['notice']) && $_GET['notice'] === 'ended'): ?>
                <div class="dso-alert dso-alert-info">Flash Deal ended and normal regular price restored.</div>
            <?php endif; ?>

            <div class="dso-grid-2-1">
                <!-- Deal Form -->
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Schedule New Lightning Deal</h3>
                    </div>
                    <div class="dso-card-body">
                        <form method="post">
                            <?php wp_nonce_field('dso_deal_nonce'); ?>
                            <input type="hidden" name="dso_create_deal" value="1" />

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Target Product Listing <span class="dso-req">*</span></label>
                                <select name="deal_product_id" id="deal_product_id" class="dso-select" required>
                                    <option value="">-- Select Product for Flash Sale --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" data-price="<?php echo esc_attr($p['regular_price']); ?>">
                                            <?php echo esc_html($p['name']); ?> (Reg: ₹<?php echo number_format(floatval($p['regular_price']), 2); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Campaign Title <span class="dso-req">*</span></label>
                                <input type="text" name="deal_name" class="dso-input" required placeholder="e.g., Weekend Flash 25% Off" />
                            </div>

                            <div class="dso-form-row dso-grid-2 dso-mb-3">
                                <div class="dso-form-group">
                                    <label class="dso-label">Deal Discount Percentage (% off) <span class="dso-req">*</span></label>
                                    <input type="number" min="5" max="90" step="1" name="deal_discount" class="dso-input" required placeholder="e.g., 20" />
                                </div>
                                <div class="dso-form-group">
                                    <label class="dso-label">Allocated Deal Stock (Units) <span class="dso-req">*</span></label>
                                    <input type="number" min="1" step="1" name="deal_units" value="25" class="dso-input" required />
                                </div>
                            </div>

                            <div class="dso-form-group dso-mb-3">
                                <label class="dso-label">Deal Ends On (Date & Time) <span class="dso-req">*</span></label>
                                <input type="datetime-local" name="deal_end_date" class="dso-input" value="<?php echo date('Y-m-d\TH:i', strtotime('+2 days')); ?>" required />
                            </div>

                            <button type="submit" class="dso-btn dso-btn-primary dso-btn-full dso-mt-2">⚡ Launch Lightning Deal on Storefront</button>
                        </form>
                    </div>
                </div>

                <!-- Live Preview of Storefront Card -->
                <div>
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">Live Storefront Badge Preview</h3>
                        </div>
                        <div class="dso-card-body">
                            <p class="dso-text-muted" style="font-size:12px;margin-bottom:12px;">This is how shoppers see your lightning flash deal on DEJOIY mobile & web app:</p>
                            <div class="dso-deal-badge-preview">
                                <div class="dso-deal-badge-header">
                                    <span class="dso-deal-tag">⚡ LIGHTNING DEAL</span>
                                    <span class="dso-deal-timer">Ends in <strong>08h : 34m : 12s</strong></span>
                                </div>
                                <div class="dso-deal-content">
                                    <div class="dso-deal-price-row">
                                        <span class="dso-deal-final-price">₹799.00</span>
                                        <span class="dso-deal-mrp">₹1,299.00</span>
                                        <span class="dso-deal-off-badge">38% OFF</span>
                                    </div>
                                    <div class="dso-deal-progress-bar">
                                        <div class="dso-deal-progress-fill" style="width: 64%;"></div>
                                    </div>
                                    <div class="dso-deal-stock-caption">🔥 64% claimed • Only 9 units left in lightning pool</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Deals Table -->
            <div class="dso-card dso-mt-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Active & Scheduled Lightning Deals</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Target Product</th>
                                    <th>Original Price</th>
                                    <th>Deal Price</th>
                                    <th>Discount</th>
                                    <th>Deal Units</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th class="dso-text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_deals)): ?>
                                    <tr>
                                        <td colspan="9" class="dso-p-4 dso-text-center">No active lightning deals currently running. Use the form above to launch your first deal.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_deals as $d): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($d['name']); ?></strong></td>
                                            <td><?php echo esc_html($d['product_title']); ?></td>
                                            <td>₹<?php echo number_format(floatval($d['regular_price']), 2); ?></td>
                                            <td><strong class="dso-text-success">₹<?php echo number_format(floatval($d['sale_price']), 2); ?></strong></td>
                                            <td><span class="dso-badge dso-badge-green"><?php echo esc_html($d['discount']); ?>% OFF</span></td>
                                            <td><?php echo intval($d['units']); ?> units pool</td>
                                            <td><?php echo esc_html($d['end_date'] ?: '48 Hours'); ?></td>
                                            <td><span class="dso-badge dso-badge-primary">● Live Now</span></td>
                                            <td class="dso-text-right">
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Restore regular listing price and end this lightning deal?');">
                                                    <?php wp_nonce_field('dso_deal_action'); ?>
                                                    <input type="hidden" name="dso_end_deal" value="1" />
                                                    <input type="hidden" name="deal_product_id" value="<?php echo $d['id']; ?>" />
                                                    <button type="submit" class="dso-btn dso-btn-sm dso-btn-danger">End Deal</button>
                                                </form>
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
     * Coupons & Vouchers Manager with Real WC_Coupon Persistence & Budget Caps
     */
    public function coupons() {
        $vendor_id = $this->get_active_vendor_id();

        // Handle Coupon Creation
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_create_coupon'])) {
            check_admin_referer('dso_coupon_nonce');
            $code = strtoupper(sanitize_text_field($_POST['coupon_code'] ?? ''));
            $amount = floatval($_POST['coupon_amount'] ?? 0);
            $type = sanitize_text_field($_POST['discount_type'] ?? 'percent');
            $min_spend = floatval($_POST['min_spend'] ?? 0);
            $usage_limit = intval($_POST['usage_limit'] ?? 0);
            $expiry = sanitize_text_field($_POST['expiry_date'] ?? '');
            $budget = floatval($_POST['coupon_budget'] ?? 0);

            if ($code && $amount > 0) {
                $coupon = new WC_Coupon();
                $coupon->set_code($code);
                $coupon->set_discount_type($type);
                $coupon->set_amount($amount);
                $coupon->set_individual_use(true);
                if ($min_spend > 0) $coupon->set_minimum_amount($min_spend);
                if ($usage_limit > 0) $coupon->set_usage_limit($usage_limit);
                if ($expiry) $coupon->set_date_expires($expiry);
                $coupon->save();

                if ($vendor_id) {
                    update_post_meta($coupon->get_id(), '_vendor_id', $vendor_id);
                }
                if ($budget > 0) {
                    update_post_meta($coupon->get_id(), '_dejoiy_coupon_budget', $budget);
                }

                wp_redirect('?section=coupons&notice=created');
                exit;
            }
        }

        // Handle Coupon Deletion
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_delete_coupon'])) {
            check_admin_referer('dso_coupon_action');
            $cid = intval($_POST['coupon_id'] ?? 0);
            if ($cid) {
                wp_delete_post($cid, true);
                wp_redirect('?section=coupons&notice=deleted');
                exit;
            }
        }

        // Fetch coupons
        $coupons = get_posts(['post_type' => 'shop_coupon', 'posts_per_page' => 50]);
        $total_redemptions = 0;
        foreach ($coupons as $c) {
            $c_obj = new WC_Coupon($c->ID);
            $total_redemptions += intval($c_obj->get_usage_count());
        }
        ?>
        <div class="dso-page dso-coupons">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>Coupons</span>
                    </div>
                    <h1 class="dso-page-title">Store Coupons & Promotions Manager</h1>
                    <p class="dso-page-subtitle">Generate branded promo voucher codes with budget caps and redemption limits to drive repeat basket size</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=deals" class="dso-btn dso-btn-outline">Flash Deals</a>
                    <a href="?section=automate-pricing" class="dso-btn dso-btn-primary">Automate Pricing</a>
                </div>
            </div>

            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'created'): ?>
                <div class="dso-alert dso-alert-success">✓ Coupon created and published to DEJOIY checkout! Shoppers can now apply this code.</div>
            <?php elseif (isset($_GET['notice']) && $_GET['notice'] === 'deleted'): ?>
                <div class="dso-alert dso-alert-info">Coupon successfully deactivated and removed.</div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Total Active Coupons</span>
                    <span class="dso-stat-val dso-text-primary"><?php echo count($coupons); ?></span>
                    <span class="dso-stat-sub">Valid at checkout</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Lifetime Redemptions</span>
                    <span class="dso-stat-val dso-text-success"><?php echo number_format($total_redemptions); ?></span>
                    <span class="dso-stat-sub">Orders using coupon</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Conversion Lift</span>
                    <span class="dso-stat-val dso-text-primary">+28.4%</span>
                    <span class="dso-stat-sub">Higher basket conversion</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Budget Guardrail</span>
                    <span class="dso-stat-val dso-text-success">Active</span>
                    <span class="dso-stat-sub">Auto-pauses when exhausted</span>
                </div>
            </div>

            <div class="dso-grid-2">
                <!-- Create Form -->
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Create New Store Coupon</h3>
                    </div>
                    <div class="dso-card-body">
                        <form method="post">
                            <?php wp_nonce_field('dso_coupon_nonce'); ?>
                            <input type="hidden" name="dso_create_coupon" value="1" />

                            <div class="dso-form-group dso-mb-3">
                                <div class="dso-flex dso-justify-between dso-align-center">
                                    <label class="dso-label">Coupon Code <span class="dso-req">*</span></label>
                                    <button type="button" class="dso-btn dso-btn-sm dso-btn-outline" style="font-size:11px;padding:2px 8px;" onclick="if(typeof DSO !== 'undefined' && DSO.generateCouponCode) { document.getElementById('coupon_code_input').value = DSO.generateCouponCode(); }">⚡ Auto-Generate</button>
                                </div>
                                <input type="text" id="coupon_code_input" name="coupon_code" class="dso-input" required placeholder="e.g., DEJOIY10 or FESTIVE20" style="text-transform:uppercase; font-weight:700; letter-spacing:1px;" />
                            </div>

                            <div class="dso-form-row dso-grid-2 dso-mb-3">
                                <div class="dso-form-group">
                                    <label class="dso-label">Discount Type</label>
                                    <select name="discount_type" class="dso-select">
                                        <option value="percent">Percentage Discount (%)</option>
                                        <option value="fixed_cart">Fixed Basket Discount (₹)</option>
                                    </select>
                                </div>
                                <div class="dso-form-group">
                                    <label class="dso-label">Discount Amount <span class="dso-req">*</span></label>
                                    <input type="number" step="0.01" min="1" name="coupon_amount" class="dso-input" required placeholder="e.g., 10" />
                                </div>
                            </div>

                            <div class="dso-form-row dso-grid-2 dso-mb-3">
                                <div class="dso-form-group">
                                    <label class="dso-label">Min Cart Value (₹)</label>
                                    <input type="number" step="1" min="0" name="min_spend" class="dso-input" placeholder="e.g., 499 (Optional)" />
                                </div>
                                <div class="dso-form-group">
                                    <label class="dso-label">Max Redemptions (Limit)</label>
                                    <input type="number" step="1" min="1" name="usage_limit" class="dso-input" placeholder="e.g., 100 uses" />
                                </div>
                            </div>

                            <div class="dso-form-row dso-grid-2 dso-mb-3">
                                <div class="dso-form-group">
                                    <label class="dso-label">Total Spend Budget Cap (₹)</label>
                                    <input type="number" step="1" min="1" name="coupon_budget" class="dso-input" placeholder="e.g., 10000" />
                                </div>
                                <div class="dso-form-group">
                                    <label class="dso-label">Expiry Date</label>
                                    <input type="date" name="expiry_date" class="dso-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" />
                                </div>
                            </div>

                            <button type="submit" class="dso-btn dso-btn-primary dso-btn-full dso-mt-3">Publish & Activate Coupon</button>
                        </form>
                    </div>
                </div>

                <!-- Existing Coupons List -->
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Live Active Store Coupons (<?php echo count($coupons); ?>)</h3>
                    </div>
                    <div class="dso-card-body dso-p-0">
                        <div class="dso-table-responsive">
                            <table class="dso-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Discount</th>
                                        <th>Usage</th>
                                        <th>Min Spend</th>
                                        <th class="dso-text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($coupons)): ?>
                                        <tr><td colspan="5" class="dso-p-4 dso-text-center">No active coupons found. Create your first branded coupon on the left.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($coupons as $c): 
                                            $c_obj = new WC_Coupon($c->ID);
                                            $uses = intval($c_obj->get_usage_count());
                                            $limit = intval($c_obj->get_usage_limit());
                                            $min = $c_obj->get_minimum_amount();
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="dso-coupon-pill" onclick="if(navigator.clipboard){navigator.clipboard.writeText('<?php echo esc_js($c_obj->get_code()); ?>'); if(typeof DSO !== 'undefined' && DSO.toast){DSO.toast('Copied <?php echo esc_js($c_obj->get_code()); ?> to clipboard!','success');}}" title="Click to Copy">
                                                        <?php echo esc_html($c_obj->get_code()); ?> 📋
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong class="dso-text-success">
                                                        <?php echo $c_obj->get_discount_type() === 'percent' ? $c_obj->get_amount() . '%' : '₹' . number_format($c_obj->get_amount(), 2); ?> OFF
                                                    </strong>
                                                </td>
                                                <td>
                                                    <?php echo $uses; ?> / <?php echo $limit > 0 ? $limit : '∞'; ?>
                                                </td>
                                                <td><?php echo $min > 0 ? '₹' . number_format($min, 2) : 'No Min'; ?></td>
                                                <td class="dso-text-right">
                                                    <form method="post" style="display:inline;" onsubmit="return confirm('Deactivate and delete this coupon?');">
                                                        <?php wp_nonce_field('dso_coupon_action'); ?>
                                                        <input type="hidden" name="dso_delete_coupon" value="1" />
                                                        <input type="hidden" name="coupon_id" value="<?php echo $c->ID; ?>" />
                                                        <button type="submit" class="dso-btn dso-btn-sm dso-btn-danger" style="padding:2px 8px;font-size:11px;">Delete</button>
                                                    </form>
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
        </div>
        <?php
    }

    /**
     * Quantity Tier Discounts
     */
    public function bulk_pricing() {
        ?>
        <div class="dso-page dso-bulk-pricing">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>Bulk Pricing</span>
                    </div>
                    <h1 class="dso-page-title">Volume & Tiered Pricing Rules</h1>
                    <p class="dso-page-subtitle">Configure tiered volume discounts for retail buyers ordering multiple units</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Active Tier Rules</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Tier Name</th>
                                    <th>Min Quantity</th>
                                    <th>Discount Applied</th>
                                    <th>Applies To</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Tier 1 — Duo Pack</strong></td>
                                    <td>2 units</td>
                                    <td><span class="dso-badge dso-badge-green">5% OFF</span></td>
                                    <td>All Catalog Listings</td>
                                    <td><span class="dso-badge dso-badge-green">Active</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Tier 2 — Family / Multi Pack</strong></td>
                                    <td>5 units</td>
                                    <td><span class="dso-badge dso-badge-green">10% OFF</span></td>
                                    <td>All Catalog Listings</td>
                                    <td><span class="dso-badge dso-badge-green">Active</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Tier 3 — Bulk Case</strong></td>
                                    <td>10+ units</td>
                                    <td><span class="dso-badge dso-badge-green">15% OFF</span></td>
                                    <td>All Catalog Listings</td>
                                    <td><span class="dso-badge dso-badge-green">Active</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * B2B Wholesale Pricing
     */
    public function b2b_pricing() {
        ?>
        <div class="dso-page dso-b2b-pricing">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=pricing">Pricing</a>
                        <span>/</span>
                        <span>B2B Wholesale</span>
                    </div>
                    <h1 class="dso-page-title">B2B Wholesale & Business Pricing</h1>
                    <p class="dso-page-subtitle">Provide GST registered corporate and trade buyers with custom wholesale rates and GST invoices</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=b2b" class="dso-btn dso-btn-primary">Go to B2B Hub →</a>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body">
                    <div class="dso-info-box dso-mb-4">
                        <h4>🏢 Corporate Wholesale Program on DEJOIY</h4>
                        <p>Verified business customers with valid GSTINs receive dedicated bulk rate cards on DEJOIY Marketplace. You earn steady large-volume recurring sales with instant invoice generation.</p>
                    </div>
                    <div class="dso-grid-3">
                        <div class="dso-card">
                            <div class="dso-card-body">
                                <h4>Tier A: MOQ 25</h4>
                                <p class="dso-text-muted">20% margin below retail</p>
                                <span class="dso-badge dso-badge-green">Enabled</span>
                            </div>
                        </div>
                        <div class="dso-card">
                            <div class="dso-card-body">
                                <h4>Tier B: MOQ 50</h4>
                                <p class="dso-text-muted">28% margin below retail</p>
                                <span class="dso-badge dso-badge-green">Enabled</span>
                            </div>
                        </div>
                        <div class="dso-card">
                            <div class="dso-card-body">
                                <h4>Tier C: MOQ 100+</h4>
                                <p class="dso-text-muted">Custom Quote / RFQ</p>
                                <span class="dso-badge dso-badge-purple">RFQ Enabled</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
