import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { FiArrowLeft, FiEdit2, FiPlus, FiTrash2, FiUpload, FiDownload, FiFileText, FiGlobe, FiSave, FiCheckCircle } from 'react-icons/fi'
import { adminApi, businessApi, errorMessage, fieldErrors, formatDate, img } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { Field, InfoGrid, Modal, Money, Stat, Status } from '../../components/ui'
import { label, money, useLookup } from '../../lib'
import { Progress } from './Projects'

const TABS = [['overview', 'Overview'], ['work', 'Milestones & Tasks'], ['files', 'Files & Photos'], ['billing', 'Billing'], ['costs', 'Costs']]

function Milestones({ project, canManage, onSaved }) {
  const { toast } = useAdmin()
  const [rows, setRows] = useState(project.milestones.map((m) => ({ ...m })))
  const [saving, setSaving] = useState(false)
  const dirty = JSON.stringify(rows.map(({ id, name, progress, due_date }) => [id, name, progress, due_date])) !== JSON.stringify(project.milestones.map(({ id, name, progress, due_date }) => [id, name, progress, due_date]))
  const set = (i, patch) => setRows(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)))
  const save = async () => {
    setSaving(true)
    try { await businessApi.post(`/projects/${project.id}/milestones`, { milestones: rows.map((r) => ({ id: r.id, name: r.name, progress: Number(r.progress), due_date: r.due_date || null })) }); toast('Milestones saved'); onSaved() } catch (e) { toast(errorMessage(e), 'error') } finally { setSaving(false) }
  }
  return (
    <div className="a-panel b-pad">
      <div className="b-row-head"><h3 className="b-panel-title">Milestones</h3>{canManage && dirty && <button className="a-btn a-btn-primary" onClick={save} disabled={saving}><FiSave /> Save</button>}</div>
      <div className="b-milestones">
        {rows.map((m, i) => (
          <div key={m.id || `n${i}`} className="b-milestone">
            {canManage ? <input value={m.name} onChange={(e) => set(i, { name: e.target.value })} /> : <b>{m.name}</b>}
            <div className="b-ms-progress">
              <input type="range" min="0" max="100" step="5" value={m.progress} disabled={!canManage} onChange={(e) => set(i, { progress: Number(e.target.value) })} />
              <span>{m.progress}%</span>
            </div>
            {canManage ? <input type="date" value={m.due_date || ''} onChange={(e) => set(i, { due_date: e.target.value })} /> : <span className="a-muted">{formatDate(m.due_date) || ''}</span>}
            {canManage && <button className="a-icon-btn danger" title="Remove" onClick={() => setRows(rows.filter((_, j) => j !== i))}><FiTrash2 /></button>}
          </div>
        ))}
      </div>
      {canManage && <button className="b-lines-add" onClick={() => setRows([...rows, { name: 'New milestone', progress: 0, due_date: '' }])}><FiPlus /> Add milestone</button>}
    </div>
  )
}

