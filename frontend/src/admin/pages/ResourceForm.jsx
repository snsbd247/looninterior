import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams, Navigate } from 'react-router-dom'
import { FiArrowLeft, FiSave, FiDownload, FiPhone, FiMail } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { adminApi, errorMessage, fieldErrors, whatsappLink } from '../../lib/api'
import Loader from '../../components/Loader'
import { RESOURCES, getPath, resourcePerm } from '../resources'
import { FieldControl, ImageInput, GalleryInput, humanize } from '../Fields'
import { useAdmin, NoAccess } from '../AdminApp'

function defaults(fields) {
  const v = {}
  fields.forEach((f) => {
    if (f.type === 'image' || f.type === 'gallery') return
    v[f.name] = f.default !== undefined ? f.default : f.type === 'switch' ? false : ''
  })
  return v
}

function ViewValue({ item, row }) {
  let v = getPath(row, item.key)
  if (item.type === 'datetime' && v) v = new Date(v).toLocaleString()
  if (v === null || v === undefined || v === '') return <span className="a-muted">—</span>
  if (item.key === 'phone') return <a href={`tel:${v}`}>{v}</a>
  if (item.key === 'email') return <a href={`mailto:${v}`}>{v}</a>
  return <span className={item.long ? 'a-pre' : ''}>{String(v)}</span>
}

