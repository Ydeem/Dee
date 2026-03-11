<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface AttendanceRecord {
  id: number;
  date: string;
  date_raw: string;
  day: string;
  check_in: string;
  check_out: string;
  hours_worked: string;
  status: string;
  status_color: string;
  note: string | null;
  employee: {
    id: number;
    name: string;
    employee_id: string;
    avatar: string | null;
    initials: string;
    department: string;
  } | null;
}

interface TodayEmployee {
  employee_id: number;
  name: string;
  emp_id: string;
  department: string;
  avatar: string | null;
  initials: string;
  attendance_id: number | null;
  status: string;
  check_in: string | null;
  check_out: string | null;
  is_marked: boolean;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Attendance', disabled: true, href: '#' }
];

const records = ref<AttendanceRecord[]>([]);
const todayStats = ref({
  present: 0,
  absent: 0,
  late: 0,
  on_leave: 0
});
const departments = ref<string[]>([]);
const loading = ref(false);
const loadingEmployees = ref(false);
const saving = ref(false);
const deleting = ref(false);
const markingSaving = ref(false);
const bulkSaving = ref(false);

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  department: '',
  status: '',
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  from: '',
  to: '',
});

const form = reactive({
  id: null as number | null,
  employee_id: null as number | null,
  status: 'Present',
  check_in: '',
  check_out: '',
  note: ''
});

const editDialog = ref(false);
const deleteDialog = ref(false);
const deletingRecord = ref<AttendanceRecord | null>(null);
const markDialog = ref(false);
const bulkDialog = ref(false);
const todayEmployees = ref<TodayEmployee[]>([]);
const markDate = ref(new Date().toISOString().split('T')[0]);
const markSummary = ref({
  total: 0,
  marked: 0,
  unmarked: 0
});
const bulkForm = reactive({
  date: new Date().toISOString().split('T')[0],
  status: 'Present',
  check_in: '',
  check_out: ''
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const statusOptions = ['Present', 'Absent', 'Late', 'Half Day', 'On Leave', 'Holiday'];

function resetEditForm() {
  form.id = null;
  form.employee_id = null;
  form.status = 'Present';
  form.check_in = '';
  form.check_out = '';
  form.note = '';
}

async function fetchAttendance() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/attendance', {
      params: {
        month: filters.month,
        year: filters.year,
        search: filters.search || undefined,
        department: filters.department || undefined,
        status: filters.status || undefined,
        from: filters.from || undefined,
        to: filters.to || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
      }
    });

    records.value = data.records?.data ?? [];
    pagination.total = data.records?.total ?? 0;
    todayStats.value = data.today ?? {
      present: 0,
      absent: 0,
      late: 0,
      on_leave: 0
    };
    departments.value = data.filters?.departments ?? [];
  } catch (error) {
    console.error('Attendance fetch error:', error);
    records.value = [];
    pagination.total = 0;
    snackbar.value = {
      show: true,
      message: 'Failed to load attendance.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}

function openEditDialog(record: AttendanceRecord) {
  form.id = record.id;
  form.employee_id = record.employee?.id ?? null;
  form.status = record.status;
  form.check_in = record.check_in === '-' ? '' : toTwentyFourHour(record.check_in);
  form.check_out = record.check_out === '-' ? '' : toTwentyFourHour(record.check_out);
  form.note = record.note ?? '';
  editDialog.value = true;
}

function askDelete(record: AttendanceRecord) {
  deletingRecord.value = record;
  deleteDialog.value = true;
}

async function saveEdit() {
  if (!form.id) return;

  saving.value = true;
  try {
    const { data } = await axios.put(`/api/hr/attendance/${form.id}`, {
      status: form.status,
      check_in: form.check_in || undefined,
      check_out: form.check_out || undefined,
      note: form.note || undefined,
    });

    snackbar.value = {
      show: true,
      message: data.message ?? 'Attendance updated.',
      color: 'success'
    };
    editDialog.value = false;
    resetEditForm();
    await fetchAttendance();
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to update attendance.',
      color: 'error'
    };
  } finally {
    saving.value = false;
  }
}

async function confirmDelete() {
  if (!deletingRecord.value) return;

  deleting.value = true;
  try {
    const { data } = await axios.delete(`/api/hr/attendance/${deletingRecord.value.id}`);
    snackbar.value = {
      show: true,
      message: data.message ?? 'Record deleted.',
      color: 'success'
    };
    deleteDialog.value = false;
    deletingRecord.value = null;
    await fetchAttendance();
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to delete record.',
      color: 'error'
    };
  } finally {
    deleting.value = false;
  }
}

