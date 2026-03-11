<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface ManagerPayload {
  id: number;
  name: string;
  avatar: string | null;
  initials: string;
}

interface DepartmentItem {
  id: number;
  name: string;
  code: string;
  description: string | null;
  status: 'Active' | 'Inactive';
  color: string | null;
  initials: string;
  employee_count: number;
  created_at: string;
  manager: ManagerPayload | null;
}

interface ManagerOption {
  id: number;
  name: string;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Departments', disabled: true, href: '#' }
];

const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const drawer = ref(false);
const assignManagerDialog = ref(false);
const deleteDialog = ref(false);
const editingDept = ref<DepartmentItem | null>(null);
const assigningDept = ref<DepartmentItem | null>(null);
const deletingDept = ref<DepartmentItem | null>(null);
const selectedManager = ref<number | null>(null);
const departments = ref<DepartmentItem[]>([]);
const managerOptions = ref<ManagerOption[]>([]);
const lastTableOptionsKey = ref('');

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  status: ''
});

const sort = reactive({
  sortBy: 'name',
  sortDir: 'asc'
});

const stats = ref({
  total: 0,
  active: 0,
  withoutManager: 0
});

const form = reactive({
  name: '',
  code: '',
  description: '',
  manager_id: null as number | null,
  parent_id: null as number | null,
  status: 'Active',
  color: '#4f6ef7'
});

const errors = ref<Record<string, string[]>>({});
const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Active', value: 'Active' },
  { title: 'Inactive', value: 'Inactive' }
];

const perPageOptions = [10, 25, 50];

