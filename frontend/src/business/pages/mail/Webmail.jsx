import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiExternalLink, FiMail } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'

/**
 * Opens a cPanel Webmail session in a new tab: the tab is opened inside the click (so popup blockers allow it),
 * then a form inside it POSTs the one-time session to cPanel's login URL.
 */
export function openWebmail(toast) {
  const win = window.open('', '_blank')
  if (win) win.document.title = 'Opening Webmail…'
  return businessApi.post('/webmail/session').then((r) => {
    const { url, session } = r.data
    if (!win) { toast('Allow pop-ups for this site to open Webmail.', 'error'); return }
    const d = win.document
    d.body.textContent = 'Opening Webmail…'
    const form = d.createElement('form')
    form.method = 'POST'
    form.action = url
    const input = d.createElement('input')
    input.type = 'hidden'
    input.name = 'session'
    input.value = session
    form.appendChild(input)
    d.body.appendChild(form)
    form.submit()
  }).catch((e) => {
    if (win) win.close()
    toast(errorMessage(e), 'error')
  })
}

export default function Webmail() {
  const { toast, can } = useAdmin()
  const [info, setInfo] = useState(null)
  const [busy, setBusy] = useState(false)

  useEffect(() => {
    businessApi.get('/webmail').then((r) => setInfo(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [toast])

  if (!info) return <Loader />

  const open = () => { setBusy(true); openWebmail(toast).finally(() => setBusy(false)) }
  const ready = info.configured && info.mailbox

  return (
    <>
      <div className="a-page-head">
        <h1>Webmail</h1>
      </div>
      <div className="a-panel b-webmail">
        <div className="b-webmail-icon"><FiMail /></div>
        {ready ? (
          <>
            <p className="b-webmail-box">{info.mailbox}</p>
            <p className="a-muted">Your company mailbox opens in a new tab, already signed in. No mail password needed.</p>
            <button type="button" className="a-btn" onClick={open} disabled={busy}>
              <FiExternalLink /> {busy ? 'Opening…' : 'Open Webmail'}
            </button>
          </>
        ) : !info.configured ? (
          <>
            <p>Webmail is not set up yet.</p>
            {can('settings.business')
              ? <p className="a-muted">Turn it on in <Link to="/admin/biz/settings">Business Settings → Webmail</Link> (cPanel username and API token).</p>
              : <p className="a-muted">Ask the admin to set it up in Business Settings → Webmail.</p>}
          </>
        ) : (
          <>
            <p>No mailbox is set for your account.</p>
            <p className="a-muted">Ask the admin to add your company e-mail address in Users → Mailbox (Webmail).</p>
          </>
        )}
      </div>
    </>
  )
}
