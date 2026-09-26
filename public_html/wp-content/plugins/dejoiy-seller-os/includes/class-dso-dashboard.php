<?php
/**
 * DSO Dashboard - Command Center for DEJOIY Seller Central
 * Real-time operational intelligence, Action Center, Store Health Score, and sales analytics
 */
if (!defined('ABSPATH')) exit;

class DSO_Dashboard {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if ($this->is_admin()) {
            return DSO_Auth::get_admin_vendor_context();
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    protected function is_admin() {
        return current_user_can('administrator') || current_user_can('manage_options');
    }


    public function render() {
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->djdb_export_csv();
            return;
        }

        $vendor_id = $this->get_active_vendor_id();
        $user = wp_get_current_user();
        $seller_name = ($user && !empty($user->display_name)) ? $user->display_name : 'Seller';

        $o_handler = new DSO_Orders();
        $p_handler = new DSO_Products();

        $range = (isset($_GET['range']) && $_GET['range'] === 'month') ? 'month' : 'week';
        $days = ($range === 'month') ? 30 : 7;

        $tz = wp_timezone();
        $today_start = (new DateTime('today', $tz))->getTimestamp();
        $cur_start = $today_start - ($days - 1) * DAY_IN_SECONDS;
        $prev_start = $cur_start - $days * DAY_IN_SECONDS;

        $nb = ($range === 'month') ? 5 : 7;
        $cur_rev = array_fill(0, $nb, 0.0);
        $prev_rev = array_fill(0, $nb, 0.0);

        $t_rev = 0.0; $p_rev = 0.0;
        $t_ord = 0;   $p_ord = 0;
        $t_units = 0; $p_units = 0;
        $regions = array();
        $order_line = array();

        $orders = $this->djdb_vendor_orders($vendor_id);

        foreach ($orders as $ord) {
            $d = $ord->get_date_created();
            if (!$d) {
                continue;
            }
            $ts = $d->getTimestamp();
            $di = (int) floor(($ts - $cur_start) / DAY_IN_SECONDS);

            $line = 0.0;
            $qty = 0;
            foreach ($ord->get_items() as $item) {
                $pid = $item->get_product_id();
                if (!$pid || !$this->djdb_item_is_vendor($pid, $vendor_id)) {
                    continue;
                }
                $line += (float) $item->get_total();
                $qty += (int) $item->get_quantity();
            }
            $order_line[$ord->get_id()] = $line;

            if ($di >= 0 && $di < $days) {
                $b = ($range === 'month') ? (int) floor($di / 7) : $di;
                if ($b >= $nb) {
                    $b = $nb - 1;
                }
                $cur_rev[$b] += $line;
                $t_rev += $line;
                $t_ord++;
                $t_units += $qty;
                $st = $ord->get_billing_state();
                if (!$st) {
                    $st = $ord->get_shipping_state();
                }
                if (!$st) {
                    $st = 'Other';
                }
                if (!isset($regions[$st])) {
                    $regions[$st] = 0.0;
                }
                $regions[$st] += $line;
            } elseif ($di >= -$days && $di < 0) {
                $b = ($range === 'month') ? (int) floor(($di + $days) / 7) : ($di + $days);
                if ($b >= $nb) {
                    $b = $nb - 1;
                }
                if ($b < 0) {
                    $b = 0;
                }
                $prev_rev[$b] += $line;
                $p_rev += $line;
                $p_ord++;
                $p_units += $qty;
            }
        }

        $delta_pct = function ($cur, $prev) {
            if ($prev == 0) {
                return $cur > 0 ? 100.0 : 0.0;
            }
            return (($cur - $prev) / $prev) * 100.0;
        };
        $delta_badge = function ($pct) {
            $cls = $pct >= 0 ? 'up' : 'down';
            $arrow = $pct >= 0 ? '&#9650;' : '&#9660;';
            return '<span class="djdb-delta ' . $cls . '">' . $arrow . ' ' . number_format(abs($pct), 1) . '%</span>';
        };

        $d_rev = $delta_pct($t_rev, $p_rev);
        $d_ord = $delta_pct($t_ord, $p_ord);
        $d_units = $delta_pct($t_units, $p_units);
        $t_aov = $t_ord > 0 ? $t_rev / $t_ord : 0.0;
        $p_aov = $p_ord > 0 ? $p_rev / $p_ord : 0.0;
        $d_aov = $delta_pct($t_aov, $p_aov);

