import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { FiPlus, FiSearch, FiList, FiColumns, FiPhone, FiClock } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import Pagination from '../../components/Pagination'
import { Status, Money } from '../../components/ui'
import { label, useList, useLookup, phoneHref } from '../../lib'

export const STAGES = ['new', 'contacted', 'site_visit', 'quotation_sent', 'negotiation', 'won', 'lost']
export const SOURCES = ['website', 'phone', 'facebook', 'instagram', 'whatsapp', 'referral', 'walk_in', 'other']

const VIEW_KEY = 'b_leads_view'

function Board({ filters, canMove, toast }) {
  const [data, setData] = useState(null)
  const [drag, setDrag] = useState(null)
  const [over, setOver] = useState(null)

  const load = useCallback(() => {
    businessApi.get('/leads', { params: { ...filters, view: 'board' } })
      .then((r) => setData(r.data.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [filters, toast])
  useEffect(() => { load() }, [load])

  const move = async (lead, stage) => {
    if (!canMove || lead.stage === stage) return
    let lost_reason
    if (stage === 'lost') {
      lost_reason = window.prompt('Why was this lead lost?')
      if (!lost_reason) return
    }
    try {
      await businessApi.post(`/leads/${lead.id}/stage`, { stage, lost_reason })
      toast(stage === 'won' ? 'Marked as won · customer created' : `Moved to ${label(stage)}`)
      load()
    } catch (e) { toast(errorMessage(e), 'error') }
  }

  if (!data) return <Loader />
  const stages = STAGES.filter((s) => s !== 'lost')
  return (
    <div className="b-board">
      {stages.map((s) => (
        <div key={s} className={`b-col ${over === s ? 'is-over' : ''}`}
          onDragOver={(e) => { if (drag) { e.preventDefault(); setOver(s) } }}
          onDragLeave={() => setOver(null)}
          onDrop={() => { if (drag) move(drag, s); setDrag(null); setOver(null) }}>
          <div className="b-col-head"><Status value={s} /><span>{(data[s] || []).length}</span></div>
          <div className="b-col-body">
            {(data[s] || []).map((l) => (
              <div key={l.id} className="b-card" draggable={canMove} onDragStart={() => setDrag(l)} onDragEnd={() => setDrag(null)}>
                <Link to={`/admin/biz/leads/${l.id}`} className="b-card-title">{l.name}</Link>
                <div className="b-card-meta">
                  {l.phone && <a href={phoneHref(l.phone)}><FiPhone /> {l.phone}</a>}
                  {l.service && <span>{l.service}</span>}
                  {Number(l.expected_value) > 0 && <span><Money value={l.expected_value} symbol /></span>}
                </div>
                <div className="b-card-foot">
                  <span className="a-muted">{l.assignee?.name || 'Unassigned'}</span>
                  {l.next_follow_up_at && (
                    <span className={new Date(l.next_follow_up_at) < new Date() ? 'b-overdue' : 'a-muted'}><FiClock /> {formatDate(l.next_follow_up_at)}</span>
                  )}
                </div>
                {canMove && (
                  <select className="b-card-move" value={l.stage} onChange={(e) => move(l, e.target.value)} aria-label="Move to stage">
                    {STAGES.map((x) => <option key={x} value={x}>{label(x)}</option>)}
                  </select>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  )
}

export default function Leads() {
  const { toast, can } = useAdmin()
  const navigate = useNavigate()
  const [view, setView] = useState(() => { try { return localStorage.getItem(VIEW_KEY) || 'list' } catch { return 'list' } })
  const [q, setQ] = useState('')
  const list = useList('/leads', { q: '', stage: '', source: '', assigned_to: '', follow: '' }, { toast })
  const staff = useLookup('staff')

  const switchView = (v) => { setView(v); try { localStorage.setItem(VIEW_KEY, v) } catch { /* ignore */ } }
  const f = list.filters

  return (
    <>
      <div className="a-page-head">
        <h1>Leads</h1>
        <div className="b-head-actions">
          <div className="b-toggle">
            <button type="button" className={view === 'list' ? 'is-on' : ''} onClick={() => switchView('list')}><FiList /> List</button>
            <button type="button" className={view === 'board' ? 'is-on' : ''} onClick={() => switchView('board')}><FiColumns /> Pipeline</button>
          </div>
          <Can perm="crm.manage"><Link to="/admin/biz/leads/new" className="a-btn a-btn-primary"><FiPlus /> Add Lead</Link></Can>
        </div>
      </div>

      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); list.setFilter('q', q) }}>
            <FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search name, phone, email…" />
          </form>
          {view === 'list' && (
            <select value={f.stage} onChange={(e) => list.setFilter('stage', e.target.value)}>
              <option value="">All stages</option>
              {STAGES.map((s) => <option key={s} value={s}>{label(s)}</option>)}
            </select>
          )}
          <select value={f.source} onChange={(e) => list.setFilter('source', e.target.value)}>
            <option value="">All sources</option>
            {SOURCES.map((s) => <option key={s} value={s}>{label(s)}</option>)}
          </select>
          <select value={f.assigned_to} onChange={(e) => list.setFilter('assigned_to', e.target.value)}>
            <option value="">Everyone</option>
            <option value="me">Assigned to me</option>
            {staff.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
          <select value={f.follow} onChange={(e) => list.setFilter('follow', e.target.value)}>
            <option value="">Any follow-up</option>
            <option value="due">Follow-up due</option>
          </select>
        </div>

        {view === 'board' ? <Board filters={{ q: f.q, source: f.source, assigned_to: f.assigned_to, follow: f.follow }} canMove={can('crm.manage')} toast={toast} /> : (
          list.rows === null ? <Loader /> : (
            <>
              <div className="a-table-wrap">
                <table className="a-table">
                  <thead><tr><th>Lead</th><th>Stage</th><th>Source</th><th>Service</th><th className="r">Value</th><th>Assigned</th><th>Follow-up</th></tr></thead>
                  <tbody>
                    {list.rows.length ? list.rows.map((l) => (
                      <tr key={l.id} className="b-clickable" onClick={() => navigate(`/admin/biz/leads/${l.id}`)}>
                        <td><b>{l.name}</b><div className="a-muted b-sub">{l.phone}{l.branch ? ` · ${l.branch.code}` : ''}</div></td>
                        <td><Status value={l.stage} /></td>
                        <td>{label(l.source)}</td>
                        <td>{l.service || '—'}</td>
                        <td className="r">{Number(l.expected_value) > 0 ? <Money value={l.expected_value} /> : '—'}</td>
                        <td>{l.assignee?.name || <span className="a-muted">—</span>}</td>
                        <td>{l.next_follow_up_at ? <span className={new Date(l.next_follow_up_at) < new Date() ? 'b-overdue' : ''}>{formatDate(l.next_follow_up_at)}</span> : '—'}</td>
                      </tr>
                    )) : <tr><td colSpan={7} className="a-empty">No leads found</td></tr>}
                  </tbody>
                </table>
              </div>
              <Pagination meta={list.meta} page={list.page} setPage={list.setPage} />
            </>
          )
        )}
      </div>
    </>
  )
}
