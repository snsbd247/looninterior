import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { FiPlus, FiArrowLeft, FiSave, FiSlash, FiDollarSign } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Field, InfoGrid, Money, Status } from '../../components/ui'
import ItemRows, { blankRow } from '../../components/ItemRows'
import { label, money, today, useList } from '../../lib'
import { useStores } from '../inventory/Stock'
import { useSuppliers } from './PurchaseOrders'
import { PayModal } from './Suppliers'

export function PurchaseList() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [q, setQ] = useState('')
  const list = useList('/purchases', { q: '', type: '', status: '' }, { toast })
  return (
    <>
      <div className="a-page-head"><h1>Purchases</h1>
        <Can perm="inventory.manage"><div className="b-head-actions">
          <Link className="a-btn a-btn-light" to="/admin/biz/purchases/new?type=direct"><FiPlus /> Direct to project</Link>
          <Link className="a-btn a-btn-primary" to="/admin/biz/purchases/new?type=grn"><FiPlus /> Goods Received (GRN)</Link>
        </div></Can>
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Number, bill no., supplier…" /></form>
          <select value={list.filters.type} onChange={(e) => list.setFilter('type', e.target.value)}><option value="">All</option><option value="grn">Into store (GRN)</option><option value="direct">Direct to project</option></select>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="">All status</option><option value="received">Received</option><option value="cancelled">Cancelled</option></select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>No.</th><th>Date</th><th>Supplier</th><th>Store / Project</th><th>Status</th><th className="r">Total</th><th className="r">Paid</th></tr></thead>
            <tbody>{list.rows.length ? list.rows.map((p) => (
              <tr key={p.id} className={`b-clickable ${p.status === 'cancelled' ? 'b-done-row' : ''}`} onClick={() => navigate(`/admin/biz/purchases/${p.id}`)}>
                <td><b>{p.number}</b>{p.supplier_ref && <div className="a-muted b-sub">Bill {p.supplier_ref}</div>}</td><td className="b-nowrap">{formatDate(p.date)}</td>
                <td>{p.supplier?.name || <span className="a-muted">Cash purchase</span>}</td><td>{p.store?.name || p.project?.code}</td><td><Status value={p.status} /></td>
                <td className="r"><Money value={p.total} /></td><td className="r"><Money value={p.paid} /></td>
              </tr>
            )) : <tr><td colSpan={7} className="a-empty">No purchases</td></tr>}</tbody>
          </table></div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}

