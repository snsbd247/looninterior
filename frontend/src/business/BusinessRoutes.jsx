import { Routes, Route, Navigate } from 'react-router-dom'
import { useAdmin, NoAccess } from '../admin/AdminApp'
import Users from './pages/users/Users'
import UserForm from './pages/users/UserForm'
import Roles from './pages/users/Roles'
import RoleForm from './pages/users/RoleForm'
import BusinessSettings from './pages/settings/BusinessSettings'
import ActivityLog from './pages/activity/ActivityLog'
import './business.css'

/* Business module routes, mounted at /admin/biz/* (lazy chunk). Every page is permission-gated. */
function Guard({ perm, children }) {
  const { can } = useAdmin()
  return can(perm) ? children : <NoAccess />
}

export default function BusinessRoutes() {
  return (
    <Routes>
      <Route path="users" element={<Guard perm="users.manage"><Users /></Guard>} />
      <Route path="users/new" element={<Guard perm="users.manage"><UserForm /></Guard>} />
      <Route path="users/:id" element={<Guard perm="users.manage"><UserForm /></Guard>} />
      <Route path="roles" element={<Guard perm="roles.manage"><Roles /></Guard>} />
      <Route path="roles/new" element={<Guard perm="roles.manage"><RoleForm /></Guard>} />
      <Route path="roles/:id" element={<Guard perm="roles.manage"><RoleForm /></Guard>} />
      <Route path="settings" element={<Guard perm="settings.business"><BusinessSettings /></Guard>} />
      <Route path="activity" element={<Guard perm="activity.view"><ActivityLog /></Guard>} />
      <Route path="*" element={<Navigate to="/admin" replace />} />
    </Routes>
  )
}
