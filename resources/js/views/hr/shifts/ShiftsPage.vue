<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue'

interface EmployeeOption {
  id: number
  name: string
}

interface ShiftOption {
  id: number
  name: string
  color: string
  schedule_label: string
  working_days: string[]
  break_duration?: number
  duration_hours?: number
  description?: string | null
  status?: string
  assigned_count?: number
  start_time?: string | null
  end_time?: string | null
}

interface ScheduleRow {
  id: number
  effective_from: string
  effective_from_raw: string
  effective_to: string
  effective_to_raw: string | null
  status: string
  note: string | null
  employee: {
    id: number
    name: string
    employee_id: string
    avatar: string | null
    initials: string
    department: string
  } | null
  shift: {
    id: number
    name: string
    color: string
    schedule_label: string
    working_days: string[]
  } | null
}

interface WeeklyDay {
  date: string
  label: string
  date_label: string
  is_today: boolean
  is_weekend: boolean
}

interface WeeklyRow {
  employee: {
    id: number
    name: string
    emp_id: string
    initials: string
    dept: string | null
  }
  days: Array<{
    date: string
    day_name: string
    has_shift: boolean
    shift: null | {
      name: string
      color: string
      time: string
    }
    is_weekend: boolean
  }>
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Shifts & Schedules', disabled: true, href: '#' }
]

const dayOptions = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

const loading = ref(false)
const weeklyLoading = ref(false)
const tab = ref('list')

const schedules = ref<ScheduleRow[]>([])
const shiftOptions = ref<ShiftOption[]>([])
const allShifts = ref<ShiftOption[]>([])
const deptOptions = ref<string[]>([])
const employeeList = ref<EmployeeOption[]>([])

const stats = ref({
  active_shifts: 0,
  assigned: 0,
  unassigned: 0,
})

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0,
})

const filters = reactive({
  search: '',
  shiftId: null as number | null,
  department: null as string | null,
  activeOnly: true,
})

const assignDialog = ref(false)
const assignSaving = ref(false)
const assignErrors = ref<Record<string, string[]>>({})
const assignForm = reactive({
  employee_id: null as number | null,
  shift_id: null as number | null,
  effective_from: new Date().toISOString().split('T')[0],
  effective_to: null as string | null,
  note: '',
})

const bulkDialog = ref(false)
const bulkSaving = ref(false)
const bulkForm = reactive({
  selectedIds: [] as number[],
  shiftId: null as number | null,
  effectiveFrom: new Date().toISOString().split('T')[0],
  effectiveTo: null as string | null,
})

const endDialog = ref(false)
const endingItem = ref<ScheduleRow | null>(null)
const endDate = ref(new Date().toISOString().split('T')[0])

const confirmDeleteDialog = ref(false)
const deletingItem = ref<ScheduleRow | null>(null)
const deleteSaving = ref(false)

const manageShiftsDialog = ref(false)
const editingShift = ref<ShiftOption | null>(null)
const shiftSaving = ref(false)
const shiftErrors = ref<Record<string, string[]>>({})
const shiftForm = reactive({
  name: '',
  start_time: '08:00',
  end_time: '17:00',
  color: '#4f6ef7',
  working_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as string[],
  break_duration: 60,
  description: '',
  status: 'Active',
})

const weeklyGrid = ref<WeeklyRow[]>([])
const weekDays = ref<WeeklyDay[]>([])
const weekLabel = ref('')
const weekStart = ref(new Date().toISOString().split('T')[0])

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

const shiftFilterItems = computed(() => [
  { title: 'All Shifts', value: null },
  ...shiftOptions.value.map((shift) => ({
    title: shift.name,
    value: shift.id,
  })),
])

const departmentItems = computed(() => [
  { title: 'All Departments', value: null },
  ...deptOptions.value.map((department) => ({
    title: department,
    value: department,
  })),
])

const employeeItems = computed(() =>
  employeeList.value.map((employee) => ({
    title: employee.name,
    value: employee.id,
  })),
)

const shiftSelectItems = computed(() =>
  shiftOptions.value.map((shift) => ({
    ...shift,
    title: shift.name,
    value: shift.id,
  })),
)

