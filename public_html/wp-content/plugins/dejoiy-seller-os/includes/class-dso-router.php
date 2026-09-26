<?php
/**
 * DSO Router - Handles section routing, navigation hierarchy, and execution
 * Built for DEJOIY Seller Operating System
 */
if (!defined('ABSPATH')) exit;

class DSO_Router {

    private static $sections = [
        // Dashboard
        'dashboard'             => ['class' => 'DSO_Dashboard', 'title' => 'Dashboard', 'group' => 'main'],
        'marketing'               => ['class' => 'DSO_Marketing', 'title' => 'Marketing', 'group' => 'marketing'],
        'messages'              => ['class' => 'DSO_Messenger', 'method' => 'render_seller_view', 'title' => 'Buyer-Seller Messages', 'group' => 'main'],

        // Catalogue
        'products'              => ['class' => 'DSO_Products', 'title' => 'All Products', 'group' => 'catalogue'],
        'catalog-upload'        => ['class' => 'DSO_Products', 'method' => 'catalog_upload', 'title' => 'Bulk Catalog Upload', 'group' => 'catalogue'],
        'action-centre'         => ['class' => 'DSO_Notifications', 'method' => 'render', 'title' => 'Action Centre', 'group' => 'main'],
        'add-product'           => ['class' => 'DSO_Products', 'method' => 'add_product', 'title' => 'Add Product', 'group' => 'catalogue'],
        'edit-product'          => ['class' => 'DSO_Products', 'method' => 'edit_product', 'title' => 'Edit Product', 'group' => 'catalogue'],
        'categories'            => ['class' => 'DSO_Products', 'method' => 'categories', 'title' => 'Categories', 'group' => 'catalogue'],
        'collections'           => ['class' => 'DSO_Products', 'method' => 'collections', 'title' => 'Collections', 'group' => 'catalogue'],
        'attributes'            => ['class' => 'DSO_Products', 'method' => 'attributes', 'title' => 'Attributes', 'group' => 'catalogue'],
        'product-quality'       => ['class' => 'DSO_Products', 'method' => 'product_quality', 'title' => 'Listing Quality', 'group' => 'catalogue'],
        'reviews'               => ['class' => 'DSO_Reviews', 'title' => 'Product Reviews', 'group' => 'catalogue'],

        // Inventory
        'inventory'             => ['class' => 'DSO_Products', 'method' => 'inventory', 'title' => 'Inventory Overview', 'group' => 'inventory'],
        'inventory-stock'       => ['class' => 'DSO_Products', 'method' => 'inventory', 'title' => 'Stock Manager', 'group' => 'inventory'],
        'inventory-low'         => ['class' => 'DSO_Products', 'method' => 'inventory_low', 'title' => 'Low Stock Alerts', 'group' => 'inventory'],
        'inventory-out'         => ['class' => 'DSO_Products', 'method' => 'inventory_out', 'title' => 'Out of Stock', 'group' => 'inventory'],
        'inventory-history'     => ['class' => 'DSO_Products', 'method' => 'inventory_history', 'title' => 'Stock History', 'group' => 'inventory'],
        'inventory-bulk'        => ['class' => 'DSO_Products', 'method' => 'inventory_bulk', 'title' => 'Bulk Stock Updater', 'group' => 'inventory'],

        // Pricing
        'pricing'               => ['class' => 'DSO_Pricing', 'title' => 'Pricing Overview', 'group' => 'pricing'],
        'automate-pricing'      => ['class' => 'DSO_Pricing', 'method' => 'automate_pricing', 'title' => 'Automate Pricing', 'group' => 'pricing'],
        'promotions'            => ['class' => 'DSO_Pricing', 'method' => 'promotions', 'title' => 'Promotions', 'group' => 'pricing'],
        'coupons-deals'           => ['class' => 'DSO_Marketing', 'method' => 'coupons_deals', 'title' => 'Coupons & Deals', 'group' => 'marketing'],
        'deals'                 => ['class' => 'DSO_Pricing', 'method' => 'deals', 'title' => 'Deals & Flash Sales', 'group' => 'pricing'],
        'coupons'               => ['class' => 'DSO_Pricing', 'method' => 'coupons', 'title' => 'Coupons', 'group' => 'pricing'],
        'bulk-pricing'          => ['class' => 'DSO_Pricing', 'method' => 'bulk_pricing', 'title' => 'Bulk Pricing Rules', 'group' => 'pricing'],
        'b2b-pricing'           => ['class' => 'DSO_Pricing', 'method' => 'b2b_pricing', 'title' => 'B2B Wholesale Pricing', 'group' => 'pricing'],

        // Orders
        'orders'                => ['class' => 'DSO_Orders', 'title' => 'All Orders', 'group' => 'orders'],
        'orders-pending'        => ['class' => 'DSO_Orders', 'method' => 'orders_pending', 'title' => 'Pending Orders', 'group' => 'orders'],
        'orders-processing'     => ['class' => 'DSO_Orders', 'method' => 'orders_processing', 'title' => 'Processing Orders', 'group' => 'orders'],
        'orders-shipped'        => ['class' => 'DSO_Orders', 'method' => 'orders_shipped', 'title' => 'Shipped Orders', 'group' => 'orders'],
        'orders-delivered'      => ['class' => 'DSO_Orders', 'method' => 'orders_delivered', 'title' => 'Delivered Orders', 'group' => 'orders'],
        'orders-returns'        => ['class' => 'DSO_Orders', 'method' => 'orders_returns', 'title' => 'Returns', 'group' => 'orders'],
        'returns'               => ['class' => 'DSO_Orders', 'method' => 'returns', 'title' => 'Returns & RTO', 'group' => 'orders'],
        'disputes' => ['class' => 'DSO_Disputes', 'title' => 'Joi', 'group' => 'orders'],
        'orders-refunds'        => ['class' => 'DSO_Orders', 'method' => 'orders_refunds', 'title' => 'Refund Requests', 'group' => 'orders'],
        'orders-cancelled'      => ['class' => 'DSO_Orders', 'method' => 'orders_cancelled', 'title' => 'Cancelled Orders', 'group' => 'orders'],
        'order-detail'          => ['class' => 'DSO_Orders', 'method' => 'order_detail', 'title' => 'Order Detail', 'group' => 'orders'],
        'print-invoice'         => ['class' => 'DSO_Orders', 'method' => 'print_invoice', 'title' => 'Tax Invoice', 'group' => 'orders'],

        // Fulfillment / Shipping
        'shipping'              => ['class' => 'DSO_Shipping', 'title' => 'Shipping & Delivery', 'group' => 'fulfillment'],
        'shipments'             => ['class' => 'DSO_Shipping', 'method' => 'shipments', 'title' => 'Shipments', 'group' => 'fulfillment'],
        'shipping-tracking'     => ['class' => 'DSO_Shipping', 'method' => 'tracking', 'title' => 'Tracking & Carriers', 'group' => 'fulfillment'],
        'shipping-delivery'     => ['class' => 'DSO_Shipping', 'method' => 'delivery', 'title' => 'Delivery Rates', 'group' => 'fulfillment'],
        'shipping-pickup'       => ['class' => 'DSO_Shipping', 'method' => 'pickup', 'title' => 'Pickup Points', 'group' => 'fulfillment'],
        'shipping-packaging'    => ['class' => 'DSO_Shipping', 'method' => 'packaging', 'title' => 'Packaging Specs', 'group' => 'fulfillment'],
        'print-label'           => ['class' => 'DSO_Shipping', 'method' => 'print_label', 'title' => 'Print Shipping Label', 'group' => 'fulfillment'],

        // Advertising
        'advertising'           => ['class' => 'DSO_Advertising', 'title' => 'Campaign Manager', 'group' => 'advertising'],
        'advertising-sponsored' => ['class' => 'DSO_Advertising', 'method' => 'sponsored', 'title' => 'Sponsored Products', 'group' => 'advertising'],
        'advertising-promotions'=> ['class' => 'DSO_Advertising', 'method' => 'promotions', 'title' => 'Ad Promotions', 'group' => 'advertising'],
        'advertising-performance'=> ['class' => 'DSO_Advertising', 'method' => 'performance', 'title' => 'RoAS & Performance', 'group' => 'advertising'],

        // Growth
        'growth'                => ['class' => 'DSO_Growth', 'title' => 'Growth Opportunities', 'group' => 'growth'],
        'growth-recommendations'=> ['class' => 'DSO_Growth', 'method' => 'recommendations', 'title' => 'AI Recommendations', 'group' => 'growth'],
        'growth-insights'       => ['class' => 'DSO_Growth', 'method' => 'insights', 'title' => 'Customer Insights', 'group' => 'growth'],
        'growth-opportunities'  => ['class' => 'DSO_Growth', 'method' => 'opportunities', 'title' => 'Product Opportunities', 'group' => 'growth'],

        // Reports & Analytics
        'reports'               => ['class' => 'DSO_Reports', 'title' => 'Sales Analytics', 'group' => 'reports'],
        'reports-orders'        => ['class' => 'DSO_Reports', 'method' => 'orders_report', 'title' => 'Order Reports', 'group' => 'reports'],
        'reports-revenue'       => ['class' => 'DSO_Reports', 'method' => 'revenue_report', 'title' => 'Revenue & Commission', 'group' => 'reports'],
        'reports-products'      => ['class' => 'DSO_Reports', 'method' => 'products_report', 'title' => 'Product Reports', 'group' => 'reports'],
        'reports-inventory'     => ['class' => 'DSO_Reports', 'method' => 'inventory_report', 'title' => 'Inventory Velocity', 'group' => 'reports'],
        'reports-customers'     => ['class' => 'DSO_Reports', 'method' => 'customers_report', 'title' => 'Customer Reports', 'group' => 'reports'],
        'reports-marketing'     => ['class' => 'DSO_Reports', 'method' => 'marketing_report', 'title' => 'Marketing Reports', 'group' => 'reports'],
        'reports-finance'       => ['class' => 'DSO_Reports', 'method' => 'financial_report', 'title' => 'Financial Statements', 'group' => 'reports'],
        'analytics'             => ['class' => 'DSO_Reports', 'title' => 'Analytics', 'group' => 'reports'],

        // Payments & Finance
        'finance'               => ['class' => 'DSO_Finance', 'title' => 'Balance Overview', 'group' => 'payments'],
        'finance-transactions'  => ['class' => 'DSO_Finance', 'method' => 'transactions', 'title' => 'Transactions & Ledger', 'group' => 'payments'],
        'finance-commissions'   => ['class' => 'DSO_Finance', 'method' => 'commissions', 'title' => 'Commissions Breakdown', 'group' => 'payments'],
        'withdrawals'           => ['class' => 'DSO_Finance', 'method' => 'withdrawals', 'title' => 'Withdrawal Requests', 'group' => 'payments'],
        'finance-payments'        => ['class' => 'DSO_Finance', 'method' => 'payments', 'title' => 'Payments', 'group' => 'finance'],
        'finance-fees'            => ['class' => 'DSO_Finance', 'method' => 'fees', 'title' => 'Fees', 'group' => 'finance'],
        'finance-payouts'       => ['class' => 'DSO_Finance', 'method' => 'payouts', 'title' => 'Payout History', 'group' => 'payments'],
        'finance-settlements'     => ['class' => 'DSO_Finance', 'method' => 'settlements', 'title' => 'Settlements', 'group' => 'finance'],
        'finance-statements'    => ['class' => 'DSO_Finance', 'method' => 'statements', 'title' => 'Tax Invoices & Statements', 'group' => 'payments'],

        // Performance
        'performance'           => ['class' => 'DSO_Performance', 'title' => 'Store Health', 'group' => 'performance'],
        'performance-products'  => ['class' => 'DSO_Performance', 'method' => 'product_health', 'title' => 'Product Health', 'group' => 'performance'],
        'performance-seller'    => ['class' => 'DSO_Performance', 'method' => 'seller_performance', 'title' => 'Seller Performance Tier', 'group' => 'performance'],
        'performance-csat'      => ['class' => 'DSO_Performance', 'method' => 'customer_satisfaction', 'title' => 'Customer Satisfaction', 'group' => 'performance'],
        'performance-delivery'  => ['class' => 'DSO_Performance', 'method' => 'delivery_performance', 'title' => 'Delivery Performance', 'group' => 'performance'],

        // B2B Wholesale
        'b2b'                   => ['class' => 'DSO_B2B', 'title' => 'B2B Wholesale Hub', 'group' => 'b2b'],
        'b2b-orders'            => ['class' => 'DSO_B2B', 'method' => 'bulk_orders', 'title' => 'Bulk Orders', 'group' => 'b2b'],
        'b2b-customers'         => ['class' => 'DSO_B2B', 'method' => 'business_customers', 'title' => 'Business Customers', 'group' => 'b2b'],
        'b2b-offers'            => ['class' => 'DSO_B2B', 'method' => 'b2b_offers', 'title' => 'B2B RFQ & Offers', 'group' => 'b2b'],

        // Brands
        'brands'                => ['class' => 'DSO_Brands', 'title' => 'My Brands', 'group' => 'brands'],
        'brands-assets'         => ['class' => 'DSO_Brands', 'method' => 'brand_assets', 'title' => 'Brand Assets & Media', 'group' => 'brands'],
        'brands-protection'     => ['class' => 'DSO_Brands', 'method' => 'brand_protection', 'title' => 'Brand Protection & IP', 'group' => 'brands'],

        // Apps & Services
        'apps'                  => ['class' => 'DSO_Apps', 'title' => 'Seller Tools & Apps', 'group' => 'apps'],
        'apps-integrations'     => ['class' => 'DSO_Apps', 'method' => 'integrations', 'title' => 'Integrations (Shiprocket, Razorpay)', 'group' => 'apps'],
        'apps-automation'       => ['class' => 'DSO_Apps', 'method' => 'automation', 'title' => 'Automation Rules', 'group' => 'apps'],
        'apps-services'         => ['class' => 'DSO_Apps', 'method' => 'services', 'title' => 'Marketplace Services', 'group' => 'apps'],

        // Learn
        'learn'                 => ['class' => 'DSO_Learn', 'title' => 'Seller University', 'group' => 'learn'],
        'learn-kb'              => ['class' => 'DSO_Learn', 'method' => 'knowledgebase', 'title' => 'Knowledgebase', 'group' => 'learn'],
        'learn-guides'          => ['class' => 'DSO_Learn', 'method' => 'guides', 'title' => 'Listing & SEO Guides', 'group' => 'learn'],
        'learn-tutorials'       => ['class' => 'DSO_Learn', 'method' => 'tutorials', 'title' => 'Video Tutorials', 'group' => 'learn'],
        'learn-policies'        => ['class' => 'DSO_Learn', 'method' => 'policies', 'title' => 'Policies & Standards', 'group' => 'learn'],

        // Customers
        'customers'             => ['class' => 'DSO_Customers', 'title' => 'Customer List', 'group' => 'orders'],

        // Settings & Operations
        'store'                 => ['class' => 'DSO_Store', 'title' => 'Store Settings', 'group' => 'settings'],
        'settings'              => ['class' => 'DSO_Settings', 'title' => 'Account & Business Profile', 'group' => 'settings'],
        'settings-tax'          => ['class' => 'DSO_Settings', 'method' => 'tax', 'title' => 'GST & Tax Settings', 'group' => 'settings'],
        'settings-security'       => ['class' => 'DSO_Settings', 'method' => 'security', 'title' => 'Security', 'group' => 'settings'],
        'settings-bank'           => ['class' => 'DSO_Settings', 'method' => 'bank', 'title' => 'Bank Details', 'group' => 'settings'],
        'settings-shipping'     => ['class' => 'DSO_Shipping', 'title' => 'Shipping Settings', 'group' => 'settings'],
        'notifications'         => ['class' => 'DSO_Notifications', 'title' => 'Notifications', 'group' => 'settings'],
        'support'               => ['class' => 'DSO_Support', 'title' => 'Support & Help Desk', 'group' => 'settings'],

        // Marketplace Admin (Master Multi-Vendor Control Plane)
        'marketplace-vendors'     => ['class' => 'DSO_Marketplace', 'method' => 'vendors', 'title' => 'Marketplace Vendors & Approvals', 'group' => 'marketplace'],
        'marketplace-withdrawals' => ['class' => 'DSO_Marketplace', 'method' => 'withdrawals', 'title' => 'Payouts & Settlement Disbursals', 'group' => 'marketplace'],
        'marketplace-settings'    => ['class' => 'DSO_Marketplace', 'method' => 'settings', 'title' => 'Marketplace Engine Settings', 'group' => 'marketplace'],
        'marketplace-ledger'      => ['class' => 'DSO_Marketplace', 'method' => 'ledger', 'title' => 'Platform Revenue & Split Ledger', 'group' => 'marketplace'],
    ];

