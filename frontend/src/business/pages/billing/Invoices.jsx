import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FiPlus, FiSearch } from 'react-icons/fi'
import { formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Money, Stat, Status } from '../../components/ui'
import { label, money, useList } from '../../lib'

export default function Invoices() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const list = useList('/invoices', { q: '', status: '' }, { toast })
  const s = list.extra?.summary

  return (
    <>
      <div className="a-page-head">
        <h1>Invoices</h1>
        <Can perm="billing.manage"><Link to="/admin/biz/invoices/new" className="a-btn a-btn-primary"><FiPlus /> New Invoice</Link></Can>
      </div>
      {s && (
        <div className="b-stats">
          <Stat label="Billed (filtered)" value={money(s.total, { symbol: true })} />
          <Stat label="Received" value={money(s.paid, { symbol: true })} tone="good" />
          <Stat label="Outstanding" value={money(s.due, { symbol: true })} tone={s.due > 0 ? 'warn' : ''} />
        </div>
      )}
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search number, customer…" />
          </form>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}>
            <option value="">All invoices</option>
            <option value="unpaid">Unpaid (issued + partial)</option>
            <option value="overdue">Overdue</option>
            {['draft', 'issued', 'partial', 'paid', 'cancelled'].map((x) => <option key={x} value={x}>{label(x)}</option>)}
          </select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Due date</th><th>Status</th><th className="r">Total</th><th className="r">Due</th></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((i) => (
                  <tr key={i.id} className="b-clickable" onClick={() => navigate(`/admin/biz/invoices/${i.id}`)}>
                    <td><b>{i.number}</b>{i.project && <div className="a-muted b-sub">{i.project.code}</div>}</td>
                    <td>{i.customer?.name}</td>
                    <td className="b-nowrap">{formatDate(i.date)}</td>
                    <td className={`b-nowrap ${i.is_overdue ? 'b-overdue' : ''}`}>{formatDate(i.due_date) || '—'}</td>
                    <td><Status value={i.is_overdue ? 'overdue' : i.status} text={i.is_overdue ? 'Overdue' : undefined} /></td>
                    <td className="r"><Money value={i.total} /></td>
                    <td className="r"><Money value={i.due} /></td>
                  </tr>
                )) : <tr><td colSpan={7} className="a-empty">No invoices</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
