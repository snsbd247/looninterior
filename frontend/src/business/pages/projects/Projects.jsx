import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FiPlus, FiSearch } from 'react-icons/fi'
import { formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Money, Status } from '../../components/ui'
import { label, useList } from '../../lib'

export const P_STATUSES = ['planning', 'running', 'on_hold', 'completed', 'cancelled']

export function Progress({ value }) {
  return <div className="b-progress" title={`${value}%`}><span style={{ width: `${Math.min(100, value || 0)}%` }} /><em>{value || 0}%</em></div>
}

export default function Projects() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const list = useList('/projects', { q: '', status: '' }, { toast })

  return (
    <>
      <div className="a-page-head">
        <h1>Projects</h1>
        <Can perm="projects.manage"><Link to="/admin/biz/projects/new" className="a-btn a-btn-primary"><FiPlus /> New Project</Link></Can>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search code, title, customer…" />
          </form>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}>
            <option value="">All status</option>
            {P_STATUSES.map((s) => <option key={s} value={s}>{label(s)}</option>)}
          </select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Project</th><th>Customer</th><th>Status</th><th>Progress</th><th>Manager</th><th>Dates</th><th className="r">Contract</th></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((p) => (
                  <tr key={p.id} className="b-clickable" onClick={() => navigate(`/admin/biz/projects/${p.id}`)}>
                    <td><b>{p.title}</b><div className="a-muted b-sub">{p.code}</div></td>
                    <td>{p.customer?.name}</td>
                    <td><Status value={p.status} /></td>
                    <td style={{ minWidth: 110 }}><Progress value={p.progress} /></td>
                    <td>{p.manager?.name || '—'}</td>
                    <td className="b-nowrap b-sub">{formatDate(p.start_date) || '—'}{p.end_date ? ` → ${formatDate(p.end_date)}` : ''}</td>
                    <td className="r"><Money value={p.contract_value} /></td>
                  </tr>
                )) : <tr><td colSpan={7} className="a-empty">No projects yet. Approve a quotation and click “Create Project”.</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
