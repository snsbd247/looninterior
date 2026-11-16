import { useState } from 'react'
import { Link } from 'react-router-dom'
import { adminApi, errorMessage } from '../../lib/api'

export default function ForgotPassword() {
  const [email, setEmail] = useState('')
  const [state, setState] = useState({ loading: false, ok: '', error: '' })

  const submit = async (e) => {
    e.preventDefault()
    setState({ loading: true, ok: '', error: '' })
    try {
      const res = await adminApi.post('/forgot-password', { email })
      setState({ loading: false, ok: res.data.message, error: '' })
    } catch (err) {
      setState({ loading: false, ok: '', error: errorMessage(err) })
    }
  }

  return (
    <div className="login-page">
      <form className="login-card" onSubmit={submit}>
        <h1>Forgot Password</h1>
        <p className="a-muted">Enter your admin e-mail. We will send you a link to set a new password.</p>
        {state.ok && <div className="a-alert a-alert-success">{state.ok}</div>}
        {state.error && <div className="a-alert a-alert-error">{state.error}</div>}
        <label className="a-field">
          <span>Email</span>
          <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoFocus />
        </label>
        <button className="a-btn a-btn-primary a-btn-block" disabled={state.loading}>
          {state.loading ? 'Sending…' : 'Send Reset Link'}
        </button>
        <div className="login-links">
          <Link to="/admin/login">← Back to login</Link>
        </div>
      </form>
    </div>
  )
}