const bulkButtonLabel = computed(() => {
  if (bulkForm.selectedIds.length > 0) {
    return `Assign to ${bulkForm.selectedIds.length} Employees`
  }

  return 'Assign to All Active Employees'
})

const schedulePreview = computed(() => {
  const start = shiftForm.start_time
  const end = shiftForm.end_time

  if (!start || !end) {
    return 'N/A'
  }

  return `${start} - ${end}`
})

const durationPreview = computed(() => {
  const [sh, sm] = shiftForm.start_time.split(':').map(Number)
  const [eh, em] = shiftForm.end_time.split(':').map(Number)

  if ([sh, sm, eh, em].some((value) => Number.isNaN(value))) {
    return '0.0'
  }

  let start = sh * 60 + sm
  let end = eh * 60 + em

  if (end <= start) {
    end += 24 * 60
  }

  const total = Math.max(0, end - start - Number(shiftForm.break_duration || 0))
  return (total / 60).toFixed(1)
})

async function fetchSchedules() {
  loading.value = true

  try {
    const { data } = await axios.get('/api/hr/shift-schedules', {
      params: {
        search: filters.search || undefined,
        shift_id: filters.shiftId || undefined,
        department: filters.department || undefined,
        active_only: filters.activeOnly ? 'true' : 'false',
        page: pagination.page,
        per_page: pagination.perPage,
      },
    })

    schedules.value = data.schedules?.data ?? []
    pagination.total = data.schedules?.total ?? 0
    stats.value = data.stats ?? stats.value
    shiftOptions.value = data.shifts ?? []
    deptOptions.value = data.departments ?? []
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to load schedules.',
      color: 'error',
    }
  } finally {
    loading.value = false
  }
}

async function fetchEmployees() {
  const { data } = await axios.get('/api/hr/employees', {
    params: {
      status: 'Active',
      per_page: 1000,
    },
  })

  employeeList.value = (data.employees?.data ?? []).map((employee: any) => ({
    id: employee.id,
    name: `${employee.full_name ?? `${employee.first_name} ${employee.last_name}`} (${employee.employee_id})`,
  }))
}

async function openAssignDialog(preselectedEmployee: ScheduleRow['employee'] | null = null) {
  assignDialog.value = true
  assignForm.employee_id = preselectedEmployee?.id ?? null
  assignForm.shift_id = null
  assignForm.effective_from = new Date().toISOString().split('T')[0]
  assignForm.effective_to = null
  assignForm.note = ''
  assignErrors.value = {}

  try {
    await fetchEmployees()
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to load employees.',
      color: 'error',
    }
  }
}

async function saveAssignShift() {
  assignSaving.value = true

  try {
    const payload = {
      ...assignForm,
      effective_to: assignForm.effective_to || undefined,
      note: assignForm.note || undefined,
    }

    const { data } = await axios.post('/api/hr/shift-schedules/assign', payload)

    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    assignDialog.value = false
    await fetchSchedules()

    if (tab.value === 'weekly') {
      await fetchWeeklyView()
    }
  } catch (error: any) {
    if (error?.response?.status === 422) {
      assignErrors.value = error.response.data.errors ?? {}
    }

    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to assign shift.',
      color: 'error',
    }
  } finally {
    assignSaving.value = false
  }
}

async function saveBulkAssign() {
  bulkSaving.value = true

  try {
    if (employeeList.value.length === 0) {
      await fetchEmployees()
    }

    const allIds = employeeList.value.map((employee) => employee.id)
    const { data } = await axios.post('/api/hr/shift-schedules/bulk-assign', {
      employee_ids: bulkForm.selectedIds.length > 0 ? bulkForm.selectedIds : allIds,
      shift_id: bulkForm.shiftId,
      effective_from: bulkForm.effectiveFrom,
      effective_to: bulkForm.effectiveTo || undefined,
    })

    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    bulkDialog.value = false
    bulkForm.selectedIds = []
    bulkForm.shiftId = null
    bulkForm.effectiveFrom = new Date().toISOString().split('T')[0]
    bulkForm.effectiveTo = null

    await fetchSchedules()

    if (tab.value === 'weekly') {
      await fetchWeeklyView()
    }
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Bulk assign failed.',
      color: 'error',
    }
  } finally {
    bulkSaving.value = false
  }
}

