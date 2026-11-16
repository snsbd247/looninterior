import { createContext, lazy, Suspense, useContext, useEffect, useState, useCallback } from 'react'
import { Routes, Route, Navigate, NavLink, Link, useNavigate, useLocation } from 'react-router-dom'
import {
  FiHome, FiSettings, FiUser, FiLogOut, FiExternalLink, FiMenu, FiUsers, FiShield, FiBriefcase, FiActivity,
  FiTarget, FiClock, FiUserCheck, FiFileText, FiTool, FiFile, FiDollarSign, FiAlertCircle, FiCreditCard, FiRepeat, FiPieChart, FiBox, FiShuffle, FiClipboard, FiPackage, FiTruck, FiLifeBuoy,
  FiChevronDown, FiFolder, FiInbox, FiLayout, FiEdit3, FiCalendar, FiUserX, FiCheckSquare, FiHardDrive, FiSend,
} from 'react-icons/fi'
import { api, adminApi, getBranch, getToken, setBranch, setToken, sitePath } from '../lib/api'
import Loader from '../components/Loader'
import { RESOURCES, GROUPS, resourcePerm } from './resources'
import Login from './pages/Login'
import ForgotPassword from './pages/ForgotPassword'
import ResetPassword from './pages/ResetPassword'
import Dashboard from './pages/Dashboard'
import ResourceList from './pages/ResourceList'
import ResourceForm from './pages/ResourceForm'
import Settings from './pages/Settings'
import Profile from './pages/Profile'
import './admin.css'

const BusinessRoutes = lazy(() => import('../business/BusinessRoutes'))

const AdminContext = createContext(null)
export const useAdmin = () => useContext(AdminContext)

/* Custom (non-generic) pages shown in a sidebar group, each behind a permission. */
const EXTRA_LINKS = {
  CRM: [
    { to: '/admin/biz/leads', label: 'Leads', icon: FiTarget, perm: 'crm.view' },
    { to: '/admin/biz/follow-ups', label: 'Follow-ups', icon: FiClock, perm: 'crm.view' },
    { to: '/admin/biz/calendar', label: 'Calendar', icon: FiCalendar, perm: 'crm.view|projects.view|tickets.view|billing.view|inventory.view' },
    { to: '/admin/biz/customers', label: 'Customers', icon: FiUserCheck, perm: 'crm.view' },
  ],
  'Sales & Projects': [
    { to: '/admin/biz/quotations', label: 'Quotations', icon: FiFileText, perm: 'quotations.view' },
    { to: '/admin/biz/projects', label: 'Projects', icon: FiTool, perm: 'projects.view' },
    { to: '/admin/biz/labour', label: 'Labour Work', icon: FiHardDrive, perm: 'labour.view' },
    { to: '/admin/biz/contractors', label: 'Contractors', icon: FiUsers, perm: 'labour.view' },
  ],
  Billing: [
    { to: '/admin/biz/invoices', label: 'Invoices', icon: FiFile, perm: 'billing.view' },
    { to: '/admin/biz/payments', label: 'Payments', icon: FiDollarSign, perm: 'billing.view' },
    { to: '/admin/biz/receivables', label: 'Receivables (Dues)', icon: FiAlertCircle, perm: 'billing.view' },
  ],
  Accounts: [
    { to: '/admin/biz/accounts', label: 'Cash & Bank', icon: FiCreditCard, perm: 'accounts.view' },
    { to: '/admin/biz/transactions', label: 'Income & Expenses', icon: FiRepeat, perm: 'accounts.view' },
    { to: '/admin/biz/reports', label: 'Reports', icon: FiPieChart, perm: 'accounts.view' },
  ],
  Inventory: [
    { to: '/admin/biz/stock', label: 'Stock', icon: FiBox, perm: 'inventory.view' },
    { to: '/admin/biz/stock-entries', label: 'Issue / Transfer', icon: FiShuffle, perm: 'inventory.view' },
    { to: '/admin/biz/purchase-orders', label: 'Purchase Orders', icon: FiClipboard, perm: 'inventory.view' },
    { to: '/admin/biz/purchases', label: 'Purchases (GRN)', icon: FiPackage, perm: 'inventory.view' },
    { to: '/admin/biz/suppliers', label: 'Suppliers', icon: FiTruck, perm: 'inventory.view|suppliers.pay' },
    { to: '/admin/biz/stores', label: 'Stores', icon: FiHome, perm: 'inventory.view' },
  ],
  Service: [
    { to: '/admin/biz/tickets', label: 'Service Tickets', icon: FiLifeBuoy, perm: 'tickets.view' },
  ],
  Marketing: [
    { to: '/admin/biz/campaigns', label: 'SMS & E-mail Campaigns', icon: FiSend, perm: 'marketing.view|marketing.send' },
  ],
  HR: [
    { to: '/admin/biz/employees', label: 'Employees', icon: FiUsers, perm: 'hr.view' },
    { to: '/admin/biz/attendance', label: 'Attendance', icon: FiCheckSquare, perm: 'hr.view' },
    { to: '/admin/biz/leaves', label: 'Leave', icon: FiUserX, perm: 'hr.view' },
    { to: '/admin/biz/payrolls', label: 'Salary Sheets', icon: FiDollarSign, perm: 'hr.view' },
  ],
  Administration: [
    { to: '/admin/biz/users', label: 'Users', icon: FiUsers, perm: 'users.manage' },
    { to: '/admin/biz/roles', label: 'Roles & Permissions', icon: FiShield, perm: 'roles.manage' },
    { to: '/admin/biz/settings', label: 'Business Settings', icon: FiBriefcase, perm: 'settings.business' },
    { to: '/admin/biz/activity', label: 'Activity Log', icon: FiActivity, perm: 'activity.view' },
  ],
}

