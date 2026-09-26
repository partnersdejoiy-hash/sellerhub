<?php
/**
 * Plugin Name: DEJOIY Seller OS
 * Description: World-class seller dashboard — Amazon-level experience powered by WCFM + WooCommerce
 * Version: 1.0.0
 * Author: DEJOIY
 * Text Domain: dejoiy-seller-os
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) exit;

define('DSO_VERSION', '20260925.1');
define('DSO_PATH', plugin_dir_path(__FILE__));
define('DSO_URL', plugin_dir_url(__FILE__));
define('DSO_BASE', 'seller-hub');

if (!function_exists('dj_icon')) {
/**
 * DEJOIY Seller Central — single coherent line-icon system (24px grid, stroke-based).
 * Replaces emoji glyphs across navigation, menus, dashboards and CTAs.
 */
function dj_icon($name, $size = 18) {
    $paths = [
        'chart'       => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'crown'       => '<path d="M3 8l4 4 5-6 5 6 4-4v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'package'     => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 2 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'plus-circle' => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
        'upload'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
        'cart'        => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'zap'         => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'tag'         => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'finance'     => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'rupee'       => '<path d="M6 3h12M6 8h12M6 13a6 6 0 0 0 12 0M6 13h6a6 6 0 0 0 6-5M9 21l6-8"/>',
        'store'       => '<path d="M3 9l1.5-5h15L21 9"/><path d="M4 9v11a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9"/><path d="M9 21v-6h6v6"/><path d="M3 9a3 3 0 0 0 6 0 3 3 0 0 0 6 0 3 3 0 0 0 6 0"/>',
        'palette'     => '<circle cx="12" cy="12" r="9"/><circle cx="8.5" cy="10.5" r="1.2"/><circle cx="15.5" cy="10.5" r="1.2"/><circle cx="12" cy="15.5" r="1.2"/>',
        'truck'       => '<rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'megaphone'   => '<path d="M3 11l18-7-4 16-6-4-3 4-1-6z"/><path d="M3 11v3a4 4 0 0 0 4 4h1"/>',
        'rocket'      => '<path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/>',
        'trending'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'bar-chart'   => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'settings'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'graduation'  => '<path d="M22 10L12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/><line x1="22" y1="10" x2="22" y2="16"/>',
        'headset'     => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
        'logout'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'globe'       => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
        'sparkle'     => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>',
        'bell'        => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'check-circle'=> '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'warning'     => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'ban'         => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'star'        => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building'    => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
        'sparkles'    => '<path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'chevron-down'=> '<polyline points="6 9 12 15 18 9"/>',
        'send'        => '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
        'plus'        => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    ];
    $p = isset($paths[$name]) ? $paths[$name] : $paths['chart'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="' . intval($size) . '" height="' . intval($size) . '" aria-hidden="true">' . $p . '</svg>';
}
}

// ─── Core bootstrap ───
require_once DSO_PATH . 'includes/class-dso-auth.php';
require_once DSO_PATH . 'includes/class-dso-router.php';
require_once DSO_PATH . 'includes/class-dso-dashboard.php';
require_once DSO_PATH . 'includes/class-dso-products.php';
require_once DSO_PATH . 'includes/class-dso-orders.php';
require_once DSO_PATH . 'includes/class-dso-customers.php';
require_once DSO_PATH . 'includes/class-dso-analytics.php';
require_once DSO_PATH . 'includes/class-dso-finance.php';
require_once DSO_PATH . 'includes/class-dso-reviews.php';
require_once DSO_PATH . 'includes/class-dso-store.php';
require_once DSO_PATH . 'includes/class-dso-notifications.php';
require_once DSO_PATH . 'includes/class-dso-support.php';
require_once DSO_PATH . 'includes/class-dso-disputes.php';
require_once DSO_PATH . 'includes/class-dso-settings.php';
require_once DSO_PATH . 'includes/class-dso-marketing.php';
require_once DSO_PATH . 'includes/class-dso-shipping.php';
require_once DSO_PATH . 'includes/class-dso-pricing.php';
require_once DSO_PATH . 'includes/class-dso-advertising.php';
require_once DSO_PATH . 'includes/class-dso-growth.php';
require_once DSO_PATH . 'includes/class-dso-reports.php';
require_once DSO_PATH . 'includes/class-dso-performance.php';
require_once DSO_PATH . 'includes/class-dso-login.php';
require_once DSO_PATH . 'includes/class-dso-apps.php';
require_once DSO_PATH . 'includes/class-dso-b2b.php';
require_once DSO_PATH . 'includes/class-dso-brands.php';
require_once DSO_PATH . 'includes/class-dso-learn.php';
require_once DSO_PATH . 'includes/class-dso-messenger.php';
require_once DSO_PATH . 'includes/class-dso-marketplace.php';
require_once DSO_PATH . 'includes/class-dso-registration.php';
require_once DSO_PATH . 'api/rest-api.php';

