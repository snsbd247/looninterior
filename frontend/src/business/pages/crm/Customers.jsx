import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FiPlus, FiSearch } from 'react-icons/fi'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { useList } from '../../lib'

export default function Customers() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const list = useList('/customers', { q: '' }, { toast })
  const cols = list.rows?.[0] || {}

  return (
    <>
      <div className="a-page-head">
        <h1>Customers</h1>
        <Can perm="crm.manage"><Link to="/admin/biz/customers/new" className="a-btn a-btn-primary"><FiPlus /> Add Customer</Link></Can>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, phone, email, company…" />
          </form>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr>
                <th>Customer</th><th>Phone</th><th>Address</th>
                {'quotations_count' in cols && <th className="r">Quotations</th>}
                {'projects_count' in cols && <th className="r">Projects</th>}
                {'invoices_count' in cols && <th className="r">Invoices</th>}
              </tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((c) => (
                  <tr key={c.id} className="b-clickable" onClick={() => navigate(`/admin/biz/customers/${c.id}`)}>
                    <td><b>{c.name}</b>{c.company && <div className="a-muted b-sub">{c.company}</div>}</td>
                    <td>{c.phone || '—'}</td>
                    <td className="b-note-cell">{c.address || '—'}</td>
                    {'quotations_count' in cols && <td className="r">{c.quotations_count}</td>}
                    {'projects_count' in cols && <td className="r">{c.projects_count}</td>}
                    {'invoices_count' in cols && <td className="r">{c.invoices_count}</td>}
                  </tr>
                )) : <tr><td colSpan={6} className="a-empty">No customers yet. Customers are created when a lead is won.</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
