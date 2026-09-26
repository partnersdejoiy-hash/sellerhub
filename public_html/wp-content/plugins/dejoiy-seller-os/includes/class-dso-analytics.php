<?php
/**
 * DSO Analytics
 */
if (!defined('ABSPATH')) exit;

class DSO_Analytics {

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $data = $this->get_analytics_data($vendor_id);

        ?>
        <div class="dso-page dso-analytics">
            <div class="dso-page-header">
                <div>
                    <h1>Analytics</h1>
                    <p>Insights into your store performance</p>
                </div>
                <div class="dso-page-actions">
                    <div class="dso-chart-filters">
                        <button class="dso-filter-btn active" data-period="7d">7D</button>
                        <button class="dso-filter-btn" data-period="30d">30D</button>
                        <button class="dso-filter-btn" data-period="90d">90D</button>
                        <button class="dso-filter-btn" data-period="1y">1Y</button>
                    </div>
                </div>
            </div>

            <!-- KPI Row -->
            <div class="dso-kpi-grid dso-kpi-grid-4">
                <div class="dso-kpi-card dso-kpi-blue">
                    <div class="dso-kpi-content">
                        <span class="dso-kpi-label">Total Revenue</span>
                        <span class="dso-kpi-value"><?php echo wc_price($data['total_revenue']) ?></span>
                    </div>
                </div>
                <div class="dso-kpi-card dso-kpi-green">
                    <div class="dso-kpi-content">
                        <span class="dso-kpi-label">Total Orders</span>
                        <span class="dso-kpi-value"><?php echo $data['total_orders'] ?></span>
                    </div>
                </div>
                <div class="dso-kpi-card dso-kpi-purple">
                    <div class="dso-kpi-content">
                        <span class="dso-kpi-label">Avg. Order Value</span>
                        <span class="dso-kpi-value"><?php echo wc_price($data['avg_order_value']) ?></span>
                    </div>
                </div>
                <div class="dso-kpi-card dso-kpi-teal">
                    <div class="dso-kpi-content">
                        <span class="dso-kpi-label">Products Sold</span>
                        <span class="dso-kpi-value"><?php echo $data['products_sold'] ?></span>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="dso-grid-2">
                <div class="dso-card dso-card-chart">
                    <div class="dso-card-header"><h3>Revenue Trend</h3></div>
                    <div class="dso-chart-container">
                        <canvas id="dso-revenue-chart"></canvas>
                    </div>
                </div>
                <div class="dso-card dso-card-chart">
                    <div class="dso-card-header"><h3>Orders Trend</h3></div>
                    <div class="dso-chart-container">
                        <canvas id="dso-orders-chart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products by Revenue -->
            <div class="dso-card">
                <div class="dso-card-header"><h3>Top Products by Revenue</h3></div>
                <div class="dso-table-responsive">
                    <table class="dso-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Product</th>
                                <th>Orders</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($data['top_products'])): ?>
                                <tr class="dso-empty-row"><td colspan="4"><div class="dso-empty-inline"><p>No product data yet</p></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($data['top_products'] as $i => $p): ?>
                                    <tr>
                                        <td><?php echo $i + 1 ?></td>
                                        <td><?php echo esc_html($p['name']) ?></td>
                                        <td><?php echo $p['orders'] ?></td>
                                        <td><?php echo wc_price($p['revenue']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            DSO.initAnalytics(<?php echo wp_json_encode($data['chart_data']) ?>);
        });
        </script>
        <?php
    }

    public function get_analytics_data($vendor_id) {
        global $wpdb;

        $total_revenue = 0;
        $total_orders = 0;
        $products_sold = 0;
        $top_products = [];
        $chart_data = ['labels' => [], 'revenue' => [], 'orders' => []];

        if ($vendor_id) {
            $orders = wc_get_orders(['limit' => -1, 'status' => ['processing', 'completed']]);
            $prod_sales = [];
            $daily_sales = [];
            $daily_orders = [];
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $daily_sales[$d] = 0.0;
                $daily_orders[$d] = 0;
            }

            foreach ($orders as $order) {
                $has_vendor_item = false;
                $order_vendor_total = 0;
                $order_date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : '';

                foreach ($order->get_items() as $item) {
                    $pid = $item->get_product_id();
                    $author = get_post_field('post_author', $pid);
                    $meta_v = get_post_meta($pid, '_vendor_id', true);
                    if ($author == $vendor_id || $meta_v == $vendor_id) {
                        $has_vendor_item = true;
                        $qty = $item->get_quantity();
                        $tot = floatval($item->get_total());
                        $order_vendor_total += $tot;
                        $products_sold += $qty;

                        if (!isset($prod_sales[$pid])) {
                            $prod_sales[$pid] = ['orders' => 0, 'revenue' => 0.0, 'product_id' => $pid];
                        }
                        $prod_sales[$pid]['orders'] += $qty;
                        $prod_sales[$pid]['revenue'] += $tot;
                    }
                }

                if ($has_vendor_item) {
                    $total_orders++;
                    $total_revenue += $order_vendor_total;
                    if (isset($daily_sales[$order_date])) {
                        $daily_sales[$order_date] += $order_vendor_total;
                        $daily_orders[$order_date]++;
                    }
                }
            }

            // Top products
            uasort($prod_sales, function($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });
            $top_products = [];
            $count = 0;
            foreach ($prod_sales as $pid => $ps) {
                if ($count++ >= 10) break;
                $prod = wc_get_product($pid);
                $top_products[] = (object)[
                    'product_id' => $pid,
                    'name' => $prod ? $prod->get_name() : 'Product #' . $pid,
                    'orders' => $ps['orders'],
                    'revenue' => $ps['revenue'],
                ];
            }

            // Chart data - last 30 days
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-{$i} days"));
                $chart_data['labels'][] = date('M j', strtotime($d));
                $chart_data['revenue'][] = $daily_sales[$d] ?? 0;
                $chart_data['orders'][] = $daily_orders[$d] ?? 0;
            }
        }

        $avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

        return [
            'total_revenue' => $total_revenue,
            'total_orders' => $total_orders,
            'avg_order_value' => $avg_order_value,
            'products_sold' => $products_sold,
            'top_products' => $top_products,
            'chart_data' => $chart_data,
        ];
    }
}
