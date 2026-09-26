<?php
/**
 * DEJOIY Seller Sign-In Page
 * Premium authentication experience
 */
if (!defined('ABSPATH')) exit;

// Handle redirect after login
$redirect_to = isset($_GET['redirect']) ? sanitize_url($_GET['redirect']) : home_url('/sellerhub/seller-hub.php');
$error_message = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
$current_user = wp_get_current_user();

// If user is already logged in, redirect
if ($current_user->ID > 0 && !is_wp_error($current_user)) {
    wp_redirect($redirect_to);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — DEJOIY Seller Central</title>
    <link rel="icon" type="image/png" href="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-FAVICON-100x100.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo plugins_url('assets/css/dejoiy-brand.css', __FILE__); ?>">
    <style>
        .dejoiy-hero-gradient {
            background: linear-gradient(135deg, #0047AB 0%, #0066CC 40%, #007AFF 100%);
        }
    </style>
</head>
<body class="dejoiy-auth-page">
    <!-- Left Side - Form -->
    <div class="dejoiy-auth-left">
        <div class="dejoiy-auth-card">
            <div class="dejoiy-auth-brand">
                <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY">
                <span class="dejoiy-brand-badge" style="font-size: 11px;">SELLER CENTRAL</span>
            </div>
            
            <h1 class="dejoiy-auth-title">Welcome Back</h1>
            <p class="dejoiy-auth-subtitle">Sign in to your DEJOIY Seller account to manage your business.</p>
            
            <?php if (!empty($error_message)): ?>
                <div style="background: #FEE2E2; border: 1px solid #FECACA; color: #DC2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 8px;">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url(wp_login_url()); ?>" class="dejoiy-auth-form">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="username">Email or Username</label>
                    <input type="text" 
                           id="username" 
                           name="log" 
                           class="dejoiy-form-input" 
                           placeholder="Enter your email" 
                           autocomplete="username"
                           required>
                </div>
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="pwd" 
                           class="dejoiy-form-input" 
                           placeholder="Enter your password" 
                           autocomplete="current-password"
                           required>
                </div>
                
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" 
                               id="rememberme" 
                               name="rememberme" 
                               value="forever" 
                               style="width: 16px; height: 16px; accent-color: #0047AB;">
                        <label for="rememberme" style="font-size: 13px; color: #64748B; cursor: pointer;">Remember me</label>
                    </div>
                    <a href="/forgot-password.php" style="font-size: 13px; color: #0047AB; font-weight: 500; text-decoration: none;">Forgot password?</a>
                </div>
                
                <button type="submit" 
                        class="dejoiy-btn dejoiy-btn-primary dejoiy-btn-full" 
                        style="margin-bottom: 20px;">
                    Sign In
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>
            
            <div class="dejoiy-auth-divider">OR CONTINUE WITH</div>
            
            <div class="dejoiy-social-login">
                <button type="button" class="dejoiy-social-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Google
                </button>
                <button type="button" class="dejoiy-social-btn">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M22.162 14.159c-.432-.26-.984-.624-1.387-1.032-.985-1.026-2.44-1.5-4.065-1.5-3.225 0-5.78 2.475-5.78 6.013 0 1.324.384 2.433.891 3.264-.376-.027-.75-.096-1.078-.096-1.324 0-2.502 1.058-2.846 2.414-.114.44-.058 1.023.368 1.626.406.56 1.18.804 2.053.78 1.08-.04 1.84-.773 1.84-1.933 0-.72-.366-1.353-.976-1.717.99 1.045 2.32 1.602 3.788 1.634-1.096 1.042-2.572 1.602-4.205 1.512.82 1.353 2.32 2.064 3.95 2.064 3.225 0 5.78-2.475 5.78-6.013 0-3.537-2.555-6.012-5.78-6.012z"/>
                    </svg>
                    Apple
                </button>
            </div>
            
            <p class="dejoiy-form-footer" style="margin-top: 24px;">
                Don't have a seller account? 
                <a href="/seller-register.php">Start selling on DEJOIY</a>
            </p>
        </div>
    </div>
    
    <!-- Right Side - Decorative -->
    <div class="dejoiy-auth-right">
        <div class="dejoiy-auth-decoration">
            <div class="dejoiy-auth-deco-content">
                <div class="dejoiy-auth-deco-badge">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    DEJOIY SELLER CENTRAL
                </div>
                <h2 class="dejoiy-auth-deco-title">Your Business,<br>Supercharged</h2>
                <p class="dejoiy-auth-deco-text">
                    Join thousands of sellers growing their business on India's premium marketplace.
                </p>
                <div class="dejoiy-auth-deco-features">
                    <div class="dejoiy-auth-deco-feature">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Fast onboarding in minutes
                    </div>
                    <div class="dejoiy-auth-deco-feature">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Pan-India delivery network
                    </div>
                    <div class="dejoiy-auth-deco-feature">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        Real-time analytics & insights
                    </div>
                    <div class="dejoiy-auth-deco-feature">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                        24/7 seller support
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bottom Nav - Mobile -->
    <style>
        @media (max-width: 768px) {
            .dejoiy-auth-left {
                display: none;
            }
            .dejoiy-auth-right {
                display: none;
            }
            .dejoiy-auth-card {
                max-width: 100%;
                margin: 16px;
            }
            .dejoiy-auth-page {
                background: white;
            }
        }
    </style>
</body>
</html>
