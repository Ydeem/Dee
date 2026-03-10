<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface LeaveRequest {
  id: number;
  from_date: string;
  to_date: string;
  days_requested: number;
  reason: string | null;
  status: string;
  rejection_reason: string | null;
  approved_at: string | null;
  created_at?: string;
  employee: {
    id: number;
    full_name: string;
    employee_id: string;
    avatar_url: string | null;
    department?: { name: string } | null;
  };
  leave_type: { id: number; name: string; color: string | null };
  approved_by_employee?: { full_name: string } | null;
}

interface LeaveType {
  id: number;
  name: string;
  days_allowed: number;
  carry_forward: boolean;
  requires_approval: boolean;
  color: string | null;
  status: string;
  leave_requests_count?: number;
}

interface Summary {
  pending: number;
  approved: number;
  rejected: number;
  on_leave_today: number;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Leave Management', disabled: true, href: '#' }
];

const loading = ref(false);
const tab = ref('requests');
const requests = ref<LeaveRequest[]>([]);
const leaveTypes = ref<LeaveType[]>([]);
const departments = ref<string[]>([]);
const summary = ref<Summary>({ pending: 0, approved: 0, rejected: 0, on_leave_today: 0 });
const balances = ref<any[]>([]);
const balanceTypes = ref<LeaveType[]>([]);
const employees = ref<any[]>([]);

const dummyDepartments = ['Human Resources', 'Engineering', 'Finance', 'Sales'];
const dummyLeaveTypes: LeaveType[] = [
  { id: 1, name: 'Annual Leave', days_allowed: 21, carry_forward: false, requires_approval: true, color: '#4f6ef7', status: 'Active' },
  { id: 2, name: 'Sick Leave', days_allowed: 14, carry_forward: false, requires_approval: true, color: '#f77c4f', status: 'Active' },
  { id: 3, name: 'Unpaid Leave', days_allowed: 30, carry_forward: false, requires_approval: true, color: '#9e9e9e', status: 'Active' }
];
const dummyEmployees = [
  { id: 1, full_name: 'Pontian Npontu', employee_id: 'EMP00001', avatar_url: null, department: { name: 'Human Resources' } },
  { id: 2, full_name: 'Sarah Oti', employee_id: 'EMP00002', avatar_url: null, department: { name: 'Human Resources' } },
  { id: 3, full_name: 'Daniel Kofi', employee_id: 'EMP00003', avatar_url: null, department: { name: 'Engineering' } }
];
const dummyRequests: LeaveRequest[] = [
  {
    id: 101,
    from_date: '2026-03-18',
    to_date: '2026-03-20',
    days_requested: 3,
    reason: 'Family event',
    status: 'Pending',
    rejection_reason: null,
    approved_at: null,
    created_at: '2026-03-10',
    employee: dummyEmployees[0],
    leave_type: { id: 1, name: 'Annual Leave', color: '#4f6ef7' },
    approved_by_employee: null
  },
  {
    id: 102,
    from_date: '2026-03-09',
    to_date: '2026-03-10',
    days_requested: 2,
    reason: 'Medical review',
    status: 'Approved',
    rejection_reason: null,
    approved_at: '2026-03-08',
    created_at: '2026-03-07',
    employee: dummyEmployees[1],
    leave_type: { id: 2, name: 'Sick Leave', color: '#f77c4f' },
    approved_by_employee: { full_name: 'HR Admin' }
  },
  {
    id: 103,
    from_date: '2026-03-01',
    to_date: '2026-03-03',
    days_requested: 3,
    reason: 'Project cooldown',
    status: 'Rejected',
    rejection_reason: 'Peak delivery week',
    approved_at: '2026-02-28',
    created_at: '2026-02-26',
    employee: dummyEmployees[2],
    leave_type: { id: 1, name: 'Annual Leave', color: '#4f6ef7' },
    approved_by_employee: { full_name: 'HR Admin' }
  }
];
const dummyBalances = [
  {
    employee: { id: 1, full_name: 'Pontian Npontu', avatar_url: null, department: 'Human Resources' },
    balances: [
      { leave_type_id: 1, name: 'Annual Leave', color: '#4f6ef7', allowed: 21, used: 4, remaining: 17 },
      { leave_type_id: 2, name: 'Sick Leave', color: '#f77c4f', allowed: 14, used: 1, remaining: 13 }
    ]
  },
  {
    employee: { id: 2, full_name: 'Sarah Oti', avatar_url: null, department: 'Human Resources' },
    balances: [
      { leave_type_id: 1, name: 'Annual Leave', color: '#4f6ef7', allowed: 21, used: 2, remaining: 19 },
      { leave_type_id: 2, name: 'Sick Leave', color: '#f77c4f', allowed: 14, used: 3, remaining: 11 }
    ]
  }
];

