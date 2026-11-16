import { FiCheck } from 'react-icons/fi'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import QuoteForm from '../components/QuoteForm'

export default function GetQuote() {
  useTitle('Get a Free Quote')
  const { settings: s } = useSite()
  const points = (s.home_about_points || '').split('\n').map((x) => x.trim()).filter(Boolean)

  return (
    <>
      <PageHeader title="Get a Free Quote" />
      <section className="section">
        <div className="container split">
          <div>
            <span className="tag">Free Consultation</span>
            <h2 className="h2">{s.quote_title || 'Get a Free Quote'}</h2>
            {s.quote_text && <p className="lead">{s.quote_text}</p>}
            {points.length > 0 && (
              <ul className="check-list">{points.map((p) => <li key={p}><FiCheck /> {p}</li>)}</ul>
            )}
            {s.phone && (
              <div className="call-box">
                <span>Prefer to talk?</span>
                <a href={`tel:${s.phone.replace(/\s/g, '')}`}>{s.phone}</a>
              </div>
            )}
          </div>
          <div className="form-card">
            <QuoteForm />
          </div>
        </div>
      </section>
    </>
  )
}
