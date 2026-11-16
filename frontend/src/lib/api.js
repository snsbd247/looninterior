import axios from 'axios'

/* global __BASE_PATH__ */
/** Sub-folder the site is served from ("" on a domain root, "/loon" etc.). */
export const BASE_PATH = typeof __BASE_PATH__ === 'string' ? __BASE_PATH__ : ''

/** Prefix an absolute site path with the base path ("/admin" -> "/loon/admin"). */
export const sitePath = (p = '/') => BASE_PATH + (p.startsWith('/') ? p : '/' + p)

export const api = axios.create({
  baseURL: sitePath('/api'),
  headers: { Accept: 'application/json' },
})

export const TOKEN_KEY = 'admin_token'

export function getToken() {
  try { return localStorage.getItem(TOKEN_KEY) } catch { return null }
}
export function setToken(token) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch { /* storage unavailable */ }
}

export const adminApi = axios.create({
  baseURL: sitePath('/api/admin'),
  headers: { Accept: 'application/json' },
})

adminApi.interceptors.request.use((config) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

adminApi.interceptors.response.use(
  (res) => res,
  (error) => {
    if (error.response?.status === 401 && !window.location.pathname.startsWith(sitePath('/admin/login'))) {
      setToken(null)
      window.location.href = sitePath('/admin/login')
    }
    return Promise.reject(error)
  }
)

/** Resolve a stored image path ("uploads/x.jpg") or full URL to a usable src. */
export function img(path) {
  if (!path) return ''
  if (/^(https?:)?\/\//.test(path) || path.startsWith('data:') || path.startsWith('blob:')) return path
  return sitePath(String(path).replace(/^\/+/, ''))
}

export function errorMessage(error) {
  const data = error?.response?.data
  if (data?.errors) return Object.values(data.errors).flat()[0]
  return data?.message || error?.message || 'Something went wrong'
}

export function fieldErrors(error) {
  const errs = error?.response?.data?.errors || {}
  const out = {}
  Object.keys(errs).forEach((k) => { out[k] = errs[k][0] })
  return out
}

export function formatDate(value) {
  if (!value) return ''
  const d = new Date(value)
  if (isNaN(d)) return value
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function videoEmbed(url) {
  if (!url) return null
  const yt = url.match(/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([\w-]{11})/)
  if (yt) return `https://www.youtube.com/embed/${yt[1]}`
  const vm = url.match(/vimeo\.com\/(\d+)/)
  if (vm) return `https://player.vimeo.com/video/${vm[1]}`
  return null
}

export function mapSrc(value) {
  if (!value) return ''
  const m = value.match(/src=["']([^"']+)["']/)
  return m ? m[1] : value
}

export function whatsappLink(number, text = '') {
  const digits = String(number || '').replace(/\D/g, '')
  if (!digits) return ''
  return `https://wa.me/${digits}${text ? `?text=${encodeURIComponent(text)}` : ''}`
}
