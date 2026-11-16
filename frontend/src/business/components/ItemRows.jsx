import { useEffect, useState } from 'react'
import { FiPlus, FiTrash2 } from 'react-icons/fi'
import { businessApi } from '../../lib/api'
import { money } from '../lib'

let itemCache = null
export function useItems() {
  const [items, setItems] = useState(itemCache || [])
  useEffect(() => {
    if (itemCache) return
    businessApi.get('/lookup/items').then((r) => { itemCache = r.data.data; setItems(r.data.data) }).catch(() => {})
  }, [])
  return items
}

export const blankRow = () => ({ item_id: '', description: '', unit: '', qty: 1, rate: 0 })

/**
 * Stock item rows. withRate: purchase lines (qty × rate). allowFree: lines without a stock item
 * (direct purchases). available: { [item_id]: qty } shown next to the qty for issues/transfers.
 */
export default function ItemRows({ rows, onChange, withRate = false, allowFree = false, available = null, signed = false, errors = {} }) {
  const items = useItems()
  const set = (i, patch) => onChange(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)))
  const pick = (i, id) => {
    const it = items.find((x) => String(x.id) === String(id))
    set(i, { item_id: id, unit: it?.unit || rows[i].unit, description: it ? '' : rows[i].description, rate: withRate && it && !Number(rows[i].rate) ? Math.round(Number(it.avg_cost) * 100) / 100 : rows[i].rate })
  }
  const total = rows.reduce((s, r) => s + Number(r.qty || 0) * Number(r.rate || 0), 0)

  return (
    <div className="b-irows">
      {rows.map((r, i) => {
        const it = items.find((x) => String(x.id) === String(r.item_id))
        const avail = available && r.item_id ? available[r.item_id] ?? 0 : null
        const err = (k) => errors[`items.${i}.${k}`]
        return (
          <div key={i} className={`b-irow ${withRate ? 'has-rate' : ''}`}>
            <div className="c-item">
              <select value={r.item_id} onChange={(e) => pick(i, e.target.value)} className={err('item_id') ? 'has-error' : ''}>
                <option value="">{allowFree ? '— Not a stock item (type below) —' : '— Select item —'}</option>
                {items.map((x) => <option key={x.id} value={x.id}>{x.name}{x.sku ? ` (${x.sku})` : ''}</option>)}
              </select>
              {allowFree && !r.item_id && <input placeholder="Description" value={r.description} onChange={(e) => set(i, { description: e.target.value })} />}
              {avail !== null && <small className={`a-muted ${avail <= 0 ? 'b-overdue' : ''}`}>Available: {avail} {it?.unit}</small>}
              {err('item_id') && <small className="field-error">{err('item_id')}</small>}
            </div>
            <label className="c-qty"><span>Qty{it ? ` (${it.unit})` : ''}</span>
              <input type="number" step="any" min={signed ? undefined : 0} value={r.qty} onChange={(e) => set(i, { qty: e.target.value })} className={err('qty') ? 'has-error' : ''} />
            </label>
            {withRate && <label className="c-rate"><span>Rate</span><input type="number" step="any" min="0" value={r.rate} onChange={(e) => set(i, { rate: e.target.value })} /></label>}
            {withRate && <span className="c-amt">{money(Number(r.qty || 0) * Number(r.rate || 0))}</span>}
            <button type="button" className="a-icon-btn danger" onClick={() => onChange(rows.length > 1 ? rows.filter((_, j) => j !== i) : [blankRow()])} title="Remove"><FiTrash2 /></button>
          </div>
        )
      })}
      <div className="b-row-head">
        <button type="button" className="b-lines-add" onClick={() => onChange([...rows, blankRow()])}><FiPlus /> Add line</button>
        {withRate && <b>Total: {money(total)}</b>}
      </div>
      {errors.items && <small className="field-error">{errors.items}</small>}
    </div>
  )
}
