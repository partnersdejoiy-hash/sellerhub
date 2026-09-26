<?php
/**
 * DEJOIY Native Multi-Vendor Storefront Template
 * Modern, high-performance seller storefront independent of WCFM
 */
if (!defined('ABSPATH')) exit;

$store_slug = get_query_var('store');
if (empty($store_slug)) {
    wp_safe_redirect(wc_get_page_id('shop') ? get_permalink(wc_get_page_id('shop')) : home_url('/'));
    exit;
}

$seller_user = get_user_by('slug', $store_slug);
if (!$seller_user) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    exit;
}

$vendor_id = $seller_user->ID;
$store_data = DSO_Auth::get_vendor_store($vendor_id);

$store_name = $store_data['name'] ?: $seller_user->display_name;
$store_banner = $store_data['banner'] ?: 'https://images.unsplash.com/photo-1579546929518-9e396f3cc809?w=1600&auto=format&fit=crop&q=80';
$store_logo = $store_data['logo'] ?: '';
$store_desc = $store_data['description'] ?: 'Welcome to the official ' . esc_html($store_name) . ' storefront on DEJOIY Marketplace.';
$store_phone = $store_data['phone'] ?: '';
$store_email = $store_data['email'] ?: $seller_user->user_email;

$is_verified = (
    get_user_meta($vendor_id, '_wcfm_email_verified', true) === 'yes' ||
    get_user_meta($vendor_id, 'dso_verified_seller', true) === 'yes' ||
    get_user_meta($vendor_id, 'wcemailverified', true) === 'true'
);

$merchant_code = get_user_meta($vendor_id, '_dejoiy_seller_id', true) ?: sprintf('DJ-VND-%04d', $vendor_id);
$joined_date = date('F Y', strtotime($seller_user->user_registered));

// Profile settings for policies
$profile_settings = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
if (is_string($profile_settings)) $profile_settings = maybe_unserialize($profile_settings);
if (!is_array($profile_settings)) $profile_settings = [];

$shipping_policy = $profile_settings['wcfm_policy_vendor_options']['shipping_policy'] ?? 'Standard 2–4 business days delivery via DEJOIY Express Logistics with real-time tracking.';
$refund_policy = $profile_settings['wcfm_policy_vendor_options']['refund_policy'] ?? 'Backed by DEJOIY 7-Day Hassle-Free Replacement & Easy Return Policy.';
$cancellation_policy = $profile_settings['wcfm_policy_vendor_options']['cancellation_policy'] ?? 'Orders can be cancelled at any time prior to carrier dispatch directly from your DEJOIY account.';

// Active tab
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'products';

// Query vendor products
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';

$args = [
    'post_type'      => 'product',
    'post_status'    => 'publish',
    'posts_per_page' => 16,
    'paged'          => $paged,
    'tax_query'      => [
        [
            'taxonomy' => 'product_visibility',
            'field'    => 'name',
            'terms'    => ['exclude-from-catalog'],
            'operator' => 'NOT IN',
        ],
    ],
];

if (isset($_GET['sq']) && !empty($_GET['sq'])) {
    $args['s'] = sanitize_text_field($_GET['sq']);
}

$dso_filter_vendor_query = function($where) use ($vendor_id) {
    global $wpdb;
    $where .= $wpdb->prepare(
        " AND ({$wpdb->posts}.post_author = %d OR {$wpdb->posts}.ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('_vendor_id', '_wcfm_vendor') AND meta_value = %s))",
        $vendor_id,
        strval($vendor_id)
    );
    return $where;
};

add_filter('posts_where', $dso_filter_vendor_query);
$vendor_query = new WP_Query($args);
remove_filter('posts_where', $dso_filter_vendor_query);
$total_products = $vendor_query->found_posts;

get_header('shop');
?>

