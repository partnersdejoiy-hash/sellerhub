<?php
/**
 * DSO Messenger - Real-Time Buyer-Seller Messaging Center
 * Connects buyers on dejoiy.com with sellers on sellerhub.dejoiy.com
 * Supports product inquiries, order tracking queries, canned replies, and real-time polling.
 */
if (!defined('ABSPATH')) exit;

class DSO_Messenger {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register REST routes
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Frontend hooks for dejoiy.com — Disabled per user request (no chat with seller button on dejoiy.com)
        // add_action('wp_footer', [$this, 'render_buyer_floating_widget'], 99);
        // add_action('woocommerce_single_product_summary', [$this, 'render_product_chat_button'], 35);
        add_filter('woocommerce_my_account_my_orders_actions', [$this, 'add_order_message_action'], 20, 2);

        // Confirmed Order hooks: Order confirmation (Thank You) & Order details view
        add_action('woocommerce_thankyou', [$this, 'render_order_sellers_box'], 15);
        add_action('woocommerce_order_details_after_order_table', [$this, 'render_order_sellers_box'], 15);

        // My Account Messages Tab
        add_action('init', [$this, 'register_account_endpoints']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_account_messages_menu_item'], 40);
        add_action('woocommerce_account_messages_endpoint', [$this, 'render_account_messages_endpoint']);
    }

    /**
     * Ensure database tables exist
     */
    public static function check_tables() {
        global $wpdb;
        $table1 = $wpdb->prefix . 'dso_conversations';
        $table2 = $wpdb->prefix . 'dso_messages';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table1'") !== $table1) {
            $c = $wpdb->get_charset_collate();
            $sql1 = "CREATE TABLE IF NOT EXISTS $table1 (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                thread_token VARCHAR(64) NOT NULL UNIQUE,
                buyer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                buyer_name VARCHAR(120) NOT NULL DEFAULT 'Customer',
                buyer_email VARCHAR(120) NOT NULL DEFAULT '',
                buyer_phone VARCHAR(30) NOT NULL DEFAULT '',
                vendor_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                vendor_name VARCHAR(150) NOT NULL DEFAULT 'Seller',
                product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                product_name VARCHAR(255) NOT NULL DEFAULT '',
                product_image VARCHAR(500) NOT NULL DEFAULT '',
                product_price VARCHAR(50) NOT NULL DEFAULT '',
                order_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                order_number VARCHAR(100) NOT NULL DEFAULT '',
                order_total VARCHAR(50) NOT NULL DEFAULT '',
                subject VARCHAR(255) NOT NULL DEFAULT 'General Inquiry',
                status VARCHAR(20) NOT NULL DEFAULT 'open',
                last_message TEXT DEFAULT NULL,
                last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_sender VARCHAR(20) NOT NULL DEFAULT 'buyer',
                unread_seller INT UNSIGNED NOT NULL DEFAULT 0,
                unread_buyer INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY buyer_id (buyer_id),
                KEY vendor_id (vendor_id),
                KEY product_id (product_id),
                KEY order_id (order_id),
                KEY status (status),
                KEY last_message_at (last_message_at)
            ) $c;";

