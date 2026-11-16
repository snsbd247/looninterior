import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { FiArrowLeft, FiDownload } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Stat } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { label, money, today } from '../../lib'

const monthStart = () => today().slice(0, 8) + '01'

export default function AccountLedger() {
  const { id } = useParams()
  const { toast } = useAdmin()
  const [range, setRange] = useState({ from: monthStart(), to: today() })
  const [data, setData] = useState(null)
  const load = useCallback(() => {
    businessApi.get(`/accounts/${id}`, { params: range }).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, range, toast])
  useEffect(() => { load() }, [load])
  if (!data) return <Loader />
  const a = data.data

  return (
    <>
      <div className="a-page-head">
        <h1>{a.name} <span className="a-muted b-sub">{label(a.type)} · {a.branch?.name}</span></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/accounts" className="a-btn a-btn-light"><FiArrowLeft /> Accounts</Link>
          <button className="a-btn a-btn-light" onClick={() => downloadCsv(`/accounts/${id}`, range, `ledger-${a.name}.csv`, toast)}><FiDownload /> CSV</button>
        </div>
      </div>
      <div className="b-stats">
        <Stat label="Current balance" value={money(a.balance, { symbol: true })} tone="good" />
        <Stat label={`Opening (${formatDate(data.from)})`} value={money(data.opening)} />
        <Stat label="In" value={money(data.total_in)} tone="good" />
        <Stat label="Out" value={money(data.total_out)} tone="warn" />
        <Stat label={`Closing (${formatDate(data.to)})`} value={money(data.closing)} />
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <label className="b-inline-field">From <input type="date" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} /></label>
          <label className="b-inline-field">To <input type="date" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} /></label>
        </div>
        <div className="a-table-wrap">
          <table className="a-table">
            <thead><tr><th>Date</th><th>Description</th><th>Category</th><th>Ref</th><th className="r">In</th><th className="r">Out</th><th className="r">Balance</th></tr></thead>
            <tbody>
              <tr className="b-items-section"><td colSpan={6}>Opening balance</td><td className="r">{money(data.opening)}</td></tr>
              {data.lines.map((l) => (
                <tr key={l.id}>
                  <td className="b-nowrap">{formatDate(l.date)}</td>
                  <td>{l.description}{l.project && <div className="a-muted b-sub">{l.project.code}</div>}</td>
                  <td>{l.category?.name || label(l.type)}</td>
                  <td>{l.reference || ''}</td>
                  <td className="r b-in">{l.in ? money(l.in) : ''}</td>
                  <td className="r b-out">{l.out ? money(l.out) : ''}</td>
                  <td className="r"><b>{money(l.balance)}</b></td>
                </tr>
              ))}
              {!data.lines.length && <tr><td colSpan={7} className="a-empty">No entries in this period</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
