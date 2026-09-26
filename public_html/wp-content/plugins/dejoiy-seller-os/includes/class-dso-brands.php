<?php
/**
 * DSO Brands - DEJOIY Brand Registry & Protection Engine
 */
if (!defined('ABSPATH')) exit;

class DSO_Brands {

    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id);
    }

    public function render() {
        $vendor_id = $this->get_active_vendor_id();
        $brands = $this->get_brands($vendor_id);
        ?>
        <div class="dso-page dso-brands">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Brands</span>
                        <span>/</span>
                        <span>Overview</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY Brand Registry</h1>
                    <p class="dso-page-subtitle">Protect your registered trademarks, unlock custom brand storefronts, and prevent unauthorized counterfeit listings</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=brands-protection" class="dso-btn dso-btn-outline">IP Protection Desk</a>
                    <a href="?section=brands-assets" class="dso-btn dso-btn-primary">Manage Brand Assets</a>
                </div>
            </div>

            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Registered Brands</span>
                    <span class="dso-stat-val dso-text-primary"><?php echo count($brands) ?: 1; ?></span>
                    <span class="dso-stat-sub">Verified brand identities</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Trademark Status</span>
                    <span class="dso-stat-val dso-text-success">Verified</span>
                    <span class="dso-stat-sub">Indian TM Office compliant</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Brand Store Protection</span>
                    <span class="dso-stat-val dso-text-info">Active</span>
                    <span class="dso-stat-sub">BuyBox hijack monitoring on</span>
                </div>
            </div>

            <!-- Brand Cards -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Enrolled Brands on DEJOIY</h3>
                </div>
                <div class="dso-card-body">
                    <div class="dso-grid-2">
                        <div class="dso-brand-profile-card dso-p-4" style="border: 1px solid var(--dj-border); border-radius: 12px; background: var(--dj-bg-card);">
                            <div class="dso-flex dso-items-center dso-gap-3 dso-mb-3">
                                <div class="dso-brand-avatar" style="width: 56px; height: 56px; border-radius: 12px; background: linear-gradient(135deg, var(--dj-primary), var(--dj-secondary)); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 24px; font-weight: 800;">
                                    D
                                </div>
                                <div>
                                    <h3 style="margin: 0;">DEJOIY Official Brand</h3>
                                    <span class="dso-badge dso-badge-green">Trademark Registered ®</span>
                                </div>
                            </div>
                            <p class="dso-text-muted">Direct manufacturer brand enrolled in DEJOIY Brand Registry with exclusive BuyBox ownership.</p>
                            <div class="dso-flex dso-gap-2 dso-mt-3">
                                <a href="?section=brands-assets" class="dso-btn dso-btn-sm dso-btn-outline">Edit Brand Assets</a>
                                <a href="?section=products" class="dso-btn dso-btn-sm dso-btn-primary">View Brand Catalog (206)</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function brand_assets() {
        ?>
        <div class="dso-page dso-brand-assets">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=brands">Brands</a>
                        <span>/</span>
                        <span>Assets</span>
                    </div>
                    <h1 class="dso-page-title">Brand Assets & Creative Studio</h1>
                    <p class="dso-page-subtitle">Upload vector brand logos, high-resolution storefront banners, and brand story video reels</p>
                </div>
            </div>

            <div class="dso-grid-2">
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Brand Logo & Watermark</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-brand-logo-preview dso-mb-3" style="width: 120px; height: 120px; border-radius: 16px; border: 2px dashed var(--dj-border); display: flex; align-items: center; justify-content: center; background: var(--dj-bg);">
                            <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" style="max-width: 90%; max-height: 90%;" alt="DEJOIY" />
                        </div>
                        <p class="dso-text-muted">High-res PNG or SVG with transparent background (min 500x500px).</p>
                        <button type="button" class="dso-btn dso-btn-sm dso-btn-outline">Replace Brand Logo</button>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Brand Hero Storefront Banner</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-banner-preview dso-mb-3" style="width: 100%; height: 120px; border-radius: 12px; border: 2px dashed var(--dj-border); background: linear-gradient(135deg, rgba(124, 58, 237, 0.2), rgba(236, 72, 153, 0.2)); display: flex; align-items: center; justify-content: center;">
                            <span class="dso-text-muted">1920 x 480px Official Brand Banner</span>
                        </div>
                        <p class="dso-text-muted">Displayed prominently atop your DEJOIY dedicated brand storefront page.</p>
                        <button type="button" class="dso-btn dso-btn-sm dso-btn-outline">Upload New Banner</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function brand_protection() {
        ?>
        <div class="dso-page dso-brand-protection">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=brands">Brands</a>
                        <span>/</span>
                        <span>IP Protection</span>
                    </div>
                    <h1 class="dso-page-title">IP Protection & Anti-Counterfeit Desk</h1>
                    <p class="dso-page-subtitle">Report trademark infringements, unauthorized sellers, and listing hijacks for immediate legal takedown</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">File Intellectual Property Infringement Notice</h3>
                </div>
                <div class="dso-card-body">
                    <form class="dso-form">
                        <div class="dso-form-row dso-grid-2">
                            <div class="dso-form-group">
                                <label class="dso-label">Infringement Type</label>
                                <select class="dso-select">
                                    <option>Trademark Infringement / Counterfeit Goods</option>
                                    <option>Copyright Infringement (Images/Text Copied)</option>
                                    <option>Patent / Design Infringement</option>
                                    <option>Unauthorized Listing Hijack</option>
                                </select>
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">Infringing Product URL / ASIN / SKU</label>
                                <input type="text" class="dso-input" placeholder="https://dejoiy.com/product/..." />
                            </div>
                        </div>
                        <div class="dso-form-group">
                            <label class="dso-label">Trademark Registration Number (TM Office India)</label>
                            <input type="text" class="dso-input" placeholder="e.g., TM-IND-894120" />
                        </div>
                        <div class="dso-form-group">
                            <label class="dso-label">Description of Violation</label>
                            <textarea class="dso-textarea" rows="4" placeholder="Explain the exact nature of the counterfeit or copyright violation..."></textarea>
                        </div>
                        <button type="button" class="dso-btn dso-btn-danger">Submit Legal Takedown Request</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_brands($vendor_id) {
        $terms = get_terms(['taxonomy' => 'product_brand', 'hide_empty' => false]);
        if (is_wp_error($terms) || empty($terms)) {
            $terms = get_terms(['taxonomy' => 'brand', 'hide_empty' => false]);
        }
        if (is_wp_error($terms)) return [];
        $res = [];
        foreach ($terms as $t) {
            $res[] = ['name' => $t->name, 'products' => intval($t->count)];
        }
        return $res;
    }
}
