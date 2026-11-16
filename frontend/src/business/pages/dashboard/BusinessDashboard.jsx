import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiTarget, FiFileText, FiDollarSign, FiCreditCard, FiTool, FiBox, FiLifeBuoy, FiAlertTriangle, FiClock, FiPhone } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Status } from '../../components/ui'
import { dateTime, label, money, phoneHref } from '../../lib'
import { Progress } from '../projects/Projects'
import '../../business.css'

function Card({ icon: Ic, title, to, children, tone }) {
  return (
    <div className={`a-panel b-dash-card ${tone ? 'is-' + tone : ''}`}>
      <div className="b-dash-head"><span className="b-dash-icon"><Ic /></span><h3>{title}</h3>{to && <Link to={to}>Open →</Link>}</div>
      {children}
    </div>
  )
}

function Kpis({ items }) {
  return (
    <div className="b-kpis">
      {items.filter(Boolean).map(([k, v, tone, to]) => (
        to ? <Link key={k} to={to} className={`b-kpi ${tone ? 'is-' + tone : ''}`}><strong>{v}</strong><span>{k}</span></Link>
          : <div key={k} className={`b-kpi ${tone ? 'is-' + tone : ''}`}><strong>{v}</strong><span>{k}</span></div>
      ))}
    </div>
  )
}

/** Two-series monthly bars (invoiced vs collected). */
function TrendBars({ rows }) {
  const max = Math.max(1, ...rows.flatMap((r) => [r.invoiced, r.collected]))
  return (
    <div className="b-trend">
      {rows.map((r) => (
        <div key={r.month} className="b-trend-col" title={`${r.month}: invoiced ${money(r.invoiced)}, collected ${money(r.collected)}`}>
          <div className="b-trend-bars">
            <span className="is-out" style={{ height: `${(r.invoiced / max) * 100}%` }} />
            <span className="is-in" style={{ height: `${(r.collected / max) * 100}%` }} />
          </div>
          <small>{r.month}</small>
        </div>
      ))}
      <div className="b-bars-legend"><span><i className="b-bar is-out" />Invoiced</span><span><i className="b-bar is-in" />Collected</span></div>
    </div>
  )
}

