<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface Shift {
  id: number;
  name: string;
  start_time: string;
  end_time: string;
  break_duration: number | null;
  working_days: string[];
  color: string | null;
  status: string;
  employees_count: number;
}

interface ShiftSchedule {
  id: number;
  effective_from: string;
  effective_to: string | null;
  note: string | null;
  employee: {
    id: number;
    full_name: string;
    employee_id: string;
    avatar_url: string | null;
    department?: { name: string } | null;
  };
  shift: {
    id: number;
    name: string;
    start_time: string;
    end_time: string;
    color: string | null;
  };
}

interface Summary {
  total_shifts: number;
  assigned_employees: number;
  unassigned_employees: number;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Shifts & Schedules', disabled: true, href: '#' }
];

const loading = ref(false);
const tab = ref('list');
const shifts = ref<Shift[]>([]);
const schedules = ref<ShiftSchedule[]>([]);
const departments = ref<string[]>([]);
const employees = ref<any[]>([]);
const summary = ref<Summary>({ total_shifts: 0, assigned_employees: 0, unassigned_employees: 0 });

const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', shift_id: '', department: '', active_only: true });

const drawerOpen = ref(false);
const scheduleId = ref<number | null>(null);
const form = reactive({
  employee_id: null as number | null,
  shift_id: null as number | null,
  effective_from: new Date().toISOString().slice(0, 10),
  effective_to: '',
  note: ''
});

const bulkDialog = ref(false);
const bulkSaving = ref(false);
const bulkForm = reactive({
  employee_ids: [] as number[],
  shift_id: null as number | null,
  effective_from: new Date().toISOString().slice(0, 10),
  note: ''
});

const manageDialog = ref(false);
const shiftFormOpen = ref(false);
const shiftForm = reactive({
  id: null as number | null,
  name: '',
  start_time: '08:00',
  end_time: '17:00',
  break_duration: 60,
  working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'] as string[],
  color: '#4f6ef7',
  status: 'Active'
});

const confirmDialog = ref({ show: false, id: null as number | null, message: '' });
const snackbar = ref({ show: false, message: '', color: 'success' });

const headers = [
  { title: 'Employee', key: 'employee', sortable: false },
  { title: 'Department', key: 'department', sortable: false },
  { title: 'Shift', key: 'shift', sortable: false },
  { title: 'Schedule', key: 'schedule', sortable: false },
  { title: 'Effective From', key: 'effective_from', sortable: true },
  { title: 'Effective To', key: 'effective_to', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

const dayOptions = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

const deptOptions = computed(() => [{ title: 'All Departments', value: '' }, ...departments.value.map((d) => ({ title: d, value: d }))]);
const shiftFilterOptions = computed(() => [{ title: 'All Shifts', value: '' }, ...shifts.value.map((s) => ({ title: s.name, value: s.id }))]);
const shiftOptions = computed(() => shifts.value.filter((s) => s.status === 'Active').map((s) => ({ title: `${s.name} (${s.start_time}-${s.end_time})`, value: s.id })));
const employeeOptions = computed(() => employees.value.map((e: any) => ({ title: `${e.full_name} (${e.employee_id})`, value: e.id })));

const netHours = computed(() => {
  const [sh, sm] = shiftForm.start_time.split(':').map(Number);
  const [eh, em] = shiftForm.end_time.split(':').map(Number);
  if ([sh, sm, eh, em].some((v) => Number.isNaN(v))) return '0.0';

  let start = sh * 60 + sm;
  let end = eh * 60 + em;
  if (end <= start) end += 24 * 60;

  const breakMinutes = Number(shiftForm.break_duration || 0);
  const total = Math.max(0, end - start - breakMinutes);
  return (total / 60).toFixed(1);
});

const dummyShifts: Shift[] = [
  {
    id: 1,
    name: 'Morning Shift',
    start_time: '08:00',
    end_time: '17:00',
    break_duration: 60,
    working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
    color: '#4f6ef7',
    status: 'Active',
    employees_count: 8
  },
  {
    id: 2,
    name: 'Night Shift',
    start_time: '22:00',
    end_time: '06:00',
    break_duration: 30,
    working_days: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
    color: '#9c27b0',
    status: 'Active',
    employees_count: 3
  }
];

const dummyEmployees = [
  { id: 1, full_name: 'Pontian Npontu', employee_id: 'EMP00001', avatar_url: null, department: { name: 'Human Resources' } },
  { id: 2, full_name: 'Sarah Oti', employee_id: 'EMP00002', avatar_url: null, department: { name: 'Human Resources' } },
  { id: 3, full_name: 'Daniel Kofi', employee_id: 'EMP00003', avatar_url: null, department: { name: 'Engineering' } }
];

const dummySchedules: ShiftSchedule[] = [
  {
    id: 1,
    effective_from: '2026-01-01',
    effective_to: null,
    note: null,
    employee: dummyEmployees[0],
    shift: { id: 1, name: 'Morning Shift', start_time: '08:00', end_time: '17:00', color: '#4f6ef7' }
  },
  {
    id: 2,
    effective_from: '2026-02-01',
    effective_to: null,
    note: null,
    employee: dummyEmployees[1],
    shift: { id: 2, name: 'Night Shift', start_time: '22:00', end_time: '06:00', color: '#9c27b0' }
  }
];

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((p) => p[0])
    .join('')
    .toUpperCase();
}

function rowData(item: any): ShiftSchedule {
  return (item?.raw ?? item) as ShiftSchedule;
}

function formatDate(d: string | null) {
  return d ? new Date(d).toLocaleDateString() : 'Ongoing';
}

function applyDummy() {
  shifts.value = dummyShifts;
  schedules.value = dummySchedules;
  employees.value = dummyEmployees;
  departments.value = ['Human Resources', 'Engineering'];
  pagination.total = dummySchedules.length;
  summary.value = { total_shifts: 2, assigned_employees: 2, unassigned_employees: 1 };
}

async function fetchShifts() {
  try {
    const { data } = await axios.get('/api/hr/shifts');
    shifts.value = data?.shifts?.length ? data.shifts : dummyShifts;
  } catch {
    shifts.value = dummyShifts;
  }
}

async function fetchEmployees() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    employees.value = data?.employees?.data?.length ? data.employees.data : dummyEmployees;
  } catch {
    employees.value = dummyEmployees;
  }
}

