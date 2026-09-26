<?php
/**
 * DSO Shipping - Fulfillment, Logistics, and Carrier Management for DEJOIY
 */
if (!defined('ABSPATH')) exit;

class DSO_Shipping {

    public function render() {
        ?>
        <div class="dso-page dso-shipping">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Fulfillment</span>
                        <span>/</span>
                        <span>Shipping Overview</span>
                    </div>
                    <h1 class="dso-page-title">Shipping & Logistics Hub</h1>
                    <p class="dso-page-subtitle">Configure automated courier dispatch, warehouse pickup points, and package specifications</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=shipping-tracking" class="dso-btn dso-btn-outline">Track Shipments</a>
                    <a href="?section=shipping-pickup" class="dso-btn dso-btn-primary">+ Add Pickup Address</a>
                </div>
            </div>

            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Default Carrier</span>
                    <span class="dso-stat-val dso-text-primary">Shiprocket</span>
                    <span class="dso-stat-sub">Air & Surface Express</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Avg Dispatch SLA</span>
                    <span class="dso-stat-val dso-text-success">18 Hours</span>
                    <span class="dso-stat-sub">Same day fulfillment</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Coverage Reach</span>
                    <span class="dso-stat-val">29,000+</span>
                    <span class="dso-stat-sub">PIN codes nationwide</span>
                </div>
            </div>

            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>📦 Active Shipments</h3>
                        <p class="dso-text-muted">Monitor orders currently in transit across courier partner hubs.</p>
                        <a href="?section=shipments" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">View Active Shipments →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>📍 Warehouse Pickup Locations</h3>
                        <p class="dso-text-muted">Set verified primary and secondary dispatch hub addresses for daily courier pickup.</p>
                        <a href="?section=shipping-pickup" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Manage Pickup Hubs →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>📐 Packaging Guidelines</h3>
                        <p class="dso-text-muted">Dimensions, volumetric weight formulas, and eco-friendly packing requirements.</p>
                        <a href="?section=shipping-packaging" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Read Standards →</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function shipments() {
        $o_handler = new DSO_Orders();
        $orders = $o_handler->get_orders(0, 50);
        ?>
        <div class="dso-page dso-shipments">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=shipping">Shipping</a>
                        <span>/</span>
                        <span>Shipments</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Shipments & Dispatches</h1>
                    <p class="dso-page-subtitle">Track air waybills, courier handovers, and estimated delivery dates</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Courier</th>
                                    <th>AWB Tracking</th>
                                    <th>Destination</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $o): ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html($o['number']); ?></strong></td>
                                        <td>Shiprocket Express</td>
                                        <td><code>AWB-<?php echo substr(md5($o['id']), 0, 10); ?></code></td>
                                        <td><?php echo esc_html($o['city']); ?></td>
                                        <td><?php echo $o_handler->status_badge($o['status']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function tracking() {
        ?>
        <div class="dso-page dso-shipping-tracking">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=shipping">Shipping</a>
                        <span>/</span>
                        <span>Carrier Tracking</span>
                    </div>
                    <h1 class="dso-page-title">Live Carrier Tracking Lookup</h1>
                    <p class="dso-page-subtitle">Track real-time shipment milestones with integrated national carriers</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body">
                    <div class="dso-form-group dso-mb-4" style="max-width: 480px;">
                        <label class="dso-label">Enter Air Waybill (AWB) / Tracking Number</label>
                        <div class="dso-flex dso-gap-2">
                            <input type="text" class="dso-input" placeholder="e.g., 1423859201" />
                            <button type="button" class="dso-btn dso-btn-primary">Track</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function delivery() {
        ?>
        <div class="dso-page dso-shipping-delivery">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=shipping">Shipping</a>
                        <span>/</span>
                        <span>Delivery Rates</span>
                    </div>
                    <h1 class="dso-page-title">Delivery Rates & Zone Matrix</h1>
                    <p class="dso-page-subtitle">Shipping fee structures across Intra-City, Regional, and National zones</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Shipping Zone</th>
                                    <th>Standard TAT</th>
                                    <th>Base Rate (Up to 500g)</th>
                                    <th>Additional 500g</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Local / Intra-City</strong></td>
                                    <td>24-48 Hours</td>
                                    <td>₹40.00</td>
                                    <td>₹25.00</td>
                                </tr>
                                <tr>
                                    <td><strong>Regional / State</strong></td>
                                    <td>2-3 Days</td>
                                    <td>₹65.00</td>
                                    <td>₹35.00</td>
                                </tr>
                                <tr>
                                    <td><strong>National / Metro to Metro</strong></td>
                                    <td>3-4 Days</td>
                                    <td>₹85.00</td>
                                    <td>₹45.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function pickup() {
        ?>
        <div class="dso-page dso-shipping-pickup">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=shipping">Shipping</a>
                        <span>/</span>
                        <span>Pickup Points</span>
                    </div>
                    <h1 class="dso-page-title">Warehouse Pickup Addresses</h1>
                    <p class="dso-page-subtitle">Locations where couriers arrive daily to collect dispatched shipments</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Primary Warehouse Hub</h3>
                    <span class="dso-badge dso-badge-green">Verified</span>
                </div>
                <div class="dso-card-body">
                    <p><strong>DEJOIY Central Distribution Warehouse</strong></p>
                    <p class="dso-text-muted">Sector 18, Udyog Vihar, Gurugram, Haryana - 122015<br/>Phone: +91 98765 43210 • Contact: Fulfillment Manager</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function packaging() {
        ?>
        <div class="dso-page dso-shipping-packaging">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=shipping">Shipping</a>
                        <span>/</span>
                        <span>Packaging</span>
                    </div>
                    <h1 class="dso-page-title">Packaging & Labeling Guidelines</h1>
                    <p class="dso-page-subtitle">Mandatory standards to prevent in-transit damages and carrier volumetric surcharges</p>
                </div>
            </div>

            <div class="dso-grid-2">
                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Volumetric Weight Formula</h3></div>
                    <div class="dso-card-body">
                        <code>Volumetric Weight (kg) = (Length x Width x Height in cm) / 5000</code>
                        <p class="dso-mt-3 dso-text-muted">Carriers bill whichever is higher between Dead Weight and Volumetric Weight. Use snug boxes without excessive empty space.</p>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header"><h3 class="dso-card-title">Label Placement Rule</h3></div>
                    <div class="dso-card-body">
                        <ul class="dso-clean-list">
                            <li>✓ Print thermal label at minimum 203 DPI</li>
                            <li>✓ Affix flat on largest box surface without folding over edges</li>
                            <li>✓ Do not cover barcode with transparent tape</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Generate pure SVG Code 39 Barcode
     */
    public static function generate_barcode_svg($code, $width = 280, $height = 55) {
        $chars = [
            '0' => '101001101101', '1' => '110100101011', '2' => '101100101011', '3' => '110110010101',
            '4' => '101001101011', '5' => '110100110101', '6' => '101100110101', '7' => '101001011011',
            '8' => '110100101101', '9' => '101100101101', 'A' => '110101001011', 'B' => '101101001011',
            'C' => '110110100101', 'D' => '101011001011', 'E' => '110101100101', 'F' => '101101100101',
            'G' => '101010011011', 'H' => '110101001101', 'I' => '101101001101', 'J' => '101011001101',
            'K' => '110101010011', 'L' => '101101010011', 'M' => '110110101001', 'N' => '101011010011',
            'O' => '110101101001', 'P' => '101101101001', 'Q' => '101010110011', 'R' => '110101011001',
            'S' => '101101011001', 'T' => '101011011001', 'U' => '110010101011', 'V' => '100110101011',
            'W' => '110011010101', 'X' => '100101101011', 'Y' => '110010110101', 'Z' => '100110110101',
            '-' => '100101011011', '.' => '110010101101', ' ' => '100110101101', '$' => '100100100101',
            '/' => '100100101001', '+' => '100101001001', '%' => '101001001001', '*' => '100101101101'
        ];
        $clean = '*' . strtoupper(preg_replace('/[^0-9A-Z\-\. \$\/\+\%]/', '', $code)) . '*';
        $binary = '';
        for ($i = 0; $i < strlen($clean); $i++) {
            $c = $clean[$i];
            if (isset($chars[$c])) {
                $binary .= $chars[$c] . '0';
            }
        }
        $len = strlen($binary);
        if (!$len) return '';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . ($len * 2) . ' ' . $height . '" width="' . $width . '" height="' . $height . '" style="display:block;margin:4px auto;">';
        for ($i = 0; $i < $len; $i++) {
            if ($binary[$i] === '1') {
                $svg .= '<rect x="' . ($i * 2) . '" y="0" width="2" height="' . $height . '" fill="#000000" />';
            }
        }
        $svg .= '</svg>';
        return $svg;
    }

    /**
     * Print 4x6" Thermal Courier Shipping Label (Amazon Easy Ship style)
     */
    public function print_label() {
        $order_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_GET['order_id']) ? intval($_GET['order_id']) : 0);
        $order = $order_id ? wc_get_order($order_id) : null;

        // If no specific order, take latest order or demo
        if (!$order) {
            $recent = wc_get_orders(['limit' => 1, 'return' => 'objects']);
            if (!empty($recent)) {
                $order = $recent[0];
                $order_id = $order->get_id();
            }
        }

        $order_num = $order ? $order->get_order_number() : '1042';
        $carrier = ($order ? get_post_meta($order_id, '_dso_tracking_carrier', true) : '') ?: 'Shiprocket Surface Express';
        $awb = ($order ? get_post_meta($order_id, '_dso_tracking_number', true) : '') ?: ('DJ' . $order_num . rand(100000, 999999) . 'IN');

        $cust_name = $order ? ($order->get_shipping_first_name() ? $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : 'Deepak Sharma';
        $address_1 = $order ? ($order->get_shipping_address_1() ?: $order->get_billing_address_1()) : 'Plot 42, Sector 21';
        $address_2 = $order ? ($order->get_shipping_address_2() ?: $order->get_billing_address_2()) : 'Near Community Center';
        $city = $order ? ($order->get_shipping_city() ?: $order->get_billing_city()) : 'New Delhi';
        $state = $order ? ($order->get_shipping_state() ?: $order->get_billing_state()) : 'Delhi';
        $postcode = $order ? ($order->get_shipping_postcode() ?: $order->get_billing_postcode()) : '110001';
        $phone = $order ? $order->get_billing_phone() : '+91 98765 43210';

        $total_amount = $order ? floatval($order->get_total()) : 1499.00;
        $is_paid = $order ? $order->is_paid() : false;
        $pay_method = $order ? $order->get_payment_method_title() : 'Cash on Delivery';
        $is_cod = !$is_paid && stripos($pay_method, 'cod') !== false || stripos($pay_method, 'cash') !== false;

        $items = [];
        if ($order) {
            foreach ($order->get_items() as $item) {
                $prod = $item->get_product();
                $items[] = [
                    'name' => $item->get_name(),
                    'sku' => $prod ? $prod->get_sku() : 'DJ-SKU-01',
                    'qty' => $item->get_quantity(),
                ];
            }
        } else {
            $items[] = ['name' => 'Premium Printed Anarkali Kurti', 'sku' => 'KURTI-RED-M', 'qty' => 1];
        }

        $hub_code = 'DEL/GUR - HUB ' . substr(crc32($postcode), 0, 3);
        ?>
        <div class="dso-print-page-wrapper">
            <!-- Non-printable Top Action Bar -->
            <div class="dso-print-toolbar dso-no-print">
                <div class="dso-flex dso-align-center dso-gap-3">
                    <a href="?section=order-detail&id=<?php echo $order_id; ?>" class="dso-btn dso-btn-outline">← Back to Order #<?php echo esc_html($order_num); ?></a>
                    <button type="button" class="dso-btn dso-btn-primary" onclick="window.print();">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        Print 4×6 Thermal Label
                    </button>
                    <a href="?section=print-invoice&id=<?php echo $order_id; ?>" class="dso-btn dso-btn-outline">📄 View Tax Invoice</a>
                </div>
                <div class="dso-print-hint">
                    🏷️ <strong>Format:</strong> 4×6 Inches (100mm × 150mm) • Direct Thermal (203/300 DPI) • Margins: None
                </div>
            </div>

            <!-- Standard 4x6" Thermal Courier Label -->
            <div class="dso-thermal-label-container">
                <!-- Carrier & Routing Header -->
                <div class="dso-label-header">
                    <div class="dso-label-carrier-col">
                        <strong class="dso-label-carrier-name"><?php echo esc_html($carrier); ?></strong>
                        <div class="dso-label-service-tag">DEJOIY FULFILLMENT NETWORK</div>
                    </div>
                    <div class="dso-label-hub-col">
                        <div class="dso-label-hub-code"><?php echo esc_html($hub_code); ?></div>
                        <div class="dso-label-hub-sub">SURFACE EXP</div>
                    </div>
                </div>

                <!-- Primary AWB Barcode Block -->
                <div class="dso-label-barcode-block">
                    <?php echo self::generate_barcode_svg($awb, 320, 52); ?>
                    <div class="dso-label-awb-text">AWB: <strong><?php echo esc_html($awb); ?></strong></div>
                </div>

                <!-- COD / Prepaid Notice Box -->
                <div class="dso-label-payment-box <?php echo $is_cod ? 'dso-label-cod' : 'dso-label-prepaid'; ?>">
                    <?php if ($is_cod): ?>
                        <div class="dso-pay-mode-title">CASH ON DELIVERY (COD)</div>
                        <div class="dso-pay-collect-amt">COLLECT CASH: ₹<?php echo number_format($total_amount, 2); ?></div>
                    <?php else: ?>
                        <div class="dso-pay-mode-title">PREPAID PACKAGE</div>
                        <div class="dso-pay-collect-amt">DO NOT COLLECT CASH FROM CUSTOMER</div>
                    <?php endif; ?>
                </div>

                <!-- Destination / Ship-To Block -->
                <div class="dso-label-shipto-block">
                    <div class="dso-label-section-title">SHIP TO:</div>
                    <div class="dso-label-cust-name"><?php echo esc_html($cust_name); ?></div>
                    <div class="dso-label-address">
                        <?php echo esc_html($address_1); ?><?php echo $address_2 ? ', ' . esc_html($address_2) : ''; ?><br/>
                        <?php echo esc_html($city); ?>, <?php echo esc_html($state); ?>
                    </div>
                    <div class="dso-label-pincode-wrap">
                        PIN CODE: <strong class="dso-label-pincode"><?php echo esc_html($postcode); ?></strong>
                    </div>
                    <div class="dso-label-phone">Contact: <?php echo esc_html($phone); ?></div>
                </div>

                <!-- Manifest Items Summary -->
                <div class="dso-label-manifest-block">
                    <table class="dso-label-items-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Item Description</th>
                                <th style="text-align:right;">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><code><?php echo esc_html($it['sku']); ?></code></td>
                                    <td><?php echo esc_html(mb_strimwidth($it['name'], 0, 32, '...')); ?></td>
                                    <td style="text-align:right;"><strong><?php echo intval($it['qty']); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Package Meta & Return Address -->
                <div class="dso-label-footer-grid">
                    <div class="dso-label-return-col">
                        <div class="dso-label-section-title">IF UNDELIVERED, RETURN TO:</div>
                        <strong>DEJOIY Central Logistics Hub</strong><br/>
                        Sector 18, Udyog Vihar, Gurugram, HR - 122015<br/>
                        GSTIN: 06AABCS1429B1Z8 • Phone: 1800-DEJOIY
                    </div>
                    <div class="dso-label-meta-col">
                        <div>Order: <strong>#<?php echo esc_html($order_num); ?></strong></div>
                        <div>Date: <?php echo date('d/m/Y'); ?></div>
                        <div>Weight: <strong>0.45 kg</strong></div>
                        <div class="dso-label-order-barcode">
                            <?php echo self::generate_barcode_svg('ORD' . $order_num, 140, 28); ?>
                        </div>
                    </div>
                </div>

                <!-- Security Routing Line -->
                <div class="dso-label-bottom-routing">
                    ★ DEJOIY EASY SHIP EXPRESS • SECURE TAMPER-EVIDENT TRANSIT • DPIN™ CERTIFIED ★
                </div>
            </div>
        </div>
        <?php
    }
}