    /**
     * Render the requested section inside the application shell
     */
    public static function render($section) {
        $user_id = DSO_Auth::require_vendor();
        $store = DSO_Auth::get_vendor_store($user_id);
        $caps = DSO_Auth::get_capabilities($user_id);

        if (!isset(self::$sections[$section])) {
            $section = 'dashboard';
        }

        $config = self::$sections[$section];
        $class_name = $config['class'];
        $method = $config['method'] ?? 'render';

        if (!class_exists($class_name)) {
            $class_name = 'DSO_Dashboard';
            $method = 'render';
        }

        $class = new $class_name();
        $page_title = $config['title'] ?? 'Seller Hub';
        $current_section = $section;
        $nav_items = self::get_nav_items($caps);
        $user_id = get_current_user_id();
        $store = DSO_Auth::get_vendor_store($user_id);
        $store_name = $store ? $store['name'] : 'Seller';
        $user_data = get_userdata($user_id);
        $display_name = $user_data ? $user_data->display_name : $store_name;
        $store_logo = $store && !empty($store['logo']) ? $store['logo'] : '';

        ob_start();
        include DSO_PATH . 'templates/seller-os-shell.php';
        $content = ob_get_clean();
        echo $content;
    }

    /**
     * Get structured navigation hierarchy with badges and icons
     */
    public static function get_nav_items($caps = []) {
        $items = [
            [
                'id' => 'overview',
                'label' => 'Home',
                'group' => 'Overview',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
                'url' => 'dashboard',
            ],
            [
                'id' => 'action-centre',
                'label' => 'Action Centre',
                'group' => 'Overview',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
                'url' => 'action-centre',
            ],
        ];

        if (current_user_can('administrator') || current_user_can('manage_options')) {
            $items[] = [
                'id' => 'marketplace-master',
                'label' => 'Marketplace Master',
                'group' => '👑 Marketplace Master',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
                'url' => 'marketplace-vendors',
                'children' => [
                    ['id' => 'marketplace-vendors', 'label' => 'Marketplace Vendors', 'url' => 'marketplace-vendors'],
                    ['id' => 'marketplace-withdrawals', 'label' => 'Payout Disbursals', 'url' => 'marketplace-withdrawals'],
                    ['id' => 'marketplace-settings', 'label' => 'Marketplace Settings', 'url' => 'marketplace-settings'],
                    ['id' => 'marketplace-ledger', 'label' => 'Platform Revenue', 'url' => 'marketplace-ledger'],
                ],
            ];
        }

        $items = array_merge($items, [
            [
                'id' => 'sell',
                'label' => 'Orders',
                'group' => 'Sell',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>',
                'url' => 'orders',
            ],
            [
                'id' => 'products',
                'label' => 'Products',
                'group' => 'Sell',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16z"/></svg>',
                'url' => 'products',
            ],
            [
                'id' => 'inventory',
                'label' => 'Inventory',
                'group' => 'Sell',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>',
                'url' => 'inventory',
            ],
            [
                'id' => 'returns',
                'label' => 'Returns & RTO',
                'group' => 'Sell',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
                'url' => 'returns',
            ],
                    [
                        'id' => 'disputes',
                        'label' => 'Joi',
                        'group' => 'Sell',
                        'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                        'url' => 'disputes',
                    ],
            [
                'id' => 'sell-reviews',
                'label' => 'Reviews',
                'group' => 'Sell',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
                'url' => 'reviews',
            ],

            [
                'id' => 'analytics',
                'label' => 'Analytics',
                'group' => 'Grow',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>',
                'url' => 'reports',
            ],
            [
                'id' => 'marketing',
                'label' => 'Marketing',
                'group' => 'Grow',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 010 14.14M15.54 8.46a5 5 0 010 7.07"/></svg>',
                'url' => 'marketing',
            ],
            [
                'id' => 'advertising',
                'label' => 'Advertising',
                'group' => 'Grow',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
                'url' => 'advertising',
            ],
            [
                'id' => 'pricing-insights',
                'label' => 'Pricing Insights',
                'group' => 'Grow',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>',
                'url' => 'pricing',
            ],
            [
                'id' => 'product-opportunities',
                'label' => 'Product Opportunities',
                'group' => 'Grow',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
                'url' => 'growth',
            ],

            [
                'id' => 'finance',
                'label' => 'Finance',
                'group' => 'Money',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>',
                'url' => 'finance',
            ],
            [
                'id' => 'payments',
                'label' => 'Payments',
                'group' => 'Money',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>',
                'url' => 'finance-payments',
            ],
            [
                'id' => 'settlements',
                'label' => 'Settlements',
                'group' => 'Money',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
                'url' => 'finance-settlements',
            ],
            [
                'id' => 'fees',
                'label' => 'Fees & Deductions',
                'group' => 'Money',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="15" x2="15.01" y2="15"/></svg>',
                'url' => 'finance-fees',
            ],
            [
                'id' => 'payouts',
                'label' => 'Payouts',
                'group' => 'Money',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
                'url' => 'withdrawals',
            ],

            [
                'id' => 'customers',
                'label' => 'Customers',
                'group' => 'Customers',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
                'url' => 'customers',
            ],
            [
                'id' => 'customer-insights',
                'label' => 'Customer Insights',
                'group' => 'Customers',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
                'url' => 'growth-insights',
            ],
            [
                'id' => 'messages',
                'label' => 'Messages',
                'group' => 'Customers',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
                'url' => 'messages',
            ],

            [
                'id' => 'storefront',
                'label' => 'Storefront',
                'group' => 'Store',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
                'url' => 'store',
            ],
            [
                'id' => 'store-settings',
                'label' => 'Store Settings',
                'group' => 'Store',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.32 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
                'url' => 'store',
            ],
            [
                'id' => 'shipping-fulfillment',
                'label' => 'Shipping & Fulfillment',
                'group' => 'Store',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
                'url' => 'shipping',
            ],
            [
                'id' => 'policies',
                'label' => 'Policies',
                'group' => 'Store',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
                'url' => 'learn-policies',
            ],

            [
                'id' => 'seller-university',
                'label' => 'Seller University',
                'group' => 'Help',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>',
                'url' => 'learn',
            ],
            [
                'id' => 'support',
                'label' => 'Support',
                'group' => 'Help',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
                'url' => 'support',
            ],
            [
                'id' => 'notices',
                'label' => 'Notices',
                'group' => 'Help',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>',
                'url' => 'notifications',
            ],

            [
                'id' => 'profile',
                'label' => 'Profile',
                'group' => 'Account',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
                'url' => 'settings',
            ],
            [
                'id' => 'kyc-gst',
                'label' => 'KYC / GST',
                'group' => 'Account',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
                'url' => 'settings-tax',
            ],
            [
                'id' => 'bank',
                'label' => 'Bank',
                'group' => 'Account',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
                'url' => 'settings-bank',
            ],
            [
                'id' => 'security',
                'label' => 'Security',
                'group' => 'Account',
                'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
                'url' => 'settings-security',
            ],
        ]);

        return $items;
    }