const headers = [
  { title: 'Department Name', key: 'name', sortable: true },
  { title: 'Code', key: 'code', sortable: true },
  { title: 'Manager', key: 'manager', sortable: false },
  { title: 'Total Employees', key: 'employee_count', sortable: true },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Created Date', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

const managerSelectItems = computed(() =>
  managerOptions.value.map((manager) => ({
    title: manager.name,
    value: manager.id
  }))
);

function rowData(item: any): DepartmentItem {
  return (item?.raw ?? item) as DepartmentItem;
}

function resetForm() {
  errors.value = {};
  form.name = '';
  form.code = '';
  form.description = '';
  form.manager_id = null;
  form.parent_id = null;
  form.status = 'Active';
  form.color = '#4f6ef7';
}

function openCreateDrawer() {
  editingDept.value = null;
  resetForm();
  drawer.value = true;
}

function openEditDrawer(dept: DepartmentItem) {
  editingDept.value = dept;
  errors.value = {};
  form.name = dept.name;
  form.code = dept.code === '—' ? '' : dept.code;
  form.description = dept.description ?? '';
  form.manager_id = dept.manager?.id ?? null;
  form.parent_id = null;
  form.status = dept.status;
  form.color = dept.color ?? '#4f6ef7';
  drawer.value = true;
}

function openAssignManagerDialog(dept: DepartmentItem) {
  assigningDept.value = dept;
  selectedManager.value = dept.manager?.id ?? null;
  assignManagerDialog.value = true;
}

function askDelete(dept: DepartmentItem) {
  deletingDept.value = dept;
  deleteDialog.value = true;
}

function resetFilters() {
  filters.search = '';
  filters.status = '';
}

function viewEmployees(dept: DepartmentItem) {
  router.visit(`/hr/employees?department=${encodeURIComponent(dept.name)}`);
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
    stats.value.total = data.stats?.total ?? 0;
    stats.value.active = data.stats?.active ?? 0;
    stats.value.withoutManager = data.stats?.without_manager ?? 0;
    managerOptions.value = data.managers ?? [];
  } catch (_error) {
    departments.value = [];
    pagination.total = 0;
    snackbar.value = {
      show: true,
      message: 'Failed to load departments.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}

async function saveDepartment() {
  saving.value = true;
  errors.value = {};
  try {
    if (editingDept.value) {
      const { data } = await axios.put(`/api/hr/departments/${editingDept.value.id}`, form);
      snackbar.value = {
        show: true,
        message: data.message,
        color: 'success'
      };
    } else {
      const { data } = await axios.post('/api/hr/departments', form);
      snackbar.value = {
        show: true,
        message: data.message,
        color: 'success'
      };
    }

    drawer.value = false;
    await fetchDepartments();
  } catch (err: any) {
    if (err?.response?.status === 422) {
      errors.value = err.response.data.errors ?? {};
      snackbar.value = {
        show: true,
        message: 'Please fix the errors.',
        color: 'error'
      };
    } else {
      snackbar.value = {
        show: true,
        message: err?.response?.data?.message ?? 'Failed to save department.',
        color: 'error'
      };
    }
  } finally {
    saving.value = false;
  }
}

async function saveManagerAssignment() {
  if (!selectedManager.value || !assigningDept.value) return;

  saving.value = true;
  try {
    const { data } = await axios.patch(`/api/hr/departments/${assigningDept.value.id}/manager`, {
      manager_id: selectedManager.value
    });
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success'
    };
    assignManagerDialog.value = false;
    await fetchDepartments();
  } catch (_error) {
    snackbar.value = {
      show: true,
      message: 'Failed to assign manager.',
      color: 'error'
    };
  } finally {
    saving.value = false;
  }
}

async function confirmDelete() {
  if (!deletingDept.value) return;

  deleting.value = true;
  try {
    await axios.delete(`/api/hr/departments/${deletingDept.value.id}`);
    snackbar.value = {
      show: true,
      message: 'Department deleted.',
      color: 'success'
    };
    deleteDialog.value = false;
    await fetchDepartments();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to delete department.',
      color: 'error'
    };
    deleteDialog.value = false;
  } finally {
    deleting.value = false;
  }
}

async function toggleStatus(dept: DepartmentItem) {
  const newStatus = dept.status === 'Active' ? 'Inactive' : 'Active';
  try {
    await axios.patch(`/api/hr/departments/${dept.id}/status`, { status: newStatus });
    snackbar.value = {
      show: true,
      message: `${dept.name} set to ${newStatus}`,
      color: 'success'
    };
    await fetchDepartments();
  } catch (_error) {
    snackbar.value = {
      show: true,
      message: 'Failed to update status.',
      color: 'error'
    };
  }
}

function handleTableOptions(options: any) {
  const nextSortBy = options.sortBy?.length ? options.sortBy[0].key : 'name';
  const nextSortDir = options.sortBy?.length ? (options.sortBy[0].order ?? 'asc') : 'asc';

  const nextKey = JSON.stringify({
    page: options.page,
    perPage: options.itemsPerPage,
    sortBy: nextSortBy,
    sortDir: nextSortDir
  });

  if (lastTableOptionsKey.value === nextKey) {
    return;
  }

  lastTableOptionsKey.value = nextKey;
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;
  sort.sortBy = nextSortBy;
  sort.sortDir = nextSortDir;
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

onMounted(() => {
  fetchDepartments();
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
    <v-col cols="12" md="4">
      <v-card class="stat-card hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Total Departments</div>
          <div class="text-h5 font-weight-bold mt-1">{{ stats.total }}</div>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" md="4">
      <v-card class="stat-card hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Active Departments</div>
          <div class="text-h5 font-weight-bold mt-1">{{ stats.active }}</div>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" md="4">
      <v-card class="stat-card hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Without Manager</div>
          <div class="text-h5 font-weight-bold mt-1">{{ stats.withoutManager }}</div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="8">
          <v-text-field v-model="filters.search" placeholder="Search by name, code or description..." variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" md="4">
          <v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details />
        </v-col>
      </v-row>
      <div class="d-flex justify-end mt-2">
        <v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn>
      </div>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item>
      <p class="text-body-2 text-lightText mb-0">Showing {{ departments.length }} of {{ pagination.total }} departments</p>
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
            <v-avatar size="36" :color="rowData(item).color ?? '#4f6ef7'">
              <span class="text-white text-caption font-weight-bold">{{ rowData(item).initials }}</span>
            </v-avatar>
            <div>
              <div class="font-weight-medium">{{ rowData(item).name }}</div>
              <div v-if="rowData(item).description" class="text-caption text-medium-emphasis">
                {{ rowData(item).description }}
              </div>
            </div>
          </div>
        </template>

        <template #item.code="{ item }">
          <v-chip v-if="rowData(item).code !== '—'" size="small" variant="tonal" color="primary">
            {{ rowData(item).code }}
          </v-chip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.manager="{ item }">
          <div v-if="rowData(item).manager" class="d-flex align-center ga-2">
            <v-avatar size="28" :color="rowData(item).manager?.avatar ? undefined : 'secondary'">
              <v-img v-if="rowData(item).manager?.avatar" :src="rowData(item).manager?.avatar ?? ''" />
              <span v-else class="text-white text-caption">{{ rowData(item).manager?.initials }}</span>
            </v-avatar>
            <span class="text-body-2">{{ rowData(item).manager?.name }}</span>
          </div>
          <span v-else class="text-medium-emphasis text-body-2">Not Assigned</span>
        </template>

        <template #item.employee_count="{ item }">
          <v-chip size="small" :color="rowData(item).employee_count > 0 ? 'primary' : 'default'" variant="tonal">
            {{ rowData(item).employee_count }}
          </v-chip>
        </template>

        <template #item.status="{ item }">
          <v-chip size="small" :color="rowData(item).status === 'Active' ? 'success' : 'default'" variant="tonal">
            {{ rowData(item).status }}
          </v-chip>
        </template>

        <template #item.created_at="{ item }">
          <span class="text-body-2 text-medium-emphasis">{{ rowData(item).created_at }}</span>
        </template>

        <template #item.actions="{ item }">
          <v-menu>
            <template #activator="{ props }">
              <v-btn v-bind="props" icon variant="text" size="small">
                <v-icon>mdi-dots-vertical</v-icon>
              </v-btn>
            </template>
            <v-list density="compact">
              <v-list-item prepend-icon="mdi-pencil" title="Edit" @click="openEditDrawer(rowData(item))" />
              <v-list-item prepend-icon="mdi-account-tie" title="Assign Manager" @click="openAssignManagerDialog(rowData(item))" />
              <v-list-item
                :prepend-icon="rowData(item).status === 'Active' ? 'mdi-pause-circle' : 'mdi-play-circle'"
                :title="rowData(item).status === 'Active' ? 'Deactivate' : 'Activate'"
                @click="toggleStatus(rowData(item))"
              />
              <v-list-item prepend-icon="mdi-account-group" title="View Employees" @click="viewEmployees(rowData(item))" />
              <v-divider />
              <v-list-item prepend-icon="mdi-delete" title="Delete" base-color="error" @click="askDelete(rowData(item))" />
            </v-list>
          </v-menu>
        </template>
      </v-data-table-server>
    </v-card-text>
  </v-card>

  <v-navigation-drawer v-model="drawer" location="right" temporary width="480">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <h5 class="text-h5 mb-0">{{ editingDept ? 'Edit Department' : 'Add Department' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawer = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-text-field v-model="form.name" label="Department Name *" variant="outlined" class="mb-3" :error-messages="errors.name ?? []" />
      <v-text-field v-model="form.code" label="Department Code" hint="ENG / HR / FIN" persistent-hint variant="outlined" class="mb-3" :error-messages="errors.code ?? []" />
      <v-textarea v-model="form.description" label="Description" rows="3" variant="outlined" class="mb-3" :error-messages="errors.description ?? []" />
      <v-autocomplete v-model="form.manager_id" :items="managerSelectItems" label="Department Manager" variant="outlined" class="mb-3" clearable :error-messages="errors.manager_id ?? []" />
      <v-text-field v-model="form.color" label="Color" hint="Hex color e.g. #4f6ef7" persistent-hint variant="outlined" class="mb-3" :error-messages="errors.color ?? []" />
      <v-radio-group v-model="form.status" inline :error-messages="errors.status ?? []">
        <v-radio label="Active" value="Active" />
        <v-radio label="Inactive" value="Inactive" />
      </v-radio-group>
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawer = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveDepartment">Save Department</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="assignManagerDialog" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Assign Manager</v-card-title>
      <v-card-text>
        <v-autocomplete v-model="selectedManager" :items="managerSelectItems" label="Manager" variant="outlined" clearable />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="assignManagerDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="saveManagerAssignment">Assign</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Department</v-card-title>
      <v-card-text>
        Are you sure you want to delete <strong>{{ deletingDept?.name }}</strong>? This action cannot be undone.
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" :loading="deleting" @click="confirmDelete">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.stat-card {
  border-radius: 16px;
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
