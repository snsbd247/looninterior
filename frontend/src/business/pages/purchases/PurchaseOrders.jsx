import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiPlus, FiArrowLeft, FiSave, FiSend, FiSlash, FiEdit2, FiPackage } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Field, InfoGrid, Money, Status } from '../../components/ui'
import DocActions from '../../components/DocActions'
import ItemRows, { blankRow } from '../../components/ItemRows'
import { label, money, today, useList } from '../../lib'
import { useStores } from '../inventory/Stock'

export function useSuppliers() {
  const [list, setList] = useState([])
  useEffect(() => { businessApi.get('/suppliers').then((r) => setList(r.data.data)).catch(() => {}) }, [])
  return list
}

export function PurchaseOrderList() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const list = useList('/purchase-orders', { status: '' }, { toast })
  return (
    <>
      <div className="a-page-head"><h1>Purchase Orders</h1><Can perm="inventory.manage"><Link className="a-btn a-btn-primary" to="/admin/biz/purchase-orders/new"><FiPlus /> New PO</Link></Can></div>
      <div className="a-panel">
        <div className="a-toolbar">
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="">All status</option>{['draft', 'sent', 'partial', 'received', 'cancelled'].map((s) => <option key={s} value={s}>{label(s)}</option>)}</select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>PO</th><th>Supplier</th><th>Store</th><th>Date</th><th>Expected</th><th>Status</th><th className="r">Total</th></tr></thead>
            <tbody>{list.rows.length ? list.rows.map((o) => (
              <tr key={o.id} className="b-clickable" onClick={() => navigate(`/admin/biz/purchase-orders/${o.id}`)}>
                <td><b>{o.number}</b></td><td>{o.supplier?.name}</td><td>{o.store?.name}</td><td>{formatDate(o.date)}</td><td>{formatDate(o.expected_date) || '—'}</td><td><Status value={o.status} /></td><td className="r"><Money value={o.total} /></td>
              </tr>
            )) : <tr><td colSpan={7} className="a-empty">No purchase orders</td></tr>}</tbody>
          </table></div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
    </>
  )
}

