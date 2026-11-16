import { Link } from 'react-router-dom'
import { FiMapPin, FiBriefcase, FiCalendar, FiUsers } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useTitle } from '../context/SiteContext'
import { formatDate } from '../lib/api'
import PageHeader from '../components/PageHeader'
import SectionHead from '../components/SectionHead'
import Loader from '../components/Loader'

export default function Careers() {
  useTitle('Careers')
  const { data, loading } = useFetch('/careers')
  const jobs = data?.data || []

  return (
    <>
      <PageHeader title="Careers" />
      <section className="section">
        <div className="container narrow">
          <SectionHead tag="Join Us" title="Build Beautiful Spaces With Us" text="We are always looking for passionate designers, engineers and craftsmen." />
          {loading ? <Loader /> : jobs.length ? (
            <div className="job-list">
              {jobs.map((j) => (
                <Link to={`/careers/${j.slug}`} className="job-card" key={j.id}>
                  <div>
                    <h3>{j.title}</h3>
                    <div className="job-meta">
                      {j.location && <span><FiMapPin /> {j.location}</span>}
                      {j.job_type && <span><FiBriefcase /> {j.job_type}</span>}
                      {j.vacancy && <span><FiUsers /> {j.vacancy} vacancy</span>}
                      {j.deadline && <span><FiCalendar /> Deadline: {formatDate(j.deadline)}</span>}
                    </div>
                  </div>
                  <span className="btn btn-outline">View & Apply</span>
                </Link>
              ))}
            </div>
          ) : <p className="center muted">No open positions right now. Please check back later.</p>}
        </div>
      </section>
    </>
  )
}
