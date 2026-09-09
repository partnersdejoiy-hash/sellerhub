import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, Dashboard as DashboardData } from '../api'
import { AreaChart, Empty, ErrorBox, Loading, Stat, money, fullMoney, useAsync } from '../ui'

const RANGES = [
  { key: 'today', label: 'Today' },
  { key: '7d', label: '7 Days' },
  { key: '30d', label: '30 Days' },
  { key: '90d', label: '90 Days' },
  { key: '1y', label: 'This Year' },
]

export default function Dashboard() {
  const [range, setRange] = useState('30d')
  const { data, loading, error, reload } = useAsync(() => api.dashboard(range), [range])

  if (loading && !data) return <Loading label="Loading your command center…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  const growth = data.prevRevenue > 0 ? ((data.summary.revenue - data.prevRevenue) / data.prevRevenue) * 100 : null

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Dashboard</h1>
          <p>Your store at a glance — all numbers are live from your DEJOIY store.</p>
        </div>
        <div className="pill-row">
          {RANGES.map((r) => (
            <button key={r.key} className={'pill' + (range === r.key ? ' active' : '')} onClick={() => setRange(r.key)}>{r.label}</button>
          ))}
        </div>
      </div>

      {/* KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: 12, marginBottom: 18 }}>
        <Stat label="Revenue" value={money(data.summary.revenue)} delta={growth} />
        <Stat label="Orders" value={String(data.summary.orders)} />
        <Stat label="Items Sold" value={String(data.summary.itemsSold)} />
        <Stat label="Avg Order Value" value={money(data.summary.aov)} />
        <Stat label="Customers" value={String(data.summary.customers)} sub={data.summary.repeatCustomers ? `${data.summary.repeatCustomers} repeat` : undefined} />
      </div>

      {/* Needs attention */}
      {data.attention.length > 0 && (
        <div className="card" style={{ marginBottom: 18, borderLeft: '4px solid var(--accent-500)' }}>
          <div className="card-head"><h3>⚡ Needs Attention</h3><span className="hint">{data.attention.length} items</span></div>
          <div style={{ padding: '10px 12px', display: 'grid', gap: 8 }}>
            {data.attention.map((a) => (
              <Link key={a.key} to={a.link} className="palette-item" style={{ border: '1px solid var(--ink-100)' }}>
                <span className="ico">{a.key === 'processing' ? '📦' : a.key === 'out' ? '🚫' : a.key === 'low' ? '📉' : '⭐'}</span>
                <span style={{ flex: 1, fontWeight: 600 }}>{a.label}</span>
                <span className="badge badge-brand">Fix →</span>
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Sales chart */}
      <div className="card" style={{ marginBottom: 18 }}>
        <div className="card-head">
          <h3>Sales Analytics</h3>
          <span className="hint">{RANGES.find((r) => r.key === range)?.label} · revenue {money(data.summary.revenue)}</span>
        </div>
        <div className="card-pad">
          {data.series.every((s) => s.revenue === 0) ? (
            <Empty art="🌱" title="No sales in this period" body="Publish more products and share your store link to get your first orders rolling." />
          ) : (
            <AreaChart data={data.series} />
          )}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
        {/* Best sellers */}
        <div className="card">
          <div className="card-head"><h3>🏆 Best Sellers</h3><Link to="/products" className="hint">View products →</Link></div>
          {data.bestSellers.length === 0 ? (
            <Empty art="📦" title="No sales yet" body="Your best sellers will appear here once orders start." />
          ) : (
            <div style={{ padding: 8 }}>
              {data.bestSellers.slice(0, 6).map((b, i) => (
                <div key={b.id} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '10px 12px', borderRadius: 10 }}>
                  <strong style={{ width: 18, color: 'var(--ink-300)' }}>{i + 1}</strong>
                  {b.image ? <img src={b.image} alt="" style={{ width: 38, height: 38, borderRadius: 8, objectFit: 'cover' }} /> : <div style={{ width: 38, height: 38, borderRadius: 8, background: 'var(--ink-100)' }} />}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, fontSize: 13, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{b.name}</div>
                    <div style={{ fontSize: 11.5, color: 'var(--ink-400)' }}>{b.qty} sold · {money(b.revenue)}</div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Inventory health + quick actions */}
        <div style={{ display: 'grid', gap: 18, alignContent: 'start' }}>
          <div className="card">
            <div className="card-head"><h3>📋 Inventory Health</h3><Link to="/inventory" className="hint">Manage →</Link></div>
            <div className="card-pad" style={{ display: 'grid', gap: 14 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                <span style={{ color: 'var(--ink-500)' }}>Total products</span><strong>{data.inventory.totalProducts}</strong>
              </div>
              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12.5, marginBottom: 6 }}>
                  <span style={{ color: 'var(--ink-500)' }}>Stock value</span><strong>{fullMoney(data.inventory.stockValue)}</strong>
                </div>
                <div className="progress"><div style={{ width: '100%' }} /></div>
              </div>
              <div style={{ display: 'flex', gap: 10 }}>
                <span className="badge badge-warn">⚠ {data.inventory.lowStock} low</span>
                <span className="badge badge-danger">🚫 {data.inventory.outOfStock} out</span>
              </div>
            </div>
          </div>

          <div className="card">
            <div className="card-head"><h3>⚡ Quick Actions</h3></div>
            <div className="card-pad" style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
              <Link to="/products/new" className="btn btn-primary btn-sm">+ Add Product</Link>
              <Link to="/orders?status=processing" className="btn btn-sm">Process Orders</Link>
              <Link to="/store" className="btn btn-sm">Edit Store</Link>
              <Link to="/joi" className="btn btn-sm">Ask JOI</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