function Toasts({ items }) {
  return (
    <div className="toasts">
      {items.map((t) => <div key={t.id} className={`toast toast-${t.type}`}>{t.message}</div>)}
    </div>
  )
}

/** Shows children only when the user has the permission (any of several, separated by "|"). */
export function Can({ perm, children, fallback = null }) {
  const { can } = useAdmin()
  return can(perm) ? children : fallback
}

function RequirePerm({ perm, children }) {
  const { can } = useAdmin()
  return can(perm) ? children : <NoAccess />
}

export function NoAccess() {
  return (
    <div className="a-panel a-noaccess">
      <h2>No access</h2>
      <p className="a-muted">Your role does not have permission to open this page. Ask the Admin if you need it.</p>
      <Link to="/admin" className="a-btn a-btn-light">Go to Dashboard</Link>
    </div>
  )
}

/* Icon of each top-level menu (submenu parents). */
const GROUP_ICONS = {
  CRM: FiTarget, 'Sales & Projects': FiTool, Billing: FiFile, Accounts: FiCreditCard, Inventory: FiBox,
  Service: FiLifeBuoy, Marketing: FiSend, HR: FiUsers, Reports: FiPieChart, 'Website Inbox': FiInbox, 'Home Page': FiLayout, Content: FiEdit3,
  Careers: FiBriefcase, Administration: FiShield, System: FiSettings,
}
const OPEN_KEY = 'admin_menu_open'

/**
 * Sidebar as an accordion: each group is a parent item that expands its submenu.
 * The group that contains the current page opens automatically; open groups are remembered.
 */
function SideMenu({ groups, pathname }) {
  const isActive = (to) => pathname === to || pathname.startsWith(to + '/')
  const [open, setOpen] = useState(() => { try { return JSON.parse(localStorage.getItem(OPEN_KEY)) || {} } catch { return {} } })
  const activeGroup = groups.find((g) => g.links.some((l) => isActive(l.to)))?.name

  useEffect(() => {
    if (activeGroup && !open[activeGroup]) setOpen((o) => ({ ...o, [activeGroup]: true }))
  }, [activeGroup]) // eslint-disable-line react-hooks/exhaustive-deps
  useEffect(() => { try { localStorage.setItem(OPEN_KEY, JSON.stringify(open)) } catch { /* ignore */ } }, [open])

  return (
    <nav className="a-nav">
      <NavLink to="/admin" end className="a-nav-item"><FiHome /> <span>Dashboard</span></NavLink>
      {groups.map((g) => {
        const Ic = GROUP_ICONS[g.name] || FiFolder
        const isOpen = !!open[g.name]
        const badge = g.links.reduce((s, l) => s + (l.badge || 0), 0)
        return (
          <div key={g.name} className={`a-sub ${isOpen ? 'is-open' : ''} ${g.name === activeGroup ? 'has-active' : ''}`}>
            <button type="button" className="a-nav-item a-sub-toggle" aria-expanded={isOpen} onClick={() => setOpen((o) => ({ ...o, [g.name]: !isOpen }))}>
              <Ic /> <span>{g.name}</span>
              {!isOpen && badge > 0 && <span className="a-badge">{badge}</span>}
              <FiChevronDown className="a-sub-caret" />
            </button>
            {isOpen && (
              <div className="a-sub-items">
                {g.links.map((l) => (
                  <NavLink key={l.to} to={l.to} className={() => (isActive(l.to) ? 'active' : '')}>
                    <span>{l.label}</span>
                    {l.badge > 0 && <span className="a-badge">{l.badge}</span>}
                  </NavLink>
                ))}
              </div>
            )}
          </div>
        )
      })}
    </nav>
  )
}

function BranchSwitcher({ user, branch, onChange }) {
  const options = user.branches || []
  if (!options.length || (options.length < 2 && !user.can_all_branches)) {
    return options[0] ? <span className="a-branch-static" title="Your branch">{options[0].name}</span> : null
  }
  return (
    <label className="a-branch" title="Working branch">
      <span>Branch</span>
      <select value={branch} onChange={(e) => onChange(e.target.value)}>
        {user.can_all_branches && <option value="all">All branches (central)</option>}
        {options.map((b) => <option key={b.id} value={String(b.id)}>{b.name} ({b.code})</option>)}
      </select>
    </label>
  )
}

