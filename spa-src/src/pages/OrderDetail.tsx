import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { api, OrderDetail as OrderDetailData } from '../api'
import { Empty, ErrorBox, Loading, ORDER_STATUS, dateTimeFmt, fullMoney, useToast, useAsync } from '../ui'

const ACTIONS: Record<string, { to: string; label: string }[]> = {
  processing: [
    { to: 'completed', label: '✓ Mark Completed' },
    { to: 'on-hold', label: '⏸ On Hold' },
    { to: 'cancelled', label: '✕ Cancel' },
  ],
  'on-hold': [
    { to: 'processing', label: '▶ Start Processing' },
    { to: 'cancelled', label: '✕ Cancel' },
  ],
  pending: [
    { to: 'processing', label: '▶ Start Processing' },
    { to: 'cancelled', label: '✕ Cancel' },
  ],
  completed: [{ to: 'refunded', label: '↩ Refund (via admin)' }],
}

export default function OrderDetail() {
  const { id } = useParams()
  const toast = useToast()
  const { data, loading, error, reload } = useAsync(() => api.order(parseInt(id!, 10)), [id])
  const [note, setNote] = useState('')
  const [noteToCustomer, setNoteToCustomer] = useState(false)
  const [busy, setBusy] = useState(false)

  if (loading && !data) return <Loading label="Loading order…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  async function changeStatus(to: string) {
    if (!data) return
    if ((to === 'cancelled' || to === 'refunded') && !confirm(`Change order #${data.number} to ${to}?`)) return
    setBusy(true)
    try {
      await api.orderStatus(data.id, to)
      toast.show('Order updated ✓')
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  async function addNote() {
    if (!data || !note.trim()) return
    setBusy(true)
    try {
      await api.orderNote(data.id, note, noteToCustomer)
      setNote('')
      setNoteToCustomer(false)
      toast.show('Note added ✓')
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  const st = ORDER_STATUS[data.status] || { label: data.status, cls: 'badge-muted' }

  return (
    <div>
      <div className="page-head">
        <div>
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
            <h1>Order #{data.number}</h1>
            <span className={'badge ' + st.cls}>{st.label}</span>
          </div>
          <p>{dateTimeFmt(data.date)} · {data.paymentMethod || 'Payment details at checkout'}</p>
        </div>
        <div className="page-actions">
          {(ACTIONS[data.status] || []).map((a) => (
            <button key={a.to} className={'btn ' + (a.to === 'completed' ? 'btn-primary' : a.to === 'cancelled' ? 'btn-danger' : '')} disabled={busy} onClick={() => changeStatus(a.to)}>
              {a.label}
            </button>
          ))}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(300px, 1fr))', gap: 18 }}>
        <div style={{ display: 'grid', gap: 18, alignContent: 'start' }}>
          {/* Items */}
          <div className="card">
            <div className="card-head"><h3>Items</h3><span className="hint">{data.itemCount} units</span></div>
            <div className="table-wrap">
              <table className="table">
                <tbody>
                  {data.itemsDetail.map((it) => (
                    <tr key={it.id}>
                      <td>
                        <div style={{ fontWeight: 600 }}>{it.name}</div>
                        <div style={{ fontSize: 11.5, color: 'var(--ink-400)' }}>{it.sku ? 'SKU ' + it.sku : ''} {it.variationId ? '· Variation #' + it.variationId : ''}</div>
                      </td>
                      <td data-label="Qty" className="cell-num">×{it.qty}</td>
                      <td data-label="Total" className="cell-num"><strong>{fullMoney(it.total)}</strong></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="card-pad" style={{ display: 'grid', gap: 6, fontSize: 13 }}>
              <Row l="Subtotal" v={fullMoney(data.totals.subtotal)} />
              {data.totals.discount > 0 && <Row l="Discount" v={'−' + fullMoney(data.totals.discount)} />}
              <Row l="Shipping" v={fullMoney(data.totals.shipping)} />
              <Row l="Tax" v={fullMoney(data.totals.tax)} />
              <div style={{ borderTop: '1px solid var(--ink-100)', paddingTop: 8, display: 'flex', justifyContent: 'space-between', fontWeight: 700, fontSize: 15 }}>
                <span>Total</span><span>{fullMoney(data.totals.total)}</span>
              </div>
            </div>
          </div>

          {/* Customer + addresses */}
          <div className="card">
            <div className="card-head"><h3>Customer</h3></div>
            <div className="card-pad" style={{ display: 'grid', gap: 12, fontSize: 13.5 }}>
              <div>
                <strong style={{ fontSize: 14 }}>{data.customer.name || 'Guest'}</strong>
                <div style={{ color: 'var(--ink-500)' }}>{data.customer.email}</div>
                <div style={{ color: 'var(--ink-500)' }}>{data.customer.phone}</div>
              </div>
              <Addr title="Billing" a={data.billing} />
              {data.shipping.address1 && <Addr title="Shipping" a={data.shipping} />}
              {data.shipping.method && <div><span className="badge badge-info">🚚 {data.shipping.method}</span></div>}
              {data.customerNote && (
                <div style={{ background: 'var(--accent-100)', padding: '10px 12px', borderRadius: 10, fontSize: 12.5 }}>
                  💬 <strong>Customer note:</strong> {data.customerNote}
                </div>
              )}
            </div>
          </div>
        </div>

        <div style={{ display: 'grid', gap: 18, alignContent: 'start' }}>
          {/* Timeline */}
          <div className="card">
            <div className="card-head"><h3>Timeline</h3></div>
            <div className="card-pad" style={{ display: 'grid', gap: 12 }}>
              {data.timeline.length === 0 && <span className="help">No notes yet.</span>}
              {data.timeline.map((t, i) => (
                <div key={i} style={{ display: 'flex', gap: 10 }}>
                  <div style={{ width: 8, height: 8, borderRadius: '50%', background: t.customer ? 'var(--accent-500)' : 'var(--brand-500)', marginTop: 5, flexShrink: 0 }} />
                  <div>
                    <div style={{ fontSize: 13 }}>{t.content}</div>
                    <div style={{ fontSize: 11, color: 'var(--ink-400)' }}>{dateTimeFmt(t.date)} · {t.addedBy}{t.customer ? ' · to customer' : ''}</div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Add note */}
          <div className="card">
            <div className="card-head"><h3>Add Note</h3></div>
            <div className="card-pad">
              <textarea className="textarea" rows={3} value={note} onChange={(e) => setNote(e.target.value)} placeholder="Internal note or update…" />
              <label style={{ display: 'flex', gap: 8, alignItems: 'center', margin: '10px 0', fontSize: 13 }}>
                <input type="checkbox" checked={noteToCustomer} onChange={(e) => setNoteToCustomer(e.target.checked)} />
                Send to customer (appears in their order view)
              </label>
              <button className="btn" disabled={busy || !note.trim()} onClick={addNote}>Add Note</button>
            </div>
          </div>

          {/* Tracking */}
          {data.tracking.length > 0 && (
            <div className="card">
              <div className="card-head"><h3>🚚 Tracking</h3></div>
              <div className="card-pad" style={{ display: 'grid', gap: 8, fontSize: 13 }}>
                {(data.tracking as Record<string, unknown>[]).map((t, i) => (
                  <div key={i}>
                    <strong>{String(t.tracking_provider || 'Courier')}</strong> — <code>{String(t.tracking_number || '')}</code>
                    {t.tracking_url ? (
                      <>
                        {' '}
                        <a href={String(t.tracking_url)} target="_blank" rel="noreferrer">Track →</a>
                      </>
                    ) : null}
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>
      {toast.node}
    </div>
  )
}

function Row({ l, v }: { l: string; v: string }) {
  return (
    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
      <span style={{ color: 'var(--ink-500)' }}>{l}</span><span>{v}</span>
    </div>
  )
}

function Addr({ title, a }: { title: string; a: { address1: string; address2: string; city: string; state: string; zip: string; country: string } }) {
  return (
    <div>
      <div style={{ fontWeight: 650, fontSize: 12, color: 'var(--ink-400)', textTransform: 'uppercase', letterSpacing: '0.06em', marginBottom: 3 }}>{title}</div>
      <div>{a.address1}{a.address2 ? ', ' + a.address2 : ''}</div>
      <div style={{ color: 'var(--ink-500)' }}>{a.city}, {a.state} {a.zip} · {a.country}</div>
    </div>
  )
}
