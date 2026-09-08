<?php
/**
 * DEJOIY Seller Hub — Standalone Entry Point
 * Bypasses WordPress normal routing to avoid redirect issues
 */

// Set up environment
$_SERVER['SERVER_PORT'] = '8080';
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';

// Load WordPress
define('ABSPATH', __DIR__ . '/');
require_once ABSPATH . 'wp-load.php';

// Override URLs after WP loads
$host = $_SERVER['HTTP_HOST'];
$site_url = 'https://' . $host;

// Override all URL filters
add_filter('option_siteurl', function($v) use ($site_url) { return $site_url; }, 9999);
add_filter('option_home', function($v) use ($site_url) { return $site_url; }, 9999);
add_filter('site_url', function($v) use ($site_url) { return $site_url; }, 9999);
add_filter('home_url', function($v) use ($site_url) { return $site_url; }, 9999);
add_filter('admin_url', function($v) use ($site_url) { return $site_url . '/wp-admin/'; }, 9999);
add_filter('login_url', function($v) use ($site_url) { return $site_url . '/wp-login.php'; }, 9999);
add_filter('force_ssl_admin', '__return_false', 9999);
add_filter('force_ssl_login', '__return_false', 9999);
add_filter('redirect_canonical', '__return_false', 9999);

// Prevent canonical redirect
remove_action('template_redirect', 'wp_redirect_canonical', 20);
remove_filter('template_redirect', 'wp_redirect_canonical', 20);

// Handle authentication
if (!is_user_logged_in()) {
    // Try to authenticate via POST data or cookies
    if (isset($_POST['log']) && isset($_POST['pwd'])) {
        $user = wp_signon([
            'user_login' => sanitize_text_field($_POST['log']),
            'user_password' => $_POST['pwd'],
            'remember' => true,
        ]);
        if (is_wp_error($user)) {
            // Show login form
            show_login_form($user->get_error_message());
            exit;
        }
        // Redirect to seller hub
        wp_redirect($site_url . '/seller-hub.php?section=dashboard');
        exit;
    }

    // Show login form
    show_login_form();
    exit;
}

// User is authenticated - check if they're a vendor
$plugin = Dejoiy_Seller_OS::instance();
if (!$plugin->is_vendor()) {
    wp_die('Access Denied — You need to be a registered seller to access the Seller Hub.', 'Unauthorized', ['response' => 403]);
}

