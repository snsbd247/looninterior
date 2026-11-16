import { Link } from 'react-router-dom'
import { useSite } from '../context/SiteContext'
import { img } from '../lib/api'

export default function PageHeader({ title, crumbs = [], image }) {
  const { settings } = useSite()
  const bg = image || settings.page_header_bg
  return (
    <section className="page-header" style={bg ? { backgroundImage: `url("${img(bg)}")` } : undefined}>
      <div className="container">
        <h1>{title}</h1>
        <nav className="crumbs">
          <Link to="/">Home</Link>
          {crumbs.map((c, i) => (
            <span key={i}>
              <span className="sep">/</span>
              {c.to ? <Link to={c.to}>{c.label}</Link> : <span>{c.label}</span>}
            </span>
          ))}
          <span className="sep">/</span>
          <span className="current">{title}</span>
        </nav>
      </div>
    </section>
  )
}
