import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { api, Product, BulkResult } from '../api'
import { Empty, ErrorBox, Loading, PRODUCT_STATUS, StockBadge, useToast, useAsync, useDebounced, dateFmt, money } from '../ui'

const STATUS_TABS = [
  { key: 'all', label: 'All' },
  { key: 'published', label: 'Published' },
  { key: 'draft', label: 'Drafts' },
  { key: 'pending', label: 'In Review' },
  { key: 'trash', label: 'Trash' },
]

export default function Products() {
  const [params, setParams] = useSearchParams()
  const status = params.get('status') || 'all'
  const stock = params.get('stock') || ''
  const search = params.get('q') || ''
  const page = parseInt(params.get('page') || '1', 10)
  const [selected, setSelected] = useState<Set<number>>(new Set())
  const [bulkBusy, setBulkBusy] = useState(false)
  const toast = useToast()
  const navigate = useNavigate()
  const debouncedSearch = useDebounced(search, 400)

  const { data, loading, error, reload } = useAsync(
    () => api.products({ status, stock, search: debouncedSearch, page, per_page: 20 }),
    [status, stock, debouncedSearch, page],
  )

  function setParam(key: string, value: string) {
    const next = new URLSearchParams(params)
    if (value) next.set(key, value)
    else next.delete(key)
    if (key !== 'page') next.delete('page')
    setParams(next)
  }

  function toggle(id: number) {
    const next = new Set(selected)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setSelected(next)
  }

  async function bulk(action: string, value?: Record<string, unknown>) {
    if (!selected.size) return
    if (action === 'delete' && !confirm(`Delete ${selected.size} product(s) permanently?`)) return
    setBulkBusy(true)
    try {
      const res: BulkResult = await api.bulkProducts(action, [...selected], value || {})
      toast.show(`${res.updated} product(s) updated`)
      setSelected(new Set())
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBulkBusy(false)
    }
  }

  const items = data?.items || []
  const totalPages = data ? Math.max(1, Math.ceil(data.total / 20)) : 1

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Products</h1>
          <p>{data ? `${data.total} products in your catalog` : 'Loading catalog…'}</p>
        </div>
        <div className="page-actions">
          <button className="btn" onClick={() => toast.show('CSV import coming from WordPress → Products → Import', false)}>⬆ Import</button>
          <Link to="/products/new" className="btn btn-primary">+ Add Product</Link>
        </div>
      </div>

      <div className="card">
        <div className="card-head">
          <div className="pill-row">
            {STATUS_TABS.map((t) => (
              <button key={t.key} className={'pill' + (status === t.key ? ' active' : '')} onClick={() => setParam('status', t.key === 'all' ? '' : t.key)}>{t.label}</button>
            ))}
            <button className={'pill' + (stock === 'low' ? ' active' : '')} onClick={() => setParam('stock', stock === 'low' ? '' : 'low')}>⚠ Low Stock</button>
            <button className={'pill' + (stock === 'out' ? ' active' : '')} onClick={() => setParam('stock', stock === 'out' ? '' : 'out')}>🚫 Out of Stock</button>
          </div>
          <input className="input" style={{ maxWidth: 260 }} placeholder="Search products…" value={search} onChange={(e) => setParam('q', e.target.value)} />
        </div>

        {loading && !data ? (
          <Loading />
        ) : error ? (
          <ErrorBox message={error} retry={reload} />
        ) : items.length === 0 ? (
          <Empty
            art="🏷️"
            title={search ? 'No products match your search' : 'No products yet'}
            body={search ? 'Try a different keyword or clear filters.' : 'Your first product can start your DEJOIY journey. Create it in under 2 minutes.'}
            action={<Link to="/products/new" className="btn btn-primary">+ Add Product</Link>}
          />
        ) : (
          <>
            {selected.size > 0 && (
              <div style={{ padding: '10px 16px', background: 'var(--brand-50)', display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', borderBottom: '1px solid var(--ink-100)' }}>
                <strong style={{ fontSize: 12.5 }}>{selected.size} selected</strong>
                <button className="btn btn-sm" disabled={bulkBusy} onClick={() => bulk('publish')}>Publish</button>
                <button className="btn btn-sm" disabled={bulkBusy} onClick={() => bulk('draft')}>→ Draft</button>
                <button className="btn btn-sm" disabled={bulkBusy} onClick={() => bulk('trash')}>Trash</button>
                <button className="btn btn-sm btn-danger" disabled={bulkBusy} onClick={() => bulk('delete')}>Delete</button>
                <button className="btn btn-sm btn-ghost" onClick={() => setSelected(new Set())}>Clear</button>
              </div>
            )}
            <div className="table-wrap">
              <table className="table">
                <thead>
                  <tr>
                    <th style={{ width: 36 }}><input type="checkbox" checked={selected.size === items.length && items.length > 0} onChange={(e) => setSelected(e.target.checked ? new Set(items.map((i) => i.id)) : new Set())} /></th>
                    <th>Product</th>
                    <th data-label="Status">Status</th>
                    <th data-label="Price" style={{ textAlign: 'right' }}>Price</th>
                    <th data-label="Stock">Stock</th>
                    <th data-label="Sales" style={{ textAlign: 'right' }}>Sales</th>
                    <th data-label="Modified">Modified</th>
                  </tr>
                </thead>
                <tbody>
                  {items.map((p) => (
                    <ProductRow key={p.id} p={p} selected={selected.has(p.id)} onToggle={() => toggle(p.id)} onSaved={reload} toast={toast} />
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
      {toast.node}
    </div>
  )
}

function ProductRow({ p, selected, onToggle, onSaved, toast }: {
  p: Product; selected: boolean; onToggle: () => void; onSaved: () => void; toast: { show: (m: string, e?: boolean) => void }
}) {
  const [stockVal, setStockVal] = useState(p.stock !== null && p.stock !== undefined ? String(p.stock) : '')
  const st = PRODUCT_STATUS[p.status] || { label: p.status, cls: 'badge-muted' }

  async function saveStock() {
    const n = parseInt(stockVal, 10)
    if (isNaN(n) || n === p.stock) return
    try {
      await api.productStock(p.id, n)
      toast.show(`Stock updated to ${n}`)
      onSaved()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    }
  }

  return (
    <tr>
      <td><input type="checkbox" checked={selected} onChange={onToggle} /></td>
      <td>
        <div className="cell-product">
          {p.image ? <img src={p.image} alt="" /> : <div style={{ width: 40, height: 40, borderRadius: 8, background: 'var(--ink-100)', flexShrink: 0 }} />}
          <div style={{ minWidth: 0 }}>
            <Link to={`/products/${p.id}`} className="name" style={{ color: 'inherit' }}>{p.name}</Link>
            <div className="sub">{p.sku ? 'SKU ' + p.sku : 'No SKU'} · {p.type}</div>
          </div>
        </div>
      </td>
      <td data-label="Status"><span className={'badge ' + st.cls}>{st.label}</span></td>
      <td data-label="Price" className="cell-num">
        <strong>{money(p.salePrice ?? p.price)}</strong>
        {p.salePrice !== null && p.salePrice < p.regularPrice && <div style={{ fontSize: 11, color: 'var(--ink-400)' }}><s>{money(p.regularPrice)}</s></div>}
      </td>
      <td data-label="Stock">
        {p.managingStock && p.stock !== null ? (
          <input className="input" style={{ width: 84, padding: '5px 9px' }} value={stockVal} onChange={(e) => setStockVal(e.target.value)} onBlur={saveStock} onKeyDown={(e) => e.key === 'Enter' && saveStock()} aria-label={`Stock for ${p.name}`} />
        ) : (
          <StockBadge stock={p.stock} status={p.stockStatus} managing={p.managingStock} />
        )}
      </td>
      <td data-label="Sales" className="cell-num">{p.sales}</td>
      <td data-label="Modified" style={{ whiteSpace: 'nowrap', color: 'var(--ink-400)', fontSize: 12.5 }}>{dateFmt(p.dateModified)}</td>
    </tr>
  )
}
