import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/pages/Login.vue'),
  },
  {
    path: '/',
    component: () => import('@/layouts/DefaultLayout.vue'),
    children: [
      { path: '', redirect: '/dashibodi' },
      {
        path: 'dashibodi',
        name: 'Dashibodi',
        component: () => {
          try {
            const raw = localStorage.getItem('shulepay_user')
            const user = raw ? JSON.parse(raw) : null
            const role = user?.role
            if (['teacher', 'teacher_pri', 'teacher_sec', 'head_teacher'].includes(role)) return import('@/views/dashboard/TeacherDashboard.vue')
            if (role === 'academic_teacher' || role === 'headmaster' || role === 'academic_pri' || role === 'academic_sec') return import('@/views/dashboard/AcademicDashboard.vue')
            if (role === 'parent') return import('@/views/dashboard/ParentDashboard.vue')
          } catch { }
          return import('@/views/dashboard/Dashboard.vue')
        },
        meta: { requiresAuth: true },
      },
      // Students
      {
        path: 'wanafunzi',
        name: 'Wanafunzi',
        component: () => import('@/views/wanafunzi/WanafunziList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'wanafunzi/:id',
        name: 'MwanafunziDetail',
        component: () => import('@/views/wanafunzi/MwanafunziDetail.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'wanafunzi/clearance',
        name: 'Clearance',
        component: () => import('@/views/wanafunzi/ClearanceCertificate.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Finance / Fees
      {
        path: 'ada-madeni',
        name: 'AdaMadeni',
        component: () => import('@/views/ada/AdaMadeni.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'ada/muundo',
        name: 'MuundoWaAda',
        component: () => import('@/views/ada/MuundoWaAda.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'malipo/rekodi',
        name: 'RekodaMalipo',
        component: () => import('@/views/malipo/RekodaMalipo.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'malipo/refunds',
        name: 'Refunds',
        component: () => import('@/views/malipo/RefundList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'installments',
        name: 'Installments',
        component: () => import('@/views/installments/InstallmentList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Guardians
      {
        path: 'walezi',
        name: 'WaleziList',
        component: () => import('@/views/walezi/WaleziList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Reports
      {
        path: 'ripoti',
        name: 'Reports',
        component: () => import('@/views/reports/Reports.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner', 'headmaster', 'head_teacher', 'academic_teacher', 'academic_pri', 'academic_sec'] },
      },
      // Expenses & Petty Cash
      {
        path: 'matumizi',
        name: 'Expenses',
        component: () => import('@/views/matumizi/MatumiziList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'matumizi/petty-cash',
        name: 'PettyCash',
        component: () => import('@/views/matumizi/PettyCashView.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Payroll
      {
        path: 'payroll',
        name: 'Payroll',
        component: () => import('@/views/payroll/PayrollView.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      {
        path: 'payroll/employees',
        name: 'Employees',
        component: () => import('@/views/payroll/EmployeeList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Suppliers
      {
        path: 'wasambazaji',
        name: 'Suppliers',
        component: () => import('@/views/wasambazaji/SupplierList.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner'] },
      },
      // Assets → merged into Inventory
      { path: 'mali', redirect: '/inventory' },
      // Admin — Schools
      {
        path: 'admin/schools',
        name: 'Schools',
        component: () => import('@/views/admin/Schools.vue'),
        meta: { requiresAuth: true, roles: ['owner'] },
      },
      {
        path: 'admin/branding',
        name: 'Branding',
        component: () => import('@/views/admin/SchoolBranding.vue'),
        meta: { requiresAuth: true, roles: ['owner', 'superadmin'] },
      },
      {
        path: 'admin/wafanyakazi',
        name: 'Wafanyakazi',
        component: () => import('@/views/admin/WafanyakaziView.vue'),
        meta: { requiresAuth: true, roles: ['owner', 'superadmin'] },
      },
      {
        path: 'superadmin/roles',
        name: 'RolesPermissions',
        component: () => import('@/views/superadmin/RolesPermissionsView.vue'),
        meta: { requiresAuth: true, roles: ['superadmin'] },
      },
      {
        path: 'superadmin/users',
        name: 'SuperadminUsers',
        component: () => import('@/views/superadmin/SuperadminUsersView.vue'),
        meta: { requiresAuth: true, roles: ['superadmin'] },
      },
      {
        path: 'admin/academic-years',
        name: 'AcademicYears',
        component: () => import('@/views/admin/AcademicYearsView.vue'),
        meta: { requiresAuth: true, roles: ['owner', 'superadmin'] },
      },
      {
        path: 'admin/terms',
        name: 'Terms',
        component: () => import('@/views/admin/TermsView.vue'),
        meta: { requiresAuth: true, roles: ['accountant', 'owner', 'superadmin'] },
      },
      {
        path: 'admin/rollover',
        name: 'Rollover',
        component: () => import('@/views/admin/RolloverWizard.vue'),
        meta: { requiresAuth: true, roles: ['owner'] },
      },
      {
        path: 'admin/bulk-import',
        name: 'BulkImport',
        component: () => import('@/views/admin/BulkImport.vue'),
        meta: { requiresAuth: true, roles: ['owner'] },
      },
      // Budget
      {
        path: 'bajeti',
        name: 'Bajeti',
        component: () => import('@/views/budgets/BudgetView.vue'),
        meta: { requiresAuth: true, roles: ['owner'] },
      },
      // Audit
      {
        path: 'audit',
        name: 'AuditLog',
        component: () => import('@/views/audit/AuditLog.vue'),
        meta: { requiresAuth: true, roles: ['owner'] },
      },
      // Shule modules
      { path: 'usafiri', name: 'Usafiri', component: () => import('@/views/usafiri/UsafiriView.vue'), meta: { requiresAuth: true } },
      { path: 'inventory', name: 'Inventory', component: () => import('@/views/inventory/InventoryView.vue'), meta: { requiresAuth: true } },
      { path: 'mahudhurio', name: 'Mahudhurio', component: () => import('@/views/mahudhurio/MahudhurioView.vue'), meta: { requiresAuth: true, roles: ['teacher', 'teacher_pri', 'teacher_sec', 'head_teacher', 'academic_teacher', 'headmaster', 'academic_pri', 'academic_sec', 'owner', 'superadmin'] } },
      { path: 'arifa', name: 'Arifa', component: () => import('@/views/arifa/ArifaView.vue'), meta: { requiresAuth: true } },
      // Stationary
      { path: 'stationary/mine', name: 'MyStationary', component: () => import('@/views/stationary/MyStationaryView.vue'), meta: { requiresAuth: true, roles: ['teacher', 'teacher_pri', 'teacher_sec', 'head_teacher', 'headmaster', 'academic_pri', 'academic_sec'] } },
      { path: 'stationary/manage', name: 'StationaryManage', component: () => import('@/views/stationary/StationaryManageView.vue'), meta: { requiresAuth: true, roles: ['accountant', 'owner', 'superadmin'] } },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/dashibodi' },
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

router.beforeEach((to, _from, next) => {
  if (!to.meta.requiresAuth) return next()

  const token = localStorage.getItem('shulepay_token')
  const valid = !!token && token !== 'undefined' && token !== 'null'
  if (!valid) return next('/login')

  if (to.meta.roles) {
    try {
      const raw = localStorage.getItem('shulepay_user')
      const user = raw ? JSON.parse(raw) : null
      if (user?.role && user.role !== 'superadmin' && !to.meta.roles.includes(user.role)) return next('/dashibodi')
    } catch { }
  }
  next()
})

export default router
