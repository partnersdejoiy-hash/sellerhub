<?php
/**
 * DSO Settings
 */
if (!defined('ABSPATH')) exit;

class DSO_Settings {

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $store = DSO_Auth::get_vendor_store($user_id);

        // Handle password change
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_update_settings'])) {
            check_admin_referer('dso_settings');

            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (!empty($new_password)) {
                if (!wp_check_password($current_password, get_userdata($user_id)->user_pass)) {
                    $error = 'Current password is incorrect.';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($new_password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } else {
                    wp_set_password($new_password, $user_id);
                    $success = 'Password updated successfully.';
                }
            }

            // Update notification preferences
            update_user_meta($user_id, 'dso_email_orders', isset($_POST['email_orders']) ? 1 : 0);
            update_user_meta($user_id, 'dso_email_reviews', isset($_POST['email_reviews']) ? 1 : 0);
            update_user_meta($user_id, 'dso_email_payments', isset($_POST['email_payments']) ? 1 : 0);

            // Update 2-Step Verification preference
            $two_fa_enabled = isset($_POST['dso_2fa_enabled']) && $_POST['dso_2fa_enabled'] === 'yes' ? 'yes' : 'no';
            update_user_meta($user_id, 'dso_2fa_enabled', $two_fa_enabled);

            if (empty($error)) {
                $success = !empty($new_password) ? 'Password and security settings updated successfully.' : 'Security settings updated successfully.';
            }
        }

        $email_orders = get_user_meta($user_id, 'dso_email_orders', true) !== '0';
        $email_reviews = get_user_meta($user_id, 'dso_email_reviews', true) !== '0';
        $email_payments = get_user_meta($user_id, 'dso_email_payments', true) !== '0';
        $is_2fa_enabled = class_exists('DSO_Login') ? DSO_Login::is_2fa_enabled($user_id) : (get_user_meta($user_id, 'dso_2fa_enabled', true) !== 'no');

        ?>
        <div class="dso-page dso-settings">
            <div class="dso-page-header">
                <div>
                    <h1>Settings</h1>
                    <p>Manage your account settings</p>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="dso-alert dso-alert-success"><?php echo esc_html($success) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="dso-alert dso-alert-error"><?php echo esc_html($error) ?></div>
            <?php endif; ?>

            <form method="post" class="dso-form">
                <?php wp_nonce_field('dso_settings'); ?>

