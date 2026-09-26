<?php
/**
 * DEJOIY Seller Hub — Marketing Landing Page
 * https://sellerhub.dejoiy.com/
 * Beautiful landing page with Sign In + Get Started CTAs
 */

// Set up environment
$_SERVER['SERVER_PORT']   = '8080';
$_SERVER['HTTP_HOST']     = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$_SERVER['REQUEST_URI']   = '/';

// Load WordPress
define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-load.php';

// Override URLs after WP loads
$host    = $_SERVER['HTTP_HOST'];
$site_url = 'https://' . $host;

add_filter('option_siteurl',   fn($v) => $site_url, 9999);
add_filter('option_home',      fn($v) => $site_url, 9999);
add_filter('site_url',         fn($v) => $site_url, 9999);
add_filter('home_url',         fn($v) => $site_url, 9999);
add_filter('admin_url',        fn($v) => $site_url . '/wp-admin/', 9999);
add_filter('login_url',        fn($v) => $site_url . '/wp-login.php', 9999);
add_filter('force_ssl_admin',  '__return_false', 9999);
add_filter('force_ssl_login',  '__return_false', 9999);
add_filter('redirect_canonical', '__return_false', 9999);
add_filter('superpwa_display_status', '__return_false', 9999);

remove_action('template_redirect', 'wp_redirect_canonical', 20);
remove_filter('template_redirect', 'wp_redirect_canonical', 20);

// Fetch homepage hero copy from WP options (with sensible defaults)
$hero_title    = get_option('dso_landing_hero_title',    'Run Your Dejoiy Store From One Brilliant Hub');
$hero_subtitle = get_option('dso_landing_hero_subtitle', 'Products, orders, payouts, shipping, ads — one place. Built for sellers who mean business.');
$hero_price    = get_option('dso_landing_hero_price',     '₹0/month. No hidden fees.');

$cta_signin    = get_option('dso_landing_cta_signin',     'Sign In');
$cta_getstarted = get_option('dso_landing_cta_getstarted', 'Get Started Free');

$pricing_heading      = get_option('dso_landing_pricing_heading', 'Simple, honest pricing');
$pricing_desc         = get_option('dso_landing_pricing_desc',     'Start free, upgrade when you\'re scaling.');
$pricing_free_label   = get_option('dso_landing_pricing_free_label', 'Starter — Free');
$pricing_free_desc    = get_option('dso_landing_pricing_free_desc', 'Everything you need to launch your first 50 products.');
$pricing_pro_label    = get_option('dso_landing_pricing_pro_label', 'Growth — ₹1,499/mo');
$pricing_pro_desc     = get_option('dso_landing_pricing_pro_desc', 'Bulk tools, repricer, ads, API access, priority support.');
$pricing_enterprise_label = get_option('dso_landing_pricing_enterprise_label', 'Enterprise');
$pricing_enterprise_desc  = get_option('dso_landing_pricing_enterprise_desc', 'Custom onboarding, dedicated account manager, multi-brand.');

$faq_heading   = get_option('dso_landing_faq_heading',   'Frequently asked questions');
$faq_1_q      = get_option('dso_landing_faq_1_q',       'What is Seller Hub?');
$faq_1_a      = get_option('dso_landing_faq_1_a',       'Seller Hub is Dejoiy\'s all-in-one operating system for marketplace sellers — manage products (DPINs), orders, shipping, payouts, ads, and analytics from a single dashboard.');
$faq_2_q      = get_option('dso_landing_faq_2_q',       'Is it free to start?');
$faq_2_a      = get_option('dso_landing_faq_2_a',       'Yes. The Starter plan is free forever for up to 50 products. Upgrade to Growth when you need bulk tools, automated pricing, and ads.');
$faq_3_q      = get_option('dso_landing_faq_3_q',       'How do I become a seller?');
$faq_3_a      = get_option('dso_landing_faq_3_a',       'Click "Get Started Free" above, verify your identity, and list your first product in minutes. No credit card required.');
$faq_4_q      = get_option('dso_landing_faq_4_q',       'Which marketplaces or channels does it work with?');
$faq_4_a      = get_option('dso_landing_faq_4_a',       'Seller Hub powers your Dejoiy storefront and syncs with the Dejoiy marketplace. Multi-channel expansion is available on Enterprise.');

$footer_copy   = get_option('dso_landing_footer_copy',   '© 2026 DEJOIY Marketplace Private Limited. All rights reserved.');
$footer_email  = get_option('dso_landing_footer_email',  'partners@dejoiy.com');

