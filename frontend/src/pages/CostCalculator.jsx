import { useEffect, useMemo, useState } from 'react'
import { FiCheck, FiClock, FiDownload, FiPhoneCall, FiCheckCircle } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { api, errorMessage, fieldErrors, whatsappLink } from '../lib/api'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import Icon from '../lib/icons'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import { Honeypot } from '../components/QuoteForm'
import '../styles/calculator.css'

/*
 * Interior cost calculator. Options and rates come from Admin → Content → Cost Calculator (/api/calculator).
 * The figure shown here is computed in the browser for instant feedback; when the visitor asks for the detailed
 * estimate the server recomputes it (App\Support\CostEstimate) and creates a CRM lead.
 */
const tk = (n) => '৳' + Math.round(Number(n) || 0).toLocaleString('en-IN')
const short = (n) => {
  const v = Number(n) || 0
  if (v >= 1e7) return '৳' + (v / 1e7).toFixed(2).replace(/\.?0+$/, '') + ' Cr'
  if (v >= 1e5) return '৳' + (v / 1e5).toFixed(1).replace(/\.0$/, '') + ' Lakh'
  return tk(v)
}
const factor = (v) => (Number(v) === 1 ? 'Standard rate' : Number(v) > 1 ? `+${Math.round((v - 1) * 100)}%` : `−${Math.round((1 - v) * 100)}%`)

function estimateOf(sel, opts, s) {
  const type = opts.type.find((o) => o.id === sel.type)
  const style = opts.style.find((o) => o.id === sel.style)
  const quality = opts.quality.find((o) => o.id === sel.quality)
  if (!type || !style || !quality) return null
  const rate = Math.round(type.value * style.value * quality.value * 100) / 100
  const work = rate * sel.area
  const addons = opts.addon.filter((o) => sel.addons.includes(o.id))
  const extras = addons.reduce((t, a) => t + Number(a.value), 0)
  const estimate = work + extras
  const days = Math.ceil((s.days_base + (s.days_per_1000 * sel.area) / 1000) / 5) * 5
  return {
    type, style, quality, rate, work, addons, extras, estimate, days,
    low: Math.round((estimate * (1 - s.range_low / 100)) / 1000) * 1000,
    high: Math.round((estimate * (1 + s.range_high / 100)) / 1000) * 1000,
  }
}

function OptionCards({ items, value, onChange, show }) {
  return (
    <div className="calc-cards">
      {items.map((o) => (
        <button type="button" key={o.id} className={`calc-card ${value === o.id ? 'is-on' : ''}`} onClick={() => onChange(o.id)} aria-pressed={value === o.id}>
          {o.icon && <span className="calc-card-icon"><Icon name={o.icon} /></span>}
          <b>{o.name}</b>
          {o.description && <small>{o.description}</small>}
          <em>{show(o)}</em>
          {value === o.id && <FiCheck className="calc-card-check" />}
        </button>
      ))}
    </div>
  )
}

function LeadForm({ sel, e, onDone }) {
  const { form_token } = useSite()
  const [form, setForm] = useState({ name: '', phone: '', email: '', location: '', message: '' })
  const [hp, setHp] = useState('')
  const [errors, setErrors] = useState({})
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const set = (k) => (ev) => setForm({ ...form, [k]: ev.target.value })
  const submit = async (ev) => {
    ev.preventDefault(); setBusy(true); setErrors({}); setError('')
    try {
      const r = await api.post('/calculator/quote', {
        ...form, website: hp, form_token, type_id: sel.type, style_id: sel.style, quality_id: sel.quality, area: sel.area, addon_ids: sel.addons,
      })
      if (window.gtag) window.gtag('event', 'generate_lead', { form: 'calculator', value: e.estimate, currency: 'BDT' })
      else if (window.dataLayer) window.dataLayer.push({ event: 'generate_lead', form: 'calculator', value: e.estimate, currency: 'BDT' })
      window.fbq?.('track', 'Lead', { value: e.estimate, currency: 'BDT' })
      onDone(r.data)
    } catch (x) { setErrors(fieldErrors(x)); setError(errorMessage(x)) } finally { setBusy(false) }
  }
  const err = (k) => errors[k] && <small className="field-error">{errors[k]}</small>
  return (
    <form className="quote-form" onSubmit={submit} noValidate>
      <Honeypot value={hp} onChange={setHp} />
      <div className="form-grid">
        <label><span>Your Name *</span><input value={form.name} onChange={set('name')} autoComplete="name" />{err('name')}</label>
        <label><span>Phone *</span><input value={form.phone} onChange={set('phone')} inputMode="tel" autoComplete="tel" placeholder="01XXXXXXXXX" />{err('phone')}</label>
        <label><span>Email</span><input type="email" value={form.email} onChange={set('email')} autoComplete="email" />{err('email')}</label>
        <label><span>Project location</span><input value={form.location} onChange={set('location')} placeholder="e.g. Bashundhara, Dhaka" /></label>
        <label className="full"><span>Anything else? (optional)</span><textarea rows={3} value={form.message} onChange={set('message')} /></label>
      </div>
      {error && <div className="alert alert-error">{error}</div>}
      <button className="btn btn-primary btn-block" disabled={busy}>{busy ? 'Preparing…' : 'Get my detailed estimate'}</button>
      <p className="calc-small">We use your number only to discuss your project. No spam.</p>
    </form>
  )
}

