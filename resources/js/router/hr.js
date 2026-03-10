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
  }
]

export default hrRoutes
