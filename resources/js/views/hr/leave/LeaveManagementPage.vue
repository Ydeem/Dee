<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface LeaveRequestItem {
  id: number;
  from_date: string;
  to_date: string;
  from_date_raw: string;
  to_date_raw: string;
  days_requested: number;
  reason: string | null;
  status: string;
  status_color: string;
  can_approve: boolean;
  can_reject: boolean;
  can_cancel: boolean;
  rejection_reason: string | null;
  applied_on: string | null;
  approved_at: string | null;
  employee: {
    id: number;
    name: string;
    employee_id: string;
    avatar: string | null;
    initials: string;
    department: string;
  } | null;
  leave_type: {
    id: number;
    name: string;
    color: string;
  } | null;
}

interface LeaveTypeItem {
  id: number;
  name: string;
  days_allowed?: number;
  color?: string | null;
  description?: string | null;
  requires_approval?: boolean;
  carry_forward?: boolean;
  max_carry_forward_days?: number | null;
  applicable_gender?: string | null;
  status?: string;
  leave_requests_count?: number;
}

interface BalanceEmployee {
  id: number;
  name: string;
  employee_id: string;
  avatar: string | null;
  initials: string;
  department: string;
  balances: Array<{
    leave_type_id: number;
    name: string;
    color: string;
    allowed: number;
    used: number;
    remaining: number;
  }>;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Leave Management', disabled: true, href: '#' },
];

const tab = ref('requests');
const loading = ref(false);
const requests = ref<LeaveRequestItem[]>([]);
const stats = ref({ pending: 0, approved: 0, rejected: 0, on_leave_today: 0 });
const departments = ref<string[]>([]);
const leaveTypes = ref<string[]>([]);
const leaveTypeOptions = ref<LeaveTypeItem[]>([]);
const employeeOptions = ref<Array<{ id: number; name: string }>>([]);
const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({
  search: '',
  department: '',
  leaveType: '',
  status: '',
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
});

const balancesLoading = ref(false);
const balanceEmployees = ref<BalanceEmployee[]>([]);
const balanceLeaveTypes = ref<Array<{ id: number; name: string; color: string }>>([]);
const balancePagination = reactive({ page: 1, total: 0 });
const balanceSearch = ref('');
const balanceDept = ref('');
const balanceYear = ref(new Date().getFullYear());

const snackbar = ref({ show: false, message: '', color: 'success' });

const newLeaveDialog = ref(false);
const newLeaveErrors = ref<Record<string, string[]>>({});
const newLeaveSaving = ref(false);
const newLeaveForm = reactive({
  employee_id: null as number | null,
  leave_type_id: null as number | null,
  from_date: '',
  to_date: '',
  reason: '',
});

const editingLeaveId = ref<number | null>(null);
const drawer = ref(false);

const rejectDialog = ref(false);
const rejectingReq = ref<LeaveRequestItem | null>(null);
const rejectReason = ref('');
const rejectSaving = ref(false);

const detailDialog = ref(false);
const detailRequest = ref<LeaveRequestItem | null>(null);

const deleteDialog = ref(false);
const deletingReq = ref<LeaveRequestItem | null>(null);
const deleting = ref(false);

const leaveTypesDialog = ref(false);
const leaveTypeFormOpen = ref(false);
const leaveTypeSaving = ref(false);
const leaveTypeFormErrors = ref<Record<string, string[]>>({});
const leaveTypeForm = reactive({
  id: null as number | null,
  name: '',
  days_allowed: 1,
  color: '#4f6ef7',
  description: '',
  requires_approval: true,
  carry_forward: false,
  max_carry_forward_days: null as number | null,
  applicable_gender: 'All',
  status: 'Active',
});

const statusOptions = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
const activeLeaveTypeOptions = computed(() => leaveTypeOptions.value.filter((type) => type.status === 'Active'));
const requestLeaveTypeOptions = computed(() => activeLeaveTypeOptions.value.map((type) => ({ title: type.name, value: type.id })));
const requestEmployeeOptions = computed(() => employeeOptions.value.map((employee) => ({ title: employee.name, value: employee.id })));

const requestedDays = computed(() => {
  if (!newLeaveForm.from_date || !newLeaveForm.to_date) return 0;
  const start = new Date(newLeaveForm.from_date);
  const end = new Date(newLeaveForm.to_date);
  if (end < start) return 0;

  let days = 0;
  const current = new Date(start);

  while (current <= end) {
    const weekday = current.getDay();
    if (weekday !== 0 && weekday !== 6) days += 1;
    current.setDate(current.getDate() + 1);
  }

  return days;
});

