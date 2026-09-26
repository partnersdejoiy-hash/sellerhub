<?php
/**
 * DSO Support - Enterprise Resolution Support Desk with Case Numbers & Threaded Conversations
 */
if (!defined('ABSPATH')) exit;

class DSO_Support {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();
        global $wpdb;

        // Check if viewing a specific case thread
        $case_number = isset($_GET['case']) ? sanitize_text_field($_GET['case']) : '';
        if (!empty($case_number)) {
            $this->render_case_thread($case_number, $vendor_id);
            return;
        }

        // Handle new ticket submission
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST' && isset($_POST['dso_submit_ticket'])) {
            check_admin_referer('dso_support_nonce');

            $subject = sanitize_text_field($_POST['ticket_subject'] ?? '');
            $category = sanitize_text_field($_POST['ticket_category'] ?? 'general');
            $priority = sanitize_text_field($_POST['ticket_priority'] ?? 'normal');
            $reference_id = sanitize_text_field($_POST['ticket_reference_id'] ?? '');
            $message = wp_kses_post($_POST['ticket_message'] ?? '');

            if (!empty($subject) && !empty($message)) {
                // Generate unique Case Number
                $case_num = 'CASE-2026-' . strtoupper(wp_generate_password(6, false, false));

                $wpdb->insert($wpdb->prefix . 'dso_support_tickets', [
                    'case_number' => $case_num,
                    'vendor_id' => $vendor_id,
                    'subject' => $subject,
                    'category' => $category,
                    'reference_id' => $reference_id,
                    'message' => $message,
                    'status' => 'open',
                    'priority' => $priority,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);

                $ticket_id = $wpdb->insert_id;

                // Insert initial message into thread
                $user = get_userdata(get_current_user_id());
                $sender_name = $user ? $user->display_name : 'Seller';

                $wpdb->insert($wpdb->prefix . 'dso_support_messages', [
                    'ticket_id' => $ticket_id,
                    'sender_type' => 'seller',
                    'sender_name' => $sender_name,
                    'message' => $message,
                    'created_at' => current_time('mysql'),
                ]);

                wp_redirect('?section=support&case=' . urlencode($case_num) . '&created=1');
                exit;
            }
        }

        $tickets = $this->get_tickets($vendor_id);
        $open_count = 0;
        $resolved_count = 0;
        foreach ($tickets as $t) {
            if ($t['status'] === 'resolved' || $t['status'] === 'closed') {
                $resolved_count++;
            } else {
                $open_count++;
            }
        }

        ?>
        <div class="dso-page dso-support">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Support Desk</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY Partner Resolution Desk</h1>
                    <p class="dso-page-subtitle">Priority seller support, case tracking, DPIN cataloging assistance, and dispute resolutions.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="#new-case" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Open New Case
                    </a>
                </div>
            </div>

            <!-- Support Metrics Banner -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Active Support Cases</span>
                    <span class="dso-stat-val <?php echo $open_count > 0 ? 'dso-text-warning' : ''; ?>"><?php echo $open_count; ?></span>
                    <span class="dso-stat-sub">Under review by DEJOIY Partner Specialists</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Resolved Cases</span>
                    <span class="dso-stat-val dso-text-success"><?php echo $resolved_count; ?></span>
                    <span class="dso-stat-sub">Lifetime successfully resolved</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Average Response SLA</span>
                    <span class="dso-stat-val dso-text-success">&lt; 2 Hours</span>
                    <span class="dso-stat-sub">Dedicated Platinum Partner SLA</span>
                </div>
            </div>

