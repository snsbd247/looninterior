export default function SectionHead({ tag, title, text, center = true, light = false }) {
  if (!title && !tag) return null
  return (
    <div className={`section-head ${center ? 'center' : ''} ${light ? 'light' : ''}`}>
      {tag && <span className="tag">{tag}</span>}
      {title && <h2>{title}</h2>}
      {text && <p>{text}</p>}
    </div>
  )
}
