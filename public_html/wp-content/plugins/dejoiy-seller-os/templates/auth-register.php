<?php
/**
 * DEJOIY Seller Registration Page
 * Start your selling journey
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Selling — DEJOIY Seller Central</title>
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
    <!-- Navigation -->
    <nav class="dejoiy-nav" style="position: fixed; top: 0; left: 0; right: 0; z-index: 100;">
        <a href="/" class="dejoiy-nav-logo">
            <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY">
        </a>
        <div class="dejoiy-nav-actions" style="margin-left: auto;">
            <a href="/wp-login.php" class="dejoiy-btn dejoiy-btn-ghost">Sign In</a>
        </div>
    </nav>

    <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 24px;">
        <div class="dejoiy-auth-card" style="max-width: 480px; width: 100%;">
            <div style="text-align: center; margin-bottom: 32px;">
                <div class="dejoiy-auth-brand" style="justify-content: center;">
                    <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY" style="height: 48px;">
                    <span class="dejoiy-brand-badge" style="font-size: 11px;">SELLER CENTRAL</span>
                </div>
            </div>
            
            <h1 class="dejoiy-auth-title" style="text-align: center; margin-bottom: 8px;">Start Selling on DEJOIY</h1>
            <p class="dejoiy-auth-subtitle" style="text-align: center; margin-bottom: 32px;">
                Join thousands of sellers growing their business on India's premium marketplace.
            </p>
            
            <form class="dejoiy-auth-form">
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="store_name">Store Name</label>
                    <input type="text" 
                           id="store_name" 
                           name="store_name" 
                           class="dejoiy-form-input" 
                           placeholder="My DEJOIY Store" 
                           required>
                    <p class="dejoiy-form-hint">This will be your public store name on DEJOIY</p>
                </div>
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="email">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="dejoiy-form-input" 
                           placeholder="you@example.com" 
                           required>
                </div>
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="phone">Mobile Number</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="dejoiy-form-input" 
                           placeholder="+91 98765 43210" 
                           required>
                    <p class="dejoiy-form-hint">For OTP verification and order updates</p>
                </div>
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="dejoiy-form-input" 
                           placeholder="Create a strong password" 
                           minlength="8"
                           required>
                    <p class="dejoiy-form-hint">Minimum 8 characters with a mix of letters and numbers</p>
                </div>
                
                <div class="dejoiy-form-group">
                    <label class="dejoiy-form-label" for="confirm_password">Confirm Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           class="dejoiy-form-input" 
                           placeholder="Confirm your password" 
                           required>
                </div>
                
                <div style="background: #F8FAFC; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
                    <p style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 12px;">What you'll get:</p>
                    <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 8px;">
                        <li style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748B;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Free seller account setup
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748B;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Access to seller dashboard
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748B;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Product listing tools
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748B;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Order management system
                        </li>
                        <li style="display: flex; align-items: center; gap: 10px; font-size: 13px; color: #64748B;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Pan-India fulfillment network
                        </li>
                    </ul>
                </div>
                
                <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 20px;">
                    <input type="checkbox" 
                           id="terms" 
                           name="terms" 
                           style="width: 16px; height: 16px; accent-color: #0047AB; margin-top: 2px;" 
                           required>
                    <label for="terms" style="font-size: 13px; color: #64748B; line-height: 1.5;">
                        I agree to the 
                        <a href="#" style="color: #0047AB; font-weight: 500;">Seller Terms & Conditions</a>, 
                        <a href="#" style="color: #0047AB; font-weight: 500;">Privacy Policy</a>, 
                        and 
                        <a href="#" style="color: #0047AB; font-weight: 500;">Seller Agreement</a>. 
                        I confirm that I am authorized to sell the products listed on DEJOIY.
                    </label>
                </div>
                
                <button type="submit" 
                        class="dejoiy-btn dejoiy-btn-primary dejoiy-btn-full" 
                        style="margin-bottom: 16px;">
                    Create Seller Account
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </button>
            </form>
            
            <p style="text-align: center; font-size: 13px; color: #64748B;">
                Already have an account? 
                <a href="/wp-login.php" style="color: #0047AB; font-weight: 600;">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>
