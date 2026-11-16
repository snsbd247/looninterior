import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import SectionHead from '../components/SectionHead'
import Loader from '../components/Loader'
import { FaqList, CtaBand } from '../components/Sections'

export default function Faq() {
  useTitle('FAQ')
  const { settings: s } = useSite()
  const { data, loading } = useFetch('/faqs')

  return (
    <>
      <PageHeader title="FAQ" />
      <section className="section">
        <div className="container narrow">
          <SectionHead tag="FAQ" title={s.faq_title || 'Frequently Asked Questions'} />
          {loading ? <Loader /> : <FaqList items={data?.data || []} />}
        </div>
      </section>
      <CtaBand />
    </>
  )
}