export function PurchaseOrderForm() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { toast } = useAdmin()
  const stores = useStores()
  const suppliers = useSuppliers()
  const [form, setForm] = useState(id ? null : { supplier_id: '', store_id: '', date: today(), expected_date: '', notes: '' })
  const [rows, setRows] = useState([blankRow()])
  const [errors, setErrors] = useState({})
  useEffect(() => {
    if (!id) return
    businessApi.get(`/purchase-orders/${id}`).then((r) => {
      const o = r.data.data
      setForm({ supplier_id: o.supplier_id, store_id: o.store_id, date: o.date, expected_date: o.expected_date || '', notes: o.notes || '' })
      setRows(o.items.map((i) => ({ item_id: i.item_id, description: i.description || '', qty: Number(i.qty), rate: Number(i.rate) })))
    }).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])
  useEffect(() => { if (form && !form.store_id && stores[0]) setForm((f) => ({ ...f, store_id: stores[0].id })) }, [stores, form])
  if (!form) return <Loader />
  const submit = async (e) => {
    e.preventDefault(); setErrors({})
    try {
      const r = await businessApi.post(id ? `/purchase-orders/${id}` : '/purchase-orders', { ...form, expected_date: form.expected_date || null, items: rows.filter((x) => x.item_id) })
      toast(r.data.message); navigate(`/admin/biz/purchase-orders/${r.data.data.id}`)
    } catch (ex) { setErrors(fieldErrors(ex)); toast(errorMessage(ex), 'error') }
  }
  return (
    <>
      <div className="a-page-head"><h1>{id ? 'Edit' : 'New'} Purchase Order</h1><Link to="/admin/biz/purchase-orders" className="a-btn a-btn-light"><FiArrowLeft /> Back</Link></div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          <Field label="Supplier *" error={errors.supplier_id}><select value={form.supplier_id} onChange={(e) => setForm({ ...form, supplier_id: e.target.value })}><option value="">—</option>{suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></Field>
          <Field label="Deliver to store *" error={errors.store_id}><select value={form.store_id} onChange={(e) => setForm({ ...form, store_id: e.target.value })}>{stores.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></Field>
          <Field label="Date *" error={errors.date}><input type="date" value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
          <Field label="Expected delivery" error={errors.expected_date}><input type="date" value={form.expected_date} onChange={(e) => setForm({ ...form, expected_date: e.target.value })} /></Field>
          <Field label="Note" full><input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
        </div>
        <h3 className="b-section-title">Items</h3>
        <ItemRows rows={rows} onChange={setRows} withRate errors={errors} />
        <div className="a-form-actions"><button className="a-btn a-btn-primary"><FiSave /> Save</button></div>
      </form>
    </>
  )
}

export function PurchaseOrderDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [o, setO] = useState(null)
  const load = useCallback(() => { businessApi.get(`/purchase-orders/${id}`).then((r) => setO(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  if (!o) return <Loader />
  const manage = can('inventory.manage')
  const setStatus = async (status) => {
    if (status === 'cancelled' && !window.confirm('Cancel this purchase order?')) return
    try { const r = await businessApi.post(`/purchase-orders/${id}/status`, { status }); toast(r.data.message); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  return (
    <>
      <div className="a-page-head">
        <h1>{o.number} <Status value={o.status} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/purchase-orders" className="a-btn a-btn-light"><FiArrowLeft /> POs</Link>
          {manage && o.status === 'draft' && <Link to={`/admin/biz/purchase-orders/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>}
        </div>
      </div>
      <div className="a-panel b-pad b-actionbar">
        <DocActions base={`/purchase-orders/${id}`} email={o.supplier?.email} canEmail={manage} onEmailed={load} />
        {manage && <div className="b-doc-actions">
          {o.status === 'draft' && <button className="a-btn a-btn-light" onClick={() => setStatus('sent')}><FiSend /> Mark Sent</button>}
          {['draft', 'sent', 'partial'].includes(o.status) && <Link className="a-btn a-btn-primary" to={`/admin/biz/purchases/new?po=${id}`}><FiPackage /> Receive Goods</Link>}
          {['draft', 'sent'].includes(o.status) && <button className="a-btn a-btn-light danger" onClick={() => setStatus('cancelled')}><FiSlash /> Cancel</button>}
        </div>}
      </div>
      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Item</th><th className="r">Ordered</th><th className="r">Received</th><th className="r">Rate</th><th className="r">Amount</th></tr></thead>
            <tbody>{o.items.map((i) => <tr key={i.id}><td>{i.item?.name}{i.description && <div className="a-muted b-sub">{i.description}</div>}</td><td className="r">{Number(i.qty)} {i.item?.unit}</td><td className={`r ${Number(i.received_qty) >= Number(i.qty) ? 'b-in' : ''}`}>{Number(i.received_qty)}</td><td className="r">{money(i.rate)}</td><td className="r">{money(i.amount)}</td></tr>)}</tbody>
          </table></div><div className="b-pad r"><b>Total: {money(o.total, { symbol: true })}</b></div></div>
          {o.purchases?.length > 0 && <div className="a-panel b-pad"><h3 className="b-panel-title">Goods received</h3>{o.purchases.map((p) => <div key={p.id}><Link to={`/admin/biz/purchases/${p.id}`}>{p.number}</Link> · {formatDate(p.date)} · <Money value={p.total} /> <Status value={p.status} /></div>)}</div>}
        </div>
        <aside className="b-detail-side"><div className="a-panel b-pad"><InfoGrid items={[['Supplier', o.supplier?.name], ['Phone', o.supplier?.phone], ['Store', o.store?.name], ['Date', formatDate(o.date)], ['Expected', formatDate(o.expected_date)], ['Branch', o.branch?.name], ['Created by', o.creator?.name], ['Note', o.notes]]} /></div></aside>
      </div>
    </>
  )
}
