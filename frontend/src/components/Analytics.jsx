import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { useSite } from '../context/SiteContext'

/**
 * Google Tag Manager, Google Analytics 4 and Facebook Pixel for the public website (IDs from Site Settings → SEO).
 * Scripts load once; every route change in the single-page app is sent as a page view.
 * Admin pages never load this component.
 */
const loaded = { ga: null, fb: null, gtm: null, gtmFirst: false }

function loadScript(src) {
  const s = document.createElement('script')
  s.async = true
  s.src = src
  document.head.appendChild(s)
}

export default function Analytics() {
  const { settings: s } = useSite()
  const { pathname, search } = useLocation()
  const ga = (s.ga_id || '').trim()
  const fb = (s.fb_pixel_id || '').trim()
  const gtm = (s.gtm_id || '').trim()

  useEffect(() => {
    // Google Tag Manager (official bootstrap). Tags/triggers are managed in tagmanager.google.com.
    if (gtm && /^GTM-[A-Z0-9]+$/i.test(gtm) && loaded.gtm !== gtm) {
      loaded.gtm = gtm
      if (window.__gtmLoaded === gtm) return // the server already put the snippet in the HTML (routes/web.php)
      window.dataLayer = window.dataLayer || []
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' })
      loadScript(`https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(gtm)}`)
    }
  }, [gtm])

  // Single-page app: tell Tag Manager about every route change after the first page.
  useEffect(() => {
    if (!loaded.gtm) return
    if (!loaded.gtmFirst) { loaded.gtmFirst = true; return }
    window.dataLayer.push({ event: 'page_view', page_path: pathname + search, page_title: document.title })
  }, [pathname, search, gtm])

  useEffect(() => {
    if (ga && /^G-[A-Z0-9]+$/i.test(ga) && loaded.ga !== ga) {
      loaded.ga = ga
      window.dataLayer = window.dataLayer || []
      window.gtag = function gtag() { window.dataLayer.push(arguments) } // eslint-disable-line prefer-rest-params
      window.gtag('js', new Date())
      window.gtag('config', ga, { send_page_view: false })
      loadScript(`https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(ga)}`)
    }
    if (fb && /^\d{6,20}$/.test(fb) && loaded.fb !== fb) {
      loaded.fb = fb
      /* Official Meta pixel bootstrap (queue until fbevents.js loads) */
      const f = window
      if (!f.fbq) {
        const n = (f.fbq = function fbq() { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }) // eslint-disable-line prefer-rest-params
        if (!f._fbq) f._fbq = n
        n.push = n; n.loaded = true; n.version = '2.0'; n.queue = []
        loadScript('https://connect.facebook.net/en_US/fbevents.js')
      }
      f.fbq('init', fb)
    }
  }, [ga, fb])

  useEffect(() => {
    if (loaded.ga && window.gtag) window.gtag('event', 'page_view', { page_path: pathname + search, page_title: document.title })
    if (loaded.fb && window.fbq) window.fbq('track', 'PageView')
  }, [pathname, search, ga, fb])

  return null
}
