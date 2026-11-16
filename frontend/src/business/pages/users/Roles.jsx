import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiPlus, FiEdit2, FiTrash2, FiLock } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'

export default function Roles() {
  const { toast } = useAdmin()
  const [roles, setRoles] = useState(null)
  const [catalog, setCatalog] = useState([])

  const load = () => businessApi.get('/roles').then((r) => setRoles(r.data.data)).catch((e) => { toast(errorMessage(e), 'error'); setRoles([]) })

  useEffect(() => {
    load()
    businessApi.get('/permissions').then((r) => setCatalog(r.data.data)).catch(() => {})
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  const remove = async (role) => {
    if (!window.confirm(`Delete the role "${role.name}"?`)) return
    try {
      await businessApi.delete(`/roles/${role.id}`)
      toast('Role deleted')
      load()
    } catch (e) {
      toast(errorMessage(e), 'error')
    }
  }

  if (!roles) return <Loader />

  // Which modules a role can use (for a quick overview)
  const summary = (role) => catalog
    .map((m) => {
      const keys = m.actions.map((a) => a.key)
      const has = keys.filter((k) => role.permissions.includes(k))
      if (!has.length) return null
      const manage = has.some((k) => !k.endsWith('.view'))
      return { label: m.label, level: manage ? 'full' : 'view' }
    })
    .filter(Boolean)

  return (
    <>
      <div className="a-page-head">
        <h1>Roles & Permissions</h1>
        <Link to="/admin/biz/roles/new" className="a-btn a-btn-primary"><FiPlus /> Add Role</Link>
      </div>
      <div className="b-role-grid">
        {roles.map((r) => (
          <div key={r.id} className="a-panel b-role">
            <div className="b-role-head">
              <h3>{r.name} {r.is_system && <FiLock title="System role" />}</h3>
              <span className="a-muted">{r.users_count} user{r.users_count === 1 ? '' : 's'}</span>
            </div>
            <div className="b-role-mods">
              {r.all ? <span className="b-chip b-chip-full">Everything</span> : summary(r).map((m) => (
                <span key={m.label} className={`b-chip ${m.level === 'full' ? 'b-chip-full' : ''}`} title={m.level === 'full' ? 'Can change' : 'View only'}>
                  {m.label}{m.level === 'view' ? ' (view)' : ''}
                </span>
              ))}
              {!r.all && !r.permissions.length && <span className="a-muted">No permissions</span>}
            </div>
            <div className="b-role-actions">
              {r.is_system ? <span className="a-muted b-sub">The Admin role always has every permission.</span> : (
                <>
                  <Link to={`/admin/biz/roles/${r.id}`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>
                  <button className="a-btn a-btn-light danger" onClick={() => remove(r)} disabled={r.users_count > 0} title={r.users_count > 0 ? 'Move its users to another role first' : 'Delete'}><FiTrash2 /></button>
                </>
              )}
            </div>
          </div>
        ))}
      </div>
    </>
  )
}
