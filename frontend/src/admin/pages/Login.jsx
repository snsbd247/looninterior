import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { adminApi, errorMessage, getToken, setToken, sitePath } from '../../lib/api'

export default function Login() {
  const navigate = useNavigate()
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  if (getToken()) return <Navigate to="/admin" replace />

  const submit = async (e) => {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await adminApi.post('/login', form)
      setToken(res.data.token)
      navigate('/admin', { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setLoading(false)
    }
  }

  return (
    <div className="login-page">
      <form className="login-card" onSubmit={submit}>
        <h1>Admin Login</h1>
        <p className="a-muted">Sign in to manage your website</p>
        {error && <div className="a-alert a-alert-error">{error}</div>}
        <label className="a-field">
          <span>Email</span>
          <input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required autoFocus />
        </label>
        <label className="a-field">
          <span>Password</span>
          <input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
        </label>
        <button className="a-btn a-btn-primary a-btn-block" disabled={loading}>{loading ? 'Signing in…' : 'Sign In'}</button>
        <a href={sitePath('/')} className="login-back">← Back to website</a>
      </form>
    </div>
  )
}
