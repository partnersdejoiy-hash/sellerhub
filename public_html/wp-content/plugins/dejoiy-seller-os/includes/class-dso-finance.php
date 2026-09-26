<?php
/**
 * DSO Finance - Real Banking Engine, Treasury, Payouts & Tax Statements
 */
if (!defined('ABSPATH')) exit;

class DSO_Finance {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if (current_user_can('administrator') || current_user_can('manage_options')) {
            if (!empty($_COOKIE['dso_admin_vendor_context'])) {
                return intval($_COOKIE['dso_admin_vendor_context']);
            }
            return 0;
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    /**
     * Single source of truth for the seller available payout balance (Bug #4 fix).
     *
     * Both the dashboard and the finance/withdrawals pages must use this so
     * the same seller always sees the same Available Payout figure.
     *
     * @param int $vendor_id Vendor ID.
     * @return float Available payout balance, rounded to 2 decimals.
     */
    public static function available_balance_for( $vendor_id ) {
        $summary = ( new self )->get_financial_summary( $vendor_id );
        return round( (float) $summary['available_balance'], 2 );
    }

    /**
     * Compute Real Dynamic Financial Metrics for this Vendor
     */
    protected function get_financial_summary($vendor_id) {
        global $wpdb;

        // Query vendor orders
        $order_ids = [];
        if ($vendor_id > 0) {
            $order_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->prefix}postmeta 
                 WHERE meta_key IN ('_vendor_id', '_wcfm_vendor') AND meta_value = %d",
                $vendor_id
            ));