function openEndDialog(schedule: ScheduleRow) {
  endingItem.value = schedule
  endDate.value = new Date().toISOString().split('T')[0]
  endDialog.value = true
}

async function confirmEndSchedule() {
  if (!endingItem.value) {
    return
  }

  try {
    const { data } = await axios.patch(`/api/hr/shift-schedules/${endingItem.value.id}/end`, {
      end_date: endDate.value,
    })

    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    endDialog.value = false

    await fetchSchedules()

    if (tab.value === 'weekly') {
      await fetchWeeklyView()
    }
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to end schedule.',
      color: 'error',
    }
  }
}

function askDelete(schedule: ScheduleRow) {
  deletingItem.value = schedule
  confirmDeleteDialog.value = true
}

async function confirmDeleteSchedule() {
  if (!deletingItem.value) {
    return
  }

  deleteSaving.value = true

  try {
    const { data } = await axios.delete(`/api/hr/shift-schedules/${deletingItem.value.id}`)
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    confirmDeleteDialog.value = false
    deletingItem.value = null

    await fetchSchedules()

    if (tab.value === 'weekly') {
      await fetchWeeklyView()
    }
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to remove schedule.',
      color: 'error',
    }
  } finally {
    deleteSaving.value = false
  }
}

async function fetchWeeklyView() {
  weeklyLoading.value = true

  try {
    const { data } = await axios.get('/api/hr/shift-schedules/weekly', {
      params: {
        week_start: weekStart.value,
      },
    })

    weeklyGrid.value = data.grid ?? []
    weekDays.value = data.days ?? []
    weekLabel.value = `${data.week_start} - ${data.week_end}`
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to load weekly view.',
      color: 'error',
    }
  } finally {
    weeklyLoading.value = false
  }
}

function changeWeek(offset: number) {
  const next = new Date(weekStart.value)
  next.setDate(next.getDate() + offset * 7)
  weekStart.value = next.toISOString().split('T')[0]
}

async function openManageShifts() {
  manageShiftsDialog.value = true

  try {
    const { data } = await axios.get('/api/hr/shifts/list')
    allShifts.value = data.shifts ?? []
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to load shifts.',
      color: 'error',
    }
  }
}

function resetShiftForm() {
  editingShift.value = null
  shiftForm.name = ''
  shiftForm.start_time = '08:00'
  shiftForm.end_time = '17:00'
  shiftForm.color = '#4f6ef7'
  shiftForm.working_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']
  shiftForm.break_duration = 60
  shiftForm.description = ''
  shiftForm.status = 'Active'
  shiftErrors.value = {}
}

function editShift(shift: ShiftOption) {
  editingShift.value = shift
  shiftForm.name = shift.name
  shiftForm.start_time = shift.start_time ?? '08:00'
  shiftForm.end_time = shift.end_time ?? '17:00'
  shiftForm.color = shift.color ?? '#4f6ef7'
  shiftForm.working_days = [...(shift.working_days ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'])]
  shiftForm.break_duration = shift.break_duration ?? 60
  shiftForm.description = shift.description ?? ''
  shiftForm.status = shift.status ?? 'Active'
  shiftErrors.value = {}
}

async function saveShift() {
  shiftSaving.value = true

  try {
    const payload = {
      ...shiftForm,
      description: shiftForm.description || undefined,
    }

    if (editingShift.value) {
      await axios.put(`/api/hr/shifts/${editingShift.value.id}/update`, payload)
    } else {
      await axios.post('/api/hr/shifts/create', payload)
    }

    snackbar.value = {
      show: true,
      message: 'Shift saved.',
      color: 'success',
    }

    resetShiftForm()
    await openManageShifts()
    await fetchSchedules()
  } catch (error: any) {
    if (error?.response?.status === 422) {
      shiftErrors.value = error.response.data.errors ?? {}
    }

    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save shift.',
      color: 'error',
    }
  } finally {
    shiftSaving.value = false
  }
}

async function removeShift(shift: ShiftOption) {
  try {
    const { data } = await axios.delete(`/api/hr/shifts/${shift.id}/delete`)
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    await openManageShifts()
    await fetchSchedules()
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to delete shift.',
      color: 'error',
    }
  }
}

