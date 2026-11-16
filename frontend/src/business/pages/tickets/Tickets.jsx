import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiPlus, FiSearch, FiArrowLeft, FiSave, FiPhone, FiImage, FiSend, FiShield } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { businessApi, errorMessage, fieldErrors, formatDate, img } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { BranchPicker, Field, InfoGrid, Stat, Status } from '../../components/ui'
import CustomerSelect from '../../components/CustomerSelect'
import { dateTime, label, phoneHref, toLocalInput, useList, useLookup, waHref } from '../../lib'

export const T_STATUSES = ['open', 'in_progress', 'resolved', 'closed']
export const PRIORITIES = ['low', 'medium', 'high', 'urgent']
const SOURCES = ['phone', 'website', 'email', 'walk_in', 'visit']

function Warranty({ value }) {
  if (value === null || value === undefined) return <span className="a-muted">—</span>
  return <span className={`a-status ${value ? 'b-st-active' : 'b-st-cancelled'}`}><FiShield /> {value ? 'In warranty' : 'Out of warranty'}</span>
}

export function TicketList() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const staff = useLookup('staff')
  const [q, setQ] = useState('')
  const list = useList('/tickets', { q: '', status: 'active', priority: '', assigned_to: '' }, { toast })
  const c = list.extra?.counts || {}
  return (
    <>
      <div className="a-page-head"><h1>Service Tickets</h1><Can perm="tickets.manage"><Link className="a-btn a-btn-primary" to="/admin/biz/tickets/new"><FiPlus /> New Ticket</Link></Can></div>
      <div className="b-stats">
        <Stat label="Open" value={c.open || 0} tone={c.open ? 'bad' : ''} />
        <Stat label="In progress" value={c.in_progress || 0} tone={c.in_progress ? 'warn' : ''} />
        <Stat label="Resolved" value={c.resolved || 0} tone="good" />
        <Stat label="Closed" value={c.closed || 0} />
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}><FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Ticket no., subject, phone, customer…" /></form>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="active">Open + in progress</option><option value="">All</option>{T_STATUSES.map((s) => <option key={s} value={s}>{label(s)}</option>)}</select>
          <select value={list.filters.priority} onChange={(e) => list.setFilter('priority', e.target.value)}><option value="">All priorities</option>{PRIORITIES.map((s) => <option key={s} value={s}>{label(s)}</option>)}</select>
          <select value={list.filters.assigned_to} onChange={(e) => list.setFilter('assigned_to', e.target.value)}><option value="">Everyone</option><option value="me">Assigned to me</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Ticket</th><th>Customer</th><th>Priority</th><th>Status</th><th>Assigned</th><th>Visit</th><th>Opened</th></tr></thead>
            <tbody>{list.rows.length ? list.rows.map((t) => (
              <tr key={t.id} className="b-clickable" onClick={() => navigate(`/admin/biz/tickets/${t.id}`)}>
                <td><b>{t.number}</b><div className="b-sub">{t.subject}</div></td>
                <td>{t.customer?.name || t.contact_name}<div className="a-muted b-sub">{t.customer?.phone || t.contact_phone}{t.project ? ` · ${t.project.code}` : ''}</div></td>
                <td><Status value={t.priority} /></td><td><Status value={t.status} /></td>
                <td>{t.assignee?.name || <span className="b-overdue">Unassigned</span>}</td>
                <td className="b-nowrap">{t.visit_at ? dateTime(t.visit_at) : '—'}</td>
                <td className="b-nowrap">{formatDate(t.created_at)}</td>
              </tr>
            )) : <tr><td colSpan={7} className="a-empty">No tickets 🎉</td></tr>}</tbody>
          </table></div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}

