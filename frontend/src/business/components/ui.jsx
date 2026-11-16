import { useEffect } from 'react'
import { FiX } from 'react-icons/fi'
import { useAdmin } from '../../admin/AdminApp'
import { label, money } from '../lib'

/** Coloured status pill: <Status value="paid" /> */
export function Status({ value, text }) {
  if (!value) return <span className="a-muted">—</span>
  return <span className={`a-status b-st b-st-${value}`}>{text || label(value)}</span>
}

export function Money({ value, symbol = false, className = '' }) {
  return <span className={`b-money ${Number(value) < 0 ? 'is-neg' : ''} ${className}`}>{money(value, { symbol })}</span>
}

export function Modal({ title, onClose, children, footer, wide = false }) {
  useEffect(() => {
    const onKey = (e) => { if (e.key === 'Escape') onClose() }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])
  return (
    <div className="b-modal-backdrop" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose() }}>
      <div className={`b-modal ${wide ? 'is-wide' : ''}`} role="dialog" aria-modal="true">
        <div className="b-modal-head"><h3>{title}</h3><button type="button" className="a-icon-btn" onClick={onClose} aria-label="Close"><FiX /></button></div>
        <div className="b-modal-body">{children}</div>
        {footer && <div className="b-modal-foot">{footer}</div>}
      </div>
    </div>
  )
}

/** Text / select / textarea field with label + error, matching admin form styles. */
export function Field({ label: text, error, help, full, children }) {
  return (
    <label className={`a-field ${full ? 'full' : ''}`}>
      <span>{text}</span>
      {children}
      {help && <small className="a-help">{help}</small>}
      {error && <small className="field-error">{error}</small>}
    </label>
  )
}

/**
 * Branch picker shown only in "All branches" mode, where a new record must say which branch it belongs to.
 * Renders nothing when a single branch is selected (the record goes to that branch).
 */
export function BranchPicker({ value, onChange, error }) {
  const { user, branch } = useAdmin()
  if (branch !== 'all') return null
  return (
    <Field label="Branch *" error={error} help="You are viewing all branches. Choose where this record belongs.">
      <select value={value || ''} onChange={(e) => onChange(e.target.value)}>
        <option value="">— Select branch —</option>
        {(user.branches || []).map((b) => <option key={b.id} value={b.id}>{b.name} ({b.code})</option>)}
      </select>
    </Field>
  )
}

export function Empty({ children = 'Nothing here yet' }) {
  return <div className="a-empty">{children}</div>
}

/** Key/value grid for detail pages. */
export function InfoGrid({ items }) {
  return (
    <dl className="b-info">
      {items.filter(Boolean).map(([k, v]) => (
        <div key={k}><dt>{k}</dt><dd>{v === null || v === undefined || v === '' ? <span className="a-muted">—</span> : v}</dd></div>
      ))}
    </dl>
  )
}

export function Stat({ label: text, value, tone, sub }) {
  return (
    <div className={`b-stat ${tone ? 'is-' + tone : ''}`}>
      <span>{text}</span>
      <strong>{value}</strong>
      {sub && <small>{sub}</small>}
    </div>
  )
}
