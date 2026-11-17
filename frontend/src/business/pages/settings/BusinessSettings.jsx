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
  {
    key: 'webmail', label: 'Webmail',
    fields: [
      { name: 'webmail_enabled', label: 'One-click Webmail', type: 'select', half: true, default: '0', options: [{ value: '1', label: 'On' }, { value: '0', label: 'Off' }], help: 'Staff open their own mailbox from Mail → Webmail without typing the mail password.' },
      { name: 'webmail_domain', label: 'E-mail domain', half: true, placeholder: 'looninterior.com', help: 'Users whose login e-mail ends with this domain get that mailbox automatically. Others: set "Mailbox" in Users.' },
      { name: 'webmail_host', label: 'cPanel server name', half: true, placeholder: 'looninterior.com', help: 'Without https:// and port. cPanel is called on port 2083, Webmail opens on 2096.' },
      { name: 'webmail_cpanel_user', label: 'cPanel username', half: true, placeholder: 'cpanel username' },
      { name: 'webmail_token', label: 'cPanel API token', type: 'secret', placeholder: 'cPanel → Security → Manage API Tokens → Create', help: 'Stored on the server only and shown as ******** afterwards. Leave ******** to keep it; clear the field to remove it. The mailboxes must belong to this cPanel account.' },
    ],
  },
  {
    key: 'sms', label: 'SMS',
    fields: [
      { name: 'sms_enabled', label: 'Automatic SMS (payment, service ticket)', type: 'select', half: true, default: '0', options: [{ value: '1', label: 'On' }, { value: '0', label: 'Off' }], help: 'Off = no automatic SMS. Marketing campaigns are sent from Marketing → Campaigns.' },
      { name: 'sms_provider', label: 'SMS provider', type: 'select', half: true, default: 'custom', options: [{ value: 'greenweb', label: 'Greenweb (BDBulkSMS)' }, { value: 'custom', label: 'Other gateway (URL)' }], help: 'Greenweb: only the API token is needed' },
      { name: 'sms_greenweb_token', label: 'Greenweb API token', half: true, placeholder: 'From your Greenweb / BDBulkSMS dashboard', help: 'Used only when the provider is Greenweb' },
      { name: 'sms_greenweb_url', label: 'Greenweb send URL (advanced)', half: true, placeholder: 'https://api.greenweb.com.bd/api.php', help: 'Leave empty unless Greenweb gives you another address' },
      { name: 'sms_greenweb_balance_url', label: 'Greenweb balance URL (advanced)', half: true, placeholder: 'https://api.greenweb.com.bd/g_api.php' },
      { name: 'sms_method', label: 'Other gateway: method', type: 'select', half: true, default: 'GET', options: [{ value: 'GET', label: 'GET (URL only)' }, { value: 'POST', label: 'POST (URL + body)' }] },
      { name: 'sms_url', label: 'Other gateway: URL', placeholder: 'https://api.your-sms-company.com/send?api_key=KEY&to={to}&msg={message}', help: 'From your SMS provider. {to} = phone (8801XXXXXXXXX), {message} = text. Empty = messages are only written to storage/logs/sms.log (test mode).' },
      { name: 'sms_body', label: 'POST body (only for POST)', type: 'textarea', rows: 2, placeholder: 'api_key=KEY&to={to}&msg={message}   or JSON {"to":"{to}","text":"{message}"}' },
      { name: 'sms_on_payment', label: 'When a payment is received', type: 'select', half: true, options: [{ value: '1', label: 'On' }, { value: '0', label: 'Off' }] },
      { name: 'sms_on_ticket', label: 'When a service ticket is opened', type: 'select', half: true, options: [{ value: '1', label: 'On' }, { value: '0', label: 'Off' }] },
      { name: 'sms_payment_text', label: 'Payment message', type: 'textarea', rows: 2, placeholder: 'Dear {name}, we received Tk {amount} against invoice {invoice}. Receipt: {receipt}. Thank you. - {company}', help: 'Empty = the default shown. Tags: {name} {amount} {invoice} {receipt} {company}' },
      { name: 'sms_ticket_text', label: 'Service ticket message', type: 'textarea', rows: 2, placeholder: 'Dear {name}, your service request {ticket} is registered. Our team will contact you soon. - {company}', help: 'Tags: {name} {ticket} {company}' },
    ],
  },
]

/** Sends a test SMS with the saved settings (save first). */
function SmsTest() {
  const { toast } = useAdmin()
  const [to, setTo] = useState('')
  const [busy, setBusy] = useState(false)
  const send = async () => {
    setBusy(true)
    try { const r = await businessApi.post('/sms/test', { to }); toast(r.data.message) } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const balance = async () => {
    setBusy(true)
    try { const r = await businessApi.get('/sms/balance'); toast(`Balance: ${r.data.message}`, r.data.ok ? undefined : 'error') } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  return (
    <div className="a-field full">
      <span>Test (uses the saved settings, so save first)</span>
      <div className="b-sms-test"><input value={to} onChange={(e) => setTo(e.target.value)} placeholder="01XXXXXXXXX" inputMode="tel" /><button type="button" className="a-btn a-btn-light" onClick={send} disabled={busy || !to}>{busy ? 'Sending…' : 'Send test SMS'}</button><button type="button" className="a-btn a-btn-light" onClick={balance} disabled={busy}>Check balance</button></div>
    </div>
  )
}

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
  if (f.type === 'secret') return <input type="password" autoComplete="new-password" name={name} {...common} />
  if (f.type === 'textarea') return <textarea rows={f.rows || 3} {...common} />
  if (f.type === 'number') return <input type="number" {...common} />
  if (f.type === 'select') {
    return (
      <select value={value || f.default || '1'} onChange={(e) => onChange(e.target.value)}>
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
          {tab === 'sms' && <SmsTest />}
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
