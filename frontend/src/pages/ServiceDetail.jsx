import { Link, useParams } from 'react-router-dom'
import { FiArrowRight } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import Img from '../components/Img'
import Rich from '../components/Rich'
import QuoteForm from '../components/QuoteForm'
import SectionHead from '../components/SectionHead'
import { ProjectCard } from '../components/Cards'
import Icon from '../lib/icons'
import NotFound from './NotFound'

export default function ServiceDetail() {
  const { slug } = useParams()
  const { settings: s } = useSite()
  const { data, loading, error } = useFetch(`/services/${slug}`)
  useTitle(data?.data?.title || 'Service')

  if (loading) return <Loader full />
  if (error || !data) return <NotFound />
  const svc = data.data

  return (
    <>
      <PageHeader title={svc.title} crumbs={[{ label: 'Services', to: '/services' }]} />
      <section className="section">
        <div className="container detail-layout">
          <article>
            {svc.image && <Img src={svc.image} alt={svc.title} className="detail-cover" />}
            {svc.short_description && <p className="lead">{svc.short_description}</p>}
            <Rich html={svc.description} />
          </article>
          <aside className="sidebar">
            {data.others.length > 0 && (
              <div className="side-card">
                <h4>Other Services</h4>
                <ul className="side-links">
                  {data.others.map((o) => (
                    <li key={o.id}><Link to={`/services/${o.slug}`}><Icon name={o.icon} fallback="layers" /> {o.title} <FiArrowRight /></Link></li>
                  ))}
                </ul>
              </div>
            )}
            <div className="side-card">
              <h4>{s.quote_title || 'Get a Free Quote'}</h4>
              <QuoteForm compact />
            </div>
          </aside>
        </div>
      </section>
      {data.projects.length > 0 && (
        <section className="section bg-cream">
          <div className="container">
            <SectionHead tag="Portfolio" title="Recent Projects" />
            <div className="grid-3">{data.projects.map((p) => <ProjectCard key={p.id} project={p} />)}</div>
          </div>
        </section>
      )}
    </>
  )
}