                <!-- Account -->
                <div class="dso-card">
                    <div class="dso-card-header"><h3>Account Security</h3></div>
                    <div class="dso-card-body">
                        <div class="dso-form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="dso-input" />
                        </div>
                        <div class="dso-form-row">
                            <div class="dso-form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="dso-input" minlength="8" />
                            </div>
                            <div class="dso-form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="dso-input" />
                            </div>
                        </div>
                        <div class="dso-form-group" style="margin-top: 18px; padding-top: 18px; border-top: 1px solid #e2e8f0;">
                            <label class="dso-checkbox-label" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="dso_2fa_enabled" value="yes" <?php echo $is_2fa_enabled ? 'checked' : ''; ?> style="margin-top: 3px; width: 17px; height: 17px; accent-color: #004bbf;" />
                                <div>
                                    <strong style="display: block; font-size: 14px; color: #0f172a;">Enable Two-Step Verification (OTP)</strong>
                                    <span style="display: block; font-size: 13px; color: #64748b; margin-top: 3px; line-height: 1.4;">
                                        Compulsory verification code sent to your email or phone when signing into DEJOIY Seller Hub.
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Notification Preferences -->
                <div class="dso-card">
                    <div class="dso-card-header"><h3>Email Notifications</h3></div>
                    <div class="dso-card-body">
                        <div class="dso-form-group">
                            <label class="dso-checkbox-label">
                                <input type="checkbox" name="email_orders" value="1" <?php echo $email_orders ? 'checked' : '' ?> />
                                Receive email for new orders
                            </label>
                        </div>
                        <div class="dso-form-group">
                            <label class="dso-checkbox-label">
                                <input type="checkbox" name="email_reviews" value="1" <?php echo $email_reviews ? 'checked' : '' ?> />
                                Receive email for new reviews
                            </label>
                        </div>
                        <div class="dso-form-group">
                            <label class="dso-checkbox-label">
                                <input type="checkbox" name="email_payments" value="1" <?php echo $email_payments ? 'checked' : '' ?> />
                                Receive email for payment updates
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" name="dso_update_settings" value="1" class="dso-btn dso-btn-primary">
                    Save Settings
                </button>
            </form>
        </div>
        <?php
    }

    public function tax() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_save_tax_settings'])) {
            check_admin_referer('dso_tax_settings');

            update_user_meta($vendor_id, 'dso_legal_business_name', sanitize_text_field($_POST['legal_business_name'] ?? ''));
            update_user_meta($vendor_id, 'dso_gstin', sanitize_text_field($_POST['gstin'] ?? ''));
            update_user_meta($vendor_id, 'dso_pan', sanitize_text_field($_POST['pan'] ?? ''));
            update_user_meta($vendor_id, 'dso_state_code', sanitize_text_field($_POST['state_code'] ?? ''));
            update_user_meta($vendor_id, 'dso_invoice_prefix', sanitize_text_field($_POST['invoice_prefix'] ?? 'INV-DJ-'));
            update_user_meta($vendor_id, 'dso_composition_scheme', isset($_POST['composition_scheme']) ? 'yes' : 'no');

            $success = 'Tax & GST configuration saved successfully.';
        }

        $legal_name = get_user_meta($vendor_id, 'dso_legal_business_name', true) ?: get_user_meta($vendor_id, 'store_name', true);
        $gstin = get_user_meta($vendor_id, 'dso_gstin', true);
        $pan = get_user_meta($vendor_id, 'dso_pan', true);
        $state_code = get_user_meta($vendor_id, 'dso_state_code', true) ?: '07';
        $prefix = get_user_meta($vendor_id, 'dso_invoice_prefix', true) ?: 'INV-DJ-';
        $composition = get_user_meta($vendor_id, 'dso_composition_scheme', true) === 'yes';

        ?>
        <div class="dso-page dso-tax-settings">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=settings">Settings</a>
                        <span>/</span>
                        <span>GST & Tax</span>
                    </div>
                    <h1 class="dso-page-title">GST & Tax Compliance</h1>
                    <p class="dso-page-subtitle">Configure GSTIN, legal entity identity, PAN, and tax invoice series</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=settings" class="dso-btn dso-btn-outline">← Back to Settings</a>
                </div>
            </div>

            <?php if (!empty($success)): ?>
                <div class="dso-alert dso-alert-success"><?php echo esc_html($success); ?></div>
            <?php endif; ?>

            <form method="post" class="dso-form">
                <?php wp_nonce_field('dso_tax_settings'); ?>

                <div class="dso-card dso-mb-4">
                    <div class="dso-card-header"><h3 class="dso-card-title">Legal Business Entity</h3></div>
                    <div class="dso-card-body">
                        <div class="dso-form-group">
                            <label for="legal_business_name">Registered Legal Business Name *</label>
                            <input type="text" id="legal_business_name" name="legal_business_name" class="dso-input" value="<?php echo esc_attr($legal_name); ?>" required />
                            <small class="dso-text-muted">Exact name as printed on GST Certificate or PAN Card</small>
                        </div>
                        <div class="dso-form-row">
                            <div class="dso-form-group">
                                <label for="gstin">GSTIN (15-character GST Identification Number)</label>
                                <input type="text" id="gstin" name="gstin" class="dso-input" value="<?php echo esc_attr($gstin); ?>" maxlength="15" placeholder="e.g. 07AAAAA0000A1Z5" style="text-transform:uppercase;" />
                            </div>
                            <div class="dso-form-group">
                                <label for="pan">Permanent Account Number (PAN) *</label>
                                <input type="text" id="pan" name="pan" class="dso-input" value="<?php echo esc_attr($pan); ?>" maxlength="10" placeholder="e.g. ABCDE1234F" style="text-transform:uppercase;" required />
                            </div>
                        </div>
                        <div class="dso-form-group">
                            <label for="state_code">Registration State Code (GST Jurisdiction)</label>
                            <input type="text" id="state_code" name="state_code" class="dso-input" value="<?php echo esc_attr($state_code); ?>" placeholder="e.g. 07 - Delhi" />
                        </div>
                    </div>
                </div>

                <div class="dso-card dso-mb-4">
                    <div class="dso-card-header"><h3 class="dso-card-title">Invoice Series & GST Rules</h3></div>
                    <div class="dso-card-body">
                        <div class="dso-form-group">
                            <label for="invoice_prefix">Tax Invoice Prefix</label>
                            <input type="text" id="invoice_prefix" name="invoice_prefix" class="dso-input" value="<?php echo esc_attr($prefix); ?>" placeholder="INV-DJ-" />
                            <small class="dso-text-muted">Prepended to customer tax invoices generated from Seller Hub</small>
                        </div>
                        <div class="dso-form-group">
                            <label class="dso-checkbox-label">
                                <input type="checkbox" name="composition_scheme" value="yes" <?php echo $composition ? 'checked' : ''; ?> />
                                Enrolled under GST Composition Scheme (Sub-limit turnover rules apply)
                            </label>
                        </div>
                    </div>
                </div>

                <button type="submit" name="dso_save_tax_settings" value="1" class="dso-btn dso-btn-primary" style="padding:12px 24px;">
                    Save Tax Configuration 💾
                </button>
            </form>
        </div>
        <?php
    }
    /**
     * Bank account details for payouts
     */
    public function bank() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Bank Details</h1><p class="dso-ph-sub">Manage the bank account used for your payouts.</p></div>
            <div class="dso-ph-card"><h2>No bank account added</h2><p>Add your bank account details to receive payouts.</p></div>
        </div>
        <?php
    }

    /**
     * Account security overview
     */
    public function security() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Security</h1><p class="dso-ph-sub">Review and manage your account security.</p></div>
            <div class="dso-ph-card"><h2>Security overview</h2><p>Two-factor authentication and login activity controls will be available here.</p></div>
        </div>
        <?php
    }

}