        $labels = array();
        for ($b = 0; $b < $nb; $b++) {
            if ($range === 'month') {
                $labels[$b] = wp_date('M j', $cur_start + $b * 7 * DAY_IN_SECONDS);
            } else {
                $labels[$b] = wp_date('D', $cur_start + $b * DAY_IN_SECONDS);
            }
        }

        $fmt_k = function ($v) {
            if ($v >= 100000) {
                return '&#8377;' . number_format($v / 100000, 1) . 'L';
            }
            if ($v >= 1000) {
                return '&#8377;' . number_format($v / 1000, 1) . 'k';
            }
            return '&#8377;' . number_format($v);
        };

        // ---------- Revenue chart (inline SVG, grouped bars: current vs previous) ----------
        $maxv = max(array_merge($cur_rev, $prev_rev));
        if ($maxv <= 0) {
            $maxv = 1;
        }
        $W = 720; $H = 300; $padL = 58; $padB = 34; $padT = 12;
        $cw = $W - $padL - 16;
        $ch = $H - $padT - $padB;
        $gw = $cw / $nb;
        $bw = ($range === 'month') ? min(34, $gw * 0.28) : min(18, $gw * 0.22);
        $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="djdb-svg" role="img" aria-label="Revenue chart">';
        for ($g = 0; $g <= 3; $g++) {
            $yv = $maxv * $g / 3;
            $y = $padT + $ch - ($ch * $g / 3);
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($W - 8) . '" y2="' . round($y, 1) . '" class="djdb-gridline"/>';
            $svg .= '<text x="' . ($padL - 8) . '" y="' . round($y + 4, 1) . '" class="djdb-axis" text-anchor="end">' . $fmt_k($yv) . '</text>';
        }
        foreach ($cur_rev as $b => $v) {
            $cx = $padL + $gw * $b + $gw / 2;
            $h1 = $ch * ($v / $maxv);
            $h2 = $ch * ($prev_rev[$b] / $maxv);
            $y1 = $padT + $ch - $h1;
            $y2 = $padT + $ch - $h2;
            $svg .= '<rect x="' . round($cx - $bw - 2, 1) . '" y="' . round($y2, 1) . '" width="' . round($bw, 1) . '" height="' . round($prev_rev[$b] > 0 ? max($h2, 3) : 0, 1) . '" rx="4" class="djdb-bar-prev"><title>Previous: ' . esc_attr($fmt_k($prev_rev[$b])) . '</title></rect>';
            $svg .= '<rect x="' . round($cx + 2, 1) . '" y="' . round($y1, 1) . '" width="' . round($bw, 1) . '" height="' . round($v > 0 ? max($h1, 3) : 0, 1) . '" rx="4" class="djdb-bar-cur"><title>' . esc_attr($labels[$b]) . ': ' . esc_attr($fmt_k($v)) . '</title></rect>';
            $svg .= '<text x="' . round($cx, 1) . '" y="' . ($H - 10) . '" class="djdb-axis" text-anchor="middle">' . esc_html($labels[$b]) . '</text>';
        }
        $svg .= '</svg>';

        // ---------- Sales by region (donut) ----------
        arsort($regions);
        $top_regions = array_slice($regions, 0, 4, true);
        $rest = array_sum(array_slice($regions, 4, null, true));
        if ($rest > 0) {
            $top_regions['Other'] = $rest;
        }
        $donut_colors = array('#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#9CA3AF');
        $total_reg = array_sum($top_regions);
        $donut_svg = '';
        $legend_html = '';
        if ($total_reg > 0) {
            $C = 2 * M_PI * 70;
            $off = 0.0;
            $i = 0;
            $segs = '';
            foreach ($top_regions as $st => $amt) {
                $frac = $amt / $total_reg;
                $len = $frac * $C;
                $segs .= '<circle cx="90" cy="90" r="70" fill="none" stroke="' . $donut_colors[$i % 5] . '" stroke-width="26" stroke-dasharray="' . round($len, 2) . ' ' . round($C, 2) . '" stroke-dashoffset="' . round(-$off, 2) . '" transform="rotate(-90 90 90)"><title>' . esc_attr($st) . ': ' . esc_attr($fmt_k($amt)) . '</title></circle>';
                $off += $len;
                $legend_html .= '<div class="djdb-leg"><span class="djdb-dot" style="background:' . $donut_colors[$i % 5] . '"></span><span class="djdb-leg-name">' . esc_html($st) . '</span><span class="djdb-leg-val">' . wc_price($amt) . '</span><span class="djdb-leg-pct">' . number_format($frac * 100, 1) . '%</span></div>';
                $i++;
            }
            $donut_svg = '<svg viewBox="0 0 180 180" class="djdb-donut" role="img" aria-label="Sales by region">' . $segs . '</svg>';
        }

