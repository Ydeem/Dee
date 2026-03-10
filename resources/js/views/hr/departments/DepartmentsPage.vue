<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface DepartmentItem {
  id: number;
  name: string;
  code: string | null;
  description: string | null;
  status: string;
  employees_count: number;
  manager?: { id: number; full_name: string } | null;
  created_at: string;
}

type ManagerItem = {
  id: number;
  full_name: string;
};

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Departments', disabled: true, href: '#' }
];

const loading = ref(true);
const saving = ref(false);
const departments = ref<DepartmentItem[]>([]);
const managers = ref<ManagerItem[]>([]);

const dummyManagers: ManagerItem[] = [
  { id: 1, full_name: 'Pontian Npontu' },
  { id: 2, full_name: 'Sarah Oti' },
  { id: 3, full_name: 'Daniel Kofi' }
];

const dummyDepartments: DepartmentItem[] = [
  {
    id: 1,
    name: 'Human Resources',
    code: 'HR',
    description: 'People operations and employee relations',
    status: 'Active',
    employees_count: 12,
    manager: { id: 1, full_name: 'Pontian Npontu' },
    created_at: '2025-01-10'
  },
  {
    id: 2,
    name: 'Engineering',
    code: 'ENG',
    description: 'Product and platform engineering',
    status: 'Active',
    employees_count: 28,
    manager: { id: 3, full_name: 'Daniel Kofi' },
    created_at: '2025-01-12'
  },
  {
    id: 3,
    name: 'Finance',
    code: 'FIN',
    description: 'Payroll, accounting and budgeting',
    status: 'Active',
    employees_count: 7,
    manager: null,
    created_at: '2025-01-15'
  },
  {
    id: 4,
    name: 'Sales',
    code: 'SAL',
    description: 'Revenue and client acquisition',
    status: 'Inactive',
    employees_count: 0,
    manager: { id: 2, full_name: 'Sarah Oti' },
    created_at: '2025-01-20'
  }
];

const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', status: '' });
const sort = reactive({ sortBy: 'created_at', sortDir: 'desc' });

const drawerOpen = ref(false);
const editingDepartmentId = ref<number | null>(null);
const form = reactive({
  name: '',
  code: '',
  description: '',
  manager_id: null as number | null,
  status: 'Active'
});

const confirmDialog = ref({ show: false, id: null as number | null, name: '' });
const snackbar = ref({ show: false, message: '', color: 'success' });

const stats = ref({
  total: 0,
  active: 0,
  withoutManager: 0
});

const headers = [
  { title: 'Department Name', key: 'name', sortable: true },
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Manager', key: 'manager', sortable: false },
  { title: 'Total Employees', key: 'employees_count', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Created Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

const perPageOptions = [10, 25, 50];
const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: 'Active' },
  { title: 'Inactive', value: 'Inactive' }
];