async function fetchSchedules() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/shift-schedules', {
      params: {
        search: filters.search || undefined,
        shift_id: filters.shift_id || undefined,
        department: filters.department || undefined,
        active_only: filters.active_only ? 1 : undefined,
        page: pagination.page,
        per_page: pagination.perPage
      }
    });

    schedules.value = data?.schedules?.data ?? [];
    pagination.total = data?.schedules?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    departments.value = data?.departments ?? departments.value;

    if ((data?.shifts ?? []).length) {
      shifts.value = data.shifts;
    }

    if (!schedules.value.length) {
      applyDummy();
    }
  } catch {
    applyDummy();
    snackbar.value = { show: true, message: 'Using dummy shifts data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

function openAssign() {
  scheduleId.value = null;
  form.employee_id = null;
  form.shift_id = null;
  form.effective_from = new Date().toISOString().slice(0, 10);
  form.effective_to = '';
  form.note = '';
  drawerOpen.value = true;
}

function editSchedule(s: ShiftSchedule) {
  scheduleId.value = s.id;
  form.employee_id = s.employee.id;
  form.shift_id = s.shift.id;
  form.effective_from = s.effective_from;
  form.effective_to = s.effective_to ?? '';
  form.note = s.note ?? '';
  drawerOpen.value = true;
}

async function saveSchedule() {
  if (!form.employee_id || !form.shift_id) return;

  try {
    const payload = {
      employee_id: form.employee_id,
      shift_id: form.shift_id,
      effective_from: form.effective_from,
      effective_to: form.effective_to || null,
      note: form.note || null
    };

    if (scheduleId.value) {
      await axios.put(`/api/hr/shift-schedules/${scheduleId.value}`, payload);
    } else {
      await axios.post('/api/hr/shift-schedules', payload);
    }

    drawerOpen.value = false;
    snackbar.value = { show: true, message: 'Schedule saved.', color: 'success' };
    await fetchSchedules();
  } catch (e: any) {
    snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Save failed.', color: 'error' };
  }
}

function askDelete(s: ShiftSchedule) {
  confirmDialog.value = { show: true, id: s.id, message: `Remove ${s.employee.full_name} assignment?` };
}

async function removeSchedule() {
  if (!confirmDialog.value.id) return;

  try {
    await axios.delete(`/api/hr/shift-schedules/${confirmDialog.value.id}`);
    confirmDialog.value.show = false;
    await fetchSchedules();
  } catch {
    snackbar.value = { show: true, message: 'Remove failed.', color: 'error' };
  }
}

async function runBulkAssign() {
  if (!bulkForm.employee_ids.length || !bulkForm.shift_id) return;

  bulkSaving.value = true;
  try {
    const { data } = await axios.post('/api/hr/shift-schedules/bulk-assign', {
      employee_ids: bulkForm.employee_ids,
      shift_id: bulkForm.shift_id,
      effective_from: bulkForm.effective_from,
      note: bulkForm.note || null
    });

    bulkDialog.value = false;
    snackbar.value = { show: true, message: data?.message ?? 'Bulk assign complete.', color: 'success' };
    await fetchSchedules();
  } catch (e: any) {
    snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Bulk assign failed.', color: 'error' };
  } finally {
    bulkSaving.value = false;
  }
}

function openShiftForm(item?: Shift) {
  if (item) {
    shiftForm.id = item.id;
    shiftForm.name = item.name;
    shiftForm.start_time = item.start_time;
    shiftForm.end_time = item.end_time;
    shiftForm.break_duration = item.break_duration ?? 0;
    shiftForm.working_days = [...(item.working_days ?? [])];
    shiftForm.color = item.color ?? '#4f6ef7';
    shiftForm.status = item.status;
  } else {
    shiftForm.id = null;
    shiftForm.name = '';
    shiftForm.start_time = '08:00';
    shiftForm.end_time = '17:00';
    shiftForm.break_duration = 60;
    shiftForm.working_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    shiftForm.color = '#4f6ef7';
    shiftForm.status = 'Active';
  }

  shiftFormOpen.value = true;
}

async function saveShift() {
  try {
    const payload = {
      name: shiftForm.name,
      start_time: shiftForm.start_time,
      end_time: shiftForm.end_time,
      break_duration: Number(shiftForm.break_duration || 0),
      working_days: shiftForm.working_days,
      color: shiftForm.color,
      status: shiftForm.status
    };

    if (shiftForm.id) {
      await axios.put(`/api/hr/shifts/${shiftForm.id}`, payload);
    } else {
      await axios.post('/api/hr/shifts', payload);
    }

    shiftFormOpen.value = false;
    snackbar.value = { show: true, message: 'Shift saved.', color: 'success' };
    await Promise.all([fetchShifts(), fetchSchedules()]);
  } catch (e: any) {
    snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Shift save failed.', color: 'error' };
  }
}

async function removeShift(s: Shift) {
  try {
    await axios.delete(`/api/hr/shifts/${s.id}`);
    snackbar.value = { show: true, message: 'Shift deleted.', color: 'success' };
    await Promise.all([fetchShifts(), fetchSchedules()]);
  } catch (e: any) {
    snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Cannot delete shift.', color: 'error' };
  }
}

function exportCsv() {
  const rows = schedules.value;
  if (!rows.length) return;

  const headers = ['Employee', 'Employee ID', 'Department', 'Shift', 'Start', 'End', 'Effective From', 'Effective To'];
  const csvRows = rows.map((r) => [
    r.employee.full_name,
    r.employee.employee_id,
    r.employee.department?.name ?? '-',
    r.shift.name,
    r.shift.start_time,
    r.shift.end_time,
    r.effective_from,
    r.effective_to ?? 'Ongoing'
  ]);

  const csvContent = [headers, ...csvRows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'shift-schedules.csv';
  link.click();
  URL.revokeObjectURL(url);
}

watch(
  () => [filters.search, filters.shift_id, filters.department, filters.active_only],
  () => {
    pagination.page = 1;
    fetchSchedules();
  }
);

onMounted(async () => {
  applyDummy();
  await Promise.all([fetchShifts(), fetchEmployees(), fetchSchedules()]);
});
</script>

<template>
  <BaseBreadcrumb title="Shifts &amp; Schedules" subtitle="Manage work shifts and employee schedules" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Shifts &amp; Schedules</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage work shifts and employee schedules</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-cog" @click="manageDialog = true">Manage Shifts</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAssign">Assign Shift</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Active Shifts: <strong>{{ summary.total_shifts }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Assigned Employees: <strong>{{ summary.assigned_employees }}</strong></v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>Unassigned Employees: <strong>{{ summary.unassigned_employees }}</strong></v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-tabs v-model="tab" color="primary" class="px-4 pt-2">
      <v-tab value="list">Schedule List</v-tab>
      <v-tab value="weekly">Weekly View</v-tab>
    </v-tabs>
    <v-divider />

    <v-window v-model="tab">
      <v-window-item value="list">
        <div class="pa-4">
          <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field v-model="filters.search" placeholder="Search by employee name or ID..." variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" sm="6" md="3">
                  <v-select v-model="filters.shift_id" :items="shiftFilterOptions" label="Shift" variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" sm="6" md="3">
                  <v-select v-model="filters.department" :items="deptOptions" label="Department" variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" md="2" class="d-flex align-center">
                  <v-switch v-model="filters.active_only" label="Active Only" hide-details color="primary" />
                </v-col>
              </v-row>

              <div class="d-flex justify-space-between mt-2">
                <v-btn variant="text" color="primary" @click="filters.search=''; filters.shift_id=''; filters.department=''; filters.active_only=true">Reset Filters</v-btn>
                <div class="d-flex ga-2">
                  <v-btn size="small" variant="outlined" prepend-icon="mdi-account-multiple" @click="bulkDialog = true">Bulk Assign Shift</v-btn>
                  <v-btn size="small" variant="outlined" prepend-icon="mdi-download" @click="exportCsv">Export CSV</v-btn>
                </div>
              </div>
            </v-card-text>
          </v-card>

          <v-skeleton-loader v-if="loading && !schedules.length" type="table" />

          <v-data-table-server
            v-else
            :headers="headers"
            :items="schedules"
            :items-length="pagination.total"
            :items-per-page="pagination.perPage"
            :page="pagination.page"
            :items-per-page-options="[10, 25, 50]"
            item-value="id"
            @update:options="(o: any) => { pagination.page = o.page; pagination.perPage = o.itemsPerPage; fetchSchedules(); }"
          >
            <template #item.employee="{ item }">
              <div class="d-flex align-center ga-3 cursor-pointer" @click="router.visit(`/hr/employees/${rowData(item).employee.id}`)">
                <v-avatar size="34" color="primary" variant="tonal">
                  <img v-if="rowData(item).employee.avatar_url" :src="rowData(item).employee.avatar_url || ''" :alt="rowData(item).employee.full_name" />
                  <span v-else class="text-caption font-weight-bold">{{ initials(rowData(item).employee.full_name) }}</span>
                </v-avatar>
                <div>
                  <div class="font-weight-medium">{{ rowData(item).employee.full_name }}</div>
                  <div class="text-caption text-lightText">{{ rowData(item).employee.employee_id }}</div>
                </div>
              </div>
            </template>

            <template #item.department="{ item }">{{ rowData(item).employee.department?.name ?? '-' }}</template>
            <template #item.shift="{ item }"><v-chip size="small" :color="rowData(item).shift.color || '#4f6ef7'" variant="flat">{{ rowData(item).shift.name }}</v-chip></template>
            <template #item.schedule="{ item }">{{ rowData(item).shift.start_time }} - {{ rowData(item).shift.end_time }}</template>
            <template #item.effective_from="{ item }">{{ formatDate(rowData(item).effective_from) }}</template>
            <template #item.effective_to="{ item }">
              <v-chip v-if="!rowData(item).effective_to" color="success" size="small" variant="tonal">Ongoing</v-chip>
              <span v-else>{{ formatDate(rowData(item).effective_to) }}</span>
            </template>
            <template #item.actions="{ item }">
              <v-menu>
                <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
                <v-list>
                  <v-list-item title="Edit Schedule" @click="editSchedule(rowData(item))" />
                  <v-list-item title="Change Shift" @click="editSchedule(rowData(item))" />
                  <v-list-item title="Remove Assignment" base-color="error" @click="askDelete(rowData(item))" />
                </v-list>
              </v-menu>
            </template>
          </v-data-table-server>
        </div>
      </v-window-item>

      <v-window-item value="weekly">
        <div class="pa-4">
          <v-alert type="info" variant="tonal">Weekly overview is available after loading active schedules.</v-alert>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="520">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <h5 class="text-h5 mb-0">{{ scheduleId ? 'Edit Schedule' : 'Assign Shift' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-autocomplete v-model="form.employee_id" :items="employeeOptions" label="Employee *" variant="outlined" class="mb-3" />
      <v-select v-model="form.shift_id" :items="shiftOptions" label="Shift *" variant="outlined" class="mb-3" />
      <v-text-field v-model="form.effective_from" type="date" label="Effective From *" variant="outlined" class="mb-3" />
      <v-text-field v-model="form.effective_to" type="date" label="Effective To" variant="outlined" class="mb-3" />
      <v-textarea v-model="form.note" label="Note" rows="2" variant="outlined" />
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
      <v-btn color="primary" @click="saveSchedule">Assign Shift</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="bulkDialog" max-width="560">
    <v-card>
      <v-card-title class="text-h5">Bulk Assign Shift</v-card-title>
      <v-card-text>
        <v-autocomplete v-model="bulkForm.employee_ids" :items="employeeOptions" label="Employees *" variant="outlined" multiple chips class="mb-3" />
        <v-select v-model="bulkForm.shift_id" :items="shiftOptions" label="Shift *" variant="outlined" class="mb-3" />
        <v-text-field v-model="bulkForm.effective_from" type="date" label="Effective From *" variant="outlined" class="mb-3" />
        <v-textarea v-model="bulkForm.note" label="Note" rows="2" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="bulkDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="bulkSaving" @click="runBulkAssign">Assign to {{ bulkForm.employee_ids.length }} Employees</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="manageDialog" max-width="720">
    <v-card>
      <v-card-title class="text-h5 d-flex justify-space-between align-center">
        <span>Manage Shifts</span>
        <v-btn size="small" variant="outlined" prepend-icon="mdi-plus" @click="openShiftForm()">Add New Shift</v-btn>
      </v-card-title>
      <v-card-text>
        <v-row>
          <v-col v-for="s in shifts" :key="s.id" cols="12" md="6">
            <v-card class="shift-card" variant="outlined" :style="{ borderLeftColor: s.color || '#4f6ef7' }">
              <v-card-text>
                <div class="font-weight-bold">{{ s.name }}</div>
                <div class="text-body-2">{{ s.start_time }} - {{ s.end_time }}</div>
                <div class="text-caption text-lightText mt-1">Employees: {{ s.employees_count }}</div>
                <div class="d-flex justify-space-between mt-2">
                  <v-chip size="x-small" :color="s.status === 'Active' ? 'success' : 'secondary'" variant="tonal">{{ s.status }}</v-chip>
                  <div class="d-flex ga-1">
                    <v-btn icon="mdi-pencil" size="small" variant="text" @click="openShiftForm(s)" />
                    <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="removeShift(s)" />
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-expand-transition>
          <div v-if="shiftFormOpen" class="mt-4">
            <v-row>
              <v-col cols="12" md="6"><v-text-field v-model="shiftForm.name" label="Shift Name *" variant="outlined" /></v-col>
              <v-col cols="12" md="3"><v-text-field v-model="shiftForm.start_time" type="time" label="Start Time *" variant="outlined" /></v-col>
              <v-col cols="12" md="3"><v-text-field v-model="shiftForm.end_time" type="time" label="End Time *" variant="outlined" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model.number="shiftForm.break_duration" type="number" label="Break (min)" variant="outlined" /></v-col>
              <v-col cols="12" md="4"><v-text-field v-model="shiftForm.color" label="Color" variant="outlined" /></v-col>
              <v-col cols="12" md="4"><v-select v-model="shiftForm.status" :items="['Active', 'Inactive']" label="Status" variant="outlined" /></v-col>
              <v-col cols="12">
                <v-select v-model="shiftForm.working_days" :items="dayOptions" label="Working Days" variant="outlined" chips multiple />
              </v-col>
            </v-row>

            <v-alert type="info" variant="tonal" class="mb-3">Net hours: {{ netHours }} hrs/day (after break)</v-alert>

            <div class="d-flex justify-end ga-2">
              <v-btn variant="outlined" @click="shiftFormOpen = false">Cancel</v-btn>
              <v-btn color="primary" @click="saveShift">Save Shift</v-btn>
            </div>
          </div>
        </v-expand-transition>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="manageDialog = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Remove Assignment</v-card-title>
      <v-card-text>{{ confirmDialog.message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="removeSchedule">Remove</v-btn>
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

.shift-card {
  border-left: 4px solid #4f6ef7;
}
</style>
