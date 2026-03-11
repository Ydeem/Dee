<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface DepartmentRef {
  id: number;
  name: string;
}

interface DesignationItem {
  id: number;
  name: string;
  initials: string;
  department: DepartmentRef | null;
  level: string | null;
  level_color: string;
  description: string | null;
  status: 'Active' | 'Inactive';
  employee_count: number;
  min_salary: string | number | null;
  max_salary: string | number | null;
  created_at: string;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Designations', disabled: true, href: '#' }
];

const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const drawer = ref(false);
const deleteDialog = ref(false);
const editingDesig = ref<DesignationItem | null>(null);
const deletingDesig = ref<DesignationItem | null>(null);
const designations = ref<DesignationItem[]>([]);
const departmentOptions = ref<string[]>([]);
const levelOptions = ref<string[]>([]);
const allDepartments = ref<DepartmentRef[]>([]);
const lastTableOptionsKey = ref('');

const stats = ref({
  total: 0,
  active: 0,
  unassigned: 0
});

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  department: '',
  level: '',
  status: ''
});

const sort = reactive({
  sortBy: 'name',
  sortDir: 'asc'
});

const form = reactive({
  name: '',
  department_id: null as number | null,
  level: null as string | null,
  description: '',
  min_salary: null as number | null,
  max_salary: null as number | null,
  status: 'Active'
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

const levelValues = ['Junior', 'Mid-level', 'Senior', 'Lead', 'Manager', 'Director', 'C-Level'];
const perPageOptions = [10, 25, 50];
const showSalaryColumn = true;

const headers = computed(() => {
  const base = [
    { title: 'Designation Name', key: 'name', sortable: true },
    { title: 'Department', key: 'department', sortable: false },
    { title: 'Level', key: 'level', sortable: true },
    { title: 'Employee Count', key: 'employee_count', sortable: true },
  ];

  if (showSalaryColumn) {
    base.push({ title: 'Salary Range', key: 'salary_range', sortable: false });
  }

  base.push(
    { title: 'Status', key: 'status', sortable: false },
    { title: 'Created Date', key: 'created_at', sortable: true },
    { title: 'Actions', key: 'actions', sortable: false }
  );

  return base;
});

const filterDepartmentItems = computed(() => [
  { title: 'All Departments', value: '' },
  ...departmentOptions.value.map((department) => ({
    title: department,
    value: department
  }))
]);

const filterLevelItems = computed(() => [
  { title: 'All Levels', value: '' },
  ...levelOptions.value.map((level) => ({
    title: level,
    value: level
  }))
]);

function rowData(item: any): DesignationItem {
  return (item?.raw ?? item) as DesignationItem;
}

function resetForm() {
  errors.value = {};
  form.name = '';
  form.department_id = null;
  form.level = null;
  form.description = '';
  form.min_salary = null;
  form.max_salary = null;
  form.status = 'Active';
}

function openCreateDrawer() {
  editingDesig.value = null;
  resetForm();
  drawer.value = true;
}

function openEditDrawer(desig: DesignationItem) {
  editingDesig.value = desig;
  errors.value = {};
  form.name = desig.name;
  form.department_id = desig.department?.id ?? null;
  form.level = desig.level;
  form.description = desig.description ?? '';
  form.min_salary = desig.min_salary ? Number(desig.min_salary) : null;
  form.max_salary = desig.max_salary ? Number(desig.max_salary) : null;
  form.status = desig.status;
  drawer.value = true;
}

function askDelete(desig: DesignationItem) {
  deletingDesig.value = desig;
  deleteDialog.value = true;
}

function resetFilters() {
  filters.search = '';
  filters.department = '';
  filters.level = '';
  filters.status = '';
}

async function fetchDepartmentSelectOptions() {
  try {
    const { data } = await axios.get('/api/hr/departments', {
      params: { per_page: 200 }
    });
    allDepartments.value = data?.departments?.data?.map((department: any) => ({
      id: department.id,
      name: department.name
    })) ?? [];
  } catch (_error) {
    allDepartments.value = [];
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
        sort_dir: sort.sortDir,
      }
    });

    designations.value = data.designations?.data ?? [];
    pagination.total = data.designations?.total ?? 0;
    stats.value.total = data.stats?.total ?? 0;
    stats.value.active = data.stats?.active ?? 0;
    stats.value.unassigned = data.stats?.unassigned ?? 0;
    departmentOptions.value = data.filters?.departments ?? [];
    levelOptions.value = data.filters?.levels ?? [];
  } catch (_error) {
    designations.value = [];
    pagination.total = 0;
    snackbar.value = {
      show: true,
      message: 'Failed to load designations.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}

async function saveDesignation() {
  saving.value = true;
  errors.value = {};
  try {
    const payload = {
      ...form,
      description: form.description || null,
      level: form.level || null,
      min_salary: form.min_salary ?? null,
      max_salary: form.max_salary ?? null
    };

    if (editingDesig.value) {
      const { data } = await axios.put(`/api/hr/designations/${editingDesig.value.id}`, payload);
      snackbar.value = {
        show: true,
        message: data.message,
        color: 'success'
      };
    } else {
      const { data } = await axios.post('/api/hr/designations', payload);
      snackbar.value = {
        show: true,
        message: data.message,
        color: 'success'
      };
    }

    drawer.value = false;
    await fetchDesignations();
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
        message: err?.response?.data?.message ?? 'Failed to save designation.',
        color: 'error'
      };
    }
  } finally {
    saving.value = false;
  }
}

