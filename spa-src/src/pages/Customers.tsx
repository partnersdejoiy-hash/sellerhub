import { api } from '../api'
import { Empty, ErrorBox, Loading, dateFmt, fullMoney, useAsync } from '../ui'

export default function Customers() {
  const { data, loading, error, reload } = useAsync(() => api.customers(1), [])

  if (loading && !data) return <Loading label="Loading customers…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Customers</h1>
          <p>{data.total} buyers from your orders — sorted by spend. Seller-owned data only.</p>
        </div>
      </div>

      <div className="card">
        {data.items.length === 0 ? (
          <Empty art="👥" title="No customers yet" body="Customer profiles build automatically as orders come in." />
        ) : (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Customer</th>
                  <th style={{ textAlign: 'right' }}>Orders</th>
                  <th style={{ textAlign: 'right' }}>Total spent</th>
                  <th>Last order</th>
                </tr>
              </thead>
              <tbody>
                {data.items.map((c) => (
                  <tr key={c.email}>
                    <td data-label="Customer">
                      <div style={{ fontWeight: 650 }}>{c.name || 'Guest'}</div>
                      <div style={{ fontSize: 12, color: 'var(--ink-400)' }}>{c.email}</div>
                    </td>
                    <td data-label="Orders" className="cell-num">{c.orders}</td>
                    <td data-label="Spent" className="cell-num"><strong>{fullMoney(c.spent)}</strong></td>
                    <td data-label="Last order" style={{ color: 'var(--ink-500)', fontSize: 12.5 }}>{dateFmt(c.lastOrder)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  )
}
