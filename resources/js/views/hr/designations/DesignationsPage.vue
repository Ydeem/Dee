<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface DesignationItem {
  id: number;
  name: string;
  department?: { id: number; name: string } | null;
  level: string | null;
  description: string | null;
  status: string;
  employees_count: number;
  created_at: string;
}

interface DepartmentOption {
  id: number;
  name: string;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Designations', disabled: true, href: '#' }
];

const levelValues = ['Junior', 'Mid-level', 'Senior', 'Lead', 'Manager', 'Director', 'C-Level'];

const loading = ref(true);
const saving = ref(false);

const designations = ref<DesignationItem[]>([]);
const departmentFilterNames = ref<string[]>([]);
const departmentOptions = ref<DepartmentOption[]>([]);

const dummyDepartmentOptions: DepartmentOption[] = [
  { id: 1, name: 'Human Resources' },
  { id: 2, name: 'Engineering' },
  { id: 3, name: 'Finance' },
  { id: 4, name: 'Sales' },
  { id: 5, name: 'Marketing' },
  { id: 6, name: 'Operations' }
];

const dummyDesignations: DesignationItem[] = [
  {
    id: 1,
    name: 'HR Manager',
    department: { id: 1, name: 'Human Resources' },
    level: 'Manager',
    description: 'Leads HR team and operations.',
    status: 'Active',
    employees_count: 3,
    created_at: '2026-01-10'
  },
  {
    id: 2,
    name: 'Software Engineer',
    department: { id: 2, name: 'Engineering' },
    level: 'Mid-level',
    description: 'Builds and maintains product features.',
    status: 'Active',
    employees_count: 18,
    created_at: '2026-01-12'
  },
  {
    id: 3,
    name: 'Finance Officer',
    department: { id: 3, name: 'Finance' },
    level: 'Mid-level',
    description: 'Supports finance and compliance processes.',
    status: 'Active',
    employees_count: 4,
    created_at: '2026-01-15'
  },
  {
    id: 4,
    name: 'Sales Executive',
    department: { id: 4, name: 'Sales' },
    level: 'Junior',
    description: 'Owns customer pipeline and outreach.',
    status: 'Inactive',
    employees_count: 0,
    created_at: '2026-01-20'
  },
  {
    id: 5,
    name: 'Chief Executive',
    department: { id: 6, name: 'Operations' },
    level: 'C-Level',
    description: 'Exec leadership and strategy owner.',
    status: 'Active',
    employees_count: 1,
    created_at: '2026-01-22'
  }
];

const stats = ref({
  total: 0,
  active: 0,
  unassigned: 0
});

const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({
  search: '',
  department: '',
  level: '',
  status: ''
});
const sort = reactive({ sortBy: 'created_at', sortDir: 'desc' });

const drawerOpen = ref(false);
const editingId = ref<number | null>(null);
const form = reactive({
  name: '',
  department_id: null as number | null,
  level: '' as string | '',
  description: '',
  status: 'Active'
});
const fieldErrors = reactive({
  name: ''
});

const confirmDialog = ref({
  show: false,
  id: null as number | null,
  name: ''
});

const snackbar = ref({ show: false, message: '', color: 'success' });

const headers = [
  { title: 'Designation Name', key: 'name', sortable: true },
  { title: 'Department', key: 'department', sortable: false },
  { title: 'Level', key: 'level', sortable: true },
  { title: 'Total Employees', key: 'employees_count', sortable: true },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

const perPageOptions = [10, 25, 50];
const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: 'Active' },
  { title: 'Inactive', value: 'Inactive' }
];
const levelFilterOptions = computed(() => [{ title: 'All Levels', value: '' }, ...levelValues.map((level) => ({ title: level, value: level }))]);
const departmentFilterOptions = computed(() => [{ title: 'All Departments', value: '' }, ...departmentFilterNames.value.map((name) => ({ title: name, value: name }))]);
const drawerDepartmentOptions = computed(() => departmentOptions.value.map((item) => ({ title: item.name, value: item.id })));
const drawerLevelOptions = computed(() => levelValues.map((level) => ({ title: level, value: level })));

function rowData(item: any): DesignationItem {
  return (item?.raw ?? item) as DesignationItem;
}

function levelColor(level: string | null) {
  if (!level) return 'secondary';
  if (level === 'Junior') return 'primary';
  if (level === 'Mid-level') return 'teal';
  if (level === 'Senior') return 'success';
  if (level === 'Lead') return 'warning';
  if (level === 'Manager') return 'purple';
  if (level === 'Director') return 'error';
  if (level === 'C-Level') return 'grey-darken-4';
  return 'secondary';
}

function statusColor(status: string) {
  return status === 'Active' ? 'success' : 'secondary';
}

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

function clearFieldErrors() {
  fieldErrors.name = '';
}

