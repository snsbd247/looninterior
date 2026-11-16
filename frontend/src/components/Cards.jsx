import { Link } from 'react-router-dom'
import { FiArrowRight, FiMapPin, FiMaximize, FiCalendar } from 'react-icons/fi'
import Img from './Img'
import Icon from '../lib/icons'
import { formatDate } from '../lib/api'

export function ServiceCard({ service }) {
  return (
    <Link to={`/services/${service.slug}`} className="service-card">
      <div className="service-media">
        <div className="service-img"><Img src={service.image} alt={service.title} /></div>
        <span className="service-icon"><Icon name={service.icon} fallback="layers" /></span>
      </div>
      <div className="service-body">
        <h3>{service.title}</h3>
        {service.short_description && <p>{service.short_description}</p>}
        <span className="more">Learn more <FiArrowRight /></span>
      </div>
    </Link>
  )
}

export function ProjectCard({ project }) {
  return (
    <Link to={`/projects/${project.slug}`} className="project-card">
      <Img src={project.cover_image} alt={project.title} />
      <div className="project-overlay">
        {project.category && <span className="project-cat">{project.category.name}</span>}
        <h3>{project.title}</h3>
        <div className="project-meta">
          {project.location && <span><FiMapPin /> {project.location}</span>}
          {project.area && <span><FiMaximize /> {project.area}</span>}
        </div>
      </div>
    </Link>
  )
}

export function PostCard({ post }) {
  return (
    <article className="post-card">
      <Link to={`/blog/${post.slug}`} className="post-media"><Img src={post.image} alt={post.title} /></Link>
      <div className="post-body">
        {post.published_at && <span className="post-date"><FiCalendar /> {formatDate(post.published_at)}</span>}
        <h3><Link to={`/blog/${post.slug}`}>{post.title}</Link></h3>
        {post.excerpt && <p>{post.excerpt}</p>}
        <Link to={`/blog/${post.slug}`} className="more">Read more <FiArrowRight /></Link>
      </div>
    </article>
  )
}

export function TeamCard({ member }) {
  const initials = (member.name || '').split(' ').map((w) => w[0]).slice(0, 2).join('')
  return (
    <div className="team-card">
      <div className="team-photo">
        {member.photo ? <Img src={member.photo} alt={member.name} /> : <span className="team-initials">{initials}</span>}
      </div>
      <h3>{member.name}</h3>
      {member.designation && <span className="team-role">{member.designation}</span>}
      {member.bio && <p>{member.bio}</p>}
      <div className="team-links">
        {member.facebook && <a href={member.facebook} target="_blank" rel="noreferrer">Facebook</a>}
        {member.linkedin && <a href={member.linkedin} target="_blank" rel="noreferrer">LinkedIn</a>}
      </div>
    </div>
  )
}
