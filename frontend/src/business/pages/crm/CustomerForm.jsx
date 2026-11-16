import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, Field } from '../../components/ui'

const EMPTY = { branch_id: '', name: '', phone: '', email: '', company: '', address: '', notes: '', marketing_opt_out: false }

export default function CustomerForm() {
  const { id } = useParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const [form, setForm] = useState(isEdit ? null : EMPTY)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!isEdit) return
    businessApi.get(`/customers/${id}`).then((r) => {
      const c = r.data.data
      setForm(Object.fromEntries(Object.keys(EMPTY).map((k) => [k, c[k] ?? ''])))
    }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/customers') })
  }, [id, isEdit, navigate, toast])

  if (!form) return <Loader />
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    try {
      const res = await businessApi.post(isEdit ? `/customers/${id}` : '/customers', form)
      toast(res.data.message)
      navigate(`/admin/biz/customers/${res.data.data.id}`)
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Customer' : 'Add Customer'}</h1>
        <Link to={isEdit ? `/admin/biz/customers/${id}` : '/admin/biz/customers'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {!isEdit && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
          <Field label="Name *" error={errors.name}><input value={form.name} onChange={set('name')} /></Field>
          <Field label="Phone" error={errors.phone}><input value={form.phone} onChange={set('phone')} inputMode="tel" /></Field>
          <Field label="Email" error={errors.email}><input type="email" value={form.email} onChange={set('email')} /></Field>
          <Field label="Company" error={errors.company}><input value={form.company} onChange={set('company')} /></Field>
          <Field label="Address" error={errors.address} full><textarea rows={2} value={form.address} onChange={set('address')} /></Field>
          <Field label="Notes" error={errors.notes} full><textarea rows={3} value={form.notes} onChange={set('notes')} /></Field>
          <label className="b-check b-full"><input type="checkbox" checked={!!form.marketing_opt_out} onChange={(e) => setForm({ ...form, marketing_opt_out: e.target.checked })} /> No marketing SMS / e-mail (opted out)</label>
        </div>
        <div className="a-form-actions"><button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Customer'}</button></div>
      </form>
    </>
  )
}
