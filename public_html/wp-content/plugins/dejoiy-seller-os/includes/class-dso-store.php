<?php
/**
 * DSO Storefront Customizer & Profile Engine
 * Highly customizable seller storefront with live preview
 */
if (!defined('ABSPATH')) exit;

class DSO_Store {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();

        // Get public store URL
        $u = get_userdata($vendor_id);
        $slug = $u ? $u->user_nicename : $vendor_id;
        $live_store_url = function_exists('wcfmmp_get_store_url') ? wcfmmp_get_store_url($vendor_id) : home_url('/store/' . $slug . '/');

        // Force the storefront URL onto the customer marketplace host (Store Studio runs on
        // sellerhub.dejoiy.com, where home_url() points at the seller app). Keep path/query/fragment.
        if (!empty($live_store_url) && strpos($live_store_url, 'https://dejoiy.com') !== 0) {
            $dso_parts = parse_url($live_store_url);
            $dso_path = isset($dso_parts['path']) ? $dso_parts['path'] : '/';
            if (!empty($dso_parts['query'])) { $dso_path .= '?' . $dso_parts['query']; }
            if (!empty($dso_parts['fragment'])) { $dso_path .= '#' . $dso_parts['fragment']; }
            $live_store_url = 'https://dejoiy.com' . $dso_path;
        }

        // Fetch current WCFM profile settings
        $wcfm_settings = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
        if (is_string($wcfm_settings)) {
            $wcfm_settings = maybe_unserialize($wcfm_settings);
        }
        if (!is_array($wcfm_settings)) {
            $wcfm_settings = [];
        }

        // Handle update
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST' && isset($_POST['dso_update_store'])) {
            check_admin_referer('dso_update_store_nonce');

            $store_name = sanitize_text_field($_POST['store_name'] ?? '');
            $store_tagline = sanitize_text_field($_POST['store_tagline'] ?? '');
            $store_desc = wp_kses_post($_POST['store_description'] ?? '');
            $store_email = sanitize_email($_POST['store_email'] ?? '');
            $store_phone = sanitize_text_field($_POST['store_phone'] ?? '');

            $banner_url = esc_url_raw($_POST['store_banner_url'] ?? '');
            $logo_url = esc_url_raw($_POST['store_logo_url'] ?? '');

            // Address
            $address = [
                'street_1' => sanitize_text_field($_POST['store_street'] ?? ''),
                'city'     => sanitize_text_field($_POST['store_city'] ?? ''),
                'state'    => sanitize_text_field($_POST['store_state'] ?? ''),
                'zip'      => sanitize_text_field($_POST['store_zip'] ?? ''),
                'country'  => sanitize_text_field($_POST['store_country'] ?? 'IN'),
            ];

            // Policies
            $shipping_policy = wp_kses_post($_POST['policy_shipping'] ?? '');
            $refund_policy = wp_kses_post($_POST['policy_refund'] ?? '');
            $cancellation_policy = wp_kses_post($_POST['policy_cancellation'] ?? '');

            // Social
            $social = [
                'fb'        => esc_url_raw($_POST['social_facebook'] ?? ''),
                'twitter'   => esc_url_raw($_POST['social_twitter'] ?? ''),
                'instagram' => esc_url_raw($_POST['social_instagram'] ?? ''),
                'youtube'   => esc_url_raw($_POST['social_youtube'] ?? ''),
                'linkedin'  => esc_url_raw($_POST['social_linkedin'] ?? ''),
            ];

            // Merge into WCFM profile array
            $wcfm_settings['store_name'] = $store_name;
            $wcfm_settings['shop_description'] = $store_desc;
            $wcfm_settings['phone'] = $store_phone;
            $wcfm_settings['address'] = $address;
            $wcfm_settings['city'] = $address['city'];
            $wcfm_settings['social'] = $social;
            $wcfm_settings['customer_support'] = [
                'email' => $store_email,
                'phone' => $store_phone,
                'address' => $address['street_1'],
            ];
            $wcfm_settings['wcfm_policy_vendor_options'] = [
                'shipping_policy' => $shipping_policy,
                'refund_policy' => $refund_policy,
                'cancellation_policy' => $cancellation_policy,
            ];

            if (!empty($banner_url)) {
                $wcfm_settings['banner'] = $banner_url;
            }
            if (!empty($logo_url)) {
                $wcfm_settings['gravatar'] = $logo_url;
            }