const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', department: '', leave_type: '', status: '', month: new Date().toISOString().slice(0, 7) });

const drawerOpen = ref(false);
const submitting = ref(false);
const editingRequestId = ref<number | null>(null);
const form = reactive({ employee_id: null as number | null, leave_type_id: null as number | null, from_date: '', to_date: '', reason: '' });

const rejectDialog = ref(false);
const rejectReason = ref('');
const rejectTarget = ref<LeaveRequest | null>(null);

const detailDialog = ref(false);
const detailTarget = ref<LeaveRequest | null>(null);

const confirmDialog = ref({ show: false, title: '', message: '', action: '' as 'approve' | 'cancel' | 'delete' | '', id: null as number | null });

const leaveTypesDialog = ref(false);
const leaveTypeFormOpen = ref(false);
const leaveTypeSaving = ref(false);
const leaveTypeForm = reactive({
  id: null as number | null,
  name: '',
  days_allowed: 1,
  color: '#4f6ef7',
  carry_forward: false,
  requires_approval: true,
  status: 'Active'
});

const snackbar = ref({ show: false, message: '', color: 'success' });
const statusOptions = [{ title: 'All', value: '' }, { title: 'Pending', value: 'Pending' }, { title: 'Approved', value: 'Approved' }, { title: 'Rejected', value: 'Rejected' }, { title: 'Cancelled', value: 'Cancelled' }];
const headers = [
  { title: 'Employee', key: 'employee', sortable: false },
  { title: 'Leave Type', key: 'leave_type', sortable: false },
  { title: 'From Date', key: 'from_date', sortable: true },
  { title: 'To Date', key: 'to_date', sortable: true },
  { title: 'Days', key: 'days_requested', sortable: false },
  { title: 'Reason', key: 'reason', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Applied On', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
];

const deptOptions = computed(() => [{ title: 'All Departments', value: '' }, ...departments.value.map((d) => ({ title: d, value: d }))]);
const leaveTypeFilterOptions = computed(() => [{ title: 'All Types', value: '' }, ...leaveTypes.value.map((l) => ({ title: l.name, value: l.id }))]);
const leaveTypeOptions = computed(() => leaveTypes.value.filter((l) => l.status === 'Active').map((l) => ({ title: l.name, value: l.id })));
const employeeOptions = computed(() => employees.value.map((e: any) => ({ title: `${e.full_name} (${e.employee_id})`, value: e.id })));

const requestedDays = computed(() => {
  if (!form.from_date || !form.to_date) return 0;
  const from = new Date(form.from_date);
  const to = new Date(form.to_date);
  if (to < from) return 0;
  let days = 0;
  const c = new Date(from);
  while (c <= to) {
    const w = c.getDay();
    if (w !== 0 && w !== 6) days += 1;
    c.setDate(c.getDate() + 1);
  }
  return days;
});

const selectedBalance = computed(() => {
  if (!form.employee_id || !form.leave_type_id) return null;
  const row = balances.value.find((b: any) => b.employee.id === form.employee_id);
  return row?.balances.find((b: any) => b.leave_type_id === form.leave_type_id) ?? null;
});

function rowData(item: any): LeaveRequest {
  return (item?.raw ?? item) as LeaveRequest;
}

function statusColor(status: string) {
  if (status === 'Pending') return 'warning';
  if (status === 'Approved') return 'success';
  if (status === 'Rejected') return 'error';
  return 'secondary';
}

function initials(name: string) {
  return name.split(' ').filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase();
}

function formatDate(date: string | null | undefined) {
  if (!date) return '-';
  return new Date(date).toLocaleDateString();
}

function getBalanceForType(row: any, typeId: number) {
  return row?.balances?.find((b: any) => b.leave_type_id === typeId) ?? null;
}

function applyDummyData() {
  requests.value = dummyRequests;
  pagination.total = dummyRequests.length;
  departments.value = dummyDepartments;
  leaveTypes.value = dummyLeaveTypes;
  balances.value = dummyBalances;
  balanceTypes.value = dummyLeaveTypes;
  employees.value = dummyEmployees;
  summary.value = {
    pending: dummyRequests.filter((r) => r.status === 'Pending').length,
    approved: dummyRequests.filter((r) => r.status === 'Approved').length,
    rejected: dummyRequests.filter((r) => r.status === 'Rejected').length,
    on_leave_today: dummyRequests.filter((r) => r.status === 'Approved').length
  };
}

// Render usable data immediately, then hydrate from API.
applyDummyData();

async function fetchRequests() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/leave-requests', { params: { ...filters, page: pagination.page, per_page: pagination.perPage } });
    requests.value = data?.requests?.data ?? [];
    pagination.total = data?.requests?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    departments.value = data?.departments ?? [];
    if ((data?.leave_types ?? []).length) {
      leaveTypes.value = data.leave_types.map((t: any) => ({
        id: t.id, name: t.name, days_allowed: t.days_allowed ?? 0, carry_forward: !!t.carry_forward, requires_approval: !!t.requires_approval, color: t.color, status: t.status ?? 'Active'
      }));
    }
    if (!requests.value.length) {
      applyDummyData();
    }
  } catch (error) {
    applyDummyData();
    snackbar.value = { show: true, message: 'Using dummy leave management data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

async function fetchLeaveTypes() {
  try {
    const { data } = await axios.get('/api/hr/leave-types');
    leaveTypes.value = data?.leave_types ?? [];
    if (!leaveTypes.value.length) leaveTypes.value = dummyLeaveTypes;
  } catch (error) {
    leaveTypes.value = dummyLeaveTypes;
  }
}

async function fetchBalances() {
  try {
    const { data } = await axios.get('/api/hr/leave-balances');
    balances.value = data?.balances ?? [];
    balanceTypes.value = data?.leave_types ?? [];
    if (!balances.value.length) {
      balances.value = dummyBalances;
      balanceTypes.value = dummyLeaveTypes;
    }
  } catch (error) {
    balances.value = dummyBalances;
    balanceTypes.value = dummyLeaveTypes;
  }
}

async function fetchEmployees() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    employees.value = data?.employees?.data ?? [];
    if (!employees.value.length) employees.value = dummyEmployees;
  } catch (error) {
    employees.value = dummyEmployees;
  }
}

