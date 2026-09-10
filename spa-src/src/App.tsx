import { useEffect, useMemo, useRef, useState } from 'react'
import { Routes, Route, NavLink, Link, useNavigate, useLocation } from 'react-router-dom'
import { api, appConfig, MeResponse, NotificationsResponse, Order } from './api'
import { Empty, Loading, useToast, money } from './ui'
import Dashboard from './pages/Dashboard'
import Products from './pages/Products'
import ProductEditor from './pages/ProductEditor'
import Inventory from './pages/Inventory'
import Orders from './pages/Orders'
import OrderDetail from './pages/OrderDetail'
import Customers from './pages/Customers'
import Finance from './pages/Finance'
import Analytics from './pages/Analytics'
import Reviews from './pages/Reviews'
import Marketing from './pages/Marketing'
import Store from './pages/Store'
import Settings from './pages/Settings'
import Support from './pages/Support'
import Joi from './pages/Joi'
import Onboarding from './pages/Onboarding'

const NAV: { section: string; items: { to: string; ico: string; label: string; badge?: 'orders' | 'notifications' }[] }[] = [
  {
    section: 'Overview',
    items: [
      { to: '/', ico: '◆', label: 'Dashboard' },
      { to: '/analytics', ico: '📈', label: 'Analytics' },
      { to: '/finance', ico: '💰', label: 'Finance' },
    ],
  },
  {
    section: 'Operations',
    items: [
      { to: '/orders', ico: '📦', label: 'Orders', badge: 'orders' },
      { to: '/products', ico: '🏷️', label: 'Products' },
      { to: '/inventory', ico: '📋', label: 'Inventory' },
      { to: '/customers', ico: '👥', label: 'Customers' },
    ],
  },
  {
    section: 'Growth',
    items: [
      { to: '/marketing', ico: '🎯', label: 'Marketing' },
      { to: '/reviews', ico: '⭐', label: 'Reviews' },
      { to: '/store', ico: '🏬', label: 'Store' },
      { to: '/joi', ico: '🤖', label: 'JOI AI' },
      { to: '/onboarding', ico: '🪪', label: 'Onboarding & KYC' },
    ],
  },
  {
    section: 'Account',
    items: [
      { to: '/support', ico: '🛟', label: 'Support' },
      { to: '/settings', ico: '⚙️', label: 'Settings' },
    ],
  },
]

const MOBILE_NAV = [
  { to: '/', ico: '◆', label: 'Home' },
  { to: '/orders', ico: '📦', label: 'Orders', badge: 'orders' as const },
  { to: '/products', ico: '🏷️', label: 'Products' },
  { to: '/finance', ico: '💰', label: 'Finance' },
  { to: '/more', ico: '⋯', label: 'More' },
]