            // Save to usermeta
            update_user_meta($vendor_id, 'wcfmmp_profile_settings', $wcfm_settings);
            update_user_meta($vendor_id, 'store_name', $store_name);
            update_user_meta($vendor_id, 'dso_store_tagline', $store_tagline);
            update_user_meta($vendor_id, 'dso_store_banner', $banner_url);
            update_user_meta($vendor_id, 'dso_store_logo', $logo_url);
            update_user_meta($vendor_id, 'dso_store_email', $store_email);
            update_user_meta($vendor_id, 'dso_store_phone', $store_phone);
            update_user_meta($vendor_id, 'dso_shipping_policy', $shipping_policy);
            update_user_meta($vendor_id, 'dso_refund_policy', $refund_policy);
            update_user_meta($vendor_id, 'dso_cancellation_policy', $cancellation_policy);

            wp_redirect('?section=store&updated=1');
            exit;
        }

        // Current values
        $store_name = !empty($wcfm_settings['store_name']) ? $wcfm_settings['store_name'] : (get_user_meta($vendor_id, 'store_name', true) ?: ($u ? $u->display_name : ''));
        $store_tagline = get_user_meta($vendor_id, 'dso_store_tagline', true) ?: 'Official Brand Store on DEJOIY Marketplace';
        $store_desc = $wcfm_settings['shop_description'] ?? '';
        $store_email = $wcfm_settings['customer_support']['email'] ?? get_user_meta($vendor_id, 'dso_store_email', true) ?: get_userdata($vendor_id)->user_email;
        $store_phone = $wcfm_settings['phone'] ?? get_user_meta($vendor_id, 'dso_store_phone', true) ?: '';

        $banner_url = get_user_meta($vendor_id, 'dso_store_banner', true) ?: ($wcfm_settings['banner'] ?? '');
        if (is_numeric($banner_url)) {
            $banner_url = wp_get_attachment_url($banner_url);
        }
        if (empty($banner_url)) {
            $banner_url = 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1600&auto=format&fit=crop&q=80';
        }

        $logo_url = get_user_meta($vendor_id, 'dso_store_logo', true) ?: ($wcfm_settings['gravatar'] ?? '');
        if (is_numeric($logo_url)) {
            $logo_url = wp_get_attachment_url($logo_url);
        }

        $addr = $wcfm_settings['address'] ?? [];
        $street = $addr['street_1'] ?? '';
        $city = $addr['city'] ?? ($wcfm_settings['city'] ?? 'Indore');
        $state = $addr['state'] ?? 'Madhya Pradesh';
        $zip = $addr['zip'] ?? '';
        $country = $addr['country'] ?? 'India';

        $policies = $wcfm_settings['wcfm_policy_vendor_options'] ?? [];
        $shipping_policy = get_user_meta($vendor_id, 'dso_shipping_policy', true) ?: ($policies['shipping_policy'] ?? 'Orders are processed within 24 hours. Standard delivery timeline across India is 2-4 business days with end-to-end tracking.');
        $refund_policy = get_user_meta($vendor_id, 'dso_refund_policy', true) ?: ($policies['refund_policy'] ?? 'Hassle-free 7-day return policy. Items must be returned unworn/unused with original tags and packaging.');
        $cancellation_policy = get_user_meta($vendor_id, 'dso_cancellation_policy', true) ?: ($policies['cancellation_policy'] ?? 'Cancellations are accepted prior to courier dispatch. Instant full refund to original payment source.');

        $social = $wcfm_settings['social'] ?? [];
        $social_fb = $social['fb'] ?? '';
        $social_tw = $social['twitter'] ?? '';
        $social_ig = $social['instagram'] ?? '';
        $social_yt = $social['youtube'] ?? '';
        $social_li = $social['linkedin'] ?? '';

        ?>
        <div class="dso-page dso-storefront-page">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Storefront Customizer</span>
                    </div>
                    <h1 class="dso-page-title">Storefront Studio & Public Brand Page</h1>
                    <p class="dso-page-subtitle">Customize how millions of DEJOIY shoppers discover and experience your official storefront.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="<?php echo esc_url($live_store_url); ?>" target="_blank" rel="noopener" class="dso-btn dso-btn-primary" style="display:inline-flex;align-items:center;gap:8px;box-shadow: 0 4px 12px rgba(124, 58, 237, 0.3);">
                        <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span>View Live Storefront ↗</span>
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['updated'])): ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:12px;">
                    <span style="font-size:20px;">✓</span>
                    <div>
                        <strong style="color:#065f46;display:block;">Storefront Updated Successfully!</strong>
                        <span style="font-size:13px;color:#047857;">Your live store branding, policies, and contact information have been updated across DEJOIY Marketplace.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Live Preview Banner Card -->
            <div class="dso-card dso-mb-4" style="overflow:hidden;border:1px solid rgba(0, 102, 255, 0.2);box-shadow:0 10px 25px -5px rgba(0,0,0,0.08);">
                <div style="position:relative;height:180px;background:url('<?php echo esc_url($banner_url); ?>') center/cover no-repeat;background-color:#001553;">
                    <div style="position:absolute;inset:0;background:linear-gradient(to top, rgba(15,23,42,0.85) 0%, rgba(15,23,42,0.2) 60%, transparent 100%);"></div>
                    <div style="position:absolute;bottom:20px;left:24px;right:24px;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;">
                        <div style="display:flex;align-items:center;gap:16px;">
                            <div style="width:72px;height:72px;border-radius:16px;background:#fff;border:3px solid #fff;box-shadow:0 8px 16px rgba(0,0,0,0.2);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <?php if (!empty($logo_url)): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" alt="" style="width:100%;height:100%;object-fit:cover;" />
                                <?php else: ?>
                                    <div style="font-size:28px;font-weight:800;color:#0066ff;"><?php echo strtoupper(substr($store_name, 0, 1) ?: 'D'); ?></div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <h2 style="color:#ffffff;margin:0;font-size:22px;font-weight:700;"><?php echo esc_html($store_name ?: 'Your Store Name'); ?></h2>
                                    <span class="dso-badge dso-badge-green" style="font-size:11px;">Verified Merchant ✓</span>
                                </div>
                                <p style="color:#cbd5e1;margin:4px 0 0;font-size:13px;"><?php echo esc_html($store_tagline); ?></p>
                            </div>
                        </div>
                        <a href="<?php echo esc_url($live_store_url); ?>" target="_blank" rel="noopener" class="dso-btn dso-btn-sm" style="background:rgba(255,255,255,0.9);color:#0f172a;font-weight:600;">
                            Visit Public URL ↗
                        </a>
                    </div>
                </div>
            </div>

            <form method="post" class="dso-form">
                <?php wp_nonce_field('dso_update_store_nonce'); ?>

                <div class="dso-grid-2">
                    <!-- Brand Identity & Visuals -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">🎨 Visual Assets & Identity</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-group">
                                <label for="store_name">Official Store Name *</label>
                                <input type="text" id="store_name" name="store_name" class="dso-input" required value="<?php echo esc_attr($store_name); ?>" />
                                <small class="dso-text-muted">Displayed on product pages, invoices, and brand search.</small>
                            </div>
                            <div class="dso-form-group">
                                <label for="store_tagline">Store Slogan / Tagline</label>
                                <input type="text" id="store_tagline" name="store_tagline" class="dso-input" value="<?php echo esc_attr($store_tagline); ?>" placeholder="e.g. Premium Handcrafted Apparel & Lifestyle" />
                            </div>
                            <div class="dso-form-group">
                                <label for="store_banner_url">Storefront Banner Image URL</label>
                                <input type="url" id="store_banner_url" name="store_banner_url" class="dso-input" value="<?php echo esc_attr($banner_url); ?>" placeholder="https://... recommended 1600x400px" />
                                <small class="dso-text-muted">Direct image URL for your header banner on DEJOIY.</small>
                            </div>
                            <div class="dso-form-group">
                                <label for="store_logo_url">Store Logo / Brand Avatar URL</label>
                                <input type="url" id="store_logo_url" name="store_logo_url" class="dso-input" value="<?php echo esc_attr($logo_url); ?>" placeholder="https://... recommended 400x400px square" />
                            </div>
                            <div class="dso-form-group">
                                <label for="store_description">Store Bio & Brand Story</label>
                                <textarea id="store_description" name="store_description" class="dso-textarea" rows="4" placeholder="Tell DEJOIY shoppers your brand's heritage, craftsmanship, and mission..."><?php echo esc_textarea($store_desc); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Customer Support & Address -->
                    <div class="dso-card">
                        <div class="dso-card-header">
                            <h3 class="dso-card-title">📞 Contact & Operating Location</h3>
                        </div>
                        <div class="dso-card-body">
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="store_email">Customer Support Email *</label>
                                    <input type="email" id="store_email" name="store_email" class="dso-input" required value="<?php echo esc_attr($store_email); ?>" />
                                </div>
                                <div class="dso-form-group">
                                    <label for="store_phone">Support Hotline / WhatsApp</label>
                                    <input type="tel" id="store_phone" name="store_phone" class="dso-input" value="<?php echo esc_attr($store_phone); ?>" placeholder="+91 98765 43210" />
                                </div>
                            </div>
                            <div class="dso-form-group">
                                <label for="store_street">Warehouse / Registered Address</label>
                                <input type="text" id="store_street" name="store_street" class="dso-input" value="<?php echo esc_attr($street); ?>" placeholder="Building, Street, Area" />
                            </div>
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="store_city">City</label>
                                    <input type="text" id="store_city" name="store_city" class="dso-input" value="<?php echo esc_attr($city); ?>" />
                                </div>
                                <div class="dso-form-group">
                                    <label for="store_state">State</label>
                                    <input type="text" id="store_state" name="store_state" class="dso-input" value="<?php echo esc_attr($state); ?>" />
                                </div>
                            </div>
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="store_zip">PIN Code</label>
                                    <input type="text" id="store_zip" name="store_zip" class="dso-input" value="<?php echo esc_attr($zip); ?>" placeholder="452001" />
                                </div>
                                <div class="dso-form-group">
                                    <label for="store_country">Country</label>
                                    <input type="text" id="store_country" name="store_country" class="dso-input" value="<?php echo esc_attr($country); ?>" readonly />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Policies & Customer Confidence -->
                <div class="dso-card dso-mt-4">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">📜 Storefront Policies & Buyer Commitments</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-grid-3">
                            <div class="dso-form-group">
                                <label for="policy_shipping">🚚 Shipping & Delivery Policy</label>
                                <textarea id="policy_shipping" name="policy_shipping" class="dso-textarea" rows="4"><?php echo esc_textarea($shipping_policy); ?></textarea>
                            </div>
                            <div class="dso-form-group">
                                <label for="policy_refund">🔄 Return & Replacement Policy</label>
                                <textarea id="policy_refund" name="policy_refund" class="dso-textarea" rows="4"><?php echo esc_textarea($refund_policy); ?></textarea>
                            </div>
                            <div class="dso-form-group">
                                <label for="policy_cancellation">❌ Order Cancellation Policy</label>
                                <textarea id="policy_cancellation" name="policy_cancellation" class="dso-textarea" rows="4"><?php echo esc_textarea($cancellation_policy); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Channels -->
                <div class="dso-card dso-mt-4">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">🌐 Social Handles & Brand Links</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-form-row">
                            <div class="dso-form-group">
                                <label for="social_instagram">Instagram Profile URL</label>
                                <input type="url" id="social_instagram" name="social_instagram" class="dso-input" value="<?php echo esc_attr($social_ig); ?>" placeholder="https://instagram.com/yourbrand" />
                            </div>
                            <div class="dso-form-group">
                                <label for="social_facebook">Facebook Page URL</label>
                                <input type="url" id="social_facebook" name="social_facebook" class="dso-input" value="<?php echo esc_attr($social_fb); ?>" placeholder="https://facebook.com/yourbrand" />
                            </div>
                            <div class="dso-form-group">
                                <label for="social_youtube">YouTube Channel URL</label>
                                <input type="url" id="social_youtube" name="social_youtube" class="dso-input" value="<?php echo esc_attr($social_yt); ?>" placeholder="https://youtube.com/@yourbrand" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dso-mt-4" style="display:flex;justify-content:flex-end;gap:12px;">
                    <a href="<?php echo esc_url($live_store_url); ?>" target="_blank" rel="noopener" class="dso-btn dso-btn-outline">
                        Preview Storefront ↗
                    </a>
                    <button type="submit" name="dso_update_store" value="1" class="dso-btn dso-btn-primary" style="padding:12px 32px;font-size:15px;font-weight:700;">
                        Save Storefront Settings
                    </button>
                </div>
            </form>
        </div>
        <?php
    }
}
