<?php
/**
 * DEJOIY Unified Authentication System
 * Enterprise-grade, progressive disclosure login experience shared across
 * DEJOIY.com (customer) and SellerHub.dejoiy.com (seller).
 *
 * @package Dejoiy_Seller_OS
 */

if (!defined('ABSPATH')) exit;

class DSO_Login {

    const OTP_EXPIRY_SECONDS = 300; // 5 minutes
    const OTP_COOLDOWN_SECONDS = 30; // 30 seconds before resend allowed
    const MAX_OTP_ATTEMPTS = 5;

    public static function init() {
        // Register shortcode
        add_shortcode('dejoiy_login', [__CLASS__, 'render_login_shortcode']);

        // Register AJAX handlers for non-logged-in users
        add_action('wp_ajax_nopriv_dso_auth_step1', [__CLASS__, 'handle_step1']);
        add_action('wp_ajax_nopriv_dso_auth_password', [__CLASS__, 'handle_password_login']);
        add_action('wp_ajax_nopriv_dso_auth_send_otp', [__CLASS__, 'handle_send_otp']);
        add_action('wp_ajax_nopriv_dso_auth_verify_otp', [__CLASS__, 'handle_verify_otp']);

        // Also allow logged-in users to hit handlers if testing
        add_action('wp_ajax_dso_auth_step1', [__CLASS__, 'handle_step1']);
        add_action('wp_ajax_dso_auth_password', [__CLASS__, 'handle_password_login']);
        add_action('wp_ajax_dso_auth_send_otp', [__CLASS__, 'handle_send_otp']);
        add_action('wp_ajax_dso_auth_verify_otp', [__CLASS__, 'handle_verify_otp']);

        // Two-Step Verification account settings hooks (WooCommerce & WordPress Profile)
        add_action('woocommerce_edit_account_form', [__CLASS__, 'render_wc_account_2fa_field']);
        add_action('woocommerce_save_account_details', [__CLASS__, 'save_wc_account_2fa_field']);
        add_action('show_user_profile', [__CLASS__, 'render_wp_profile_2fa_field']);
        add_action('edit_user_profile', [__CLASS__, 'render_wp_profile_2fa_field']);
        add_action('personal_options_update', [__CLASS__, 'save_wp_profile_2fa_field']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_wp_profile_2fa_field']);
    }

    /**
     * Check whether 2-step verification is active for a user.
     * Compulsory by default (true), unless user explicitly disabled it ('no', '0', 'disabled').
     */
    public static function is_2fa_enabled($user_id) {
        $meta = get_user_meta($user_id, 'dso_2fa_enabled', true);
        if ($meta === 'no' || $meta === '0' || $meta === 'disabled') {
            return false;
        }
        return true;
    }

    /**
     * Render 2-Step Verification field in WooCommerce Account Details (Customer Settings)
     */
    public static function render_wc_account_2fa_field() {
        $user_id = get_current_user_id();
        $is_enabled = self::is_2fa_enabled($user_id);
        ?>
        <fieldset class="dso-2fa-settings-card" style="margin: 28px 0; padding: 22px 24px; border: 1.5px solid #e2e8f0; border-radius: 14px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <legend style="font-weight: 700; font-size: 16px; color: #0f172a; padding: 0 10px;">Security & Two-Step Verification</legend>
            <div style="margin-top: 8px;">
                <label style="display: flex; align-items: flex-start; gap: 14px; cursor: pointer;">
                    <input type="checkbox" name="dso_2fa_enabled" value="yes" <?php checked($is_enabled, true); ?> style="margin-top: 3px; width: 18px; height: 18px; accent-color: #004bbf; cursor: pointer;" />
                    <span>
                        <strong style="display: block; font-size: 14.5px; color: #0f172a;">Enable Two-Step Verification (OTP)</strong>
                        <span style="display: block; font-size: 13.5px; color: #64748b; margin-top: 4px; line-height: 1.45;">
                            When enabled, DEJOIY will require a 6-digit one-time passcode sent to your registered email or phone whenever you sign in with your password.
                        </span>
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Save 2-Step Verification field in WooCommerce Account Details
     */
    public static function save_wc_account_2fa_field($user_id) {
        $enabled = isset($_POST['dso_2fa_enabled']) && $_POST['dso_2fa_enabled'] === 'yes' ? 'yes' : 'no';
        update_user_meta($user_id, 'dso_2fa_enabled', $enabled);
    }

    /**
     * Render 2-Step Verification in WordPress User Profile
     */
    public static function render_wp_profile_2fa_field($user) {
        $is_enabled = self::is_2fa_enabled($user->ID);
        ?>
        <h3>DEJOIY Account Security</h3>
        <table class="form-table">
            <tr>
                <th><label for="dso_2fa_enabled">Two-Step Verification</label></th>
                <td>
                    <label>
                        <input type="checkbox" name="dso_2fa_enabled" id="dso_2fa_enabled" value="yes" <?php checked($is_enabled, true); ?> />
                        Require 6-digit OTP verification code after password sign-in
                    </label>
                    <p class="description">Compulsory two-factor authentication for logging into DEJOIY and Seller Central.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save 2-Step Verification in WordPress User Profile
     */
    public static function save_wp_profile_2fa_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        $enabled = isset($_POST['dso_2fa_enabled']) && $_POST['dso_2fa_enabled'] === 'yes' ? 'yes' : 'no';
        update_user_meta($user_id, 'dso_2fa_enabled', $enabled);
    }

    /**
     * Determine if current request is seller-facing
     */
    public static function is_seller_context($override = null) {
        if ($override !== null) {
            return (bool) $override;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (strpos($host, 'sellerhub') !== false) {
            return true;
        }
        if (isset($_GET['context']) && $_GET['context'] === 'seller') {
            return true;
        }
        if (isset($_REQUEST['is_seller']) && $_REQUEST['is_seller']) {
            return true;
        }
        return false;
    }

    /**
     * Resolve destination redirect URL
     */
    public static function get_redirect_url($user, $is_seller = false, $custom_redirect = '') {
        if (!empty($custom_redirect)) {
            $validated = wp_validate_redirect($custom_redirect, '');
            if ($validated) return $validated;
        }

        if ($is_seller) {
            return 'https://sellerhub.dejoiy.com/seller-hub.php?section=dashboard';
        }

        // If user is a vendor logging in from main site, or customer
        if (function_exists('wcfm_is_vendor') && wcfm_is_vendor($user->ID)) {
            return home_url('/seller-app/');
        }

        if (function_exists('wc_get_page_permalink')) {
            return wc_get_page_permalink('myaccount');
        }

        return home_url('/my-account/');
    }

    /**
     * Resolve user from username, email, or phone number
     */
    public static function find_user_by_identifier($identifier) {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return null;
        }

        // 1. Direct login username match
        $user = get_user_by('login', $identifier);
        if ($user) {
            return $user;
        }

        // 2. Email match
        if (is_email($identifier)) {
            $user = get_user_by('email', $identifier);
            if ($user) {
                return $user;
            }
        }

        // 3. Normalized phone match
        $digits = preg_replace('/[^0-9]/', '', $identifier);
        if (strlen($digits) >= 10) {
            $last10 = substr($digits, -10);
            global $wpdb;
            $user_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->usermeta} 
                 WHERE meta_key IN ('billing_phone', 'phone', 'mobile') 
                   AND meta_value LIKE %s 
                 ORDER BY umeta_id DESC LIMIT 1",
                '%' . $wpdb->esc_like($last10) . '%'
            ));

            if (!empty($user_ids)) {
                $user = get_userdata(intval($user_ids[0]));
                if ($user) {
                    return $user;
                }
            }
        }

        // 4. Slug / nicename fallback
        $user = get_user_by('slug', sanitize_title($identifier));
        if ($user) {
            return $user;
        }

