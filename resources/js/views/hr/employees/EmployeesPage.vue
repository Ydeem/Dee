<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface EmployeeItem {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  employee_id: string;
  department?: { id: number; name: string } | null;
  designation?: { id: number; name: string } | null;
  employment_type: string;
  join_date: string | null;
  employment_status: string;
  personal_email: string;
  phone: string;
  avatar_url?: string | null;
}

interface OptionItem {
  id: number;
  name: string;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Employees', disabled: true, href: '#' }
];

const loading = ref(true);
const viewMode = ref<'table' | 'grid'>('table');
const employees = ref<EmployeeItem[]>([]);
const selectedEmployeeIds = ref<number[]>([]);

const departments = ref<OptionItem[]>([]);
const designations = ref<OptionItem[]>([]);

const dummyDepartments: OptionItem[] = [
  { id: 1, name: 'People Operations' },
  { id: 2, name: 'Engineering' },
  { id: 3, name: 'Finance' },
  { id: 4, name: 'Sales' }
];

const dummyDesignations: OptionItem[] = [
  { id: 1, name: 'HR Manager' },
  { id: 2, name: 'Software Engineer' },
  { id: 3, name: 'Payroll Specialist' },
  { id: 4, name: 'Sales Executive' }
];

const dummyEmployees: EmployeeItem[] = [
  {
    id: 1,
    first_name: 'Pontian',
    last_name: 'Npontu',
    full_name: 'Pontian Npontu',
    employee_id: 'EMP00001',
    department: { id: 1, name: 'People Operations' },
    designation: { id: 1, name: 'HR Manager' },
    employment_type: 'Full-time',
    join_date: '2024-01-08',
    employment_status: 'Active',
    personal_email: 'pontian.npontu@example.com',
    phone: '+233240000001',
    avatar_url: null
  },
  {
    id: 2,
    first_name: 'Sarah',
    last_name: 'Oti',
    full_name: 'Sarah Oti',
    employee_id: 'EMP00002',
    department: { id: 1, name: 'People Operations' },
    designation: { id: 1, name: 'HR Manager' },
    employment_type: 'Full-time',
    join_date: '2026-03-03',
    employment_status: 'Probation',
    personal_email: 'sarah.oti@example.com',
    phone: '+233240000002',
    avatar_url: null
  },
  {
    id: 3,
    first_name: 'Daniel',
    last_name: 'Kofi',
    full_name: 'Daniel Kofi',
    employee_id: 'EMP00003',
    department: { id: 2, name: 'Engineering' },
    designation: { id: 2, name: 'Software Engineer' },
    employment_type: 'Full-time',
    join_date: '2025-10-14',
    employment_status: 'Active',
    personal_email: 'daniel.kofi@example.com',
    phone: '+233240000003',
    avatar_url: null
  },
  {
    id: 4,
    first_name: 'Amanda',
    last_name: 'Boateng',
    full_name: 'Amanda Boateng',
    employee_id: 'EMP00004',
    department: { id: 3, name: 'Finance' },
    designation: { id: 3, name: 'Payroll Specialist' },
    employment_type: 'Contract',
    join_date: '2025-05-01',
    employment_status: 'On Leave',
    personal_email: 'amanda.boateng@example.com',
    phone: '+233240000004',
    avatar_url: null
  },
  {
    id: 5,
    first_name: 'Michael',
    last_name: 'Adu',
    full_name: 'Michael Adu',
    employee_id: 'EMP00005',
    department: { id: 4, name: 'Sales' },
    designation: { id: 4, name: 'Sales Executive' },
    employment_type: 'Part-time',
    join_date: '2025-11-20',
    employment_status: 'Inactive',
    personal_email: 'michael.adu@example.com',
    phone: '+233240000005',
    avatar_url: null
  }
];

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  department: '',
  designation: '',
  type: '',
  status: ''
});

