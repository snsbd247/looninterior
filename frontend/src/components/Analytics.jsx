import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'
import { useSite } from '../context/SiteContext'

/**
 * Google Analytics 4 + Facebook Pixel for the public website (IDs from Site Settings → SEO).
 * Scripts load once; every route change in the single-page app is sent as a page view.
 * Admin pages never load this component.
 */
const loaded = { ga: null, fb: null }

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
