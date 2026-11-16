import { useEffect, useMemo, useRef } from 'react'
import ReactQuill from 'react-quill'
import 'react-quill/dist/quill.snow.css'
import { FiUpload, FiX, FiRotateCcw } from 'react-icons/fi'
import { img } from '../lib/api'
import Icon from '../lib/icons'

const QUILL_MODULES = {
  toolbar: [
    [{ header: [2, 3, 4, false] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['blockquote', 'link', 'image'],
    [{ align: [] }],
    ['clean'],
  ],
}

export const humanize = (s) => s.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())

function useObjectUrl(file) {
  const url = useMemo(() => (file ? URL.createObjectURL(file) : null), [file])
  useEffect(() => () => { if (url) URL.revokeObjectURL(url) }, [url])
  return url
}

export function ImageInput({ value, file, removed, onFile, onRemove, onRestore }) {
  const input = useRef(null)
  const preview = useObjectUrl(file)
  const src = preview || (!removed && value ? img(value) : null)

  return (
    <div className="image-input">
      <div className="image-preview" onClick={() => input.current?.click()}>
        {src ? <img src={src} alt="" /> : <span><FiUpload /> Click to upload</span>}
      </div>
      <div className="image-actions">
        <button type="button" className="a-btn a-btn-light" onClick={() => input.current?.click()}><FiUpload /> {src ? 'Change' : 'Upload'}</button>
        {src && <button type="button" className="a-btn a-btn-light danger" onClick={onRemove}><FiX /> Remove</button>}
        {removed && value && !file && <button type="button" className="a-btn a-btn-light" onClick={onRestore}><FiRotateCcw /> Undo</button>}
      </div>
      <input
        ref={input}
        type="file"
        accept="image/*"
        hidden
        onChange={(e) => { if (e.target.files[0]) onFile(e.target.files[0]); e.target.value = '' }}
      />
    </div>
  )
}

function NewThumb({ file, onRemove }) {
  const url = useObjectUrl(file)
  return (
    <div className="g-thumb is-new">
      <img src={url} alt="" />
      <button type="button" onClick={onRemove} aria-label="Remove"><FiX /></button>
      <span className="g-badge">new</span>
    </div>
  )
}

export function GalleryInput({ existing = [], removeIds = [], onToggleRemove, newFiles = [], onNewFiles }) {
  const input = useRef(null)
  return (
    <div className="gallery-input">
      <div className="g-grid">
        {existing.map((im) => {
          const marked = removeIds.includes(im.id)
          return (
            <div key={im.id} className={`g-thumb ${marked ? 'is-removed' : ''}`}>
              <img src={img(im.image)} alt="" />
              <button type="button" onClick={() => onToggleRemove(im.id)} aria-label={marked ? 'Undo' : 'Remove'}>
                {marked ? <FiRotateCcw /> : <FiX />}
              </button>
            </div>
          )
        })}
        {newFiles.map((f, i) => (
          <NewThumb key={`${f.name}-${i}`} file={f} onRemove={() => onNewFiles(newFiles.filter((_, j) => j !== i))} />
        ))}
        <button type="button" className="g-add" onClick={() => input.current?.click()}><FiUpload /><span>Add images</span></button>
      </div>
      <input
        ref={input}
        type="file"
        accept="image/*"
        multiple
        hidden
        onChange={(e) => { onNewFiles([...newFiles, ...Array.from(e.target.files)]); e.target.value = '' }}
      />
    </div>
  )
}

/**
 * Renders one form control.
 * state: { values, files, removed } ; handlers: setValue, setFile, setRemoved
 */
export function FieldControl({ f, value, onChange, options = [], error }) {
  const id = `f-${f.name}`
  switch (f.type) {
    case 'textarea':
      return <textarea id={id} rows={f.rows || 4} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
    case 'rich':
      return <ReactQuill theme="snow" value={value || ''} onChange={onChange} modules={QUILL_MODULES} />
    case 'number':
      return <input id={id} type="number" value={value ?? ''} onChange={(e) => onChange(e.target.value)} />
    case 'date':
      return <input id={id} type="date" value={value ? String(value).slice(0, 10) : ''} onChange={(e) => onChange(e.target.value)} />
    case 'color':
      return (
        <div className="color-input">
          <input type="color" value={value || '#b08d57'} onChange={(e) => onChange(e.target.value)} />
          <input id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)} placeholder="#b08d57" />
        </div>
      )
    case 'switch':
      return (
        <label className="switch">
          <input type="checkbox" checked={!!value} onChange={(e) => onChange(e.target.checked)} />
          <span className="slider" />
          <span className="switch-label">{f.label}</span>
        </label>
      )
    case 'select':
      return (
        <select id={id} value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
          <option value="">— Select —</option>
          {options.map((o) => {
            const opt = typeof o === 'object' ? o : { value: o, label: humanize(String(o)) }
            return <option key={opt.value} value={opt.value}>{opt.label}</option>
          })}
        </select>
      )
    case 'icon':
      return (
        <div className="icon-picker">
          {(f.options || []).map((k) => (
            <button type="button" key={k} title={k} className={value === k ? 'is-active' : ''} onClick={() => onChange(k)}>
              <Icon name={k} />
            </button>
          ))}
        </div>
      )
    default:
      return <input id={id} type="text" value={value ?? ''} onChange={(e) => onChange(e.target.value)} className={error ? 'has-error' : ''} />
  }
}