function rowData(item: any): DepartmentItem {
  return (item?.raw ?? item) as DepartmentItem;
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

function statusColor(status: string) {
  return status === 'Active' ? 'success' : 'secondary';
}

const managerOptions = computed(() => managers.value.map((manager) => ({ title: manager.full_name, value: manager.id })));

async function fetchManagers() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 100 } });
    const list = data?.employees?.data ?? data?.employees ?? [];
    managers.value = list.map((employee: any) => ({
      id: employee.id,
      full_name: employee.full_name ?? `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim()
    }));

    if (!managers.value.length) {
      managers.value = dummyManagers;
    }
  } catch (error) {
    managers.value = dummyManagers;
  }
}

async function fetchDepartments() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/departments', {
      params: {
        search: filters.search || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
        sort_by: sort.sortBy,
        sort_dir: sort.sortDir
      }
    });

    departments.value = data.departments?.data ?? [];
    pagination.total = data.departments?.total ?? 0;

    if (!departments.value.length) {
      departments.value = dummyDepartments;
      pagination.total = dummyDepartments.length;
    }
  } catch (error) {
    departments.value = dummyDepartments;
    pagination.total = dummyDepartments.length;
    snackbar.value = { show: true, message: 'Using dummy department data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

async function fetchStats() {
  try {
    const { data } = await axios.get('/api/hr/departments', { params: { per_page: 999 } });
    const all = data.departments?.data ?? [];
    const source = all.length ? all : dummyDepartments;
    stats.value = {
      total: source.length,
      active: source.filter((item: DepartmentItem) => item.status === 'Active').length,
      withoutManager: source.filter((item: DepartmentItem) => !item.manager).length
    };
  } catch (error) {
    stats.value = {
      total: dummyDepartments.length,
      active: dummyDepartments.filter((item) => item.status === 'Active').length,
      withoutManager: dummyDepartments.filter((item) => !item.manager).length
    };
  }
}

function resetForm() {
  form.name = '';
  form.code = '';
  form.description = '';
  form.manager_id = null;
  form.status = 'Active';
}

function openCreateDrawer() {
  editingDepartmentId.value = null;
  resetForm();
  drawerOpen.value = true;
}

function openEditDrawer(item: DepartmentItem) {
  editingDepartmentId.value = item.id;
  form.name = item.name;
  form.code = item.code ?? '';
  form.description = item.description ?? '';
  form.manager_id = item.manager?.id ?? null;
  form.status = item.status;
  drawerOpen.value = true;
}

function openDeleteDialog(item: DepartmentItem) {
  confirmDialog.value = { show: true, id: item.id, name: item.name };
}

async function saveDepartment() {
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      code: form.code || null,
      description: form.description || null,
      manager_id: form.manager_id,
      status: form.status
    };

    if (editingDepartmentId.value) {
      await axios.put(`/api/hr/departments/${editingDepartmentId.value}`, payload);
      snackbar.value = { show: true, message: 'Department updated successfully.', color: 'success' };
    } else {
      await axios.post('/api/hr/departments', payload);
      snackbar.value = { show: true, message: 'Department created successfully.', color: 'success' };
    }

    drawerOpen.value = false;
    await Promise.all([fetchDepartments(), fetchStats()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save department.',
      color: 'error'
    };
  } finally {
    saving.value = false;
  }
}

async function deleteDepartment() {
  if (!confirmDialog.value.id) return;

  try {
    await axios.delete(`/api/hr/departments/${confirmDialog.value.id}`);
    snackbar.value = { show: true, message: 'Department deleted.', color: 'success' };
    confirmDialog.value.show = false;
    await Promise.all([fetchDepartments(), fetchStats()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Cannot delete a department with active employees',
      color: 'error'
    };
  }
}

function viewEmployees(item: DepartmentItem) {
  router.visit(`/hr/employees?department=${encodeURIComponent(item.name)}`);
}

function resetFilters() {
  filters.search = '';
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

  fetchDepartments();
}

watch(
  () => form.code,
  (value) => {
    form.code = (value ?? '').toUpperCase();
  }
);

watch(
  () => [filters.search, filters.status],
  () => {
    pagination.page = 1;
    fetchDepartments();
  }
);

onMounted(async () => {
  await Promise.all([fetchManagers(), fetchDepartments(), fetchStats()]);
});
</script>

<template>
  <BaseBreadcrumb title="Departments" subtitle="Manage company departments" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Departments</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage company departments</p>
    </div>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDrawer">Add Department</v-btn>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Total Departments: <strong>{{ stats.total }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Active Departments: <strong>{{ stats.active }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Without Manager: <strong>{{ stats.withoutManager }}</strong></v-card-text></v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="8"><v-text-field v-model="filters.search" placeholder="Search by name or code..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" md="4"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details /></v-col>
      </v-row>
      <div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item>
      <div class="d-flex justify-space-between align-center flex-wrap ga-2">
        <p class="text-body-2 text-lightText mb-0">Showing {{ departments.length }} of {{ pagination.total }} departments</p>
      </div>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <v-skeleton-loader v-if="loading && !departments.length" type="table" />

      <v-data-table-server
        v-else
        :headers="headers"
        :items="departments"
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
            <div class="font-weight-medium">{{ rowData(item).name }}</div>
          </div>
        </template>

        <template #item.code="{ item }">
          <v-chip size="small" color="primary" variant="tonal">{{ rowData(item).code ?? '-' }}</v-chip>
        </template>

        <template #item.manager="{ item }">
          <div v-if="rowData(item).manager" class="d-flex align-center ga-2">
            <v-avatar size="28" color="primary" variant="tonal">{{ initials(rowData(item).manager?.full_name || '') }}</v-avatar>
            <span>{{ rowData(item).manager?.full_name }}</span>
          </div>
          <span v-else class="text-lightText">Not Assigned</span>
        </template>

        <template #item.employees_count="{ item }">
          <v-chip size="small" color="secondary" variant="tonal">{{ rowData(item).employees_count }}</v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(rowData(item).status)" variant="tonal">{{ rowData(item).status }}</v-chip>
        </template>

        <template #item.created_at="{ item }">{{ new Date(rowData(item).created_at).toLocaleDateString() }}</template>

        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
            <v-list>
              <v-list-item title="Edit Department" @click="openEditDrawer(rowData(item))" />
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
      <h5 class="text-h5 mb-0">{{ editingDepartmentId ? 'Edit Department' : 'Add Department' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-text-field v-model="form.name" label="Department Name *" variant="outlined" class="mb-3" />
      <v-text-field v-model="form.code" label="Department Code" hint="ENG / HR / FIN" persistent-hint variant="outlined" class="mb-3" />
      <v-textarea v-model="form.description" label="Description" rows="3" variant="outlined" class="mb-3" />
      <v-autocomplete v-model="form.manager_id" :items="managerOptions" label="Department Manager" variant="outlined" class="mb-3" />
      <v-radio-group v-model="form.status" inline>
        <v-radio label="Active" value="Active" />
        <v-radio label="Inactive" value="Inactive" />
      </v-radio-group>
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveDepartment">Save Department</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Department</v-card-title>
      <v-card-text>
        Are you sure you want to delete <strong>{{ confirmDialog.name }}</strong>? This action cannot be undone.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="deleteDepartment">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
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
