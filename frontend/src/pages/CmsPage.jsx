import { useParams } from 'react-router-dom'
import { useFetch } from '../lib/useFetch'
import { useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import Rich from '../components/Rich'
import NotFound from './NotFound'

export default function CmsPage() {
  const { slug } = useParams()
  const { data, loading, error } = useFetch(`/pages/${slug}`)
  useTitle(data?.data?.title || '')

  if (loading) return <Loader full />
  if (error || !data) return <NotFound />

  return (
    <>
      <PageHeader title={data.data.title} />
      <section className="section">
        <div className="container narrow"><Rich html={data.data.content} /></div>
      </section>
    </>
  )
}