function resetFilters() {
  filters.search = '';
  filters.department = '';
  filters.leave_type = '';
  filters.status = '';
  filters.month = new Date().toISOString().slice(0, 7);
}

function openNewRequest() {
  editingRequestId.value = null;
  form.employee_id = null;
  form.leave_type_id = null;
  form.from_date = '';
  form.to_date = '';
  form.reason = '';
  drawerOpen.value = true;
}

function openEditRequest(item: LeaveRequest) {
  editingRequestId.value = item.id;
  form.employee_id = item.employee.id;
  form.leave_type_id = item.leave_type.id;
  form.from_date = item.from_date;
  form.to_date = item.to_date;
  form.reason = item.reason ?? '';
  drawerOpen.value = true;
}

async function submitRequest() {
  if (!form.employee_id || !form.leave_type_id || !form.from_date || !form.to_date) {
    snackbar.value = { show: true, message: 'Please complete required fields.', color: 'error' };
    return;
  }
  submitting.value = true;
  const wasEditing = Boolean(editingRequestId.value);
  try {
    if (editingRequestId.value) {
      await axios.put(`/api/hr/leave-requests/${editingRequestId.value}`, { ...form, reason: form.reason || null });
    } else {
      await axios.post('/api/hr/leave-requests', { ...form, reason: form.reason || null });
    }
    drawerOpen.value = false;
    editingRequestId.value = null;
    snackbar.value = { show: true, message: wasEditing ? 'Leave request updated.' : 'Leave request submitted.', color: 'success' };
    await Promise.all([fetchRequests(), fetchBalances()]);
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Failed to submit request.', color: 'error' };
  } finally {
    submitting.value = false;
  }
}