async function openMarkDialog() {
  markDialog.value = true;
  loadingEmployees.value = true;
  try {
    const { data } = await axios.get('/api/hr/attendance/today');
    todayEmployees.value = data.employees ?? [];
    markDate.value = new Date().toISOString().split('T')[0];
    markSummary.value = {
      total: data.total ?? 0,
      marked: data.marked ?? 0,
      unmarked: data.unmarked ?? 0,
    };
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to load today attendance.',
      color: 'error'
    };
  } finally {
    loadingEmployees.value = false;
  }
}

async function saveMarkAttendance() {
  markingSaving.value = true;
  try {
    const toMark = todayEmployees.value.filter((employee) => !employee.is_marked && employee.status !== 'Not Marked');

    if (!toMark.length) {
      snackbar.value = {
        show: true,
        message: 'No new records to save.',
        color: 'warning'
      };
      return;
    }

    await Promise.all(
      toMark.map((employee) =>
        axios.post('/api/hr/attendance', {
          employee_id: employee.employee_id,
          date: markDate.value,
          status: employee.status,
        })
      )
    );

    snackbar.value = {
      show: true,
      message: `${toMark.length} attendance records saved.`,
      color: 'success'
    };
    markDialog.value = false;
    await fetchAttendance();
  } catch (_error) {
    snackbar.value = {
      show: true,
      message: 'Failed to save attendance.',
      color: 'error'
    };
  } finally {
    markingSaving.value = false;
  }
}

async function saveBulkAttendance() {
  bulkSaving.value = true;
  try {
    const employeeResponse = await axios.get('/api/hr/employees', {
      params: {
        status: 'Active',
        per_page: 1000
      }
    });

    const ids = (employeeResponse.data.employees?.data ?? []).map((employee: any) => employee.id);

    const { data } = await axios.post('/api/hr/attendance/bulk', {
      date: bulkForm.date,
      status: bulkForm.status,
      employee_ids: ids,
      check_in: bulkForm.check_in || undefined,
      check_out: bulkForm.check_out || undefined,
    });

    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success'
    };
    bulkDialog.value = false;
    await fetchAttendance();
  } catch (_error) {
    snackbar.value = {
      show: true,
      message: 'Bulk mark failed.',
      color: 'error'
    };
  } finally {
    bulkSaving.value = false;
  }
}

