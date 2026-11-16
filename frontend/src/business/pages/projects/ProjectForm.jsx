import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Field, Money } from '../../components/ui'
import CustomerSelect from '../../components/CustomerSelect'
import { label, today, useLookup } from '../../lib'
import { P_STATUSES } from './Projects'

const KEYS = ['customer_id', 'quotation_id', 'title', 'site_address', 'contract_value', 'start_date', 'end_date', 'handover_date', 'warranty_until', 'manager_id', 'status', 'progress', 'description']

export default function ProjectForm() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const staff = useLookup('staff')
  const [form, setForm] = useState(null)
  const [quotation, setQuotation] = useState(null)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (isEdit) {
      businessApi.get(`/projects/${id}`).then((r) => {
        const p = r.data.data
        setForm(Object.fromEntries(KEYS.map((k) => [k, p[k] ?? ''])))
      }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/projects') })
      return
    }
    const qid = params.get('quotation_id')
    const base = { customer_id: '', quotation_id: qid || '', title: '', site_address: '', contract_value: '', start_date: today(), end_date: '', manager_id: '', status: 'planning', description: '' }
    if (qid) {
      businessApi.get(`/quotations/${qid}`).then((r) => {
        const q = r.data.data
        setQuotation(q)
        setForm({ ...base, customer_id: q.customer_id, title: q.title || '', site_address: q.customer?.address || '', contract_value: q.total })
      }).catch((e) => { toast(errorMessage(e), 'error'); setForm(base) })
    } else setForm(base)
  }, [id, isEdit, navigate, params, toast])

  if (!form) return <Loader />
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
    try {
      const res = await businessApi.post(isEdit ? `/projects/${id}` : '/projects', payload)
      toast(res.data.message)
      navigate(`/admin/biz/projects/${res.data.data.id}`)
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Project' : 'New Project'}</h1>
        <Link to={isEdit ? `/admin/biz/projects/${id}` : '/admin/biz/projects'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      {quotation && <div className="b-callout">From approved quotation <b>{quotation.number}</b> · <Money value={quotation.total} symbol /> · default milestones and a 50/30/20 payment schedule will be created (editable later).</div>}
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {!isEdit && !quotation && (
            <Field label="Customer *" error={errors.customer_id}><CustomerSelect value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} /></Field>
          )}
          <Field label={isEdit ? 'Title *' : 'Title'} error={errors.title}><input value={form.title} onChange={set('title')} placeholder="e.g. Gulshan 3 Bed Apartment" /></Field>
          <Field label="Contract Value (৳)" error={errors.contract_value}><input type="number" min="0" step="any" value={form.contract_value} onChange={set('contract_value')} /></Field>
          <Field label="Project Manager" error={errors.manager_id}>
            <select value={form.manager_id || ''} onChange={set('manager_id')}>
              <option value="">— None —</option>
              {staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
            </select>
          </Field>
          <Field label="Status" error={errors.status}>
            <select value={form.status} onChange={set('status')}>{P_STATUSES.map((s) => <option key={s} value={s}>{label(s)}</option>)}</select>
          </Field>
          <Field label="Start Date" error={errors.start_date}><input type="date" value={form.start_date || ''} onChange={set('start_date')} /></Field>
          <Field label="Expected End" error={errors.end_date}><input type="date" value={form.end_date || ''} onChange={set('end_date')} /></Field>
          {isEdit && <Field label="Handover Date" error={errors.handover_date}><input type="date" value={form.handover_date || ''} onChange={set('handover_date')} /></Field>}
          {isEdit && <Field label="Warranty Until" error={errors.warranty_until} help="Used by service tickets"><input type="date" value={form.warranty_until || ''} onChange={set('warranty_until')} /></Field>}
          <Field label="Site Address" error={errors.site_address} full><textarea rows={2} value={form.site_address || ''} onChange={set('site_address')} /></Field>
          <Field label="Description / Scope" error={errors.description} full><textarea rows={3} value={form.description || ''} onChange={set('description')} /></Field>
        </div>
        <div className="a-form-actions"><button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Project'}</button></div>
      </form>
    </>
  )
}
