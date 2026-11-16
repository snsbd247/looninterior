/** Renders admin-authored HTML content. */
export default function Rich({ html, className = '' }) {
  if (!html) return null
  return <div className={`rich ${className}`} dangerouslySetInnerHTML={{ __html: html }} />
}
