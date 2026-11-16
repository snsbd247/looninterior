import { createContext, useContext, useEffect, useState } from 'react'
import { api, img } from '../lib/api'
import Loader from '../components/Loader'

const SiteContext = createContext({ settings: {}, services: [], categories: [], pages: [] })

export function SiteProvider({ children }) {
  const [site, setSite] = useState(null)

  useEffect(() => {
    api.get('/site')
      .then((res) => setSite(res.data))
      .catch(() => setSite({ settings: {}, services: [], categories: [], pages: [] }))
  }, [])

  useEffect(() => {
    if (!site) return
    const s = site.settings || {}
    if (s.primary_color) document.documentElement.style.setProperty('--primary', s.primary_color)
    if (s.favicon) {
      let link = document.querySelector("link[rel='icon']")
      if (!link) {
        link = document.createElement('link')
        link.rel = 'icon'
        document.head.appendChild(link)
      }
      link.href = img(s.favicon)
    }
  }, [site])

  if (!site) return <Loader full />

  return <SiteContext.Provider value={site}>{children}</SiteContext.Provider>
}

export const useSite = () => useContext(SiteContext)

/** Set document title using the site name. */
export function useTitle(title) {
  const { settings } = useSite()
  useEffect(() => {
    const name = settings?.site_name || ''
    document.title = title ? `${title} | ${name}` : settings?.meta_title || name
  }, [title, settings])
}
