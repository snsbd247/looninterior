import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiChevronLeft, FiChevronRight } from 'react-icons/fi'
import { img } from '../lib/api'
import { useSite } from '../context/SiteContext'

function CtaLink({ to, children }) {
  if (!to) return null
  if (/^https?:\/\//.test(to)) return <a href={to} className="btn btn-primary btn-lg" target="_blank" rel="noreferrer">{children}</a>
  return <Link to={to} className="btn btn-primary btn-lg">{children}</Link>
}

export default function HeroSlider({ slides = [] }) {
  const { settings: s } = useSite()
  const [index, setIndex] = useState(0)
  const count = slides.length

  useEffect(() => {
    if (count < 2) return
    const t = setInterval(() => setIndex((i) => (i + 1) % count), 6000)
    return () => clearInterval(t)
  }, [count, index])

  if (!count) {
    return (
      <section className="hero">
        <div className="hero-slide is-active hero-plain">
          <div className="container hero-content">
            <h1>{s.site_name}</h1>
            {s.tagline && <p>{s.tagline}</p>}
            <CtaLink to="/get-quote">Get a Free Quote</CtaLink>
          </div>
        </div>
      </section>
    )
  }

  const go = (d) => setIndex((i) => (i + d + count) % count)

  return (
    <section className="hero">
      {slides.map((sl, i) => (
        <div
          key={sl.id}
          className={`hero-slide ${i === index ? 'is-active' : ''}`}
          style={sl.image ? { backgroundImage: `url("${img(sl.image)}")` } : undefined}
          aria-hidden={i !== index}
        >
          <div className="container hero-content">
            {s.tagline && <span className="hero-tag">{s.tagline}</span>}
            {sl.title && <h1>{sl.title}</h1>}
            {sl.subtitle && <p>{sl.subtitle}</p>}
            <div className="hero-actions">
              <CtaLink to={sl.button_link}>{sl.button_text || 'Learn More'}</CtaLink>
              <Link to="/projects" className="btn btn-outline-light btn-lg">Our Projects</Link>
            </div>
          </div>
        </div>
      ))}

      {count > 1 && (
        <>
          <button className="hero-arrow prev" onClick={() => go(-1)} aria-label="Previous slide"><FiChevronLeft /></button>
          <button className="hero-arrow next" onClick={() => go(1)} aria-label="Next slide"><FiChevronRight /></button>
          <div className="hero-dots">
            {slides.map((sl, i) => (
              <button key={sl.id} className={i === index ? 'is-active' : ''} onClick={() => setIndex(i)} aria-label={`Slide ${i + 1}`} />
            ))}
          </div>
        </>
      )}
    </section>
  )
}
