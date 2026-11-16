import { Link } from 'react-router-dom'
import { FiPhone, FiMail, FiMapPin, FiClock } from 'react-icons/fi'
import { useSite } from '../context/SiteContext'
import { img } from '../lib/api'
import Socials from './Socials'

export default function Footer() {
  const { settings: s, services, pages } = useSite()
  const logo = s.logo_light || s.logo

  return (
    <footer className="footer">
      <div className="container footer-grid">
        <div>
          <Link to="/" className="logo footer-logo">
            {logo ? <img src={img(logo)} alt={s.site_name} /> : <span className="logo-text">{s.site_name}</span>}
          </Link>
          {s.footer_about && <p className="footer-about">{s.footer_about}</p>}
          <Socials settings={s} />
        </div>

        <div>
          <h4>Quick Links</h4>
          <ul>
            <li><Link to="/about">About Us</Link></li>
            <li><Link to="/projects">Projects</Link></li>
            <li><Link to="/blog">Blog</Link></li>
            <li><Link to="/careers">Careers</Link></li>
            <li><Link to="/faq">FAQ</Link></li>
            <li><Link to="/service-request">Service Request</Link></li>
            <li><Link to="/contact">Contact</Link></li>
            {pages.map((p) => <li key={p.id}><Link to={`/page/${p.slug}`}>{p.title}</Link></li>)}
          </ul>
        </div>

        <div>
          <h4>Services</h4>
          <ul>
            {services.slice(0, 7).map((x) => <li key={x.id}><Link to={`/services/${x.slug}`}>{x.title}</Link></li>)}
          </ul>
        </div>

        <div>
          <h4>Contact</h4>
          <ul className="footer-contact">
            {s.address && <li><FiMapPin /> <span>{s.address}</span></li>}
            {s.phone && <li><FiPhone /> <a href={`tel:${s.phone.replace(/\s/g, '')}`}>{s.phone}</a></li>}
            {s.phone_2 && <li><FiPhone /> <a href={`tel:${s.phone_2.replace(/\s/g, '')}`}>{s.phone_2}</a></li>}
            {s.email && <li><FiMail /> <a href={`mailto:${s.email}`}>{s.email}</a></li>}
            {s.office_hours && <li><FiClock /> <span>{s.office_hours}</span></li>}
          </ul>
        </div>
      </div>
      <div className="footer-bottom">
        <div className="container">{s.copyright || `© ${new Date().getFullYear()} ${s.site_name || ''}`}</div>
      </div>
    </footer>
  )
}
