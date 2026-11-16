import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import SectionHead from '../components/SectionHead'
import Loader from '../components/Loader'
import { ServiceCard } from '../components/Cards'
import { ProcessSection, CtaBand } from '../components/Sections'

export default function Services() {
  useTitle('Services')
  const { settings: s } = useSite()
  const { data, loading } = useFetch('/services')

  return (
    <>
      <PageHeader title="Our Services" />
      <section className="section">
        <div className="container">
          <SectionHead tag="What We Do" title={s.services_title} text={s.services_subtitle} />
          {loading ? <Loader /> : (
            <div className="grid-3">
              {(data?.data || []).map((x) => <ServiceCard key={x.id} service={x} />)}
            </div>
          )}
        </div>
      </section>
      {data && <ProcessSection steps={data.process} title={s.process_title} subtitle={s.process_subtitle} />}
      <CtaBand />
    </>
  )
}