/**
 * Main Seller OS class
 */
class Dejoiy_Seller_OS {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Register hooks
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Register custom rewrite rules and query vars
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('init', [$this, 'register_roles']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('template_redirect', [$this, 'handle_template_redirect']);
        add_action('template_redirect', [$this, 'handle_legacy_store_manager_redirect']);
        add_filter('template_include', [$this, 'load_storefront_template'], 9999);
        add_filter('wcfmmp_store_template', [$this, 'load_storefront_template'], 9999);

        // Order processing hooks for native commission & notification tracking
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_order_processed'], 20, 1);
        add_action('woocommerce_new_order', [$this, 'handle_order_processed'], 20, 1);

        // Register activation/deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // WooCommerce endpoint integration
        add_filter('woocommerce_account_menu_items', [$this, 'add_seller_hub_menu_item']);
        add_action('woocommerce_account_seller-hub_endpoint', [$this, 'render_seller_hub']);

        add_filter('allowed_redirect_hosts', [$this, 'allow_sellerhub_redirect_host']);

        if (class_exists('DSO_Registration')) {
            DSO_Registration::init();
        }
    }

    public function allow_sellerhub_redirect_host($hosts) {
        $hosts[] = 'sellerhub.dejoiy.com';
        return array_values(array_unique(array_filter($hosts)));
    }

    public function init() {
        // Add endpoint
        add_rewrite_endpoint('seller-hub', EP_ROOT | EP_PAGES);

        // Initialize registration shortcodes & processor
        if (class_exists('DSO_Registration')) {
            DSO_Registration::init();
        }

        // Initialize automated notification event listeners
        if (class_exists('DSO_Notifications')) {
            DSO_Notifications::init();
        }
    }

    public function register_query_vars($vars) {
        $vars[] = 'store';
        $vars[] = 'seller-hub';
        return $vars;
    }

    public function register_rewrite_rules() {
        add_rewrite_rule('^' . DSO_BASE . '/(.+?)/?$', 'index.php?seller-hub=$matches[1]', 'top');
        add_rewrite_rule('^' . DSO_BASE . '/?$', 'index.php?seller-hub=dashboard', 'top');
        add_rewrite_rule('^store/([^/]+)/?$', 'index.php?store=$matches[1]', 'top');
        add_rewrite_rule('^store/([^/]+)/page/?([0-9]{1,})/?$', 'index.php?store=$matches[1]&paged=$matches[2]', 'top');
    }

    public function load_storefront_template($template) {
        $store = get_query_var('store');
        if (!empty($store)) {
            global $wp_query;
            $user = get_user_by('slug', $store);
            if (!$user) {
                $user = get_user_by('login', $store);
            }
            if ($user) {
                $wp_query->is_404 = false;
                status_header(200);
                $custom = DSO_PATH . 'templates/storefront.php';
                if (file_exists($custom)) {
                    return $custom;
                }
            }
        }
        return $template;
    }

    /**
     * Register vendor roles and capabilities so marketplace functions without WCFM
     */
    public function register_roles() {
        $seller_caps = [
            'read'                      => true,
            'edit_posts'                => true,
            'delete_posts'              => false,
            'upload_files'              => true,
            'publish_posts'             => true,
            'edit_products'             => true,
            'publish_products'          => true,
            'read_products'             => true,
            'edit_published_products'   => true,
            'assign_product_terms'      => true,
        ];

        if (!get_role('seller')) {
            add_role('seller', 'DEJOIY Seller', $seller_caps);
        }
        if (!get_role('wcfm_vendor')) {
            add_role('wcfm_vendor', 'DEJOIY Seller (Legacy)', $seller_caps);
        }
    }

