import { Empty, useAsync } from '../ui'

export default function Marketing() {
  // Coupons require WooCommerce + WCFM backend config; show honest status.
  useAsync(async () => ({}), [])

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Marketing</h1>
          <p>Grow your store with offers and campaigns.</p>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 18 }}>
        <div className="card">
          <div className="card-head"><h3>🎯 Coupons & Offers</h3></div>
          <Empty
            art="🎟️"
            title="Coupon tools connect here"
            body="Coupon management activates as soon as your marketplace admin enables seller coupons. Until then, ask DEJOIY support to run a promotion for your products."
          />
        </div>

        <div className="card">
          <div className="card-head"><h3>📈 Growth playbook</h3></div>
          <div className="card-pad" style={{ display: 'grid', gap: 14, fontSize: 13.5 }}>
            {[
              ['📸', 'Complete every product', 'Listings with 3+ images and full descriptions convert up to 2× better.'],
              ['⚡', 'Keep stock healthy', 'Out-of-stock items lose ranking. Restock before you hit zero.'],
              ['⭐', 'Answer every review', 'Responded reviews push conversion and seller score up.'],
              ['📦', 'Ship same-day', 'Fast dispatch drives better reviews and repeat buyers.'],
              ['💰', 'Smart pricing', 'Check competitors; small bundle offers lift average order value.'],
            ].map(([ico, t, b]) => (
              <div key={t} style={{ display: 'flex', gap: 12 }}>
                <span style={{ fontSize: 18 }}>{ico}</span>
                <div>
                  <strong style={{ display: 'block' }}>{t}</strong>
                  <span style={{ color: 'var(--ink-500)', fontSize: 12.5 }}>{b}</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        <div className="card">
          <div className="card-head"><h3>📣 Advertising</h3></div>
          <Empty
            art="📣"
            title="Advertising integration not connected"
            body="When DEJOIY launches promoted listings, campaign creation, budgets and ROAS reporting will live here. This space will never show fake numbers."
          />
        </div>
      </div>
    </div>
  )
}
