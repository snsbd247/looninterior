import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { adminApi, errorMessage } from '../../lib/api'

export default function ResetPassword() {
  const [params] = useSearchParams()
  const token = params.get('token') || ''
  const email = params.get('email') || ''
  const [form, setForm] = useState({ password: '', password_confirmation: '' })
  const [state, setState] = useState({ loading: false, ok: '', error: '' })

  const submit = async (e) => {
    e.preventDefault()
    if (form.password !== form.password_confirmation) {
      setState({ loading: false, ok: '', error: 'The two passwords do not match.' })
      return
    }
    setState({ loading: true, ok: '', error: '' })
    try {
      const res = await adminApi.post('/reset-password', { token, email, ...form })
      setState({ loading: false, ok: res.data.message, error: '' })
    } catch (err) {
      setState({ loading: false, ok: '', error: errorMessage(err) })
    }
  }

  if (!token || !email) {
    return (
      <div className="login-page">
        <div className="login-card">
          <h1>Invalid Link</h1>
          <div className="a-alert a-alert-error">This reset link is incomplete. Please open the link from the e-mail again, or request a new one.</div>
          <div className="login-links"><Link to="/admin/forgot-password">Request a new link</Link></div>
        </div>
      </div>
    )
  }

  return (
    <div className="login-page">
      <form className="login-card" onSubmit={submit}>
        <h1>Set New Password</h1>
        <p className="a-muted">For {email}</p>
        {state.ok && <div className="a-alert a-alert-success">{state.ok}</div>}
        {state.error && <div className="a-alert a-alert-error">{state.error}</div>}
        {!state.ok && (
          <>
            <label className="a-field">
              <span>New Password</span>
              <input type="password" minLength={8} value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required autoFocus />
              <small className="a-help">At least 8 characters</small>
            </label>
            <label className="a-field">
              <span>Confirm New Password</span>
              <input type="password" minLength={8} value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} required />
            </label>
            <button className="a-btn a-btn-primary a-btn-block" disabled={state.loading}>
              {state.loading ? 'Saving…' : 'Change Password'}
            </button>
          </>
        )}
        <div className="login-links">
          <Link to="/admin/login">{state.ok ? 'Go to login →' : '← Back to login'}</Link>
          {state.error && <Link to="/admin/forgot-password">Request a new link</Link>}
        </div>
      </form>
    </div>
  )
}
