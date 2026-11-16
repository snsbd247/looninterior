import { useEffect, useState } from 'react'
import { Link, NavLink, useLocation } from 'react-router-dom'
import { FiPhone, FiMail, FiClock, FiMenu, FiX, FiChevronDown } from 'react-icons/fi'
import { useSite } from '../context/SiteContext'
import { img } from '../lib/api'
import Socials from './Socials'

export default function Header() {
  const { settings: s, services, categories } = useSite()
  const [open, setOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const location = useLocation()

  useEffect(() => setOpen(false), [location.pathname, location.search])

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 40)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  useEffect(() => {
    document.body.style.overflow = open ? 'hidden' : ''
    return () => { document.body.style.overflow = '' }
  }, [open])

  return (
    <>
      <div className="topbar">
        <div className="container topbar-inner">
          <div className="topbar-info">
            {s.phone && <a href={`tel:${s.phone.replace(/\s/g, '')}`}><FiPhone /> {s.phone}</a>}
            {s.email && <a href={`mailto:${s.email}`}><FiMail /> {s.email}</a>}
            {s.office_hours && <span className="hide-sm"><FiClock /> {s.office_hours}</span>}
          </div>
          <Socials settings={s} className="socials socials-sm" />
        </div>
      </div>

      <header className={`header ${scrolled ? 'is-scrolled' : ''} ${open ? 'menu-open' : ''}`}>
        {open && <div className="nav-backdrop" onClick={() => setOpen(false)} />}
        <div className="container header-inner">
          <Link to="/" className="logo" aria-label={s.site_name}>
            {s.logo ? <img src={img(s.logo)} alt={s.site_name} /> : <span className="logo-text">{s.site_name || 'Interior'}</span>}
          </Link>

          <nav className={`nav ${open ? 'is-open' : ''}`}>
            <NavLink to="/" end>Home</NavLink>
            <NavLink to="/about">About</NavLink>

            <div className="nav-drop">
              <NavLink to="/services">Services {services.length > 0 && <FiChevronDown className="caret" />}</NavLink>
              {services.length > 0 && (
                <div className="drop">
                  {services.map((x) => <Link key={x.id} to={`/services/${x.slug}`}>{x.title}</Link>)}
                </div>
              )}
            </div>

            <div className="nav-drop">
              <NavLink to="/projects">Projects {categories.length > 0 && <FiChevronDown className="caret" />}</NavLink>
              {categories.length > 0 && (
                <div className="drop">
                  {categories.map((c) => <Link key={c.id} to={`/projects?category=${c.slug}`}>{c.name}</Link>)}
                </div>
              )}
            </div>

            <NavLink to="/blog">Blog</NavLink>
            <NavLink to="/careers">Careers</NavLink>
            <NavLink to="/contact">Contact</NavLink>
            <Link to="/get-quote" className="btn btn-primary nav-cta">Get a Quote</Link>
          </nav>

          <button className="burger" onClick={() => setOpen((o) => !o)} aria-label="Toggle menu">
            {open ? <FiX /> : <FiMenu />}
          </button>
        </div>
      </header>
    </>
  )
}