    /**
     * Register DEJOIY Seller Central entry point in WordPress Admin
     */
    public function register_admin_menu() {
        add_menu_page(
            'DEJOIY Seller Hub',
            '🏪 Seller Central',
            'edit_products',
            'dejoiy-seller-hub',
            function() {
                echo '<script>window.location.href="https://sellerhub.dejoiy.com/";</script>';
                echo '<div class="wrap" style="padding:40px;text-align:center;font-family:sans-serif;">' .
                     '<h2>Redirecting to DEJOIY Seller Central...</h2>' .
                     '<p><a href="https://sellerhub.dejoiy.com/" class="button button-primary button-hero">Open Seller Hub Now →</a></p>' .
                     '</div>';
            },
            'dashicons-store',
            55
        );
    }

    /**
     * Gracefully route legacy /store-manager/ links to Seller Hub
     */
    public function handle_legacy_store_manager_redirect() {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/store-manager/?#', $uri)) {
            wp_redirect('https://sellerhub.dejoiy.com/', 301);
            exit;
        }
    }

    /**
     * Automatically track commissions, tag order items with vendor IDs, and notify sellers on order creation
     */
    public function handle_order_processed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $settings = class_exists('DSO_Marketplace') ? DSO_Marketplace::get_marketplace_settings() : [];
        $default_comm = floatval($settings['default_commission_percent'] ?? 10.0);
        $vendor_orders = [];

        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) continue;

            $vendor_id = intval(get_post_meta($product_id, '_vendor_id', true));
            if (!$vendor_id) {
                $vendor_id = intval(get_post_field('post_author', $product_id));
            }
            if (!$vendor_id) $vendor_id = 2; // Default store

            // Tag item with vendor ID
            wc_update_order_item_meta($item_id, '_vendor_id', $vendor_id);
            wc_update_order_item_meta($item_id, '_wcfm_vendor', $vendor_id);

            // Calculate commission
            $custom_comm = get_user_meta($vendor_id, 'dso_custom_commission', true);
            $comm_rate = ($custom_comm !== '' && is_numeric($custom_comm)) ? floatval($custom_comm) : $default_comm;

            $line_total = floatval($item->get_total());
            $platform_fee = ($line_total * $comm_rate) / 100.0;
            $vendor_earning = max(0, $line_total - $platform_fee);

            wc_update_order_item_meta($item_id, '_dso_commission_rate', $comm_rate);
            wc_update_order_item_meta($item_id, '_dso_platform_fee', $platform_fee);
            wc_update_order_item_meta($item_id, '_dso_vendor_earning', $vendor_earning);

            $vendor_orders[$vendor_id] = true;
        }

        // Tag order postmeta
        if (!empty($vendor_orders)) {
            $vids = array_keys($vendor_orders);
            update_post_meta($order_id, '_vendor_ids', $vids);
            if (count($vids) === 1) {
                update_post_meta($order_id, '_vendor_id', $vids[0]);
                update_post_meta($order_id, '_wcfm_vendor', $vids[0]);
            }

            // Create in-app notification for each vendor
            try {
                if (class_exists('DSO_Notifications')) {
                    $notif = new DSO_Notifications();
                    if (method_exists($notif, 'create')) {
                        foreach ($vids as $vid) {
                            $notif->create(
                                $vid,
                                'order',
                                'New Order #' . $order->get_order_number(),
                                'You have received an order for ₹' . number_format($order->get_total(), 2),
                                'https://sellerhub.dejoiy.com/?section=orders'
                            );
                        }
                    }
                }
            } catch (\Throwable $notif_err) {
                error_log('DSO notification error: ' . $notif_err->getMessage());
            }
        }
    }

    /**
     * Add seller-hub to WooCommerce My Account menu
     */
    public function add_seller_hub_menu_item($items) {
        // Check if user is a vendor
        if ($this->is_vendor()) {
            $new_items = [];
            foreach ($items as $key => $value) {
                $new_items[$key] = $value;
                if ($key === 'dashboard') {
                    $new_items['seller-hub'] = '🏪 Seller Hub';
                }
            }
            return $new_items;
        }
        return $items;
    }

    /**
     * Render the seller hub inside WooCommerce My Account
     */
    public function render_seller_hub() {
        $section = isset($_GET['seller-hub']) ? sanitize_text_field($_GET['seller-hub']) : 'dashboard';
        DSO_Router::render($section);
    }

    /**
     * Check if current user is a vendor or store administrator
     */
    public function is_vendor($user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return false;

        // Administrator and Shop Manager have full access
        if (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce')) {
            return true;
        }

        // Check WCFM vendor capability
        if (function_exists('wcfm_is_vendor') && wcfm_is_vendor($user_id)) {
            return true;
        }

        // Fallback: check user role
        $user = get_userdata($user_id);
        if (!$user) return false;

        $vendor_roles = ['wcfm_vendor', 'vendor', 'seller', 'store_manager', 'administrator'];
        foreach ($vendor_roles as $role) {
            if (in_array($role, (array) $user->roles)) return true;
        }

        return false;
    }

    /**
     * Get vendor ID for current user (in WCFM Marketplace vendor ID is the user ID)
     */
    public function get_vendor_id($user_id = 0) {
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return 0;

        if ($this->is_vendor($user_id)) {
            return $user_id;
        }

        return 0;
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        DSO_REST_API::register_routes();
    }

    /**
     * Handle template redirect for direct URL access
     */
    public function handle_template_redirect() {
        // Check if accessing seller-hub directly
        if (isset($_GET['seller-hub']) || preg_match('#/seller-hub/#', $_SERVER['REQUEST_URI'] ?? '')) {
            if (!$this->is_vendor()) {
                wp_redirect(wp_login_url(home_url('/seller-hub/')));
                exit;
            }
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        global $post;

        // Only load on seller hub pages
        $is_seller_page = false;
        if (is_account_page() && isset($_GET['seller-hub'])) {
            $is_seller_page = true;
        }
        if ($post && has_shortcode($post->post_content, 'seller_hub')) {
            $is_seller_page = true;
        }

        if (!$is_seller_page) return;

        // Chart.js
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js', [], '4.4.0', true);

        // Seller OS CSS
        wp_enqueue_style('dso-main', DSO_URL . 'assets/css/seller-os-20260923.css', [], DSO_VERSION);

        // Impact theme overlay (additive reskin, no logic change)
        wp_enqueue_style('dso-impact', DSO_URL . 'assets/css/seller-os-impact.css', ['dso-main'], DSO_VERSION);

        // HubUI overlay: store-manager light/dark skin (additive, no logic change)
        wp_enqueue_style('dso-hubui', DSO_URL . 'assets/css/seller-os-hubui.css', ['dso-impact'], DSO_VERSION);

        // Seller OS JS
        wp_enqueue_script('dso-main', DSO_URL . 'assets/js/seller-os.js', ['jquery', 'chartjs'], DSO_VERSION, true);

        // Pass data to JS
        wp_localize_script('dso-main', 'dsoData', [
        'ajaxUrl' => '/wp-admin/admin-ajax.php',
        'restUrl' => '/wp-json/dejoiy-seller-os/v1/',
            'nonce' => wp_create_nonce('dso_nonce'),
            'vendorId' => $this->get_vendor_id(),
            'userId' => get_current_user_id(),
            'baseUrl' => home_url('/' . DSO_BASE . '/'),
            'currency' => get_woocommerce_currency_symbol(),
        ]);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        global $wpdb;

        // Create notifications table
        $charset_collate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix . 'dso_notifications';

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            type VARCHAR(50) NOT NULL DEFAULT 'info',
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            action_url VARCHAR(500) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY is_read (is_read)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Create support tickets table
        $table2 = $wpdb->prefix . 'dso_support_tickets';
        $sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            subject VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            message TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'open',
            priority VARCHAR(20) NOT NULL DEFAULT 'normal',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY status (status)
        ) $charset_collate;";

        dbDelta($sql2);
        // Create Joi (disputes) tables
        $table3 = $wpdb->prefix . 'dso_disputes';
        $sql3 = "CREATE TABLE IF NOT EXISTS $table3 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            vendor_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NULL,
            direction ENUM('filed_by_me','against_me') NOT NULL DEFAULT 'filed_by_me',
            claim_type VARCHAR(60) NOT NULL DEFAULT '',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            status ENUM('open','under_review','resolved','rejected','withdrawn') NOT NULL DEFAULT 'open',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY vendor_id (vendor_id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql3);
        $table4 = $wpdb->prefix . 'dso_dispute_messages';
        $sql4 = "CREATE TABLE IF NOT EXISTS $table4 (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            dispute_id BIGINT(20) UNSIGNED NOT NULL,
            author_type ENUM('seller','admin','system') NOT NULL DEFAULT 'seller',
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY dispute_id (dispute_id)
        ) $charset_collate;";
        dbDelta($sql4);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Initialize
Dejoiy_Seller_OS::instance();
