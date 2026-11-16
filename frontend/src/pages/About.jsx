import { FiTarget, FiEye } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import SectionHead from '../components/SectionHead'
import Loader from '../components/Loader'
import Img from '../components/Img'
import Rich from '../components/Rich'
import { TeamCard } from '../components/Cards'
import { StatsBand, FeaturesSection, ProcessSection, ClientsSection, CtaBand } from '../components/Sections'

export default function About() {
  useTitle('About Us')
  const { settings: s } = useSite()
  const { data, loading } = useFetch('/about')

  return (
    <>
      <PageHeader title="About Us" />

      <section className="section">
        <div className="container about-intro">
          <div className="about-media">
            <Img src={s.about_image} alt={s.about_title} />
            {s.years_experience && (
              <div className="exp-badge"><strong>{s.years_experience}+</strong><span>Years of<br />Experience</span></div>
            )}
          </div>
          <div>
            <SectionHead tag="Who We Are" title={s.about_title} center={false} />
            <Rich html={s.about_content} />
          </div>
        </div>
      </section>

      {(s.mission || s.vision) && (
        <section className="section bg-cream">
          <div className="container grid-2">
            {s.mission && (
              <div className="mv-card"><FiTarget /><h3>Our Mission</h3><p>{s.mission}</p></div>
            )}
            {s.vision && (
              <div className="mv-card"><FiEye /><h3>Our Vision</h3><p>{s.vision}</p></div>
            )}
          </div>
        </section>
      )}

      {loading ? <Loader /> : data && (
        <>
          <StatsBand stats={data.stats} />
          {data.team.length > 0 && (
            <section className="section">
              <div className="container">
                <SectionHead tag="Our Team" title="Meet the People Behind the Designs" />
                <div className="grid-4">
                  {data.team.map((m) => <TeamCard key={m.id} member={m} />)}
                </div>
              </div>
            </section>
          )}
          <FeaturesSection items={data.features} title={s.features_title} subtitle={s.features_subtitle} />
          <ProcessSection steps={data.process} title={s.process_title} subtitle={s.process_subtitle} />
          <ClientsSection items={data.clients} title={s.clients_title} />
        </>
      )}
      <CtaBand />
    </>
  )
}
