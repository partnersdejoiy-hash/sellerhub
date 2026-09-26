<?php
/**
 * DSO Apps - Seller Tools, Integrations, Automation & Services for DEJOIY
 */
if (!defined('ABSPATH')) exit;

class DSO_Apps {

    public function render() {
        ?>
        <div class="dso-page dso-apps">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <span>Apps & Services</span>
                        <span>/</span>
                        <span>Directory</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY App Ecosystem & Integrations</h1>
                    <p class="dso-page-subtitle">Supercharge your selling operations with verified courier APIs, automation triggers, and expert services</p>
                </div>
            </div>

            <!-- Ecosystem Grid -->
            <div class="dso-grid-3">
                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-flex dso-justify-between dso-items-center dso-mb-3">
                            <div class="dso-app-icon" style="font-size: 32px;">🚚</div>
                            <span class="dso-badge dso-badge-green">Connected</span>
                        </div>
                        <h3>Shiprocket Automation</h3>
                        <p class="dso-text-muted">Automated AWB generation, automated pickup scheduling, and live tracking webhooks.</p>
                        <a href="?section=apps-integrations" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Configure API →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-flex dso-justify-between dso-items-center dso-mb-3">
                            <div class="dso-app-icon" style="font-size: 32px;">💬</div>
                            <span class="dso-badge dso-badge-primary">Recommended</span>
                        </div>
                        <h3>WhatsApp Order Bot</h3>
                        <p class="dso-text-muted">Send automated WhatsApp order confirmations, dispatch tracking links, and delivery alerts.</p>
                        <a href="?section=apps-integrations" class="dso-btn dso-btn-sm dso-btn-primary dso-mt-3">Connect WhatsApp →</a>
                    </div>
                </div>

                <div class="dso-card">
                    <div class="dso-card-body">
                        <div class="dso-flex dso-justify-between dso-items-center dso-mb-3">
                            <div class="dso-app-icon" style="font-size: 32px;">🧾</div>
                            <span class="dso-badge dso-badge-outline">Accounting</span>
                        </div>
                        <h3>Zoho & Tally Sync</h3>
                        <p class="dso-text-muted">Automatic sync of GST sales registers, input credit vouchers, and financial ledgers.</p>
                        <a href="?section=apps-integrations" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Connect Accounting →</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function integrations() {
        ?>
        <div class="dso-page dso-integrations">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=apps">Apps</a>
                        <span>/</span>
                        <span>Integrations</span>
                    </div>
                    <h1 class="dso-page-title">Third-Party API Integrations</h1>
                    <p class="dso-page-subtitle">Configure API credentials, webhook endpoints, and automated sync intervals</p>
                </div>
            </div>

            <div class="dso-card dso-mb-4">
                <div class="dso-card-header">
                    <h3 class="dso-card-title">Shiprocket Credentials</h3>
                    <span class="dso-badge dso-badge-green">Live Sync</span>
                </div>
                <div class="dso-card-body">
                    <form class="dso-form">
                        <div class="dso-form-row dso-grid-2">
                            <div class="dso-form-group">
                                <label class="dso-label">Shiprocket API Email</label>
                                <input type="email" class="dso-input" value="seller@dejoiy.com" />
                            </div>
                            <div class="dso-form-group">
                                <label class="dso-label">API Key / Token</label>
                                <input type="password" class="dso-input" value="••••••••••••••••••••••••" />
                            </div>
                        </div>
                        <button type="button" class="dso-btn dso-btn-primary">Save API Connection</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function automation() {
        ?>
        <div class="dso-page dso-automation">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=apps">Apps</a>
                        <span>/</span>
                        <span>Automation</span>
                    </div>
                    <h1 class="dso-page-title">Store Automation Rules</h1>
                    <p class="dso-page-subtitle">Configure smart background triggers to run repetitive store operations on autopilot</p>
                </div>
            </div>

            <div class="dso-card">
                <div class="dso-card-body dso-p-0">
                    <div class="dso-table-responsive">
                        <table class="dso-table">
                            <thead>
                                <tr>
                                    <th>Trigger Event</th>
                                    <th>Automated Action</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>New Prepaid Order Received</strong></td>
                                    <td>Auto-accept order and send instant WhatsApp confirmation</td>
                                    <td><span class="dso-badge dso-badge-green">Enabled</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Inventory Reaches Low Threshold (≤5)</strong></td>
                                    <td>Send priority email alert and push notification to store manager</td>
                                    <td><span class="dso-badge dso-badge-green">Enabled</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Order Marked Delivered</strong></td>
                                    <td>Request product review & feedback 48 hours post-delivery</td>
                                    <td><span class="dso-badge dso-badge-green">Enabled</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function services() {
        ?>
        <div class="dso-page dso-services">
            <div class="dso-page-header">
                <div>
                    <div class="dso-breadcrumb">
                        <a href="?section=dashboard">Dashboard</a>
                        <span>/</span>
                        <a href="?section=apps">Apps</a>
                        <span>/</span>
                        <span>Services</span>
                    </div>
                    <h1 class="dso-page-title">DEJOIY Professional Marketplace Services</h1>
                    <p class="dso-page-subtitle">Accelerate your brand growth with on-demand studio photography, cataloging, and GST compliance</p>
                </div>
            </div>

            <div class="dso-grid-3">
                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>📸 Studio Product Shoot</h3>
                        <p class="dso-text-muted">Professional white-background model & tabletop photography meeting DEJOIY marketplace guidelines.</p>
                        <a href="?section=support" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Request Shoot Quote →</a>
                    </div>
                </div>
                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>📑 Catalog & SEO Optimization</h3>
                        <p class="dso-text-muted">Expert keyword researchers optimize your titles, descriptions, and bullet points to achieve 95+ LQS.</p>
                        <a href="?section=support" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Book Cataloger →</a>
                    </div>
                </div>
                <div class="dso-card">
                    <div class="dso-card-body">
                        <h3>⚖️ GST & Tax Filing Support</h3>
                        <p class="dso-text-muted">Certified Chartered Accountants assist with TCS reconciliation and monthly GSTR-1/3B filing.</p>
                        <a href="?section=support" class="dso-btn dso-btn-sm dso-btn-outline dso-mt-3">Connect with CA →</a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
