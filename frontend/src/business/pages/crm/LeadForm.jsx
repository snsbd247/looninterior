import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, Field } from '../../components/ui'
import { label, useLookup } from '../../lib'
import { SOURCES } from './Leads'

const EMPTY = { branch_id: '', name: '', phone: '', email: '', address: '', source: 'phone', service: '', property_type: '', area_size: '', budget: '', expected_value: '', requirements: '', assigned_to: '' }

export default function LeadForm() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast, user } = useAdmin()
  const staff = useLookup('staff')
  const [form, setForm] = useState(isEdit ? null : { ...EMPTY, assigned_to: user.id })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!isEdit) return
    businessApi.get(`/leads/${id}`).then((r) => {
      const l = r.data.data
      setForm(Object.fromEntries(Object.keys(EMPTY).map((k) => [k, l[k] ?? ''])))
    }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/leads') })
  }, [id, isEdit, navigate, toast])

  if (!form) return <Loader />
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const res = await businessApi.post(isEdit ? `/leads/${id}` : '/leads', form)
      toast(res.data.message)
      navigate(`/admin/biz/leads/${res.data.data.id}`)
    } catch (ex) {
      setErrors(fieldErrors(ex))
      toast(errorMessage(ex), 'error')
    } finally {
      setSaving(false)
    }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Lead' : 'Add Lead'}</h1>
        <Link to={isEdit ? `/admin/biz/leads/${id}` : '/admin/biz/leads'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {!isEdit && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
          <Field label="Name *" error={errors.name}><input value={form.name} onChange={set('name')} /></Field>
          <Field label="Phone" error={errors.phone}><input value={form.phone} onChange={set('phone')} inputMode="tel" /></Field>
          <Field label="Email" error={errors.email}><input type="email" value={form.email} onChange={set('email')} /></Field>
          <Field label="Source" error={errors.source}>
            <select value={form.source} onChange={set('source')}>{SOURCES.map((s) => <option key={s} value={s}>{label(s)}</option>)}</select>
          </Field>
          <Field label="Service" error={errors.service}><input value={form.service} onChange={set('service')} placeholder="e.g. Residential Interior" /></Field>
          <Field label="Property Type" error={errors.property_type}><input value={form.property_type} onChange={set('property_type')} placeholder="Apartment, Office…" /></Field>
          <Field label="Area Size" error={errors.area_size}><input value={form.area_size} onChange={set('area_size')} placeholder="e.g. 1800 sqft" /></Field>
          <Field label="Budget" error={errors.budget}><input value={form.budget} onChange={set('budget')} placeholder="e.g. 10 – 20 Lakh" /></Field>
          <Field label="Expected Value (৳)" error={errors.expected_value} help="Estimated deal size, used in pipeline totals"><input type="number" min="0" value={form.expected_value} onChange={set('expected_value')} /></Field>
          <Field label="Assigned To" error={errors.assigned_to}>
            <select value={form.assigned_to || ''} onChange={set('assigned_to')}>
              <option value="">— Unassigned —</option>
              {staff.map((u) => <option key={u.id} value={u.id}>{u.name}{u.role ? ` (${u.role.name})` : ''}</option>)}
            </select>
          </Field>
          <Field label="Address / Location" error={errors.address} full><textarea rows={2} value={form.address} onChange={set('address')} /></Field>
          <Field label="Requirements" error={errors.requirements} full><textarea rows={4} value={form.requirements} onChange={set('requirements')} /></Field>
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Lead'}</button>
        </div>
      </form>
    </>
  )
}
