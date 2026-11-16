import { FiPlus, FiTrash2, FiArrowUp, FiArrowDown, FiFolderPlus } from 'react-icons/fi'
import { money } from '../lib'

export const UNITS = ['sqft', 'rft', 'pcs', 'set', 'lot', 'job', 'sqm', 'kg', 'ltr', 'box', 'roll', 'sheet', 'nos']

export const blankItem = (section = '') => ({ section, description: '', unit: 'sqft', qty: 1, rate: 0 })

/** Client-side preview only. The server recalculates everything on save. */
export function calcTotals(items, discountType = 'amount', discountValue = 0, taxRate = 0) {
  const subtotal = round2(items.reduce((s, i) => s + round2(Number(i.qty || 0) * Number(i.rate || 0)), 0))
  const dv = Number(discountValue || 0)
  const discount = Math.min(subtotal, discountType === 'percent' ? round2(subtotal * dv / 100) : dv)
  const tax = round2((subtotal - discount) * Number(taxRate || 0) / 100)
  return { subtotal, discount, tax, total: round2(subtotal - discount + tax) }
}
const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100

/**
 * Editable item rows, optionally grouped into sections (rooms).
 * items: [{ section, description, unit, qty, rate }]
 */
export default function LineItems({ items, onChange, sections = true, errors = {}, descriptionPlaceholder = 'Item / work description' }) {
  const update = (idx, patch) => onChange(items.map((it, i) => (i === idx ? { ...it, ...patch } : it)))
  const remove = (idx) => onChange(items.length > 1 ? items.filter((_, i) => i !== idx) : [blankItem(items[0]?.section || '')])
  const move = (idx, dir) => {
    const j = idx + dir
    if (j < 0 || j >= items.length) return
    const next = [...items]
    ;[next[idx], next[j]] = [next[j], next[idx]]
    onChange(next)
  }
  const addItem = (section, afterIdx) => {
    const next = [...items]
    next.splice(afterIdx + 1, 0, blankItem(section))
    onChange(next)
  }
  const addSection = () => {
    const name = window.prompt('Section / room name (e.g. Living Room, Kitchen)')
    if (name === null) return
    onChange([...items.filter((i) => i.description || items.length > 1), blankItem(name.trim())])
  }
  const renameSection = (oldName, idxs) => {
    const name = window.prompt('Rename section', oldName)
    if (name === null) return
    onChange(items.map((it, i) => (idxs.includes(i) ? { ...it, section: name.trim() } : it)))
  }

  // Group consecutive rows by section for display
  const groups = []
  items.forEach((it, idx) => {
    const key = sections ? (it.section || '') : ''
    const last = groups[groups.length - 1]
    if (last && last.name === key) last.rows.push(idx)
    else groups.push({ name: key, rows: [idx] })
  })

  let sl = 0
  return (
    <div className="b-lines">
      <div className="b-lines-head">
        <span className="c-sl">#</span><span className="c-desc">Description</span><span className="c-unit">Unit</span>
        <span className="c-qty">Qty</span><span className="c-rate">Rate</span><span className="c-amt">Amount</span><span className="c-act" />
      </div>
      {groups.map((g, gi) => (
        <div key={gi} className="b-lines-group">
          {sections && (g.name || groups.length > 1) && (
            <div className="b-lines-section">
              <button type="button" onClick={() => renameSection(g.name, g.rows)} title="Rename section">{g.name || 'General'}</button>
              <span className="a-muted">{money(g.rows.reduce((s, i) => s + Number(items[i].qty || 0) * Number(items[i].rate || 0), 0))}</span>
            </div>
          )}
          {g.rows.map((idx) => {
            const it = items[idx]
            sl += 1
            const err = (k) => errors[`items.${idx}.${k}`]
            return (
              <div key={idx} className="b-line">
                <span className="c-sl">{sl}</span>
                <div className="c-desc">
                  <textarea rows={1} value={it.description} placeholder={descriptionPlaceholder} onChange={(e) => update(idx, { description: e.target.value })} className={err('description') ? 'has-error' : ''} />
                  {err('description') && <small className="field-error">{err('description')}</small>}
                </div>
                <label className="c-unit"><span>Unit</span>
                  <input list="b-units" value={it.unit || ''} onChange={(e) => update(idx, { unit: e.target.value })} />
                </label>
                <label className="c-qty"><span>Qty</span>
                  <input type="number" step="any" min="0" value={it.qty} onChange={(e) => update(idx, { qty: e.target.value })} className={err('qty') ? 'has-error' : ''} />
                </label>
                <label className="c-rate"><span>Rate</span>
                  <input type="number" step="any" min="0" value={it.rate} onChange={(e) => update(idx, { rate: e.target.value })} className={err('rate') ? 'has-error' : ''} />
                </label>
                <span className="c-amt">{money(Number(it.qty || 0) * Number(it.rate || 0))}</span>
                <span className="c-act">
                  <button type="button" className="a-icon-btn" onClick={() => move(idx, -1)} title="Move up"><FiArrowUp /></button>
                  <button type="button" className="a-icon-btn" onClick={() => move(idx, 1)} title="Move down"><FiArrowDown /></button>
                  <button type="button" className="a-icon-btn danger" onClick={() => remove(idx)} title="Remove"><FiTrash2 /></button>
                </span>
              </div>
            )
          })}
          <button type="button" className="b-lines-add" onClick={() => addItem(sections ? g.name : '', g.rows[g.rows.length - 1])}><FiPlus /> Add item{sections && g.name ? ` to ${g.name}` : ''}</button>
        </div>
      ))}
      {sections && <button type="button" className="a-btn a-btn-light b-mt" onClick={addSection}><FiFolderPlus /> Add section / room</button>}
      {errors.items && <small className="field-error">{errors.items}</small>}
      <datalist id="b-units">{UNITS.map((u) => <option key={u} value={u} />)}</datalist>
    </div>
  )
}

/** Discount + tax + totals box used under LineItems. */
export function TotalsBox({ items, form, setForm, taxRates, children }) {
  const rate = taxRates.find((t) => String(t.id) === String(form.tax_rate_id))?.rate || 0
  const t = calcTotals(items, form.discount_type, form.discount_value, rate)
  return (
    <div className="b-totals">
      <div className="b-totals-row"><span>Subtotal</span><b>{money(t.subtotal)}</b></div>
      <div className="b-totals-row">
        <span className="b-totals-input">Discount
          <select value={form.discount_type} onChange={(e) => setForm({ ...form, discount_type: e.target.value })}>
            <option value="amount">৳</option><option value="percent">%</option>
          </select>
          <input type="number" min="0" step="any" value={form.discount_value} onChange={(e) => setForm({ ...form, discount_value: e.target.value })} />
        </span>
        <b>- {money(t.discount)}</b>
      </div>
      <div className="b-totals-row">
        <span className="b-totals-input">Tax
          <select value={form.tax_rate_id || ''} onChange={(e) => setForm({ ...form, tax_rate_id: e.target.value })}>
            <option value="">No tax</option>
            {taxRates.map((x) => <option key={x.id} value={x.id}>{x.name} ({Number(x.rate)}%)</option>)}
          </select>
        </span>
        <b>{money(t.tax)}</b>
      </div>
      <div className="b-totals-row is-total"><span>Total (BDT)</span><b>{money(t.total)}</b></div>
      {children}
    </div>
  )
}