function askApprove(item: LeaveRequest) {
  confirmDialog.value = { show: true, title: 'Approve Leave Request', message: `Approve request for ${item.employee.full_name}?`, action: 'approve', id: item.id };
}

function openReject(item: LeaveRequest) {
  rejectTarget.value = item;
  rejectReason.value = '';
  rejectDialog.value = true;
}

async function submitReject() {
  if (!rejectTarget.value) return;
  if (!rejectReason.value.trim()) {
    snackbar.value = { show: true, message: 'Rejection reason is required.', color: 'error' };
    return;
  }
  try {
    await axios.patch(`/api/hr/leave-requests/${rejectTarget.value.id}/reject`, { rejection_reason: rejectReason.value.trim() });
    rejectDialog.value = false;
    snackbar.value = { show: true, message: 'Leave request rejected.', color: 'success' };
    await Promise.all([fetchRequests(), fetchBalances()]);
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Reject failed.', color: 'error' };
  }
}

function askCancel(item: LeaveRequest) {
  confirmDialog.value = { show: true, title: 'Cancel Leave Request', message: `Cancel request for ${item.employee.full_name}?`, action: 'cancel', id: item.id };
}

function askDelete(item: LeaveRequest) {
  confirmDialog.value = { show: true, title: 'Delete Leave Request', message: `Delete request for ${item.employee.full_name}?`, action: 'delete', id: item.id };
}

async function runConfirm() {
  if (!confirmDialog.value.id) return;
  try {
    if (confirmDialog.value.action === 'approve') await axios.patch(`/api/hr/leave-requests/${confirmDialog.value.id}/approve`);
    if (confirmDialog.value.action === 'cancel') await axios.patch(`/api/hr/leave-requests/${confirmDialog.value.id}/cancel`);
    if (confirmDialog.value.action === 'delete') await axios.delete(`/api/hr/leave-requests/${confirmDialog.value.id}`);
    confirmDialog.value.show = false;
    snackbar.value = { show: true, message: 'Action completed.', color: 'success' };
    await Promise.all([fetchRequests(), fetchBalances()]);
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Action failed.', color: 'error' };
  }
}

function openDetail(item: LeaveRequest) {
  detailTarget.value = item;
  detailDialog.value = true;
}

function openLeaveTypeForm(item?: LeaveType) {
  if (item) Object.assign(leaveTypeForm, item);
  else Object.assign(leaveTypeForm, { id: null, name: '', days_allowed: 1, color: '#4f6ef7', carry_forward: false, requires_approval: true, status: 'Active' });
  leaveTypeFormOpen.value = true;
}

async function saveLeaveType() {
  leaveTypeSaving.value = true;
  try {
    const payload = { ...leaveTypeForm };
    if (leaveTypeForm.id) await axios.put(`/api/hr/leave-types/${leaveTypeForm.id}`, payload);
    else await axios.post('/api/hr/leave-types', payload);
    leaveTypeFormOpen.value = false;
    await fetchLeaveTypes();
    snackbar.value = { show: true, message: 'Leave type saved.', color: 'success' };
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Save failed.', color: 'error' };
  } finally {
    leaveTypeSaving.value = false;
  }
}

