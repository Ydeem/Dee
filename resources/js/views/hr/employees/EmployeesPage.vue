<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue';
import { usePermissions } from '@/composables/usePermissions';
import { appUrl } from '@/utils/appUrl';

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

const { can } = usePermissions();
const canViewEmployees = computed(() => can('view employees'));
const canCreateEmployees = computed(() => can('create employees'));
const canEditEmployees = computed(() => can('edit employees'));
const canDeleteEmployees = computed(() => can('delete employees'));

const loading = ref(true);
const viewMode = ref<'table' | 'grid'>('table');
const employees = ref<EmployeeItem[]>([]);
const selectedEmployeeIds = ref<number[]>([]);
const importDialog = ref(false);
const importFile = ref<File | null>(null);
const importing = ref(false);

const departments = ref<OptionItem[]>([]);
const designations = ref<OptionItem[]>([]);

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
const lastTableOptionsKey = ref('');

const confirmDialog = ref({
  show: false,
  title: '',
  message: '',
  action: '' as 'delete' | 'status' | '',
  employeeId: null as number | null,
  statusValue: ''
});

const snackbar = ref({ show: false, message: '', color: 'success' });
const avatarLoadFailures = ref<number[]>([]);

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

function hasDisplayAvatar(employee: EmployeeItem) {
  return Boolean(employee.avatar_url) && !avatarLoadFailures.value.includes(employee.id);
}

function onAvatarLoadError(employee: EmployeeItem) {
  if (!avatarLoadFailures.value.includes(employee.id)) {
    avatarLoadFailures.value.push(employee.id);
  }
}

function rowData(item: any): EmployeeItem {
  return (item?.raw ?? item) as EmployeeItem;
}

function openAddEmployeePage() {
  router.visit(appUrl('/hr/employees/create'));
}

function openImportDialog() {
  importDialog.value = true;
}

function openEditEmployeePage(employee: EmployeeItem) {
  router.visit(appUrl(`/hr/employees/${employee.id}/edit`));
}

function viewProfile(employee: EmployeeItem) {
  router.visit(appUrl(`/hr/employees/${employee.id}`));
}

async function fetchOptions() {
  try {
    const { data } = await axios.get('/api/hr/employees/options');
    departments.value = data.departments ?? [];
    designations.value = data.designations ?? [];
  } catch (error) {
    departments.value = [];
    designations.value = [];
    snackbar.value = { show: true, message: 'Failed to load filter options.', color: 'error' };
  }
}

