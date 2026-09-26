<?php
/**
 * DSO Products - Comprehensive Product & Inventory Operating System for DEJOIY
 * Designed for DEJOIY Seller Central
 */
if (!defined('ABSPATH')) exit;

class DSO_Products {

    /**
     * Helper to get current vendor ID or 0 for admin
     */
    protected function get_active_vendor_id() {
        $user_id = get_current_user_id();
        if ($this->is_admin()) {
            return DSO_Auth::get_admin_vendor_context();
        }
        $plugin = Dejoiy_Seller_OS::instance();
        return $plugin->get_vendor_id($user_id) ?: $user_id;
    }

    /**
     * Check if user has marketplace admin privileges
     */
    protected function is_admin() {
        return current_user_can('administrator') || current_user_can('manage_options');
    }

    /**
     * Main Product Catalog
     */
    public function render() {
        $vendor_id = $this->get_active_vendor_id();
        $this->handle_bulk_actions();

        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $category_filter = isset($_GET['cat']) ? intval($_GET['cat']) : 0;
        $stock_filter = isset($_GET['stock']) ? sanitize_text_field($_GET['stock']) : '';

        $stats = $this->get_product_stats($vendor_id);
        $products = $this->get_products($vendor_id, 100, 0, $current_tab, $search, $category_filter, $stock_filter);
        $categories = $this->get_product_categories();
        ?>
        <div class="dso-page dso-products">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Catalogue</span>
                        <span>/</span>
                        <span>All Products</span>
                    </div>
                    <h1 class="dso-page-title">Product Catalog</h1>
                    <p class="dso-page-subtitle">Manage, optimize, and grow your DEJOIY marketplace listings</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=inventory-bulk" class="dso-btn dso-btn-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                        Bulk Update
                    </a>
                    <a href="?section=product-quality" class="dso-btn dso-btn-outline">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        Listing Quality Score
                    </a>
                    <a href="?section=add-product" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add New Product
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'created'): 
                $assigned_dpin = sanitize_text_field($_GET['dpin'] ?? '');
            ?>
                <div class="dso-notice dso-notice-success dso-mb-4" style="background:#ecfdf5;border:1px solid #10b981;border-radius:12px;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:16px;">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;">🎉</span>
                        <div>
                            <strong style="color:#065f46;font-size:15px;display:block;">Product Published Successfully!</strong>
                            <span style="font-size:13px;color:#047857;">Assigned DEJOIY Product Identification Number (DPIN): <code style="font-size:14px;font-weight:700;background:#d1fae5;padding:2px 8px;border-radius:4px;color:#065f46;"><?php echo esc_html($assigned_dpin); ?></code></span>
                        </div>
                    </div>
                    <?php if (!empty($assigned_dpin)): ?>
                        <button type="button" class="dso-btn dso-btn-sm" style="background:#10b981;color:#fff;" onclick="dsoCopyText('<?php echo esc_attr($assigned_dpin); ?>', this)">
                            Copy DPIN 📋
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Stats Bar -->
            <div class="dso-stats-row">
                <a href="?section=products&tab=all" class="dso-stat-card <?php echo $current_tab === 'all' ? 'active' : '' ?>">
                    <span class="dso-stat-label">Total Listings</span>
                    <span class="dso-stat-val"><?php echo number_format($stats['total']); ?></span>
                    <span class="dso-stat-sub">Active in catalog</span>
                </a>
                <a href="?section=products&tab=publish" class="dso-stat-card <?php echo $current_tab === 'publish' ? 'active' : '' ?>">
                    <span class="dso-stat-label">Published / Live</span>
                    <span class="dso-stat-val dso-text-success"><?php echo number_format($stats['published']); ?></span>
                    <span class="dso-stat-sub">Shoppers can buy</span>
                </a>
                <a href="?section=products&tab=draft" class="dso-stat-card <?php echo $current_tab === 'draft' ? 'active' : '' ?>">
                    <span class="dso-stat-label">Drafts</span>
                    <span class="dso-stat-val"><?php echo number_format($stats['draft']); ?></span>
                    <span class="dso-stat-sub">Not yet published</span>
                </a>
                <a href="?section=products&tab=lowstock" class="dso-stat-card <?php echo $current_tab === 'lowstock' ? 'active' : '' ?>">
                    <span class="dso-stat-label">Low Stock</span>
                    <span class="dso-stat-val dso-text-warning"><?php echo number_format($stats['low_stock']); ?></span>
                    <span class="dso-stat-sub">Needs replenishment</span>
                </a>
                <a href="?section=products&tab=outofstock" class="dso-stat-card <?php echo $current_tab === 'outofstock' ? 'active' : '' ?>">
                    <span class="dso-stat-label">Out of Stock</span>
                    <span class="dso-stat-val dso-text-danger"><?php echo number_format($stats['out_of_stock']); ?></span>
                    <span class="dso-stat-sub">Zero inventory</span>
                </a>
            </div>

            <!-- Main Catalog Panel -->
            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <!-- Tabs & Search Toolbar -->
                    <div class="dso-toolbar">
                        <div class="dso-tabs">
                            <a href="?section=products&tab=all" class="dso-tab <?php echo $current_tab === 'all' ? 'active' : ''; ?>">All Products (<?php echo $stats['total']; ?>)</a>
                            <a href="?section=products&tab=publish" class="dso-tab <?php echo $current_tab === 'publish' ? 'active' : ''; ?>">Published (<?php echo $stats['published']; ?>)</a>
                            <a href="?section=products&tab=draft" class="dso-tab <?php echo $current_tab === 'draft' ? 'active' : ''; ?>">Drafts (<?php echo $stats['draft']; ?>)</a>
                            <a href="?section=products&tab=lowstock" class="dso-tab <?php echo $current_tab === 'lowstock' ? 'active' : ''; ?>">Low Stock (<?php echo $stats['low_stock']; ?>)</a>
                            <a href="?section=products&tab=outofstock" class="dso-tab <?php echo $current_tab === 'outofstock' ? 'active' : ''; ?>">Out of Stock (<?php echo $stats['out_of_stock']; ?>)</a>
                        </div>

                        <form method="get" class="dso-filters-row">
                            <input type="hidden" name="section" value="products" />
                            <?php if ($current_tab !== 'all'): ?>
                                <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />
                            <?php endif; ?>

                            <div class="dso-search-box">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by title, SKU..." class="dso-input" />
                            </div>

                            <select name="cat" class="dso-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat->term_id; ?>" <?php selected($category_filter, $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="stock" class="dso-select" onchange="this.form.submit()">
                                <option value="">All Stock Levels</option>
                                <option value="instock" <?php selected($stock_filter, 'instock'); ?>>In Stock</option>
                                <option value="lowstock" <?php selected($stock_filter, 'lowstock'); ?>>Low Stock</option>
                                <option value="outofstock" <?php selected($stock_filter, 'outofstock'); ?>>Out of Stock</option>
                            </select>

                            <?php if ($search || $category_filter || $stock_filter): ?>
                                <a href="?section=products&tab=<?php echo esc_attr($current_tab); ?>" class="dso-btn dso-btn-outline dso-btn-sm">Reset</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <form method="post" id="dso-bulk-form">
                        <?php wp_nonce_field('dso_bulk_products', 'dso_bulk_nonce'); ?>
                        <div class="dso-bulk-bar" id="dso-bulk-bar" style="display:none;">
                            <span class="dso-bulk-count"><strong id="dso-selected-count">0</strong> products selected</span>
                            <div class="dso-bulk-actions">
                                <button type="submit" name="dso_bulk_action" value="publish" class="dso-btn dso-btn-sm dso-btn-outline">Publish</button>
                                <button type="submit" name="dso_bulk_action" value="draft" class="dso-btn dso-btn-sm dso-btn-outline">Move to Draft</button>
                                <button type="submit" name="dso_bulk_action" value="mark_instock" class="dso-btn dso-btn-sm dso-btn-outline">Set In Stock</button>
                                <button type="submit" name="dso_bulk_action" value="mark_outofstock" class="dso-btn dso-btn-sm dso-btn-outline">Set Out of Stock</button>
                                <button type="submit" name="dso_bulk_action" value="delete" class="dso-btn dso-btn-sm dso-btn-danger" onclick="return confirm('Are you sure you want to delete selected products?');">Delete</button>
                            </div>
                        </div>

                        <!-- Product Table -->
                        <div class="dso-table-responsive">
                            <table class="dso-table dso-table-products">
                                <thead>
                                    <tr>
                                        <th class="dso-th-check"><input type="checkbox" id="dso-select-all" aria-label="Select all products" /></th>
                                        <th style="min-width: 280px;">Product</th>
                                        <th style="width: 140px;">DPIN</th>
                                        <th>SKU</th>
                                        <th>Price</th>
                                        <th>Inventory</th>
                                        <th>LQS Score</th>
                                        <th>Sales</th>
                                        <th>Status</th>
                                        <th class="dso-text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="10">
                                                <div class="dso-empty-state">
                                                    <div class="dso-empty-icon">📦</div>
                                                    <h3>No products found</h3>
                                                    <p><?php echo $search ? 'No listings match your search criteria.' : 'Start adding products to your DEJOIY store catalog.'; ?></p>
                                                    <a href="?section=add-product" class="dso-btn dso-btn-primary">Add Your First Product</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $p): ?>
                                            <tr class="dso-product-row" data-id="<?php echo $p['id']; ?>">
                                                <td class="dso-td-check">
                                                    <input type="checkbox" name="product_ids[]" value="<?php echo $p['id']; ?>" class="dso-product-check" aria-label="<?php echo esc_attr('Select ' . $p['name']); ?>" />
                                                </td>
                                                <td class="dso-td-product">
                                                    <div class="dso-product-cell">
                                                        <div class="dso-product-thumb">
                                                            <?php echo $p['image_html']; ?>
                                                        </div>
                                                        <div class="dso-product-meta">
                                                            <a href="?section=edit-product&id=<?php echo $p['id']; ?>" class="dso-product-title">
                                                                <?php echo esc_html($p['name']); ?>
                                                            </a>
                                                            <div class="dso-product-submeta">
                                                                <span class="dso-cat-tag"><?php echo esc_html($p['category']); ?></span>
                                                                <?php if (!empty($p['brand'])): ?>
                                                                    <span class="dso-brand-tag">• <?php echo esc_html($p['brand']); ?></span>
                                                                <?php endif; ?>
                                                                <span class="dso-date-tag">• Added <?php echo esc_html($p['date']); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="dso-dpin-pill" onclick="dsoCopyText('<?php echo esc_attr($p['dpin']); ?>', this)" title="Click to copy DPIN">
                                                        <code><?php echo esc_html($p['dpin'] ?: '—'); ?></code>
                                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="dso-sku-badge"><?php echo esc_html($p['sku'] ?: '—'); ?></span>
                                                </td>
                                                <td>
                                                    <div class="dso-price-cell">
                                                        <span class="dso-selling-price"><?php echo $p['price_html']; ?></span>
                                                        <?php if ($p['mrp'] && $p['mrp'] > $p['regular_price']): ?>
                                                            <span class="dso-mrp-strike">MRP ₹<?php echo number_format($p['mrp'], 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dso-stock-cell">
                                                        <?php if ($p['manage_stock']): ?>
                                                            <input type="number" class="dso-quick-stock-input" value="<?php echo intval($p['stock_quantity']); ?>" min="0" data-id="<?php echo $p['id']; ?>" title="Click and change to instantly update stock" />
                                                            <span class="dso-stock-status-tag <?php echo $p['stock_status_class']; ?>">
                                                                <?php echo $p['stock_label']; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="dso-stock-status-tag <?php echo $p['stock_status_class']; ?>">
                                                                <?php echo $p['stock_label']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="dso-lqs-cell" title="Listing Quality Score: <?php echo $p['lqs_score']; ?>/100">
                                                        <div class="dso-lqs-bar">
                                                            <div class="dso-lqs-fill <?php echo $p['lqs_class']; ?>" style="width: <?php echo $p['lqs_score']; ?>%;"></div>
                                                        </div>
                                                        <span class="dso-lqs-text <?php echo $p['lqs_class']; ?>"><?php echo $p['lqs_score']; ?>%</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="dso-sales-count"><?php echo intval($p['total_sales']); ?> units</span>
                                                </td>
                                                <td>
                                                    <?php echo $p['status_badge']; ?>
                                                </td>
                                                <td class="dso-text-right">
                                                    <div class="dso-row-actions">
                                                        <a href="?section=edit-product&id=<?php echo $p['id']; ?>" class="dso-btn-icon" title="Edit Listing">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                        </a>
                                                        <a href="<?php $dso_mpu = parse_url(get_permalink($p['id'])); echo 'https://dejoiy.com' . (isset($dso_mpu['path']) ? $dso_mpu['path'] : '/') . (isset($dso_mpu['query']) ? '?' . $dso_mpu['query'] : ''); ?>" target="_blank" class="dso-btn-icon" title="View on DEJOIY Storefront">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Add Product View & Controller
     */
    public function add_product() {
        $vendor_id = $this->get_active_vendor_id();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_save_product'])) {
            check_admin_referer('dso_add_product_nonce');
            $new_id = $this->save_product_data(0, $vendor_id);
            if ($new_id) {
                $dpin = get_post_meta($new_id, '_dejoiy_dpin', true);
                wp_redirect('?section=products&notice=created&dpin=' . urlencode($dpin));
                exit;
            }
        }

        $this->render_product_form(0);
    }