export default function App() {
  const [me, setMe] = useState<MeResponse | null>(null)
  const [notif, setNotif] = useState<NotificationsResponse | null>(null)
  const [orderCount, setOrderCount] = useState(0)
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const [paletteOpen, setPaletteOpen] = useState(false)
  const [notifOpen, setNotifOpen] = useState(false)
  const [userOpen, setUserOpen] = useState(false)
  const toast = useToast()
  const navigate = useNavigate()
  const location = useLocation()
  const cfg = appConfig()

  useEffect(() => {
    api.me().then(setMe).catch(() => {})
    api.notifications().then(setNotif).catch(() => {})
    api.orderCounts().then((c) => setOrderCount((c.processing || 0) + (c.pending || 0))).catch(() => {})
  }, [])

  useEffect(() => {
    setSidebarOpen(false)
    window.scrollTo(0, 0)
  }, [location.pathname])

  // Cmd/Ctrl+K palette
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        setPaletteOpen((v) => !v)
      }
      if (e.key === 'Escape') { setPaletteOpen(false); setNotifOpen(false); setUserOpen(false); setSidebarOpen(false) }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const title = useMemo(() => {
    for (const s of NAV) for (const i of s.items) if (i.to === location.pathname) return i.label
    if (location.pathname.startsWith('/products/')) return 'Product Editor'
    if (location.pathname.startsWith('/orders/')) return 'Order Detail'
    if (location.pathname === '/more') return 'All Modules'
    return 'DEJOIY Seller App'
  }, [location.pathname])

  if (!cfg) {
    return <Empty art="🔒" title="App must be opened from DEJOIY" body="Open Seller App from your DEJOIY account menu: dejoiy.com/seller-app/" />
  }

  return (
    <div className="shell">
      {/* Sidebar (desktop) */}
      <aside className={'sidebar' + (sidebarOpen ? ' open' : '')} aria-label="Main navigation">
        <div className="sidebar-brand">
          <img src="/seller-app/dejoiy-mark.png" alt="DEJOIY" className="brand-mark-img" onError={(e) => {
            (e.target as HTMLElement).style.display = 'none'
          }} />
          <div>
            <strong>DEJOIY</strong>
            <span>Seller Central</span>
          </div>
        </div>
        <nav className="sidebar-nav">
          {NAV.map((s) => (
            <div key={s.section}>
              <div className="nav-section">{s.section}</div>
              {s.items.map((i) => (
                <NavLink key={i.to} to={i.to} className={({ isActive }) => 'nav-item' + (isActive ? ' active' : '')} end={i.to === '/'}>
                  <span className="nav-ico">{i.ico}</span>
                  {i.label}
                  {i.badge === 'orders' && orderCount > 0 && <span className="nav-badge">{orderCount}</span>}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
        <div className="sidebar-foot">
          {me && (
            <div className="sidebar-seller">
              <img src={me.user.avatar} alt="" />
              <div>
                <strong>{me.user.name}</strong>
                <span>{me.isAdmin ? 'Administrator' : 'Seller'}</span>
              </div>
            </div>
          )}
        </div>
      </aside>
      {sidebarOpen && <div className="drawer-overlay" onClick={() => setSidebarOpen(false)} aria-hidden="true" />}

      <div className="main">
        {/* Topbar */}
        <header className="topbar">
          <button className="icon-btn hamburger" aria-label="Menu" onClick={() => setSidebarOpen(true)}>☰</button>
          <div className="topbar-search" onClick={() => setPaletteOpen(true)} role="button" tabIndex={0}>
            <span>🔍</span>
            <span style={{ color: 'var(--ink-300)' }}>Search orders, products…</span>
            <kbd>Ctrl K</kbd>
          </div>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 4 }}>
            <div style={{ position: 'relative' }}>
              <button className="icon-btn" aria-label="Notifications" onClick={() => setNotifOpen((v) => !v)}>
                🔔{notif && notif.unread > 0 && <span className="dot" />}
              </button>
              {notifOpen && notif && (
                <div className="notif-pop">
                  <div className="card-head"><h3>Notifications</h3><span className="hint">{notif.unread} new</span></div>
                  {notif.items.length === 0 && <div style={{ padding: 24, textAlign: 'center', color: 'var(--ink-400)' }}>All caught up 🎉</div>}
                  {notif.items.map((n) => (
                    <div key={n.id} className="notif-item" onClick={() => { setNotifOpen(false); navigate(n.link) }}>
                      <div className="n-ico" style={{ background: 'var(--brand-50)' }}>
                        {n.type === 'order' ? '📦' : n.type === 'inventory' ? '📋' : n.type === 'review' ? '⭐' : '🔔'}
                      </div>
                      <div>
                        <strong>{n.title}</strong>
                        <span>{n.body}</span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
            <div style={{ position: 'relative' }}>
              <button className="icon-btn" aria-label="Account menu" aria-expanded={userOpen} onClick={() => setUserOpen((v) => !v)}>
                {me ? <img src={me.user.avatar} alt="" style={{ width: 30, height: 30, borderRadius: '50%' }} /> : '👤'}
              </button>
              {userOpen && (
                <div className="notif-pop user-pop">
                  {me && (
                    <div style={{ padding: '14px 16px', borderBottom: '1px solid var(--ink-100)' }}>
                      <strong style={{ fontSize: 13.5, display: 'block' }}>{me.user.name}</strong>
                      <span style={{ fontSize: 12, color: 'var(--ink-400)' }}>{me.isAdmin ? 'Administrator' : 'Seller'}</span>
                    </div>
                  )}
                  <button className="palette-item" onClick={() => { setUserOpen(false); window.open(cfg.home + '', '_blank', 'noopener') }}>
                    <span className="ico">🏪</span>View Storefront
                  </button>
                  <button className="palette-item" onClick={() => { setUserOpen(false); navigate('/settings') }}>
                    <span className="ico">⚙️</span>Settings
                  </button>
                  <button className="palette-item" onClick={() => { window.location.href = '/wp-login.php?action=logout' }}>
                    <span className="ico">↩</span>Log Out
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        <main className="content">
          <div className="page-anim" key={location.pathname}>
          <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/analytics" element={<Analytics />} />
            <Route path="/finance" element={<Finance />} />
            <Route path="/orders" element={<Orders />} />
            <Route path="/orders/:id" element={<OrderDetail />} />
            <Route path="/products" element={<Products />} />
            <Route path="/products/new" element={<ProductEditor />} />
            <Route path="/products/:id" element={<ProductEditor />} />
            <Route path="/inventory" element={<Inventory />} />
            <Route path="/customers" element={<Customers />} />
            <Route path="/reviews" element={<Reviews />} />
            <Route path="/marketing" element={<Marketing />} />
            <Route path="/store" element={<Store />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/settings/:tab" element={<Settings />} />
            <Route path="/onboarding" element={<Onboarding />} />
            <Route path="/support" element={<Support />} />
            <Route path="/joi" element={<Joi />} />
            <Route path="/more" element={<More />} />
            <Route path="*" element={<Empty art="🧭" title="Page not found" body="This route does not exist in the Seller App." />} />
          </Routes>
          </div>
        </main>
      </div>

      {/* Mobile bottom nav */}
      <nav className="mobile-nav" aria-label="Primary">
        {MOBILE_NAV.map((m) => (
          <NavLink key={m.to} to={m.to} className={({ isActive }) => (isActive ? 'active' : '')} end={m.to === '/'}>
            <span className="m-ico">{m.ico}</span>
            {m.label}
            {m.badge === 'orders' && orderCount > 0 && <span className="m-badge">{orderCount}</span>}
          </NavLink>
        ))}
      </nav>

      {/* Command palette */}
      {paletteOpen && <Palette onClose={() => setPaletteOpen(false)} />}

      {toast.node}
    </div>
  )
}

// ── Command palette (Ctrl+K) ──
function Palette({ onClose }: { onClose: () => void }) {
  const [q, setQ] = useState('')
  const [results, setResults] = useState<{ products: import('./api').Product[]; orders: Order[] } | null>(null)
  const navigate = useNavigate()
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => { inputRef.current?.focus() }, [])
  useEffect(() => {
    if (q.trim().length < 2) { setResults(null); return }
    const t = setTimeout(() => {
      api.search(q).then(setResults).catch(() => setResults(null))
    }, 250)
    return () => clearTimeout(t)
  }, [q])

  const go = (to: string) => { onClose(); navigate(to) }

  const pages = NAV.flatMap((s) => s.items).filter((i) => i.label.toLowerCase().includes(q.toLowerCase()))

  return (
    <div className="overlay" onClick={onClose}>
      <div className="palette" onClick={(e) => e.stopPropagation()}>
        <input ref={inputRef} value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search products, orders, pages…" />
        <div className="palette-list">
          {q.trim().length < 2 && (
            <>
              <div className="palette-sec">Pages</div>
              {NAV.flatMap((s) => s.items).slice(0, 8).map((i) => (
                <button key={i.to} className="palette-item" onClick={() => go(i.to)}>
                  <span className="ico">{i.ico}</span>{i.label}
                </button>
              ))}
            </>
          )}
          {q.trim().length >= 2 && (
            <>
              {pages.length > 0 && <div className="palette-sec">Pages</div>}
              {pages.map((i) => (
                <button key={i.to} className="palette-item" onClick={() => go(i.to)}>
                  <span className="ico">{i.ico}</span>{i.label}
                </button>
              ))}
              {results?.products && results.products.length > 0 && (
                <>
                  <div className="palette-sec">Products</div>
                  {results.products.map((p) => (
                    <button key={p.id} className="palette-item" onClick={() => go(`/products/${p.id}`)}>
                      <span className="ico">🏷️</span>
                      <span style={{ flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{p.name}</span>
                      <span style={{ color: 'var(--ink-400)', fontSize: 12 }}>{money(p.price)}</span>
                    </button>
                  ))}
                </>
              )}
              {results?.orders && results.orders.length > 0 && (
                <>
                  <div className="palette-sec">Orders</div>
                  {results.orders.map((o) => (
                    <button key={o.id} className="palette-item" onClick={() => go(`/orders/${o.id}`)}>
                      <span className="ico">📦</span>#{o.number} — {o.customer.name} · {money(o.total, o.currency)}
                    </button>
                  ))}
                </>
              )}
              {(!results || (results.products.length === 0 && results.orders.length === 0)) && pages.length === 0 && (
                <div style={{ padding: 24, textAlign: 'center', color: 'var(--ink-400)' }}>No matches for "{q}"</div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  )
}

// ── More (mobile modules directory) ──
function More() {
  return (
    <div>
      <div className="page-head"><h1>All Modules</h1></div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(min(150px, 100%), 1fr))', gap: 12 }}>
        {NAV.flatMap((s) => s.items).map((i) => (
          <Link key={i.to} to={i.to} className="card card-pad" style={{ display: 'flex', gap: 12, alignItems: 'center', textDecoration: 'none', color: 'inherit' }}>
            <span style={{ fontSize: 22 }}>{i.ico}</span>
            <strong style={{ fontSize: 13.5 }}>{i.label}</strong>
          </Link>
        ))}
      </div>
    </div>
  )
}