// Get current section
$section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'dashboard';

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'stock_update') {
    header('Content-Type: application/json');
    $product_id = intval($_POST['product_id'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    $product = wc_get_product($product_id);
    if ($product) {
        $product->set_stock_quantity($stock);
        $product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        $product->save();
        echo json_encode(['success' => true, 'stock' => $stock]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
    exit;
}

// Render the seller hub
$user_id = get_current_user_id();
$store = DSO_Auth::get_vendor_store($user_id);
$caps = DSO_Auth::get_capabilities($user_id);
$nav_items = DSO_Router::get_nav_items($caps);
$unread_count = 0;
if (class_exists('DSO_Notifications')) {
    $notif = new DSO_Notifications();
    $unread_count = $notif->get_unread_count($user_id);
}

// Determine which section to render
$config = DSO_Router::get_current_section_config($section);
if (!$config) {
    $section = 'dashboard';
    $config = ['class' => 'DSO_Dashboard', 'title' => 'Dashboard'];
}

$current_section = $section;
$page_title = $config['title'] ?? 'Dashboard';

// Start output
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Hub — DEJOIY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://<?php echo $host; ?>/wp-content/plugins/dejoiy-seller-os/assets/css/seller-os.css" rel="stylesheet">
    <?php wp_head(); ?>
    <script>
    var dsoData = {
        ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
        restUrl: '<?php echo rest_url('dejoiy-seller-os/v1/'); ?>',
        nonce: '<?php echo wp_create_nonce('wp_rest'); ?>',
        vendorId: <?php echo $plugin->get_vendor_id(); ?>,
        userId: <?php echo $user_id; ?>,
        baseUrl: '<?php echo $site_url; ?>/seller-hub.php',
        currency: '<?php echo get_woocommerce_currency_symbol(); ?>'
    };
    </script>
</head>
<body>
<div id="dso-app" class="dso-app">
    <!-- Top Bar -->
    <div class="dso-topbar">
        <button class="dso-menu-toggle" id="dso-menu-toggle" aria-label="Toggle menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="dso-topbar-title"><span class="dso-logo-text">🏪 Seller Hub</span></div>
        <div class="dso-topbar-actions">
            <button class="dso-search-toggle" id="dso-search-toggle" aria-label="Search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
            <a href="?section=notifications" class="dso-topbar-notif">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                <?php if ($unread_count > 0): ?>
                    <span class="dso-notif-badge"><?php echo $unread_count ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <aside class="dso-sidebar" id="dso-sidebar">
        <div class="dso-sidebar-header">
            <a href="?section=dashboard" class="dso-sidebar-logo">
                <span class="dso-logo-icon">🏪</span>
                <span class="dso-logo-text">Seller Hub</span>
            </a>
            <button class="dso-sidebar-close" id="dso-sidebar-close" aria-label="Close menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <nav class="dso-sidebar-nav">
            <?php foreach ($nav_items as $item): ?>
                <div class="dso-nav-item <?php echo $current_section === $item['id'] ? 'dso-nav-active' : '' ?>">
                    <a href="?section=<?php echo esc_attr($item['url']) ?>" class="dso-nav-link">
                        <span class="dso-nav-icon"><?php echo $item['icon'] ?></span>
                        <span class="dso-nav-label"><?php echo esc_html($item['label']) ?></span>
                        <?php if ($item['id'] === 'notifications' && $unread_count > 0): ?>
                            <span class="dso-nav-badge"><?php echo $unread_count ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['children'])): ?>
                            <svg class="dso-nav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg>
                        <?php endif; ?>
                    </a>
                    <?php if (!empty($item['children'])): ?>
                        <div class="dso-nav-children">
                            <?php foreach ($item['children'] as $child): ?>
                                <a href="?section=<?php echo esc_attr($child['url']) ?>" class="dso-nav-child <?php echo $current_section === $child['id'] ? 'dso-nav-active' : '' ?>">
                                    <?php echo esc_html($child['label']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="dso-sidebar-footer">
            <a href="https://dejoiy.com/my-account/" class="dso-nav-link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Back to My Account</span>
            </a>
            <a href="<?php echo wp_logout_url('https://dejoiy.com'); ?>" class="dso-nav-link dso-nav-logout">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Logout</span>
            </a>
        </div>
    </aside>

    <div class="dso-sidebar-overlay" id="dso-sidebar-overlay"></div>

    <!-- Main Content -->
    <main class="dso-main" id="dso-main">
        <?php
        $class_name = $config['class'];
        $method = $config['method'] ?? 'render';
        $class = new $class_name();
        $class->$method();
        ?>
    </main>

    <!-- Mobile Bottom Nav -->
    <nav class="dso-bottom-nav" id="dso-bottom-nav">
        <a href="?section=dashboard" class="dso-bottom-nav-item <?php echo $current_section === 'dashboard' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            <span>Home</span>
        </a>
        <a href="?section=orders" class="dso-bottom-nav-item <?php echo $current_section === 'orders' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            <span>Orders</span>
        </a>
        <a href="?section=products" class="dso-bottom-nav-item <?php echo $current_section === 'products' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 002 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0022 16z"/></svg>
            <span>Products</span>
        </a>
        <a href="?section=analytics" class="dso-bottom-nav-item <?php echo $current_section === 'analytics' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg>
            <span>Analytics</span>
        </a>
        <a href="?section=finance" class="dso-bottom-nav-item <?php echo $current_section === 'finance' ? 'dso-bottom-active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            <span>Finance</span>
        </a>
    </nav>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://<?php echo $host; ?>/wp-content/plugins/dejoiy-seller-os/assets/js/seller-os.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    var toggle = document.getElementById('dso-menu-toggle');
    var sidebar = document.getElementById('dso-sidebar');
    var overlay = document.getElementById('dso-sidebar-overlay');
    var close = document.getElementById('dso-sidebar-close');
    if (toggle) toggle.addEventListener('click', function() {
        sidebar.classList.toggle('dso-sidebar-open');
        overlay.classList.toggle('dso-visible');
    });
    function closeSidebar() {
        sidebar.classList.remove('dso-sidebar-open');
        overlay.classList.remove('dso-visible');
    }
    if (overlay) overlay.addEventListener('click', closeSidebar);
    if (close) close.addEventListener('click', closeSidebar);

    // Nav children
    document.querySelectorAll('.dso-nav-item').forEach(function(item) {
        var chevron = item.querySelector('.dso-nav-chevron');
        if (chevron) {
            item.addEventListener('mouseenter', function() { item.classList.add('dso-nav-expanded'); });
            item.addEventListener('mouseleave', function() { item.classList.remove('dso-nav-expanded'); });
        }
    });
});
</script>
</body>
</html>
<?php
$page_html = ob_get_clean();
echo $page_html;
exit;

/**
 * Show login form
 */
function show_login_form($error = '') {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login — Seller Hub</title>
        <link href="https://<?php echo $host; ?>/wp-content/plugins/dejoiy-seller-os/assets/css/seller-os.css" rel="stylesheet">
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #0f1629 0%, #1a1d3a 50%, #2d1b69 100%); padding: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
            .login-card { background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 40px 32px; width: 100%; max-width: 420px; margin: auto; }
            .login-card h1 { text-align: center; margin-bottom: 8px; font-size: 26px; font-weight: 800; color: #1a1d2e; }
            .login-card .subtitle { text-align: center; color: #6b7280; margin-bottom: 28px; font-size: 14px; }
            .login-card .error { background: #fef2f2; color: #991b1b; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; border: 1px solid #fca5a5; }
            .login-card .dso-form-group { margin-bottom: 18px; }
            .login-card .dso-form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
            .login-card .dso-input { width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 15px; font-family: inherit; color: #1a1d2e; transition: all 0.2s; }
            .login-card .dso-input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 4px rgba(79,70,229,0.1); }
            .login-card .dso-btn { display: block; width: 100%; padding: 13px 20px; background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; margin-top: 8px; }
            .login-card .dso-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 15px rgba(79,70,229,0.4); }
            .login-card .dso-btn:active { transform: translateY(0); }
            .login-footer { text-align: center; margin-top: 20px; font-size: 12px; color: #9ca3af; }
            .login-footer a { color: #4f46e5; text-decoration: none; font-weight: 600; }
            @media (max-width: 480px) { .login-card { padding: 28px 20px; } .login-card h1 { font-size: 22px; } }
            @media (max-width: 360px) { .login-card { padding: 24px 16px; } }
        </style>
    </head>
    <body>
        <div class="login-card">
            <h1>🏪 Seller Hub</h1>
            <p class="subtitle">Sign in to manage your store</p>
            <?php if ($error): ?>
                <div class="error"><?php echo esc_html($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="dso-form-group">
                    <label for="log">Username or Email</label>
                    <input type="text" id="log" name="log" class="dso-input" required autofocus />
                </div>
                <div class="dso-form-group">
                    <label for="pwd">Password</label>
                    <input type="password" id="pwd" name="pwd" class="dso-input" required />
                </div>
                <button type="submit" class="dso-btn">Sign In →</button>
            </form>
            <div class="login-footer">
                <p>Don't have an account? <a href="https://dejoiy.com/vendor-register/">Register as Seller</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
