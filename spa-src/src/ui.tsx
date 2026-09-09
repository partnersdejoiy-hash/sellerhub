import { useEffect, useState } from 'react'

// ── Formatting helpers ──
export function money(n: number | null | undefined, currency = 'INR'): string {
  if (n === null || n === undefined) return '—'
  const symbol = currency === 'INR' ? '₹' : currency === 'USD' ? '$' : currency + ' '
  const v = Number(n) || 0
  if (Math.abs(v) >= 100000) return symbol + (v / 100000).toFixed(2) + 'L'
  if (Math.abs(v) >= 1000) return symbol + (v / 1000).toFixed(1) + 'k'
  return symbol + v.toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

export function fullMoney(n: number | null | undefined): string {
  if (n === null || n === undefined) return '—'
  return '₹' + (Number(n) || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })
}

export function dateFmt(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function dateTimeFmt(iso: string): string {
  if (!iso) return '—'
  const d = new Date(iso)
  if (isNaN(d.getTime())) return iso
  return d.toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}

// ── Status maps ──
export const ORDER_STATUS: Record<string, { label: string; cls: string }> = {
  pending: { label: 'Pending', cls: 'badge-warn' },
  processing: { label: 'Processing', cls: 'badge-info' },
  'on-hold': { label: 'On Hold', cls: 'badge-warn' },
  completed: { label: 'Completed', cls: 'badge-ok' },
  cancelled: { label: 'Cancelled', cls: 'badge-muted' },
  refunded: { label: 'Refunded', cls: 'badge-danger' },
  'partially-refunded': { label: 'Part Refunded', cls: 'badge-warn' },
  failed: { label: 'Failed', cls: 'badge-danger' },
}

export const PRODUCT_STATUS: Record<string, { label: string; cls: string }> = {
  publish: { label: 'Published', cls: 'badge-ok' },
  draft: { label: 'Draft', cls: 'badge-muted' },
  pending: { label: 'In Review', cls: 'badge-warn' },
  private: { label: 'Private', cls: 'badge-info' },
  trash: { label: 'Trash', cls: 'badge-danger' },
}

export function StockBadge({ stock, status, managing }: { stock: number | null; status: string; managing?: boolean }) {
  if (managing && stock !== null && stock !== undefined) {
    if (stock <= 0) return <span className="badge badge-danger">Out · 0</span>
    if (stock <= 5) return <span className="badge badge-warn">Low · {stock}</span>
    return <span className="badge badge-ok">{stock} in stock</span>
  }
  if (status === 'outofstock') return <span className="badge badge-danger">Out of stock</span>
  if (status === 'onbackorder') return <span className="badge badge-warn">Backorder</span>
  return <span className="badge badge-ok">In stock</span>
}

// ── Toast ──
let toastTimer: ReturnType<typeof setTimeout> | null = null
export function useToast() {
  const [toast, setToast] = useState<{ msg: string; err?: boolean } | null>(null)
  function show(msg: string, err = false) {
    if (toastTimer) clearTimeout(toastTimer)
    setToast({ msg, err })
    toastTimer = setTimeout(() => setToast(null), 3200)
  }
  const node = toast ? <div className={'toast' + (toast.err ? ' err' : '')}>{toast.err ? '⚠️' : '✓'} {toast.msg}</div> : null
  return { show, node }
}

// ── Empty state ──
export function Empty({ art, title, body, action }: { art: string; title: string; body: string; action?: React.ReactNode }) {
  return (
    <div className="empty">
      <div className="art" aria-hidden>{art}</div>
      <h3>{title}</h3>
      <p>{body}</p>
      {action}
    </div>
  )
}

export function Loading({ label = 'Loading…' }: { label?: string }) {
  return (
    <div className="empty" style={{ padding: '48px 20px' }}>
      <div className="spinner" />
      <p style={{ marginTop: 12, color: 'var(--ink-400)', fontSize: 13 }}>{label}</p>
    </div>
  )
}

export function ErrorBox({ message, retry }: { message: string; retry?: () => void }) {
  return (
    <div className="empty">
      <div className="art">⚠️</div>
      <h3>Something went wrong</h3>
      <p>{message}</p>
      {retry && <button className="btn" onClick={retry}>Try again</button>}
    </div>
  )
}

// ── Stat card ──
export function Stat({ label, value, delta, sub }: { label: string; value: string; delta?: number | null; sub?: string }) {
  return (
    <div className="stat">
      <div className="label">{label}</div>
      <div className="value">{value}</div>
      {delta !== null && delta !== undefined && isFinite(delta) && (
        <div className={'delta ' + (delta >= 0 ? 'up' : 'down')}>
          {delta >= 0 ? '▲' : '▼'} {Math.abs(Math.round(delta))}% vs prev period
        </div>
      )}
      {sub && <div className="delta" style={{ color: 'var(--ink-400)' }}>{sub}</div>}
    </div>
  )
}

// ── Area chart (pure SVG, no deps) ──
export function AreaChart({ data, height = 220 }: { data: { date: string; revenue: number; orders: number }[]; height?: number }) {
  if (!data.length) return null
  const W = 800
  const H = height
  const P = { t: 14, r: 10, b: 26, l: 46 }
  const max = Math.max(...data.map((d) => d.revenue), 1)
  const iw = W - P.l - P.r
  const ih = H - P.t - P.b
  const x = (i: number) => P.l + (iw * i) / Math.max(1, data.length - 1)
  const y = (v: number) => P.t + ih - (ih * v) / max
  const pts = data.map((d, i) => `${x(i)},${y(d.revenue)}`).join(' ')
  const area = `${P.l},${P.t + ih} ${pts} ${x(data.length - 1)},${P.t + ih}`
  const ticks = [0, 0.5, 1].map((f) => ({ v: max * f, y: y(max * f) }))
  const step = Math.ceil(data.length / 8)

  return (
    <svg className="chart-svg" viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Revenue chart">
      {ticks.map((t, i) => (
        <g key={i}>
          <line x1={P.l} y1={t.y} x2={W - P.r} y2={t.y} stroke="var(--ink-100)" strokeWidth="1" />
          <text x={P.l - 8} y={t.y + 4} textAnchor="end" fontSize="10" fill="var(--ink-400)">{money(t.v)}</text>
        </g>
      ))}
      <polygon points={area} fill="url(#dsaGrad)" opacity="0.9" />
      <polyline points={pts} fill="none" stroke="var(--brand-600)" strokeWidth="2.5" strokeLinejoin="round" strokeLinecap="round" />
      {data.map((d, i) =>
        i % step === 0 ? (
          <text key={i} x={x(i)} y={H - 8} textAnchor="middle" fontSize="10" fill="var(--ink-400)">
            {d.date.slice(8, 10)}/{d.date.slice(5, 7)}
          </text>
        ) : null,
      )}
      <defs>
        <linearGradient id="dsaGrad" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="var(--brand-500)" stopOpacity="0.35" />
          <stop offset="100%" stopColor="var(--brand-500)" stopOpacity="0.02" />
        </linearGradient>
      </defs>
    </svg>
  )
}

// ── Rating stars ──
export function Stars({ rating }: { rating: number }) {
  const full = Math.round(rating)
  return (
    <span className="stars" title={rating.toFixed(1)}>
      {'★'.repeat(full)}{'☆'.repeat(5 - full)}
    </span>
  )
}

// ── Debounce hook ──
export function useDebounced<T>(value: T, ms = 350): T {
  const [v, setV] = useState(value)
  useEffect(() => {
    const t = setTimeout(() => setV(value), ms)
    return () => clearTimeout(t)
  }, [value, ms])
  return v
}

// ── Async data hook ──
export function useAsync<T>(fn: () => Promise<T>, deps: unknown[] = []) {
  const [data, setData] = useState<T | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [tick, setTick] = useState(0)
  useEffect(() => {
    let alive = true
    setLoading(true)
    setError('')
    fn().then(
      (d) => { if (alive) { setData(d); setLoading(false) } },
      (e) => { if (alive) { setError(e?.message || 'Failed'); setLoading(false) } },
    )
    return () => { alive = false }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [...deps, tick])
  return { data, loading, error, reload: () => setTick((t) => t + 1) }
}
