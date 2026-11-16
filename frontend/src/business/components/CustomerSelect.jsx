import { useEffect, useMemo, useState } from 'react'
import { businessApi } from '../../lib/api'

/** Searchable customer dropdown (loads up to 1000 customers of the current branch view). */
export default function CustomerSelect({ value, onChange, disabled }) {
  const [list, setList] = useState([])
  const [q, setQ] = useState('')
  useEffect(() => {
    businessApi.get('/customers', { params: { all: 1 } }).then((r) => setList(r.data.data)).catch(() => {})
  }, [])
  const filtered = useMemo(() => {
    const s = q.trim().toLowerCase()
    const rows = s ? list.filter((c) => `${c.name} ${c.phone || ''}`.toLowerCase().includes(s)) : list
    const selected = list.find((c) => String(c.id) === String(value))
    return selected && !rows.includes(selected) ? [selected, ...rows] : rows
  }, [list, q, value])
  return (
    <div className="b-combo">
      {!disabled && <input placeholder="Search customer by name / phone…" value={q} onChange={(e) => setQ(e.target.value)} />}
      <select value={value || ''} onChange={(e) => onChange(e.target.value, list.find((c) => String(c.id) === e.target.value))} disabled={disabled}>
        <option value="">— Select customer —</option>
        {filtered.slice(0, 300).map((c) => <option key={c.id} value={c.id}>{c.name}{c.phone ? ` · ${c.phone}` : ''}</option>)}
      </select>
    </div>
  )
}
