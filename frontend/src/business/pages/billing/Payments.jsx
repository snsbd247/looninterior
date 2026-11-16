import { useState } from 'react'
import { Link } from 'react-router-dom'
import { FiSearch, FiFileText } from 'react-icons/fi'
import { formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Money, Stat, Status } from '../../components/ui'
import { openPdf } from '../../components/DocActions'
import { label, money, useList } from '../../lib'
import { METHODS } from './InvoiceDetail'

export default function Payments() {
  const { toast } = useAdmin()
  const [q, setQ] = useState('')
  const list = useList('/payments', { q: '', method: '', status: '', from: '', to: '' }, { toast })
  const f = list.filters

  return (
    <>
      <div className="a-page-head"><h1>Payments Received</h1></div>
      {list.extra?.summary && <div className="b-stats"><Stat label="Received (filtered)" value={money(list.extra.summary.received, { symbol: true })} tone="good" /></div>}
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Receipt no., reference, customer…" />
          </form>
          <select value={f.method} onChange={(e) => list.setFilter('method', e.target.value)}>
            <option value="">All methods</option>{METHODS.map((m) => <option key={m} value={m}>{label(m)}</option>)}
          </select>
          <select value={f.status} onChange={(e) => list.setFilter('status', e.target.value)}>
            <option value="">All</option><option value="received">Received</option><option value="cancelled">Cancelled</option>
          </select>
          <input type="date" value={f.from} onChange={(e) => list.setFilter('from', e.target.value)} title="From" />
          <input type="date" value={f.to} onChange={(e) => list.setFilter('to', e.target.value)} title="To" />
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Receipt</th><th>Date</th><th>Customer</th><th>Invoice</th><th>Method</th><th>Account</th><th>Status</th><th className="r">Amount</th><th /></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((p) => (
                  <tr key={p.id}>
                    <td><b>{p.number}</b>{p.reference && <div className="a-muted b-sub">{p.reference}</div>}</td>
                    <td className="b-nowrap">{formatDate(p.date)}</td>
                    <td>{p.customer?.name}</td>
                    <td><Link to={`/admin/biz/invoices/${p.invoice_id}`}>{p.invoice?.number}</Link></td>
                    <td>{label(p.method)}</td><td>{p.account?.name}</td>
                    <td><Status value={p.status} /></td>
                    <td className="r"><Money value={p.amount} /></td>
                    <td className="a-actions"><button className="a-icon-btn" title="Receipt PDF" onClick={() => openPdf(`/payments/${p.id}/pdf`, 'en', toast)}><FiFileText /></button></td>
                  </tr>
                )) : <tr><td colSpan={9} className="a-empty">No payments</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
