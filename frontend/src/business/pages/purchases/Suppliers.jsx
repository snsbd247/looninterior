import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiPlus, FiArrowLeft, FiDollarSign, FiEdit2, FiSlash, FiPhone } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { Field, InfoGrid, Modal, Money, Stat, Status } from '../../components/ui'
import { label, money, phoneHref, today } from '../../lib'

export function PayModal({ supplier, purchase, onClose, onDone }) {
  const { toast } = useAdmin()
  const [accounts, setAccounts] = useState([])
  const [form, setForm] = useState({ amount: purchase?.left || '', account_id: '', method: 'bank', reference: '', date: today(), notes: '', purchase_id: purchase?.id || '' })
  const [errors, setErrors] = useState({})
  useEffect(() => { businessApi.get('/lookup/accounts').then((r) => { setAccounts(r.data.data); if (r.data.data[0]) setForm((f) => ({ ...f, account_id: r.data.data[0].id })) }).catch(() => {}) }, [])
  const save = async () => {
    try { const r = await businessApi.post(`/suppliers/${supplier.id}/pay`, { ...form, purchase_id: form.purchase_id || null }); toast(r.data.message); onDone() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  return (
    <Modal title={`Pay ${supplier.name}`} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}><FiDollarSign /> Pay</button></>}>
      {purchase && <div className="b-callout is-warn">Against {purchase.number} · unpaid {money(purchase.left)}</div>}
      <Field label="Amount (৳) *" error={errors.amount}><input type="number" min="0.01" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>
      <Field label="From account *" error={errors.account_id}><select value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>{accounts.map((a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}</option>)}</select></Field>
      <Field label="Method"><select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>{['cash', 'bank', 'bkash', 'nagad', 'cheque', 'other'].map((m) => <option key={m} value={m}>{label(m)}</option>)}</select></Field>
      <Field label="Reference"><input value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} placeholder="Cheque / transaction no." /></Field>
      <Field label="Date *" error={errors.date}><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
    </Modal>
  )
}

export function SupplierList() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  const [q, setQ] = useState('')
  const load = useCallback((query = '') => { businessApi.get('/suppliers', { params: { q: query } }).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  useEffect(() => { load() }, [load])
  return (
    <>
      <div className="a-page-head"><h1>Suppliers</h1><Can perm="inventory.manage"><Link className="a-btn a-btn-primary" to="/admin/r/suppliers/new"><FiPlus /> Supplier</Link></Can></div>
      {data && <div className="b-stats"><Stat label="Total payable to suppliers" value={money(data.total_due, { symbol: true })} tone={data.total_due > 0 ? 'warn' : ''} /></div>}
      <div className="a-panel">
        <div className="a-toolbar"><form className="a-search" onSubmit={(e) => { e.preventDefault(); load(q) }}><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Name or phone…" /></form></div>
        {!data ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Supplier</th><th>Phone</th><th className="r">Purchased</th><th className="r">Paid</th><th className="r">Due</th></tr></thead>
            <tbody>{data.data.length ? data.data.map((s) => (
              <tr key={s.id} className="b-clickable" onClick={() => navigate(`/admin/biz/suppliers/${s.id}`)}>
                <td><b>{s.name}</b></td><td>{s.phone || '—'}</td><td className="r">{money(s.purchased)}</td><td className="r">{money(s.paid)}</td><td className="r"><Money value={s.due} /></td>
              </tr>
            )) : <tr><td colSpan={5} className="a-empty">No suppliers yet</td></tr>}</tbody>
          </table></div>
        )}
      </div>
    </>
  )
}

export function SupplierDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [data, setData] = useState(null)
  const [pay, setPay] = useState(false)
  const load = useCallback(() => { businessApi.get(`/suppliers/${id}`).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  if (!data) return <Loader />
  const s = data.data
  const cancelPay = async (l) => {
    const reason = window.prompt('Why cancel this payment?')
    if (!reason) return
    try { await businessApi.post(`/supplier-payments/${l.id}/cancel`, { reason }); toast('Payment cancelled'); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  return (
    <>
      <div className="a-page-head">
        <h1>{s.name}</h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/suppliers" className="a-btn a-btn-light"><FiArrowLeft /> Suppliers</Link>
          <Can perm="inventory.manage"><Link to={`/admin/r/suppliers/${id}`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link></Can>
          {can('suppliers.pay') && <button className="a-btn a-btn-primary" onClick={() => setPay(true)}><FiDollarSign /> Pay</button>}
        </div>
      </div>
      <div className="b-stats"><Stat label="Due to supplier" value={money(data.due, { symbol: true })} tone={data.due > 0 ? 'warn' : 'good'} /><Stat label="Opening due" value={money(s.opening_due)} /></div>
      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel">
            <div className="a-panel-head"><h3>Statement</h3></div>
            <div className="a-table-wrap"><table className="a-table">
              <thead><tr><th>Date</th><th>Document</th><th>Detail</th><th className="r">Purchase</th><th className="r">Paid</th><th className="r">Balance</th><th /></tr></thead>
              <tbody>
                <tr className="b-items-section"><td colSpan={5}>Opening due</td><td className="r">{money(s.opening_due)}</td><td /></tr>
                {data.lines.map((l) => (
                  <tr key={`${l.kind}${l.id}`} className={l.status === 'cancelled' ? 'b-done-row' : ''}>
                    <td className="b-nowrap">{formatDate(l.date)}</td>
                    <td>{l.kind === 'purchase' ? <Link to={`/admin/biz/purchases/${l.id}`}>{l.number}</Link> : l.number}{l.ref && <div className="a-muted b-sub">{l.ref}</div>}</td>
                    <td className="b-sub">{l.detail} {l.status === 'cancelled' && <Status value="cancelled" />}</td>
                    <td className="r">{l.debit ? money(l.debit) : ''}</td><td className="r b-in">{l.credit ? money(l.credit) : ''}</td><td className="r"><b>{money(l.balance)}</b></td>
                    <td className="a-actions">{l.kind === 'payment' && l.status === 'paid' && can('suppliers.pay') && <button className="a-icon-btn danger" title="Cancel payment" onClick={() => cancelPay(l)}><FiSlash /></button>}</td>
                  </tr>
                ))}
              </tbody>
            </table></div>
          </div>
        </div>
        <aside className="b-detail-side"><div className="a-panel b-pad">
          {s.phone && <div className="b-contact-actions"><a className="a-btn a-btn-light" href={phoneHref(s.phone)}><FiPhone /> Call</a></div>}
          <InfoGrid items={[['Phone', s.phone], ['Email', s.email], ['Address', s.address], ['Notes', s.notes]]} />
        </div></aside>
      </div>
      {pay && <PayModal supplier={s} onClose={() => setPay(false)} onDone={() => { setPay(false); load() }} />}
    </>
  )
}
