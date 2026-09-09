import { useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api, Product, Category, Media, Variation } from '../api'
import { ErrorBox, Loading, money, useToast } from '../ui'

type Draft = Partial<Product> & { categoryIds?: number[]; galleryIds?: number[]; imageId?: number; accountNumberInput?: string; seoTitle?: string; seoDescription?: string }

const SECTIONS = [
  { key: 'basic', label: 'Basic', ico: '📝' },
  { key: 'media', label: 'Media', ico: '🖼️' },
  { key: 'pricing', label: 'Pricing', ico: '💰' },
  { key: 'inventory', label: 'Inventory', ico: '📋' },
  { key: 'shipping', label: 'Shipping', ico: '🚚' },
  { key: 'variations', label: 'Variations', ico: '🎨' },
  { key: 'seo', label: 'SEO', ico: '🔍' },
  { key: 'advanced', label: 'Advanced', ico: '⚙️' },
]

export default function ProductEditor() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const toast = useToast()

  const [draft, setDraft] = useState<Draft | null>(null)
  const [original, setOriginal] = useState<Draft | null>(null)
  const [section, setSection] = useState('basic')
  const [saving, setSaving] = useState(false)
  const [loadErr, setLoadErr] = useState('')
  const [cats, setCats] = useState<Category[]>([])
  const [typeTouched, setTypeTouched] = useState(false)

  // Load
  useEffect(() => {
    api.categories().then(setCats).catch(() => {})
    if (isEdit) {
      api
        .product(parseInt(id!, 10))
        .then((p) => {
          const d: Draft = {
            ...p,
            categoryIds: p.categories.map((c) => c.id),
          }
          setDraft(d)
          setOriginal(d)
        })
        .catch((e) => setLoadErr(e.message))
    } else {
      const fresh: Draft = { name: '', type: 'simple', status: 'draft', description: '', shortDescription: '', manageStock: true, stock: 0, regularPrice: undefined }
      setDraft(fresh)
      setOriginal(fresh)
    }
  }, [id])

  const dirty = useMemo(() => JSON.stringify(draft) !== JSON.stringify(original), [draft, original])

  function set<K extends keyof Draft>(key: K, value: Draft[K]) {
    setDraft((d) => (d ? { ...d, [key]: value } : d))
  }

  async function save(publish?: 'publish') {
    if (!draft) return
    if (!draft.name?.trim()) {
      toast.show('Product name is required', true)
      setSection('basic')
      return
    }
    setSaving(true)
    try {
      const payload: Draft = { ...draft }
      if (publish) payload.status = publish
      const saved = isEdit ? await api.updateProduct(parseInt(id!, 10), payload) : await api.createProduct(payload)
      toast.show(isEdit ? 'Product saved ✓' : 'Product created ✓')
      setOriginal({ ...saved, categoryIds: saved.categories.map((c) => c.id) })
      setDraft({ ...saved, categoryIds: saved.categories.map((c) => c.id) })
      if (!isEdit) navigate('/products/' + saved.id, { replace: true })
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setSaving(false)
    }
  }

  if (loadErr) return <ErrorBox message={loadErr} />
  if (!draft) return <Loading label="Loading product…" />

  const completion = useMemo(() => {
    let score = 0
    if (draft.name) score += 20
    if (draft.description) score += 15
    if (draft.imageId || draft.image) score += 15
    if (draft.regularPrice) score += 15
    if (draft.categoryIds?.length) score += 10
    if (draft.sku) score += 10
    if (draft.stock || draft.stockStatus === 'outofstock') score += 10
    if (draft.shortDescription) score += 5
    return score
  }, [draft])

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>{isEdit ? 'Edit Product' : 'Add Product'}</h1>
          <p>
            {isEdit ? `Editing "${draft.name}"` : 'Create a new product for your DEJOIY store'} ·{' '}
            {completion < 100 ? <span style={{ color: 'var(--warn-600)', fontWeight: 650 }}>{completion}% complete</span> : <span style={{ color: 'var(--ok-600)', fontWeight: 650 }}>Complete ✓</span>}
          </p>
        </div>
        <div className="page-actions">
          <button className="btn" onClick={() => navigate(-1)}>Cancel</button>
          <button className="btn" disabled={saving || !dirty} onClick={() => save()}>Save Draft</button>
          <button className="btn btn-primary" disabled={saving} onClick={() => save('publish')}>
            {saving ? 'Saving…' : isEdit ? 'Update' : 'Publish'}
          </button>
        </div>
      </div>

      <div style={{ marginBottom: 16 }}>
        <div className="progress"><div style={{ width: `${completion}%` }} /></div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '200px 1fr', gap: 18, alignItems: 'start' }}>
        {/* Section rail */}
        <div className="card card-pad" style={{ position: 'sticky', top: 80, display: 'grid', gap: 4 }}>
          {SECTIONS.map((s) => (
            <button
              key={s.key}
              className={'nav-item' + (section === s.key ? ' active' : '')}
              style={{ color: section === s.key ? undefined : 'var(--ink-700)' }}
              onClick={() => setSection(s.key)}
            >
              <span className="nav-ico">{s.ico}</span>{s.label}
            </button>
          ))}
          {draft.type === 'variable' && !isEdit && <div className="help" style={{ padding: '6px 12px', fontSize: 11 }}>Save the product first to add variations.</div>}
        </div>

        {/* Panels */}
        <div className="card card-pad">
          {section === 'basic' && (
            <>
              <div className="field">
                <label>Product name *</label>
                <input className="input" value={draft.name || ''} onChange={(e) => set('name', e.target.value)} placeholder="e.g. Handcrafted Blue Pottery Vase" autoFocus />
              </div>
              <div className="form-grid">
              <div className="form-grid">
                <div className="field">
                  <label>Product type</label>
                  <select className="select" value={draft.type} onChange={(e) => { set('type', e.target.value); setTypeTouched(true) }} disabled={typeTouched && !isEdit}>
                    <option value="simple">Simple product</option>
                    <option value="variable">Variable (has sizes/colors)</option>
                  </select>
                  <div className="help">Type locks after first save.</div>
                </div>
                <div className="field">
                  <label>Brand</label>
                  <input className="input" value={draft.brand || ''} onChange={(e) => set('brand', e.target.value)} placeholder="e.g. DEJOIY Basics" />
                </div>
              </div>
                <div className="field">
                  <label>Status</label>
                  <select className="select" value={draft.status} onChange={(e) => set('status', e.target.value)}>
                    <option value="draft">Draft</option>
                    <option value="publish">Published</option>
                    <option value="pending">Pending review</option>
                    <option value="private">Private</option>
                  </select>
                </div>
              </div>
              <div className="field">
                <label>Categories</label>
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                  {cats.map((c) => {
                    const active = draft.categoryIds?.includes(c.id)
                    return (
                      <button
                        key={c.id}
                        type="button"
                        className={'pill' + (active ? ' active' : '')}
                        onClick={() => set('categoryIds', active ? (draft.categoryIds || []).filter((x) => x !== c.id) : [...(draft.categoryIds || []), c.id])}
                      >
                        {c.name}
                      </button>
                    )
                  })}
                  {cats.length === 0 && <span className="help">No categories configured on the marketplace.</span>}
                </div>
              </div>
              <div className="field">
                <label>Short description</label>
                <textarea className="textarea" rows={2} value={draft.shortDescription || ''} onChange={(e) => set('shortDescription', e.target.value)} placeholder="One-liner shown near the buy box" />
              </div>
              <div className="field">
                <label>Full description</label>
                <textarea className="textarea" rows={7} value={draft.description || ''} onChange={(e) => set('description', e.target.value)} placeholder="Tell buyers everything: materials, care, dimensions, what's in the box…" />
              </div>
            </>
          )}

          {section === 'media' && (
            <MediaManager
              image={draft.image}
              gallery={draft.gallery || []}
              onChange={(imageId, image, galleryIds, gallery) => {
                setDraft((d) => (d ? { ...d, imageId, image, galleryIds, gallery } : d))
              }}
            />
          )}

          {section === 'pricing' && (
            <>
              <div className="form-grid">
                <div className="field">
                  <label>Regular price (₹) *</label>
                  <input className="input" type="number" min="0" step="0.01" value={draft.regularPrice ?? ''} onChange={(e) => set('regularPrice', e.target.value ? parseFloat(e.target.value) : undefined)} />
                </div>
                <div className="field">
                  <label>Sale price (₹)</label>
                  <input className="input" type="number" min="0" step="0.01" value={draft.salePrice ?? ''} onChange={(e) => set('salePrice', e.target.value === '' ? null : parseFloat(e.target.value))} />
                  {draft.salePrice && draft.regularPrice && draft.salePrice >= draft.regularPrice && (
                    <div className="help" style={{ color: 'var(--danger-600)' }}>Sale price should be below regular price.</div>
                  )}
                </div>
                <div className="field">
                  <label>Sale starts</label>
                  <input className="input" type="datetime-local" value={draft.saleFrom ? draft.saleFrom.slice(0, 16) : ''} onChange={(e) => set('saleFrom', e.target.value)} />
                </div>
                <div className="field">
                  <label>Sale ends</label>
                  <input className="input" type="datetime-local" value={draft.saleTo ? draft.saleTo.slice(0, 16) : ''} onChange={(e) => set('saleTo', e.target.value)} />
                </div>
                <div className="field">
                  <label>Cost per item (₹)</label>
                  <input className="input" type="number" min="0" step="0.01" value={draft.costPrice ?? ''} onChange={(e) => set('costPrice', e.target.value === '' ? null : parseFloat(e.target.value))} />
                  <div className="help">Private — used for profit math, never shown to buyers.</div>
                </div>
              </div>
              <div className="field">
                <label>Tax status</label>
                <select className="select" value={draft.taxStatus || 'taxable'} onChange={(e) => set('taxStatus', e.target.value)}>
                  <option value="taxable">Taxable</option>
                  <option value="shipping">Shipping only</option>
                </select>
              </div>
              <div className="field">
                <label>Tax class</label>
                <select className="select" value={draft.taxClass || ''} onChange={(e) => set('taxClass', e.target.value)}>
                  <option value="">Standard</option>
                  <option value="reduced-rate">Reduced rate</option>
                  <option value="zero-rate">Zero rate</option>
                </select>
              </div>
            </>
          )}

          {section === 'inventory' && (
            <>
              <div className="field" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label className="switch">
                  <input type="checkbox" checked={!!draft.manageStock} onChange={(e) => set('manageStock', e.target.checked)} />
                  <span className="track" />
                </label>
                <label style={{ margin: 0 }}>Track stock quantity</label>
                <span className="help" style={{ margin: 0 }}>Turn off for made-to-order items</span>
                <span style={{ display: 'none' }}>{draft.sku}</span>
              </div>
              {draft.manageStock ? (
                <div className="form-grid-3">
                  <div className="field">
                    <label>Stock quantity</label>
                    <input className="input" type="number" min="0" value={draft.stock ?? 0} onChange={(e) => set('stock', parseInt(e.target.value, 10) || 0)} />
                  </div>
                  <div className="field">
                    <label>Low-stock alert at</label>
                    <input className="input" type="number" min="0" value={draft.lowStockThreshold ?? 5} onChange={(e) => set('lowStockThreshold', parseInt(e.target.value, 10) || 0)} />
                  </div>
                  <div className="field">
                    <label>Backorders</label>
                    <select className="select" value={draft.backorders || 'no'} onChange={(e) => set('backorders', e.target.value)}>
                      <option value="no">Do not allow</option>
                      <option value="notify">Allow, notify customer</option>
                      <option value="yes">Allow</option>
                    </select>
                  </div>
                </div>
              ) : (
                <div className="field">
                  <label>Stock status</label>
                  <select className="select" value={draft.stockStatus || 'instock'} onChange={(e) => set('stockStatus', e.target.value)}>
                    <option value="instock">In stock</option>
                    <option value="outofstock">Out of stock</option>
                    <option value="onbackorder">On backorder</option>
                  </select>
                </div>
              )}
              <div className="form-grid">
                <div className="field">
                  <label>SKU</label>
                  <input className="input" value={draft.sku || ''} onChange={(e) => set('sku', e.target.value)} placeholder="Unique product code" />
                </div>
                <div className="field">
                  <label>GTIN / Barcode</label>
                  <input className="input" value={draft.gtin || ''} onChange={(e) => set('gtin', e.target.value)} placeholder="EAN/UPC/ISBN" />
                </div>
                <div className="field">
                  <label>MPN</label>
                  <input className="input" value={draft.mpn || ''} onChange={(e) => set('mpn', e.target.value)} placeholder="Manufacturer part number" />
                </div>
              </div>
            </>
          )}

          {section === 'shipping' && (
            <>
              <div className="form-grid-3">
                <div className="field">
                  <label>Weight (kg)</label>
                  <input className="input" type="number" min="0" step="0.01" value={draft.weight ?? ''} onChange={(e) => set('weight', e.target.value ? parseFloat(e.target.value) : undefined)} />
                </div>
                <div className="field">
                  <label>Length (cm)</label>
                  <input className="input" type="number" min="0" step="0.1" value={draft.dimensions?.length ?? ''} onChange={(e) => set('dimensions', { ...(draft.dimensions || { length: 0, width: 0, height: 0 }), length: parseFloat(e.target.value) || 0 })} />
                </div>
                <div className="field">
                  <label>Width (cm)</label>
                  <input className="input" type="number" min="0" step="0.1" value={draft.dimensions?.width ?? ''} onChange={(e) => set('dimensions', { ...(draft.dimensions || { length: 0, width: 0, height: 0 }), width: parseFloat(e.target.value) || 0 })} />
                </div>
                <div className="field">
                  <label>Height (cm)</label>
                  <input className="input" type="number" min="0" step="0.1" value={draft.dimensions?.height ?? ''} onChange={(e) => set('dimensions', { ...(draft.dimensions || { length: 0, width: 0, height: 0 }), height: parseFloat(e.target.value) || 0 })} />
                </div>
              </div>
              <div className="field" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label className="switch">
                  <input type="checkbox" checked={!!draft.virtual} onChange={(e) => set('virtual', e.target.checked)} />
                  <span className="track" />
                </label>
                <label style={{ margin: 0 }}>Virtual product (no shipping)</label>
              </div>
              <div className="field" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label className="switch">
                  <input type="checkbox" checked={!!draft.soldIndividually} onChange={(e) => set('soldIndividually', e.target.checked)} />
                  <span className="track" />
                </label>
                <label style={{ margin: 0 }}>Sold individually (limit 1 per order)</label>
              </div>
            </>
          )}

          {section === 'variations' && (
            <VariationsBuilder draft={draft} set={set} />
          )}

          {section === 'seo' && (
            <>
              <div className="field">
                <label>SEO title</label>
                <input className="input" value={draft.seoTitle || ''} onChange={(e) => set('seoTitle', e.target.value)} placeholder={draft.name || 'Defaults to product name'} maxLength={60} />
                <div className="help">{(draft.seoTitle || '').length}/60 characters</div>
              </div>
              <div className="field">
                <label>Meta description</label>
                <textarea className="textarea" rows={3} value={draft.seoDescription || ''} onChange={(e) => set('seoDescription', e.target.value)} maxLength={160} placeholder="Shown in Google results under the title" />
                <div className="help">{(draft.seoDescription || '').length}/160 characters</div>
              </div>
              <div className="field">
                <label>URL slug</label>
                <input className="input" value={draft.slug || ''} onChange={(e) => set('slug', e.target.value)} placeholder="auto-generated-from-name" />
              </div>
            </>
          )}

          {section === 'advanced' && (
            <>
              <div className="field">
                <label>Purchase note</label>
                <textarea className="textarea" rows={2} value={draft.purchaseNote || ''} onChange={(e) => set('purchaseNote', e.target.value)} placeholder="Shown to customer after purchase" />
              </div>
              <div className="field" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label className="switch">
                  <input type="checkbox" checked={draft.reviewsAllowed !== false} onChange={(e) => set('reviewsAllowed', e.target.checked)} />
                  <span className="track" />
                </label>
                <label style={{ margin: 0 }}>Allow reviews</label>
              </div>
              <div className="field" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <label className="switch">
                  <input type="checkbox" checked={!!draft.featured} onChange={(e) => set('featured', e.target.checked)} />
                  <span className="track" />
                </label>
                <label style={{ margin: 0 }}>Featured product</label>
              </div>
            </>
          )}
        </div>
      </div>
      {toast.node}
    </div>
  )
}

