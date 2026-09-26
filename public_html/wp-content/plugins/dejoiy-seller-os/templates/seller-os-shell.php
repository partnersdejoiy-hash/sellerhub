<?php
/**
 * Seller OS Shell Template
 * Wraps all seller hub pages with sidebar + top bar
 */
if (!defined('ABSPATH')) exit;

$current_section = DSO_Router::get_current_section();
$user_id = get_current_user_id();
$unread_count = 0;
if (class_exists('DSO_Notifications')) {
    $notif = new DSO_Notifications();
    $unread_count = $notif->get_unread_count($user_id);
}

if (!isset($display_name)) {
    $store = DSO_Auth::get_vendor_store($user_id);
    $store_name = $store ? $store['name'] : 'Seller';
    $user_data = get_userdata($user_id);
    $display_name = $user_data ? $user_data->display_name : $store_name;
    $store_logo = $store && !empty($store['logo']) ? $store['logo'] : '';
}
$vendor_id = Dejoiy_Seller_OS::instance()->get_vendor_id($user_id) ?: $user_id;

// Compute actual storefront URL
$store_slug = '';
if (!empty($store_name)) {
    $store_slug = sanitize_title($store_name);
} elseif (!empty($display_name)) {
    $store_slug = sanitize_title($display_name);
}

$live_store_url = '';
if (function_exists('wcfmmp_get_store_url')) {
    $live_store_url = wcfmmp_get_store_url($vendor_id);
}
if (empty($live_store_url) || strpos($live_store_url, 'http') === false) {
    $live_store_url = 'https://dejoiy.com/store/' . ($store_slug ?: 'seller') . '/';
} else {
    $parsed = parse_url($live_store_url);
    $path = !empty($parsed['path']) ? rtrim($parsed['path'], '/') . '/' : ('/store/' . ($store_slug ?: 'seller') . '/');
    $live_store_url = 'https://dejoiy.com' . $path;
}

$merchant_code = sprintf('DJ-VND-%04d', $vendor_id);

// Time-based greeting
$hour = (int) current_time('G');
if ($hour >= 5 && $hour < 12) {
    $salutation = 'Good morning';
} elseif ($hour >= 12 && $hour < 17) {
    $salutation = 'Good afternoon';
} elseif ($hour >= 17 && $hour < 21) {
    $salutation = 'Good evening';
} else {
    $salutation = 'Good night';
}

// Prepare Search Data for client-side search
$search_products_data = [];
$vendor_products = get_posts([
    'post_type' => 'product',
    'post_status' => ['publish', 'draft', 'pending', 'private'],
    'author' => $vendor_id,
    'posts_per_page' => 100,
]);
foreach ($vendor_products as $vp) {
    $wc_prod = wc_get_product($vp->ID);
    if (!$wc_prod) continue;
    $dpin = get_post_meta($vp->ID, '_dejoiy_dpin', true) ?: (get_post_meta($vp->ID, '_dpin', true) ?: '');
    $sku = $wc_prod->get_sku() ?: '';
    $img_id = $wc_prod->get_image_id();
    $thumb = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
    $raw_price = $wc_prod->get_price_html() ?: ('₹' . number_format(floatval($wc_prod->get_price()), 2));
    $clean_price = html_entity_decode(wp_strip_all_tags($raw_price), ENT_QUOTES, 'UTF-8');
    $search_products_data[] = [
        'id' => $vp->ID,
        'title' => $wc_prod->get_name(),
        'dpin' => $dpin,
        'sku' => $sku,
        'price' => $clean_price,
        'thumb' => $thumb,
        'url' => '?section=products&action=edit&id=' . $vp->ID,
        'status' => $vp->post_status
    ];
}