async function confirmDelete() {
  if (!deletingDesig.value) return;

  deleting.value = true;
  try {
    await axios.delete(`/api/hr/designations/${deletingDesig.value.id}`);
    snackbar.value = {
      show: true,
      message: 'Designation deleted.',
      color: 'success'
    };
    deleteDialog.value = false;
    await fetchDesignations();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to delete.',
      color: 'error'
    };
    deleteDialog.value = false;
  } finally {
    deleting.value = false;
  }
}

async function toggleStatus(desig: DesignationItem) {
  const newStatus = desig.status === 'Active' ? 'Inactive' : 'Active';
  try {
    await axios.patch(`/api/hr/designations/${desig.id}/status`, { status: newStatus });
    snackbar.value = {
      show: true,
      message: `${desig.name} set to ${newStatus}`,
      color: 'success'
    };
    await fetchDesignations();
  } catch (_error) {
    snackbar.value = {
      show: true,
      message: 'Failed to update status.',
      color: 'error'
    };
  }
}

function viewEmployees(desig: DesignationItem) {
  router.visit(`/hr/employees?designation=${encodeURIComponent(desig.name)}`);
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
  sort.sortBy = nextSortBy === 'salary_range' ? 'name' : nextSortBy;
  sort.sortDir = nextSortDir;
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
  await fetchDepartmentSelectOptions();
  await fetchDesignations();
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
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Total Designations: <strong>{{ stats.total }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Active Designations: <strong>{{ stats.active }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Unassigned Designations: <strong>{{ stats.unassigned }}</strong></v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4">
          <v-text-field v-model="filters.search" placeholder="Search designations..." variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select v-model="filters.department" :items="filterDepartmentItems" label="Department" variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select v-model="filters.level" :items="filterLevelItems" label="Level" variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" sm="6" md="2">
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
            <v-avatar size="36" color="primary" variant="tonal">
              <span class="text-caption font-weight-bold">{{ rowData(item).initials }}</span>
            </v-avatar>
            <div>
              <span class="font-weight-medium mr-2">{{ rowData(item).name }}</span>
              <v-chip v-if="rowData(item).level" size="x-small" :color="rowData(item).level_color" variant="tonal">
                {{ rowData(item).level }}
              </v-chip>
            </div>
          </div>
        </template>

        <template #item.department="{ item }">
          <v-chip v-if="rowData(item).department" size="small" variant="tonal" color="primary">
            {{ rowData(item).department?.name }}
          </v-chip>
          <span v-else class="text-medium-emphasis text-body-2">Not Assigned</span>
        </template>

        <template #item.level="{ item }">
          <v-chip v-if="rowData(item).level" size="small" variant="tonal" :color="rowData(item).level_color">
            {{ rowData(item).level }}
          </v-chip>
          <span v-else class="text-medium-emphasis">—</span>
        </template>

        <template #item.employee_count="{ item }">
          <v-chip size="small" :color="rowData(item).employee_count > 0 ? 'primary' : 'default'" variant="tonal">
            {{ rowData(item).employee_count }}
          </v-chip>
        </template>

        <template v-if="showSalaryColumn" #item.salary_range="{ item }">
          <span v-if="rowData(item).min_salary" class="text-body-2">
            GHS {{ Number(rowData(item).min_salary).toLocaleString() }} –
            {{ Number(rowData(item).max_salary).toLocaleString() }}
          </span>
          <span v-else class="text-medium-emphasis">—</span>
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
      <h5 class="text-h5 mb-0">{{ editingDesig ? 'Edit Designation' : 'Add Designation' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawer = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-text-field v-model="form.name" label="Designation Name *" variant="outlined" :error-messages="errors.name?.[0]" class="mb-3" />
      <v-select
        v-model="form.department_id"
        label="Department"
        variant="outlined"
        :items="allDepartments"
        item-title="name"
        item-value="id"
        clearable
        class="mb-3"
        :error-messages="errors.department_id?.[0]"
      />
      <v-select
        v-model="form.level"
        label="Level"
        variant="outlined"
        :items="levelValues"
        clearable
        class="mb-3"
      />
      <v-textarea v-model="form.description" label="Description" variant="outlined" rows="3" class="mb-3" />
      <v-row>
        <v-col cols="6">
          <v-text-field v-model="form.min_salary" label="Min Salary (GHS)" variant="outlined" type="number" :error-messages="errors.min_salary?.[0]" />
        </v-col>
        <v-col cols="6">
          <v-text-field v-model="form.max_salary" label="Max Salary (GHS)" variant="outlined" type="number" :error-messages="errors.max_salary?.[0]" />
        </v-col>
      </v-row>
      <v-select v-model="form.status" label="Status *" variant="outlined" :items="['Active', 'Inactive']" :error-messages="errors.status?.[0]" />
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawer = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveDesignation">Save Designation</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Designation</v-card-title>
      <v-card-text>
        Are you sure you want to delete <strong>{{ deletingDesig?.name }}</strong>? This cannot be undone.
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
