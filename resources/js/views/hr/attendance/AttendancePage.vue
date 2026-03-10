<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface AttendanceRecord {
  id: number;
  date: string;
  check_in: string | null;
  check_out: string | null;
  hours_worked: number | null;
  status: string;
  note: string | null;
  employee: {
    id: number;
    full_name: string;
    employee_id: string;
    avatar_url: string | null;
    department?: { name: string } | null;
  };
}

interface Summary {
  present: number;
  absent: number;
  late: number;
  on_leave: number;
}

interface EmployeeOption {
  id: number;
  full_name: string;
  employee_id: string;
  avatar_url: string | null;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Attendance', disabled: true, href: '#' }
];

const statusValues = ['Present', 'Absent', 'Late', 'Half Day', 'On Leave', 'Holiday'];
const nowMonth = new Date().toISOString().slice(0, 7);
const today = new Date().toISOString().slice(0, 10);

const loading = ref(true);
const saving = ref(false);
const viewMode = ref<'table' | 'calendar'>('table');

const attendance = ref<AttendanceRecord[]>([]);
const calendarRecords = ref<AttendanceRecord[]>([]);
const departments = ref<string[]>([]);
const employeeOptions = ref<EmployeeOption[]>([]);

const dummyDepartments = ['Human Resources', 'Engineering', 'Finance', 'Sales'];
const dummyEmployees: EmployeeOption[] = [
  { id: 1, full_name: 'Pontian Npontu', employee_id: 'EMP00001', avatar_url: null },
  { id: 2, full_name: 'Sarah Oti', employee_id: 'EMP00002', avatar_url: null },
  { id: 3, full_name: 'Daniel Kofi', employee_id: 'EMP00003', avatar_url: null }
];
const dummyAttendance: AttendanceRecord[] = [
  {
    id: 1,
    date: today,
    check_in: `${today} 08:00:00`,
    check_out: `${today} 17:00:00`,
    hours_worked: 9.0,
    status: 'Present',
    note: null,
    employee: {
      id: 1,
      full_name: 'Pontian Npontu',
      employee_id: 'EMP00001',
      avatar_url: null,
      department: { name: 'Human Resources' }
    }
  },
  {
    id: 2,
    date: today,
    check_in: `${today} 09:30:00`,
    check_out: `${today} 17:00:00`,
    hours_worked: 7.5,
    status: 'Late',
    note: 'Traffic delay',
    employee: {
      id: 2,
      full_name: 'Sarah Oti',
      employee_id: 'EMP00002',
      avatar_url: null,
      department: { name: 'Human Resources' }
    }
  },
  {
    id: 3,
    date: today,
    check_in: null,
    check_out: null,
    hours_worked: null,
    status: 'On Leave',
    note: 'Annual leave',
    employee: {
      id: 3,
      full_name: 'Daniel Kofi',
      employee_id: 'EMP00003',
      avatar_url: null,
      department: { name: 'Engineering' }
    }
  }
];

const summary = ref<Summary>({
  present: 0,
  absent: 0,
  late: 0,
  on_leave: 0
});

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  department: '',
  status: '',
  month: nowMonth,
  dateFrom: '',
  dateTo: ''
});

const sort = reactive({
  sortBy: 'date',
  sortDir: 'desc'
});

const drawerOpen = ref(false);
const editingId = ref<number | null>(null);
const form = reactive({
  employee_id: null as number | null,
  date: today,
  status: 'Present',
  check_in: '',
  check_out: '',
  note: ''
});

const bulkDialog = ref(false);
const bulkForm = reactive({
  date: today,
  status: 'Present'
});

const dayDialog = ref(false);
const selectedDay = ref('');
const selectedDayRecords = ref<AttendanceRecord[]>([]);

