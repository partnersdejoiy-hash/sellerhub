import { Link, useParams } from 'react-router-dom'
import { api, MeResponse, Store } from '../api'
import { ErrorBox, Loading, useAsync } from '../ui'

const TABS = [
  { key: 'account', label: 'Account', ico: '👤' },
  { key: 'store', label: 'Store settings', ico: '🏬' },
  { key: 'notifications', label: 'Notifications', ico: '🔔' },
  { key: 'security', label: 'Security', ico: '🔒' },
]

export default function Settings() {
  const { tab } = useParams()
  const active = tab || 'account'
  const { data: me } = useAsync(() => api.me(), [])
  const { data: store, loading, error, reload } = useAsync(() => api.store(), [])

  return (
    <div>
      <div className="page-head"><div><h1>Settings</h1><p>Account, store and app preferences.</p></div></div>

      <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr', gap: 18, alignItems: 'start' }}>
        <div className="card card-pad" style={{ position: 'sticky', top: 80, display: 'grid', gap: 4 }}>
          {TABS.map((t) => (
            <Link key={t.key} to={t.key === 'account' ? '/settings' : '/settings/' + t.key} className={'nav-item' + (active === t.key ? ' active' : '')} style={{ color: active === t.key ? undefined : 'var(--ink-700)' }}>
              <span className="nav-ico">{t.ico}</span>{t.label}
            </Link>
          ))}
        </div>

        <div className="card card-pad">
          {active === 'account' && (me ? <AccountTab me={me} /> : <Loading />)}
          {active === 'store' && (store ? <StoreTab store={store} /> : loading ? <Loading /> : error ? <ErrorBox message={error} retry={reload} /> : null)}
          {active === 'notifications' && <NotifTab />}
          {active === 'security' && <SecurityTab />}
        </div>
      </div>
    </div>
  )
}

function AccountTab({ me }: { me: MeResponse }) {
  return (
    <div>
      <h3 style={{ marginBottom: 14 }}>Account</h3>
      <div style={{ display: 'flex', gap: 14, alignItems: 'center', marginBottom: 18 }}>
        <img src={me.user.avatar} alt="" style={{ width: 56, height: 56, borderRadius: '50%' }} />
        <div>
          <strong style={{ fontSize: 15 }}>{me.user.name}</strong>
          <div style={{ fontSize: 12.5, color: 'var(--ink-500)' }}>{me.user.email}</div>
        </div>
        <span className={'badge ' + (me.isAdmin ? 'badge-info' : me.isVendor ? 'badge-brand' : 'badge-muted')} style={{ marginLeft: 'auto' }}>
          {me.isAdmin ? 'Administrator' : me.isVendor ? 'Verified Seller' : 'No store yet'}
        </span>
      </div>
      <div className="field">
        <label>Seller ID</label>
        <input className="input" value={me.vendorId ? 'DJY-VND-' + String(me.vendorId).padStart(6, '0') : '—'} disabled />
        <div className="help">Your permanent, immutable marketplace identity.</div>
      </div>
      <div className="field">
        <label>Storefront</label>
        <div style={{ display: 'flex', gap: 8 }}>
          <input className="input" value={me.storefrontUrl} disabled />
          <a className="btn" href={me.storefrontUrl} target="_blank" rel="noreferrer">Open ↗</a>
        </div>
      </div>
      <p className="help">Profile photo and password are managed by your DEJOIY account. <a href="/wp-admin/profile.php" target="_blank" rel="noreferrer">Edit WordPress profile ↗</a></p>
    </div>
  )
}

function StoreTab({ store }: { store: Store }) {
  return (
    <div>
      <h3 style={{ marginBottom: 8 }}>Store settings</h3>
      <p className="help" style={{ marginBottom: 14 }}>Store branding, address and policies live in the Store module.</p>
      <div className="field">
        <label>Store name</label>
        <input className="input" value={store.storeName} disabled />
      </div>
      <Link className="btn btn-primary" to="/store">Open Store Manager →</Link>
    </div>
  )
}

function NotifTab() {
  return (
    <div>
      <h3 style={{ marginBottom: 14 }}>Notification preferences</h3>
      <p className="help" style={{ marginBottom: 16 }}>
        In-app alerts are always on for orders, low stock and reviews. They are derived from your live store data —
        nothing fake, nothing spammy.
      </p>
      {[
        ['📦', 'New order alerts', 'Always on'],
        ['📋', 'Low / out of stock', 'Always on'],
        ['⭐', 'Review waiting', 'Always on'],
      ].map(([ico, t, s]) => (
        <div key={t} style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '12px 0', borderBottom: '1px solid var(--ink-100)' }}>
          <span>{ico}</span>
          <div style={{ flex: 1 }}>
            <strong style={{ fontSize: 13.5 }}>{t}</strong>
            <div style={{ fontSize: 12, color: 'var(--ink-400)' }}>{s}</div>
          </div>
          <span className="badge badge-ok">On</span>
        </div>
      ))}
      <p className="help" style={{ marginTop: 14 }}>Email + push preferences coming with the DEJOIY notification service.</p>
    </div>
  )
}

function SecurityTab() {
  return (
    <div>
      <h3 style={{ marginBottom: 14 }}>Security</h3>
      <div style={{ display: 'grid', gap: 12, fontSize: 13.5 }}>
        <div className="card card-pad" style={{ boxShadow: 'none' }}>
          <strong style={{ display: 'block', marginBottom: 4 }}>🔐 Session security</strong>
          <span style={{ color: 'var(--ink-500)', fontSize: 12.5 }}>You are signed in with your DEJOIY account via encrypted cookie session. The app never stores passwords.</span>
        </div>
        <div className="card card-pad" style={{ boxShadow: 'none' }}>
          <strong style={{ display: 'block', marginBottom: 4 }}>🛡️ Data access</strong>
          <span style={{ color: 'var(--ink-500)', fontSize: 12.5 }}>Every API request is scoped server-side to your own seller data. Cross-seller access is blocked at the API layer.</span>
        </div>
        <div className="card card-pad" style={{ boxShadow: 'none' }}>
          <strong style={{ display: 'block', marginBottom: 4 }}>🔑 Password</strong>
          <span style={{ color: 'var(--ink-500)', fontSize: 12.5 }}><a href="/wp-login.php?action=lostpassword" target="_blank" rel="noreferrer">Reset via DEJOIY account recovery ↗</a></span>
        </div>
      </div>
    </div>
  )
}
