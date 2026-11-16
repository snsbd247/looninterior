import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiPlus, FiEdit2, FiSave, FiCheck, FiX, FiDownload, FiFileText, FiDollarSign, FiArrowLeft, FiRotateCcw } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, DeleteButton, Field, Modal, Money, Stat, Status } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { openPdf } from '../../components/DocActions'
import { label, money, today, useLookup } from '../../lib'

const ATT = ['present', 'absent', 'half_day', 'leave', 'holiday']
const LEAVE_TYPES = ['casual', 'sick', 'annual', 'unpaid']
const thisMonth = () => today().slice(0, 7)

/* ---------------- Employees ---------------- */
export function Employees() {
  const { toast, can } = useAdmin()
  const staff = useLookup('staff')
  const [rows, setRows] = useState(null)
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const load = useCallback(() => { businessApi.get('/employees', { params: { inactive: 1 } }).then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  useEffect(() => { load() }, [load])
  const save = async () => {
    try { await businessApi.post(form.id ? `/employees/${form.id}` : '/employees', { ...form, is_active: form.is_active ? 1 : 0, user_id: form.user_id || null }); toast('Employee saved'); setForm(null); load() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })
  if (!rows) return <Loader />
  const active = rows.filter((r) => r.is_active)
  return (
    <>
      <div className="a-page-head"><h1>Employees</h1><Can perm="hr.manage"><button className="a-btn a-btn-primary" onClick={() => { setErrors({}); setForm({ name: '', designation: '', department: '', phone: '', email: '', basic_salary: '', allowance: 0, join_date: today(), is_active: true }) }}><FiPlus /> Employee</button></Can></div>
      <div className="b-stats"><Stat label="Active employees" value={active.length} /><Stat label="Monthly salary (basic + allowance)" value={money(active.reduce((s, r) => s + Number(r.basic_salary) + Number(r.allowance), 0), { symbol: true })} /></div>
      <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
        <thead><tr><th>Name</th><th>Designation</th><th>Phone</th><th>Joined</th><th className="r">Basic</th><th className="r">Allowance</th><th>Status</th><th /></tr></thead>
        <tbody>{rows.length ? rows.map((r) => (
          <tr key={r.id} className={r.is_active ? '' : 'b-done-row'}>
            <td><Link to={`/admin/biz/employees/${r.id}`}><b>{r.name}</b></Link>{r.code && <div className="a-muted b-sub">{r.code}</div>}</td><td>{r.designation}{r.department && <div className="a-muted b-sub">{r.department}</div>}</td>
            <td>{r.phone}</td><td>{formatDate(r.join_date)}</td><td className="r">{money(r.basic_salary)}</td><td className="r">{money(r.allowance)}</td>
            <td><Status value={r.is_active ? 'active' : 'cancelled'} text={r.is_active ? 'Active' : 'Left'} /></td>
            <td className="a-actions">{can('hr.manage') && <><button className="a-icon-btn" onClick={() => { setErrors({}); setForm({ ...r }) }}><FiEdit2 /></button><DeleteButton icon path={`/employees/${r.id}`} what="employee" onDone={load} /></>}</td>
          </tr>
        )) : <tr><td colSpan={8} className="a-empty">No employees yet</td></tr>}</tbody>
      </table></div></div>
      {form && (
        <Modal title={form.id ? 'Edit employee' : 'New employee'} onClose={() => setForm(null)} wide footer={<><button className="a-btn a-btn-light" onClick={() => setForm(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}><FiSave /> Save</button></>}>
          <div className="a-form-grid">
            {!form.id && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
            <Field label="Name *" error={errors.name}><input value={form.name} onChange={set('name')} /></Field>
            <Field label="Employee code"><input value={form.code || ''} onChange={set('code')} /></Field>
            <Field label="Designation"><input value={form.designation || ''} onChange={set('designation')} /></Field>
            <Field label="Department"><input value={form.department || ''} onChange={set('department')} /></Field>
            <Field label="Phone"><input value={form.phone || ''} onChange={set('phone')} /></Field>
            <Field label="Email" error={errors.email}><input value={form.email || ''} onChange={set('email')} /></Field>
            <Field label="NID"><input value={form.nid || ''} onChange={set('nid')} /></Field>
            <Field label="Join date"><input type="date" value={form.join_date || ''} onChange={set('join_date')} /></Field>
            <Field label="Basic salary (৳) *" error={errors.basic_salary}><input type="number" min="0" value={form.basic_salary} onChange={set('basic_salary')} /></Field>
            <Field label="Allowance (৳)"><input type="number" min="0" value={form.allowance} onChange={set('allowance')} /></Field>
            <Field label="Login user (optional)" help="Link if this employee also uses the admin panel"><select value={form.user_id || ''} onChange={set('user_id')}><option value="">—</option>{staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}</select></Field>
            <Field label="Address" full><input value={form.address || ''} onChange={set('address')} /></Field>
          </div>
          <label className="switch"><input type="checkbox" checked={!!form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /><span className="slider" /><span className="switch-label">Active (on payroll)</span></label>
        </Modal>
      )}
    </>
  )
}

export function EmployeeDetail() {
  const { id } = useParams()
  const { toast } = useAdmin()
  const [month, setMonth] = useState(thisMonth())
  const [d, setD] = useState(null)
  useEffect(() => { businessApi.get(`/employees/${id}`, { params: { month } }).then((r) => setD(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, month, toast])
  if (!d) return <Loader />
  const e = d.data
  const counts = Object.values(d.attendance).reduce((m, s) => ({ ...m, [s]: (m[s] || 0) + 1 }), {})
  return (
    <>
      <div className="a-page-head"><h1>{e.name} <span className="a-muted b-sub">{e.designation}</span></h1><Link to="/admin/biz/employees" className="a-btn a-btn-light"><FiArrowLeft /> Employees</Link></div>
      <div className="b-stats"><Stat label="Basic + allowance" value={money(Number(e.basic_salary) + Number(e.allowance), { symbol: true })} /><Stat label={`Absent (${month})`} value={(counts.absent || 0) + (counts.half_day || 0) / 2} tone={counts.absent ? 'warn' : ''} /><Stat label="Leave days" value={counts.leave || 0} /></div>
      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel b-pad">
            <div className="b-row-head"><h3 className="b-panel-title">Attendance marks</h3><input type="month" value={month} onChange={(x) => setMonth(x.target.value)} className="b-inline-select" /></div>
            <p className="a-muted b-sub">Days without a mark count as present.</p>
            {Object.keys(d.attendance).length ? <div className="b-stage-chips">{Object.entries(d.attendance).map(([dt, s]) => <span key={dt} className={`a-status b-att-${s}`}>{dt.slice(8)} · {label(s)}</span>)}</div> : <div className="a-empty">No marks this month</div>}
          </div>
          <div className="a-panel"><div className="a-panel-head"><h3>Leave</h3></div><div className="a-table-wrap"><table className="a-table"><tbody>
            {d.leaves.length ? d.leaves.map((l) => <tr key={l.id}><td>{label(l.type)}</td><td>{formatDate(l.from_date)} → {formatDate(l.to_date)}</td><td>{Number(l.days)} d</td><td><Status value={l.status === 'approved' ? 'approved' : l.status === 'rejected' ? 'rejected' : 'draft'} text={label(l.status)} /></td></tr>) : <tr><td className="a-empty">No leave</td></tr>}
          </tbody></table></div></div>
        </div>
        <aside className="b-detail-side"><div className="a-panel b-pad">
          <h3 className="b-panel-title">Payslips</h3>
          {d.payslips.length ? d.payslips.map((p) => <div key={p.id} className="b-row-head b-sub"><span>{p.payroll?.month} <Status value={p.payroll?.status} /></span><span><Money value={p.net} /> <button className="a-icon-btn" onClick={() => openPdf(`/payrolls/${p.payroll_id}/items/${p.id}/payslip`, 'en', toast)}><FiFileText /></button></span></div>) : <p className="a-muted b-sub">No salary sheets yet</p>}
        </div></aside>
      </div>
    </>
  )
}

/* ---------------- Attendance ---------------- */
export function Attendance() {
  const { toast, can } = useAdmin()
  const [tab, setTab] = useState('day')
  const [date, setDate] = useState(today())
  const [month, setMonth] = useState(thisMonth())
  const [rows, setRows] = useState(null)
  const [grid, setGrid] = useState(null)
  const [saving, setSaving] = useState(false)
  useEffect(() => {
    if (tab === 'day') businessApi.get('/attendance', { params: { date } }).then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error'))
    else businessApi.get('/attendance/month', { params: { month } }).then((r) => setGrid(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [tab, date, month, toast])
  const save = async () => {
    setSaving(true)
    try { await businessApi.post('/attendance', { date, rows: rows.map((r) => ({ employee_id: r.employee_id, status: r.status, note: r.note })) }); toast('Attendance saved') } catch (e) { toast(errorMessage(e), 'error') } finally { setSaving(false) }
  }
  return (
    <>
      <div className="a-page-head"><h1>Attendance</h1>
        <div className="b-toggle"><button className={tab === 'day' ? 'is-on' : ''} onClick={() => setTab('day')}>Mark a day</button><button className={tab === 'month' ? 'is-on' : ''} onClick={() => setTab('month')}>Month view</button></div>
      </div>
      {tab === 'day' ? (
        <div className="a-panel">
          <div className="a-toolbar"><label className="b-inline-field">Date <input type="date" max={today()} value={date} onChange={(e) => setDate(e.target.value)} /></label><span className="a-muted b-sub">Everyone is present by default. Change only absences, half days, leave or holidays.</span></div>
          {!rows ? <Loader /> : (
            <div className="a-table-wrap"><table className="a-table">
              <thead><tr><th>Employee</th><th>Status</th><th>Note</th></tr></thead>
              <tbody>{rows.length ? rows.map((r, i) => (
                <tr key={r.employee_id}><td><b>{r.name}</b><div className="a-muted b-sub">{r.designation}</div></td>
                  <td><div className="b-att-pick">{ATT.map((s) => <button key={s} type="button" disabled={!can('hr.manage')} className={`${r.status === s ? 'is-on' : ''} b-att-${s}`} onClick={() => setRows(rows.map((x, j) => (j === i ? { ...x, status: s } : x)))}>{label(s)}</button>)}</div></td>
                  <td><input className="b-inline-select" value={r.note || ''} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, note: e.target.value } : x)))} disabled={!can('hr.manage')} /></td></tr>
              )) : <tr><td colSpan={3} className="a-empty">No active employees</td></tr>}</tbody>
            </table></div>
          )}
          <Can perm="hr.manage"><div className="b-pad"><button className="a-btn a-btn-primary" onClick={save} disabled={saving || !rows?.length}><FiSave /> {saving ? 'Saving…' : 'Save attendance'}</button></div></Can>
        </div>
      ) : (
        <div className="a-panel">
          <div className="a-toolbar"><label className="b-inline-field">Month <input type="month" value={month} onChange={(e) => setMonth(e.target.value)} /></label></div>
          {!grid ? <Loader /> : (
            <div className="a-table-wrap"><table className="a-table b-att-grid">
              <thead><tr><th>Employee</th>{Array.from({ length: grid.days }, (_, i) => <th key={i}>{i + 1}</th>)}<th className="r">Absent</th><th className="r">Leave</th></tr></thead>
              <tbody>{grid.data.map((r) => (
                <tr key={r.employee_id}><td className="b-nowrap"><b>{r.name}</b></td>
                  {Array.from({ length: grid.days }, (_, i) => { const s = r.marks[i + 1]; return <td key={i} className={s ? `b-att-${s}` : ''} title={s ? label(s) : 'Present'}>{s ? { absent: 'A', half_day: '½', leave: 'L', holiday: 'H', present: 'P' }[s] : ''}</td> })}
                  <td className="r">{r.absent}</td><td className="r">{r.leave}</td></tr>
              ))}</tbody>
            </table></div>
          )}
        </div>
      )}
    </>
  )
}

/* ---------------- Leave ---------------- */
export function Leaves() {
  const { toast, can } = useAdmin()
  const [rows, setRows] = useState(null)
  const [status, setStatus] = useState('pending')
  const [employees, setEmployees] = useState([])
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const load = useCallback(() => { businessApi.get('/leaves', { params: { status } }).then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [status, toast])
  useEffect(() => { load() }, [load])
  useEffect(() => { businessApi.get('/employees').then((r) => setEmployees(r.data.data)).catch(() => {}) }, [])
  const decide = async (l, s) => { try { await businessApi.post(`/leaves/${l.id}/decide`, { status: s }); toast(`Leave ${s}`); load() } catch (e) { toast(errorMessage(e), 'error') } }
  const save = async () => { try { await businessApi.post('/leaves', form); toast('Leave request saved'); setForm(null); load() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') } }
  return (
    <>
      <div className="a-page-head"><h1>Leave</h1><Can perm="hr.manage"><button className="a-btn a-btn-primary" onClick={() => { setErrors({}); setForm({ employee_id: '', type: 'casual', from_date: today(), to_date: today(), reason: '' }) }}><FiPlus /> Leave request</button></Can></div>
      <div className="a-panel">
        <div className="a-toolbar"><div className="b-toggle">{['pending', 'approved', 'rejected', ''].map((s) => <button key={s} className={status === s ? 'is-on' : ''} onClick={() => setStatus(s)}>{s ? label(s) : 'All'}</button>)}</div></div>
        {!rows ? <Loader /> : (
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th className="r">Days</th><th>Reason</th><th>Status</th><th /></tr></thead>
            <tbody>{rows.length ? rows.map((l) => (
              <tr key={l.id}><td><b>{l.employee?.name}</b></td><td>{label(l.type)}</td><td className="b-nowrap">{formatDate(l.from_date)} → {formatDate(l.to_date)}</td><td className="r">{Number(l.days)}</td><td>{l.reason}</td>
                <td><Status value={l.status === 'pending' ? 'draft' : l.status} text={label(l.status)} /></td>
                <td className="a-actions">{can('hr.manage') && l.status === 'pending' && <><button className="a-icon-btn" title="Approve" onClick={() => decide(l, 'approved')}><FiCheck /></button><button className="a-icon-btn danger" title="Reject" onClick={() => decide(l, 'rejected')}><FiX /></button><DeleteButton icon path={`/leaves/${l.id}`} what="leave request" onDone={load} /></>}</td></tr>
            )) : <tr><td colSpan={7} className="a-empty">No leave requests</td></tr>}</tbody>
          </table></div>
        )}
      </div>
      {form && (
        <Modal title="Leave request" onClose={() => setForm(null)} footer={<><button className="a-btn a-btn-light" onClick={() => setForm(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}>Save</button></>}>
          <Field label="Employee *" error={errors.employee_id}><select value={form.employee_id} onChange={(e) => setForm({ ...form, employee_id: e.target.value })}><option value="">—</option>{employees.map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}</select></Field>
          <Field label="Type" help="Unpaid leave is deducted from salary"><select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}>{LEAVE_TYPES.map((t) => <option key={t} value={t}>{label(t)}</option>)}</select></Field>
          <Field label="From *"><input type="date" value={form.from_date} onChange={(e) => setForm({ ...form, from_date: e.target.value })} /></Field>
          <Field label="To *" error={errors.to_date}><input type="date" value={form.to_date} onChange={(e) => setForm({ ...form, to_date: e.target.value })} /></Field>
          <Field label="Reason"><input value={form.reason} onChange={(e) => setForm({ ...form, reason: e.target.value })} /></Field>
        </Modal>
      )}
    </>
  )
}

/* ---------------- Payroll ---------------- */
export function Payrolls() {
  const { toast, branch } = useAdmin()
  const [rows, setRows] = useState(null)
  const [month, setMonth] = useState(thisMonth())
  const [busy, setBusy] = useState(false)
  const load = useCallback(() => { businessApi.get('/payrolls').then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  useEffect(() => { load() }, [load])
  const generate = async () => {
    setBusy(true)
    try { const r = await businessApi.post('/payrolls/generate', { month }); toast(r.data.message); load() } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  return (
    <>
      <div className="a-page-head"><h1>Salary Sheets</h1>
        <Can perm="hr.manage"><div className="b-head-actions">
          <input type="month" value={month} onChange={(e) => setMonth(e.target.value)} className="b-inline-select" />
          <button className="a-btn a-btn-primary" onClick={generate} disabled={busy || branch === 'all'} title={branch === 'all' ? 'Choose a branch first' : ''}><FiPlus /> {busy ? 'Preparing…' : 'Prepare salary sheet'}</button>
        </div></Can>
      </div>
      <div className="a-panel">{!rows ? <Loader /> : (
        <div className="a-table-wrap"><table className="a-table">
          <thead><tr><th>Month</th><th>Sheet</th><th className="r">Employees</th><th>Status</th><th>Paid from</th><th className="r">Total</th></tr></thead>
          <tbody>{rows.length ? rows.map((p) => (
            <tr key={p.id}><td><Link to={`/admin/biz/payrolls/${p.id}`}><b>{p.month}</b></Link></td><td>{p.number}{p.branch && <span className="a-muted"> · {p.branch.code}</span>}</td><td className="r">{p.items_count}</td>
              <td><Status value={p.status === 'finalized' ? 'issued' : p.status} text={label(p.status)} /></td><td>{p.account?.name || '—'}{p.paid_date && <div className="a-muted b-sub">{formatDate(p.paid_date)}</div>}</td><td className="r"><Money value={p.total} /></td></tr>
          )) : <tr><td colSpan={6} className="a-empty">No salary sheets yet. Choose a month and click “Prepare salary sheet”.</td></tr>}</tbody>
        </table></div>
      )}</div>
    </>
  )
}

export function PayrollDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [p, setP] = useState(null)
  const [pay, setPay] = useState(null)
  const navigate = useNavigate()
  const [accounts, setAccounts] = useState([])
  const load = useCallback(() => { businessApi.get(`/payrolls/${id}`).then((r) => setP(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  useEffect(() => { load() }, [load])
  useEffect(() => { if (can('hr.pay')) businessApi.get('/lookup/accounts').then((r) => setAccounts(r.data.data)).catch(() => {}) }, [can])
  if (!p) return <Loader />
  const draft = p.status === 'draft' && can('hr.manage')
  const saveItem = async (it, patch) => { try { await businessApi.post(`/payrolls/${id}/items/${it.id}`, { bonus: it.bonus, advance: it.advance, note: it.note, ...patch }); load() } catch (e) { toast(errorMessage(e), 'error') } }
  const act = async (path, body, msg) => { try { await businessApi.post(`/payrolls/${id}/${path}`, body); toast(msg); setPay(null); load() } catch (e) { toast(errorMessage(e), 'error') } }
  return (
    <>
      <div className="a-page-head">
        <h1>Salary {p.month} <Status value={p.status === 'finalized' ? 'issued' : p.status} text={label(p.status)} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/payrolls" className="a-btn a-btn-light"><FiArrowLeft /> Sheets</Link>
          <button className="a-btn a-btn-light" onClick={() => downloadCsv(`/payrolls/${id}/csv`, {}, `salary-${p.month}.csv`, toast)}><FiDownload /> CSV</button>
          {draft && <DeleteButton path={`/payrolls/${id}`} what="draft salary sheet" onDone={() => navigate('/admin/biz/payrolls')} />}
          {draft && <button className="a-btn a-btn-primary" onClick={() => act('finalize', {}, 'Finalized')}><FiCheck /> Finalize</button>}
          {p.status === 'finalized' && can('hr.pay') && <button className="a-btn a-btn-primary" onClick={() => setPay({ account_id: accounts.find((a) => a.branch_id === p.branch_id)?.id || '', date: today() })}><FiDollarSign /> Pay salaries</button>}
          {p.status === 'paid' && can('hr.pay') && <button className="a-btn a-btn-light danger" onClick={() => { const reason = window.prompt('Why cancel the salary payment?'); if (reason) act('unpay', { reason }, 'Payment cancelled') }}><FiRotateCcw /> Cancel payment</button>}
        </div>
      </div>
      <div className="b-stats"><Stat label="Working days" value={p.working_days} /><Stat label="Employees" value={p.items.length} /><Stat label="Net payable" value={money(p.total, { symbol: true })} tone="good" sub={p.account ? `Paid from ${p.account.name} on ${formatDate(p.paid_date)}` : ''} /></div>
      {draft && <div className="b-callout is-warn">Draft: add bonus or salary advance below, then Finalize. “Prepare salary sheet” again rebuilds it from the latest attendance.</div>}
      <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
        <thead><tr><th>Employee</th><th className="r">Basic + allow.</th><th className="r">Absent</th><th className="r">Unpaid leave</th><th className="r">Deduction</th><th className="r">Bonus</th><th className="r">Advance</th><th className="r">Net pay</th><th /></tr></thead>
        <tbody>{p.items.map((it) => (
          <tr key={it.id}><td><b>{it.employee?.name}</b><div className="a-muted b-sub">{it.employee?.designation}</div></td>
            <td className="r">{money(Number(it.basic) + Number(it.allowance))}</td><td className="r">{Number(it.absent_days)}</td><td className="r">{Number(it.unpaid_leave_days)}</td><td className="r b-out">{money(it.deduction)}</td>
            <td className="r">{draft ? <input type="number" min="0" className="b-num-input" defaultValue={Number(it.bonus)} onBlur={(e) => Number(e.target.value) !== Number(it.bonus) && saveItem(it, { bonus: e.target.value })} /> : money(it.bonus)}</td>
            <td className="r">{draft ? <input type="number" min="0" className="b-num-input" defaultValue={Number(it.advance)} onBlur={(e) => Number(e.target.value) !== Number(it.advance) && saveItem(it, { advance: e.target.value })} /> : money(it.advance)}</td>
            <td className="r"><b>{money(it.net)}</b></td>
            <td className="a-actions"><button className="a-icon-btn" title="Payslip PDF" onClick={() => openPdf(`/payrolls/${id}/items/${it.id}/payslip`, 'en', toast)}><FiFileText /></button></td></tr>
        ))}</tbody>
      </table></div></div>
      {pay && (
        <Modal title={`Pay salaries ${p.month} · ${money(p.total, { symbol: true })}`} onClose={() => setPay(null)} footer={<><button className="a-btn a-btn-light" onClick={() => setPay(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={() => act('pay', pay, 'Salaries paid')}><FiDollarSign /> Pay</button></>}>
          <Field label="From account *"><select value={pay.account_id} onChange={(e) => setPay({ ...pay, account_id: e.target.value })}><option value="">—</option>{accounts.filter((a) => a.branch_id === p.branch_id).map((a) => <option key={a.id} value={a.id}>{a.name} · {money(a.balance)}</option>)}</select></Field>
          <Field label="Date *"><input type="date" max={today()} value={pay.date} onChange={(e) => setPay({ ...pay, date: e.target.value })} /></Field>
          <p className="a-muted b-sub">One “Salary” expense of the total is recorded in the account.</p>
        </Modal>
      )}
    </>
  )
}