function Tasks({ project, canManage, onSaved }) {
  const { toast } = useAdmin()
  const staff = useLookup('staff')
  const [form, setForm] = useState(null)
  const save = async () => {
    try {
      await businessApi.post(`/projects/${project.id}/tasks${form.id ? `/${form.id}` : ''}`, { ...form, milestone_id: form.milestone_id || null, assigned_to: form.assigned_to || null, due_date: form.due_date || null })
      setForm(null); toast('Task saved'); onSaved()
    } catch (e) { toast(errorMessage(e), 'error') }
  }
  const setStatus = async (t, status) => {
    try { await businessApi.post(`/projects/${project.id}/tasks/${t.id}`, { title: t.title, notes: t.notes, milestone_id: t.milestone_id, assigned_to: t.assigned_to, due_date: t.due_date, status }); onSaved() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const remove = async (t) => {
    if (!window.confirm('Remove this task?')) return
    try { await businessApi.delete(`/projects/${project.id}/tasks/${t.id}`); onSaved() } catch (e) { toast(errorMessage(e), 'error') }
  }
  return (
    <div className="a-panel">
      <div className="a-panel-head"><h3>Tasks</h3>{canManage && <button className="a-btn a-btn-light" onClick={() => setForm({ title: '', notes: '', milestone_id: '', assigned_to: '', due_date: '', status: 'todo' })}><FiPlus /> Task</button>}</div>
      <div className="a-table-wrap">
        <table className="a-table">
          <thead><tr><th>Task</th><th>Milestone</th><th>Assigned</th><th>Due</th><th>Status</th><th /></tr></thead>
          <tbody>
            {project.tasks.length ? project.tasks.map((t) => (
              <tr key={t.id} className={t.status === 'done' ? 'b-done-row' : ''}>
                <td><b>{t.title}</b>{t.notes && <div className="a-muted b-sub">{t.notes}</div>}</td>
                <td>{t.milestone?.name || '—'}</td>
                <td>{t.assignee?.name || '—'}</td>
                <td className={t.status !== 'done' && t.due_date && new Date(t.due_date) < new Date(new Date().toDateString()) ? 'b-overdue' : ''}>{formatDate(t.due_date) || '—'}</td>
                <td>{canManage ? (
                  <select value={t.status} onChange={(e) => setStatus(t, e.target.value)} className="b-inline-select">
                    {['todo', 'doing', 'done'].map((s) => <option key={s} value={s}>{label(s)}</option>)}
                  </select>
                ) : <Status value={t.status} />}</td>
                <td className="a-actions">{canManage && <><button className="a-icon-btn" onClick={() => setForm({ ...t, milestone_id: t.milestone_id || '', assigned_to: t.assigned_to || '', due_date: t.due_date || '' })}><FiEdit2 /></button><button className="a-icon-btn danger" onClick={() => remove(t)}><FiTrash2 /></button></>}</td>
              </tr>
            )) : <tr><td colSpan={6} className="a-empty">No tasks yet</td></tr>}
          </tbody>
        </table>
      </div>
      {form && (
        <Modal title={form.id ? 'Edit task' : 'New task'} onClose={() => setForm(null)} footer={<><button className="a-btn a-btn-light" onClick={() => setForm(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save} disabled={!form.title}>Save</button></>}>
          <Field label="Task *"><input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></Field>
          <Field label="Milestone"><select value={form.milestone_id} onChange={(e) => setForm({ ...form, milestone_id: e.target.value })}><option value="">—</option>{project.milestones.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</select></Field>
          <Field label="Assigned to"><select value={form.assigned_to} onChange={(e) => setForm({ ...form, assigned_to: e.target.value })}><option value="">—</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
          <Field label="Due date"><input type="date" value={form.due_date} onChange={(e) => setForm({ ...form, due_date: e.target.value })} /></Field>
          <Field label="Notes"><textarea rows={3} value={form.notes || ''} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
        </Modal>
      )}
    </div>
  )
}

function Files({ project, canManage, onSaved }) {
  const { toast } = useAdmin()
  const input = useRef(null)
  const [busy, setBusy] = useState(false)
  const upload = async (files) => {
    if (!files.length) return
    const fd = new FormData()
    Array.from(files).forEach((f) => fd.append('files[]', f))
    setBusy(true)
    try { const r = await businessApi.post(`/projects/${project.id}/files`, fd); toast(r.data.message); onSaved() } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const download = async (f) => {
    try {
      const r = await businessApi.get(`/projects/${project.id}/files/${f.id}`, { responseType: 'blob' })
      const url = URL.createObjectURL(r.data)
      const a = document.createElement('a'); a.href = url; a.download = f.original_name; a.click()
      setTimeout(() => URL.revokeObjectURL(url), 30000)
    } catch (e) { toast(errorMessage(e), 'error') }
  }
  const remove = async (f) => {
    if (!window.confirm(`Remove ${f.original_name}?`)) return
    try { await businessApi.delete(`/projects/${project.id}/files/${f.id}`); onSaved() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const images = project.files.filter((f) => f.kind === 'image')
  const docs = project.files.filter((f) => f.kind !== 'image')
  return (
    <div className="a-panel b-pad">
      <div className="b-row-head">
        <h3 className="b-panel-title">Site photos & documents</h3>
        {canManage && <button className="a-btn a-btn-primary" onClick={() => input.current?.click()} disabled={busy}><FiUpload /> {busy ? 'Uploading…' : 'Upload'}</button>}
        <input ref={input} type="file" multiple hidden accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.zip" onChange={(e) => { upload(e.target.files); e.target.value = '' }} />
      </div>
      <p className="a-muted b-sub">Photos (JPG/PNG/WebP) can later be published to the website portfolio. Documents (PDF, Word, Excel…) stay private. Max 10 MB each.</p>
      {images.length > 0 && (
        <div className="b-photos">
          {images.map((f) => (
            <figure key={f.id}>
              <a href={img(f.path)} target="_blank" rel="noreferrer"><img src={img(f.path)} alt={f.original_name} loading="lazy" /></a>
              {canManage && <button className="a-icon-btn danger" onClick={() => remove(f)} title="Remove"><FiTrash2 /></button>}
            </figure>
          ))}
        </div>
      )}
      {docs.length > 0 && (
        <ul className="b-docs">
          {docs.map((f) => (
            <li key={f.id}><FiFileText /><span>{f.original_name}</span><small className="a-muted">{Math.round(f.size / 1024)} KB · {f.uploader?.name}</small>
              <button className="a-icon-btn" onClick={() => download(f)} title="Download"><FiDownload /></button>
              {canManage && <button className="a-icon-btn danger" onClick={() => remove(f)} title="Remove"><FiTrash2 /></button>}
            </li>
          ))}
        </ul>
      )}
      {!project.files.length && <div className="a-empty">No files yet</div>}
    </div>
  )
}

function Billing({ project, invoices, canManage, onSaved }) {
  const { toast, can } = useAdmin()
  const [rows, setRows] = useState(null)
  const locked = project.schedules.some((s) => s.invoice_id)
  const save = async () => {
    try { await businessApi.post(`/projects/${project.id}/schedule`, { schedules: rows.map((r) => ({ label: r.label, percent: r.percent === '' ? null : r.percent, amount: r.amount, due_date: r.due_date || null })) }); setRows(null); toast('Schedule saved'); onSaved() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const totalPct = (rows || project.schedules).reduce((s, r) => s + Number(r.percent || 0), 0)
  return (
    <>
      <div className="a-panel">
        <div className="a-panel-head">
          <h3>Payment schedule</h3>
          {canManage && !locked && !rows && <button className="a-btn a-btn-light" onClick={() => setRows(project.schedules.map((s) => ({ ...s, percent: s.percent ?? '', due_date: s.due_date || '' })))}><FiEdit2 /> Edit</button>}
        </div>
        {rows ? (
          <div className="b-pad">
            {rows.map((r, i) => (
              <div key={i} className="b-sched-row">
                <input value={r.label} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, label: e.target.value } : x)))} placeholder="Installment" />
                <label><input type="number" min="0" max="100" step="any" value={r.percent} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, percent: e.target.value } : x)))} /> %</label>
                <input type="date" value={r.due_date} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, due_date: e.target.value } : x)))} />
                <span className="b-money">{money(Number(project.contract_value) * Number(r.percent || 0) / 100)}</span>
                <button className="a-icon-btn danger" onClick={() => setRows(rows.filter((_, j) => j !== i))}><FiTrash2 /></button>
              </div>
            ))}
            <div className="b-row-head b-mt">
              <button className="b-lines-add" onClick={() => setRows([...rows, { label: 'Installment', percent: '', due_date: '' }])}><FiPlus /> Add installment</button>
              <span className={Math.round(totalPct) !== 100 ? 'b-overdue' : 'a-muted'}>Total {totalPct}%</span>
            </div>
            <div className="b-doc-actions b-mt"><button className="a-btn a-btn-light" onClick={() => setRows(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}><FiSave /> Save schedule</button></div>
          </div>
        ) : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Installment</th><th className="r">%</th><th className="r">Amount</th><th>Due</th><th>Invoice</th></tr></thead>
              <tbody>
                {project.schedules.length ? project.schedules.map((s) => (
                  <tr key={s.id}>
                    <td>{s.label}</td><td className="r">{s.percent !== null ? Number(s.percent) : '—'}</td><td className="r"><Money value={s.amount} /></td><td>{formatDate(s.due_date) || '—'}</td>
                    <td>{s.invoice ? <Link to={`/admin/biz/invoices/${s.invoice.id}`}>{s.invoice.number} <Status value={s.invoice.status} /></Link>
                      : can('billing.manage') ? <Link className="a-btn a-btn-light b-btn-sm" to={`/admin/biz/invoices/new?schedule_id=${s.id}`}><FiPlus /> Invoice</Link> : '—'}</td>
                  </tr>
                )) : <tr><td colSpan={5} className="a-empty">No schedule</td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </div>
      <div className="a-panel">
        <div className="a-panel-head"><h3>Invoices</h3><Can perm="billing.manage"><Link className="a-btn a-btn-light" to={`/admin/biz/invoices/new?project_id=${project.id}`}><FiPlus /> Invoice</Link></Can></div>
        <div className="a-table-wrap">
          <table className="a-table">
            <thead><tr><th>No.</th><th>Date</th><th>Status</th><th className="r">Total</th><th className="r">Received</th><th className="r">Due</th></tr></thead>
            <tbody>
              {invoices.length ? invoices.map((i) => (
                <tr key={i.id}><td><Link to={`/admin/biz/invoices/${i.id}`}>{i.number}</Link></td><td>{formatDate(i.date)}</td><td><Status value={i.status} /></td>
                  <td className="r"><Money value={i.total} /></td><td className="r"><Money value={i.paid} /></td><td className="r"><Money value={i.due} /></td></tr>
              )) : <tr><td colSpan={6} className="a-empty">No invoices yet</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}

function Costs({ project, expenses }) {
  return (
    <div className="a-panel">
      <div className="a-panel-head b-wrap-head"><h3>Expenses linked to this project</h3><div className="b-head-actions"><Can perm="accounts.manage"><Link className="a-btn a-btn-light" to={`/admin/biz/transactions?new=expense&project=${project.id}`}><FiPlus /> Expense</Link></Can><Can perm="inventory.manage"><Link className="a-btn a-btn-light" to={`/admin/biz/stock-entries/new?type=issue&project=${project.id}`}><FiPlus /> Issue material</Link><Link className="a-btn a-btn-light" to={`/admin/biz/purchases/new?type=direct&project=${project.id}`}><FiPlus /> Direct purchase</Link></Can><Can perm="labour.view"><Link className="a-btn a-btn-light" to={`/admin/biz/labour?project=${project.id}`}><FiPlus /> Labour</Link></Can></div></div>
      <div className="a-table-wrap">
        <table className="a-table">
          <thead><tr><th>Date</th><th>Category</th><th>Description</th><th className="r">Amount</th></tr></thead>
          <tbody>
            {expenses.length ? expenses.map((t) => (
              <tr key={t.id}><td>{formatDate(t.date)}</td><td>{t.category?.name}</td><td>{t.description}</td><td className="r"><Money value={t.amount} /></td></tr>
            )) : <tr><td colSpan={4} className="a-empty">No expenses recorded for this project</td></tr>}
          </tbody>
        </table>
      </div>
      <p className="a-muted b-sub b-pad">Material issued from stores, direct purchases and labour work recorded for this project are added to the cost automatically.</p>
    </div>
  )
}

function PublishModal({ project, onClose, onDone }) {
  const { toast } = useAdmin()
  const [cats, setCats] = useState([])
  const [form, setForm] = useState({ title: project.title, project_category_id: '', short_description: '', description: project.description || '', location: '', area: '', show_client: false })
  const [errors, setErrors] = useState({})
  useEffect(() => { adminApi.get('/project_categories', { params: { all: 1 } }).then((r) => setCats(r.data.data)).catch(() => {}) }, [])
  const submit = async () => {
    try { await businessApi.post(`/projects/${project.id}/publish`, { ...form, project_category_id: form.project_category_id || null }); toast('Published to the website portfolio'); onDone() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  return (
    <Modal title="Publish to website portfolio" onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={submit}><FiGlobe /> Publish</button></>}>
      <p className="a-muted b-sub">The first photo becomes the cover; the others become the gallery. You can edit it later in Website → Projects.</p>
      {errors.images && <div className="a-alert a-alert-error">{errors.images}</div>}
      <Field label="Title *" error={errors.title}><input value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} /></Field>
      <Field label="Category"><select value={form.project_category_id} onChange={(e) => setForm({ ...form, project_category_id: e.target.value })}><option value="">—</option>{cats.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></Field>
      <Field label="Location (shown on website)"><input value={form.location} onChange={(e) => setForm({ ...form, location: e.target.value })} placeholder="e.g. Gulshan, Dhaka" /></Field>
      <Field label="Area"><input value={form.area} onChange={(e) => setForm({ ...form, area: e.target.value })} placeholder="e.g. 2,400 sqft" /></Field>
      <Field label="Short description"><textarea rows={2} value={form.short_description} onChange={(e) => setForm({ ...form, short_description: e.target.value })} /></Field>
      <label className="switch"><input type="checkbox" checked={form.show_client} onChange={(e) => setForm({ ...form, show_client: e.target.checked })} /><span className="slider" /><span className="switch-label">Show client name</span></label>
    </Modal>
  )
}

export default function ProjectDetail() {
  const { id } = useParams()
  const [params, setParams] = useSearchParams()
  const { toast, can } = useAdmin()
  const [data, setData] = useState(null)
  const [publish, setPublish] = useState(false)
  const tab = params.get('tab') || 'overview'

  const load = useCallback(() => {
    businessApi.get(`/projects/${id}`).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])
  useEffect(() => { load() }, [load])

  if (!data) return <Loader />
  const p = data.data
  const f = data.finance
  const canManage = can('projects.manage')

  return (
    <>
      <div className="a-page-head">
        <h1>{p.title} <Status value={p.status} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/projects" className="a-btn a-btn-light"><FiArrowLeft /> Projects</Link>
          {canManage && <Link to={`/admin/biz/projects/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>}
          {canManage && can('website.manage') && p.status === 'completed' && !p.portfolio && <button className="a-btn a-btn-primary" onClick={() => setPublish(true)}><FiGlobe /> Publish to website</button>}
        </div>
      </div>

      {p.portfolio && <div className="b-callout"><span><FiCheckCircle /> Published on the website as “{p.portfolio.title}”.</span><Link to={`/admin/r/projects/${p.portfolio.id}`}>Edit on website</Link></div>}

      <div className="b-stats">
        <Stat label="Contract" value={money(f.contract, { symbol: true })} sub={`${p.code}`} />
        {can('billing.view') && <Stat label="Invoiced" value={money(f.invoiced, { symbol: true })} />}
        {can('billing.view') && <Stat label="Received" value={money(f.received, { symbol: true })} tone="good" sub={f.receivable > 0 ? `Due ${money(f.receivable)}` : ''} />}
        <Stat label="Cost so far" value={money(f.cost, { symbol: true })} tone="warn" sub={[f.materials ? `Materials ${money(f.materials)}` : '', f.labour ? `Labour ${money(f.labour)}` : ''].filter(Boolean).join(' · ')} />
        <Stat label="Profit (contract − cost)" value={money(f.profit, { symbol: true })} tone={f.profit >= 0 ? 'good' : 'bad'} />
      </div>

      <div className="a-tabs b-tabs">
        {TABS.filter(([k]) => k !== 'billing' || can('billing.view')).map(([k, t]) => <button key={k} type="button" className={tab === k ? 'is-active' : ''} onClick={() => setParams({ tab: k }, { replace: true })}>{t}</button>)}
      </div>

      {tab === 'overview' && (
        <div className="b-detail">
          <div className="b-detail-main">
            <div className="a-panel b-pad">
              <div className="b-row-head"><h3 className="b-panel-title">Progress</h3><span className="a-muted">{p.progress}%</span></div>
              <Progress value={p.progress} />
              <ul className="b-ms-summary">{p.milestones.map((m) => <li key={m.id}><span>{m.name}</span><Progress value={m.progress} /></li>)}</ul>
            </div>
            {p.description && <div className="a-panel b-pad"><h3 className="b-panel-title">Scope</h3><p className="b-pre">{p.description}</p></div>}
          </div>
          <aside className="b-detail-side">
            <div className="a-panel b-pad">
              <InfoGrid items={[
                ['Code', p.code], ['Customer', <Link to={`/admin/biz/customers/${p.customer_id}`}>{p.customer?.name}</Link>], ['Phone', p.customer?.phone],
                ['Site', p.site_address], ['Manager', p.manager?.name], ['Start', formatDate(p.start_date)], ['Expected end', formatDate(p.end_date)],
                ['Handover', formatDate(p.handover_date)], ['Warranty until', formatDate(p.warranty_until)],
                ['Quotation', p.quotation ? <Link to={`/admin/biz/quotations/${p.quotation.id}`}>{p.quotation.number}</Link> : null], ['Branch', p.branch?.name],
              ]} />
            </div>
          </aside>
        </div>
      )}
      {tab === 'work' && <><Milestones key={JSON.stringify(p.milestones)} project={p} canManage={canManage} onSaved={load} /><Tasks project={p} canManage={canManage} onSaved={load} /></>}
      {tab === 'files' && <Files project={p} canManage={canManage} onSaved={load} />}
      {tab === 'billing' && <Billing project={p} invoices={data.invoices} canManage={canManage} onSaved={load} />}
      {tab === 'costs' && <Costs project={p} expenses={data.expenses} />}
      {publish && <PublishModal project={p} onClose={() => setPublish(false)} onDone={() => { setPublish(false); load() }} />}
    </>
  )
}
