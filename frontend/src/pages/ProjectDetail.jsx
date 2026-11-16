import { useState } from 'react'
import { useParams, Link } from 'react-router-dom'
import { FiUser, FiMapPin, FiMaximize, FiCalendar, FiFolder } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import { videoEmbed } from '../lib/api'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import Img from '../components/Img'
import Rich from '../components/Rich'
import SectionHead from '../components/SectionHead'
import { ProjectCard } from '../components/Cards'
import { Lightbox, CtaBand } from '../components/Sections'
import NotFound from './NotFound'

export default function ProjectDetail() {
  const { slug } = useParams()
  const { settings: s } = useSite()
  const { data, loading, error } = useFetch(`/projects/${slug}`)
  const [lb, setLb] = useState(null)
  useTitle(data?.data?.title || 'Project')

  if (loading) return <Loader full />
  if (error || !data) return <NotFound />

  const p = data.data
  const gallery = p.images.length ? p.images.map((i) => i.image) : (p.cover_image ? [p.cover_image] : [])
  const embed = videoEmbed(p.video_url)
  const info = [
    [FiFolder, 'Category', p.category?.name],
    [FiUser, 'Client', p.client_name],
    [FiMapPin, 'Location', p.location],
    [FiMaximize, 'Area', p.area],
    [FiCalendar, 'Year', p.year],
  ].filter(([, , v]) => v)

  return (
    <>
      <PageHeader title={p.title} crumbs={[{ label: 'Projects', to: '/projects' }]} image={p.cover_image} />
      <section className="section">
        <div className="container detail-layout">
          <article>
            {gallery.length > 0 && (
              <div className="gallery">
                {gallery.map((g, i) => (
                  <button key={i} className={`gallery-item ${i === 0 ? 'big' : ''}`} onClick={() => setLb(i)}>
                    <Img src={g} alt={`${p.title} ${i + 1}`} />
                  </button>
                ))}
              </div>
            )}
            {p.short_description && <p className="lead mt-30">{p.short_description}</p>}
            <Rich html={p.description} />
            {embed && (
              <div className="video-wrap mt-30"><iframe src={embed} title={p.title} allowFullScreen loading="lazy" /></div>
            )}
            {!embed && p.video_url && (
              <p className="mt-30"><a href={p.video_url} target="_blank" rel="noreferrer" className="btn btn-outline">Watch Video / Virtual Tour</a></p>
            )}
          </article>
          <aside className="sidebar">
            {info.length > 0 && (
              <div className="side-card">
                <h4>Project Info</h4>
                <ul className="info-list">
                  {info.map(([Ic, label, value]) => (
                    <li key={label}><Ic /> <span>{label}</span> <strong>{value}</strong></li>
                  ))}
                </ul>
              </div>
            )}
            <div className="side-card side-cta">
              <h4>Like this project?</h4>
              <p>Let's design something just as beautiful for you.</p>
              <Link to="/get-quote" className="btn btn-primary btn-block">Get a Free Quote</Link>
              {s.phone && <a href={`tel:${s.phone.replace(/\s/g, '')}`} className="btn btn-outline btn-block mt-10">Call {s.phone}</a>}
            </div>
          </aside>
        </div>
      </section>

      {data.related.length > 0 && (
        <section className="section bg-cream">
          <div className="container">
            <SectionHead tag="More Work" title="Related Projects" />
            <div className="grid-3">{data.related.map((r) => <ProjectCard key={r.id} project={r} />)}</div>
          </div>
        </section>
      )}
      <CtaBand />
      <Lightbox images={gallery} index={lb} onClose={() => setLb(null)} onIndex={setLb} />
    </>
  )
}
