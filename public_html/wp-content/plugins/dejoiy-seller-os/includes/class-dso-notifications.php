<?php
/**
 * DSO Notifications
 */
if (!defined('ABSPATH')) exit;

class DSO_Notifications {

    /**
     * Create a notification record for a seller
     *
     * @param int    $vendor_id
     * @param string $type
     * @param string $title
     * @param string $message
     * @param string $action_url
     * @return int|bool
     */
    public function create($vendor_id, $type = 'info', $title = '', $message = '', $action_url = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'dso_notifications';
        return $wpdb->insert(
            $table,
            [
                'vendor_id'  => intval($vendor_id),
                'type'       => sanitize_text_field($type),
                'title'      => sanitize_text_field($title),
                'message'    => sanitize_textarea_field($message),
                'action_url' => esc_url_raw($action_url),
                'is_read'    => 0,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /**
     * Static helper to create notification
     */
    public static function create_notification($vendor_id, $type = 'info', $title = '', $message = '', $action_url = '') {
        $instance = new self();
        return $instance->create($vendor_id, $type, $title, $message, $action_url);
    }

    /**
     * Bootstrap automated notification hooks
     */
    public static function init() {
        // Order lifecycle updates
        add_action('woocommerce_order_status_changed', [__CLASS__, 'on_order_status_changed'], 20, 4);

        // Product approval and status transitions
        add_action('transition_post_status', [__CLASS__, 'on_product_status_transition'], 20, 3);

        // Low stock & out-of-stock inventory alerts
        add_action('woocommerce_low_stock', [__CLASS__, 'on_low_stock'], 20, 1);
        add_action('woocommerce_no_stock', [__CLASS__, 'on_no_stock'], 20, 1);
    }

    /**
     * Trigger notification on order status change
     */
    public static function on_order_status_changed($order_id, $old_status, $new_status, $order = null) {
        if (!$order && function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        // Find vendors for items in order
        $vendors = [];
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            if (!$product_id) continue;

            $vendor_id = get_post_field('post_author', $product_id);
            if (empty($vendor_id)) {
                $vendor_id = get_post_meta($product_id, '_vendor_id', true);
            }
            if ($vendor_id) {
                $vendors[intval($vendor_id)] = true;
            }
        }

        if (empty($vendors)) {
            $order_vendor = get_post_meta($order_id, '_vendor_id', true);
            if ($order_vendor) {
                $vendors[intval($order_vendor)] = true;
            }
        }

        $formatted_old = ucfirst(str_replace('-', ' ', $old_status));
        $formatted_new = ucfirst(str_replace('-', ' ', $new_status));
        $action_url = '?section=orders&s=' . $order_id;

        foreach (array_keys($vendors) as $vendor_id) {
            $title = "Order #{$order_id}: {$formatted_new}";
            $message = "Order #{$order_id} moved from {$formatted_old} to {$formatted_new}. Check order details for fulfillment.";
            self::create_notification($vendor_id, 'order', $title, $message, $action_url);
        }
    }

    /**
     * Trigger notification on product status transition
     */
    public static function on_product_status_transition($new_status, $old_status, $post) {
        if (!$post || $post->post_type !== 'product' || $new_status === $old_status) {
            return;
        }

        $vendor_id = intval($post->post_author);
        if (empty($vendor_id)) {
            $vendor_id = intval(get_post_meta($post->ID, '_vendor_id', true));
        }
        if (!$vendor_id) {
            return;
        }

        $product_title = get_the_title($post->ID) ?: "Product #{$post->ID}";

        if ($old_status !== 'publish' && $new_status === 'publish') {
            $title = "Listing Approved: " . wp_trim_words($product_title, 6);
            $message = "Your product listing '{$product_title}' has been reviewed, approved, and is now live on DEJOIY.";
            $action_url = '?section=products&tab=publish&s=' . urlencode($product_title);
            self::create_notification($vendor_id, 'product', $title, $message, $action_url);
        } elseif ($new_status === 'pending') {
            $title = "Product In Review: " . wp_trim_words($product_title, 6);
            $message = "Your product listing '{$product_title}' has been submitted for catalog verification.";
            $action_url = '?section=products&tab=all';
            self::create_notification($vendor_id, 'product', $title, $message, $action_url);
        }
    }

    /**
     * Trigger notification on low stock
     */
    public static function on_low_stock($product) {
        if (!is_object($product)) return;
        $product_id = $product->get_id();
        $vendor_id = intval(get_post_field('post_author', $product_id));
        if (empty($vendor_id)) {
            $vendor_id = intval(get_post_meta($product_id, '_vendor_id', true));
        }
        if (!$vendor_id) return;

        $name = $product->get_name();
        $stock = $product->get_stock_quantity();
        $title = "⚠️ Low Stock: " . wp_trim_words($name, 6);
        $message = "Inventory for '{$name}' is running low ({$stock} units remaining). Restock soon to prevent listing pause.";
        $action_url = '?section=inventory-bulk&s=' . urlencode($product->get_sku() ?: $name);
        self::create_notification($vendor_id, 'alert', $title, $message, $action_url);
    }

    /**
     * Trigger notification on zero stock
     */
    public static function on_no_stock($product) {
        if (!is_object($product)) return;
        $product_id = $product->get_id();
        $vendor_id = intval(get_post_field('post_author', $product_id));
        if (empty($vendor_id)) {
            $vendor_id = intval(get_post_meta($product_id, '_vendor_id', true));
        }
        if (!$vendor_id) return;

        $name = $product->get_name();
        $title = "🚨 Out of Stock: " . wp_trim_words($name, 6);
        $message = "Listing '{$name}' has reached 0 units and has been automatically paused from shopper checkout.";
        $action_url = '?section=inventory-bulk&s=' . urlencode($product->get_sku() ?: $name);
        self::create_notification($vendor_id, 'alert', $title, $message, $action_url);
    }

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $notifications = $this->get_notifications($vendor_id);
        $unread_count = $this->get_unread_count($vendor_id);

        // Mark as read
        if (isset($_GET['mark_read'])) {
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'dso_notifications',
                ['is_read' => 1],
                ['vendor_id' => $vendor_id],
                ['%d'],
                ['%d']
            );
            wp_redirect('?section=notifications');
            exit;
        }

        ?>
        <div class="dso-page dso-notifications">
            <div class="dso-page-header">
                <div>
                    <h1>Notifications</h1>
                    <p><?php echo $unread_count ?> unread notification(s)</p>
                </div>
                <div class="dso-page-actions">
                    <?php if ($unread_count > 0): ?>
                        <a href="?section=notifications&mark_read=1" class="dso-btn dso-btn-secondary">Mark All as Read</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="dso-empty-state">
                            <div class="dso-empty-icon">🔔</div>
                            <h3>All caught up!</h3>
                            <p>No notifications at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $n): ?>
                            <div class="dso-notification-item <?php echo !$n['is_read'] ? 'dso-unread' : '' ?>">
                                <div class="dso-notif-icon"><?php echo $this->get_type_icon($n['type']) ?></div>
                                <div class="dso-notif-content">
                                    <h4><?php echo esc_html($n['title']) ?></h4>
                                    <p><?php echo esc_html($n['message']) ?></p>
                                    <span class="dso-notif-time"><?php echo esc_html($n['date']) ?></span>
                                </div>
                                <?php if (!empty($n['action_url'])): ?>
                                    <a href="<?php echo esc_url($n['action_url']) ?>" class="dso-btn dso-btn-sm dso-btn-secondary">View</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_notifications($vendor_id) {
        global $wpdb;
        if (!$vendor_id) return [];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dso_notifications
            WHERE vendor_id = %d ORDER BY created_at DESC LIMIT 50",
            $vendor_id
        ));

        $notifs = [];
        foreach ($rows as $row) {
            $notifs[] = [
                'id' => $row->id,
                'type' => $row->type,
                'title' => $row->title,
                'message' => $row->message,
                'is_read' => $row->is_read,
                'action_url' => $row->action_url,
                'date' => $row->created_at ? date('M j, Y g:i A', strtotime($row->created_at)) : '—',
            ];
        }
        return $notifs;
    }

    public function get_unread_count($vendor_id) {
        global $wpdb;
        if (!$vendor_id) return 0;
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dso_notifications WHERE vendor_id = %d AND is_read = 0",
            $vendor_id
        )));
    }

    private function get_type_icon($type) {
        $icons = [
            'order' => '📦',
            'product' => '🛍️',
            'review' => '⭐',
            'payment' => '💰',
            'shipping' => '🚚',
            'alert' => '⚠️',
            'info' => 'ℹ️',
        ];
        return $icons[$type] ?? '🔔';
    }
}
