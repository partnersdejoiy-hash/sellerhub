import { useRef, useState } from 'react'
import { api } from '../api'
import { useToast } from '../ui'

interface Msg { role: 'user' | 'joi'; text: string }

const SUGGESTIONS = [
  'Show my pending orders',
  'Which products are low on stock?',
  'How much did I sell this week?',
  'What needs attention?',
  'Which product sold the most?',
]

export default function Joi() {
  const [msgs, setMsgs] = useState<Msg[]>([
    { role: 'joi', text: 'Namaste! Main JOI hoon — aapka seller assistant. Main sirf aapke real store data se baat karta hoon. Poochho:' },
  ])
  const [input, setInput] = useState('')
  const [busy, setBusy] = useState(false)
  const listRef = useRef<HTMLDivElement>(null)
  const toast = useToast()

  async function send(text?: string) {
    const q = (text ?? input).trim()
    if (!q || busy) return
    const next = [...msgs, { role: 'user' as const, text: q }]
    setMsgs(next)
    setInput('')
    setBusy(true)
    try {
      const res = await api.ai(q)
      setMsgs([...next, { role: 'joi', text: res.reply }])
      setTimeout(() => listRef.current?.scrollTo({ top: 99999, behavior: 'smooth' }), 50)
    } catch (e: unknown) {
      toast.show((e as Error).message, true)
      setMsgs([...next, { role: 'joi', text: 'Sorry, data fetch fail hua. Thodi der baad try karein.' }])
    } finally {
      setBusy(false)
    }
  }

  return (
    <div style={{ maxWidth: 760, margin: '0 auto' }}>
      <div className="page-head">
        <div>
          <h1>🤖 JOI AI</h1>
          <p>Grounded in your live store data — JOI never invents numbers.</p>
        </div>
      </div>

      <div className="card" style={{ display: 'flex', flexDirection: 'column', height: 'calc(100vh - 220px)', minHeight: 420 }}>
        <div ref={listRef} style={{ flex: 1, overflowY: 'auto', padding: 20, display: 'grid', gap: 14, alignContent: 'start' }}>
          {msgs.map((m, i) => (
            <div key={i} style={{ display: 'flex', gap: 10, justifyContent: m.role === 'user' ? 'flex-end' : 'flex-start' }}>
              {m.role === 'joi' && <div style={{ width: 34, height: 34, borderRadius: 10, background: 'linear-gradient(135deg, var(--brand-500), var(--brand-700))', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 16, flexShrink: 0 }}>🤖</div>}
              <div style={{
                maxWidth: '78%', padding: '11px 15px', borderRadius: 16,
                background: m.role === 'user' ? 'linear-gradient(135deg, var(--brand-600), var(--brand-700))' : 'var(--ink-100)',
                color: m.role === 'user' ? '#fff' : 'var(--ink-900)',
                borderBottomRightRadius: m.role === 'user' ? 4 : 16,
                borderBottomLeftRadius: m.role === 'joi' ? 4 : 16,
                fontSize: 13.5, lineHeight: 1.55,
              }}>
                {m.text}
              </div>
            </div>
          ))}
          {busy && <div style={{ display: 'flex', gap: 10 }}><div style={{ width: 34, height: 34, borderRadius: 10, background: 'linear-gradient(135deg, var(--brand-500), var(--brand-700))', display: 'grid', placeItems: 'center', color: '#fff', fontSize: 16 }}>🤖</div><div style={{ background: 'var(--ink-100)', borderRadius: 16, borderBottomLeftRadius: 4, padding: '11px 15px' }}><span className="spinner" style={{ width: 14, height: 14, borderWidth: 2 }} /></div></div>}
        </div>

        <div style={{ borderTop: '1px solid var(--ink-100)', padding: 14 }}>
          <div style={{ display: 'flex', gap: 8, overflowX: 'auto', paddingBottom: 10, scrollbarWidth: 'none' }}>
            {SUGGESTIONS.map((s) => (
              <button key={s} className="pill" onClick={() => send(s)}>{s}</button>
            ))}
          </div>
          <div style={{ display: 'flex', gap: 10 }}>
            <input
              className="input"
              placeholder="Ask about your sales, stock, orders…"
              value={input}
              onChange={(e) => setInput(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && send()}
            />
            <button className="btn btn-primary" disabled={busy || !input.trim()} onClick={() => send()}>Send</button>
          </div>
        </div>
      </div>
      {toast.node}
    </div>
  )
}
