import { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react'
import { Link, NavLink, Navigate, Route, Routes, useNavigate, useParams } from 'react-router-dom'
import axios from 'axios'
import { FiGrid, FiFileText, FiLifeBuoy, FiUser, FiLogOut, FiDownload, FiPlus, FiSend, FiImage, FiArrowLeft, FiLock } from 'react-icons/fi'
import { errorMessage, fieldErrors, formatDate, img, sitePath } from '../lib/api'
import { useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import './portal.css'

/*
 * Client portal (/portal). Customers sign in with the phone or e-mail and the password staff set
 * on their customer page. Uses its own token (localStorage "portal_token"), never the admin token.
 */
const TOKEN = 'portal_token'
const getToken = () => { try { return localStorage.getItem(TOKEN) } catch { return null } }
const setToken = (t) => { try { t ? localStorage.setItem(TOKEN, t) : localStorage.removeItem(TOKEN) } catch { /* private mode */ } }

const portalApi = axios.create({ baseURL: sitePath('/api/portal'), headers: { Accept: 'application/json' } })
portalApi.interceptors.request.use((c) => { const t = getToken(); if (t) c.headers.Authorization = `Bearer ${t}`; return c })

const Ctx = createContext(null)
const usePortal = () => useContext(Ctx)

const tk = (n) => 'Tk ' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const label = (s) => String(s ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
const dt = (v) => (v ? new Date(v).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' }) : '')
const Badge = ({ value }) => <span className={`p-badge p-${value}`}>{label(value)}</span>

async function openPdf(path, lang = 'en') {
  const win = window.open('', '_blank')
  try {
    const r = await portalApi.get(path, { params: { lang }, responseType: 'blob' })
    const url = URL.createObjectURL(r.data)
    if (win) win.location.href = url; else window.location.href = url
  } catch (e) { win?.close(); alert(errorMessage(e)) }
}

function Login({ onDone }) {
  const [form, setForm] = useState({ login: '', password: '' })
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('')
    try { const r = await portalApi.post('/login', form); setToken(r.data.token); onDone() } catch (x) { setError(errorMessage(x)) } finally { setBusy(false) }
  }
  return (
    <div className="p-login form-card">
      <FiLock className="p-login-icon" />
      <h2 className="h3">Sign in to your client portal</h2>
      <p className="p-muted">Follow your project, download invoices and receipts, and request service.</p>
      <form className="quote-form" onSubmit={submit}>
        <div className="form-grid">
          <label className="full"><span>Phone or e-mail</span><input value={form.login} onChange={(e) => setForm({ ...form, login: e.target.value })} autoComplete="username" required /></label>
          <label className="full"><span>Password</span><input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete="current-password" required /></label>
        </div>
        {error && <div className="alert alert-error">{error}</div>}
        <button className="btn btn-primary btn-block" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}</button>
      </form>
      <p className="p-muted p-small">No password yet? Ask your project manager to turn on portal access for you.</p>
    </div>
  )
}

function Dashboard() {
  const { me } = usePortal()
  const [projects, setProjects] = useState(null)
  useEffect(() => { portalApi.get('/projects').then((r) => setProjects(r.data.data)).catch(() => setProjects([])) }, [])
  const s = me.summary
  return (
    <>
      <div className="p-stats">
        <div><span>Projects</span><b>{s.projects}</b></div>
        <div><span>Total billed</span><b>{tk(s.invoiced)}</b></div>
        <div><span>Paid</span><b className="p-good">{tk(s.paid)}</b></div>
        <div><span>Due</span><b className={s.due > 0 ? 'p-warn' : ''}>{tk(s.due)}</b></div>
      </div>
      <h3 className="p-title">Your projects</h3>
      {!projects ? <Loader /> : projects.length ? (
        <div className="p-projects">{projects.map((p) => (
          <Link key={p.id} to={`/portal/projects/${p.id}`} className="p-card">
            <div className="p-row"><b>{p.title}</b><Badge value={p.status} /></div>
            <div className="p-muted p-small">{p.code}{p.site_address ? ` · ${p.site_address}` : ''}</div>
            <div className="p-progress"><span style={{ width: `${p.progress}%` }} /></div>
            <div className="p-row p-small"><span>{p.progress}% complete</span><span>{p.end_date ? `Target ${formatDate(p.end_date)}` : ''}</span></div>
          </Link>
        ))}</div>
      ) : <p className="p-empty">No projects yet.</p>}
    </>
  )
}

function ProjectView() {
  const { id } = useParams()
  const [d, setD] = useState(null)
  const [err, setErr] = useState('')
  useEffect(() => { portalApi.get(`/projects/${id}`).then((r) => setD(r.data)).catch((e) => setErr(errorMessage(e))) }, [id])
  if (err) return <p className="p-empty">{err}</p>
  if (!d) return <Loader />
  const p = d.data
  return (
    <>
      <Link to="/portal" className="p-back"><FiArrowLeft /> All projects</Link>
      <div className="p-row p-head"><h3 className="p-title">{p.title}</h3><Badge value={p.status} /></div>
      <div className="p-muted p-small">{p.code}{p.site_address ? ` · ${p.site_address}` : ''}</div>
      <div className="p-progress p-big"><span style={{ width: `${p.progress}%` }} /></div>
      <div className="p-facts">
        <div><span>Progress</span><b>{p.progress}%</b></div>
        <div><span>Start</span><b>{formatDate(p.start_date) || '—'}</b></div>
        <div><span>Target finish</span><b>{formatDate(p.end_date) || '—'}</b></div>
        <div><span>Warranty until</span><b>{formatDate(p.warranty_until) || '—'}</b></div>
      </div>
      {d.milestones.length > 0 && (<>
        <h4 className="p-sub">Work stages</h4>
        <ul className="p-stages">{d.milestones.map((m) => (
          <li key={m.id}><div className="p-row"><span>{m.name}</span><span className="p-small">{m.progress}%</span></div><div className="p-progress"><span style={{ width: `${m.progress}%` }} /></div></li>
        ))}</ul>
      </>)}
      {d.photos.length > 0 && (<>
        <h4 className="p-sub">Site photos</h4>
        <div className="p-photos">{d.photos.map((f) => <a key={f.id} href={img(f.path)} target="_blank" rel="noreferrer"><img src={img(f.path)} alt={f.original_name} loading="lazy" /></a>)}</div>
      </>)}
      {d.invoices.length > 0 && (<><h4 className="p-sub">Invoices for this project</h4><InvoiceTable rows={d.invoices} /></>)}
    </>
  )
}

function InvoiceTable({ rows }) {
  return (
    <div className="p-table-wrap"><table className="p-table">
      <thead><tr><th>Invoice</th><th>Date</th><th className="r">Total</th><th className="r">Due</th><th /></tr></thead>
      <tbody>{rows.map((i) => (
        <tr key={i.id}><td><b>{i.number}</b><div className="p-small p-muted">{i.title || i.project?.title}</div></td><td>{formatDate(i.date)}</td><td className="r">{tk(i.total)}</td>
          <td className="r">{Number(i.due) > 0 ? <b className="p-warn">{tk(i.due)}</b> : <Badge value="paid" />}</td>
          <td className="r p-nowrap"><button className="p-link" onClick={() => openPdf(`/invoices/${i.id}/pdf`)}><FiDownload /> PDF</button> <button className="p-link" onClick={() => openPdf(`/invoices/${i.id}/pdf`, 'bn')}>PDF (Bangla)</button></td></tr>
      ))}</tbody>
    </table></div>
  )
}

function Billing() {
  const [inv, setInv] = useState(null)
  const [pay, setPay] = useState(null)
  useEffect(() => {
    portalApi.get('/invoices').then((r) => setInv(r.data.data)).catch(() => setInv([]))
    portalApi.get('/payments').then((r) => setPay(r.data.data)).catch(() => setPay([]))
  }, [])
  return (
    <>
      <h3 className="p-title">Invoices</h3>
      {!inv ? <Loader /> : inv.length ? <InvoiceTable rows={inv} /> : <p className="p-empty">No invoices yet.</p>}
      <h3 className="p-title">Payments</h3>
      {!pay ? <Loader /> : pay.length ? (
        <div className="p-table-wrap"><table className="p-table">
          <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th className="r">Amount</th><th /></tr></thead>
          <tbody>{pay.map((p) => (
            <tr key={p.id}><td><b>{p.number}</b><div className="p-small p-muted">for {p.invoice?.number}</div></td><td>{formatDate(p.date)}</td><td>{label(p.method)}</td><td className="r">{tk(p.amount)}</td>
              <td className="r p-nowrap"><button className="p-link" onClick={() => openPdf(`/payments/${p.id}/pdf`)}><FiDownload /> Receipt</button></td></tr>
          ))}</tbody>
        </table></div>
      ) : <p className="p-empty">No payments yet.</p>}
    </>
  )
}

function Tickets() {
  const [rows, setRows] = useState(null)
  const [projects, setProjects] = useState([])
  const [form, setForm] = useState(null)
  const [photo, setPhoto] = useState(null)
  const [errors, setErrors] = useState({})
  const [msg, setMsg] = useState('')
  const navigate = useNavigate()
  const load = useCallback(() => portalApi.get('/tickets').then((r) => setRows(r.data.data)).catch(() => setRows([])), [])
  useEffect(() => { load(); portalApi.get('/projects').then((r) => setProjects(r.data.data)).catch(() => {}) }, [load])
  const submit = async (e) => {
    e.preventDefault(); setErrors({}); setMsg('')
    const fd = new FormData()
    Object.entries(form).forEach(([k, v]) => v && fd.append(k, v))
    if (photo) fd.append('photo', photo)
    try { const r = await portalApi.post('/tickets', fd); navigate(`/portal/tickets/${r.data.data.id}`) } catch (x) { setErrors(fieldErrors(x)); setMsg(errorMessage(x)) }
  }
  return (
    <>
      <div className="p-row p-head"><h3 className="p-title">Service requests</h3>{!form && <button className="btn btn-primary btn-sm" onClick={() => setForm({ subject: '', description: '', work_project_id: projects[0]?.id || '' })}><FiPlus /> New request</button>}</div>
      {form && (
        <form className="quote-form form-card p-form" onSubmit={submit}>
          <div className="form-grid">
            {projects.length > 1 && <label className="full"><span>Project</span><select value={form.work_project_id} onChange={(e) => setForm({ ...form, work_project_id: e.target.value })}>{projects.map((p) => <option key={p.id} value={p.id}>{p.title}</option>)}</select></label>}
            <label className="full"><span>What needs service? *</span><input value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="e.g. Kitchen cabinet door hinge" />{errors.subject && <small className="field-error">{errors.subject}</small>}</label>
            <label className="full"><span>Details</span><textarea rows={4} value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></label>
            <label className="full"><span>Photo (optional)</span><input type="file" accept="image/*" onChange={(e) => setPhoto(e.target.files[0] || null)} /></label>
          </div>
          {msg && <div className="alert alert-error">{msg}</div>}
          <div className="p-row"><button type="button" className="btn btn-outline btn-sm" onClick={() => setForm(null)}>Cancel</button><button className="btn btn-primary btn-sm"><FiSend /> Send request</button></div>
        </form>
      )}
      {!rows ? <Loader /> : rows.length ? (
        <div className="p-list">{rows.map((t) => (
          <Link key={t.id} to={`/portal/tickets/${t.id}`} className="p-card">
            <div className="p-row"><b>{t.subject}</b><Badge value={t.status} /></div>
            <div className="p-muted p-small">{t.number} · {formatDate(t.created_at)}{t.visit_at ? ` · Visit ${dt(t.visit_at)}` : ''}</div>
          </Link>
        ))}</div>
      ) : !form && <p className="p-empty">No service requests. If something needs repair or adjustment, open a new request.</p>}
    </>
  )
}

function TicketView() {
  const { id } = useParams()
  const [d, setD] = useState(null)
  const [body, setBody] = useState('')
  const [photo, setPhoto] = useState(null)
  const [err, setErr] = useState('')
  const fileRef = useRef(null)
  const load = useCallback(() => portalApi.get(`/tickets/${id}`).then((r) => setD(r.data)).catch((e) => setErr(errorMessage(e))), [id])
  useEffect(() => { load() }, [load])
  const send = async () => {
    const fd = new FormData(); if (body) fd.append('body', body); if (photo) fd.append('photo', photo)
    try { await portalApi.post(`/tickets/${id}/comments`, fd); setBody(''); setPhoto(null); load() } catch (e) { setErr(errorMessage(e)) }
  }
  if (!d) return err ? <p className="p-empty">{err}</p> : <Loader />
  const t = d.data
  return (
    <>
      <Link to="/portal/tickets" className="p-back"><FiArrowLeft /> All requests</Link>
      <div className="p-row p-head"><h3 className="p-title">{t.subject}</h3><Badge value={t.status} /></div>
      <div className="p-muted p-small">{t.number} · opened {dt(t.created_at)}{t.project ? ` · ${t.project.title}` : ''}</div>
      {t.visit_at && <div className="p-note">Technician visit: <b>{dt(t.visit_at)}</b></div>}
      {t.description && <p>{t.description}</p>}
      <ul className="p-thread">{d.comments.map((c) => (
        <li key={c.id} className={c.mine ? 'mine' : ''}><div className="p-small p-muted"><b>{c.by}</b> · {dt(c.created_at)}</div><p>{c.body}</p>{c.attachment && <a href={img(c.attachment)} target="_blank" rel="noreferrer"><img src={img(c.attachment)} alt="" /></a>}</li>
      ))}{!d.comments.length && <li className="p-muted">Our team will reply here.</li>}</ul>
      {t.status !== 'closed' ? (
        <div className="p-reply">
          <textarea rows={3} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write a message…" />
          <div className="p-row">
            <span><button type="button" className="btn btn-outline btn-sm" onClick={() => fileRef.current?.click()}><FiImage /> {photo ? photo.name : 'Photo'}</button><input ref={fileRef} type="file" accept="image/*" hidden onChange={(e) => setPhoto(e.target.files[0] || null)} /></span>
            <button className="btn btn-primary btn-sm" disabled={!body && !photo} onClick={send}><FiSend /> Send</button>
          </div>
          {err && <div className="alert alert-error">{err}</div>}
        </div>
      ) : <p className="p-empty">This request is closed. Open a new request if you need more help.</p>}
    </>
  )
}

function Account() {
  const { me } = usePortal()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [errors, setErrors] = useState({})
  const [msg, setMsg] = useState('')
  const submit = async (e) => {
    e.preventDefault(); setErrors({}); setMsg('')
    try { await portalApi.post('/password', form); setMsg('Password changed.'); setForm({ current_password: '', password: '', password_confirmation: '' }) } catch (x) { setErrors(fieldErrors(x)); setMsg(errorMessage(x)) }
  }
  const c = me.customer
  return (
    <>
      <h3 className="p-title">My details</h3>
      <div className="p-facts">
        <div><span>Name</span><b>{c.name}</b></div><div><span>Phone</span><b>{c.phone || '—'}</b></div>
        <div><span>E-mail</span><b>{c.email || '—'}</b></div><div><span>Address</span><b>{c.address || '—'}</b></div>
      </div>
      <p className="p-muted p-small">To change these details, please contact us.</p>
      <h3 className="p-title">Change password</h3>
      <form className="quote-form form-card p-form" onSubmit={submit}>
        <div className="form-grid">
          <label className="full"><span>Current password</span><input type="password" value={form.current_password} onChange={(e) => setForm({ ...form, current_password: e.target.value })} autoComplete="current-password" />{errors.current_password && <small className="field-error">{errors.current_password}</small>}</label>
          <label><span>New password</span><input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} autoComplete="new-password" />{errors.password && <small className="field-error">{errors.password}</small>}</label>
          <label><span>Repeat new password</span><input type="password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} autoComplete="new-password" /></label>
        </div>
        {msg && <div className={`alert ${Object.keys(errors).length || msg !== 'Password changed.' ? 'alert-error' : 'alert-success'}`}>{msg}</div>}
        <button className="btn btn-primary btn-sm">Save password</button>
      </form>
    </>
  )
}

export default function Portal() {
  useTitle('Client Portal')
  const [me, setMe] = useState(undefined)
  const navigate = useNavigate()
  const loadMe = useCallback(() => {
    if (!getToken()) { setMe(null); return }
    portalApi.get('/me').then((r) => setMe(r.data)).catch(() => { setToken(null); setMe(null) })
  }, [])
  useEffect(() => { loadMe() }, [loadMe])
  useEffect(() => {
    const id = portalApi.interceptors.response.use((r) => r, (e) => { if (e.response?.status === 401 && getToken()) { setToken(null); setMe(null) } return Promise.reject(e) })
    return () => portalApi.interceptors.response.eject(id)
  }, [])
  const logout = async () => { try { await portalApi.post('/logout') } catch { /* already signed out */ } setToken(null); setMe(null); navigate('/portal') }

  return (
    <>
      <PageHeader title="Client Portal" />
      <section className="section p-section">
        <div className="container">
          {me === undefined ? <Loader /> : !me ? <Login onDone={loadMe} /> : (
            <Ctx.Provider value={{ me }}>
              <div className="p-bar">
                <div><span className="p-muted p-small">Welcome</span><b>{me.customer.name}</b></div>
                <button className="p-link" onClick={logout}><FiLogOut /> Sign out</button>
              </div>
              <nav className="p-tabs">
                <NavLink end to="/portal"><FiGrid /> Projects</NavLink>
                <NavLink to="/portal/billing"><FiFileText /> Invoices</NavLink>
                <NavLink to="/portal/tickets"><FiLifeBuoy /> Service{me.summary.open_tickets > 0 && <i>{me.summary.open_tickets}</i>}</NavLink>
                <NavLink to="/portal/account"><FiUser /> Account</NavLink>
              </nav>
              <div className="p-body">
                <Routes>
                  <Route index element={<Dashboard />} />
                  <Route path="projects/:id" element={<ProjectView />} />
                  <Route path="billing" element={<Billing />} />
                  <Route path="tickets" element={<Tickets />} />
                  <Route path="tickets/:id" element={<TicketView />} />
                  <Route path="account" element={<Account />} />
                  <Route path="*" element={<Navigate to="/portal" replace />} />
                </Routes>
              </div>
            </Ctx.Provider>
          )}
        </div>
      </section>
    </>
  )
}