watch(
  () => [filters.search, filters.shiftId, filters.department, filters.activeOnly],
  () => {
    pagination.page = 1
    fetchSchedules()
  },
)

watch(
  () => [pagination.page, pagination.perPage],
  () => {
    fetchSchedules()
  },
)

watch(tab, (value) => {
  if (value === 'weekly') {
    fetchWeeklyView()
  }
})

watch(weekStart, () => {
  if (tab.value === 'weekly') {
    fetchWeeklyView()
  }
})

onMounted(async () => {
  await fetchSchedules()
})
</script>

<template>
  <BaseBreadcrumb title="Shifts &amp; Schedules" subtitle="Manage work shifts and employee schedules" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-3 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Shifts &amp; Schedules</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage work shifts, assignments, and the weekly schedule grid.</p>
    </div>
    <div class="d-flex ga-2 flex-wrap">
      <v-btn variant="outlined" prepend-icon="mdi-cog" @click="openManageShifts">Manage Shifts</v-btn>
      <v-btn variant="outlined" prepend-icon="mdi-account-multiple" @click="bulkDialog = true; fetchEmployees()">Bulk Assign</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAssignDialog()">Assign Shift</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" md="4">
      <v-card variant="outlined" class="hr-card-shadow">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Active Shifts</div>
          <div class="text-h4 mt-1">{{ stats.active_shifts }}</div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" md="4">
      <v-card variant="outlined" class="hr-card-shadow">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Assigned Employees</div>
          <div class="text-h4 mt-1">{{ stats.assigned }}</div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" md="4">
      <v-card variant="outlined" class="hr-card-shadow">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Unassigned Employees</div>
          <div class="text-h4 mt-1">{{ stats.unassigned }}</div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card variant="outlined" class="hr-card-shadow">
    <v-tabs v-model="tab" color="primary" class="px-4 pt-2">
      <v-tab value="list">Schedule List</v-tab>
      <v-tab value="weekly">Weekly View</v-tab>
    </v-tabs>
    <v-divider />

    <v-window v-model="tab">
      <v-window-item value="list">
        <div class="pa-4">
          <v-card variant="outlined" class="mb-4">
            <v-card-text>
              <v-row>
                <v-col cols="12" md="4">
                  <v-text-field v-model="filters.search" placeholder="Search by employee name or ID..." variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" sm="6" md="3">
                  <v-select v-model="filters.shiftId" :items="shiftFilterItems" label="Shift" variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" sm="6" md="3">
                  <v-select v-model="filters.department" :items="departmentItems" label="Department" variant="outlined" hide-details />
                </v-col>
                <v-col cols="12" md="2" class="d-flex align-center">
                  <v-switch v-model="filters.activeOnly" label="Active Only" hide-details color="primary" />
                </v-col>
              </v-row>

              <div class="d-flex justify-space-between flex-wrap ga-2 mt-3">
                <v-btn variant="text" color="primary" @click="filters.search=''; filters.shiftId=null; filters.department=null; filters.activeOnly=true">
                  Reset Filters
                </v-btn>
                <div class="text-caption text-medium-emphasis d-flex align-center">
                  Showing {{ schedules.length }} of {{ pagination.total }} schedules
                </div>
              </div>
            </v-card-text>
          </v-card>

          <v-skeleton-loader v-if="loading" type="table-tbody" />

          <template v-else>
            <v-table class="schedule-table">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Department</th>
                  <th>Shift</th>
                  <th>Schedule</th>
                  <th>Effective From</th>
                  <th>Effective To</th>
                  <th>Status</th>
                  <th class="text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="sch in schedules" :key="sch.id">
                  <td>
                    <div v-if="sch.employee" class="d-flex align-center ga-3">
                      <v-avatar size="34" color="primary" variant="tonal">
                        <img v-if="sch.employee.avatar" :src="sch.employee.avatar" :alt="sch.employee.name" />
                        <span v-else class="text-caption font-weight-bold">{{ sch.employee.initials }}</span>
                      </v-avatar>
                      <div>
                        <div class="font-weight-medium">{{ sch.employee.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ sch.employee.employee_id }}</div>
                      </div>
                    </div>
                  </td>
                  <td>{{ sch.employee?.department ?? '-' }}</td>
                  <td>
                    <v-chip v-if="sch.shift" size="small" :color="sch.shift.color" variant="tonal">
                      {{ sch.shift.name }}
                    </v-chip>
                  </td>
                  <td>
                    <div>{{ sch.shift?.schedule_label ?? '-' }}</div>
                    <div class="text-caption text-medium-emphasis">{{ sch.shift?.working_days?.join(', ') ?? '' }}</div>
                  </td>
                  <td>{{ sch.effective_from }}</td>
                  <td>{{ sch.effective_to }}</td>
                  <td>
                    <v-chip size="small" :color="sch.status === 'Active' ? 'success' : 'secondary'" variant="tonal">
                      {{ sch.status }}
                    </v-chip>
                  </td>
                  <td class="text-right">
                    <v-menu>
                      <template #activator="{ props }">
                        <v-btn v-bind="props" icon variant="text" size="small">
                          <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                        </v-btn>
                      </template>
                      <v-list density="compact">
                        <v-list-item prepend-icon="mdi-swap-horizontal" title="Change Shift" @click="openAssignDialog(sch.employee)" />
                        <v-list-item prepend-icon="mdi-calendar-remove" title="End Schedule" @click="openEndDialog(sch)" />
                        <v-list-item prepend-icon="mdi-account" title="View Employee" @click="router.visit('/hr/employees/' + sch.employee?.id)" />
                        <v-divider />
                        <v-list-item prepend-icon="mdi-delete" title="Remove Schedule" base-color="error" @click="askDelete(sch)" />
                      </v-list>
                    </v-menu>
                  </td>
                </tr>
                <tr v-if="schedules.length === 0">
                  <td colspan="8" class="text-center py-8 text-medium-emphasis">No schedules found for the current filters.</td>
                </tr>
              </tbody>
            </v-table>

            <div class="d-flex justify-space-between align-center flex-wrap ga-3 mt-4">
              <v-select
                v-model="pagination.perPage"
                :items="[10, 25, 50]"
                label="Rows per page"
                variant="outlined"
                hide-details
                density="compact"
                max-width="140"
              />
              <v-pagination v-model="pagination.page" :length="Math.max(1, Math.ceil(pagination.total / pagination.perPage))" rounded="circle" />
            </div>
          </template>
        </div>
      </v-window-item>

      <v-window-item value="weekly">
        <div class="pa-4">
          <div class="d-flex justify-space-between align-center flex-wrap ga-3 mb-4">
            <div>
              <div class="text-caption text-medium-emphasis">Week Range</div>
              <div class="text-h6">{{ weekLabel }}</div>
            </div>
            <div class="d-flex ga-2 flex-wrap">
              <v-btn variant="outlined" prepend-icon="mdi-chevron-left" @click="changeWeek(-1)">Previous</v-btn>
              <v-btn variant="outlined" @click="weekStart = new Date().toISOString().split('T')[0]">This Week</v-btn>
              <v-btn variant="outlined" append-icon="mdi-chevron-right" @click="changeWeek(1)">Next</v-btn>
            </div>
          </div>

          <v-skeleton-loader v-if="weeklyLoading" type="table" />

          <v-table v-else class="weekly-table">
            <thead>
              <tr>
                <th>Employee</th>
                <th
                  v-for="day in weekDays"
                  :key="day.date"
                  :class="{ 'bg-grey-lighten-4': day.is_weekend, 'bg-blue-lighten-5': day.is_today }"
                  class="text-center"
                >
                  <div class="font-weight-medium">{{ day.label }}</div>
                  <div class="text-caption text-medium-emphasis">{{ day.date_label }}</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in weeklyGrid" :key="row.employee.id">
                <td>
                  <div class="d-flex align-center ga-2">
                    <v-avatar size="28" color="primary" variant="tonal">
                      <span class="text-caption">{{ row.employee.initials }}</span>
                    </v-avatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">{{ row.employee.name }}</div>
                      <div class="text-caption text-medium-emphasis">{{ row.employee.dept }}</div>
                    </div>
                  </div>
                </td>
                <td
                  v-for="day in row.days"
                  :key="day.date"
                  class="text-center pa-1"
                  :class="{ 'bg-grey-lighten-4': day.is_weekend }"
                >
                  <v-chip v-if="day.has_shift" size="x-small" :color="day.shift?.color" variant="tonal" class="text-caption">
                    {{ day.shift?.name }}
                  </v-chip>
                  <span v-else-if="!day.is_weekend" class="text-caption text-medium-emphasis">-</span>
                </td>
              </tr>
              <tr v-if="weeklyGrid.length === 0">
                <td :colspan="weekDays.length + 1" class="text-center py-8 text-medium-emphasis">No weekly schedule data available.</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-dialog v-model="assignDialog" max-width="520">
    <v-card>
      <v-card-title class="pa-4">Assign Shift</v-card-title>
      <v-card-text class="px-4">
        <v-select
          v-model="assignForm.employee_id"
          label="Employee *"
          variant="outlined"
          :items="employeeItems"
          :error-messages="assignErrors.employee_id?.[0]"
          class="mb-3"
        />
        <v-select
          v-model="assignForm.shift_id"
          label="Shift *"
          variant="outlined"
          :items="shiftSelectItems"
          item-title="title"
          item-value="value"
          :error-messages="assignErrors.shift_id?.[0]"
          class="mb-3"
        >
          <template #item="{ item, props }">
            <v-list-item v-bind="props">
              <template #append>
                <v-chip size="x-small" :color="item.raw.color">
                  {{ item.raw.schedule_label }}
                </v-chip>
              </template>
            </v-list-item>
          </template>
        </v-select>
        <v-row>
          <v-col cols="6">
            <v-text-field
              v-model="assignForm.effective_from"
              label="Effective From *"
              type="date"
              variant="outlined"
              :error-messages="assignErrors.effective_from?.[0]"
            />
          </v-col>
          <v-col cols="6">
            <v-text-field
              v-model="assignForm.effective_to"
              label="Effective To"
              type="date"
              variant="outlined"
              :error-messages="assignErrors.effective_to?.[0]"
              hint="Leave empty for ongoing"
              persistent-hint
            />
          </v-col>
        </v-row>
        <v-textarea
          v-model="assignForm.note"
          label="Note (optional)"
          variant="outlined"
          rows="2"
          class="mt-2"
        />
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="assignDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="assignSaving" @click="saveAssignShift">Assign Shift</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="bulkDialog" max-width="560">
    <v-card>
      <v-card-title class="pa-4">Bulk Assign Shift</v-card-title>
      <v-card-text class="px-4">
        <v-autocomplete
          v-model="bulkForm.selectedIds"
          label="Employees"
          variant="outlined"
          :items="employeeItems"
          multiple
          chips
          closable-chips
          hint="Leave empty to assign all active employees"
          persistent-hint
          class="mb-3"
        />
        <v-select
          v-model="bulkForm.shiftId"
          label="Shift *"
          variant="outlined"
          :items="shiftSelectItems"
          item-title="title"
          item-value="value"
          class="mb-3"
        />
        <v-row>
          <v-col cols="6">
            <v-text-field v-model="bulkForm.effectiveFrom" label="Effective From *" type="date" variant="outlined" />
          </v-col>
          <v-col cols="6">
            <v-text-field v-model="bulkForm.effectiveTo" label="Effective To" type="date" variant="outlined" />
          </v-col>
        </v-row>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="bulkDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="bulkSaving" @click="saveBulkAssign">{{ bulkButtonLabel }}</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="endDialog" max-width="420">
    <v-card>
      <v-card-title class="pa-4">End Schedule</v-card-title>
      <v-card-text class="px-4">
        <div class="text-body-2 mb-3">
          End the current schedule for <strong>{{ endingItem?.employee?.name }}</strong>.
        </div>
        <v-text-field v-model="endDate" type="date" label="End Date" variant="outlined" />
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="endDialog = false">Cancel</v-btn>
        <v-btn color="warning" variant="flat" @click="confirmEndSchedule">End Schedule</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="manageShiftsDialog" max-width="980">
    <v-card>
      <v-card-title class="pa-4 d-flex justify-space-between align-center flex-wrap ga-2">
        <span>Manage Shifts</span>
        <v-btn variant="outlined" prepend-icon="mdi-plus" @click="resetShiftForm">New Shift</v-btn>
      </v-card-title>
      <v-card-text class="px-4">
        <v-row class="mb-2">
          <v-col v-for="shift in allShifts" :key="shift.id" cols="12" md="6">
            <v-card variant="outlined" class="shift-card" :style="{ borderLeftColor: shift.color || '#4f6ef7' }">
              <v-card-text>
                <div class="d-flex justify-space-between align-start ga-3">
                  <div>
                    <div class="font-weight-bold">{{ shift.name }}</div>
                    <div class="text-body-2">{{ shift.schedule_label }}</div>
                    <div class="text-caption text-medium-emphasis mt-1">{{ shift.working_days?.join(', ') }}</div>
                  </div>
                  <v-chip size="small" :color="shift.status === 'Active' ? 'success' : 'secondary'" variant="tonal">
                    {{ shift.status }}
                  </v-chip>
                </div>
                <div class="d-flex justify-space-between align-center mt-3">
                  <div class="text-caption text-medium-emphasis">
                    Assigned: {{ shift.assigned_count ?? 0 }} | Break: {{ shift.break_duration ?? 60 }} min | Hours: {{ shift.duration_hours ?? 0 }}
                  </div>
                  <div class="d-flex ga-1">
                    <v-btn icon="mdi-pencil" size="small" variant="text" @click="editShift(shift)" />
                    <v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="removeShift(shift)" />
                  </div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-divider class="my-4" />

        <div class="text-subtitle-1 font-weight-medium mb-3">
          {{ editingShift ? 'Edit Shift' : 'Create Shift' }}
        </div>

        <v-row>
          <v-col cols="12" md="6">
            <v-text-field v-model="shiftForm.name" label="Shift Name *" variant="outlined" :error-messages="shiftErrors.name?.[0]" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="shiftForm.start_time" label="Start Time *" type="time" variant="outlined" :error-messages="shiftErrors.start_time?.[0]" />
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field v-model="shiftForm.end_time" label="End Time *" type="time" variant="outlined" :error-messages="shiftErrors.end_time?.[0]" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model.number="shiftForm.break_duration" label="Break Duration (min)" type="number" min="0" variant="outlined" />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="shiftForm.color" label="Color" variant="outlined" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select v-model="shiftForm.status" :items="['Active', 'Inactive']" label="Status" variant="outlined" />
          </v-col>
          <v-col cols="12">
            <v-select
              v-model="shiftForm.working_days"
              :items="dayOptions"
              label="Working Days"
              variant="outlined"
              multiple
              chips
              :error-messages="shiftErrors.working_days?.[0]"
            />
          </v-col>
          <v-col cols="12">
            <v-textarea v-model="shiftForm.description" label="Description" variant="outlined" rows="2" />
          </v-col>
        </v-row>

        <v-alert type="info" variant="tonal" class="mt-2">
          {{ schedulePreview }} | Net hours after break: {{ durationPreview }}
        </v-alert>
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="manageShiftsDialog = false">Close</v-btn>
        <v-btn variant="outlined" @click="resetShiftForm">Reset</v-btn>
        <v-btn color="primary" variant="flat" :loading="shiftSaving" @click="saveShift">Save Shift</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDeleteDialog" max-width="420">
    <v-card>
      <v-card-title class="pa-4">Remove Schedule</v-card-title>
      <v-card-text class="px-4">
        Remove the schedule for <strong>{{ deletingItem?.employee?.name }}</strong>?
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="confirmDeleteDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" :loading="deleteSaving" @click="confirmDeleteSchedule">Remove</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3500">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.schedule-table :deep(th),
.schedule-table :deep(td),
.weekly-table :deep(th),
.weekly-table :deep(td) {
  white-space: nowrap;
}

.shift-card {
  border-left: 4px solid #4f6ef7;
}
</style>
