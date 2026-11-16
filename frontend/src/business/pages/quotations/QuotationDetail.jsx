import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { FiArrowLeft, FiEdit2, FiSend, FiCheck, FiX, FiCopy, FiSlash, FiBriefcase } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { InfoGrid, Money, Status } from '../../components/ui'
import DocActions from '../../components/DocActions'
import { money } from '../../lib'

export function ItemsTable({ items }) {
  let current = null
  return (
    <div className="a-table-wrap">
      <table className="a-table b-items-table">
        <thead><tr><th>#</th><th>Description</th><th>Unit</th><th className="r">Qty</th><th className="r">Rate</th><th className="r">Amount</th></tr></thead>
        <tbody>
          {items.map((it, i) => {
            const head = it.section && it.section !== current
            current = it.section
            return [
              head && <tr key={`s${i}`} className="b-items-section"><td colSpan={6}>{it.section}</td></tr>,
              <tr key={i}><td>{i + 1}</td><td className="b-pre">{it.description}</td><td>{it.unit}</td><td className="r">{Number(it.qty)}</td><td className="r">{money(it.rate)}</td><td className="r">{money(it.amount)}</td></tr>,
            ]
          })}
        </tbody>
      </table>
    </div>
  )
}

export function TotalsView({ doc, extra }) {
  return (
    <div className="b-totals is-view">
      <div className="b-totals-row"><span>Subtotal</span><b>{money(doc.subtotal)}</b></div>
      {Number(doc.discount) > 0 && <div className="b-totals-row"><span>Discount{doc.discount_type === 'percent' ? ` (${Number(doc.discount_value)}%)` : ''}</span><b>- {money(doc.discount)}</b></div>}
      {Number(doc.tax) > 0 && <div className="b-totals-row"><span>{doc.tax_name} ({Number(doc.tax_rate)}%)</span><b>{money(doc.tax)}</b></div>}
      <div className="b-totals-row is-total"><span>Total (BDT)</span><b>{money(doc.total)}</b></div>
      {extra}
    </div>
  )
}

export default function QuotationDetail() {
  const { id } = useParams()
  const navigate = useNavigate()
  const { toast, can } = useAdmin()
  const [data, setData] = useState(null)
  const [busy, setBusy] = useState(false)

  const load = useCallback(() => {
    businessApi.get(`/quotations/${id}`).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [id, toast])
  useEffect(() => { load() }, [load])

  if (!data) return <Loader />
  const q = data.data
  const manage = can('quotations.manage')

  const setStatus = async (status, confirmText) => {
    if (confirmText && !window.confirm(confirmText)) return
    setBusy(true)
    try { const r = await businessApi.post(`/quotations/${id}/status`, { status }); toast(r.data.message); load() } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }
  const revise = async () => {
    setBusy(true)
    try { const r = await businessApi.post(`/quotations/${id}/revise`); toast(r.data.message); navigate(`/admin/biz/quotations/${r.data.data.id}/edit`) } catch (e) { toast(errorMessage(e), 'error') } finally { setBusy(false) }
  }

  return (
    <>
      <div className="a-page-head">
        <h1>{q.number} <Status value={q.status} /></h1>
        <div className="b-head-actions">
          <Link to="/admin/biz/quotations" className="a-btn a-btn-light"><FiArrowLeft /> Quotations</Link>
          {manage && q.status === 'draft' && <Link to={`/admin/biz/quotations/${id}/edit`} className="a-btn a-btn-light"><FiEdit2 /> Edit</Link>}
        </div>
      </div>

      <div className="a-panel b-pad b-actionbar">
        <DocActions base={`/quotations/${id}`} email={q.customer?.email} phone={q.customer?.phone} shareUrl={data.share_url}
          shareText={`Quotation ${q.number} (${money(q.total, { symbol: true })}) from us. View PDF:`} canEmail={manage} onEmailed={load} />
        {manage && (
          <div className="b-doc-actions">
            {q.status === 'draft' && <button className="a-btn a-btn-light" disabled={busy} onClick={() => setStatus('sent')}><FiSend /> Mark Sent</button>}
            {['draft', 'sent'].includes(q.status) && <button className="a-btn a-btn-primary" disabled={busy} onClick={() => setStatus('approved', 'Mark this quotation as approved by the customer?')}><FiCheck /> Approved</button>}
            {q.status === 'sent' && <button className="a-btn a-btn-light" disabled={busy} onClick={() => setStatus('rejected')}><FiX /> Rejected</button>}
            {['sent', 'rejected'].includes(q.status) && <button className="a-btn a-btn-light" disabled={busy} onClick={revise}><FiCopy /> Revise</button>}
            {['draft', 'sent', 'rejected', 'approved'].includes(q.status) && <button className="a-btn a-btn-light danger" disabled={busy} onClick={() => setStatus('cancelled', 'Cancel this quotation?')}><FiSlash /> Cancel</button>}
          </div>
        )}
      </div>

      {q.status === 'approved' && (
        <div className="b-callout">
          {data.projects?.length ? (
            <>Project created: {data.projects.map((p) => <Link key={p.id} to={`/admin/biz/projects/${p.id}`}><b>{p.code}</b> {p.title}</Link>)}</>
          ) : (
            <>
              <span>This quotation is approved. Start the work as a project.</span>
              <Can perm="projects.manage"><Link className="a-btn a-btn-primary" to={`/admin/biz/projects/new?quotation_id=${id}`}><FiBriefcase /> Create Project</Link></Can>
            </>
          )}
        </div>
      )}

      <div className="b-detail">
        <div className="b-detail-main">
          <div className="a-panel">
            <div className="a-panel-head"><h3>{q.title || 'Items'}</h3></div>
            <ItemsTable items={q.items} />
            <div className="b-pad b-totals-wrap"><TotalsView doc={q} /></div>
          </div>
          {(q.terms || q.notes) && (
            <div className="a-panel b-pad">
              {q.notes && <><h4 className="b-mini-title">Note</h4><p className="b-pre">{q.notes}</p></>}
              {q.terms && <><h4 className="b-mini-title">Terms & Conditions</h4><p className="b-pre">{q.terms}</p></>}
            </div>
          )}
        </div>
        <aside className="b-detail-side">
          <div className="a-panel b-pad">
            <InfoGrid items={[
              ['Customer', <Link to={`/admin/biz/customers/${q.customer_id}`}>{q.customer?.name}</Link>],
              ['Phone', q.customer?.phone], ['Date', formatDate(q.date)], ['Valid until', formatDate(q.valid_until)],
              ['Total', <Money value={q.total} symbol />], ['Lead', q.lead ? <Link to={`/admin/biz/leads/${q.lead.id}`}>{q.lead.name}</Link> : null],
              ['Branch', q.branch?.name], ['Prepared by', q.creator?.name],
              ['Sent', q.sent_at ? formatDate(q.sent_at) : null], ['Approved', q.approved_at ? formatDate(q.approved_at) : null],
            ]} />
          </div>
          {data.versions.length > 1 && (
            <div className="a-panel b-pad">
              <h3 className="b-panel-title">Versions</h3>
              <ul className="b-versions">
                {data.versions.map((v) => (
                  <li key={v.id} className={String(v.id) === String(id) ? 'is-current' : ''}>
                    <Link to={`/admin/biz/quotations/${v.id}`}>{v.number}</Link><Status value={v.status} /><Money value={v.total} />
                  </li>
                ))}
              </ul>
            </div>
          )}
        </aside>
      </div>
    </>
  )
}
