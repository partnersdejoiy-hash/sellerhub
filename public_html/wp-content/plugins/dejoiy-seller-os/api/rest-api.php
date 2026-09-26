<?php
/**
 * DSO REST API
 */
if (!defined('ABSPATH')) exit;

class DSO_REST_API {

    public static function register_routes() {
        $namespace = 'dejoiy-seller-os/v1';

        // Dashboard data
        register_rest_route($namespace, '/dashboard', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_dashboard'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        // Products
        register_rest_route($namespace, '/products', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_products'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        register_rest_route($namespace, '/products/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_product'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        register_rest_route($namespace, '/products/(?P<id>\d+)/stock', [
            'methods' => 'POST',
            'callback' => [self::class, 'update_stock'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        // Orders
        register_rest_route($namespace, '/orders', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_orders'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        // Chart data
        register_rest_route($namespace, '/chart/(?P<period>[a-z0-9-]+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_chart_data'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        // Notifications
        register_rest_route($namespace, '/notifications/count', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_notification_count'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);

        // Search
        register_rest_route($namespace, '/search', [
            'methods' => 'GET',
            'callback' => [self::class, 'global_search'],
            'permission_callback' => [self::class, 'check_vendor'],
        ]);
    }

    public static function check_vendor($request) {
        if (!is_user_logged_in()) {
            return new WP_Error('unauthorized', 'Login required', ['status' => 401]);
        }

        $plugin = Dejoiy_Seller_OS::instance();
        if (!$plugin->is_vendor()) {
            return new WP_Error('forbidden', 'Vendor access required', ['status' => 403]);
        }

        return true;
    }

    public static function get_dashboard($request) {
        $user_id = get_current_user_id();
        $dashboard = new DSO_Dashboard();
        $data = $dashboard->get_dashboard_data($user_id);

        return rest_ensure_response($data);
    }

    public static function get_products($request) {
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();
        $products = new DSO_Products();

        return rest_ensure_response($products->get_products($vendor_id));
    }

    public static function get_product($request) {
        $product_id = $request->get_param('id');
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        return rest_ensure_response([
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'status' => $product->get_status(),
            'image' => get_the_post_thumbnail_url($product->get_id(), 'medium'),
            'permalink' => get_permalink($product->get_id()),
        ]);
    }

    public static function update_stock($request) {
        $product_id = $request->get_param('id');
        $stock = intval($request->get_param('stock'));

        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        // SECURITY: Verify vendor ownership — seller A must NEVER update seller B's product
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();
        $product_author = intval(get_post_field('post_author', $product_id));
        $product_vendor = intval(get_post_meta($product_id, '_vendor_id', true));
        $product_wcfm_vendor = intval(get_post_meta($product_id, '_wcfm_vendor', true));

        if ($vendor_id !== $product_author && $vendor_id !== $product_vendor && $vendor_id !== $product_wcfm_vendor) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'You do not own this product.', ['status' => 403]);
            }
        }

        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $product->save();

        return rest_ensure_response(['success' => true, 'stock' => $stock]);
    }

    public static function get_orders($request) {
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();
        $orders = new DSO_Orders();

        return rest_ensure_response($orders->get_orders($vendor_id));
    }

    public static function get_chart_data($request) {
        $period = $request->get_param('period');
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();

        $days = 7;
        switch ($period) {
            case '7d': $days = 7; break;
            case '30d': $days = 30; break;
            case '90d': $days = 90; break;
            case '1y': $days = 365; break;
        }

        global $wpdb;
        $labels = [];
        $sales = [];
        $orders = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));

            $day_sales = 0.0;
            $day_orders = 0;

            // Check if wcfm table exists first
            static $has_wcfm_tbl = null;
            if ($has_wcfm_tbl === null) {
                $has_wcfm_tbl = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}wcfm_marketplace_orders'");
            }

            if ($has_wcfm_tbl) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT COALESCE(SUM(item_total), 0) as sales, COUNT(*) as orders
                    FROM {$wpdb->prefix}wcfm_marketplace_orders
                    WHERE vendor_id = %d AND order_status IN ('wc-completed', 'wc-processing')
                    AND created >= %s AND created <= %s",
                    $vendor_id, $date . ' 00:00:00', $date . ' 23:59:59'
                ));
                if ($row && ($row->sales > 0 || $row->orders > 0)) {
                    $day_sales = floatval($row->sales);
                    $day_orders = intval($row->orders);
                }
            }

            if ($day_sales == 0 && $day_orders == 0) {
                $pids = $wpdb->get_col($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND (post_author = %d OR ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('_vendor_id', '_wcfm_vendor') AND meta_value = %s))",
                    $vendor_id, strval($vendor_id)
                ));
                if (!empty($pids)) {
                    $pid_in = implode(',', array_map('intval', $pids));
                    $day_stats = $wpdb->get_row($wpdb->prepare(
                        "SELECT COALESCE(SUM(oim2.meta_value), 0) as sales, COUNT(DISTINCT oi.order_id) as orders
                         FROM {$wpdb->prefix}woocommerce_order_items oi
                         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id' AND oim.meta_value IN ($pid_in)
                         INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
                         INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
                         WHERE p.post_status IN ('wc-completed', 'wc-processing')
                         AND p.post_date >= %s AND p.post_date <= %s",
                        $date . ' 00:00:00', $date . ' 23:59:59'
                    ));
                    if ($day_stats) {
                        $day_sales = floatval($day_stats->sales ?? 0);
                        $day_orders = intval($day_stats->orders ?? 0);
                    }
                }
            }

            $sales[] = $day_sales;
            $orders[] = $day_orders;
        }

        return rest_ensure_response(compact('labels', 'sales', 'orders'));
    }

    public static function get_notification_count($request) {
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();
        $notifications = new DSO_Notifications();

        return rest_ensure_response(['unread' => $notifications->get_unread_count($vendor_id)]);
    }

    public static function global_search($request) {
        $query = sanitize_text_field($request->get_param('q'));
        if (strlen($query) < 2) {
            return rest_ensure_response(['results' => []]);
        }

        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id();
        $results = [];

        // Search products
        $products = new DSO_Products();
        foreach ($products->get_products($vendor_id, 5) as $p) {
            if (stripos($p['name'], $query) !== false || stripos($p['sku'], $query) !== false) {
                $results[] = [
                    'type' => 'product',
                    'title' => $p['name'],
                    'subtitle' => $p['sku'] ?: $p['category'],
                    'url' => '?seller-hub=edit-product&id=' . $p['id'],
                ];
            }
        }

        // Search orders
        $orders = new DSO_Orders();
        foreach ($orders->get_orders($vendor_id, 20) as $o) {
            if (stripos($o['number'], $query) !== false || stripos($o['customer'], $query) !== false) {
                $results[] = [
                    'type' => 'order',
                    'title' => 'Order #' . $o['number'],
                    'subtitle' => $o['customer'],
                    'url' => '?seller-hub=order-detail&id=' . $o['id'],
                ];
            }
        }

        return rest_ensure_response(['results' => array_slice($results, 0, 10)]);
    }
}