            <div class="dso-grid-2">
                <!-- Submit Ticket Form -->
                <div class="dso-card" id="new-case">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Open a New Resolution Case</h3>
                    </div>
                    <div class="dso-card-body">
                        <form method="post" class="dso-form">
                            <?php wp_nonce_field('dso_support_nonce'); ?>
                            <div class="dso-form-group">
                                <label for="ticket_subject">Subject / Issue Summary *</label>
                                <input type="text" id="ticket_subject" name="ticket_subject" class="dso-input" required placeholder="e.g. DPIN Catalog Verification Delay or Payout Discrepancy" />
                            </div>
                            <div class="dso-form-row">
                                <div class="dso-form-group">
                                    <label for="ticket_category">Category *</label>
                                    <select id="ticket_category" name="ticket_category" class="dso-select">
                                        <option value="catalog_dpin">DPIN & Product Cataloging</option>
                                        <option value="orders_fulfillment">Orders & Logistics / Pickup</option>
                                        <option value="payments_finance">Payouts & Bank Settlement</option>
                                        <option value="returns_disputes">Buyer Returns & Claims</option>
                                        <option value="account_kyc">Account Health & KYC</option>
                                        <option value="advertising">DEJOIY Ads & Campaigns</option>
                                        <option value="technical">Technical / API Integration</option>
                                    </select>
                                </div>
                                <div class="dso-form-group">
                                    <label for="ticket_priority">Priority Level</label>
                                    <select id="ticket_priority" name="ticket_priority" class="dso-select">
                                        <option value="normal">Standard (Within 6 hrs)</option>
                                        <option value="high">High Priority (Within 2 hrs)</option>
                                        <option value="urgent">Critical / Urgent Dispatch Block</option>
                                    </select>
                                </div>
                            </div>
                            <div class="dso-form-group">
                                <label for="ticket_reference_id">Related Reference (DPIN, Order #, or Payout ID) <small class="dso-text-muted">Optional</small></label>
                                <input type="text" id="ticket_reference_id" name="ticket_reference_id" class="dso-input" placeholder="e.g. DPIN DEZCZEZDQ3L or Order #5421" />
                            </div>
                            <div class="dso-form-group">
                                <label for="ticket_message">Detailed Description *</label>
                                <textarea id="ticket_message" name="ticket_message" class="dso-textarea" rows="6" required placeholder="Provide full context, affected product DPINs, order numbers, or screenshots..."></textarea>
                            </div>
                            <button type="submit" name="dso_submit_ticket" value="1" class="dso-btn dso-btn-primary dso-btn-full" style="padding:12px 18px;font-size:14px;font-weight:600;">
                                Submit Case & Generate Case ID ↗
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Support SLAs & FAQ Shortcuts -->
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Resolution Policies & Direct Contacts</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-info-box dso-mb-4" style="background:#f8fafc;border-left:4px solid #0066ff;padding:16px;border-radius:8px;">
                            <h4 style="margin:0 0 6px;color:#0f172a;font-size:14px;">⏱️ Seller Support SLAs</h4>
                            <p style="margin:0;font-size:13px;color:#475569;line-height:1.5;">Every case submitted is assigned a unique tracking case number (e.g. <code>CASE-2026-XXXXX</code>). Our seller operations team is active 24×7 for high-priority logistics and payout inquiries.</p>
                        </div>
                        <div class="dso-faq-list">
                            <div class="dso-faq-item dso-mb-3" style="padding-bottom:12px;border-bottom:1px solid #f1f5f9;">
                                <h4 style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">How do I track my submitted case?</h4>
                                <p style="font-size:12px;color:#64748b;margin:0;">All past cases with full message transcripts are logged in the table below. Click "View Thread" to review replies or send additional documentation.</p>
                            </div>
                            <div class="dso-faq-item dso-mb-3" style="padding-bottom:12px;border-bottom:1px solid #f1f5f9;">
                                <h4 style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">What if my courier pickup is delayed?</h4>
                                <p style="font-size:12px;color:#64748b;margin:0;">Select <em>Orders & Logistics</em> category with <em>High Priority</em> and include your Order Number. We will automatically escalate to Delhivery/Bluedart dispatch controllers.</p>
                            </div>
                            <div class="dso-faq-item" style="padding-bottom:12px;">
                                <h4 style="font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">Direct DEJOIY Merchant Escalation</h4>
                                <p style="font-size:12px;color:#64748b;margin:0;">Emergency Seller Hotline: <strong>1800-DEJOIY-HUB</strong> (Mon-Sat 9AM-8PM IST)<br/>Email: <strong>partners@dejoiy.com</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Past Tickets Table -->
            <div class="dso-card dso-mt-4">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Resolution History & Active Cases</h3>
                    <span class="dso-badge dso-badge-blue"><?php echo count($tickets); ?> Total Cases</span>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Case ID</th>
                                    <th>Subject & Reference</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th class="dso-text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tickets)): ?>
                                    <tr class="dso-empty-row">
                                        <td colspan="7" class="dso-p-4 dso-text-center">
                                            <div style="padding:24px;color:#64748b;">
                                                <svg viewBox="0 0 24 24" width="36" height="36" fill="none" stroke="#94a3b8" stroke-width="1.5" style="margin-bottom:8px;"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                                <p style="margin:0;font-size:14px;font-weight:500;">No support cases submitted yet.</p>
                                                <small>When you need help with orders, DPIN cataloging, or banking, submit a case above.</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($tickets as $t): ?>
                                        <tr>
                                            <td>
                                                <a href="?section=support&case=<?php echo urlencode($t['case_number']); ?>" style="font-weight:700;font-family:monospace;color:#0066ff;">
                                                    <?php echo esc_html($t['case_number']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <strong style="color:#0f172a;"><?php echo esc_html($t['subject']); ?></strong>
                                                <?php if (!empty($t['reference_id'])): ?>
                                                    <div style="font-size:11px;color:#64748b;font-family:monospace;margin-top:2px;">Ref: <?php echo esc_html($t['reference_id']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo esc_html($t['category_label']); ?></td>
                                            <td>
                                                <?php if ($t['priority'] === 'urgent'): ?>
                                                    <span class="dso-badge dso-badge-red">Urgent</span>
                                                <?php elseif ($t['priority'] === 'high'): ?>
                                                    <span class="dso-badge dso-badge-orange">High</span>
                                                <?php else: ?>
                                                    <span class="dso-badge dso-badge-gray">Normal</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $t['status_badge']; ?></td>
                                            <td style="font-size:12px;color:#64748b;"><?php echo esc_html($t['updated_date']); ?></td>
                                            <td class="dso-text-right">
                                                <a href="?section=support&case=<?php echo urlencode($t['case_number']); ?>" class="dso-btn dso-btn-sm dso-btn-outline">
                                                    View Thread ↗
                                                </a>
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

    /**
     * Render Threaded Case Detail View
     */
    protected function render_case_thread($case_number, $vendor_id) {
        global $wpdb;

        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dso_support_tickets WHERE case_number = %s AND vendor_id = %d",
            $case_number,
            $vendor_id
        ));

        if (!$ticket) {
            ?>
            <div class="dso-page">
                <div class="dso-card">
                    <div class="dso-card-body dso-text-center dso-p-5">
                        <h2>Case Not Found</h2>
                        <p class="dso-text-muted">The requested case number does not exist or does not belong to your store account.</p>
                        <a href="?section=support" class="dso-btn dso-btn-primary dso-mt-3">← Return to Support Desk</a>
                    </div>
                </div>
            </div>
            <?php
            return;
        }

        // Handle reply submission
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if ($method === 'POST' && isset($_POST['dso_case_reply'])) {
            check_admin_referer('dso_case_reply_nonce');
            $reply = wp_kses_post($_POST['reply_message'] ?? '');

            if (!empty($reply)) {
                $user = get_userdata(get_current_user_id());
                $sender_name = $user ? $user->display_name : 'Seller';

                $wpdb->insert($wpdb->prefix . 'dso_support_messages', [
                    'ticket_id' => $ticket->id,
                    'sender_type' => 'seller',
                    'sender_name' => $sender_name,
                    'message' => $reply,
                    'created_at' => current_time('mysql'),
                ]);

                // Update ticket updated_at and status
                $wpdb->update(
                    $wpdb->prefix . 'dso_support_tickets',
                    [
                        'updated_at' => current_time('mysql'),
                        'status' => 'open',
                    ],
                    ['id' => $ticket->id]
                );

                wp_redirect('?section=support&case=' . urlencode($case_number) . '&replied=1');
                exit;
            }
        }

        // Fetch all messages in this ticket thread
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dso_support_messages WHERE ticket_id = %d ORDER BY created_at ASC",
            $ticket->id
        ));