export function PurchaseForm() {
  const [params] = useSearchParams()
  const navigate = useNavigate()
  const { toast, can } = useAdmin()
  const stores = useStores()
  const suppliers = useSuppliers()
  const [projects, setProjects] = useState([])
  const [accounts, setAccounts] = useState([])
  const [po, setPo] = useState(null)
  const [form, setForm] = useState({ type: params.get('type') || 'grn', supplier_id: '', store_id: '', work_project_id: params.get('project') || '', purchase_order_id: '', supplier_ref: '', date: today(), notes: '', pay_now: false, pay_now_account_id: '', pay_method: 'cash' })
  const [rows, setRows] = useState([blankRow()])
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)

  useEffect(() => { businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data.filter((p) => p.status !== 'cancelled'))).catch(() => {}) }, [])
  useEffect(() => { if (can('suppliers.pay')) businessApi.get('/lookup/accounts').then((r) => setAccounts(r.data.data)).catch(() => {}) }, [can])
  useEffect(() => { if (!form.store_id && stores[0]) setForm((f) => ({ ...f, store_id: stores[0].id })) }, [stores, form.store_id])
  useEffect(() => {
    const poId = params.get('po')
    if (!poId) return
    businessApi.get(`/purchase-orders/${poId}`).then((r) => {
      const o = r.data.data
      setPo(o)
      setForm((f) => ({ ...f, type: 'grn', supplier_id: o.supplier_id, store_id: o.store_id, purchase_order_id: o.id }))
      setRows(o.items.filter((i) => Number(i.received_qty) < Number(i.qty)).map((i) => ({ item_id: i.item_id, purchase_order_item_id: i.id, description: '', qty: Number(i.qty) - Number(i.received_qty), rate: Number(i.rate) })))
    }).catch((e) => toast(errorMessage(e), 'error'))
  }, [params, toast])

  const submit = async (e) => {
    e.preventDefault(); setSaving(true); setErrors({})
    const payload = { ...form, items: rows.filter((r) => r.item_id || r.description), supplier_id: form.supplier_id || null, purchase_order_id: form.purchase_order_id || null,
      store_id: form.type === 'grn' ? form.store_id : null, work_project_id: form.type === 'direct' ? form.work_project_id : null,
      pay_now_account_id: form.pay_now ? form.pay_now_account_id : null }
    try { const r = await businessApi.post('/purchases', payload); toast(r.data.message); navigate(`/admin/biz/purchases/${r.data.data.id}`) } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') } finally { setSaving(false) }
  }
  const total = rows.reduce((s, r) => s + Number(r.qty || 0) * Number(r.rate || 0), 0)

  return (
    <>
      <div className="a-page-head"><h1>{form.type === 'grn' ? 'Goods Received (GRN)' : 'Direct Purchase for Project'}</h1><Link to="/admin/biz/purchases" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link></div>
      {!po && <div className="b-chips-select b-type-chips">
        <button type="button" className={form.type === 'grn' ? 'is-on' : ''} onClick={() => setForm({ ...form, type: 'grn' })}>Into a store (stock)</button>
        <button type="button" className={form.type === 'direct' ? 'is-on' : ''} onClick={() => setForm({ ...form, type: 'direct' })}>Directly to a project</button>
      </div>}
      {po && <div className="b-callout">Receiving against <b>{po.number}</b> ({po.supplier?.name}). Adjust quantities to what actually arrived.</div>}
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <Field label="Supplier" error={errors.supplier_id} help={form.type === 'direct' ? 'Empty = cash purchase from the market (pay now below)' : ''}>
            <select value={form.supplier_id} onChange={(e) => setForm({ ...form, supplier_id: e.target.value })} disabled={!!po}><option value="">—</option>{suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>
          </Field>
          {form.type === 'grn' ? (
            <Field label="Into store *" error={errors.store_id}><select value={form.store_id} onChange={(e) => setForm({ ...form, store_id: e.target.value })} disabled={!!po}>{stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></Field>
          ) : (
            <Field label="Project *" error={errors.work_project_id}><select value={form.work_project_id} onChange={(e) => setForm({ ...form, work_project_id: e.target.value })}><option value="">—</option>{projects.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select></Field>
          )}
          <Field label="Supplier bill / challan no." error={errors.supplier_ref}><input value={form.supplier_ref} onChange={(e) => setForm({ ...form, supplier_ref: e.target.value })} /></Field>
          <Field label="Date *" error={errors.date}><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
        </div>
        <h3 className="b-section-title">Items</h3>
        <ItemRows rows={rows} onChange={setRows} withRate allowFree={form.type === 'direct'} errors={errors} />
        {can('suppliers.pay') && (
          <div className="b-paynow">
            <label className="switch"><input type="checkbox" checked={form.pay_now} onChange={(e) => setForm({ ...form, pay_now: e.target.checked })} /><span className="slider" /><span className="switch-label">Paid now ({money(total, { symbol: true })})</span></label>
            {form.pay_now && <>
              <select value={form.pay_now_account_id} onChange={(e) => setForm({ ...form, pay_now_account_id: e.target.value })}><option value="">— From account —</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}</option>)}</select>
              <select value={form.pay_method} onChange={(e) => setForm({ ...form, pay_method: e.target.value })}>{['cash', 'bank', 'bkash', 'nagad', 'cheque'].map((m) => <option key={m} value={m}>{label(m)}</option>)}</select>
            </>}
            {errors.pay_now_account_id && <small className="field-error">{errors.pay_now_account_id}</small>}
            {errors.amount && <small className="field-error">{errors.amount}</small>}
          </div>
        )}
        <Field label="Note"><input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
        <div className="a-form-actions"><button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save'}</button><span className="a-muted">{form.type === 'grn' ? 'Stock and average cost update immediately.' : 'Cost is added to the project; supplier due increases unless paid now.'}</span></div>
      </form>
    </>
  )
}