export default function CostCalculator() {
  useTitle('Interior Cost Calculator')
  const { settings: site } = useSite()
  const { data, loading, error } = useFetch('/calculator')
  const [sel, setSel] = useState(null)
  const [done, setDone] = useState(null)

  useEffect(() => {
    if (!data || sel) return
    const first = (g) => (data.options[g].find((o) => o.is_default) || data.options[g][0])?.id
    setSel({ type: first('type'), style: first('style'), quality: first('quality'), area: Number(data.settings.default_area), addons: [] })
  }, [data, sel])

  const e = useMemo(() => (data && sel ? estimateOf(sel, data.options, data.settings) : null), [data, sel])

  if (loading || (data && !sel)) return <><PageHeader title="Interior Cost Calculator" /><Loader /></>
  if (error || !data) return <><PageHeader title="Interior Cost Calculator" /><section className="section"><div className="container"><p>The calculator is not available right now. Please contact us for a quote.</p></div></section></>

  const s = data.settings
  const set = (patch) => { setSel({ ...sel, ...patch }); setDone(null) }
  const toggleAddon = (id) => set({ addons: sel.addons.includes(id) ? sel.addons.filter((x) => x !== id) : [...sel.addons, id] })
  const area = (v) => set({ area: Math.min(s.max_area, Math.max(s.min_area, Math.round(Number(v) || s.min_area))) })
  const wa = site.whatsapp && e ? whatsappLink(site.whatsapp, `Hello, I used the cost calculator on your website.\n${e.type.name}, ${sel.area.toLocaleString('en-IN')} sqft, ${e.style.name} style, ${e.quality.name} quality${e.addons.length ? ` + ${e.addons.map((a) => a.name).join(', ')}` : ''}.\nEstimate: ${tk(e.low)} – ${tk(e.high)}. Please contact me.`) : null

  return (
    <>
      <PageHeader title="Interior Cost Calculator" />
      <section className="section calc-section">
        <div className="container">
          <div className="calc-intro">
            <span className="tag">Free · Instant · No obligation</span>
            <h2 className="h2">How much will your interior cost?</h2>
            <p className="lead">Choose your space, size, style and finish. You get a realistic budget range right away, based on our recent projects.</p>
          </div>

          <div className="calc-layout">
            <div className="calc-steps">
              <div className="calc-step">
                <h3><span>1</span> What kind of space?</h3>
                <OptionCards items={data.options.type} value={sel.type} onChange={(id) => set({ type: id })} show={(o) => `from ${tk(o.value)}/sqft`} />
              </div>

              <div className="calc-step">
                <h3><span>2</span> How big is it?</h3>
                <div className="calc-area">
                  <input type="range" min={s.min_area} max={s.max_area} step={50} value={sel.area} onChange={(ev) => area(ev.target.value)} aria-label="Area in square feet" />
                  <label className="calc-area-box"><input type="number" min={s.min_area} max={s.max_area} value={sel.area} onChange={(ev) => setSel({ ...sel, area: Number(ev.target.value) || 0 })} onBlur={(ev) => area(ev.target.value)} /> sqft</label>
                </div>
                <div className="calc-area-scale"><span>{Number(s.min_area).toLocaleString('en-IN')} sqft</span><span>{Number(s.max_area).toLocaleString('en-IN')} sqft</span></div>
              </div>

              <div className="calc-step">
                <h3><span>3</span> Design style</h3>
                <OptionCards items={data.options.style} value={sel.style} onChange={(id) => set({ style: id })} show={(o) => factor(o.value)} />
              </div>

              <div className="calc-step">
                <h3><span>4</span> Material & finish quality</h3>
                <OptionCards items={data.options.quality} value={sel.quality} onChange={(id) => set({ quality: id })} show={(o) => (Number(o.value) === 1 ? 'Base price' : `×${Number(o.value)}`)} />
              </div>

              {data.options.addon.length > 0 && (
                <div className="calc-step">
                  <h3><span>5</span> Extras <small>(optional)</small></h3>
                  <div className="calc-addons">
                    {data.options.addon.map((o) => (
                      <label key={o.id} className={`calc-addon ${sel.addons.includes(o.id) ? 'is-on' : ''}`}>
                        <input type="checkbox" checked={sel.addons.includes(o.id)} onChange={() => toggleAddon(o.id)} />
                        <span className="calc-addon-text"><b>{o.name}</b>{o.description && <small>{o.description}</small>}</span>
                        <em>+{tk(o.value)}</em>
                      </label>
                    ))}
                  </div>
                </div>
              )}
            </div>

            <aside className="calc-result" id="calc-result">
              <div className="calc-result-card">
                <span className="calc-label">Estimated budget</span>
                <div className="calc-range"><b>{tk(e.low)}</b><span>to</span><b>{tk(e.high)}</b></div>
                <ul className="calc-lines">
                  <li><span>{e.type.name}</span><span>{tk(e.type.value)}/sqft</span></li>
                  <li><span>Area</span><span>{sel.area.toLocaleString('en-IN')} sqft</span></li>
                  <li><span>{e.style.name} style</span><span>×{Number(e.style.value)}</span></li>
                  <li><span>{e.quality.name} quality</span><span>×{Number(e.quality.value)}</span></li>
                  <li className="calc-strong"><span>Your rate</span><span>{tk(e.rate)}/sqft</span></li>
                  {e.addons.length > 0 && <li><span>Extras ({e.addons.length})</span><span>{tk(e.extras)}</span></li>}
                </ul>
                <div className="calc-time"><FiClock /> About <b>{e.days} days</b> to complete</div>
                {done ? (
                  <div className="calc-done">
                    <FiCheckCircle />
                    <p>{done.message}</p>
                    {done.pdf_url && <a className="btn btn-primary btn-block" href={done.pdf_url} target="_blank" rel="noreferrer"><FiDownload /> Download estimate (PDF)</a>}
                  </div>
                ) : (
                  <a href="#calc-get" className="btn btn-primary btn-block"><FiDownload /> Get detailed estimate (PDF)</a>
                )}
                {wa && <a className="btn btn-outline-light btn-block calc-wa" href={wa} target="_blank" rel="noreferrer"><FaWhatsapp /> Discuss on WhatsApp</a>}
                {site.phone && <a className="calc-call" href={`tel:${site.phone.replace(/\s/g, '')}`}><FiPhoneCall /> {site.phone}</a>}
                {s.note && <p className="calc-note">{s.note}</p>}
              </div>
            </aside>
          </div>

          {s.breakdown?.length > 0 && (
            <div className="calc-split">
              <h3 className="h3">Where your money goes</h3>
              <p className="calc-small">Typical split of the budget for a project like yours.</p>
              {s.breakdown.map((b) => (
                <div className="calc-bar" key={b.name}>
                  <span className="calc-bar-name">{b.name}</span>
                  <span className="calc-bar-track"><span style={{ width: `${b.percent}%` }} /></span>
                  <span className="calc-bar-pct">{b.percent}%</span>
                  <span className="calc-bar-amt">{short((e.estimate * b.percent) / 100)}</span>
                </div>
              ))}
            </div>
          )}

          <div className="calc-get" id="calc-get">
            <div>
              <span className="tag">Next step</span>
              <h3 className="h3">Get your detailed estimate</h3>
              <p>Leave your name and number. You get a PDF of this estimate right away, and one of our designers will call you to book a free site visit for an exact, itemised quotation.</p>
              <ul className="check-list">
                <li><FiCheck /> Free site visit and measurement</li>
                <li><FiCheck /> Itemised quotation (BOQ) with materials</li>
                <li><FiCheck /> 3D design before work starts</li>
              </ul>
            </div>
            <div className="form-card">
              {done ? (
                <div className="service-done">
                  <FiCheckCircle />
                  <h3>Thank you!</h3>
                  <p>{done.message}</p>
                  {done.pdf_url && <a className="btn btn-primary" href={done.pdf_url} target="_blank" rel="noreferrer"><FiDownload /> Download estimate (PDF)</a>}
                </div>
              ) : <LeadForm sel={sel} e={e} onDone={setDone} />}
            </div>
          </div>
        </div>
      </section>

      <a href="#calc-result" className="calc-mobile-bar">
        <span>Estimate</span><b>{short(e.low)} – {short(e.high)}</b>
      </a>
    </>
  )
}