        return null;
    }

    /**
     * Generate masked display string for phone/email
     */
    public static function mask_recipient($identifier, $user = null) {
        $identifier = trim((string) $identifier);
        if (is_email($identifier) || ($user && is_email($user->user_email))) {
            $email = is_email($identifier) ? $identifier : $user->user_email;
            $parts = explode('@', $email);
            $name = $parts[0];
            $domain = $parts[1] ?? 'dejoiy.com';
            $masked_name = substr($name, 0, 1) . str_repeat('•', max(3, strlen($name) - 2)) . (strlen($name) > 1 ? substr($name, -1) : '');
            return $masked_name . '@' . $domain;
        }

        $digits = preg_replace('/[^0-9]/', '', $identifier);
        if (strlen($digits) >= 10) {
            return '••••••' . substr($digits, -4);
        }

        if ($user && !empty($user->user_email)) {
            return self::mask_recipient($user->user_email);
        }

        return '••••' . substr($identifier, -3);
    }

    /* ==========================================================================
       AJAX ENDPOINTS
       ========================================================================== */

    /**
     * AJAX Step 1: Identifier check
     */
    public static function handle_step1() {
        check_ajax_referer('dso_auth_nonce', 'security', false);

        $identifier = sanitize_text_field($_POST['identifier'] ?? '');
        if (empty($identifier)) {
            wp_send_json_error(['message' => 'Please enter your phone number or email.']);
        }

        $user = self::find_user_by_identifier($identifier);
        if (!$user) {
            wp_send_json_error([
                'message' => "We couldn't find a DEJOIY account matching that phone number or email."
            ]);
        }

        $is_seller = self::is_seller_context();
        if ($is_seller) {
            // Check seller status
            $plugin = class_exists('Dejoiy_Seller_OS') ? Dejoiy_Seller_OS::instance() : null;
            $is_vendor = $plugin ? $plugin->is_vendor($user->ID) : false;
            $is_admin = user_can($user, 'manage_options') || in_array('administrator', (array) $user->roles, true);

            if (!$is_vendor && !$is_admin) {
                wp_send_json_error([
                    'message' => 'This account is not registered as a seller on DEJOIY. Please register as a vendor or sign in to the customer store.',
                    'not_seller' => true
                ]);
            }
        }

        // Generate temporary auth challenge token
        $token = wp_generate_password(32, false);
        set_transient('dso_auth_flow_' . $token, [
            'user_id' => $user->ID,
            'identifier' => $identifier,
            'created' => time()
        ], 900); // 15 minutes

        $masked = self::mask_recipient($identifier, $user);

        wp_send_json_success([
            'token' => $token,
            'masked_recipient' => $masked,
            'methods' => ['password', 'otp'],
            'display_name' => $user->first_name ?: $user->display_name
        ]);
    }

    /**
     * AJAX Step 2: Password Login
     */
    public static function handle_password_login() {
        check_ajax_referer('dso_auth_nonce', 'security', false);

        $token = sanitize_text_field($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = !empty($_POST['remember']);
        $custom_redirect = esc_url_raw($_POST['redirect_to'] ?? '');
        $is_seller = self::is_seller_context();

        if (empty($password)) {
            wp_send_json_error(['message' => 'Please enter your password.']);
        }

        $flow = $token ? get_transient('dso_auth_flow_' . $token) : null;
        $user = null;

        if ($flow && !empty($flow['user_id'])) {
            $user = get_userdata(intval($flow['user_id']));
        } elseif (!empty($_POST['identifier'])) {
            $user = self::find_user_by_identifier($_POST['identifier']);
        }

        if (!$user) {
            wp_send_json_error(['message' => 'Session expired. Please enter your email or phone again.']);
        }

        // Authenticate with WordPress
        $authenticated = wp_authenticate($user->user_login, $password);

        if (is_wp_error($authenticated)) {
            wp_send_json_error(['message' => 'Incorrect password. Please try again or sign in with OTP.']);
        }

        // Seller authorization check
        if ($is_seller) {
            $plugin = class_exists('Dejoiy_Seller_OS') ? Dejoiy_Seller_OS::instance() : null;
            $is_vendor = $plugin ? $plugin->is_vendor($user->ID) : false;
            $is_admin = user_can($user, 'manage_options') || in_array('administrator', (array) $user->roles, true);

            if (!$is_vendor && !$is_admin) {
                wp_send_json_error([
                    'message' => 'Access Denied: You need a registered seller account to access DEJOIY Seller Central.',
                    'not_seller' => true
                ]);
            }
        }

        // Two-Step Verification Check:
        // Compulsory by default unless explicitly disabled in user account settings
        if (self::is_2fa_enabled($user->ID)) {
            $otp = sprintf("%06d", wp_rand(100000, 999999));

            set_transient('dso_otp_code_' . $user->ID, [
                'hash' => wp_hash_password($otp),
                'raw_dev' => (defined('WP_DEBUG') && WP_DEBUG) ? $otp : '',
                'expires' => time() + self::OTP_EXPIRY_SECONDS,
                'attempts' => 0
            ], self::OTP_EXPIRY_SECONDS);

            set_transient('dso_otp_rate_' . $user->ID, 1, self::OTP_COOLDOWN_SECONDS);

            if (!$token) {
                $token = wp_generate_password(32, false);
            }
            set_transient('dso_auth_flow_' . $token, [
                'user_id' => $user->ID,
                'identifier' => $flow['identifier'] ?? $user->user_email,
                'method' => 'password_2fa',
                'password_verified' => true,
                'remember' => $remember,
                'redirect_to' => $custom_redirect,
                'created' => time()
            ], 15 * MINUTE_IN_SECONDS);

            // Dispatch via email
            $site_name = get_bloginfo('name');
            $subject = "Your {$site_name} Two-Step Verification Code: {$otp}";
            $message = "Hello {$user->display_name},\n\n"
                     . "Your two-step verification code is: {$otp}\n\n"
                     . "This code is valid for 5 minutes. Never share this code with anyone.\n\n"
                     . "If you did not request this login, please secure your account immediately.\n\n"
                     . "Warm regards,\n"
                     . "DEJOIY Trust & Safety Team\n"
                     . "https://dejoiy.com\n";

            $headers = ['Content-Type: text/plain; charset=UTF-8'];
            wp_mail($user->user_email, $subject, $message, $headers);

            // Trigger SMS action hook if SMS gateway is integrated
            $user_phone = get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true);
            if ($user_phone) {
                do_action('dso_send_otp_sms', $user_phone, $otp, $user);
            }

            $masked = self::mask_recipient($flow['identifier'] ?? $user->user_email, $user);

            wp_send_json_success([
                'requires_2fa' => true,
                'message' => 'Please enter the 6-digit verification code sent to your registered contact.',
                'token' => $token,
                'masked_recipient' => $masked,
                'cooldown' => self::OTP_COOLDOWN_SECONDS
            ]);
        }

        // If 2FA is disabled, proceed directly with sign in
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        if ($token) {
            delete_transient('dso_auth_flow_' . $token);
        }

        $redirect_url = self::get_redirect_url($user, $is_seller, $custom_redirect);

        wp_send_json_success([
            'message' => "You're signed in",
            'redirect_url' => $redirect_url
        ]);
    }

    /**
     * AJAX Step 3a: Send OTP
     */
    public static function handle_send_otp() {
        check_ajax_referer('dso_auth_nonce', 'security', false);

        $token = sanitize_text_field($_POST['token'] ?? '');
        $flow = $token ? get_transient('dso_auth_flow_' . $token) : null;
        $user = null;

        if ($flow && !empty($flow['user_id'])) {
            $user = get_userdata(intval($flow['user_id']));
        } elseif (!empty($_POST['identifier'])) {
            $user = self::find_user_by_identifier($_POST['identifier']);
        }

        if (!$user) {
            wp_send_json_error(['message' => 'Session expired. Please re-enter your email or phone.']);
        }

        // Check rate limiting
        $rate_key = 'dso_otp_rate_' . $user->ID;
        if (get_transient($rate_key)) {
            wp_send_json_error(['message' => 'Please wait a few moments before requesting another code.']);
        }

        // Generate cryptographically secure 6-digit OTP
        $otp = sprintf("%06d", wp_rand(100000, 999999));

        // Save OTP transient
        set_transient('dso_otp_code_' . $user->ID, [
            'hash' => wp_hash_password($otp),
            'raw_dev' => (defined('WP_DEBUG') && WP_DEBUG) ? $otp : '', // available in debug mode
            'expires' => time() + self::OTP_EXPIRY_SECONDS,
            'attempts' => 0
        ], self::OTP_EXPIRY_SECONDS);

        // Set cooldown rate limit
        set_transient($rate_key, 1, self::OTP_COOLDOWN_SECONDS);

        // Dispatch via email
        $site_name = get_bloginfo('name');
        $subject = "Your {$site_name} Verification Code: {$otp}";
        $message = "Hello {$user->display_name},\n\n"
                 . "Your one-time login verification code is: {$otp}\n\n"
                 . "This code is valid for 5 minutes. Never share this code with anyone.\n\n"
                 . "If you did not request this login, please secure your account.\n\n"
                 . "Warm regards,\n"
                 . "DEJOIY Trust & Safety Team\n"
                 . "https://dejoiy.com\n";

        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($user->user_email, $subject, $message, $headers);

        // Trigger SMS action hook if SMS gateway is integrated
        $user_phone = get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, 'phone', true);
        if ($user_phone) {
            do_action('dso_send_otp_sms', $user_phone, $otp, $user);
        }

        $masked = self::mask_recipient($flow['identifier'] ?? $user->user_email, $user);

        wp_send_json_success([
            'message' => 'OTP dispatched successfully.',
            'masked_recipient' => $masked,
            'cooldown' => self::OTP_COOLDOWN_SECONDS
        ]);
    }

    /**
     * AJAX Step 3b: Verify OTP & Sign In
     */
    public static function handle_verify_otp() {
        check_ajax_referer('dso_auth_nonce', 'security', false);

        $token = sanitize_text_field($_POST['token'] ?? '');
        $otp_entered = sanitize_text_field($_POST['otp'] ?? '');
        $remember = !empty($_POST['remember']);
        $custom_redirect = esc_url_raw($_POST['redirect_to'] ?? '');
        $is_seller = self::is_seller_context();

        $flow = $token ? get_transient('dso_auth_flow_' . $token) : null;
        $user = null;

        if ($flow && !empty($flow['user_id'])) {
            $user = get_userdata(intval($flow['user_id']));
            if (!empty($flow['remember'])) {
                $remember = true;
            }
            if (empty($custom_redirect) && !empty($flow['redirect_to'])) {
                $custom_redirect = $flow['redirect_to'];
            }
        } elseif (!empty($_POST['identifier'])) {
            $user = self::find_user_by_identifier($_POST['identifier']);
        }

        if (!$user) {
            wp_send_json_error(['message' => 'Session expired. Please restart sign-in.']);
        }

        if (empty($otp_entered) || strlen($otp_entered) !== 6) {
            wp_send_json_error(['message' => 'Please enter a valid 6-digit code.']);
        }

        $otp_record = get_transient('dso_otp_code_' . $user->ID);
        if (!$otp_record || empty($otp_record['hash'])) {
            wp_send_json_error(['message' => 'Your OTP has expired. Request a new code.']);
        }

        if ($otp_record['attempts'] >= self::MAX_OTP_ATTEMPTS) {
            delete_transient('dso_otp_code_' . $user->ID);
            wp_send_json_error(['message' => 'Too many failed attempts. Please request a new code.']);
        }

        // Verify password hash of OTP
        $valid = wp_check_password($otp_entered, $otp_record['hash']);

        if (!$valid) {
            $otp_record['attempts']++;
            set_transient('dso_otp_code_' . $user->ID, $otp_record, max(1, $otp_record['expires'] - time()));
            wp_send_json_error(['message' => "That code doesn't look right. Please try again."]);
        }

        // OTP Verified successfully! Clear records
        delete_transient('dso_otp_code_' . $user->ID);
        if ($token) {
            delete_transient('dso_auth_flow_' . $token);
        }

        // Seller authorization check
        if ($is_seller) {
            $plugin = class_exists('Dejoiy_Seller_OS') ? Dejoiy_Seller_OS::instance() : null;
            $is_vendor = $plugin ? $plugin->is_vendor($user->ID) : false;
            $is_admin = user_can($user, 'manage_options') || in_array('administrator', (array) $user->roles, true);

            if (!$is_vendor && !$is_admin) {
                wp_send_json_error([
                    'message' => 'Access Denied: You need a registered seller account to access DEJOIY Seller Central.',
                    'not_seller' => true
                ]);
            }
        }

        // Log user in
        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
        do_action('wp_login', $user->user_login, $user);

        $redirect_url = self::get_redirect_url($user, $is_seller, $custom_redirect);

        wp_send_json_success([
            'message' => "You're signed in",
            'redirect_url' => $redirect_url
        ]);
    }

    /**
     * Render the shortcode [dejoiy_login]
     */
    public static function render_login_shortcode($atts = []) {
        $atts = shortcode_atts([
            'context' => '',
            'redirect' => ''
        ], $atts);

        $is_seller = ($atts['context'] === 'seller') || self::is_seller_context();
        return self::render_login_form([
            'is_seller' => $is_seller,
            'redirect_to' => $atts['redirect']
        ]);
    }

    /**
     * Render Standalone HTML Page for full-page auth (e.g. sellerhub.dejoiy.com root or /wp-login.php)
     */
    public static function render_standalone_page($args = []) {
        $is_seller = isset($args['is_seller']) ? (bool) $args['is_seller'] : self::is_seller_context();
        $title = $is_seller ? 'Sign In — DEJOIY Seller Central' : 'Sign In — DEJOIY';
        ?>
        <!DOCTYPE html>
        <html lang="en" dir="ltr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
            <title><?php echo esc_html($title); ?></title>
            <link rel="icon" type="image/png" href="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-FAVICON-100x100.png">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@600;700;800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
            <style>
                *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
                html, body {
                    min-height: 100%;
                    width: 100%;
                    background-color: #faf8f3;
                    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    color: #172521;
                    -webkit-font-smoothing: antialiased;
                }
                body {
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    align-items: center;
                    padding: 24px 16px;
                }
            </style>
        </head>
        <body>
            <?php echo self::render_login_form($args); ?>
        </body>
        </html>
        <?php
    }

    /**
     * Primary Render: Unified Login Form Component
     */
    public static function render_login_form($args = []) {
        $is_seller = isset($args['is_seller']) ? (bool) $args['is_seller'] : self::is_seller_context();
        $initial_error = $args['error'] ?? '';
        $redirect_to = $args['redirect_to'] ?? ($_GET['redirect_to'] ?? '');

        $title = $is_seller ? 'Welcome back, Seller' : 'Welcome back';
        $subtitle = $is_seller ? 'Sign in to your DEJOIY Seller account' : 'Sign in to your DEJOIY account';
        $nonce = wp_create_nonce('dso_auth_nonce');
        $ajax_url = '/wp-admin/admin-ajax.php';
        $logo_url = 'https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png';

        $g_settings = get_option('googlesitekit_sign-in-with-google_settings');
        $google_client_id = (is_array($g_settings) && !empty($g_settings['clientID'])) ? $g_settings['clientID'] : '382069039377-04dbrl71c6nvmcrd0pq38msjuhl499th.apps.googleusercontent.com';

        ob_start();
        ?>
        <div class="dso-auth-wrapper" id="dso-auth-app" data-is-seller="<?php echo $is_seller ? '1' : '0'; ?>" data-ajax="<?php echo esc_url($ajax_url); ?>" data-nonce="<?php echo esc_attr($nonce); ?>" data-redirect="<?php echo esc_attr($redirect_to); ?>" data-google-client-id="<?php echo esc_attr($google_client_id); ?>">
            <div class="dso-auth-card">
                
                <!-- Logo & Brand Header -->
                <div class="dso-auth-brand">
                    <a href="<?php echo $is_seller ? 'https://sellerhub.dejoiy.com' : 'https://dejoiy.com'; ?>" class="dso-auth-logo-link" aria-label="DEJOIY Home">
                        <img src="<?php echo esc_url($logo_url); ?>" alt="DEJOIY" class="dso-auth-logo" width="168" height="42" />
                    </a>
                    <?php if ($is_seller): ?>
                        <div class="dso-auth-context-pill">
                            <span class="dso-pill-dot"></span>
                            <span>Seller Central</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dso-auth-heading-group">
                    <h1 class="dso-auth-title" id="dso-auth-title"><?php echo esc_html($title); ?></h1>
                    <p class="dso-auth-subtitle" id="dso-auth-sub"><?php echo esc_html($subtitle); ?></p>
                </div>

                <!-- Global Alert Box -->
                <div class="dso-auth-alert" id="dso-global-alert" role="alert" aria-live="assertive" <?php echo empty($initial_error) ? 'style="display:none;"' : ''; ?>>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="dso-alert-icon"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span class="dso-alert-text"><?php echo esc_html($initial_error); ?></span>
                </div>

                <!-- Active Identifier Badge (Hidden on Step 1, shown on Step 2 & 3) -->
                <div class="dso-identifier-pill" id="dso-identifier-pill" style="display:none;">
                    <div class="dso-pill-left">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span class="dso-identifier-val" id="dso-pill-val"></span>
                    </div>
                    <button type="button" class="dso-link-btn dso-change-btn" id="dso-btn-change-user">Change</button>
                </div>

                <!-- STEP 1: Identifier Entry -->
                <div class="dso-auth-view active" id="view-step-1">
                    <form id="dso-form-step1" novalidate>
                        <div class="dso-input-group">
                            <label for="dso-identifier" class="dso-label">Phone number or email</label>
                            <input 
                                type="text" 
                                id="dso-identifier" 
                                name="identifier" 
                                class="dso-input" 
                                placeholder="Phone number or email" 
                                autocomplete="username email tel" 
                                required 
                                autofocus 
                            />
                            <div class="dso-error-msg" id="err-step1"></div>
                        </div>

                        <button type="submit" class="dso-btn dso-btn-primary" id="btn-submit-step1">
                            <span class="dso-btn-label">Continue</span>
                            <span class="dso-spinner" aria-hidden="true"></span>
                        </button>

                        <div class="dso-divider">
                            <span>OR</span>
                        </div>

                        <div class="dso-google-container" id="dso-google-box-step1">
                            <div class="googlesitekit-sign-in-with-google__frontend-output-button" data-shape="pill" data-theme="outline" data-text="continue_with" data-width="360" style="width: 100%; display: flex; justify-content: center;"></div>
                            <button type="button" class="dso-btn-google" id="btn-google-step1" aria-label="Continue with Google">
                                <svg class="dso-google-icon" width="18" height="18" viewBox="0 0 24 24">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Continue with Google</span>
                            </button>
                        </div>
                    </form>

                    <div class="dso-register-cue">
                        <?php if ($is_seller): ?>
                            <span>New to selling on DEJOIY?</span>
                            <a href="https://dejoiy.com/vendor-register/" target="_blank" class="dso-link">Register as a Seller →</a>
                        <?php else: ?>
                            <span>New to DEJOIY?</span>
                            <a href="<?php echo esc_url(wp_registration_url()); ?>" class="dso-link">Create your account →</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- STEP 2: Password Entry -->
                <div class="dso-auth-view" id="view-password" style="display:none;">
                    <form id="dso-form-password" novalidate>
                        <div class="dso-input-group">
                            <div class="dso-label-row">
                                <label for="dso-password" class="dso-label">Password</label>
                                <a href="<?php echo esc_url(wp_lostpassword_url()); ?>" target="_blank" class="dso-forgot-link">Forgot password?</a>
                            </div>
                            <div class="dso-password-container">
                                <input 
                                    type="password" 
                                    id="dso-password" 
                                    name="password" 
                                    class="dso-input dso-password-input" 
                                    placeholder="Password" 
                                    autocomplete="current-password" 
                                    required 
                                />
                                <button type="button" class="dso-pwd-toggle" id="dso-pwd-toggle" aria-label="Toggle password visibility" aria-pressed="false">
                                    <svg class="icon-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="display:none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <div class="dso-error-msg" id="err-password"></div>
                        </div>

                        <div class="dso-remember-row">
                            <label class="dso-checkbox-label">
                                <input type="checkbox" id="dso-remember" name="remember" value="1" checked />
                                <span>Keep me signed in</span>
                            </label>
                        </div>

                        <button type="submit" class="dso-btn dso-btn-primary" id="btn-submit-password">
                            <span class="dso-btn-label">Sign In</span>
                            <span class="dso-spinner" aria-hidden="true"></span>
                        </button>

                        <div class="dso-divider">
                            <span>OR</span>
                        </div>

                        <div class="dso-alt-actions">
                            <button type="button" class="dso-btn dso-btn-secondary" id="btn-trigger-otp">
                                <span>Sign in with OTP</span>
                            </button>

                            <div class="dso-google-container" id="dso-google-box-step2" style="margin-top: 10px;">
                                <div class="googlesitekit-sign-in-with-google__frontend-output-button" data-shape="pill" data-theme="outline" data-text="continue_with" data-width="360" style="width: 100%; display: flex; justify-content: center;"></div>
                                <button type="button" class="dso-btn-google" id="btn-google-step2" aria-label="Sign in with Google">
                                    <svg class="dso-google-icon" width="18" height="18" viewBox="0 0 24 24">
                                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                    </svg>
                                    <span>Continue with Google</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- STEP 3: OTP Verification -->
                <div class="dso-auth-view" id="view-otp" style="display:none;">
                    <div class="dso-otp-prompt">
                        <p class="dso-otp-prompt-text">
                            Enter the 6-digit code sent to <strong id="dso-otp-target"></strong>
                        </p>
                    </div>

                    <form id="dso-form-otp" novalidate>
                        <!-- Accessible 6-cell OTP input -->
                        <div class="dso-otp-cells" id="dso-otp-cells" role="group" aria-label="6-digit verification code">
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="0" autocomplete="one-time-code" required aria-label="Digit 1" />
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="1" required aria-label="Digit 2" />
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="2" required aria-label="Digit 3" />
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="3" required aria-label="Digit 4" />
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="4" required aria-label="Digit 5" />
                            <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="dso-otp-box" data-idx="5" required aria-label="Digit 6" />
                        </div>
                        <div class="dso-error-msg text-center" id="err-otp"></div>

                        <!-- OTP Resend & Timer -->
                        <div class="dso-otp-timer-wrap">
                            <div class="dso-otp-timer" id="dso-timer-msg">
                                Resend code in <strong id="dso-timer-countdown">00:30</strong>
                            </div>
                            <button type="button" class="dso-link-btn dso-resend-btn" id="dso-btn-resend" style="display:none;">
                                Resend OTP
                            </button>
                        </div>

                        <button type="submit" class="dso-btn dso-btn-primary" id="btn-submit-otp">
                            <span class="dso-btn-label">Verify & Continue</span>
                            <span class="dso-spinner" aria-hidden="true"></span>
                        </button>

                        <div class="dso-alt-switch">
                            <button type="button" class="dso-link-btn" id="dso-btn-switch-password">
                                ← Sign in with password instead
                            </button>
                        </div>
                    </form>
                </div>

                <!-- SUCCESS VIEW -->
                <div class="dso-auth-view" id="view-success" style="display:none;">
                    <div class="dso-success-box">
                        <div class="dso-success-badge">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                        <h2 class="dso-success-title">You're signed in</h2>
                        <p class="dso-success-text">Taking you to DEJOIY...</p>
                    </div>
                </div>

                <!-- Mandatory Legal Footer -->
                <div class="dso-auth-footer">
                    <p>
                        By logging in to DEJOIY, you agree to DEJOIY's 
                        <a href="https://dejoiy.com/terms-and-conditions/" target="_blank" rel="noopener noreferrer" class="dso-legal-link">Terms and Conditions</a> 
                        and 
                        <a href="https://dejoiy.com/privacy-policy/" target="_blank" rel="noopener noreferrer" class="dso-legal-link">Privacy Policy</a>.
                    </p>
                </div>

            </div>
        </div>

        <!-- STYLES -->
        <style>
            .dso-auth-wrapper {
                width: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 16px 8px;
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                color: #172521;
            }
            .dso-auth-card {
                width: 100%;
                max-width: 410px;
                background: #ffffff;
                border: 1px solid #d8dcd4;
                border-radius: 16px;
                padding: 36px 32px;
                box-shadow: 0 15px 50px rgba(23, 37, 33, 0.06);
                transition: box-shadow 0.2s ease;
            }
            .dso-auth-brand {
                display: flex;
                flex-direction: column;
                align-items: center;
                margin-bottom: 20px;
                text-align: center;
            }
            .dso-auth-logo-link {
                display: inline-block;
                text-decoration: none;
            }
            .dso-auth-logo {
                height: 40px;
                width: auto;
                max-width: 100%;
                object-fit: contain;
                display: block;
            }
            .dso-auth-context-pill {
                margin-top: 8px;
                display: inline-flex;
                align-items: center;
                gap: 6px;
                background: #f4c9af;
                color: #172521;
                border: 1px solid #f4c9af;
                padding: 3px 10px;
                border-radius: 20px;
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-transform: uppercase;
            }
            .dso-pill-dot {
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #c3471b;
            }
            .dso-auth-heading-group {
                text-align: center;
                margin-bottom: 24px;
            }
            .dso-auth-title {
                font-family: 'Barlow', 'Inter', sans-serif;
                font-size: 24px;
                font-weight: 800;
                color: #172521;
                margin: 0 0 6px;
                letter-spacing: -0.03em;
            }
            .dso-auth-subtitle {
                font-size: 13.5px;
                color: #59645f;
                margin: 0;
                line-height: 1.45;
            }
            .dso-auth-alert {
                background: #f9e4e1;
                border: 1px solid #e5b8b2;
                color: #8a241c;
                border-radius: 10px;
                padding: 10px 14px;
                margin-bottom: 18px;
                font-size: 13px;
                display: flex;
                align-items: flex-start;
                gap: 10px;
                line-height: 1.4;
            }
            .dso-alert-icon {
                flex-shrink: 0;
                margin-top: 1px;
                color: #ae3025;
            }
            .dso-identifier-pill {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: #faf8f3;
                border: 1px solid #d8dcd4;
                border-radius: 10px;
                padding: 9px 12px;
                margin-bottom: 20px;
                font-size: 13px;
            }
            .dso-pill-left {
                display: flex;
                align-items: center;
                gap: 8px;
                color: #3d4a43;
                font-weight: 600;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .dso-link-btn {
                background: none;
                border: none;
                color: #c3471b;
                font-weight: 600;
                font-size: 13px;
                cursor: pointer;
                padding: 2px 4px;
                border-radius: 4px;
                text-decoration: none;
                transition: color 0.15s ease;
            }
            .dso-link-btn:hover {
                color: #9a3a12;
                text-decoration: underline;
            }
            .dso-link-btn:focus-visible {
                outline: 2px solid #c3471b;
                outline-offset: 2px;
            }
            .dso-input-group {
                margin-bottom: 18px;
            }
            .dso-label {
                display: block;
                font-size: 13px;
                font-weight: 600;
                color: #3d4a43;
                margin-bottom: 6px;
            }
            .dso-label-row {
                display: flex;
                justify-content: space-between;
                align-items: baseline;
                margin-bottom: 6px;
            }
            .dso-forgot-link {
                font-size: 12px;
                color: #c3471b;
                font-weight: 600;
                text-decoration: none;
            }
            .dso-forgot-link:hover {
                text-decoration: underline;
            }
            .dso-input {
                width: 100%;
                height: 46px;
                padding: 0 14px;
                border: 1px solid #d8dcd4;
                border-radius: 8px;
                font-size: 16px;
                font-family: inherit;
                color: #172521;
                background: #ffffff;
                transition: border-color 0.15s ease, box-shadow 0.15s ease;
                outline: none;
            }
            .dso-input::placeholder {
                color: #8a938c;
            }
            .dso-input:focus {
                border-color: #c3471b;
                box-shadow: 0 0 0 3px rgba(195, 71, 27, 0.16);
            }
            .dso-input.has-error {
                border-color: #ae3025;
                background: #fdf6f4;
            }
            .dso-password-container {
                position: relative;
                width: 100%;
            }
            .dso-password-input {
                padding-right: 44px;
            }
            .dso-pwd-toggle {
                position: absolute;
                right: 6px;
                top: 50%;
                transform: translateY(-50%);
                background: transparent;
                border: none;
                color: #59645f;
                width: 34px;
                height: 34px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                border-radius: 6px;
                padding: 0;
            }
            .dso-pwd-toggle:hover {
                color: #172521;
            }
            .dso-pwd-toggle:focus-visible {
                outline: 2px solid #c3471b;
            }
            .dso-error-msg {
                font-size: 12px;
                color: #ae3025;
                margin-top: 5px;
                min-height: 16px;
                font-weight: 500;
            }
            .dso-error-msg.text-center {
                text-align: center;
            }
            .dso-remember-row {
                display: flex;
                align-items: center;
                margin-bottom: 18px;
            }
            .dso-checkbox-label {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: #59645f;
                cursor: pointer;
                user-select: none;
            }
            .dso-checkbox-label input[type="checkbox"] {
                width: 16px;
                height: 16px;
                border: 1.5px solid #d8dcd4;
                border-radius: 4px;
                cursor: pointer;
                accent-color: #c3471b;
            }
            .dso-btn {
                width: 100%;
                height: 46px;
                border-radius: 10px;
                font-size: 14.5px;
                font-weight: 600;
                letter-spacing: 0.01em;
                cursor: pointer;
                transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.1s ease, box-shadow 0.15s ease;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
                user-select: none;
                border: none;
            }
            .dso-btn-primary {
                background: #172521;
                color: #ffffff;
                box-shadow: 0 2px 6px rgba(23, 37, 33, 0.25);
            }
            .dso-btn-primary:hover:not(:disabled) {
                background: #0e1a16;
                box-shadow: 0 4px 12px rgba(23, 37, 33, 0.30);
            }
            .dso-btn-primary:active:not(:disabled) {
                transform: translateY(0);
            }
            .dso-btn-secondary {
                background: #ffffff;
                color: #172521;
                border: 1.5px solid #d8dcd4;
            }
            .dso-btn-secondary:hover:not(:disabled) {
                background: #faf8f3;
                border-color: #172521;
            }
            .dso-btn:disabled, .dso-btn.is-loading {
                opacity: 0.75;
                cursor: not-allowed;
                transform: none !important;
            }
            .dso-btn.is-loading .dso-btn-label {
                opacity: 0;
            }
            .dso-spinner {
                display: none;
                position: absolute;
                width: 18px;
                height: 18px;
                border: 2.2px solid rgba(255, 255, 255, 0.35);
                border-radius: 50%;
                border-top-color: #ffffff;
                animation: dsoSpin 0.7s linear infinite;
            }
            .dso-btn-secondary .dso-spinner {
                border-color: rgba(15, 23, 42, 0.2);
                border-top-color: #172521;
            }
            .dso-btn.is-loading .dso-spinner {
                display: block;
            }
            @keyframes dsoSpin {
                to { transform: rotate(360deg); }
            }
            .dso-divider {
                display: flex;
                align-items: center;
                margin: 20px 0;
                color: #8a938c;
                font-size: 11.5px;
                font-weight: 700;
                letter-spacing: 0.5px;
            }
            .dso-divider::before, .dso-divider::after {
                content: '';
                flex: 1;
                border-bottom: 1px solid #e7eae2;
            }
            .dso-divider span {
                padding: 0 12px;
            }
            .dso-register-cue {
                text-align: center;
                margin-top: 20px;
                padding-top: 16px;
                border-top: 1px solid #e7eae2;
                font-size: 13px;
                color: #59645f;
            }
            .dso-register-cue .dso-link {
                color: #c3471b;
                font-weight: 600;
                text-decoration: none;
                margin-left: 4px;
            }
            .dso-register-cue .dso-link:hover {
                text-decoration: underline;
            }
            /* OTP View Specifics */
            .dso-otp-prompt {
                text-align: center;
                margin-bottom: 20px;
            }
            .dso-otp-prompt-text {
                font-size: 13.5px;
                color: #3d4a43;
                line-height: 1.5;
            }
            .dso-otp-prompt-text strong {
                color: #172521;
            }
            .dso-otp-cells {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin-bottom: 8px;
            }
            .dso-otp-box {
                width: 44px;
                height: 52px;
                border: 1.5px solid #d8dcd4;
                border-radius: 10px;
                text-align: center;
                font-size: 22px;
                font-weight: 700;
                color: #172521;
                background: #ffffff;
                transition: border-color 0.15s, box-shadow 0.15s;
                outline: none;
                padding: 0;
            }
            .dso-otp-box:focus {
                border-color: #c3471b;
                box-shadow: 0 0 0 3px rgba(195, 71, 27, 0.16);
            }
            .dso-otp-box.has-error {
                border-color: #ae3025;
                background: #fdf6f4;
            }
            .dso-otp-timer-wrap {
                text-align: center;
                margin: 16px 0 20px;
                min-height: 22px;
            }
            .dso-otp-timer {
                font-size: 13px;
                color: #59645f;
            }
            .dso-otp-timer strong {
                color: #172521;
                font-family: monospace;
            }
            .dso-resend-btn {
                font-size: 13px;
                color: #c3471b;
                font-weight: 600;
            }
            .dso-alt-switch {
                text-align: center;
                margin-top: 16px;
            }
            /* Success State */
            .dso-success-box {
                text-align: center;
                padding: 24px 8px 12px;
            }
            .dso-success-badge {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: #e4efe4;
                border: 2px solid #b8c2af;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 16px;
                animation: dsoPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            }
            @keyframes dsoPop {
                0% { transform: scale(0.6); opacity: 0; }
                100% { transform: scale(1); opacity: 1; }
            }
            @media (prefers-reduced-motion: reduce) {
                .dso-success-badge { animation: none; }
                .dso-btn, .dso-input, .dso-otp-box { transition: none; }
            }
            .dso-success-title {
                font-family: 'Barlow', 'Inter', sans-serif;
                font-size: 20px;
                font-weight: 800;
                color: #172521;
                margin-bottom: 6px;
            }
            .dso-success-text {
                font-size: 13.5px;
                color: #59645f;
            }
            /* Footer */
            .dso-auth-footer {
                margin-top: 28px;
                padding-top: 18px;
                border-top: 1px solid #e7eae2;
                text-align: center;
            }
            .dso-auth-footer p {
                font-size: 12px;
                line-height: 1.6;
                color: #59645f;
                margin: 0;
            }
            .dso-legal-link {
                color: #59645f;
                text-decoration: underline;
                text-underline-offset: 2px;
                transition: color 0.15s ease;
            }
            .dso-legal-link:hover {
                color: #c3471b;
            }
            .dso-legal-link:focus-visible {
                outline: 2px solid #c3471b;
                outline-offset: 2px;
                border-radius: 2px;
            }

            /* Google Sign-In Container & Button */
            .dso-google-container {
                width: 100%;
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                position: relative;
            }
            .dso-google-container .googlesitekit-sign-in-with-google__frontend-output-button {
                width: 100% !important;
                max-width: 100% !important;
                display: flex !important;
                justify-content: center !important;
            }
            .dso-google-container .googlesitekit-sign-in-with-google__frontend-output-button iframe {
                margin: 0 auto !important;
            }
            .dso-google-container .googlesitekit-sign-in-with-google__frontend-output-button:not(:empty) + .dso-btn-google,
            .dso-google-container:has(iframe) .dso-btn-google {
                display: none !important;
            }
            .dso-btn-google {
                width: 100%;
                height: 46px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                background: #ffffff;
                border: 1.5px solid #d8dcd4;
                border-radius: 24px;
                color: #3d4a43;
                font-size: 14.5px;
                font-weight: 600;
                font-family: inherit;
                cursor: pointer;
                text-decoration: none;
                transition: background-color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
                box-sizing: border-box;
                padding: 0 16px;
            }
            .dso-btn-google:hover {
                background: #faf8f3;
                border-color: #172521;
                color: #172521;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            }
            .dso-btn-google:focus-visible {
                outline: 2px solid #c3471b;
                outline-offset: 2px;
            }
            .dso-btn-google:active {
                background: #f1eee8;
            }
            .dso-google-icon {
                flex-shrink: 0;
                width: 18px;
                height: 18px;
                display: block;
            }

            /* Responsive rules for ALL screen sizes */
            @media (min-width: 1025px) {
                .dso-auth-wrapper {
                    padding: 32px 16px;
                }
                .dso-auth-card {
                    max-width: 420px;
                }
            }
            @media (max-width: 1024px) {
                .dso-auth-wrapper {
                    padding: 24px 12px;
                }
                .dso-auth-card {
                    max-width: 420px;
                    padding: 32px 28px;
                }
            }
            @media (max-width: 480px) {
                .dso-auth-wrapper {
                    padding: 16px 8px;
                }
                .dso-auth-card {
                    padding: 26px 18px;
                    border-radius: 14px;
                    box-shadow: 0 8px 30px rgba(23, 37, 33, 0.06);
                    border: 1px solid #d8dcd4;
                    width: 100%;
                    max-width: 100%;
                }
                .dso-auth-title {
                    font-size: 20px;
                }
                .dso-auth-subtitle {
                    font-size: 13px;
                }
                .dso-otp-cells {
                    gap: 6px;
                }
                .dso-otp-box {
                    width: 40px;
                    height: 48px;
                    font-size: 19px;
                }
                .dso-btn, .dso-btn-google {
                    height: 44px;
                    font-size: 14px;
                }
            }
            @media (max-width: 360px) {
                .dso-auth-wrapper {
                    padding: 8px 4px;
                }
                .dso-auth-card {
                    padding: 20px 14px;
                    border-radius: 12px;
                }
                .dso-auth-title {
                    font-size: 18px;
                }
                .dso-otp-cells {
                    gap: 4px;
                }
                .dso-otp-box {
                    width: 35px;
                    height: 42px;
                    font-size: 17px;
                }
                .dso-btn, .dso-btn-google {
                    height: 42px;
                    font-size: 13.5px;
                }
            }
        </style>

        <!-- CLIENT SCRIPT -->
        <script>
        (function() {
            function initDSOAuth() {
                var app = document.getElementById('dso-auth-app');
                if (!app || app.dataset.initialized) return;
                app.dataset.initialized = 'true';

                var isSeller = app.getAttribute('data-is-seller') === '1';
                var ajaxUrl = app.getAttribute('data-ajax') || '/wp-admin/admin-ajax.php';
                var nonce = app.getAttribute('data-nonce') || '';
                var targetRedirect = app.getAttribute('data-redirect') || '';

                // Views
                var viewStep1 = document.getElementById('view-step-1');
                var viewPassword = document.getElementById('view-password');
                var viewOtp = document.getElementById('view-otp');
                var viewSuccess = document.getElementById('view-success');

                // Forms & Inputs
                var formStep1 = document.getElementById('dso-form-step1');
                var formPassword = document.getElementById('dso-form-password');
                var formOtp = document.getElementById('dso-form-otp');
                var inputIdentifier = document.getElementById('dso-identifier');
                var inputPassword = document.getElementById('dso-password');
                var inputRemember = document.getElementById('dso-remember');
                var otpCells = document.querySelectorAll('.dso-otp-box');

                // Alerts & Errors
                var globalAlert = document.getElementById('dso-global-alert');
                var errStep1 = document.getElementById('err-step1');
                var errPassword = document.getElementById('err-password');
                var errOtp = document.getElementById('err-otp');

                // Pills & Info
                var identifierPill = document.getElementById('dso-identifier-pill');
                var pillVal = document.getElementById('dso-pill-val');
                var otpTarget = document.getElementById('dso-otp-target');
                var timerCountdown = document.getElementById('dso-timer-countdown');
                var timerMsg = document.getElementById('dso-timer-msg');
                var btnResend = document.getElementById('dso-btn-resend');
                var btnSwitchPassword = document.getElementById('dso-btn-switch-password');

                // Header Titles
                var authTitle = document.getElementById('dso-auth-title');
                var authSub = document.getElementById('dso-auth-sub');
                var defaultTitle = authTitle ? authTitle.textContent : '';
                var defaultSub = authSub ? authSub.textContent : '';

                // State
                var currentToken = '';
                var currentIdentifier = '';
                var currentMasked = '';
                var isTwoStep = false;
                var countdownInterval = null;

                function setAlert(msg) {
                    if (!globalAlert) return;
                    if (msg) {
                        globalAlert.querySelector('.dso-alert-text').textContent = msg;
                        globalAlert.style.display = 'flex';
                    } else {
                        globalAlert.style.display = 'none';
                    }
                }

                function clearErrors() {
                    setAlert('');
                    if (errStep1) errStep1.textContent = '';
                    if (errPassword) errPassword.textContent = '';
                    if (errOtp) errOtp.textContent = '';
                    if (inputIdentifier) inputIdentifier.classList.remove('has-error');
                    if (inputPassword) inputPassword.classList.remove('has-error');
                    otpCells.forEach(function(c) { c.classList.remove('has-error'); });
                }

                function switchView(viewName) {
                    clearErrors();
                    [viewStep1, viewPassword, viewOtp, viewSuccess].forEach(function(v) {
                        if (v) {
                            v.style.display = 'none';
                            v.classList.remove('active');
                        }
                    });

                    if (viewName === 'step1') {
                        isTwoStep = false;
                        viewStep1.style.display = 'block';
                        viewStep1.classList.add('active');
                        identifierPill.style.display = 'none';
                        if (authTitle) authTitle.textContent = defaultTitle;
                        if (authSub) authSub.textContent = defaultSub;
                        setTimeout(function() { inputIdentifier.focus(); }, 50);
                    } else if (viewName === 'password') {
                        isTwoStep = false;
                        viewPassword.style.display = 'block';
                        viewPassword.classList.add('active');
                        identifierPill.style.display = 'flex';
                        pillVal.textContent = currentIdentifier;
                        if (authTitle) authTitle.textContent = defaultTitle;
                        if (authSub) authSub.textContent = defaultSub;
                        setTimeout(function() { inputPassword.focus(); }, 50);
                    } else if (viewName === 'otp') {
                        viewOtp.style.display = 'block';
                        viewOtp.classList.add('active');
                        identifierPill.style.display = 'flex';
                        pillVal.textContent = currentIdentifier;
                        otpTarget.textContent = currentMasked || currentIdentifier;
                        if (isTwoStep) {
                            if (authTitle) authTitle.textContent = 'Two-Step Verification';
                            if (authSub) authSub.textContent = 'Enter the 6-digit verification code sent to your registered contact.';
                            if (btnSwitchPassword) btnSwitchPassword.style.display = 'none';
                        } else {
                            if (authTitle) authTitle.textContent = defaultTitle;
                            if (authSub) authSub.textContent = defaultSub;
                            if (btnSwitchPassword) btnSwitchPassword.style.display = 'inline-block';
                        }
                        resetOtpInputs();
                        setTimeout(function() { otpCells[0].focus(); }, 50);
                    } else if (viewName === 'success') {
                        viewSuccess.style.display = 'block';
                        viewSuccess.classList.add('active');
                        identifierPill.style.display = 'none';
                    }
                }

                function setBtnLoading(btn, loading) {
                    if (!btn) return;
                    if (loading) {
                        btn.classList.add('is-loading');
                        btn.disabled = true;
                    } else {
                        btn.classList.remove('is-loading');
                        btn.disabled = false;
                    }
                }

                // Password toggle
                var toggleBtn = document.getElementById('dso-pwd-toggle');
                if (toggleBtn && inputPassword) {
                    toggleBtn.addEventListener('click', function() {
                        var isPass = inputPassword.getAttribute('type') === 'password';
                        inputPassword.setAttribute('type', isPass ? 'text' : 'password');
                        toggleBtn.setAttribute('aria-pressed', isPass ? 'true' : 'false');
                        toggleBtn.querySelector('.icon-eye').style.display = isPass ? 'none' : 'block';
                        toggleBtn.querySelector('.icon-eye-off').style.display = isPass ? 'block' : 'none';
                    });
                }

                // Change user button
                var btnChange = document.getElementById('dso-btn-change-user');
                if (btnChange) {
                    btnChange.addEventListener('click', function(e) {
                        e.preventDefault();
                        switchView('step1');
                    });
                }

                // STEP 1 Form Submission
                if (formStep1) {
                    formStep1.addEventListener('submit', function(e) {
                        e.preventDefault();
                        clearErrors();
                        var val = inputIdentifier.value.trim();
                        if (!val) {
                            errStep1.textContent = 'Please enter your phone number or email.';
                            inputIdentifier.classList.add('has-error');
                            inputIdentifier.focus();
                            return;
                        }

                        var btn = document.getElementById('btn-submit-step1');
                        setBtnLoading(btn, true);

                        var fd = new FormData();
                        fd.append('action', 'dso_auth_step1');
                        fd.append('security', nonce);
                        fd.append('identifier', val);
                        fd.append('is_seller', isSeller ? '1' : '0');

                        fetch(ajaxUrl, { method: 'POST', body: fd })
                            .then(function(res) { return res.json(); })
                            .then(function(resp) {
                                setBtnLoading(btn, false);
                                if (resp && resp.success && resp.data) {
                                    currentToken = resp.data.token;
                                    currentIdentifier = val;
                                    currentMasked = resp.data.masked_recipient || val;
                                    switchView('password');
                                } else {
                                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'User not found.';
                                    errStep1.textContent = msg;
                                    inputIdentifier.classList.add('has-error');
                                }
                            })
                            .catch(function() {
                                setBtnLoading(btn, false);
                                errStep1.textContent = 'Network error. Please check your connection and try again.';
                            });
                    });
                }

                // STEP 2: Password Form Submission
                if (formPassword) {
                    formPassword.addEventListener('submit', function(e) {
                        e.preventDefault();
                        clearErrors();
                        var pwd = inputPassword.value;
                        if (!pwd) {
                            errPassword.textContent = 'Please enter your password.';
                            inputPassword.classList.add('has-error');
                            inputPassword.focus();
                            return;
                        }

                        var btn = document.getElementById('btn-submit-password');
                        setBtnLoading(btn, true);

                        var fd = new FormData();
                        fd.append('action', 'dso_auth_password');
                        fd.append('security', nonce);
                        fd.append('token', currentToken);
                        fd.append('identifier', currentIdentifier);
                        fd.append('password', pwd);
                        fd.append('remember', inputRemember && inputRemember.checked ? '1' : '0');
                        fd.append('redirect_to', targetRedirect);
                        fd.append('is_seller', isSeller ? '1' : '0');

                        fetch(ajaxUrl, { method: 'POST', body: fd })
                            .then(function(res) { return res.json(); })
                            .then(function(resp) {
                                setBtnLoading(btn, false);
                                if (resp && resp.success && resp.data) {
                                    if (resp.data.requires_2fa) {
                                        isTwoStep = true;
                                        if (resp.data.token) currentToken = resp.data.token;
                                        if (resp.data.masked_recipient) currentMasked = resp.data.masked_recipient;
                                        switchView('otp');
                                        startCountdown(resp.data.cooldown || 30);
                                        return;
                                    }
                                    switchView('success');
                                    setTimeout(function() {
                                        window.location.href = resp.data.redirect_url;
                                    }, 800);
                                } else {
                                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Incorrect credentials.';
                                    errPassword.textContent = msg;
                                    inputPassword.classList.add('has-error');
                                }
                            })
                            .catch(function() {
                                setBtnLoading(btn, false);
                                errPassword.textContent = 'Connection error. Please try again.';
                            });
                    });
                }

                // Switch to OTP button
                var btnTriggerOtp = document.getElementById('btn-trigger-otp');
                if (btnTriggerOtp) {
                    btnTriggerOtp.addEventListener('click', function(e) {
                        e.preventDefault();
                        isTwoStep = false;
                        sendOtpRequest(btnTriggerOtp);
                    });
                }

                // Switch to Password from OTP view
                if (btnSwitchPassword) {
                    btnSwitchPassword.addEventListener('click', function(e) {
                        e.preventDefault();
                        switchView('password');
                    });
                }

                function startCountdown(durationSec) {
                    if (countdownInterval) clearInterval(countdownInterval);
                    var remaining = durationSec || 30;
                    if (btnResend) btnResend.style.display = 'none';
                    if (timerMsg) timerMsg.style.display = 'block';

                    function render() {
                        var sec = remaining < 10 ? '0' + remaining : remaining;
                        if (timerCountdown) timerCountdown.textContent = '00:' + sec;
                    }
                    render();

                    countdownInterval = setInterval(function() {
                        remaining--;
                        if (remaining <= 0) {
                            clearInterval(countdownInterval);
                            if (timerMsg) timerMsg.style.display = 'none';
                            if (btnResend) btnResend.style.display = 'inline-block';
                        } else {
                            render();
                        }
                    }, 1000);
                }

                function sendOtpRequest(triggerBtn) {
                    clearErrors();
                    var btn = triggerBtn || btnTriggerOtp;
                    setBtnLoading(btn, true);

                    var fd = new FormData();
                    fd.append('action', 'dso_auth_send_otp');
                    fd.append('security', nonce);
                    fd.append('token', currentToken);
                    fd.append('identifier', currentIdentifier);
                    fd.append('is_seller', isSeller ? '1' : '0');

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                        .then(function(res) { return res.json(); })
                        .then(function(resp) {
                            setBtnLoading(btn, false);
                            if (resp && resp.success) {
                                if (resp.data && resp.data.masked_recipient) {
                                    currentMasked = resp.data.masked_recipient;
                                }
                                switchView('otp');
                                startCountdown(resp.data && resp.data.cooldown ? resp.data.cooldown : 30);
                            } else {
                                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Unable to send OTP. Try again.';
                                setAlert(msg);
                            }
                        })
                        .catch(function() {
                            setBtnLoading(btn, false);
                            setAlert('Unable to send code. Please check connection.');
                        });
                }

                if (btnResend) {
                    btnResend.addEventListener('click', function(e) {
                        e.preventDefault();
                        sendOtpRequest(btnResend);
                    });
                }

                // OTP Cell Navigation & Input handling
                function resetOtpInputs() {
                    otpCells.forEach(function(cell) { cell.value = ''; });
                }

                function getOtpValue() {
                    var str = '';
                    otpCells.forEach(function(c) { str += c.value; });
                    return str.trim();
                }

                otpCells.forEach(function(cell, idx) {
                    cell.addEventListener('input', function(e) {
                        var val = e.target.value;
                        // Handle paste of whole code into any cell
                        if (val.length > 1) {
                            var clean = val.replace(/[^0-9]/g, '');
                            for (var i = 0; i < otpCells.length; i++) {
                                otpCells[i].value = clean[i] || '';
                            }
                            if (clean.length >= 6) {
                                otpCells[5].focus();
                                formOtp.dispatchEvent(new Event('submit'));
                            } else if (otpCells[clean.length]) {
                                otpCells[clean.length].focus();
                            }
                            return;
                        }

                        var single = val.replace(/[^0-9]/g, '');
                        cell.value = single;
                        if (single && idx < otpCells.length - 1) {
                            otpCells[idx + 1].focus();
                        }

                        if (getOtpValue().length === 6) {
                            formOtp.dispatchEvent(new Event('submit'));
                        }
                    });

                    cell.addEventListener('keydown', function(e) {
                        if (e.key === 'Backspace' && !cell.value && idx > 0) {
                            otpCells[idx - 1].focus();
                        }
                    });

                    cell.addEventListener('paste', function(e) {
                        e.preventDefault();
                        var pasted = (e.clipboardData || window.clipboardData).getData('text');
                        var clean = pasted.replace(/[^0-9]/g, '');
                        for (var i = 0; i < otpCells.length; i++) {
                            otpCells[i].value = clean[i] || '';
                        }
                        if (clean.length >= 6) {
                            otpCells[5].focus();
                            formOtp.dispatchEvent(new Event('submit'));
                        } else if (otpCells[clean.length]) {
                            otpCells[clean.length].focus();
                        }
                    });
                });

                // STEP 3: OTP Form Submit
                if (formOtp) {
                    formOtp.addEventListener('submit', function(e) {
                        e.preventDefault();
                        clearErrors();
                        var code = getOtpValue();
                        if (code.length !== 6) {
                            errOtp.textContent = 'Please enter all 6 digits.';
                            otpCells.forEach(function(c) { if (!c.value) c.classList.add('has-error'); });
                            return;
                        }

                        var btn = document.getElementById('btn-submit-otp');
                        setBtnLoading(btn, true);

                        var fd = new FormData();
                        fd.append('action', 'dso_auth_verify_otp');
                        fd.append('security', nonce);
                        fd.append('token', currentToken);
                        fd.append('identifier', currentIdentifier);
                        fd.append('otp', code);
                        fd.append('remember', inputRemember && inputRemember.checked ? '1' : '0');
                        fd.append('redirect_to', targetRedirect);
                        fd.append('is_seller', isSeller ? '1' : '0');

                        fetch(ajaxUrl, { method: 'POST', body: fd })
                            .then(function(res) { return res.json(); })
                            .then(function(resp) {
                                setBtnLoading(btn, false);
                                if (resp && resp.success && resp.data) {
                                    switchView('success');
                                    setTimeout(function() {
                                        window.location.href = resp.data.redirect_url;
                                    }, 800);
                                } else {
                                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Invalid code.';
                                    errOtp.textContent = msg;
                                    otpCells.forEach(function(c) { c.classList.add('has-error'); });
                                }
                            })
                            .catch(function() {
                                setBtnLoading(btn, false);
                                errOtp.textContent = 'Connection error. Please try again.';
                            });
                    });
                }

                // GOOGLE SIGN-IN INTEGRATION
                var googleClientId = app.getAttribute('data-google-client-id') || '382069039377-04dbrl71c6nvmcrd0pq38msjuhl499th.apps.googleusercontent.com';

                function renderGoogleButtons() {
                    if (!window.google || !window.google.accounts || !window.google.accounts.id) return;
                    try {
                        window.google.accounts.id.initialize({
                            client_id: googleClientId,
                            callback: handleGoogleAuthCallback,
                            context: 'signin'
                        });

                        var googleSlots = document.querySelectorAll('.googlesitekit-sign-in-with-google__frontend-output-button');
                        googleSlots.forEach(function(slot) {
                            if (slot && !slot.dataset.gsiRendered) {
                                slot.dataset.gsiRendered = 'true';
                                var parentW = slot.parentElement ? slot.parentElement.offsetWidth : 360;
                                var targetW = Math.max(220, Math.min(parentW || 360, 360));
                                window.google.accounts.id.renderButton(slot, {
                                    type: 'standard',
                                    shape: 'pill',
                                    theme: 'outline',
                                    text: 'continue_with',
                                    size: 'large',
                                    width: targetW,
                                    logo_alignment: 'left'
                                });
                                var fallback = slot.parentElement ? slot.parentElement.querySelector('.dso-btn-google') : null;
                                if (fallback) fallback.style.display = 'none';
                            }
                        });
                    } catch(e) {
                        console.warn('Google GSI init notice:', e);
                    }
                }

                function handleGoogleAuthCallback(response) {
                    if (!response || !response.credential) return;

                    switchView('success');
                    var sTitle = document.querySelector('.dso-success-title');
                    var sText = document.querySelector('.dso-success-text');
                    if (sTitle) sTitle.textContent = 'Connecting with Google...';
                    if (sText) sText.textContent = 'Authenticating your account...';

                    var fd = new URLSearchParams();
                    fd.append('credential', response.credential);
                    fd.append('select_by', response.select_by || 'btn');
                    fd.append('integration', 'woocommerce');

                    fetch('/wp-login.php?action=googlesitekit_auth', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: fd.toString()
                    })
                    .then(function(res) {
                        if (res.ok && res.redirected) {
                            window.location.assign(res.url);
                        } else {
                            window.location.href = targetRedirect || (isSeller ? 'https://sellerhub.dejoiy.com/seller-hub.php?section=dashboard' : '/my-account/');
                        }
                    })
                    .catch(function() {
                        window.location.href = targetRedirect || (isSeller ? 'https://sellerhub.dejoiy.com/seller-hub.php?section=dashboard' : '/my-account/');
                    });
                }

                function loadGoogleGsi(cb) {
                    if (document.getElementById('dso-google-gsi-js')) {
                        if (cb) cb();
                        return;
                    }
                    var script = document.createElement('script');
                    script.id = 'dso-google-gsi-js';
                    script.src = 'https://accounts.google.com/gsi/client';
                    script.async = true;
                    script.defer = true;
                    script.onload = function() {
                        renderGoogleButtons();
                        if (cb) cb();
                    };
                    document.head.appendChild(script);
                }

                var btnGoogleStep1 = document.getElementById('btn-google-step1');
                var btnGoogleStep2 = document.getElementById('btn-google-step2');
                [btnGoogleStep1, btnGoogleStep2].forEach(function(gBtn) {
                    if (gBtn) {
                        gBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            if (window.google && window.google.accounts && window.google.accounts.id) {
                                renderGoogleButtons();
                                window.google.accounts.id.prompt();
                            } else {
                                loadGoogleGsi(function() {
                                    if (window.google && window.google.accounts && window.google.accounts.id) {
                                        window.google.accounts.id.prompt();
                                    }
                                });
                            }
                        });
                    }
                });

                if (window.google && window.google.accounts && window.google.accounts.id) {
                    renderGoogleButtons();
                } else {
                    loadGoogleGsi();
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initDSOAuth);
            } else {
                initDSOAuth();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

DSO_Login::init();
