import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { FiMapPin, FiBriefcase, FiCalendar, FiUsers, FiDollarSign } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import { Honeypot, hasFieldErrors } from '../components/QuoteForm'
import { api, errorMessage, fieldErrors, formatDate } from '../lib/api'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import Rich from '../components/Rich'
import NotFound from './NotFound'

function ApplyForm({ slug }) {
  const { form_token } = useSite()
  const [hp, setHp] = useState('')
  const [form, setForm] = useState({ name: '', email: '', phone: '', cover_letter: '' })
  const [cv, setCv] = useState(null)
  const [errors, setErrors] = useState({})
  const [status, setStatus] = useState({ loading: false, ok: '', error: '' })
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setStatus({ loading: true, ok: '', error: '' })
    setErrors({})
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => fd.append(k, v))
    if (cv) fd.append('cv', cv)
    fd.append('website', hp)
    fd.append('form_token', form_token || '')
    try {
      const res = await api.post(`/careers/${slug}/apply`, fd)
      setForm({ name: '', email: '', phone: '', cover_letter: '' })
      setCv(null)
      e.target.reset()
      setStatus({ loading: false, ok: res.data.message, error: '' })
    } catch (err) {
      setErrors(fieldErrors(err))
      setStatus({ loading: false, ok: '', error: errorMessage(err) })
    }
  }

  const err = (k) => errors[k] && <small className="field-error">{errors[k]}</small>

  return (
    <form className="quote-form" onSubmit={submit} noValidate>
      <Honeypot value={hp} onChange={setHp} />
      <div className="form-grid one">
        <label><span>Full Name *</span><input value={form.name} onChange={set('name')} />{err('name')}</label>
        <label><span>Email *</span><input type="email" value={form.email} onChange={set('email')} />{err('email')}</label>
        <label><span>Phone *</span><input value={form.phone} onChange={set('phone')} />{err('phone')}</label>
        <label><span>CV (PDF/DOC, max 5MB) *</span><input type="file" accept=".pdf,.doc,.docx" onChange={(e) => setCv(e.target.files[0] || null)} />{err('cv')}</label>
        <label><span>Cover Letter</span><textarea rows={4} value={form.cover_letter} onChange={set('cover_letter')} /></label>
      </div>
      {status.ok && <div className="alert alert-success">{status.ok}</div>}
      {status.error && !hasFieldErrors(errors) && <div className="alert alert-error">{status.error}</div>}
      <button className="btn btn-primary btn-block" disabled={status.loading}>{status.loading ? 'Submitting…' : 'Submit Application'}</button>
    </form>
  )
}

export default function CareerDetail() {
  const { slug } = useParams()
  const { data, loading, error } = useFetch(`/careers/${slug}`)
  useTitle(data?.data?.title || 'Career')

  if (loading) return <Loader full />
  if (error || !data) return <NotFound />
  const j = data.data

  return (
    <>
      <PageHeader title={j.title} crumbs={[{ label: 'Careers', to: '/careers' }]} />
      <section className="section">
        <div className="container detail-layout">
          <article>
            <div className="job-meta big">
              {j.location && <span><FiMapPin /> {j.location}</span>}
              {j.job_type && <span><FiBriefcase /> {j.job_type}</span>}
              {j.vacancy && <span><FiUsers /> {j.vacancy} vacancy</span>}
              {j.salary && <span><FiDollarSign /> {j.salary}</span>}
              {j.deadline && <span><FiCalendar /> Deadline: {formatDate(j.deadline)}</span>}
            </div>
            <Rich html={j.description} />
          </article>
          <aside className="sidebar">
            <div className="side-card">
              <h4>Apply for this job</h4>
              <ApplyForm slug={slug} />
            </div>
          </aside>
        </div>
      </section>
    </>
  )
}