const confirmDialog = ref({
  show: false,
  id: null as number | null,
  employeeName: ''
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const headers = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Department', key: 'department', sortable: false },
  { title: 'Date', key: 'date', sortable: true },
  { title: 'Check In', key: 'check_in', sortable: false },
  { title: 'Check Out', key: 'check_out', sortable: false },
  { title: 'Hours Worked', key: 'hours_worked', sortable: true },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Note', key: 'note', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false }
];

const perPageOptions = [10, 25, 50];
const departmentOptions = computed(() => [{ title: 'All Departments', value: '' }, ...departments.value.map((name) => ({ title: name, value: name }))]);
const statusFilterOptions = computed(() => [{ title: 'All', value: '' }, ...statusValues.map((value) => ({ title: value, value }))]);
const markStatusOptions = statusValues.map((value) => ({ title: value, value }));
const bulkStatusOptions = ['Present', 'Absent', 'Holiday'].map((value) => ({ title: value, value }));

const canShowTimeFields = computed(() => ['Present', 'Late', 'Half Day'].includes(form.status));
const calculatedHours = computed(() => {
  if (!form.check_in || !form.check_out) return null;

  const start = new Date(`${form.date}T${form.check_in}`);
  const end = new Date(`${form.date}T${form.check_out}`);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end <= start) {
    return null;
  }
  const diff = (end.getTime() - start.getTime()) / (1000 * 60 * 60);
  return Math.round(diff * 100) / 100;
});

const activeEmployeeCount = computed(() => employeeOptions.value.length);
const existingRecordWarning = computed(() => {
  if (!form.employee_id || !form.date) return false;
  return attendance.value.some((item) => item.employee.id === form.employee_id && item.date === form.date && item.id !== editingId.value);
});

const calendarMonthDate = computed(() => {
  const [year, month] = filters.month.split('-').map((part) => Number(part));
  return new Date(year, (month || 1) - 1, 1);
});

const calendarDays = computed(() => {
  const firstDay = calendarMonthDate.value;
  const year = firstDay.getFullYear();
  const month = firstDay.getMonth();
  const startWeekDay = new Date(year, month, 1).getDay();
  const totalDays = new Date(year, month + 1, 0).getDate();

  const days: Array<{ key: string; date: string | null; day: number | null }> = [];

  for (let i = 0; i < startWeekDay; i += 1) {
    days.push({ key: `blank-start-${i}`, date: null, day: null });
  }

  for (let day = 1; day <= totalDays; day += 1) {
    const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    days.push({ key: date, date, day });
  }

  while (days.length % 7 !== 0) {
    days.push({ key: `blank-end-${days.length}`, date: null, day: null });
  }

  return days;
});

const recordsByDate = computed(() => {
  const map: Record<string, AttendanceRecord[]> = {};
  calendarRecords.value.forEach((item) => {
    if (!map[item.date]) map[item.date] = [];
    map[item.date].push(item);
  });
  return map;
});

function rowData(item: any): AttendanceRecord {
  return (item?.raw ?? item) as AttendanceRecord;
}

function statusColor(status: string) {
  if (status === 'Present') return 'success';
  if (status === 'Absent') return 'error';
  if (status === 'Late') return 'warning';
  if (status === 'Half Day') return 'yellow-darken-2';
  if (status === 'On Leave') return 'primary';
  if (status === 'Holiday') return 'purple';
  return 'secondary';
}

function getInitials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

function formatDate(date: string) {
  return new Date(date).toLocaleDateString();
}

