<?php
/**
 * DEJOIY vendor registration policy.
 *
 * Canonical seller registration: the WCFM vendor registration page at
 * https://dejoiy.com/vendor-register/ (shortcode: [wcfm_vendor_registration]).
 *
 * The old redirect to sellerhub.dejoiy.com broke new-seller registration;
 * the page must render its own form. Logged-in vendors are sent straight to
 * the Seller App instead of seeing the form again.
 */
if (!defined('ABSPATH')) exit;

add_action('template_redirect', function () {
    if (is_page('vendor-register') || is_page('vendor-registration')) {
        if (is_user_logged_in() && (function_exists('wcfm_is_vendor') && wcfm_is_vendor())) {
            wp_safe_redirect(home_url('/seller-app/'));
            exit;
        }
        // Otherwise: render the canonical WCFM registration form. No redirect.
    }
}, 1);
