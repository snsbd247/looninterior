import { useState } from 'react'
import { api, errorMessage, fieldErrors } from '../lib/api'
import { useSite } from '../context/SiteContext'

const PROPERTY_TYPES = ['Apartment', 'Duplex / Villa', 'Office', 'Restaurant / Cafe', 'Showroom / Retail', 'Hotel / Resort', 'Other']
const BUDGETS = ['Below 5 Lakh', '5 – 10 Lakh', '10 – 20 Lakh', '20 – 50 Lakh', '50 Lakh+']

/** Hidden spam trap: real visitors never see or fill this field. */
export function Honeypot({ value, onChange }) {
  return (
    <div className="hp-field" aria-hidden="true">
      <label>Website<input name="website" tabIndex={-1} autoComplete="off" value={value} onChange={(e) => onChange(e.target.value)} /></label>
    </div>
  )
}

/** True when the error bag has errors tied to visible fields (not the general "form" error). */
export const hasFieldErrors = (errors) => Object.keys(errors).some((k) => k !== 'form')

const EMPTY ={ name: '', phone: '', email: '', service: '', property_type: '', area_size: '', budget: '', location: '', message: '' }

export default function QuoteForm({ type = 'quote', compact = false }) {
  const { services, form_token } = useSite()
  const [form, setForm] = useState(EMPTY)
  const [hp, setHp] = useState('')
  const [errors, setErrors] = useState({})
  const [status, setStatus] = useState({ loading: false, ok: '', error: '' })

  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setStatus({ loading: true, ok: '', error: '' })
    setErrors({})
    try {
      const res = await api.post('/inquiries', { ...form, type, website: hp, form_token })
      setForm(EMPTY)
      setStatus({ loading: false, ok: res.data.message, error: '' })
      // Conversion events for ads (no-op when Analytics/Pixel are not configured)
      if (window.gtag) window.gtag('event', 'generate_lead', { form: type })
      else if (window.dataLayer) window.dataLayer.push({ event: 'generate_lead', form: type }) // Tag Manager only
      window.fbq?.('track', type === 'quote' ? 'Lead' : 'Contact')
    } catch (err) {
      setErrors(fieldErrors(err))
      setStatus({ loading: false, ok: '', error: errorMessage(err) })
    }
  }

  const err = (k) => errors[k] && <small className="field-error">{errors[k]}</small>

  return (
    <form className="quote-form" onSubmit={submit} noValidate>
      <Honeypot value={hp} onChange={setHp} />
      <div className="form-grid">
        <label>
          <span>Your Name *</span>
          <input value={form.name} onChange={set('name')} required placeholder="Full name" />
          {err('name')}
        </label>
        <label>
          <span>Phone *</span>
          <input value={form.phone} onChange={set('phone')} required placeholder="01XXXXXXXXX" inputMode="tel" />
          {err('phone')}
        </label>
        <label>
          <span>Email</span>
          <input type="email" value={form.email} onChange={set('email')} placeholder="you@example.com" />
          {err('email')}
        </label>
        {type === 'quote' && (
          <>
            <label>
              <span>Service</span>
              <select value={form.service} onChange={set('service')}>
                <option value="">Select a service</option>
                {services.map((x) => <option key={x.id} value={x.title}>{x.title}</option>)}
              </select>
            </label>
            {!compact && (
              <>
                <label>
                  <span>Property Type</span>
                  <select value={form.property_type} onChange={set('property_type')}>
                    <option value="">Select type</option>
                    {PROPERTY_TYPES.map((p) => <option key={p}>{p}</option>)}
                  </select>
                </label>
                <label>
                  <span>Area Size (sqft)</span>
                  <input value={form.area_size} onChange={set('area_size')} placeholder="e.g. 1800" />
                </label>
                <label>
                  <span>Budget</span>
                  <select value={form.budget} onChange={set('budget')}>
                    <option value="">Select budget</option>
                    {BUDGETS.map((b) => <option key={b}>{b}</option>)}
                  </select>
                </label>
                <label>
                  <span>Location</span>
                  <input value={form.location} onChange={set('location')} placeholder="e.g. Gulshan, Dhaka" />
                </label>
              </>
            )}
          </>
        )}
        <label className="full">
          <span>Message</span>
          <textarea rows={compact ? 3 : 4} value={form.message} onChange={set('message')} placeholder="Tell us about your project" />
          {err('message')}
        </label>
      </div>

      {status.ok && <div className="alert alert-success">{status.ok}</div>}
      {status.error && !hasFieldErrors(errors) && <div className="alert alert-error">{status.error}</div>}

      <button className="btn btn-primary btn-block" disabled={status.loading}>
        {status.loading ? 'Sending…' : type === 'quote' ? 'Request Free Quote' : 'Send Message'}
      </button>
    </form>
  )
}
