<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import { appUrl } from '@/utils/appUrl';

const props = defineProps<{ employeeId: number }>();

const loadingProfile = ref(true);
const loadingAttendance = ref(true);
const loadingLeave = ref(true);
const loadingPayroll = ref(true);
const loadingDocuments = ref(true);
const loadingActivity = ref(true);

const activeTab = ref('overview');
const employee = ref<any>(null);
const attendance = ref<any>({ summary: {}, calendar: [], history: [] });
const leaveData = ref<any>({ balances: [], history: [] });
const payrollData = ref<any>({ salary: { basic_salary: 0, allowances: [], deductions: 0, net_pay: 0 }, history: [] });
const documents = ref<any[]>([]);
const activityLog = ref<any[]>([]);
const profileAvatarFailed = ref(false);
const avatarUploading = ref(false);
const avatarRemoving = ref(false);
const avatarInputRef = ref<HTMLInputElement | null>(null);

const editableSkills = ref<string[]>([]);
const newSkill = ref('');
const notes = ref('');
const bio = ref('');

const snackbar = ref({ show: false, message: '', color: 'success' });
const confirmDialog = ref({ show: false, title: '', message: '', action: '', payload: null as any });

const breadcrumbs = computed(() => [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Employees', disabled: false, href: '/hr/employees' },
  { title: employee.value?.full_name || 'Profile', disabled: true, href: '#' }
]);

const quickStats = computed(() => {
  const summary = attendance.value.summary || {};
  return [
    { label: 'Days Present This Month', value: summary.present ?? 0 },
    {
      label: 'Leave Days Remaining',
      value: leaveData.value.balances.reduce((total: number, item: any) => total + ((item.total_days ?? 0) - (item.used_days ?? 0)), 0)
    },
    { label: 'Last Active', value: employee.value?.last_active_at ?? 'N/A' }
  ];
});

function statusColor(status: string) {
  if (status === 'Active' || status === 'Done' || status === 'Approved' || status === 'Present') return 'success';
  if (status === 'On Leave' || status === 'Pending' || status === 'Late') return 'warning';
  if (status === 'Probation' || status === 'In Progress') return 'primary';
  if (status === 'Absent' || status === 'Rejected') return 'error';
  return 'secondary';
}

function attendanceDot(status: string) {
  if (status === 'Present') return 'attendance-dot bg-success';
  if (status === 'Absent') return 'attendance-dot bg-error';
  if (status === 'Late') return 'attendance-dot bg-warning';
  return 'attendance-dot bg-secondary';
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

function hasProfileAvatar() {
  return Boolean(employee.value?.avatar_url) && !profileAvatarFailed.value;
}

function onProfileAvatarError() {
  profileAvatarFailed.value = true;
}

function triggerAvatarPicker() {
  avatarInputRef.value?.click();
}

async function onAvatarFileSelected(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];

  if (!file) {
    return;
  }

  avatarUploading.value = true;

  try {
    const formData = new FormData();
    formData.append('avatar', file);

    const { data } = await axios.post(`/api/hr/employees/${props.employeeId}/avatar`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    if (employee.value) {
      employee.value.avatar_url = data?.avatar_url ?? null;
    }

    profileAvatarFailed.value = false;
    snackbar.value = { show: true, message: data?.message ?? 'Profile photo updated.', color: 'success' };
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to upload photo.',
      color: 'error'
    };
  } finally {
    avatarUploading.value = false;
    target.value = '';
  }
}

async function removeAvatar() {
  avatarRemoving.value = true;

  try {
    const { data } = await axios.delete(`/api/hr/employees/${props.employeeId}/avatar`);
    if (employee.value) {
      employee.value.avatar_url = null;
    }
    profileAvatarFailed.value = false;
    snackbar.value = { show: true, message: data?.message ?? 'Profile photo removed.', color: 'success' };
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to remove photo.',
      color: 'error'
    };
  } finally {
    avatarRemoving.value = false;
  }
}