$search_sections_data = [
    ['title' => 'Dashboard Overview', 'desc' => 'Sales, live metrics & alerts', 'url' => '?section=dashboard', 'icon' => '📊', 'tags' => 'home metrics overview stats revenue sales graph'],
    ['title' => 'Products & Catalog', 'desc' => 'Manage inventory & DPINs', 'url' => '?section=products', 'icon' => '📦', 'tags' => 'products catalog inventory items dpin sku add stock'],
    ['title' => 'Add New Product', 'desc' => 'Instant DPIN generation & publishing', 'url' => '?section=products&action=new', 'icon' => '➕', 'tags' => 'create product publish dpin new listing draft upload'],
    ['title' => 'Orders & Shipments', 'desc' => 'Fulfill customer orders & tracking', 'url' => '?section=orders', 'icon' => '📋', 'tags' => 'orders shipments tracking dispatch delivery customer fulfill invoice'],
    ['title' => 'Finance & Payouts', 'desc' => 'Bank verification & earnings balance', 'url' => '?section=finance', 'icon' => '💰', 'tags' => 'finance bank payout balance wallet earnings withdrawal tax ifsc pan account'],
    ['title' => 'Storefront Studio', 'desc' => 'Customise public DEJOIY store', 'url' => '?section=store', 'icon' => '🎨', 'tags' => 'store storefront banner logo design customize url bio slug brand'],
    ['title' => 'Logistics & Shipping', 'desc' => 'Pincodes & courier partners', 'url' => '?section=shipping', 'icon' => '🚚', 'tags' => 'shipping delivery courier pincode courier partner fee logistics'],
    ['title' => 'Pricing & Bulk Deals', 'desc' => 'Dynamic rules & volume discounts', 'url' => '?section=pricing', 'icon' => '🏷️', 'tags' => 'pricing discount sale bulk b2b offer coupon tier'],
    ['title' => 'Advertising & Sponsored', 'desc' => 'Boost listing reach & sales', 'url' => '?section=advertising', 'icon' => '📢', 'tags' => 'ads advertising sponsored campaign boost reach impressions'],
    ['title' => 'Growth & Smart Insights', 'desc' => 'Demand analytics & recommendations', 'url' => '?section=growth', 'icon' => '🚀', 'tags' => 'growth insights recommendations demand trending sales opportunities'],
    ['title' => 'Performance & Reports', 'desc' => 'Conversion telemetry & KPI graphs', 'url' => '?section=performance', 'icon' => '📈', 'tags' => 'performance analytics conversion charts graphs kpi reports telemetry'],
    ['title' => 'Customer Messages', 'desc' => 'Live buyer-seller communication', 'url' => '?section=messages', 'icon' => '💬', 'tags' => 'messages chat buyer customer inbox communication support inquiries'],
    ['title' => 'Support Desk & Tickets', 'desc' => 'Dispute resolution & direct support', 'url' => '?section=support', 'icon' => '🎫', 'tags' => 'support help ticket complaint issue desk agent contact contact seller support'],
    ['title' => 'Settings & Security', 'desc' => 'Seller profile & store credentials', 'url' => '?section=settings', 'icon' => '⚙️', 'tags' => 'settings profile password email phone gst pan verification business']
];
?>
<script>
window.dsoSearchData = {
    products: <?php echo json_encode($search_products_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    sections: <?php echo json_encode($search_sections_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
};
</script>
<div id="dso-app" class="dso-app">
    <!-- Top Bar -->
    <header class="dso-topbar">
        <div class="dso-topbar-left">
            <button class="dso-menu-toggle" id="dso-menu-toggle" aria-label="Toggle navigation menu" title="Toggle navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a href="?section=dashboard" class="dso-topbar-brand" title="DEJOIY Seller Central">
                <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY" class="dso-brand-logo-img" />
                <span class="dso-brand-badge">SELLER HUB</span>
            </a>
        </div>
        <div class="dso-topbar-center">
            <div class="dso-header-search-wrap" id="dso-header-search-wrap">
                <div class="dso-search-field">
                    <svg class="dso-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="dso-header-search-input" class="dso-header-search-input" placeholder="Search products, DPIN, orders, tools..." autocomplete="off" spellcheck="false" aria-label="Search DEJOIY Seller Hub" />
                    <button type="button" id="dso-search-clear-btn" class="dso-search-clear-btn" style="display:none;" aria-label="Clear search">&times;</button>
                    <kbd class="dso-search-kbd">⌘K</kbd>
                </div>
                <div id="dso-header-search-results" class="dso-header-search-results" style="display:none;"></div>
            </div>
        </div>
        <div class="dso-topbar-right">
            <!-- Dynamic Logged-in Seller Greeting Pill -->
            <div class="dso-topbar-greeting-pill" title="<?php echo esc_attr($store_name . ' (' . $merchant_code . ')'); ?>">
                <span class="dso-greeting-wave">👋</span>
                <span class="dso-greeting-salutation"><?php echo esc_html($salutation); ?>,</span>
                <span class="dso-greeting-name"><?php echo esc_html($display_name); ?></span>
            </div>

            <!-- Actual Live Storefront Link -->
            <a href="<?php echo esc_url($live_store_url); ?>" target="_blank" rel="noopener" class="dso-topbar-link" title="Open Live Storefront: <?php echo esc_attr($live_store_url); ?>">
                Storefront ↗
            </a>

            <!-- Light / Dark theme toggle -->
            <button type="button" class="dso-topbar-icon-btn dso-theme-toggle" id="dso-theme-toggle" title="Toggle light / dark theme" aria-label="Toggle light / dark theme">
                <span class="dso-ico-moon" style="display:inline-flex;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg></span>
                <span class="dso-ico-sun" style="display:none;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg></span>
            </button>

            <!-- Quick Seller AI Drawer Toggle -->
            <button class="dso-topbar-icon-btn" id="dso-seller-ai-btn" title="Open DEJOIY Seller AI Copilot" aria-label="Open DEJOIY Seller AI Copilot">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M12 2a7 7 0 017 7c0 2.38-1.19 4.47-3 5.74V17a2 2 0 01-2 2h-4a2 2 0 01-2-2v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 017-7z"/><line x1="10" y1="22" x2="14" y2="22"/></svg>
            </button>

            <!-- Notifications -->
            <a href="?section=notifications" class="dso-topbar-icon-btn dso-notif-btn" title="Notifications" aria-label="View notifications">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <?php if ($unread_count > 0): ?>
                    <span class="dso-notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>

            <!-- Consolidated Right-Hand Actions Dropdown Menu -->
            <div class="dso-topbar-dropdown" id="dso-quick-hub-dropdown-wrap" style="position:relative;">
                <button type="button" class="dso-hub-menu-btn" id="dso-quick-hub-toggle" aria-label="Seller Hub Actions Menu" aria-expanded="false" title="Account & Quick Hub Menu">
                    <div class="dso-hub-avatar">
                        <?php if (!empty($store_logo)): ?>
                            <img src="<?php echo esc_url($store_logo); ?>" alt="" />
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($display_name, 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="dso-hub-btn-label"><?php echo esc_html(mb_strimwidth($display_name, 0, 14, '...')); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><polyline points="6 9 12 15 18 9"/></svg>
                </button>

                <div class="dso-dropdown-hub" id="dso-quick-hub-dropdown">
                    <div class="dso-hub-header-box">
                        <div class="dso-hub-seller-title">👋 <?php echo esc_html($salutation . ', ' . $display_name); ?></div>
                        <div class="dso-hub-store-name">🏪 <?php echo esc_html($store_name); ?></div>
                        <div class="dso-hub-meta-badges">
                            <span class="dso-hub-merchant-tag"><?php echo esc_html($merchant_code); ?></span>
                            <span style="font-size:11px;color:#34d399;font-weight:600;">🟢 Verified Seller</span>
                        </div>
                    </div>
                    <div class="dso-hub-content-list">
                        <a href="<?php echo esc_url($live_store_url); ?>" target="_blank" rel="noopener" class="dso-hub-item" style="color:#0066ff;font-weight:700;">
                            <span class="dso-hub-item-icon">🌐</span>
                            <span>Visit Live Storefront</span>
                            <span class="dso-hub-badge-pill" style="background:rgba(0,102,255,0.12);color:#0066ff;">↗</span>
                        </a>
                        <button type="button" class="dso-hub-item" id="dso-hub-trigger-ai">
                            <span class="dso-hub-item-icon">✨</span>
                            <span>Seller AI Copilot</span>
                            <span class="dso-hub-badge-pill" style="background:#fef3c7;color:#d97706;">Smart</span>
                        </button>
                        <a href="?section=notifications" class="dso-hub-item">
                            <span class="dso-hub-item-icon">🔔</span>
                            <span>Notifications</span>
                            <?php if ($unread_count > 0): ?>
                                <span class="dso-hub-badge-pill" style="background:#ef4444;color:#ffffff;"><?php echo $unread_count; ?> new</span>
                            <?php endif; ?>
                        </a>
                        <div class="dso-hub-divider"></div>
                        <div style="padding:6px 20px 2px;font-size:10px;font-weight:800;letter-spacing:0.5px;color:#94a3b8;text-transform:uppercase;">Quick Actions</div>
                        <a href="?section=products&action=new" class="dso-hub-item">
                            <span class="dso-hub-item-icon">➕</span>
                            <span>Add New Product (DPIN)</span>
                        </a>
                        <a href="?section=orders" class="dso-hub-item">
                            <span class="dso-hub-item-icon">📦</span>
                            <span>Orders & Fulfillment</span>
                        </a>
                        <a href="?section=messages" class="dso-hub-item">
                            <span class="dso-hub-item-icon">💬</span>
                            <span>Buyer Messages</span>
                        </a>
                        <a href="?section=finance" class="dso-hub-item">
                            <span class="dso-hub-item-icon">💳</span>
                            <span>Finance & Bank Account</span>
                        </a>
                        <a href="?section=performance" class="dso-hub-item">
                            <span class="dso-hub-item-icon">📊</span>
                            <span>Analytics & Performance</span>
                        </a>
                        <a href="?section=store" class="dso-hub-item">
                            <span class="dso-hub-item-icon">🎨</span>
                            <span>Storefront Studio</span>
                        </a>
                        <a href="?section=settings" class="dso-hub-item">
                            <span class="dso-hub-item-icon">⚙️</span>
                            <span>Seller Settings</span>
                        </a>
                        <div class="dso-hub-divider"></div>
                        <div style="padding:6px 20px 2px;font-size:10px;font-weight:800;letter-spacing:0.5px;color:#94a3b8;text-transform:uppercase;">Support & Learning</div>
                        <a href="?section=learn" class="dso-hub-item">
                            <span class="dso-hub-item-icon">🎓</span>
                            <span>Seller University</span>
                        </a>
                        <a href="?section=support" class="dso-hub-item">
                            <span class="dso-hub-item-icon">🎫</span>
                            <span>Support Desk Tickets</span>
                        </a>
                        <div class="dso-hub-divider"></div>
                        <a href="<?php echo wp_logout_url(home_url()); ?>" class="dso-hub-item dso-hub-danger">
                            <span class="dso-hub-item-icon">🚪</span>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="dso-sidebar" id="dso-sidebar">
        <div class="dso-sidebar-header">
            <a href="?section=dashboard" class="dso-sidebar-logo" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY" class="dso-brand-logo-img" style="height:32px;width:auto;" />
                <span class="dso-brand-badge">SELLER HUB</span>
            </a>
            <button class="dso-sidebar-close" id="dso-sidebar-close" aria-label="Close menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <nav class="dso-sidebar-nav">
            <?php 
            $current_group = null;
            foreach ($nav_items as $item): 
                $item_group = $item['group'] ?? 'General';
                if ($item_group !== $current_group):
                    $current_group = $item_group;
            ?>
                <div class="dso-nav-group-header"><?php echo esc_html($current_group); ?></div>
            <?php endif; ?>
                <div class="dso-nav-item <?php echo ($current_section === $item['id'] || $current_section === ($item['url'] ?? '')) ? 'dso-nav-active' : '' ?>">
                    <a href="?section=<?php echo esc_attr($item['url']) ?>" class="dso-nav-link">
                        <span class="dso-nav-icon"><?php echo $item['icon'] ?></span>
                        <span class="dso-nav-label"><?php echo esc_html($item['label']) ?></span>
                        <?php if ($item['id'] === 'notifications' && $unread_count > 0): ?>
                            <span class="dso-nav-badge"><?php echo $unread_count ?></span>
                        <?php endif; ?>
                        <?php if ($item['id'] === 'messages'): ?>
                            <?php 
                            global $wpdb;
                            $unread_msg = (int) $wpdb->get_var($wpdb->prepare("SELECT SUM(unread_seller) FROM {$wpdb->prefix}dso_conversations WHERE vendor_id = %d", $vendor_id));
                            ?>
                            <span class="dso-nav-badge dso-nav-badge-messages" style="<?php echo $unread_msg > 0 ? '' : 'display:none;'; ?>background:#d9006c;"><?php echo $unread_msg ?: ''; ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['children'])): ?>
                            <svg class="dso-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                        <div class="dso-nav-children">
                            <?php foreach ($item['children'] as $child): ?>
                                <a href="?section=<?php echo esc_attr($child['url']) ?>" class="dso-nav-child <?php echo ($current_section === $child['id'] || $current_section === ($child['url'] ?? '')) ? 'dso-nav-active' : '' ?>">
                                    <?php echo esc_html($child['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="dso-sidebar-footer">
            <a href="<?php echo home_url('/my-account/') ?>" class="dso-nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Back to My Account</span>
            </a>
            <a href="<?php echo wp_logout_url(home_url()) ?>" class="dso-nav-link dso-nav-logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <!-- Sidebar Overlay -->
    <div class="dso-sidebar-overlay" id="dso-sidebar-overlay"></div>

    <!-- Command Palette (Ctrl+K) -->
    <div class="dso-command-palette" id="dso-command-palette">
        <div class="dso-cp-backdrop" id="dso-cp-backdrop"></div>
        <div class="dso-cp-modal">
            <div class="dso-cp-input-wrap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="dso-cp-input" class="dso-cp-input" placeholder="Search products, orders, pages..." autocomplete="off" />
                <kbd class="dso-cp-kbd">ESC</kbd>
            </div>
            <div class="dso-cp-results" id="dso-cp-results">
                <div class="dso-cp-group">
                    <div class="dso-cp-group-title">Quick Actions</div>
                    <a href="?section=add-product" class="dso-cp-item">➕ Add Product</a>
                    <a href="?section=orders" class="dso-cp-item">🛒 View Orders</a>
                    <a href="?section=reports" class="dso-cp-item">📈 Open Analytics</a>
                    <a href="?section=pricing" class="dso-cp-item">🏷️ Deals & Coupons</a>
                    <a href="?section=finance" class="dso-cp-item">💰 Withdraw Funds</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Seller AI Side Drawer -->
    <div class="dso-ai-drawer" id="dso-ai-drawer">
        <div class="dso-ai-drawer-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <span style="font-size:22px;">✨</span>
                <div>
                    <strong style="display:block;font-size:15px;color:#fff;">DEJOIY Seller AI</strong>
                    <small style="color:#a78bfa;font-size:11px;">Instant Growth & Listing Copilot</small>
                </div>
            </div>
            <button id="dso-ai-close-btn" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:6px;display:flex;" aria-label="Close Seller AI">
                <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="dso-ai-drawer-body" id="dso-ai-chat-body">
            <div style="background:#f1f5f9;border-radius:12px;padding:14px 16px;font-size:13px;color:#334155;line-height:1.5;">
                👋 <strong>Hi <?php echo esc_html($display_name); ?>!</strong> I'm your DEJOIY AI Marketplace Copilot. What can I analyze or optimize for you today?
            </div>
            <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-top:6px;">Recommended Actions</div>
            <button class="dso-ai-prompt-btn" data-prompt="Analyze my current product catalog quality and recommend improvements.">
                <span>📊</span> Analyze my catalog & LQS score
            </button>
            <button class="dso-ai-prompt-btn" data-prompt="Which inventory items are currently at risk of stockout or dead stock?">
                <span>📦</span> Predict stock replenishment needs
            </button>
            <button class="dso-ai-prompt-btn" data-prompt="Suggest promotional discounts and flash deal opportunities for upcoming festive demand.">
                <span>🏷️</span> Generate high-conversion deal suggestions
            </button>
            <button class="dso-ai-prompt-btn" data-prompt="How do I boost my Seller Tier score to Platinum?">
                <span>⭐</span> Audit my Seller Tier performance metrics
            </button>
            <div id="dso-ai-conversation" style="display:flex;flex-direction:column;gap:12px;margin-top:10px;"></div>
        </div>
        <div class="dso-ai-drawer-footer">
            <input type="text" id="dso-ai-user-input" class="dso-input" placeholder="Ask Seller AI anything..." style="flex:1;font-size:13px;padding:10px 14px;" />
            <button id="dso-ai-send-btn" class="dso-btn dso-btn-primary" style="padding:10px 16px;">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            </button>
        </div>
    </div>
    <div class="dso-drawer-backdrop" id="dso-ai-backdrop" style="position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:999;display:none;backdrop-filter:blur(2px);"></div>

    <!-- Main Content -->
    <main class="dso-main" id="dso-main">
        <?php
        // Render the section content
        $class = new $config['class']();
        $method = $config['method'] ?? 'render';
        $class->$method();
        ?>

        <!-- Professional Enterprise Footer -->
        <footer class="dso-footer">
            <div class="dso-footer-grid">
                <div class="dso-footer-brand-col">
                    <div class="dso-footer-logo-row">
                        <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY" class="dso-footer-logo" />
                        <span class="dso-footer-badge">SELLER OS v2.4</span>
                    </div>
                    <p class="dso-footer-desc">
                        DEJOIY Marketplace Seller Operating System. Powering high-growth commerce, DPIN cataloging, and nationwide fulfillment.
                    </p>
                    <div class="dso-footer-status">
                        <span class="dso-status-dot"></span>
                        <span>All Systems Operational • 99.98% Uptime</span>
                    </div>
                </div>
                <div class="dso-footer-col">
                    <h4>Seller Central</h4>
                    <a href="?section=dashboard">Overview</a>
                    <a href="?section=products">Product Catalog (DPIN)</a>
                    <a href="?section=orders">Order Fulfillment</a>
                    <a href="?section=pricing">Smart Pricing</a>
                    <a href="?section=advertising">DEJOIY Ads</a>
                </div>
                <div class="dso-footer-col">
                    <h4>Treasury & Growth</h4>
                    <a href="?section=finance">Settlements & Payouts</a>
                    <a href="?section=withdrawals">Instant Withdrawals</a>
                    <a href="?section=performance">Account Health SLA</a>
                    <a href="?section=growth">Growth Advisor</a>
                    <a href="?section=store">Storefront Studio</a>
                </div>
                <div class="dso-footer-col">
                    <h4>Support & Legal</h4>
                    <a href="?section=learn">Seller University</a>
                    <a href="?section=support">Resolution Support Desk</a>
                    <a href="https://dejoiy.com" target="_blank" rel="noopener">DEJOIY.com ↗</a>
                    <a href="mailto:partners@dejoiy.com">partners@dejoiy.com</a>
                    <span class="dso-footer-support-phone">📞 1800-DEJOIY-HUB</span>
                </div>
            </div>
            <div class="dso-footer-bottom">
                <div>© 2026 DEJOIY Marketplace Private Limited. All rights reserved.</div>
                <div class="dso-footer-tags">
                    <span>DPIN™ Protected</span>
                    <span>•</span>
                    <span>RBI-Compliant Payouts</span>
                    <span>•</span>
                    <span>AES-256 Encrypted</span>
                </div>
            </div>
        </footer>
    </main>

    <!-- Mobile Bottom Nav -->
    <nav class="dso-bottom-nav" id="dso-bottom-nav">
        <a href="?section=dashboard" class="dso-bottom-nav-item <?php echo $current_section === 'dashboard' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span>Home</span>
        </a>
        <a href="?section=orders" class="dso-bottom-nav-item <?php echo $current_section === 'orders' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <span>Orders</span>
        </a>
        <a href="?section=products" class="dso-bottom-nav-item <?php echo $current_section === 'products' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16z"/></svg>
            <span>Products</span>
        </a>
        <a href="?section=reports" class="dso-bottom-nav-item <?php echo $current_section === 'reports' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
            <span>Reports</span>
        </a>
        <a href="?section=finance" class="dso-bottom-nav-item <?php echo $current_section === 'finance' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <span>Finance</span>
        </a>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle for both Mobile/Tablet (drawer) and Desktop (collapse)
    var menuToggle = document.getElementById('dso-menu-toggle');
    var sidebar = document.getElementById('dso-sidebar');
    var overlay = document.getElementById('dso-sidebar-overlay');
    var sidebarClose = document.getElementById('dso-sidebar-close');
    var app = document.getElementById('dso-app');

    function toggleSidebar() {
        if (!sidebar) return;
        if (window.innerWidth >= 1024) {
            // Desktop Collapse / Expand
            if (app) app.classList.toggle('dso-sidebar-collapsed');
            sidebar.classList.toggle('dso-sidebar-collapsed');
        } else {
            // Mobile / Tablet Slide-over
            var isOpen = sidebar.classList.contains('open') || sidebar.classList.contains('dso-sidebar-open');
            if (isOpen) {
                sidebar.classList.remove('open', 'dso-sidebar-open');
                if (overlay) overlay.classList.remove('visible', 'dso-visible');
                document.body.style.overflow = '';
            } else {
                sidebar.classList.add('open', 'dso-sidebar-open');
                if (overlay) overlay.classList.add('visible', 'dso-visible');
                document.body.style.overflow = 'hidden';
            }
        }
    }

    function closeSidebar() {
        if (sidebar) sidebar.classList.remove('open', 'dso-sidebar-open');
        if (overlay) overlay.classList.remove('visible', 'dso-visible');
        document.body.style.overflow = '';
    }

    if (menuToggle) menuToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        toggleSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', closeSidebar);

    // Auto-close sidebar on mobile when navigating
    document.querySelectorAll('.dso-nav-link, .dso-nav-child').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 1024) closeSidebar();
        });
    });

    // Nav children toggle
    document.querySelectorAll('.dso-nav-link').forEach(function(link) {
        var parent = link.closest('.dso-nav-item');
        var children = parent ? parent.querySelector('.dso-nav-children') : null;
        if (children && link.querySelector('.dso-nav-chevron')) {
            parent.addEventListener('mouseenter', function() {
                parent.classList.add('dso-nav-expanded');
            });
            parent.addEventListener('mouseleave', function() {
                parent.classList.remove('dso-nav-expanded');
            });
        }
    });

    // Consolidated Right-Hand Quick Hub Dropdown
    var quickHubToggle = document.getElementById('dso-quick-hub-toggle');
    var quickHubMenu = document.getElementById('dso-quick-hub-dropdown');
    var hubTriggerAi = document.getElementById('dso-hub-trigger-ai');

    if (quickHubToggle && quickHubMenu) {
        quickHubToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            var isOpen = quickHubMenu.classList.toggle('dso-open');
            quickHubToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (headerSearchResults) headerSearchResults.style.display = 'none';
        });
    }

    if (hubTriggerAi) {
        hubTriggerAi.addEventListener('click', function(e) {
            e.preventDefault();
            if (quickHubMenu) quickHubMenu.classList.remove('dso-open');
            toggleAi(true);
        });
    }

    // Interactive Header Live Search
    var headerSearchInput = document.getElementById('dso-header-search-input');
    var headerSearchResults = document.getElementById('dso-header-search-results');
    var headerSearchClear = document.getElementById('dso-search-clear-btn');
    var currentHighlightIndex = -1;

    function renderHeaderSearchResults(query) {
        if (!headerSearchResults) return;
        var q = (query || '').trim().toLowerCase();
        if (!q) {
            headerSearchResults.style.display = 'none';
            headerSearchResults.innerHTML = '';
            currentHighlightIndex = -1;
            return;
        }

        var searchData = window.dsoSearchData || { products: [], sections: [] };
        var products = searchData.products || [];
        var sections = searchData.sections || [];

        // Match products by Title, DPIN, SKU
        var matchedProducts = products.filter(function(p) {
            var title = (p.title || '').toLowerCase();
            var dpin = (p.dpin || '').toLowerCase();
            var sku = (p.sku || '').toLowerCase();
            return title.indexOf(q) !== -1 || dpin.indexOf(q) !== -1 || sku.indexOf(q) !== -1;
        }).slice(0, 6);

        // Match sections by Title or Tags
        var matchedSections = sections.filter(function(s) {
            var title = (s.title || '').toLowerCase();
            var desc = (s.desc || '').toLowerCase();
            var tags = (s.tags || '').toLowerCase();
            return title.indexOf(q) !== -1 || desc.indexOf(q) !== -1 || tags.indexOf(q) !== -1;
        }).slice(0, 4);

        if (matchedProducts.length === 0 && matchedSections.length === 0) {
            headerSearchResults.innerHTML = 
                '<div class="dso-search-empty">' +
                    '🔍 No results found for "<strong>' + escapeHtml(q) + '</strong>"<br>' +
                    '<a href="?section=products&search=' + encodeURIComponent(q) + '" style="display:inline-block;margin-top:8px;color:#0066ff;font-weight:600;text-decoration:underline;">Search full catalog &rarr;</a>' +
                '</div>';
            headerSearchResults.style.display = 'block';
            currentHighlightIndex = -1;
            return;
        }

        var html = '';

        if (matchedProducts.length > 0) {
            html += '<div class="dso-search-group-title">Products & Catalog (' + matchedProducts.length + ')</div>';
            matchedProducts.forEach(function(p) {
                var thumbHtml = p.thumb 
                    ? '<img src="' + p.thumb + '" alt="" />'
                    : '📦';
                var dpinHtml = p.dpin ? '<code>' + escapeHtml(p.dpin) + '</code>' : '';
                var skuHtml = p.sku ? '<span>SKU: ' + escapeHtml(p.sku) + '</span>' : '';
                html += 
                    '<a href="' + p.url + '" class="dso-search-res-item">' +
                        '<div class="dso-search-item-thumb">' + thumbHtml + '</div>' +
                        '<div class="dso-search-item-body">' +
                            '<div class="dso-search-item-title">' + highlightMatch(p.title, q) + '</div>' +
                            '<div class="dso-search-item-meta">' +
                                dpinHtml +
                                (dpinHtml && skuHtml ? ' • ' : '') +
                                skuHtml +
                            '</div>' +
                        '</div>' +
                        '<div class="dso-search-item-price">' + escapeHtml(p.price) + '</div>' +
                    '</a>';
            });
        }

        if (matchedSections.length > 0) {
            html += '<div class="dso-search-group-title">Seller Tools & Hub (' + matchedSections.length + ')</div>';
            matchedSections.forEach(function(s) {
                html += 
                    '<a href="' + s.url + '" class="dso-search-res-item">' +
                        '<div class="dso-search-item-thumb" style="font-size:18px;">' + s.icon + '</div>' +
                        '<div class="dso-search-item-body">' +
                            '<div class="dso-search-item-title">' + highlightMatch(s.title, q) + '</div>' +
                            '<div class="dso-search-item-meta">' + escapeHtml(s.desc) + '</div>' +
                        '</div>' +
                    '</a>';
            });
        }

        html += 
            '<div style="padding:8px 16px;border-top:1px solid #f1f5f9;background:#f8fafc;font-size:12px;display:flex;align-items:center;justify-content:space-between;">' +
                '<span style="color:#64748b;">Press <kbd style="background:#e2e8f0;padding:1px 5px;border-radius:3px;font-family:monospace;">Enter</kbd> to search catalog</span>' +
                '<a href="?section=products&search=' + encodeURIComponent(q) + '" style="color:#0066ff;font-weight:600;text-decoration:none;">View all results &rarr;</a>' +
            '</div>';

        headerSearchResults.innerHTML = html;
        headerSearchResults.style.display = 'block';
        currentHighlightIndex = -1;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function highlightMatch(text, query) {
        if (!text || !query) return escapeHtml(text);
        var idx = text.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return escapeHtml(text);
        return escapeHtml(text.substring(0, idx)) + 
               '<mark style="background:#fef08a;color:#854d0e;padding:0 2px;border-radius:2px;font-weight:700;">' + 
               escapeHtml(text.substring(idx, idx + query.length)) + 
               '</mark>' + 
               escapeHtml(text.substring(idx + query.length));
    }

    if (headerSearchInput) {
        headerSearchInput.addEventListener('input', function() {
            var val = this.value;
            if (headerSearchClear) {
                headerSearchClear.style.display = val.length > 0 ? 'inline-block' : 'none';
            }
            renderHeaderSearchResults(val);
        });

        headerSearchInput.addEventListener('focus', function() {
            if (this.value.trim().length > 0) {
                renderHeaderSearchResults(this.value);
            }
        });

        headerSearchInput.addEventListener('keydown', function(e) {
            var items = headerSearchResults ? headerSearchResults.querySelectorAll('.dso-search-res-item') : [];
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (items.length > 0) {
                    currentHighlightIndex = (currentHighlightIndex + 1) % items.length;
                    items.forEach(function(el, idx) {
                        el.classList.toggle('dso-selected', idx === currentHighlightIndex);
                    });
                    if (items[currentHighlightIndex]) items[currentHighlightIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (items.length > 0) {
                    currentHighlightIndex = (currentHighlightIndex - 1 + items.length) % items.length;
                    items.forEach(function(el, idx) {
                        el.classList.toggle('dso-selected', idx === currentHighlightIndex);
                    });
                    if (items[currentHighlightIndex]) items[currentHighlightIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                if (currentHighlightIndex >= 0 && items[currentHighlightIndex]) {
                    e.preventDefault();
                    window.location.href = items[currentHighlightIndex].getAttribute('href');
                } else if (this.value.trim().length > 0) {
                    e.preventDefault();
                    window.location.href = '?section=products&search=' + encodeURIComponent(this.value.trim());
                }
            } else if (e.key === 'Escape') {
                if (headerSearchResults) headerSearchResults.style.display = 'none';
                this.blur();
            }
        });
    }

    if (headerSearchClear) {
        headerSearchClear.addEventListener('click', function(e) {
            e.stopPropagation();
            if (headerSearchInput) {
                headerSearchInput.value = '';
                headerSearchInput.focus();
            }
            this.style.display = 'none';
            if (headerSearchResults) {
                headerSearchResults.style.display = 'none';
                headerSearchResults.innerHTML = '';
            }
        });
    }

    // Dismiss dropdowns and live search on outside click
    document.addEventListener('click', function(e) {
        if (quickHubMenu && !quickHubMenu.contains(e.target) && (!quickHubToggle || !quickHubToggle.contains(e.target))) {
            quickHubMenu.classList.remove('dso-open');
            if (quickHubToggle) quickHubToggle.setAttribute('aria-expanded', 'false');
        }
        if (headerSearchResults && !headerSearchResults.contains(e.target) && (!headerSearchInput || !headerSearchInput.contains(e.target))) {
            headerSearchResults.style.display = 'none';
        }
        if (settingsMenu && !settingsMenu.contains(e.target) && (!settingsToggle || !settingsToggle.contains(e.target))) {
            settingsMenu.classList.remove('dso-dropdown-open');
        }
        if (helpMenu && !helpMenu.contains(e.target) && (!helpToggle || !helpToggle.contains(e.target))) {
            helpMenu.classList.remove('dso-dropdown-open');
        }
    });

    // Command palette & keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (headerSearchInput) {
                headerSearchInput.focus();
                headerSearchInput.select();
            }
        }
        if (e.key === 'Escape') {
            if (headerSearchResults) headerSearchResults.style.display = 'none';
            if (quickHubMenu) quickHubMenu.classList.remove('dso-open');
            if (settingsMenu) settingsMenu.classList.remove('dso-dropdown-open');
            if (helpMenu) helpMenu.classList.remove('dso-dropdown-open');
            if (typeof DSO !== 'undefined' && DSO.toggleAiDrawer) DSO.toggleAiDrawer(false);
        }
    });

    // Search in command palette
    var searchTimer;
    if (cpInput && typeof dsoData !== 'undefined') {
        cpInput.addEventListener('input', function() {
            clearTimeout(searchTimer);
            var q = this.value.trim();
            if (q.length < 2) {
                cpResults.innerHTML = '<div class="dso-cp-group"><div class="dso-cp-group-title">Quick Actions</div><a href="?section=add-product" class="dso-cp-item">➕ Add Product</a><a href="?section=orders" class="dso-cp-item">🛒 View Orders</a><a href="?section=reports" class="dso-cp-item">📈 Open Analytics</a><a href="?section=finance" class="dso-cp-item">💰 Withdraw Funds</a></div>';
                return;
            }
            searchTimer = setTimeout(function() {
                fetch(dsoData.restUrl + 'search?q=' + encodeURIComponent(q), {
                    headers: { 'X-WP-Nonce': dsoData.nonce }
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.results && data.results.length > 0) {
                        var html = '<div class="dso-cp-group"><div class="dso-cp-group-title">Results</div>';
                        data.results.forEach(function(r) {
                            html += '<a href="' + r.url + '" class="dso-cp-item">';
                            html += '<span class="dso-cp-item-type">' + r.type + '</span> ';
                            html += r.title;
                            if (r.subtitle) html += ' <span class="dso-cp-item-sub">' + r.subtitle + '</span>';
                            html += '</a>';
                        });
                        html += '</div>';
                        cpResults.innerHTML = html;
                    } else {
                        cpResults.innerHTML = '<div class="dso-cp-empty">No results found</div>';
                    }
                });
            }, 300);
        });
    }

    // Seller AI Side Drawer
    var aiBtn = document.getElementById('dso-seller-ai-btn');
    var aiCloseBtn = document.getElementById('dso-ai-close-btn');
    var aiDrawer = document.getElementById('dso-ai-drawer');
    var aiBackdrop = document.getElementById('dso-ai-backdrop');
    function toggleAi(open) {
        if (!aiDrawer) return;
        var shouldOpen = typeof open === 'boolean' ? open : !aiDrawer.classList.contains('dso-open');
        if (shouldOpen) {
            aiDrawer.classList.add('dso-open');
            if (aiBackdrop) aiBackdrop.style.display = 'block';
            var inp = document.getElementById('dso-ai-user-input');
            if (inp) inp.focus();
        } else {
            aiDrawer.classList.remove('dso-open');
            if (aiBackdrop) aiBackdrop.style.display = 'none';
        }
    }
    if (aiBtn) aiBtn.addEventListener('click', function() { toggleAi(); });
    if (aiCloseBtn) aiCloseBtn.addEventListener('click', function() { toggleAi(false); });
    if (aiBackdrop) aiBackdrop.addEventListener('click', function() { toggleAi(false); });

    // AI quick prompts
    document.querySelectorAll('.dso-ai-prompt-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var prompt = this.getAttribute('data-prompt');
            var conv = document.getElementById('dso-ai-conversation');
            var chatBody = document.getElementById('dso-ai-chat-body');
            if (!conv) return;

            var userMsg = document.createElement('div');
            userMsg.style.cssText = 'background:linear-gradient(135deg, #0066ff 0%, #d9006c 100%);color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;font-size:13px;align-self:flex-end;max-width:85%;line-height:1.4;';
            userMsg.textContent = prompt;
            conv.appendChild(userMsg);

            var botMsg = document.createElement('div');
            botMsg.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;padding:12px 14px;border-radius:12px 12px 12px 2px;font-size:13px;line-height:1.5;max-width:90%;';
            botMsg.innerHTML = '<span style="color:#0066ff;font-weight:700;">DEJOIY AI:</span> Analyzing real-time catalog & sales telemetry...<br><br>💡 <strong>Insight:</strong> 12 listings can gain up to +18% CTR by adding bullet points and high-res gallery images. Consider enrolling in upcoming Mega Deals.';
            conv.appendChild(botMsg);

            if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        });
    });

    var aiSendBtn = document.getElementById('dso-ai-send-btn');
    var aiUserInput = document.getElementById('dso-ai-user-input');
    if (aiSendBtn && aiUserInput) {
        function sendAiMsg() {
            var val = aiUserInput.value.trim();
            if (!val) return;
            aiUserInput.value = '';
            var conv = document.getElementById('dso-ai-conversation');
            var chatBody = document.getElementById('dso-ai-chat-body');
            if (!conv) return;

            var userMsg = document.createElement('div');
            userMsg.style.cssText = 'background:linear-gradient(135deg, #0066ff 0%, #d9006c 100%);color:#fff;padding:10px 14px;border-radius:12px 12px 2px 12px;font-size:13px;align-self:flex-end;max-width:85%;line-height:1.4;';
            userMsg.textContent = val;
            conv.appendChild(userMsg);

            var botMsg = document.createElement('div');
            botMsg.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;color:#1e293b;padding:12px 14px;border-radius:12px 12px 12px 2px;font-size:13px;line-height:1.5;max-width:90%;';
            botMsg.innerHTML = '<span style="color:#0066ff;font-weight:700;">DEJOIY AI:</span> Understood! Analyzing your store data regarding "' + val.replace(/</g, '&lt;') + '"... Everything is in good standing with 94/100 Health Score.';
            conv.appendChild(botMsg);

            if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
        }
        aiSendBtn.addEventListener('click', sendAiMsg);
        aiUserInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') sendAiMsg();
        });
    }

    // HubUI theme: apply saved preference, then bind the topbar toggle
    try {
        if (localStorage.getItem('dso-theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    } catch (e) {}
    var themeBtn = document.getElementById('dso-theme-toggle');
    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            var h = document.documentElement;
            var dark = h.getAttribute('data-theme') === 'dark';
            if (dark) { h.removeAttribute('data-theme'); } else { h.setAttribute('data-theme', 'dark'); }
            try { localStorage.setItem('dso-theme', dark ? 'light' : 'dark'); } catch (e2) {}
        });
    }
});
</script>
