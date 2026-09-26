<?php
/**
 * DSO Customers
 */
if (!defined('ABSPATH')) exit;

class DSO_Customers {

    public function render() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        $vendor_id = $plugin->get_vendor_id($user_id);
        $customers = $this->get_customers($vendor_id);

        ?>
        <div class="dso-page dso-customers">
            <div class="dso-page-header">
                <div>
                    <h1>Customers</h1>
                    <p>View customers who purchased from your store</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-filters-bar">
                    <div class="dso-search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" placeholder="Search customers..." id="dso-customer-search" class="dso-input" />
                    </div>
                </div>

                <div class="dso-table-responsive">
                    <table class="dso-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                                <th>Avg. Order</th>
                                <th>Last Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($customers)): ?>
                                <tr class="dso-empty-row">
                                    <td colspan="5">
                                        <div class="dso-empty-state">
                                            <div class="dso-empty-icon">👥</div>
                                            <h3>No customers yet</h3>
                                            <p>Customer data will appear here once you receive orders.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($customers as $c): ?>
                                    <tr>
                                        <td>
                                            <div class="dso-customer-cell">
                                                <div class="dso-avatar"><?php echo strtoupper(substr($c['name'], 0, 1)) ?></div>
                                                <div>
                                                    <span class="dso-customer-name"><?php echo esc_html($c['name']) ?></span>
                                                    <span class="dso-customer-email"><?php echo esc_html($c['email']) ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $c['order_count'] ?></td>
                                        <td><?php echo wc_price($c['total_spent']) ?></td>
                                        <td><?php echo wc_price($c['avg_order']) ?></td>
                                        <td><?php echo esc_html($c['last_order_date']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_customers($vendor_id) {
        if (!$vendor_id) return [];

        $orders = wc_get_orders(['limit' => -1, 'status' => ['processing', 'completed']]);
        $cust_data = [];

        foreach ($orders as $order) {
            $has_vendor_item = false;
            $vendor_total = 0;
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                $author = get_post_field('post_author', $pid);
                $meta_v = get_post_meta($pid, '_vendor_id', true);
                if ($author == $vendor_id || $meta_v == $vendor_id) {
                    $has_vendor_item = true;
                    $vendor_total += floatval($item->get_total());
                }
            }
            if (!$has_vendor_item) continue;

            $cid = $order->get_customer_id();
            $email = $order->get_billing_email();
            $key = $cid > 0 ? 'user_' . $cid : 'guest_' . md5($email);

            if (!isset($cust_data[$key])) {
                $cust_data[$key] = [
                    'name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: 'Customer',
                    'email' => $email ?: '—',
                    'order_count' => 0,
                    'total_spent' => 0.0,
                    'last_order_date' => $order->get_date_created() ? $order->get_date_created()->date('M j, Y') : '—',
                ];
            }

            $cust_data[$key]['order_count']++;
            $cust_data[$key]['total_spent'] += $vendor_total;
        }

        $customers = [];
        foreach ($cust_data as $cd) {
            $cd['avg_order'] = $cd['order_count'] > 0 ? round($cd['total_spent'] / $cd['order_count'], 2) : 0;
            $customers[] = $cd;
        }

        usort($customers, function($a, $b) {
            return $b['total_spent'] <=> $a['total_spent'];
        });

        return $customers;
    }
}
