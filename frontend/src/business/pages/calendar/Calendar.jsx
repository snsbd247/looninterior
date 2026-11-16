import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiChevronLeft, FiChevronRight } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'

const TYPES = {
  followup: ['Follow-up', '#2563eb'], visit: ['Site visit', '#b08d57'], task: ['Task', '#7c3aed'], milestone: ['Milestone', '#0d9488'],
  handover: ['Handover', '#16a34a'], invoice: ['Invoice due', '#dc2626'], delivery: ['Delivery', '#ea580c'],
}
const pad = (n) => String(n).padStart(2, '0')
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`

export default function Calendar() {
  const { toast } = useAdmin()
  const [month, setMonth] = useState(() => { const d = new Date(); return new Date(d.getFullYear(), d.getMonth(), 1) })
  const [mine, setMine] = useState(false)
  const [hidden, setHidden] = useState({})
  const [events, setEvents] = useState(null)

  // Grid: Saturday-first weeks (Bangladesh work week)
  const days = useMemo(() => {
    const start = new Date(month)
    start.setDate(1 - ((month.getDay() + 1) % 7))
    return Array.from({ length: 42 }, (_, i) => { const d = new Date(start); d.setDate(start.getDate() + i); return d })
  }, [month])

  const load = useCallback(() => {
    setEvents(null)
    businessApi.get('/calendar', { params: { from: ymd(days[0]), to: ymd(days[41]), mine: mine ? 1 : 0 } })
      .then((r) => setEvents(r.data.data)).catch((e) => { toast(errorMessage(e), 'error'); setEvents([]) })
  }, [days, mine, toast])
  useEffect(() => { load() }, [load])

  const visible = (events || []).filter((e) => !hidden[e.type])
  const byDate = visible.reduce((m, e) => { (m[e.date] ||= []).push(e); return m }, {})
  const today = ymd(new Date())
  const shift = (n) => setMonth(new Date(month.getFullYear(), month.getMonth() + n, 1))
  const agenda = visible.filter((e) => e.date >= ymd(month) && e.date <= ymd(new Date(month.getFullYear(), month.getMonth() + 1, 0)))

  return (
    <>
      <div className="a-page-head">
        <h1>Calendar</h1>
        <div className="b-head-actions">
          <div className="b-toggle">
            <button type="button" className={!mine ? 'is-on' : ''} onClick={() => setMine(false)}>Everything</button>
            <button type="button" className={mine ? 'is-on' : ''} onClick={() => setMine(true)}>Mine</button>
          </div>
        </div>
      </div>
      <div className="a-panel">
        <div className="a-toolbar b-cal-bar">
          <div className="b-cal-nav">
            <button type="button" className="a-icon-btn" onClick={() => shift(-1)} aria-label="Previous month"><FiChevronLeft /></button>
            <b>{month.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })}</b>
            <button type="button" className="a-icon-btn" onClick={() => shift(1)} aria-label="Next month"><FiChevronRight /></button>
            <button type="button" className="a-btn a-btn-light b-btn-sm" onClick={() => { const d = new Date(); setMonth(new Date(d.getFullYear(), d.getMonth(), 1)) }}>Today</button>
          </div>
          <div className="b-cal-legend">
            {Object.entries(TYPES).map(([k, [l, c]]) => (
              <button key={k} type="button" className={hidden[k] ? 'is-off' : ''} onClick={() => setHidden({ ...hidden, [k]: !hidden[k] })}><i style={{ background: c }} />{l}</button>
            ))}
          </div>
        </div>
        {events === null ? <Loader /> : (
          <>
            <div className="b-cal">
              {['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'].map((d) => <div key={d} className="b-cal-dow">{d}</div>)}
              {days.map((d) => {
                const key = ymd(d)
                const list = byDate[key] || []
                return (
                  <div key={key} className={`b-cal-day ${d.getMonth() !== month.getMonth() ? 'is-out' : ''} ${key === today ? 'is-today' : ''}`}>
                    <span className="b-cal-num">{d.getDate()}</span>
                    {list.slice(0, 4).map((e, i) => (
                      <Link key={i} to={e.link} className="b-cal-ev" style={{ borderLeftColor: TYPES[e.type]?.[1] }} title={`${e.title}${e.detail ? ` · ${e.detail}` : ''}`}>
                        {e.time && <b>{e.time}</b>} {e.title}
                      </Link>
                    ))}
                    {list.length > 4 && <span className="b-cal-more">+{list.length - 4} more</span>}
                  </div>
                )
              })}
            </div>
            <ul className="b-agenda">
              {agenda.length ? agenda.map((e, i) => (
                <li key={i} style={{ borderLeftColor: TYPES[e.type]?.[1] }}>
                  <span className="b-agenda-date">{new Date(e.date).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })}{e.time ? ` · ${e.time}` : ''}</span>
                  <Link to={e.link}><b>{e.title}</b></Link>
                  {e.detail && <span className="a-muted b-sub">{e.detail}</span>}
                </li>
              )) : <li className="a-empty">Nothing scheduled this month</li>}
            </ul>
          </>
        )}
      </div>
    </>
  )
}