async function fetchDepartmentOptions() {
  try {
    const { data } = await axios.get('/api/hr/departments', { params: { per_page: 200 } });
    const list = data?.departments?.data ?? [];
    departmentOptions.value = list.map((item: any) => ({ id: item.id, name: item.name }));
    if (!departmentOptions.value.length) {
      departmentOptions.value = dummyDepartmentOptions;
    }
  } catch (error) {
    departmentOptions.value = dummyDepartmentOptions;
  }
}

async function fetchDesignations() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/designations', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        level: filters.level || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
        sort_by: sort.sortBy,
        sort_dir: sort.sortDir
      }
    });

    designations.value = data?.designations?.data ?? [];
    pagination.total = data?.designations?.total ?? 0;

    const apiDeptNames: string[] = data?.departments ?? [];
    departmentFilterNames.value = apiDeptNames.length
      ? apiDeptNames
      : Array.from(new Set(dummyDesignations.map((item) => item.department?.name).filter(Boolean) as string[]));

    if (!designations.value.length) {
      designations.value = dummyDesignations;
      pagination.total = dummyDesignations.length;
      if (!departmentFilterNames.value.length) {
        departmentFilterNames.value = Array.from(new Set(dummyDesignations.map((item) => item.department?.name).filter(Boolean) as string[]));
      }
    }
  } catch (error) {
    designations.value = dummyDesignations;
    pagination.total = dummyDesignations.length;
    departmentFilterNames.value = Array.from(new Set(dummyDesignations.map((item) => item.department?.name).filter(Boolean) as string[]));
    snackbar.value = { show: true, message: 'Using dummy designation data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

async function fetchStats() {
  try {
    const { data } = await axios.get('/api/hr/designations', { params: { per_page: 999 } });
    const list = data?.designations?.data ?? [];
    const source: DesignationItem[] = list.length ? list : dummyDesignations;

    stats.value = {
      total: source.length,
      active: source.filter((item) => item.status === 'Active').length,
      unassigned: source.filter((item) => (item.employees_count ?? 0) === 0).length
    };
  } catch (error) {
    stats.value = {
      total: dummyDesignations.length,
      active: dummyDesignations.filter((item) => item.status === 'Active').length,
      unassigned: dummyDesignations.filter((item) => item.employees_count === 0).length
    };
  }
}

function resetForm() {
  clearFieldErrors();
  form.name = '';
  form.department_id = null;
  form.level = '';
  form.description = '';
  form.status = 'Active';
}

function openCreateDrawer() {
  editingId.value = null;
  resetForm();
  drawerOpen.value = true;
}

function openEditDrawer(item: DesignationItem) {
  editingId.value = item.id;
  clearFieldErrors();
  form.name = item.name;
  form.department_id = item.department?.id ?? null;
  form.level = item.level ?? '';
  form.description = item.description ?? '';
  form.status = item.status;
  drawerOpen.value = true;
}

function openDeleteDialog(item: DesignationItem) {
  confirmDialog.value = { show: true, id: item.id, name: item.name };
}

async function saveDesignation() {
  clearFieldErrors();

  if (!form.name.trim()) {
    fieldErrors.name = 'Designation name is required.';
    return;
  }

  saving.value = true;
  try {
    const payload = {
      name: form.name.trim(),
      department_id: form.department_id,
      level: form.level || null,
      description: form.description || null,
      status: form.status
    };

    if (editingId.value) {
      await axios.put(`/api/hr/designations/${editingId.value}`, payload);
      snackbar.value = { show: true, message: 'Designation updated successfully.', color: 'success' };
    } else {
      await axios.post('/api/hr/designations', payload);
      snackbar.value = { show: true, message: 'Designation created successfully.', color: 'success' };
    }

    drawerOpen.value = false;
    await Promise.all([fetchDesignations(), fetchStats()]);
  } catch (error: any) {
    const apiErrors = error?.response?.data?.errors;
    if (apiErrors?.name?.length) {
      fieldErrors.name = apiErrors.name[0];
    }

    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save designation.',
      color: 'error'
    };
  } finally {
    saving.value = false;
  }
}

async function deleteDesignation() {
  if (!confirmDialog.value.id) return;

  try {
    await axios.delete(`/api/hr/designations/${confirmDialog.value.id}`);
    snackbar.value = { show: true, message: 'Designation deleted.', color: 'success' };
    confirmDialog.value.show = false;
    await Promise.all([fetchDesignations(), fetchStats()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Cannot delete a designation assigned to employees.',
      color: 'error'
    };
  }
}

function viewEmployees(item: DesignationItem) {
  router.visit(`/hr/employees?designation=${encodeURIComponent(item.name)}`);
}

function resetFilters() {
  filters.search = '';
  filters.department = '';
  filters.level = '';
  filters.status = '';
}

function handleTableOptions(options: any) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;

  if (options.sortBy?.length) {
    sort.sortBy = options.sortBy[0].key;
    sort.sortDir = options.sortBy[0].order ?? 'asc';
  } else {
    sort.sortBy = 'created_at';
    sort.sortDir = 'desc';
  }

  fetchDesignations();
}

watch(
  () => [filters.search, filters.department, filters.level, filters.status],
  () => {
    pagination.page = 1;
    fetchDesignations();
  }
);

onMounted(async () => {
  await Promise.all([fetchDepartmentOptions(), fetchDesignations(), fetchStats()]);
});
</script>

<template>
  <BaseBreadcrumb title="Designations" subtitle="Manage job titles and roles" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Designations</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage job titles and roles</p>
    </div>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDrawer">Add Designation</v-btn>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Total Designations: <strong>{{ stats.total }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Active Designations: <strong>{{ stats.active }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Unassigned Designations: <strong>{{ stats.unassigned }}</strong></v-card-text></v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by designation name..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="3"><v-select v-model="filters.department" :items="departmentFilterOptions" label="Department" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="3"><v-select v-model="filters.level" :items="levelFilterOptions" label="Level" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details /></v-col>
      </v-row>
      <div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item>
      <div class="d-flex justify-space-between align-center flex-wrap ga-2">
        <p class="text-body-2 text-lightText mb-0">Showing {{ designations.length }} of {{ pagination.total }} designations</p>
      </div>
    </v-card-item>

    <v-divider />

    <v-card-text>
      <v-skeleton-loader v-if="loading && !designations.length" type="table" />

      <v-data-table-server
        v-else
        :headers="headers"
        :items="designations"
        :items-length="pagination.total"
        :items-per-page="pagination.perPage"
        :page="pagination.page"
        :items-per-page-options="perPageOptions"
        item-value="id"
        @update:options="handleTableOptions"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center ga-3">
            <v-avatar color="primary" variant="tonal" size="34">{{ initials(rowData(item).name) }}</v-avatar>
            <div class="d-flex align-center ga-2 flex-wrap">
              <span class="font-weight-medium">{{ rowData(item).name }}</span>
              <v-chip v-if="rowData(item).level" size="x-small" :color="levelColor(rowData(item).level)" variant="tonal">{{ rowData(item).level }}</v-chip>
            </div>
          </div>
        </template>

        <template #item.department="{ item }">
          <v-chip size="small" :color="rowData(item).department ? 'primary' : 'secondary'" variant="tonal">{{ rowData(item).department?.name ?? 'Not Assigned' }}</v-chip>
        </template>

        <template #item.level="{ item }">
          <v-chip size="small" :color="levelColor(rowData(item).level)" variant="tonal">{{ rowData(item).level ?? '-' }}</v-chip>
        </template>

        <template #item.employees_count="{ item }">
          <v-chip size="small" color="secondary" variant="tonal" class="cursor-pointer" @click="viewEmployees(rowData(item))">{{ rowData(item).employees_count }}</v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(rowData(item).status)" variant="tonal">{{ rowData(item).status }}</v-chip>
        </template>

        <template #item.created_at="{ item }">{{ new Date(rowData(item).created_at).toLocaleDateString() }}</template>

        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
            <v-list>
              <v-list-item title="Edit Designation" @click="openEditDrawer(rowData(item))" />
              <v-list-item title="View Employees" @click="viewEmployees(rowData(item))" />
              <v-list-item title="Delete" base-color="error" @click="openDeleteDialog(rowData(item))" />
            </v-list>
          </v-menu>
        </template>
      </v-data-table-server>
    </v-card-text>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="480">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <h5 class="text-h5 mb-0">{{ editingId ? 'Edit Designation' : 'Add Designation' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-text-field v-model="form.name" label="Designation Name *" variant="outlined" :error-messages="fieldErrors.name ? [fieldErrors.name] : []" class="mb-3" />
      <v-select v-model="form.department_id" :items="drawerDepartmentOptions" label="Department" variant="outlined" clearable class="mb-3" />
      <v-select v-model="form.level" :items="drawerLevelOptions" label="Level" variant="outlined" clearable class="mb-3" />
      <v-textarea v-model="form.description" label="Description" rows="3" variant="outlined" class="mb-3" />
      <v-radio-group v-model="form.status" inline>
        <v-radio label="Active" value="Active" />
        <v-radio label="Inactive" value="Inactive" />
      </v-radio-group>
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveDesignation">Save Designation</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Designation</v-card-title>
      <v-card-text>
        Are you sure you want to delete <strong>{{ confirmDialog.name }}</strong>? This cannot be undone.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="deleteDesignation">Delete</v-btn>
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

.drawer-body {
  height: calc(100% - 130px);
  overflow-y: auto;
}

.sticky-footer {
  position: sticky;
  bottom: 0;
  background: #fff;
}

.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.border-t {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}
</style>
