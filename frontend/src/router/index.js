import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/auth/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/auth/microsoft/callback',
    name: 'MicrosoftCallback',
    component: () => import('../views/auth/MicrosoftCallbackView.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    component: () => import('../components/layout/AppLayout.vue'),
    meta: { auth: true },
    children: [
      { path: '', name: 'Dashboard', component: () => import('../views/DashboardView.vue') },
      { path: 'incidents', name: 'Incidents', component: () => import('../views/incidents/IncidentListView.vue') },
      { path: 'incidents/:id', name: 'IncidentDetail', component: () => import('../views/incidents/IncidentDetailView.vue') },
      { path: 'incidents/create', name: 'IncidentCreate', component: () => import('../views/incidents/IncidentFormView.vue') },
      { path: 'requests', name: 'Requests', component: () => import('../views/requests/RequestListView.vue') },
      { path: 'requests/:id', name: 'RequestDetail', component: () => import('../views/requests/RequestDetailView.vue') },
      { path: 'requests/create', name: 'RequestCreate', component: () => import('../views/requests/RequestFormView.vue') },
      { path: 'problems', name: 'Problems', component: () => import('../views/problems/ProblemListView.vue') },
      { path: 'problems/:id', name: 'ProblemDetail', component: () => import('../views/problems/ProblemDetailView.vue') },
      { path: 'problems/create', name: 'ProblemCreate', component: () => import('../views/problems/ProblemFormView.vue') },
      { path: 'changes', name: 'Changes', component: () => import('../views/changes/ChangeListView.vue') },
      { path: 'changes/:id', name: 'ChangeDetail', component: () => import('../views/changes/ChangeDetailView.vue') },
      { path: 'changes/create', name: 'ChangeCreate', component: () => import('../views/changes/ChangeFormView.vue') },
      { path: 'csi', name: 'CSI', component: () => import('../views/csi/CsiListView.vue') },
      { path: 'csi/create', name: 'CsiCreate', component: () => import('../views/csi/CsiFormView.vue') },
      { path: 'csi/:id', name: 'CsiDetail', component: () => import('../views/csi/CsiDetailView.vue') },
      { path: 'cmdb', name: 'CMDB', component: () => import('../views/cmdb/CiListView.vue') },
      { path: 'cmdb/create', name: 'CiCreate', component: () => import('../views/cmdb/CiFormView.vue') },
      { path: 'cmdb/:id', name: 'CiDetail', component: () => import('../views/cmdb/CiDetailView.vue') },
      { path: 'intune', name: 'IntuneDevices', component: () => import('../views/intune/IntuneDevicesView.vue') },
  { path: 'manual-devices', name: 'ManualDevices', component: () => import('../views/intune/ManualDevicesView.vue') },
      { path: 'knowledge', name: 'KnowledgeBase', component: () => import('../views/knowledge/KnowledgeListView.vue') },
      { path: 'knowledge/create', name: 'KnowledgeCreate', component: () => import('../views/knowledge/KnowledgeFormView.vue') },
      { path: 'knowledge/:id', name: 'KnowledgeDetail', component: () => import('../views/knowledge/KnowledgeDetailView.vue') },
      { path: 'sla', name: 'SLA', component: () => import('../views/sla/SlaListView.vue') },
      { path: 'year-wheel', name: 'YearWheel', component: () => import('../views/yearwheel/YearWheelView.vue') },
      { path: 'tasks', name: 'Tasks', component: () => import('../views/tasks/TaskListView.vue') },
      { path: 'reports', name: 'Reports', component: () => import('../views/reports/ReportDashboardView.vue') },
      { path: 'vendors', name: 'Vendors', component: () => import('../views/vendors/VendorsView.vue') },
      { path: 'customers', name: 'Customers', component: () => import('../views/customers/CustomerManagementView.vue') },
      { path: 'customers/:id', name: 'CustomerDetail', component: () => import('../views/customers/CustomerDetailView.vue') },
      { path: 'admin/users', name: 'AdminUsers', component: () => import('../views/admin/UserManagementView.vue') },
      { path: 'admin/audit-log', name: 'AuditLog', component: () => import('../views/admin/AuditLogView.vue') },
      { path: 'admin/impersonate', name: 'Impersonate', component: () => import('../views/admin/ImpersonateView.vue') },
      { path: 'gdpr', name: 'GDPR', component: () => import('../views/gdpr/GdprDashboardView.vue') },
      { path: 'settings', name: 'Settings', component: () => import('../views/SettingsView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to, from, next) => {
  const auth = useAuthStore()
  if (!auth.user && to.meta.auth) {
    try {
      await auth.fetchUser()
    } catch {
      // ignore
    }
  }
  if (to.meta.auth && !auth.user) {
    next('/login')
  } else if (to.meta.guest && auth.user) {
    next('/')
  } else {
    next()
  }
})

export default router
