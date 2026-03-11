const hrRoutes = [
  {
    path: '/hr/dashboard',
    name: 'hr.dashboard',
    component: () => import('@/Pages/HR/Dashboard/Index.vue'),
    meta: { permission: 'view hr dashboard' }
  },
  {
    path: '/hr/employees',
    name: 'hr.employees',
    component: () => import('@/Pages/HR/Employees/Index.vue'),
    meta: { permission: 'view employees' }
  },
  {
    path: '/hr/employees/create',
    name: 'hr.employees.create',
    component: () => import('@/Pages/HR/Employees/Create.vue'),
    meta: { permission: 'create employees' }
  },
  {
    path: '/hr/employees/:id/edit',
    name: 'hr.employees.edit',
    component: () => import('@/Pages/HR/Employees/Edit.vue'),
    meta: { permission: 'edit employees' }
  },
  {
    path: '/hr/employees/:id',
    name: 'hr.employees.show',
    component: () => import('@/Pages/HR/Employees/Show.vue'),
    meta: { permission: 'view employees' }
  },
  {
    path: '/hr/departments',
    name: 'hr.departments',
    component: () => import('@/views/hr/departments/DepartmentsPage.vue'),
    meta: { permission: 'view departments' }
  },
  {
    path: '/hr/designations',
    name: 'hr.designations',
    component: () => import('@/views/hr/designations/DesignationsPage.vue'),
    meta: { permission: 'view designations' }
  },
  {
    path: '/hr/attendance',
    name: 'hr.attendance',
    component: () => import('@/views/hr/attendance/AttendancePage.vue'),
    meta: { permission: 'view attendance' }
  },
  {
    path: '/hr/leave-management',
    name: 'hr.leave-management',
    component: () => import('@/views/hr/leave/LeaveManagementPage.vue'),
    meta: { permission: 'view leave requests' }
  },
  {
    path: '/hr/shifts',
    name: 'hr.shifts',
    component: () => import('@/views/hr/shifts/ShiftsPage.vue'),
    meta: { permission: 'view shifts' }
  },
  {
    path: '/hr/job-openings',
    name: 'hr.job-openings',
    component: () => import('@/views/hr/recruitment/JobOpeningsPage.vue'),
    meta: { permission: 'view job openings' }
  },
  {
    path: '/hr/applicants',
    name: 'hr.applicants',
    component: () => import('@/views/hr/recruitment/ApplicantsPage.vue'),
    meta: { permission: 'view applicants' }
  },
  {
    path: '/hr/onboarding',
    name: 'hr.onboarding',
    component: () => import('@/views/hr/recruitment/OnboardingPage.vue'),
    meta: { permission: 'view onboarding' }
  },
  {
    path: '/hr/payroll',
    name: 'hr.payroll',
    component: () => import('@/views/hr/payroll/PayrollPage.vue'),
    meta: { permission: 'view payroll' }
  },
  {
    path: '/hr/payroll/:id/payslip',
    name: 'hr.payroll.payslip',
    component: () => import('@/views/hr/payroll/PayslipPage.vue'),
    meta: { permission: 'view payslips' }
  },
  {
    path: '/hr/expenses',
    name: 'hr.expenses',
    component: () => import('@/views/hr/expenses/ExpensesPage.vue'),
    meta: { permission: 'view expenses' }
  },
  {
    path: '/hr/reports',
    name: 'hr.reports',
    component: () => import('@/views/hr/reports/ReportsPage.vue'),
    meta: { permission: 'view reports' }
  },
  {
    path: '/hr/settings',
    name: 'hr.settings',
    component: () => import('@/views/hr/settings/HRSettingsPage.vue'),
    meta: { permission: 'view hr settings' }
  },
  {
    path: '/hr/roles-permissions',
    name: 'hr.roles-permissions',
    component: () => import('@/views/hr/settings/RolesPermissionsPage.vue'),
    meta: { permission: 'manage roles' }
  }
]

export default hrRoutes
