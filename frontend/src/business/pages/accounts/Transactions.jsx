import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { FiPlus, FiMinus, FiRepeat, FiSearch, FiSlash, FiPaperclip, FiDownload } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Field, Modal, Money, Stat, Status } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { label, money, today, useList } from '../../lib'

function useAccounts() {
  const [list, setList] = useState([])
  useEffect(() => { businessApi.get('/lookup/accounts').then((r) => setList(r.data.data)).catch(() => {}) }, [])
  return list
}
function useCategories() {
  const [list, setList] = useState([])
  useEffect(() => { businessApi.get('/lookup/categories').then((r) => setList(r.data.data)).catch(() => {}) }, [])
  return list
}

export function EntryModal({ type, projectId, onClose, onDone }) {
  const { toast } = useAdmin()
  const accounts = useAccounts()
  const categories = useCategories().filter((c) => c.type === type && !['customer_payment', 'supplier_payment'].includes(c.slug))
  const [projects, setProjects] = useState([])
  const [form, setForm] = useState({ account_id: '', category_id: '', amount: '', date: today(), work_project_id: projectId || '', description: '', reference: '' })
  const [file, setFile] = useState(null)
  const [errors, setErrors] = useState({})
  const [saving, setSaving] = useState(false)
  useEffect(() => { businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data)).catch(() => {}) }, [])
  useEffect(() => { if (!form.account_id && accounts[0]) setForm((f) => ({ ...f, account_id: accounts[0].id })) }, [accounts, form.account_id])
  const save = async () => {
    setSaving(true); setErrors({})
    const fd = new FormData()
    Object.entries({ ...form, type }).forEach(([k, v]) => { if (v !== '' && v !== null) fd.append(k, v) })
    if (file) fd.append('attachment', file)
    try { const r = await businessApi.post('/transactions', fd); toast(r.data.message); onDone() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') } finally { setSaving(false) }
  }
  return (
    <Modal title={type === 'income' ? 'Record income' : 'Record expense'} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={save} disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></>}>
      <Field label="Amount (৳) *" error={errors.amount}><input type="number" min="0.01" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} autoFocus /></Field>
      <Field label="Category *" error={errors.category_id}>
        <select value={form.category_id} onChange={(e) => setForm({ ...form, category_id: e.target.value })}><option value="">— Select —</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
      </Field>
      <Field label={type === 'income' ? 'Into account *' : 'Paid from account *'} error={errors.account_id}>
        <select value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>{accounts.map((a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}{a.branch ? ` (${a.branch.code})` : ''}</option>)}</select>
      </Field>
      <Field label="Date *" error={errors.date}><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
      <Field label="Project (optional)" error={errors.work_project_id} help="Link the cost to a project to see its real profit">
        <select value={form.work_project_id} onChange={(e) => setForm({ ...form, work_project_id: e.target.value })}><option value="">—</option>{projects.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select>
      </Field>
      <Field label="Description" error={errors.description}><input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} /></Field>
      <Field label="Reference / Voucher no." error={errors.reference}><input value={form.reference} onChange={(e) => setForm({ ...form, reference: e.target.value })} /></Field>
      <Field label="Receipt photo / PDF" error={errors.attachment}><input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" onChange={(e) => setFile(e.target.files[0] || null)} /></Field>
    </Modal>
  )
}

export function TransferModal({ accounts: given, onClose, onDone }) {
  const { toast } = useAdmin()
  const loaded = useAccounts()
  const accounts = given || loaded
  const [form, setForm] = useState({ from_account_id: '', to_account_id: '', amount: '', date: today(), note: '', reference: '' })
  const [errors, setErrors] = useState({})
  const save = async () => {
    try { const r = await businessApi.post('/transactions/transfer', form); toast(r.data.message); onDone() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  const opt = (a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}{a.branch ? ` (${a.branch.code})` : ''}</option>
  return (
    <Modal title="Transfer between accounts" onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}><FiRepeat /> Transfer</button></>}>
      <Field label="From *" error={errors.from_account_id}><select value={form.from_account_id} onChange={(e) => setForm({ ...form, from_account_id: e.target.value })}><option value="">—</option>{accounts.map(opt)}</select></Field>
      <Field label="To *" error={errors.to_account_id} help="Choose “All branches” view to transfer to another branch"><select value={form.to_account_id} onChange={(e) => setForm({ ...form, to_account_id: e.target.value })}><option value="">—</option>{accounts.filter((a) => String(a.id) !== String(form.from_account_id)).map(opt)}</select></Field>
      <Field label="Amount (৳) *" error={errors.amount}><input type="number" min="0.01" step="any" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>
      <Field label="Date *" error={errors.date}><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
      <Field label="Note"><input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="e.g. Bank deposit" /></Field>
    </Modal>
  )
}

