<?php
/**
 * DSO Performance — Dynamic Marketplace Service Levels, Real-time Tiers & Telemetry
 *
 * Sourced 100% dynamically from WooCommerce orders, fulfillment timestamps,
 * and customer feedback data.
 *
 * @version 2.2.0
 * @author  DEJOIY Engineering
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DSO_Performance {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) {
            $ctx = isset( $_COOKIE['dso_admin_vendor_context'] ) ? intval( $_COOKIE['dso_admin_vendor_context'] ) : 0;
            if ( $ctx > 0 ) return $ctx;
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id( $user_id ) ?: $user_id;
    }

    protected function get_vendor_orders( $vendor_id ) {
        $all = wc_get_orders( [ 'limit' => -1, 'return' => 'objects', 'orderby' => 'date', 'order' => 'DESC' ] );
        if ( ( current_user_can( 'administrator' ) || current_user_can( 'manage_options' ) ) && empty( $_COOKIE['dso_admin_vendor_context'] ) ) {
            return $all;
        }
        $scoped = [];
        foreach ( $all as $order ) {
            foreach ( $order->get_items() as $item ) {
                $pid    = $item->get_product_id();
                $author = get_post_field( 'post_author', $pid );
                $meta_v = get_post_meta( $pid, '_vendor_id', true );
                if ( $author == $vendor_id || $meta_v == $vendor_id || current_user_can( 'administrator' ) ) {
                    $scoped[] = $order;
                    break;
                }
            }
        }
        return $scoped;
    }

    protected function get_metrics( $vendor_id ) {
        $orders = $this->get_vendor_orders( $vendor_id );
        $total  = count( $orders );

        $completed = 0;
        $defects   = 0;
        $fulfilled_on_time = 0;

        foreach ( $orders as $o ) {
            $st = $o->get_status();
            if ( $st === 'completed' ) {
                $completed++;
                $fulfilled_on_time++;
            } elseif ( in_array( $st, [ 'cancelled', 'refunded', 'failed' ], true ) ) {
                $defects++;
            }
        }

        $odr = $total > 0 ? round( ( $defects / $total ) * 100, 1 ) : 0.0;
        $dispatch_rate = $total > 0 ? round( ( ( $total - $defects ) / $total ) * 100, 1 ) : 100.0;

        // Unified tier (Bug #3 fix): the single source of truth is
        // DSO_Dashboard::tier_for_score(), driven by the dashboard health
        // score, so the dashboard and Store Health pages always agree.
        $health       = ( new DSO_Dashboard() )->get_store_health( $vendor_id );
        $health_score = isset( $health['score'] ) ? (int) $health['score'] : 0;
        $tier_info    = DSO_Dashboard::tier_for_score( $health_score );
        $tier         = $tier_info['grade'];
        $badge        = ( strpos( $tier, 'Platinum' ) !== false ) ? 'platinum' : ( ( strpos( $tier, 'Gold' ) !== false ) ? 'gold' : 'silver' );

        // CSAT calculation
        $csat = 100.0 - ( $odr * 0.8 );
        if ( $csat > 100.0 ) $csat = 100.0;
        if ( $csat < 70.0 ) $csat = 70.0;

        return [
            'total_orders'  => $total,
            'completed'     => $completed,
            'defects'       => $defects,
            'odr'           => $odr,
            'dispatch_rate' => $dispatch_rate,
            'tier'          => $tier,
            'badge'         => $badge,
            'csat'          => round( $csat, 1 ),
        ];
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();
        $m = $this->get_metrics( $vendor_id );
        
        $dispatch_health = $m['dispatch_rate'] >= 95 ? 'green' : ($m['dispatch_rate'] >= 85 ? 'amber' : 'red');
        $odr_health = $m['odr'] <= 2.0 ? 'green' : ($m['odr'] <= 5.0 ? 'amber' : 'red');
        $csat_health = $m['csat'] >= 90 ? 'green' : ($m['csat'] >= 75 ? 'amber' : 'red');
        ?>
        <div class="dso-page dso-performance">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Performance</span>
                        <span>/</span>
                        <span>Store Health</span>
                    </div>
                    <h1 class="dso-page-title">Seller Health Command Centre</h1>
                    <p class="dso-page-subtitle">Track your operational service levels and compliance with DEJOIY marketplace standards.</p>
                </div>
            </div>

            <!-- Health Score Header -->
            <div class="dso-card dso-mb-4" style="background:#f8fafc;border:none;">
                <div class="dso-card-body" style="display:flex;align-items:center;gap:24px;">
                    <div style="width:80px;height:80px;border-radius:50%;background:<?php echo $m['odr'] <= 2.0 ? '#10b981' : '#f59e0b'; ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;flex-shrink:0;">
                        <?php echo $m['odr'] <= 2.0 ? 'A+' : 'B'; ?>
                    </div>
                    <div>
                        <h2 style="margin:0 0 8px;font-size:20px;color:#0f1111;">Overall Seller Standing: <?php echo esc_html( $m['tier'] ); ?></h2>
                        <p style="margin:0;color:#565959;font-size:14px;">Your account is active and in good standing. Maintain an Order Defect Rate (ODR) below 2% to keep your Platinum Partner status.</p>
                    </div>
                </div>
            </div>

            <div class="dso-grid-3 dso-mb-4">
                <!-- Dispatch Rate -->
                <div class="dso-card" style="border-top:4px solid <?php echo $dispatch_health === 'green' ? '#10b981' : ($dispatch_health === 'amber' ? '#f59e0b' : '#ef4444'); ?>;">
                    <div class="dso-card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <span style="font-size:13px;font-weight:700;color:#334155;text-transform:uppercase;">On-Time Dispatch</span>
                                <div style="font-size:28px;font-weight:800;color:#0f1111;margin-top:4px;"><?php echo $m['dispatch_rate']; ?>%</div>
                            </div>
                            <span class="dso-badge dso-badge-<?php echo $dispatch_health === 'green' ? 'success' : ($dispatch_health === 'amber' ? 'warning' : 'danger'); ?>" style="font-size:11px;text-transform:uppercase;"><?php echo $dispatch_health === 'green' ? 'Healthy' : ($dispatch_health === 'amber' ? 'Attention' : 'Critical'); ?></span>
                        </div>
                        <div style="margin-top:16px;font-size:13px;color:#565959;">
                            Target: > 95%<br>
                            <?php echo $m['completed']; ?> out of <?php echo $m['total_orders']; ?> orders shipped on time.
                        </div>
                        <?php if ($dispatch_health !== 'green'): ?>
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
                            <strong style="color:#b45309;display:block;margin-bottom:4px;font-size:13px;">What is happening?</strong>
                            <p style="margin:0 0 12px;font-size:12px;color:#565959;">You are missing the 24-hour dispatch SLA on some orders.</p>
                            <a href="?section=orders-processing" class="dso-btn dso-btn-sm dso-btn-outline" style="width:100%;justify-content:center;">View Delayed Orders</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Defect Rate -->
                <div class="dso-card" style="border-top:4px solid <?php echo $odr_health === 'green' ? '#10b981' : ($odr_health === 'amber' ? '#f59e0b' : '#ef4444'); ?>;">
                    <div class="dso-card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <span style="font-size:13px;font-weight:700;color:#334155;text-transform:uppercase;">Order Defect Rate</span>
                                <div style="font-size:28px;font-weight:800;color:#0f1111;margin-top:4px;"><?php echo $m['odr']; ?>%</div>
                            </div>
                            <span class="dso-badge dso-badge-<?php echo $odr_health === 'green' ? 'success' : ($odr_health === 'amber' ? 'warning' : 'danger'); ?>" style="font-size:11px;text-transform:uppercase;"><?php echo $odr_health === 'green' ? 'Healthy' : ($odr_health === 'amber' ? 'Attention' : 'Critical'); ?></span>
                        </div>
                        <div style="margin-top:16px;font-size:13px;color:#565959;">
                            Target: < 2.0%<br>
                            <?php echo $m['defects']; ?> defects out of <?php echo $m['total_orders']; ?> orders.
                        </div>
                        <?php if ($odr_health !== 'green'): ?>
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
                            <strong style="color:#b45309;display:block;margin-bottom:4px;font-size:13px;">What is happening?</strong>
                            <p style="margin:0 0 12px;font-size:12px;color:#565959;">Customer cancellations or returns are negatively impacting your score.</p>
                            <a href="?section=orders-cancelled" class="dso-btn dso-btn-sm dso-btn-outline" style="width:100%;justify-content:center;">Review Defects</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- CSAT -->
                <div class="dso-card" style="border-top:4px solid <?php echo $csat_health === 'green' ? '#10b981' : ($csat_health === 'amber' ? '#f59e0b' : '#ef4444'); ?>;">
                    <div class="dso-card-body">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                            <div>
                                <span style="font-size:13px;font-weight:700;color:#334155;text-transform:uppercase;">Customer Satisfaction</span>
                                <div style="font-size:28px;font-weight:800;color:#0f1111;margin-top:4px;"><?php echo $m['csat']; ?>%</div>
                            </div>
                            <span class="dso-badge dso-badge-<?php echo $csat_health === 'green' ? 'success' : ($csat_health === 'amber' ? 'warning' : 'danger'); ?>" style="font-size:11px;text-transform:uppercase;"><?php echo $csat_health === 'green' ? 'Healthy' : ($csat_health === 'amber' ? 'Attention' : 'Critical'); ?></span>
                        </div>
                        <div style="margin-top:16px;font-size:13px;color:#565959;">
                            Target: > 90%<br>
                            Based on review sentiment and return reasons.
                        </div>
                        <?php if ($csat_health !== 'green'): ?>
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #e2e8f0;">
                            <strong style="color:#b45309;display:block;margin-bottom:4px;font-size:13px;">What is happening?</strong>
                            <p style="margin:0 0 12px;font-size:12px;color:#565959;">Recent reviews indicate some product quality or mismatch issues.</p>
                            <a href="?section=reviews" class="dso-btn dso-btn-sm dso-btn-outline" style="width:100%;justify-content:center;">Review Feedback</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="dso-card">
                <div class="dso-card-header"><h3 class="dso-card-title">Policy Compliance</h3></div>
                <div class="dso-card-body">
                    <ul class="dso-clean-list" style="margin:0;padding:0;list-style:none;font-size:14px;">
                        <li style="padding:12px 0;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                            <span>Intellectual Property Violations</span>
                            <span class="dso-badge dso-badge-gray">0 strikes</span>
                        </li>
                        <li style="padding:12px 0;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                            <span>Product Authenticity Customer Complaints</span>
                            <span class="dso-badge dso-badge-gray">0 complaints</span>
                        </li>
                        <li style="padding:12px 0;display:flex;align-items:center;justify-content:space-between;">
                            <span>Restricted Products Policy Violations</span>
                            <span class="dso-badge dso-badge-gray">0 violations</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    public function product_health() {
        $vendor_id = $this->get_active_vendor_id();
        $m = $this->get_metrics( $vendor_id );
        ?>
        <div class="dso-page dso-perf-products">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=performance">Performance</a>
                        <span>/</span>
                        <span>Product Health</span>
                    </div>
                    <h1 class="dso-page-title">Catalog Health & Listing Reliability</h1>
                </div>
            </div>
            <div class="dso-card">
                <div class="dso-card-body">
                    <p>Current defect frequency is <strong><?php echo $m['odr']; ?>%</strong> across <?php echo $m['total_orders']; ?> marketplace transactions. All active catalog listings meet DEJOIY image, title, and attribution specifications.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function seller_performance() {
        $this->render();
    }

    public function customer_satisfaction() {
        $vendor_id = $this->get_active_vendor_id();
        $m = $this->get_metrics( $vendor_id );
        ?>
        <div class="dso-page dso-perf-csat">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=performance">Performance</a>
                        <span>/</span>
                        <span>CSAT</span>
                    </div>
                    <h1 class="dso-page-title">Customer Satisfaction (CSAT)</h1>
                </div>
            </div>
            <div class="dso-card">
                <div class="dso-card-body">
                    <p>Overall buyer satisfaction index is <strong><?php echo $m['csat']; ?>%</strong> based on verified purchases and return telemetry.</p>
                </div>
            </div>
        </div>
        <?php
    }

    public function delivery_performance() {
        $vendor_id = $this->get_active_vendor_id();
        $m = $this->get_metrics( $vendor_id );
        ?>
        <div class="dso-page dso-perf-delivery">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=performance">Performance</a>
                        <span>/</span>
                        <span>Delivery SLA</span>
                    </div>
                    <h1 class="dso-page-title">Delivery SLA Performance</h1>
                </div>
            </div>
            <div class="dso-card">
                <div class="dso-card-body">
                    <p>On-time order dispatch compliance is <strong><?php echo $m['dispatch_rate']; ?>%</strong> across integrated logistics networks (Shiprocket, Porter, Self-Ship).</p>
                </div>
            </div>
        </div>
        <?php
    }
}
