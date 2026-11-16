import { useEffect, useState } from 'react'
import { img } from '../lib/api'

/** Image with graceful placeholder when missing or broken. */
export default function Img({ src, alt = '', className = '', ...rest }) {
  const [broken, setBroken] = useState(false)
  useEffect(() => setBroken(false), [src])

  if (!src || broken) {
    return <div className={`img-ph ${className}`} role="img" aria-label={alt} />
  }
  return <img src={img(src)} alt={alt} className={className} loading="lazy" onError={() => setBroken(true)} {...rest} />
}