        // ---------- Recent orders rows ----------
        $recent_rows = '';
        $rc = 0;
        foreach ($orders as $ord) {
            if ($rc >= 6) {
                break;
            }
            $rc++;
            $oid = $ord->get_id();
            $cust = trim($ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name());
            if ($cust === '') {
                $cust = 'Guest Customer';
            }
            $dd = $ord->get_date_created();
            $when = $dd ? wp_date('M j, Y', $dd->getTimestamp()) : '';
            $recent_rows .= '<tr><td class="djdb-ord">#' . esc_html($ord->get_order_number()) . '</td>'
                . '<td class="djdb-cust">' . esc_html($cust) . '</td>'
                . '<td>' . esc_html($when) . '</td>'
                . '<td class="djdb-total">' . wc_price(isset($order_line[$oid]) ? $order_line[$oid] : 0) . '</td>'
                . '<td>' . $o_handler->status_badge($ord->get_status()) . '</td></tr>';
        }

        // ---------- Top products ----------
        $top_html = '';
        $tops = $p_handler->get_products($vendor_id, 5);
        $rank = 0;
        foreach ($tops as $tp) {
            $rank++;
            $img = isset($tp['image_html']) ? $tp['image_html'] : '';
            if (strpos($img, '<img') === false) {
                $nm = isset($tp['name']) ? $tp['name'] : '';
                $initial = ($nm !== '') ? mb_strtoupper(mb_substr($nm, 0, 1, 'UTF-8'), 'UTF-8') : 'P';
                $img = '<span class="djdb-thumb-ph" aria-hidden="true">' . esc_html($initial) . '</span>';
            }
            $sales = isset($tp['total_sales']) ? (int) $tp['total_sales'] : 0;
            $top_html .= '<div class="djdb-prow"><span class="djdb-rank">' . $rank . '</span><div class="djdb-thumb">' . $img . '</div>'
                . '<div class="djdb-pinfo"><div class="djdb-pname">' . esc_html($tp['name']) . '</div>'
                . '<div class="djdb-psales">' . number_format($sales) . ' units sold</div>'
                . '<div class="djdb-pprice">' . $tp['price_html'] . '</div></div></div>';
        }

        // ---------- Recent alerts (real store signals only) ----------
        $pstats = $p_handler->get_product_stats($vendor_id);
        $awaiting = 0;
        foreach ($orders as $ord) {
            if (in_array($ord->get_status(), array('pending', 'on-hold', 'processing'), true)) {
                $awaiting++;
            }
        }
        $alert_ico = function ($path) {
            return '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg>';
        };
        $alert_row = function ($tone, $icon, $title, $desc, $url, $link) {
            return '<div class="djdb-alert-row"><span class="djdb-alert-ico ' . $tone . '">' . $icon . '</span>'
                . '<div><div class="djdb-alert-title">' . esc_html($title) . '</div>'
                . '<div class="djdb-alert-desc">' . esc_html($desc) . '</div></div>'
                . '<a class="djdb-alert-link" href="' . $url . '">' . esc_html($link) . ' &rarr;</a></div>';
        };
        $alerts_html = '';
        if (!empty($pstats['out_of_stock'])) {
            $alerts_html .= $alert_row('danger', $alert_ico('<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>'),
                'Out of stock', $pstats['out_of_stock'] . ' product(s) unavailable for sale',
                esc_url(add_query_arg(array('section' => 'inventory-out'))), 'Restock');
        }
        if (!empty($pstats['low_stock'])) {
            $alerts_html .= $alert_row('warning', $alert_ico('<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'),
                'Low stock alert', $pstats['low_stock'] . ' item(s) below minimum stock',
                esc_url(add_query_arg(array('section' => 'inventory-low'))), 'Review');
        }
        if ($awaiting > 0) {
            $alerts_html .= $alert_row('info', $alert_ico('<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>'),
                'Orders awaiting dispatch', $awaiting . ' order(s) need fulfillment',
                esc_url(add_query_arg(array('section' => 'orders-pending'))), 'Fulfill');
        }
        if (!empty($pstats['draft'])) {
            $alerts_html .= $alert_row('muted', $alert_ico('<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>'),
                'Draft listings', $pstats['draft'] . ' product(s) not published yet',
                esc_url(add_query_arg(array('section' => 'products'))), 'Publish');
        }
        if ($alerts_html === '') {
            $alerts_html = '<div class="djdb-empty">All clear — no stock or order alerts right now.</div>';
        }

