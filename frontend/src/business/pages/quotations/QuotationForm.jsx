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

export default function QuotationForm() {
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
    if (isEdit) {
      businessApi.get(`/quotations/${id}`).then((r) => {
        const q = r.data.data
        if (q.status !== 'draft') { toast('Only drafts can be edited', 'error'); navigate(`/admin/biz/quotations/${id}`); return }
        setForm({
          customer_id: q.customer_id, lead_id: q.lead_id || '', title: q.title || '', date: q.date, valid_until: q.valid_until || '',
          discount_type: q.discount_type, discount_value: q.discount_value, tax_rate_id: q.tax_rate_id || '', terms: q.terms || '', notes: q.notes || '',
        })
        setItems(q.items.map((i) => ({ section: i.section || '', description: i.description, unit: i.unit || '', qty: Number(i.qty), rate: Number(i.rate) })))
      }).catch((e) => { toast(errorMessage(e), 'error'); navigate('/admin/biz/quotations') })
    } else {
      setForm({
        customer_id: params.get('customer_id') || '', lead_id: params.get('lead_id') || '', title: '', date: today(), valid_until: plusDays(15),
        discount_type: 'amount', discount_value: 0, tax_rate_id: '', terms: '', notes: '',
      })
      setItems([blankItem('Living Room')])
    }
  }, [id, isEdit, navigate, params, toast])

  // Default tax rate for new quotations
  useEffect(() => {
    if (!isEdit && form && form.tax_rate_id === '' && taxRates.length && !form._taxTouched) {
      const def = taxRates.find((t) => t.is_default)
      if (def) setForm((f) => ({ ...f, tax_rate_id: def.id, _taxTouched: true }))
    }
  }, [taxRates, form, isEdit])

  if (!form) return <Loader />
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const { _taxTouched, ...payload } = form // eslint-disable-line no-unused-vars
    try {
      const res = await businessApi.post(isEdit ? `/quotations/${id}` : '/quotations', {
        ...payload, lead_id: payload.lead_id || null, tax_rate_id: payload.tax_rate_id || null, valid_until: payload.valid_until || null,
        items: items.filter((i) => i.description.trim() || Number(i.rate)),
      })
      toast(res.data.message)
      navigate(`/admin/biz/quotations/${res.data.data.id}`)
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{isEdit ? 'Edit Quotation' : 'New Quotation'}</h1>
        <Link to={isEdit ? `/admin/biz/quotations/${id}` : '/admin/biz/quotations'} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <Field label="Customer *" error={errors.customer_id}>
            <CustomerSelect value={form.customer_id} onChange={(v) => setForm({ ...form, customer_id: v })} disabled={isEdit} />
          </Field>
          <Field label="Project / Title" error={errors.title}><input value={form.title} onChange={set('title')} placeholder="e.g. 3 Bed Apartment Interior, Gulshan" /></Field>
          <Field label="Date *" error={errors.date}><input type="date" value={form.date} onChange={set('date')} /></Field>
          <Field label="Valid Until" error={errors.valid_until}><input type="date" value={form.valid_until} onChange={set('valid_until')} /></Field>
        </div>

        <h3 className="b-section-title">Items (BOQ)</h3>
        <LineItems items={items} onChange={setItems} errors={errors} />

        <div className="b-form-bottom">
          <div className="a-form-grid b-form-notes">
            <Field label="Terms & Conditions" help="Empty = company terms from Business Settings" error={errors.terms} full><textarea rows={4} value={form.terms} onChange={set('terms')} /></Field>
            <Field label="Note on document" error={errors.notes} full><textarea rows={2} value={form.notes} onChange={set('notes')} /></Field>
          </div>
          <TotalsBox items={items} form={form} setForm={setForm} taxRates={taxRates}>
            {errors.discount_value && <small className="field-error">{errors.discount_value}</small>}
          </TotalsBox>
        </div>

        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Quotation'}</button>
          <span className="a-muted">Saved as draft. Totals are recalculated on the server.</span>
        </div>
      </form>
    </>
  )
}
