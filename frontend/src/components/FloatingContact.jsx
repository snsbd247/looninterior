import { FaWhatsapp, FaFacebookMessenger } from 'react-icons/fa'
import { FiPhone } from 'react-icons/fi'
import { useSite } from '../context/SiteContext'
import { whatsappLink } from '../lib/api'

export default function FloatingContact() {
  const { settings: s } = useSite()
  const wa = whatsappLink(s.whatsapp, `Hello ${s.site_name || ''}, I want to know about your interior services.`)

  return (
    <div className="floating">
      {s.messenger && (
        <a href={s.messenger} target="_blank" rel="noreferrer" className="fl-btn fl-messenger" aria-label="Messenger"><FaFacebookMessenger /></a>
      )}
      {s.phone && (
        <a href={`tel:${s.phone.replace(/\s/g, '')}`} className="fl-btn fl-call" aria-label="Call"><FiPhone /></a>
      )}
      {wa && (
        <a href={wa} target="_blank" rel="noreferrer" className="fl-btn fl-wa" aria-label="WhatsApp"><FaWhatsapp /></a>
      )}
    </div>
  )
}
