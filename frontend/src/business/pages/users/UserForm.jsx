import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { adminApi, businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'

const EMPTY = { name: '', email: '', phone: '', mailbox: '', role_id: '', branch_ids: [], is_active: true, password: '', password_confirmation: '' }

export default function UserForm() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast, user: me } = useAdmin()
  const [form, setForm] = useState(isEdit ? null : EMPTY)
  const [roles, setRoles] = useState([])
  const [branches, setBranches] = useState([])
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const self = isEdit && Number(id) === me.id

  useEffect(() => {
    businessApi.get('/roles').then((r) => setRoles(r.data.data)).catch(() => {})
    // All branches (active and inactive) so existing assignments stay visible.
    adminApi.get('/branches', { params: { all: 1 } })
      .then((r) => setBranches(r.data.data))
      .catch(() => setBranches(me.branches || []))
    if (isEdit) {
      businessApi.get(`/users/${id}`).then((r) => {
        const u = r.data.data
        setForm({
          name: u.name, email: u.email, phone: u.phone || '', mailbox: u.mailbox || '', role_id: u.role_id || '',
          branch_ids: (u.branches || []).map((b) => b.id), is_active: u.is_active !== false,
          password: '', password_confirmation: '',
        })
      }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/users') })
    }
  }, [id, isEdit, navigate, toast, me.branches])

  if (!form) return <Loader />

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.type === 'checkbox' ? e.target.checked : e.target.value })
  const toggleBranch = (bid) => setForm({
    ...form,
    branch_ids: form.branch_ids.includes(bid) ? form.branch_ids.filter((x) => x !== bid) : [...form.branch_ids, bid],
  })
  const err = (k) => errors[k] && <small className="field-error">{errors[k]}</small>

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const payload = { ...form }
      if (isEdit && !payload.password) { delete payload.password; delete payload.password_confirmation }
      const res = await businessApi.post(isEdit ? `/users/${id}` : '/users', payload)
      toast(res.data.message)
      navigate('/admin/biz/users')
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
        <h1>{isEdit ? 'Edit User' : 'Add User'}</h1>
        <Link to="/admin/biz/users" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <label className="a-field half"><span>Full Name *</span><input value={form.name} onChange={set('name')} />{err('name')}</label>
          <label className="a-field half"><span>Email (login) *</span><input type="email" value={form.email} onChange={set('email')} />{err('email')}</label>
          <label className="a-field half"><span>Phone</span><input value={form.phone} onChange={set('phone')} />{err('phone')}</label>
          <label className="a-field half"><span>Mailbox (Webmail)</span><input type="email" value={form.mailbox} onChange={set('mailbox')} placeholder="name@looninterior.com" /><small className="a-help">The user's company e-mail account for Mail → Webmail. Empty = the login e-mail, if it is on the company domain.</small>{err('mailbox')}</label>
          <label className="a-field half">
            <span>Role *</span>
            <select value={form.role_id} onChange={set('role_id')} disabled={self}>
              <option value="">— Select role —</option>
              {roles.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
            </select>
            {self && <small className="a-help">You cannot change your own role.</small>}
            {err('role_id')}
          </label>

          <div className="a-field full">
            <span>Branches *</span>
            <div className="b-checks">
              {branches.map((b) => (
                <label key={b.id} className={`b-check ${form.branch_ids.includes(b.id) ? 'is-on' : ''}`}>
                  <input type="checkbox" checked={form.branch_ids.includes(b.id)} onChange={() => toggleBranch(b.id)} />
                  {b.name} <small>({b.code})</small>{b.is_active === false && <small className="a-muted"> · inactive</small>}
                </label>
              ))}
            </div>
            <small className="a-help">The user only sees data of these branches. Admins with “all branches” permission see every branch.</small>
            {err('branch_ids')}
          </div>

          <label className="a-field half">
            <span>{isEdit ? 'New Password' : 'Password *'}</span>
            <input type="password" value={form.password} onChange={set('password')} autoComplete="new-password" />
            <small className="a-help">{isEdit ? 'Leave empty to keep the current password.' : 'At least 8 characters.'}</small>
            {err('password')}
          </label>
          <label className="a-field half">
            <span>Confirm Password</span>
            <input type="password" value={form.password_confirmation} onChange={set('password_confirmation')} autoComplete="new-password" />
          </label>

          <div className="a-field half is-switch">
            <label className="switch">
              <input type="checkbox" checked={form.is_active} onChange={set('is_active')} disabled={self} />
              <span className="slider" />
              <span className="switch-label">Active (can sign in)</span>
            </label>
            {err('is_active')}
          </div>
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save User'}</button>
          {isEdit && !self && <span className="a-muted">Deactivating signs the user out immediately.</span>}
        </div>
      </form>
    </>
  )
}
