import { useEffect, useState, useCallback } from 'react'
import { Link, useParams, Navigate } from 'react-router-dom'
import { FiPlus, FiEdit2, FiTrash2, FiSearch, FiEye, FiCheck, FiX } from 'react-icons/fi'
import { adminApi, errorMessage, formatDate, img } from '../../lib/api'
import Loader from '../../components/Loader'
import { RESOURCES, getPath, resourcePerm } from '../resources'
import { humanize } from '../Fields'
import { useAdmin, NoAccess } from '../AdminApp'

function Cell({ col, row }) {
  const v = col.get ? col.get(row) : getPath(row, col.key)
  switch (col.type) {
    case 'image':
      return v ? <img src={img(v)} alt="" className="a-thumb" /> : <span className="a-thumb a-thumb-empty" />
    case 'bool':
      return v ? <span className="a-yes"><FiCheck /></span> : <span className="a-no"><FiX /></span>
    case 'date':
      return formatDate(v) || '—'
    case 'badge':
      return <span className={`a-status s-${v}`}>{v}</span>
    default:
      return v === null || v === undefined || v === '' ? '—' : String(v)
  }
}

function List({ resource, cfg }) {
  const { toast, refreshBadges } = useAdmin()
  const [rows, setRows] = useState(null)
  const [meta, setMeta] = useState(null)
  const [page, setPage] = useState(1)
  const [q, setQ] = useState('')
  const [search, setSearch] = useState('')
  const [filter, setFilter] = useState('')
  const [filterOptions, setFilterOptions] = useState([])

  useEffect(() => {
    if (cfg.filter?.source) {
      adminApi.get(`/${cfg.filter.source}`, { params: { all: 1 } })
        .then((r) => setFilterOptions(r.data.data.map((x) => ({ value: x.id, label: x[cfg.filter.labelKey || 'name'] }))))
        .catch(() => {})
    } else if (cfg.filter?.options) {
      setFilterOptions(cfg.filter.options.map((o) => ({ value: o, label: humanize(o) })))
    }
  }, [cfg])

  const load = useCallback(() => {
    const params = { page, q: search }
    if (filter && cfg.filter) params[cfg.filter.name] = filter
    adminApi.get(`/${resource}`, { params })
      .then((r) => { setRows(r.data.data); setMeta(r.data) })
      .catch((e) => { toast(errorMessage(e), 'error'); setRows([]) })
  }, [resource, page, search, filter, cfg, toast])

  useEffect(() => { load() }, [load])

  const remove = async (row) => {
    if (!window.confirm('Delete this item permanently?')) return
    try {
      await adminApi.delete(`/${resource}/${row.id}`)
      toast('Deleted successfully')
      load()
      refreshBadges()
    } catch (e) {
      toast(errorMessage(e), 'error')
    }
  }

  const submitSearch = (e) => { e.preventDefault(); setPage(1); setSearch(q) }

  return (
    <>
      <div className="a-page-head">
        <h1>{cfg.label}</h1>
        {!cfg.readonly && (
          <Link to={`/admin/r/${resource}/new`} className="a-btn a-btn-primary"><FiPlus /> Add {cfg.singular}</Link>
        )}
      </div>

      <div className="a-panel">
        <div className="a-toolbar">
          <form onSubmit={submitSearch} className="a-search">
            <FiSearch />
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search…" />
          </form>
          {cfg.filter && (
            <select value={filter} onChange={(e) => { setFilter(e.target.value); setPage(1) }}>
              <option value="">{cfg.filter.label}</option>
              {filterOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
          )}
        </div>

        {rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead>
                <tr>
                  {cfg.columns.map((c) => <th key={c.key}>{c.label || ''}</th>)}
                  <th className="a-actions-col">Actions</th>
                </tr>
              </thead>
              <tbody>
                {rows.length ? rows.map((row) => (
                  <tr key={row.id} className={row.status === 'new' ? 'is-new' : ''}>
                    {cfg.columns.map((c) => <td key={c.key}><Cell col={c} row={row} /></td>)}
                    <td className="a-actions">
                      <Link to={`/admin/r/${resource}/${row.id}`} className="a-icon-btn" title={cfg.readonly ? 'View' : 'Edit'}>
                        {cfg.readonly ? <FiEye /> : <FiEdit2 />}
                      </Link>
                      {!cfg.noDelete && <button className="a-icon-btn danger" onClick={() => remove(row)} title="Delete"><FiTrash2 /></button>}
                    </td>
                  </tr>
                )) : (
                  <tr><td colSpan={cfg.columns.length + 1} className="a-empty">No records found</td></tr>
                )}
              </tbody>
            </table>
          </div>
        )}

        {meta && meta.last_page > 1 && (
          <div className="a-pagination">
            <span>Showing {meta.from}–{meta.to} of {meta.total}</span>
            <div>
              <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹ Prev</button>
              <span>Page {meta.current_page} / {meta.last_page}</span>
              <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next ›</button>
            </div>
          </div>
        )}
      </div>
    </>
  )
}

export default function ResourceList() {
  const { resource } = useParams()
  const { can } = useAdmin()
  const cfg = RESOURCES[resource]
  if (!cfg) return <Navigate to="/admin" replace />
  if (!can(resourcePerm(cfg))) return <NoAccess />
  return <List key={resource} resource={resource} cfg={cfg} />
}
