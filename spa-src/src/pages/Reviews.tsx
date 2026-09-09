import { useState } from 'react'
import { api, ReviewsResponse } from '../api'
import { Empty, ErrorBox, Loading, Stars, dateFmt, useToast, useAsync } from '../ui'

export default function Reviews() {
  const { data, loading, error, reload } = useAsync(() => api.reviews(), [])
  const toast = useToast()
  const [replyTo, setReplyTo] = useState<number | null>(null)
  const [reply, setReply] = useState('')
  const [busy, setBusy] = useState(false)
  const [filter, setFilter] = useState<'all' | 'unanswered'>('all')

  if (loading && !data) return <Loading label="Loading reviews…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  const items = filter === 'unanswered' ? data.items.filter((r) => !r.responded) : data.items
  const avg = data.items.length ? data.items.reduce((a, r) => a + r.rating, 0) / data.items.length : 0
  const dist = [5, 4, 3, 2, 1].map((star) => ({
    star,
    count: data.items.filter((r) => r.rating === star).length,
  }))

  async function sendReply(id: number) {
    if (!reply.trim()) return
    setBusy(true)
    try {
      await api.reviewReply(id, reply)
      toast.show('Reply posted ✓')
      setReply('')
      setReplyTo(null)
      reload()
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Reviews</h1>
          <p>{data.count} reviews · {data.unanswered} awaiting your response</p>
        </div>
        <div className="pill-row">
          <button className={'pill' + (filter === 'all' ? ' active' : '')} onClick={() => setFilter('all')}>All</button>
          <button className={'pill' + (filter === 'unanswered' ? ' active' : '')} onClick={() => setFilter('unanswered')}>⚠ Unanswered ({data.unanswered})</button>
        </div>
      </div>

      {data.count === 0 ? (
        <div className="card">
          <Empty art="⭐" title="No reviews yet" body="Reviews build buyer trust. They'll appear here as customers rate your products." />
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: 18, alignItems: 'start' }}>
          <div className="card card-pad sticky-panel">
            <div style={{ textAlign: 'center', marginBottom: 16 }}>
              <div style={{ fontSize: 40, fontWeight: 800 }}>{avg.toFixed(1)}</div>
              <Stars rating={avg} />
              <div style={{ fontSize: 12, color: 'var(--ink-400)', marginTop: 4 }}>{data.count} reviews</div>
            </div>
            {dist.map((d) => (
              <div key={d.star} style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6, fontSize: 12 }}>
                <span style={{ width: 26 }}>{d.star}★</span>
                <div className="progress" style={{ flex: 1 }}><div style={{ width: `${(d.count / data.count) * 100}%` }} /></div>
                <span style={{ width: 22, textAlign: 'right', color: 'var(--ink-400)' }}>{d.count}</span>
              </div>
            ))}
          </div>

          <div style={{ display: 'grid', gap: 12 }}>
            {items.length === 0 && <div className="card"><Empty art="✅" title="All caught up" body="Every review has a response. Nice work!" /></div>}
            {items.map((r) => (
              <div key={r.id} className="card card-pad">
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
                  <div>
                    <Stars rating={r.rating} />
                    <strong style={{ display: 'block', marginTop: 4 }}>{r.product.name}</strong>
                    <span style={{ fontSize: 12, color: 'var(--ink-400)' }}>{r.author} · {dateFmt(r.date)}</span>
                  </div>
                  {r.responded ? <span className="badge badge-ok">Responded</span> : <span className="badge badge-warn">Needs reply</span>}
                </div>
                <p style={{ fontSize: 13.5, margin: '10px 0', color: 'var(--ink-700)' }}>{r.content}</p>
                {!r.responded && replyTo !== r.id && (
                  <button className="btn btn-sm" onClick={() => { setReplyTo(r.id); setReply('') }}>↩ Reply</button>
                )}
                {replyTo === r.id && (
                  <div style={{ marginTop: 8 }}>
                    <textarea className="textarea" rows={3} autoFocus value={reply} onChange={(e) => setReply(e.target.value)} placeholder="Thank the buyer, address concerns…" />
                    <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                      <button className="btn btn-primary btn-sm" disabled={busy || !reply.trim()} onClick={() => sendReply(r.id)}>Post Reply</button>
                      <button className="btn btn-sm" onClick={() => setReplyTo(null)}>Cancel</button>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
      {toast.node}
    </div>
  )
}
