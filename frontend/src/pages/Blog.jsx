import { useState } from 'react'
import { useFetch } from '../lib/useFetch'
import { useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import { PostCard } from '../components/Cards'
import { Pagination } from '../components/Sections'

export default function Blog() {
  useTitle('Blog')
  const [page, setPage] = useState(1)
  const { data, loading } = useFetch(`/posts?page=${page}`)

  return (
    <>
      <PageHeader title="Blog" />
      <section className="section">
        <div className="container">
          {loading ? <Loader /> : (
            <>
              {data?.data?.length ? (
                <div className="grid-3">{data.data.map((p) => <PostCard key={p.id} post={p} />)}</div>
              ) : <p className="center muted">No articles yet.</p>}
              <Pagination meta={data} onChange={(p) => { setPage(p); window.scrollTo({ top: 0, behavior: 'smooth' }) }} />
            </>
          )}
        </div>
      </section>
    </>
  )
}