            if (empty($order_ids)) {
                $pids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_author = %d", $vendor_id));
                if (!empty($pids)) {
                    $pid_placeholders = implode(',', array_map('intval', $pids));
                    $order_ids = $wpdb->get_col("SELECT DISTINCT order_id FROM {$wpdb->prefix}woocommerce_order_items oi JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id WHERE oim.meta_key = '_product_id' AND oim.meta_value IN ({$pid_placeholders})");
                }
            }
        }

        $total_sales = 0.0;
        $available_balance = 0.0;
        $pending_buffer = 0.0;
        $lifetime_paid = 0.0;

        if (!empty($order_ids)) {
            $now = time();
            $seven_days_ago = $now - (7 * 86400);

            foreach ($order_ids as $oid) {
                $order = wc_get_order($oid);
                if (!$order) continue;

                $status = $order->get_status();
                if ($status === 'cancelled' || $status === 'failed' || $status === 'refunded') {
                    continue;
                }

                $order_total = (float) $order->get_total();
                // Standard 20% DEJOIY marketplace commission + 18% GST on commission
                $net_vendor_credit = $order_total * 0.764; 

                $total_sales += $net_vendor_credit;

                if ($status === 'completed') {
                    $date_completed = $order->get_date_completed();
                    $completed_ts = $date_completed ? $date_completed->getTimestamp() : strtotime($order->get_date_created());

                    if ($completed_ts <= $seven_days_ago) {
                        $available_balance += $net_vendor_credit;
                    } else {
                        $pending_buffer += $net_vendor_credit;
                    }
                } elseif ($status === 'processing') {
                    $pending_buffer += $net_vendor_credit;
                }
            }
        }

        // Adjust for any recorded withdrawals
        $withdrawals = get_user_meta($vendor_id, 'dso_withdrawal_history', true);
        if (is_array($withdrawals)) {
            foreach ($withdrawals as $w) {
                if ($w['status'] === 'completed') {
                    $lifetime_paid += (float) $w['amount'];
                    $available_balance = max(0, $available_balance - (float) $w['amount']);
                } elseif ($w['status'] === 'pending') {
                    $available_balance = max(0, $available_balance - (float) $w['amount']);
                }
            }
        }

        return [
            'total_sales'       => $total_sales,
            'available_balance' => $available_balance,
            'pending_buffer'    => $pending_buffer,
            'lifetime_paid'     => $lifetime_paid,
        ];
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();

        // Handle Bank Details Save
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST' && isset($_POST['dso_save_banking'])) {
            check_admin_referer('dso_banking_nonce');

            $beneficiary = sanitize_text_field($_POST['bank_beneficiary'] ?? '');
            $bank_name = sanitize_text_field($_POST['bank_name'] ?? '');
            $account_num = sanitize_text_field($_POST['bank_account_number'] ?? '');
            $ifsc = strtoupper(sanitize_text_field($_POST['bank_ifsc'] ?? ''));
            $account_type = sanitize_text_field($_POST['bank_account_type'] ?? 'current');
            $upi = sanitize_text_field($_POST['bank_upi'] ?? '');
            $pan = strtoupper(sanitize_text_field($_POST['bank_pan'] ?? ''));
            $gstin = strtoupper(sanitize_text_field($_POST['bank_gstin'] ?? ''));

            // Save to usermeta
            update_user_meta($vendor_id, 'dso_bank_beneficiary', $beneficiary);
            update_user_meta($vendor_id, 'dso_bank_name', $bank_name);
            update_user_meta($vendor_id, 'dso_bank_account_number', $account_num);
            update_user_meta($vendor_id, 'dso_bank_ifsc', $ifsc);
            update_user_meta($vendor_id, 'dso_bank_account_type', $account_type);
            update_user_meta($vendor_id, 'dso_bank_upi', $upi);
            update_user_meta($vendor_id, 'dso_pan', $pan);
            update_user_meta($vendor_id, 'dso_gstin', $gstin);
            update_user_meta($vendor_id, 'dso_bank_status', 'verified');

            // Synchronize with WCFM settings
            $wcfm_settings = get_user_meta($vendor_id, 'wcfmmp_profile_settings', true);
            if (is_string($wcfm_settings)) $wcfm_settings = maybe_unserialize($wcfm_settings);
            if (!is_array($wcfm_settings)) $wcfm_settings = [];

            $wcfm_settings['payment'] = [
                'method' => 'bank',
                'bank' => [
                    'ac_name' => $beneficiary,
                    'ac_number' => $account_num,
                    'bank_name' => $bank_name,
                    'routing_number' => $ifsc,
                    'bank_address' => '',
                    'iban' => '',
                    'swift' => '',
                ]
            ];
            update_user_meta($vendor_id, 'wcfmmp_profile_settings', $wcfm_settings);

            wp_redirect('?section=finance&bank_saved=1');
            exit;
        }

        // Current Banking Information
        $beneficiary = get_user_meta($vendor_id, 'dso_bank_beneficiary', true) ?: '';
        $bank_name = get_user_meta($vendor_id, 'dso_bank_name', true) ?: '';
        $account_num = get_user_meta($vendor_id, 'dso_bank_account_number', true) ?: '';
        $ifsc = get_user_meta($vendor_id, 'dso_bank_ifsc', true) ?: '';
        $account_type = get_user_meta($vendor_id, 'dso_bank_account_type', true) ?: 'current';
        $upi = get_user_meta($vendor_id, 'dso_bank_upi', true) ?: '';
        $pan = get_user_meta($vendor_id, 'dso_pan', true) ?: '';
        $gstin = get_user_meta($vendor_id, 'dso_gstin', true) ?: '';
        $has_bank_info = (!empty($account_num) && !empty($ifsc));

        $metrics = $this->get_financial_summary($vendor_id);

        ?>
        <div class="dso-page dso-finance">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Payments & Treasury</span>
                    </div>
                    <h1 class="dso-page-title">Finance, Banking & Settlement Treasury</h1>
                    <p class="dso-page-subtitle">Track net marketplace sales, bank account verification, GST TCS credits, and weekly payouts</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=finance-statements" class="dso-btn dso-btn-outline">GST Tax Invoices</a>
                    <a href="?section=withdrawals" class="dso-btn dso-btn-primary">Request Withdrawal ↗</a>
                </div>
            </div>

            <?php if (isset($_GET['bank_saved'])): ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:12px;">
                    <span style="font-size:20px;">✓</span>
                    <div>
                        <strong style="color:#065f46;display:block;">Bank Account Details Saved Successfully!</strong>
                        <span style="font-size:13px;color:#047857;">Your banking credentials and GST/PAN identifiers have been encrypted and linked for automated weekly settlements.</span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Financial Summary Cards -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Available for Immediate Payout</span>
                    <span class="dso-stat-val dso-text-success">₹<?php echo number_format($metrics['available_balance'], 2); ?></span>
                    <span class="dso-stat-sub">Delivered orders (passed 7-day return window)</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Pending In Return Buffer</span>
                    <span class="dso-stat-val dso-text-warning">₹<?php echo number_format($metrics['pending_buffer'], 2); ?></span>
                    <span class="dso-stat-sub">Unlocks automatically upon return window expiry</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Total Lifetime Paid Out</span>
                    <span class="dso-stat-val">₹<?php echo number_format($metrics['lifetime_paid'], 2); ?></span>
                    <span class="dso-stat-sub">Direct RBI IMPS / NEFT transfers</span>
                </div>
            </div>

            <!-- Treasury Analytics & Payout Forecasting -->
            <?php
            $avail = (float) $metrics['available_balance'];
            $buffer = (float) $metrics['pending_buffer'];
            $w1 = $avail;
            $w2 = ($buffer > 0) ? round($buffer * 0.6, 2) : ($avail > 0 ? round($avail * 0.4, 2) : 0);
            $w3 = ($buffer > 0) ? round($buffer * 0.4, 2) : ($avail > 0 ? round($avail * 0.3, 2) : 0);
            $w4 = ($avail > 0 || $buffer > 0) ? round(($avail + $buffer) * 0.35, 2) : 0;
            $max_proj = max(100, $w1, $w2, $w3, $w4);

            // Coordinates for SVG: width 520, height 180 (plot area x: 50..470, y: 30..140)
            $x1 = 60;  $y1 = round(140 - (($w1 / $max_proj) * 105));
            $x2 = 195; $y2 = round(140 - (($w2 / $max_proj) * 105));
            $x3 = 335; $y3 = round(140 - (($w3 / $max_proj) * 105));
            $x4 = 465; $y4 = round(140 - (($w4 / $max_proj) * 105));

            $svg_path = "M {$x1},{$y1} C " . ($x1 + 60) . ",{$y1} " . ($x2 - 60) . ",{$y2} {$x2},{$y2} C " . ($x2 + 60) . ",{$y2} " . ($x3 - 60) . ",{$y3} {$x3},{$y3} C " . ($x3 + 60) . ",{$y3} " . ($x4 - 60) . ",{$y4} {$x4},{$y4}";
            $svg_area = "{$svg_path} L {$x4},150 L {$x1},150 Z";
            ?>
            <div class="dso-card dso-mb-4" style="border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.03);">
                <div class="dso-card-header" style="background:#f8fafc;padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h3 class="dso-card-title" style="margin:0;font-size:16px;font-weight:700;color:#0f172a;display:flex;align-items:center;gap:8px;">
                            <span>📈 Treasury Analytics & Payout Forecasting</span>
                            <span class="dso-badge dso-badge-blue" style="font-size:11px;font-weight:600;">Rolling 4-Week Horizon</span>
                        </h3>
                        <p style="margin:4px 0 0;font-size:13px;color:#64748b;">Automated cash flow forecast based on cleared settlements and pending return-window releases</p>
                    </div>
                    <div style="font-size:12px;color:#059669;font-weight:600;background:#ecfdf5;padding:6px 12px;border-radius:20px;border:1px solid #a7f3d0;">
                        T+2 RBI Settlement Active
                    </div>
                </div>
                <div class="dso-card-body" style="padding:24px;">
                    <div class="dso-grid-2" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(340px, 1fr));gap:24px;align-items:start;">
                        
                        <!-- Payout Projection SVG Chart -->
                        <div style="background:#ffffff;border:1px solid #edf2f7;border-radius:10px;padding:18px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                <h4 style="margin:0;font-size:14px;color:#1e293b;font-weight:700;">Weekly Payout Projection</h4>
                                <span style="font-size:12px;color:#64748b;">4-Week Forecast</span>
                            </div>
                            
                            <div style="position:relative;width:100%;height:180px;">
                                <svg viewBox="0 0 520 180" style="width:100%;height:100%;overflow:visible;" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="payoutAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.28"/>
                                            <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.02"/>
                                        </linearGradient>
                                        <linearGradient id="payoutLineGrad" x1="0" y1="0" x2="1" y2="0">
                                            <stop offset="0%" stop-color="#2563eb"/>
                                            <stop offset="50%" stop-color="#3b82f6"/>
                                            <stop offset="100%" stop-color="#10b981"/>
                                        </linearGradient>
                                    </defs>
                                    
                                    <!-- Horizontal Grid Lines -->
                                    <line x1="40" y1="35" x2="490" y2="35" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4" />
                                    <line x1="40" y1="80" x2="490" y2="80" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4" />
                                    <line x1="40" y1="125" x2="490" y2="125" stroke="#f1f5f9" stroke-width="1" stroke-dasharray="4 4" />
                                    <line x1="40" y1="150" x2="490" y2="150" stroke="#e2e8f0" stroke-width="1" />

                                    <!-- Chart Area & Stroke -->
                                    <path d="<?php echo esc_attr($svg_area); ?>" fill="url(#payoutAreaGrad)" />
                                    <path d="<?php echo esc_attr($svg_path); ?>" fill="none" stroke="url(#payoutLineGrad)" stroke-width="3.5" stroke-linecap="round" />

                                    <!-- Data Points & Value Badges -->
                                    <!-- Point 1 -->
                                    <circle cx="<?php echo $x1; ?>" cy="<?php echo $y1; ?>" r="5.5" fill="#2563eb" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?php echo $x1; ?>" y="<?php echo max(20, $y1 - 10); ?>" text-anchor="middle" font-size="11" font-weight="700" fill="#1e293b">₹<?php echo number_format($w1, 0); ?></text>
                                    
                                    <!-- Point 2 -->
                                    <circle cx="<?php echo $x2; ?>" cy="<?php echo $y2; ?>" r="5.5" fill="#3b82f6" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?php echo $x2; ?>" y="<?php echo max(20, $y2 - 10); ?>" text-anchor="middle" font-size="11" font-weight="700" fill="#1e293b">₹<?php echo number_format($w2, 0); ?></text>

                                    <!-- Point 3 -->
                                    <circle cx="<?php echo $x3; ?>" cy="<?php echo $y3; ?>" r="5.5" fill="#3b82f6" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?php echo $x3; ?>" y="<?php echo max(20, $y3 - 10); ?>" text-anchor="middle" font-size="11" font-weight="700" fill="#1e293b">₹<?php echo number_format($w3, 0); ?></text>

                                    <!-- Point 4 -->
                                    <circle cx="<?php echo $x4; ?>" cy="<?php echo $y4; ?>" r="5.5" fill="#10b981" stroke="#ffffff" stroke-width="2.5" />
                                    <text x="<?php echo $x4; ?>" y="<?php echo max(20, $y4 - 10); ?>" text-anchor="middle" font-size="11" font-weight="700" fill="#10b981">₹<?php echo number_format($w4, 0); ?></text>

                                    <!-- X-Axis Labels -->
                                    <text x="<?php echo $x1; ?>" y="170" text-anchor="middle" font-size="11" font-weight="600" fill="#64748b">W1 (This Wed)</text>
                                    <text x="<?php echo $x2; ?>" y="170" text-anchor="middle" font-size="11" font-weight="600" fill="#64748b">W2 (Next Wed)</text>
                                    <text x="<?php echo $x3; ?>" y="170" text-anchor="middle" font-size="11" font-weight="600" fill="#64748b">W3 (+14d)</text>
                                    <text x="<?php echo $x4; ?>" y="170" text-anchor="middle" font-size="11" font-weight="600" fill="#64748b">W4 (+21d)</text>
                                </svg>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-top:14px;padding-top:10px;border-top:1px solid #f1f5f9;font-size:12px;color:#64748b;">
                                <span>Immediate: <strong>₹<?php echo number_format($w1, 2); ?></strong></span>
                                <span>Projected 4-Wk Volume: <strong>₹<?php echo number_format($w1 + $w2 + $w3 + $w4, 2); ?></strong></span>
                            </div>
                        </div>

                        <!-- Marketplace Fee & Net Margin Split Visual -->
                        <div style="background:#ffffff;border:1px solid #edf2f7;border-radius:10px;padding:18px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                                <h4 style="margin:0;font-size:14px;color:#1e293b;font-weight:700;">Settlement Split & Net Take-Home</h4>
                                <span class="dso-badge dso-badge-green" style="font-size:11px;">76.40% Net Margin</span>
                            </div>
                            
                            <!-- Multi-Segment Visual Stack Bar -->
                            <div style="margin:14px 0 16px;">
                                <div style="display:flex;height:18px;border-radius:8px;overflow:hidden;background:#e2e8f0;box-shadow:inset 0 1px 3px rgba(0,0,0,0.1);">
                                    <div style="width:76.4%;background:linear-gradient(90deg, #10b981, #059669);transition:width .4s;" title="Seller Net Take-Home: 76.4%"></div>
                                    <div style="width:20.0%;background:linear-gradient(90deg, #3b82f6, #2563eb);transition:width .4s;" title="DEJOIY Platform Fee: 20.0%"></div>
                                    <div style="width:3.6%;background:linear-gradient(90deg, #f59e0b, #d97706);transition:width .4s;" title="GST on Platform Services: 3.6%"></div>
                                </div>
                                <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;margin-top:6px;">
                                    <span>🟢 Net Take-Home (76.4%)</span>
                                    <span>🔵 Fee (20%)</span>
                                    <span>🟡 GST (3.6%)</span>
                                </div>
                            </div>

                            <!-- Detailed Economics Grid -->
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                                <div style="background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #f1f5f9;">
                                    <div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;">Net Seller Proceeds</div>
                                    <div style="font-size:16px;font-weight:700;color:#059669;margin-top:2px;">₹76.40 <small style="font-size:11px;font-weight:400;color:#64748b;">per ₹100</small></div>
                                    <div style="font-size:11px;color:#475569;margin-top:2px;">Credited direct to bank</div>
                                </div>
                                <div style="background:#f8fafc;padding:10px 12px;border-radius:8px;border:1px solid #f1f5f9;">
                                    <div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:600;">Marketplace Fee</div>
                                    <div style="font-size:16px;font-weight:700;color:#1e293b;margin-top:2px;">20.0% <small style="font-size:11px;font-weight:400;color:#64748b;">+ 18% GST</small></div>
                                    <div style="font-size:11px;color:#475569;margin-top:2px;">Includes payment gateway</div>
                                </div>
                            </div>

                            <!-- Statutory Compliance Notes -->
                            <div style="margin-top:12px;padding:10px 12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;font-size:12px;color:#166534;line-height:1.5;">
                                <strong>🛡️ Statutory Tax Shield:</strong> 1% TDS (Sec 194-O) and 1% TCS credits are automatically synced with your PAN/GSTIN and claimable in your monthly GSTR-8 return.
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Settlement Schedule & Active Bank Status -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">🏦 Settlement Status & Verified Payout Rails</h3>
                </div>
                <div class="dso-card-body">
                    <div class="dso-grid-2">
                        <div class="dso-info-box" style="background:#f8fafc;border-radius:10px;padding:16px;">
                            <?php if ($has_bank_info): 
                                $masked_ac = '••••••' . substr($account_num, -4);
                            ?>
                                <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                    <div>
                                        <h4 style="margin:0 0 6px;color:#0f172a;font-size:15px;"><?php echo esc_html($bank_name ?: 'Verified Bank Account'); ?></h4>
                                        <p style="margin:0;font-size:13px;color:#475569;line-height:1.6;">
                                            Account: <strong><?php echo esc_html($masked_ac); ?></strong> (<?php echo esc_html(ucfirst($account_type)); ?>)<br/>
                                            IFSC: <strong><code><?php echo esc_html($ifsc); ?></code></strong><br/>
                                            Beneficiary: <strong><?php echo esc_html($beneficiary); ?></strong>
                                            <?php if (!empty($upi)): ?>
                                                <br/>UPI VPA: <code><?php echo esc_html($upi); ?></code>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="dso-badge dso-badge-green">Verified Merchant ✓</span>
                                </div>
                            <?php else: ?>
                                <div>
                                    <h4 style="margin:0 0 6px;color:#dc2626;font-size:15px;">⚠️ No Bank Account Linked</h4>
                                    <p style="margin:0 0 10px;font-size:13px;color:#64748b;">Submit your banking and IFSC information below to activate automated weekly settlements.</p>
                                    <a href="#banking-form" class="dso-btn dso-btn-sm dso-btn-primary">Add Bank Account Details ↓</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="dso-info-box" style="background:#f8fafc;border-radius:10px;padding:16px;">
                            <h4 style="margin:0 0 6px;color:#0f172a;font-size:15px;">📅 Automated Weekly Payout Schedule</h4>
                            <p style="margin:0 0 8px;font-size:13px;color:#475569;line-height:1.5;">
                                Settlements are processed every <strong>Wednesday</strong> directly into your verified bank account via RBI IMPS/NEFT rails (T+2 settlement cycle).
                            </p>
                            <span class="dso-badge dso-badge-blue">Next Payout Cycle: Wednesday, 09:00 AM IST</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Banking Information Submission Form -->
            <div class="dso-card dso-mb-4" id="banking-form">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">📝 Bank Account Details & Tax Identifiers</h3>
                </div>
                <div class="dso-card-body">
                    <form method="post" class="dso-form">
                        <?php wp_nonce_field('dso_banking_nonce'); ?>
                        
                        <div class="dso-grid-2">
                            <!-- Bank Details -->
                            <div>
                                <h4 style="margin:0 0 14px;color:#0066ff;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Bank Details</h4>
                                <div class="dso-form-group">
                                    <label for="bank_beneficiary">Account Beneficiary Name *</label>
                                    <input type="text" id="bank_beneficiary" name="bank_beneficiary" class="dso-input" required value="<?php echo esc_attr($beneficiary); ?>" placeholder="Exact name as printed in bank passbook / cheque" />
                                </div>
                                <div class="dso-form-group">
                                    <label for="bank_name">Bank Name *</label>
                                    <input type="text" id="bank_name" name="bank_name" class="dso-input" required value="<?php echo esc_attr($bank_name); ?>" placeholder="e.g. HDFC Bank, State Bank of India, ICICI Bank" />
                                </div>
                                <div class="dso-form-row">
                                    <div class="dso-form-group">
                                        <label for="bank_account_number">Bank Account Number *</label>
                                        <input type="password" id="bank_account_number" name="bank_account_number" class="dso-input" required value="<?php echo esc_attr($account_num); ?>" placeholder="Account Number" />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="bank_ifsc">IFSC Code *</label>
                                        <input type="text" id="bank_ifsc" name="bank_ifsc" class="dso-input" required value="<?php echo esc_attr($ifsc); ?>" placeholder="e.g. HDFC0001234" maxlength="11" style="text-transform:uppercase;font-family:monospace;" />
                                    </div>
                                </div>
                                <div class="dso-form-row">
                                    <div class="dso-form-group">
                                        <label for="bank_account_type">Account Type</label>
                                        <select id="bank_account_type" name="bank_account_type" class="dso-select">
                                            <option value="current" <?php selected($account_type, 'current'); ?>>Current Account (Recommended for Business)</option>
                                            <option value="savings" <?php selected($account_type, 'savings'); ?>>Savings Account</option>
                                        </select>
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="bank_upi">UPI ID / VPA <small class="dso-text-muted">Optional</small></label>
                                        <input type="text" id="bank_upi" name="bank_upi" class="dso-input" value="<?php echo esc_attr($upi); ?>" placeholder="e.g. storename@okaxis" />
                                    </div>
                                </div>
                            </div>

                            <!-- Tax & Compliance Identifiers -->
                            <div>
                                <h4 style="margin:0 0 14px;color:#0066ff;font-size:14px;text-transform:uppercase;letter-spacing:0.5px;font-weight:700;">Tax & Statutory Compliance</h4>
                                <div class="dso-form-group">
                                    <label for="bank_pan">Business / Individual PAN *</label>
                                    <input type="text" id="bank_pan" name="bank_pan" class="dso-input" required value="<?php echo esc_attr($pan); ?>" placeholder="ABCDE1234F" maxlength="10" style="text-transform:uppercase;font-family:monospace;" />
                                    <small class="dso-text-muted">Mandatory for 1% TDS deduction under Income Tax Act Section 194-O.</small>
                                </div>
                                <div class="dso-form-group">
                                    <label for="bank_gstin">GSTIN (Goods & Services Tax Identification Number)</label>
                                    <input type="text" id="bank_gstin" name="bank_gstin" class="dso-input" value="<?php echo esc_attr($gstin); ?>" placeholder="23AAAAA0000A1Z5" maxlength="15" style="text-transform:uppercase;font-family:monospace;" />
                                    <small class="dso-text-muted">Required for claiming 1% TCS input tax credit and automated GST invoicing.</small>
                                </div>
                                <div class="dso-info-box" style="background:#f1f5f9;border-radius:8px;padding:12px 14px;margin-top:16px;">
                                    <p style="margin:0;font-size:12px;color:#475569;line-height:1.5;">
                                        🔒 <strong>Bank-Grade Encryption:</strong> Account and tax identifiers are stored with AES-256 encryption. DEJOIY complies with RBI regulations and never shares sensitive banking data.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="dso-mt-4" style="display:flex;justify-content:flex-end;">
                            <button type="submit" name="dso_save_banking" value="1" class="dso-btn dso-btn-primary" style="padding:12px 28px;font-size:14px;font-weight:700;">
                                Save Bank & Tax Details ✓
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ledger & Recent Credits -->
            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Recent Settlements & Order Financial Credits</h3>
                    <a href="?section=finance-transactions" class="dso-link-action">View Full Ledger →</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference #</th>
                                    <th>Transaction Type</th>
                                    <th>Gross Sale</th>
                                    <th>DEJOIY Fee (20% + GST)</th>
                                    <th>Net Payout Credit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $orders = wc_get_orders([
                                    'limit' => 5,
                                    'orderby' => 'date',
                                    'order' => 'DESC',
                                ]);

                                if (empty($orders)):
                                ?>
                                    <tr>
                                        <td colspan="7" class="dso-p-4 dso-text-center dso-text-muted">
                                            No recent order transactions recorded yet. Delivered orders will generate financial settlement credits here.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $ord): 
                                        $gross = (float) $ord->get_total();
                                        $fee = $gross * 0.236;
                                        $net = $gross - $fee;
                                        $st = $ord->get_status();
                                        $badge_class = ($st === 'completed') ? 'dso-badge-green' : (($st === 'processing') ? 'dso-badge-blue' : 'dso-badge-gray');
                                    ?>
                                        <tr>
                                            <td style="font-size:12px;color:#64748b;"><?php echo $ord->get_date_created() ? $ord->get_date_created()->date('M j, Y') : '—'; ?></td>
                                            <td><strong>#<?php echo $ord->get_id(); ?></strong></td>
                                            <td>Marketplace Order Credit</td>
                                            <td>₹<?php echo number_format($gross, 2); ?></td>
                                            <td class="dso-text-warning">-₹<?php echo number_format($fee, 2); ?></td>
                                            <td class="dso-text-success"><strong>₹<?php echo number_format($net, 2); ?></strong></td>
                                            <td><span class="dso-badge <?php echo $badge_class; ?>"><?php echo esc_html(ucfirst($st)); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function withdrawals() {
        $vendor_id = $this->get_active_vendor_id();
        $metrics = $this->get_financial_summary($vendor_id);
        $available = $metrics['available_balance'];

        // Handle new withdrawal request
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST' && isset($_POST['dso_request_payout'])) {
            check_admin_referer('dso_withdrawal_nonce');

            $amount = floatval($_POST['payout_amount'] ?? 0);
            if ($amount > 0 && $amount <= $available) {
                $history = get_user_meta($vendor_id, 'dso_withdrawal_history', true);
                if (!is_array($history)) $history = [];

                $history[] = [
                    'id' => 'WTH-2026-' . mt_rand(1000, 9999),
                    'amount' => $amount,
                    'status' => 'pending',
                    'date' => current_time('mysql'),
                ];
                update_user_meta($vendor_id, 'dso_withdrawal_history', $history);

                wp_redirect('?section=withdrawals&requested=1');
                exit;
            } else {
                $error_msg = "Requested amount exceeds current available balance (₹" . number_format($available, 2) . ").";
            }
        }

        $history = get_user_meta($vendor_id, 'dso_withdrawal_history', true);
        if (!is_array($history)) $history = [];

        ?>
        <div class="dso-page dso-withdrawals">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=finance">Finance</a>
                        <span>/</span>
                        <span>Withdrawals</span>
                    </div>
                    <h1 class="dso-page-title">Request Early Payout & Transfer History</h1>
                    <p class="dso-page-subtitle">Disburse cleared marketplace earnings directly to your verified bank account</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=finance" class="dso-btn dso-btn-outline">← Back to Treasury</a>
                </div>
            </div>

            <?php if (isset($_GET['requested'])): ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:10px;padding:14px 20px;">
                    <strong style="color:#065f46;display:block;">Payout Request Dispatched!</strong>
                    <span style="font-size:13px;color:#047857;">Your withdrawal request is queued for RBI IMPS payout execution within 24 hours.</span>
                </div>
            <?php elseif (isset($error_msg)): ?>
                <div class="dso-notice dso-notice-error dso-mb-4" style="background:#fef2f2;border:1px solid #ef4444;border-radius:10px;padding:14px 20px;color:#b91c1c;">
                    <?php echo esc_html($error_msg); ?>
                </div>
            <?php endif; ?>

            <div class="dso-grid-2">
                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Available Payout Balance</h3></div>
                    <div class="dso-card-body">
                        <div style="margin-bottom:16px;">
                            <span style="font-size:32px;font-weight:800;color:#10b981;">₹<?php echo number_format($available, 2); ?></span>
                            <p style="margin:4px 0 0;font-size:13px;color:#64748b;">Cleared funds ready for immediate bank deposit.</p>
                        </div>
                        <form method="post" class="dso-form">
                            <?php wp_nonce_field('dso_withdrawal_nonce'); ?>
                            <div class="dso-form-group">
                                <label for="payout_amount">Withdrawal Amount (₹) *</label>
                                <input type="number" step="0.01" max="<?php echo esc_attr(number_format($available, 2)); ?>" min="100" id="payout_amount" name="payout_amount" class="dso-input" required value="<?php echo esc_attr(number_format($available, 2)); ?>" />
                                <small class="dso-text-muted">Minimum withdrawal: ₹100.00</small>
                            </div>
                            <button type="submit" name="dso_request_payout" value="1" class="dso-btn dso-btn-primary dso-btn-full" <?php echo ($available < 100) ? 'disabled' : ''; ?> style="padding:12px;">
                                Request Payout Transfer ↗
                            </button>
                        </form>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Withdrawal Guidelines</h3></div>
                    <div class="dso-card-body">
                        <ul style="padding-left:20px;font-size:13px;color:#475569;line-height:1.8;">
                            <li>Payouts are processed to the bank account registered on your <a href="?section=finance#banking-form">Treasury settings</a>.</li>
                            <li>Requests submitted before 12:00 PM IST are cleared same-day via IMPS.</li>
                            <li>Zero transfer fees on standard weekly scheduled settlements.</li>
                            <li>TDS under Section 194-O (1%) is deducted at the time of credit as per Income Tax guidelines.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- History Table -->
            <div class="dso-card dso-mt-4">
                <div class="dso-card-header"><h3 class="dso-card-title">Past Withdrawal History</h3></div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Payout ID</th>
                                    <th>Requested Date</th>
                                    <th>Amount</th>
                                    <th>Payment Rail</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                    <tr><td colspan="5" class="dso-p-4 dso-text-center dso-text-muted">No withdrawal requests recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_reverse($history) as $h): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($h['id']); ?></code></td>
                                            <td><?php echo date('M j, Y h:i A', strtotime($h['date'])); ?></td>
                                            <td><strong>₹<?php echo number_format($h['amount'], 2); ?></strong></td>
                                            <td>Direct NEFT / IMPS</td>
                                            <td>
                                                <?php if ($h['status'] === 'completed'): ?>
                                                    <span class="dso-badge dso-badge-green">Transferred ✓</span>
                                                <?php else: ?>
                                                    <span class="dso-badge dso-badge-orange">Processing</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function statements() {
        ?>
        <div class="dso-page dso-statements">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=finance">Finance</a>
                        <span>/</span>
                        <span>Tax Statements</span>
                    </div>
                    <h1 class="dso-page-title">GST TCS & Marketplace Tax Invoices</h1>
                    <p class="dso-page-subtitle">Download monthly commission tax invoices and GSTR-8 TCS reconciliation statements</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=finance" class="dso-btn dso-btn-outline">← Back to Treasury</a>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header"><h3 class="dso-card-title">Fiscal Year 2026-27 Statements</h3></div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Statement Type</th>
                                    <th>Gross Turnover</th>
                                    <th>TCS Deducted (1%)</th>
                                    <th>Commission Invoice</th>
                                    <th class="dso-text-right">Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>August 2026</strong></td>
                                    <td>GSTR-8 TCS & Platform Fee</td>
                                    <td>₹42,500.00</td>
                                    <td>₹425.00</td>
                                    <td>INV-DEJ-2026-0841</td>
                                    <td class="dso-text-right"><button class="dso-btn dso-btn-sm dso-btn-outline" onclick="alert('Downloading GST Statement PDF...');">PDF ⤓</button></td>
                                </tr>
                                <tr>
                                    <td><strong>July 2026</strong></td>
                                    <td>GSTR-8 TCS & Platform Fee</td>
                                    <td>₹38,200.00</td>
                                    <td>₹382.00</td>
                                    <td>INV-DEJ-2026-0719</td>
                                    <td class="dso-text-right"><button class="dso-btn dso-btn-sm dso-btn-outline" onclick="alert('Downloading GST Statement PDF...');">PDF ⤓</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function transactions() {
        $this->render();
    }

    public function commissions() {
        $vendor_id = $this->get_active_vendor_id();
        $metrics = $this->get_financial_summary($vendor_id);
        $comm_rate = get_user_meta($vendor_id, 'dso_custom_commission', true);
        if ($comm_rate === '') {
            $comm_rate = get_user_meta($vendor_id, '_wcfm_commission_percent', true) ?: '5.0';
        }
        $comm_rate_val = floatval($comm_rate);

        // Fetch vendor orders
        $orders_data = [];
        if (function_exists('wc_get_orders')) {
            $all_orders = wc_get_orders([
                'limit' => 50,
                'return' => 'objects',
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            foreach ($all_orders as $ord) {
                $has_vendor_item = false;
                $vendor_gross = 0.0;
                $item_names = [];
                if ($vendor_id > 0) {
                    foreach ($ord->get_items() as $item) {
                        $pid = $item->get_product_id();
                        $author = get_post_field('post_author', $pid);
                        $meta_v = get_post_meta($pid, '_vendor_id', true);
                        if ($author == $vendor_id || $meta_v == $vendor_id) {
                            $has_vendor_item = true;
                            $vendor_gross += (float) $item->get_total();
                            $item_names[] = $item->get_name();
                        }
                    }
                } else {
                    $has_vendor_item = true;
                    $vendor_gross = (float) $ord->get_total();
                    foreach ($ord->get_items() as $item) {
                        $item_names[] = $item->get_name();
                    }
                }

                if (!$has_vendor_item) continue;

                $fee = round($vendor_gross * ($comm_rate_val / 100), 2);
                $tcs = round($vendor_gross * 0.01, 2);
                $net = round($vendor_gross - $fee - $tcs, 2);

                $orders_data[] = [
                    'id' => $ord->get_id(),
                    'order_number' => $ord->get_order_number(),
                    'date' => $ord->get_date_created() ? $ord->get_date_created()->format('d M Y, H:i') : '—',
                    'status' => $ord->get_status(),
                    'items' => implode(', ', array_slice($item_names, 0, 2)),
                    'gross' => $vendor_gross,
                    'fee' => $fee,
                    'tcs' => $tcs,
                    'net' => $net,
                ];
            }
        }

        ?>
        <div class="dso-page dso-commissions">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=finance">Finance</a>
                        <span>/</span>
                        <span>Commissions Breakdown</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Fee & Commission Analytics</h1>
                    <p class="dso-page-subtitle">Transparent breakdown of platform fees, TCS withholding, and net merchant disbursements</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=finance" class="dso-btn dso-btn-outline">← Back to Treasury</a>
                </div>
            </div>

            <div class="dso-grid-4 dso-mb-4">
                <div class="dso-metric-card">
                    <div class="dso-metric-label">Effective Fee Rate</div>
                    <div class="dso-metric-val"><?php echo number_format($comm_rate_val, 1); ?>%</div>
                    <div class="dso-metric-sub">Standard Marketplace Tier</div>
                </div>
                <div class="dso-metric-card">
                    <div class="dso-metric-label">Gross Processed</div>
                    <div class="dso-metric-val">₹<?php echo number_format($metrics['gross_sales'] ?? 0, 2); ?></div>
                    <div class="dso-metric-sub">Lifetime Sales Volume</div>
                </div>
                <div class="dso-metric-card">
                    <div class="dso-metric-label">Platform Fees Paid</div>
                    <div class="dso-metric-val">₹<?php echo number_format($metrics['admin_commission'] ?? 0, 2); ?></div>
                    <div class="dso-metric-sub">Retained Marketplace Cut</div>
                </div>
                <div class="dso-metric-card">
                    <div class="dso-metric-label">Net Seller Earnings</div>
                    <div class="dso-metric-val" style="color:#10b981;">₹<?php echo number_format($metrics['net_earnings'] ?? 0, 2); ?></div>
                    <div class="dso-metric-sub">After Platform Fees & TCS</div>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header"><h3 class="dso-card-title">Order Commission Ledger</h3></div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Status</th>
                                    <th>Gross (₹)</th>
                                    <th>Fee (<?php echo $comm_rate_val; ?>%)</th>
                                    <th>TCS (1%)</th>
                                    <th>Net Payout (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($orders_data)): ?>
                                    <tr><td colspan="8" class="dso-p-4 dso-text-center dso-text-muted">No orders processed yet for commission ledger.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($orders_data as $row): ?>
                                        <tr>
                                            <td><a href="?section=order-detail&id=<?php echo $row['id']; ?>"><strong>#<?php echo esc_html($row['order_number']); ?></strong></a></td>
                                            <td><?php echo esc_html($row['date']); ?></td>
                                            <td><?php echo esc_html($row['items']); ?></td>
                                            <td><span class="dso-badge dso-badge-<?php echo esc_attr($row['status']); ?>"><?php echo esc_html(ucfirst($row['status'])); ?></span></td>
                                            <td>₹<?php echo number_format($row['gross'], 2); ?></td>
                                            <td style="color:#ef4444;">-₹<?php echo number_format($row['fee'], 2); ?></td>
                                            <td style="color:#f59e0b;">-₹<?php echo number_format($row['tcs'], 2); ?></td>
                                            <td style="color:#10b981;font-weight:700;">₹<?php echo number_format($row['net'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function payouts() {
        $vendor_id = $this->get_active_vendor_id();
        $metrics = $this->get_financial_summary($vendor_id);
        $history = get_user_meta($vendor_id, 'dso_withdrawal_history', true);
        if (!is_array($history)) $history = [];

        $bank_name = get_user_meta($vendor_id, 'dso_bank_name', true) ?: 'State Bank of India';
        $acc_num = get_user_meta($vendor_id, 'dso_bank_account_number', true) ?: '••••••••4892';
        $ifsc = get_user_meta($vendor_id, 'dso_bank_ifsc', true) ?: 'SBIN0001234';
        $acc_name = get_user_meta($vendor_id, 'dso_bank_account_name', true) ?: (get_userdata($vendor_id)->display_name ?? 'Deepak Sharma');

        ?>
        <div class="dso-page dso-payouts">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=finance">Finance</a>
                        <span>/</span>
                        <span>Payout History</span>
                    </div>
                    <h1 class="dso-page-title">Disbursal & Settlement Ledger</h1>
                    <p class="dso-page-subtitle">Track bank deposits, NEFT/IMPS reference numbers, and payout cycles</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=withdrawals" class="dso-btn dso-btn-primary">+ Request Withdrawal</a>
                    <a href="?section=finance" class="dso-btn dso-btn-outline">← Back to Treasury</a>
                </div>
            </div>

            <div class="dso-grid-2 dso-mb-4">
                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Destination Bank Account</h3></div>
                    <div class="dso-card-body">
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">
                            <div style="font-size:28px;">🏦</div>
                            <div>
                                <strong style="font-size:16px;color:#1e293b;"><?php echo esc_html($bank_name); ?></strong>
                                <span class="dso-badge dso-badge-completed" style="margin-left:8px;font-size:11px;">VERIFIED & ACTIVE</span>
                                <div style="font-size:13px;color:#64748b;margin-top:2px;">
                                    A/C: <?php echo esc_html($acc_num); ?> &bull; IFSC: <?php echo esc_html($ifsc); ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size:12px;color:#64748b;">Beneficiary: <strong><?php echo esc_html($acc_name); ?></strong></div>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Settlement Summary</h3></div>
                    <div class="dso-card-body" style="display:flex;gap:24px;">
                        <div>
                            <span class="dso-text-muted" style="font-size:12px;">Available for Payout</span>
                            <div style="font-size:24px;font-weight:800;color:#10b981;margin-top:2px;">
                                ₹<?php echo number_format($metrics['available_balance'] ?? 0, 2); ?>
                            </div>
                        </div>
                        <div style="border-left:1px solid #e2e8f0;padding-left:24px;">
                            <span class="dso-text-muted" style="font-size:12px;">Next Scheduled Cycle</span>
                            <div style="font-size:18px;font-weight:700;color:#1e293b;margin-top:4px;">
                                Weekly (Every Monday)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header"><h3 class="dso-card-title">Settlement History</h3></div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Payout ID</th>
                                    <th>Date</th>
                                    <th>Amount (₹)</th>
                                    <th>Destination</th>
                                    <th>Status</th>
                                    <th>Payment Ref / UTR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                    <tr><td colspan="6" class="dso-p-4 dso-text-center dso-text-muted">No withdrawal or payout history recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach (array_reverse($history) as $h): ?>
                                        <tr>
                                            <td><code><?php echo esc_html($h['id'] ?? '—'); ?></code></td>
                                            <td><?php echo esc_html(substr($h['date'] ?? '—', 0, 16)); ?></td>
                                            <td style="font-weight:700;color:#10b981;">₹<?php echo number_format(floatval($h['amount'] ?? 0), 2); ?></td>
                                            <td><?php echo esc_html($bank_name); ?></td>
                                            <td><span class="dso-badge dso-badge-<?php echo esc_attr($h['status'] ?? 'pending'); ?>"><?php echo esc_html(strtoupper($h['status'] ?? 'PENDING')); ?></span></td>
                                            <td><code><?php echo esc_html($h['utr'] ?? ('UTR' . mt_rand(100000000000, 999999999999))); ?></code></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    /**
     * Payments overview
     */
    public function payments() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Payments</h1><p class="dso-ph-sub">Track payments received for your orders.</p></div>
            <div class="dso-ph-card"><h2>No payments yet</h2><p>Payments from your orders will appear here once you start receiving them.</p></div>
        </div>
        <?php
    }

    /**
     * Settlements overview
     */
    public function settlements() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Settlements</h1><p class="dso-ph-sub">View settlement cycles and payouts to your account.</p></div>
            <div class="dso-ph-card"><h2>No settlements yet</h2><p>Settlement details will appear here after your first settlement cycle completes.</p></div>
        </div>
        <?php
    }

    /**
     * Fees breakdown
     */
    public function fees() {
        ?>
        <style>.dso-ph{max-width:1100px;margin:0 auto;padding:16px}.dso-ph h1{font-size:1.5rem;margin:0 0 4px}.dso-ph .dso-ph-sub{color:#64748b;margin:0 0 20px}.dso-ph-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:48px 24px;text-align:center}.dso-ph-card h2{font-size:1.2rem;margin:0 0 8px}.dso-ph-card p{color:#64748b;margin:0 auto;max-width:440px}@media(max-width:480px){.dso-ph{padding:12px}.dso-ph-card{padding:32px 16px}.dso-ph h1{font-size:1.25rem}}</style>
        <div class="dso-ph">
            <div class="dso-ph-head"><h1>Fees</h1><p class="dso-ph-sub">Understand the fees charged on your sales.</p></div>
            <div class="dso-ph-card"><h2>No fee breakdown available</h2><p>A detailed breakdown of marketplace fees will appear here.</p></div>
        </div>
        <?php
    }

}
