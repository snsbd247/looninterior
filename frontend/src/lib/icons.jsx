import {
  FiHome, FiBriefcase, FiMonitor, FiLayers, FiGrid, FiBox, FiAward, FiSmile, FiClock, FiUsers,
  FiDollarSign, FiTool, FiStar, FiShield, FiPenTool, FiMessageSquare, FiCheckCircle, FiKey,
  FiTruck, FiSun, FiCoffee, FiMap, FiTarget, FiThumbsUp, FiHeart, FiCompass, FiFeather, FiZap,
} from 'react-icons/fi'

export const ICONS = {
  home: FiHome, briefcase: FiBriefcase, monitor: FiMonitor, layers: FiLayers, grid: FiGrid,
  box: FiBox, award: FiAward, smile: FiSmile, clock: FiClock, users: FiUsers, dollar: FiDollarSign,
  tool: FiTool, star: FiStar, shield: FiShield, pen: FiPenTool, message: FiMessageSquare,
  check: FiCheckCircle, key: FiKey, truck: FiTruck, sun: FiSun, coffee: FiCoffee, map: FiMap,
  target: FiTarget, thumbs: FiThumbsUp, heart: FiHeart, compass: FiCompass, feather: FiFeather, zap: FiZap,
}

export const ICON_KEYS = Object.keys(ICONS)

export default function Icon({ name, fallback = 'star', ...props }) {
  const C = ICONS[name] || ICONS[fallback]
  return <C {...props} />
}