<style>
.dso-store-wrap {
    max-width: 1380px;
    margin: 0 auto;
    padding: 16px 20px 48px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
    color: #1e293b;
}
.dso-store-hero {
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    background: #0f172a;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}
.dso-store-banner {
    width: 100%;
    height: 260px;
    object-fit: cover;
    display: block;
    filter: brightness(0.85);
}
.dso-store-profile-bar {
    background: #ffffff;
    padding: 20px 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    border-top: 1px solid #f1f5f9;
}
.dso-store-identity {
    display: flex;
    align-items: center;
    gap: 20px;
}
.dso-store-avatar {
    width: 84px;
    height: 84px;
    border-radius: 16px;
    border: 4px solid #ffffff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    margin-top: -48px;
    background: #001553;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: 800;
    color: #ffffff;
    overflow: hidden;
    flex-shrink: 0;
}
.dso-store-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.dso-store-title-wrap h1 {
    margin: 0;
    font-size: 24px;
    font-weight: 800;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.dso-store-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-top: 6px;
    font-size: 13px;
    color: #64748b;
    flex-wrap: wrap;
}
.dso-badge-verified {
    background: #ecfdf5;
    color: #059669;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 1px solid #a7f3d0;
}
.dso-store-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}
.dso-btn-store-chat {
    background: #001553;
    color: #ffffff !important;
    padding: 10px 20px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 12px rgba(0,21,83,0.25);
    transition: all 0.2s ease;
}
.dso-btn-store-chat:hover {
    background: #032b85;
    transform: translateY(-1px);
}
.dso-btn-store-share {
    background: #f8fafc;
    color: #334155;
    border: 1px solid #e2e8f0;
    padding: 10px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s ease;
}
.dso-btn-store-share:hover {
    background: #f1f5f9;
}

/* Store Navigation Tabs */
.dso-store-nav-tabs {
    display: flex;
    align-items: center;
    gap: 4px;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 24px;
    background: #ffffff;
    border-radius: 12px 12px 0 0;
    padding: 0 16px;
}
.dso-store-tab-link {
    padding: 14px 20px;
    font-size: 14px;
    font-weight: 700;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    margin-bottom: -2px;
    transition: all 0.15s ease;
}
.dso-store-tab-link:hover {
    color: #001553;
}
.dso-store-tab-link.active {
    color: #001553;
    border-bottom-color: #001553;
}