const selectedBalance = computed(() => {
  if (!newLeaveForm.employee_id || !newLeaveForm.leave_type_id) return null;
  const employee = balanceEmployees.value.find((item) => item.id === newLeaveForm.employee_id);
  return employee?.balances.find((balance) => balance.leave_type_id === newLeaveForm.leave_type_id) ?? null;
});

function resetNewLeaveForm() {
  editingLeaveId.value = null;
  newLeaveErrors.value = {};
  newLeaveForm.employee_id = null;
  newLeaveForm.leave_type_id = null;
  newLeaveForm.from_date = '';
  newLeaveForm.to_date = '';
  newLeaveForm.reason = '';
}

function resetLeaveTypeForm() {
  leaveTypeFormErrors.value = {};
  leaveTypeForm.id = null;
  leaveTypeForm.name = '';
  leaveTypeForm.days_allowed = 1;
  leaveTypeForm.color = '#4f6ef7';
  leaveTypeForm.description = '';
  leaveTypeForm.requires_approval = true;
  leaveTypeForm.carry_forward = false;
  leaveTypeForm.max_carry_forward_days = null;
  leaveTypeForm.applicable_gender = 'All';
  leaveTypeForm.status = 'Active';
}

function openNewLeaveDialog() {
  resetNewLeaveForm();
  newLeaveDialog.value = true;
}

function openEditDrawer(req: LeaveRequestItem) {
  editingLeaveId.value = req.id;
  newLeaveErrors.value = {};
  newLeaveForm.employee_id = req.employee?.id ?? null;
  newLeaveForm.leave_type_id = req.leave_type?.id ?? null;
  newLeaveForm.from_date = req.from_date_raw;
  newLeaveForm.to_date = req.to_date_raw;
  newLeaveForm.reason = req.reason ?? '';
  drawer.value = true;
}

function openViewDialog(req: LeaveRequestItem) {
  detailRequest.value = req;
  detailDialog.value = true;
}

function openRejectDialog(req: LeaveRequestItem) {
  rejectingReq.value = req;
  rejectReason.value = '';
  rejectDialog.value = true;
}

function askDelete(req: LeaveRequestItem) {
  deletingReq.value = req;
  deleteDialog.value = true;
}

async function fetchLeaveRequests() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/leave-requests', {
      params: {
        month: filters.month,
        year: filters.year,
        search: filters.search || undefined,
        department: filters.department || undefined,
        leave_type: filters.leaveType || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
      },
    });

    requests.value = data.requests?.data ?? [];
    pagination.total = data.requests?.total ?? 0;
    stats.value = data.stats ?? { pending: 0, approved: 0, rejected: 0, on_leave_today: 0 };
    departments.value = data.filters?.departments ?? [];
    leaveTypes.value = data.filters?.leave_types ?? [];
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load leave requests.', color: 'error' };
  } finally {
    loading.value = false;
  }
}

async function fetchLeaveTypes() {
  try {
    const { data } = await axios.get('/api/hr/leave-types');
    leaveTypeOptions.value = data.leave_types ?? [];
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load leave types.', color: 'error' };
  }
}