function Form({ resource, cfg, id }) {
  const navigate = useNavigate()
  const { toast, refreshBadges } = useAdmin()
  const isEdit = !!id

  const [record, setRecord] = useState(null)
  const [values, setValues] = useState(() => defaults(cfg.fields))
  const [files, setFiles] = useState({})
  const [removed, setRemoved] = useState({})
  const [newGallery, setNewGallery] = useState([])
  const [removeGallery, setRemoveGallery] = useState([])
  const [options, setOptions] = useState({})
  const [errors, setErrors] = useState({})
  const [loading, setLoading] = useState(isEdit)
  const [saving, setSaving] = useState(false)

  // Load select options from other resources
  useEffect(() => {
    cfg.fields.filter((f) => f.source).forEach((f) => {
      adminApi.get(`/${f.source}`, { params: { all: 1 } }).then((r) => {
        setOptions((o) => ({ ...o, [f.name]: r.data.data.map((x) => ({ value: x.id, label: x[f.labelKey || 'name'] })) }))
      }).catch(() => {})
    })
  }, [cfg])

  // Load record
  useEffect(() => {
    if (!isEdit) return
    adminApi.get(`/${resource}/${id}`).then((r) => {
      const row = r.data.data
      setRecord(row)
      const v = defaults(cfg.fields)
      cfg.fields.forEach((f) => {
        if (f.type === 'image' || f.type === 'gallery') return
        const val = row[f.name]
        v[f.name] = f.type === 'switch' ? !!val : val ?? ''
      })
      setValues(v)
      setLoading(false)
      if (cfg.readonly && row.status === 'new') {
        // Opening a new lead marks nothing automatically, but refresh sidebar badges.
        refreshBadges()
      }
    }).catch((e) => {
      toast(errorMessage(e), 'error')
      navigate(`/admin/r/${resource}`)
    })
  }, [resource, id, isEdit, cfg, navigate, toast, refreshBadges])

  const setValue = (name, v) => setValues((s) => ({ ...s, [name]: v }))

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    setErrors({})

    const fd = new FormData()
    cfg.fields.forEach((f) => {
      if (f.type === 'image') {
        if (files[f.name]) fd.append(f.name, files[f.name])
        else if (removed[f.name]) fd.append(`remove_${f.name}`, '1')
      } else if (f.type === 'gallery') {
        newGallery.forEach((file) => fd.append('gallery[]', file))
        removeGallery.forEach((gid) => fd.append('remove_gallery[]', gid))
      } else if (f.type === 'switch') {
        fd.append(f.name, values[f.name] ? '1' : '0')
      } else {
        fd.append(f.name, values[f.name] ?? '')
      }
    })

    try {
      const url = isEdit ? `/${resource}/${id}` : `/${resource}`
      const res = await adminApi.post(url, fd)
      toast(res.data.message || 'Saved')
      refreshBadges()
      if (isEdit) {
        const row = res.data.data
        setRecord(row)
        setFiles({}); setRemoved({}); setNewGallery([]); setRemoveGallery([])
      } else {
        navigate(`/admin/r/${resource}`)
      }
    } catch (err) {
      setErrors(fieldErrors(err))
      toast(errorMessage(err), 'error')
    } finally {
      setSaving(false)
    }
  }

  const downloadCv = async () => {
    try {
      const res = await adminApi.get(`/job_applications/${id}/cv`, { responseType: 'blob' })
      const disposition = res.headers['content-disposition'] || ''
      const match = disposition.match(/filename="?([^";]+)"?/)
      const url = URL.createObjectURL(res.data)
      const a = document.createElement('a')
      a.href = url
      a.download = match ? match[1] : 'cv'
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch {
      toast('CV file not found', 'error')
    }
  }

  if (loading) return <Loader />

  const title = cfg.readonly ? `${cfg.singular} Details` : `${isEdit ? 'Edit' : 'Add'} ${cfg.singular}`

  return (
    <>
      <div className="a-page-head">
        <h1>{title}</h1>
        <Link to={`/admin/r/${resource}`} className="a-btn a-btn-light"><FiArrowLeft /> Back</Link>
      </div>

      {cfg.readonly && record && (
        <div className="a-panel a-view">
          <div className="a-view-actions">
            {record.phone && <a className="a-btn a-btn-light" href={`tel:${record.phone}`}><FiPhone /> Call</a>}
            {record.phone && whatsappLink(record.phone) && (
              <a className="a-btn a-btn-light" href={whatsappLink(record.phone)} target="_blank" rel="noreferrer"><FaWhatsapp /> WhatsApp</a>
            )}
            {record.email && <a className="a-btn a-btn-light" href={`mailto:${record.email}`}><FiMail /> Email</a>}
            {resource === 'job_applications' && record.has_cv && (
              <button type="button" className="a-btn a-btn-primary" onClick={downloadCv}><FiDownload /> Download CV</button>
            )}
          </div>
          <dl className="a-dl">
            {cfg.view.map((item) => (
              <div key={item.key} className={item.long ? 'full' : ''}>
                <dt>{item.label}</dt>
                <dd><ViewValue item={item} row={record} /></dd>
              </div>
            ))}
          </dl>
        </div>
      )}

      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {cfg.fields.map((f) => {
            const err = errors[f.name] || (f.type === 'gallery' && (errors.gallery || Object.entries(errors).find(([k]) => k.startsWith('gallery.'))?.[1]))
            return (
              <div key={f.name} className={`a-field ${f.half ? 'half' : 'full'} ${f.type === 'switch' ? 'is-switch' : ''}`}>
                {f.type !== 'switch' && <span>{f.label || humanize(f.name)}{f.required && ' *'}</span>}
                {f.type === 'image' ? (
                  <ImageInput
                    value={record?.[f.name]}
                    file={files[f.name]}
                    removed={removed[f.name]}
                    onFile={(file) => { setFiles((s) => ({ ...s, [f.name]: file })); setRemoved((s) => ({ ...s, [f.name]: false })) }}
                    onRemove={() => { setFiles((s) => ({ ...s, [f.name]: null })); setRemoved((s) => ({ ...s, [f.name]: true })) }}
                    onRestore={() => setRemoved((s) => ({ ...s, [f.name]: false }))}
                  />
                ) : f.type === 'gallery' ? (
                  <GalleryInput
                    existing={record?.[f.name] || []}
                    removeIds={removeGallery}
                    onToggleRemove={(gid) => setRemoveGallery((s) => (s.includes(gid) ? s.filter((x) => x !== gid) : [...s, gid]))}
                    newFiles={newGallery}
                    onNewFiles={setNewGallery}
                  />
                ) : (
                  <FieldControl
                    f={f}
                    value={values[f.name]}
                    onChange={(v) => setValue(f.name, v)}
                    options={f.source ? options[f.name] || [] : f.options || []}
                    error={err}
                  />
                )}
                {f.help && <small className="a-help">{f.help}</small>}
                {err && <small className="a-error">{err}</small>}
              </div>
            )
          })}
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save'}</button>
          <Link to={`/admin/r/${resource}`} className="a-btn a-btn-light">Cancel</Link>
        </div>
      </form>
    </>
  )
}

export default function ResourceForm() {
  const { resource, id } = useParams()
  const { can } = useAdmin()
  const cfg = RESOURCES[resource]
  if (!cfg) return <Navigate to="/admin" replace />
  if (!can(resourcePerm(cfg))) return <NoAccess />
  if (cfg.readonly && !id) return <Navigate to={`/admin/r/${resource}`} replace />
  return <Form key={`${resource}-${id || 'new'}`} resource={resource} cfg={cfg} id={id} />
}