const sort = reactive({
  sortBy: 'created_at',
  sortDir: 'desc'
});

const confirmDialog = ref({
  show: false,
  title: '',
  message: '',
  action: '' as 'delete' | 'status' | '',
  employeeId: null as number | null,
  statusValue: ''
});

const snackbar = ref({ show: false, message: '', color: 'success' });

const perPageOptions = [10, 25, 50];
const employmentTypes = [
  { title: 'All', value: '' },
  { title: 'Full-time', value: 'Full-time' },
  { title: 'Part-time', value: 'Part-time' },
  { title: 'Contract', value: 'Contract' },
  { title: 'Intern', value: 'Intern' }
];
const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: 'Active' },
  { title: 'Inactive', value: 'Inactive' },
  { title: 'On Leave', value: 'On Leave' },
  { title: 'Probation', value: 'Probation' }
];

const tableHeaders = [
  { title: 'Employee', key: 'full_name', sortable: true },
  { title: 'Employee ID', key: 'employee_id', sortable: false },
  { title: 'Department', key: 'department', sortable: true },
  { title: 'Designation', key: 'designation', sortable: false },
  { title: 'Employment Type', key: 'employment_type', sortable: false },
  { title: 'Join Date', key: 'join_date', sortable: true },
  { title: 'Status', key: 'employment_status', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false }
];

const filteredDepartmentOptions = computed(() => [{ title: 'All Departments', value: '' }, ...departments.value.map((item) => ({ title: item.name, value: item.name }))]);
const filteredDesignationOptions = computed(() => [{ title: 'All Designations', value: '' }, ...designations.value.map((item) => ({ title: item.name, value: item.name }))]);

function statusColor(status: string) {
  if (status === 'Active') return 'success';
  if (status === 'On Leave') return 'warning';
  if (status === 'Probation') return 'primary';
  return 'secondary';
}

function getInitials(employee: EmployeeItem) {
  const source = employee.full_name || `${employee.first_name} ${employee.last_name}`;
  return source
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((segment) => segment[0])
    .join('')
    .toUpperCase();
}

function rowData(item: any): EmployeeItem {
  return (item?.raw ?? item) as EmployeeItem;
}

function openAddEmployeePage() {
  router.visit('/hr/employees/create');
}

function openEditEmployeePage(employee: EmployeeItem) {
  router.visit(`/hr/employees/${employee.id}/edit`);
}

function viewProfile(employee: EmployeeItem) {
  router.visit(`/hr/employees/${employee.id}`);
}

async function fetchOptions() {
  try {
    const { data } = await axios.get('/api/hr/employees/options');
    departments.value = data.departments ?? [];
    designations.value = data.designations ?? [];

    if (!departments.value.length) {
      departments.value = dummyDepartments;
    }
    if (!designations.value.length) {
      designations.value = dummyDesignations;
    }
  } catch (error) {
    departments.value = dummyDepartments;
    designations.value = dummyDesignations;
    snackbar.value = { show: true, message: 'Using dummy filter options.', color: 'warning' };
  }
}

