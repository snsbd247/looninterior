import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiInbox, FiGrid, FiLayers, FiUserPlus, FiEdit, FiMessageCircle, FiUsers, FiBell } from 'react-icons/fi'
import { adminApi, formatDate } from '../../lib/api'
import Loader from '../../components/Loader'

export default function Dashboard() {
  const [data, setData] = useState(null)

  useEffect(() => {
    adminApi.get('/dashboard').then((r) => setData(r.data)).catch(() => setData({ counts: {}, recent_inquiries: [], recent_applications: [] }))
  }, [])

  if (!data) return <Loader />
  const c = data.counts

  const cards = [
    ['New Inquiries', c.new_inquiries, FiBell, '/admin/r/inquiries', 'accent'],
    ['Total Inquiries', c.inquiries, FiInbox, '/admin/r/inquiries'],
    ['Projects', c.projects, FiGrid, '/admin/r/projects'],
    ['Services', c.services, FiLayers, '/admin/r/services'],
    ['Job Applications', c.applications, FiUserPlus, '/admin/r/job_applications'],
    ['Blog Posts', c.posts, FiEdit, '/admin/r/posts'],
    ['Testimonials', c.testimonials, FiMessageCircle, '/admin/r/testimonials'],
    ['Team Members', c.team, FiUsers, '/admin/r/team_members'],
  ]

  return (
    <>
      <div className="a-page-head"><h1>Dashboard</h1></div>
      <div className="a-cards">
        {cards.map(([label, value, Ic, to, variant]) => (
          <Link to={to} key={label} className={`a-card ${variant || ''}`}>
            <span className="a-card-icon"><Ic /></span>
            <div><strong>{value ?? 0}</strong><span>{label}</span></div>
          </Link>
        ))}
      </div>

      <div className="a-grid-2">
        <div className="a-panel">
          <div className="a-panel-head"><h3>Recent Quote Requests</h3><Link to="/admin/r/inquiries">View all</Link></div>
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Name</th><th>Phone</th><th>Service</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              {data.recent_inquiries.length ? data.recent_inquiries.map((r) => (
                <tr key={r.id}>
                  <td><Link to={`/admin/r/inquiries/${r.id}`}>{r.name}</Link></td>
                  <td><a href={`tel:${r.phone}`}>{r.phone}</a></td>
                  <td>{r.service || '—'}</td>
                  <td><span className={`a-status s-${r.status}`}>{r.status}</span></td>
                  <td>{formatDate(r.created_at)}</td>
                </tr>
              )) : <tr><td colSpan={5} className="a-empty">No inquiries yet</td></tr>}
            </tbody>
          </table></div>
        </div>
        <div className="a-panel">
          <div className="a-panel-head"><h3>Recent Applications</h3><Link to="/admin/r/job_applications">View all</Link></div>
          <div className="a-table-wrap"><table className="a-table">
            <thead><tr><th>Name</th><th>Job</th><th>Status</th></tr></thead>
            <tbody>
              {data.recent_applications.length ? data.recent_applications.map((r) => (
                <tr key={r.id}>
                  <td><Link to={`/admin/r/job_applications/${r.id}`}>{r.name}</Link></td>
                  <td>{r.career?.title || '—'}</td>
                  <td><span className={`a-status s-${r.status}`}>{r.status}</span></td>
                </tr>
              )) : <tr><td colSpan={3} className="a-empty">No applications yet</td></tr>}
            </tbody>
          </table></div>
        </div>
      </div>
    </>
  )
}
