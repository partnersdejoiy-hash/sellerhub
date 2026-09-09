import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { api, Order } from '../api'
import { Empty, ErrorBox, Loading, ORDER_STATUS, dateTimeFmt, money, useAsync } from '../ui'

const TABS = [
  { key: 'all', label: 'All' },
  { key: 'processing', label: 'To Process' },
  { key: 'completed', label: 'Completed' },
  { key: 'on-hold', label: 'On Hold' },
  { key: 'cancelled', label: 'Cancelled' },
  { key: 'refunded', label: 'Refunded' },
]

export default function Orders() {
  const [params, setParams] = useSearchParams()
  const status = params.get('status') || 'all'
  const search = params.get('q') || ''
  const page = parseInt(params.get('page') || '1', 10)

  const { data: counts } = useAsync(() => api.orderCounts(), [])
  const { data, loading, error, reload } = useAsync(
    () => api.orders({ status: status === 'all' ? '' : status, search, page, per_page: 20 }),
    [status, search, page],
  )

  function setParam(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  const items = data?.items || []
  const totalPages = data ? Math.max(1, Math.ceil(data.total / 20)) : 1

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Orders</h1>
          <p>{data ? `${data.total} orders` : 'Loading…'} · fulfill on time to grow your seller score</p>
        </div>
      </div>

      <div className="card">
        <div className="card-head">
          <div className="pill-row">
            {TABS.map((t) => (
              <button key={t.key} className={'pill' + (status === t.key ? ' active' : '')} onClick={() => setParam('status', t.key === 'all' ? '' : t.key)}>
                {t.label}
                {counts && t.key !== 'all' && counts[t.key] ? <span className="cnt">{counts[t.key]}</span> : ''}
              </button>
            ))}
          </div>
          <input className="input" style={{ maxWidth: 260 }} placeholder="Search #order, email…" value={search} onChange={(e) => setParam('q', e.target.value)} />
        </div>

        {loading && !data ? (
          <Loading />
        ) : error ? (
          <ErrorBox message={error} retry={reload} />
        ) : items.length === 0 ? (
          <Empty art="📦" title="No orders here" body={'Orders will appear in this view as customers buy from you.'} />
        ) : (
          <>
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th>Order</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th style={{ textAlign: 'right' }}>Total</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((o) => (
                    <tr key={o.id}>
                      <td data-label="Order"><Link to={`/orders/${o.id}`}><strong>#{o.number}</strong></Link></td>
                      <td data-label="Date" style={{ whiteSpace: 'nowrap', color: 'var(--ink-500)', fontSize: 12.5 }}>{dateTimeFmt(o.date)}</td>
                      <td data-label="Customer">
                        <div style={{ fontWeight: 600 }}>{o.customer.name || 'Guest'}</div>
                        <div style={{ fontSize: 11.5, color: 'var(--ink-400)' }}>{o.customer.email}</div>
                      </td>
                      <td data-label="Items">
                        {o.items.slice(0, 2).map((i, idx) => (
                          <div key={idx} style={{ fontSize: 12.5 }}>{i.qty}× {i.name.length > 26 ? i.name.slice(0, 26) + '…' : i.name}</div>
                        ))}
                        {o.items.length > 2 && <div style={{ fontSize: 11.5, color: 'var(--ink-400)' }}>+{o.items.length - 2} more</div>}
                      </td>
                      <td data-label="Total" className="cell-num"><strong>{money(o.total, o.currency)}</strong></td>
                      <td data-label="Status">{(() => { const s = ORDER_STATUS[o.status] || { label: o.status, cls: 'badge-muted' }; return <span className={'badge ' + s.cls}>{s.label}</span> })()}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {totalPages > 1 && (
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 16px', borderTop: '1px solid var(--ink-100)' }}>
                <span style={{ fontSize: 12.5, color: 'var(--ink-400)' }}>Page {page} of {totalPages}</span>
                <div style={{ display: 'flex', gap: 8 }}>
                  <button className="btn btn-sm" disabled={page <= 1} onClick={() => setParam('page', String(page - 1))}>← Prev</button>
                  <button className="btn btn-sm" disabled={page >= totalPages} onClick={() => setParam('page', String(page + 1))}>Next →</button>
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  )
}