/* Product Grid */
.dso-store-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}
.dso-p-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: all 0.25s ease;
    text-decoration: none;
    color: inherit;
    position: relative;
}
.dso-p-card:hover {
    border-color: #cbd5e1;
    transform: translateY(-3px);
    box-shadow: 0 10px 24px rgba(0,0,0,0.06);
}
.dso-p-thumb {
    width: 100%;
    aspect-ratio: 1;
    object-fit: cover;
    background: #f8fafc;
}
.dso-p-body {
    padding: 14px;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.dso-p-dpin {
    font-family: monospace;
    font-size: 10px;
    color: #64748b;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}
.dso-p-title {
    font-size: 13.5px;
    font-weight: 600;
    color: #0f172a;
    line-height: 1.4;
    margin: 0 0 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.dso-p-pricing {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-top: auto;
    padding-top: 8px;
}
.dso-p-price {
    font-size: 16px;
    font-weight: 800;
    color: #001553;
}
.dso-p-mrp {
    font-size: 12px;
    color: #94a3b8;
    text-decoration: line-through;
}
.dso-p-express-tag {
    font-size: 10px;
    font-weight: 700;
    color: #b45309;
    background: #fef3c7;
    padding: 2px 6px;
    border-radius: 4px;
    align-self: flex-start;
    margin-top: 6px;
}

/* Empty State */
.dso-empty-store {
    text-align: center;
    padding: 64px 20px;
    background: #ffffff;
    border-radius: 16px;
    border: 1px dashed #cbd5e1;
}
.dso-empty-store svg {
    width: 64px;
    height: 64px;
    color: #94a3b8;
    margin-bottom: 16px;
}

@media (max-width: 768px) {
    .dso-store-banner { height: 160px; }
    .dso-store-profile-bar { padding: 16px; flex-direction: column; align-items: flex-start; }
    .dso-store-actions { width: 100%; justify-content: flex-start; }
    .dso-store-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
}
</style>

<div class="dso-store-wrap">
    <!-- Storefront Hero & Identity -->
    <div class="dso-store-hero">
        <img src="<?php echo esc_url($store_banner); ?>" alt="<?php echo esc_attr($store_name); ?> Banner" class="dso-store-banner" />
        
        <div class="dso-store-profile-bar">
            <div class="dso-store-identity">
                <div class="dso-store-avatar">
                    <?php if (!empty($store_logo)): ?>
                        <img src="<?php echo esc_url($store_logo); ?>" alt="<?php echo esc_attr($store_name); ?>" />
                    <?php else: ?>
                        <?php echo esc_html(mb_strtoupper(mb_substr($store_name, 0, 1))); ?>
                    <?php endif; ?>
                </div>
                <div class="dso-store-title-wrap">
                    <h1>
                        <?php echo esc_html($store_name); ?>
                        <?php if ($is_verified): ?>
                            <span class="dso-badge-verified">✓ Verified Merchant</span>
                        <?php endif; ?>
                    </h1>
                    <div class="dso-store-meta">
                        <span>Merchant ID: <code><?php echo esc_html($merchant_code); ?></code></span>
                        <span>•</span>
                        <span>Joined <?php echo esc_html($joined_date); ?></span>
                        <span>•</span>
                        <span><?php echo number_format($total_products); ?> Products</span>
                    </div>
                </div>
            </div>

            <div class="dso-store-actions">
                <a href="<?php echo esc_url(add_query_arg(['contact_seller' => $vendor_id], home_url('/my-account/messages/'))); ?>" class="dso-btn-store-chat">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    Contact Seller
                </a>
                <button type="button" class="dso-btn-store-share" onclick="navigator.clipboard.writeText(window.location.href); alert('Store link copied to clipboard!');">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                    Share
                </button>
            </div>
        </div>
    </div>

    <!-- Storefront Navigation Tabs -->
    <div class="dso-store-nav-tabs">
        <a href="?tab=products" class="dso-store-tab-link <?php echo $active_tab === 'products' ? 'active' : ''; ?>">
            Products (<?php echo $total_products; ?>)
        </a>
        <a href="?tab=about" class="dso-store-tab-link <?php echo $active_tab === 'about' ? 'active' : ''; ?>">
            About Store
        </a>
        <a href="?tab=policies" class="dso-store-tab-link <?php echo $active_tab === 'policies' ? 'active' : ''; ?>">
            Policies & Returns
        </a>
    </div>

    <!-- Tab Contents -->
    <?php if ($active_tab === 'products'): ?>
        <?php if ($vendor_query->have_posts()): ?>
            <div class="dso-store-grid">
                <?php while ($vendor_query->have_posts()): $vendor_query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if (!$product) continue;
                    $dpin = get_post_meta($product->get_id(), '_dejoiy_dpin', true) ?: (get_post_meta($product->get_id(), '_dpin', true) ?: '');
                    $mrp = get_post_meta($product->get_id(), '_dso_mrp', true);
                ?>
                    <a href="<?php the_permalink(); ?>" class="dso-p-card">
                        <?php if (has_post_thumbnail()): ?>
                            <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium')); ?>" alt="<?php the_title_attribute(); ?>" class="dso-p-thumb" loading="lazy" />
                        <?php else: ?>
                            <div class="dso-p-thumb" style="display:flex;align-items:center;justify-content:center;background:#e2e8f0;color:#94a3b8;font-size:12px;">No Image</div>
                        <?php endif; ?>
                        
                        <div class="dso-p-body">
                            <?php if (!empty($dpin)): ?>
                                <span class="dso-p-dpin"><?php echo esc_html($dpin); ?></span>
                            <?php endif; ?>
                            
                            <h3 class="dso-p-title"><?php the_title(); ?></h3>
                            
                            <div class="dso-p-pricing">
                                <span class="dso-p-price"><?php echo wc_price($product->get_price()); ?></span>
                                <?php if (!empty($mrp) && floatval($mrp) > floatval($product->get_price())): ?>
                                    <span class="dso-p-mrp"><?php echo wc_price($mrp); ?></span>
                                <?php endif; ?>
                            </div>

                            <span class="dso-p-express-tag">⚡ DEJOIY Express Fulfilled</span>
                        </div>
                    </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <!-- Pagination -->
            <?php if ($vendor_query->max_num_pages > 1): ?>
                <div style="margin-top:32px;display:flex;justify-content:center;gap:8px;">
                    <?php
                    echo paginate_links([
                        'total'   => $vendor_query->max_num_pages,
                        'current' => $paged,
                        'format'  => '?paged=%#%',
                    ]);
                    ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="dso-empty-store">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                <h3 style="font-size:18px;font-weight:700;color:#334155;margin:0 0 6px;">No Products Found</h3>
                <p style="font-size:14px;color:#64748b;margin:0;">This seller is currently restocking inventory. Please check back shortly.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'about'): ?>
        <div style="background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0;">
            <h2 style="font-size:20px;font-weight:800;color:#0f172a;margin-top:0;">About <?php echo esc_html($store_name); ?></h2>
            <div style="font-size:15px;line-height:1.7;color:#334155;margin-bottom:24px;">
                <?php echo wp_kses_post(wpautop($store_desc)); ?>
            </div>
            
            <hr style="border:0;border-top:1px solid #f1f5f9;margin:24px 0;" />
            
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:12px;">Store Support & Contact</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;font-size:14px;color:#475569;">
                <div>
                    <strong>Email:</strong><br/>
                    <a href="mailto:<?php echo esc_attr($store_email); ?>" style="color:#001553;"><?php echo esc_html($store_email); ?></a>
                </div>
                <?php if (!empty($store_phone)): ?>
                <div>
                    <strong>Phone:</strong><br/>
                    <a href="tel:<?php echo esc_attr($store_phone); ?>" style="color:#001553;"><?php echo esc_html($store_phone); ?></a>
                </div>
                <?php endif; ?>
                <div>
                    <strong>Platform Verification:</strong><br/>
                    <span style="color:#059669;font-weight:700;">100% Certified Authentic Merchant</span>
                </div>
            </div>
        </div>

    <?php elseif ($active_tab === 'policies'): ?>
        <div style="background:#ffffff;border-radius:16px;padding:32px;border:1px solid #e2e8f0;display:flex;flex-direction:column;gap:24px;">
            <div>
                <h3 style="font-size:17px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;margin-top:0;">
                    <span>🚚</span> Shipping & Delivery Policy
                </h3>
                <div style="font-size:14px;color:#475569;line-height:1.6;">
                    <?php echo wp_kses_post(wpautop($shipping_policy)); ?>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #f1f5f9;margin:0;" />

            <div>
                <h3 style="font-size:17px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;margin-top:0;">
                    <span>🛡️</span> Return & Replacement Guarantee
                </h3>
                <div style="font-size:14px;color:#475569;line-height:1.6;">
                    <?php echo wp_kses_post(wpautop($refund_policy)); ?>
                </div>
            </div>

            <hr style="border:0;border-top:1px solid #f1f5f9;margin:0;" />

            <div>
                <h3 style="font-size:17px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;margin-top:0;">
                    <span>✕</span> Cancellation Policy
                </h3>
                <div style="font-size:14px;color:#475569;line-height:1.6;">
                    <?php echo wp_kses_post(wpautop($cancellation_policy)); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
get_footer('shop');
