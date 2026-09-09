import { useState } from 'react'
import { Empty } from '../ui'

const FAQS = [
  ['How do I add my first product?', 'Open Products → Add Product. Fill the name, price and upload one image — you can publish and improve it later. Your product goes live after marketplace review.'],
  ['When do I get paid?', 'Earnings appear in Finance. Payout cycles depend on the marketplace commission settings; the WCFM ledger shows your withdrawable balance once configured.'],
  ['How do I ship an order?', 'Open the order, verify items and address, then mark it Processing → Completed once shipped. Tracking numbers added in WooCommerce appear automatically.'],
  ['What does the low stock alert mean?', 'Your product reached the low-stock threshold (default 5). Restock before it hits zero to keep your search ranking.'],
  ['Can I edit a published product?', 'Yes — open it from Products and press Update. Changes go live immediately.'],
  ['How is my seller score calculated?', 'On-time fulfillment, low cancellation rate, review responses and stock health all contribute. Keep them green and the score follows.'],
]

export default function Support() {
  const [open, setOpen] = useState<number | null>(0)

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Support</h1>
          <p>Seller University — guides, answers and help.</p>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(min(320px, 100%), 1fr))', gap: 18 }}>
        <div className="card">
          <div className="card-head"><h3>📚 Frequently asked</h3></div>
          <div style={{ padding: '8px 16px 16px' }}>
            {FAQS.map(([q, a], i) => (
              <div key={i} style={{ borderBottom: '1px solid var(--ink-100)', padding: '10px 0' }}>
                <button style={{ width: '100%', textAlign: 'left', background: 'none', border: 'none', fontWeight: 650, fontSize: 13.5, cursor: 'pointer', display: 'flex', justifyContent: 'space-between', gap: 10, padding: '4px 0' }} onClick={() => setOpen(open === i ? null : i)}>
                  {q} <span style={{ color: 'var(--ink-400)' }}>{open === i ? '−' : '+'}</span>
                </button>
                {open === i && <p style={{ fontSize: 13, color: 'var(--ink-500)', marginTop: 6 }}>{a}</p>}
              </div>
            ))}
          </div>
        </div>

        <div style={{ display: 'grid', gap: 18, alignContent: 'start' }}>
          <div className="card">
            <div className="card-head"><h3>🛟 Contact DEJOIY</h3></div>
            <div className="card-pad" style={{ display: 'grid', gap: 12, fontSize: 13.5 }}>
              <p style={{ color: 'var(--ink-500)' }}>Need a human? Reach the marketplace team:</p>
              <a className="btn" href="mailto:support@dejoiy.com">✉ support@dejoiy.com</a>
              <a className="btn" href="https://dejoiy.com/contact" target="_blank" rel="noreferrer">🌐 dejoiy.com/contact</a>
            </div>
          </div>

          <div className="card">
            <div className="card-head"><h3>🎓 Seller University</h3></div>
            <Empty
              art="🎓"
              title="Courses launching soon"
              body="Structured seller courses — photography, pricing, ads — are being produced for the DEJOIY seller community."
            />
          </div>
        </div>
      </div>
    </div>
  )
}
