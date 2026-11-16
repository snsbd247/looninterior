import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiPlus, FiEdit2, FiSearch } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { DeleteButton } from '../../components/ui'

export default function Users() {
  const { toast } = useAdmin()
  const [rows, setRows] = useState(null)
  const [meta, setMeta] = useState(null)
  const [roles, setRoles] = useState([])
  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [search, setSearch] = useState('')
  const [roleId, setRoleId] = useState('')
  const [status, setStatus] = useState('')

  useEffect(() => {
    businessApi.get('/roles').then((r) => setRoles(r.data.data)).catch(() => {})
  }, [])

  const load = useCallback(() => {
    businessApi.get('/users', { params: { page, q: search, role_id: roleId, status } })
      .then((r) => { setRows(r.data.data); setMeta(r.data) })
      .catch((e) => { toast(errorMessage(e), 'error'); setRows([]) })
  }, [page, search, roleId, status, toast])

  useEffect(() => { load() }, [load])

  return (
    <>
      <div className="a-page-head">
        <h1>Users</h1>
        <Link to="/admin/biz/users/new" className="a-btn a-btn-primary"><FiPlus /> Add User</Link>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); setPage(1); setSearch(q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, email, phone…" />
          </form>
          <select value={roleId} onChange={(e) => { setRoleId(e.target.value); setPage(1) }}>
            <option value="">All roles</option>
            {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
          </select>
          <select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
        {rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Name</th><th>Role</th><th>Branches</th><th>Status</th><th>Last Login</th><th className="a-actions-col">Actions</th></tr></thead>
              <tbody>
                {rows.length ? rows.map((u) => (
                  <tr key={u.id}>
                    <td><Link to={`/admin/biz/users/${u.id}`}><b>{u.name}</b></Link><div className="a-muted b-sub">{u.email}{u.phone ? ` · ${u.phone}` : ''}</div></td>
                    <td>{u.role?.name || '—'}</td>
                    <td>{u.branches?.length ? u.branches.map((b) => <span key={b.id} className="b-chip">{b.code}</span>) : '—'}</td>
                    <td><span className={`a-status ${u.is_active ? 's-active' : 's-inactive'}`}>{u.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>{u.last_login_at ? formatDate(u.last_login_at) : <span className="a-muted">Never</span>}</td>
                    <td className="a-actions"><Link to={`/admin/biz/users/${u.id}`} className="a-icon-btn" title="Edit"><FiEdit2 /></Link><DeleteButton icon path={`/users/${u.id}`} what="user" onDone={load} /></td>
                  </tr>
                )) : <tr><td colSpan={6} className="a-empty">No users found</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={meta} page={page} setPage={setPage} />
      </div>
    </>
  )
}
