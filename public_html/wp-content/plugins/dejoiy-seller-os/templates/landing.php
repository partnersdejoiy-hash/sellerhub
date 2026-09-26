<?php
/**
 * DEJOIY Seller Landing Page
 * Beautiful marketing page before authentication
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DEJOIY Seller Central — Start Selling Today</title>
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
<body>
    <!-- Navigation -->
    <nav class="dejoiy-nav">
        <a href="/" class="dejoiy-nav-logo">
            <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY">
        </a>
        <div class="dejoiy-nav-links">
            <a href="#features" class="dejoiy-nav-link">Why Sell</a>
            <a href="#how-it-works" class="dejoiy-nav-link">How It Works</a>
            <a href="#tools" class="dejoiy-nav-link">Features</a>
            <a href="#support" class="dejoiy-nav-link">Support</a>
        </div>
        <div class="dejoiy-nav-actions">
            <a href="/wp-login.php" class="dejoiy-btn dejoiy-btn-ghost">Sign In</a>
            <a href="/seller-register.php" class="dejoiy-btn dejoiy-btn-primary">Start Selling</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="dejoiy-hero">
        <div class="dejoiy-hero-content">
            <div class="dejoiy-hero-badge">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                India's Premium Marketplace for Brands
            </div>
            <h1 class="dejoiy-hero-title">
                Build Your Business on<br>
                <span class="highlight">DEJOIY Seller Central</span>
            </h1>
            <p class="dejoiy-hero-subtitle">
                Join thousands of ambitious sellers reaching millions of customers across India. 
                Powerful tools, seamless fulfillment, and instant growth opportunities.
            </p>
            <div class="dejoiy-hero-cta">
                <a href="/seller-register.php" class="dejoiy-btn dejoiy-btn-primary dejoiy-btn-lg">
                    Start Selling Free
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/>
                        <polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
                <a href="/wp-login.php" class="dejoiy-btn dejoiy-btn-secondary dejoiy-btn-lg">
                    Sign In to Dashboard
                </a>
            </div>
            <div class="dejoiy-hero-visual">
                <div class="dejoiy-hero-mockup">
                    <div class="dejoiy-hero-mockup-bar">
                        <span class="dejoiy-hero-mockup-dot"></span>
                        <span class="dejoiy-hero-mockup-dot"></span>
                        <span class="dejoiy-hero-mockup-dot"></span>
                    </div>
                    <div class="dejoiy-hero-mockup-body">
                        <div style="background: linear-gradient(135deg, #0047AB 0%, #007AFF 100%); border-radius: 12px; padding: 24px; color: white; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                                </svg>
                                <span style="font-weight: 600;">DEJOIY Seller Central</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: 700;">₹48,290</div>
                                    <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">Today's Sales</div>
                                </div>
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: 700;">127</div>
                                    <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">Orders</div>
                                </div>
                                <div style="background: rgba(255,255,255,0.15); padding: 12px; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 24px; font-weight: 700;">₹2.4L</div>
                                    <div style="font-size: 11px; opacity: 0.8; margin-top: 4px;">Revenue</div>
                                </div>
                            </div>
                            <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 12px;">
                                <div style="display: flex; justify-content: space-between; font-size: 12px; opacity: 0.9;">
                                    <span>📦 1,247 Products</span>
                                    <span>⭐ 4.8 Rating</span>
                                </div>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                            <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; background: white;">
                                <div style="font-size: 11px; color: #64748B; margin-bottom: 4px;">Best Seller</div>
                                <div style="font-weight: 600; color: #0F172A;">Premium Silk Saree</div>
                                <div style="font-size: 13px; color: #FF1493; font-weight: 600;">₹2,499</div>
                            </div>
                            <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 12px; background: white;">
                                <div style="font-size: 11px; color: #64748B; margin-bottom: 4px;">Trending</div>
                                <div style="font-weight: 600; color: #0F172A;">Handcrafted Jewelry</div>
                                <div style="font-size: 13px; color: #0047AB; font-weight: 600;">₹1,299</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Trust Bar -->
    <section style="padding: 32px 24px; background: white; border-bottom: 1px solid #E2E8F0;">
        <div style="max-width: 1200px; margin: 0 auto; text-align: center;">
            <p style="font-size: 13px; color: #64748B; margin-bottom: 20px; font-weight: 500;">TRUSTED BY TOP SELLERS ACROSS INDIA</p>
            <div style="display: flex; justify-content: center; gap: 48px; flex-wrap: wrap; align-items: center;">
                <span style="font-size: 18px; font-weight: 700; color: #94A3B8;">🏆</span>
                <span style="font-size: 18px; font-weight: 600; color: #94A3B8;">★ 4.9 Seller Rating</span>
                <span style="font-size: 18px; font-weight: 600; color: #94A3B8;">📦 50K+ Products</span>
                <span style="font-size: 18px; font-weight: 600; color: #94A3B8;">🚀 1M+ Customers</span>
                <span style="font-size: 18px; font-weight: 600; color: #94A3B8;">🇮🇳 Pan-India Delivery</span>
            </div>
        </div>
    </section>

    <!-- Why Sell Section -->
    <section class="dejoiy-section" id="features">
        <div class="dejoiy-section-header">
            <div class="dejoiy-section-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                Why DEJOIY
            </div>
            <h2 class="dejoiy-section-title">Everything You Need to Grow Your Business</h2>
            <p class="dejoiy-section-subtitle">
                From listing your first product to scaling into a brand — DEJOIY Seller Central gives you the tools, reach, and support to succeed.
            </p>
        </div>
        <div class="dejoiy-features-grid">
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-1">
                <div class="dejoiy-feature-icon">🚀</div>
                <h3>Instant Product Listings</h3>
                <p>Create DPIN-powered product listings in minutes. Rich media support, variants, and smart categorization.</p>
            </div>
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-2">
                <div class="dejoiy-feature-icon">📦</div>
                <h3>Pan-India Fulfillment</h3>
                <p>Leverage our logistics network for fast, reliable delivery across 19,000+ pin codes. COD, prepaid, and express options.</p>
            </div>
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-3">
                <div class="dejoiy-feature-icon">💰</div>
                <h3>Fast Payouts</h3>
                <p>RBI-compliant settlements with transparent fee structure. Track earnings in real-time and withdraw instantly.</p>
            </div>
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-4">
                <div class="dejoiy-feature-icon">📊</div>
                <h3>Smart Analytics</h3>
                <p>Understand your customers, track performance, and make data-driven decisions with powerful dashboards.</p>
            </div>
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-5">
                <div class="dejoiy-feature-icon">🎯</div>
                <h3>Growth Tools</h3>
                <p>Promotions, deals, advertising, and AI-powered recommendations to boost your visibility and sales.</p>
            </div>
            <div class="dejoiy-feature-card dejoiy-animate-fade-in dejoiy-stagger-5">
                <div class="dejoiy-feature-icon">🛡️</div>
                <h3>Seller Protection</h3>
                <p>DPIN-protected listings, fraud prevention, and dedicated support to keep your business secure.</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section style="padding: 64px 24px; background: #F8FAFC;">
        <div style="max-width: 1200px; margin: 0 auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 32px; text-align: center;">
                <div>
                    <div style="font-size: 48px; font-weight: 800; color: #0047AB; line-height: 1;">₹500Cr+</div>
                    <div style="font-size: 14px; color: #64748B; margin-top: 8px;">Seller Earnings Powered</div>
                </div>
                <div>
                    <div style="font-size: 48px; font-weight: 800; color: #FF1493; line-height: 1;">50,000+</div>
                    <div style="font-size: 14px; color: #64748B; margin-top: 8px;">Active Sellers</div>
                </div>
                <div>
                    <div style="font-size: 48px; font-weight: 800; color: #007AFF; line-height: 1;">10M+</div>
                    <div style="font-size: 14px; color: #64748B; margin-top: 8px;">Orders Fulfilled</div>
                </div>
                <div>
                    <div style="font-size: 48px; font-weight: 800; color: #10B981; line-height: 1;">99.9%</div>
                    <div style="font-size: 14px; color: #64748B; margin-top: 8px;">Order Accuracy</div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="dejoiy-section" id="how-it-works">
        <div class="dejoiy-section-header">
            <div class="dejoiy-section-label">Simple Process</div>
            <h2 class="dejoiy-section-title">Start Selling in 3 Simple Steps</h2>
            <p class="dejoiy-section-subtitle">
                Get your store up and running quickly. No complex setup, no hidden fees.
            </p>
        </div>
        <div style="max-width: 1000px; margin: 0 auto;">
            <div style="display: flex; flex-direction: column; gap: 32px;">
                <div style="display: flex; gap: 20px; align-items: flex-start; padding: 24px; background: white; border: 1px solid #E2E8F0; border-radius: 12px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #FF1493 0%, #0047AB 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; flex-shrink: 0;">1</div>
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 8px;">Create Your Seller Account</h3>
                        <p style="color: #64748B; line-height: 1.6;">Sign up in minutes with your email or mobile number. Complete simple verification and get instant access to your seller dashboard.</p>
                    </div>
                </div>
                <div style="display: flex; gap: 20px; align-items: flex-start; padding: 24px; background: white; border: 1px solid #E2E8F0; border-radius: 12px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #FF1493 0%, #0047AB 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; flex-shrink: 0;">2</div>
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 8px;">Add Your Products</h3>
                        <p style="color: #64748B; line-height: 1.6;">List products with photos, descriptions, pricing, and inventory. Use bulk upload for large catalogs or add items one by one.</p>
                    </div>
                </div>
                <div style="display: flex; gap: 20px; align-items: flex-start; padding: 24px; background: white; border: 1px solid #E2E8F0; border-radius: 12px;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #FF1493 0%, #0047AB 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 18px; flex-shrink: 0;">3</div>
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 8px;">Start Selling & Growing</h3>
                        <p style="color: #64748B; line-height: 1.6;">Go live and start receiving orders. Use our tools to optimize listings, run promotions, and scale your business.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Seller Tools Section -->
    <section class="dejoiy-section" id="tools" style="background: #F8FAFC;">
        <div class="dejoiy-section-header">
            <div class="dejoiy-section-label">Powerful Tools</div>
            <h2 class="dejoiy-section-title">Everything a Modern Seller Needs</h2>
            <p class="dejoiy-section-subtitle">
                Professional-grade tools to manage every aspect of your business from one place.
            </p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; max-width: 1200px; margin: 0 auto;">
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">📦</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Product Catalog</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">Manage products, variants, inventory, and DPIN listings with ease.</p>
            </div>
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">🛒</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Order Management</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">Process orders, print invoices, manage returns, and track shipments.</p>
            </div>
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">💳</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Finance & Payouts</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">View earnings, manage payouts, track transactions, and plan finances.</p>
            </div>
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">📈</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Analytics & Reports</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">Deep insights into sales, customer behavior, and business growth.</p>
            </div>
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">🎯</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Marketing & Ads</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">Promotions, deals, coupons, and advertising to boost visibility.</p>
            </div>
            <div style="background: white; border: 1px solid #E2E8F0; border-radius: 12px; padding: 24px; transition: all 0.2s;">
                <div style="font-size: 32px; margin-bottom: 16px;">🤖</div>
                <h3 style="font-size: 16px; margin-bottom: 8px;">Seller AI Copilot</h3>
                <p style="font-size: 14px; color: #64748B; line-height: 1.6;">AI-powered insights, recommendations, and automation for smarter selling.</p>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="dejoiy-cta-section">
        <div class="dejoiy-cta-content">
            <h2 class="dejoiy-cta-title">Ready to Grow Your Business?</h2>
            <p class="dejoiy-cta-subtitle">
                Join thousands of sellers already growing on DEJOIY. Start selling today — it's free to get started.
            </p>
            <a href="/seller-register.php" class="dejoiy-btn dejoiy-cta-btn">
                Start Selling on DEJOIY
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                    <polyline points="12 5 19 12 12 19"/>
                </svg>
            </a>
            <p style="margin-top: 20px; font-size: 14px; color: rgba(255,255,255,0.7);">
                No credit card required • Free to list • Fast onboarding
            </p>
        </div>
    </section>

    <!-- Footer -->
    <footer class="dejoiy-footer" id="support">
        <div class="dejoiy-footer-content">
            <div class="dejoiy-footer-brand">
                <div class="dejoiy-footer-logo">
                    <img src="https://sellerhub.dejoiy.com/wp-content/uploads/2026/05/DEJOIY-OFFICIAL-LOGO-e1778929142857.png" alt="DEJOIY">
                </div>
                <p class="dejoiy-footer-desc">
                    India's premium marketplace for brands. Sell to millions of customers with powerful tools and fast fulfillment.
                </p>
                <div class="dejoiy-footer-social">
                    <a href="#" aria-label="Facebook">FB</a>
                    <a href="#" aria-label="Twitter">X</a>
                    <a href="#" aria-label="Instagram">IG</a>
                    <a href="#" aria-label="YouTube">YT</a>
                </div>
            </div>
            <div class="dejoiy-footer-col">
                <h4>Sell on DEJOIY</h4>
                <a href="/seller-register.php">Start Selling</a>
                <a href="/sellerhub/seller-hub.php">Seller Dashboard</a>
                <a href="#">Seller Policies</a>
                <a href="#">Seller University</a>
                <a href="#">Growth Resources</a>
            </div>
            <div class="dejoiy-footer-col">
                <h4>Support</h4>
                <a href="#">Help Center</a>
                <a href="#">Contact Support</a>
                <a href="#">Seller Community</a>
                <a href="#">Status Page</a>
                <a href="tel:1800-DEJOIY">1800-DEJOIY-HUB</a>
            </div>
            <div class="dejoiy-footer-col">
                <h4>Company</h4>
                <a href="https://dejoiy.com">DEJOIY.com</a>
                <a href="#">About Us</a>
                <a href="#">Careers</a>
                <a href="#">Press</a>
                <a href="#">Partners</a>
            </div>
        </div>
        <div class="dejoiy-footer-bottom">
            <div>© 2026 DEJOIY Marketplace Private Limited. All rights reserved.</div>
            <div style="display: flex; gap: 24px;">
                <a href="#" style="color: rgba(255,255,255,0.5); font-size: 13px;">Privacy Policy</a>
                <a href="#" style="color: rgba(255,255,255,0.5); font-size: 13px;">Terms of Service</a>
                <a href="#" style="color: rgba(255,255,255,0.5); font-size: 13px;">Seller Agreement</a>
            </div>
        </div>
    </footer>
</body>
</html>
