export interface menu {
  header?: string;
  title?: string;
  icon?: string;
  to?: string;
  divider?: boolean;
  getURL?: boolean;
  chip?: string;
  chipColor?: string;
  chipVariant?: string;
  chipIcon?: string;
  children?: menu[];
  disabled?: boolean;
  type?: string;
  subCaption?: string;
}

const sidebarItem: menu[] = [
  { header: 'OVERVIEW' },
  {
    title: 'Dashboard',
    icon: 'custom-home',
    to: '/dashboard'
  },
  { header: 'WORKFORCE' },
  {
    title: 'Employees',
    icon: 'custom-users',
    to: '/hr/employees'
  },
  {
    title: 'Departments',
    icon: 'custom-building-outline',
    to: '/hr/departments'
  },
  {
    title: 'Designations',
    icon: 'custom-security-safe',
    to: '/hr/designations'
  },
  { header: 'TIME & ATTENDANCE' },
  {
    title: 'Attendance',
    icon: 'custom-calendar-outline',
    to: '/hr/attendance'
  },
  {
    title: 'Leave Management',
    icon: 'custom-calendar-1',
    to: '/hr/leave-management'
  },
  {
    title: 'Shifts & Schedules',
    icon: 'custom-clock-outline',
    to: '/hr/shifts'
  },
  { header: 'RECRUITMENT' },
  {
    title: 'Job Openings',
    icon: 'custom-bag',
    to: '/hr/job-openings'
  },
  {
    title: 'Applicants',
    icon: 'custom-user-add',
    to: '/hr/applicants'
  },
  {
    title: 'Onboarding',
    icon: 'custom-clipboard',
    to: '/hr/onboarding'
  },
  { header: 'PAYROLL' },
  {
    title: 'Payroll',
    icon: 'custom-dollar-fill',
    to: '/hr/payroll'
  },
  {
    title: 'Expenses',
    icon: 'custom-payment-outline',
    to: '/hr/expenses'
  },
  {
    title: 'Reports',
    icon: 'custom-graph-outline',
    to: '/hr/reports'
  },
  { header: 'SETTINGS' },
  {
    title: 'HR Settings',
    icon: 'custom-setting-outline',
    to: '/hr/settings'
  },
  {
    title: 'Roles & Permissions',
    icon: 'custom-shield',
    to: '/hr/roles-permissions'
  }
];

export default sidebarItem;
