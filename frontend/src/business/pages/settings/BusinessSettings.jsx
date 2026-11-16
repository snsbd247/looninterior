import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiSave, FiFileText, FiPercent, FiMapPin } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { ImageInput } from '../../../admin/Fields'
import { useAdmin } from '../../../admin/AdminApp'

/* Fields grouped into tabs. "bn: true" adds a Bangla twin field (key + "_bn"). */
const TABS = [
  {
    key: 'company', label: 'Company',
    fields: [
      { name: 'company_name', label: 'Company Name', bn: true, help: 'Empty = website name' },
      { name: 'address', label: 'Address', type: 'textarea', bn: true, help: 'Empty = website address' },
      { name: 'phone', label: 'Phone', half: true },
      { name: 'email', label: 'Email', half: true },
      { name: 'website', label: 'Website', half: true },
      { name: 'bin', label: 'BIN (VAT Registration No.)', half: true },
      { name: 'tin', label: 'TIN', half: true },
      { name: 'logo', label: 'Logo on documents', type: 'image', help: 'Empty = website logo. PNG/JPG works best in PDF.' },
    ],
  },
  {
    key: 'documents', label: 'Documents',
    fields: [
      { name: 'terms', label: 'Terms & Conditions', type: 'textarea', rows: 6, bn: true },
      { name: 'bank_details', label: 'Bank Details', type: 'textarea', bn: true, help: 'Bank, branch, account name & number, routing no.' },
      { name: 'footer', label: 'Footer Line', bn: true, help: 'Small text at the bottom of every page' },
    ],
  },
  {
    key: 'signature', label: 'Signature & Seal',
    fields: [
      { name: 'signatory_name', label: 'Signatory Name', bn: true },
      { name: 'signatory_title', label: 'Signatory Designation', bn: true },
      { name: 'signature', label: 'Signature Image', type: 'image', half: true, help: 'Transparent PNG' },
      { name: 'seal', label: 'Company Seal', type: 'image', half: true, help: 'Transparent PNG' },
    ],
  },
  {
    key: 'numbering', label: 'Numbering',
    fields: [
      { name: 'number_format', label: 'Document Number Format', half: true, placeholder: '{BRANCH}-{TYPE}-{YYYY}-{SEQ}', help: '{BRANCH} branch code · {TYPE} QT/INV/RCV… · {YYYY} or {YY} year · {SEQ} running number (required)' },
      { name: 'number_padding', label: 'Number Digits', type: 'number', half: true, placeholder: '4', help: '4 → 0001' },
      { name: 'bn_digits', label: 'Use Bangla digits (১২৩) in Bangla documents', type: 'select', half: true, options: [{ value: '1', label: 'Yes' }, { value: '0', label: 'No (123)' }] },
    ],
  },
  {
    key: 'crm', label: 'CRM',
    fields: [
      { name: 'lead_branch_id', label: 'Branch for website leads', type: 'branch', half: true, help: 'Quote requests from the website become leads in this branch. Empty = first active branch.' },
    ],
  },
]

const IMAGE_KEYS = TABS.flatMap((t) => t.fields).filter((f) => f.type === 'image').map((f) => f.name)
const TEXT_KEYS = TABS.flatMap((t) => t.fields).filter((f) => f.type !== 'image').flatMap((f) => (f.bn ? [f.name, `${f.name}_bn`] : [f.name]))

