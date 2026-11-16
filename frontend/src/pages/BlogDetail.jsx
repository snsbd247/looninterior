import { Link, useParams } from 'react-router-dom'
import { FiCalendar } from 'react-icons/fi'
import { useFetch } from '../lib/useFetch'
import { useTitle } from '../context/SiteContext'
import { formatDate } from '../lib/api'
import PageHeader from '../components/PageHeader'
import Loader from '../components/Loader'
import Img from '../components/Img'
import Rich from '../components/Rich'
import NotFound from './NotFound'

export default function BlogDetail() {
  const { slug } = useParams()
  const { data, loading, error } = useFetch(`/posts/${slug}`)
  useTitle(data?.data?.title || 'Blog')

  if (loading) return <Loader full />
  if (error || !data) return <NotFound />
  const post = data.data

  return (
    <>
      <PageHeader title={post.title} crumbs={[{ label: 'Blog', to: '/blog' }]} />
      <section className="section">
        <div className="container detail-layout">
          <article>
            {post.image && <Img src={post.image} alt={post.title} className="detail-cover" />}
            {post.published_at && <p className="post-date"><FiCalendar /> {formatDate(post.published_at)}</p>}
            <Rich html={post.body} />
          </article>
          <aside className="sidebar">
            {data.recent.length > 0 && (
              <div className="side-card">
                <h4>Recent Posts</h4>
                <ul className="recent-posts">
                  {data.recent.map((r) => (
                    <li key={r.id}>
                      <Link to={`/blog/${r.slug}`}>
                        <Img src={r.image} alt={r.title} />
                        <div><strong>{r.title}</strong><small>{formatDate(r.published_at)}</small></div>
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            )}
            <div className="side-card side-cta">
              <h4>Need design help?</h4>
              <p>Talk to our designers for free.</p>
              <Link to="/get-quote" className="btn btn-primary btn-block">Get a Free Quote</Link>
            </div>
          </aside>
        </div>
      </section>
    </>
  )
}
