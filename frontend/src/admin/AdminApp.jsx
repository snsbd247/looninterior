import { createContext, useContext, useEffect, useState, useCallback } from 'react'
import { Routes, Route, Navigate, NavLink, Link, useNavigate, useLocation } from 'react-router-dom'
import { FiHome, FiSettings, FiUser, FiLogOut, FiExternalLink, FiMenu } from 'react-icons/fi'
import { adminApi, getToken, setToken, sitePath } from '../lib/api'
import Loader from '../components/Loader'
import { RESOURCES, GROUPS } from './resources'
import Login from './pages/Login'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import Dashboard from './pages/Dashboard'
import ResourceList from './pages/ResourceList'
import ResourceForm from './pages/ResourceForm'
import Settings from './pages/Settings'
import Profile from './pages/Profile'
import './admin.css'

const AdminContext = createContext(null)
export const useAdmin = () => useContext(AdminContext)

function Toasts({ items }) {
  return (
    <div className="toasts">
      {items.map((t) => <div key={t.id} className={`toast toast-${t.type}`}>{t.message}</div>)}
    </div>
  )
}

function Layout() {
  const navigate = useNavigate()
  const location = useLocation()
  const [user, setUser] = useState(null)
  const [toasts, setToasts] = useState([])
  const [menu, setMenu] = useState(false)
  const [badges, setBadges] = useState({})

  const toast = useCallback((message, type = 'success') => {
    const id = Date.now() + Math.random()
    setToasts((t) => [...t, { id, message, type }])
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 3500)
  }, [])

  const refreshBadges = useCallback(() => {
    adminApi.get('/dashboard').then((r) => setBadges({
      inquiries: r.data.counts.new_inquiries,
      job_applications: r.data.counts.new_applications,
    })).catch(() => {})
  }, [])

  useEffect(() => {
    adminApi.get('/me').then((r) => setUser(r.data.user)).catch(() => {})
    refreshBadges()
  }, [refreshBadges])

  useEffect(() => setMenu(false), [location.pathname])

  const logout = async () => {
    try { await adminApi.post('/logout') } catch { /* ignore */ }
    setToken(null)
    navigate('/admin/login')
  }

  if (!user) return <Loader full />

  return (
    <AdminContext.Provider value={{ user, setUser, toast, refreshBadges }}>
      <div className={`admin ${menu ? 'menu-open' : ''}`}>
        <aside className="a-sidebar">
          <Link to="/admin" className="a-brand">Admin Panel</Link>
          <nav>
            <NavLink to="/admin" end><FiHome /> Dashboard</NavLink>
            {GROUPS.map((g) => (
              <div key={g} className="a-group">
                <span className="a-group-title">{g}</span>
                {Object.entries(RESOURCES).filter(([, r]) => r.group === g).map(([key, r]) => {
                  const Ic = r.icon
                  return (
                    <NavLink key={key} to={`/admin/r/${key}`}>
                      <Ic /> {r.label}
                      {badges[key] > 0 && <span className="a-badge">{badges[key]}</span>}
                    </NavLink>
                  )
                })}
              </div>
            ))}
            <div className="a-group">
              <span className="a-group-title">System</span>
              <NavLink to="/admin/settings"><FiSettings /> Site Settings</NavLink>
              <NavLink to="/admin/profile"><FiUser /> My Profile</NavLink>
            </div>
          </nav>
        </aside>
        <div className="a-backdrop" onClick={() => setMenu(false)} />

        <div className="a-main">
          <header className="a-topbar">
            <button className="a-burger" onClick={() => setMenu((m) => !m)} aria-label="Menu"><FiMenu /></button>
            <div className="a-top-right">
              <a href={sitePath('/')} target="_blank" rel="noreferrer" className="a-btn a-btn-light"><FiExternalLink /> View Site</a>
              <span className="a-user">{user.name}</span>
              <button className="a-btn a-btn-light" onClick={logout}><FiLogOut /> Logout</button>
            </div>
          </header>
          <div className="a-content">
            <Routes>
              <Route index element={<Dashboard />} />
              <Route path="r/:resource" element={<ResourceList />} />
              <Route path="r/:resource/new" element={<ResourceForm />} />
              <Route path="r/:resource/:id" element={<ResourceForm />} />
              <Route path="settings" element={<Settings />} />
              <Route path="profile" element={<Profile />} />
              <Route path="*" element={<Navigate to="/admin" replace />} />
            </Routes>
          </div>
        </div>
        <Toasts items={toasts} />
      </div>
    </AdminContext.Provider>
  )
}

function RequireAuth({ children }) {
  return getToken() ? children : <Navigate to="/admin/login" replace />
}

export default function AdminApp() {
  useEffect(() => { document.title = 'Admin Panel' }, [])
  return (
    <Routes>
      <Route path="login" element={<Login />} />
      <Route path="forgot-password" element={<ForgotPassword />} />
      <Route path="reset-password" element={<ResetPassword />} />
      <Route path="*" element={<RequireAuth><Layout /></RequireAuth>} />
    </Routes>
  )
}
