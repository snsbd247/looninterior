import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'

export default function RoleForm() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const [catalog, setCatalog] = useState(null)
  const [name, setName] = useState('')
  const [perms, setPerms] = useState([])
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    businessApi.get('/permissions').then((r) => setCatalog(r.data.data)).catch(() => setCatalog([]))
    if (isEdit) {
      businessApi.get(`/roles/${id}`).then((r) => {
        if (r.data.data.is_system) { navigate('/admin/biz/roles'); return }
        setName(r.data.data.name)
        setPerms(r.data.data.permissions || [])
      }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/roles') })
    }
  }, [id, isEdit, navigate, toast])

  if (!catalog) return <Loader />

  const toggle = (key) => setPerms((p) => (p.includes(key) ? p.filter((x) => x !== key) : [...p, key]))
  const toggleModule = (m) => {
    const keys = m.actions.map((a) => a.key)
    const allOn = keys.every((k) => perms.includes(k))
    setPerms((p) => (allOn ? p.filter((k) => !keys.includes(k)) : [...new Set([...p, ...keys])]))
  }

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const res = await businessApi.post(isEdit ? `/roles/${id}` : '/roles', { name, permissions: perms })
      toast(res.data.message)
      navigate('/admin/biz/roles')
    } catch (ex) {
      setErrors(fieldErrors(ex))
      toast(errorMessage(ex), 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Role' : 'Add Role'}</h1>
        <Link to="/admin/biz/roles" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <label className="a-field b-role-name">
          <span>Role Name *</span>
          <input value={name} onChange={(e) => setName(e.target.value)} />
          {errors.name && <small className="field-error">{errors.name}</small>}
        </label>

        <h3 className="b-section-title">Permissions</h3>
        <p className="a-muted b-sub">Tick what this role may do. Changes apply to every user with this role on their next page load.</p>
        <div className="b-perm-grid">
          {catalog.map((m) => {
            const keys = m.actions.map((a) => a.key)
            const on = keys.filter((k) => perms.includes(k)).length
            return (
              <div key={m.key} className={`b-perm ${on ? 'is-on' : ''}`}>
                <label className="b-perm-head">
                  <input type="checkbox" checked={on === keys.length} ref={(el) => { if (el) el.indeterminate = on > 0 && on < keys.length }} onChange={() => toggleModule(m)} />
                  <b>{m.label}</b>
                </label>
                {m.actions.map((a) => (
                  <label key={a.key} className="b-perm-item">
                    <input type="checkbox" checked={perms.includes(a.key)} onChange={() => toggle(a.key)} />
                    <span><b>{a.action}</b> · {a.label}</span>
                  </label>
                ))}
              </div>
            )
          })}
        </div>
        {errors.permissions && <small className="field-error">{errors.permissions}</small>}

        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Role'}</button>
          <span className="a-muted">{perms.length} permission{perms.length === 1 ? '' : 's'} selected</span>
        </div>
      </form>
    </>
  )
}
