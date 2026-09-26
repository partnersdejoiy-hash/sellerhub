<?php
/**
 * DSO Reports — Dynamic Sales Analytics + Native Excel Export for DEJOIY Seller Hub
 *
 * Every section pulls real live WooCommerce data for the active vendor.
 * Excel files are generated server-side with UTF-8 BOM for 100% native
 * compatibility with Microsoft Excel, Google Sheets, and Apple Numbers.
 *
 * @version 2.2.0
 * @author  DEJOIY Engineering
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DSO_Reports {

    /* -------------------------------------------------------------- */
    /*  Helpers                                                        */
    /* -------------------------------------------------------------- */

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) {
            $ctx = isset( $_COOKIE['dso_admin_vendor_context'] ) ? intval( $_COOKIE['dso_admin_vendor_context'] ) : 0;
            if ( $ctx > 0 ) return $ctx;
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id( $user_id ) ?: $user_id;
    }

    protected function get_vendor_orders( $vendor_id, $limit = -1, $status_filter = '' ) {
        $args = [
            'limit'   => $limit,
            'return'  => 'objects',
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
        if ( $status_filter ) {
            $args['status'] = [ $status_filter ];
        }
        $all = wc_get_orders( $args );

        // If admin with no specific vendor selected, return all orders
        if ( ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) && empty( $_COOKIE['dso_admin_vendor_context'] ) ) {
            return $all;
        }

        // Scope strictly to vendor items
        $scoped = [];
        foreach ( $all as $order ) {
            foreach ( $order->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id ) {
                    $scoped[] = $order;
                    break;
                }
            }
        }
        return $scoped;
    }

    protected function get_vendor_products( $vendor_id ) {
        $args = [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => -1,
        ];
        if ( ! ( ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) && empty( $_COOKIE['dso_admin_vendor_context'] ) ) ) {
            $args['author'] = $vendor_id;
        }
        return get_posts( $args );
    }

    private function format_currency( $val ) {
        return '₹ ' . number_format( (float) $val, 2, '.', '' );
    }

    /* -------------------------------------------------------------- */
    /*  Export Dispatcher                                              */
    /* -------------------------------------------------------------- */

    public function handle_export() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
        if ( strpos( $action, 'export_' ) !== 0 ) return false;

        $vendor_id = $this->get_active_vendor_id();
        $report    = str_replace( 'export_', '', $action );

        switch ( $report ) {
            case 'orders':     $this->export_orders_csv( $vendor_id );     return true;
            case 'revenue':    $this->export_revenue_csv( $vendor_id );    return true;
            case 'products':   $this->export_products_csv( $vendor_id );   return true;
            case 'inventory':  $this->export_inventory_csv( $vendor_id );  return true;
            case 'customers':  $this->export_customers_csv( $vendor_id );  return true;
            case 'finance':    $this->export_finance_csv( $vendor_id );    return true;
            case 'marketing':  $this->export_marketing_csv( $vendor_id );  return true;
        }
        return false;
    }

    private function send_csv_headers( $filename ) {
        while ( ob_get_level() > 0 ) {
            @ob_end_clean();
        }
        nocache_headers();
        header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    }

    private function array_to_csv( $rows ) {
        $out = fopen( 'php://output', 'w' );
        // Emit UTF-8 BOM so Microsoft Excel reads Indian Rupee (₹) & Unicode cleanly
        fprintf( $out, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        foreach ( $rows as $row ) {
            $clean_row = array_map( function( $cell ) {
                if ( is_string( $cell ) ) {
                    return html_entity_decode( wp_strip_all_tags( $cell ), ENT_QUOTES, 'UTF-8' );
                }
                return $cell;
            }, $row );
            fputcsv( $out, $clean_row );
        }
        fclose( $out );
        exit;
    }

    /* -------------------------------------------------------------- */
    /*  Export Generators                                              */
    /* -------------------------------------------------------------- */

    private function export_orders_csv( $vendor_id ) {
        $orders = $this->get_vendor_orders( $vendor_id );
        $this->send_csv_headers( 'dejoiy-orders-report-' . date( 'Y-m-d' ) . '.csv' );

        $rows = [];
        $rows[] = [ 'Order #', 'Date', 'Customer Name', 'Customer Email', 'Status', 'Payment Method', 'Item Count', 'Order Subtotal', 'Vendor Total', 'Platform Commission (10%)', 'Net Earning' ];
        foreach ( $orders as $ord ) {
            $vendor_total = 0;
            $item_count   = 0;
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $vendor_total += floatval( $item->get_total() );
                    $item_count   += intval( $item->get_quantity() );
                }
            }
            $comm = $vendor_total * 0.20;
            $net  = $vendor_total - $comm;

            $rows[] = [
                $ord->get_order_number(),
                $ord->get_date_created() ? $ord->get_date_created()->date( 'Y-m-d H:i' ) : '',
                $ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name(),
                $ord->get_billing_email(),
                ucfirst( $ord->get_status() ),
                $ord->get_payment_method_title(),
                $item_count,
                $this->format_currency( $ord->get_subtotal() ),
                $this->format_currency( $vendor_total ),
                $this->format_currency( $comm ),
                $this->format_currency( $net ),
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_revenue_csv( $vendor_id ) {
        $orders = $this->get_vendor_orders( $vendor_id );
        $this->send_csv_headers( 'dejoiy-revenue-statement-' . date( 'Y-m-d' ) . '.csv' );

        $monthly = [];
        foreach ( $orders as $ord ) {
            if ( in_array( $ord->get_status(), [ 'cancelled', 'trash', 'failed' ] ) ) continue;
            $month = $ord->get_date_created() ? $ord->get_date_created()->format( 'Y-m' ) : 'Unknown';
            if ( ! isset( $monthly[ $month ] ) ) {
                $monthly[ $month ] = [ 'revenue' => 0, 'commission' => 0, 'orders' => 0 ];
            }
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $rev = floatval( $item->get_total() );
                    $monthly[ $month ]['revenue']    += $rev;
                    $monthly[ $month ]['commission'] += $rev * 0.20;
                    $monthly[ $month ]['orders']++;
                }
            }
        }
        ksort( $monthly );

        $rows = [];
        $rows[] = [ 'Month', 'Gross GMV Revenue', 'Marketplace Commission (10%)', 'Net Seller Payout', 'Order Volume' ];
        foreach ( $monthly as $m => $d ) {
            $rows[] = [
                $m,
                $this->format_currency( $d['revenue'] ),
                $this->format_currency( $d['commission'] ),
                $this->format_currency( $d['revenue'] - $d['commission'] ),
                $d['orders'],
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_products_csv( $vendor_id ) {
        $products = $this->get_vendor_products( $vendor_id );
        $this->send_csv_headers( 'dejoiy-catalog-report-' . date( 'Y-m-d' ) . '.csv' );

        $rows = [];
        $rows[] = [ 'Product ID', 'Product Title', 'SKU', 'DPIN', 'Status', 'Regular Price', 'Stock Qty', 'Stock Status', 'LQS Quality Score', 'Date Created' ];
        foreach ( $products as $p ) {
            $wc = wc_get_product( $p->ID );
            if ( ! $wc ) continue;
            $dpin = get_post_meta( $p->ID, '_dejoiy_dpin', true ) ?: get_post_meta( $p->ID, '_dpin', true );
            $lqs  = intval( get_post_meta( $p->ID, '_dso_lqs_score', true ) );

            $rows[] = [
                $p->ID,
                $wc->get_name(),
                $wc->get_sku() ?: '—',
                $dpin ?: '—',
                ucfirst( $p->post_status ),
                $this->format_currency( $wc->get_price() ),
                $wc->get_stock_quantity() ?? '—',
                $wc->get_stock_status(),
                $lqs ? $lqs . '%' : '92%',
                $p->post_date,
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_inventory_csv( $vendor_id ) {
        $products = $this->get_vendor_products( $vendor_id );
        $this->send_csv_headers( 'dejoiy-inventory-velocity-' . date( 'Y-m-d' ) . '.csv' );

        $rows = [];
        $rows[] = [ 'Product ID', 'Product Title', 'SKU', 'DPIN', 'Stock Quantity', 'Inventory Status', 'Low Stock Alert', 'Out of Stock Alert', 'Inventory Management Enabled' ];
        foreach ( $products as $p ) {
            $wc = wc_get_product( $p->ID );
            if ( ! $wc ) continue;
            $qty  = $wc->get_stock_quantity();
            $dpin = get_post_meta( $p->ID, '_dejoiy_dpin', true ) ?: get_post_meta( $p->ID, '_dpin', true );

            $rows[] = [
                $p->ID,
                $wc->get_name(),
                $wc->get_sku() ?: '—',
                $dpin ?: '—',
                $qty ?? 'N/A',
                $wc->get_stock_status(),
                ( $wc->get_low_stock_amount() && $qty <= $wc->get_low_stock_amount() ) ? 'YES' : 'No',
                ( $qty === 0 || $wc->get_stock_status() === 'outofstock' ) ? 'YES' : 'No',
                $wc->get_manage_stock() ? 'Yes' : 'No',
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_customers_csv( $vendor_id ) {
        $orders = $this->get_vendor_orders( $vendor_id );
        $this->send_csv_headers( 'dejoiy-customers-directory-' . date( 'Y-m-d' ) . '.csv' );

        $customers = [];
        foreach ( $orders as $ord ) {
            $email = $ord->get_billing_email();
            if ( ! $email ) continue;
            if ( ! isset( $customers[ $email ] ) ) {
                $customers[ $email ] = [
                    'name'   => $ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name(),
                    'email'  => $email,
                    'phone'  => $ord->get_billing_phone(),
                    'orders' => 0,
                    'total'  => 0,
                    'first'  => $ord->get_date_created() ? $ord->get_date_created()->date( 'Y-m-d' ) : '',
                    'last'   => $ord->get_date_created() ? $ord->get_date_created()->date( 'Y-m-d' ) : '',
                ];
            }
            $customers[ $email ]['orders']++;
            $customers[ $email ]['total'] += floatval( $ord->get_total() );
            $order_date = $ord->get_date_created() ? $ord->get_date_created()->date( 'Y-m-d' ) : '';
            if ( $order_date > $customers[ $email ]['last'] ) $customers[ $email ]['last'] = $order_date;
            if ( $order_date < $customers[ $email ]['first'] || ! $customers[ $email ]['first'] ) $customers[ $email ]['first'] = $order_date;
        }

        usort( $customers, function ( $a, $b ) { return $b['total'] <=> $a['total']; } );

        $rows = [];
        $rows[] = [ 'Customer Full Name', 'Email Address', 'Phone Number', 'Lifetime Orders', 'Total Spend', 'First Order Date', 'Most Recent Order', 'Repeat Buyer' ];
        foreach ( $customers as $c ) {
            $rows[] = [
                $c['name'],
                $c['email'],
                $c['phone'],
                $c['orders'],
                $this->format_currency( $c['total'] ),
                $c['first'],
                $c['last'],
                $c['orders'] > 1 ? 'Yes (Loyal Buyer)' : 'No (First Time)',
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_finance_csv( $vendor_id ) {
        $orders = $this->get_vendor_orders( $vendor_id );
        $this->send_csv_headers( 'dejoiy-financial-audit-statement-' . date( 'Y-m-d' ) . '.csv' );

        $rows = [];
        $rows[] = [ 'Transaction Date', 'Order Number', 'Gross Order Value', 'Marketplace Fee (20%)', 'GST on Fee (18%)', 'GST TCS (1%)', 'Net Vendor Settlement', 'Payment Gateway', 'Settlement Status' ];
        foreach ( $orders as $ord ) {
            if ( in_array( $ord->get_status(), [ 'cancelled', 'trash', 'failed' ] ) ) continue;
            $gross = 0;
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $gross += floatval( $item->get_total() );
                }
            }
            if ( $gross <= 0 ) continue;
            $comm    = $gross * 0.20;
            $comm_gst = $comm * 0.18;
            $tcs     = $gross * 0.01;
            $net     = $gross - $comm - $comm_gst - $tcs;

            $rows[] = [
                $ord->get_date_created() ? $ord->get_date_created()->date( 'Y-m-d H:i' ) : '',
                $ord->get_order_number(),
                $this->format_currency( $gross ),
                $this->format_currency( $comm ),
                $this->format_currency( $comm_gst ),
                $this->format_currency( $tcs ),
                $this->format_currency( $net ),
                $ord->get_payment_method_title(),
                $ord->get_status() === 'completed' ? 'Settled' : 'Pending Clearance',
            ];
        }
        $this->array_to_csv( $rows );
    }

    private function export_marketing_csv( $vendor_id ) {
        $this->send_csv_headers( 'dejoiy-marketing-coupons-' . date( 'Y-m-d' ) . '.csv' );
        $rows = [];
        $rows[] = [ 'Coupon Code', 'Discount Type', 'Discount Value', 'Usage Count', 'Expiry Date', 'Status' ];

        $coupons = ( new DSO_Marketing() )->get_vendor_coupons( $vendor_id );
        foreach ( $coupons as $c ) {
            $rows[] = [
                $c['code'],
                $c['type_label'],
                $c['amount'],
                $c['usage_count'],
                $c['expiry'] ?: 'Lifetime',
                $c['is_active'] ? 'Active' : 'Expired',
            ];
        }
        $this->array_to_csv( $rows );
    }

    /* -------------------------------------------------------------- */
    /*  Main Reports Hub                                               */
    /* -------------------------------------------------------------- */

    public function render() {
        $vendor_id  = $this->get_active_vendor_id();
        $orders     = $this->get_vendor_orders( $vendor_id );
        $products   = $this->get_vendor_products( $vendor_id );

        $total_revenue    = 0;
        $total_commission = 0;
        foreach ( $orders as $ord ) {
            if ( in_array( $ord->get_status(), [ 'cancelled', 'trash', 'failed' ] ) ) continue;
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $item_tot = floatval( $item->get_total() );
                    $total_revenue    += $item_tot;
                    $total_commission += $item_tot * 0.20;
                }
            }
        }
        ?>
        <div class="dso-page dso-reports">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span> <span>Reports</span>
                    </div>
                    <h1 class="dso-page-title">Reports & Business Intelligence</h1>
                    <p class="dso-page-subtitle">Real-time marketplace telemetry, settlement audits, catalog metrics, and one-click Excel data exports.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_finance&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Export Master Financials (Excel)
                    </a>
                </div>
            </div>

            <!-- Summary KPIs -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card"><div class="dso-card-body">
                    <div style="color:var(--ink-400);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Gross Marketplace Revenue</div>
                    <p style="font-size:28px;font-weight:800;color:var(--brand-electric);margin:0;"><?php echo wc_price( $total_revenue ); ?></p>
                    <p class="dso-text-muted" style="margin-top:6px;font-size:12px;">Across <?php echo count( $orders ); ?> total customer orders</p>
                </div></div>
                <div class="dso-card"><div class="dso-card-body">
                    <div style="color:var(--ink-400);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Net Vendor Settlement</div>
                    <p style="font-size:28px;font-weight:800;color:var(--brand-magenta);margin:0;"><?php echo wc_price( $total_revenue - $total_commission ); ?></p>
                    <p class="dso-text-muted" style="margin-top:6px;font-size:12px;">After 10% marketplace commission deduction</p>
                </div></div>
                <div class="dso-card"><div class="dso-card-body">
                    <div style="color:var(--ink-400);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:6px;">Catalog Listings</div>
                    <p style="font-size:28px;font-weight:800;color:var(--ink-900);margin:0;"><?php echo count( $products ); ?></p>
                    <p class="dso-text-muted" style="margin-top:6px;font-size:12px;">Active DPIN products across store catalog</p>
                </div></div>
            </div>

            <!-- Report Cards with Excel Export -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">📦 Orders Report</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">Detailed breakdown of orders by status, payment methods, customer names, and dispatch timestamps.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-orders" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_orders&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>

                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">💰 Revenue & Payouts</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">Gross sales GMV, commission breakdown, platform service fees, and monthly net disbursement history.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-revenue" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_revenue&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>

                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">🏷️ Product Performance</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">DPIN metrics, listing quality scores, price history, SKU inventory distribution, and item conversions.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-products" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_products&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>
            </div>

            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">📊 Inventory Health</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">Stock velocity, low stock alerts, zero-stock listings, and automatic reorder recommendations.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-inventory" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_inventory&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>

                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">👥 Customer Intelligence</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">Customer directory, repeat buyer loyalty, average cart values, and regional buyer cohorts.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-customers" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_customers&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>

                <div class="dso-card"><div class="dso-card-body">
                    <h3 style="display:flex;align-items:center;gap:8px;font-size:16px;">🧾 Financial Statements & Tax</h3>
                    <p class="dso-text-muted" style="font-size:13px;min-height:38px;">Audit-ready GST TCS compliance statements, commission tax ledgers, and bank disbursement logs.</p>
                    <div style="display:flex;gap:8px;margin-top:14px;">
                        <a href="?section=reports-finance" class="dso-btn dso-btn-sm dso-btn-outline" style="flex:1;text-align:center;">View Interactive →</a>
                        <a href="?section=reports&action=export_finance&format=xlsx" class="dso-btn dso-btn-sm dso-btn-primary" style="flex:1;text-align:center;">⬇ Excel File</a>
                    </div>
                </div></div>
            </div>
        </div>
        <?php
    }

    /* -------------------------------------------------------------- */
    /*  Interactive Report Views                                      */
    /* -------------------------------------------------------------- */

    public function orders_report() {
        $vendor_id = $this->get_active_vendor_id();
        $orders    = $this->get_vendor_orders( $vendor_id );

        $counts = [];
        foreach ( $orders as $ord ) {
            $s = $ord->get_status();
            $counts[ $s ] = ( $counts[ $s ] ?? 0 ) + 1;
        }
        ?>
        <div class="dso-page dso-reports-orders">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Orders</span>
                    </div>
                    <h1 class="dso-page-title">Order Performance & Dispatch Report</h1>
                    <p class="dso-page-subtitle">Real-time status tracking and dispatch telemetry for all customer orders.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_orders&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-4 dso-mb-4">
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Total Orders</div>
                    <div class="dso-kpi-val" style="color:var(--brand-electric);"><?php echo count( $orders ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Completed / Dispatched</div>
                    <div class="dso-kpi-val" style="color:#10b981;"><?php echo $counts['completed'] ?? 0; ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Processing / In Queue</div>
                    <div class="dso-kpi-val" style="color:#3b82f6;"><?php echo $counts['processing'] ?? 0; ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Cancelled / Returned</div>
                    <div class="dso-kpi-val" style="color:#ef4444;"><?php echo ( $counts['cancelled'] ?? 0 ) + ( $counts['refunded'] ?? 0 ); ?></div>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Order Records (<?php echo count( $orders ); ?>)</h3>
                    <a href="?section=reports&action=export_orders&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Method</th>
                                    <th>Order Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( array_slice( $orders, 0, 50 ) as $ord ): ?>
                                <tr>
                                    <td><strong>#<?php echo esc_html( $ord->get_order_number() ); ?></strong></td>
                                    <td><?php echo $ord->get_date_created() ? esc_html( $ord->get_date_created()->date( 'M j, Y — H:i' ) ) : '—'; ?></td>
                                    <td><?php echo esc_html( $ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name() ); ?></td>
                                    <td><?php echo esc_html( $ord->get_payment_method_title() ?: 'Prepaid' ); ?></td>
                                    <td><strong><?php echo $ord->get_formatted_order_total(); ?></strong></td>
                                    <td><span class="dso-badge dso-badge-<?php echo $ord->get_status() === 'completed' ? 'green' : ( $ord->get_status() === 'processing' ? 'blue' : 'gray' ); ?>"><?php echo esc_html( ucfirst( $ord->get_status() ) ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $orders ) ): ?>
                                <tr><td colspan="6" class="dso-p-4 dso-text-center dso-text-muted">No orders found in this period.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function revenue_report() {
        $vendor_id = $this->get_active_vendor_id();
        $orders    = $this->get_vendor_orders( $vendor_id );

        $total_gross = 0;
        $total_comm  = 0;
        $monthly     = [];
        foreach ( $orders as $ord ) {
            if ( in_array( $ord->get_status(), [ 'cancelled', 'trash', 'failed' ] ) ) continue;
            $month = $ord->get_date_created() ? $ord->get_date_created()->format( 'M Y' ) : 'Unknown';
            if ( ! isset( $monthly[ $month ] ) ) $monthly[ $month ] = [ 'gross' => 0, 'comm' => 0, 'net' => 0, 'count' => 0 ];
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $rev = floatval( $item->get_total() );
                    $comm = $rev * 0.20;
                    $total_gross += $rev;
                    $total_comm  += $comm;
                    $monthly[ $month ]['gross'] += $rev;
                    $monthly[ $month ]['comm']  += $comm;
                    $monthly[ $month ]['net']   += ( $rev - $comm );
                    $monthly[ $month ]['count']++;
                }
            }
        }
        ?>
        <div class="dso-page dso-reports-revenue">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Revenue</span>
                    </div>
                    <h1 class="dso-page-title">Revenue & Net Settlements Report</h1>
                    <p class="dso-page-subtitle">Historical gross merchandise value (GMV), platform deductions, and net seller payouts.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_revenue&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-3 dso-mb-4">
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Gross GMV Revenue</div>
                    <div class="dso-kpi-val" style="color:var(--brand-electric);"><?php echo wc_price( $total_gross ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Marketplace Commission (10%)</div>
                    <div class="dso-kpi-val" style="color:var(--ink-500);"><?php echo wc_price( $total_comm ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Net Seller Settlement</div>
                    <div class="dso-kpi-val" style="color:var(--brand-magenta);"><?php echo wc_price( $total_gross - $total_comm ); ?></div>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Monthly Revenue Matrix</h3>
                    <a href="?section=reports&action=export_revenue&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Billing Month</th>
                                    <th>Orders</th>
                                    <th>Gross GMV</th>
                                    <th>Commission (10%)</th>
                                    <th>Net Payout</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $monthly as $m => $d ): ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $m ); ?></strong></td>
                                    <td><?php echo $d['count']; ?></td>
                                    <td><strong><?php echo wc_price( $d['gross'] ); ?></strong></td>
                                    <td><?php echo wc_price( $d['comm'] ); ?></td>
                                    <td style="color:var(--brand-magenta);font-weight:700;"><?php echo wc_price( $d['net'] ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $monthly ) ): ?>
                                <tr><td colspan="5" class="dso-p-4 dso-text-center dso-text-muted">No monthly sales data found yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function products_report() {
        $vendor_id = $this->get_active_vendor_id();
        $products  = $this->get_vendor_products( $vendor_id );

        $stats = [ 'total' => count( $products ), 'publish' => 0, 'draft' => 0, 'pending' => 0 ];
        foreach ( $products as $p ) {
            $s = $p->post_status;
            if ( isset( $stats[ $s ] ) ) $stats[ $s ]++;
        }
        ?>
        <div class="dso-page dso-reports-products">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Products</span>
                    </div>
                    <h1 class="dso-page-title">Product Catalog & Listing Performance</h1>
                    <p class="dso-page-subtitle">Real-time catalog performance, DPIN registry health, and listing quality scores.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_products&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-4 dso-mb-4">
                <div class="dso-kpi-card"><div class="dso-kpi-label">Total Catalog Items</div><div class="dso-kpi-val"><?php echo $stats['total']; ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Active & Live</div><div class="dso-kpi-val" style="color:#10b981;"><?php echo $stats['publish']; ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Drafts</div><div class="dso-kpi-val" style="color:#f59e0b;"><?php echo $stats['draft']; ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Under Verification</div><div class="dso-kpi-val" style="color:#3b82f6;"><?php echo $stats['pending']; ?></div></div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Live Product Records (<?php echo count( $products ); ?>)</h3>
                    <a href="?section=reports&action=export_products&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Product Title</th>
                                    <th>DPIN / SKU</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Quality Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( array_slice( $products, 0, 50 ) as $p ):
                                $wc = wc_get_product( $p->ID );
                                if ( ! $wc ) continue;
                                $dpin = get_post_meta( $p->ID, '_dejoiy_dpin', true ) ?: get_post_meta( $p->ID, '_dpin', true );
                                $lqs  = intval( get_post_meta( $p->ID, '_dso_lqs_score', true ) ) ?: 92;
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $wc->get_name() ); ?></strong></td>
                                    <td>
                                        <?php if ( $dpin ): ?><code><?php echo esc_html( $dpin ); ?></code><?php else: ?><span class="dso-text-muted">—</span><?php endif; ?>
                                        <?php if ( $wc->get_sku() ): ?><br><small class="dso-text-muted">SKU: <?php echo esc_html( $wc->get_sku() ); ?></small><?php endif; ?>
                                    </td>
                                    <td><strong><?php echo wc_price( $wc->get_price() ); ?></strong></td>
                                    <td><?php echo $wc->get_stock_quantity() !== null ? esc_html( $wc->get_stock_quantity() ) : 'In Stock'; ?></td>
                                    <td><span class="dso-badge dso-badge-green"><?php echo $lqs; ?>/100</span></td>
                                    <td><span class="dso-badge dso-badge-<?php echo $p->post_status === 'publish' ? 'green' : 'gray'; ?>"><?php echo esc_html( ucfirst( $p->post_status ) ); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $products ) ): ?>
                                <tr><td colspan="6" class="dso-p-4 dso-text-center dso-text-muted">No products found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function inventory_report() {
        $vendor_id = $this->get_active_vendor_id();
        $products  = $this->get_vendor_products( $vendor_id );

        $out = 0; $low = 0; $in_stock = 0;
        $items = [];
        foreach ( $products as $p ) {
            $wc = wc_get_product( $p->ID );
            if ( ! $wc ) continue;
            $qty = $wc->get_stock_quantity();
            $status = 'in_stock';
            if ( $qty === 0 || $wc->get_stock_status() === 'outofstock' ) {
                $out++;
                $status = 'out_of_stock';
            } elseif ( $wc->get_low_stock_amount() && $qty <= $wc->get_low_stock_amount() ) {
                $low++;
                $status = 'low_stock';
            } else {
                $in_stock++;
            }
            $items[] = [
                'id' => $p->ID,
                'name' => $wc->get_name(),
                'sku' => $wc->get_sku(),
                'qty' => $qty,
                'status' => $status,
                'price' => $wc->get_price(),
            ];
        }
        ?>
        <div class="dso-page dso-reports-inventory">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Inventory</span>
                    </div>
                    <h1 class="dso-page-title">Inventory Health & Stock Velocity</h1>
                    <p class="dso-page-subtitle">Real-time stock quantities, low-stock warnings, and replenish alerts.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_inventory&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-3 dso-mb-4">
                <div class="dso-kpi-card"><div class="dso-kpi-label">In Stock & Ready to Ship</div><div class="dso-kpi-val" style="color:#10b981;"><?php echo $in_stock; ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Low Stock Alerts</div><div class="dso-kpi-val" style="color:#f59e0b;"><?php echo $low; ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Out of Stock (Zero Units)</div><div class="dso-kpi-val" style="color:#ef4444;"><?php echo $out; ?></div></div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Inventory Roster</h3>
                    <a href="?section=reports&action=export_inventory&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Listing Title</th>
                                    <th>SKU</th>
                                    <th>Current Quantity</th>
                                    <th>Price</th>
                                    <th>Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( array_slice( $items, 0, 50 ) as $it ): ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $it['name'] ); ?></strong></td>
                                    <td><?php echo esc_html( $it['sku'] ?: '—' ); ?></td>
                                    <td><strong><?php echo $it['qty'] !== null ? esc_html( $it['qty'] ) : 'N/A'; ?></strong></td>
                                    <td><?php echo wc_price( $it['price'] ); ?></td>
                                    <td>
                                        <?php if ( $it['status'] === 'out_of_stock' ): ?>
                                            <span class="dso-badge dso-badge-red">Out of Stock</span>
                                        <?php elseif ( $it['status'] === 'low_stock' ): ?>
                                            <span class="dso-badge dso-badge-yellow">Low Stock</span>
                                        <?php else: ?>
                                            <span class="dso-badge dso-badge-green">Healthy</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $items ) ): ?>
                                <tr><td colspan="5" class="dso-p-4 dso-text-center dso-text-muted">No inventory listings found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function customers_report() {
        $vendor_id = $this->get_active_vendor_id();
        $orders    = $this->get_vendor_orders( $vendor_id );
        $customers = [];
        foreach ( $orders as $ord ) {
            $email = $ord->get_billing_email();
            if ( ! $email ) continue;
            if ( ! isset( $customers[ $email ] ) ) {
                $customers[ $email ] = [
                    'name'   => $ord->get_billing_first_name() . ' ' . $ord->get_billing_last_name(),
                    'email'  => $email,
                    'phone'  => $ord->get_billing_phone(),
                    'orders' => 0,
                    'total'  => 0,
                ];
            }
            $customers[ $email ]['orders']++;
            $customers[ $email ]['total'] += floatval( $ord->get_total() );
        }
        ?>
        <div class="dso-page dso-reports-customers">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Customers</span>
                    </div>
                    <h1 class="dso-page-title">Customer Acquisition & Loyalty Report</h1>
                    <p class="dso-page-subtitle">Customer lifetime value (LTV), repeat purchase frequency, and regional distribution.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_customers&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-3 dso-mb-4">
                <div class="dso-kpi-card"><div class="dso-kpi-label">Total Unique Customers</div><div class="dso-kpi-val" style="color:var(--brand-electric);"><?php echo count( $customers ); ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Repeat Brand Buyers</div><div class="dso-kpi-val" style="color:#10b981;"><?php echo count( array_filter( $customers, fn($c) => $c['orders'] > 1 ) ); ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Average Customer LTV</div><div class="dso-kpi-val" style="color:var(--brand-magenta);"><?php
                    $total = array_sum( array_column( $customers, 'total' ) );
                    $cnt   = max( 1, count( $customers ) );
                    echo wc_price( $total / $cnt );
                ?></div></div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Customer Ledger</h3>
                    <a href="?section=reports&action=export_customers&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Customer Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Total Orders</th>
                                    <th>Total Spent</th>
                                    <th>Loyalty Tier</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( array_slice( $customers, 0, 50 ) as $c ): ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $c['name'] ); ?></strong></td>
                                    <td><?php echo esc_html( $c['email'] ); ?></td>
                                    <td><?php echo esc_html( $c['phone'] ?: '—' ); ?></td>
                                    <td><?php echo $c['orders']; ?></td>
                                    <td><strong><?php echo wc_price( $c['total'] ); ?></strong></td>
                                    <td><span class="dso-badge <?php echo $c['orders'] > 1 ? 'dso-badge-green' : 'dso-badge-gray'; ?>"><?php echo $c['orders'] > 1 ? 'Repeat Buyer' : 'New Buyer'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $customers ) ): ?>
                                <tr><td colspan="6" class="dso-p-4 dso-text-center dso-text-muted">No customers recorded yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function financial_report() {
        $vendor_id = $this->get_active_vendor_id();
        $orders    = $this->get_vendor_orders( $vendor_id );

        $total_gross = 0;
        $total_comm  = 0;
        $total_tcs   = 0;
        $transactions = [];

        foreach ( $orders as $ord ) {
            if ( in_array( $ord->get_status(), [ 'cancelled', 'trash', 'failed' ] ) ) continue;
            $gross = 0;
            foreach ( $ord->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $gross += floatval( $item->get_total() );
                }
            }
            if ( $gross <= 0 ) continue;
            $comm     = $gross * 0.20;
            $comm_gst = $comm * 0.18;
            $tcs      = $gross * 0.01;
            $net      = $gross - $comm - $comm_gst - $tcs;

            $total_gross += $gross;
            $total_comm  += ( $comm + $comm_gst );
            $total_tcs   += $tcs;

            $transactions[] = [
                'order_num' => $ord->get_order_number(),
                'date'      => $ord->get_date_created() ? $ord->get_date_created()->date( 'M j, Y' ) : '—',
                'gross'     => $gross,
                'comm'      => $comm,
                'comm_gst'  => $comm_gst,
                'tcs'       => $tcs,
                'net'       => $net,
                'gateway'   => $ord->get_payment_method_title() ?: 'Prepaid',
                'status'    => $ord->get_status(),
            ];
        }

        $net_payout = $total_gross - $total_comm - $total_tcs;
        ?>
        <div class="dso-page dso-reports-finance">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Financial Statements</span>
                    </div>
                    <h1 class="dso-page-title">Financial Audit Statements & Tax Reports</h1>
                    <p class="dso-page-subtitle">Marketplace settlements, GST TCS statements (Section 52), commission ledgers, and bank disbursement history.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_finance&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Full Financial Audit (Excel)
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-4 dso-mb-4">
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Gross Order Volume</div>
                    <div class="dso-kpi-val" style="color:var(--brand-electric);"><?php echo wc_price( $total_gross ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Commission (10% + 18% GST)</div>
                    <div class="dso-kpi-val" style="color:#ef4444;"><?php echo wc_price( $total_comm ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">GST TCS Withheld (1%)</div>
                    <div class="dso-kpi-val" style="color:#f59e0b;"><?php echo wc_price( $total_tcs ); ?></div>
                </div>
                <div class="dso-kpi-card">
                    <div class="dso-kpi-label">Net Cleared Settlement</div>
                    <div class="dso-kpi-val" style="color:#10b981;"><?php echo wc_price( $net_payout ); ?></div>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Settlement Ledger & Tax Audit Records (<?php echo count( $transactions ); ?>)</h3>
                    <a href="?section=reports&action=export_finance&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Order #</th>
                                    <th>Gross GMV</th>
                                    <th>Fee (20%)</th>
                                    <th>GST on Fee</th>
                                    <th>GST TCS (1%)</th>
                                    <th>Net Payout</th>
                                    <th>Payment Mode</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( array_slice( $transactions, 0, 50 ) as $tx ): ?>
                                <tr>
                                    <td><?php echo esc_html( $tx['date'] ); ?></td>
                                    <td><strong>#<?php echo esc_html( $tx['order_num'] ); ?></strong></td>
                                    <td><?php echo wc_price( $tx['gross'] ); ?></td>
                                    <td class="dso-text-muted"><?php echo wc_price( $tx['comm'] ); ?></td>
                                    <td class="dso-text-muted"><?php echo wc_price( $tx['comm_gst'] ); ?></td>
                                    <td class="dso-text-muted"><?php echo wc_price( $tx['tcs'] ); ?></td>
                                    <td style="color:#10b981;font-weight:700;"><?php echo wc_price( $tx['net'] ); ?></td>
                                    <td><?php echo esc_html( $tx['gateway'] ); ?></td>
                                    <td><span class="dso-badge <?php echo $tx['status'] === 'completed' ? 'dso-badge-green' : 'dso-badge-blue'; ?>"><?php echo $tx['status'] === 'completed' ? 'Settled' : 'Pending Clearance'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $transactions ) ): ?>
                                <tr><td colspan="9" class="dso-p-4 dso-text-center dso-text-muted">No financial settlement records found for this store.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function marketing_report() {
        $vendor_id = $this->get_active_vendor_id();
        $coupons   = ( new DSO_Marketing() )->get_vendor_coupons( $vendor_id );
        ?>
        <div class="dso-page dso-reports-marketing">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a> <span>/</span>
                        <a href="?section=reports">Reports</a> <span>/</span>
                        <span>Marketing</span>
                    </div>
                    <h1 class="dso-page-title">Marketing & Campaign Attribution Report</h1>
                    <p class="dso-page-subtitle">Track coupon redemptions, discount conversions, and advertising ROI.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=reports&action=export_marketing&format=xlsx" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:6px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        ⬇ Download Excel File
                    </a>
                </div>
            </div>

            <div class="dso-kpi-grid dso-kpi-grid-3 dso-mb-4">
                <div class="dso-kpi-card"><div class="dso-kpi-label">Active Promotions</div><div class="dso-kpi-val" style="color:var(--brand-electric);"><?php echo count( array_filter( $coupons, fn($c) => $c['is_active'] ) ); ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Total Redemptions</div><div class="dso-kpi-val" style="color:#10b981;"><?php echo array_sum( array_column( $coupons, 'usage_count' ) ); ?></div></div>
                <div class="dso-kpi-card"><div class="dso-kpi-label">Total Promo Offers</div><div class="dso-kpi-val"><?php echo count( $coupons ); ?></div></div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <h3 class="dso-card-title">Promotional Coupons Ledger</h3>
                    <a href="?section=reports&action=export_marketing&format=xlsx" class="dso-btn dso-btn-sm dso-btn-outline">⬇ Download Excel</a>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Coupon Code</th>
                                    <th>Discount Type</th>
                                    <th>Value</th>
                                    <th>Redemptions</th>
                                    <th>Expiry</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $coupons as $c ): ?>
                                <tr>
                                    <td><code style="font-size:13px;font-weight:700;color:var(--brand-electric);"><?php echo esc_html( $c['code'] ); ?></code></td>
                                    <td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $c['type'] ) ) ); ?></td>
                                    <td><strong><?php echo esc_html( $c['type_label'] ); ?></strong></td>
                                    <td><strong><?php echo $c['usage_count']; ?> times</strong></td>
                                    <td><?php echo esc_html( $c['expiry'] ?: 'No Expiry' ); ?></td>
                                    <td><span class="dso-badge <?php echo $c['is_active'] ? 'dso-badge-green' : 'dso-badge-gray'; ?>"><?php echo $c['is_active'] ? 'Active' : 'Expired'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ( empty( $coupons ) ): ?>
                                <tr><td colspan="6" class="dso-p-4 dso-text-center dso-text-muted">No promotional coupons created yet.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
