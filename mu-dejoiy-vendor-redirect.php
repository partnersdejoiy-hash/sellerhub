<?php
/**
 * DEJOIY vendor routing policy (mu-plugin).
 *
 * 1. Canonical seller registration stays at /vendor-register/ (WCFM form).
 * 2. Sellers go to the Seller App at /seller-app/ — WCFM frontend pages
 *    (store-manager, old seller-hub account endpoint) redirect there.
 * 3. My Account gets ONE CTA: "Open Seller App" in a NEW TAB. No WCFM
 *    dashboard link is shown in My Account.
 */
if (!defined('ABSPATH')) exit;

/** One-time rewrite flush flag on upgrade of this mu-plugin. */
add_action('init', function () {
    if (!get_option('dejoiy_vendor_routing_v2')) {
        flush_rewrite_rules(false);
        update_option('dejoiy_vendor_routing_v2', 1);
    }
    add_rewrite_rule('^seller-hub/?$', 'index.php?dsa_legacy_hub=1', 'top');
    add_rewrite_rule('^store-manager/?$', 'index.php?dsa_legacy_hub=1', 'top');
}, 20);

add_filter('query_vars', function ($vars) {
    $vars[] = 'dsa_legacy_hub';
    return $vars;
});

/** Redirect legacy WCFM vendor pages to the Seller App. */
add_action('template_redirect', function () {
    if (get_query_var('dsa_legacy_hub')) {
        wp_safe_redirect(home_url('/seller-app/'), 302);
        exit;
    }
    // Direct hits on the WCFM store-manager page or old seller-hub endpoint.
    if (is_page('store-manager')) {
        wp_safe_redirect(home_url('/seller-app/'), 302);
        exit;
    }
}, 1);

/**
 * My Account menu: remove WCFM/seller-hub entries, add ONE new-tab CTA.
 */
add_filter('woocommerce_account_menu_items', function ($items) {
    $cta = '<span id="dsa-open-seller-app" style="display:flex;align-items:center;gap:8px;color:#7c3aed;font-weight:600">'
        . '🚀 Open Seller App <span style="font-size:11px;opacity:.6">↗</span></span>';
    unset($items['seller-hub'], $items['wcfm'], $items['store-manager'], $items['store-manager-child']);
    $items['dsa-seller-app'] = $cta;
    return $items;
}, 100);

/** Register the CTA endpoint so WooCommerce renders it as a menu entry. */
add_action('init', function () {
    add_rewrite_endpoint('dsa-seller-app', EP_ROOT | EP_PAGES);
});

add_action('woocommerce_account_dsa-seller-app_endpoint', function () {
    // Clicking the menu item lands here; bounce to the app (menu link opens new tab via JS below).
    wp_safe_redirect(home_url('/seller-app/'), 302);
    exit;
});

/**
 * Make the CTA open in a NEW TAB and never render the WCFM dashboard inside
 * My Account content area (older WCFM versions inject it via template_include).
 */
add_action('wp_footer', function () {
    if (!is_account_page()) return;
    ?>
    <script>
    (function () {
        var link = document.querySelector('a[href*="dsa-seller-app"]');
        if (link) { link.setAttribute('target', '_blank'); link.setAttribute('rel', 'noopener'); }
        var el = document.getElementById('dsa-open-seller-app');
        if (el && link) { el.addEventListener('click', function (e) { e.preventDefault(); window.open(link.href, '_blank', 'noopener'); }); }
    })();
    </script>
    <?php
});

/** Belt & suspenders: never let WCFM dashboard render inside My Account page. */
add_filter('template_include', function ($template) {
    if (is_account_page() && function_exists('wcfm_is_vendor') && wcfm_is_vendor()) {
        global $wp_query;
        $endpoint = isset($wp_query->query_vars['wcfm-dashboard']) ? $wp_query->query_vars['wcfm-dashboard'] : null;
        if (null !== $endpoint && false !== $endpoint) {
            wp_safe_redirect(home_url('/seller-app/'), 302);
            exit;
        }
    }
    return $template;
}, 99);
