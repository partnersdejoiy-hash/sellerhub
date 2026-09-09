import { api, Finance as FinanceData } from '../api'
import { Empty, ErrorBox, Loading, Stat, fullMoney, money, useAsync } from '../ui'

export default function Finance() {
  const { data, loading, error, reload } = useAsync(() => api.finance(), [])

  if (loading && !data) return <Loading label="Crunching your numbers…" />
  if (error) return <ErrorBox message={error} retry={reload} />
  if (!data) return null

  return (
    <div>
      <div className="page-head">
        <div>
          <h1>Finance</h1>
          <p>
            {data.ledger
              ? 'Commission ledger data from WCFM marketplace engine.'
              : 'Order-derived earnings. WCFM commission ledger is not active, so net commission figures are not shown.'}
          </p>
        </div>
        <span className={'badge ' + (data.ledger ? 'badge-ok' : 'badge-muted')}>
          {data.ledger ? '● Ledger connected' : '● Order-derived'}
        </span>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(170px, 1fr))', gap: 12, marginBottom: 18 }}>
        <Stat label="Revenue · 30 days" value={money(data.revenue30)} />
        <Stat label="Revenue · 90 days" value={money(data.revenue90)} />
        <Stat label="Refunded · 30 days" value={money(data.refund30)} />
        {data.commission30 !== null && <Stat label="Commission · 30d" value={money(data.commission30)} sub="Platform commission" />}
        {data.netEarnings30 !== null && <Stat label="Net earnings · 30d" value={money(data.netEarnings30)} />}
        {data.pendingPayout !== null && <Stat label="Pending payout" value={fullMoney(data.pendingPayout)} />}
        {data.totalPaid !== null && <Stat label="Total paid out" value={fullMoney(data.totalPaid)} />}
      </div>

      {!data.ledger && (
        <div className="card card-pad" style={{ display: 'flex', gap: 14, alignItems: 'flex-start' }}>
          <span style={{ fontSize: 24 }}>ℹ️</span>
          <div>
            <strong style={{ display: 'block', marginBottom: 4 }}>Commission ledger not connected</strong>
            <p style={{ fontSize: 13, color: 'var(--ink-500)', maxWidth: 560 }}>
              Your marketplace's WCFM commission module hasn't recorded any ledger entries yet. Once commissions are
              configured and orders flow through the ledger, your net earnings, payouts and withdrawal status will
              appear here automatically. Revenue and refunds above are always real order data.
            </p>
          </div>
        </div>
      )}

      {data.ledger && data.pendingPayout === 0 && data.totalPaid === 0 && (
        <Empty art="🏦" title="No withdrawals yet" body="When you request your first payout from your earnings, its status will show here." />
      )}
    </div>
  )
}