async function fetchEmployees() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/employees', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        designation: filters.designation || undefined,
        type: filters.type || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
        sort_by: sort.sortBy,
        sort_dir: sort.sortDir
      }
    });

    employees.value = data.employees?.data ?? [];
    pagination.total = data.employees?.total ?? 0;

    if (!employees.value.length) {
      employees.value = dummyEmployees;
      pagination.total = dummyEmployees.length;
    }

    if ((data.filters?.departments ?? []).length && !departments.value.length) {
      departments.value = data.filters.departments.map((name: string, index: number) => ({ id: index + 1, name }));
    }

    if ((data.filters?.designations ?? []).length && !designations.value.length) {
      designations.value = data.filters.designations.map((name: string, index: number) => ({ id: index + 1, name }));
    }
  } catch (error) {
    employees.value = dummyEmployees;
    pagination.total = dummyEmployees.length;
    snackbar.value = { show: true, message: 'Using dummy employee data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

function resetFilters() {
  filters.search = '';
  filters.department = '';
  filters.designation = '';
  filters.type = '';
  filters.status = '';
}

function handleTableOptions(options: any) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;

  if (options.sortBy?.length) {
    const selectedKey = options.sortBy[0].key;
    sort.sortBy = selectedKey === 'full_name' ? 'first_name' : selectedKey;
    sort.sortDir = options.sortBy[0].order ?? 'asc';
  } else {
    sort.sortBy = 'created_at';
    sort.sortDir = 'desc';
  }

  fetchEmployees();
}

function askDelete(employee: EmployeeItem) {
  confirmDialog.value = {
    show: true,
    title: 'Delete Employee',
    message: `Delete ${employee.full_name}? This action cannot be undone.`,
    action: 'delete',
    employeeId: employee.id,
    statusValue: ''
  };
}

function askStatus(employee: EmployeeItem) {
  const next = employee.employment_status === 'Active' ? 'Inactive' : 'Active';
  confirmDialog.value = {
    show: true,
    title: `${next === 'Active' ? 'Activate' : 'Deactivate'} Employee`,
    message: `Set ${employee.full_name} to ${next}?`,
    action: 'status',
    employeeId: employee.id,
    statusValue: next
  };
}

async function confirmAction() {
  const current = confirmDialog.value;
  if (!current.employeeId) {
    confirmDialog.value.show = false;
    return;
  }

  try {
    if (current.action === 'delete') {
      await axios.delete(`/api/hr/employees/${current.employeeId}`);
      snackbar.value = { show: true, message: 'Employee deleted.', color: 'success' };
    }

    if (current.action === 'status') {
      await axios.patch(`/api/hr/employees/${current.employeeId}/status`, { status: current.statusValue });
      snackbar.value = { show: true, message: 'Employee status updated.', color: 'success' };
    }

    confirmDialog.value.show = false;
    fetchEmployees();
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Action failed.',
      color: 'error'
    };
  }
}

async function runBulkAction(action: 'activate' | 'deactivate' | 'export') {
  if (!selectedEmployeeIds.value.length) return;

  if (action === 'export') {
    exportSelected('csv');
    return;
  }

  try {
    await axios.post('/api/hr/employees/bulk-action', {
      action,
      employee_ids: selectedEmployeeIds.value
    });
    snackbar.value = { show: true, message: 'Bulk action completed.', color: 'success' };
    fetchEmployees();
  } catch (error) {
    snackbar.value = { show: true, message: 'Bulk action failed.', color: 'error' };
  }
}

