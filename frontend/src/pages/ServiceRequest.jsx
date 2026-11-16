import { useState } from 'react'
import { FiCheckCircle, FiTool } from 'react-icons/fi'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import { Honeypot, hasFieldErrors } from '../components/QuoteForm'

const EMPTY = { name: '', phone: '', email: '', address: '', subject: '', description: '' }

/** After-sales / warranty service request from existing clients. Creates a ticket. */
export default function ServiceRequest() {
  useTitle('Service Request')
  const { settings: s, form_token } = useSite()
  const [form, setForm] = useState(EMPTY)
  const [hp, setHp] = useState('')
  const [errors, setErrors] = useState({})
  const [status, setStatus] = useState({ loading: false, ok: '', number: '', error: '' })
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })
  const err = (k) => errors[k] && <small className="field-error">{errors[k]}</small>

  const submit = async (e) => {
    e.preventDefault()
    setStatus({ loading: true, ok: '', number: '', error: '' })
    setErrors({})
    try {
      const res = await api.post('/service-requests', { ...form, website: hp, form_token })
      setForm(EMPTY)
      setStatus({ loading: false, ok: res.data.message, number: res.data.number, error: '' })
    } catch (ex) {
      setErrors(fieldErrors(ex))
      setStatus({ loading: false, ok: '', number: '', error: errorMessage(ex) })
    }
  }

  return (
    <>
      <PageHeader title="Service Request" />
      <section className="section">
        <div className="container split">
          <div>
            <span className="tag">After-sales Service</span>
            <h2 className="h2">Need a repair or adjustment?</h2>
            <p className="lead">If we did your interior and something needs attention (a loose hinge, a drawer, paint touch-up, electrical fitting…), send us a service request. You will get a ticket number and our service team will call you to schedule a visit.</p>
            <ul className="check-list">
              <li><FiTool /> Free service within the warranty period</li>
              <li><FiCheckCircle /> Track it with your ticket number</li>
            </ul>
            {s.phone && <div className="call-box"><span>Urgent? Call us</span><a href={`tel:${s.phone.replace(/\s/g, '')}`}>{s.phone}</a></div>}
          </div>
          <div className="form-card">
            {status.ok ? (
              <div className="service-done">
                <FiCheckCircle />
                <h3>Request received</h3>
                {status.number && <p className="ticket-no">{status.number}</p>}
                <p>{status.ok}</p>
                <button className="btn btn-outline" onClick={() => setStatus({ loading: false, ok: '', number: '', error: '' })}>Send another request</button>
              </div>
            ) : (
              <form className="quote-form" onSubmit={submit} noValidate>
                <Honeypot value={hp} onChange={setHp} />
                <div className="form-grid">
                  <label><span>Your Name *</span><input value={form.name} onChange={set('name')} />{err('name')}</label>
                  <label><span>Phone *</span><input value={form.phone} onChange={set('phone')} inputMode="tel" placeholder="The number you used with us" />{err('phone')}</label>
                  <label><span>Email</span><input type="email" value={form.email} onChange={set('email')} />{err('email')}</label>
                  <label><span>What needs service? *</span><input value={form.subject} onChange={set('subject')} placeholder="e.g. Kitchen cabinet door hinge" />{err('subject')}</label>
                  <label className="full"><span>Address</span><input value={form.address} onChange={set('address')} />{err('address')}</label>
                  <label className="full"><span>Details</span><textarea rows={4} value={form.description} onChange={set('description')} />{err('description')}</label>
                </div>
                {status.error && !hasFieldErrors(errors) && <div className="alert alert-error">{status.error}</div>}
                <button className="btn btn-primary btn-block" disabled={status.loading}>{status.loading ? 'Sending…' : 'Submit Service Request'}</button>
              </form>
            )}
          </div>
        </div>
      </section>
    </>
  )
}
