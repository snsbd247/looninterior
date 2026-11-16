import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { FiArrowLeft, FiSave } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Field } from '../../components/ui'
import LineItems, { TotalsBox, blankItem } from '../../components/LineItems'
import CustomerSelect from '../../components/CustomerSelect'
import { today, useLookup } from '../../lib'

const plusDays = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return d.toISOString().slice(0, 10) }

export default function InvoiceForm() {
  const { id } = useParams()
  const [params] = useSearchParams()
  const isEdit = Boolean(id)
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const taxRates = useLookup('tax-rates')
  const [form, setForm] = useState(null)
  const [items, setItems] = useState([blankItem('')])
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    const base = { customer_id: '', work_project_id: '', quotation_id: '', schedule_id: '', title: '', date: today(), due_date: plusDays(7), discount_type: 'amount', discount_value: 0, tax_rate_id: '', terms: '', notes: '' }
    const toItems = (list) => (list?.length ? list.map((i) => ({ section: i.section || '', description: i.description, unit: i.unit || '', qty: Number(i.qty), rate: Number(i.rate) })) : [blankItem('')])
    if (isEdit) {
      businessApi.get(`/invoices/${id}`).then((r) => {
        const i = r.data.data
        if (i.status !== 'draft') { toast('Only drafts can be edited', 'error'); navigate(`/admin/biz/invoices/${id}`); return }
        setForm({ ...base, ...Object.fromEntries(Object.keys(base).map((k) => [k, i[k] ?? base[k]])) })
        setItems(toItems(i.items))
      }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/invoices') })
      return
    }
    const src = ['schedule_id', 'quotation_id', 'project_id'].find((k) => params.get(k))
    if (src) {
      businessApi.get('/invoices/prefill', { params: { [src]: params.get(src) } }).then((r) => {
        const d = r.data.data
        setForm({ ...base, ...Object.fromEntries(Object.entries(d).filter(([k]) => k !== 'items').map(([k, v]) => [k, v ?? ''])) })
        setItems(toItems(d.items))
      }).catch((e) => { toast(errorMessage(e), 'error'); setForm(base) })
    } else {
      setForm({ ...base, customer_id: params.get('customer_id') || '' })
    }
  }, [id, isEdit, navigate, params, toast])

  if (!form) return <Loader />
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const payload = { ...form, items: items.filter((i) => i.description.trim() || Number(i.rate)) }
    ;['work_project_id', 'quotation_id', 'schedule_id', 'tax_rate_id', 'due_date'].forEach((k) => { if (!payload[k]) payload[k] = null })
    try {
      const res = await businessApi.post(isEdit ? `/invoices/${id}` : '/invoices', payload)
      toast(res.data.message)
      navigate(`/admin/biz/invoices/${res.data.data.id}`)
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }

  const locked = Boolean(form.work_project_id || form.schedule_id)
  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Invoice' : 'New Invoice'}</h1>
        <Link to={isEdit ? `/admin/biz/invoices/${id}` : '/admin/biz/invoices'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <Field label="Customer *" error={errors.customer_id}><CustomerSelect value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} disabled={isEdit || locked} /></Field>
          <Field label="Title" error={errors.title}><input value={form.title || ''} onChange={set('title')} placeholder="e.g. Advance for Gulshan apartment" /></Field>
          <Field label="Invoice Date *" error={errors.date}><input type="date" value={form.date} onChange={set('date')} /></Field>
          <Field label="Due Date" error={errors.due_date}><input type="date" value={form.due_date || ''} onChange={set('due_date')} /></Field>
        </div>
        {errors.schedule_id && <div className="a-alert a-alert-error b-mt">{errors.schedule_id}</div>}
        <h3 className="b-section-title">Items</h3>
        <LineItems items={items} onChange={setItems} errors={errors} />
        <div className="b-form-bottom">
          <div className="a-form-grid b-form-notes">
            <Field label="Terms" help="Empty = company terms" error={errors.terms} full><textarea rows={3} value={form.terms || ''} onChange={set('terms')} /></Field>
            <Field label="Note on invoice" error={errors.notes} full><textarea rows={2} value={form.notes || ''} onChange={set('notes')} /></Field>
          </div>
          <TotalsBox items={items} form={form} setForm={setForm} taxRates={taxRates}>
            {errors.discount_value && <small className="field-error">{errors.discount_value}</small>}
          </TotalsBox>
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save as Draft'}</button>
          <span className="a-muted">Issue it from the next page to start collecting payment.</span>
        </div>
      </form>
    </>
  )
}
