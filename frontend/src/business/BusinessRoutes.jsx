import { Routes, Route, Navigate } from 'react-router-dom'
import { useAdmin, NoAccess } from '../admin/AdminApp'
import Users from './pages/users/Users'
import UserForm from './pages/users/UserForm'
import Roles from './pages/users/Roles'
import RoleForm from './pages/users/RoleForm'
import BusinessSettings from './pages/settings/BusinessSettings'
import ActivityLog from './pages/activity/ActivityLog'
import Leads from './pages/crm/Leads'
import LeadForm from './pages/crm/LeadForm'
import LeadDetail from './pages/crm/LeadDetail'
import FollowUps from './pages/crm/FollowUps'
import Customers from './pages/crm/Customers'
import CustomerForm from './pages/crm/CustomerForm'
import CustomerDetail from './pages/crm/CustomerDetail'
import Quotations from './pages/quotations/Quotations'
import QuotationForm from './pages/quotations/QuotationForm'
import QuotationDetail from './pages/quotations/QuotationDetail'
import Projects from './pages/projects/Projects'
import ProjectForm from './pages/projects/ProjectForm'
import ProjectDetail from './pages/projects/ProjectDetail'
import Invoices from './pages/billing/Invoices'
import InvoiceForm from './pages/billing/InvoiceForm'
import InvoiceDetail from './pages/billing/InvoiceDetail'
import Payments from './pages/billing/Payments'
import Receivables from './pages/billing/Receivables'
import Accounts from './pages/accounts/Accounts'
import AccountLedger from './pages/accounts/AccountLedger'
import Transactions from './pages/accounts/Transactions'
import Reports from './pages/accounts/Reports'
import './business.css'

/* Business module routes, mounted at /admin/biz/* (lazy chunk). Every page is permission-gated. */
function Guard({ perm, children }) {
  const { can } = useAdmin()
  return can(perm) ? children : <NoAccess />
}

/* [path, permission, element] */
const ROUTES = [
  // Step 1: foundation
  ['users', 'users.manage', <Users />],
  ['users/new', 'users.manage', <UserForm />],
  ['users/:id', 'users.manage', <UserForm />],
  ['roles', 'roles.manage', <Roles />],
  ['roles/new', 'roles.manage', <RoleForm />],
  ['roles/:id', 'roles.manage', <RoleForm />],
  ['settings', 'settings.business', <BusinessSettings />],
  ['activity', 'activity.view', <ActivityLog />],
  // Step 2: CRM
  ['leads', 'crm.view', <Leads />],
  ['leads/new', 'crm.manage', <LeadForm />],
  ['leads/:id', 'crm.view', <LeadDetail />],
  ['leads/:id/edit', 'crm.manage', <LeadForm />],
  ['follow-ups', 'crm.view', <FollowUps />],
  ['customers', 'crm.view', <Customers />],
  ['customers/new', 'crm.manage', <CustomerForm />],
  ['customers/:id', 'crm.view', <CustomerDetail />],
  ['customers/:id/edit', 'crm.manage', <CustomerForm />],
  // Step 3: Quotations
  ['quotations', 'quotations.view', <Quotations />],
  ['quotations/new', 'quotations.manage', <QuotationForm />],
  ['quotations/:id', 'quotations.view', <QuotationDetail />],
  ['quotations/:id/edit', 'quotations.manage', <QuotationForm />],
  // Step 4: Projects & billing
  ['projects', 'projects.view', <Projects />],
  ['projects/new', 'projects.manage', <ProjectForm />],
  ['projects/:id', 'projects.view', <ProjectDetail />],
  ['projects/:id/edit', 'projects.manage', <ProjectForm />],
  ['invoices', 'billing.view', <Invoices />],
  ['invoices/new', 'billing.manage', <InvoiceForm />],
  ['invoices/:id', 'billing.view', <InvoiceDetail />],
  ['invoices/:id/edit', 'billing.manage', <InvoiceForm />],
  ['payments', 'billing.view', <Payments />],
  ['receivables', 'billing.view', <Receivables />],
  // Step 5: Accounts
  ['accounts', 'accounts.view', <Accounts />],
  ['accounts/:id', 'accounts.view', <AccountLedger />],
  ['transactions', 'accounts.view', <Transactions />],
  ['reports', 'accounts.view', <Reports />],
]

export default function BusinessRoutes() {
  return (
    <Routes>
      {ROUTES.map(([path, perm, el]) => <Route key={path} path={path} element={<Guard perm={perm}>{el}</Guard>} />)}
      <Route path="*" element={<Navigate to="/admin" replace />} />
    </Routes>
  )
}
