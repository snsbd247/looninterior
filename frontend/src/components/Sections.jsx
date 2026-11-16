import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiChevronLeft, FiChevronRight, FiPlus, FiMinus, FiStar } from 'react-icons/fi'
import { FaQuoteLeft } from 'react-icons/fa'
import Icon from '../lib/icons'
import Img from './Img'
import SectionHead from './SectionHead'
import { img, videoEmbed } from '../lib/api'
import { useSite } from '../context/SiteContext'

/* ---------- Animated counter ---------- */
function Counter({ value = 0, duration = 1600 }) {
  const ref = useRef(null)
  const [n, setN] = useState(0)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    let raf
    const io = new IntersectionObserver(([entry]) => {
      if (!entry.isIntersecting) return
      io.disconnect()
      const start = performance.now()
      const tick = (now) => {
        const p = Math.min((now - start) / duration, 1)
        setN(Math.round(value * (1 - Math.pow(1 - p, 3))))
        if (p < 1) raf = requestAnimationFrame(tick)
      }
      raf = requestAnimationFrame(tick)
    }, { threshold: 0.3 })
    io.observe(el)
    return () => { io.disconnect(); cancelAnimationFrame(raf) }
  }, [value, duration])

  return <span ref={ref}>{n.toLocaleString()}</span>
}

export function StatsBand({ stats = [] }) {
  if (!stats.length) return null
  return (
    <section className="stats-band">
      <div className="container stats-grid">
        {stats.map((st) => (
          <div className="stat" key={st.id}>
            <span className="stat-icon"><Icon name={st.icon} fallback="award" /></span>
            <strong><Counter value={st.value} />{st.suffix}</strong>
            <span>{st.label}</span>
          </div>
        ))}
      </div>
    </section>
  )
}