export function TicketForm() {
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const staff = useLookup('staff')
  const [projects, setProjects] = useState([])
  const [form, setForm] = useState({ branch_id: '', customer_id: '', work_project_id: '', contact_name: '', contact_phone: '', contact_email: '', address: '', subject: '', description: '', priority: 'medium', source: 'phone', assigned_to: '', visit_at: '' })
  const [errors, setErrors] = useState({})
  useEffect(() => { businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data)).catch(() => {}) }, [])
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })
  const submit = async (e) => {
    e.preventDefault(); setErrors({})
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
    try { const r = await businessApi.post('/tickets', payload); toast(r.data.message); navigate(`/admin/biz/tickets/${r.data.data.id}`) } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') }
  }
  const custProjects = projects.filter((p) => !form.customer_id || String(p.customer_id) === String(form.customer_id))
  return (
    <>
      <div className="a-page-head"><h1>New Service Ticket</h1><Link to="/admin/biz/tickets" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link></div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />
          <Field label="Customer" error={errors.customer_id} help="Leave empty for a new contact"><CustomerSelect value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v, work_project_id: '' })} /></Field>
          <Field label="Project (for warranty)" error={errors.work_project_id}><select value={form.work_project_id} onChange={set('work_project_id')}><option value="">—</option>{custProjects.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select></Field>
          {!form.customer_id && <>
            <Field label="Contact name *" error={errors.contact_name}><input value={form.contact_name} onChange={set('contact_name')} /></Field>
            <Field label="Contact phone" error={errors.contact_phone}><input value={form.contact_phone} onChange={set('contact_phone')} /></Field>
          </>}
          <Field label="Subject *" error={errors.subject}><input value={form.subject} onChange={set('subject')} placeholder="e.g. Wardrobe sliding door stuck" /></Field>
          <Field label="Priority"><select value={form.priority} onChange={set('priority')}>{PRIORITIES.map((p) => <option key={p} value={p}>{label(p)}</option>)}</select></Field>
          <Field label="Source"><select value={form.source} onChange={set('source')}>{SOURCES.map((p) => <option key={p} value={p}>{label(p)}</option>)}</select></Field>
          <Field label="Assign technician"><select value={form.assigned_to} onChange={set('assigned_to')}><option value="">—</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
          <Field label="Visit date & time"><input type="datetime-local" value={form.visit_at} onChange={set('visit_at')} /></Field>
          <Field label="Address" full><input value={form.address} onChange={set('address')} placeholder="Empty = project site / customer address" /></Field>
          <Field label="Details" full><textarea rows={4} value={form.description} onChange={set('description')} /></Field>
        </div>
        <div className="a-form-actions"><button className="a-btn a-btn-primary"><FiSave /> Open Ticket</button></div>
      </form>
    </>
  )
}