function formatTime(dateTime: string | null) {
  if (!dateTime) return 'â€”';
  const date = new Date(dateTime.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return 'â€”';
  return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatHours(value: number | null) {
  return value == null ? 'â€”' : `${value} hrs`;
}

function dayStatusCount(date: string, status: string) {
  return (recordsByDate.value[date] ?? []).filter((item) => item.status === status).length;
}

async function fetchEmployeeOptions() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    const items = data?.employees?.data ?? [];
    employeeOptions.value = items.map((item: any) => ({
      id: item.id,
      full_name: item.full_name ?? `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim(),
      employee_id: item.employee_id ?? '',
      avatar_url: item.avatar_url ?? null
    }));

    if (!employeeOptions.value.length) {
      employeeOptions.value = dummyEmployees;
    }
  } catch (error) {
    employeeOptions.value = dummyEmployees;
  }
}

async function fetchAttendance() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/attendance', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        status: filters.status || undefined,
        month: filters.month || undefined,
        date_from: filters.dateFrom || undefined,
        date_to: filters.dateTo || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
        sort_by: sort.sortBy,
        sort_dir: sort.sortDir
      }
    });

    attendance.value = data?.attendance?.data ?? [];
    pagination.total = data?.attendance?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    departments.value = (data?.departments ?? []).length ? data.departments : dummyDepartments;

    if (!attendance.value.length) {
      attendance.value = dummyAttendance;
      pagination.total = dummyAttendance.length;
      summary.value = {
        present: dummyAttendance.filter((item) => item.status === 'Present').length,
        absent: dummyAttendance.filter((item) => item.status === 'Absent').length,
        late: dummyAttendance.filter((item) => item.status === 'Late').length,
        on_leave: dummyAttendance.filter((item) => item.status === 'On Leave').length
      };
    }
  } catch (error) {
    attendance.value = dummyAttendance;
    pagination.total = dummyAttendance.length;
    departments.value = dummyDepartments;
    summary.value = {
      present: dummyAttendance.filter((item) => item.status === 'Present').length,
      absent: dummyAttendance.filter((item) => item.status === 'Absent').length,
      late: dummyAttendance.filter((item) => item.status === 'Late').length,
      on_leave: dummyAttendance.filter((item) => item.status === 'On Leave').length
    };
    snackbar.value = { show: true, message: 'Using dummy attendance data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

async function fetchCalendarRecords() {
  try {
    const { data } = await axios.get('/api/hr/attendance', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        status: filters.status || undefined,
        month: filters.month || undefined,
        date_from: filters.dateFrom || undefined,
        date_to: filters.dateTo || undefined,
        page: 1,
        per_page: 2000,
        sort_by: 'date',
        sort_dir: 'desc'
      }
    });
    calendarRecords.value = data?.attendance?.data ?? [];
    if (!calendarRecords.value.length) {
      calendarRecords.value = dummyAttendance;
    }
  } catch (error) {
    calendarRecords.value = dummyAttendance;
  }
}

function resetFilters() {
  filters.search = '';
  filters.department = '';
  filters.status = '';
  filters.month = nowMonth;
  filters.dateFrom = '';
  filters.dateTo = '';
}

function handleTableOptions(options: any) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;

  if (options.sortBy?.length) {
    const key = options.sortBy[0].key;
    sort.sortBy = key === 'employee' ? 'employee_name' : key;
    sort.sortDir = options.sortBy[0].order ?? 'asc';
  } else {
    sort.sortBy = 'date';
    sort.sortDir = 'desc';
  }

  fetchAttendance();
}

function openCreateDrawer() {
  editingId.value = null;
  form.employee_id = null;
  form.date = today;
  form.status = 'Present';
  form.check_in = '';
  form.check_out = '';
  form.note = '';
  drawerOpen.value = true;
}

function openEditDrawer(item: AttendanceRecord) {
  editingId.value = item.id;
  form.employee_id = item.employee.id;
  form.date = item.date;
  form.status = item.status;
  form.check_in = item.check_in ? formatTime(item.check_in) : '';
  form.check_out = item.check_out ? formatTime(item.check_out) : '';
  form.note = item.note ?? '';
  drawerOpen.value = true;
}

function openDeleteDialog(item: AttendanceRecord) {
  confirmDialog.value = {
    show: true,
    id: item.id,
    employeeName: item.employee.full_name
  };
}

async function saveRecord() {
  if (!form.employee_id || !form.date) {
    snackbar.value = { show: true, message: 'Employee and date are required.', color: 'error' };
    return;
  }

  if (canShowTimeFields.value && form.check_in && form.check_out) {
    const start = new Date(`${form.date}T${form.check_in}`);
    const end = new Date(`${form.date}T${form.check_out}`);
    if (end <= start) {
      snackbar.value = { show: true, message: 'Check Out must be after Check In.', color: 'error' };
      return;
    }
  }

  saving.value = true;
  try {
    const payload = {
      employee_id: form.employee_id,
      date: form.date,
      status: form.status,
      check_in: canShowTimeFields.value ? form.check_in || null : null,
      check_out: canShowTimeFields.value ? form.check_out || null : null,
      note: form.note || null
    };

    if (editingId.value) {
      await axios.put(`/api/hr/attendance/${editingId.value}`, payload);
    } else {
      await axios.post('/api/hr/attendance', payload);
    }

    snackbar.value = { show: true, message: 'Attendance saved successfully.', color: 'success' };
    drawerOpen.value = false;
    await Promise.all([fetchAttendance(), fetchCalendarRecords()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save attendance.',
      color: 'error'
    };
  } finally {
    saving.value = false;
  }
}

async function deleteRecord() {
  if (!confirmDialog.value.id) return;
  try {
    await axios.delete(`/api/hr/attendance/${confirmDialog.value.id}`);
    confirmDialog.value.show = false;
    snackbar.value = { show: true, message: 'Record deleted.', color: 'success' };
    await Promise.all([fetchAttendance(), fetchCalendarRecords()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to delete record.',
      color: 'error'
    };
  }
}

async function markBulkAttendance() {
  try {
    const { data } = await axios.post('/api/hr/attendance/bulk', {
      date: bulkForm.date,
      status: bulkForm.status
    });

    bulkDialog.value = false;
    snackbar.value = {
      show: true,
      message: data?.message ?? `Attendance marked for ${activeEmployeeCount.value} employees.`,
      color: 'success'
    };

    await Promise.all([fetchAttendance(), fetchCalendarRecords()]);
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Bulk attendance failed.',
      color: 'error'
    };
  }
}

function openDayDialog(date: string) {
  selectedDay.value = date;
  selectedDayRecords.value = recordsByDate.value[date] ?? [];
  dayDialog.value = true;
}

function viewEmployeeProfile(id: number) {
  router.visit(`/hr/employees/${id}`);
}

function exportCsv() {
  const rows = attendance.value;
  if (!rows.length) return;

  const headers = ['Employee', 'Employee ID', 'Department', 'Date', 'Check In', 'Check Out', 'Hours Worked', 'Status', 'Note'];
  const csvRows = rows.map((item) => [
    item.employee.full_name,
    item.employee.employee_id,
    item.employee.department?.name ?? '-',
    item.date,
    formatTime(item.check_in),
    formatTime(item.check_out),
    item.hours_worked ?? '-',
    item.status,
    item.note ?? '-'
  ]);

  const csv = [headers, ...csvRows]
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

watch(
  () => [filters.search, filters.department, filters.status, filters.month, filters.dateFrom, filters.dateTo],
  () => {
    pagination.page = 1;
    fetchAttendance();
    fetchCalendarRecords();
  }
);

onMounted(async () => {
  await Promise.all([fetchEmployeeOptions(), fetchAttendance(), fetchCalendarRecords()]);
});
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
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDrawer">Mark Attendance</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="success" variant="tonal"><v-icon icon="mdi-check-circle" /></v-avatar>
          <div>Present Today: <strong>{{ summary.present }}</strong></div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="error" variant="tonal"><v-icon icon="mdi-close-circle" /></v-avatar>
          <div>Absent Today: <strong>{{ summary.absent }}</strong></div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="warning" variant="tonal"><v-icon icon="mdi-clock-alert" /></v-avatar>
          <div>Late Today: <strong>{{ summary.late }}</strong></div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="tonal"><v-icon icon="mdi-calendar-remove" /></v-avatar>
          <div>On Leave Today: <strong>{{ summary.on_leave }}</strong></div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by employee name or ID..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.department" :items="departmentOptions" label="Department" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusFilterOptions" label="Status" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-text-field v-model="filters.month" type="month" label="Month" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="1"><v-text-field v-model="filters.dateFrom" type="date" label="From" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="1"><v-text-field v-model="filters.dateTo" type="date" label="To" variant="outlined" hide-details /></v-col>
      </v-row>
      <div class="d-flex justify-end mt-2">
        <v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn>
      </div>
    </v-card-text>
  </v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item>
      <div class="d-flex justify-space-between align-center flex-wrap ga-2">
        <div class="d-flex align-center ga-2">
          <p class="text-body-2 text-lightText mb-0">Showing {{ attendance.length }} of {{ pagination.total }} records</p>
          <v-btn size="small" variant="outlined" prepend-icon="mdi-download" @click="exportCsv">Export CSV</v-btn>
        </div>
        <div class="d-flex align-center ga-2">
          <v-btn :color="viewMode === 'table' ? 'primary' : 'default'" icon="mdi-view-list" variant="text" @click="viewMode = 'table'" />
          <v-btn :color="viewMode === 'calendar' ? 'primary' : 'default'" icon="mdi-calendar-month" variant="text" @click="viewMode = 'calendar'" />
        </div>
      </div>
    </v-card-item>
    <v-divider />
    <v-card-text>
      <v-skeleton-loader v-if="loading && !attendance.length" type="table" />

      <template v-else>
        <v-data-table-server
          v-if="viewMode === 'table'"
          :headers="headers"
          :items="attendance"
          :items-length="pagination.total"
          :items-per-page="pagination.perPage"
          :page="pagination.page"
          :items-per-page-options="perPageOptions"
          item-value="id"
          @update:options="handleTableOptions"
        >
          <template #item.employee="{ item }">
            <div class="d-flex align-center ga-3 cursor-pointer" @click="viewEmployeeProfile(rowData(item).employee.id)">
              <v-avatar color="primary" variant="tonal" size="36">
                <img v-if="rowData(item).employee.avatar_url" :src="rowData(item).employee.avatar_url || ''" :alt="rowData(item).employee.full_name" />
                <span v-else class="text-caption font-weight-bold">{{ getInitials(rowData(item).employee.full_name) }}</span>
              </v-avatar>
              <div>
                <div class="font-weight-medium text-body-1">{{ rowData(item).employee.full_name }}</div>
                <div class="text-caption text-lightText">{{ rowData(item).employee.employee_id }}</div>
              </div>
            </div>
          </template>
          <template #item.department="{ item }">{{ rowData(item).employee.department?.name ?? 'â€”' }}</template>
          <template #item.date="{ item }">{{ formatDate(rowData(item).date) }}</template>
          <template #item.check_in="{ item }">{{ formatTime(rowData(item).check_in) }}</template>
          <template #item.check_out="{ item }">{{ formatTime(rowData(item).check_out) }}</template>
          <template #item.hours_worked="{ item }">{{ formatHours(rowData(item).hours_worked) }}</template>
          <template #item.status="{ item }">
            <v-chip :color="statusColor(rowData(item).status)" size="small" variant="tonal">{{ rowData(item).status }}</v-chip>
          </template>
          <template #item.note="{ item }">
            <div class="text-truncate" style="max-width: 180px;" :title="rowData(item).note ?? 'â€”'">{{ rowData(item).note ?? 'â€”' }}</div>
          </template>
          <template #item.actions="{ item }">
            <v-menu>
              <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
              <v-list>
                <v-list-item title="Edit Record" @click="openEditDrawer(rowData(item))" />
                <v-list-item title="Delete" base-color="error" @click="openDeleteDialog(rowData(item))" />
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>

        <template v-else>
          <div class="calendar-grid mb-3">
            <div class="calendar-header" v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" :key="day">{{ day }}</div>
            <div
              v-for="cell in calendarDays"
              :key="cell.key"
              class="calendar-cell"
              :class="{ 'calendar-cell--blank': !cell.date }"
              @click="cell.date ? openDayDialog(cell.date) : null"
            >
              <template v-if="cell.date">
                <div class="font-weight-medium mb-1">{{ cell.day }}</div>
                <div class="dots">
                  <span v-if="dayStatusCount(cell.date, 'Present')" class="dot dot-present" />
                  <span v-if="dayStatusCount(cell.date, 'Absent')" class="dot dot-absent" />
                  <span v-if="dayStatusCount(cell.date, 'Late')" class="dot dot-late" />
                  <span v-if="dayStatusCount(cell.date, 'On Leave')" class="dot dot-leave" />
                </div>
              </template>
            </div>
          </div>
        </template>
      </template>
    </v-card-text>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="520">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <h5 class="text-h5 mb-0">{{ editingId ? 'Edit Attendance' : 'Mark Attendance' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
    </div>
    <div class="pa-4 drawer-body">
      <v-autocomplete
        v-model="form.employee_id"
        :items="employeeOptions.map((item) => ({ title: `${item.full_name} (${item.employee_id})`, value: item.id }))"
        label="Employee *"
        variant="outlined"
        :disabled="Boolean(editingId)"
        class="mb-3"
      />
      <v-text-field v-model="form.date" type="date" label="Date *" variant="outlined" :disabled="Boolean(editingId)" class="mb-3" />
      <v-select v-model="form.status" :items="markStatusOptions" label="Status *" variant="outlined" class="mb-3" />
      <v-text-field v-if="canShowTimeFields" v-model="form.check_in" type="time" label="Check In Time" variant="outlined" class="mb-3" />
      <v-text-field v-if="canShowTimeFields" v-model="form.check_out" type="time" label="Check Out Time" variant="outlined" class="mb-3" />
      <v-text-field :model-value="calculatedHours != null ? `${calculatedHours} hrs` : 'â€”'" label="Hours Worked" variant="outlined" readonly class="mb-3" />
      <v-textarea v-model="form.note" label="Note" rows="2" variant="outlined" />
      <v-alert v-if="existingRecordWarning && !editingId" type="warning" variant="tonal" class="mt-3">
        A record already exists for this date. Saving will update the existing record.
      </v-alert>
    </div>
    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveRecord">Save Record</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="bulkDialog" max-width="480">
    <v-card>
      <v-card-title class="text-h5">Mark Bulk Attendance</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-3">This will mark attendance for ALL active employees for the selected date.</p>
        <v-text-field v-model="bulkForm.date" type="date" label="Date *" variant="outlined" class="mb-3" />
        <v-select v-model="bulkForm.status" :items="bulkStatusOptions" label="Status *" variant="outlined" class="mb-3" />
        <v-chip color="warning" variant="tonal">This will affect {{ activeEmployeeCount }} active employees</v-chip>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="bulkDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" @click="markBulkAttendance">Mark Attendance</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="dayDialog" max-width="620">
    <v-card>
      <v-card-title class="text-h5">Attendance - {{ selectedDay ? formatDate(selectedDay) : '' }}</v-card-title>
      <v-card-text>
        <v-list v-if="selectedDayRecords.length">
          <v-list-item v-for="item in selectedDayRecords" :key="item.id">
            <template #prepend>
              <v-avatar size="32" color="primary" variant="tonal">{{ getInitials(item.employee.full_name) }}</v-avatar>
            </template>
            <v-list-item-title>{{ item.employee.full_name }} ({{ item.employee.employee_id }})</v-list-item-title>
            <v-list-item-subtitle>
              {{ item.employee.department?.name ?? 'â€”' }} Â· {{ formatTime(item.check_in) }} - {{ formatTime(item.check_out) }}
            </v-list-item-subtitle>
            <template #append><v-chip size="small" :color="statusColor(item.status)" variant="tonal">{{ item.status }}</v-chip></template>
          </v-list-item>
        </v-list>
        <p v-else class="text-body-2 text-lightText mb-0">No records for this date.</p>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="dayDialog = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Attendance Record</v-card-title>
      <v-card-text>Delete record for <strong>{{ confirmDialog.employeeName }}</strong>? This cannot be undone.</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="deleteRecord">Delete</v-btn>
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

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 8px;
}

.calendar-header {
  font-weight: 600;
  text-align: center;
  color: rgba(0, 0, 0, 0.65);
}

.calendar-cell {
  min-height: 88px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 10px;
  padding: 8px;
  cursor: pointer;
}

.calendar-cell--blank {
  background: rgba(0, 0, 0, 0.02);
  cursor: default;
}

.dots {
  display: flex;
  gap: 6px;
}

.dot {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  display: inline-block;
}

.dot-present {
  background: #2e7d32;
}

.dot-absent {
  background: #d32f2f;
}

.dot-late {
  background: #ef6c00;
}

.dot-leave {
  background: #4f6ef7;
}
</style>
