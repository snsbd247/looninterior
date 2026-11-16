import { useState } from 'react'
import { FiSave } from 'react-icons/fi'
import { adminApi, errorMessage, fieldErrors } from '../../lib/api'
import { useAdmin } from '../AdminApp'

export default function Profile() {
  const { user, setUser, toast } = useAdmin()
  const [form, setForm] = useState({ name: user.name, email: user.email, current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const res = await adminApi.post('/profile', form)
      setUser(res.data.user)
      setForm((f) => ({ ...f, current_password: '', password: '', password_confirmation: '' }))
      toast('Profile updated')
    } catch (err) {
      setErrors(fieldErrors(err))
      toast(errorMessage(err), 'error')
    } finally {
      setSaving(false)
    }
  }

  const field = (k, label, type = 'text') => (
    <label className="a-field half">
      <span>{label}</span>
      <input type={type} value={form[k]} onChange={set(k)} autoComplete={type === 'password' ? 'new-password' : undefined} />
      {errors[k] && <small className="a-error">{errors[k]}</small>}
    </label>
  )

  return (
    <>
      <div className="a-page-head"><h1>My Profile</h1></div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {field('name', 'Name')}
          {field('email', 'Email', 'email')}
          <div className="a-field full"><h3 className="a-subhead">Change Password <small className="a-muted">(leave empty to keep current)</small></h3></div>
          {field('current_password', 'Current Password', 'password')}
          <div className="a-field half" />
          {field('password', 'New Password', 'password')}
          {field('password_confirmation', 'Confirm New Password', 'password')}
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Update Profile'}</button>
        </div>
      </form>
    </>
  )
}