    /**
     * Get current active section from request parameter
     */
    public static function get_current_section() {
        if (!empty($_GET['section'])) {
            return sanitize_text_field($_GET['section']);
        }
        if (!empty($_GET['seller-hub'])) {
            return sanitize_text_field($_GET['seller-hub']);
        }
        return 'dashboard';
    }

    /**
     * Get configuration array for a section
     */
    public static function get_current_section_config($section) {
        if (isset(self::$sections[$section])) {
            return self::$sections[$section];
        }
        return [
            'class'  => 'DSO_Router',
            'method' => 'render_404',
            'title'  => 'Page Not Found',
            'group'  => 'main',
        ];
    }

    /**
     * Get all registered sections
     */
    /**
     * Render a mobile-friendly 404 page for unknown sections
     */
    public function render_404() {
        if (function_exists('status_header')) {
            status_header(404);
        }
        ?>
        <style>
        .dso-404{max-width:720px;margin:0 auto;padding:48px 16px;text-align:center}
        .dso-404 h1{font-size:2rem;margin:0 0 8px}
        .dso-404 p{color:#64748b;margin:0 0 24px}
        .dso-404 a{display:inline-block;background:#6d28d9;color:#fff;text-decoration:none;padding:10px 24px;border-radius:8px;font-weight:600}
        .dso-404 a:hover{background:#5b21b6}
        @media(max-width:480px){.dso-404{padding:32px 12px}.dso-404 h1{font-size:1.5rem}}
        </style>
        <div class="dso-404">
            <h1>Page Not Found</h1>
            <p>The page you are looking for does not exist or has been moved.</p>
            <a href="?section=dashboard">Back to Dashboard</a>
        </div>
        <?php
    }

    public static function get_all_sections() {
        return self::$sections;
    }
}

