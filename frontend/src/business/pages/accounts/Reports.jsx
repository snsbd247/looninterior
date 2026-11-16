import { useCallback, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { FiDownload, FiPrinter } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Money, Stat, Status } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { label, money, today } from '../../lib'
import { Progress } from '../projects/Projects'

const monthStart = () => today().slice(0, 8) + '01'

const TABS = [
  ['cashbook', 'Cash Book', '/reports/cashbook'],
  ['summary', 'Income & Expense', '/reports/summary'],
  ['pnl', 'Profit & Loss (yearly)', '/reports/profit-loss'],
  ['projects', 'Project Profit', '/reports/projects'],
  ['branches', 'Branch Comparison', '/reports/branches'],
]

/** Simple horizontal bars (no chart library). */
function Bars({ rows, keys }) {
  const max = Math.max(1, ...rows.flatMap((r) => keys.map(([k]) => Math.abs(r[k] || 0))))
  return (
    <div className="b-bars">
      {rows.map((r) => (
        <div key={r.month || r.code} className="b-bars-row">
          <span className="b-bars-label">{r.month || r.code}</span>
          <div className="b-bars-track">
            {keys.map(([k, , cls]) => <div key={k} className={`b-bar ${cls}`} style={{ width: `${(Math.abs(r[k] || 0) / max) * 100}%` }} title={`${k}: ${money(r[k])}`} />)}
          </div>
        </div>
      ))}
      <div className="b-bars-legend">{keys.map(([k, t, cls]) => <span key={k}><i className={`b-bar ${cls}`} />{t}</span>)}</div>
    </div>
  )
}