export function PurchaseDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [p, setP] = useState(null)
  const [pay, setPay] = useState(false)
  const load = useCallback(() => { businessApi.get(`/purchases/${id}`).then((r) => setP(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  if (!p) return <Loader />
  const left = Number(p.total) - Number(p.paid)
  const cancel = async () => {
    const reason = window.prompt('Why cancel this purchase? (Stock will be removed again for GRNs)')
    if (!reason) return
    try { await businessApi.post(`/purchases/${id}/cancel`, { reason }); toast('Purchase cancelled'); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  return (
    <>
      <div className="a-page-head">
        <h1>{p.number} <Status value={p.status} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/purchases" className="a-btn a-btn-light"><FiArrowLeft /> Purchases</Link>
          {can('suppliers.pay') && p.status === 'received' && p.supplier && left > 0.004 && <button className="a-btn a-btn-primary" onClick={() => setPay(true)}><FiDollarSign /> Pay supplier</button>}
          {can('inventory.manage') && p.status === 'received' && <button className="a-btn a-btn-light danger" onClick={cancel}><FiSlash /> Cancel</button>}
        </div>
      </div>
      {p.cancel_reason && <div className="b-callout is-warn">Cancelled: {p.cancel_reason}</div>}
      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Item</th><th className="r">Qty</th><th className="r">Rate</th><th className="r">Amount</th></tr></thead>
            <tbody>{p.items.map((i) => <tr key={i.id}><td>{i.item?.name || i.description}{i.item && i.description && i.description !== i.item.name && <div className="a-muted b-sub">{i.description}</div>}</td><td className="r">{Number(i.qty)} {i.unit}</td><td className="r">{money(i.rate)}</td><td className="r">{money(i.amount)}</td></tr>)}</tbody>
          </table></div><div className="b-pad r"><b>Total: {money(p.total, { symbol: true })}</b> · Paid {money(p.paid)} · Unpaid {money(left)}</div></div>
          {p.payments?.length > 0 && <div className="a-panel b-pad"><h3 className="b-panel-title">Payments</h3>{p.payments.map((x) => <div key={x.id} className="b-sub">{x.number} · {formatDate(x.date)} · {label(x.method)} · {x.account?.name} · <Money value={x.amount} /> <Status value={x.status} /></div>)}</div>}
        </div>
        <aside className="b-detail-side"><div className="a-panel b-pad"><InfoGrid items={[
          ['Type', p.type === 'grn' ? 'Into store (GRN)' : 'Direct to project'], ['Supplier', p.supplier ? <Link to={`/admin/biz/suppliers/${p.supplier_id}`}>{p.supplier.name}</Link> : 'Cash purchase'],
          ['Store', p.store?.name], ['Project', p.project ? <Link to={`/admin/biz/projects/${p.work_project_id}`}>{p.project.code}</Link> : null],
          ['PO', p.order ? <Link to={`/admin/biz/purchase-orders/${p.order.id}`}>{p.order.number}</Link> : null], ['Bill no.', p.supplier_ref],
          ['Date', formatDate(p.date)], ['Branch', p.branch?.name], ['Created by', p.creator?.name], ['Note', p.notes],
        ]} /></div></aside>
      </div>
      {pay && <PayModal supplier={p.supplier} purchase={{ id: p.id, number: p.number, left }} onClose={() => setPay(false)} onDone={() => { setPay(false); load() }} />}
    </>
  )
}