            $sql2 = "CREATE TABLE IF NOT EXISTS $table2 (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                conversation_id BIGINT(20) UNSIGNED NOT NULL,
                sender_type VARCHAR(20) NOT NULL DEFAULT 'buyer',
                sender_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                sender_name VARCHAR(120) NOT NULL DEFAULT '',
                message TEXT NOT NULL,
                attachments TEXT DEFAULT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                read_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY conversation_id (conversation_id),
                KEY is_read (is_read),
                KEY created_at (created_at)
            ) $c;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql1);
            dbDelta($sql2);
        }
    }

    /**
     * Register REST API Routes
     */
    public function register_rest_routes() {
        $ns = 'dejoiy/v1/messenger';

        // 1. Get Threads - authenticated users only
        register_rest_route($ns, '/threads', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_threads'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 2. Get Messages for a Thread - authenticated
        register_rest_route($ns, '/messages', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_messages'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 3. Send Message - authenticated
        register_rest_route($ns, '/send', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_send_message'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 4. Start Conversation - authenticated (guests use token-based flow)
        register_rest_route($ns, '/start', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_start_conversation'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 5. Mark Read - authenticated
        register_rest_route($ns, '/mark-read', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_mark_read'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 6. Update Status (open / resolved) - authenticated
        register_rest_route($ns, '/status', [
            'methods' => 'POST',
            'callback' => [$this, 'rest_update_status'],
            'permission_callback' => 'is_user_logged_in',
        ]);

        // 7. Unread Count Badge - authenticated
        register_rest_route($ns, '/unread', [
            'methods' => 'GET',
            'callback' => [$this, 'rest_get_unread_count'],
            'permission_callback' => 'is_user_logged_in',
        ]);
    }

    /**
     * Helper: Get current vendor ID
     */
    protected function get_vendor_id() {
        $user_id = get_current_user_id();
        if (!$user_id) return 0;
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    /**
     * REST: Get threads list
     */
    public function rest_get_threads($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $role = sanitize_text_field($request->get_param('role') ?: '');
        $status = sanitize_text_field($request->get_param('status') ?: 'all');
        $search = sanitize_text_field($request->get_param('q') ?: '');
        $token = sanitize_text_field($request->get_param('token') ?: '');

        $where = ["1=1"];
        $params = [];

        // Detect if seller or buyer
        $is_seller = ($role === 'seller') || ($user_id && $this->get_vendor_id() == $user_id && !empty($_COOKIE['dso_vendor_logged']));
        if (isset($_GET['is_seller']) && $_GET['is_seller'] == '1') {
            $is_seller = true;
        }

        if ($is_seller) {
            $vendor_id = $this->get_vendor_id();
            if (!$vendor_id && $request->get_param('vendor_id')) {
                $vendor_id = intval($request->get_param('vendor_id'));
            }
            if ($vendor_id) {
                $where[] = "vendor_id = %d";
                $params[] = $vendor_id;
            }
        } else {
            // Buyer mode
            if ($user_id) {
                $where[] = "(buyer_id = %d OR thread_token = %s)";
                $params[] = $user_id;
                $params[] = $token;
            } elseif ($token) {
                $where[] = "thread_token = %s";
                $params[] = $token;
            } else {
                return rest_ensure_response(['threads' => [], 'unread' => 0]);
            }
        }

        if ($status && $status !== 'all') {
            $where[] = "status = %s";
            $params[] = $status;
        }

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(buyer_name LIKE %s OR product_name LIKE %s OR order_number LIKE %s OR subject LIKE %s OR last_message LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql_where = implode(' AND ', $where);
        $query = "SELECT * FROM {$wpdb->prefix}dso_conversations WHERE $sql_where ORDER BY last_message_at DESC LIMIT 50";
        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        $threads = $wpdb->get_results($query, ARRAY_A) ?: [];

        // Format dates & timeago
        $total_unread = 0;
        foreach ($threads as &$t) {
            $t['last_message_display'] = esc_html($t['last_message'] ?: 'No messages yet');
            $t['time_ago'] = human_time_diff(strtotime($t['last_message_at']), current_time('timestamp')) . ' ago';
            $t['buyer_initials'] = strtoupper(substr($t['buyer_name'] ?: 'C', 0, 2));
            if ($is_seller) {
                $total_unread += intval($t['unread_seller']);
            } else {
                $total_unread += intval($t['unread_buyer']);
            }
        }

        return rest_ensure_response([
            'success' => true,
            'threads' => $threads,
            'total_unread' => $total_unread,
        ]);
    }

    /**
     * REST: Get messages for a thread (supports incremental polling via `since_id`)
     */
    public function rest_get_messages($request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $token = sanitize_text_field($request->get_param('thread_token') ?: '');
        $since_id = intval($request->get_param('since_id') ?: 0);

        if (!$conv_id && !$token) {
            return new WP_Error('missing_params', 'Conversation ID or token required', ['status' => 400]);
        }

        // Fetch thread
        if ($conv_id) {
            $thread = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dso_conversations WHERE id = %d", $conv_id), ARRAY_A);
        } else {
            $thread = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dso_conversations WHERE thread_token = %s", $token), ARRAY_A);
            if ($thread) $conv_id = $thread['id'];
        }

        if (!$thread) {
            return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
        }

        // Fetch messages
        $sql = "SELECT * FROM {$wpdb->prefix}dso_messages WHERE conversation_id = %d";
        $params = [$conv_id];
        if ($since_id > 0) {
            $sql .= " AND id > %d";
            $params[] = $since_id;
        }
        $sql .= " ORDER BY id ASC";

        $messages = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A) ?: [];

        foreach ($messages as &$m) {
            $m['formatted_time'] = date_i18n('g:i A', strtotime($m['created_at']));
            $m['formatted_date'] = date_i18n('M j, Y', strtotime($m['created_at']));
            $m['message_html'] = nl2br(make_clickable(esc_html($m['message'])));
        }

        return rest_ensure_response([
            'success' => true,
            'thread' => $thread,
            'messages' => $messages,
        ]);
    }

    /**
     * REST: Send a message
     */
    public function rest_send_message($request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $token = sanitize_text_field($request->get_param('thread_token') ?: '');
        $message_text = trim(wp_strip_all_tags($request->get_param('message') ?: ''));
        $sender_type = sanitize_text_field($request->get_param('sender_type') ?: '');

        if (empty($message_text)) {
            return new WP_Error('empty_message', 'Message cannot be empty', ['status' => 400]);
        }

        // Find thread
        if ($conv_id) {
            $thread = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dso_conversations WHERE id = %d", $conv_id), ARRAY_A);
        } else {
            $thread = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dso_conversations WHERE thread_token = %s", $token), ARRAY_A);
            if ($thread) $conv_id = $thread['id'];
        }

        if (!$thread) {
            return new WP_Error('not_found', 'Conversation not found', ['status' => 404]);
        }

        $user_id = get_current_user_id();

        // Determine sender type if not explicitly supplied
        if (empty($sender_type)) {
            if ($user_id && $user_id == $thread['vendor_id']) {
                $sender_type = 'seller';
            } else {
                $sender_type = 'buyer';
            }
        }

        if ($sender_type === 'seller') {
            $sender_name = $thread['vendor_name'] ?: 'Seller';
            $unread_buyer_inc = 1;
            $unread_seller_inc = 0;
        } else {
            $sender_name = $thread['buyer_name'] ?: ($user_id ? (get_userdata($user_id)->display_name ?: 'Buyer') : 'Customer');
            $unread_buyer_inc = 0;
            $unread_seller_inc = 1;
        }

        $now = current_time('mysql');

        // Insert message
        $wpdb->insert($wpdb->prefix . 'dso_messages', [
            'conversation_id' => $conv_id,
            'sender_type' => $sender_type,
            'sender_id' => $user_id,
            'sender_name' => $sender_name,
            'message' => $message_text,
            'is_read' => 0,
            'created_at' => $now,
        ]);

        $msg_id = $wpdb->insert_id;

        // Update conversation summary
        $update_data = [
            'last_message' => mb_substr($message_text, 0, 150),
            'last_message_at' => $now,
            'last_sender' => $sender_type,
            'status' => 'open',
            'updated_at' => $now,
        ];

        if ($sender_type === 'seller') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}dso_conversations 
                 SET last_message = %s, last_message_at = %s, last_sender = 'seller', unread_buyer = unread_buyer + 1, unread_seller = 0, status = 'open', updated_at = %s 
                 WHERE id = %d",
                mb_substr($message_text, 0, 150), $now, $now, $conv_id
            ));
        } else {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}dso_conversations 
                 SET last_message = %s, last_message_at = %s, last_sender = 'buyer', unread_seller = unread_seller + 1, unread_buyer = 0, status = 'open', updated_at = %s 
                 WHERE id = %d",
                mb_substr($message_text, 0, 150), $now, $now, $conv_id
            ));

            // Create notification for seller
            $wpdb->insert($wpdb->prefix . 'dso_notifications', [
                'vendor_id' => $thread['vendor_id'],
                'type' => 'message',
                'title' => '💬 New message from ' . $sender_name,
                'message' => mb_substr($message_text, 0, 100),
                'is_read' => 0,
                'action_url' => '?section=messages&id=' . $conv_id,
                'created_at' => $now,
            ]);
        }

        $new_msg = [
            'id' => $msg_id,
            'conversation_id' => $conv_id,
            'sender_type' => $sender_type,
            'sender_id' => $user_id,
            'sender_name' => $sender_name,
            'message' => $message_text,
            'message_html' => nl2br(make_clickable(esc_html($message_text))),
            'formatted_time' => date_i18n('g:i A', strtotime($now)),
            'is_read' => 0,
            'created_at' => $now,
        ];

        return rest_ensure_response([
            'success' => true,
            'message' => $new_msg,
        ]);
    }

    /**
     * REST: Start or open conversation
     */
    public function rest_start_conversation($request) {
        global $wpdb;
        $vendor_id = intval($request->get_param('vendor_id') ?: 2);
        $product_id = intval($request->get_param('product_id') ?: 0);
        $order_id = intval($request->get_param('order_id') ?: 0);
        $buyer_name = sanitize_text_field($request->get_param('buyer_name') ?: '');
        $buyer_email = sanitize_email($request->get_param('buyer_email') ?: '');
        $buyer_phone = sanitize_text_field($request->get_param('buyer_phone') ?: '');
        $subject = sanitize_text_field($request->get_param('subject') ?: 'Product Inquiry');
        $initial_msg = trim(wp_strip_all_tags($request->get_param('message') ?: ''));

        $user_id = get_current_user_id();
        if ($user_id) {
            $user = get_userdata($user_id);
            if (empty($buyer_name)) $buyer_name = $user->display_name;
            if (empty($buyer_email)) $buyer_email = $user->user_email;
        }

        if (empty($buyer_name)) $buyer_name = 'Customer';

        // Retrieve vendor name
        $vendor_user = get_userdata($vendor_id);
        $vendor_name = $vendor_user ? $vendor_user->display_name : 'DEJOIY Partner';
        if (class_exists('DSO_Auth')) {
            $store = DSO_Auth::get_vendor_store($vendor_id);
            if ($store && !empty($store['name'])) {
                $vendor_name = $store['name'];
            }
        }

        // Product details if applicable
        $product_name = '';
        $product_image = '';
        $product_price = '';
        if ($product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product_name = $product->get_name();
                $img_id = $product->get_image_id();
                $product_image = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
                $product_price = '₹' . number_format(floatval($product->get_price()), 2);
                if (empty($subject) || $subject === 'General Inquiry') {
                    $subject = 'Inquiry: ' . $product_name;
                }
            }
        }

        // Order details if applicable
        $order_number = '';
        $order_total = '';
        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_number = $order->get_order_number();
                $order_total = '₹' . number_format(floatval($order->get_total()), 2);
                $subject = 'Order #' . $order_number . ' Inquiry';
            }
        }

        // Check if an existing open thread exists for same buyer & vendor & (product or order)
        $existing = null;
        if ($user_id) {
            $existing_query = "SELECT * FROM {$wpdb->prefix}dso_conversations WHERE buyer_id = %d AND vendor_id = %d";
            $ex_params = [$user_id, $vendor_id];
            if ($order_id) {
                $existing_query .= " AND order_id = %d";
                $ex_params[] = $order_id;
            } elseif ($product_id) {
                $existing_query .= " AND product_id = %d";
                $ex_params[] = $product_id;
            }
            $existing_query .= " AND status != 'archived' ORDER BY last_message_at DESC LIMIT 1";
            $existing = $wpdb->get_row($wpdb->prepare($existing_query, $ex_params), ARRAY_A);
        }

        $now = current_time('mysql');

        if ($existing) {
            $conv_id = $existing['id'];
            $token = $existing['thread_token'];

            // If initial message provided, append it
            if (!empty($initial_msg)) {
                $wpdb->insert($wpdb->prefix . 'dso_messages', [
                    'conversation_id' => $conv_id,
                    'sender_type' => 'buyer',
                    'sender_id' => $user_id,
                    'sender_name' => $buyer_name,
                    'message' => $initial_msg,
                    'is_read' => 0,
                    'created_at' => $now,
                ]);

                $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->prefix}dso_conversations 
                     SET last_message = %s, last_message_at = %s, last_sender = 'buyer', unread_seller = unread_seller + 1, status = 'open', updated_at = %s 
                     WHERE id = %d",
                    mb_substr($initial_msg, 0, 150), $now, $now, $conv_id
                ));
            }

            return rest_ensure_response([
                'success' => true,
                'conversation_id' => $conv_id,
                'thread_token' => $token,
                'thread' => $existing,
                'is_new' => false,
            ]);
        }

        // Create new thread
        $token = bin2hex(random_bytes(16));

        $wpdb->insert($wpdb->prefix . 'dso_conversations', [
            'thread_token' => $token,
            'buyer_id' => $user_id,
            'buyer_name' => $buyer_name,
            'buyer_email' => $buyer_email,
            'buyer_phone' => $buyer_phone,
            'vendor_id' => $vendor_id,
            'vendor_name' => $vendor_name,
            'product_id' => $product_id,
            'product_name' => $product_name,
            'product_image' => $product_image,
            'product_price' => $product_price,
            'order_id' => $order_id,
            'order_number' => $order_number,
            'order_total' => $order_total,
            'subject' => $subject,
            'status' => 'open',
            'last_message' => $initial_msg ?: 'Conversation started',
            'last_message_at' => $now,
            'last_sender' => 'buyer',
            'unread_seller' => !empty($initial_msg) ? 1 : 0,
            'unread_buyer' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $conv_id = $wpdb->insert_id;

        if (!empty($initial_msg)) {
            $wpdb->insert($wpdb->prefix . 'dso_messages', [
                'conversation_id' => $conv_id,
                'sender_type' => 'buyer',
                'sender_id' => $user_id,
                'sender_name' => $buyer_name,
                'message' => $initial_msg,
                'is_read' => 0,
                'created_at' => $now,
            ]);

            // Notify seller
            $wpdb->insert($wpdb->prefix . 'dso_notifications', [
                'vendor_id' => $vendor_id,
                'type' => 'message',
                'title' => '💬 New inquiry from ' . $buyer_name,
                'message' => mb_substr($initial_msg, 0, 100),
                'is_read' => 0,
                'action_url' => '?section=messages&id=' . $conv_id,
                'created_at' => $now,
            ]);
        }

        $thread = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}dso_conversations WHERE id = %d", $conv_id), ARRAY_A);

        return rest_ensure_response([
            'success' => true,
            'conversation_id' => $conv_id,
            'thread_token' => $token,
            'thread' => $thread,
            'is_new' => true,
        ]);
    }

    /**
     * REST: Mark thread as read
     */
    public function rest_mark_read($request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $role = sanitize_text_field($request->get_param('role') ?: 'seller');

        if (!$conv_id) {
            return new WP_Error('missing_params', 'Conversation ID required', ['status' => 400]);
        }

        $now = current_time('mysql');

        if ($role === 'seller') {
            $wpdb->update($wpdb->prefix . 'dso_conversations', ['unread_seller' => 0, 'updated_at' => $now], ['id' => $conv_id]);
            $wpdb->update($wpdb->prefix . 'dso_messages', ['is_read' => 1, 'read_at' => $now], ['conversation_id' => $conv_id, 'sender_type' => 'buyer']);
        } else {
            $wpdb->update($wpdb->prefix . 'dso_conversations', ['unread_buyer' => 0, 'updated_at' => $now], ['id' => $conv_id]);
            $wpdb->update($wpdb->prefix . 'dso_messages', ['is_read' => 1, 'read_at' => $now], ['conversation_id' => $conv_id, 'sender_type' => 'seller']);
        }

        return rest_ensure_response(['success' => true]);
    }

    /**
     * REST: Update thread status (open / resolved)
     */
    public function rest_update_status($request) {
        global $wpdb;
        $conv_id = intval($request->get_param('conversation_id'));
        $status = sanitize_text_field($request->get_param('status') ?: 'open');

        if (!in_array($status, ['open', 'resolved', 'archived'])) {
            $status = 'open';
        }

        $wpdb->update($wpdb->prefix . 'dso_conversations', ['status' => $status, 'updated_at' => current_time('mysql')], ['id' => $conv_id]);

        return rest_ensure_response(['success' => true, 'status' => $status]);
    }

    /**
     * REST: Get unread count badge
     */
    public function rest_get_unread_count($request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $role = sanitize_text_field($request->get_param('role') ?: 'seller');
        $token = sanitize_text_field($request->get_param('token') ?: '');

        if ($role === 'seller') {
            $vendor_id = $this->get_vendor_id();
            $count = $wpdb->get_var($wpdb->prepare("SELECT SUM(unread_seller) FROM {$wpdb->prefix}dso_conversations WHERE vendor_id = %d", $vendor_id));
        } else {
            if ($user_id) {
                $count = $wpdb->get_var($wpdb->prepare("SELECT SUM(unread_buyer) FROM {$wpdb->prefix}dso_conversations WHERE buyer_id = %d", $user_id));
            } elseif ($token) {
                $count = $wpdb->get_var($wpdb->prepare("SELECT unread_buyer FROM {$wpdb->prefix}dso_conversations WHERE thread_token = %s", $token));
            } else {
                $count = 0;
            }
        }

        return rest_ensure_response(['success' => true, 'unread' => intval($count ?: 0)]);
    }

    /**
     * RENDER: Seller Central Messages Center (https://sellerhub.dejoiy.com/?section=messages)
     */
    public function render_seller_view() {
        self::check_tables();
        // Load the Discord-inspired messenger template
        include DSO_PATH . 'templates/messenger-seller.php';
    }

    /* ─── Old render_seller_view (replaced by template) ─── */
    public function _old_render_seller_view_LEGACY() {
        self::check_tables();
        $vendor_id = $this->get_vendor_id();
        $selected_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if (!$selected_id && $order_id) {
            global $wpdb;
            $table_conv = $wpdb->prefix . 'dso_conversations';
            $found_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$table_conv} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
                $order_id
            ));
            if ($found_id) {
                $selected_id = intval($found_id);
            } else {
                $order = wc_get_order($order_id);
                if ($order) {
                    $buyer_id = $order->get_customer_id() ?: 0;
                    $buyer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Customer';
                    $buyer_email = $order->get_billing_email();
                    $buyer_phone = $order->get_billing_phone();
                    $order_num = $order->get_order_number();
                    $order_tot = '₹' . number_format(floatval($order->get_total()), 2);

                    $vendor_user = get_user_by('id', $vendor_id);
                    $vendor_name = $vendor_user ? $vendor_user->display_name : 'DEJOIY Partner';
                    if (function_exists('wcfm_get_vendor_store_info')) {
                        $store = wcfm_get_vendor_store_info($vendor_id);
                        if (!empty($store['name'])) {
                            $vendor_name = $store['name'];
                        }
                    }

                    $token = wp_generate_password(32, false);
                    $now = current_time('mysql');

                    $first_product_id = 0;
                    $first_product_name = '';
                    $items = $order->get_items();
                    if (!empty($items)) {
                        $first_item = reset($items);
                        $first_product_id = $first_item->get_product_id();
                        $first_product_name = $first_item->get_name();
                    }

                    $wpdb->insert($table_conv, [
                        'thread_token' => $token,
                        'buyer_id' => $buyer_id,
                        'buyer_name' => $buyer_name,
                        'buyer_email' => $buyer_email,
                        'buyer_phone' => $buyer_phone,
                        'vendor_id' => $vendor_id ?: 1,
                        'vendor_name' => $vendor_name,
                        'product_id' => $first_product_id,
                        'product_name' => $first_product_name,
                        'order_id' => $order_id,
                        'order_number' => $order_num,
                        'order_total' => $order_tot,
                        'subject' => 'Order #' . $order_num,
                        'status' => 'open',
                        'last_message' => 'Direct thread started for Order #' . $order_num,
                        'last_message_at' => $now,
                        'last_sender' => 'seller',
                        'unread_seller' => 0,
                        'unread_buyer' => 0,
                        'created_at' => $now,
                        'updated_at' => $now
                    ]);
                    $selected_id = $wpdb->insert_id;

                    $wpdb->insert($wpdb->prefix . 'dso_messages', [
                        'conversation_id' => $selected_id,
                        'sender_type' => 'system',
                        'sender_id' => 0,
                        'sender_name' => 'DEJOIY System',
                        'message' => 'Direct thread opened for Order #' . $order_num . '. You can now communicate directly with ' . esc_html($buyer_name) . '.',
                        'created_at' => $now
                    ]);
                }
            }
        }
        ?>
        <div class="dso-page dso-messenger-page" id="dso-seller-messenger">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Buyer-Seller Messages</span>
                    </div>
                    <h1 class="dso-page-title">Customer Messaging Center</h1>
                    <p class="dso-page-subtitle">Real-time direct communication with marketplace customers, order queries, and product support.</p>
                </div>
                <div class="dso-page-actions">
                    <button type="button" class="dso-btn dso-btn-outline" id="dso-refresh-threads-btn" title="Refresh messages">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 4v6h-6"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/></svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Main Messenger Layout -->
            <div class="dso-messenger-container">
                <!-- Left Pane: Threads List -->
                <div class="dso-msg-sidebar" id="dso-msg-sidebar">
                    <div class="dso-msg-search-wrap">
                        <div class="dso-msg-search-box">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="dso-thread-search-input" placeholder="Search customer, order #..." />
                        </div>
                        <div class="dso-msg-filter-pills">
                            <button type="button" class="dso-msg-pill active" data-filter="all">All</button>
                            <button type="button" class="dso-msg-pill" data-filter="unread">Unread <span class="dso-pill-badge" id="dso-unread-filter-badge" style="display:none;">0</span></button>
                            <button type="button" class="dso-msg-pill" data-filter="orders">Orders</button>
                            <button type="button" class="dso-msg-pill" data-filter="resolved">Resolved</button>
                        </div>
                    </div>

                    <div class="dso-msg-threads-list" id="dso-msg-threads-list">
                        <div class="dso-msg-loading">
                            <div class="dso-spinner"></div> Loading customer messages...
                        </div>
                    </div>
                </div>

                <!-- Right Pane: Active Chat -->
                <div class="dso-msg-chat-pane" id="dso-msg-chat-pane">
                    <!-- Blank State when no thread is selected -->
                    <div class="dso-msg-empty-state" id="dso-msg-empty-state">
                        <div class="dso-empty-bubble-icon">💬</div>
                        <h3>Select a conversation</h3>
                        <p>Choose a customer message from the left to view order context and reply in real time.</p>
                    </div>

                    <!-- Active Chat View -->
                    <div class="dso-msg-active-chat" id="dso-msg-active-chat" style="display:none;">
                        <!-- Chat Header -->
                        <div class="dso-chat-header">
                            <div class="dso-chat-header-left">
                                <button type="button" class="dso-chat-back-btn" id="dso-chat-back-btn" aria-label="Back to messages">
                                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                                </button>
                                <div class="dso-chat-avatar" id="dso-chat-avatar">DS</div>
                                <div>
                                    <div class="dso-chat-buyer-title">
                                        <span id="dso-chat-buyer-name">Deepak Sharma</span>
                                        <span class="dso-badge dso-badge-green" id="dso-chat-status-badge">● Open</span>
                                    </div>
                                    <div class="dso-chat-subtext" id="dso-chat-subtext">Verified Buyer • deepak.dejoi@gmail.com</div>
                                </div>
                            </div>
                            <div class="dso-chat-header-actions">
                                <div id="dso-chat-context-pills" style="display:flex;gap:6px;align-items:center;"></div>
                                <button type="button" class="dso-btn dso-btn-sm dso-btn-outline" id="dso-resolve-btn" title="Toggle resolved status">
                                    ✓ Resolve
                                </button>
                            </div>
                        </div>

                        <!-- Context Bar (Order / Product Summary Banner) -->
                        <div class="dso-chat-context-bar" id="dso-chat-context-bar" style="display:none;"></div>

                        <!-- Messages Stream -->
                        <div class="dso-chat-messages" id="dso-chat-messages">
                            <!-- Messages bubbles populated dynamically -->
                        </div>

                        <!-- Canned Quick Replies -->
                        <div class="dso-canned-replies">
                            <span class="dso-canned-title">⚡ Quick Replies:</span>
                            <button type="button" class="dso-canned-chip" data-reply="Hello! Your order is being processed and will be dispatched via express courier shortly.">📦 Order Processing</button>
                            <button type="button" class="dso-canned-chip" data-reply="Hi! Your order has been dispatched. You can track it using the tracking link in your account.">🚚 Shipped & Tracking</button>
                            <button type="button" class="dso-canned-chip" data-reply="Yes, this product is in stock, genuine, and comes with DEJOIY Buyer Protection guarantee.">✅ In Stock & Genuine</button>
                            <button type="button" class="dso-canned-chip" data-reply="We provide a 7-day hassle-free replacement under our standard seller policy.">🔄 Returns & Warranty</button>
                        </div>

                        <!-- Composer -->
                        <div class="dso-chat-composer">
                            <textarea id="dso-msg-input" placeholder="Type your reply to customer... (Press Enter to send)" rows="1"></textarea>
                            <button type="button" id="dso-msg-send-btn" class="dso-btn dso-btn-primary" aria-label="Send Message">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                <span>Send</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            function initSellerMessenger() {
            var activeConvId = <?php echo $selected_id ? $selected_id : 0; ?>;
            var activeFilter = 'all';
            var searchQuery = '';
            var lastMessageId = 0;
            var pollInterval = null;
            var restBase = '/wp-json/dejoiy/v1/messenger/';
            var currentThread = null;

            // Audio notification synthesized via Web Audio
            function playChime(isIncoming) {
                try {
                    var ctx = new (window.AudioContext || window.webkitAudioContext)();
                    var osc = ctx.createOscillator();
                    var gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    var now = ctx.currentTime;
                    if (isIncoming) {
                        osc.frequency.setValueAtTime(587.33, now);
                        osc.frequency.exponentialRampToValueAtTime(880, now + 0.12);
                        gain.gain.setValueAtTime(0.08, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
                        osc.start(now);
                        osc.stop(now + 0.25);
                    } else {
                        osc.frequency.setValueAtTime(440, now);
                        osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.08);
                        gain.gain.setValueAtTime(0.06, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.18);
                        osc.start(now);
                        osc.stop(now + 0.18);
                    }
                } catch(e){}
            }

            var currentVendorId = <?php echo (int) $vendor_id; ?>;
            function loadThreads() {
                var url = restBase + 'threads?role=seller&vendor_id=' + currentVendorId + '&status=' + activeFilter + '&q=' + encodeURIComponent(searchQuery);
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data.success) return;
                        renderThreadsList(data.threads);
                        updateUnreadBadges(data.total_unread);
                        if (activeConvId && !currentThread) {
                            selectThread(activeConvId);
                        }
                    })
                    .catch(function(e){ console.error(e); });
            }

            function updateUnreadBadges(count) {
                var badge = document.getElementById('dso-unread-filter-badge');
                if (badge) {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                var navBadge = document.querySelector('.dso-nav-badge-messages');
                if (navBadge) {
                    navBadge.textContent = count > 0 ? count : '';
                    navBadge.style.display = count > 0 ? 'inline-block' : 'none';
                }
            }

            function renderThreadsList(threads) {
                var container = document.getElementById('dso-msg-threads-list');
                if (!threads || threads.length === 0) {
                    container.innerHTML = '<div class="dso-msg-empty-list"><div style="font-size:32px;margin-bottom:8px;">📬</div><strong>No conversations found</strong><p style="font-size:12px;color:#64748b;margin-top:4px;">Customer messages will appear here.</p></div>';
                    return;
                }

                var html = '';
                threads.forEach(function(t) {
                    var isSelected = (t.id == activeConvId);
                    var unreadCount = parseInt(t.unread_seller) || 0;
                    var statusClass = t.status === 'resolved' ? 'dso-th-resolved' : '';
                    var badgeTag = '';
                    if (t.order_number) {
                        badgeTag = '<span class="dso-th-tag dso-th-tag-order">#' + escapeHtml(t.order_number) + '</span>';
                    } else if (t.product_name) {
                        badgeTag = '<span class="dso-th-tag dso-th-tag-prod">📦 ' + escapeHtml(t.product_name.substring(0, 20)) + '</span>';
                    }

                    html += '<div class="dso-msg-thread-item ' + (isSelected ? 'active ' : '') + statusClass + '" data-id="' + t.id + '">' +
                                '<div class="dso-th-avatar">' + (t.buyer_initials || 'C') + '</div>' +
                                '<div class="dso-th-body">' +
                                    '<div class="dso-th-row">' +
                                        '<strong class="dso-th-name">' + escapeHtml(t.buyer_name) + '</strong>' +
                                        '<span class="dso-th-time">' + escapeHtml(t.time_ago) + '</span>' +
                                    '</div>' +
                                    '<div class="dso-th-row" style="margin-top:2px;">' +
                                        badgeTag +
                                    '</div>' +
                                    '<div class="dso-th-preview">' + escapeHtml(t.last_message_display) + '</div>' +
                                '</div>' +
                                (unreadCount > 0 ? '<div class="dso-th-unread-badge">' + unreadCount + '</div>' : '') +
                            '</div>';
                });

                container.innerHTML = html;

                // Click handler
                container.querySelectorAll('.dso-msg-thread-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var id = parseInt(this.getAttribute('data-id'));
                        selectThread(id);
                    });
                });
            }

            function selectThread(convId) {
                activeConvId = convId;
                lastMessageId = 0;

                // Highlight in list
                document.querySelectorAll('.dso-msg-thread-item').forEach(function(el) {
                    el.classList.toggle('active', el.getAttribute('data-id') == convId);
                });

                // Show active chat pane on mobile
                var container = document.querySelector('.dso-messenger-container');
                if (container) container.classList.add('dso-chat-open');

                document.getElementById('dso-msg-empty-state').style.display = 'none';
                document.getElementById('dso-msg-active-chat').style.display = 'flex';

                loadThreadMessages(convId, true);
                markThreadRead(convId);

                // Start real-time poller
                if (pollInterval) clearInterval(pollInterval);
                pollInterval = setInterval(function() {
                    pollNewMessages(activeConvId);
                }, 2500);
            }

            function markThreadRead(convId) {
                fetch(restBase + 'mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: convId, role: 'seller' }),
                    credentials: 'same-origin'
                }).then(function(){
                    loadThreads();
                });
            }

            function loadThreadMessages(convId, isFullLoad) {
                var url = restBase + 'messages?conversation_id=' + convId + (isFullLoad ? '' : ('&since_id=' + lastMessageId));
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data.success) return;
                        currentThread = data.thread;
                        updateChatHeader(data.thread);
                        updateContextBar(data.thread);

                        var chatBox = document.getElementById('dso-chat-messages');
                        if (isFullLoad) {
                            chatBox.innerHTML = '';
                        }

                        if (data.messages && data.messages.length > 0) {
                            data.messages.forEach(function(m) {
                                appendMessageBubble(m);
                                if (m.id > lastMessageId) lastMessageId = m.id;
                            });
                            scrollChatToBottom();
                        }
                    });
            }

            function pollNewMessages(convId) {
                if (!convId || convId !== activeConvId) return;
                var url = restBase + 'messages?conversation_id=' + convId + '&since_id=' + lastMessageId;
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data.success || !data.messages || data.messages.length === 0) return;
                        var hasIncoming = false;
                        data.messages.forEach(function(m) {
                            appendMessageBubble(m);
                            if (m.id > lastMessageId) lastMessageId = m.id;
                            if (m.sender_type === 'buyer') hasIncoming = true;
                        });
                        scrollChatToBottom();
                        if (hasIncoming) {
                            playChime(true);
                            markThreadRead(convId);
                        }
                    });
            }

            function updateChatHeader(thread) {
                document.getElementById('dso-chat-avatar').textContent = (thread.buyer_name || 'C').substring(0, 2).toUpperCase();
                document.getElementById('dso-chat-buyer-name').textContent = thread.buyer_name;
                document.getElementById('dso-chat-subtext').textContent = (thread.buyer_email || 'Verified Customer') + (thread.buyer_phone ? ' • ' + thread.buyer_phone : '');

                var statusBadge = document.getElementById('dso-chat-status-badge');
                if (thread.status === 'resolved') {
                    statusBadge.className = 'dso-badge dso-badge-gray';
                    statusBadge.textContent = '✓ Resolved';
                    document.getElementById('dso-resolve-btn').textContent = 'Reopen Case';
                } else {
                    statusBadge.className = 'dso-badge dso-badge-green';
                    statusBadge.textContent = '● Open';
                    document.getElementById('dso-resolve-btn').textContent = '✓ Mark Resolved';
                }

                var pillsBox = document.getElementById('dso-chat-context-pills');
                pillsBox.innerHTML = '';
                if (thread.order_id && thread.order_number) {
                    pillsBox.innerHTML += '<a href="?section=order-detail&id=' + thread.order_id + '" class="dso-btn dso-btn-sm dso-btn-outline" target="_blank">📋 Order #' + escapeHtml(thread.order_number) + ' ↗</a>';
                }
                if (thread.product_id) {
                    pillsBox.innerHTML += '<a href="?section=edit-product&id=' + thread.product_id + '" class="dso-btn dso-btn-sm dso-btn-outline" target="_blank">📦 Product #' + thread.product_id + ' ↗</a>';
                }
            }

            function updateContextBar(thread) {
                var bar = document.getElementById('dso-chat-context-bar');
                if (thread.product_name || thread.order_number) {
                    var html = '<div class="dso-bar-flex">';
                    if (thread.product_image) {
                        html += '<img src="' + escapeHtml(thread.product_image) + '" class="dso-bar-thumb" alt="" />';
                    }
                    html += '<div class="dso-bar-info">';
                    if (thread.product_name) {
                        html += '<strong>' + escapeHtml(thread.product_name) + '</strong>' + (thread.product_price ? ' • <span class="dso-text-primary">' + escapeHtml(thread.product_price) + '</span>' : '');
                    }
                    if (thread.order_number) {
                        html += '<div style="font-size:11.5px;color:#64748b;">Linked Order: <strong>#' + escapeHtml(thread.order_number) + '</strong>' + (thread.order_total ? ' (' + escapeHtml(thread.order_total) + ')' : '') + '</div>';
                    }
                    html += '</div></div>';
                    bar.innerHTML = html;
                    bar.style.display = 'block';
                } else {
                    bar.style.display = 'none';
                }
            }

            function appendMessageBubble(m) {
                var isSeller = (m.sender_type === 'seller');
                var chatBox = document.getElementById('dso-chat-messages');

                var bubble = document.createElement('div');
                bubble.className = 'dso-bubble-row ' + (isSeller ? 'dso-bubble-out' : 'dso-bubble-in');
                bubble.innerHTML = '<div class="dso-bubble">' +
                                       '<div class="dso-bubble-sender">' + (isSeller ? 'You (Seller)' : escapeHtml(m.sender_name)) + '</div>' +
                                       '<div class="dso-bubble-text">' + m.message_html + '</div>' +
                                       '<div class="dso-bubble-meta">' +
                                           '<span>' + m.formatted_time + '</span>' +
                                           (isSeller ? '<span class="dso-bubble-ticks">✓✓</span>' : '') +
                                       '</div>' +
                                   '</div>';
                chatBox.appendChild(bubble);
            }

            function scrollChatToBottom() {
                var chatBox = document.getElementById('dso-chat-messages');
                chatBox.scrollTop = chatBox.scrollHeight;
            }

            function sendMessage() {
                var input = document.getElementById('dso-msg-input');
                var text = input.value.trim();
                if (!text || !activeConvId) return;

                input.value = '';
                input.style.height = 'auto';

                fetch(restBase + 'send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: activeConvId,
                        message: text,
                        sender_type: 'seller'
                    }),
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success && data.message) {
                        appendMessageBubble(data.message);
                        if (data.message.id > lastMessageId) lastMessageId = data.message.id;
                        scrollChatToBottom();
                        playChime(false);
                        loadThreads();
                    }
                });
            }

            // Composer Events
            var sendBtn = document.getElementById('dso-msg-send-btn');
            var msgInput = document.getElementById('dso-msg-input');
            if (sendBtn) sendBtn.addEventListener('click', sendMessage);
            if (msgInput) {
                msgInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
                msgInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });
            }

            // Canned Replies click
            document.querySelectorAll('.dso-canned-chip').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    var reply = this.getAttribute('data-reply');
                    if (msgInput) {
                        msgInput.value = reply;
                        msgInput.focus();
                    }
                });
            });

            // Status Filter Tabs
            document.querySelectorAll('.dso-msg-pill').forEach(function(pill) {
                pill.addEventListener('click', function() {
                    document.querySelectorAll('.dso-msg-pill').forEach(function(p){ p.classList.remove('active'); });
                    this.classList.add('active');
                    activeFilter = this.getAttribute('data-filter');
                    loadThreads();
                });
            });

            // Live Search
            var searchInp = document.getElementById('dso-thread-search-input');
            var searchDebounce = null;
            if (searchInp) {
                searchInp.addEventListener('input', function() {
                    var v = this.value;
                    clearTimeout(searchDebounce);
                    searchDebounce = setTimeout(function() {
                        searchQuery = v;
                        loadThreads();
                    }, 250);
                });
            }

            // Refresh Button
            var refreshBtn = document.getElementById('dso-refresh-threads-btn');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    loadThreads();
                    if (activeConvId) loadThreadMessages(activeConvId, true);
                });
            }

            // Mobile Back Button
            var backBtn = document.getElementById('dso-chat-back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    var container = document.querySelector('.dso-messenger-container');
                    if (container) container.classList.remove('dso-chat-open');
                });
            }

            // Toggle Resolve
            var resolveBtn = document.getElementById('dso-resolve-btn');
            if (resolveBtn) {
                resolveBtn.addEventListener('click', function() {
                    if (!activeConvId || !currentThread) return;
                    var newSt = (currentThread.status === 'resolved') ? 'open' : 'resolved';
                    fetch(restBase + 'status', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ conversation_id: activeConvId, status: newSt }),
                        credentials: 'same-origin'
                    }).then(function(){
                        loadThreads();
                        if (activeConvId) loadThreadMessages(activeConvId, true);
                    });
                });
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Initial load
            loadThreads();
        }

        // Run immediately as elements are already in the DOM
        initSellerMessenger();
        })();
        </script>
        <?php
    }

    /**
     * RENDER: Floating Buyer-Seller Widget on dejoiy.com
     */
    public function render_buyer_floating_widget() {
        // Only run on buyer-facing pages (not on seller hub standalone)
        if (strpos($_SERVER['HTTP_HOST'] ?? '', 'sellerhub') !== false) {
            return;
        }

        self::check_tables();
        // Load Discord-inspired buyer widget template
        include DSO_PATH . 'templates/messenger-buyer-widget.php';
        return; // Skip legacy code below (template loaded above)
        // ─── LEGACY CODE BELOW (unreachable, kept for reference) ───

        self::check_tables();

        $user_id = get_current_user_id();
        $user_name = '';
        $user_email = '';
        if ($user_id) {
            $user = get_userdata($user_id);
            $user_name = $user ? $user->display_name : '';
            $user_email = $user ? $user->user_email : '';
        }

        $current_prod_id = 0;
        $current_prod_title = '';
        $current_prod_price = '';
        $current_prod_thumb = '';
        $current_vendor_id = 2; // Default verified merchant deepak ki dukaan

        if (is_product()) {
            global $post;
            if ($post) {
                $current_prod_id = $post->ID;
                $current_prod_title = get_the_title($post->ID);
                $p = wc_get_product($post->ID);
                if ($p) {
                    $current_prod_price = '₹' . number_format(floatval($p->get_price()), 2);
                    $img_id = $p->get_image_id();
                    $current_prod_thumb = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';
                }
                $v = get_post_field('post_author', $post->ID);
                if ($v) $current_vendor_id = $v;
            }
        }
        ?>
        <style>
        /* DEJOIY Buyer Messenger Floating Widget */
        #dejoiy-buyer-widget-root {
          position: fixed;
          bottom: 24px;
          right: 24px;
          z-index: 999999;
          font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        #djy-chat-launcher {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 12px 20px;
          background: linear-gradient(135deg, #001553 0%, #0066ff 100%);
          color: #ffffff;
          border: none;
          border-radius: 30px;
          cursor: pointer;
          font-size: 14px;
          font-weight: 700;
          box-shadow: 0 8px 25px rgba(0, 102, 255, 0.35);
          transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
          position: relative;
        }
        #djy-chat-launcher:hover {
          transform: translateY(-2px);
          box-shadow: 0 12px 30px rgba(0, 102, 255, 0.45);
        }
        .djy-launcher-pulse {
          position: absolute;
          top: 0; left: 0; right: 0; bottom: 0;
          border-radius: 30px;
          box-shadow: 0 0 0 0 rgba(0, 102, 255, 0.6);
          animation: djyPulse 2s infinite;
        }
        @keyframes djyPulse {
          0% { box-shadow: 0 0 0 0 rgba(0, 102, 255, 0.6); }
          70% { box-shadow: 0 0 0 12px rgba(0, 102, 255, 0); }
          100% { box-shadow: 0 0 0 0 rgba(0, 102, 255, 0); }
        }
        .djy-launcher-unread {
          background: #d9006c;
          color: #fff;
          font-size: 11px;
          font-weight: 800;
          padding: 2px 7px;
          border-radius: 10px;
          margin-left: 4px;
        }
        #djy-chat-modal {
          width: 380px;
          height: 540px;
          max-width: calc(100vw - 32px);
          max-height: calc(100vh - 100px);
          background: #ffffff;
          border-radius: 18px;
          border: 1px solid #e2e8f0;
          box-shadow: 0 20px 50px rgba(0, 12, 44, 0.25);
          display: flex;
          flex-direction: column;
          overflow: hidden;
          animation: djySlideUp 0.25s ease;
        }
        @keyframes djySlideUp {
          from { opacity: 0; transform: translateY(16px); }
          to { opacity: 1; transform: translateY(0); }
        }
        .djy-cm-header {
          background: linear-gradient(135deg, #000c2c 0%, #001553 100%);
          color: #ffffff;
          padding: 14px 16px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }
        .djy-cm-brand {
          display: flex;
          align-items: center;
          gap: 10px;
        }
        .djy-cm-avatar {
          width: 36px;
          height: 36px;
          border-radius: 50%;
          background: rgba(255, 255, 255, 0.12);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 18px;
        }
        .djy-cm-title {
          font-size: 14px;
          font-weight: 800;
          color: #ffffff;
        }
        .djy-cm-online {
          font-size: 11px;
          color: #94a3b8;
          display: flex;
          align-items: center;
          gap: 5px;
          margin-top: 1px;
        }
        .djy-cm-dot {
          width: 7px;
          height: 7px;
          border-radius: 50%;
          background: #10b981;
        }
        .djy-cm-close-btn {
          background: rgba(255, 255, 255, 0.1);
          border: none;
          color: #ffffff;
          width: 28px;
          height: 28px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          font-size: 14px;
        }
        .djy-cm-prod-banner {
          display: flex;
          align-items: center;
          gap: 10px;
          background: #f8fafc;
          padding: 8px 14px;
          border-bottom: 1px solid #e2e8f0;
        }
        .djy-cm-prod-img {
          width: 36px;
          height: 36px;
          border-radius: 6px;
          object-fit: cover;
          border: 1px solid #e2e8f0;
        }
        .djy-cm-prod-meta {
          flex: 1;
          min-width: 0;
        }
        .djy-cm-prod-name {
          font-size: 12.5px;
          font-weight: 700;
          color: #0f172a;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
        .djy-cm-prod-price {
          font-size: 11px;
          color: #0066ff;
          font-weight: 600;
        }
        .djy-cm-messages {
          flex: 1;
          overflow-y: auto;
          padding: 14px;
          display: flex;
          flex-direction: column;
          gap: 10px;
          background: #fafafa;
        }
        .djy-cm-welcome {
          margin: auto;
          text-align: center;
          padding: 24px 14px;
        }
        .djy-msg-row {
          display: flex;
          flex-direction: column;
          max-width: 80%;
        }
        .djy-msg-in { align-self: flex-start; }
        .djy-msg-out { align-self: flex-end; }
        .djy-msg-bubble {
          border-radius: 12px;
          padding: 8px 12px;
          font-size: 13px;
          line-height: 1.4;
          word-break: break-word;
        }
        .djy-msg-in .djy-msg-bubble {
          background: #ffffff;
          border: 1px solid #e2e8f0;
          color: #0f172a;
          border-bottom-left-radius: 3px;
        }
        .djy-msg-out .djy-msg-bubble {
          background: #0066ff;
          color: #ffffff;
          border-bottom-right-radius: 3px;
        }
        .djy-msg-sender {
          font-size: 10px;
          font-weight: 700;
          color: #0066ff;
          margin-bottom: 3px;
        }
        .djy-msg-meta {
          font-size: 10px;
          opacity: 0.7;
          text-align: right;
          margin-top: 3px;
        }
        .djy-cm-quick-prompts {
          display: flex;
          gap: 6px;
          overflow-x: auto;
          padding: 6px 12px;
          background: #f8fafc;
          border-top: 1px solid #e2e8f0;
          scrollbar-width: none;
        }
        .djy-cm-quick-prompts::-webkit-scrollbar { display: none; }
        .djy-prompt-chip {
          padding: 4px 10px;
          border-radius: 12px;
          background: #ffffff;
          border: 1px solid #cbd5e1;
          font-size: 11px;
          font-weight: 600;
          color: #334155;
          white-space: nowrap;
          cursor: pointer;
        }
        .djy-prompt-chip:hover {
          background: #eff6ff;
          border-color: #0066ff;
          color: #0066ff;
        }
        .djy-cm-guest-bar {
          display: flex;
          gap: 6px;
          padding: 6px 12px;
          background: #f1f5f9;
          border-top: 1px solid #e2e8f0;
        }
        .djy-cm-guest-bar input {
          flex: 1;
          padding: 6px 10px !important;
          font-size: 11.5px !important;
          border-radius: 6px !important;
          border: 1px solid #cbd5e1 !important;
          background: #ffffff !important;
        }
        .djy-cm-composer {
          padding: 10px 12px;
          background: #ffffff;
          border-top: 1px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 8px;
        }
        .djy-cm-composer input {
          flex: 1;
          border: 1.5px solid #cbd5e1 !important;
          border-radius: 20px !important;
          padding: 8px 14px !important;
          font-size: 13.5px !important;
          color: #0f172a !important;
          outline: none !important;
          background: #ffffff !important;
        }
        .djy-cm-composer input:focus { border-color: #0066ff !important; }
        .djy-cm-composer button {
          width: 38px;
          height: 38px;
          border-radius: 50%;
          background: #0066ff;
          color: #ffffff;
          border: none;
          cursor: pointer;
          display: flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
          transition: transform 0.15s ease;
        }
        .djy-cm-composer button:hover { transform: scale(1.05); }
        .djy-product-chat-strip { margin: 14px 0; }
        .djy-btn-chat-seller {
          display: inline-flex;
          align-items: center;
          gap: 8px;
          padding: 10px 18px;
          border-radius: 10px;
          background: rgba(0, 102, 255, 0.08);
          border: 1.5px solid rgba(0, 102, 255, 0.35);
          color: #0066ff;
          font-size: 13.5px;
          font-weight: 700;
          cursor: pointer;
          transition: all 0.2s ease;
        }
        .djy-btn-chat-seller:hover {
          background: #0066ff;
          color: #ffffff;
          border-color: #0066ff;
          box-shadow: 0 4px 12px rgba(0, 102, 255, 0.25);
        }
        @media (max-width: 768px) {
          #djy-chat-modal {
            width: 100vw;
            height: 85vh;
            max-width: 100vw;
            max-height: 85vh;
            position: fixed;
            bottom: 0;
            right: 0;
            left: 0;
            border-radius: 20px 20px 0 0;
          }
        }
        </style>
        <!-- DEJOIY Buyer-Seller Real-Time Messenger Widget (Modal Container Only) -->
        <div id="dejoiy-buyer-widget-root" style="pointer-events:none;">
            <!-- Floating Chat Window Card (Opened via Order / Menu Contact Seller) -->
            <div id="djy-chat-modal" style="display:none;pointer-events:auto;">
                <!-- Header -->
                <div class="djy-cm-header">
                    <div class="djy-cm-brand">
                        <div class="djy-cm-avatar">🏪</div>
                        <div>
                            <div class="djy-cm-title">DEJOIY Seller Messenger</div>
                            <div class="djy-cm-online"><span class="djy-cm-dot"></span> Online • Fast seller response</div>
                        </div>
                    </div>
                    <div class="djy-cm-actions">
                        <button type="button" class="djy-cm-close-btn" id="djy-cm-close-btn" title="Close chat">✕</button>
                    </div>
                </div>

                <!-- Product Context Banner (if on product page) -->
                <?php if ($current_prod_id): ?>
                <div class="djy-cm-prod-banner">
                    <?php if ($current_prod_thumb): ?>
                        <img src="<?php echo esc_url($current_prod_thumb); ?>" class="djy-cm-prod-img" alt="" />
                    <?php endif; ?>
                    <div class="djy-cm-prod-meta">
                        <div class="djy-cm-prod-name"><?php echo esc_html($current_prod_title); ?></div>
                        <div class="djy-cm-prod-price"><?php echo esc_html($current_prod_price); ?> • Verified Merchant</div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Messages Stream -->
                <div class="djy-cm-messages" id="djy-cm-messages">
                    <div class="djy-cm-welcome">
                        <div style="font-size:28px;margin-bottom:6px;">👋</div>
                        <strong>Have a question for the seller?</strong>
                        <p style="font-size:12px;color:#64748b;margin:4px 0 0;">Ask about sizing, delivery estimation, custom requests, or stock status.</p>
                    </div>
                </div>

                <!-- Quick Inquiry Prompts -->
                <div class="djy-cm-quick-prompts" id="djy-cm-quick-prompts">
                    <button type="button" class="djy-prompt-chip" data-text="Is this item currently in stock and ready to ship?">⚡ Is this in stock?</button>
                    <button type="button" class="djy-prompt-chip" data-text="What is the expected delivery time to my pincode?">🚚 Delivery time?</button>
                    <button type="button" class="djy-prompt-chip" data-text="Is cash on delivery (COD) available for this product?">💵 COD available?</button>
                </div>

                <!-- Guest identity fields if not logged in -->
                <?php if (!$user_id): ?>
                <div class="djy-cm-guest-bar" id="djy-cm-guest-bar">
                    <input type="text" id="djy-guest-name" placeholder="Your Name" />
                    <input type="email" id="djy-guest-email" placeholder="Your Email (for replies)" />
                </div>
                <?php endif; ?>

                <!-- Composer -->
                <div class="djy-cm-composer">
                    <input type="text" id="djy-cm-input" placeholder="Type message to seller..." autocomplete="off" />
                    <button type="button" id="djy-cm-send-btn" aria-label="Send message">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var restBase = '/wp-json/dejoiy/v1/messenger/';
            var currentProdId = <?php echo (int) $current_prod_id; ?>;
            var currentVendorId = <?php echo (int) $current_vendor_id; ?>;
            var currentOrderId = 0;
            var isUserLoggedIn = <?php echo $user_id ? 'true' : 'false'; ?>;
            var userName = '<?php echo esc_js($user_name); ?>';
            var userEmail = '<?php echo esc_js($user_email); ?>';

            var activeToken = localStorage.getItem('dejoiy_buyer_thread_token_' + currentVendorId + '_' + currentProdId) || localStorage.getItem('dejoiy_buyer_active_token') || '';
            var activeConvId = localStorage.getItem('dejoiy_buyer_active_conv_id') || 0;
            var lastMsgId = 0;
            var pollTimer = null;

            var launcherBtn = document.getElementById('djy-chat-launcher');
            var chatModal = document.getElementById('djy-chat-modal');
            var closeBtn = document.getElementById('djy-cm-close-btn');
            var sendBtn = document.getElementById('djy-cm-send-btn');
            var textInput = document.getElementById('djy-cm-input');
            var messagesBox = document.getElementById('djy-cm-messages');

            function toggleModal(open) {
                if (open) {
                    chatModal.style.display = 'flex';
                    launcherBtn.style.display = 'none';
                    if (textInput) textInput.focus();
                    if (activeToken && activeConvId) {
                        loadMessages(true);
                    }
                    if (pollTimer) clearInterval(pollTimer);
                    pollTimer = setInterval(pollMessages, 2500);
                } else {
                    chatModal.style.display = 'none';
                    launcherBtn.style.display = 'flex';
                    if (pollTimer) clearInterval(pollTimer);
                }
            }

            if (launcherBtn) launcherBtn.addEventListener('click', function(){ toggleModal(true); });
            if (closeBtn) closeBtn.addEventListener('click', function(){ toggleModal(false); });

            // Expose globally for "Contact Seller" buttons
            window.dejoiyOpenSellerChat = function(prodId, vendorId, orderId) {
                if (prodId) currentProdId = prodId;
                if (vendorId) currentVendorId = vendorId;
                if (orderId) currentOrderId = orderId;
                toggleModal(true);
            };

            window.dejoiyOpenSellerChatForOrder = function(orderId, orderNum, prodId, prodTitle, prodThumb, prodPrice, vendorId, storeName) {
                if (orderId) currentOrderId = orderId;
                if (prodId) currentProdId = prodId;
                if (vendorId) currentVendorId = vendorId;

                var banner = document.getElementById('djy-cm-product-banner');
                var titleEl = document.getElementById('djy-context-name');
                var priceEl = document.getElementById('djy-context-price');
                var imgEl = document.getElementById('djy-context-img');
                var brandSub = document.querySelector('.djy-cm-online');

                if (banner && (prodTitle || orderNum)) {
                    banner.style.display = 'flex';
                    if (titleEl) titleEl.textContent = prodTitle || ('Order #' + orderNum);
                    if (priceEl) priceEl.textContent = (prodPrice ? prodPrice + ' • ' : '') + 'Sold by ' + (storeName || 'Verified Seller');
                    if (imgEl) {
                        if (prodThumb) {
                            imgEl.src = prodThumb;
                            imgEl.style.display = 'block';
                        } else {
                            imgEl.style.display = 'none';
                        }
                    }
                }
                if (brandSub && storeName) {
                    brandSub.innerHTML = '<span class="djy-cm-dot"></span> ' + escapeHtml(storeName) + ' • Dedicated Merchant';
                }

                activeConvId = 0;
                activeToken = '';
                messagesBox.innerHTML = '<div style="padding:20px;text-align:center;color:#64748b;font-size:12.5px;">Connecting with ' + escapeHtml(storeName || 'Seller') + '...</div>';

                fetch(restBase + 'threads?role=buyer&order_id=' + orderId, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (data.success && data.threads && data.threads.length > 0) {
                            var found = data.threads.find(function(t){ return t.vendor_id == vendorId; }) || data.threads[0];
                            if (found) {
                                activeConvId = found.id;
                                activeToken = found.thread_token;
                                loadMessages(true);
                                return;
                            }
                        }
                        messagesBox.innerHTML = '<div style="padding:20px 16px;text-align:center;background:#f8fafc;border-radius:12px;margin:12px;border:1px dashed #cbd5e1;">' +
                            '<div style="font-size:28px;margin-bottom:6px;">💬</div>' +
                            '<strong style="font-size:13.5px;color:#0f172a;">Contact ' + escapeHtml(storeName || 'Seller') + '</strong>' +
                            '<p style="font-size:12px;color:#64748b;margin:4px 0 0 0;">Direct inquiry regarding Order #' + escapeHtml(orderNum) + '. Ask about delivery date, tracking, or items.</p>' +
                        '</div>';
                    })
                    .catch(function(){
                        messagesBox.innerHTML = '';
                    });

                toggleModal(true);
            };

            // Order action listener
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('a[href*="#chat-order-"]');
                if (btn) {
                    e.preventDefault();
                    var href = btn.getAttribute('href');
                    var match = href.match(/#chat-order-(\d+)/);
                    if (match && match[1]) {
                        window.dejoiyOpenSellerChat(0, 0, parseInt(match[1]));
                    }
                }
            });

            function loadMessages(isInitial) {
                var url = restBase + 'messages?thread_token=' + encodeURIComponent(activeToken) + '&conversation_id=' + activeConvId;
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data.success || !data.messages) return;
                        if (isInitial) messagesBox.innerHTML = '';
                        data.messages.forEach(function(m) {
                            appendBubble(m);
                            if (m.id > lastMsgId) lastMsgId = m.id;
                        });
                        messagesBox.scrollTop = messagesBox.scrollHeight;
                        markRead();
                    });
            }

            function pollMessages() {
                if (!activeToken && !activeConvId) return;
                var url = restBase + 'messages?thread_token=' + encodeURIComponent(activeToken) + '&conversation_id=' + activeConvId + '&since_id=' + lastMsgId;
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data.success || !data.messages || data.messages.length === 0) return;
                        data.messages.forEach(function(m) {
                            appendBubble(m);
                            if (m.id > lastMsgId) lastMsgId = m.id;
                        });
                        messagesBox.scrollTop = messagesBox.scrollHeight;
                        markRead();
                    });
            }

            function markRead() {
                if (!activeConvId) return;
                fetch(restBase + 'mark-read', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ conversation_id: activeConvId, role: 'buyer' }),
                    credentials: 'same-origin'
                });
            }

            function appendBubble(m) {
                var isMe = (m.sender_type === 'buyer');
                var div = document.createElement('div');
                div.className = 'djy-msg-row ' + (isMe ? 'djy-msg-out' : 'djy-msg-in');
                div.innerHTML = '<div class="djy-msg-bubble">' +
                                    (isMe ? '' : '<div class="djy-msg-sender">' + escapeHtml(m.sender_name) + ' (Verified Seller)</div>') +
                                    '<div class="djy-msg-text">' + m.message_html + '</div>' +
                                    '<div class="djy-msg-meta">' + m.formatted_time + '</div>' +
                                '</div>';
                messagesBox.appendChild(div);
            }

            function sendBuyerMessage(customText) {
                var text = customText || (textInput ? textInput.value.trim() : '');
                if (!text) return;

                if (textInput) textInput.value = '';

                var gName = userName;
                var gEmail = userEmail;
                if (!isUserLoggedIn) {
                    var nEl = document.getElementById('djy-guest-name');
                    var eEl = document.getElementById('djy-guest-email');
                    if (nEl && nEl.value.trim()) gName = nEl.value.trim();
                    if (eEl && eEl.value.trim()) gEmail = eEl.value.trim();
                }

                // If no active thread yet, start one
                if (!activeConvId && !activeToken) {
                    fetch(restBase + 'start', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            vendor_id: currentVendorId,
                            product_id: currentProdId,
                            order_id: currentOrderId,
                            buyer_name: gName,
                            buyer_email: gEmail,
                            message: text
                        }),
                        credentials: 'same-origin'
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (data.success && data.conversation_id) {
                            activeConvId = data.conversation_id;
                            activeToken = data.thread_token;
                            localStorage.setItem('dejoiy_buyer_active_conv_id', activeConvId);
                            localStorage.setItem('dejoiy_buyer_active_token', activeToken);
                            localStorage.setItem('dejoiy_buyer_thread_token_' + currentVendorId + '_' + currentProdId, activeToken);

                            // Hide welcome
                            var w = document.querySelector('.djy-cm-welcome');
                            if (w) w.style.display = 'none';

                            loadMessages(true);
                        }
                    });
                } else {
                    // Send message into existing thread
                    fetch(restBase + 'send', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            conversation_id: activeConvId,
                            thread_token: activeToken,
                            message: text,
                            sender_type: 'buyer'
                        }),
                        credentials: 'same-origin'
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (data.success && data.message) {
                            appendBubble(data.message);
                            if (data.message.id > lastMsgId) lastMsgId = data.message.id;
                            messagesBox.scrollTop = messagesBox.scrollHeight;
                        }
                    });
                }
            }

            if (sendBtn) sendBtn.addEventListener('click', function(){ sendBuyerMessage(); });
            if (textInput) {
                textInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        sendBuyerMessage();
                    }
                });
            }

            // Quick prompts chips
            document.querySelectorAll('.djy-prompt-chip').forEach(function(chip) {
                chip.addEventListener('click', function() {
                    var promptText = this.getAttribute('data-text');
                    sendBuyerMessage(promptText);
                    document.getElementById('djy-cm-quick-prompts').style.display = 'none';
                });
            });

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }
        })();
        </script>
        <?php
    }

    /**
     * Helper: Resolve vendor ID and Store Name for any product
     */
    public static function get_item_vendor_info($product_id) {
        $vendor_id = 0;
        if (function_exists('wcfm_get_vendor_id_by_post')) {
            $vendor_id = intval(wcfm_get_vendor_id_by_post($product_id));
        }
        if (!$vendor_id) {
            $vendor_id = intval(get_post_field('post_author', $product_id));
        }
        if (!$vendor_id) {
            $vendor_id = 2; // Default verified merchant
        }

        $store_name = '';
        if (function_exists('wcfm_get_vendor_store_name')) {
            $store_name = wcfm_get_vendor_store_name($vendor_id);
        }
        if (empty($store_name) && function_exists('wcfm_get_vendor_store_info')) {
            $info = wcfm_get_vendor_store_info($vendor_id);
            if (!empty($info['store_name'])) $store_name = $info['store_name'];
            elseif (!empty($info['name'])) $store_name = $info['name'];
        }
        if (empty($store_name)) {
            $u = get_userdata($vendor_id);
            if ($u) $store_name = $u->display_name;
        }
        if (empty($store_name)) {
            $store_name = 'DEJOIY Verified Merchant';
        }

        return [
            'vendor_id' => $vendor_id,
            'store_name' => $store_name,
        ];
    }

    /**
     * RENDER: Product Single Page "Contact Seller" (Only for confirmed buyers of this product)
     */
    public function render_product_chat_button() {
        static $rendered = false;
        if ($rendered) return;

        if (!is_user_logged_in()) {
            return; // Not logged in -> No contact seller button
        }

        global $product;
        if (!$product) {
            $product = wc_get_product(get_the_ID());
        }
        if (!$product) return;

        $user_id = get_current_user_id();
        $prod_id = $product->get_id();

        // Check if current user has an order for this product
        if (!function_exists('wc_customer_bought_product') || !wc_customer_bought_product('', $user_id, $prod_id)) {
            return; // Not purchased - do not show contact seller button
        }

        $rendered = true;
        $vendor_info = self::get_item_vendor_info($prod_id);
        $vendor_id = $vendor_info['vendor_id'];
        $store_name = $vendor_info['store_name'];
        $p_title = $product->get_name();
        $p_price = '₹' . number_format(floatval($product->get_price()), 2);
        $img_id = $product->get_image_id();
        $p_img = $img_id ? wp_get_attachment_image_url($img_id, 'thumbnail') : '';

        // Find most recent order ID for this customer and product
        global $wpdb;
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT o.ID FROM {$wpdb->prefix}posts o
             INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON o.ID = oi.order_id
             INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
             INNER JOIN {$wpdb->prefix}postmeta pm ON o.ID = pm.post_id AND pm.meta_key = '_customer_user'
             WHERE oim.meta_key IN ('_product_id', '_variation_id') AND oim.meta_value = %d
             AND pm.meta_value = %d
             ORDER BY o.ID DESC LIMIT 1",
            $prod_id,
            $user_id
        )) ?: 0;
        $order_num = $order_id ? (wc_get_order($order_id) ? wc_get_order($order_id)->get_order_number() : $order_id) : '';
        ?>
        <div class="djy-product-buyer-contact-strip" style="margin:16px 0;padding:12px 16px;background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
            <div>
                <div style="font-size:12px;font-weight:700;color:#16a34a;display:flex;align-items:center;gap:6px;">
                    <span>✓ Verified Purchase</span>
                    <?php if ($order_num) : ?>
                        <span style="color:#64748b;font-weight:500;">(Order #<?php echo esc_html($order_num); ?>)</span>
                    <?php endif; ?>
                </div>
                <div style="font-size:12.5px;color:#334155;margin-top:2px;">
                    Dedicated Seller: <strong><?php echo esc_html($store_name); ?></strong>
                </div>
            </div>
            <button type="button" class="button" style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;background:linear-gradient(135deg, #001553 0%, #0066ff 100%);color:#ffffff;border:none;border-radius:20px;font-size:12.5px;font-weight:700;cursor:pointer;" onclick="if(window.dejoiyOpenSellerChatForOrder){ window.dejoiyOpenSellerChatForOrder(<?php echo (int) $order_id; ?>, '<?php echo esc_js($order_num); ?>', <?php echo (int) $prod_id; ?>, '<?php echo esc_js($p_title); ?>', '<?php echo esc_js($p_img); ?>', '<?php echo esc_js($p_price); ?>', <?php echo (int) $vendor_id; ?>, '<?php echo esc_js($store_name); ?>'); }">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                <span>Contact Seller</span>
            </button>
        </div>
        <?php
    }

    /**
     * RENDER: Confirmed Order Dedicated Sellers Box (Thank You Page & Order Details)
     */
    public function render_order_sellers_box($order_id_or_order) {
        $order = is_a($order_id_or_order, 'WC_Order') ? $order_id_or_order : wc_get_order($order_id_or_order);
        if (!$order) return;

        static $rendered_orders = [];
        $order_id = $order->get_id();
        if (isset($rendered_orders[$order_id])) return;
        $rendered_orders[$order_id] = true;

        $items = $order->get_items();
        if (empty($items)) return;

        $order_id = $order->get_id();
        $order_num = $order->get_order_number();
        ?>
        <div class="djy-order-sellers-section" style="margin:28px 0;background:#ffffff;border:1.5px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.04);">
            <div style="background:linear-gradient(135deg, #000c2c 0%, #001553 100%);color:#ffffff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:18px;">💬</div>
                    <div>
                        <h4 style="margin:0;font-size:15px;font-weight:800;color:#ffffff;letter-spacing:-0.2px;">Dedicated Sellers for Your Order #<?php echo esc_html($order_num); ?></h4>
                        <p style="margin:2px 0 0 0;font-size:12px;color:#94a3b8;">Direct message support for shipping updates, customization, or warranty inquiries.</p>
                    </div>
                </div>
                <span style="font-size:11px;font-weight:700;background:rgba(16,185,129,0.2);color:#34d399;padding:4px 10px;border-radius:12px;border:1px solid rgba(52,211,153,0.3);">✓ Order Confirmed</span>
            </div>
            <div style="padding:16px 20px;display:flex;flex-direction:column;gap:14px;">
                <?php
                foreach ($items as $item) :
                    $prod_id = $item->get_product_id();
                    $product = $item->get_product();
                    $vendor_info = self::get_item_vendor_info($prod_id);
                    $vendor_id = $vendor_info['vendor_id'];
                    $store_name = $vendor_info['store_name'];
                    $img = $product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '';
                    $item_name = $item->get_name();
                    $item_price = '₹' . number_format((float) $item->get_total(), 2);
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;flex-wrap:wrap;gap:12px;">
                    <div style="display:flex;align-items:center;gap:14px;">
                        <?php if ($img) : ?>
                            <img src="<?php echo esc_url($img); ?>" style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid #cbd5e1;" alt="" />
                        <?php else : ?>
                            <div style="width:52px;height:52px;border-radius:10px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:20px;">📦</div>
                        <?php endif; ?>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:#0f172a;line-height:1.3;"><?php echo esc_html($item_name); ?></div>
                            <div style="font-size:12.5px;color:#64748b;margin-top:3px;">
                                Dedicated Seller: <strong style="color:#001553;"><?php echo esc_html($store_name); ?></strong>
                                <span style="display:inline-block;margin-left:6px;padding:1px 6px;background:#e0f2fe;color:#0369a1;border-radius:8px;font-size:10.5px;font-weight:700;">Verified Seller</span>
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-top:2px;"><?php echo esc_html($item_price); ?> • Qty: <?php echo (int) $item->get_quantity(); ?></div>
                        </div>
                    </div>
                    <button type="button" class="button" style="display:inline-flex;align-items:center;gap:8px;padding:9px 18px;background:linear-gradient(135deg, #001553 0%, #0066ff 100%);color:#ffffff;border:none;border-radius:24px;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 4px 12px rgba(0,102,255,0.25);" onclick="if(window.dejoiyOpenSellerChatForOrder){ window.dejoiyOpenSellerChatForOrder(<?php echo (int) $order_id; ?>, '<?php echo esc_js($order_num); ?>', <?php echo (int) $prod_id; ?>, '<?php echo esc_js($item_name); ?>', '<?php echo esc_js($img); ?>', '<?php echo esc_js($item_price); ?>', <?php echo (int) $vendor_id; ?>, '<?php echo esc_js($store_name); ?>'); }">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>Contact Seller</span>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * HOOK: Add "Contact Seller" action to customer orders list
     */
    public function add_order_message_action($actions, $order) {
        if (!$order) return $actions;
        $order_id = $order->get_id();
        $actions['contact_seller'] = [
            'url' => wc_get_account_endpoint_url('messages') . '?order_id=' . $order_id,
            'name' => '💬 Contact Seller',
            'action' => 'contact-seller',
        ];
        return $actions;
    }

    /**
     * WooCommerce My Account Endpoint Registration
     */
    public function register_account_endpoints() {
        add_rewrite_endpoint('messages', EP_ROOT | EP_PAGES);
    }

    public function add_account_messages_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $val) {
            $new_items[$key] = $val;
            if ($key === 'orders') {
                $new_items['messages'] = 'Contact Seller';
            }
        }
        return $new_items;
    }

    // ─── END LEGACY CODE ───

    /**
     * RENDER: Dedicated Customer Messages & Confirmed Orders Hub (/my-account/messages/)
     */
    public function render_account_messages_endpoint() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $email = $user ? $user->user_email : '';

        $customer_orders = [];
        if ($user_id || $email) {
            $customer_orders = wc_get_orders([
                'customer' => array_filter([$user_id, $email]),
                'limit' => 20,
                'status' => ['completed', 'processing', 'on-hold', 'pending', 'cancelled']
            ]);
        }

        $focused_order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        ?>
        <div class="djy-account-messages-hub" style="font-family:'Inter',sans-serif;margin-bottom:40px;">
            <div style="background:linear-gradient(135deg, #000c2c 0%, #001553 100%);color:#ffffff;padding:24px;border-radius:16px;margin-bottom:24px;box-shadow:0 8px 30px rgba(0,21,83,0.15);">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:42px;height:42px;border-radius:12px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;font-size:22px;">💬</div>
                    <div>
                        <h2 style="color:#ffffff;margin:0;font-size:20px;font-weight:800;letter-spacing:-0.3px;">Buyer-Seller Message Center</h2>
                        <p style="color:#94a3b8;margin:4px 0 0 0;font-size:13.5px;">Contact dedicated marketplace sellers for your confirmed orders regarding delivery, tracking, and warranty.</p>
                    </div>
                </div>
            </div>

            <!-- View Toggle Tabs -->
            <div style="display:flex;gap:10px;margin-bottom:20px;border-bottom:1px solid #e2e8f0;padding-bottom:12px;">
                <button type="button" id="djy-tab-orders-btn" class="button" style="background:#001553;color:#ffffff;border-radius:20px;padding:8px 18px;font-size:13px;font-weight:700;border:none;cursor:pointer;" onclick="djySwitchTab('orders')">📦 Your Confirmed Orders (<?php echo count($customer_orders); ?>)</button>
                <button type="button" id="djy-tab-convs-btn" class="button" style="background:#f1f5f9;color:#475569;border-radius:20px;padding:8px 18px;font-size:13px;font-weight:700;border:none;cursor:pointer;" onclick="djySwitchTab('convs')">💬 Active Conversations</button>
            </div>

            <!-- TAB 1: Confirmed Orders & Dedicated Sellers -->
            <div id="djy-pane-orders" style="display:block;">
                <?php if (empty($customer_orders)) : ?>
                    <div style="padding:40px 20px;text-align:center;background:#f8fafc;border-radius:14px;border:1.5px dashed #cbd5e1;">
                        <div style="font-size:36px;margin-bottom:10px;">📦</div>
                        <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0 0 6px 0;">No Confirmed Orders Found</h3>
                        <p style="font-size:13px;color:#64748b;max-width:480px;margin:0 auto 16px auto;">
                            Contact Seller is enabled once an order is confirmed on DEJOIY. Place an order to communicate directly with verified marketplace merchants regarding order tracking and delivery.
                        </p>
                        <a href="<?php echo esc_url(home_url('/shop/')); ?>" class="button" style="background:#0066ff;color:#ffffff;border-radius:20px;padding:10px 22px;font-weight:700;text-decoration:none;display:inline-block;">Browse Marketplace & Shop</a>
                    </div>
                <?php else : ?>
                    <div style="display:flex;flex-direction:column;gap:18px;">
                        <?php foreach ($customer_orders as $o) : 
                            $oid = $o->get_id();
                            $onum = $o->get_order_number();
                            $ostatus = $o->get_status();
                            $ototal = '₹' . number_format((float) $o->get_total(), 2);
                            $odate = wc_format_datetime($o->get_date_created(), 'M d, Y');
                            $is_focused = ($focused_order_id && $focused_order_id === $oid);
                        ?>
                        <div style="background:#ffffff;border:1.5px solid <?php echo $is_focused ? '#0066ff' : '#e2e8f0'; ?>;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.03);<?php if ($is_focused) echo 'box-shadow:0 0 0 3px rgba(0,102,255,0.15);'; ?>">
                            <div style="background:#f8fafc;padding:12px 18px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;border-bottom:1px solid #e2e8f0;">
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span style="font-weight:800;color:#0f172a;font-size:14px;">Order #<?php echo esc_html($onum); ?></span>
                                    <span style="font-size:12px;color:#64748b;">• Placed on <?php echo esc_html($odate); ?></span>
                                    <span style="font-size:11px;font-weight:700;padding:2px 8px;border-radius:10px;background:#e2e8f0;color:#334155;text-transform:capitalize;"><?php echo esc_html($ostatus); ?></span>
                                </div>
                                <div style="font-weight:800;color:#001553;font-size:14px;">Total: <?php echo esc_html($ototal); ?></div>
                            </div>
                            <div style="padding:14px 18px;display:flex;flex-direction:column;gap:12px;">
                                <?php foreach ($o->get_items() as $item) : 
                                    $pid = $item->get_product_id();
                                    $prod = $item->get_product();
                                    $vinfo = self::get_item_vendor_info($pid);
                                    $vid = $vinfo['vendor_id'];
                                    $sname = $vinfo['store_name'];
                                    $pimg = $prod ? wp_get_attachment_image_url($prod->get_image_id(), 'thumbnail') : '';
                                    $iname = $item->get_name();
                                    $iprice = '₹' . number_format((float) $item->get_total(), 2);
                                ?>
                                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:#fbfcfe;border:1px solid #f1f5f9;border-radius:10px;flex-wrap:wrap;gap:10px;">
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <?php if ($pimg) : ?>
                                            <img src="<?php echo esc_url($pimg); ?>" style="width:46px;height:46px;object-fit:cover;border-radius:8px;border:1px solid #cbd5e1;" alt="" />
                                        <?php else : ?>
                                            <div style="width:46px;height:46px;border-radius:8px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:18px;">📦</div>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-size:13.5px;font-weight:700;color:#0f172a;line-height:1.2;"><?php echo esc_html($iname); ?></div>
                                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                                Dedicated Seller: <strong style="color:#001553;"><?php echo esc_html($sname); ?></strong>
                                                <span style="color:#10b981;font-weight:700;margin-left:4px;">✓ Verified Merchant</span>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="button" style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:linear-gradient(135deg, #001553 0%, #0066ff 100%);color:#ffffff;border:none;border-radius:20px;font-weight:700;font-size:12.5px;cursor:pointer;" onclick="if(window.dejoiyOpenSellerChatForOrder){ window.dejoiyOpenSellerChatForOrder(<?php echo (int) $oid; ?>, '<?php echo esc_js($onum); ?>', <?php echo (int) $pid; ?>, '<?php echo esc_js($iname); ?>', '<?php echo esc_js($pimg); ?>', '<?php echo esc_js($iprice); ?>', <?php echo (int) $vid; ?>, '<?php echo esc_js($sname); ?>'); }">
                                        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                        <span>Contact Seller</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB 2: Conversation History -->
            <div id="djy-pane-convs" style="display:none;">
                <div id="djy-account-threads-list">
                    <p style="color:#64748b;font-size:13px;">Loading conversation history...</p>
                </div>
            </div>

            <script>
            function djySwitchTab(tab) {
                var btnOrders = document.getElementById('djy-tab-orders-btn');
                var btnConvs = document.getElementById('djy-tab-convs-btn');
                var paneOrders = document.getElementById('djy-pane-orders');
                var paneConvs = document.getElementById('djy-pane-convs');
                if (tab === 'orders') {
                    btnOrders.style.background = '#001553';
                    btnOrders.style.color = '#ffffff';
                    btnConvs.style.background = '#f1f5f9';
                    btnConvs.style.color = '#475569';
                    paneOrders.style.display = 'block';
                    paneConvs.style.display = 'none';
                } else {
                    btnConvs.style.background = '#001553';
                    btnConvs.style.color = '#ffffff';
                    btnOrders.style.background = '#f1f5f9';
                    btnOrders.style.color = '#475569';
                    paneOrders.style.display = 'none';
                    paneConvs.style.display = 'block';
                    loadBuyerAccountThreads();
                }
            }

            function loadBuyerAccountThreads() {
                var restBase = '/wp-json/dejoiy/v1/messenger/';
                fetch(restBase + 'threads?role=buyer', { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        var box = document.getElementById('djy-account-threads-list');
                        if (!data.success || !data.threads || data.threads.length === 0) {
                            box.innerHTML = '<div style="padding:32px;text-align:center;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">' +
                                                '<div style="font-size:32px;margin-bottom:8px;">📬</div>' +
                                                '<strong style="font-size:14px;color:#0f172a;">No past conversations found</strong>' +
                                                '<p style="font-size:12.5px;color:#64748b;margin-top:4px;">Click "Contact Seller" on any of your confirmed orders above to start messaging.</p>' +
                                            '</div>';
                            return;
                        }

                        var html = '<div style="display:flex;flex-direction:column;gap:12px;">';
                        data.threads.forEach(function(t) {
                            var unreadTag = (parseInt(t.unread_buyer) > 0) ? ' <span style="background:#d9006c;color:#fff;font-size:11px;font-weight:800;padding:2px 7px;border-radius:10px;margin-left:6px;">' + t.unread_buyer + ' new</span>' : '';
                            html += '<div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">' +
                                        '<div>' +
                                            '<div style="font-weight:700;color:#0f172a;font-size:14.5px;">' + escapeHtml(t.vendor_name) + unreadTag + '</div>' +
                                            '<div style="font-size:12.5px;color:#64748b;margin-top:2px;">' + (t.order_number ? '📋 Order #' + escapeHtml(t.order_number) : (t.product_name ? '📦 ' + escapeHtml(t.product_name) : escapeHtml(t.subject))) + '</div>' +
                                            '<div style="font-size:12px;color:#94a3b8;margin-top:4px;">' + escapeHtml(t.last_message_display) + ' • ' + escapeHtml(t.time_ago) + '</div>' +
                                        '</div>' +
                                        '<button type="button" class="button" style="padding:7px 16px;border-radius:20px;font-size:12.5px;font-weight:700;" onclick="if(window.dejoiyOpenSellerChatForOrder){ window.dejoiyOpenSellerChatForOrder(' + (t.order_id || 0) + ', \'' + (t.order_number || '') + '\', ' + (t.product_id || 0) + ', \'' + escapeHtml(t.product_name || '') + '\', \'' + escapeHtml(t.product_image || '') + '\', \'\', ' + t.vendor_id + ', \'' + escapeHtml(t.vendor_name) + '\'); }">Open Chat 💬</button>' +
                                    '</div>';
                        });
                        html += '</div>';
                        box.innerHTML = html;
                    });
            }
            </script>
        </div>
        <?php
    }
    /**
     * Same-origin JSON bridge for the seller hub.
     */
    public static function maybe_handle_bridge() {
        if (empty($_GET['dso_msg_api'])) return;
        if (!is_user_logged_in()) wp_send_json(array('success' => false), 401);
        $m = array('threads' => 'rest_get_threads', 'messages' => 'rest_get_messages', 'send' => 'rest_send_message', 'mark-read' => 'rest_mark_read', 'status' => 'rest_update_status', 'unread' => 'rest_get_unread_count', 'start' => 'rest_start_conversation');
        $e = sanitize_key($_GET['dso_msg_api']);
        if (!isset($m[$e])) wp_send_json(array('success' => false), 404);
        $p = ($_SERVER['REQUEST_METHOD'] === 'POST');
        $r = new WP_REST_Request($p ? 'POST' : 'GET', '/dso-bridge/' . $e);
        $q = array();
        foreach ($_GET as $k => $v) { if ($k !== 'dso_msg_api' && $k !== 'section') $q[$k] = is_scalar($v) ? wp_unslash($v) : $v; }
        $r->set_query_params($q);
        if ($p) { $b = json_decode(file_get_contents('php://input'), true); if (!is_array($b)) $b = array(); foreach ((array) $_POST as $k => $v) { if (!isset($b[$k])) $b[$k] = is_scalar($v) ? wp_unslash($v) : $v; } $r->set_body_params($b); }
        $x = self::instance()->{$m[$e]}($r);
        if ($x instanceof WP_Error) wp_send_json(array('success' => false, 'error' => $x->get_error_message()), 400);
        wp_send_json($x instanceof WP_REST_Response ? $x->get_data() : $x, $x instanceof WP_REST_Response ? $x->get_status() : 200);
    }
}

// Initialize messenger
DSO_Messenger::instance();
add_action('init', array('DSO_Messenger', 'maybe_handle_bridge'), 1);
