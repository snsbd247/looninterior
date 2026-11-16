import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FiPlus, FiSearch } from 'react-icons/fi'
import { formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Money, Status } from '../../components/ui'
import { label, useList } from '../../lib'

export const Q_STATUSES = ['draft', 'sent', 'approved', 'rejected', 'revised', 'cancelled']

export default function Quotations() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const list = useList('/quotations', { q: '', status: '' }, { toast })

  return (
    <>
      <div className="a-page-head">
        <h1>Quotations</h1>
        <Can perm="quotations.manage"><Link to="/admin/biz/quotations/new" className="a-btn a-btn-primary"><FiPlus /> New Quotation</Link></Can>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search number, title, customer…" />
          </form>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}>
            <option value="">Latest versions</option>
            {Q_STATUSES.map((s) => <option key={s} value={s}>{label(s)}</option>)}
          </select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Number</th><th>Customer</th><th>Title</th><th>Date</th><th>Status</th><th className="r">Total</th></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((x) => (
                  <tr key={x.id} className="b-clickable" onClick={() => navigate(`/admin/biz/quotations/${x.id}`)}>
                    <td><b>{x.number}</b>{x.branch && <div className="a-muted b-sub">{x.branch.code}</div>}</td>
                    <td>{x.customer?.name}<div className="a-muted b-sub">{x.customer?.phone}</div></td>
                    <td>{x.title || '—'}</td>
                    <td className="b-nowrap">{formatDate(x.date)}</td>
                    <td><Status value={x.status} /></td>
                    <td className="r"><Money value={x.total} /></td>
                  </tr>
                )) : <tr><td colSpan={6} className="a-empty">No quotations yet</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