function Input({ f, name, value, onChange, error }) {
  const { user } = useAdmin()
  const common = { value: value ?? '', onChange: (e) => onChange(e.target.value), placeholder: f.placeholder || '' }
  if (f.type === 'branch') {
    return (
      <select value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
        <option value="">— First active branch —</option>
        {(user.branches || []).map((b) => <option key={b.id} value={String(b.id)}>{b.name} ({b.code})</option>)}
      </select>
    )
  }
  if (f.type === 'textarea') return <textarea rows={f.rows || 3} {...common} />
  if (f.type === 'number') return <input type="number" {...common} />
  if (f.type === 'select') {
    return (
      <select value={value ?? '1'} onChange={(e) => onChange(e.target.value)}>
        {f.options.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
      </select>
    )
  }
  return <input type="text" name={name} className={error ? 'has-error' : ''} {...common} />
}

export default function BusinessSettings() {
  const { toast, can } = useAdmin()
  const [tab, setTab] = useState(TABS[0].key)
  const [saved, setSaved] = useState(null)
  const [values, setValues] = useState({})
  const [files, setFiles] = useState({})
  const [removed, setRemoved] = useState({})
  const [errors, setErrors] = useState({})
  const [preview, setPreview] = useState('')
  const [saving, setSaving] = useState(false)
  const [pdfLoading, setPdfLoading] = useState('')

  useEffect(() => {
    businessApi.get('/settings').then((r) => {
      setSaved(r.data.data)
      setValues(r.data.data)
      setPreview(r.data.number_preview)
    }).catch((e) => toast(errorMessage(e), 'error'))
  }, [toast])

  if (!saved) return <Loader />

  const key = (n) => `biz_${n}`
  const setVal = (n) => (v) => setValues((s) => ({ ...s, [key(n)]: v }))

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})
    const fd = new FormData()
    TEXT_KEYS.forEach((n) => fd.append(key(n), values[key(n)] ?? ''))
    IMAGE_KEYS.forEach((n) => {
      if (files[n]) fd.append(key(n), files[n])
      else if (removed[n]) fd.append(`remove_${key(n)}`, '1')
    })
    try {
      const res = await businessApi.post('/settings', fd)
      setSaved(res.data.data)
      setValues(res.data.data)
      setPreview(res.data.number_preview)
      setFiles({})
      setRemoved({})
      toast(res.data.message)
    } catch (ex) {
      const fe = fieldErrors(ex)
      setErrors(fe)
      toast(errorMessage(ex), 'error')
    } finally {
      setSaving(false)
    }
  }

  const openPdf = async (lang) => {
    setPdfLoading(lang)
    // Open the tab synchronously (inside the click) so popup blockers allow it.
    const win = window.open('', '_blank')
    try {
      const res = await businessApi.get('/pdf/sample', { params: { lang }, responseType: 'blob' })
      const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      if (win) win.location.href = url
      else window.location.href = url
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } catch (ex) {
      if (win) win.close()
      toast(errorMessage(ex), 'error')
    } finally {
      setPdfLoading('')
    }
  }

  const current = TABS.find((t) => t.key === tab)

  return (
    <>
      <div className="a-page-head">
        <h1>Business Settings</h1>
        <div className="b-head-actions">
          <button type="button" className="a-btn a-btn-light" onClick={() => openPdf('en')} disabled={!!pdfLoading}><FiFileText /> {pdfLoading === 'en' ? 'Loading…' : 'Sample PDF (English)'}</button>
          <button type="button" className="a-btn a-btn-light" onClick={() => openPdf('bn')} disabled={!!pdfLoading}><FiFileText /> {pdfLoading === 'bn' ? 'Loading…' : 'Sample PDF (Bangla)'}</button>
        </div>
      </div>
      <p className="a-muted b-sub b-intro">
        These details print on quotations, invoices, receipts and purchase orders. Save first, then open a sample PDF to check.
        {' '}Related: {can('settings.business') && <Link to="/admin/r/tax_rates"><FiPercent /> Tax Rates</Link>}
        {can('branches.manage') && <> · <Link to="/admin/r/branches"><FiMapPin /> Branches</Link></>}
      </p>

      <div className="a-tabs">
        {TABS.map((t) => <button key={t.key} type="button" className={t.key === tab ? 'is-active' : ''} onClick={() => setTab(t.key)}>{t.label}</button>)}
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {current.fields.map((f) => {
            if (f.type === 'image') {
              return (
                <div key={f.name} className={`a-field ${f.half ? '' : 'full'}`}>
                  <span>{f.label}</span>
                  <ImageInput
                    value={saved[key(f.name)]}
                    file={files[f.name]}
                    removed={removed[f.name]}
                    onFile={(file) => { setFiles((s) => ({ ...s, [f.name]: file })); setRemoved((s) => ({ ...s, [f.name]: false })) }}
                    onRemove={() => { setFiles((s) => ({ ...s, [f.name]: null })); setRemoved((s) => ({ ...s, [f.name]: true })) }}
                    onRestore={() => setRemoved((s) => ({ ...s, [f.name]: false }))}
                  />
                  {f.help && <small className="a-help">{f.help}</small>}
                  {errors[key(f.name)] && <small className="field-error">{errors[key(f.name)]}</small>}
                </div>
              )
            }
            const blocks = [{ name: f.name, label: f.bn ? `${f.label} (English)` : f.label }]
            if (f.bn) blocks.push({ name: `${f.name}_bn`, label: `${f.label} (Bangla)` })
            return blocks.map((b) => (
              <label key={b.name} className={`a-field ${f.half || f.bn ? '' : 'full'}`}>
                <span>{b.label}</span>
                <Input f={f} name={b.name} value={values[key(b.name)]} onChange={setVal(b.name)} error={errors[key(b.name)]} />
                {f.help && b.name === f.name && <small className="a-help">{f.help}</small>}
                {errors[key(b.name)] && <small className="field-error">{errors[key(b.name)]}</small>}
              </label>
            ))
          })}
          {tab === 'numbering' && preview && (
            <div className="a-field full">
              <span>Next invoice number in this branch</span>
              <div className="b-number-preview">{preview}</div>
            </div>
          )}
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Settings'}</button>
          <span className="a-muted">Saves all tabs at once</span>
        </div>
      </form>
    </>
  )
}