function openConfirm(title: string, message: string, action: string, payload: any = null) {
  confirmDialog.value = { show: true, title, message, action, payload };
}

async function fetchProfile() {
  loadingProfile.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}`);
    employee.value = data.employee;
    profileAvatarFailed.value = false;
    editableSkills.value = data.employee?.skills ?? [];
    notes.value = data.employee?.notes ?? '';
    bio.value = data.employee?.bio ?? '';
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load employee profile.', color: 'error' };
  } finally {
    loadingProfile.value = false;
  }
}

async function fetchAttendance() {
  loadingAttendance.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}/attendance`);
    attendance.value = data;
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load attendance.', color: 'error' };
  } finally {
    loadingAttendance.value = false;
  }
}

async function fetchLeave() {
  loadingLeave.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}/leave`);
    leaveData.value = data;
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load leave data.', color: 'error' };
  } finally {
    loadingLeave.value = false;
  }
}

async function fetchPayroll() {
  loadingPayroll.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}/payroll`);
    payrollData.value = data;
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load payroll data.', color: 'error' };
  } finally {
    loadingPayroll.value = false;
  }
}

async function fetchDocuments() {
  loadingDocuments.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}/documents`);
    documents.value = data.documents ?? [];
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load documents.', color: 'error' };
  } finally {
    loadingDocuments.value = false;
  }
}

async function fetchActivity() {
  loadingActivity.value = true;
  try {
    const { data } = await axios.get(`/api/hr/employees/${props.employeeId}/activity-log`);
    activityLog.value = data.activity_log ?? [];
  } catch (error) {
    snackbar.value = { show: true, message: 'Failed to load activity log.', color: 'error' };
  } finally {
    loadingActivity.value = false;
  }
}

function addSkill() {
  if (!newSkill.value.trim()) return;
  editableSkills.value.push(newSkill.value.trim());
  newSkill.value = '';
}

function removeSkill(index: number) {
  editableSkills.value.splice(index, 1);
}

function exportTable(rows: any[], headers: string[], mapper: (row: any) => string[], file: string, format: 'csv' | 'pdf') {
  if (!rows.length) return;

  if (format === 'csv') {
    const csvRows = [headers, ...rows.map((row) => mapper(row))];
    const csv = csvRows.map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${file}.csv`;
    link.click();
    URL.revokeObjectURL(url);
    return;
  }

  const bodyRows = rows
    .map((row) => `<tr>${mapper(row).map((cell) => `<td>${cell}</td>`).join('')}</tr>`)
    .join('');

  const newWindow = window.open('', '_blank');
  if (newWindow) {
    newWindow.document.open();
    newWindow.document.write(
      '<html><head><title>' + file + '</title></head><body>' +
      '<h3>' + file + '</h3>' +
      '<table border="1" cellpadding="6" cellspacing="0">' +
      '<thead><tr>' + headers.map((header) => `<th>${header}</th>`).join('') + '</tr></thead>' +
      '<tbody>' + bodyRows + '</tbody>' +
      '</table>' +
      '</body></html>'
    );
    newWindow.document.close();
    newWindow.print();
  }
}

function askDeleteDocument(document: any) {
  openConfirm('Delete Document', `Delete ${document.file_name}?`, 'deleteDocument', document);
}

async function runConfirmedAction() {
  const action = confirmDialog.value.action;
  const payload = confirmDialog.value.payload;

  try {
    if (action === 'deleteDocument') {
      await axios.delete(`/api/hr/employees/${props.employeeId}/documents/${payload.id}`);
      snackbar.value = { show: true, message: 'Document deleted.', color: 'success' };
      fetchDocuments();
    }

    if (action === 'deleteEmployee') {
      await axios.delete(`/api/hr/employees/${props.employeeId}`);
      router.visit(appUrl('/hr/employees'));
      return;
    }

    if (action === 'setStatus') {
      await axios.patch(`/api/hr/employees/${props.employeeId}/status`, { status: payload.status });
      snackbar.value = { show: true, message: `Employee set to ${payload.status}.`, color: 'success' };
      fetchProfile();
    }

    confirmDialog.value.show = false;
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Action failed.', color: 'error' };
  }
}

