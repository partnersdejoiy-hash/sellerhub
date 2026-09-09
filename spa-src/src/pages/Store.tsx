import { useEffect, useState } from 'react'
import { api, Store as StoreData, Media } from '../api'
import { ErrorBox, Loading, useToast, useAsync } from '../ui'

export default function Store() {
  const { data, loading, error, reload } = useAsync(() => api.store(), [])
  const [draft, setDraft] = useState<Partial<StoreData> | null>(null)
  const [saving, setSaving] = useState(false)
  const toast = useToast()

  useEffect(() => {
    if (data) setDraft(data)
  }, [data])

  if (loading && !data) return <Loading label="Loading your store…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!draft) return null

  function set<K extends keyof StoreData>(k: K, v: StoreData[K]) {
    setDraft((d) => (d ? { ...d, [k]: v } : d))
  }

  async function save() {
    setSaving(true)
    try {
      const saved = await api.saveStore(draft as Partial<StoreData>)
      setDraft(saved)
      toast.show('Store saved ✓')
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setSaving(false)
    }
  }

  async function uploadLogo(file: File) {
    try {
      const m: Media = await api.uploadMedia(file)
      set('logo', m.url)
      toast.show('Logo uploaded — press Save')
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    }
  }

  async function uploadBanner(file: File) {
    try {
      const m: Media = await api.uploadMedia(file)
      set('banner', m.url)
      toast.show('Banner uploaded — press Save')
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Store</h1>
          <p>This is what buyers see on the DEJOIY marketplace.</p>
        </div>
        <button className="btn btn-primary" disabled={saving} onClick={save}>{saving ? 'Saving…' : 'Save changes'}</button>
      </div>

      {/* Live preview */}
      <div className="card" style={{ overflow: 'hidden', marginBottom: 18 }}>
        <div style={{ height: 130, background: draft.banner ? `url(${draft.banner}) center/cover` : 'linear-gradient(120deg, var(--brand-800), var(--brand-600))' }} />
        <div className="card-pad" style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap' }}>
          {draft.logo ? (
            <img src={draft.logo} alt="" style={{ width: 64, height: 64, borderRadius: 14, objectFit: 'cover', border: '3px solid #fff', boxShadow: 'var(--shadow-md)', marginTop: -46 }} />
          ) : (
            <div style={{ width: 64, height: 64, borderRadius: 14, background: 'linear-gradient(135deg, var(--brand-500), var(--brand-700))', display: 'grid', placeItems: 'center', color: '#fff', fontWeight: 800, fontSize: 22, marginTop: -46, border: '3px solid #fff' }}>
              {(draft.storeName || 'D').charAt(0).toUpperCase()}
            </div>
          )}
          <div style={{ flex: 1, minWidth: 180 }}>
            <strong style={{ fontSize: 16 }}>{draft.storeName || 'Your store name'}</strong>
            <p style={{ fontSize: 12.5, color: 'var(--ink-500)' }}>{draft.description ? draft.description.slice(0, 90) : 'Add a description so buyers know what you sell.'}</p>
          </div>
          <a className="btn btn-sm" href={undefined} onClick={(e) => { e.preventDefault(); window.open((window.DSA_CONFIG?.home || 'https://dejoiy.com'), '_blank') }}>View storefront ↗</a>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(320px, 1fr))', gap: 18 }}>
        <div className="card card-pad">
          <h3 style={{ marginBottom: 14 }}>Brand</h3>
          <div className="field">
            <label>Store name</label>
            <input className="input" value={draft.storeName || ''} onChange={(e) => set('storeName', e.target.value)} />
          </div>
          <div className="field">
            <label>Description</label>
            <textarea className="textarea" rows={4} value={draft.description || ''} onChange={(e) => set('description', e.target.value)} placeholder="What do you sell? What makes you special?" />
          </div>
          <div style={{ display: 'flex', gap: 12 }}>
            <div className="field" style={{ flex: 1 }}>
              <label>Logo</label>
              <label className="btn btn-sm" style={{ cursor: 'pointer' }}>
                Upload logo
                <input type="file" accept="image/*" hidden onChange={(e) => e.target.files && uploadLogo(e.target.files[0])} />
              </label>
            </div>
            <div className="field" style={{ flex: 1 }}>
              <label>Banner (1200×300 recommended)</label>
              <label className="btn btn-sm" style={{ cursor: 'pointer' }}>
                Upload banner
                <input type="file" accept="image/*" hidden onChange={(e) => e.target.files && uploadBanner(e.target.files[0])} />
              </label>
            </div>
          </div>
        </div>

        <div className="card card-pad">
          <h3 style={{ marginBottom: 14 }}>Contact & Address</h3>
          <div className="form-grid">
            <div className="field">
              <label>Phone</label>
              <input className="input" value={draft.phone || ''} onChange={(e) => set('phone', e.target.value)} />
            </div>
            <div className="field">
              <label>Email</label>
              <input className="input" value={draft.email || ''} disabled title="Email is managed by DEJOIY accounts" />
            </div>
            <div className="field">
              <label>Address line 1</label>
              <input className="input" value={draft.address1 || ''} onChange={(e) => set('address1', e.target.value)} />
            </div>
            <div className="field">
              <label>Address line 2</label>
              <input className="input" value={draft.address2 || ''} onChange={(e) => set('address2', e.target.value)} />
            </div>
            <div className="field">
              <label>City</label>
              <input className="input" value={draft.city || ''} onChange={(e) => set('city', e.target.value)} />
            </div>
            <div className="field">
              <label>State</label>
              <input className="input" value={draft.state || ''} onChange={(e) => set('state', e.target.value)} />
            </div>
            <div className="field">
              <label>ZIP / PIN</label>
              <input className="input" value={draft.zip || ''} onChange={(e) => set('zip', e.target.value)} />
            </div>
            <div className="field">
              <label>Country</label>
              <input className="input" value={draft.country || 'IN'} onChange={(e) => set('country', e.target.value)} />
            </div>
          </div>
        </div>

        <div className="card card-pad">
          <h3 style={{ marginBottom: 14 }}>Social profiles</h3>
          {(['facebook', 'instagram', 'twitter', 'youtube', 'linkedin'] as const).map((net) => (
            <div className="field" key={net}>
              <label style={{ textTransform: 'capitalize' }}>{net}</label>
              <input className="input" placeholder={`https://${net}.com/yourstore`} value={draft.social?.[net] || ''} onChange={(e) => set('social', { ...(draft.social || { facebook: '', twitter: '', instagram: '', youtube: '', linkedin: '' }), [net]: e.target.value })} />
            </div>
          ))}
        </div>
      </div>
      {toast.node}
    </div>
  )
}