function exportRows(rows: EmployeeItem[], format: 'csv' | 'pdf') {
  if (!rows.length) return;

  if (format === 'csv') {
    const headers = [
      'Name',
      'Employee ID',
      'Department',
      'Designation',
      'Type',
      'Join Date',
      'Status'
    ];

    const csvRows = rows.map((item) => [
      item.full_name,
      item.employee_id,
      item.department?.name ?? '-',
      item.designation?.name ?? '-',
      item.employment_type,
      item.join_date ?? '-',
      item.employment_status
    ]);

    const csvContent = [headers, ...csvRows]
      .map((row) =>
        row
          .map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`)
          .join(',')
      )
      .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'employees.csv';
    link.click();
    URL.revokeObjectURL(url);
    return;
  }

  // PDF print — avoid using a template literal that includes a script-closing tag
  // as it breaks the Vue SFC compiler. Use document.write() line by line
  // and call newWindow.print() directly instead of injecting a script tag.
  const tableRows = rows
    .map(
      (item) =>
        '<tr>' +
        '<td>' + item.full_name + '</td>' +
        '<td>' + item.employee_id + '</td>' +
        '<td>' + (item.department?.name ?? '-') + '</td>' +
        '<td>' + (item.designation?.name ?? '-') + '</td>' +
        '<td>' + item.employment_type + '</td>' +
        '<td>' + (item.join_date ?? '-') + '</td>' +
        '<td>' + item.employment_status + '</td>' +
        '</tr>'
    )
    .join('');

  const newWindow = window.open('', '_blank');
  if (newWindow) {
    newWindow.document.open();
    newWindow.document.write(
      '<html>' +
      '<head>' +
      '<title>Employees Export</title>' +
      '<style>' +
      'body { font-family: Arial, sans-serif; padding: 20px; }' +
      'h2 { margin-bottom: 16px; }' +
      'table { border-collapse: collapse; width: 100%; }' +
      'th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }' +
      'th { background-color: #f5f5f5; font-weight: bold; }' +
      'tr:nth-child(even) { background-color: #fafafa; }' +
      '</style>' +
      '</head>' +
      '<body>' +
      '<h2>Employees Export</h2>' +
      '<table>' +
      '<thead>' +
      '<tr>' +
      '<th>Name</th>' +
      '<th>Employee ID</th>' +
      '<th>Department</th>' +
      '<th>Designation</th>' +
      '<th>Type</th>' +
      '<th>Join Date</th>' +
      '<th>Status</th>' +
      '</tr>' +
      '</thead>' +
      '<tbody>' + tableRows + '</tbody>' +
      '</table>' +
      '</body>' +
      '</html>'
    );
    newWindow.document.close();
    newWindow.print();
  }
}

function exportSelected(format: 'csv' | 'pdf') {
  const selectedRows = employees.value.filter((item) => selectedEmployeeIds.value.includes(item.id));
  exportRows(selectedRows, format);
}

function exportAll(format: 'csv' | 'pdf') {
  exportRows(employees.value, format);
}

watch(
  () => [filters.search, filters.department, filters.designation, filters.type, filters.status],
  () => {
    pagination.page = 1;
    fetchEmployees();
  }
);

onMounted(async () => {
  await fetchOptions();
  await fetchEmployees();
});
</script>

<template>
  <BaseBreadcrumb title="Employees" subtitle="Manage your workforce" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Employees</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage your workforce</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-upload">Import Employees</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddEmployeePage">Add Employee</v-btn>
    </div>
  </div>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by name, email, ID..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.department" :items="filteredDepartmentOptions" label="Department" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.designation" :items="filteredDesignationOptions" label="Designation" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select v-model="filters.type" :items="employmentTypes" label="Employment Type" variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details /></v-col>
      </v-row>
      <div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item>
      <div class="d-flex justify-space-between align-center flex-wrap ga-2">
        <div class="d-flex align-center ga-2" v-if="selectedEmployeeIds.length">
          <v-chip color="primary" variant="tonal">{{ selectedEmployeeIds.length }} selected</v-chip>
          <v-btn size="small" variant="outlined" @click="runBulkAction('activate')">Bulk Activate</v-btn>
          <v-btn size="small" variant="outlined" @click="runBulkAction('deactivate')">Bulk Deactivate</v-btn>
          <v-btn size="small" variant="outlined" @click="runBulkAction('export')">Export Selected</v-btn>
        </div>
        <div class="d-flex align-center ga-2 ms-auto">
          <v-menu>
            <template #activator="{ props }">
              <v-btn variant="outlined" prepend-icon="mdi-download" v-bind="props">Export</v-btn>
            </template>
            <v-list>
              <v-list-item title="Export Current (CSV)" @click="exportAll('csv')" />
              <v-list-item title="Export Current (PDF)" @click="exportAll('pdf')" />
            </v-list>
          </v-menu>
          <v-btn :color="viewMode === 'grid' ? 'primary' : 'default'" icon="mdi-view-grid" variant="text" @click="viewMode = 'grid'" />
          <v-btn :color="viewMode === 'table' ? 'primary' : 'default'" icon="mdi-view-list" variant="text" @click="viewMode = 'table'" />
        </div>
      </div>
    </v-card-item>

    <v-divider />

    <v-card-text>
      <v-skeleton-loader v-if="loading && !employees.length" type="table" />

      <template v-else>
        <v-data-table-server
          v-if="viewMode === 'table'"
          v-model="selectedEmployeeIds"
          :headers="tableHeaders"
          :items="employees"
          :items-length="pagination.total"
          :items-per-page="pagination.perPage"
          :page="pagination.page"
          :items-per-page-options="perPageOptions"
          item-value="id"
          show-select
          @update:options="handleTableOptions"
        >
          <template #item.full_name="{ item }">
            <div class="d-flex align-center ga-3 cursor-pointer" @click="viewProfile(rowData(item))">
              <v-avatar color="primary" variant="tonal" size="36">
                <img v-if="rowData(item).avatar_url" :src="rowData(item).avatar_url || ''" :alt="rowData(item).full_name" />
                <span v-else class="text-caption font-weight-bold">{{ getInitials(rowData(item)) }}</span>
              </v-avatar>
              <div>
                <div class="font-weight-medium text-body-1">{{ rowData(item).full_name }}</div>
                <div class="text-caption text-lightText">{{ rowData(item).personal_email }}</div>
              </div>
            </div>
          </template>

          <template #item.department="{ item }">{{ rowData(item).department?.name ?? '-' }}</template>
          <template #item.designation="{ item }">{{ rowData(item).designation?.name ?? '-' }}</template>
          <template #item.join_date="{ item }">{{ rowData(item).join_date ?? '-' }}</template>
          <template #item.employment_status="{ item }">
            <v-chip :color="statusColor(rowData(item).employment_status)" size="small" variant="tonal">{{ rowData(item).employment_status }}</v-chip>
          </template>
          <template #item.actions="{ item }">
            <v-menu>
              <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
              <v-list>
                <v-list-item title="View Profile" @click="viewProfile(rowData(item))" />
                <v-list-item title="Edit Employee" @click="openEditEmployeePage(rowData(item))" />
                <v-list-item title="Assign to Department" @click="openEditEmployeePage(rowData(item))" />
                <v-list-item :title="rowData(item).employment_status === 'Active' ? 'Deactivate' : 'Activate'" @click="askStatus(rowData(item))" />
                <v-list-item title="Delete" base-color="error" @click="askDelete(rowData(item))" />
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>

        <v-row v-else>
          <v-col v-for="employee in employees" :key="employee.id" cols="12" sm="6" md="4" xl="3">
            <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0">
              <v-card-text>
                <div class="d-flex justify-space-between align-start">
                  <v-avatar color="primary" variant="tonal" size="54">
                    <img v-if="employee.avatar_url" :src="employee.avatar_url" :alt="employee.full_name" />
                    <span v-else class="font-weight-bold">{{ getInitials(employee) }}</span>
                  </v-avatar>
                  <v-chip :color="statusColor(employee.employment_status)" size="small" variant="tonal">{{ employee.employment_status }}</v-chip>
                </div>

                <h6 class="text-h6 mt-3 mb-1">{{ employee.full_name }}</h6>
                <p class="text-body-2 text-lightText mb-2">{{ employee.designation?.name ?? 'Not assigned' }}</p>
                <v-chip color="primary" size="small" variant="tonal" class="mb-3">{{ employee.department?.name ?? 'No department' }}</v-chip>

                <div class="d-flex ga-2 mb-3">
                  <v-btn icon="mdi-email" variant="text" size="small" />
                  <v-btn icon="mdi-phone" variant="text" size="small" />
                </div>

                <v-btn block color="primary" variant="outlined" @click="viewProfile(employee)">View Profile</v-btn>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>
    </v-card-text>
  </v-card>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title>
      <v-card-text>{{ confirmDialog.message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="confirmAction">Confirm</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