export function FeaturesSection({ items = [], title, subtitle }) {
  if (!items.length) return null
  return (
    <section className="section bg-cream">
      <div className="container">
        <SectionHead tag="Our Strength" title={title} text={subtitle} />
        <div className="features-grid">
          {items.map((f) => (
            <div className="feature" key={f.id}>
              <span className="feature-icon"><Icon name={f.icon} /></span>
              <div>
                <h3>{f.title}</h3>
                {f.description && <p>{f.description}</p>}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function ProcessSection({ steps = [], title, subtitle }) {
  if (!steps.length) return null
  return (
    <section className="section">
      <div className="container">
        <SectionHead tag="Process" title={title} text={subtitle} />
        <div className="process-grid">
          {steps.map((p, i) => (
            <div className="process-step" key={p.id}>
              <span className="process-num">{String(i + 1).padStart(2, '0')}</span>
              <span className="process-icon"><Icon name={p.icon} fallback="check" /></span>
              <h3>{p.title}</h3>
              {p.description && <p>{p.description}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}

export function TestimonialsSection({ items = [], title }) {
  const track = useRef(null)
  if (!items.length) return null
  const scroll = (d) => {
    const el = track.current
    if (el) el.scrollBy({ left: d * el.clientWidth * 0.9, behavior: 'smooth' })
  }
  return (
    <section className="section bg-dark testimonials">
      <div className="container">
        <div className="section-head-row">
          <SectionHead tag="Testimonials" title={title} center={false} light />
          <div className="slider-nav">
            <button onClick={() => scroll(-1)} aria-label="Previous"><FiChevronLeft /></button>
            <button onClick={() => scroll(1)} aria-label="Next"><FiChevronRight /></button>
          </div>
        </div>
        <div className="t-track" ref={track}>
          {items.map((t) => {
            const embed = videoEmbed(t.video_url)
            return (
              <div className="t-card" key={t.id}>
                {embed ? (
                  <div className="t-video"><iframe src={embed} title={t.name} allowFullScreen loading="lazy" /></div>
                ) : (
                  <FaQuoteLeft className="t-quote" />
                )}
                <div className="t-stars">
                  {Array.from({ length: t.rating || 5 }).map((_, i) => <FiStar key={i} />)}
                </div>
                <p>{t.message}</p>
                <div className="t-person">
                  {t.photo ? <Img src={t.photo} alt={t.name} className="t-photo" /> : <span className="t-photo t-initial">{t.name?.[0]}</span>}
                  <div>
                    <strong>{t.name}</strong>
                    {t.designation && <span>{t.designation}</span>}
                  </div>
                </div>
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export function ClientsSection({ items = [], title }) {
  if (!items.length) return null
  const loop = [...items, ...items]
  return (
    <section className="section clients-section">
      <div className="container">
        <SectionHead title={title} />
      </div>
      <div className="marquee">
        <div className="marquee-track" style={{ animationDuration: `${Math.max(items.length * 4, 20)}s` }}>
          {loop.map((c, i) => {
            const inner = c.logo ? <img src={img(c.logo)} alt={c.name} loading="lazy" /> : <span>{c.name}</span>
            return (
              <div className="client" key={`${c.id}-${i}`} aria-hidden={i >= items.length}>
                {c.website ? <a href={c.website} target="_blank" rel="noreferrer">{inner}</a> : inner}
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}

export function FaqList({ items = [] }) {
  const [open, setOpen] = useState(0)
  if (!items.length) return null
  return (
    <div className="faq-list">
      {items.map((f, i) => (
        <div className={`faq-item ${open === i ? 'is-open' : ''}`} key={f.id}>
          <button className="faq-q" onClick={() => setOpen(open === i ? -1 : i)} aria-expanded={open === i}>
            <span>{f.question}</span>
            {open === i ? <FiMinus /> : <FiPlus />}
          </button>
          {open === i && <div className="faq-a">{f.answer}</div>}
        </div>
      ))}
    </div>
  )
}

export function CtaBand() {
  const { settings: s } = useSite()
  if (!s.cta_title) return null
  return (
    <section className="cta-band" style={s.cta_bg ? { backgroundImage: `url("${img(s.cta_bg)}")` } : undefined}>
      <div className="container cta-inner">
        <div>
          <h2>{s.cta_title}</h2>
          {s.cta_text && <p>{s.cta_text}</p>}
        </div>
        <Link to="/get-quote" className="btn btn-primary btn-lg">{s.cta_button || 'Get a Quote'}</Link>
      </div>
    </section>
  )
}

export function Pagination({ meta, onChange }) {
  if (!meta || meta.last_page <= 1) return null
  const pages = Array.from({ length: meta.last_page }, (_, i) => i + 1)
  return (
    <div className="pagination">
      {pages.map((p) => (
        <button key={p} className={p === meta.current_page ? 'is-active' : ''} onClick={() => onChange(p)}>{p}</button>
      ))}
    </div>
  )
}

export function Lightbox({ images = [], index, onClose, onIndex }) {
  useEffect(() => {
    if (index === null || index === undefined) return
    const onKey = (e) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') onIndex((index + 1) % images.length)
      if (e.key === 'ArrowLeft') onIndex((index - 1 + images.length) % images.length)
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [index, images.length, onClose, onIndex])

  if (index === null || index === undefined || !images[index]) return null
  return (
    <div className="lightbox" onClick={onClose}>
      <button className="lb-close" onClick={onClose} aria-label="Close">×</button>
      {images.length > 1 && (
        <button className="lb-prev" onClick={(e) => { e.stopPropagation(); onIndex((index - 1 + images.length) % images.length) }} aria-label="Previous"><FiChevronLeft /></button>
      )}
      <img src={img(images[index])} alt="" onClick={(e) => e.stopPropagation()} />
      {images.length > 1 && (
        <button className="lb-next" onClick={(e) => { e.stopPropagation(); onIndex((index + 1) % images.length) }} aria-label="Next"><FiChevronRight /></button>
      )}
    </div>
  )
}
