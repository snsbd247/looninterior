import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiDownload, FiPhone } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Money, Stat } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { money, phoneHref } from '../../lib'

const BUCKETS = [['current', 'Not yet due'], ['d30', '1–30 days'], ['d60', '31–60 days'], ['d90', '61–90 days'], ['d90p', '90+ days']]

export default function Receivables() {
  const { toast } = useAdmin()
  const [data, setData] = useState(null)
  const [view, setView] = useState('customer')
  useEffect(() => { businessApi.get('/receivables').then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  if (!data) return <Loader />

  return (
    <>
      <div className="a-page-head">
        <h1>Receivables (Dues)</h1>
        <button className="a-btn a-btn-light" onClick={() => downloadCsv('/receivables', {}, 'receivables.csv', toast)}><FiDownload /> Excel (CSV)</button>
      </div>
      <div className="b-stats">
        <Stat label="Total outstanding" value={money(data.total, { symbol: true })} tone={data.total > 0 ? 'warn' : ''} />
        {BUCKETS.map(([k, t]) => <Stat key={k} label={t} value={money(data.buckets[k])} tone={k === 'current' ? '' : data.buckets[k] > 0 ? 'bad' : ''} />)}
      </div>
      <div className="a-panel">
        <div className="a-toolbar">
          <div className="b-toggle">
            <button type="button" className={view === 'customer' ? 'is-on' : ''} onClick={() => setView('customer')}>By customer</button>
            <button type="button" className={view === 'invoice' ? 'is-on' : ''} onClick={() => setView('invoice')}>By invoice</button>
          </div>
        </div>
        <div className="a-table-wrap">
          {view === 'customer' ? (
            <table className="a-table">
              <thead><tr><th>Customer</th><th>Phone</th><th className="r">Invoices</th><th className="r">Oldest (days overdue)</th><th className="r">Due</th></tr></thead>
              <tbody>
                {data.by_customer.length ? data.by_customer.map((c) => (
                  <tr key={c.customer?.id}>
                    <td><Link to={`/admin/biz/customers/${c.customer?.id}`}><b>{c.customer?.name}</b></Link></td>
                    <td>{c.customer?.phone && <a href={phoneHref(c.customer.phone)}><FiPhone /> {c.customer.phone}</a>}</td>
                    <td className="r">{c.invoices}</td>
                    <td className={`r ${c.oldest_days > 0 ? 'b-overdue' : ''}`}>{c.oldest_days || '—'}</td>
                    <td className="r"><Money value={c.due} /></td>
                  </tr>
                )) : <tr><td colSpan={5} className="a-empty">Nothing outstanding 🎉</td></tr>}
              </tbody>
            </table>
          ) : (
            <table className="a-table">
              <thead><tr><th>Invoice</th><th>Customer</th><th>Date</th><th>Due date</th><th className="r">Days overdue</th><th className="r">Due</th></tr></thead>
              <tbody>
                {data.data.length ? data.data.map((r) => (
                  <tr key={r.id}>
                    <td><Link to={`/admin/biz/invoices/${r.id}`}><b>{r.number}</b></Link></td>
                    <td>{r.customer?.name}</td><td>{formatDate(r.date)}</td><td>{formatDate(r.due_date) || '—'}</td>
                    <td className={`r ${r.days_overdue > 0 ? 'b-overdue' : ''}`}>{r.days_overdue || '—'}</td>
                    <td className="r"><Money value={r.due} /></td>
                  </tr>
                )) : <tr><td colSpan={6} className="a-empty">Nothing outstanding 🎉</td></tr>}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </>
  )
}