// Vendor store count for social proof (safe — methods may not exist yet)
$vendor_count  = 0;
$product_count = 0;
$processed_qty = 0;
try {
    if (class_exists('DSO_Marketplace')) {
        if (method_exists('DSO_Marketplace', 'get_active_vendor_count'))  $vendor_count  = DSO_Marketplace::get_active_vendor_count();
        if (method_exists('DSO_Marketplace', 'get_total_product_count')) $product_count = DSO_Marketplace::get_total_product_count();
        if (method_exists('DSO_Marketplace', 'get_total_fulfilled_orders')) $processed_qty = DSO_Marketplace::get_total_fulfilled_orders();
    }
} catch (\Throwable $e) { /* keep zeros */ }
if ($vendor_count < 0)  $vendor_count  = 0;
if ($product_count < 0) $product_count = 0;
if ($processed_qty < 0) $processed_qty = 0;

// Featured categories (static DEMO content — replace with dynamic DB query later)
$hero_features = [
    [
        'icon'  => 'package',
        'title' => 'DPIN Product Catalog',
        'desc'  => 'Generate, publish, and manage DPIN-protected listings with one click.',
    ],
    [
        'icon'  => 'cart-ship',
        'title' => 'Orders & Dispatch',
        'desc'  => 'Track every order from placement to delivery. Auto-print shipping labels.',
    ],
    [
        'icon'  => 'rupee',
        'title' => 'Payouts & Wallet',
        'desc'  => 'RBI-compliant settlements, transparent ledger, instant withdrawal requests.',
    ],
    [
        'icon'  => 'truck',
        'title' => 'Logistics & Shipping',
        'desc'  => 'Pincode coverage maps, courier partner rates, and real-time tracking.',
    ],
    [
        'icon'  => 'tag',
        'title'  => 'Pricing & Bulk Deals',
        'desc'  => 'Dynamic rules, B2B wholesale tiers, volume discounts, and flash deals.',
    ],
    [
        'icon'  => 'megaphone',
        'title' => 'Advertising & Reach',
        'desc'  => 'Sponsored listings, impression budgets, and campaign performance dashboards.',
    ],
];

$stats = [
    'vendor_count'   => number_format($vendor_count),
    'product_count'  => number_format($product_count),
    'processed_qty'  => number_format($processed_qty),
];

// Build Sign In link (triggers seller-hub.php auth flow)
$signin_url = $site_url . '/seller-hub.php';

// Build Get Started link (WordPress signup / account creation page)
$signup_url = $site_url . '/wp-signup.php';
if (function_exists('wcfmmp_get_marketplace_signup_url')) {
    $signup_url = wcfmmp_get_marketplace_signup_url();
}
if (empty($signup_url) || strpos($signup_url, 'http') === false) {
    $signup_url = $site_url . '/register/';
}

?>
<?php
/**
 * Feature-icon helper used in the hero-features loop.
 * Falls back to dj_icon() from the Dejoiy Seller OS plugin, or
 * a simple inline SVG if the plugin is not loaded.
 */