export default function Reports() {
  const { toast, branch } = useAdmin()
  const [params, setParams] = useSearchParams()
  const tab = params.get('tab') || 'cashbook'
  const [range, setRange] = useState({ from: monthStart(), to: today() })
  const [year, setYear] = useState(new Date().getFullYear())
  const [data, setData] = useState(null)
  const current = TABS.find((t) => t[0] === tab) || TABS[0]
  const query = tab === 'pnl' ? { year } : tab === 'projects' ? {} : range

  const load = useCallback(() => {
    setData(null)
    businessApi.get(current[2], { params: query }).then((r) => setData(r.data)).catch((e) => { toast(errorMessage(e), 'error'); setData({}) })
  }, [current, JSON.stringify(query), toast]) // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => { load() }, [load])

  return (
    <>
      <div className="a-page-head">
        <h1>Reports {branch === 'all' && <span className="a-muted b-sub">· all branches</span>}</h1>
        <div className="b-head-actions">
          <button className="a-btn a-btn-light" onClick={() => window.print()}><FiPrinter /> Print</button>
          <button className="a-btn a-btn-light" onClick={() => downloadCsv(current[2], query, `${current[0]}.csv`, toast)}><FiDownload /> Excel (CSV)</button>
        </div>
      </div>
      <div className="a-tabs b-tabs">{TABS.map(([k, t]) => <button key={k} className={tab === k ? 'is-active' : ''} onClick={() => setParams({ tab: k }, { replace: true })}>{t}</button>)}</div>
      <div className="a-panel">
        <div className="a-toolbar">
          {tab === 'pnl' ? (
            <label className="b-inline-field">Year <select value={year} onChange={(e) => setYear(e.target.value)}>{[0, 1, 2, 3].map((n) => { const y = new Date().getFullYear() - n; return <option key={y} value={y}>{y}</option> })}</select></label>
          ) : tab !== 'projects' && (
            <>
              <label className="b-inline-field">From <input type="date" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} /></label>
              <label className="b-inline-field">To <input type="date" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} /></label>
            </>
          )}
        </div>
        {!data ? <Loader /> : (
          <div className="b-pad">
            {tab === 'cashbook' && data.lines && (
              <>
                <div className="b-stats"><Stat label="Opening" value={money(data.opening)} /><Stat label="In" value={money(data.total_in)} tone="good" /><Stat label="Out" value={money(data.total_out)} tone="warn" /><Stat label="Closing" value={money(data.closing)} /></div>
                <div className="a-table-wrap"><table className="a-table">
                  <thead><tr><th>Date</th><th>Account</th><th>Category</th><th>Description</th><th className="r">In</th><th className="r">Out</th><th className="r">Balance</th></tr></thead>
                  <tbody>
                    {data.lines.map((l, i) => <tr key={i}><td className="b-nowrap">{formatDate(l.date)}</td><td>{l.account}</td><td>{l.category || label(l.type)}</td><td>{l.description}</td><td className="r b-in">{l.in ? money(l.in) : ''}</td><td className="r b-out">{l.out ? money(l.out) : ''}</td><td className="r">{money(l.balance)}</td></tr>)}
                    {!data.lines.length && <tr><td colSpan={7} className="a-empty">No entries</td></tr>}
                  </tbody>
                </table></div>
              </>
            )}
            {tab === 'summary' && data.rows && (
              <>
                <div className="b-stats"><Stat label="Income" value={money(data.income, { symbol: true })} tone="good" /><Stat label="Expense" value={money(data.expense, { symbol: true })} tone="warn" /><Stat label="Net" value={money(data.net, { symbol: true })} tone={data.net >= 0 ? 'good' : 'bad'} /></div>
                <div className="b-two-col">
                  {['income', 'expense'].map((type) => (
                    <div key={type}>
                      <h4 className="b-mini-title">{label(type)}</h4>
                      <table className="a-table"><tbody>
                        {data.rows.filter((r) => r.type === type).map((r) => <tr key={r.category}><td>{r.category}</td><td className="a-muted r">{r.entries}</td><td className="r"><Money value={r.amount} /></td></tr>)}
                        {!data.rows.some((r) => r.type === type) && <tr><td className="a-empty">None</td></tr>}
                      </tbody></table>
                    </div>
                  ))}
                </div>
              </>
            )}
            {tab === 'pnl' && data.months && (
              <>
                <div className="b-stats"><Stat label="Sales (invoiced)" value={money(data.totals.sales, { symbol: true })} /><Stat label="Collected income" value={money(data.totals.income, { symbol: true })} tone="good" /><Stat label="Expense" value={money(data.totals.expense, { symbol: true })} tone="warn" /><Stat label="Net profit (cash)" value={money(data.totals.net, { symbol: true })} tone={data.totals.net >= 0 ? 'good' : 'bad'} /></div>
                <Bars rows={data.months} keys={[['income', 'Income', 'is-in'], ['expense', 'Expense', 'is-out']]} />
                <div className="a-table-wrap b-mt"><table className="a-table">
                  <thead><tr><th>Month</th><th className="r">Sales</th><th className="r">Income</th><th className="r">Expense</th><th className="r">Net</th></tr></thead>
                  <tbody>{data.months.map((m) => <tr key={m.month}><td>{m.month}</td><td className="r">{money(m.sales)}</td><td className="r b-in">{money(m.income)}</td><td className="r b-out">{money(m.expense)}</td><td className="r"><Money value={m.net} /></td></tr>)}</tbody>
                </table></div>
              </>
            )}
            {tab === 'projects' && data.data && (
              <>
                <div className="b-stats"><Stat label="Contract value" value={money(data.totals.contract, { symbol: true })} /><Stat label="Received" value={money(data.totals.received, { symbol: true })} tone="good" /><Stat label="Cost" value={money(data.totals.cost, { symbol: true })} tone="warn" /><Stat label="Profit" value={money(data.totals.profit, { symbol: true })} tone={data.totals.profit >= 0 ? 'good' : 'bad'} /></div>
                <div className="a-table-wrap"><table className="a-table">
                  <thead><tr><th>Project</th><th>Status</th><th>Progress</th><th className="r">Contract</th><th className="r">Received</th><th className="r">Cost</th><th className="r">Profit</th><th className="r">Margin</th></tr></thead>
                  <tbody>{data.data.map((p) => (
                    <tr key={p.id}>
                      <td><Link to={`/admin/biz/projects/${p.id}`}><b>{p.title}</b></Link><div className="a-muted b-sub">{p.code} · {p.customer}</div></td>
                      <td><Status value={p.status} /></td><td style={{ minWidth: 100 }}><Progress value={p.progress} /></td>
                      <td className="r">{money(p.contract)}</td><td className="r b-in">{money(p.received)}</td><td className="r b-out">{money(p.cost)}</td>
                      <td className="r"><Money value={p.profit} /></td><td className="r">{p.contract ? Math.round((p.profit / p.contract) * 100) : 0}%</td>
                    </tr>
                  ))}{!data.data.length && <tr><td colSpan={8} className="a-empty">No projects</td></tr>}</tbody>
                </table></div>
              </>
            )}
            {tab === 'branches' && data.data && (
              <>
                {branch !== 'all' && <div className="b-callout is-warn">Select “All branches (central)” in the top bar to compare every branch.</div>}
                <Bars rows={data.data} keys={[['collected', 'Collected', 'is-in'], ['expense', 'Expense', 'is-out']]} />
                <div className="a-table-wrap b-mt"><table className="a-table">
                  <thead><tr><th>Branch</th><th className="r">Sales</th><th className="r">Collected</th><th className="r">Income</th><th className="r">Expense</th><th className="r">Net</th><th className="r">Cash & bank now</th></tr></thead>
                  <tbody>{data.data.map((b) => <tr key={b.code}><td><b>{b.branch}</b> <span className="a-muted">{b.code}</span></td><td className="r">{money(b.sales)}</td><td className="r b-in">{money(b.collected)}</td><td className="r">{money(b.income)}</td><td className="r b-out">{money(b.expense)}</td><td className="r"><Money value={b.net} /></td><td className="r">{money(b.cash)}</td></tr>)}</tbody>
                </table></div>
              </>
            )}
          </div>
        )}
      </div>
    </>
  )
}