// ── Media manager ──
function MediaManager({ image, gallery, onChange }: {
  image?: string
  gallery: string[]
  onChange: (imageId: number | undefined, image: string | undefined, galleryIds: number[] | undefined, gallery: string[]) => void
}) {
  const [busy, setBusy] = useState(false)
  const [err, setErr] = useState('')

  async function upload(files: FileList | null, target: 'main' | 'gallery') {
    if (!files?.length) return
    setBusy(true)
    setErr('')
    try {
      const ids: number[] = []
      const urls: string[] = []
      for (const f of Array.from(files)) {
        const m: Media = await api.uploadMedia(f)
        ids.push(m.id)
        urls.push(m.url)
      }
      if (target === 'main') {
        onChange(ids[0], urls[0], undefined, gallery)
      } else {
        onChange(undefined, image, [...(galleryIdsFromDom() || []), ...ids], [...gallery, ...urls])
      }
    } catch (e: unknown) {
      setErr((e as Error).message)
    } finally {
      setBusy(false)
    }
  }

  // gallery ids come from the parent draft; this helper reads the current draft via closure trick
  const [galleryIdsLocal, setGalleryIdsLocal] = useState<number[]>([])
  function galleryIdsFromDom() {
    return galleryIdsLocal
  }

  return (
    <div>
      <div className="field">
        <label>Featured image</label>
        <div style={{ display: 'flex', gap: 14, alignItems: 'flex-start', flexWrap: 'wrap' }}>
          {image ? (
            <div style={{ position: 'relative' }}>
              <img src={image} alt="" style={{ width: 132, height: 132, objectFit: 'cover', borderRadius: 12, border: '1px solid var(--ink-200)' }} />
              <button className="btn btn-sm btn-danger" style={{ position: 'absolute', top: -8, right: -8, borderRadius: '50%', width: 28, height: 28, padding: 0 }} onClick={() => onChange(undefined, undefined, undefined, gallery)}>✕</button>
            </div>
          ) : (
            <label className="empty" style={{ width: 132, height: 132, border: '2px dashed var(--ink-200)', borderRadius: 12, display: 'grid', placeItems: 'center', cursor: 'pointer', padding: 8 }}>
              <span style={{ fontSize: 26 }}>🖼️</span>
              <input type="file" accept="image/*" hidden onChange={(e) => upload(e.target.files, 'main')} />
            </label>
          )}
          <div style={{ flex: 1, minWidth: 200 }}>
            <p style={{ fontSize: 13, color: 'var(--ink-500)', marginBottom: 8 }}>
              {busy ? 'Uploading…' : 'JPG, PNG or WebP. Square images look best on DEJOIY. First image becomes the thumbnail.'}
            </p>
            <label className="btn btn-sm" style={{ cursor: 'pointer' }}>
              {image ? 'Replace image' : 'Upload image'}
              <input type="file" accept="image/*" hidden onChange={(e) => upload(e.target.files, 'main')} />
            </label>
            {err && <div className="help" style={{ color: 'var(--danger-600)' }}>{err}</div>}
          </div>
        </div>
      </div>
      <div className="field">
        <label>Gallery</label>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 10 }}>
          {gallery.map((g, i) => (
            <div key={i} style={{ position: 'relative' }}>
              <img src={g} alt="" style={{ width: 84, height: 84, objectFit: 'cover', borderRadius: 10, border: '1px solid var(--ink-200)' }} />
              <button
                className="btn btn-sm btn-danger"
                style={{ position: 'absolute', top: -6, right: -6, borderRadius: '50%', width: 24, height: 24, padding: 0, fontSize: 11 }}
                onClick={() => onChange(undefined, image, (galleryIdsLocal || []).filter((_, idx) => idx !== i), gallery.filter((_, idx) => idx !== i))}
              >✕</button>
            </div>
          ))}
          <label style={{ width: 84, height: 84, border: '2px dashed var(--ink-200)', borderRadius: 10, display: 'grid', placeItems: 'center', cursor: 'pointer', color: 'var(--ink-400)', fontSize: 22 }}>
            +
            <input type="file" accept="image/*" multiple hidden onChange={(e) => upload(e.target.files, 'gallery')} />
          </label>
        </div>
        <div className="help">Up to 8 images recommended. Drag ordering coming from WordPress media library sync.</div>
      </div>
    </div>
  )
}

