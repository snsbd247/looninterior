import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { FiPlus, FiSlash, FiArrowLeft, FiDollarSign, FiEdit2, FiPhone } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Field, Modal, Money, Stat, Status } from '../../components/ui'
import { label, money, phoneHref, today, useList } from '../../lib'

function useContractors() {
  const [list, setList] = useState([])
  useEffect(() => { businessApi.get('/contractors').then((r) => setList(r.data.data)).catch(() => {}) }, [])
  return list
}

export function LabourEntries() {
  const { toast, can } = useAdmin()
  const [params] = useSearchParams()
  const contractors = useContractors()
  const [projects, setProjects] = useState([])
  const list = useList('/labour', { work_project_id: params.get('project') || '', contractor_id: '', status: 'active' }, { toast })
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  useEffect(() => { businessApi.get('/projects', { params: { all: 1 } }).then((r) => setProjects(r.data.data)).catch(() => {}) }, [])
  const save = async () => {
    try { const r = await businessApi.post('/labour', { ...form, contractor_id: form.contractor_id || null }); toast(r.data.message); setForm(null); list.reload() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  const cancel = async (e) => { const reason = window.prompt('Why cancel this labour entry?'); if (!reason) return; try { await businessApi.post(`/labour/${e.id}/cancel`, { reason }); list.reload() } catch (x) { toast(errorMessage(x), 'error') } }
  const pickContractor = (id) => { const c = contractors.find((x) => String(x.id) === String(id)); setForm({ ...form, contractor_id: id, rate: c && !Number(form.rate) ? Number(c.daily_rate) : form.rate }) }
  return (
    <>
      <div className="a-page-head"><h1>Labour Work</h1><Can perm="labour.manage"><button className="a-btn a-btn-primary" onClick={() => { setErrors({}); setForm({ work_project_id: params.get('project') || '', contractor_id: '', date: today(), description: '', workers: 1, days: 1, rate: 0 }) }}><FiPlus /> Record labour</button></Can></div>
      {list.extra?.amount_total !== undefined && <div className="b-stats"><Stat label="Labour cost (filtered)" value={money(list.extra.amount_total, { symbol: true })} /></div>}
      <div className="a-panel">
        <div className="a-toolbar">
          <select value={list.filters.work_project_id} onChange={(e) => list.setFilter('work_project_id', e.target.value)}><option value="">All projects</option>{projects.map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select>
          <select value={list.filters.contractor_id} onChange={(e) => list.setFilter('contractor_id', e.target.value)}><option value="">All contractors</option>{contractors.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}</select>
          <select value={list.filters.status} onChange={(e) => list.setFilter('status', e.target.value)}><option value="active">Active</option><option value="cancelled">Cancelled</option></select>
        </div>
        {list.rows === null ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Date</th><th>Work</th><th>Project</th><th>Contractor</th><th className="r">Workers × days × rate</th><th className="r">Amount</th><th /></tr></thead>
            <tbody>{list.rows.length ? list.rows.map((e) => (
              <tr key={e.id} className={e.status === 'cancelled' ? 'b-done-row' : ''}>
                <td className="b-nowrap">{formatDate(e.date)}</td><td><b>{e.description}</b><div className="a-muted b-sub">{e.number}</div></td>
                <td><Link to={`/admin/biz/projects/${e.work_project_id}?tab=costs`}>{e.project?.code}</Link></td><td>{e.contractor?.name || '—'}</td>
                <td className="r b-nowrap">{e.workers} × {Number(e.days)} × {money(e.rate)}</td><td className="r"><Money value={e.amount} /></td>
                <td className="a-actions">{can('labour.manage') && e.status === 'active' && <button className="a-icon-btn danger" title="Cancel" onClick={() => cancel(e)}><FiSlash /></button>}</td>
              </tr>
            )) : <tr><td colSpan={7} className="a-empty">No labour recorded</td></tr>}</tbody>
          </table></div>
        )}
        <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
      </div>
      {form && (
        <Modal title="Record labour work" onClose={() => setForm(null)} footer={<><button className="a-btn a-btn-light" onClick={() => setForm(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}>Save · {money(form.workers * form.days * form.rate)}</button></>}>
          <Field label="Project *" error={errors.work_project_id}><select value={form.work_project_id} onChange={(e) => setForm({ ...form, work_project_id: e.target.value })}><option value="">—</option>{projects.filter((p) => !['completed', 'cancelled'].includes(p.status) || String(p.id) === String(form.work_project_id)).map((p) => <option key={p.id} value={p.id}>{p.code} · {p.title}</option>)}</select></Field>
          <Field label="Contractor / group" help="Empty = paid on the spot (record the payment as an expense)"><select value={form.contractor_id} onChange={(e) => pickContractor(e.target.value)}><option value="">—</option>{contractors.map((c) => <option key={c.id} value={c.id}>{c.name}{c.trade ? ` (${label(c.trade)})` : ''}</option>)}</select></Field>
          <Field label="Work done *" error={errors.description}><input value={form.description} onChange={(e) => setForm({ ...form, description: e.target.value })} placeholder="e.g. Wardrobe carpentry, wall putty" /></Field>
          <div className="b-three">
            <Field label="Workers"><input type="number" min="1" value={form.workers} onChange={(e) => setForm({ ...form, workers: e.target.value })} /></Field>
            <Field label="Days"><input type="number" min="0.5" step="0.5" value={form.days} onChange={(e) => setForm({ ...form, days: e.target.value })} /></Field>
            <Field label="Rate / day (৳)"><input type="number" min="0" value={form.rate} onChange={(e) => setForm({ ...form, rate: e.target.value })} /></Field>
          </div>
          <p className="a-muted b-sub">For a fixed job price, keep workers and days at 1 and enter the price as the rate.</p>
          <Field label="Date *"><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
        </Modal>
      )}
    </>
  )
}

function PayModal({ contractor, onClose, onDone }) {
  const { toast } = useAdmin()
  const [accounts, setAccounts] = useState([])
  const [form, setForm] = useState({ amount: contractor.due > 0 ? contractor.due : '', account_id: '', method: 'cash', reference: '', date: today() })
  const [errors, setErrors] = useState({})
  useEffect(() => { businessApi.get('/lookup/accounts').then((r) => { setAccounts(r.data.data); if (r.data.data[0]) setForm((f) => ({ ...f, account_id: r.data.data[0].id })) }).catch(() => {}) }, [])
  const save = async () => { try { await businessApi.post(`/contractors/${contractor.id}/pay`, form); toast('Payment recorded'); onDone() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') } }
  return (
    <Modal title={`Pay ${contractor.name}`} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}><FiDollarSign /> Pay</button></>}>
      <Field label="Amount (৳) *" error={errors.amount}><input type="number" min="0.01" value={form.amount} onChange={(e) => setForm({ ...form, amount: e.target.value })} /></Field>
      <Field label="From account *" error={errors.account_id}><select value={form.account_id} onChange={(e) => setForm({ ...form, account_id: e.target.value })}>{accounts.map((a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}</option>)}</select></Field>
      <Field label="Method"><select value={form.method} onChange={(e) => setForm({ ...form, method: e.target.value })}>{['cash', 'bank', 'bkash', 'nagad', 'cheque'].map((m) => <option key={m} value={m}>{label(m)}</option>)}</select></Field>
      <Field label="Date *"><input type="date" max={today()} value={form.date} onChange={(e) => setForm({ ...form, date: e.target.value })} /></Field>
    </Modal>
  )
}

export function Contractors() {
  const { toast } = useAdmin()
  const navigate = useNavigate()
  const [data, setData] = useState(null)
  useEffect(() => { businessApi.get('/contractors').then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  if (!data) return <Loader />
  return (
    <>
      <div className="a-page-head"><h1>Contractors</h1><Can perm="labour.manage"><Link className="a-btn a-btn-primary" to="/admin/r/contractors/new"><FiPlus /> Contractor</Link></Can></div>
      <div className="b-stats"><Stat label="Total payable to contractors" value={money(data.total_due, { symbol: true })} tone={data.total_due > 0 ? 'warn' : ''} /></div>
      <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
        <thead><tr><th>Contractor</th><th>Trade</th><th>Phone</th><th className="r">Daily rate</th><th className="r">Work done</th><th className="r">Paid</th><th className="r">Due</th></tr></thead>
        <tbody>{data.data.length ? data.data.map((c) => (
          <tr key={c.id} className="b-clickable" onClick={() => navigate(`/admin/biz/contractors/${c.id}`)}><td><b>{c.name}</b></td><td>{c.trade ? label(c.trade) : '—'}</td><td>{c.phone}</td><td className="r">{money(c.daily_rate)}</td><td className="r">{money(c.work)}</td><td className="r">{money(c.paid)}</td><td className="r"><Money value={c.due} /></td></tr>
        )) : <tr><td colSpan={7} className="a-empty">No contractors yet</td></tr>}</tbody>
      </table></div></div>
    </>
  )
}

export function ContractorDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [d, setD] = useState(null)
  const [pay, setPay] = useState(false)
  const load = useCallback(() => { businessApi.get(`/contractors/${id}`).then((r) => setD(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  if (!d) return <Loader />
  const c = d.data
  const cancelPay = async (l) => { const reason = window.prompt('Why cancel this payment?'); if (!reason) return; try { await businessApi.post(`/contractor-payments/${l.id}/cancel`, { reason }); load() } catch (e) { toast(errorMessage(e), 'error') } }
  return (
    <>
      <div className="a-page-head"><h1>{c.name} <span className="a-muted b-sub">{c.trade ? label(c.trade) : ''}</span></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/contractors" className="a-btn a-btn-light"><FiArrowLeft /> Contractors</Link>
          {c.phone && <a className="a-btn a-btn-light" href={phoneHref(c.phone)}><FiPhone /> Call</a>}
          <Can perm="labour.manage"><Link to={`/admin/r/contractors/${id}`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link></Can>
          {can('labour.pay') && <button className="a-btn a-btn-primary" onClick={() => setPay(true)}><FiDollarSign /> Pay</button>}
        </div>
      </div>
      <div className="b-stats"><Stat label="Due" value={money(d.due, { symbol: true })} tone={d.due > 0 ? 'warn' : 'good'} /><Stat label="Daily rate" value={money(c.daily_rate)} /></div>
      <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
        <thead><tr><th>Date</th><th>Document</th><th>Detail</th><th className="r">Work</th><th className="r">Paid</th><th className="r">Balance</th><th /></tr></thead>
        <tbody><tr className="b-items-section"><td colSpan={5}>Opening due</td><td className="r">{money(c.opening_due)}</td><td /></tr>
          {d.lines.map((l) => (
            <tr key={l.kind + l.id} className={l.status === 'cancelled' ? 'b-done-row' : ''}><td className="b-nowrap">{formatDate(l.date)}</td><td>{l.number}</td><td className="b-sub">{l.detail} {l.status === 'cancelled' && <Status value="cancelled" />}</td>
              <td className="r">{l.debit ? money(l.debit) : ''}</td><td className="r b-in">{l.credit ? money(l.credit) : ''}</td><td className="r"><b>{money(l.balance)}</b></td>
              <td className="a-actions">{l.kind === 'payment' && l.status === 'paid' && can('labour.pay') && <button className="a-icon-btn danger" onClick={() => cancelPay(l)}><FiSlash /></button>}</td></tr>
          ))}</tbody>
      </table></div></div>
      {pay && <PayModal contractor={{ ...c, due: d.due }} onClose={() => setPay(false)} onDone={() => { setPay(false); load() }} />}
    </>
  )
}