async function deleteLeaveType(type: LeaveType) {
  try {
    await axios.delete(`/api/hr/leave-types/${type.id}`);
    await fetchLeaveTypes();
    snackbar.value = { show: true, message: 'Leave type deleted.', color: 'success' };
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Cannot delete this leave type.', color: 'error' };
  }
}

watch(() => [filters.search, filters.department, filters.leave_type, filters.status, filters.month], () => {
  pagination.page = 1;
  fetchRequests();
});

onMounted(async () => {
  await Promise.all([fetchLeaveTypes(), fetchEmployees(), fetchRequests(), fetchBalances()]);
});
</script>

<template>
  <BaseBreadcrumb title="Leave Management" subtitle="Manage employee leave requests and policies" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Leave Management</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage employee leave requests and policies</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-cog" @click="leaveTypesDialog = true">Leave Types</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openNewRequest">New Leave Request</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Pending: <strong>{{ summary.pending }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Approved: <strong>{{ summary.approved }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Rejected: <strong>{{ summary.rejected }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>On Leave Today: <strong>{{ summary.on_leave_today }}</strong></v-card-text></v-card></v-col>
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
                <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by employee name..." variant="outlined" hide-details /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.department" :items="deptOptions" label="Department" variant="outlined" hide-details /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.leave_type" :items="leaveTypeFilterOptions" label="Leave Type" variant="outlined" hide-details /></v-col>
                <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details /></v-col>
                <v-col cols="12" sm="6" md="2"><v-text-field v-model="filters.month" type="month" label="Month" variant="outlined" hide-details /></v-col>
              </v-row>
              <div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div>
            </v-card-text>
          </v-card>

          <v-skeleton-loader v-if="loading && !requests.length" type="table" />
          <v-data-table-server v-else :headers="headers" :items="requests" :items-length="pagination.total" :items-per-page="pagination.perPage" :page="pagination.page" :items-per-page-options="[10,25,50]" item-value="id" @update:options="(o)=>{pagination.page=o.page;pagination.perPage=o.itemsPerPage;fetchRequests();}">
            <template #item.employee="{ item }">
              <div class="d-flex align-center ga-3 cursor-pointer" @click="router.visit(`/hr/employees/${rowData(item).employee.id}`)">
                <v-avatar color="primary" variant="tonal" size="34">
                  <img v-if="rowData(item).employee.avatar_url" :src="rowData(item).employee.avatar_url || ''" :alt="rowData(item).employee.full_name" />
                  <span v-else class="text-caption font-weight-bold">{{ initials(rowData(item).employee.full_name) }}</span>
                </v-avatar>
                <div>
                  <div class="font-weight-medium">{{ rowData(item).employee.full_name }}</div>
                  <div class="text-caption text-lightText">{{ rowData(item).employee.department?.name ?? '-' }}</div>
                </div>
              </div>
            </template>
            <template #item.leave_type="{ item }"><v-chip size="small" :color="rowData(item).leave_type.color || '#4f6ef7'" variant="flat">{{ rowData(item).leave_type.name }}</v-chip></template>
            <template #item.from_date="{ item }">{{ formatDate(rowData(item).from_date) }}</template>
            <template #item.to_date="{ item }">{{ formatDate(rowData(item).to_date) }}</template>
            <template #item.days_requested="{ item }"><v-chip size="small" color="secondary" variant="tonal">{{ rowData(item).days_requested }} days</v-chip></template>
            <template #item.reason="{ item }"><div class="text-truncate" style="max-width:180px" :title="rowData(item).reason ?? '-'">{{ rowData(item).reason ?? '-' }}</div></template>
            <template #item.status="{ item }"><v-chip size="small" :color="statusColor(rowData(item).status)" variant="tonal">{{ rowData(item).status }}</v-chip></template>
            <template #item.created_at="{ item }">{{ formatDate(rowData(item).created_at) }}</template>
            <template #item.actions="{ item }">
              <v-menu>
                <template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template>
                <v-list>
                  <v-list-item v-if="rowData(item).status==='Pending'" title="Edit" @click="openEditRequest(rowData(item))" />
                  <v-list-item v-if="rowData(item).status==='Pending'" title="Approve" @click="askApprove(rowData(item))" />
                  <v-list-item v-if="rowData(item).status==='Pending'" title="Reject" @click="openReject(rowData(item))" />
                  <v-list-item v-if="['Pending','Approved'].includes(rowData(item).status)" title="Cancel" @click="askCancel(rowData(item))" />
                  <v-list-item title="View Details" @click="openDetail(rowData(item))" />
                  <v-list-item title="Delete" base-color="error" @click="askDelete(rowData(item))" />
                </v-list>
              </v-menu>
            </template>
          </v-data-table-server>
        </div>
      </v-window-item>

      <v-window-item value="balances">
        <div class="pa-4">
          <v-table class="rounded-lg border-sm">
            <thead>
              <tr><th>Employee</th><th v-for="type in balanceTypes" :key="type.id">{{ type.name }}</th><th>Total Used</th><th>Total Remaining</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in balances" :key="row.employee.id">
                <td>
                  <div class="d-flex align-center ga-2">
                    <v-avatar size="30" color="primary" variant="tonal"><img v-if="row.employee.avatar_url" :src="row.employee.avatar_url || ''" :alt="row.employee.full_name" /><span v-else class="text-caption font-weight-bold">{{ initials(row.employee.full_name) }}</span></v-avatar>
                    <div><div class="font-weight-medium">{{ row.employee.full_name }}</div><div class="text-caption text-lightText">{{ row.employee.department || '-' }}</div></div>
                  </div>
                </td>
                <td v-for="type in balanceTypes" :key="`${row.employee.id}-${type.id}`" style="min-width:140px">
                  <template v-if="getBalanceForType(row, type.id)">
                    <div class="text-caption mb-1">{{ getBalanceForType(row, type.id).used }} / {{ getBalanceForType(row, type.id).allowed }}</div>
                    <v-progress-linear :model-value="Math.min(100,(getBalanceForType(row, type.id).used/Math.max(1,getBalanceForType(row, type.id).allowed))*100)" :color="getBalanceForType(row, type.id).color || '#4f6ef7'" height="6" rounded />
                  </template>
                  <template v-else>-</template>
                </td>
                <td>{{ row.balances.reduce((s,b)=>s+b.used,0) }}</td>
                <td>{{ row.balances.reduce((s,b)=>s+b.remaining,0) }}</td>
              </tr>
            </tbody>
          </v-table>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="540">
    <div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">{{ editingRequestId ? 'Edit Leave Request' : 'New Leave Request' }}</h5><v-btn icon="mdi-close" variant="text" @click="drawerOpen=false" /></div>
    <div class="pa-4 drawer-body">
      <v-autocomplete v-model="form.employee_id" :items="employeeOptions" label="Employee *" variant="outlined" class="mb-3" />
      <v-select v-model="form.leave_type_id" :items="leaveTypeOptions" label="Leave Type *" variant="outlined" class="mb-3" />
      <v-text-field v-model="form.from_date" type="date" label="From Date *" variant="outlined" class="mb-3" />
      <v-text-field v-model="form.to_date" type="date" label="To Date *" variant="outlined" class="mb-3" />
      <v-text-field :model-value="`${requestedDays} business days`" label="Days Requested" variant="outlined" readonly class="mb-3" />
      <v-textarea v-model="form.reason" label="Reason" rows="3" variant="outlined" />
      <v-card v-if="selectedBalance" variant="tonal" class="mt-3"><v-card-text>{{ selectedBalance.name }}: {{ selectedBalance.used }} used of {{ selectedBalance.allowed }} days ({{ selectedBalance.remaining }} remaining)</v-card-text></v-card>
      <v-alert v-if="selectedBalance && requestedDays > selectedBalance.remaining" type="warning" variant="tonal" class="mt-3">Employee only has {{ selectedBalance.remaining }} days remaining for this leave type.</v-alert>
    </div>
    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="drawerOpen=false">Cancel</v-btn><v-btn color="primary" :loading="submitting" @click="submitRequest">Submit Request</v-btn></div>
  </v-navigation-drawer>

  <v-dialog v-model="rejectDialog" max-width="480"><v-card><v-card-title class="text-h5">Reject Leave Request</v-card-title><v-card-text><v-textarea v-model="rejectReason" label="Rejection Reason *" rows="3" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="rejectDialog=false">Cancel</v-btn><v-btn color="error" @click="submitReject">Reject</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="detailDialog" max-width="560"><v-card><v-card-title class="text-h5">Leave Request Details</v-card-title><v-card-text v-if="detailTarget"><div><strong>Employee:</strong> {{ detailTarget.employee.full_name }}</div><div><strong>Type:</strong> {{ detailTarget.leave_type.name }}</div><div><strong>Date Range:</strong> {{ formatDate(detailTarget.from_date) }} - {{ formatDate(detailTarget.to_date) }}</div><div><strong>Days:</strong> {{ detailTarget.days_requested }}</div><div><strong>Reason:</strong> {{ detailTarget.reason || '-' }}</div><div><strong>Status:</strong> <v-chip size="small" :color="statusColor(detailTarget.status)" variant="tonal">{{ detailTarget.status }}</v-chip></div><v-alert v-if="detailTarget.rejection_reason" type="error" variant="tonal" class="mt-3">{{ detailTarget.rejection_reason }}</v-alert></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog=false">Close</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="leaveTypesDialog" max-width="700"><v-card><v-card-title class="text-h5 d-flex justify-space-between align-center"><span>Leave Types</span><v-btn size="small" variant="outlined" prepend-icon="mdi-plus" @click="openLeaveTypeForm()">Add Leave Type</v-btn></v-card-title><v-card-text><v-table><thead><tr><th>Name</th><th>Days Allowed</th><th>Carry Forward</th><th>Requires Approval</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr v-for="type in leaveTypes" :key="type.id"><td><v-chip size="small" :color="type.color || '#4f6ef7'">{{ type.name }}</v-chip></td><td>{{ type.days_allowed }}</td><td>{{ type.carry_forward ? 'Yes' : 'No' }}</td><td>{{ type.requires_approval ? 'Yes' : 'No' }}</td><td><v-chip size="small" :color="type.status === 'Active' ? 'success' : 'secondary'" variant="tonal">{{ type.status }}</v-chip></td><td><v-btn icon="mdi-pencil" size="small" variant="text" @click="openLeaveTypeForm(type)" /><v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="deleteLeaveType(type)" /></td></tr></tbody></v-table><v-expand-transition><div v-if="leaveTypeFormOpen" class="mt-4"><v-row><v-col cols="12" md="6"><v-text-field v-model="leaveTypeForm.name" label="Name *" variant="outlined" /></v-col><v-col cols="12" md="6"><v-text-field v-model.number="leaveTypeForm.days_allowed" type="number" label="Days Allowed *" variant="outlined" /></v-col><v-col cols="12" md="6"><v-text-field v-model="leaveTypeForm.color" label="Color" variant="outlined" /></v-col><v-col cols="12" md="6"><v-select v-model="leaveTypeForm.status" :items="['Active','Inactive']" label="Status" variant="outlined" /></v-col><v-col cols="12" md="6"><v-switch v-model="leaveTypeForm.carry_forward" label="Carry Forward" /></v-col><v-col cols="12" md="6"><v-switch v-model="leaveTypeForm.requires_approval" label="Requires Approval" /></v-col></v-row><div class="d-flex justify-end ga-2"><v-btn variant="outlined" @click="leaveTypeFormOpen=false">Cancel</v-btn><v-btn color="primary" :loading="leaveTypeSaving" @click="saveLeaveType">Save</v-btn></div></div></v-expand-transition></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="leaveTypesDialog=false">Close</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="confirmDialog.show" max-width="420"><v-card><v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title><v-card-text>{{ confirmDialog.message }}</v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="confirmDialog.show=false">Cancel</v-btn><v-btn color="error" variant="flat" @click="runConfirm">Confirm</v-btn></v-card-actions></v-card></v-dialog>
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