// ── Variations builder ──
function VariationsBuilder({ draft, set }: { draft: Draft; set: <K extends keyof Draft>(k: K, v: Draft[K]) => void }) {
  const vars = draft.variations || []
  const [attrName, setAttrName] = useState('Size')
  const [attrValues, setAttrValues] = useState('S, M, L')

  function generate() {
    const values = attrValues.split(',').map((s) => s.trim()).filter(Boolean)
    if (!values.length) return
    const newVars: Variation[] = values.map((v, i) => ({
      id: 0,
      sku: (draft.sku || '') + '-' + v.toLowerCase(),
      price: draft.regularPrice || 0,
      regularPrice: draft.regularPrice || 0,
      salePrice: null,
      stock: 0,
      stockStatus: 'instock',
      image: '',
      attributes: { [attrName.toLowerCase()]: v },
      status: 'publish',
    }))
    set('variations', [...vars, ...newVars])
  }

  return (
    <div>
      <div className="form-grid">
        <div className="field">
          <label>Attribute name</label>
          <input className="input" value={attrName} onChange={(e) => setAttrName(e.target.value)} placeholder="Size / Color / Material" />
        </div>
        <div className="field">
          <label>Options (comma separated)</label>
          <input className="input" value={attrValues} onChange={(e) => setAttrValues(e.target.value)} placeholder="S, M, L, XL" />
        </div>
      </div>
      <button className="btn" onClick={generate}>✨ Generate variations</button>

      {vars.length === 0 ? (
        <div className="help" style={{ marginTop: 12 }}>No variations yet. Generate from an attribute above, or add per-variation pricing after saving.</div>
      ) : (
        <div className="table-wrap" style={{ marginTop: 14 }}>
          <table className="table">
            <thead>
              <tr><th>Attributes</th><th>SKU</th><th>Price</th><th>Stock</th><th /></tr>
            </thead>
            <tbody>
              {vars.map((v, i) => (
                <tr key={i}>
                  <td data-label="Attributes">{Object.entries(v.attributes).map(([k, val]) => `${k}: ${val}`).join(' · ')}</td>
                  <td data-label="SKU"><input className="input" style={{ width: 130, padding: '5px 9px' }} value={v.sku} onChange={(e) => { const nv = [...vars]; nv[i] = { ...v, sku: e.target.value }; set('variations', nv) }} /></td>
                  <td data-label="Price"><input className="input" style={{ width: 100, padding: '5px 9px' }} type="number" value={v.regularPrice} onChange={(e) => { const nv = [...vars]; nv[i] = { ...v, regularPrice: parseFloat(e.target.value) || 0, price: parseFloat(e.target.value) || 0 }; set('variations', nv) }} /></td>
                  <td data-label="Stock"><input className="input" style={{ width: 84, padding: '5px 9px' }} type="number" value={v.stock ?? 0} onChange={(e) => { const nv = [...vars]; nv[i] = { ...v, stock: parseInt(e.target.value, 10) || 0 }; set('variations', nv) }} /></td>
                  <td><button className="btn btn-sm btn-ghost" onClick={() => set('variations', vars.filter((_, idx) => idx !== i))}>🗑</button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {vars.length > 0 && <div className="help" style={{ marginTop: 10 }}>Total stock: {vars.reduce((a, v) => a + (v.stock || 0), 0)} · Avg price: {money(vars.reduce((a, v) => a + (v.regularPrice || 0), 0) / vars.length)}</div>}
    </div>
  )
}
