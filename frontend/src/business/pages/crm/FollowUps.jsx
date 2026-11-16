import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiPhone, FiCheckCircle } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { Status } from '../../components/ui'
import { dateTime, label, phoneHref } from '../../lib'

export default function FollowUps() {
  const { toast, can } = useAdmin()
  const [rows, setRows] = useState(null)
  const [scope, setScope] = useState({ all: false, upcoming: false })

  const load = useCallback(() => {
    businessApi.get('/follow-ups', { params: { all: scope.all ? 1 : 0, upcoming: scope.upcoming ? 1 : 0 } })
      .then((r) => setRows(r.data.data)).catch((e) => { toast(errorMessage(e), 'error'); setRows([]) })
  }, [scope, toast])
  useEffect(() => { load() }, [load])

  const done = async (a) => {
    try { await businessApi.post(`/lead-activities/${a.id}/toggle`); toast('Marked done'); load() } catch (e) { toast(errorMessage(e), 'error') }
  }

  const now = new Date()
  const endToday = new Date(); endToday.setHours(23, 59, 59, 999)

  return (
    <>
      <div className="a-page-head"><h1>Follow-ups</h1></div>
      <div className="a-panel">
        <div className="a-toolbar">
          <div className="b-toggle">
            <button type="button" className={!scope.all ? 'is-on' : ''} onClick={() => setScope({ ...scope, all: false })}>Mine</button>
            <button type="button" className={scope.all ? 'is-on' : ''} onClick={() => setScope({ ...scope, all: true })}>Everyone</button>
          </div>
          <div className="b-toggle">
            <button type="button" className={!scope.upcoming ? 'is-on' : ''} onClick={() => setScope({ ...scope, upcoming: false })}>Today &amp; overdue</button>
            <button type="button" className={scope.upcoming ? 'is-on' : ''} onClick={() => setScope({ ...scope, upcoming: true })}>Include upcoming</button>
          </div>
        </div>
        {rows === null ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>When</th><th>Lead</th><th>Stage</th><th>Note</th><th>By</th><th className="a-actions-col">Done</th></tr></thead>
              <tbody>
                {rows.length ? rows.map((a) => {
                  const t = new Date(a.follow_up_at)
                  const cls = t < now ? 'b-overdue' : t <= endToday ? 'b-today' : ''
                  return (
                    <tr key={a.id}>
                      <td className={`b-nowrap ${cls}`}>{dateTime(a.follow_up_at)}{cls === 'b-overdue' && <div className="b-sub">Overdue</div>}</td>
                      <td><Link to={`/admin/biz/leads/${a.lead.id}`}><b>{a.lead.name}</b></Link>{a.lead.phone && <div><a href={phoneHref(a.lead.phone)} className="b-sub"><FiPhone /> {a.lead.phone}</a></div>}</td>
                      <td><Status value={a.lead.stage} /></td>
                      <td className="b-note-cell">{a.note || <span className="a-muted">{label(a.type)}</span>}</td>
                      <td>{a.user?.name || '—'}</td>
                      <td className="a-actions">{can('crm.manage') && <button className="a-icon-btn" title="Mark done" onClick={() => done(a)}><FiCheckCircle /></button>}</td>
                    </tr>
                  )
                }) : <tr><td colSpan={6} className="a-empty">No follow-ups due. 🎉</td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  )
}