async function fetchEmployees() {
  loading.value = true;
  avatarLoadFailures.value = [];
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

    const rows = Array.isArray(data?.employees?.data)
      ? data.employees.data
      : Array.isArray(data?.data?.data)
        ? data.data.data
        : [];

    employees.value = rows.map((employee: EmployeeItem) => ({
      ...employee,
      full_name: employee.full_name || `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim(),
      avatar_url: employee.avatar_url ?? null
    }));
    pagination.total = Number(data?.employees?.total ?? data?.data?.total ?? 0);

    if ((data.filters?.departments ?? []).length && !departments.value.length) {
      departments.value = data.filters.departments.map((name: string, index: number) => ({ id: index + 1, name }));
    }

    if ((data.filters?.designations ?? []).length && !designations.value.length) {
      designations.value = data.filters.designations.map((name: string, index: number) => ({ id: index + 1, name }));
    }
  } catch (error) {
    employees.value = [];
    pagination.total = 0;
    snackbar.value = { show: true, message: 'Failed to load employees.', color: 'error' };
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
  const nextPage = options.page;
  const nextPerPage = options.itemsPerPage;
  let nextSortBy = 'created_at';
  let nextSortDir = 'desc';
  if (options.sortBy?.length) {
    const selectedKey = options.sortBy[0].key;
    nextSortBy = selectedKey === 'full_name' ? 'first_name' : selectedKey;
    nextSortDir = options.sortBy[0].order ?? 'asc';
  }

  const nextKey = JSON.stringify({
    page: nextPage,
    perPage: nextPerPage,
    sortBy: nextSortBy,
    sortDir: nextSortDir
  });

  if (lastTableOptionsKey.value === nextKey) {
    return;
  }

  lastTableOptionsKey.value = nextKey;
  pagination.page = nextPage;
  pagination.perPage = nextPerPage;
  sort.sortBy = nextSortBy;
  sort.sortDir = nextSortDir;

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

async function handleImport() {
  if (!importFile.value) return;

  importing.value = true;
  const formData = new FormData();
  formData.append('file', importFile.value);

  try {
    const { data } = await axios.post('/api/hr/employees/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success'
    };
    importDialog.value = false;
    importFile.value = null;
    fetchEmployees();

    if (data.errors?.length) {
      console.warn('Import errors:', data.errors);
    }
  } catch (error) {
    snackbar.value = {
      show: true,
      message: 'Import failed.',
      color: 'error'
    };
  } finally {
    importing.value = false;
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

  // PDF print — avoid any literal script-closing sequence in this block.
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

  <UnauthorizedPage v-if="!canViewEmployees" />

  <template v-else>
  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Employees</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage your workforce</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-upload" @click="openImportDialog">Import Employees</v-btn>
      <v-btn v-if="canCreateEmployees" color="primary" prepend-icon="mdi-plus" @click="openAddEmployeePage">Add Employee</v-btn>
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
        <div
          v-if="!loading && employees.length === 0"
          class="text-center py-16"
        >
          <v-icon size="64" color="grey-lighten-2">
            mdi-account-group-outline
          </v-icon>
          <p class="text-h6 text-medium-emphasis mt-4">
            No employees yet
          </p>
          <p class="text-body-2 text-medium-emphasis mb-6">
            Get started by adding your first employee
          </p>
          <v-btn
            v-if="canCreateEmployees"
            color="primary"
            variant="flat"
            prepend-icon="mdi-plus"
            @click="router.visit(appUrl('/hr/employees/create'))"
          >
            Add First Employee
          </v-btn>
        </div>

        <v-data-table-server
          v-else-if="viewMode === 'table'"
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
                <img
                  v-if="hasDisplayAvatar(rowData(item))"
                  :src="rowData(item).avatar_url || ''"
                  :alt="rowData(item).full_name"
                  @error="onAvatarLoadError(rowData(item))"
                />
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
              <template #activator="{ props }">
                <v-btn icon variant="text" v-bind="props">
                  <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                </v-btn>
              </template>
              <v-list>
                <v-list-item title="View Profile" @click="viewProfile(rowData(item))" />
                <v-list-item v-if="canEditEmployees" title="Edit Employee" @click="openEditEmployeePage(rowData(item))" />
                <v-list-item v-if="canEditEmployees" title="Assign to Department" @click="openEditEmployeePage(rowData(item))" />
                <v-list-item :title="rowData(item).employment_status === 'Active' ? 'Deactivate' : 'Activate'" @click="askStatus(rowData(item))" />
                <v-list-item v-if="canDeleteEmployees" title="Delete" base-color="error" @click="askDelete(rowData(item))" />
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>

        <v-row v-else-if="viewMode === 'grid'">
          <v-col v-for="employee in employees" :key="employee.id" cols="12" sm="6" md="4" xl="3">
            <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0">
              <v-card-text>
                <div class="d-flex justify-space-between align-start">
                  <v-avatar color="primary" variant="tonal" size="54">
                    <img
                      v-if="hasDisplayAvatar(employee)"
                      :src="employee.avatar_url || ''"
                      :alt="employee.full_name"
                      @error="onAvatarLoadError(employee)"
                    />
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

  <v-dialog v-model="importDialog" max-width="480">
    <v-card>
      <v-card-title>Import Employees</v-card-title>
      <v-card-text>
        <p class="mb-4 text-body-2">
          Upload a CSV file with columns:
          first_name, last_name, personal_email,
          phone, department, designation,
          employment_type, employment_status, join_date
        </p>
        <v-file-input
          v-model="importFile"
          label="Choose CSV file"
          accept=".csv"
          variant="outlined"
          prepend-icon="mdi-file-delimited"
        />
        <v-btn
          text
          color="primary"
          :href="appUrl('/imports/employee-import-template.csv')"
          download
        >
          Download CSV Template
        </v-btn>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="importDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="importing" @click="handleImport">Import</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
  </template>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
