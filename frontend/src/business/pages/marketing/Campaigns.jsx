import { useCallback, useEffect, useRef, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiPlus, FiSend, FiArrowLeft, FiEdit2, FiRotateCcw, FiPause, FiMessageSquare, FiMail, FiSave, FiUsers } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, DeleteButton, Field, Stat, Status } from '../../components/ui'
import Pagination from '../../components/Pagination'
import { dateTime, label } from '../../lib'

/*
 * Marketing campaigns (phase G): one SMS or e-mail to a filtered list of leads/customers.
 * Sending runs in batches from this page (POST …/batch until done), so keep the page open while it sends.
 */
const STAGES = ['new', 'contacted', 'site_visit', 'quotation_sent', 'negotiation', 'won', 'lost']
const SOURCES = ['website', 'phone', 'facebook', 'instagram', 'whatsapp', 'referral', 'walk_in', 'other']
const STATUS_TONE = { draft: 'draft', sending: 'in_progress', sent: 'paid', cancelled: 'cancelled' }

const ChannelIcon = ({ c }) => (c === 'sms' ? <FiMessageSquare /> : <FiMail />)

export function CampaignList() {
  const { toast } = useAdmin()
  const [rows, setRows] = useState(null)
  useEffect(() => { businessApi.get('/campaigns').then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  return (
    <>
      <div className="a-page-head"><h1>Campaigns</h1><Can perm="marketing.send"><Link to="/admin/biz/campaigns/new" className="a-btn a-btn-primary"><FiPlus /> New campaign</Link></Can></div>
      <p className="a-muted b-sub b-intro">Send an offer or news to your leads and customers by SMS or e-mail. People who opted out are skipped automatically.</p>
      <div className="a-panel">{!rows ? <Loader /> : (
        <div className="a-table-wrap"><table className="a-table">
          <thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th className="r">Sent</th><th className="r">Failed</th><th>Date</th></tr></thead>
          <tbody>{rows.length ? rows.map((c) => (
            <tr key={c.id}><td><Link to={`/admin/biz/campaigns/${c.id}`}><b>{c.name}</b></Link><div className="a-muted b-sub">{c.creator?.name}{c.branch ? ` · ${c.branch.code}` : ''}</div></td>
              <td className="b-nowrap"><ChannelIcon c={c.channel} /> {c.channel === 'sms' ? 'SMS' : 'E-mail'}</td>
              <td><Status value={STATUS_TONE[c.status]} text={label(c.status)} /></td>
              <td className="r">{c.sent}/{c.total}</td><td className="r">{c.failed || ''}</td><td className="b-nowrap">{dateTime(c.sent_at || c.created_at)}</td></tr>
          )) : <tr><td colSpan={6} className="a-empty">No campaigns yet</td></tr>}</tbody>
        </table></div>
      )}</div>
    </>
  )
}

const blank = { name: '', channel: 'sms', subject: '', message: '', audience: { type: 'customers', customers: 'all', stages: [], sources: [], from: '', to: '' } }

export function CampaignForm() {
  const { id } = useParams()
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [form, setForm] = useState(id ? null : blank)
  const [errors, setErrors] = useState({})
  const [preview, setPreview] = useState(null)
  const timer = useRef(null)

  useEffect(() => {
    if (id) businessApi.get(`/campaigns/${id}`).then((r) => { const c = r.data.data; setForm({ ...blank, ...c, subject: c.subject || '', audience: { ...blank.audience, ...(c.audience || {}) } }) }).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])

  // Live audience count (debounced).
  useEffect(() => {
    if (!form) return undefined
    clearTimeout(timer.current)
    timer.current = setTimeout(() => {
      businessApi.post('/campaigns/preview', { channel: form.channel, message: form.message, audience: form.audience }).then((r) => setPreview(r.data)).catch(() => setPreview(null))
    }, 400)
    return () => clearTimeout(timer.current)
  }, [form])

  if (!form) return <Loader />
  const a = form.audience
  const setA = (patch) => setForm({ ...form, audience: { ...a, ...patch } })
  const toggle = (key, v) => setA({ [key]: a[key].includes(v) ? a[key].filter((x) => x !== v) : [...a[key], v] })
  const save = async () => {
    setErrors({})
    try {
      const r = await businessApi.post(id ? `/campaigns/${id}` : '/campaigns', form)
      toast(r.data.message); navigate(`/admin/biz/campaigns/${r.data.data.id}`)
    } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  const chars = form.message.length
  return (
    <>
      <div className="a-page-head"><h1>{id ? 'Edit campaign' : 'New campaign'}</h1><Link to={id ? `/admin/biz/campaigns/${id}` : '/admin/biz/campaigns'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link></div>
      <div className="b-detail b-side-last">
        <div className="b-detail-main">
          <div className="a-panel b-pad">
            <div className="a-form-grid">
              {!id && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
              <Field label="Campaign name *" error={errors.name} help="For you only, e.g. “Eid offer 2026”"><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></Field>
              <Field label="Send by">
                <div className="b-toggle"><button type="button" className={form.channel === 'sms' ? 'is-on' : ''} onClick={() => setForm({ ...form, channel: 'sms' })}><FiMessageSquare /> SMS</button><button type="button" className={form.channel === 'email' ? 'is-on' : ''} onClick={() => setForm({ ...form, channel: 'email' })}><FiMail /> E-mail</button></div>
              </Field>
              {form.channel === 'email' && <Field label="Subject *" error={errors.subject} full><input value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} placeholder="e.g. 10% off on kitchen interiors this Eid" /></Field>}
              <Field label="Message *" error={errors.message} full help="{name} = the person's name, {company} = your company name">
                <textarea rows={form.channel === 'sms' ? 4 : 9} value={form.message} onChange={(e) => setForm({ ...form, message: e.target.value })} />
              </Field>
            </div>
            {form.channel === 'sms' && <p className="a-muted b-sub">{chars} characters · {preview?.sms_parts || 1} SMS per person{/[^\x00-\x7F]/.test(form.message) ? ' (Bangla: 70 characters per SMS)' : ' (160 characters per SMS)'}</p>}
            {form.channel === 'email' && <p className="a-muted b-sub">An “Unsubscribe” link is added at the bottom of every e-mail.</p>}
          </div>
        </div>
        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <h3 className="b-panel-title"><FiUsers /> Who gets it</h3>
            <Field label="Send to"><select value={a.type} onChange={(e) => setA({ type: e.target.value })}><option value="customers">Customers</option><option value="leads">Leads</option><option value="both">Customers and leads</option></select></Field>
            {a.type !== 'leads' && <Field label="Customers"><select value={a.customers} onChange={(e) => setA({ customers: e.target.value })}><option value="all">All customers</option><option value="with_project">With a project</option><option value="completed_project">With a completed project</option></select></Field>}
            {a.type !== 'customers' && (<>
              <div className="b-filter-label">Lead stage <span className="a-muted">(none = all)</span></div>
              <div className="b-chips-pick">{STAGES.map((s) => <button key={s} type="button" className={a.stages.includes(s) ? 'is-on' : ''} onClick={() => toggle('stages', s)}>{label(s)}</button>)}</div>
              <div className="b-filter-label">Lead source <span className="a-muted">(none = all)</span></div>
              <div className="b-chips-pick">{SOURCES.map((s) => <button key={s} type="button" className={a.sources.includes(s) ? 'is-on' : ''} onClick={() => toggle('sources', s)}>{label(s)}</button>)}</div>
            </>)}
            <div className="b-two">
              <Field label="Added from"><input type="date" value={a.from || ''} onChange={(e) => setA({ from: e.target.value })} /></Field>
              <Field label="to"><input type="date" value={a.to || ''} onChange={(e) => setA({ to: e.target.value })} /></Field>
            </div>
            <div className="b-audience-count">
              <b>{preview ? preview.count : '…'}</b> people
              {form.channel === 'sms' && preview && <span className="a-muted"> · about {preview.count * (preview.sms_parts || 1)} SMS</span>}
            </div>
            {preview?.sample?.length > 0 && <ul className="b-sample">{preview.sample.map((p) => <li key={p.type + p.id}>{p.name} <span className="a-muted">{p.to}</span></li>)}</ul>}
            <p className="a-muted b-sub">Only people with a valid {form.channel === 'sms' ? 'mobile number' : 'e-mail'} are counted. Duplicates and people who opted out are skipped.</p>
          </div>
          <button className="a-btn a-btn-primary b-wide" onClick={save}><FiSave /> Save draft</button>
        </aside>
      </div>
    </>
  )
}

export function CampaignDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const navigate = useNavigate()
  const [d, setD] = useState(null)
  const [filter, setFilter] = useState('')
  const [page, setPage] = useState(1)
  const [running, setRunning] = useState(false)
  const stopRef = useRef(false)
  const load = useCallback(() => businessApi.get(`/campaigns/${id}`, { params: { status: filter, page } }).then((r) => setD(r.data)).catch((e) => toast(errorMessage(e), 'error')), [id, filter, page, toast])
  useEffect(() => { load() }, [load])
  useEffect(() => () => { stopRef.current = true }, [])

  const run = async () => {
    setRunning(true); stopRef.current = false
    try {
      for (;;) {
        const r = await businessApi.post(`/campaigns/${id}/batch`)
        setD((x) => (x ? { ...x, data: r.data.data } : x))
        if (r.data.done || stopRef.current) break
      }
      toast(stopRef.current ? 'Paused' : 'Campaign sent')
    } catch (e) { toast(errorMessage(e), 'error') } finally { setRunning(false); load() }
  }
  const post = async (path, msg, then) => { try { const r = await businessApi.post(`/campaigns/${id}/${path}`); toast(r.data.message || msg); await load(); if (then) then(r.data) } catch (e) { toast(errorMessage(e), 'error') } }
  const start = () => {
    const c = d.data
    const what = c.channel === 'sms' ? 'SMS (this costs SMS balance)' : 'e-mails'
    if (!window.confirm(`Send ${what} to everyone in this campaign now?`)) return
    post('start', 'Sending started', () => run())
  }

  if (!d) return <Loader />
  const c = d.data
  const send = can('marketing.send')
  const pct = c.total ? Math.round(((c.sent + c.failed) / c.total) * 100) : 0
  return (
    <>
      <div className="a-page-head">
        <h1><ChannelIcon c={c.channel} /> {c.name} <Status value={STATUS_TONE[c.status]} text={label(c.status)} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/campaigns" className="a-btn a-btn-light"><FiArrowLeft /> Campaigns</Link>
          {send && c.status === 'draft' && <><Link to={`/admin/biz/campaigns/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link><DeleteButton path={`/campaigns/${id}`} what="draft campaign" onDone={() => navigate('/admin/biz/campaigns')} /><button className="a-btn a-btn-primary" onClick={start}><FiSend /> Send now</button></>}
          {send && c.status === 'sending' && (running
            ? <button className="a-btn a-btn-light" onClick={() => { stopRef.current = true }}><FiPause /> Pause</button>
            : <><button className="a-btn a-btn-primary" onClick={run}><FiSend /> Continue sending</button><button className="a-btn a-btn-light danger" onClick={() => post('stop', 'Stopped')}>Stop</button></>)}
          {send && ['sent', 'cancelled'].includes(c.status) && c.failed > 0 && <button className="a-btn a-btn-light" onClick={() => post('retry', 'Queued again', (r) => r.retry && run())}><FiRotateCcw /> Retry failed ({c.failed})</button>}
        </div>
      </div>
      {c.status !== 'draft' && (
        <div className="a-panel b-pad">
          <div className="b-row-head"><b>{c.sent} sent{c.failed ? ` · ${c.failed} failed` : ''} of {c.total}</b><span className="a-muted">{pct}%</span></div>
          <div className="b-progress"><span style={{ width: `${pct}%` }} /></div>
          {running && <p className="a-muted b-sub">Sending… keep this page open.</p>}
        </div>
      )}
      <div className="b-stats">
        <Stat label="Channel" value={c.channel === 'sms' ? 'SMS' : 'E-mail'} sub={c.channel === 'sms' && d.sms_parts ? `${d.sms_parts} SMS per person` : ''} />
        <Stat label="Recipients" value={c.total || '—'} sub={c.status === 'draft' ? 'Counted when you send' : ''} />
        <Stat label="Created" value={dateTime(c.created_at)} sub={c.creator?.name} />
      </div>
      <div className="a-panel b-pad">
        <h3 className="b-panel-title">Message</h3>
        {c.subject && <p><b>{c.subject}</b></p>}
        <p className="b-pre">{c.message}</p>
      </div>
      {c.status !== 'draft' && (
        <div className="a-panel">
          <div className="a-toolbar"><div className="b-toggle">{[['', 'All'], ['sent', 'Sent'], ['failed', 'Failed'], ['pending', 'Waiting']].map(([k, t]) => <button key={k} className={filter === k ? 'is-on' : ''} onClick={() => { setPage(1); setFilter(k) }}>{t}{d.counts?.[k] ? ` (${d.counts[k]})` : ''}</button>)}</div></div>
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Name</th><th>To</th><th>Status</th><th>Note</th></tr></thead>
            <tbody>{d.recipients.data.length ? d.recipients.data.map((r) => (
              <tr key={r.id}><td>{r.name}<div className="a-muted b-sub">{label(r.recipient_type)}</div></td><td className="b-nowrap">{r.to}</td>
                <td><Status value={{ sent: 'paid', failed: 'cancelled', pending: 'draft', sending: 'in_progress' }[r.status]} text={label(r.status)} /></td><td className="b-sub b-err" title={r.error || ""}>{r.error && r.error.length > 80 ? `${r.error.slice(0, 80)}…` : r.error}</td></tr>
            )) : <tr><td colSpan={4} className="a-empty">Nobody here</td></tr>}</tbody>
          </table></div>
          <Pagination meta={d.recipients} page={page} setPage={setPage} />
        </div>
      )}
    </>
  )
}
