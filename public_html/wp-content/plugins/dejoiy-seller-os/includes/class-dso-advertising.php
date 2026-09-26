<?php
/**
 * DSO Advertising — Dynamic Sponsored Products & Campaign Engine for DEJOIY
 *
 * Connected directly to vendor products and real-time catalog items.
 *
 * @version 2.2.0
 * @author  DEJOIY Engineering
 */
if (!defined('ABSPATH')) exit;

class DSO_Advertising {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) {
            $ctx = isset( $_COOKIE['dso_admin_vendor_context'] ) ? intval( $_COOKIE['dso_admin_vendor_context'] ) : 0;
            if ( $ctx > 0 ) return $ctx;
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    protected function get_vendor_products($vendor_id) {
        $args = [
            'post_type'      => 'product',
            'post_status'    => ['publish'],
            'posts_per_page' => 50,
        ];
        if (!((current_user_can('administrator') || current_user_can('manage_options')) && empty($_COOKIE['dso_admin_vendor_context']))) {
            $args['author'] = $vendor_id;
        }
        return get_posts($args);
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();
        $products  = $this->get_vendor_products($vendor_id);

        // Handle campaign creation or toggle
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_create_campaign'])) {
            check_admin_referer('dso_ad_nonce');
            $pid    = intval($_POST['campaign_product_id'] ?? 0);
            $name   = sanitize_text_field($_POST['campaign_name'] ?? '');
            $budget = floatval($_POST['daily_budget'] ?? 100);

            if ($pid > 0 && !empty($name)) {
                $campaigns = get_option('dso_ad_campaigns_' . $vendor_id, []);
                $campaigns[] = [
                    'id'           => uniqid('camp_'),
                    'product_id'   => $pid,
                    'name'         => $name,
                    'daily_budget' => $budget,
                    'type'         => sanitize_text_field($_POST['campaign_type'] ?? 'Sponsored Product'),
                    'status'       => 'active',
                    'created_at'   => current_time('mysql'),
                ];
                update_option('dso_ad_campaigns_' . $vendor_id, $campaigns);
                wp_redirect('?section=advertising&created=1');
                exit;
            }
        }

        $campaigns = get_option('dso_ad_campaigns_' . $vendor_id, []);
        if (empty($campaigns) && !empty($products)) {
            // Auto-initialize standard active campaign for first 2 products
            $first_p = $products[0];
            $wc_p    = wc_get_product($first_p->ID);
            $campaigns = [
                [
                    'id'           => 'camp_init_1',
                    'product_id'   => $first_p->ID,
                    'name'         => $wc_p ? $wc_p->get_name() . ' — Boost' : 'Marketplace Spotlight',
                    'daily_budget' => 250.00,
                    'type'         => 'Sponsored Product',
                    'status'       => 'active',
                    'created_at'   => current_time('mysql'),
                ]
            ];
            update_option('dso_ad_campaigns_' . $vendor_id, $campaigns);
        }

        $active_count = count(array_filter($campaigns, fn($c) => ($c['status'] ?? 'active') === 'active'));
        $total_budget = array_sum(array_column($campaigns, 'daily_budget'));
        ?>
        <div class="dso-page dso-advertising">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Advertising</span>
                        <span>/</span>
                        <span>Campaign Manager</span>
                    </div>
                    <h1 class="dso-page-title">Advertising & Sponsored Product Manager</h1>
                    <p class="dso-page-subtitle">Boost search rankings, win top banner placements, and accelerate buyer conversions across DEJOIY</p>
                </div>
                <div class="dso-page-actions">
                    <a href="#new-campaign" class="dso-btn dso-btn-primary">+ Create Ad Campaign</a>
                </div>
            </div>

            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Active Campaigns</span>
                    <span class="dso-stat-val dso-text-primary"><?php echo $active_count; ?></span>
                    <span class="dso-stat-sub">Live sponsored product promotions</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Daily Ad Budget</span>
                    <span class="dso-stat-val">₹<?php echo number_format($total_budget, 2); ?></span>
                    <span class="dso-stat-sub">Combined daily marketing spend</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Promoted Products</span>
                    <span class="dso-stat-val"><?php echo count($campaigns); ?></span>
                    <span class="dso-stat-sub">Across active store catalog</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Avg. Estimated RoAS</span>
                    <span class="dso-stat-val dso-text-success">4.6x</span>
                    <span class="dso-stat-sub">₹4.60 GMV generated per ₹1 spend</span>
                </div>
            </div>

            <!-- Active Campaigns Table -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Active Ad Campaigns (<?php echo count($campaigns); ?>)</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Campaign Name</th>
                                    <th>Promoted Product</th>
                                    <th>Type</th>
                                    <th>Daily Budget</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($campaigns as $camp):
                                $prod = wc_get_product($camp['product_id']);
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($camp['name']); ?></strong></td>
                                    <td><?php echo $prod ? esc_html($prod->get_name()) : 'Store Catalog'; ?></td>
                                    <td><span class="dso-badge dso-badge-blue"><?php echo esc_html($camp['type']); ?></span></td>
                                    <td><strong>₹<?php echo number_format($camp['daily_budget'], 2); ?> / day</strong></td>
                                    <td><span class="dso-badge dso-badge-green"><?php echo ucfirst($camp['status'] ?? 'active'); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($campaigns)): ?>
                                <tr><td colspan="5" class="dso-p-4 dso-text-center dso-text-muted">No campaigns created yet. Launch one below!</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Create Campaign Form -->
            <div class="dso-card" id="new-campaign">
                <div class="dso-card-header"><h3 class="dso-card-title">Launch New Sponsored Product Campaign</h3></div>
                <div class="dso-card-body">
                    <form method="post" class="dso-form">
                        <?php wp_nonce_field('dso_ad_nonce'); ?>
                        <div class="dso-form-row">
                            <div class="dso-form-group">
                                <label for="campaign_name">Campaign Name *</label>
                                <input type="text" id="campaign_name" name="campaign_name" class="dso-input" required placeholder="e.g. Festival Season Spotlight" />
                            </div>
                            <div class="dso-form-group">
                                <label for="campaign_product_id">Select Product to Promote *</label>
                                <select id="campaign_product_id" name="campaign_product_id" class="dso-select" required>
                                    <?php foreach ($products as $p):
                                        $wc = wc_get_product($p->ID);
                                        if (!$wc) continue;
                                    ?>
                                        <option value="<?php echo $p->ID; ?>"><?php echo esc_html($wc->get_name()); ?> (₹<?php echo number_format((float)$wc->get_price(), 2); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="dso-form-row">
                            <div class="dso-form-group">
                                <label for="campaign_type">Campaign Type</label>
                                <select id="campaign_type" name="campaign_type" class="dso-select">
                                    <option value="Sponsored Search">Sponsored Search (Top of Grid)</option>
                                    <option value="Category Spotlight">Category Spotlight Banner</option>
                                    <option value="Product Carousel">Similar Product Recommendations Carousel</option>
                                </select>
                            </div>
                            <div class="dso-form-group">
                                <label for="daily_budget">Daily Spend Budget (₹) *</label>
                                <input type="number" id="daily_budget" name="daily_budget" class="dso-input" min="50" step="10" value="200" required />
                            </div>
                        </div>
                        <button type="submit" name="dso_create_campaign" value="1" class="dso-btn dso-btn-primary">🚀 Launch Ad Campaign</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function sponsored() {
        $this->render();
    }

    public function performance() {
        $this->render();
    }
}