export default function Transactions() {
  const { toast } = useAdmin()
  const [params, setParams] = useSearchParams()
  const accounts = useAccounts()
  const categories = useCategories()
  const [q, setQ] = useState('')
  const list = useList('/transactions', { q: '', type: '', account_id: '', category_id: '', from: '', to: '', status: 'active' }, { toast })
  const [modal, setModal] = useState(() => (params.get('new') ? { type: params.get('new'), project: params.get('project') } : null))
  const f = list.filters
  const s = list.extra?.summary

  const cancel = async (t) => {
    const reason = window.prompt('Why cancel this entry?')
    if (!reason) return
    try { await businessApi.post(`/transactions/${t.id}/cancel`, { reason }); toast('Entry cancelled'); list.reload() } catch (e) { toast(errorMessage(e), 'error') }
  }
  const openAttachment = async (t) => {
    const win = window.open('', '_blank')
    try { const r = await businessApi.get(`/transactions/${t.id}/attachment`, { responseType: 'blob' }); win.location.href = URL.createObjectURL(r.data) } catch (e) { win?.close(); toast(errorMessage(e), 'error') }
  }
  const closeModal = () => { setModal(null); if (params.get('new')) setParams({}, { replace: true }) }

  return (
    <>
      <div className="a-page-head">
        <h1>Income & Expenses</h1>
        <div className="b-head-actions">
          <button className="a-btn a-btn-light" onClick={() => downloadCsv('/transactions', f, 'transactions.csv', toast)}><FiDownload /> CSV</button>
          <Can perm="accounts.manage">
            <button className="a-btn a-btn-light" onClick={() => setModal({ type: 'transfer' })}><FiRepeat /> Transfer</button>
            <button className="a-btn a-btn-light" onClick={() => setModal({ type: 'income' })}><FiPlus /> Income</button>
            <button className="a-btn a-btn-primary" onClick={() => setModal({ type: 'expense' })}><FiMinus /> Expense</button>
          </Can>
        </div>
      </div>
      {s && <div className="b-stats"><Stat label="Income (filtered)" value={money(s.income, { symbol: true })} tone="good" /><Stat label="Expense (filtered)" value={money(s.expense, { symbol: true })} tone="warn" /><Stat label="Net" value={money(s.income - s.expense, { symbol: true })} tone={s.income - s.expense >= 0 ? 'good' : 'bad'} /></div>}
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}><FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Description, reference…" /></form>
          <select value={f.type} onChange={(e) => list.setFilter('type', e.target.value)}><option value="">All types</option>{['income', 'expense', 'transfer_in', 'transfer_out'].map((t) => <option key={t} value={t}>{label(t)}</option>)}</select>
          <select value={f.account_id} onChange={(e) => list.setFilter('account_id', e.target.value)}><option value="">All accounts</option>{accounts.map((a) => <option key={a.id} value={a.id}>{a.name}</option>)}</select>
          <select value={f.category_id} onChange={(e) => list.setFilter('category_id', e.target.value)}><option value="">All categories</option>{categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
          <input type="date" value={f.from} onChange={(e) => list.setFilter('from', e.target.value)} title="From" />
          <input type="date" value={f.to} onChange={(e) => list.setFilter('to', e.target.value)} title="To" />
          <select value={f.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="active">Active</option><option value="cancelled">Cancelled</option></select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Description</th><th>Account</th><th>Project</th><th className="r">Amount</th><th /></tr></thead>
              <tbody>
                {list.rows.length ? list.rows.map((t) => (
                  <tr key={t.id} className={t.status === 'cancelled' ? 'b-done-row' : ''}>
                    <td className="b-nowrap">{formatDate(t.date)}</td>
                    <td><Status value={t.type === 'income' || t.type === 'transfer_in' ? 'received' : 'cancelled'} text={label(t.type)} /></td>
                    <td>{t.category?.name || '—'}</td>
                    <td>{t.description}{t.reference && <div className="a-muted b-sub">{t.reference}</div>}{t.cancel_reason && <div className="b-overdue b-sub">Cancelled: {t.cancel_reason}</div>}</td>
                    <td>{t.account?.name}{t.branch && <div className="a-muted b-sub">{t.branch.code}</div>}</td>
                    <td>{t.project ? <Link to={`/admin/biz/projects/${t.work_project_id}`}>{t.project.code}</Link> : '—'}</td>
                    <td className={`r ${['income', 'transfer_in'].includes(t.type) ? 'b-in' : 'b-out'}`}><Money value={t.amount} /></td>
                    <td className="a-actions">
                      {t.attachment && <button className="a-icon-btn" title="Receipt" onClick={() => openAttachment(t)}><FiPaperclip /></button>}
                      <Can perm="accounts.manage">{t.status === 'active' && !t.source_type && <button className="a-icon-btn danger" title="Cancel entry" onClick={() => cancel(t)}><FiSlash /></button>}</Can>
                    </td>
                  </tr>
                )) : <tr><td colSpan={8} className="a-empty">No entries</td></tr>}
              </tbody>
            </table>
          </div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
      {modal && ['income', 'expense'].includes(modal.type) && <EntryModal type={modal.type} projectId={modal.project} onClose={closeModal} onDone={() => { closeModal(); list.reload() }} />}
      {modal?.type === 'transfer' && <TransferModal onClose={closeModal} onDone={() => { closeModal(); list.reload() }} />}
    </>
  )
}