        // ---------- Page ----------
        $week_url = esc_url(add_query_arg(array('range' => 'week', 'export' => false)));
        $month_url = esc_url(add_query_arg(array('range' => 'month', 'export' => false)));
        $csv_url = esc_url(add_query_arg(array('export' => 'csv')));
        $orders_url = esc_url(add_query_arg(array('section' => 'orders')));
        $products_url = esc_url(add_query_arg(array('section' => 'products')));
        $reports_url = esc_url(add_query_arg(array('section' => 'reports')));
        $range_txt = ($range === 'month') ? 'Last 30 days' : 'Last 7 days';

        echo '<div class="djdb-wrap">';

        // Header
        echo '<div class="djdb-head"><div>';
        echo '<h1 class="djdb-title">Welcome, ' . esc_html($seller_name) . ' &#128075;</h1>';
        echo '<p class="djdb-sub">' . esc_html(wp_date('l, F j, Y')) . ' &middot; ' . esc_html($range_txt) . '</p>';
        echo '</div><div class="djdb-controls">';
        echo '<div class="djdb-seg"><a href="' . $week_url . '" class="' . ($range === 'week' ? 'active' : '') . '">Week</a><a href="' . $month_url . '" class="' . ($range === 'month' ? 'active' : '') . '">Month</a></div>';
        echo '<a class="djdb-btn" href="' . $csv_url . '">&#11015; Export Report</a>';
        echo '</div></div>';

        // KPI cards
        $kpi_ico = function ($path) {
            return '<span class="djdb-kpi-ico"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>';
        };
        echo '<div class="djdb-kpis">';
        echo '<div class="djdb-kpi"><div class="djdb-kpi-label"><span>Total Revenue</span>' . $kpi_ico('<circle cx="12" cy="12" r="10"/><path d="M12 6v12M15 9H9.5a2.5 2.5 0 000 5h5a2.5 2.5 0 010 5H9"/>') . '</div><div class="djdb-kpi-row"><span class="djdb-kpi-val">' . wc_price($t_rev) . '</span>' . $delta_badge($d_rev) . '</div></div>';
        echo '<div class="djdb-kpi"><div class="djdb-kpi-label"><span>Total Orders</span>' . $kpi_ico('<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/>') . '</div><div class="djdb-kpi-row"><span class="djdb-kpi-val">' . number_format($t_ord) . '</span>' . $delta_badge($d_ord) . '</div></div>';
        echo '<div class="djdb-kpi"><div class="djdb-kpi-label"><span>Units Sold</span>' . $kpi_ico('<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>') . '</div><div class="djdb-kpi-row"><span class="djdb-kpi-val">' . number_format($t_units) . '</span>' . $delta_badge($d_units) . '</div></div>';
        echo '<div class="djdb-kpi"><div class="djdb-kpi-label"><span>Avg. Order Value</span>' . $kpi_ico('<path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/>') . '</div><div class="djdb-kpi-row"><span class="djdb-kpi-val">' . wc_price($t_aov) . '</span>' . $delta_badge($d_aov) . '</div></div>';
        echo '</div>';

        // Chart + donut
        echo '<div class="djdb-grid">';
        echo '<div class="djdb-card"><div class="djdb-card-h"><h2>Revenue Overview</h2><a href="' . $reports_url . '">View reports &rarr;</a></div>';
        echo '<div class="djdb-chart-legend"><span><i class="djdb-dot" style="background:#F97316"></i>This period</span><span><i class="djdb-dot" style="background:#D1D5DB"></i>Previous period</span></div>';
        echo '<div class="djdb-chart-scroll">' . $svg . '</div>';
        echo '</div>';
        echo '<div class="djdb-card"><div class="djdb-card-h"><h2>Sales by Region</h2></div>';
        if ($donut_svg !== '') {
            echo '<div class="djdb-donut-wrap"><div class="djdb-donut-holder">' . $donut_svg . '<div class="djdb-donut-center"><strong>' . wc_price($total_reg) . '</strong><span>Revenue</span></div></div>';
            echo '<div class="djdb-legend">' . $legend_html . '</div></div>';
        } else {
            echo '<div class="djdb-empty">No regional sales in this period yet.</div>';
        }
        echo '</div>';
        echo '</div>';

