import { useState } from 'react'
import { api, Dashboard as AnalyticsData } from '../api'
import { AreaChart, Empty, ErrorBox, Loading, Stat, money, useAsync } from '../ui'

const RANGES = [
  { key: '7d', label: '7 Days' },
  { key: '30d', label: '30 Days' },
  { key: '90d', label: '90 Days' },
  { key: '1y', label: 'This Year' },
]

export default function Analytics() {
  const [range, setRange] = useState('30d')
  const { data, loading, error, reload } = useAsync(() => api.dashboard(range), [range])

  if (loading && !data) return <Loading label="Crunching analytics…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  const growth = data.prevRevenue > 0 ? ((data.summary.revenue - data.prevRevenue) / data.prevRevenue) * 100 : null

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Analytics</h1>
          <p>Every number comes from your real DEJOIY orders — no estimates.</p>
        </div>
        <div className="pill-row">
          {RANGES.map((r) => (
            <button key={r.key} className={'pill' + (range === r.key ? ' active' : '')} onClick={() => setRange(r.key)}>{r.label}</button>
          ))}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: 12, marginBottom: 18 }}>
        <Stat label="Revenue" value={money(data.summary.revenue)} delta={growth} />
        <Stat label="Orders" value={String(data.summary.orders)} />
        <Stat label="Items sold" value={String(data.summary.itemsSold)} />
        <Stat label="AOV" value={money(data.summary.aov)} />
        <Stat label="Refunded" value={money(data.summary.refunded)} />
        <Stat label="Repeat customers" value={String(data.summary.repeatCustomers)} />
      </div>

      <div className="card" style={{ marginBottom: 18 }}>
        <div className="card-head"><h3>Revenue trend</h3><span className="hint">{RANGES.find((r) => r.key === range)?.label}</span></div>
        <div className="card-pad">
          {data.series.every((s) => s.revenue === 0) ? <Empty art="📈" title="Flat line for now" body="Sales data will chart here as orders arrive." /> : <AreaChart data={data.series} />}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
        <div className="card">
          <div className="card-head"><h3>🏆 Top performers</h3></div>
          {data.bestSellers.length === 0 ? <Empty art="🏆" title="No sales yet" body="Best sellers are ranked by units sold." /> : (
            <div className="table-wrap">
              <table className="table">
                <thead><tr><th>Product</th><th style={{ textAlign: 'right' }}>Units</th><th style={{ textAlign: 'right' }}>Revenue</th></tr></thead>
                <tbody>
                  {data.bestSellers.map((b) => (
                    <tr key={b.id}>
                      <td data-label="Product">{b.name}</td>
                      <td data-label="Units" className="cell-num">{b.qty}</td>
                      <td data-label="Revenue" className="cell-num">{money(b.revenue)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>

        <div className="card">
          <div className="card-head"><h3>👥 Customer insight</h3></div>
          <div className="card-pad" style={{ display: 'grid', gap: 14, fontSize: 13.5 }}>
            <Row l="Unique customers" v={String(data.summary.customers)} />
            <Row l="Repeat customers" v={String(data.summary.repeatCustomers)} />
            <Row l="Refund amount" v={money(data.summary.refunded)} />
            <Row l="Avg order value" v={money(data.summary.aov)} />
            <p className="help" style={{ margin: 0 }}>
              Traffic and conversion tracking activate when Google Site Kit / analytics data is linked to your store.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}

function Row({ l, v }: { l: string; v: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
      <span style={{ color: 'var(--ink-500)' }}>{l}</span><strong>{v}</strong>
    </div>
  )
}
