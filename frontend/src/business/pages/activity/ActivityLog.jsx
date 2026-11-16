import { Fragment, useCallback, useEffect, useState } from 'react'
import { FiSearch, FiChevronDown, FiChevronRight } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'

const ACTIONS = ['created', 'updated', 'deleted', 'login', 'password_changed']

const fmt = (v) => {
  if (v === null || v === undefined || v === '') return '—'
  if (typeof v === 'object') return JSON.stringify(v)
  return String(v)
}

function Changes({ changes }) {
  const before = changes?.before || {}
  const after = changes?.after || {}
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])]
  if (!keys.length) return <span className="a-muted">No field details</span>
  return (
    <table className="b-changes">
      <thead><tr><th>Field</th><th>Before</th>{changes.after && <th>After</th>}</tr></thead>
      <tbody>
        {keys.map((k) => (
          <tr key={k}><td>{k}</td><td>{fmt(before[k])}</td>{changes.after && <td>{fmt(after[k])}</td>}</tr>
        ))}
      </tbody>
    </table>
  )
}

export default function ActivityLog() {
  const { toast } = useAdmin()
  const [rows, setRows] = useState(null)
  const [meta, setMeta] = useState(null)
  const [types, setTypes] = useState([])
  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [filters, setFilters] = useState({ q: '', action: '', subject_type: '', from: '', to: '' })
  const [open, setOpen] = useState(null)

  const load = useCallback(() => {
    businessApi.get('/activity-logs', { params: { page, ...filters } })
      .then((r) => { setRows(r.data.data); setMeta(r.data); setTypes(r.data.subject_types || []) })
      .catch((e) => { toast(errorMessage(e), 'error'); setRows([]) })
  }, [page, filters, toast])

  useEffect(() => { load() }, [load])

  const setFilter = (k) => (e) => { setPage(1); setFilters({ ...filters, [k]: e.target.value }) }

  return (
    <>
      <div className="a-page-head"><h1>Activity Log</h1></div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); setPage(1); setFilters({ ...filters, q }) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search description…" />
          </form>
          <select value={filters.action} onChange={setFilter('action')}>
            <option value="">All actions</option>
            {ACTIONS.map((a) => <option key={a} value={a}>{a.replace('_', ' ')}</option>)}
          </select>
          <select value={filters.subject_type} onChange={setFilter('subject_type')}>
            <option value="">All records</option>
            {types.map((t) => <option key={t} value={t}>{t}</option>)}
          </select>
          <input type="date" value={filters.from} onChange={setFilter('from')} title="From" />
          <input type="date" value={filters.to} onChange={setFilter('to')} title="To" />
        </div>
        {rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th style={{ width: 28 }} /><th>Time</th><th>User</th><th>Action</th><th>Description</th><th>Branch</th></tr></thead>
              <tbody>
                {rows.length ? rows.map((r) => (
                  <Fragment key={r.id}>
                    <tr className={r.changes ? 'b-clickable' : ''} onClick={() => r.changes && setOpen(open === r.id ? null : r.id)}>
                      <td>{r.changes ? (open === r.id ? <FiChevronDown /> : <FiChevronRight />) : null}</td>
                      <td className="b-nowrap">{new Date(r.created_at).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}</td>
                      <td>{r.user?.name || <span className="a-muted">System</span>}</td>
                      <td><span className={`a-status b-act-${r.action}`}>{r.action.replace('_', ' ')}</span></td>
                      <td>{r.description}</td>
                      <td>{r.branch?.code || <span className="a-muted">—</span>}</td>
                    </tr>
                    {open === r.id && (
                      <tr className="b-detail-row"><td /><td colSpan={5}><Changes changes={r.changes} /><div className="a-muted b-sub">IP: {r.ip || '—'}</div></td></tr>
                    )}
                  </Fragment>
                )) : <tr><td colSpan={6} className="a-empty">No activity found</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={meta} page={page} setPage={setPage} />
      </div>
    </>
  )
}
