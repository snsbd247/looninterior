import { useCallback, useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { FiArrowLeft, FiEdit2, FiSend, FiDollarSign, FiMinusCircle, FiSlash, FiFileText } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Field, InfoGrid, Modal, Money, Status } from '../../components/ui'
import DocActions, { openPdf } from '../../components/DocActions'
import { label, money, today, useLookup } from '../../lib'
import { ItemsTable, TotalsView } from '../quotations/QuotationDetail'

export const METHODS = ['cash', 'bank', 'bkash', 'nagad', 'rocket', 'cheque', 'card', 'other']

function ReceiveModal({ invoice, onClose, onDone }) {
  const { toast } = useAdmin()
  const [accounts, setAccounts] = useState([])
  const [form, setForm] = useState({ amount: Number(invoice.due), account_id: '', method: 'cash', reference: '', date: today(), notes: '' })
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  useEffect(() => {
    businessApi.get('/lookup/accounts').then((r) => {
      const list = r.data.data.filter((a) => a.branch_id === invoice.branch_id)
      setAccounts(list)
      if (list[0]) setForm((f) => ({ ...f, account_id: list[0].id }))
    }).catch(() => {})
  }, [invoice.branch_id])
  const submit = async () => {
    setSaving(true); setErrors({})
    try { const r = await businessApi.post(`/invoices/${invoice.id}/receive`, form); toast(r.data.message); onDone(r.data.data) } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') } finally { setSaving(false) }
  }
  return (
    <Modal title={`Receive payment · ${invoice.number}`} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={submit} disabled={saving}><FiDollarSign /> {saving ? 'Saving…' : 'Receive'}</button></>}>
      <div className="b-callout is-warn">Due: <b>{money(invoice.due, { symbol: true })}</b></div>
      <Field label="Amount (৳) *" error={errors.amount}><input type="number" min="0.01" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>
      <Field label="Method *" error={errors.method}><select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>{METHODS.map((m) => <option key={m} value={m}>{label(m)}</option>)}</select></Field>
      <Field label="Into account *" error={errors.account_id} help="Cash in hand, bank or bKash/Nagad account of this branch">
        <select value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>
          {accounts.map((a) => <option key={a.id} value={a.id}>{a.name} ({label(a.type)}) · {money(a.balance)}</option>)}
        </select>
      </Field>
      <Field label="Reference" error={errors.reference} help="Cheque no., transaction ID…"><input value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} /></Field>
      <Field label="Date *" error={errors.date}><input type="date" value={form.date} max={today()} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
      <Field label="Note"><input value={form.notes} onChange={(e) => setForm({ ...form, notes: e.target.value })} /></Field>
    </Modal>
  )
}

function ReasonModal({ title, amount, onClose, onSubmit, max }) {
  const [form, setForm] = useState({ amount: '', reason: '', date: today() })
  const [errors, setErrors] = useState({})
  const submit = async () => { try { await onSubmit(form) } catch (e) { setErrors(fieldErrors(e)) } }
  return (
    <Modal title={title} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Back</button><button className="a-btn a-btn-primary" onClick={submit} disabled={!form.reason}>Confirm</button></>}>
      {amount && <Field label={`Amount (max ${money(max)})`} error={errors.amount}><input type="number" min="0.01" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>}
      <Field label="Reason *" error={errors.reason}><input value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></Field>
    </Modal>
  )
}