        // If no separate messages found (legacy ticket), show the main ticket message
        if (empty($messages) && !empty($ticket->message)) {
            $messages = [
                (object) [
                    'id' => 0,
                    'ticket_id' => $ticket->id,
                    'sender_type' => 'seller',
                    'sender_name' => 'Seller',
                    'message' => $ticket->message,
                    'created_at' => $ticket->created_at,
                ]
            ];
        }

        $status_badges = [
            'open' => '<span class="dso-badge dso-badge-green">Open • In Queue</span>',
            'in_progress' => '<span class="dso-badge dso-badge-blue">In Progress by Specialist</span>',
            'waiting_on_seller' => '<span class="dso-badge dso-badge-orange">Awaiting Your Input</span>',
            'resolved' => '<span class="dso-badge dso-badge-green">Resolved ✓</span>',
            'closed' => '<span class="dso-badge dso-badge-gray">Closed</span>',
        ];
        $badge = $status_badges[$ticket->status] ?? '<span class="dso-badge dso-badge-gray">' . esc_html(ucfirst($ticket->status)) . '</span>';

        ?>
        <div class="dso-page dso-case-detail">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=support">Support Desk</a>
                        <span>/</span>
                        <span><?php echo esc_html($ticket->case_number); ?></span>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;margin-top:4px;">
                        <h1 class="dso-page-title" style="margin:0;font-family:monospace;"><?php echo esc_html($ticket->case_number); ?></h1>
                        <?php echo $badge; ?>
                    </div>
                    <p class="dso-page-subtitle" style="font-size:16px;font-weight:600;color:#0f172a;margin-top:6px;">
                        <?php echo esc_html($ticket->subject); ?>
                    </p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=support" class="dso-btn dso-btn-outline">← Back to All Cases</a>
                </div>
            </div>