function Layout() {
  const navigate = useNavigate()
  const location = useLocation()
  const [user, setUser] = useState(null)
  const [branch, setBranchState] = useState(getBranch() || '')
  const [toasts, setToasts] = useState([])
  const [menu, setMenu] = useState(false)
  const [badges, setBadges] = useState({})

  const toast = useCallback((message, type = 'success') => {
    const id = Date.now() + Math.random()
    setToasts((t) => [...t, { id, message, type }])
    setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 3500)
  }, [])

  const can = useCallback((perm) => {
    if (!perm) return true
    const list = user?.permissions || []
    return String(perm).split('|').some((p) => list.includes(p.trim()))
  }, [user])

  const refreshBadges = useCallback(() => {
    if (!can('website.manage')) return
    adminApi.get('/dashboard').then((r) => setBadges({
      inquiries: r.data.counts.new_inquiries,
      job_applications: r.data.counts.new_applications,
    })).catch(() => {})
  }, [can])

  useEffect(() => {
    adminApi.get('/me').then((r) => {
      const u = r.data.user
      // Keep the saved branch only if it is still allowed; otherwise use the first one.
      const ids = (u.branches || []).map((b) => String(b.id))
      let b = getBranch()
      if (!(b && (ids.includes(b) || (b === 'all' && u.can_all_branches)))) b = ids[0] || ''
      setBranch(b)
      setBranchState(b)
      setUser(u)
    }).catch(() => {})
  }, [])

  useEffect(() => { if (user) refreshBadges() }, [user, refreshBadges])
  useEffect(() => setMenu(false), [location.pathname])

  const changeBranch = (b) => {
    setBranch(b)
    setBranchState(b)
    toast(b === 'all' ? 'Showing all branches' : 'Branch changed')
  }

  const logout = async () => {
    try { await adminApi.post('/logout') } catch { /* ignore */ }
    setToken(null)
    navigate('/admin/login')
  }

  if (!user) return <Loader full />

  const groupLinks = (g) => [
    ...Object.entries(RESOURCES).filter(([, r]) => r.group === g && !r.hidden && can(resourcePerm(r))).map(([key, r]) => ({
      to: `/admin/r/${key}`, label: r.label, icon: r.icon, badge: badges[key],
    })),
    ...(EXTRA_LINKS[g] || []).filter((l) => can(l.perm)),
  ]

  return (
    <AdminContext.Provider value={{ user, setUser, toast, refreshBadges, can, branch }}>
      <div className={`admin ${menu ? 'menu-open' : ''}`}>
        <aside className="a-sidebar">
          <Link to="/admin" className="a-brand">Admin Panel</Link>
          <SideMenu
            pathname={location.pathname}
            groups={[
              ...GROUPS.map((g) => ({ name: g, links: groupLinks(g) })),
              { name: 'System', links: [
                ...(can('website.manage') ? [{ to: '/admin/settings', label: 'Site Settings', icon: FiSettings }] : []),
                { to: '/admin/profile', label: 'My Profile', icon: FiUser },
              ] },
            ].filter((g) => g.links.length)}
          />
        </aside>
        <div className="a-backdrop" onClick={() => setMenu(false)} />

        <div className="a-main">
          <header className="a-topbar">
            <button className="a-burger" onClick={() => setMenu((m) => !m)} aria-label="Menu"><FiMenu /></button>
            <BranchSwitcher user={user} branch={branch} onChange={changeBranch} />
            <div className="a-top-right">
              <a href={sitePath('/')} target="_blank" rel="noreferrer" className="a-btn a-btn-light"><FiExternalLink /> <span className="hide-sm">View Site</span></a>
              <span className="a-user" title={user.role?.name}>{user.name}<small>{user.role?.name}</small></span>
              <button className="a-btn a-btn-light" onClick={logout}><FiLogOut /> <span className="hide-sm">Logout</span></button>
            </div>
          </header>
          {/* key: switching branch reloads the page content for the new branch */}
          <div className="a-content" key={branch}>
            <Routes>
              <Route index element={<Dashboard />} />
              <Route path="r/:resource" element={<ResourceList />} />
              <Route path="r/:resource/new" element={<ResourceForm />} />
              <Route path="r/:resource/:id" element={<ResourceForm />} />
              <Route path="settings" element={<RequirePerm perm="website.manage"><Settings /></RequirePerm>} />
              <Route path="profile" element={<Profile />} />
              <Route path="biz/*" element={<Suspense fallback={<Loader />}><BusinessRoutes /></Suspense>} />
              <Route path="*" element={<Navigate to="/admin" replace />} />
            </Routes>
          </div>
          <AdminFooter />
        </div>
        <Toasts items={toasts} />
      </div>
    </AdminContext.Provider>
  )
}

/** Copyright line at the bottom of every admin page (same text as the website footer setting). */
function AdminFooter() {
  const [s, setS] = useState({})
  useEffect(() => { api.get('/site').then((r) => setS(r.data.settings || {})).catch(() => {}) }, [])
  return <footer className="a-footer">{s.copyright || `© ${new Date().getFullYear()} ${s.site_name || 'Loon Interior'}. All rights reserved.`}</footer>
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