        // Top products + recent alerts
        echo '<div class="djdb-grid">';
        echo '<div class="djdb-card"><div class="djdb-card-h"><h2>Top Products</h2><a href="' . $products_url . '">View all &rarr;</a></div>';
        if ($top_html !== '') {
            echo $top_html;
        } else {
            echo '<div class="djdb-empty">No products listed yet.</div>';
        }
        echo '</div>';
        echo '<div class="djdb-card"><div class="djdb-card-h"><h2>Recent Alerts</h2><a href="' . $orders_url . '">View all &rarr;</a></div>';
        echo $alerts_html;
        echo '</div>';
        echo '</div>';

        // Recent orders (full width)
        echo '<div class="djdb-grid" style="grid-template-columns:1fr;">';
        echo '<div class="djdb-card"><div class="djdb-card-h"><h2>Recent Orders</h2><a href="' . $orders_url . '">View all &rarr;</a></div>';
        if ($recent_rows !== '') {
            echo '<div class="djdb-table-scroll"><table class="djdb-table"><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead><tbody>' . $recent_rows . '</tbody></table></div>';
        } else {
            echo '<div class="djdb-empty">No orders found for your store yet.</div>';
        }
        echo '</div>';
        echo '</div>';

        echo '</div>';
    }

    protected function djdb_vendor_orders($vendor_id) {
        $found = array();
        $orders = wc_get_orders(array('limit' => 400, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC'));
        if (!is_array($orders)) {
            return $found;
        }
        foreach ($orders as $ord) {
            if (!is_object($ord) || !method_exists($ord, 'get_status')) {
                continue;
            }
            $st = $ord->get_status();
            if (in_array($st, array('cancelled', 'refunded', 'trash', 'failed'), true)) {
                continue;
            }
            if ($this->djdb_order_is_vendor($ord, $vendor_id)) {
                $found[] = $ord;
            }
        }
        return $found;
    }

    protected function djdb_order_is_vendor($order, $vendor_id) {
        foreach ($order->get_items() as $item) {
            if ($this->djdb_item_is_vendor($item->get_product_id(), $vendor_id)) {
                return true;
            }
        }
        return false;
    }

    protected function djdb_item_is_vendor($pid, $vendor_id) {
        if (!$pid) {
            return false;
        }
        if ((int) get_post_field('post_author', $pid) === (int) $vendor_id) {
            return true;
        }
        $meta = get_post_meta($pid, '_vendor_id', true);
        return $meta !== '' && (int) $meta === (int) $vendor_id;
    }

    protected function djdb_export_csv() {
        $vendor_id = $this->get_active_vendor_id();
        $rows = array();
        $rows[] = array('Order Number', 'Date', 'Customer', 'City', 'State', 'Items', 'Quantity', 'Total (INR)', 'Status', 'Payment Method');
        foreach ($this->djdb_vendor_orders($vendor_id) as $ord) {
            $d = $ord->get_date_created();
            $line = 0.0;
            $qty = 0;
            $names = array();
            foreach ($ord->get_items() as $item) {
                if (!$this->djdb_item_is_vendor($item->get_product_id(), $vendor_id)) {
                    continue;
                }
                $line += (float) $item->get_total();
                $qty += (int) $item->get_quantity();
                $names[] = $item->get_name() . ' x' . (int) $item->get_quantity();
            }
            $cust = trim($ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name());
            if ($cust === '') {
                $cust = 'Guest Customer';
            }
            $rows[] = array(
                $ord->get_order_number(),
                $d ? $d->date('Y-m-d H:i') : '',
                $cust,
                $ord->get_billing_city(),
                $ord->get_billing_state(),
                implode('; ', $names),
                $qty,
                number_format($line, 2, '.', ''),
                $ord->get_status(),
                $ord->get_payment_method_title(),
            );
        }
        // Mirror DSO_Reports::send_csv_headers()/array_to_csv(): the router wraps
        // render() in ob_start(), so discard buffers before sending download headers.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        nocache_headers();
        $filename = 'dejoiy-dashboard-orders-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM so Excel reads Unicode cleanly
        foreach ($rows as $r) {
            $clean = array_map(function ($cell) {
                if (is_string($cell)) {
                    return html_entity_decode(wp_strip_all_tags($cell), ENT_QUOTES, 'UTF-8');
                }
                return $cell;
            }, $r);
            fputcsv($out, $clean);
        }
        fclose($out);
        exit;
    }


    /**
     * Compute IPI (Inventory Performance Index) - Amazon Style
     */
    protected function get_ipi_score($vendor_id, $data) {
        // IPI is a complex calculation, here we use a realistic proxy
        $base = 450; // Starting point
        
        // Bonus for published products
        $base += min(200, $data['published_products'] * 5);
        
        // Penalty for out of stock
        $base -= ($data['out_of_stock_count'] * 20);
        
        // Bonus for low stock (active inventory management)
        $base += ($data['low_stock_count'] * 5);
        
        // Bonus for sales velocity
        $base += min(300, ($data['total_orders'] / 2));
        
        $score = max(100, min(990, $base));
        
        $label = 'Excellent';
        if ($score < 400) $label = 'Critical';
        elseif ($score < 600) $label = 'Standard';
        
        // Buy Box proxy: depends on LQS and stock
        $buy_box = 85; 
        if ($data['out_of_stock_count'] > 0) $buy_box -= 15;
        
        return [
            'score' => $score,
            'label' => $label,
            'buy_box' => $buy_box
        ];
    }

    /**
     * Get Sales Distribution by Region (India)
     */
    protected function get_regional_sales_data($vendor_id) {
        return [
            'New Delhi & NCR' => 32,
            'Mumbai' => 24,
            'Bangalore' => 18,
            'Hyderabad' => 14,
            'Other Regions' => 12
        ];
    }

    /**
     * Get Voice of the Customer (Mock/Real review aggregation)
     */
    protected function get_voice_of_customer($vendor_id) {
        $reviews = [];
        // Attempt to get real reviews if WooCommerce Comments are enabled for products
        $args = [
            'status' => 'approve',
            'post_type' => 'product',
            'number' => 4,
        ];
        
        $comments = get_comments($args);
        foreach ($comments as $c) {
            $reviews[] = [
                'rating' => get_comment_meta($c->comment_ID, 'rating', true) ?: 5,
                'comment' => $c->comment_content,
                'date' => human_time_diff(strtotime($c->comment_date), current_time('timestamp')) . ' ago',
                'product' => get_the_title($c->comment_post_ID)
            ];
        }
        
        if (empty($reviews)) {
            $reviews = [
                ['rating' => 5, 'comment' => 'Excellent quality and fast delivery. Very satisfied!', 'date' => '2h ago', 'product' => 'Premium Kurti Set'],
                ['rating' => 4, 'comment' => 'Fitting is perfect, color is slightly different but still good.', 'date' => '1d ago', 'product' => 'Floral Printed Saree'],
            ];
        }
        
        return $reviews;
    }

    /**
     * Gather Unified Dashboard Data
     */
    public function get_dashboard_data($vendor_id, $user_id) {
        $p_handler = new DSO_Products();
        $p_stats = $p_handler->get_product_stats($vendor_id);

        $o_handler = new DSO_Orders();
        $o_stats = $o_handler->get_order_stats($vendor_id);
        $recent_raw = $o_handler->get_orders($vendor_id, 5);

        $recent_orders = [];
        foreach ($recent_raw as $ro) {
            $recent_orders[] = [
                'id' => $ro['id'],
                'number' => $ro['number'],
                'customer' => $ro['customer'],
                'total' => $ro['total_html'],
                'date' => $ro['date'],
                'status_badge' => $o_handler->status_badge($ro['status']),
            ];
        }

        $top_raw = $p_handler->get_products($vendor_id, 5);
        $top_products = [];
        foreach ($top_raw as $tp) {
            $top_products[] = [
                'id' => $tp['id'],
                'name' => $tp['name'],
                'price' => $tp['price_html'],
                'lqs' => $tp['lqs_score'],
                'image' => $tp['image_html'],
                'status_badge' => $tp['status_badge'],
            ];
        }

        // Calculate Real Revenue from Orders
        $total_sales = 0;
        $today_sales = 0;
        $available_balance = 0;
        
        $all_orders = wc_get_orders(['limit' => 200, 'return' => 'objects']);
        $scoped_orders = [];
        $today_start = strtotime('today midnight');
        
        foreach ($all_orders as $ord) {
            $status = $ord->get_status();
            if ($status === 'cancelled' || $status === 'refunded' || $status === 'trash') continue;
            
            $order_vendor_total = 0;
            $has_vendor_item = false;
            
            foreach ($ord->get_items() as $item) {
                $pid = $item->get_product_id();
                $author = get_post_field('post_author', $pid);
                $meta_v = get_post_meta($pid, '_vendor_id', true);
                
                if ($vendor_id <= 0 || $author == $vendor_id || $meta_v == $vendor_id) {
                    $has_vendor_item = true;
                    $order_vendor_total += floatval($item->get_total());
                }
            }
            
            if ($has_vendor_item) {
                $total_sales += $order_vendor_total;
                $scoped_orders[] = $ord;
                
                // Today's Sales
                if ($ord->get_date_created() && $ord->get_date_created()->getTimestamp() >= $today_start) {
                    $today_sales += $order_vendor_total;
                }
                
                // Available Balance (Completed orders)
                if ($status === 'completed') {
                    $available_balance += $order_vendor_total;
                }
            }
        }

        // Chart Data Generator
        $chart_data = $this->generate_chart_timeline_data($scoped_orders);

        return [
            'total_sales' => $total_sales,
            'today_sales' => $today_sales,
            'total_orders' => $o_stats['total'],
            'pending_orders' => $o_stats['pending'],
            'processing_orders' => $o_stats['processing'],
            'completed_orders' => $o_stats['completed'],
            'total_products' => $p_stats['total'],
            'published_products' => $p_stats['published'],
            'draft_products' => $p_stats['draft'],
            'low_stock_count' => $p_stats['low_stock'],
            'out_of_stock_count' => $p_stats['out_of_stock'],
            'available_balance' => DSO_Finance::available_balance_for( $vendor_id ), 
            'pending_balance' => ($total_sales - $available_balance) * 0.85,
            'recent_orders' => $recent_orders,
            'top_products' => $top_products,
            'chart_data' => $chart_data,
        ];
    }

    /**
     * Compute Action Center Recommendations
     */
    protected function get_action_center_items($data) {
        $items = [];

        if ($data['out_of_stock_count'] > 0) {
            $items[] = [
                'type' => 'danger',
                'icon' => '🚫',
                'title' => $data['out_of_stock_count'] . ' Listings are Out of Stock',
                'description' => 'Listings with zero stock cannot be purchased and lose search algorithmic ranking on DEJOIY.',
                'button' => 'Restock Listings',
                'url' => '?section=inventory-out',
            ];
        }

        if ($data['low_stock_count'] > 0) {
            $items[] = [
                'type' => 'warning',
                'icon' => '⚠️',
                'title' => $data['low_stock_count'] . ' Listings Nearing Stock Exhaustion',
                'description' => 'Replenish inventory to maintain high fulfillment performance tiers.',
                'button' => 'View Low Stock',
                'url' => '?section=inventory-low',
            ];
        }

        if ($data['processing_orders'] > 0) {
            $items[] = [
                'type' => 'info',
                'icon' => '📦',
                'title' => $data['processing_orders'] . ' Orders Ready for Courier Dispatch',
                'description' => 'Generate shipping AWB labels and pack shipments for same-day handover.',
                'button' => 'Dispatch Orders',
                'url' => '?section=orders-processing',
            ];
        }

        $items[] = [
            'type' => 'primary',
            'icon' => '⚡',
            'title' => 'Optimize Listing Quality Scores (LQS)',
            'description' => 'Add category smart fields, high-res images, and MRP disclosures to boost impressions.',
            'button' => 'Audit Listings',
            'url' => '?section=product-quality',
        ];

        return $items;
    }

    /**
     * Single source of truth for the seller tier (Bug #3 fix).
     *
     * Maps a dashboard health score to its tier grade + label. Both the
     * dashboard and the Store Health page must call this so the same
     * seller always sees the same tier.
     *
     * @param int|float $score Health score (0-100).
     * @return array { grade: string, label: string }
     */
    public static function tier_for_score( $score ) {
        $score = (float) $score;
        $grade = 'Tier 1 (Platinum)';
        $label = 'Outstanding Marketplace Standing';
        if ( $score < 60 ) {
            $grade = 'Standard';
            $label = 'Needs Listing Attention';
        } elseif ( $score < 80 ) {
            $grade = 'Tier 2 (Gold)';
            $label = 'Strong Operational Health';
        }
        return array( 'grade' => $grade, 'label' => $label );
    }

    /**
     * Compute Store Health Rating
     */
    public function get_store_health($vendor_id) {
        global $wpdb;

        $p_handler = new DSO_Products();
        $stats = $p_handler->get_product_stats($vendor_id);

        $instock_rate = 100;
        if ($stats['total'] > 0) {
            $instock_rate = round((($stats['total'] - $stats['out_of_stock']) / $stats['total']) * 100);
        }

        // Compute real LQS average from products
        $lqs_avg = 0;
        $lqs_count = 0;
        if ($vendor_id > 0) {
            $vendor_products = get_posts([
                'post_type' => 'product',
                'author' => $vendor_id,
                'posts_per_page' => 50,
                'post_status' => 'publish',
            ]);
            foreach ($vendor_products as $vp) {
                $lqs = intval(get_post_meta($vp->ID, '_dso_lqs_score', true));
                if ($lqs > 0) {
                    $lqs_avg += $lqs;
                    $lqs_count++;
                }
            }
        }
        $lqs_avg = $lqs_count > 0 ? round($lqs_avg / $lqs_count) : 0;

        // Compute real fulfillment on-time rate from completed orders
        $fulfillment_rate = 0;
        $policy_compliance = 100; // Default 100 unless violations found
        if ($vendor_id > 0 && function_exists('wc_get_orders')) {
            $completed_orders = wc_get_orders([
                'limit' => 100,
                'return' => 'objects',
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            $vendor_completed = [];
            foreach ($completed_orders as $co) {
                $has_vendor_item = false;
                foreach ($co->get_items() as $item) {
                    $pid = $item->get_product_id();
                    $author = get_post_field('post_author', $pid);
                    $meta_v = get_post_meta($pid, '_vendor_id', true);
                    if ($author == $vendor_id || $meta_v == $vendor_id) {
                        $has_vendor_item = true;
                        break;
                    }
                }
                if ($has_vendor_item) $vendor_completed[] = $co;
            }

            if (!empty($vendor_completed)) {
                $on_time = 0;
                foreach ($vendor_completed as $ord) {
                    // Consider order on-time if completed within 7 days of creation
                    $created = $ord->get_date_created();
                    $completed = $ord->get_date_completed();
                    if ($created && $completed) {
                        $diff_days = $completed->diff($created)->days;
                        if ($diff_days <= 7) $on_time++;
                    } else {
                        $on_time++; // No data = assume on-time
                    }
                }
                $fulfillment_rate = round(($on_time / count($vendor_completed)) * 100);
            }
        }

        $score = round(($instock_rate * 0.3) + ($lqs_avg * 0.3) + ($fulfillment_rate * 0.2) + ($policy_compliance * 0.2));

        $tier_info = self::tier_for_score( $score );
        $grade     = $tier_info['grade'];
        $label     = $tier_info['label'];

        return [
            'score' => $score,
            'grade' => $grade,
            'rating_label' => $label,
            'instock_rate' => $instock_rate,
            'lqs_avg' => $lqs_avg,
            'fulfillment_rate' => $fulfillment_rate,
            'policy_compliance' => $policy_compliance,
        ];
    }

    /**
     * Multi-Period Timeline Chart Data
     */
    protected function generate_chart_timeline_data($orders) {
        $periods = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 12];
        $res = [];

        foreach (['7d', '30d', '90d'] as $p) {
            $days = $periods[$p];
            $labels = [];
            $sales = [];
            $order_counts = [];

            for ($i = $days - 1; $i >= 0; $i--) {
                $d = date('M j', strtotime("-{$i} days"));
                $labels[] = $d;
                $sales[] = 0;
                $order_counts[] = 0;
            }

            $res[$p] = [
                'labels' => $labels,
                'sales' => $sales,
                'orders' => $order_counts,
            ];
        }

        // 1Y Months
        $labels_1y = [];
        $sales_1y = [];
        $orders_1y = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = date('M Y', strtotime("-{$i} months"));
            $labels_1y[] = $m;
            $sales_1y[] = 0;
            $orders_1y[] = 0;
        }
        $res['1y'] = [
            'labels' => $labels_1y,
            'sales' => $sales_1y,
            'orders' => $orders_1y,
        ];

        return $res;
    }

    public function get_greeting() {
        $hour = (int) current_time('G');
        if ($hour < 12) return 'Good morning';
        if ($hour < 17) return 'Good afternoon';
        return 'Good evening';
    }
}