function exportCsv() {
  if (!records.value.length) return;

  const headers = ['Employee', 'Employee ID', 'Department', 'Date', 'Check In', 'Check Out', 'Hours', 'Status', 'Note'];
  const rows = records.value.map((record) => [
    record.employee?.name ?? '',
    record.employee?.employee_id ?? '',
    record.employee?.department ?? '',
    record.date,
    record.check_in,
    record.check_out,
    record.hours_worked,
    record.status,
    record.note ?? '-'
  ]);

  const csv = [headers, ...rows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'attendance.csv';
  link.click();
  URL.revokeObjectURL(url);
}

function toTwentyFourHour(value: string) {
  const [time, modifier] = value.split(' ');
  let [hours, minutes] = time.split(':');

  if (modifier === 'PM' && hours !== '12') {
    hours = String(Number(hours) + 12);
  }

  if (modifier === 'AM' && hours === '12') {
    hours = '00';
  }

  return `${hours.padStart(2, '0')}:${minutes}`;
}

watch(
  [
    () => filters.search,
    () => filters.department,
    () => filters.status,
    () => filters.month,
    () => filters.year,
    () => filters.from,
    () => filters.to,
  ],
  () => {
    pagination.page = 1;
    fetchAttendance();
  }
);

watch(() => pagination.page, fetchAttendance);
watch(() => pagination.perPage, () => {
  pagination.page = 1;
  fetchAttendance();
});

onMounted(() => fetchAttendance());
</script>

<template>
  <BaseBreadcrumb title="Attendance" subtitle="Track daily employee attendance" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Attendance</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Track daily employee attendance</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-account-multiple-check" @click="bulkDialog = true">Mark Bulk Attendance</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openMarkDialog">Mark Attendance</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="6" md="3">
      <v-card class="stat-card hr-card-shadow" variant="outlined">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="success" variant="tonal" size="48"><v-icon>mdi-check-circle</v-icon></v-avatar>
          <div>
            <div class="text-caption text-medium-emphasis">Present Today</div>
            <div class="text-h5 font-weight-bold">{{ todayStats.present }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="6" md="3">
      <v-card class="stat-card hr-card-shadow" variant="outlined">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="error" variant="tonal" size="48"><v-icon>mdi-close-circle</v-icon></v-avatar>
          <div>
            <div class="text-caption text-medium-emphasis">Absent Today</div>
            <div class="text-h5 font-weight-bold">{{ todayStats.absent }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="6" md="3">
      <v-card class="stat-card hr-card-shadow" variant="outlined">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="warning" variant="tonal" size="48"><v-icon>mdi-clock-alert</v-icon></v-avatar>
          <div>
            <div class="text-caption text-medium-emphasis">Late Today</div>
            <div class="text-h5 font-weight-bold">{{ todayStats.late }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="6" md="3">
      <v-card class="stat-card hr-card-shadow" variant="outlined">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="tonal" size="48"><v-icon>mdi-beach</v-icon></v-avatar>
          <div>
            <div class="text-caption text-medium-emphasis">On Leave Today</div>
            <div class="text-h5 font-weight-bold">{{ todayStats.on_leave }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3"><v-text-field v-model="filters.search" label="Search" variant="outlined" hide-details /></v-col>
        <v-col cols="12" md="2">
          <v-select v-model="filters.department" :items="[{ title: 'All Departments', value: '' }, ...departments.map((dept) => ({ title: dept, value: dept }))]" label="Department" variant="outlined" hide-details />
        </v-col>
        <v-col cols="12" md="2">
          <v-select v-model="filters.status" :items="[{ title: 'All', value: '' }, ...statusOptions.map((status) => ({ title: status, value: status }))]" label="Status" variant="outlined" hide-details />
        </v-col>
        <v-col cols="6" md="1"><v-text-field v-model="filters.month" type="number" label="Month" min="1" max="12" variant="outlined" hide-details /></v-col>
        <v-col cols="6" md="1"><v-text-field v-model="filters.year" type="number" label="Year" variant="outlined" hide-details /></v-col>
        <v-col cols="6" md="1"><v-text-field v-model="filters.from" type="date" label="From" variant="outlined" hide-details /></v-col>
        <v-col cols="6" md="1"><v-text-field v-model="filters.to" type="date" label="To" variant="outlined" hide-details /></v-col>
        <v-col cols="12" md="1" class="d-flex align-end"><v-btn variant="text" color="primary" @click="fetchAttendance">Apply</v-btn></v-col>
        <v-col cols="12" class="d-flex justify-end"><v-btn variant="text" color="primary" @click="exportCsv">Export CSV</v-btn></v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined">
    <v-card-item>
      <div class="text-body-2 text-lightText">Showing {{ records.length }} of {{ pagination.total }} records</div>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <v-table>
        <thead>
          <tr>
            <th>Employee</th>
            <th>Date</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Hours</th>
            <th>Status</th>
            <th>Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8">
              <v-skeleton-loader type="table-row@5" />
            </td>
          </tr>
          <tr v-else-if="records.length === 0">
            <td colspan="8" class="text-center py-12">
              <v-icon size="48" color="grey-lighten-2">mdi-calendar-check</v-icon>
              <p class="text-body-1 mt-3 text-medium-emphasis">No attendance records for this period</p>
              <v-btn color="primary" variant="flat" class="mt-3" @click="openMarkDialog">Mark Today's Attendance</v-btn>
            </td>
          </tr>
          <tr v-else v-for="record in records" :key="record.id">
            <td>
              <div class="d-flex align-center ga-2">
                <v-avatar size="32" :color="record.employee?.avatar ? undefined : 'primary'" variant="tonal">
                  <v-img v-if="record.employee?.avatar" :src="record.employee.avatar ?? ''" />
                  <span v-else class="text-caption">{{ record.employee?.initials }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ record.employee?.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ record.employee?.employee_id }} · {{ record.employee?.department }}</div>
                </div>
              </div>
            </td>
            <td>
              <div class="text-body-2">{{ record.date }}</div>
              <div class="text-caption text-medium-emphasis">{{ record.day }}</div>
            </td>
            <td class="text-body-2"><span :class="record.check_in === '-' ? 'text-medium-emphasis' : ''">{{ record.check_in }}</span></td>
            <td class="text-body-2"><span :class="record.check_out === '-' ? 'text-medium-emphasis' : ''">{{ record.check_out }}</span></td>
            <td class="text-body-2"><span :class="record.hours_worked === '-' ? 'text-medium-emphasis' : 'font-weight-medium'">{{ record.hours_worked }}</span></td>
            <td><v-chip size="small" :color="record.status_color" variant="tonal">{{ record.status }}</v-chip></td>
            <td class="text-body-2 text-medium-emphasis">{{ record.note ?? '-' }}</td>
            <td>
              <v-menu>
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon variant="text" size="small">
                    <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                  </v-btn>
                </template>
                <v-list density="compact">
                  <v-list-item prepend-icon="mdi-pencil" title="Edit" @click="openEditDialog(record)" />
                  <v-list-item prepend-icon="mdi-account" title="View Employee" @click="router.visit('/hr/employees/' + record.employee?.id)" />
                  <v-divider />
                  <v-list-item prepend-icon="mdi-delete" title="Delete" base-color="error" @click="askDelete(record)" />
                </v-list>
              </v-menu>
            </td>
          </tr>
        </tbody>
      </v-table>

      <div class="d-flex justify-space-between align-center mt-4 flex-wrap ga-3">
        <v-select v-model="pagination.perPage" :items="[10, 25, 50]" label="Rows" variant="outlined" density="compact" hide-details style="max-width: 120px" />
        <v-pagination v-model="pagination.page" :length="Math.max(1, Math.ceil(pagination.total / pagination.perPage))" rounded="circle" />
      </div>
    </v-card-text>
  </v-card>

  <v-dialog v-model="markDialog" max-width="600">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center pa-4">
        <span>Mark Attendance</span>
        <v-chip color="primary" size="small">{{ markSummary.marked }}/{{ markSummary.total }} marked today</v-chip>
      </v-card-title>
      <v-card-text>
        <v-text-field v-model="markDate" label="Date" type="date" variant="outlined" density="compact" class="mb-4" />
        <div v-if="loadingEmployees" class="py-6">
          <v-skeleton-loader type="list-item-three-line@4" />
        </div>
        <div v-else v-for="employee in todayEmployees" :key="employee.employee_id" class="d-flex align-center justify-space-between py-2 border-b">
          <div class="d-flex align-center ga-2">
            <v-avatar size="32" :color="employee.avatar ? undefined : 'primary'" variant="tonal">
              <v-img v-if="employee.avatar" :src="employee.avatar ?? ''" />
              <span v-else class="text-caption">{{ employee.initials }}</span>
            </v-avatar>
            <div>
              <div class="text-body-2 font-weight-medium">{{ employee.name }}</div>
              <div class="text-caption text-medium-emphasis">{{ employee.emp_id }}</div>
            </div>
          </div>
          <v-select
            v-model="employee.status"
            :items="[...statusOptions, 'Not Marked']"
            variant="outlined"
            density="compact"
            hide-details
            style="max-width: 160px"
            :disabled="employee.is_marked"
          />
        </div>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="markDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="markingSaving" @click="saveMarkAttendance">Save Attendance</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="bulkDialog" max-width="480">
    <v-card>
      <v-card-title class="text-h5">Mark Bulk Attendance</v-card-title>
      <v-card-text>
        <v-text-field v-model="bulkForm.date" type="date" label="Date" variant="outlined" class="mb-3" />
        <v-select v-model="bulkForm.status" :items="statusOptions" label="Status" variant="outlined" class="mb-3" />
        <v-text-field v-model="bulkForm.check_in" type="time" label="Check In" variant="outlined" class="mb-3" />
        <v-text-field v-model="bulkForm.check_out" type="time" label="Check Out" variant="outlined" />
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="bulkDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="bulkSaving" @click="saveBulkAttendance">Save Bulk Attendance</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="editDialog" max-width="480">
    <v-card>
      <v-card-title>Edit Attendance</v-card-title>
      <v-card-text>
        <v-select v-model="form.status" :items="statusOptions" label="Status" variant="outlined" class="mb-3" />
        <v-text-field v-model="form.check_in" type="time" label="Check In" variant="outlined" class="mb-3" />
        <v-text-field v-model="form.check_out" type="time" label="Check Out" variant="outlined" class="mb-3" />
        <v-textarea v-model="form.note" label="Note" variant="outlined" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="editDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="saving" @click="saveEdit">Save</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title>Delete Attendance Record</v-card-title>
      <v-card-text>Delete this attendance record?</v-card-text>
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

.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}
</style>
