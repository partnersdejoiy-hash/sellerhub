<?php
/**
 * DEJOIY Seller OS - Joi: Disputes & Claims Protection.
 *
 * Mirrors the Support Cases module (DSO_Support): self-POST forms,
 * check_admin_referer() nonces and per-vendor scoping.
 */

if (!defined('ABSPATH')) {
    exit;
}

class DSO_Disputes {

    const CLAIM_TYPES = array(
        'lost_in_transit'      => 'Lost in transit',
        'damaged_in_transit'   => 'Damaged in transit',
        'wrong_damaged_return' => 'Wrong / damaged return',
        'carrier_issue'        => 'Carrier issue',
        'fee_dispute'          => 'Fee dispute',
    );

    const STATUSES = array(
        'open'         => 'Open',
        'under_review' => 'Under Review',
        'resolved'     => 'Resolved',
        'rejected'     => 'Rejected',
        'withdrawn'    => 'Withdrawn',
    );

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    protected function disputes_table() {
        global $wpdb;
        return $wpdb->prefix . 'dso_disputes';
    }

    protected function messages_table() {
        global $wpdb;
        return $wpdb->prefix . 'dso_dispute_messages';
    }

    protected function get_dispute($dispute_id, $vendor_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . $this->disputes_table() . ' WHERE id = %d AND vendor_id = %d',
            $dispute_id,
            $vendor_id
        ));
    }

    protected function status_pill($status) {
        $colors = array(
            'open'         => 'color:#b45309;background:#fef3c7',
            'under_review' => 'color:#1d4ed8;background:#dbeafe',
            'resolved'     => 'color:#047857;background:#d1fae5',
            'rejected'     => 'color:#b91c1c;background:#fee2e2',
            'withdrawn'    => 'color:#475569;background:#e2e8f0',
        );
        $label = isset(self::STATUSES[$status]) ? self::STATUSES[$status] : $status;
        $style = isset($colors[$status]) ? $colors[$status] : $colors['withdrawn'];
        return '<span class="dso-pill" style="' . esc_attr($style) . ';font-size:11px;font-weight:700;padding:3px 10px;border-radius:999px;white-space:nowrap;">' . esc_html($label) . '</span>';
    }
    public function render() {
        global $wpdb;
        $vendor_id = $this->get_active_vendor_id();
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';

        // ---- File a new claim -------------------------------------------
        if ($method === 'POST' && isset($_POST['dso_file_claim'])) {
            check_admin_referer('dso_disputes_nonce');
            $order_id   = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $claim_type = isset($_POST['claim_type']) ? sanitize_key($_POST['claim_type']) : '';
            $subject    = isset($_POST['subject']) ? sanitize_text_field(wp_unslash($_POST['subject'])) : '';
            $desc       = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
            $amount     = isset($_POST['amount']) ? round((float) $_POST['amount'], 2) : 0.0;

            if (!array_key_exists($claim_type, self::CLAIM_TYPES)) {
                wp_die(esc_html__('Please choose a valid claim type.', 'dejoiy-seller-os'));
            }
            if ($subject === '' || $desc === '') {
                wp_die(esc_html__('Subject and description are required.', 'dejoiy-seller-os'));
            }
            if ($amount < 0) {
                wp_die(esc_html__('Amount cannot be negative.', 'dejoiy-seller-os'));
            }

            $now = current_time('mysql');
            $ok = $wpdb->insert(
                $this->disputes_table(),
                array(
                    'vendor_id'   => $vendor_id,
                    'order_id'    => $order_id,
                    'direction'   => 'filed_by_me',
                    'claim_type'  => $claim_type,
                    'subject'     => $subject,
                    'description' => $desc,
                    'amount'      => $amount,
                    'status'      => 'open',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
            );
            if ($ok) {
                $new_id = (int) $wpdb->insert_id;
                $wpdb->insert(
                    $this->messages_table(),
                    array(
                        'dispute_id'  => $new_id,
                        'author_type' => 'system',
                        'message'     => 'Claim filed by seller. Status: Open.',
                        'created_at'  => $now,
                    ),
                    array('%d', '%s', '%s', '%s')
                );
                wp_redirect('?section=disputes&dispute_id=' . $new_id . '&created=1');
                exit;
            }
            wp_die(esc_html__('Could not file the claim. Please try again.', 'dejoiy-seller-os'));
        }
        // ---- Reply on a claim -------------------------------------------
        if ($method === 'POST' && isset($_POST['dso_dispute_reply'])) {
            check_admin_referer('dso_dispute_reply_nonce');
            $dispute_id = isset($_POST['dispute_id']) ? absint($_POST['dispute_id']) : 0;
            $message    = isset($_POST['reply_message']) ? wp_kses_post(wp_unslash($_POST['reply_message'])) : '';
            $dispute    = $this->get_dispute($dispute_id, $vendor_id);
            if (!$dispute || !in_array($dispute->status, array('open', 'under_review'), true) || $message === '') {
                wp_die(esc_html__('This reply cannot be posted.', 'dejoiy-seller-os'));
            }
            $now = current_time('mysql');
            $wpdb->insert(
                $this->messages_table(),
                array(
                    'dispute_id'  => $dispute_id,
                    'author_type' => 'seller',
                    'message'     => $message,
                    'created_at'  => $now,
                ),
                array('%d', '%s', '%s', '%s')
            );
            $wpdb->update(
                $this->disputes_table(),
                array('updated_at' => $now),
                array('id' => $dispute_id),
                array('%s'),
                array('%d')
            );
            wp_redirect('?section=disputes&dispute_id=' . $dispute_id . '&replied=1');
            exit;
        }

        // ---- Withdraw a claim --------------------------------------------
        if ($method === 'POST' && isset($_POST['dso_withdraw_claim'])) {
            check_admin_referer('dso_dispute_withdraw_nonce');
            $dispute_id = isset($_POST['dispute_id']) ? absint($_POST['dispute_id']) : 0;
            $dispute    = $this->get_dispute($dispute_id, $vendor_id);
            if (!$dispute || $dispute->direction !== 'filed_by_me' || $dispute->status !== 'open') {
                wp_die(esc_html__('Only your own open claims can be withdrawn.', 'dejoiy-seller-os'));
            }
            $now = current_time('mysql');
            $wpdb->update(
                $this->disputes_table(),
                array('status' => 'withdrawn', 'updated_at' => $now),
                array('id' => $dispute_id),
                array('%s', '%s'),
                array('%d')
            );
            $wpdb->insert(
                $this->messages_table(),
                array(
                    'dispute_id'  => $dispute_id,
                    'author_type' => 'system',
                    'message'     => 'Claim withdrawn by seller.',
                    'created_at'  => $now,
                ),
                array('%d', '%s', '%s', '%s')
            );
            wp_redirect('?section=disputes&dispute_id=' . $dispute_id . '&withdrawn=1');
            exit;
        }

        $view_id = isset($_GET['dispute_id']) ? absint($_GET['dispute_id']) : 0;
        if ($view_id) {
            $this->render_detail($view_id, $vendor_id);
            return;
        }
        $this->render_list($vendor_id);
    }
    protected function render_list($vendor_id) {
        global $wpdb;
        $t = $this->disputes_table();
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'all';
        if (!in_array($tab, array('all', 'mine', 'against'), true)) { $tab = 'all'; }
        $status_filter = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';
        if (!array_key_exists($status_filter, self::STATUSES)) { $status_filter = ''; }
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $vid = intval($vendor_id);
        $counts = array(
            'all'     => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $t . ' WHERE vendor_id = %d', $vid)),
            'mine'    => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $t . ' WHERE vendor_id = %d AND direction = %s', $vid, 'filed_by_me')),
            'against' => (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $t . ' WHERE vendor_id = %d AND direction = %s', $vid, 'against_me')),
        );
        $where = array($wpdb->prepare('vendor_id = %d', $vid));
        if ($tab === 'mine') { $where[] = $wpdb->prepare('direction = %s', 'filed_by_me'); }
        elseif ($tab === 'against') { $where[] = $wpdb->prepare('direction = %s', 'against_me'); }
        if ($status_filter !== '') { $where[] = $wpdb->prepare('status = %s', $status_filter); }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = $wpdb->prepare('(subject LIKE %s OR claim_type LIKE %s)', $like, $like);
        }
        $disputes = $wpdb->get_results('SELECT * FROM ' . $t . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC LIMIT 100');
        $orders = array();
        if (class_exists('DSO_Orders')) { $orders = (new DSO_Orders())->get_orders($vendor_id, 50); }
        $show_form = isset($_GET['new']) && $_GET['new'] === '1';
        echo '<div class="dso-page dso-disputes">';
        echo '<div class="dso-page-header"><div>';
        echo '<div class="dso-breadcrumb"><a href="?section=dashboard">Dashboard</a> / Disputes &amp; Claims</div>';
        echo '<h1 class="dso-page-title">Joi</h1>';
        echo '<p class="dso-page-sub">Disputes &amp; Claims Protection</p></div>';
        echo '<div class="dso-page-actions"><a class="dso-btn dso-btn-primary" href="?section=disputes&new=1">+ File a Claim</a></div></div>';
        $tabs = array('all' => 'All', 'mine' => 'My Claims', 'against' => 'Claims Against Me');
        echo '<div class="dso-tabs">';
        foreach ($tabs as $key => $label) {
            $active = ($tab === $key) ? ' dso-tab-active' : '';
            echo '<a class="dso-tab' . $active . '" href="?section=disputes&tab=' . $key . '">' . esc_html($label) . ' <span class="dso-tab-count">' . intval($counts[$key]) . '</span></a>';
        }
        echo '</div>';
        echo '<form method="get" class="dso-dispute-filters">';
        echo '<input type="hidden" name="section" value="disputes" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
        echo '<div class="dso-chips">';
        $chip_statuses = array('' => 'All statuses', 'open' => 'Open', 'under_review' => 'Under Review', 'resolved' => 'Resolved', 'rejected' => 'Rejected');
        foreach ($chip_statuses as $val => $label) {
            $active = ($status_filter === $val) ? ' dso-chip-active' : '';
            echo '<button type="submit" name="status" value="' . esc_attr($val) . '" class="dso-chip' . $active . '">' . esc_html($label) . '</button>';
        }
        echo '</div>';
        echo '<div class="dso-search"><input type="search" name="s" class="dso-input" placeholder="Search subject or type..." value="' . esc_attr($search) . '" />';
        echo '<button type="submit" class="dso-btn dso-btn-outline dso-btn-sm">Search</button></div>';
        echo '</form>';
        if ($show_form) { $this->render_claim_form($orders); }
        if (empty($disputes)) {
            echo '<div class="dso-empty">';
            echo '<h3>No claims here yet</h3>';
            if ($tab === 'against') {
                echo '<p>Nobody has raised a claim against your store. Joi protects your earnings - good going.</p>';
            } elseif ($tab === 'mine') {
                echo '<p>You have not filed any claims. If a shipment went missing or arrived damaged, file one and we will pick it up.</p>';
            } else {
                echo '<p>Nothing to show yet. Joi protects your earnings: claims you file, and any raised against your store, will appear here.</p>';
            }
            echo '<a class="dso-btn dso-btn-primary" href="?section=disputes&new=1">File a Claim</a></div>';
        } else {
            echo '<div class="dso-table-wrap"><table class="dso-table dso-dispute-table">';
            echo '<thead><tr><th>Claim ID</th><th>Order</th><th>Type</th><th>Subject</th><th>Amount</th><th>Status</th><th>Updated</th></tr></thead><tbody>';
            foreach ($disputes as $d) {
                $type_label = isset(self::CLAIM_TYPES[$d->claim_type]) ? self::CLAIM_TYPES[$d->claim_type] : $d->claim_type;
                echo '<tr>';
                echo '<td data-th="Claim ID"><a href="?section=disputes&dispute_id=' . intval($d->id) . '">#' . intval($d->id) . '</a>';
                if ($d->direction === 'against_me') { echo ' <span class="dso-mini-tag">Against me</span>'; }
                echo '</td>';
                echo '<td data-th="Order">' . ($d->order_id ? '#' . intval($d->order_id) : '-') . '</td>';
                echo '<td data-th="Type">' . esc_html($type_label) . '</td>';
                echo '<td data-th="Subject"><a href="?section=disputes&dispute_id=' . intval($d->id) . '">' . esc_html($d->subject) . '</a></td>';
                echo '<td data-th="Amount">' . esc_html('INR ' . number_format((float) $d->amount, 2)) . '</td>';
                echo '<td data-th="Status">' . $this->status_pill($d->status) . '</td>';
                echo '<td data-th="Updated">' . esc_html(mysql2date('d M Y', $d->updated_at)) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';
    }
    protected function render_claim_form($orders) {
        echo '<div class="dso-card dso-dispute-form-card" id="dso-file-claim">';
        echo '<h3>File a Claim</h3>';
        echo '<p class="dso-muted">Tell us what went wrong and we will review it. Most claims are resolved within 5-7 business days.</p>';
        echo '<form method="post" class="dso-form dso-dispute-form">';
        wp_nonce_field('dso_disputes_nonce');
        echo '<div class="dso-form-grid">';
        echo '<label>Related Order (optional)<select name="order_id" class="dso-input"><option value="0">- No specific order -</option>';
        foreach ($orders as $o) {
            $oid = isset($o['id']) ? intval($o['id']) : 0;
            $num = isset($o['number']) ? $o['number'] : $oid;
            echo '<option value="' . $oid . '">#' . esc_html($num) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Claim Type<select name="claim_type" class="dso-input" required>';
        foreach (self::CLAIM_TYPES as $val => $label) {
            echo '<option value="' . esc_attr($val) . '">' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label class="dso-span-2">Subject<input type="text" name="subject" class="dso-input" maxlength="255" required placeholder="e.g. Order #12345 lost in transit" /></label>';
        echo '<label class="dso-span-2">Description<textarea name="description" class="dso-textarea" rows="5" required placeholder="Describe what happened: dates, tracking IDs, amounts involved."></textarea></label>';
        echo '<label>Claim Amount (INR)<input type="number" name="amount" class="dso-input" min="0" step="0.01" value="0" /></label>';
        echo '</div>';
        echo '<div class="dso-form-actions"><button type="submit" name="dso_file_claim" value="1" class="dso-btn dso-btn-primary">Submit Claim</button> <a class="dso-btn dso-btn-outline" href="?section=disputes">Cancel</a></div>';
        echo '</form></div>';
    }
    protected function render_detail($dispute_id, $vendor_id) {
        global $wpdb;
        $d = $this->get_dispute($dispute_id, $vendor_id);
        echo '<div class="dso-page dso-disputes">';
        echo '<div class="dso-breadcrumb"><a href="?section=dashboard">Dashboard</a> / <a href="?section=disputes">Joi</a> / #' . intval($dispute_id) . '</div>';
        if (!$d) {
            echo '<div class="dso-empty"><h3>Claim not found</h3><p>This claim does not exist or does not belong to your store.</p><a class="dso-btn dso-btn-outline" href="?section=disputes">Back to Joi</a></div></div>';
            return;
        }
        if (isset($_GET['created'])) { echo '<div class="dso-notice dso-notice-success">Claim #' . intval($d->id) . ' filed. Our team will review it shortly.</div>'; }
        if (isset($_GET['replied'])) { echo '<div class="dso-notice dso-notice-success">Your reply was added to the claim.</div>'; }
        if (isset($_GET['withdrawn'])) { echo '<div class="dso-notice dso-notice-success">Claim withdrawn.</div>'; }
        $type_label = isset(self::CLAIM_TYPES[$d->claim_type]) ? self::CLAIM_TYPES[$d->claim_type] : $d->claim_type;
        $can_reply = in_array($d->status, array('open', 'under_review'), true);
        $can_withdraw = ($d->direction === 'filed_by_me' && $d->status === 'open');
        echo '<div class="dso-page-header"><div>';
        echo '<h1 class="dso-page-title">Claim #' . intval($d->id) . ' ' . $this->status_pill($d->status) . '</h1>';
        echo '<p class="dso-page-sub">' . esc_html($d->subject) . '</p></div>';
        echo '<div class="dso-page-actions">';
        if ($can_withdraw) {
            echo '<form method="post" style="display:inline">';
            wp_nonce_field('dso_dispute_withdraw_nonce');
            echo '<input type="hidden" name="dispute_id" value="' . intval($d->id) . '" />';
            echo '<button type="submit" name="dso_withdraw_claim" value="1" class="dso-btn dso-btn-outline">Withdraw Claim</button></form> ';
        }
        echo '<a class="dso-btn dso-btn-outline" href="?section=disputes">Back to All</a></div></div>';
        echo '<div class="dso-dispute-meta">';
        echo '<div><span>Direction</span><strong>' . ($d->direction === 'filed_by_me' ? 'Filed by me' : 'Against me') . '</strong></div>';
        echo '<div><span>Type</span><strong>' . esc_html($type_label) . '</strong></div>';
        echo '<div><span>Order</span><strong>' . ($d->order_id ? '#' . intval($d->order_id) : '-') . '</strong></div>';
        echo '<div><span>Amount</span><strong>' . esc_html('INR ' . number_format((float) $d->amount, 2)) . '</strong></div>';
        echo '<div><span>Filed</span><strong>' . esc_html(mysql2date('d M Y', $d->created_at)) . '</strong></div>';
        echo '</div>';
        echo '<div class="dso-card"><h3>Description</h3><p>' . wp_kses_post(nl2br($d->description)) . '</p></div>';
        $messages = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $this->messages_table() . ' WHERE dispute_id = %d ORDER BY created_at ASC', $d->id));
        echo '<div class="dso-card"><h3>Timeline</h3><div class="dso-timeline">';
        if (empty($messages)) { echo '<p class="dso-muted">No messages yet.</p>'; }
        foreach ($messages as $m) {
            $who = $m->author_type === 'seller' ? 'You (Seller)' : ($m->author_type === 'admin' ? 'DEJOIY Support' : 'System');
            echo '<div class="dso-timeline-item"><div class="dso-timeline-meta"><strong>' . esc_html($who) . '</strong><span>' . esc_html(mysql2date('d M Y, h:i A', $m->created_at)) . '</span></div><p>' . wp_kses_post(nl2br($m->message)) . '</p></div>';
        }
        echo '</div></div>';
        if ($can_reply) {
            echo '<div class="dso-card"><h3>Add a Reply</h3>';
            echo '<form method="post" class="dso-form">';
            wp_nonce_field('dso_dispute_reply_nonce');
            echo '<input type="hidden" name="dispute_id" value="' . intval($d->id) . '" />';
            echo '<textarea name="reply_message" class="dso-textarea" rows="4" required placeholder="Share an update, tracking details or a document reference..."></textarea>';
            echo '<div class="dso-form-actions"><button type="submit" name="dso_dispute_reply" value="1" class="dso-btn dso-btn-primary">Send Reply</button></div>';
            echo '</form></div>';
        } else {
            echo '<div class="dso-notice">This claim is ' . esc_html(self::STATUSES[$d->status]) . ' and closed for replies.</div>';
        }
        echo '</div>';
    }
}