export default function InvoiceDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [inv, setInv] = useState(null)
  const [modal, setModal] = useState(null)

  const load = useCallback(() => {
    businessApi.get(`/invoices/${id}`).then((r) => setInv(r.data.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])
  useEffect(() => { load() }, [load])

  if (!inv) return <Loader />
  const manage = can('billing.manage')
  const open = ['issued', 'partial'].includes(inv.status)

  const issue = async () => {
    try { const r = await businessApi.post(`/invoices/${id}/issue`); toast(r.data.message); load() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const post = (path) => async (form) => {
    const r = await businessApi.post(path, form).catch((e) => { toast(errorMessage(e), 'error'); throw e })
    toast(r.data.message); setModal(null); load()
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{inv.number} <Status value={inv.is_overdue ? 'overdue' : inv.status} text={inv.is_overdue ? 'Overdue' : undefined} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/invoices" className="a-btn a-btn-light"><FiArrowLeft /> Invoices</Link>
          {manage && inv.status === 'draft' && <Link to={`/admin/biz/invoices/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>}
        </div>
      </div>

      <div className="a-panel b-pad b-actionbar">
        <DocActions base={`/invoices/${id}`} email={inv.customer?.email} phone={inv.customer?.phone} canEmail={manage && inv.status !== 'draft'} />
        {manage && (
          <div className="b-doc-actions">
            {inv.status === 'draft' && <button className="a-btn a-btn-primary" onClick={issue}><FiSend /> Issue</button>}
            {open && <button className="a-btn a-btn-primary" onClick={() => setModal('receive')}><FiDollarSign /> Receive Payment</button>}
            {open && <button className="a-btn a-btn-light" onClick={() => setModal('credit')}><FiMinusCircle /> Credit Note</button>}
            {inv.status !== 'cancelled' && Number(inv.paid) === 0 && <button className="a-btn a-btn-light danger" onClick={() => setModal('cancel')}><FiSlash /> Cancel</button>}
          </div>
        )}
      </div>

      {inv.status === 'cancelled' && <div className="b-callout is-warn">Cancelled{inv.cancel_reason ? `: ${inv.cancel_reason}` : ''}</div>}

      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel">
            <div className="a-panel-head"><h3>{inv.title || 'Items'}</h3></div>
            <ItemsTable items={inv.items} />
            <div className="b-pad b-totals-wrap">
              <TotalsView doc={inv} extra={inv.status !== 'draft' && <>
                <div className="b-totals-row"><span>Received</span><b>{money(inv.paid)}</b></div>
                {Number(inv.credited) > 0 && <div className="b-totals-row"><span>Credit notes</span><b>{money(inv.credited)}</b></div>}
                <div className="b-totals-row is-due"><span>Balance due</span><b>{money(inv.due)}</b></div>
              </>} />
            </div>
          </div>

          {inv.status !== 'draft' && (
            <div className="a-panel">
              <div className="a-panel-head"><h3>Payments</h3></div>
              <div className="a-table-wrap">
                <table className="a-table">
                  <thead><tr><th>Receipt</th><th>Date</th><th>Method</th><th>Account</th><th>Status</th><th className="r">Amount</th><th /></tr></thead>
                  <tbody>
                    {inv.payments.length ? inv.payments.map((p) => (
                      <tr key={p.id} className={p.status === 'cancelled' ? 'b-done-row' : ''}>
                        <td className="b-nowrap"><b>{p.number}</b>{p.reference && <div className="a-muted b-sub">{p.reference}</div>}</td>
                        <td className="b-nowrap">{formatDate(p.date)}</td><td>{label(p.method)}</td><td>{p.account?.name}</td>
                        <td><Status value={p.status} />{p.cancel_reason && <div className="a-muted b-sub">{p.cancel_reason}</div>}</td>
                        <td className="r"><Money value={p.amount} /></td>
                        <td className="a-actions">
                          <button className="a-icon-btn" title="Receipt PDF" onClick={() => openPdf(`/payments/${p.id}/pdf`, 'en', toast)}><FiFileText /></button>
                          {manage && p.status === 'received' && <button className="a-icon-btn danger" title="Cancel payment" onClick={() => setModal({ cancelPayment: p })}><FiSlash /></button>}
                        </td>
                      </tr>
                    )) : <tr><td colSpan={7} className="a-empty">No payments yet</td></tr>}
                  </tbody>
                </table>
              </div>
              {inv.credit_notes?.length > 0 && (
                <div className="b-pad">
                  <h4 className="b-mini-title">Credit notes</h4>
                  {inv.credit_notes.map((c) => <div key={c.id} className="b-sub">{c.number} · {formatDate(c.date)} · <Money value={c.amount} /> · {c.reason}</div>)}
                </div>
              )}
            </div>
          )}
        </div>
        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <InfoGrid items={[
              ['Customer', <Link to={`/admin/biz/customers/${inv.customer_id}`}>{inv.customer?.name}</Link>], ['Phone', inv.customer?.phone],
              ['Project', inv.project ? <Link to={`/admin/biz/projects/${inv.project.id}`}>{inv.project.code}</Link> : null],
              ['Date', formatDate(inv.date)], ['Due date', formatDate(inv.due_date)], ['Total', <Money value={inv.total} symbol />],
              ['Balance due', <Money value={inv.due} symbol />], ['Branch', inv.branch?.name], ['Created by', inv.creator?.name],
            ]} />
          </div>
        </aside>
      </div>

      {modal === 'receive' && <ReceiveModal invoice={inv} onClose={() => setModal(null)} onDone={() => { setModal(null); load() }} />}
      {modal === 'credit' && <ReasonModal title="Credit note" amount max={inv.due} onClose={() => setModal(null)} onSubmit={post(`/invoices/${id}/credit-note`)} />}
      {modal === 'cancel' && <ReasonModal title="Cancel invoice" onClose={() => setModal(null)} onSubmit={post(`/invoices/${id}/cancel`)} />}
      {modal?.cancelPayment && <ReasonModal title={`Cancel payment ${modal.cancelPayment.number}`} onClose={() => setModal(null)} onSubmit={post(`/payments/${modal.cancelPayment.id}/cancel`)} />}
    </>
  )
}