function placeholderAction(label: string) {
  snackbar.value = { show: true, message: `${label} action queued.`, color: 'info' };
}

onMounted(async () => {
  await Promise.all([fetchProfile(), fetchAttendance(), fetchLeave(), fetchPayroll(), fetchDocuments(), fetchActivity()]);
});
</script>

<template>
  <BaseBreadcrumb :title="employee?.full_name || 'Employee Profile'" subtitle="Employee profile details" :breadcrumbs="breadcrumbs" />

  <v-skeleton-loader v-if="loadingProfile" type="article" class="mb-4" />

  <v-card v-else class="bg-surface hr-card-shadow rounded-lg mb-4" variant="outlined" elevation="0">
    <v-card-text class="d-flex justify-space-between align-center flex-wrap ga-4">
      <div class="d-flex align-center ga-4">
        <v-menu location="bottom start" offset="8">
          <template #activator="{ props }">
            <div class="avatar-action-wrap" v-bind="props">
              <v-avatar size="84" color="primary" variant="tonal" class="cursor-pointer">
                <img
                  v-if="hasProfileAvatar()"
                  :src="employee?.avatar_url || ''"
                  :alt="employee?.full_name || 'Employee Avatar'"
                  @error="onProfileAvatarError"
                />
                <span v-else class="text-h6 font-weight-bold">{{ initials(employee?.full_name || '') }}</span>
              </v-avatar>
              <v-btn
                class="avatar-edit-btn"
                icon
                size="small"
                color="surface"
                variant="elevated"
                :loading="avatarUploading || avatarRemoving"
              >
                <img src="/assets/images/icons/pencil-edit.svg" alt="Edit photo" class="pencil-icon-img" />
              </v-btn>
            </div>
          </template>

          <v-list density="compact" min-width="220">
            <v-list-item
              :title="employee?.avatar_url ? 'Change photo' : 'Upload photo'"
              prepend-icon="mdi-image-plus"
              :disabled="avatarUploading || avatarRemoving"
              @click="triggerAvatarPicker"
            />
            <v-list-item
              v-if="employee?.avatar_url"
              title="Remove photo"
              prepend-icon="mdi-delete-outline"
              base-color="error"
              :disabled="avatarUploading || avatarRemoving"
              @click="removeAvatar"
            />
          </v-list>
        </v-menu>
        <input
          ref="avatarInputRef"
          type="file"
          accept="image/png,image/jpeg,image/webp"
          class="d-none"
          @change="onAvatarFileSelected"
        />
        <div>
          <h2 class="text-h3 mb-1">{{ employee?.full_name }}</h2>
          <p class="text-subtitle-1 text-lightText mb-2">{{ employee?.designation?.name ?? 'Not assigned' }}</p>
          <div class="d-flex ga-2 flex-wrap">
            <v-chip color="primary" variant="tonal">{{ employee?.department?.name ?? 'No department' }}</v-chip>
            <v-chip :color="statusColor(employee?.employment_status)" variant="tonal">{{ employee?.employment_status }}</v-chip>
          </div>
        </div>
      </div>

      <div class="d-flex ga-2 flex-wrap">
        <v-btn
          class="header-edit-btn"
          icon
          color="surface"
          size="large"
          variant="elevated"
          title="Edit Employee"
          aria-label="Edit Employee"
          @click="router.visit(appUrl(`/hr/employees/${props.employeeId}/edit`))"
        >
          <img src="/assets/images/icons/pencil-edit.svg" alt="Edit employee" class="pencil-icon-img" />
        </v-btn>
        <v-btn variant="outlined" prepend-icon="mdi-message-text-outline" @click="placeholderAction('Message')">Send Message</v-btn>
        <v-menu>
          <template #activator="{ props }">
            <v-btn icon variant="text" v-bind="props">
              <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
            </v-btn>
          </template>
          <v-list>
            <v-list-item title="Deactivate" @click="openConfirm('Deactivate Employee', 'Set this employee to Inactive?', 'setStatus', { status: 'Inactive' })" />
            <v-list-item title="Reset Password" @click="placeholderAction('Reset password')" />
            <v-list-item title="Delete" base-color="error" @click="openConfirm('Delete Employee', 'Delete this employee record?', 'deleteEmployee')" />
          </v-list>
        </v-menu>
      </div>
    </v-card-text>
  </v-card>

  <v-row>
    <v-col cols="12" lg="8">
      <v-card class="bg-surface hr-card-shadow rounded-lg" variant="outlined" elevation="0">
        <v-tabs v-model="activeTab" color="primary" grow>
          <v-tab value="overview">Overview</v-tab>
          <v-tab value="attendance">Attendance</v-tab>
          <v-tab value="leave">Leave</v-tab>
          <v-tab value="payroll">Payroll</v-tab>
          <v-tab value="documents">Documents</v-tab>
          <v-tab value="activity">Activity Log</v-tab>
        </v-tabs>

        <v-divider />

        <v-card-text>
          <v-window v-model="activeTab">
            <v-window-item value="overview">
              <v-row>
                <v-col cols="12" sm="6">
                  <h6 class="text-subtitle-1 mb-2">Personal Details</h6>
                  <p class="mb-1"><strong>Name:</strong> {{ employee?.full_name }}</p>
                  <p class="mb-1"><strong>DOB:</strong> {{ employee?.date_of_birth ?? '-' }}</p>
                  <p class="mb-1"><strong>Gender:</strong> {{ employee?.gender ?? '-' }}</p>
                  <p class="mb-1"><strong>Phone:</strong> {{ employee?.phone }}</p>
                  <p class="mb-1"><strong>Email:</strong> {{ employee?.personal_email }}</p>
                  <p class="mb-0"><strong>Address:</strong> {{ employee?.address ?? '-' }}</p>
                </v-col>
                <v-col cols="12" sm="6">
                  <h6 class="text-subtitle-1 mb-2">Employment Details</h6>
                  <p class="mb-1"><strong>Employee ID:</strong> {{ employee?.employee_id }}</p>
                  <p class="mb-1"><strong>Type:</strong> {{ employee?.employment_type }}</p>
                  <p class="mb-1"><strong>Join Date:</strong> {{ employee?.join_date ?? '-' }}</p>
                  <p class="mb-1"><strong>Manager:</strong> {{ employee?.manager?.first_name }} {{ employee?.manager?.last_name }}</p>
                  <p class="mb-1"><strong>Location:</strong> {{ employee?.work_location ?? '-' }}</p>
                  <p class="mb-0"><strong>Shift:</strong> {{ employee?.shift?.name ?? '-' }}</p>
                </v-col>

                <v-col cols="12">
                  <h6 class="text-subtitle-1 mb-2">Skills</h6>
                  <div class="d-flex flex-wrap ga-2 mb-2">
                    <v-chip v-for="(skill, index) in editableSkills" :key="`skill-${index}`" color="primary" variant="tonal" closable @click:close="removeSkill(index)">
                      {{ skill }}
                    </v-chip>
                  </div>
                  <div class="d-flex ga-2">
                    <v-text-field v-model="newSkill" label="Add skill" variant="outlined" density="comfortable" hide-details />
                    <v-btn color="primary" @click="addSkill">Add</v-btn>
                  </div>
                </v-col>

                <v-col cols="12"><v-textarea v-model="bio" label="Bio / Notes" rows="2" variant="outlined" /></v-col>
                <v-col cols="12"><v-textarea v-model="notes" label="Notes" rows="2" variant="outlined" /></v-col>
              </v-row>
            </v-window-item>

            <v-window-item value="attendance">
              <v-skeleton-loader v-if="loadingAttendance" type="table" />
              <template v-else>
                <v-row class="mb-3">
                  <v-col cols="6" sm="3"><v-card variant="tonal" color="success"><v-card-text>Present: {{ attendance.summary.present ?? 0 }}</v-card-text></v-card></v-col>
                  <v-col cols="6" sm="3"><v-card variant="tonal" color="error"><v-card-text>Absent: {{ attendance.summary.absent ?? 0 }}</v-card-text></v-card></v-col>
                  <v-col cols="6" sm="3"><v-card variant="tonal" color="warning"><v-card-text>Late: {{ attendance.summary.late ?? 0 }}</v-card-text></v-card></v-col>
                  <v-col cols="6" sm="3"><v-card variant="tonal" color="primary"><v-card-text>On Leave: {{ attendance.summary.on_leave ?? 0 }}</v-card-text></v-card></v-col>
                </v-row>

                <div class="calendar-grid mb-4">
                  <div v-for="(day, index) in attendance.calendar" :key="`cal-${index}`" class="calendar-item">
                    <span :class="attendanceDot(day.status)"></span>
                    <span class="text-caption">{{ day.date }}</span>
                  </div>
                </div>

                <div class="d-flex justify-end mb-2 ga-2">
                  <v-btn size="small" variant="outlined" @click="exportTable(attendance.history, ['Date', 'Check-in', 'Check-out', 'Hours', 'Status'], (r) => [r.date, r.check_in, r.check_out, r.hours, r.status], 'attendance-history', 'csv')">CSV</v-btn>
                  <v-btn size="small" variant="outlined" @click="exportTable(attendance.history, ['Date', 'Check-in', 'Check-out', 'Hours', 'Status'], (r) => [r.date, r.check_in, r.check_out, r.hours, r.status], 'attendance-history', 'pdf')">PDF</v-btn>
                </div>

                <v-table density="comfortable">
                  <thead><tr><th>Date</th><th>Check-in</th><th>Check-out</th><th>Hours</th><th>Status</th></tr></thead>
                  <tbody>
                    <tr v-for="row in attendance.history" :key="`att-${row.id}`">
                      <td>{{ row.date }}</td>
                      <td>{{ row.check_in ?? '-' }}</td>
                      <td>{{ row.check_out ?? '-' }}</td>
                      <td>{{ row.hours ?? '-' }}</td>
                      <td><v-chip :color="statusColor(row.status)" size="small" variant="tonal">{{ row.status }}</v-chip></td>
                    </tr>
                  </tbody>
                </v-table>
              </template>
            </v-window-item>

            <v-window-item value="leave">
              <v-skeleton-loader v-if="loadingLeave" type="table" />
              <template v-else>
                <v-row class="mb-4">
                  <v-col v-for="balance in leaveData.balances" :key="`bal-${balance.id}`" cols="12" sm="6">
                    <v-card variant="outlined" class="rounded-md" elevation="0">
                      <v-card-text>
                        <div class="d-flex justify-space-between mb-1">
                          <span>{{ balance.leave_type }}</span>
                          <strong>{{ balance.used_days }}/{{ balance.total_days }}</strong>
                        </div>
                        <v-progress-linear :model-value="balance.total_days ? (balance.used_days / balance.total_days) * 100 : 0" color="primary" height="6" rounded />
                      </v-card-text>
                    </v-card>
                  </v-col>
                </v-row>

                <div class="d-flex justify-end mb-2 ga-2">
                  <v-btn size="small" variant="outlined" @click="exportTable(leaveData.history, ['Type', 'From', 'To', 'Days', 'Status', 'Approved By'], (r) => [r.leave_type, r.from_date, r.to_date, String(r.days), r.status, r.approved_by ?? '-'], 'leave-history', 'csv')">CSV</v-btn>
                  <v-btn size="small" variant="outlined" @click="exportTable(leaveData.history, ['Type', 'From', 'To', 'Days', 'Status', 'Approved By'], (r) => [r.leave_type, r.from_date, r.to_date, String(r.days), r.status, r.approved_by ?? '-'], 'leave-history', 'pdf')">PDF</v-btn>
                </div>

                <v-table density="comfortable">
                  <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Status</th><th>Approved By</th></tr></thead>
                  <tbody>
                    <tr v-for="row in leaveData.history" :key="`leave-${row.id}`">
                      <td>{{ row.leave_type }}</td>
                      <td>{{ row.from_date }}</td>
                      <td>{{ row.to_date }}</td>
                      <td>{{ row.days }}</td>
                      <td><v-chip :color="statusColor(row.status)" size="small" variant="tonal">{{ row.status }}</v-chip></td>
                      <td>{{ row.approved_by ?? '-' }}</td>
                    </tr>
                  </tbody>
                </v-table>
              </template>
            </v-window-item>

            <v-window-item value="payroll">
              <v-skeleton-loader v-if="loadingPayroll" type="table" />
              <template v-else>
                <v-row class="mb-4">
                  <v-col cols="12" sm="4"><v-card variant="tonal" color="primary"><v-card-text>Basic: {{ payrollData.salary.basic_salary }}</v-card-text></v-card></v-col>
                  <v-col cols="12" sm="4"><v-card variant="tonal" color="warning"><v-card-text>Allowances: {{ payrollData.salary.allowances?.length ?? 0 }}</v-card-text></v-card></v-col>
                  <v-col cols="12" sm="4"><v-card variant="tonal" color="success"><v-card-text>Net Pay: {{ payrollData.salary.net_pay }}</v-card-text></v-card></v-col>
                </v-row>

                <div class="d-flex justify-end mb-2 ga-2">
                  <v-btn size="small" variant="outlined" @click="exportTable(payrollData.history, ['Month', 'Gross', 'Deductions', 'Net', 'Status'], (r) => [r.pay_month, r.gross, r.deductions, r.net, r.status], 'payroll-history', 'csv')">CSV</v-btn>
                  <v-btn size="small" variant="outlined" @click="exportTable(payrollData.history, ['Month', 'Gross', 'Deductions', 'Net', 'Status'], (r) => [r.pay_month, r.gross, r.deductions, r.net, r.status], 'payroll-history', 'pdf')">PDF</v-btn>
                </div>

                <v-table density="comfortable">
                  <thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Download</th></tr></thead>
                  <tbody>
                    <tr v-for="row in payrollData.history" :key="`pay-${row.id}`">
                      <td>{{ row.pay_month }}</td>
                      <td>{{ row.gross }}</td>
                      <td>{{ row.deductions }}</td>
                      <td>{{ row.net }}</td>
                      <td><v-chip :color="statusColor(row.status)" size="small" variant="tonal">{{ row.status }}</v-chip></td>
                      <td><v-btn size="x-small" variant="text" icon="mdi-download" /></td>
                    </tr>
                  </tbody>
                </v-table>
              </template>
            </v-window-item>

            <v-window-item value="documents">
              <v-skeleton-loader v-if="loadingDocuments" type="list-item-three-line" />
              <template v-else>
                <v-row>
                  <v-col v-for="doc in documents" :key="`doc-${doc.id}`" cols="12" sm="6" md="4">
                    <v-card variant="outlined" class="rounded-md" elevation="0">
                      <v-card-text>
                        <v-chip size="small" color="primary" variant="tonal" class="mb-2">{{ doc.category }}</v-chip>
                        <h6 class="text-subtitle-1 mb-2">{{ doc.file_name }}</h6>
                        <div class="d-flex ga-2">
                          <v-btn size="small" variant="outlined" :href="doc.url" target="_blank">Download</v-btn>
                          <v-btn size="small" color="error" variant="text" @click="askDeleteDocument(doc)">Delete</v-btn>
                        </div>
                      </v-card-text>
                    </v-card>
                  </v-col>
                </v-row>
              </template>
            </v-window-item>

            <v-window-item value="activity">
              <v-skeleton-loader v-if="loadingActivity" type="list-item-three-line" />
              <v-timeline v-else density="compact" side="end" line-color="primary">
                <v-timeline-item v-for="item in activityLog" :key="`log-${item.id}`" dot-color="primary" size="small">
                  <div class="d-flex justify-space-between align-start">
                    <div>
                      <h6 class="text-subtitle-2 mb-1">{{ item.action }}</h6>
                      <p class="text-body-2 mb-0">{{ item.description }}</p>
                      <small class="text-lightText">By {{ item.actor_name || 'System' }}</small>
                    </div>
                    <small class="text-lightText">{{ item.created_at }}</small>
                  </div>
                </v-timeline-item>
              </v-timeline>
            </v-window-item>
          </v-window>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" lg="4">
      <v-card class="bg-surface hr-card-shadow rounded-lg mb-4" variant="outlined" elevation="0">
        <v-card-title>Quick Stats</v-card-title>
        <v-divider />
        <v-list>
          <v-list-item v-for="stat in quickStats" :key="stat.label">
            <v-list-item-title>{{ stat.label }}</v-list-item-title>
            <template #append><strong>{{ stat.value }}</strong></template>
          </v-list-item>
        </v-list>
      </v-card>

      <v-card class="bg-surface hr-card-shadow rounded-lg mb-4" variant="outlined" elevation="0">
        <v-card-title>Reporting Structure</v-card-title>
        <v-divider />
        <v-card-text>
          <p class="mb-2"><strong>Reports To:</strong></p>
          <div class="d-flex align-center ga-2 mb-4">
            <v-avatar size="34" color="primary" variant="tonal">{{ initials(`${employee?.manager?.first_name || ''} ${employee?.manager?.last_name || ''}`) }}</v-avatar>
            <span>{{ employee?.manager?.first_name }} {{ employee?.manager?.last_name }}</span>
          </div>

          <p class="mb-2"><strong>Direct Reports:</strong></p>
          <v-list density="compact" class="pa-0">
            <v-list-item v-for="report in employee?.direct_reports ?? []" :key="`report-${report.id}`" class="px-0">
              <template #prepend><v-avatar size="26" color="primary" variant="tonal">{{ initials(`${report.first_name} ${report.last_name}`) }}</v-avatar></template>
              <v-list-item-title>{{ report.first_name }} {{ report.last_name }}</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>

      <v-card class="bg-surface hr-card-shadow rounded-lg" variant="outlined" elevation="0">
        <v-card-title>Upcoming Events</v-card-title>
        <v-divider />
        <v-list>
          <v-list-item>
            <v-list-item-title>Probation End Date</v-list-item-title>
            <template #append>{{ employee?.employment_status === 'Probation' ? 'Pending Review' : '-' }}</template>
          </v-list-item>
          <v-list-item>
            <v-list-item-title>Contract Renewal Date</v-list-item-title>
            <template #append>{{ employee?.join_date ?? '-' }}</template>
          </v-list-item>
          <v-list-item>
            <v-list-item-title>Next Performance Review</v-list-item-title>
            <template #append>{{ employee?.join_date ?? '-' }}</template>
          </v-list-item>
        </v-list>
      </v-card>
    </v-col>
  </v-row>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title>
      <v-card-text>{{ confirmDialog.message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="runConfirmedAction">Confirm</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 8px;
}

.calendar-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  border: 1px solid rgba(0, 0, 0, 0.08);
  border-radius: 8px;
}

.attendance-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.avatar-action-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.avatar-edit-btn {
  position: absolute;
  right: -4px;
  bottom: -4px;
  min-width: 34px !important;
  width: 34px !important;
  height: 34px !important;
  border-radius: 50% !important;
  border: 1px solid rgba(15, 23, 42, 0.14);
  background: #eef1f6 !important;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
}

.pencil-icon-img {
  width: 16px;
  height: 16px;
  display: block;
  filter: none;
}

.header-edit-btn {
  border: 1px solid rgba(15, 23, 42, 0.14);
  background: #eef1f6 !important;
  box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
}
</style>