export default function BusinessDashboard() {
  const { toast, user, branch } = useAdmin()
  const [d, setD] = useState(null)
  useEffect(() => { businessApi.get('/dashboard').then((r) => setD(r.data)).catch((e) => { toast(errorMessage(e), 'error'); setD({}) }) }, [toast])
  if (!d) return <Loader />

  return (
    <>
      <div className="b-dash-hello">
        <h2>Hello, {user.name.split(' ')[0]}</h2>
        <span className="a-muted">{user.role?.name} · {d.month}{branch === 'all' ? ' · all branches' : ''}</span>
      </div>

      {d.branches && (
        <div className="a-panel">
          <div className="a-panel-head"><h3>Branches this month</h3><Link to="/admin/biz/reports?tab=branches">Report →</Link></div>
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Branch</th><th className="r">New leads</th><th className="r">Conversion</th><th className="r">Sales</th><th className="r">Collected</th><th className="r">Expense</th><th className="r">Running projects</th><th className="r">Open tickets</th></tr></thead>
            <tbody>{d.branches.map((b) => (
              <tr key={b.code}><td><b>{b.name}</b> <span className="a-muted">{b.code}</span></td><td className="r">{b.leads}</td><td className="r">{b.conversion === null ? '—' : `${b.conversion}%`}</td>
                <td className="r">{money(b.sales)}</td><td className="r b-in">{money(b.collected)}</td><td className="r b-out">{money(b.expense)}</td><td className="r">{b.running_projects}</td><td className="r">{b.open_tickets}</td></tr>
            ))}</tbody>
          </table></div>
        </div>
      )}

      <div className="b-dash-grid">
        {d.billing && (
          <Card icon={FiDollarSign} title="Billing" to="/admin/biz/invoices">
            <Kpis items={[
              ['Invoiced this month', money(d.billing.invoiced)],
              ['Collected this month', money(d.billing.collected), 'good', '/admin/biz/payments'],
              ['Outstanding', money(d.billing.outstanding), d.billing.outstanding > 0 ? 'warn' : '', '/admin/biz/receivables'],
              ['Overdue', `${d.billing.overdue_count} · ${money(d.billing.overdue_amount)}`, d.billing.overdue_count ? 'bad' : '', '/admin/biz/invoices?status=overdue'],
            ]} />
            <TrendBars rows={d.billing.trend} />
          </Card>
        )}

        {d.accounts && (
          <Card icon={FiCreditCard} title="Cash & Bank" to="/admin/biz/accounts">
            <Kpis items={[
              ['Balance now', money(d.accounts.cash_bank), 'good'],
              ['Income this month', money(d.accounts.income)],
              ['Expense this month', money(d.accounts.expense), 'warn'],
              ['Net this month', money(d.accounts.income - d.accounts.expense), d.accounts.income - d.accounts.expense >= 0 ? 'good' : 'bad'],
            ]} />
          </Card>
        )}

        {d.crm && (
          <Card icon={FiTarget} title="Leads" to="/admin/biz/leads">
            <Kpis items={[
              ['New this month', d.crm.new_leads],
              ['Open leads', d.crm.open_leads, '', '/admin/biz/leads'],
              ['Pipeline value', money(d.crm.pipeline_value)],
              ['Won / conversion', `${d.crm.won}${d.crm.conversion !== null ? ` · ${d.crm.conversion}%` : ''}`, 'good'],
            ]} />
            <div className="b-stage-chips">{Object.entries(d.crm.by_stage || {}).map(([s, n]) => <span key={s} className="b-chip">{label(s)}: {n}</span>)}</div>
            <h4 className="b-mini-title"><FiClock /> My follow-ups due</h4>
            {d.crm.my_followups.length ? (
              <ul className="b-dash-list">{d.crm.my_followups.map((a) => (
                <li key={a.id}><Link to={`/admin/biz/leads/${a.lead_id}`}>{a.lead?.name}</Link>
                  <span className={new Date(a.follow_up_at) < new Date() ? 'b-overdue' : 'a-muted'}>{dateTime(a.follow_up_at)}</span>
                  {a.lead?.phone && <a href={phoneHref(a.lead.phone)} title="Call"><FiPhone /></a>}</li>
              ))}</ul>
            ) : <p className="a-muted b-sub">Nothing due. 🎉</p>}
          </Card>
        )}

        {d.quotations && (
          <Card icon={FiFileText} title="Quotations" to="/admin/biz/quotations">
            <Kpis items={[
              ['Sent this month', d.quotations.sent],
              ['Waiting for reply', d.quotations.pending, d.quotations.pending ? 'warn' : '', '/admin/biz/quotations?status=sent'],
              ['Approved this month', d.quotations.approved, 'good'],
              ['Approved value', money(d.quotations.approved_value), 'good'],
            ]} />
          </Card>
        )}

        {d.projects && (
          <Card icon={FiTool} title="Projects" to="/admin/biz/projects">
            <Kpis items={[['Running', d.projects.running], ['Planning', d.projects.planning], ['Completed this month', d.projects.completed_month, 'good']]} />
            <ul className="b-dash-list is-projects">{d.projects.list.map((p) => (
              <li key={p.id}><Link to={`/admin/biz/projects/${p.id}`}><b>{p.title}</b><small className="a-muted">{p.code} · {p.customer?.name}</small></Link><Progress value={p.progress} /></li>
            ))}{!d.projects.list.length && <li className="a-muted">No running projects</li>}</ul>
            {d.projects.my_tasks.length > 0 && <>
              <h4 className="b-mini-title">My open tasks</h4>
              <ul className="b-dash-list">{d.projects.my_tasks.map((t) => (
                <li key={t.id}><Link to={`/admin/biz/projects/${t.work_project_id}?tab=work`}>{t.title}</Link><span className="a-muted">{t.project?.code}</span>
                  <span className={t.due_date && new Date(t.due_date) < new Date(new Date().toDateString()) ? 'b-overdue' : 'a-muted'}>{formatDate(t.due_date)}</span></li>
              ))}</ul>
            </>}
          </Card>
        )}

        {d.inventory && (
          <Card icon={FiBox} title="Inventory" to="/admin/biz/stock" tone={d.inventory.low_count ? 'warn' : ''}>
            <Kpis items={[['Stock value', money(d.inventory.stock_value)], ['Low stock items', d.inventory.low_count, d.inventory.low_count ? 'bad' : '', '/admin/biz/stock']]} />
            {d.inventory.low.length > 0 && (
              <ul className="b-dash-list">{d.inventory.low.map((i) => (
                <li key={i.id}><FiAlertTriangle className="b-overdue" /><Link to={`/admin/biz/stock/items/${i.id}`}>{i.name}</Link><span className="b-overdue">{i.qty} / min {i.min} {i.unit}</span></li>
              ))}</ul>
            )}
          </Card>
        )}

        {d.tickets && (
          <Card icon={FiLifeBuoy} title="Service" to="/admin/biz/tickets">
            <Kpis items={[
              ['Open', d.tickets.open, d.tickets.open ? 'bad' : ''], ['In progress', d.tickets.in_progress, 'warn'],
              ['High / urgent', d.tickets.urgent, d.tickets.urgent ? 'bad' : ''], ['Unassigned', d.tickets.unassigned, d.tickets.unassigned ? 'warn' : ''],
            ]} />
            {d.tickets.mine.length > 0 && <>
              <h4 className="b-mini-title">Assigned to me</h4>
              <ul className="b-dash-list">{d.tickets.mine.map((t) => (
                <li key={t.id}><Link to={`/admin/biz/tickets/${t.id}`}>{t.number}</Link><span>{t.subject}</span><Status value={t.priority} />{t.visit_at && <span className="a-muted">{dateTime(t.visit_at)}</span>}</li>
              ))}</ul>
            </>}
          </Card>
        )}
      </div>
    </>
  )
}