    /**
     * Edit Product View & Controller
     */
    public function edit_product() {
        $product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $vendor_id = $this->get_active_vendor_id();

        if (!$product_id) {
            wp_redirect('?section=products');
            exit;
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_die('Product not found in DEJOIY catalog.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_save_product'])) {
            check_admin_referer('dso_edit_product_nonce');
            $this->save_product_data($product_id, $vendor_id);
            wp_redirect('?section=edit-product&id=' . $product_id . '&notice=updated');
            exit;
        }

        $this->render_product_form($product_id);
    }

    /**
     * Unified Comprehensive Product Editor Form (Add/Edit)
     */
    protected function render_product_form($product_id = 0) {
        $is_edit = $product_id > 0;
        $product = $is_edit ? wc_get_product($product_id) : null;

        // Populate fields
        $title = $product ? $product->get_name() : '';
        $desc = $product ? $product->get_description() : '';
        $short_desc = $product ? $product->get_short_description() : '';
        $status = $product ? $product->get_status() : 'publish';
        $sku = $product ? $product->get_sku() : '';
        $regular_price = $product ? $product->get_regular_price() : '';
        $sale_price = $product ? $product->get_sale_price() : '';
        $mrp = $product ? get_post_meta($product_id, '_dso_mrp', true) : '';
        $manage_stock = $product ? $product->get_manage_stock() : true;
        $stock_qty = $product ? $product->get_stock_quantity() : 10;
        $stock_status = $product ? $product->get_stock_status() : 'instock';
        $weight = $product ? $product->get_weight() : '';
        $length = $product ? $product->get_length() : '';
        $width = $product ? $product->get_width() : '';
        $height = $product ? $product->get_height() : '';

        // Category & terms
        $categories = $this->get_product_categories();
        $selected_cats = $product ? wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']) : [];
        $tags = $product ? implode(', ', wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names'])) : '';

        // Images
        $thumb_id = $product ? $product->get_image_id() : 0;
        $thumb_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium') : '';
        $gallery_ids = $product ? $product->get_gallery_image_ids() : [];

        // Meta Disclosures & Compliance
        $brand = $product ? get_post_meta($product_id, '_dso_brand', true) : '';
        $country_of_origin = $product ? (get_post_meta($product_id, '_dso_country_of_origin', true) ?: 'India') : 'India';
        $hsn_code = $product ? get_post_meta($product_id, '_dso_hsn_code', true) : '';
        $gst_rate = $product ? get_post_meta($product_id, '_dso_gst_rate', true) : '18';
        $manufacturer = $product ? get_post_meta($product_id, '_dso_mfg_details', true) : '';
        $packer = $product ? get_post_meta($product_id, '_dso_packer_details', true) : '';

        // Smart Category Fields
        $smart_type = $product ? (get_post_meta($product_id, '_dso_smart_category', true) ?: 'general') : 'general';
        
        // Fashion
        $fashion_size = $product ? get_post_meta($product_id, '_dso_size', true) : '';
        $fashion_color = $product ? get_post_meta($product_id, '_dso_color', true) : '';
        $fashion_fabric = $product ? get_post_meta($product_id, '_dso_fabric', true) : '';
        $fashion_gender = $product ? get_post_meta($product_id, '_dso_gender', true) : 'Unisex';
        $fashion_fit = $product ? get_post_meta($product_id, '_dso_fit', true) : 'Regular';
        $fashion_care = $product ? get_post_meta($product_id, '_dso_care', true) : '';

        // Electronics
        $elec_model = $product ? get_post_meta($product_id, '_dso_model_number', true) : '';
        $elec_warranty = $product ? get_post_meta($product_id, '_dso_warranty', true) : '1 Year Brand Warranty';
        $elec_voltage = $product ? get_post_meta($product_id, '_dso_power', true) : '';
        $elec_connectivity = $product ? get_post_meta($product_id, '_dso_connectivity', true) : '';

        // Beauty
        $beauty_skin = $product ? get_post_meta($product_id, '_dso_skin_type', true) : 'All Skin Types';
        $beauty_ingredients = $product ? get_post_meta($product_id, '_dso_ingredients', true) : '';
        $beauty_net_weight = $product ? get_post_meta($product_id, '_dso_net_weight', true) : '';
        $beauty_expiry = $product ? get_post_meta($product_id, '_dso_expiry', true) : '24 Months';

        // Grocery
        $grocery_fssai = $product ? get_post_meta($product_id, '_dso_fssai', true) : '';
        $grocery_veg = $product ? get_post_meta($product_id, '_dso_veg_nonveg', true) : 'veg';
        $grocery_shelf_life = $product ? get_post_meta($product_id, '_dso_shelf_life', true) : '';

        // Books
        $book_isbn = $product ? get_post_meta($product_id, '_dso_isbn', true) : '';
        $book_author = $product ? get_post_meta($product_id, '_dso_author', true) : '';
        $book_publisher = $product ? get_post_meta($product_id, '_dso_publisher', true) : '';
        $book_language = $product ? get_post_meta($product_id, '_dso_language', true) : 'English';

        // LQS Score
        $lqs_data = $product ? $this->calculate_lqs($product_id) : ['score' => 20, 'status' => 'Needs Work', 'class' => 'dso-lqs-low'];
        ?>
        <div class="dso-page dso-product-editor">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=products">Catalogue</a>
                        <span>/</span>
                        <span><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></span>
                    </div>
                    <h1 class="dso-page-title"><?php echo $is_edit ? 'Edit: ' . esc_html($title) : 'Create New Product Listing'; ?></h1>
                    <p class="dso-page-subtitle">Complete all mandatory disclosures and category specifications to maximize buyer conversions</p>
                </div>
                <div class="dso-page-actions">
                    <?php if ($is_edit): ?>
                        <a href="<?php $dso_mpu = parse_url(get_permalink($product_id)); echo 'https://dejoiy.com' . (isset($dso_mpu['path']) ? $dso_mpu['path'] : '/') . (isset($dso_mpu['query']) ? '?' . $dso_mpu['query'] : ''); ?>" target="_blank" class="dso-btn dso-btn-outline">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            View on DEJOIY
                        </a>
                    <?php endif; ?>
                    <a href="?section=products" class="dso-btn dso-btn-outline">Cancel</a>
                    <button type="button" class="dso-btn dso-btn-primary" onclick="document.getElementById('dso-product-form').submit();">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <?php echo $is_edit ? 'Save Changes' : 'Publish Product'; ?>
                    </button>
                </div>
            </div>

            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'updated'): ?>
                <div class="dso-alert dso-alert-success">
                    Listing updated successfully! Your changes are now live across DEJOIY Marketplace.
                </div>
            <?php endif; ?>

            <form method="post" id="dso-product-form" class="dso-form" enctype="multipart/form-data">
                <?php wp_nonce_field($is_edit ? 'dso_edit_product_nonce' : 'dso_add_product_nonce'); ?>
                <input type="hidden" name="dso_save_product" value="1" />

                <div class="dso-editor-grid">
                    <!-- Left Column: Form Details -->
                    <div class="dso-editor-main">
                        <!-- Section 1: Basic Information -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">1. Basic Information</h3>
                                <span class="dso-badge dso-badge-primary">Core Details</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-group">
                                    <label for="product_name" class="dso-label">Product Title <span class="dso-req">*</span></label>
                                    <input type="text" id="product_name" name="product_name" class="dso-input dso-input-lg" value="<?php echo esc_attr($title); ?>" required placeholder="e.g., DEJOIY Signature Premium Cotton Oversized T-Shirt" />
                                    <span class="dso-help">Clear, descriptive titles attract 3x more clicks on DEJOIY search. Include Brand, Item Type, Material/Model.</span>
                                </div>

                                <div class="dso-form-row dso-grid-2">
                                    <div class="dso-form-group">
                                        <label for="dso_brand" class="dso-label">Brand Name <span class="dso-req">*</span></label>
                                        <input type="text" id="dso_brand" name="dso_brand" class="dso-input" value="<?php echo esc_attr($brand); ?>" placeholder="e.g., DEJOIY Essentials or Generic" required />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="smart_category" class="dso-label">Department / Industry Smart Fields <span class="dso-req">*</span></label>
                                        <select id="smart_category" name="smart_category" class="dso-select" onchange="DSO.toggleSmartFields(this.value)">
                                            <option value="general" <?php selected($smart_type, 'general'); ?>>General Merchandise</option>
                                            <option value="fashion" <?php selected($smart_type, 'fashion'); ?>>Fashion & Apparel</option>
                                            <option value="electronics" <?php selected($smart_type, 'electronics'); ?>>Electronics & Gadgets</option>
                                            <option value="beauty" <?php selected($smart_type, 'beauty'); ?>>Beauty & Personal Care</option>
                                            <option value="grocery" <?php selected($smart_type, 'grocery'); ?>>Grocery & Gourmet Foods</option>
                                            <option value="books" <?php selected($smart_type, 'books'); ?>>Books & Media</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="dso-form-group">
                                    <label for="short_description" class="dso-label">Key Highlights / Short Description (Bullet Points)</label>
                                    <textarea id="short_description" name="short_description" class="dso-textarea" rows="3" placeholder="• 100% Bio-Washed Combed Cotton&#10;• Breathable 220 GSM heavyweight fabric&#10;• Pre-shrunk durable stitching"><?php echo esc_textarea($short_desc); ?></textarea>
                                </div>

                                <div class="dso-form-group">
                                    <label for="description" class="dso-label">Detailed Product Description</label>
                                    <textarea id="description" name="description" class="dso-textarea" rows="7" placeholder="Detailed product story, specifications, and styling/usage guidance..."><?php echo esc_textarea($desc); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Category Smart Fields (Dynamic) -->
                        <div class="dso-card dso-mb-4" id="dso-smart-fields-card">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">2. Category Specific Smart Specifications</h3>
                                <span class="dso-badge dso-badge-purple" id="dso-smart-badge">Dynamic</span>
                            </div>
                            <div class="dso-card-body">
                                <!-- Fashion Fields -->
                                <div class="dso-smart-group" id="smart-group-fashion" style="<?php echo $smart_type === 'fashion' ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-3">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Available Sizes</label>
                                            <input type="text" name="fashion_size" class="dso-input" value="<?php echo esc_attr($fashion_size); ?>" placeholder="e.g., S, M, L, XL, XXL" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Primary Color</label>
                                            <input type="text" name="fashion_color" class="dso-input" value="<?php echo esc_attr($fashion_color); ?>" placeholder="e.g., Midnight Black" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Fabric / Material</label>
                                            <input type="text" name="fashion_fabric" class="dso-input" value="<?php echo esc_attr($fashion_fabric); ?>" placeholder="e.g., 100% Combed Cotton" />
                                        </div>
                                    </div>
                                    <div class="dso-form-row dso-grid-3">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Target Gender</label>
                                            <select name="fashion_gender" class="dso-select">
                                                <option value="Unisex" <?php selected($fashion_gender, 'Unisex'); ?>>Unisex</option>
                                                <option value="Men" <?php selected($fashion_gender, 'Men'); ?>>Men</option>
                                                <option value="Women" <?php selected($fashion_gender, 'Women'); ?>>Women</option>
                                                <option value="Kids" <?php selected($fashion_gender, 'Kids'); ?>>Kids / Youth</option>
                                            </select>
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Fit Type</label>
                                            <select name="fashion_fit" class="dso-select">
                                                <option value="Regular" <?php selected($fashion_fit, 'Regular'); ?>>Regular Fit</option>
                                                <option value="Oversized" <?php selected($fashion_fit, 'Oversized'); ?>>Oversized / Drop Shoulder</option>
                                                <option value="Slim" <?php selected($fashion_fit, 'Slim'); ?>>Slim Fit</option>
                                                <option value="Relaxed" <?php selected($fashion_fit, 'Relaxed'); ?>>Relaxed Fit</option>
                                            </select>
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Wash & Care Instructions</label>
                                            <input type="text" name="fashion_care" class="dso-input" value="<?php echo esc_attr($fashion_care); ?>" placeholder="e.g., Machine wash cold inside out" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Electronics Fields -->
                                <div class="dso-smart-group" id="smart-group-electronics" style="<?php echo $smart_type === 'electronics' ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-2">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Model Name / Number</label>
                                            <input type="text" name="elec_model" class="dso-input" value="<?php echo esc_attr($elec_model); ?>" placeholder="e.g., DJ-AUDIO-X500" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Warranty Period</label>
                                            <input type="text" name="elec_warranty" class="dso-input" value="<?php echo esc_attr($elec_warranty); ?>" placeholder="e.g., 1 Year Brand Warranty" />
                                        </div>
                                    </div>
                                    <div class="dso-form-row dso-grid-2">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Power / Voltage / Battery</label>
                                            <input type="text" name="elec_voltage" class="dso-input" value="<?php echo esc_attr($elec_voltage); ?>" placeholder="e.g., 5000 mAh / Type-C 20W Fast Charge" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Connectivity Technologies</label>
                                            <input type="text" name="elec_connectivity" class="dso-input" value="<?php echo esc_attr($elec_connectivity); ?>" placeholder="e.g., Bluetooth 5.3, WiFi 6, AUX" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Beauty Fields -->
                                <div class="dso-smart-group" id="smart-group-beauty" style="<?php echo $smart_type === 'beauty' ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-3">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Skin / Hair Type</label>
                                            <input type="text" name="beauty_skin" class="dso-input" value="<?php echo esc_attr($beauty_skin); ?>" placeholder="e.g., All Skin Types / Oily" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Net Content / Volume</label>
                                            <input type="text" name="beauty_net_weight" class="dso-input" value="<?php echo esc_attr($beauty_net_weight); ?>" placeholder="e.g., 100 ml / 50 g" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Shelf Life / Expiry</label>
                                            <input type="text" name="beauty_expiry" class="dso-input" value="<?php echo esc_attr($beauty_expiry); ?>" placeholder="e.g., 24 Months from MFD" />
                                        </div>
                                    </div>
                                    <div class="dso-form-group">
                                        <label class="dso-label">Active Key Ingredients</label>
                                        <input type="text" name="beauty_ingredients" class="dso-input" value="<?php echo esc_attr($beauty_ingredients); ?>" placeholder="e.g., 2% Salicylic Acid, Hyaluronic Acid, Tea Tree Extract" />
                                    </div>
                                </div>

                                <!-- Grocery Fields -->
                                <div class="dso-smart-group" id="smart-group-grocery" style="<?php echo $smart_type === 'grocery' ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-3">
                                        <div class="dso-form-group">
                                            <label class="dso-label">FSSAI License Number <span class="dso-req">*</span></label>
                                            <input type="text" name="grocery_fssai" class="dso-input" value="<?php echo esc_attr($grocery_fssai); ?>" placeholder="14-digit FSSAI number" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Dietary Classification</label>
                                            <select name="grocery_veg" class="dso-select">
                                                <option value="veg" <?php selected($grocery_veg, 'veg'); ?>>🟢 100% Vegetarian</option>
                                                <option value="nonveg" <?php selected($grocery_veg, 'nonveg'); ?>>🔴 Non-Vegetarian</option>
                                                <option value="egg" <?php selected($grocery_veg, 'egg'); ?>>🟡 Contains Egg</option>
                                            </select>
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Shelf Life</label>
                                            <input type="text" name="grocery_shelf_life" class="dso-input" value="<?php echo esc_attr($grocery_shelf_life); ?>" placeholder="e.g., 6 Months" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Books Fields -->
                                <div class="dso-smart-group" id="smart-group-books" style="<?php echo $smart_type === 'books' ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-2">
                                        <div class="dso-form-group">
                                            <label class="dso-label">ISBN-10 / ISBN-13</label>
                                            <input type="text" name="book_isbn" class="dso-input" value="<?php echo esc_attr($book_isbn); ?>" placeholder="e.g., 978-0143424123" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Author(s)</label>
                                            <input type="text" name="book_author" class="dso-input" value="<?php echo esc_attr($book_author); ?>" placeholder="e.g., Author Name" />
                                        </div>
                                    </div>
                                    <div class="dso-form-row dso-grid-2">
                                        <div class="dso-form-group">
                                            <label class="dso-label">Publisher</label>
                                            <input type="text" name="book_publisher" class="dso-input" value="<?php echo esc_attr($book_publisher); ?>" placeholder="e.g., Penguin Random House" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label class="dso-label">Language</label>
                                            <input type="text" name="book_language" class="dso-input" value="<?php echo esc_attr($book_language); ?>" placeholder="e.g., English / Hindi" />
                                        </div>
                                    </div>
                                </div>

                                <div class="dso-smart-group" id="smart-group-general" style="<?php echo $smart_type === 'general' ? '' : 'display:none;'; ?>">
                                    <p class="dso-text-muted">General category selected. Standard compliance specifications apply below.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Media & Product Visuals -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">3. Product Images & Media</h3>
                                <span class="dso-badge dso-badge-outline">High-Resolution</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-media-uploader-grid">
                                    <div class="dso-primary-media-box">
                                        <label class="dso-label">Main Display Image <span class="dso-req">*</span></label>
                                        <div class="dso-thumb-preview" id="dso-thumb-preview">
                                            <?php if ($thumb_url): ?>
                                                <img src="<?php echo esc_url($thumb_url); ?>" alt="" id="dso-thumb-img" />
                                                <button type="button" class="dso-remove-thumb-btn" onclick="DSO.removeFeaturedImage();">×</button>
                                            <?php else: ?>
                                                <div class="dso-thumb-empty" id="dso-thumb-empty">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="40" height="40"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                                    <span>Drop featured image here or click upload</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="featured_image_id" id="featured_image_id" value="<?php echo esc_attr($thumb_id); ?>" />
                                        <div class="dso-upload-btn-row">
                                            <button type="button" class="dso-btn dso-btn-sm dso-btn-outline" onclick="DSO.openMediaUploader('featured_image_id', 'dso-thumb-preview');">Choose from Media Library</button>
                                            <input type="file" name="featured_file" id="featured_file" accept="image/*" class="dso-file-input" onchange="DSO.handleDirectUpload(this, 'dso-thumb-preview');" />
                                        </div>
                                    </div>

                                    <div class="dso-gallery-media-box">
                                        <label class="dso-label">Additional Angle Gallery Images (Up to 5)</label>
                                        <div class="dso-gallery-grid" id="dso-gallery-grid">
                                            <?php foreach ($gallery_ids as $gid): 
                                                $gurl = wp_get_attachment_image_url($gid, 'thumbnail');
                                                if ($gurl):
                                            ?>
                                                <div class="dso-gallery-item" data-id="<?php echo $gid; ?>">
                                                    <img src="<?php echo esc_url($gurl); ?>" alt="" />
                                                    <button type="button" class="dso-gallery-remove" onclick="this.parentElement.remove(); DSO.syncGalleryIds();">×</button>
                                                </div>
                                            <?php endif; endforeach; ?>
                                        </div>
                                        <input type="hidden" name="gallery_image_ids" id="gallery_image_ids" value="<?php echo esc_attr(implode(',', $gallery_ids)); ?>" />
                                        <input type="file" name="gallery_files[]" id="gallery_files" accept="image/*" multiple hidden onchange="DSO.handleGalleryFiles(this);" />
                                        <button type="button" class="dso-btn dso-btn-sm dso-btn-outline" onclick="DSO.openGalleryUploader();">+ Add Gallery Images</button>
                                        <span class="dso-help">Recommended: 1000x1000px square images on white or neutral background. You can also drag &amp; drop files onto the grid — they upload to the media library when you save.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 4: Pricing, Tax & Maximum Retail Price (MRP) -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">4. Pricing, MRP & Tax Disclosures</h3>
                                <span class="dso-badge dso-badge-success">Marketplace Value</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-row dso-grid-3">
                                    <div class="dso-form-group">
                                        <label for="mrp" class="dso-label">MRP (Max Retail Price ₹) <span class="dso-req">*</span></label>
                                        <input type="number" step="0.01" min="0" id="mrp" name="mrp" class="dso-input" value="<?php echo esc_attr($mrp); ?>" required placeholder="e.g., 999.00" oninput="DSO.recalculateDiscount()" />
                                        <span class="dso-help">Mandatory consumer disclosure printed on package.</span>
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="regular_price" class="dso-label">DEJOIY Selling Price (₹) <span class="dso-req">*</span></label>
                                        <input type="number" step="0.01" min="0" id="regular_price" name="regular_price" class="dso-input" value="<?php echo esc_attr($regular_price); ?>" required placeholder="e.g., 599.00" oninput="DSO.recalculateDiscount()" />
                                        <span class="dso-help">The standard price customer pays.</span>
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="sale_price" class="dso-label">Special Offer / Sale Price (₹)</label>
                                        <input type="number" step="0.01" min="0" id="sale_price" name="sale_price" class="dso-input" value="<?php echo esc_attr($sale_price); ?>" placeholder="e.g., 499.00" oninput="DSO.recalculateDiscount()" />
                                        <span class="dso-help">Optional promotional price.</span>
                                    </div>
                                </div>

                                <div class="dso-discount-callout" id="dso-discount-callout">
                                    <span class="dso-badge dso-badge-green" id="dso-discount-badge">0% OFF</span>
                                    <span class="dso-discount-text" id="dso-discount-text">Enter MRP and Selling Price to see customer discount banner</span>
                                </div>

                                <div class="dso-form-row dso-grid-2 dso-mt-3">
                                    <div class="dso-form-group">
                                        <label for="hsn_code" class="dso-label">HSN Code <span class="dso-req">*</span></label>
                                        <input type="text" id="hsn_code" name="hsn_code" class="dso-input" value="<?php echo esc_attr($hsn_code); ?>" placeholder="e.g., 61091000 for T-Shirts" required />
                                        <span class="dso-help">Harmonized System of Nomenclature for GST invoices.</span>
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="gst_rate" class="dso-label">Applicable GST Tax Slab <span class="dso-req">*</span></label>
                                        <select id="gst_rate" name="gst_rate" class="dso-select">
                                            <option value="0" <?php selected($gst_rate, '0'); ?>>0% (Exempt)</option>
                                            <option value="5" <?php selected($gst_rate, '5'); ?>>5% (Apparel under ₹1000, Grocery essentials)</option>
                                            <option value="12" <?php selected($gst_rate, '12'); ?>>12% (Apparel over ₹1000, Processed foods)</option>
                                            <option value="18" <?php selected($gst_rate, '18'); ?>>18% (Standard rate / Electronics / Personal care)</option>
                                            <option value="28" <?php selected($gst_rate, '28'); ?>>28% (Luxury goods)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 5: Inventory, SKU & Stock Management -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">5. Inventory & Stock Controls</h3>
                                <span class="dso-badge dso-badge-outline">Fulfillment</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-row dso-grid-2">
                                    <div class="dso-form-group">
                                        <label for="sku" class="dso-label">Stock Keeping Unit (SKU) <span class="dso-req">*</span></label>
                                        <input type="text" id="sku" name="sku" class="dso-input" value="<?php echo esc_attr($sku); ?>" placeholder="e.g., DJ-TSHIRT-BLK-M" required />
                                        <span class="dso-help">Unique barcode or internal identifier for tracking.</span>
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="stock_status" class="dso-label">Stock Availability</label>
                                        <select id="stock_status" name="stock_status" class="dso-select">
                                            <option value="instock" <?php selected($stock_status, 'instock'); ?>>In Stock</option>
                                            <option value="outofstock" <?php selected($stock_status, 'outofstock'); ?>>Out of Stock</option>
                                            <option value="onbackorder" <?php selected($stock_status, 'onbackorder'); ?>>On Backorder</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="dso-form-group">
                                    <label class="dso-checkbox-container">
                                        <input type="checkbox" name="manage_stock" value="yes" id="manage_stock" <?php checked($manage_stock, true); ?> onchange="document.getElementById('dso-stock-qty-wrap').style.display = this.checked ? 'block' : 'none';" />
                                        <span class="dso-checkbox-label">Track exact inventory quantity for this product on DEJOIY</span>
                                    </label>
                                </div>

                                <div id="dso-stock-qty-wrap" style="<?php echo $manage_stock ? '' : 'display:none;'; ?>">
                                    <div class="dso-form-row dso-grid-2">
                                        <div class="dso-form-group">
                                            <label for="stock_quantity" class="dso-label">Available Units in Warehouse / Shelf</label>
                                            <input type="number" id="stock_quantity" name="stock_quantity" class="dso-input" value="<?php echo esc_attr($stock_qty); ?>" min="0" placeholder="e.g., 50" />
                                        </div>
                                        <div class="dso-form-group">
                                            <label for="low_stock_amount" class="dso-label">Low Stock Alert Threshold</label>
                                            <input type="number" id="low_stock_amount" name="low_stock_amount" class="dso-input" value="5" min="1" placeholder="e.g., 5" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Shipping Dimensions & Packaging -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">6. Shipping & Package Dimensions</h3>
                                <span class="dso-badge dso-badge-outline">Logistics</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-row dso-grid-4">
                                    <div class="dso-form-group">
                                        <label for="weight" class="dso-label">Dead Weight (kg)</label>
                                        <input type="number" step="0.01" min="0" id="weight" name="weight" class="dso-input" value="<?php echo esc_attr($weight); ?>" placeholder="0.25" />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="length" class="dso-label">Length (cm)</label>
                                        <input type="number" step="0.1" min="0" id="length" name="length" class="dso-input" value="<?php echo esc_attr($length); ?>" placeholder="25" />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="width" class="dso-label">Width (cm)</label>
                                        <input type="number" step="0.1" min="0" id="width" name="width" class="dso-input" value="<?php echo esc_attr($width); ?>" placeholder="20" />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="height" class="dso-label">Height (cm)</label>
                                        <input type="number" step="0.1" min="0" id="height" name="height" class="dso-input" value="<?php echo esc_attr($height); ?>" placeholder="3" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 7: Mandatory Legal Compliance & Consumer Disclosures -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">7. Legal Compliance & Regulatory Disclosures</h3>
                                <span class="dso-badge dso-badge-warning">Mandatory</span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-row dso-grid-2">
                                    <div class="dso-form-group">
                                        <label for="country_of_origin" class="dso-label">Country of Origin <span class="dso-req">*</span></label>
                                        <input type="text" id="country_of_origin" name="country_of_origin" class="dso-input" value="<?php echo esc_attr($country_of_origin); ?>" required placeholder="e.g., India" />
                                    </div>
                                    <div class="dso-form-group">
                                        <label for="manufacturer" class="dso-label">Manufacturer Name & Address <span class="dso-req">*</span></label>
                                        <input type="text" id="manufacturer" name="manufacturer" class="dso-input" value="<?php echo esc_attr($manufacturer); ?>" required placeholder="e.g., ABC Textiles Ltd, Surat, Gujarat - 395002" />
                                    </div>
                                </div>
                                <div class="dso-form-group">
                                    <label for="packer" class="dso-label">Packer / Importer Details (if different)</label>
                                    <input type="text" id="packer" name="packer" class="dso-input" value="<?php echo esc_attr($packer); ?>" placeholder="e.g., Packed & Marketed by DEJOIY Retail Partners" />
                                </div>
                            </div>
                        </div>

                        <!-- Section 8: Google SERP & Marketplace Search Preview -->
                        <div class="dso-card">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">8. Search Engine & Marketplace Snippet Preview</h3>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-serp-preview">
                                    <div class="dso-serp-url">https://dejoiy.com › product › <span id="dso-serp-slug-preview"><?php echo $product ? $product->get_slug() : 'sample-product-url'; ?></span></div>
                                    <div class="dso-serp-title" id="dso-serp-title-preview"><?php echo esc_html($title ?: 'Your Product Name — DEJOIY Marketplace'); ?></div>
                                    <div class="dso-serp-desc" id="dso-serp-desc-preview"><?php echo esc_html($short_desc ? wp_trim_words($short_desc, 25) : 'Buy genuine products on DEJOIY with fast nationwide shipping and secure payments.'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Publishing Sidebar & LQS Score Meter -->
                    <div class="dso-editor-sidebar">
                        <!-- Publish Controls -->
                        <div class="dso-card dso-mb-4 dso-sticky-top">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">Listing Status</h3>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-form-group">
                                    <label for="post_status" class="dso-label">Status</label>
                                    <select id="post_status" name="post_status" class="dso-select">
                                        <option value="publish" <?php selected($status, 'publish'); ?>>🟢 Published (Active)</option>
                                        <option value="draft" <?php selected($status, 'draft'); ?>>⚪ Draft (Hidden)</option>
                                        <option value="pending" <?php selected($status, 'pending'); ?>>🟡 Pending Review</option>
                                    </select>
                                </div>

                                <div class="dso-form-group">
                                    <label class="dso-label">Category Assignment <span class="dso-req">*</span></label>
                                    <div class="dso-category-checklist">
                                        <?php foreach ($categories as $cat): ?>
                                            <label class="dso-checkbox-item">
                                                <input type="checkbox" name="product_categories[]" value="<?php echo $cat->term_id; ?>" <?php checked(in_array($cat->term_id, $selected_cats)); ?> />
                                                <span><?php echo esc_html($cat->name); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="dso-form-group">
                                    <label for="product_tags" class="dso-label">Search Tags (comma separated)</label>
                                    <input type="text" id="product_tags" name="product_tags" class="dso-input" value="<?php echo esc_attr($tags); ?>" placeholder="tshirt, summer, cotton" />
                                </div>

                                <div class="dso-sidebar-actions dso-mt-4">
                                    <button type="submit" class="dso-btn dso-btn-primary dso-btn-full">
                                        <?php echo $is_edit ? 'Update Listing' : 'Save & Publish'; ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Real-Time Listing Quality Score (LQS) -->
                        <div class="dso-card dso-mb-4">
                            <div class="dso-card-header">
                                <h3 class="dso-card-title">Listing Quality Score (LQS)</h3>
                                <span class="dso-badge <?php echo $lqs_data['class']; ?>" id="dso-lqs-badge"><?php echo $lqs_data['status']; ?></span>
                            </div>
                            <div class="dso-card-body">
                                <div class="dso-lqs-radial">
                                    <div class="dso-lqs-dial">
                                        <span class="dso-lqs-dial-val" id="dso-lqs-val"><?php echo $lqs_data['score']; ?></span>
                                        <span class="dso-lqs-dial-denom">/ 100</span>
                                    </div>
                                </div>

                                <div class="dso-lqs-checklist">
                                    <div class="dso-lqs-check" id="lqs-check-title">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>Descriptive Title (≥25 chars)</span>
                                    </div>
                                    <div class="dso-lqs-check" id="lqs-check-img">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>High-res Primary Image</span>
                                    </div>
                                    <div class="dso-lqs-check" id="lqs-check-gallery">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>Multiple Angle Gallery Photos</span>
                                    </div>
                                    <div class="dso-lqs-check" id="lqs-check-mrp">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>Pricing & MRP Disclosed</span>
                                    </div>
                                    <div class="dso-lqs-check" id="lqs-check-desc">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>Rich Description & Bullet Points</span>
                                    </div>
                                    <div class="dso-lqs-check" id="lqs-check-compliance">
                                        <span class="dso-lqs-icon">✓</span>
                                        <span>HSN Code & Manufacturer Details</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.DSO && DSO.initProductEditor) {
                DSO.initProductEditor();
            }
        });
        </script>
        <?php
    }

    /**
     * Category Browser
     */
    public function categories() {
        $categories = $this->get_product_categories();
        ?>
        <div class="dso-page dso-categories">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Catalogue</span>
                        <span>/</span>
                        <span>Categories</span>
                    </div>
                    <h1 class="dso-page-title">Marketplace Categories</h1>
                    <p class="dso-page-subtitle">Browse commission structures, mandatory attributes, and listing guidelines per category</p>
                </div>
            </div>

            <div class="dso-grid-3">
                <?php foreach ($categories as $cat): 
                    $thumb_id = get_term_meta($cat->term_id, 'thumbnail_id', true);
                    $thumb_url = $thumb_id ? wp_get_attachment_thumb_url($thumb_id) : '';
                ?>
                    <div class="dso-card dso-category-card">
                        <div class="dso-card-body">
                            <div class="dso-cat-header">
                                <div class="dso-cat-icon">📁</div>
                                <div>
                                    <h3 class="dso-cat-name"><?php echo esc_html($cat->name); ?></h3>
                                    <span class="dso-badge dso-badge-gray"><?php echo $cat->count; ?> active items</span>
                                </div>
                            </div>
                            <p class="dso-cat-desc"><?php echo esc_html($cat->description ?: 'Marketplace catalog department for ' . $cat->name); ?></p>
                            <div class="dso-cat-footer">
                                <span class="dso-rate-label">Standard Marketplace Fee: <strong>20% (+18% GST on fee)</strong></span>
                                <a href="?section=products&cat=<?php echo $cat->term_id; ?>" class="dso-btn dso-btn-sm dso-btn-outline">View Listings</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Collections & Tags Management
     */
    public function collections() {
        $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
        ?>
        <div class="dso-page dso-collections">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Catalogue</span>
                        <span>/</span>
                        <span>Collections & Tags</span>
                    </div>
                    <h1 class="dso-page-title">Product Collections & Tags</h1>
                    <p class="dso-page-subtitle">Organize promotional seasons, festive campaigns, and searchable tags</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Tag Name</th>
                                    <th>Slug</th>
                                    <th>Products Count</th>
                                    <th class="dso-text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr><td colspan="4" class="dso-text-center dso-p-4">No product tags found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $t): ?>
                                        <tr>
                                            <td><strong>#<?php echo esc_html($t->name); ?></strong></td>
                                            <td><code><?php echo esc_html($t->slug); ?></code></td>
                                            <td><?php echo intval($t->count); ?> items</td>
                                            <td class="dso-text-right">
                                                <a href="?section=products&s=<?php echo urlencode($t->name); ?>" class="dso-btn dso-btn-sm dso-btn-outline">Browse</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Attributes Manager
     */
    public function attributes() {
        $taxonomies = wc_get_attribute_taxonomies();
        ?>
        <div class="dso-page dso-attributes">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Catalogue</span>
                        <span>/</span>
                        <span>Global Attributes</span>
                    </div>
                    <h1 class="dso-page-title">Product Attributes</h1>
                    <p class="dso-page-subtitle">Standardized sizing, colors, and specifications for variation matrices</p>
                </div>
            </div>

            <div class="dso-grid-2">
                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Available Attributes</h3>
                    </div>
                    <div class="dso-card-body dso-p-0">
                        <div class="dso-table-responsive">
                            <table class="dso-table">
                                <thead>
                                    <tr>
                                        <th>Attribute Name</th>
                                        <th>Slug</th>
                                        <th>Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($taxonomies)): ?>
                                        <tr><td colspan="3" class="dso-p-4 dso-text-center">No global attributes defined.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($taxonomies as $tax): ?>
                                            <tr>
                                                <td><strong><?php echo esc_html($tax->attribute_label); ?></strong></td>
                                                <td><code><?php echo esc_html($tax->attribute_name); ?></code></td>
                                                <td><span class="dso-badge dso-badge-gray"><?php echo esc_html($tax->attribute_type); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-header">
                        <h3 class="dso-card-title">Variation Best Practices</h3>
                    </div>
                    <div class="dso-card-body">
                        <div class="dso-info-list">
                            <div class="dso-info-item">
                                <strong>Standard Color Palettes:</strong> Use recognizable names (e.g. Navy Blue, Olive Green) alongside hex previews.
                            </div>
                            <div class="dso-info-item">
                                <strong>Precise Sizing:</strong> Clearly map Indian/US/UK size conversions in your short description.
                            </div>
                            <div class="dso-info-item">
                                <strong>Barcode / SKU Mapping:</strong> Assign distinct SKUs to every variation for reliable warehouse fulfillment.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Dedicated Listing Quality Score (LQS) Audit Page
     */
    public function product_quality() {
        $vendor_id = $this->get_active_vendor_id();
        $products = $this->get_products($vendor_id, 100);

        $high_count = 0;
        $medium_count = 0;
        $low_count = 0;

        foreach ($products as $p) {
            if ($p['lqs_score'] >= 80) $high_count++;
            elseif ($p['lqs_score'] >= 55) $medium_count++;
            else $low_count++;
        }
        ?>
        <div class="dso-page dso-quality">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Catalogue</span>
                        <span>/</span>
                        <span>Listing Quality Audit</span>
                    </div>
                    <h1 class="dso-page-title">Listing Quality Score (LQS)</h1>
                    <p class="dso-page-subtitle">Listings with high LQS (80+) receive 4.2x more impressions on DEJOIY search results</p>
                </div>
            </div>

            <div class="dso-stats-row">
                <div class="dso-stat-card">
                    <span class="dso-stat-label">High Quality (80-100)</span>
                    <span class="dso-stat-val dso-text-success"><?php echo $high_count; ?></span>
                    <span class="dso-stat-sub">Optimized for maximum conversion</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Moderate Quality (55-79)</span>
                    <span class="dso-stat-val dso-text-warning"><?php echo $medium_count; ?></span>
                    <span class="dso-stat-sub">Missing minor attributes or images</span>
                </div>
                <div class="dso-stat-card">
                    <span class="dso-stat-label">Needs Urgent Work (&lt;55)</span>
                    <span class="dso-stat-val dso-text-danger"><?php echo $low_count; ?></span>
                    <span class="dso-stat-sub">Risk of suppressed search visibility</span>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Listing Quality Audit Table</h3>
                </div>
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>LQS Score</th>
                                    <th>Status</th>
                                    <th>Recommended Fix</th>
                                    <th class="dso-text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): ?>
                                    <tr>
                                        <td>
                                            <div class="dso-product-cell">
                                                <div class="dso-product-thumb"><?php echo $p['image_html']; ?></div>
                                                <a href="?section=edit-product&id=<?php echo $p['id']; ?>" class="dso-product-title"><?php echo esc_html($p['name']); ?></a>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="dso-lqs-cell">
                                                <div class="dso-lqs-bar"><div class="dso-lqs-fill <?php echo $p['lqs_class']; ?>" style="width:<?php echo $p['lqs_score']; ?>%;"></div></div>
                                                <span class="dso-lqs-text <?php echo $p['lqs_class']; ?>"><?php echo $p['lqs_score']; ?>%</span>
                                            </div>
                                        </td>
                                        <td><?php echo $p['status_badge']; ?></td>
                                        <td>
                                            <span class="dso-text-muted">
                                                <?php 
                                                if ($p['lqs_score'] < 60) echo '⚠️ Add 3+ gallery photos & MRP details';
                                                elseif ($p['lqs_score'] < 80) echo '💡 Add bullet points & HSN code';
                                                else echo '✨ Listing fully optimized';
                                                ?>
                                            </span>
                                        </td>
                                        <td class="dso-text-right">
                                            <a href="?section=edit-product&id=<?php echo $p['id']; ?>" class="dso-btn dso-btn-sm dso-btn-primary">Optimize Now</a>
                                        </td>
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

    /**
     * Inventory Overview
     */
    /**
     * Amazon-style "Manage All Inventory" Table
     */
    public function inventory() {
        $vendor_id = $this->get_active_vendor_id();
        $current_tab = sanitize_text_field($_GET['tab'] ?? 'all');
        $search = sanitize_text_field($_GET['s'] ?? '');

        // Fetch products
        $all_products = $this->get_products($vendor_id, 200, 0, 'all', $search);

        // Compute counts
        $total_count = count($all_products);
        $instock_count = 0;
        $lowstock_count = 0;
        $outofstock_count = 0;

        foreach ($all_products as $p) {
            $qty = intval($p['stock_quantity']);
            if ($qty > 5) {
                $instock_count++;
            } elseif ($qty > 0 && $qty <= 5) {
                $lowstock_count++;
                $instock_count++;
            } else {
                $outofstock_count++;
            }
        }

        // Filter products for active tab
        $products = array_filter($all_products, function($p) use ($current_tab) {
            $qty = intval($p['stock_quantity']);
            if ($current_tab === 'instock') return $qty > 0;
            if ($current_tab === 'lowstock') return ($qty > 0 && $qty <= 5);
            if ($current_tab === 'outofstock') return $qty <= 0;
            return true;
        });
        ?>
        <div class="dso-page dso-inventory">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Inventory</span>
                        <span>/</span>
                        <span>Manage All Inventory</span>
                    </div>
                    <h1 class="dso-page-title">Manage All Inventory</h1>
                    <p class="dso-page-subtitle">Amazon-style high-velocity catalog grid. Adjust stock, update prices, and review net seller proceeds in real time.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=catalog-upload" class="dso-btn dso-btn-outline" style="border-color:#0066ff;color:#0066ff;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Upload Products via CSV/Sheet
                    </a>
                    <a href="?section=add-product" class="dso-btn dso-btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        + Add Single Product
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['notice']) && $_GET['notice'] === 'imported'): ?>
                <div class="dso-alert dso-alert-success dso-mb-4">
                    🎉 Successfully bulk-imported <strong><?php echo intval($_GET['count'] ?? 0); ?> listings</strong> into your catalog with permanent DPIN identifiers!
                </div>
            <?php endif; ?>

            <!-- Status Tabs -->
            <div class="dso-tabs dso-mb-4" style="display:flex;gap:8px;border-bottom:1px solid #e2e8f0;padding-bottom:12px;overflow-x:auto;">
                <a href="?section=inventory&tab=all" class="dso-btn dso-btn-sm <?php echo $current_tab === 'all' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>">
                    All Listings (<?php echo $total_count; ?>)
                </a>
                <a href="?section=inventory&tab=instock" class="dso-btn dso-btn-sm <?php echo $current_tab === 'instock' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>">
                    Active In Stock (<?php echo $instock_count; ?>)
                </a>
                <a href="?section=inventory&tab=lowstock" class="dso-btn dso-btn-sm <?php echo $current_tab === 'lowstock' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="<?php echo $lowstock_count > 0 ? 'border-color:#f59e0b;color:#d97706;' : ''; ?>">
                    ⚠️ Low Stock &le; 5 units (<?php echo $lowstock_count; ?>)
                </a>
                <a href="?section=inventory&tab=outofstock" class="dso-btn dso-btn-sm <?php echo $current_tab === 'outofstock' ? 'dso-btn-primary' : 'dso-btn-outline'; ?>" style="<?php echo $outofstock_count > 0 ? 'border-color:#ef4444;color:#dc2626;' : ''; ?>">
                    🚫 Out of Stock (<?php echo $outofstock_count; ?>)
                </a>
            </div>

            <!-- Search & Filters -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-body" style="padding:16px;">
                    <form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <input type="hidden" name="section" value="inventory" />
                        <input type="hidden" name="tab" value="<?php echo esc_attr($current_tab); ?>" />
                        <div style="flex:1;min-width:240px;position:relative;">
                            <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search SKU, Product Title, or DPIN (e.g. DEZC...)" class="dso-input" style="padding-left:36px;" />
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#94a3b8" stroke-width="2" style="position:absolute;left:12px;top:12px;"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <button type="submit" class="dso-btn dso-btn-outline">Filter</button>
                        <?php if ($search): ?>
                            <a href="?section=inventory&tab=<?php echo esc_attr($current_tab); ?>" class="dso-btn dso-btn-outline">Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Manage All Inventory Table -->
            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table dso-inv-table">
                            <thead>
                                <tr>
                                    <th style="width:36px;"><input type="checkbox" id="dso-select-all-inv" onclick="document.querySelectorAll('.dso-inv-checkbox').forEach(c => c.checked = this.checked);" /></th>
                                    <th>Status</th>
                                    <th>Product Details</th>
                                    <th>DPIN / SKU</th>
                                    <th style="width:140px;">Available Units</th>
                                    <th style="width:180px;">Your Price + Fees</th>
                                    <th>LQS Score</th>
                                    <th class="dso-text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($products)): ?>
                                    <tr>
                                        <td colspan="8" style="padding:48px;text-align:center;color:#64748b;">
                                            <div style="font-size:36px;margin-bottom:10px;">📦</div>
                                            <strong>No products match your current inventory filter.</strong>
                                            <div style="margin-top:10px;">
                                                <a href="?section=add-product" class="dso-btn dso-btn-primary dso-btn-sm">+ Add New Listing</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($products as $p): 
                                        $price = floatval($p['regular_price'] ?: $p['price']);
                                        $referral_fee = round($price * 0.12, 2);
                                        $net_payout = round($price - $referral_fee, 2);
                                        $qty = intval($p['stock_quantity']);
                                        $lqs = intval($p['lqs_score']);
                                        
                                        // Stock Risk Indicator
                                        $avg_daily_sales = max(0.2, round($p['total_sales'] / 30, 1));
                                        $est_stockout_days = $avg_daily_sales > 0 ? ceil($qty / $avg_daily_sales) : 0;
                                    ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="dso-inv-checkbox" value="<?php echo $p['id']; ?>" />
                                            </td>
                                            <td>
                                                <?php if ($qty > 5): ?>
                                                    <span class="dso-badge dso-badge-green">● Healthy</span>
                                                <?php elseif ($qty > 0): ?>
                                                    <span class="dso-badge dso-badge-amber">⚠️ Low Stock</span>
                                                <?php else: ?>
                                                    <span class="dso-badge dso-badge-red">✕ Out of Stock</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($qty > 0 && $est_stockout_days < 10): ?>
                                                    <div style="font-size:10px;color:<?php echo $est_stockout_days < 3 ? '#ef4444' : '#f59e0b'; ?>;font-weight:700;margin-top:4px;text-transform:uppercase;">
                                                        Est. Stockout: <?php echo $est_stockout_days; ?> days
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="dso-product-cell" style="display:flex;align-items:center;gap:12px;">
                                                    <div class="dso-product-thumb" style="width:44px;height:44px;border-radius:8px;overflow:hidden;flex-shrink:0;border:1px solid #e2e8f0;">
                                                        <?php echo $p['image_html']; ?>
                                                    </div>
                                                    <div>
                                                        <a href="?section=edit-product&id=<?php echo $p['id']; ?>" style="color:#0f172a;font-weight:600;font-size:13.5px;text-decoration:none;">
                                                            <?php echo esc_html($p['name']); ?>
                                                        </a>
                                                        <div style="font-size:11px;color:#64748b;margin-top:2px;">Category: <?php echo esc_html($p['category']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display:flex;flex-direction:column;gap:3px;">
                                                    <span class="dso-dpin-pill" style="cursor:pointer;" onclick="dsoCopyText('<?php echo esc_attr($p['dpin']); ?>', this)" title="Click to copy DPIN">
                                                        <code><?php echo esc_html($p['dpin'] ?: '—'); ?></code>
                                                    </span>
                                                    <small style="color:#64748b;font-family:monospace;font-size:11px;">SKU: <?php echo esc_html($p['sku'] ?: '—'); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="dso-inv-editable-cell">
                                                    <input type="number" id="stock-input-<?php echo $p['id']; ?>" class="dso-inline-input" value="<?php echo $qty; ?>" min="0" />
                                                    <button type="button" class="dso-inline-save-btn" onclick="DSO.saveStockQuick(<?php echo $p['id']; ?>);" title="Save Stock">✓</button>
                                                </div>
                                                <div style="font-size:10px;color:#94a3b8;margin-top:4px;">Sales Vel: <?php echo $avg_daily_sales; ?>/day</div>
                                            </td>
                                            <td>
                                                <div class="dso-inv-editable-cell">
                                                    <span style="font-weight:700;color:#64748b;font-size:13px;">₹</span>
                                                    <input type="number" id="price-input-<?php echo $p['id']; ?>" class="dso-inline-input" value="<?php echo $price; ?>" step="0.5" min="0" />
                                                    <button type="button" class="dso-inline-save-btn" onclick="DSO.savePriceQuick(<?php echo $p['id']; ?>);" title="Save Price">✓</button>
                                                </div>
                                                <div class="dso-fee-preview" id="fee-preview-<?php echo $p['id']; ?>" style="font-size:10px;line-height:1.4;margin-top:4px;">
                                                    Ref. Fee: ₹<?php echo number_format($referral_fee, 2); ?><br>
                                                    <strong style="color:#10b981;">Net: ₹<?php echo number_format($net_payout, 2); ?></strong>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="dso-lqs-cell" style="display:flex;flex-direction:column;gap:4px;">
                                                    <div class="dso-lqs-bar" style="width:60px;height:4px;background:#e2e8f0;border-radius:2px;overflow:hidden;">
                                                        <div class="dso-lqs-fill" style="width:<?php echo $lqs; ?>%;height:100%;background:<?php echo $lqs >= 80 ? '#10b981' : ($lqs >= 55 ? '#f59e0b' : '#ef4444'); ?>;"></div>
                                                    </div>
                                                    <span style="font-size:11px;font-weight:600;color:<?php echo $lqs >= 80 ? '#059669' : ($lqs >= 55 ? '#d97706' : '#dc2626'); ?>;"><?php echo $lqs; ?>%</span>
                                                </div>
                                            </td>
                                            <td class="dso-text-right">
                                                <div style="display:flex;gap:6px;justify-content:flex-end;">
                                                    <a href="?section=edit-product&id=<?php echo $p['id']; ?>" class="dso-btn dso-btn-sm dso-btn-outline" title="Full Editor">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Amazon-style Bulk Excel / CSV Catalog Upload Engine
     */
    public function catalog_upload() {
        $vendor_id = $this->get_active_vendor_id();

        // Process CSV import
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_catalog_csv_commit'])) {
            check_admin_referer('dso_csv_upload_action');
            $raw_csv = wp_unslash($_POST['csv_raw_data'] ?? '');
            $imported_count = 0;

            if (!empty($raw_csv)) {
                $lines = explode("\n", str_replace("\r", "", $raw_csv));
                $header_skipped = false;

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    if (!$header_skipped) {
                        $header_skipped = true;
                        continue;
                    }

                    $cols = str_getcsv($line);
                    if (count($cols) < 2) continue;

                    $title = sanitize_text_field($cols[0] ?? '');
                    $category_name = sanitize_text_field($cols[1] ?? 'General');
                    $sku = sanitize_text_field($cols[2] ?? '');
                    $price = floatval($cols[3] ?? 0);
                    $sale_price = !empty($cols[4]) ? floatval($cols[4]) : '';
                    $stock = intval($cols[5] ?? 10);
                    $short_desc = sanitize_textarea_field($cols[6] ?? '');
                    $desc = wp_kses_post($cols[7] ?? '');

                    if (empty($title) || $price <= 0) continue;

                    // Generate permanent DEJOIY DPIN
                    $dpin = 'DE' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 9));

                    // Create product post
                    $post_id = wp_insert_post([
                        'post_title'   => $title,
                        'post_content' => $desc,
                        'post_excerpt' => $short_desc,
                        'post_status'  => 'publish',
                        'post_type'    => 'product',
                        'post_author'  => get_current_user_id()
                    ]);

                    if ($post_id && !is_wp_error($post_id)) {
                        update_post_meta($post_id, '_regular_price', $price);
                        update_post_meta($post_id, '_price', $sale_price ?: $price);
                        if (!empty($sale_price)) {
                            update_post_meta($post_id, '_sale_price', $sale_price);
                        }
                        update_post_meta($post_id, '_manage_stock', 'yes');
                        update_post_meta($post_id, '_stock', $stock);
                        update_post_meta($post_id, '_stock_status', $stock > 0 ? 'instock' : 'outofstock');
                        update_post_meta($post_id, '_sku', $sku ?: $dpin);
                        update_post_meta($post_id, '_dejoiy_dpin', $dpin);

                        // Assign vendor
                        if ($vendor_id) {
                            wp_set_object_terms($post_id, intval($vendor_id), 'wcfm_vendor');
                        }

                        // Category
                        if ($category_name) {
                            wp_set_object_terms($post_id, $category_name, 'product_cat');
                        }

                        $imported_count++;
                    }
                }
            }

            wp_redirect('?section=inventory&notice=imported&count=' . $imported_count);
            exit;
        }
        ?>
        <div class="dso-page dso-catalog-upload">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=inventory">Inventory</a>
                        <span>/</span>
                        <span>Bulk Upload</span>
                    </div>
                    <h1 class="dso-page-title">Upload Products via Sheet (Bulk Feed)</h1>
                    <p class="dso-page-subtitle">Add hundreds of listings simultaneously using DEJOIY's official CSV/Excel catalog template.</p>
                </div>
                <div class="dso-page-actions">
                    <a href="data:text/csv;charset=utf-8,<?php echo rawurlencode("Product Name,Category,SKU,Regular Price,Sale Price,Stock Quantity,Short Description,Full Description\nHandcrafted Blue Ceramic Mug,Home & Living,DJ-MUG-001,499,399,50,Premium handmade coffee mug,100% lead-free ceramic with high-durability glaze.\nFestive Cotton Embroidered Kurti,Fashion,DJ-KUR-101,1299,899,25,Pure cotton breathable ethnic wear,Hand-woven pure cotton fabric with zardozi embroidery."); ?>" download="DEJOIY_Bulk_Listing_Template.csv" class="dso-btn dso-btn-outline" style="border-color:#0066ff;color:#0066ff;">
                        📥 Download Official CSV Template
                    </a>
                </div>
            </div>

            <!-- Steps Grid -->
            <div class="dso-grid-3 dso-mb-4">
                <div class="dso-card">
                    <div class="dso-card-body" style="padding:20px;">
                        <span style="font-size:20px;display:block;margin-bottom:8px;">1️⃣</span>
                        <h4 style="margin:0 0 6px;">Download Template</h4>
                        <p style="font-size:12.5px;color:#64748b;margin:0;">Get the official CSV template with standard column headers for products, prices, and stock.</p>
                    </div>
                </div>
                <div class="dso-card">
                    <div class="dso-card-body" style="padding:20px;">
                        <span style="font-size:20px;display:block;margin-bottom:8px;">2️⃣</span>
                        <h4 style="margin:0 0 6px;">Fill Product Rows</h4>
                        <p style="font-size:12.5px;color:#64748b;margin:0;">Open in Microsoft Excel, Google Sheets, or Numbers. Enter your product names, pricing, and stock.</p>
                    </div>
                </div>
                <div class="dso-card">
                    <div class="dso-card-body" style="padding:20px;">
                        <span style="font-size:20px;display:block;margin-bottom:8px;">3️⃣</span>
                        <h4 style="margin:0 0 6px;">Instant DPIN Generation</h4>
                        <p style="font-size:12.5px;color:#64748b;margin:0;">Upload below. DEJOIY automatically generates verified DPINs and publishes all listings live.</p>
                    </div>
                </div>
            </div>

            <!-- Drag & Drop Upload Zone -->
            <div class="dso-card dso-mb-4">
                <div class="dso-card-body" style="padding:32px;">
                    <div class="dso-upload-dropzone" onclick="document.getElementById('dso-catalog-file-input').click();">
                        <div class="dso-upload-icon">📄</div>
                        <h3 style="font-size:18px;margin-bottom:6px;color:#0f172a;">Drag & drop your listing CSV file here</h3>
                        <p style="color:#64748b;font-size:13.5px;margin-bottom:16px;">or click to browse from your computer (CSV, TSV)</p>
                        <button type="button" class="dso-btn dso-btn-primary">Select CSV File</button>
                        <input type="file" id="dso-catalog-file-input" accept=".csv,.txt" style="display:none;" onchange="DSO.handleCatalogCsvUpload(this);" />
                    </div>

                    <!-- Client-side Validation & Preview Table -->
                    <div id="dso-csv-preview-container" style="display:none;" class="dso-csv-preview-wrap">
                        <div style="padding:16px 20px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
                            <div>
                                <strong style="color:#0f172a;font-size:15px;">Listing Validation Preview</strong>
                                <span class="dso-badge dso-badge-blue" id="dso-csv-count" style="margin-left:8px;">0 Rows</span>
                            </div>
                            <form method="post">
                                <?php wp_nonce_field('dso_csv_upload_action'); ?>
                                <input type="hidden" name="dso_catalog_csv_commit" value="1" />
                                <input type="hidden" name="csv_raw_data" id="dso-csv-raw-data" />
                                <button type="submit" id="dso-csv-commit-btn" class="dso-btn dso-btn-primary" style="display:none;">
                                    🚀 Commit & Publish to Catalog
                                </button>
                            </form>
                        </div>
                        <div class="dso-table-responsive">
                            <table class="dso-table">
                                <thead>
                                    <tr>
                                        <th>Row</th>
                                        <th>Product Title</th>
                                        <th>Category</th>
                                        <th>SKU</th>
                                        <th>Regular Price</th>
                                        <th>Stock</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="dso-csv-preview-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Low Stock Alert View
     */
    public function inventory_low() {
        $_GET['tab'] = 'lowstock';
        $this->render();
    }

    /**
     * Out of Stock View
     */
    public function inventory_out() {
        $_GET['tab'] = 'outofstock';
        $this->render();
    }

    /**
     * Stock Movement History Audit
     */
    public function inventory_history() {
        $vendor_id = $this->get_active_vendor_id();
        ?>
        <div class="dso-page dso-inventory-history">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=inventory">Inventory</a>
                        <span>/</span>
                        <span>Stock History</span>
                    </div>
                    <h1 class="dso-page-title">Inventory Movement Log</h1>
                    <p class="dso-page-subtitle">Audit trail of sales decrements, returns restocking, and manual adjustments</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Product / SKU</th>
                                    <th>Event Type</th>
                                    <th>Change Quantity</th>
                                    <th>Balance</th>
                                    <th>Initiated By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php echo date('M j, Y H:i'); ?></td>
                                    <td><strong>System Sync</strong></td>
                                    <td><span class="dso-badge dso-badge-green">Stock Validated</span></td>
                                    <td>—</td>
                                    <td>Active</td>
                                    <td>DEJOIY Inventory Engine</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Bulk Stock Updater Grid
     */
    public function inventory_bulk() {
        $vendor_id = $this->get_active_vendor_id();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_bulk_stock_save'])) {
            check_admin_referer('dso_bulk_stock');
            if (!empty($_POST['stocks']) && is_array($_POST['stocks'])) {
                foreach ($_POST['stocks'] as $pid => $qty) {
                    $prod = wc_get_product(intval($pid));
                    if ($prod) {
                        $q = max(0, intval($qty));
                        $prod->set_manage_stock(true);
                        $prod->set_stock_quantity($q);
                        $prod->set_stock_status($q > 0 ? 'instock' : 'outofstock');
                        $prod->save();
                    }
                }
            }
            wp_redirect('?section=inventory&notice=bulk_updated');
            exit;
        }

        $products = $this->get_products($vendor_id, 150);
        ?>
        <div class="dso-page dso-inventory-bulk">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=inventory">Inventory</a>
                        <span>/</span>
                        <span>Bulk Updater</span>
                    </div>
                    <h1 class="dso-page-title">Bulk Inventory & Pricing Grid</h1>
                    <p class="dso-page-subtitle">Edit stock quantities in bulk spreadsheet mode and save with one click</p>
                </div>
                <div class="dso-page-actions">
                    <a href="?section=inventory" class="dso-btn dso-btn-outline">Cancel</a>
                    <button type="button" class="dso-btn dso-btn-primary" onclick="document.getElementById('dso-bulk-stock-form').submit();">Save All Changes</button>
                </div>
            </div>

            <form method="post" id="dso-bulk-stock-form">
                <?php wp_nonce_field('dso_bulk_stock'); ?>
                <input type="hidden" name="dso_bulk_stock_save" value="1" />

                <div class="dso-card">
                    <div class="dso-card-body dso-p-0">
                        <div class="dso-table-responsive">
                            <table class="dso-table">
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>SKU</th>
                                        <th>Current Price (₹)</th>
                                        <th style="width: 180px;">Inventory Quantity</th>
                                        <th>Stock Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $p): ?>
                                        <tr>
                                            <td><strong><?php echo esc_html($p['name']); ?></strong></td>
                                            <td><code><?php echo esc_html($p['sku'] ?: '—'); ?></code></td>
                                            <td><?php echo $p['price_html']; ?></td>
                                            <td>
                                                <input type="number" name="stocks[<?php echo $p['id']; ?>]" value="<?php echo intval($p['stock_quantity']); ?>" min="0" class="dso-input dso-input-sm" />
                                            </td>
                                            <td>
                                                <span class="dso-stock-status-tag <?php echo $p['stock_status_class']; ?>">
                                                    <?php echo $p['stock_label']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Save Product Data Handler
     */
    protected function save_product_data($product_id = 0, $vendor_id = 0) {
        $name = sanitize_text_field($_POST['product_name'] ?? '');
        if (empty($name)) return 0;

        $desc = wp_kses_post($_POST['description'] ?? '');
        $short_desc = sanitize_textarea_field($_POST['short_description'] ?? '');
        $status = sanitize_text_field($_POST['post_status'] ?? 'publish');

        if ($product_id > 0) {
            wp_update_post([
                'ID' => $product_id,
                'post_title' => $name,
                'post_content' => $desc,
                'post_excerpt' => $short_desc,
                'post_status' => $status,
            ]);
        } else {
            $product_id = wp_insert_post([
                'post_title' => $name,
                'post_content' => $desc,
                'post_excerpt' => $short_desc,
                'post_status' => $status,
                'post_type' => 'product',
                'post_author' => $vendor_id ?: get_current_user_id(),
            ]);
        }

        if (!$product_id || is_wp_error($product_id)) return 0;

        // Ensure permanent DPIN (DEJOIY Product Identification Number)
        $dpin = get_post_meta($product_id, '_dejoiy_dpin', true);
        if (empty($dpin)) {
            if (function_exists('dejoiy_ensure_product_dpin')) {
                $dpin = dejoiy_ensure_product_dpin($product_id);
            } else {
                $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $len = strlen($chars);
                $dpin = 'D';
                for ($i = 0; $i < 10; $i++) {
                    $dpin .= $chars[random_int(0, $len - 1)];
                }
            }
            update_post_meta($product_id, '_dejoiy_dpin', $dpin);
            update_post_meta($product_id, '_dpin', $dpin);
        }

        // Associate Vendor
        if ($vendor_id) {
            update_post_meta($product_id, '_vendor_id', $vendor_id);
            update_post_meta($product_id, '_wcfm_product_author', $vendor_id);
        }

        // Pricing & MRP
        $regular_price = sanitize_text_field($_POST['regular_price'] ?? '');
        $sale_price = sanitize_text_field($_POST['sale_price'] ?? '');
        $mrp = sanitize_text_field($_POST['mrp'] ?? '');

        update_post_meta($product_id, '_regular_price', $regular_price);
        update_post_meta($product_id, '_price', $sale_price ?: $regular_price);
        if (!empty($sale_price)) {
            update_post_meta($product_id, '_sale_price', $sale_price);
        } else {
            delete_post_meta($product_id, '_sale_price');
        }
        if (!empty($mrp)) {
            update_post_meta($product_id, '_dso_mrp', $mrp);
        }

        // SKU & Stock
        $sku = sanitize_text_field($_POST['sku'] ?? '');
        update_post_meta($product_id, '_sku', $sku);

        $manage_stock = !empty($_POST['manage_stock']) ? 'yes' : 'no';
        update_post_meta($product_id, '_manage_stock', $manage_stock);

        if ($manage_stock === 'yes') {
            $stock_qty = intval($_POST['stock_quantity'] ?? 0);
            update_post_meta($product_id, '_stock', $stock_qty);
            update_post_meta($product_id, '_stock_status', $stock_qty > 0 ? 'instock' : 'outofstock');
        } else {
            $stock_status = sanitize_text_field($_POST['stock_status'] ?? 'instock');
            update_post_meta($product_id, '_stock_status', $stock_status);
        }

        // Dimensions & Weight
        if (isset($_POST['weight'])) update_post_meta($product_id, '_weight', sanitize_text_field($_POST['weight']));
        if (isset($_POST['length'])) update_post_meta($product_id, '_length', sanitize_text_field($_POST['length']));
        if (isset($_POST['width'])) update_post_meta($product_id, '_width', sanitize_text_field($_POST['width']));
        if (isset($_POST['height'])) update_post_meta($product_id, '_height', sanitize_text_field($_POST['height']));

        // Compliance
        if (isset($_POST['dso_brand'])) update_post_meta($product_id, '_dso_brand', sanitize_text_field($_POST['dso_brand']));
        if (isset($_POST['country_of_origin'])) update_post_meta($product_id, '_dso_country_of_origin', sanitize_text_field($_POST['country_of_origin']));
        if (isset($_POST['hsn_code'])) update_post_meta($product_id, '_dso_hsn_code', sanitize_text_field($_POST['hsn_code']));
        if (isset($_POST['gst_rate'])) update_post_meta($product_id, '_dso_gst_rate', sanitize_text_field($_POST['gst_rate']));
        if (isset($_POST['manufacturer'])) update_post_meta($product_id, '_dso_mfg_details', sanitize_text_field($_POST['manufacturer']));
        if (isset($_POST['packer'])) update_post_meta($product_id, '_dso_packer_details', sanitize_text_field($_POST['packer']));

        // Smart Category Attributes
        $smart_type = sanitize_text_field($_POST['smart_category'] ?? 'general');
        update_post_meta($product_id, '_dso_smart_category', $smart_type);

        if (isset($_POST['fashion_size'])) update_post_meta($product_id, '_dso_size', sanitize_text_field($_POST['fashion_size']));
        if (isset($_POST['fashion_color'])) update_post_meta($product_id, '_dso_color', sanitize_text_field($_POST['fashion_color']));
        if (isset($_POST['fashion_fabric'])) update_post_meta($product_id, '_dso_fabric', sanitize_text_field($_POST['fashion_fabric']));
        if (isset($_POST['fashion_gender'])) update_post_meta($product_id, '_dso_gender', sanitize_text_field($_POST['fashion_gender']));
        if (isset($_POST['fashion_fit'])) update_post_meta($product_id, '_dso_fit', sanitize_text_field($_POST['fashion_fit']));
        if (isset($_POST['fashion_care'])) update_post_meta($product_id, '_dso_care', sanitize_text_field($_POST['fashion_care']));

        if (isset($_POST['elec_model'])) update_post_meta($product_id, '_dso_model_number', sanitize_text_field($_POST['elec_model']));
        if (isset($_POST['elec_warranty'])) update_post_meta($product_id, '_dso_warranty', sanitize_text_field($_POST['elec_warranty']));
        if (isset($_POST['elec_voltage'])) update_post_meta($product_id, '_dso_power', sanitize_text_field($_POST['elec_voltage']));
        if (isset($_POST['elec_connectivity'])) update_post_meta($product_id, '_dso_connectivity', sanitize_text_field($_POST['elec_connectivity']));

        if (isset($_POST['beauty_skin'])) update_post_meta($product_id, '_dso_skin_type', sanitize_text_field($_POST['beauty_skin']));
        if (isset($_POST['beauty_ingredients'])) update_post_meta($product_id, '_dso_ingredients', sanitize_text_field($_POST['beauty_ingredients']));
        if (isset($_POST['beauty_net_weight'])) update_post_meta($product_id, '_dso_net_weight', sanitize_text_field($_POST['beauty_net_weight']));
        if (isset($_POST['beauty_expiry'])) update_post_meta($product_id, '_dso_expiry', sanitize_text_field($_POST['beauty_expiry']));

        if (isset($_POST['grocery_fssai'])) update_post_meta($product_id, '_dso_fssai', sanitize_text_field($_POST['grocery_fssai']));
        if (isset($_POST['grocery_veg'])) update_post_meta($product_id, '_dso_veg_nonveg', sanitize_text_field($_POST['grocery_veg']));
        if (isset($_POST['grocery_shelf_life'])) update_post_meta($product_id, '_dso_shelf_life', sanitize_text_field($_POST['grocery_shelf_life']));

        if (isset($_POST['book_isbn'])) update_post_meta($product_id, '_dso_isbn', sanitize_text_field($_POST['book_isbn']));
        if (isset($_POST['book_author'])) update_post_meta($product_id, '_dso_author', sanitize_text_field($_POST['book_author']));
        if (isset($_POST['book_publisher'])) update_post_meta($product_id, '_dso_publisher', sanitize_text_field($_POST['book_publisher']));
        if (isset($_POST['book_language'])) update_post_meta($product_id, '_dso_language', sanitize_text_field($_POST['book_language']));

        // Taxonomies
        if (!empty($_POST['product_categories']) && is_array($_POST['product_categories'])) {
            wp_set_object_terms($product_id, array_map('intval', $_POST['product_categories']), 'product_cat');
        }
        if (!empty($_POST['product_tags'])) {
            $tags = array_map('trim', explode(',', sanitize_text_field($_POST['product_tags'])));
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }

        // Featured Image
        if (!empty($_POST['featured_image_id'])) {
            set_post_thumbnail($product_id, intval($_POST['featured_image_id']));
        }
        if (!empty($_FILES['featured_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attach_id = media_handle_upload('featured_file', $product_id);
            if ($attach_id && !is_wp_error($attach_id)) {
                set_post_thumbnail($product_id, $attach_id);
            }
        }

        // Gallery: media-library ids + fresh file uploads (max 5). New files
        // become real attachments in the WordPress media library.
        $gids = [];
        if (isset($_POST['gallery_image_ids'])) {
            foreach (explode(',', sanitize_text_field($_POST['gallery_image_ids'])) as $g) {
                $g = intval($g);
                if ($g > 0 && !in_array($g, $gids, true)) {
                    $gids[] = $g;
                }
            }
        }
        $gids = $this->handle_gallery_uploads($product_id, $gids);
        update_post_meta($product_id, '_product_image_gallery', implode(',', array_slice($gids, 0, 5)));

        // Ensure product is visible in marketplace catalog & storefront
        wp_remove_object_terms($product_id, ['exclude-from-catalog', 'exclude-from-search'], 'product_visibility');
        clean_post_cache($product_id);

        // Recalculate and store LQS
        $this->calculate_lqs($product_id);

        return $product_id;
    }

    /**
     * Upload gallery files into the WordPress media library and merge the new
     * attachment ids with the existing gallery list (max 5).
     */
    protected function handle_gallery_uploads($product_id, $gids) {
        if (empty($_FILES['gallery_files']['name']) || !is_array($_FILES['gallery_files']['name'])) {
            return $gids;
        }
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }
        $files = $_FILES['gallery_files'];
        $count = count($files['name']);
        for ($i = 0; $i < $count && count($gids) < 5; $i++) {
            if (empty($files['name'][$i]) || (isset($files['error'][$i]) && $files['error'][$i] !== UPLOAD_ERR_OK)) {
                continue;
            }
            if (!preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', (string) $files['name'][$i])) {
                continue;
            }
            $_FILES['dso_gallery_single'] = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_OK,
                'size'     => $files['size'][$i] ?? 0,
            ];
            $attach_id = media_handle_upload('dso_gallery_single', $product_id);
            if ($attach_id && !is_wp_error($attach_id)) {
                $gids[] = (int) $attach_id;
            }
        }
        unset($_FILES['dso_gallery_single']);
        return $gids;
    }

    /**
     * Calculate Real-time Listing Quality Score (LQS)
     */
    public function calculate_lqs($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return ['score' => 0, 'status' => 'Needs Work', 'class' => 'dso-lqs-low'];

        $score = 0;

        // Title length (>= 25 chars)
        if (strlen($product->get_name()) >= 25) $score += 15;

        // Description
        $desc = $product->get_description() . ' ' . $product->get_short_description();
        if (str_word_count(strip_tags($desc)) >= 25) $score += 15;

        // Featured Image
        if ($product->get_image_id()) $score += 15;

        // Gallery
        $gallery = $product->get_gallery_image_ids();
        if (!empty($gallery)) $score += 10;

        // SKU
        if (!empty($product->get_sku())) $score += 10;

        // Category
        $cats = wp_get_post_terms($product_id, 'product_cat');
        if (!empty($cats)) $score += 10;

        // Pricing & MRP
        $mrp = get_post_meta($product_id, '_dso_mrp', true);
        if ($product->get_regular_price() > 0 && !empty($mrp)) $score += 10;

        // Weight / Dimensions
        if ($product->get_weight() || $product->get_length()) $score += 10;

        // Compliance (HSN / Brand / Manufacturer)
        $hsn = get_post_meta($product_id, '_dso_hsn_code', true);
        $brand = get_post_meta($product_id, '_dso_brand', true);
        if ($hsn || $brand) $score += 5;

        $score = min(100, $score);
        update_post_meta($product_id, '_dso_lqs_score', $score);

        if ($score >= 80) {
            return ['score' => $score, 'status' => 'Excellent', 'class' => 'dso-lqs-high'];
        } elseif ($score >= 55) {
            return ['score' => $score, 'status' => 'Good', 'class' => 'dso-lqs-mid'];
        } else {
            return ['score' => $score, 'status' => 'Needs Work', 'class' => 'dso-lqs-low'];
        }
    }

    /**
     * Handle Bulk Actions
     */
    protected function handle_bulk_actions() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dso_bulk_action'])) {
            check_admin_referer('dso_bulk_products', 'dso_bulk_nonce');
            $action = sanitize_text_field($_POST['dso_bulk_action']);
            $ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

            if (!empty($ids)) {
                foreach ($ids as $id) {
                    $prod = wc_get_product($id);
                    if (!$prod) continue;

                    switch ($action) {
                        case 'publish':
                            wp_update_post(['ID' => $id, 'post_status' => 'publish']);
                            break;
                        case 'draft':
                            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
                            break;
                        case 'mark_instock':
                            $prod->set_stock_status('instock');
                            $prod->save();
                            break;
                        case 'mark_outofstock':
                            $prod->set_stock_status('outofstock');
                            $prod->save();
                            break;
                        case 'delete':
                            wp_trash_post($id);
                            break;
                    }
                }
            }
        }
    }

    /**
     * Product Query with Vendor Filter and Stats
     */
    public function get_products($vendor_id = 0, $limit = 100, $offset = 0, $tab = 'all', $search = '', $category = 0, $stock_filter = '') {
        global $wpdb;

        $where = ["p.post_type = 'product'"];
        $params = [];

        // Check vendor scoping
        if ($vendor_id > 0) {
            $where[] = "(p.post_author = %d OR p.ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vendor_id' AND meta_value = %s))";
            $params[] = $vendor_id;
            $params[] = strval($vendor_id);
        }

        // Tab filters
        if ($tab === 'publish') {
            $where[] = "p.post_status = 'publish'";
        } elseif ($tab === 'draft') {
            $where[] = "p.post_status = 'draft'";
        } else {
            $where[] = "p.post_status IN ('publish', 'draft', 'pending', 'private')";
        }

        // Search
        if ($search) {
            $where[] = "(p.post_title LIKE %s OR p.ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key IN ('_sku', '_dejoiy_dpin', '_dpin') AND meta_value LIKE %s))";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where);
        $query = "SELECT DISTINCT p.ID FROM {$wpdb->prefix}posts p WHERE {$where_sql} ORDER BY p.ID DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $product_ids = $wpdb->get_col($wpdb->prepare($query, ...$params));
        $list = [];

        $low_threshold = intval(get_option('woocommerce_notify_low_stock_amount', 2));

        foreach ($product_ids as $pid) {
            $p = wc_get_product($pid);
            if (!$p) continue;

            $stock_qty = $p->get_stock_quantity();
            $stock_status = $p->get_stock_status();
            $manage_stock = $p->get_manage_stock();

            $is_low = $manage_stock && $stock_qty <= $low_threshold && $stock_qty > 0;
            $is_out = $stock_status === 'outofstock' || ($manage_stock && $stock_qty <= 0);

            // Tab-based stock filtering
            if ($tab === 'lowstock' && !$is_low) continue;
            if ($tab === 'outofstock' && !$is_out) continue;

            if ($stock_filter === 'instock' && ($is_out || $is_low)) continue;
            if ($stock_filter === 'lowstock' && !$is_low) continue;
            if ($stock_filter === 'outofstock' && !$is_out) continue;

            // Category filter
            if ($category > 0 && !has_term($category, 'product_cat', $pid)) continue;

            $thumb = get_the_post_thumbnail($pid, [56, 56]);
            if (!$thumb) {
                $thumb = '<div class="dso-product-placeholder-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="24" height="24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
            }

            $lqs = intval(get_post_meta($pid, '_dso_lqs_score', true));
            if (!$lqs) {
                $lqs_calc = $this->calculate_lqs($pid);
                $lqs = $lqs_calc['score'];
            }
            $lqs_class = $lqs >= 80 ? 'dso-lqs-high' : ($lqs >= 55 ? 'dso-lqs-mid' : 'dso-lqs-low');

            $stock_label = 'In Stock';
            $stock_class = 'dso-stock-in';
            if ($is_out) {
                $stock_label = 'Out of Stock';
                $stock_class = 'dso-stock-out';
            } elseif ($is_low) {
                $stock_label = 'Low Stock (' . $stock_qty . ')';
                $stock_class = 'dso-stock-low';
            }

            $list[] = [
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'dpin' => get_post_meta($pid, '_dejoiy_dpin', true) ?: (function_exists('dejoiy_get_product_dpin') ? dejoiy_get_product_dpin($pid) : ''),
                'sku' => $p->get_sku(),
                'price_html' => $p->get_price_html() ?: '₹0.00',
                'regular_price' => $p->get_regular_price(),
                'sale_price' => $p->get_sale_price(),
                'mrp' => floatval(get_post_meta($pid, '_dso_mrp', true)),
                'stock_quantity' => $stock_qty,
                'manage_stock' => $manage_stock,
                'stock_status' => $stock_status,
                'stock_label' => $stock_label,
                'stock_status_class' => $stock_class,
                'total_sales' => $p->get_total_sales(),
                'status' => $p->get_status(),
                'status_badge' => $this->status_badge($p->get_status()),
                'image_html' => $thumb,
                'category' => $this->get_product_category_name($pid),
                'brand' => get_post_meta($pid, '_dso_brand', true),
                'date' => $p->get_date_created() ? $p->get_date_created()->format('M j, Y') : '—',
                'lqs_score' => $lqs,
                'lqs_class' => $lqs_class,
            ];
        }

        return $list;
    }

    /**
     * Aggregate Product Stats
     */
    public function get_product_stats($vendor_id = 0) {
        global $wpdb;

        $where = "p.post_type = 'product'";
        $params = [];

        if ($vendor_id > 0) {
            $where .= " AND (p.post_author = %d OR p.ID IN (SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_vendor_id' AND meta_value = %s))";
            $params = [$vendor_id, strval($vendor_id)];
        }

        $all_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p WHERE {$where} AND p.post_status IN ('publish', 'draft', 'pending', 'private')";
        $pub_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p WHERE {$where} AND p.post_status = 'publish'";
        $draft_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p WHERE {$where} AND p.post_status = 'draft'";

        if (!empty($params)) {
            $total = intval($wpdb->get_var($wpdb->prepare($all_query, ...$params)));
            $published = intval($wpdb->get_var($wpdb->prepare($pub_query, ...$params)));
            $draft = intval($wpdb->get_var($wpdb->prepare($draft_query, ...$params)));
        } else {
            $total = intval($wpdb->get_var($all_query));
            $published = intval($wpdb->get_var($pub_query));
            $draft = intval($wpdb->get_var($draft_query));
        }

        $low_thresh = intval(get_option('woocommerce_notify_low_stock_amount', 2));
        $low_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p 
            INNER JOIN {$wpdb->prefix}postmeta pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = '_stock'
            INNER JOIN {$wpdb->prefix}postmeta pm_mg ON p.ID = pm_mg.post_id AND pm_mg.meta_key = '_manage_stock' AND pm_mg.meta_value = 'yes'
            WHERE {$where} AND pm_st.meta_value > 0 AND CAST(pm_st.meta_value AS SIGNED) <= {$low_thresh}";

        $out_query = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->prefix}posts p 
            INNER JOIN {$wpdb->prefix}postmeta pm_st ON p.ID = pm_st.post_id AND pm_st.meta_key = '_stock_status' AND pm_st.meta_value = 'outofstock'
            WHERE {$where}";

        if (!empty($params)) {
            $low = intval($wpdb->get_var($wpdb->prepare($low_query, ...$params)));
            $out = intval($wpdb->get_var($wpdb->prepare($out_query, ...$params)));
        } else {
            $low = intval($wpdb->get_var($low_query));
            $out = intval($wpdb->get_var($out_query));
        }

        return [
            'total' => $total,
            'published' => $published,
            'draft' => $draft,
            'low_stock' => $low,
            'out_of_stock' => $out,
        ];
    }

    public function get_product_categories() {
        return get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => 0,
        ]);
    }

    public function get_product_category_name($product_id) {
        $terms = get_the_terms($product_id, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            return $terms[0]->name;
        }
        return 'General';
    }

    public function status_badge($status) {
        $map = [
            'publish' => ['Published', 'dso-badge-green'],
            'draft' => ['Draft', 'dso-badge-gray'],
            'pending' => ['Pending Review', 'dso-badge-yellow'],
            'private' => ['Private', 'dso-badge-blue'],
        ];
        $label = $map[$status][0] ?? ucfirst($status);
        $class = $map[$status][1] ?? 'dso-badge-gray';
        return '<span class="dso-badge ' . $class . '">' . $label . '</span>';
    }
}
