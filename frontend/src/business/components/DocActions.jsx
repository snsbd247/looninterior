import { useState } from 'react'
import { FiFileText, FiMail, FiDownload } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { businessApi, errorMessage } from '../../lib/api'
import { useAdmin } from '../../admin/AdminApp'
import { Field, Modal } from './ui'
import { waHref } from '../lib'

/** Open a PDF from the business API in a new tab (opened inside the click so popups are allowed). */
export async function openPdf(path, lang, toast, download = false) {
  const win = download ? null : window.open('', '_blank')
  try {
    const res = await businessApi.get(path, { params: { lang, download: download ? 1 : 0 }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    if (download) {
      const a = document.createElement('a')
      a.href = url
      a.download = (res.headers['content-disposition'] || '').match(/filename="([^"]+)"/)?.[1] || 'document.pdf'
      document.body.appendChild(a); a.click(); a.remove()
    } else if (win) win.location.href = url
    else window.location.href = url
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    if (win) win.close()
    toast(errorMessage(e), 'error')
  }
}

/**
 * PDF (English/Bangla), e-mail and WhatsApp buttons for a document.
 *  base: "/quotations/5" (pdf at base/pdf, email at base/email)
 */
export default function DocActions({ base, email, phone, shareUrl, shareText, canEmail = true, onEmailed }) {
  const { toast } = useAdmin()
  const [mail, setMail] = useState(null)
  const [sending, setSending] = useState(false)

  const send = async () => {
    setSending(true)
    try {
      const res = await businessApi.post(`${base}/email`, mail)
      toast(res.data.message)
      setMail(null)
      onEmailed?.()
    } catch (e) { toast(errorMessage(e), 'error') } finally { setSending(false) }
  }

  const wa = shareUrl ? waHref(phone, `${shareText}\n${shareUrl.en}`) : null

  return (
    <>
      <div className="b-doc-actions">
        <button type="button" className="a-btn a-btn-light" onClick={() => openPdf(`${base}/pdf`, 'en', toast)}><FiFileText /> PDF</button>
        <button type="button" className="a-btn a-btn-light" onClick={() => openPdf(`${base}/pdf`, 'bn', toast)}><FiFileText /> PDF (Bangla)</button>
        <button type="button" className="a-btn a-btn-light" onClick={() => openPdf(`${base}/pdf`, 'en', toast, true)} title="Download"><FiDownload /></button>
        {canEmail && <button type="button" className="a-btn a-btn-light" onClick={() => setMail({ to: email || '', lang: 'en', message: '' })}><FiMail /> Email</button>}
        {wa && phone && <a className="a-btn a-btn-light" href={wa} target="_blank" rel="noreferrer"><FaWhatsapp /> WhatsApp</a>}
      </div>
      {mail && (
        <Modal title="Send by e-mail" onClose={() => setMail(null)} footer={<>
          <button type="button" className="a-btn a-btn-light" onClick={() => setMail(null)}>Cancel</button>
          <button type="button" className="a-btn a-btn-primary" onClick={send} disabled={sending || !mail.to}><FiMail /> {sending ? 'Sending…' : 'Send'}</button>
        </>}>
          <Field label="To"><input type="email" value={mail.to} onChange={(e) => setMail({ ...mail, to: e.target.value })} /></Field>
          <Field label="PDF language">
            <select value={mail.lang} onChange={(e) => setMail({ ...mail, lang: e.target.value })}><option value="en">English</option><option value="bn">Bangla</option></select>
          </Field>
          <Field label="Message (optional)"><textarea rows={4} value={mail.message} onChange={(e) => setMail({ ...mail, message: e.target.value })} placeholder="Dear Sir, please find the attached document…" /></Field>
        </Modal>
      )}
    </>
  )
}
