import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { FiArrowLeft, FiEdit2, FiPhone, FiMail, FiUser, FiCheckCircle, FiCircle, FiClock, FiFileText } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { Field, InfoGrid, Money, Status } from '../../components/ui'
import { dateTime, label, phoneHref, toLocalInput, waHref } from '../../lib'
import { STAGES } from './Leads'

const TYPES = [['call', 'Call'], ['meeting', 'Meeting'], ['visit', 'Site Visit'], ['note', 'Note'], ['follow_up', 'Follow-up only']]

function ActivityForm({ leadId, onSaved }) {
  const { toast } = useAdmin()
  const [form, setForm] = useState({ type: 'call', note: '', follow_up_at: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      await businessApi.post(`/leads/${leadId}/activities`, { ...form, follow_up_at: form.follow_up_at || null })
      setForm({ type: 'call', note: '', follow_up_at: '' })
      toast('Saved')
      onSaved()
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }
  return (
    <form className="b-activity-form" onSubmit={submit}>
      <div className="b-chips-select">
        {TYPES.map(([v, t]) => <button type="button" key={v} className={form.type === v ? 'is-on' : ''} onClick={() => setForm({ ...form, type: v })}>{t}</button>)}
      </div>
      <textarea rows={3} placeholder="What happened? (call summary, meeting notes…)" value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} />
      {errors.note && <small className="field-error">{errors.note}</small>}
      <div className="b-activity-foot">
        <label className="b-inline-field"><FiClock /> Next follow-up
          <input type="datetime-local" value={form.follow_up_at} onChange={(e) => setForm({ ...form, follow_up_at: e.target.value })} />
        </label>
        <button className="a-btn a-btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Add to timeline'}</button>
      </div>
    </form>
  )
}

export default function LeadDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [lead, setLead] = useState(null)
  const [busy, setBusy] = useState(false)
  const canManage = can('crm.manage')

  const load = useCallback(() => {
    businessApi.get(`/leads/${id}`).then((r) => setLead(r.data.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])
  useEffect(() => { load() }, [load])

  if (!lead) return <Loader />

  const setStage = async (stage) => {
    let lost_reason
    if (stage === 'lost') {
      lost_reason = window.prompt('Why was this lead lost? (price, timing, chose competitor…)')
      if (!lost_reason) return
    }
    setBusy(true)
    try {
      await businessApi.post(`/leads/${id}/stage`, { stage, lost_reason })
      toast(stage === 'won' ? 'Won! Customer created.' : `Stage: ${label(stage)}`)
      load()
    } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }

  const toggle = async (a) => {
    try { await businessApi.post(`/lead-activities/${a.id}/toggle`); load() } catch (e) { toast(errorMessage(e), 'error') }
  }

  const convert = async () => {
    setBusy(true)
    try { await businessApi.post(`/leads/${id}/convert`); toast('Customer ready'); load() } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }

  const stageIndex = STAGES.indexOf(lead.stage)

  return (
    <>
      <div className="a-page-head">
        <h1>{lead.name} <Status value={lead.stage} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/leads" className="a-btn a-btn-light"><FiArrowLeft /> Leads</Link>
          {canManage && <Link to={`/admin/biz/leads/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>}
        </div>
      </div>

      {canManage && (
        <div className="b-stagebar">
          {STAGES.filter((s) => s !== 'lost').map((s, i) => (
            <button key={s} type="button" disabled={busy} className={`${s === lead.stage ? 'is-current' : ''} ${i < stageIndex && lead.stage !== 'lost' ? 'is-done' : ''}`} onClick={() => setStage(s)}>{label(s)}</button>
          ))}
          <button type="button" disabled={busy} className={`is-lost ${lead.stage === 'lost' ? 'is-current' : ''}`} onClick={() => setStage('lost')}>Lost</button>
        </div>
      )}

      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel b-pad">
            <h3 className="b-panel-title">Timeline</h3>
            {canManage && <ActivityForm leadId={lead.id} onSaved={load} />}
            <ul className="b-timeline">
              {lead.activities.map((a) => (
                <li key={a.id} className={`t-${a.type}`}>
                  <div className="b-tl-head">
                    <b>{label(a.type === 'visit' ? 'site_visit' : a.type)}</b>
                    <span className="a-muted">{a.user?.name || 'Website'} · {dateTime(a.created_at)}</span>
                  </div>
                  {a.note && <p>{a.note}</p>}
                  {a.follow_up_at && (
                    <button type="button" className={`b-followup ${a.done_at ? 'is-done' : new Date(a.follow_up_at) < new Date() ? 'is-overdue' : ''}`} onClick={() => canManage && toggle(a)} disabled={!canManage}>
                      {a.done_at ? <FiCheckCircle /> : <FiCircle />} Follow-up {dateTime(a.follow_up_at)}{a.done_at ? ' · done' : ''}
                    </button>
                  )}
                </li>
              ))}
            </ul>
          </div>
        </div>

        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <div className="b-contact-actions">
              {lead.phone && <a className="a-btn a-btn-light" href={phoneHref(lead.phone)}><FiPhone /> Call</a>}
              {lead.phone && <a className="a-btn a-btn-light" href={waHref(lead.phone)} target="_blank" rel="noreferrer"><FaWhatsapp /> WhatsApp</a>}
              {lead.email && <a className="a-btn a-btn-light" href={`mailto:${lead.email}`}><FiMail /> Email</a>}
            </div>
            <InfoGrid items={[
              ['Phone', lead.phone], ['Email', lead.email], ['Source', label(lead.source)], ['Branch', lead.branch?.name],
              ['Assigned to', lead.assignee?.name], ['Service', lead.service], ['Property', lead.property_type],
              ['Area', lead.area_size], ['Budget', lead.budget],
              ['Expected value', Number(lead.expected_value) > 0 ? <Money value={lead.expected_value} symbol /> : null],
              ['Address', lead.address], lead.lost_reason && ['Lost reason', lead.lost_reason],
              ['Next follow-up', lead.next_follow_up_at ? dateTime(lead.next_follow_up_at) : null],
              ['Created', dateTime(lead.created_at)],
            ]} />
            {lead.requirements && <><h4 className="b-mini-title">Requirements</h4><p className="b-pre">{lead.requirements}</p></>}
          </div>

          <div className="a-panel b-pad">
            <h3 className="b-panel-title"><FiUser /> Customer</h3>
            {lead.customer ? (
              <>
                <Link to={`/admin/biz/customers/${lead.customer.id}`} className="b-link-strong">{lead.customer.name}</Link>
                <Can perm="quotations.manage">
                  <Link to={`/admin/biz/quotations/new?customer_id=${lead.customer.id}&lead_id=${lead.id}`} className="a-btn a-btn-primary a-btn-block b-mt"><FiFileText /> New Quotation</Link>
                </Can>
              </>
            ) : (
              <>
                <p className="a-muted b-sub">A customer record is created automatically when the lead is won, or now to prepare a quotation.</p>
                {canManage && <button type="button" className="a-btn a-btn-light a-btn-block" onClick={convert} disabled={busy}><FiUser /> Create customer now</button>}
              </>
            )}
          </div>
        </aside>
      </div>
    </>
  )
}
