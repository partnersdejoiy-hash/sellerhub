import { useState } from 'react'
import { Link } from 'react-router-dom'
import { api, Product } from '../api'
import { Empty, ErrorBox, Loading, money, useAsync, useDebounced, useToast } from '../ui'

export default function Inventory() {
  const [filter, setFilter] = useState<'all' | 'low' | 'out'>('all')
  const [search, setSearch] = useState('')
  const q = useDebounced(search, 400)
  const toast = useToast()

  const { data, loading, error, reload } = useAsync(
    () => api.products({ stock: filter === 'all' ? '' : filter, search: q, per_page: 40 }),
    [filter, q],
  )

  async function adjust(p: Product, delta: number) {
    try {
      await api.productStock(p.id, delta, true)
      toast.show(`${p.name}: stock ${delta > 0 ? '+' : ''}${delta}`)
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    }
  }

  const items = (data?.items || []).filter((p) => p.managingStock)

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Inventory</h1>
          <p>Adjust stock in one tap. Low-stock alerts appear on your dashboard automatically.</p>
        </div>
        <div className="page-actions">
          <button className="btn" onClick={() => window.open('/wp-admin/edit.php?post_type=product&page=product_importer', '_blank')}>⬆ Import CSV</button>
        </div>
      </div>

      <div className="card">
        <div className="card-head">
          <div className="pill-row">
            {(['all', 'low', 'out'] as const).map((f) => (
              <button key={f} className={'pill' + (filter === f ? ' active' : '')} onClick={() => setFilter(f)}>
                {f === 'all' ? 'All tracked' : f === 'low' ? '⚠ Low stock' : '🚫 Out of stock'}
              </button>
            ))}
          </div>
          <input className="input" style={{ maxWidth: 240 }} placeholder="Search SKU / name…" value={search} onChange={(e) => setSearch(e.target.value)} />
        </div>

        {loading && !data ? (
          <Loading />
        ) : error ? (
          <ErrorBox message={error} retry={reload} />
        ) : items.length === 0 ? (
          <Empty art="📋" title="Nothing to restock" body={filter === 'all' ? 'No stock-tracked products yet. Enable stock management when creating a product.' : 'No products in this state — great job keeping inventory healthy!'} action={<Link to="/products/new" className="btn btn-primary">+ Add Product</Link>} />
        ) : (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>SKU</th>
                  <th style={{ textAlign: 'right' }}>Available</th>
                  <th>Status</th>
                  <th style={{ textAlign: 'right' }}>Value</th>
                  <th style={{ textAlign: 'right' }}>Quick adjust</th>
                </tr>
              </thead>
              <tbody>
                {items.map((p) => (
                  <tr key={p.id}>
                    <td data-label="Product">
                      <div className="cell-product">
                        {p.image ? <img src={p.image} alt="" /> : <div style={{ width: 40, height: 40, borderRadius: 8, background: 'var(--ink-100)', flexShrink: 0 }} />}
                        <div>
                          <Link to={`/products/${p.id}`} style={{ color: 'inherit', fontWeight: 600 }}>{p.name}</Link>
                          <div className="sub">{p.type === 'variable' ? `${p.variations?.length || 0} variations` : money(p.price)}</div>
                        </div>
                      </div>
                    </td>
                    <td data-label="SKU" style={{ fontFamily: 'var(--mono)', fontSize: 12 }}>{p.sku || '—'}</td>
                    <td data-label="Available" className="cell-num"><strong style={{ fontSize: 15 }}>{p.stock ?? 0}</strong></td>
                    <td data-label="Status">
                      {p.stock !== null && p.stock <= 0 ? <span className="badge badge-danger">Out</span> : p.stock !== null && p.stock <= 5 ? <span className="badge badge-warn">Low</span> : <span className="badge badge-ok">OK</span>}
                    </td>
                    <td data-label="Value" className="cell-num">{money((p.stock || 0) * p.price)}</td>
                    <td data-label="Adjust" className="cell-num">
                      <div style={{ display: 'inline-flex', gap: 6 }}>
                        <button className="btn btn-sm" onClick={() => adjust(p, -1)} aria-label={`Decrease ${p.name}`}>−1</button>
                        <button className="btn btn-sm" onClick={() => adjust(p, +1)} aria-label={`Increase ${p.name}`}>+1</button>
                        <button className="btn btn-sm" onClick={() => adjust(p, +10)} aria-label={`Add ten ${p.name}`}>+10</button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
      {toast.node}
    </div>
  )
}