            <!-- Case Metadata Strip -->
            <div class="dso-card dso-mb-4" style="background:#f8fafc;">
                <div class="dso-card-body" style="padding:14px 20px;">
                    <div style="display:flex;flex-wrap:wrap;gap:24px;font-size:13px;color:#475569;">
                        <div><strong style="color:#0f172a;">Category:</strong> <?php echo esc_html(ucwords(str_replace('_', ' ', $ticket->category))); ?></div>
                        <?php if (!empty($ticket->reference_id)): ?>
                            <div><strong style="color:#0f172a;">Reference:</strong> <code><?php echo esc_html($ticket->reference_id); ?></code></div>
                        <?php endif; ?>
                        <div><strong style="color:#0f172a;">Priority:</strong> <?php echo esc_html(ucfirst($ticket->priority)); ?></div>
                        <div><strong style="color:#0f172a;">Opened:</strong> <?php echo date('M j, Y h:i A', strtotime($ticket->created_at)); ?></div>
                        <div><strong style="color:#0f172a;">Last Updated:</strong> <?php echo date('M j, Y h:i A', strtotime($ticket->updated_at)); ?></div>
                    </div>
                </div>
            </div>

            <!-- Conversation Thread -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Case Discussion Timeline</h3>
                </div>
                <div class="dso-card-body" style="display:flex;flex-direction:column;gap:18px;">
                    <?php foreach ($messages as $msg): 
                        $is_support = ($msg->sender_type === 'support');
                        $is_system = ($msg->sender_type === 'system');
                    ?>
                        <div class="dso-message-item" style="display:flex;gap:14px;align-items:flex-start;<?php echo $is_support ? 'background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;' : 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;'; ?>">
                            <div style="width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0;<?php echo $is_support ? 'background:#16a34a;color:#fff;' : 'background:#0066ff;color:#fff;'; ?>">
                                <?php echo $is_support ? 'DJ' : 'ME'; ?>
                            </div>
                            <div style="flex:1;">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <div>
                                        <strong style="font-size:14px;color:#0f172a;"><?php echo esc_html($msg->sender_name); ?></strong>
                                        <?php if ($is_support): ?>
                                            <span class="dso-badge dso-badge-green" style="font-size:11px;margin-left:6px;">DEJOIY Specialist ✓</span>
                                        <?php elseif ($is_system): ?>
                                            <span class="dso-badge dso-badge-gray" style="font-size:11px;margin-left:6px;">System Notification</span>
                                        <?php else: ?>
                                            <span class="dso-badge dso-badge-blue" style="font-size:11px;margin-left:6px;">Store Owner</span>
                                        <?php endif; ?>
                                    </div>
                                    <span style="font-size:12px;color:#94a3b8;"><?php echo date('M j, Y h:i A', strtotime($msg->created_at)); ?></span>
                                </div>
                                <div style="font-size:14px;color:#334155;line-height:1.6;white-space:pre-wrap;"><?php echo esc_html($msg->message); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Reply Box -->
            <?php if ($ticket->status !== 'closed'): ?>
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Send a Reply or Additional Details</h3>
                    </div>
                    <div class="dso-card-body">
                        <form method="post" class="dso-form">
                            <?php wp_nonce_field('dso_case_reply_nonce'); ?>
                            <div class="dso-form-group">
                                <label for="reply_message">Your Message *</label>
                                <textarea id="reply_message" name="reply_message" class="dso-textarea" rows="5" required placeholder="Type your reply to DEJOIY Partner Resolution team..."></textarea>
                            </div>
                            <div style="display:flex;justify-content:flex-end;">
                                <button type="submit" name="dso_case_reply" value="1" class="dso-btn dso-btn-primary" style="padding:10px 24px;">
                                    Send Reply ↗
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="dso-card" style="background:#f8fafc;text-align:center;padding:24px;">
                    <p style="margin:0;color:#64748b;">This case has been marked as closed. If you have a new issue, please open a new support case.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function get_tickets($vendor_id) {
        global $wpdb;
        if (!$vendor_id) return [];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dso_support_tickets
            WHERE vendor_id = %d ORDER BY updated_at DESC",
            $vendor_id
        ));

        $category_labels = [
            'catalog_dpin' => 'DPIN & Cataloging',
            'orders_fulfillment' => 'Orders & Logistics',
            'payments_finance' => 'Payouts & Banking',
            'returns_disputes' => 'Returns & Claims',
            'account_kyc' => 'KYC & Account Health',
            'advertising' => 'DEJOIY Ads',
            'technical' => 'Technical & API',
            'general' => 'General Inquiry',
        ];

        $status_map = [
            'open' => ['Open', 'dso-badge-green'],
            'in_progress' => ['In Progress', 'dso-badge-blue'],
            'waiting_on_seller' => ['Waiting Input', 'dso-badge-orange'],
            'resolved' => ['Resolved', 'dso-badge-green'],
            'closed' => ['Closed', 'dso-badge-gray'],
        ];

        $tickets = [];
        foreach ($rows as $row) {
            $s = $status_map[$row->status] ?? ['Open', 'dso-badge-green'];
            $case_num = !empty($row->case_number) ? $row->case_number : sprintf('CASE-2026-%05d', $row->id);

            $tickets[] = [
                'id' => $row->id,
                'case_number' => $case_num,
                'subject' => $row->subject,
                'category' => $row->category,
                'category_label' => $category_labels[$row->category] ?? ucfirst($row->category),
                'reference_id' => $row->reference_id ?? '',
                'priority' => $row->priority ?? 'normal',
                'status' => $row->status,
                'status_badge' => '<span class="dso-badge ' . $s[1] . '">' . $s[0] . '</span>',
                'created_date' => $row->created_at ? date('M j, Y', strtotime($row->created_at)) : '—',
                'updated_date' => $row->updated_at ? date('M j, Y h:i A', strtotime($row->updated_at)) : ($row->created_at ? date('M j, Y', strtotime($row->created_at)) : '—'),
            ];
        }
        return $tickets;
    }
}
