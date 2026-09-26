<?php
/**
 * DSO Learn - DEJOIY Seller University, Academy & Operational Standards
 */
if (!defined('ABSPATH')) exit;

class DSO_Learn {

    public function render() {
        ?>
        <div class="dso-page dso-learn">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Learn</span>
                        <span>/</span>
                        <span>Seller University</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY Seller University & Academy</h1>
                    <p class="dso-page-subtitle">Masterclasses, optimization checklists, DPIN guidelines, and policy blueprints to scale your GMV 10x on DEJOIY</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=learn-guides" class="dso-btn dso-btn-outline">All Guides</a>
                    <a href="?section=learn-kb" class="dso-btn dso-btn-primary">Knowledgebase FAQs ↗</a>
                </div>
            </div>

            <!-- Curriculum Pillars -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-app-icon" style="font-size: 32px;margin-bottom:12px;">🏷️</div>
                        <h3 style="margin:0 0 8px;font-size:16px;color:#0f172a;">DPIN & Cataloging Excellence</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">How the 11-character DEJOIY Product Identification Number powers organic search rank, Google Shopping indexing, and Listing Quality Scores (LQS).</p>
                        <a href="?section=learn-guides#dpin-guide" class="dso-btn dso-btn-sm dso-btn-primary dso-mt-3">Read DPIN Playbook →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-app-icon" style="font-size: 32px;margin-bottom:12px;">🚚</div>
                        <h3 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Logistics & Dispatch SLAs</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">Standard operating procedures for 24-hour order dispatch, automated label generation, courier pickups, and reducing Return to Origin (RTO) rates.</p>
                        <a href="?section=learn-policies#sla-policy" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">View Shipping SLAs →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-app-icon" style="font-size: 32px;margin-bottom:12px;">💰</div>
                        <h3 style="margin:0 0 8px;font-size:16px;color:#0f172a;">Settlements, GST & Payouts</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">Everything about the Wednesday automated payout cycle, Section 194-O 1% TDS, 1% GST TCS credits, and downloading monthly commission invoices.</p>
                        <a href="?section=learn-policies#payout-policy" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Payout & Tax Rules →</a>
                    </div>
                </div>
            </div>

            <!-- Deep Dive Operational Modules -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">📚 Core Marketplace Curriculum</h3>
                </div>
                <div class="dso-card-body">
                    <div class="dso-grid-2">
                        <div class="dso-info-box" style="background:#f8fafc;padding:18px;border-radius:12px;border:1px solid #e2e8f0;">
                            <h4 style="margin:0 0 8px;color:#0066ff;font-size:15px;">1. Optimizing Listing Quality Score (LQS)</h4>
                            <p style="margin:0 0 10px;font-size:13px;color:#475569;line-height:1.6;">
                                Listings with LQS ≥ 85 receive <strong>3.8x more impressions</strong> on DEJOIY search. Key factors include:
                            </p>
                            <ul style="padding-left:18px;font-size:13px;color:#475569;line-height:1.8;margin:0;">
                                <li>Descriptive product title (Formula: Brand + Model + Material + Color/Size)</li>
                                <li>At least 4 high-res photos (1000×1000px minimum with pure white main cover)</li>
                                <li>Clear MRP and competitive selling price (showing discount badges)</li>
                                <li>Complete category attributes (fabric, origin, dimensions, care instructions)</li>
                            </ul>
                        </div>

                        <div class="dso-info-box" style="background:#f8fafc;padding:18px;border-radius:12px;border:1px solid #e2e8f0;">
                            <h4 style="margin:0 0 8px;color:#0066ff;font-size:15px;">2. Understanding DPIN (DEJOIY Product ID)</h4>
                            <p style="margin:0 0 10px;font-size:13px;color:#475569;line-height:1.6;">
                                Every listing created on DEJOIY is immediately assigned a permanent, unique 11-character identifier (e.g. <code>DEZCZEZDQ3L</code>):
                            </p>
                            <ul style="padding-left:18px;font-size:13px;color:#475569;line-height:1.8;margin:0;">
                                <li>Permanent canonical URL: <code>https://dejoiy.com/dpin/[DPIN]</code></li>
                                <li>Enables universal search across Seller Central, shipping labels, and buyer support</li>
                                <li>Click-to-copy DPIN pill available on your Products table and orders dispatch view</li>
                                <li>Guarantees brand protection and prevents duplicate listing collisions</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick FAQ Accordion -->
            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">💡 Frequently Asked Operational Questions</h3>
                </div>
                <div class="dso-card-body">
                    <div class="dso-faq-list">
                        <div class="dso-faq-item dso-mb-4">
                            <h4 style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;">When do funds from completed orders become available for withdrawal?</h4>
                            <p class="dso-text-muted" style="font-size:13px;line-height:1.6;margin:0;">
                                Funds enter your available treasury balance 7 calendar days after order delivery (to allow for the buyer return window). Settlements are disbursed automatically every Wednesday via RBI IMPS/NEFT, or you can request an instant early payout anytime through the <a href="?section=withdrawals">Withdrawals section</a>.
                            </p>
                        </div>
                        <div class="dso-faq-item dso-mb-4">
                            <h4 style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;">How do I maintain a Platinum Tier seller rating?</h4>
                            <p class="dso-text-muted" style="font-size:13px;line-height:1.6;margin:0;">
                                Platinum status requires: (1) Account Health Score ≥ 90/100, (2) On-time dispatch rate ≥ 95%, (3) Order cancellation rate &lt; 1%, and (4) Catalog LQS average ≥ 80%. Platinum sellers enjoy priority algorithm ranking, lowest commission brackets, and a dedicated account manager.
                            </p>
                        </div>
                        <div class="dso-faq-item">
                            <h4 style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px;">How does DEJOIY protect sellers against fake buyer returns?</h4>
                            <p class="dso-text-muted" style="font-size:13px;line-height:1.6;margin:0;">
                                All return pickups require OTP verification from the buyer, and returns are checked at the hub against your dispatch video/photo records. In case of wrong or damaged returns, submit a case within 48 hours under <a href="?section=support">Support Desk</a> with category <em>Returns & Claims</em> for full reimbursement.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function knowledgebase() {
        $this->render();
    }

    public function guides() {
        ?>
        <div class="dso-page dso-guides">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=learn">Learn</a>
                        <span>/</span>
                        <span>Guides</span>
                    </div>
                    <h1 class="dso-page-title">Listing Optimization & DPIN Standards</h1>
                    <p class="dso-page-subtitle">Actionable manuals designed to maximize search visibility and organic rank</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=learn" class="dso-btn dso-btn-outline">← Back to University</a>
                </div>
            </div>

            <div class="dso-grid-2">
                <div class="dso-card" id="dpin-guide">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">1. High-Conversion Title Architecture</h3>
                    </div>
                    <div class="dso-card-body">
                        <p>Follow the standard DEJOIY naming formula:</p>
                        <div style="background:#f1f5f9;padding:12px;border-radius:8px;font-family:monospace;font-size:12px;color:#0f172a;margin:10px 0;">
                            [Brand Name] + [Core Item Type] + [Key Specification/Material] + [Color / Size / Pack]
                        </div>
                        <p class="dso-text-muted" style="font-size:13px;"><em>Example: DEJOIY Essentials Men's 100% Combed Cotton Oversized T-Shirt (Midnight Black, Large)</em></p>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">2. Professional Photography Standards</h3>
                    </div>
                    <div class="dso-card-body">
                        <ul class="dso-clean-list" style="font-size:13px;line-height:1.8;color:#475569;">
                            <li>✓ Minimum resolution: 1000 x 1000 pixels (enables zoom on product page)</li>
                            <li>✓ Pure white or neutral background for primary cover image</li>
                            <li>✓ Include lifestyle in-context photos and close-ups of fabric/stitching</li>
                            <li>✓ Zero watermarks, unauthorized promo badges, or borders on photos</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function tutorials() {
        ?>
        <div class="dso-page dso-tutorials">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=learn">Learn</a>
                        <span>/</span>
                        <span>Video Masterclasses</span>
                    </div>
                    <h1 class="dso-page-title">Video Tutorials & Masterclasses</h1>
                    <p class="dso-page-subtitle">Watch quick 3-minute video guides on operating your store like a pro</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=learn" class="dso-btn dso-btn-outline">← Back to University</a>
                </div>
            </div>

            <div class="dso-grid-3">
                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-video-thumb dso-mb-3" style="width:100%; height:120px; background:linear-gradient(135deg, #000c2c, #001553); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
                            ▶️
                        </div>
                        <h4 style="margin:0 0 6px;">Mastering Product Creation & DPIN</h4>
                        <p class="dso-text-muted" style="font-size:12px;line-height:1.5;">Learn how to create listings, set up variation matrices, and obtain your DPIN in under 3 minutes.</p>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-video-thumb dso-mb-3" style="width:100%; height:120px; background:linear-gradient(135deg, #064e3b, #065f46); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
                            ▶️
                        </div>
                        <h4 style="margin:0 0 6px;">Order Dispatch & Logistics SLAs</h4>
                        <p class="dso-text-muted" style="font-size:12px;line-height:1.5;">How to generate shipping labels, print packing slips, and coordinate pickups with courier partners.</p>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-video-thumb dso-mb-3" style="width:100%; height:120px; background:linear-gradient(135deg, #1e293b, #0f172a); border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:28px;">
                            ▶️
                        </div>
                        <h4 style="margin:0 0 6px;">Reconciling Payouts & Taxes</h4>
                        <p class="dso-text-muted" style="font-size:12px;line-height:1.5;">Understanding GST TCS deductions, Section 194-O TDS credits, and weekly bank settlements.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function policies() {
        ?>
        <div class="dso-page dso-policies">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=learn">Learn</a>
                        <span>/</span>
                        <span>Policies & Fees</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Policies & SLAs</h1>
                    <p class="dso-page-subtitle">Clear, enforceable operational standards for DEJOIY partner sellers</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=learn" class="dso-btn dso-btn-outline">← Back to University</a>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body">
                    <div class="dso-policy-section dso-mb-4" id="sla-policy">
                        <h3 style="color:#0f172a;">1. Seller Service Level Agreement (SLA)</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">
                            All orders must be packaged and marked 'Ready to Ship' within 24 business hours of order placement. Orders unfulfilled after 72 hours are subject to automated cancellation and account health penalties.
                        </p>
                    </div>

                    <div class="dso-policy-section dso-mb-4" id="payout-policy">
                        <h3 style="color:#0f172a;">2. Commission & Settlement Policy</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">
                            DEJOIY operates on a transparent, category-based commission model ranging between 5% and 15%. No listing fees, no monthly software subscription fees. Settlements are processed weekly directly via RBI IMPS/NEFT rails.
                        </p>
                    </div>

                    <div class="dso-policy-section">
                        <h3 style="color:#0f172a;">3. Zero-Tolerance Counterfeit & Brand Protection Policy</h3>
                        <p class="dso-text-muted" style="font-size:13px;line-height:1.6;">
                            Sellers offering fake, counterfeit, or expired merchandise will have their accounts immediately suspended and payouts permanently withheld in accordance with Indian e-commerce consumer protection regulations.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
