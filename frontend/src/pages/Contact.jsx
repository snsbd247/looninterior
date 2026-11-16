import { FiPhone, FiMail, FiMapPin, FiClock } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { useSite, useTitle } from '../context/SiteContext'
import { mapSrc, whatsappLink } from '../lib/api'
import PageHeader from '../components/PageHeader'
import QuoteForm from '../components/QuoteForm'
import Socials from '../components/Socials'

export default function Contact() {
  useTitle('Contact Us')
  const { settings: s } = useSite()
  const tel = (n) => `tel:${n.replace(/\s/g, '')}`

  const cards = [
    s.phone && { icon: FiPhone, title: 'Call Us', lines: [s.phone, s.phone_2].filter(Boolean), href: tel(s.phone) },
    s.whatsapp && { icon: FaWhatsapp, title: 'WhatsApp', lines: [s.whatsapp], href: whatsappLink(s.whatsapp), external: true },
    s.email && { icon: FiMail, title: 'Email', lines: [s.email], href: `mailto:${s.email}` },
    s.address && { icon: FiMapPin, title: 'Visit Us', lines: [s.address] },
    s.office_hours && { icon: FiClock, title: 'Office Hours', lines: [s.office_hours] },
  ].filter(Boolean)

  return (
    <>
      <PageHeader title="Contact Us" />
      <section className="section">
        <div className="container split">
          <div>
            <span className="tag">Get In Touch</span>
            <h2 className="h2">We'd love to hear about your project</h2>
            <div className="contact-cards">
              {cards.map((c) => {
                const Ic = c.icon
                const body = (
                  <>
                    <span className="cc-icon"><Ic /></span>
                    <div><h4>{c.title}</h4>{c.lines.map((l) => <p key={l}>{l}</p>)}</div>
                  </>
                )
                return c.href
                  ? <a key={c.title} href={c.href} className="contact-card" {...(c.external ? { target: '_blank', rel: 'noreferrer' } : {})}>{body}</a>
                  : <div key={c.title} className="contact-card">{body}</div>
              })}
            </div>
            <Socials settings={s} className="socials socials-dark" />
          </div>
          <div className="form-card">
            <h3>Send us a message</h3>
            <QuoteForm type="contact" />
          </div>
        </div>
      </section>
      {s.map_embed && (
        <div className="map-wrap">
          <iframe src={mapSrc(s.map_embed)} title="Location map" loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen />
        </div>
      )}
    </>
  )
}
