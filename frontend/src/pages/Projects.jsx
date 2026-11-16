import { useState, useEffect } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useFetch } from '../lib/useFetch'
import { useSite, useTitle } from '../context/SiteContext'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import { ProjectCard } from '../components/Cards'
import { Pagination, CtaBand } from '../components/Sections'

export default function Projects() {
  useTitle('Projects')
  const { categories } = useSite()
  const [params, setParams] = useSearchParams()
  const category = params.get('category') || ''
  const [page, setPage] = useState(1)

  useEffect(() => setPage(1), [category])

  const { data, loading } = useFetch(`/projects?page=${page}${category ? `&category=${encodeURIComponent(category)}` : ''}`)
  const current = categories.find((c) => c.slug === category)

  return (
    <>
      <PageHeader title={current ? `${current.name} Projects` : 'Our Projects'} />
      <section className="section">
        <div className="container">
          {categories.length > 0 && (
            <div className="filter-tabs">
              <button className={!category ? 'is-active' : ''} onClick={() => setParams({})}>All</button>
              {categories.map((c) => (
                <button key={c.id} className={category === c.slug ? 'is-active' : ''} onClick={() => setParams({ category: c.slug })}>{c.name}</button>
              ))}
            </div>
          )}
          {loading ? <Loader /> : (
            <>
              {data?.data?.length ? (
                <div className="grid-3 projects-grid">
                  {data.data.map((p) => <ProjectCard key={p.id} project={p} />)}
                </div>
              ) : <p className="center muted">No projects found.</p>}
              <Pagination meta={data} onChange={(p) => { setPage(p); window.scrollTo({ top: 0, behavior: 'smooth' }) }} />
            </>
          )}
        </div>
      </section>
      <CtaBand />
    </>
  )
}
