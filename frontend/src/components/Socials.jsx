import { FaFacebookF, FaInstagram, FaYoutube, FaLinkedinIn, FaTiktok, FaPinterestP } from 'react-icons/fa'

const LIST = [
  ['facebook', FaFacebookF, 'Facebook'],
  ['instagram', FaInstagram, 'Instagram'],
  ['youtube', FaYoutube, 'YouTube'],
  ['linkedin', FaLinkedinIn, 'LinkedIn'],
  ['tiktok', FaTiktok, 'TikTok'],
  ['pinterest', FaPinterestP, 'Pinterest'],
]

export default function Socials({ settings = {}, className = 'socials' }) {
  const items = LIST.filter(([key]) => settings[key])
  if (!items.length) return null
  return (
    <div className={className}>
      {items.map(([key, Icon, label]) => (
        <a key={key} href={settings[key]} target="_blank" rel="noreferrer" aria-label={label}>
          <Icon />
        </a>
      ))}
    </div>
  )
}
