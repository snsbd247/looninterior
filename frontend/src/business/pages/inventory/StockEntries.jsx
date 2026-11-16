import { useEffect, useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { FiPlus, FiArrowLeft, FiSave, FiSlash } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Field, Status } from '../../components/ui'
import ItemRows, { blankRow } from '../../components/ItemRows'
import { label, today, useList } from '../../lib'
import { useStores } from './Stock'

const TYPES = [
  ['issue', 'Issue to project', 'Material goes from a store to a site; its cost is added to the project.'],
  ['return', 'Return from project', 'Unused material comes back from a site into a store.'],
  ['transfer', 'Store transfer', 'Move stock between stores (also to another branch in “All branches” view).'],
  ['adjust', 'Adjustment', 'Correct stock after a count, damage or loss. Use − for less, + for more.'],
]

export function StockEntryForm() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { toast, user } = useAdmin()
  const stores = useStores()
  const [projects, setProjects] = useState([])
  const [form, setForm] = useState({ type: params.get('type') || 'issue', store_id: '', to_store_id: '', work_project_id: params.get('project') || '', date: today(), notes: '' })
  const [rows, setRows] = useState([blankRow()])
  const [stock, setStock] = useState({})
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  const [allStores, setAllStores] = useState([])

  useEffect(() => { businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data.filter((p) => !['completed', 'cancelled'].includes(p.status)))).catch(() => {}) }, [])
  useEffect(() => { if (!form.store_id && stores[0]) setForm((f) => ({ ...f, store_id: stores[0].id })) }, [stores, form.store_id])
  useEffect(() => {
    if (form.type !== 'transfer' || !user.can_all_branches) { setAllStores(stores); return }
    businessApi.get('/lookup/stores', { headers: { 'X-Branch-Id': 'all' } }).then((r) => setAllStores(r.data.data)).catch(() => setAllStores(stores))
  }, [form.type, stores, user.can_all_branches])
  useEffect(() => {
    if (!form.store_id) return
    businessApi.get('/stock', { params: { store_id: form.store_id } }).then((r) => setStock(Object.fromEntries(r.data.data.map((i) => [i.id, i.qty])))).catch(() => {})
  }, [form.store_id])

  const store = stores.find((s) => String(s.id) === String(form.store_id))
  const submit = async (e) => {
    e.preventDefault()
    setSaving(true); setErrors({})
    try {
      const res = await businessApi.post('/stock-entries', { ...form, to_store_id: form.to_store_id || null, work_project_id: form.work_project_id || null, items: rows.filter((r) => r.item_id).map((r) => ({ item_id: r.item_id, qty: r.qty })) },
        form.type === 'transfer' && user.can_all_branches ? { headers: { 'X-Branch-Id': 'all' } } : undefined)
      toast(res.data.message)
      navigate('/admin/biz/stock-entries')
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }
  const t = TYPES.find((x) => x[0] === form.type)

  return (
    <>
      <div className="a-page-head"><h1>Stock Entry</h1><Link to="/admin/biz/stock-entries" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link></div>
      <div className="b-chips-select b-type-chips">{TYPES.map(([k, l]) => <button type="button" key={k} className={form.type === k ? 'is-on' : ''} onClick={() => setForm({ ...form, type: k })}>{l}</button>)}</div>
      <p className="a-muted b-sub">{t[2]}</p>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <Field label={form.type === 'transfer' ? 'From store *' : 'Store *'} error={errors.store_id}>
            <select value={form.store_id} onChange={(e) => setForm({ ...form, store_id: e.target.value })}>{stores.map((s) => <option key={s.id} value={s.id}>{s.name}{s.branch ? ` (${s.branch.code})` : ''}</option>)}</select>
          </Field>
          {form.type === 'transfer' && (
            <Field label="To store *" error={errors.to_store_id}>
              <select value={form.to_store_id} onChange={(e) => setForm({ ...form, to_store_id: e.target.value })}><option value="">—</option>{allStores.filter((s) => String(s.id) !== String(form.store_id)).map((s) => <option key={s.id} value={s.id}>{s.name}{s.branch ? ` (${s.branch.code})` : ''}</option>)}</select>
            </Field>
          )}
          {['issue', 'return'].includes(form.type) && (
            <Field label="Project *" error={errors.work_project_id}>
              <select value={form.work_project_id} onChange={(e) => setForm({ ...form, work_project_id: e.target.value })}><option value="">—</option>{projects.filter((p) => !store || p.branch_id === store.branch_id).map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select>
            </Field>
          )}
          <Field label="Date *" error={errors.date}><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
          <Field label={form.type === 'adjust' ? 'Reason *' : 'Note'} error={errors.notes} full><input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
        </div>
        <h3 className="b-section-title">Items</h3>
        <ItemRows rows={rows} onChange={setRows} available={['issue', 'transfer', 'adjust'].includes(form.type) ? stock : null} signed={form.type === 'adjust'} errors={errors} />
        <div className="a-form-actions"><button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save'}</button></div>
      </form>
    </>
  )
}

export function StockEntryDetail({ id, onClose, onChanged }) {
  const { toast, can } = useAdmin()
  const [e, setE] = useState(null)
  useEffect(() => { businessApi.get(`/stock-entries/${id}`).then((r) => setE(r.data.data)).catch((x) => toast(errorMessage(x), 'error')) }, [id, toast])
  if (!e) return null
  const cancel = async () => {
    const reason = window.prompt('Why cancel this entry? Stock will be put back.')
    if (!reason) return
    try { await businessApi.post(`/stock-entries/${id}/cancel`, { reason }); toast('Cancelled'); onChanged() } catch (x) { toast(errorMessage(x), 'error') }
  }
  return (
    <tr className="b-detail-row"><td colSpan={7}>
      <table className="b-changes"><thead><tr><th>Item</th><th>Qty</th><th>Unit cost</th><th>Amount</th></tr></thead>
        <tbody>{e.items.map((i) => <tr key={i.id}><td>{i.item?.name}</td><td>{Number(i.qty)} {i.item?.unit}</td><td>{Number(i.unit_cost).toFixed(2)}</td><td>{Number(i.amount).toFixed(2)}</td></tr>)}</tbody>
      </table>
      {e.notes && <div className="b-sub">Note: {e.notes}</div>}
      {e.cancel_reason && <div className="b-sub b-overdue">Cancelled: {e.cancel_reason}</div>}
      <div className="b-doc-actions b-mt">
        <button className="a-btn a-btn-light" onClick={onClose}>Close</button>
        {can('inventory.manage') && e.status === 'active' && <button className="a-btn a-btn-light danger" onClick={cancel}><FiSlash /> Cancel entry</button>}
      </div>
    </td></tr>
  )
}

export default function StockEntries() {
  const { toast } = useAdmin()
  const list = useList('/stock-entries', { type: '', status: '' }, { toast })
  const [open, setOpen] = useState(null)
  return (
    <>
      <div className="a-page-head">
        <h1>Stock Entries</h1>
        <Can perm="inventory.manage"><Link className="a-btn a-btn-primary" to="/admin/biz/stock-entries/new"><FiPlus /> New Entry</Link></Can>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <select value={list.filters.type} onChange={(e) => list.setFilter('type', e.target.value)}><option value="">All types</option>{TYPES.map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="">All</option><option value="active">Active</option><option value="cancelled">Cancelled</option></select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>No.</th><th>Date</th><th>Type</th><th>Store</th><th>To / Project</th><th className="r">Lines</th><th>Status</th></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((e) => [
                  <tr key={e.id} className={`b-clickable ${e.status === 'cancelled' ? 'b-done-row' : ''}`} onClick={() => setOpen(open === e.id ? null : e.id)}>
                    <td><b>{e.number}</b></td><td className="b-nowrap">{formatDate(e.date)}</td><td>{label(e.type)}</td><td>{e.store?.name}</td>
                    <td>{e.to_store?.name || (e.project ? `${e.project.code} · ${e.project.title}` : '—')}</td><td className="r">{e.items_count}</td><td><Status value={e.status} /></td>
                  </tr>,
                  open === e.id && <StockEntryDetail key={`d${e.id}`} id={e.id} onClose={() => setOpen(null)} onChanged={() => { setOpen(null); list.reload() }} />,
                ]) : <tr><td colSpan={7} className="a-empty">No entries</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}