export function TicketDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const staff = useLookup('staff')
  const [t, setT] = useState(null)
  const [projects, setProjects] = useState([])
  const [comment, setComment] = useState('')
  const [photo, setPhoto] = useState(null)
  const [isPublic, setIsPublic] = useState(false)
  const fileRef = useRef(null)
  const manage = can('tickets.manage')

  const load = useCallback(() => { businessApi.get(`/tickets/${id}`).then((r) => setT(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  useEffect(() => { if (manage) businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data)).catch(() => {}) }, [manage])
  if (!t) return <Loader />

  const update = async (patch, msg) => {
    try { await businessApi.post(`/tickets/${id}`, patch); toast(msg || 'Updated'); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const send = async () => {
    const fd = new FormData()
    if (comment) fd.append('body', comment)
    if (photo) fd.append('photo', photo)
    if (isPublic) fd.append('is_public', '1')
    try { await businessApi.post(`/tickets/${id}/comments`, fd); setComment(''); setPhoto(null); setIsPublic(false); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const phone = t.customer?.phone || t.contact_phone
  const next = { open: ['in_progress', 'Start work'], in_progress: ['resolved', 'Mark resolved'], resolved: ['closed', 'Close ticket'] }[t.status]

  return (
    <>
      <div className="a-page-head">
        <h1>{t.number} <Status value={t.status} /> <Status value={t.priority} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/tickets" className="a-btn a-btn-light"><FiArrowLeft /> Tickets</Link>
          {manage && next && <button className="a-btn a-btn-primary" onClick={() => update({ status: next[0] }, next[1])}>{next[1]}</button>}
          {manage && ['resolved', 'closed'].includes(t.status) && <button className="a-btn a-btn-light" onClick={() => update({ status: 'open', note: window.prompt('Why reopen?') || '' }, 'Reopened')}>Reopen</button>}
        </div>
      </div>
      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel b-pad">
            <h3 className="b-panel-title">{t.subject}</h3>
            {t.description && <p className="b-pre">{t.description}</p>}
          </div>
          <div className="a-panel b-pad">
            <h3 className="b-panel-title">Timeline</h3>
            {manage && (
              <div className="b-activity-form">
                <textarea rows={3} placeholder="Update for the team: what was checked, what was fixed, parts needed…" value={comment} onChange={(e) => setComment(e.target.value)} />
                <div className="b-activity-foot">
                  <span><button type="button" className="a-btn a-btn-light" onClick={() => fileRef.current?.click()}><FiImage /> {photo ? photo.name : 'Photo'}</button>
                    <input ref={fileRef} type="file" accept="image/*" hidden onChange={(e) => setPhoto(e.target.files[0] || null)} /></span>
                  <label className="b-check" title="Shown to the customer in the client portal"><input type="checkbox" checked={isPublic} onChange={(e) => setIsPublic(e.target.checked)} /> Visible to customer</label>
                  <button className="a-btn a-btn-primary" onClick={send} disabled={!comment && !photo}><FiSend /> Add</button>
                </div>
              </div>
            )}
            <ul className="b-timeline">
              {t.comments.map((c) => (
                <li key={c.id} className={c.kind === 'status' ? 't-stage' : c.kind === 'system' ? '' : c.kind === 'client' ? 't-client' : 't-call'}>
                  <div className="b-tl-head"><b>{c.kind === 'client' ? `${t.customer?.name || 'Customer'} (portal)` : c.user?.name || 'System'}{c.is_public ? <span className="b-public"> · visible to customer</span> : null}</b><span className="a-muted">{dateTime(c.created_at)}</span></div>
                  <p>{c.body}</p>
                  {c.attachment && <a href={img(c.attachment)} target="_blank" rel="noreferrer"><img src={img(c.attachment)} alt="" className="b-tl-photo" /></a>}
                </li>
              ))}
            </ul>
          </div>
        </div>
        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <div className="b-contact-actions">
              {phone && <a className="a-btn a-btn-light" href={phoneHref(phone)}><FiPhone /> Call</a>}
              {phone && <a className="a-btn a-btn-light" href={waHref(phone, `Regarding your service ticket ${t.number}`)} target="_blank" rel="noreferrer"><FaWhatsapp /> WhatsApp</a>}
            </div>
            <InfoGrid items={[
              ['Customer', t.customer ? <Link to={`/admin/biz/customers/${t.customer_id}`}>{t.customer.name}</Link> : t.contact_name],
              ['Phone', phone], ['Email', t.customer?.email || t.contact_email], ['Address', t.address],
              ['Project', t.project ? <Link to={`/admin/biz/projects/${t.work_project_id}`}>{t.project.code}</Link> : null],
              ['Warranty', <><Warranty value={t.in_warranty} />{t.warranty_until && <div className="a-muted b-sub">until {formatDate(t.warranty_until)}</div>}</>],
              ['Source', label(t.source)], ['Opened', dateTime(t.created_at)], ['Resolved', t.resolved_at ? dateTime(t.resolved_at) : null], ['Branch', t.branch?.name],
            ]} />
          </div>
          {manage && (
            <div className="a-panel b-pad b-ticket-ctrl">
              <Field label="Assigned technician"><select value={t.assigned_to || ''} onChange={(e) => update({ assigned_to: e.target.value || null }, 'Assigned')}><option value="">— Unassigned —</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
              <Field label="Priority"><select value={t.priority} onChange={(e) => update({ priority: e.target.value })}>{PRIORITIES.map((p) => <option key={p} value={p}>{label(p)}</option>)}</select></Field>
              <Field label="Visit date & time"><input type="datetime-local" defaultValue={toLocalInput(t.visit_at)} onBlur={(e) => { if (e.target.value !== toLocalInput(t.visit_at)) update({ visit_at: e.target.value || null }, 'Visit scheduled') }} /></Field>
              <Field label="Project"><select value={t.work_project_id || ''} onChange={(e) => update({ work_project_id: e.target.value || null }, 'Project linked')}><option value="">—</option>{projects.filter((p) => !t.customer_id || p.customer_id === t.customer_id).map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select></Field>
            </div>
          )}
        </aside>
      </div>
    </>
  )
}
