import { createRouter, createWebHistory } from 'vue-router'

const hrRoutes = [
  {
    path: '/hr/dashboard',
    name: 'hr.dashboard',
    component: () => import('@/Pages/HR/Dashboard/Index.vue')
  },
  {
    path: '/hr/employees',
    name: 'hr.employees',
    component: () => import('@/Pages/HR/Employees/Index.vue')
  },
  {
    path: '/hr/employees/create',
    name: 'hr.employees.create',
    component: () => import('@/Pages/HR/Employees/Create.vue')
  },
  {
    path: '/hr/employees/:id/edit',
    name: 'hr.employees.edit',
    component: () => import('@/Pages/HR/Employees/Edit.vue')
  },
  {
    path: '/hr/employees/:id',
    name: 'hr.employees.show',
    component: () => import('@/Pages/HR/Employees/Show.vue')
  },
  {
    path: '/hr/departments',
    name: 'hr.departments',
    component: () => import('@/views/hr/departments/DepartmentsPage.vue')
  },
  {
    path: '/hr/designations',
    name: 'hr.designations',
    component: () => import('@/views/hr/designations/DesignationsPage.vue')
  },
  {
    path: '/hr/attendance',
    name: 'hr.attendance',
    component: () => import('@/views/hr/attendance/AttendancePage.vue')
  },
  {
    path: '/hr/leave-management',
    name: 'hr.leave-management',
    component: () => import('@/views/hr/leave/LeaveManagementPage.vue')
  },
  {
    path: '/hr/shifts',
    name: 'hr.shifts',
    component: () => import('@/views/hr/shifts/ShiftsPage.vue')
  },
  {
    path: '/hr/job-openings',
    name: 'hr.job-openings',
    component: () => import('@/views/hr/recruitment/JobOpeningsPage.vue')
  },
  {
    path: '/hr/applicants',
    name: 'hr.applicants',
    component: () => import('@/views/hr/recruitment/ApplicantsPage.vue')
  },
  {
    path: '/hr/onboarding',
    name: 'hr.onboarding',
    component: () => import('@/views/hr/recruitment/OnboardingPage.vue')
  },
  {
    path: '/hr/payroll',
    name: 'hr.payroll',
    component: () => import('@/views/hr/payroll/PayrollPage.vue')
  },
  {
    path: '/hr/payroll/:id/payslip',
    name: 'hr.payroll.payslip',
    component: () => import('@/views/hr/payroll/PayslipPage.vue')
  }
]

export default hrRoutes
