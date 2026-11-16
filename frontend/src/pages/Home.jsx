import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiCheck, FiArrowRight } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import Loader from '../components/Loader'
import HeroSlider from '../components/HeroSlider'
import SectionHead from '../components/SectionHead'
import Img from '../components/Img'
import QuoteForm from '../components/QuoteForm'
import { ServiceCard, ProjectCard, PostCard } from '../components/Cards'
import {
  StatsBand, FeaturesSection, ProcessSection, TestimonialsSection, ClientsSection, FaqList, CtaBand,
} from '../components/Sections'

export default function Home() {
  useTitle('')
  const { settings: s } = useSite()
  const { data, loading } = useFetch('/home')
  const [cat, setCat] = useState('all')

  const projectCats = useMemo(() => {
    const map = new Map()
    ;(data?.projects || []).forEach((p) => p.category && map.set(p.category.slug, p.category.name))
    return [...map.entries()]
  }, [data])

  if (loading) return <Loader full />
  if (!data) return <div className="container section"><p>Unable to load content.</p></div>

  const projects = cat === 'all' ? data.projects : data.projects.filter((p) => p.category?.slug === cat)
  const points = (s.home_about_points || '').split('\n').map((x) => x.trim()).filter(Boolean)

  return (
    <>
      <HeroSlider slides={data.sliders} />

      {(s.home_about_title || s.home_about_text) && (
        <section className="section">
          <div className="container about-intro">
            <div className="about-media">
              <Img src={s.home_about_image} alt={s.home_about_title} />
              {s.years_experience && (
                <div className="exp-badge">
                  <strong>{s.years_experience}+</strong>
                  <span>Years of<br />Experience</span>
                </div>
              )}
            </div>
            <div>
              <SectionHead tag={s.home_about_tag || 'About Us'} title={s.home_about_title} center={false} />
              {s.home_about_text && <p className="lead">{s.home_about_text}</p>}
              {points.length > 0 && (
                <ul className="check-list">
                  {points.map((p) => <li key={p}><FiCheck /> {p}</li>)}
                </ul>
              )}
              <Link to="/about" className="btn btn-dark">More About Us <FiArrowRight /></Link>
            </div>
          </div>
        </section>
      )}

      {data.services.length > 0 && (
        <section className="section bg-cream">
          <div className="container">
            <SectionHead tag="What We Do" title={s.services_title} text={s.services_subtitle} />
            <div className="grid-3">
              {data.services.map((x) => <ServiceCard key={x.id} service={x} />)}
            </div>
            <div className="center mt-40"><Link to="/services" className="btn btn-outline">All Services</Link></div>
          </div>
        </section>
      )}

      <StatsBand stats={data.stats} />

      {data.projects.length > 0 && (
        <section className="section">
          <div className="container">
            <SectionHead tag="Portfolio" title={s.projects_title} text={s.projects_subtitle} />
            {projectCats.length > 1 && (
              <div className="filter-tabs">
                <button className={cat === 'all' ? 'is-active' : ''} onClick={() => setCat('all')}>All</button>
                {projectCats.map(([slug, name]) => (
                  <button key={slug} className={cat === slug ? 'is-active' : ''} onClick={() => setCat(slug)}>{name}</button>
                ))}
              </div>
            )}
            <div className="grid-3 projects-grid">
              {projects.map((p) => <ProjectCard key={p.id} project={p} />)}
            </div>
            <div className="center mt-40"><Link to="/projects" className="btn btn-primary">View All Projects</Link></div>
          </div>
        </section>
      )}

      <FeaturesSection items={data.features} title={s.features_title} subtitle={s.features_subtitle} />
      <ProcessSection steps={data.process} title={s.process_title} subtitle={s.process_subtitle} />
      <TestimonialsSection items={data.testimonials} title={s.testimonials_title} />
      <ClientsSection items={data.clients} title={s.clients_title} />

      <section className="section bg-cream">
        <div className={`container ${data.faqs.length ? 'split' : 'narrow'}`}>
          {data.faqs.length > 0 && (
            <div>
              <SectionHead tag="FAQ" title={s.faq_title} center={false} />
              <FaqList items={data.faqs} />
            </div>
          )}
          <div className="form-card" id="quote">
            <h3>{s.quote_title || 'Get a Free Quote'}</h3>
            {s.quote_text && <p className="muted">{s.quote_text}</p>}
            <QuoteForm compact />
          </div>
        </div>
      </section>

      {data.posts.length > 0 && (
        <section className="section">
          <div className="container">
            <SectionHead tag="Blog" title={s.blog_title} />
            <div className="grid-3">
              {data.posts.map((p) => <PostCard key={p.id} post={p} />)}
            </div>
          </div>
        </section>
      )}

      <CtaBand />
    </>
  )
}
