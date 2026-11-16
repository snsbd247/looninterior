import { useCallback, useEffect, useState } from 'react'
import { businessApi, errorMessage } from '../lib/api'

/** Taka with Bangladeshi grouping: 1,20,000.00 */
export function money(value, { symbol = false, decimals = 2 } = {}) {
  const n = Number(value || 0)
  const neg = n < 0
  const [whole, frac] = Math.abs(n).toFixed(decimals).split('.')
  const last3 = whole.slice(-3)
  const rest = whole.slice(0, -3)
  const grouped = rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3
  return `${neg ? '-' : ''}${symbol ? '৳ ' : ''}${grouped}${decimals ? '.' + frac : ''}`
}

export const label = (s) => String(s ?? '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

export function dateTime(v) {
  if (!v) return ''
  const d = new Date(v)
  return isNaN(d) ? v : d.toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

/** "2026-11-16T10:30" for <input type="datetime-local"> */
export function toLocalInput(v) {
  if (!v) return ''
  const d = new Date(v)
  if (isNaN(d)) return ''
  const p = (x) => String(x).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`
}

export const today = () => new Date().toISOString().slice(0, 10)

/** Paginated list from the business API with filters. */
export function useList(endpoint, initial = {}, { toast } = {}) {
  const [rows, setRows] = useState(null)
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [filters, setFiltersState] = useState(initial)
  const [extra, setExtra] = useState({})

  const load = useCallback(() => {
    const params = { page }
    Object.entries(filters).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) params[k] = v })
    return businessApi.get(endpoint, { params })
      .then((r) => { setRows(r.data.data); setMeta(r.data); setExtra(r.data) })
      .catch((e) => { toast?.(errorMessage(e), 'error'); setRows([]) })
  }, [endpoint, page, filters, toast])

  useEffect(() => { load() }, [load])

  const setFilter = (k, v) => { setPage(1); setFiltersState((f) => ({ ...f, [k]: v })) }
  return { rows, meta, page, setPage, filters, setFilter, setFilters: (f) => { setPage(1); setFiltersState(f) }, reload: load, extra }
}

/** Lookups shared by many forms (cached per page load). */
const cache = {}
export function useLookup(name) {
  const [data, setData] = useState(cache[name] || [])
  useEffect(() => {
    if (cache[name]) return
    businessApi.get(`/lookup/${name}`).then((r) => { cache[name] = r.data.data; setData(r.data.data) }).catch(() => {})
  }, [name])
  return data
}

export function phoneHref(phone) {
  return phone ? `tel:${String(phone).replace(/[^\d+]/g, '')}` : undefined
}

export function waHref(phone, text = '') {
  let d = String(phone || '').replace(/\D/g, '')
  if (!d) return undefined
  if (d.startsWith('01')) d = '88' + d
  return `https://wa.me/${d}${text ? `?text=${encodeURIComponent(text)}` : ''}`
}