if (!function_exists('dejoiy_seller_icon')) {
    function dejoiy_seller_icon($name, $size = 22) {
        if (function_exists('dj_icon')) {
            return dj_icon($name, $size);
        }
        // Generic inline SVG fallback keyed by icon name
        $icons = [
            'package'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>',
            'cart-ship'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>',
            'rupee'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
            'tag'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
            'truck'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v4h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
            'megaphone'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><path d="M3 11l18-5v12L3 13v-2z"/><path d="M11.6 16.8a3 3 0 11-5.2-1.2"/></svg>',
            'chart'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="' . $size . '" height="' . $size . '"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
        ];
        return isset($icons[$name]) ? $icons[$name] : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($hero_title); ?> — DEJOIY Seller Hub</title>
    <meta name="description" content="<?php echo esc_html($hero_subtitle); ?>">
    <link rel="icon" type="image/png" href="<?php echo esc_url($site_url); ?>/wp-content/uploads/2026/05/DEJOIY-FAVICON-100x100.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           DEJOIY Seller Hub Landing Page — Core Styles
           ============================================================ */

        :root {
            --color-bg:        #faf8f3;
            --color-surface:   #ffffff;
            --color-card:      #ffffff;
            --color-border:    #d8dcd4;
            --color-primary:   #ef632f;
            --color-primary-2: #c3471b;
            --color-accent:    #f0c417;
            --color-text:      #172521;
            --color-muted:     #59645f;
            --color-muted-2:   #8a938c;
            --color-success:   #1e7a4c;
            --color-info:      #172521;
            --font-body:       'Inter', system-ui, -apple-system, sans-serif;
            --font-display:    'Barlow', 'Inter', system-ui, sans-serif;
            --radius-sm:       8px;
            --radius-md:       12px;
            --radius-lg:       20px;
            --shadow-card:     0 15px 50px rgba(23, 37, 33, 0.06);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: var(--font-body);
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ---------- Reusable link & button resets ---------- */
        a {
            text-decoration: none;
            color: inherit;
        }

        .dso-landing-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-family: var(--font-body);
            font-weight: 700;
            font-size: 15px;
            padding: 14px 28px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            letter-spacing: 0.2px;
            white-space: nowrap;
        }

        .dso-landing-btn--primary {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
            box-shadow: 0 8px 24px -6px rgba(239, 99, 47, 0.35);
        }
        .dso-landing-btn--primary:hover {
            background: #c3471b;
            border-color: #c3471b;
            box-shadow: 0 12px 28px -8px rgba(239, 99, 47, 0.45);
        }

        .dso-landing-btn--ghost {
            background: transparent;
            color: var(--color-text);
            border-color: #d8dcd4;
        }
        .dso-landing-btn--ghost:hover {
            background: #fff;
            border-color: #172521;
        }

        .dso-landing-btn--filled {
            background: var(--color-accent);
            color: #172521;
            border-color: var(--color-accent);
        }
        .dso-landing-btn--filled:hover {
            background: #e2b80d;
            border-color: #e2b80d;
        }

        .dso-landing-btn--small {
            font-size: 13px;
            padding: 9px 18px;
            border-radius: 8px;
        }

        .dso-landing-btn--large {
            font-size: 17px;
            padding: 17px 36px;
            border-radius: 12px;
        }

        /* ---------- Top bar (lightweight nav for landing) ---------- */

        .dso-landing-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            background: rgba(250, 248, 243, 0.88);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-bottom: 1px solid #d8dcd4;
            padding: 16px 0;
        }

        .dso-landing-topbar-inner {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dso-landing-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dso-landing-brand-img {
            height: 36px;
            width: auto;
            filter: brightness(0);
        }

        .dso-landing-brand-text {
            font-weight: 800;
            font-size: 18px;
            letter-spacing: -0.3px;
            color: var(--color-text);
        }

        .dso-landing-brand-badge {
            background: var(--color-primary);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.8px;
            padding: 3px 8px;
            border-radius: 6px;
            margin-left: 4px;
            text-transform: uppercase;
        }

        .dso-landing-topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dso-landing-topbar-divider {
            width: 1px;
            height: 22px;
            background: #d8dcd4;
            margin: 0 6px;
        }

        /* ---------- Page wrapper ---------- */

        .dso-landing-page {
            padding-top: 84px;
        }

        /* ---------- HERO ---------- */

        .dso-hero {
            position: relative;
            padding: 96px 28px 80px;
            text-align: center;
            overflow: hidden;
        }

        .dso-hero-glow {
            position: absolute;
            top: -120px;
            left: 50%;
            transform: translateX(-50%);
            width: 700px;
            height: 400px;
            background: radial-gradient(ellipse at center, rgba(239, 99, 47, 0.13) 0%, rgba(239, 99, 47, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        .dso-hero-glow-bottom {
            position: absolute;
            bottom: -160px;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 500px;
            background: radial-gradient(ellipse at center, rgba(244, 201, 175, 0.45) 0%, rgba(244, 201, 175, 0) 70%);
            pointer-events: none;
            z-index: 0;
        }

        .dso-hero-inner {
            position: relative;
            z-index: 1;
            max-width: 960px;
            margin: 0 auto;
        }

        .dso-hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #d8dcd4;
            border-radius: 999px;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-muted);
            margin-bottom: 32px;
            letter-spacing: 0.2px;
        }

        .dso-hero-badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--color-success);
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50%   { opacity: 0.35; }
        }

        .dso-hero-title {
            font-family: var(--font-display);
            font-size: clamp(40px, 6vw, 72px);
            font-weight: 800;
            line-height: 1.04;
            letter-spacing: -0.03em;
            margin-bottom: 20px;
            color: #172521;
        }

        .dso-hero-subtitle {
            font-size: clamp(17px, 1.8vw, 22px);
            color: var(--color-muted);
            max-width: 680px;
            margin: 0 auto 36px;
            line-height: 1.6;
            font-weight: 400;
        }

        .dso-hero-ctas {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 48px;
        }

        .dso-hero-price-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--color-muted);
            background: #fff;
            border: 1px solid #d8dcd4;
            border-radius: 999px;
            padding: 7px 16px;
            margin-top: 4px;
        }

        .dso-hero-price-tag-label {
            font-weight: 700;
            color: #c3471b;
        }

        /* ---------- HERO INTRO CARDS (3 meta cards) ---------- */

        .dso-hero-intro-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            max-width: 820px;
            margin: 40px auto 0;
        }

        .dso-intro-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            text-align: left;
            box-shadow: var(--shadow-card);
            transition: transform 0.2s ease;
        }
        .dso-intro-card:hover {
            border-color: #172521;
        }

        .dso-intro-card-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: #fbe3d3;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
            font-size: 18px;
            margin-bottom: 10px;
        }

        .dso-intro-card-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--color-text);
        }

        .dso-intro-card-desc {
            font-size: 12.5px;
            color: var(--color-muted);
            line-height: 1.45;
        }

        /* ---------- STATS BANNER ---------- */

        .dso-stats-banner {
            max-width: 1040px;
            margin: 60px auto 0;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 32px 24px;
            box-shadow: var(--shadow-card);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            text-align: center;
        }

        .dso-stat-item {
            position: relative;
        }
        .dso-stat-item::after {
            content: '';
            position: absolute;
            right: 0;
            top: 18%;
            height: 60%;
            width: 1px;
            background: #e7eae2;
        }
        .dso-stat-item:last-child::after {
            display: none;
        }

        .dso-stat-number {
            font-family: var(--font-display);
            font-size: clamp(30px, 3.2vw, 40px);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #172521;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .dso-stat-label {
            font-size: 13px;
            color: var(--color-muted);
            font-weight: 500;
        }

        /* ---------- FEATURES ---------- */

        .dso-features {
            max-width: 1040px;
            margin: 72px auto 0;
            padding: 0 28px;
        }

        .dso-section-head {
            text-align: center;
            margin-bottom: 48px;
        }

        .dso-section-label {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #c3471b;
            background: #fbe3d3;
            border: 1px solid #f4c9af;
            border-radius: 999px;
            padding: 5px 14px;
            margin-bottom: 14px;
        }

        .dso-section-title {
            font-family: var(--font-display);
            font-size: clamp(28px, 3.4vw, 40px);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--color-text);
            margin-bottom: 8px;
        }

        .dso-section-sub {
            font-size: 16px;
            color: var(--color-muted);
            max-width: 520px;
            margin: 0 auto;
        }

        .dso-features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .dso-feature-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
        }
        .dso-feature-card:hover {
            border-color: #172521;
            box-shadow: 0 15px 50px rgba(23, 37, 33, 0.10);
        }

        .dso-feature-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            font-size: 22px;
        }
        .dso-feature-icon--red      { background: #fbe3d3; color: #c3471b; }
        .dso-feature-icon--blue     { background: #f4c9af; color: #172521; }
        .dso-feature-icon--yellow   { background: #faf0d7; color: #7a4800; }
        .dso-feature-icon--green    { background: #e4efe4; color: #145c38; }
        .dso-feature-icon--purple   { background: #e9ec9b; color: #172521; }
        .dso-feature-icon--cyan     { background: #e7eae2; color: #172521; }

        .dso-feature-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 6px;
            letter-spacing: -0.2px;
        }

        .dso-feature-desc {
            font-size: 13.5px;
            color: var(--color-muted);
            line-height: 1.55;
        }

        /* ---------- PRICING ---------- */

        .dso-pricing {
            max-width: 1040px;
            margin: 80px auto 0;
            padding: 0 28px;
        }

        .dso-pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }

        .dso-pricing-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 28px 24px;
            box-shadow: var(--shadow-card);
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
            position: relative;
        }
        .dso-pricing-card:hover {
            border-color: #172521;
        }

        .dso-pricing-card--featured {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 1px var(--color-primary), var(--shadow-card);
        }

        .dso-pricing-popular-badge {
            position: absolute;
            top: -12px;
            right: 18px;
            background: var(--color-primary);
            color: #fff;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 5px 12px;
            border-radius: 999px;
            text-transform: uppercase;
        }

        .dso-pricing-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 4px;
            letter-spacing: -0.2px;
        }

        .dso-pricing-desc {
            font-size: 13px;
            color: var(--color-muted);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .dso-pricing-price {
            font-family: var(--font-display);
            font-size: 36px;
            font-weight: 800;
            color: var(--color-text);
            letter-spacing: -0.02em;
            margin-bottom: 4px;
        }

        .dso-pricing-price-unit {
            font-size: 14px;
            color: var(--color-muted);
            font-weight: 500;
        }

        .dso-pricing-divider {
            height: 1px;
            background: #e7eae2;
            margin: 18px 0 16px;
        }

        .dso-pricing-features {
            list-style: none;
            margin: 0 0 22px;
            flex: 1;
        }

        .dso-pricing-features li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: var(--color-muted);
            padding: 8px 0;
            line-height: 1.45;
        }

        .dso-pricing-features li::before {
            content: '✓';
            color: var(--color-success);
            font-weight: 800;
            font-size: 13px;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .dso-pricing-cta {
            margin-top: auto;
        }

        /* ---------- FAQ ---------- */

        .dso-faq {
            max-width: 820px;
            margin: 80px auto 0;
            padding: 0 28px;
        }

        .dso-faq-list {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }

        .dso-faq-item {
            border-bottom: 1px solid #e7eae2;
            padding: 20px 24px;
        }
        .dso-faq-item:last-child {
            border-bottom: none;
        }

        .dso-faq-q {
            font-size: 15px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dso-faq-q-num {
            color: var(--color-primary);
            font-weight: 800;
            font-size: 13px;
        }

        .dso-faq-a {
            font-size: 13.5px;
            color: var(--color-muted);
            line-height: 1.55;
        }

        /* ---------- FINAL CTA BANNER ---------- */

        .dso-final-cta {
            max-width: 1040px;
            margin: 72px auto 0;
            padding: 0 28px;
        }

        .dso-final-cta-card {
            background: #172521;
            border: 1px solid #2c3831;
            border-radius: var(--radius-lg);
            padding: 48px 32px;
            text-align: center;
            box-shadow: 0 25px 90px rgba(23, 37, 33, 0.18);
            position: relative;
            overflow: hidden;
        }

        .dso-final-cta-card::before {
            content: '';
            position: absolute;
            top: -80px;
            left: 50%;
            transform: translateX(-50%);
            width: 500px;
            height: 300px;
            background: radial-gradient(ellipse at center, rgba(239, 99, 47, 0.13) 0%, rgba(239, 99, 47, 0) 70%);
            pointer-events: none;
        }

        .dso-final-cta-inner {
            position: relative;
            z-index: 1;
        }

        .dso-final-cta-title {
            font-family: var(--font-display);
            font-size: clamp(26px, 3.2vw, 36px);
            font-weight: 800;
            color: #faf8f3;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
        }

        .dso-final-cta-sub {
            font-size: 15px;
            color: #d2d8d1;
            margin-bottom: 26px;
        }

        .dso-final-cta-btns {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin-bottom: 16px;
        }

        .dso-final-trust {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 18px;
            font-size: 12.5px;
            color: #b9c0bb;
        }
        .dso-final-cta-card .dso-landing-btn--ghost {
            color: #faf8f3;
            border-color: rgba(250, 248, 243, 0.35);
        }
        .dso-final-cta-card .dso-landing-btn--ghost:hover {
            background: rgba(250, 248, 243, 0.10);
            border-color: #faf8f3;
        }

        .dso-final-trust-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dso-final-trust-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #e9ec9b;
        }

        /* ---------- FOOTER ---------- */

        .dso-landing-footer {
            max-width: 1040px;
            margin: 60px auto 0;
            padding: 32px 28px 50px;
            border-top: 1px solid var(--color-border);
        }

        .dso-footer-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .dso-footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dso-footer-brand-img {
            height: 26px;
            width: auto;
            filter: brightness(0);
        }

        .dso-footer-brand-text {
            font-weight: 800;
            font-size: 14px;
            color: var(--color-text);
            letter-spacing: -0.2px;
        }

        .dso-footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dso-footer-link {
            font-size: 13px;
            color: var(--color-muted);
            transition: color 0.15s;
        }
        .dso-footer-link:hover {
            color: var(--color-text);
        }

        .dso-footer-copy {
            font-size: 12px;
            color: var(--color-muted-2);
            text-align: center;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e7eae2;
        }

        /* ---------- RESPONSIVE ---------- */

        @media (max-width: 900px) {
            .dso-hero-intro-cards { grid-template-columns: 1fr; max-width: 400px; }
            .dso-stats-banner     { grid-template-columns: repeat(2, 1fr); }
            .dso-stat-item::after { display: none; }
            .dso-features-grid    { grid-template-columns: 1fr; }
            .dso-pricing-grid     { grid-template-columns: 1fr; }
            .dso-landing-topbar-actions { display: none; }
        }

        @media (max-width: 520px) {
            .dso-hero-ctas { flex-direction: column; align-items: stretch; }
            .dso-hero-ctas .dso-landing-btn { width: 100%; }
            .dso-hero-intro-cards { grid-template-columns: 1fr; }
            .dso-stats-banner { grid-template-columns: 1fr 1fr; padding: 24px 16px; }
            .dso-final-cta-card { padding: 32px 20px; }
            .dso-footer-inner { flex-direction: column; align-items: center; text-align: center; }
            .dso-footer-links { justify-content: center; }
        }

        /* ---------- Scroll reveal helper (lightweight) ---------- */

        .dso-reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }
        .dso-reveal--visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            html { scroll-behavior: auto; }
            .dso-hero-badge-dot { animation: none; }
            .dso-reveal { opacity: 1; transform: none; transition: none; }
        }
    </style>
</head>
<body>

<!-- ===================== TOP BAR ===================== -->
<header class="dso-landing-topbar">
    <div class="dso-landing-topbar-inner">
        <a href="<?php echo esc_url($site_url); ?>" class="dso-landing-brand" aria-label="DEJOIY Seller Hub Home">
            <img src="<?php echo esc_url($site_url); ?>/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png"
                 alt="DEJOIY" class="dso-landing-brand-img" />
            <span>
                DEJOIY
                <span class="dso-landing-brand-badge">Seller Hub</span>
            </span>
        </a>

        <div class="dso-landing-topbar-actions">
            <a href="<?php echo esc_url($signup_url); ?>" class="dso-landing-btn dso-landing-btn--ghost dso-landing-btn--small">
                Create Account
            </a>
            <span class="dso-landing-topbar-divider" aria-hidden="true"></span>
            <a href="<?php echo esc_url($signin_url); ?>" class="dso-landing-btn dso-landing-btn--primary dso-landing-btn--small">
                <?php echo esc_html($cta_signin); ?>
            </a>
        </div>
    </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="dso-landing-page">
    <div class="dso-hero">
        <div class="dso-hero-glow" aria-hidden="true"></div>
        <div class="dso-hero-glow-bottom" aria-hidden="true"></div>

        <div class="dso-hero-inner">
            <div class="dso-hero-badge">
                <span class="dso-hero-badge-dot" aria-hidden="true"></span>
                India's fastest-growing seller platform
            </div>

            <h1 class="dso-hero-title">
                <?php echo esc_html($hero_title); ?>
            </h1>
            <p class="dso-hero-subtitle">
                <?php echo esc_html($hero_subtitle); ?>
            </p>

            <div class="dso-hero-ctas">
                <a href="<?php echo esc_url($signin_url); ?>" class="dso-landing-btn dso-landing-btn--primary dso-landing-btn--large">
                    <?php echo esc_html($cta_signin); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                </a>
                <a href="<?php echo esc_url($signup_url); ?>" class="dso-landing-btn dso-landing-btn--ghost dso-landing-btn--large">
                    <?php echo esc_html($cta_getstarted); ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                </a>
            </div>

            <div class="dso-hero-price-tag">
                <span class="dso-hero-price-tag-label"><?php echo esc_html($hero_price); ?></span>
                <span style="opacity:0.6;">·</span>
                <span>Setup in 5 minutes</span>
            </div>
        </div>

        <!-- Intro meta cards -->
        <div class="dso-hero-intro-cards">
            <div class="dso-intro-card dso-reveal">
                <div class="dso-intro-card-icon"><?php echo dejoiy_seller_icon('package'); ?></div>
                <div class="dso-intro-card-title">DPIN Catalog</div>
                <div class="dso-intro-card-desc">Protect & publish listings with one click</div>
            </div>
            <div class="dso-intro-card dso-reveal">
                <div class="dso-intro-card-icon"><?php echo dejoiy_seller_icon('cart-ship'); ?></div>
                <div class="dso-intro-card-title">Order Management</div>
                <div class="dso-intro-card-desc">Track, print labels & dispatch fast</div>
            </div>
            <div class="dso-intro-card dso-reveal">
                <div class="dso-intro-card-icon"><?php echo dejoiy_seller_icon('rupee'); ?></div>
                <div class="dso-intro-card-title">Payouts</div>
                <div class="dso-intro-card-desc">RBI-compliant, transparent ledger</div>
            </div>
        </div>
    </div>

    <!-- Stats banner -->
    <div class="dso-stats-banner dso-reveal">
        <div class="dso-stat-item">
            <div class="dso-stat-number"><?php echo esc_html($stats['vendor_count']); ?>+</div>
            <div class="dso-stat-label">Active Sellers</div>
        </div>
        <div class="dso-stat-item">
            <div class="dso-stat-number"><?php echo esc_html($stats['product_count']); ?>+</div>
            <div class="dso-stat-label">Products Listed</div>
        </div>
        <div class="dso-stat-item">
            <div class="dso-stat-number"><?php echo esc_html($stats['processed_qty']); ?>+</div>
            <div class="dso-stat-label">Orders Fulfilled</div>
        </div>
        <div class="dso-stat-item">
            <div class="dso-stat-number">99.9%</div>
            <div class="dso-stat-label">Uptime SLA</div>
        </div>
    </div>

    <!-- ===================== FEATURES ===================== -->
    <section class="dso-features">
        <div class="dso-section-head dso-reveal">
            <span class="dso-section-label">Everything you need</span>
            <h2 class="dso-section-title">Built for sellers who scale</h2>
            <p class="dso-section-sub">One dashboard to run your entire Dejoiy business. No juggling tabs.</p>
        </div>

        <div class="dso-features-grid">
            <?php foreach ($hero_features as $idx => $feat): ?>
                <?php
                    $icon_color = match($feat['icon']) {
                        'package', 'cart-ship' => 'dso-feature-icon--red',
                        'rupee', 'tag'         => 'dso-feature-icon--yellow',
                        'truck'               => 'dso-feature-icon--blue',
                        'megaphone'           => 'dso-feature-icon--green',
                        default               => 'dso-feature-icon--purple',
                    };
                ?>
                <div class="dso-feature-card dso-reveal" style="transition-delay: <?php echo $idx * 80; ?>ms">
                    <div class="dso-feature-icon <?php echo esc_attr($icon_color); ?>">
                        <?php echo dejoiy_seller_icon($feat['icon']); ?>
                    </div>
                    <h3 class="dso-feature-title"><?php echo esc_html($feat['title']); ?></h3>
                    <p class="dso-feature-desc"><?php echo esc_html($feat['desc']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ===================== PRICING ===================== -->
    <section class="dso-pricing">
        <div class="dso-section-head dso-reveal">
            <span class="dso-section-label">Pricing</span>
            <h2 class="dso-section-title"><?php echo esc_html($pricing_heading); ?></h2>
            <p class="dso-section-sub"><?php echo esc_html($pricing_desc); ?></p>
        </div>

        <div class="dso-pricing-grid">
            <!-- Free -->
            <div class="dso-pricing-card dso-reveal">
                <div class="dso-pricing-name"><?php echo esc_html($pricing_free_label); ?></div>
                <div class="dso-pricing-desc"><?php echo esc_html($pricing_free_desc); ?></div>
                <div class="dso-pricing-price">₹0<span class="dso-pricing-price-unit"> /month</span></div>
                <div class="dso-pricing-divider"></div>
                <ul class="dso-pricing-features">
                    <li>Up to 50 products (DPINs)</li>
                    <li>Manual stock & price updates</li>
                    <li>Basic order dashboard</li>
                    <li>Standard support</li>
                </ul>
                <div class="dso-pricing-cta">
                    <a href="<?php echo esc_url($signup_url); ?>" class="dso-landing-btn dso-landing-btn--ghost dso-landing-btn--large">
                        Start Free
                    </a>
                </div>
            </div>

            <!-- Growth (featured) -->
            <div class="dso-pricing-card dso-pricing-card--featured dso-reveal" style="transition-delay:120ms">
                <div class="dso-pricing-popular-badge">Most Popular</div>
                <div class="dso-pricing-name"><?php echo esc_html($pricing_pro_label); ?></div>
                <div class="dso-pricing-desc"><?php echo esc_html($pricing_pro_desc); ?></div>
                <div class="dso-pricing-price">₹1,499<span class="dso-pricing-price-unit"> /month</span></div>
                <div class="dso-pricing-divider"></div>
                <ul class="dso-pricing-features">
                    <li>Unlimited products</li>
                    <li>Smart repricer (Buy Box)</li>
                    <li>Bulk CSV upload & edits</li>
                    <li>Sponsored ads manager</li>
                    <li>Priority support</li>
                </ul>
                <div class="dso-pricing-cta">
                    <a href="<?php echo esc_url($signup_url); ?>" class="dso-landing-btn dso-landing-btn--primary dso-landing-btn--large">
                        Try Growth Free
                    </a>
                </div>
            </div>

            <!-- Enterprise -->
            <div class="dso-pricing-card dso-reveal" style="transition-delay:240ms">
                <div class="dso-pricing-name"><?php echo esc_html($pricing_enterprise_label); ?></div>
                <div class="dso-pricing-desc"><?php echo esc_html($pricing_enterprise_desc); ?></div>
                <div class="dso-pricing-price">Custom</div>
                <div class="dso-pricing-divider"></div>
                <ul class="dso-pricing-features">
                    <li>Dedicated account manager</li>
                    <li>Multi-brand marketplace</li>
                    <li>API & custom integrations</li>
                    <li>On-premise / white-label option</li>
                </ul>
                <div class="dso-pricing-cta">
                    <a href="<?php echo esc_url($signin_url); ?>" class="dso-landing-btn dso-landing-btn--ghost dso-landing-btn--large">
                        Talk to Sales
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== FAQ ===================== -->
    <section class="dso-faq">
        <div class="dso-section-head dso-reveal">
            <span class="dso-section-label">FAQ</span>
            <h2 class="dso-section-title"><?php echo esc_html($faq_heading); ?></h2>
        </div>

        <div class="dso-faq-list">
            <div class="dso-faq-item dso-reveal">
                <div class="dso-faq-q"><span class="dso-faq-q-num">01</span><?php echo esc_html($faq_1_q); ?></div>
                <p class="dso-faq-a"><?php echo esc_html($faq_1_a); ?></p>
            </div>
            <div class="dso-faq-item dso-reveal">
                <div class="dso-faq-q"><span class="dso-faq-q-num">02</span><?php echo esc_html($faq_2_q); ?></div>
                <p class="dso-faq-a"><?php echo esc_html($faq_2_a); ?></p>
            </div>
            <div class="dso-faq-item dso-reveal">
                <div class="dso-faq-q"><span class="dso-faq-q-num">03</span><?php echo esc_html($faq_3_q); ?></div>
                <p class="dso-faq-a"><?php echo esc_html($faq_3_a); ?></p>
            </div>
            <div class="dso-faq-item dso-reveal">
                <div class="dso-faq-q"><span class="dso-faq-q-num">04</span><?php echo esc_html($faq_4_q); ?></div>
                <p class="dso-faq-a"><?php echo esc_html($faq_4_a); ?></p>
            </div>
        </div>
    </section>

    <!-- ===================== FINAL CTA ===================== -->
    <section class="dso-final-cta">
        <div class="dso-final-cta-card dso-reveal">
            <div class="dso-final-cta-inner">
                <h2 class="dso-final-cta-title">Ready to grow your store?</h2>
                <p class="dso-final-cta-sub">Join thousands of sellers already running on DEJOIY Seller Hub.</p>
                <div class="dso-final-cta-btns">
                    <a href="<?php echo esc_url($signup_url); ?>" class="dso-landing-btn dso-landing-btn--primary dso-landing-btn--large">
                        Get Started Free
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>
                    </a>
                    <a href="<?php echo esc_url($signin_url); ?>" class="dso-landing-btn dso-landing-btn--ghost dso-landing-btn--large">
                        Sign In
                    </a>
                </div>
                <div class="dso-final-trust">
                    <span class="dso-final-trust-item"><span class="dso-final-trust-dot"></span> No credit card required</span>
                    <span class="dso-final-trust-item"><span class="dso-final-trust-dot"></span> Setup in 5 minutes</span>
                    <span class="dso-final-trust-item"><span class="dso-final-trust-dot"></span> Cancel anytime</span>
                </div>
            </div>
        </div>
    </section>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="dso-landing-footer">
    <div class="dso-footer-inner">
        <div class="dso-footer-brand">
            <img src="<?php echo esc_url($site_url); ?>/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png"
                 alt="DEJOIY" class="dso-footer-brand-img" />
            <span class="dso-footer-brand-text">DEJOIY Seller Hub</span>
        </div>
        <div class="dso-footer-links">
            <a href="<?php echo esc_url($signin_url); ?>" class="dso-footer-link">Sign In</a>
            <a href="<?php echo esc_url($signup_url); ?>" class="dso-footer-link">Get Started</a>
            <a href="<?php echo esc_url($site_url); ?>/seller-hub.php?section=support" class="dso-footer-link">Support</a>
            <a href="mailto:<?php echo esc_attr($footer_email); ?>" class="dso-footer-link">Contact</a>
        </div>
    </div>
    <div class="dso-footer-copy"><?php echo esc_html($footer_copy); ?></div>
</footer>

<!-- ===================== SCRIPTS ===================== -->
<script>
(function() {
    'use strict';

    // Lightweight scroll-reveal using IntersectionObserver
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('dso-reveal--visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.dso-reveal').forEach(function(el) {
            observer.observe(el);
        });
    } else {
        document.querySelectorAll('.dso-reveal').forEach(function(el) {
            el.classList.add('dso-reveal--visible');
        });
    }

    // Prefetch signup & signin pages on first idle (faster CTA click)
    if ('requestIdleCallback' in window) {
        requestIdleCallback(function() {
            var p1 = document.createElement('link');
            p1.rel = 'prefetch';
            p1.href = '<?php echo esc_url($signup_url); ?>';
            document.head.appendChild(p1);
            var p2 = document.createElement('link');
            p2.rel = 'prefetch';
            p2.href = '<?php echo esc_url($signin_url); ?>';
            document.head.appendChild(p2);
        });
    }
})();
</script>
</body>
</html>
