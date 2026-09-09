# DEJOIY Seller Hub / Seller App

Independent, world-class **Seller Operating System** for the DEJOIY marketplace (dejoiy.com).

WooCommerce + WCFM remain the backend marketplace engine — but sellers **never** need WordPress admin or the WCFM dashboard. Every seller operation happens inside this app.

## 🚀 Live App

**URL:** https://dejoiy.com/seller-app/

Login with any seller (WCFM vendor) account. The app is served by the `dejoiy-seller-app` WordPress plugin at `/seller-app/`.

## 📦 Repository Layout

```
dejoiy-seller-app/   # WordPress plugin (PHP backend + built SPA)
├── dejoiy-seller-app.php   # Plugin bootstrap
├── includes/               # Service classes (auth, orders, products, analytics, finance…)
├── api/rest-api.php        # REST API: /wp-json/dsa/v1/*
└── app/                    # Built SPA (served at /seller-app/)

spa-src/              # SPA source code (React + TypeScript + Vite)
├── src/pages/        # 15 seller modules (Dashboard, Orders, Products, Finance, JOI AI…)
├── src/api.ts        # Typed API client
├── src/styles.css    # DEJOIY design system
└── …                 # Build with: npm install && npm run build
```

## 🧩 Modules

Dashboard (command center) · Analytics · Finance (WCFM ledger-aware) · Orders + Order Detail ·
Products + full Product Editor (variations, media, bulk) · Inventory OS · Customers · Reviews +
Reply · Marketing · Store Manager (branding/address/social) · Notifications · Global Search
(Ctrl+K) · JOI AI (data-grounded assistant) · Support · Settings.

## 🔐 Security Model

- Cookie-session auth (WordPress nonce) — no custom tokens, no passwords stored.
- **Vendor scoping enforced server-side**: vendors can only ever read/write their own
  products, orders, reviews and earnings. Role-based admin detection
  (`administrator` / `shop_manager`) — capability checks are not trusted because WCFM
  grants `manage_woocommerce` to vendors.
- Admins can pass `?vendor_id=` to inspect any store.

## 🛠 Development

```bash
# SPA
cd spa-src
npm install
npm run dev        # dev server
npm run build      # builds into ../dejoiy-seller-app/app/

# Deploy: zip dejoiy-seller-app/ → install via WP admin / WP-CLI
wp plugin install dejoiy-seller-app.zip --activate --allow-root
wp rewrite flush --allow-root
```

## 📋 WCFM Feature Parity

| WCFM Feature        | Seller App Module        | Backend                | Status |
| ------------------- | ------------------------ | ---------------------- | ------ |
| Dashboard           | Dashboard                | WC orders + analytics  | ✅     |
| Products CRUD       | Products + Editor        | WC CRUD (author-scoped)| ✅     |
| Variations          | Variations builder       | WC Product_Variation   | ✅     |
| Inventory / stock   | Inventory OS             | wc_update_product_stock| ✅     |
| Orders + status     | Orders OS                | WC orders (WCFM-scoped)| ✅     |
| Order notes         | Order detail timeline    | WC order notes         | ✅     |
| Customers           | Customers                | Order-derived          | ✅     |
| Reviews + reply     | Reviews                  | WP comments            | ✅     |
| Store settings      | Store Manager            | WCFM shop meta         | ✅     |
| Finance / ledger    | Finance                  | WCFM ledger (if live)  | ✅     |
| Coupons             | Marketing (honest state) | Needs WCFM config      | 🔜     |
| Shipping (AST)      | Order detail tracking    | AST plugin             | ✅     |
| Notifications       | Notification center      | Real store signals     | ✅     |
| Seller AI           | JOI AI                   | Grounded on real data  | ✅     |

WCFM requirement for normal sellers: **0** — sellers never open WCFM.

## 🏗 Architecture

```
SELLER UI (React SPA at /seller-app/)
   ↓  fetch + WP nonce
DEJOIY SELLER SERVICE LAYER (dsa/v1 REST API)
   ↓  DSA_Auth::resolve_vendor() — server-side scoping
WORDPRESS / WOOCOMMERCE CRUD + WCFM helpers
   ↓
DATABASE (Woo = source of truth)
```

No iframes. No WCFM pages. No duplicate UI.
