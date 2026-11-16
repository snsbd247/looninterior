import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiEdit2, FiPhone, FiMail, FiFileText, FiPlus } from 'react-icons/fi'
import { FaWhatsapp } from 'react-icons/fa'
import { businessApi, errorMessage, fieldErrors, formatDate, sitePath } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { DeleteButton, InfoGrid, Money, Stat, Status } from '../../components/ui'
import { phoneHref, waHref, money, dateTime } from '../../lib'

/** Client portal access for this customer (perm portal.manage). The customer signs in at /portal with phone or e-mail. */
function PortalAccess({ customer, onChange }) {
  const { toast } = useAdmin()
  const [pw, setPw] = useState(null)
  const [errors, setErrors] = useState({})
  const save = async (enabled, password) => {
    try { const r = await businessApi.post(`/customers/${customer.id}/portal`, { enabled, password: password || null }); toast(r.data.message); setPw(null); setErrors({}); onChange(r.data.data) } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  return (
    <div className="a-panel b-pad">
      <h3 className="b-panel-title">Client portal</h3>
      {customer.portal_enabled ? (
        <p className="b-sub"><Status value="active" text="Enabled" /> <span className="a-muted">{customer.portal_last_login ? `Last sign-in ${dateTime(customer.portal_last_login)}` : 'Not signed in yet'}</span></p>
      ) : <p className="a-muted b-sub">Off. Turn it on so the customer can follow the project, download invoices and receipts, and request service online.</p>}
      {pw !== null ? (
        <div className="b-portal-pw">
          <input type="text" value={pw} onChange={(e) => setPw(e.target.value)} placeholder="Password (min 8 characters)" autoComplete="off" />
          {(errors.password || errors.enabled) && <small className="a-error">{errors.password || errors.enabled}</small>}
          <div className="b-head-actions"><button className="a-btn a-btn-light" onClick={() => setPw(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={() => save(true, pw)}>Save & enable</button></div>
          <p className="a-muted b-sub">Share it with the customer: sign in at {window.location.origin + sitePath('/portal')} with {customer.phone || customer.email}.</p>
        </div>
      ) : (
        <div className="b-head-actions">
          <button className="a-btn a-btn-light" onClick={() => setPw('')}>{customer.portal_enabled ? 'Reset password' : 'Enable portal'}</button>
          {customer.portal_enabled && <button className="a-btn a-btn-light danger" onClick={() => save(false)}>Turn off</button>}
        </div>
      )}
    </div>
  )
}

/* Sections appear only when that module exists and has data (later steps add more). */
const SECTIONS = [
  { key: 'leads', title: 'Leads', perm: 'crm.view', link: (r) => `/admin/biz/leads/${r.id}`, cols: [['Name', (r) => r.name], ['Stage', (r) => <Status value={r.stage} />], ['Assigned', (r) => r.assignee?.name || '—'], ['Date', (r) => formatDate(r.created_at)]] },
  { key: 'quotations', title: 'Quotations', perm: 'quotations.view', link: (r) => `/admin/biz/quotations/${r.id}`, cols: [['No.', (r) => r.number], ['Status', (r) => <Status value={r.status} />], ['Date', (r) => formatDate(r.date)], ['Total', (r) => <Money value={r.total} />, 'r']] },
  { key: 'projects', title: 'Projects', perm: 'projects.view', link: (r) => `/admin/biz/projects/${r.id}`, cols: [['Code', (r) => r.code], ['Title', (r) => r.title], ['Status', (r) => <Status value={r.status} />], ['Contract', (r) => <Money value={r.contract_value} />, 'r']] },
  { key: 'invoices', title: 'Invoices', perm: 'billing.view', link: (r) => `/admin/biz/invoices/${r.id}`, cols: [['No.', (r) => r.number], ['Status', (r) => <Status value={r.status} />], ['Total', (r) => <Money value={r.total} />, 'r'], ['Due', (r) => <Money value={r.due} />, 'r']] },
  { key: 'payments', title: 'Payments', perm: 'billing.view', link: (r) => `/admin/biz/payments/${r.id}`, cols: [['Receipt', (r) => r.number], ['Date', (r) => formatDate(r.date)], ['Method', (r) => r.method], ['Amount', (r) => <Money value={r.amount} />, 'r']] },
  { key: 'tickets', title: 'Service Tickets', perm: 'tickets.view', link: (r) => `/admin/biz/tickets/${r.id}`, cols: [['No.', (r) => r.number], ['Subject', (r) => r.subject], ['Status', (r) => <Status value={r.status} />], ['Date', (r) => formatDate(r.created_at)]] },
]

export default function CustomerDetail() {
  const { id } = useParams()
  const { toast, can } = useAdmin()
  const [c, setC] = useState(null)
  const navigate = useNavigate()

  useEffect(() => {
    businessApi.get(`/customers/${id}`).then((r) => setC(r.data.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])

  if (!c) return <Loader />

  const invoices = c.invoices || []
  const billed = invoices.filter((i) => i.status !== 'cancelled').reduce((s, i) => s + Number(i.total || 0), 0)
  const paid = invoices.filter((i) => i.status !== 'cancelled').reduce((s, i) => s + Number(i.paid || 0), 0)

  return (
    <>
      <div className="a-page-head">
        <h1>{c.name}</h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/customers" className="a-btn a-btn-light"><FiArrowLeft /> Customers</Link>
          <Can perm="crm.manage"><Link to={`/admin/biz/customers/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link><DeleteButton path={`/customers/${id}`} what="customer" onDone={() => navigate('/admin/biz/customers')} /></Can>
          {'quotations' in c && <Can perm="quotations.manage"><Link to={`/admin/biz/quotations/new?customer_id=${id}`} className="a-btn a-btn-primary"><FiPlus /> Quotation</Link></Can>}
        </div>
      </div>

      {'invoices' in c && can('billing.view') && (
        <div className="b-stats">
          <Stat label="Total billed" value={money(billed, { symbol: true })} />
          <Stat label="Received" value={money(paid, { symbol: true })} tone="good" />
          <Stat label="Outstanding" value={money(billed - paid, { symbol: true })} tone={billed - paid > 0 ? 'warn' : ''} />
        </div>
      )}

      <div className="b-detail">
        <div className="b-detail-main">
          {SECTIONS.filter((s) => s.key in c && can(s.perm)).map((s) => (
            <div key={s.key} className="a-panel">
              <div className="a-panel-head"><h3>{s.title}</h3><span className="a-muted">{c[s.key].length}</span></div>
              <div className="a-table-wrap">
                <table className="a-table">
                  <thead><tr>{s.cols.map(([h, , cls]) => <th key={h} className={cls}>{h}</th>)}</tr></thead>
                  <tbody>
                    {c[s.key].length ? c[s.key].map((r) => (
                      <tr key={r.id}>
                        {s.cols.map(([h, get, cls], i) => <td key={h} className={cls}>{i === 0 ? <Link to={s.link(r)}>{get(r)}</Link> : get(r)}</td>)}
                      </tr>
                    )) : <tr><td colSpan={s.cols.length} className="a-empty">None</td></tr>}
                  </tbody>
                </table>
              </div>
            </div>
          ))}
        </div>
        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <div className="b-contact-actions">
              {c.phone && <a className="a-btn a-btn-light" href={phoneHref(c.phone)}><FiPhone /> Call</a>}
              {c.phone && <a className="a-btn a-btn-light" href={waHref(c.phone)} target="_blank" rel="noreferrer"><FaWhatsapp /> WhatsApp</a>}
              {c.email && <a className="a-btn a-btn-light" href={`mailto:${c.email}`}><FiMail /> Email</a>}
            </div>
            <InfoGrid items={[['Phone', c.phone], ['Email', c.email], ['Company', c.company], ['Address', c.address], ['Branch', c.branch?.name], ['Since', formatDate(c.created_at)]]} />
            {c.notes && <><h4 className="b-mini-title"><FiFileText /> Notes</h4><p className="b-pre">{c.notes}</p></>}
          </div>
          {can('portal.manage') && <PortalAccess customer={c} onChange={(d) => setC({ ...c, ...d })} />}
        </aside>
      </div>
    </>
  )
}
