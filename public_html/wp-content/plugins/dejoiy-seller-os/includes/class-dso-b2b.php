<?php
/**
 * DSO B2B - Wholesale & Business-to-Business Commerce Engine for DEJOIY Seller Central
 */
if (!defined('ABSPATH')) exit;

class DSO_B2B {

    public function render() {
        ?>
        <div class="dso-page dso-b2b">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>B2B Wholesale</span>
                        <span>/</span>
                        <span>Hub</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY B2B Wholesale Hub</h1>
                    <p class="dso-page-subtitle">Sell in high-volume bulk quantities directly to verified GST business enterprises and corporate buyers</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=b2b-offers" class="dso-btn dso-btn-outline">RFQ Inquiries (2)</a>
                    <a href="?section=b2b-pricing" class="dso-btn dso-btn-primary">Manage Wholesale Pricing</a>
                </div>
            </div>

            <!-- B2B Stats Row -->
            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">B2B Wholesale Volume</span>
                    <span class="dso-stat-val dso-text-primary">₹1,42,800</span>
                    <span class="dso-stat-sub">Corporate sales</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Average Order Size</span>
                    <span class="dso-stat-val">45 units</span>
                    <span class="dso-stat-sub">Units per wholesale cart</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Business Customers</span>
                    <span class="dso-stat-val dso-text-success">18</span>
                    <span class="dso-stat-sub">GSTIN verified enterprises</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Pending RFQs</span>
                    <span class="dso-stat-val dso-text-warning">2</span>
                    <span class="dso-stat-sub">Quotes awaiting response</span>
                </div>
            </div>

            <!-- B2B Quick Navigation Cards -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Bulk Order Fulfillment</h3>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Process commercial pallet shipments, generate GST tax invoices with HSN breakdown, and coordinate B2B freight.</p>
                        <a href="?section=b2b-orders" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">View Bulk Orders →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Corporate Buyer Directory</h3>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Manage corporate accounts, credit payment terms (Net 15 / Net 30), and commercial contracts.</p>
                        <a href="?section=b2b-customers" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Manage Buyers →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">RFQ Custom Quotes</h3>
                    </div>
                    <div class="dso-card-body">
                        <p class="dso-text-muted">Respond to custom RFQ tenders submitted by verified institutions, hotels, and retail chains.</p>
                        <a href="?section=b2b-offers" class="dso-btn dso-btn-sm dso-btn-primary dso-mt-3">Review RFQ Quotes →</a>
                    </div>
                </div>
            </div>

            <!-- Recent Inquiries Table -->
            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Recent B2B Bulk Inquiries</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Company / Enterprise</th>
                                    <th>GSTIN</th>
                                    <th>Requested Item</th>
                                    <th>Quantity</th>
                                    <th>Target Budget</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Apex Retail Ventures Ltd</strong></td>
                                    <td><code>27AAACA1234A1Z5</code></td>
                                    <td>DEJOIY Signature Apparel</td>
                                    <td>100 units</td>
                                    <td>₹45,000</td>
                                    <td><a href="?section=b2b-offers" class="dso-btn dso-btn-sm dso-btn-primary">Send Offer</a></td>
                                </tr>
                                <tr>
                                    <td><strong>Trident Hospitality Group</strong></td>
                                    <td><code>07AABCT9876C1Z2</code></td>
                                    <td>Handmade Ceramic Mugs & Ware</td>
                                    <td>250 units</td>
                                    <td>₹62,500</td>
                                    <td><a href="?section=b2b-offers" class="dso-btn dso-btn-sm dso-btn-primary">Send Offer</a></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function bulk_orders() {
        ?>
        <div class="dso-page dso-b2b-orders">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=b2b">B2B</a>
                        <span>/</span>
                        <span>Bulk Orders</span>
                    </div>
                    <h1 class="dso-page-title">Wholesale & Bulk Orders</h1>
                    <p class="dso-page-subtitle">Commercial invoices and bulk shipments</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Enterprise Buyer</th>
                                    <th>Units</th>
                                    <th>Invoice Value</th>
                                    <th>GST Status</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>#B2B-1084</strong></td>
                                    <td><?php echo date('M j, Y', strtotime('-2 days')); ?></td>
                                    <td>Apex Retail Ventures Ltd</td>
                                    <td>50 units</td>
                                    <td>₹22,500.00</td>
                                    <td><span class="dso-badge dso-badge-green">GST e-Invoice Generated</span></td>
                                    <td><span class="dso-badge dso-badge-blue">Dispatching</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function business_customers() {
        ?>
        <div class="dso-page dso-b2b-customers">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=b2b">B2B</a>
                        <span>/</span>
                        <span>Business Accounts</span>
                    </div>
                    <h1 class="dso-page-title">Verified Corporate Accounts</h1>
                    <p class="dso-page-subtitle">GST verified business partners registered on DEJOIY B2B Network</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Enterprise Name</th>
                                    <th>GSTIN</th>
                                    <th>City / State</th>
                                    <th>Tier Assigned</th>
                                    <th>Total Spend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Apex Retail Ventures Ltd</strong></td>
                                    <td><code>27AAACA1234A1Z5</code></td>
                                    <td>Mumbai, Maharashtra</td>
                                    <td><span class="dso-badge dso-badge-primary">Tier A (Wholesale)</span></td>
                                    <td>₹82,400.00</td>
                                </tr>
                                <tr>
                                    <td><strong>Trident Hospitality Group</strong></td>
                                    <td><code>07AABCT9876C1Z2</code></td>
                                    <td>New Delhi, Delhi</td>
                                    <td><span class="dso-badge dso-badge-purple">Tier B (Hospitality)</span></td>
                                    <td>₹60,400.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function b2b_offers() {
        ?>
        <div class="dso-page dso-b2b-offers">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=b2b">B2B</a>
                        <span>/</span>
                        <span>RFQ Offers</span>
                    </div>
                    <h1 class="dso-page-title">Request for Quote (RFQ) Tender Desk</h1>
                    <p class="dso-page-subtitle">Review corporate RFQs and respond with customized unit pricing and shipping timelines</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Pending RFQ Inquiries</h3>
                </div>
                <div class="dso-card-body">
                    <div class="dso-rfq-item dso-p-3 dso-mb-3" style="border: 1px solid var(--dj-border); border-radius: 8px;">
                        <div class="dso-flex dso-justify-between dso-items-center dso-mb-2">
                            <h4>Tender #RFQ-2026-904: 100x Oversized Apparel</h4>
                            <span class="dso-badge dso-badge-warning">Expires in 48h</span>
                        </div>
                        <p class="dso-text-muted">Buyer: <strong>Apex Retail Ventures Ltd (GST: 27AAACA1234A1Z5)</strong>. Need delivery to Bhiwandi warehouse within 7 days.</p>
                        <div class="dso-flex dso-gap-2 dso-mt-3">
                            <input type="number" class="dso-input dso-input-sm" style="max-width: 200px;" placeholder="Offer Unit Price (₹)" value="450" />
                            <button class="dso-btn dso-btn-sm dso-btn-primary">Submit Binding Quote</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