async function fetchEmployeeOptions() {
  try {
    const { data } = await axios.get('/api/hr/employees', {
      params: { per_page: 1000, status: 'Active' },
    });

    employeeOptions.value = (data.employees?.data ?? []).map((employee: any) => ({
      id: employee.id,
      name: employee.full_name ?? `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim(),
    }));
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load employees.', color: 'error' };
  }
}

async function fetchLeaveBalances() {
  balancesLoading.value = true;
  try {
    const { data } = await axios.get('/api/hr/leave-balances', {
      params: {
        year: balanceYear.value,
        search: balanceSearch.value || undefined,
        department: balanceDept.value || undefined,
        page: balancePagination.page,
        per_page: 10,
      },
    });

    balanceEmployees.value = data.employees?.data ?? [];
    balanceLeaveTypes.value = data.leave_types ?? [];
    balancePagination.total = data.employees?.total ?? 0;
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load leave balances.', color: 'error' };
  } finally {
    balancesLoading.value = false;
  }
}

async function saveNewLeave() {
  newLeaveSaving.value = true;
  newLeaveErrors.value = {};

  try {
    const payload = {
      employee_id: newLeaveForm.employee_id,
      leave_type_id: newLeaveForm.leave_type_id,
      from_date: newLeaveForm.from_date,
      to_date: newLeaveForm.to_date,
      reason: newLeaveForm.reason || null,
    };

    const { data } = editingLeaveId.value
      ? await axios.put(`/api/hr/leave-requests/${editingLeaveId.value}`, payload)
      : await axios.post('/api/hr/leave-requests', payload);

    snackbar.value = {
      show: true,
      message: data.message ?? (editingLeaveId.value ? 'Leave request updated.' : 'Leave request submitted.'),
      color: 'success',
    };

    newLeaveDialog.value = false;
    drawer.value = false;
    resetNewLeaveForm();
    await Promise.all([fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    if (err?.response?.status === 422) {
      newLeaveErrors.value = err.response.data.errors ?? {};
    }

    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to save leave request.', color: 'error' };
  } finally {
    newLeaveSaving.value = false;
  }
}

async function approveRequest(req: LeaveRequestItem) {
  try {
    const { data } = await axios.patch(`/api/hr/leave-requests/${req.id}/approve`);
    snackbar.value = { show: true, message: data.message, color: 'success' };
    await Promise.all([fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to approve.', color: 'error' };
  }
}

async function confirmReject() {
  if (!rejectingReq.value) return;
  rejectSaving.value = true;

  try {
    const { data } = await axios.patch(`/api/hr/leave-requests/${rejectingReq.value.id}/reject`, {
      reason: rejectReason.value,
    });

    snackbar.value = { show: true, message: data.message, color: 'success' };
    rejectDialog.value = false;
    await Promise.all([fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to reject.', color: 'error' };
  } finally {
    rejectSaving.value = false;
  }
}

async function cancelRequest(req: LeaveRequestItem) {
  try {
    const { data } = await axios.patch(`/api/hr/leave-requests/${req.id}/cancel`);
    snackbar.value = { show: true, message: data.message, color: 'success' };
    await Promise.all([fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to cancel.', color: 'error' };
  }
}

async function confirmDelete() {
  if (!deletingReq.value) return;
  deleting.value = true;

  try {
    const { data } = await axios.delete(`/api/hr/leave-requests/${deletingReq.value.id}`);
    snackbar.value = { show: true, message: data.message ?? 'Leave request deleted.', color: 'success' };
    deleteDialog.value = false;
    await Promise.all([fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to delete.', color: 'error' };
  } finally {
    deleting.value = false;
  }
}

function openLeaveTypesDialog() {
  leaveTypesDialog.value = true;
  leaveTypeFormOpen.value = false;
  resetLeaveTypeForm();
}

function openLeaveTypeForm(type?: LeaveTypeItem) {
  leaveTypeFormOpen.value = true;
  leaveTypeFormErrors.value = {};

  if (!type) {
    resetLeaveTypeForm();
    leaveTypeFormOpen.value = true;
    return;
  }

  leaveTypeForm.id = type.id;
  leaveTypeForm.name = type.name;
  leaveTypeForm.days_allowed = Number(type.days_allowed ?? 1);
  leaveTypeForm.color = type.color ?? '#4f6ef7';
  leaveTypeForm.description = type.description ?? '';
  leaveTypeForm.requires_approval = Boolean(type.requires_approval);
  leaveTypeForm.carry_forward = Boolean(type.carry_forward);
  leaveTypeForm.max_carry_forward_days = type.max_carry_forward_days ?? null;
  leaveTypeForm.applicable_gender = type.applicable_gender ?? 'All';
  leaveTypeForm.status = type.status ?? 'Active';
}

async function saveLeaveType() {
  leaveTypeSaving.value = true;
  leaveTypeFormErrors.value = {};

  try {
    const payload = {
      name: leaveTypeForm.name,
      days_allowed: leaveTypeForm.days_allowed,
      color: leaveTypeForm.color || null,
      description: leaveTypeForm.description || null,
      requires_approval: leaveTypeForm.requires_approval,
      carry_forward: leaveTypeForm.carry_forward,
      max_carry_forward_days: leaveTypeForm.max_carry_forward_days,
      applicable_gender: leaveTypeForm.applicable_gender || null,
      status: leaveTypeForm.status,
    };

    const { data } = leaveTypeForm.id
      ? await axios.put(`/api/hr/leave-types/${leaveTypeForm.id}`, payload)
      : await axios.post('/api/hr/leave-types', payload);

    snackbar.value = { show: true, message: data.message ?? 'Leave type saved.', color: 'success' };
    leaveTypeFormOpen.value = false;
    resetLeaveTypeForm();
    await Promise.all([fetchLeaveTypes(), fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    if (err?.response?.status === 422) {
      leaveTypeFormErrors.value = err.response.data.errors ?? {};
    }

    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Failed to save leave type.', color: 'error' };
  } finally {
    leaveTypeSaving.value = false;
  }
}

async function deleteLeaveType(type: LeaveTypeItem) {
  try {
    const { data } = await axios.delete(`/api/hr/leave-types/${type.id}`);
    snackbar.value = { show: true, message: data.message ?? 'Leave type deleted.', color: 'success' };
    await Promise.all([fetchLeaveTypes(), fetchLeaveRequests(), fetchLeaveBalances()]);
  } catch (err: any) {
    snackbar.value = { show: true, message: err?.response?.data?.message ?? 'Cannot delete leave type.', color: 'error' };
  }
}

watch([() => filters.search, () => filters.department, () => filters.leaveType, () => filters.status, () => filters.month, () => filters.year], () => {
  pagination.page = 1;
  fetchLeaveRequests();
});

watch([balanceSearch, balanceDept, balanceYear], () => {
  balancePagination.page = 1;
  fetchLeaveBalances();
});

watch(() => pagination.page, () => {
  fetchLeaveRequests();
});

watch(() => balancePagination.page, () => {
  fetchLeaveBalances();
});

onMounted(async () => {
  await Promise.all([fetchLeaveTypes(), fetchEmployeeOptions(), fetchLeaveRequests(), fetchLeaveBalances()]);
});
</script>

<template>
  <BaseBreadcrumb title="Leave Management" subtitle="Manage employee leave requests and balances" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Leave Management</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage employee leave requests, balances, and leave types</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-cog" @click="openLeaveTypesDialog">Leave Types</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openNewLeaveDialog">New Leave Request</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Pending: <strong>{{ stats.pending }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Approved: <strong>{{ stats.approved }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Rejected: <strong>{{ stats.rejected }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>On Leave Today: <strong>{{ stats.on_leave_today }}</strong></v-card-text></v-card></v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-tabs v-model="tab" color="primary" class="px-4 pt-2">
      <v-tab value="requests">Leave Requests</v-tab>
      <v-tab value="balances">Leave Balances</v-tab>
    </v-tabs>
    <v-divider />
    <v-window v-model="tab">
      <v-window-item value="requests">
        <div class="pa-4">
          <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
            <v-card-text>
              <v-row>
                <v-col cols="12" md="3"><v-text-field v-model="filters.search" placeholder="Search employee..." variant="outlined" hide-details /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.department" :items="departments" label="Department" variant="outlined" hide-details clearable /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.leaveType" :items="leaveTypes" label="Leave Type" variant="outlined" hide-details clearable /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details clearable /></v-col>
                <v-col cols="6" md="1"><v-text-field v-model.number="filters.month" type="number" min="1" max="12" label="Month" variant="outlined" hide-details /></v-col>
                <v-col cols="6" md="2"><v-text-field v-model.number="filters.year" type="number" min="2020" label="Year" variant="outlined" hide-details /></v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <v-table>
            <thead>
              <tr>
                <th>Employee</th>
                <th>Leave Type</th>
                <th>From</th>
                <th>To</th>
                <th>Days</th>
                <th>Status</th>
                <th>Applied On</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="8"><v-skeleton-loader type="table-row@5" /></td>
              </tr>
              <tr v-else-if="requests.length === 0">
                <td colspan="8" class="text-center py-10 text-medium-emphasis">No leave requests found.</td>
              </tr>
              <tr v-for="req in requests" v-else :key="req.id">
                <td>
                  <div v-if="req.employee" class="d-flex align-center ga-3 cursor-pointer" @click="router.visit('/hr/employees/' + req.employee.id)">
                    <v-avatar color="primary" variant="tonal" size="34">
                      <v-img v-if="req.employee.avatar" :src="req.employee.avatar" />
                      <span v-else class="text-caption font-weight-bold">{{ req.employee.initials }}</span>
                    </v-avatar>
                    <div>
                      <div class="font-weight-medium">{{ req.employee.name }}</div>
                      <div class="text-caption text-lightText">{{ req.employee.employee_id }} · {{ req.employee.department }}</div>
                    </div>
                  </div>
                </td>
                <td><v-chip v-if="req.leave_type" size="small" :color="req.leave_type.color" variant="tonal">{{ req.leave_type.name }}</v-chip></td>
                <td>{{ req.from_date }}</td>
                <td>{{ req.to_date }}</td>
                <td>{{ req.days_requested }} day<span v-if="req.days_requested !== 1">s</span></td>
                <td><v-chip size="small" :color="req.status_color" variant="tonal">{{ req.status }}</v-chip></td>
                <td>{{ req.applied_on }}</td>
                <td>
                  <div class="d-flex gap-1">
                    <v-btn v-if="req.can_approve" icon size="small" color="success" variant="tonal" @click="approveRequest(req)" title="Approve"><v-icon size="16">mdi-check</v-icon></v-btn>
                    <v-btn v-if="req.can_reject" icon size="small" color="error" variant="tonal" @click="openRejectDialog(req)" title="Reject"><v-icon size="16">mdi-close</v-icon></v-btn>
                    <v-btn v-if="req.can_cancel" icon size="small" color="warning" variant="tonal" @click="cancelRequest(req)" title="Cancel"><v-icon size="16">mdi-cancel</v-icon></v-btn>
                    <v-btn icon size="small" variant="text" @click="openViewDialog(req)" title="View Details"><v-icon size="16">mdi-eye</v-icon></v-btn>
                    <v-btn v-if="req.status === 'Pending'" icon size="small" variant="text" @click="openEditDrawer(req)" title="Edit"><v-icon size="16">mdi-pencil</v-icon></v-btn>
                    <v-btn icon size="small" color="error" variant="text" @click="askDelete(req)" title="Delete"><v-icon size="16">mdi-delete</v-icon></v-btn>
                  </div>
                </td>
              </tr>
            </tbody>
          </v-table>

          <div class="d-flex justify-space-between align-center mt-4">
            <div class="text-body-2 text-medium-emphasis">Showing {{ requests.length }} of {{ pagination.total }} records</div>
            <div class="d-flex align-center ga-3">
              <v-select v-model="pagination.perPage" :items="[10, 25, 50]" label="Rows" density="compact" variant="outlined" hide-details style="max-width: 110px" @update:model-value="pagination.page = 1; fetchLeaveRequests()" />
              <v-pagination v-model="pagination.page" :length="Math.max(1, Math.ceil(pagination.total / pagination.perPage))" density="comfortable" />
            </div>
          </div>
        </div>
      </v-window-item>

      <v-window-item value="balances">
        <div class="pa-4">
          <v-row class="mb-2">
            <v-col cols="12" md="4"><v-text-field v-model="balanceSearch" label="Search employee" variant="outlined" hide-details /></v-col>
            <v-col cols="12" md="4"><v-select v-model="balanceDept" :items="departments" label="Department" variant="outlined" hide-details clearable /></v-col>
            <v-col cols="12" md="4"><v-text-field v-model.number="balanceYear" type="number" label="Year" variant="outlined" hide-details /></v-col>
          </v-row>

          <v-table>
            <thead>
              <tr>
                <th>Employee</th>
                <th v-for="type in balanceLeaveTypes" :key="type.id">{{ type.name }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="balancesLoading">
                <td :colspan="Math.max(2, balanceLeaveTypes.length + 1)"><v-skeleton-loader type="table-row@4" /></td>
              </tr>
              <tr v-else-if="balanceEmployees.length === 0">
                <td :colspan="Math.max(2, balanceLeaveTypes.length + 1)" class="text-center py-10 text-medium-emphasis">No leave balances found.</td>
              </tr>
              <tr v-for="emp in balanceEmployees" v-else :key="emp.id">
                <td>
                  <div class="d-flex align-center gap-2">
                    <v-avatar size="32" color="primary" variant="tonal">
                      <v-img v-if="emp.avatar" :src="emp.avatar" />
                      <span v-else class="text-caption">{{ emp.initials }}</span>
                    </v-avatar>
                    <div>
                      <div class="text-body-2 font-weight-medium">{{ emp.name }}</div>
                      <div class="text-caption text-medium-emphasis">{{ emp.department }}</div>
                    </div>
                  </div>
                </td>
                <td v-for="balance in emp.balances" :key="balance.leave_type_id" class="text-center">
                  <div class="text-body-2 font-weight-medium" :class="balance.remaining === 0 ? 'text-error' : ''">
                    {{ balance.remaining }}
                    <span class="text-caption text-medium-emphasis">/ {{ balance.allowed }}</span>
                  </div>
                  <v-progress-linear :model-value="balance.allowed ? (balance.used / balance.allowed) * 100 : 0" :color="balance.remaining === 0 ? 'error' : 'primary'" height="3" rounded class="mt-1" />
                </td>
              </tr>
            </tbody>
          </v-table>

          <div class="d-flex justify-end mt-4">
            <v-pagination v-model="balancePagination.page" :length="Math.max(1, Math.ceil(balancePagination.total / 10))" density="comfortable" />
          </div>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-dialog v-model="newLeaveDialog" max-width="540">
    <v-card>
      <v-card-title>New Leave Request</v-card-title>
      <v-card-text>
        <v-autocomplete v-model="newLeaveForm.employee_id" :items="requestEmployeeOptions" item-title="title" item-value="value" label="Employee *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.employee_id?.[0]" />
        <v-select v-model="newLeaveForm.leave_type_id" :items="requestLeaveTypeOptions" item-title="title" item-value="value" label="Leave Type *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.leave_type_id?.[0]" />
        <v-row>
          <v-col cols="12" md="6"><v-text-field v-model="newLeaveForm.from_date" type="date" label="From Date *" variant="outlined" :error-messages="newLeaveErrors.from_date?.[0]" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model="newLeaveForm.to_date" type="date" label="To Date *" variant="outlined" :error-messages="newLeaveErrors.to_date?.[0]" /></v-col>
        </v-row>
        <v-text-field :model-value="`${requestedDays} business days`" label="Days Requested" variant="outlined" readonly class="mb-3" />
        <v-textarea v-model="newLeaveForm.reason" label="Reason" variant="outlined" rows="3" />
        <v-card v-if="selectedBalance" variant="tonal" class="mt-3"><v-card-text>{{ selectedBalance.name }}: {{ selectedBalance.used }} used of {{ selectedBalance.allowed }} days ({{ selectedBalance.remaining }} remaining)</v-card-text></v-card>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="newLeaveDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :loading="newLeaveSaving" @click="saveNewLeave">Save Request</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-navigation-drawer v-model="drawer" location="right" temporary width="540">
    <div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">Edit Leave Request</h5><v-btn icon="mdi-close" variant="text" @click="drawer = false" /></div>
    <div class="pa-4 drawer-body">
      <v-autocomplete v-model="newLeaveForm.employee_id" :items="requestEmployeeOptions" item-title="title" item-value="value" label="Employee *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.employee_id?.[0]" />
      <v-select v-model="newLeaveForm.leave_type_id" :items="requestLeaveTypeOptions" item-title="title" item-value="value" label="Leave Type *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.leave_type_id?.[0]" />
      <v-text-field v-model="newLeaveForm.from_date" type="date" label="From Date *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.from_date?.[0]" />
      <v-text-field v-model="newLeaveForm.to_date" type="date" label="To Date *" variant="outlined" class="mb-3" :error-messages="newLeaveErrors.to_date?.[0]" />
      <v-text-field :model-value="`${requestedDays} business days`" label="Days Requested" variant="outlined" readonly class="mb-3" />
      <v-textarea v-model="newLeaveForm.reason" label="Reason" rows="3" variant="outlined" />
    </div>
    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="drawer = false">Cancel</v-btn><v-btn color="primary" :loading="newLeaveSaving" @click="saveNewLeave">Save Changes</v-btn></div>
  </v-navigation-drawer>

  <v-dialog v-model="rejectDialog" max-width="420">
    <v-card>
      <v-card-title>Reject Leave Request</v-card-title>
      <v-card-text>
        <p class="text-body-2 mb-4">Rejecting leave for <strong>{{ rejectingReq?.employee?.name }}</strong> ({{ rejectingReq?.days_requested }} days)</p>
        <v-textarea v-model="rejectReason" label="Reason for rejection (optional)" variant="outlined" rows="3" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" :loading="rejectSaving" @click="confirmReject">Reject Request</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="detailDialog" max-width="540">
    <v-card>
      <v-card-title>Leave Request Details</v-card-title>
      <v-card-text v-if="detailRequest">
        <div><strong>Employee:</strong> {{ detailRequest.employee?.name }}</div>
        <div><strong>Leave Type:</strong> {{ detailRequest.leave_type?.name }}</div>
        <div><strong>Dates:</strong> {{ detailRequest.from_date }} to {{ detailRequest.to_date }}</div>
        <div><strong>Days:</strong> {{ detailRequest.days_requested }}</div>
        <div><strong>Status:</strong> {{ detailRequest.status }}</div>
        <div><strong>Applied On:</strong> {{ detailRequest.applied_on }}</div>
        <div><strong>Reason:</strong> {{ detailRequest.reason || '-' }}</div>
        <div v-if="detailRequest.approved_at"><strong>Approved At:</strong> {{ detailRequest.approved_at }}</div>
        <div v-if="detailRequest.rejection_reason" class="mt-3"><strong>Rejection Reason:</strong> {{ detailRequest.rejection_reason }}</div>
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog = false">Close</v-btn></v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title>Delete Leave Request</v-card-title>
      <v-card-text>Delete leave request for <strong>{{ deletingReq?.employee?.name }}</strong>?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" :loading="deleting" @click="confirmDelete">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="leaveTypesDialog" max-width="760">
    <v-card>
      <v-card-title class="d-flex justify-space-between align-center"><span>Leave Types</span><v-btn size="small" variant="outlined" prepend-icon="mdi-plus" @click="openLeaveTypeForm()">Add Leave Type</v-btn></v-card-title>
      <v-card-text>
        <v-table>
          <thead>
            <tr><th>Name</th><th>Days</th><th>Status</th><th>Requests</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <tr v-for="type in leaveTypeOptions" :key="type.id">
              <td><v-chip size="small" :color="type.color || '#4f6ef7'" variant="tonal">{{ type.name }}</v-chip></td>
              <td>{{ type.days_allowed }}</td>
              <td>{{ type.status }}</td>
              <td>{{ type.leave_requests_count ?? 0 }}</td>
              <td><v-btn icon="mdi-pencil" size="small" variant="text" @click="openLeaveTypeForm(type)" /><v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="deleteLeaveType(type)" /></td>
            </tr>
          </tbody>
        </v-table>

        <v-expand-transition>
          <div v-if="leaveTypeFormOpen" class="mt-4">
            <v-row>
              <v-col cols="12" md="6"><v-text-field v-model="leaveTypeForm.name" label="Name *" variant="outlined" :error-messages="leaveTypeFormErrors.name?.[0]" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model.number="leaveTypeForm.days_allowed" type="number" label="Days Allowed *" variant="outlined" :error-messages="leaveTypeFormErrors.days_allowed?.[0]" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model="leaveTypeForm.color" label="Color" variant="outlined" :error-messages="leaveTypeFormErrors.color?.[0]" /></v-col>
              <v-col cols="12" md="6"><v-select v-model="leaveTypeForm.applicable_gender" :items="['Male', 'Female', 'All']" label="Applicable Gender" variant="outlined" clearable /></v-col>
              <v-col cols="12" md="6"><v-select v-model="leaveTypeForm.status" :items="['Active', 'Inactive']" label="Status" variant="outlined" :error-messages="leaveTypeFormErrors.status?.[0]" /></v-col>
              <v-col cols="12" md="6"><v-text-field v-model.number="leaveTypeForm.max_carry_forward_days" type="number" label="Max Carry Forward Days" variant="outlined" /></v-col>
              <v-col cols="12"><v-textarea v-model="leaveTypeForm.description" label="Description" variant="outlined" rows="3" /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="leaveTypeForm.carry_forward" label="Carry Forward" /></v-col>
              <v-col cols="12" md="6"><v-switch v-model="leaveTypeForm.requires_approval" label="Requires Approval" /></v-col>
            </v-row>
            <div class="d-flex justify-end ga-2"><v-btn variant="outlined" @click="leaveTypeFormOpen = false">Cancel</v-btn><v-btn color="primary" :loading="leaveTypeSaving" @click="saveLeaveType">Save</v-btn></div>
          </div>
        </v-expand-transition>
      </v-card-text>
      <v-card-actions><v-spacer /><v-btn variant="text" @click="leaveTypesDialog = false">Close</v-btn></v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.cursor-pointer { cursor: pointer; }
.drawer-body { height: calc(100% - 130px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
</style>
