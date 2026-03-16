<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import CreatePortalAccountDialog from '@/components/HR/CreatePortalAccountDialog.vue';

interface AccountRow {
  employee_id: number;
  employee_code: string;
  employee_name: string;
  avatar_url: string | null;
  designation: string;
  department_id: number | null;
  department: string;
  work_email: string | null;
  personal_email: string | null;
  default_email: string | null;
  has_account: boolean;
  user_id: number | null;
  user_name: string | null;
  user_email: string | null;
  role_id: number | null;
  role: string | null;
  role_color: string | null;
  created_at: string | null;
}

interface RoleOption {
  id: number;
  name: string;
  color?: string | null;
}

interface DepartmentOption {
  id: number;
  name: string;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Portal Accounts', disabled: true, href: '#' },
];

const loading = ref(false);
const creating = ref(false);
const rows = ref<AccountRow[]>([]);
const roles = ref<RoleOption[]>([]);
const departments = ref<DepartmentOption[]>([]);
const stats = ref({
  total_employees: 0,
  active_accounts: 0,
  no_access: 0,
  role_breakdown: [] as Array<{ role: string; count: number; color: string }>,
});

const filters = reactive({
  q: '',
  access: 'all',
  role_id: null as number | null,
  department_id: null as number | null,
});

const createDialog = ref(false);
const targetEmployee = ref<AccountRow | null>(null);
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
});

const accessOptions = [
  { title: 'All', value: 'all' },
  { title: 'Has Access', value: 'has_access' },
  { title: 'No Access', value: 'no_access' },
];

function employeeInitials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toUpperCase();
}

function tableRow(item: any): AccountRow {
  return (item?.raw ?? item) as AccountRow;
}

function showSnack(message: string, color = 'success') {
  snackbar.value = { show: true, message, color };
}

async function fetchAccounts() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/accounts', {
      params: {
        q: filters.q || undefined,
        access: filters.access !== 'all' ? filters.access : undefined,
        role_id: filters.role_id ?? undefined,
        department_id: filters.department_id ?? undefined,
      },
    });

    rows.value = data.employees ?? [];
    roles.value = data.roles ?? [];
    departments.value = data.departments ?? [];
    stats.value = data.stats ?? stats.value;
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to load portal accounts.', 'error');
  } finally {
    loading.value = false;
  }
}

function openCreateDialog(row: AccountRow) {
  targetEmployee.value = row;
  createDialog.value = true;
}

async function createAccount(payload: {
  name: string;
  email: string;
  username: string;
  password: string;
  role_id: number;
  send_email: boolean;
}) {
  if (!targetEmployee.value) return;

  creating.value = true;
  try {
    const { data } = await axios.post(
      `/api/hr/employees/${targetEmployee.value.employee_id}/create-account`,
      payload
    );

    showSnack(data?.message ?? 'Portal account created.');
    createDialog.value = false;
    targetEmployee.value = null;
    await fetchAccounts();
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to create account.', 'error');
  } finally {
    creating.value = false;
  }
}

async function resetPassword(row: AccountRow) {
  const confirmed = window.confirm(`Reset temporary password for ${row.employee_name}?`);
  if (!confirmed) return;

  try {
    const { data } = await axios.post(`/api/hr/employees/${row.employee_id}/reset-password`, {
      send_email: true,
    });
    showSnack(`${data?.message ?? 'Password reset.'} Temporary password: ${data?.new_password ?? ''}`);
    await fetchAccounts();
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to reset password.', 'error');
  }
}

async function revokeAccess(row: AccountRow) {
  const confirmed = window.confirm(`Revoke portal access for ${row.employee_name}?`);
  if (!confirmed) return;

  try {
    const { data } = await axios.delete(`/api/hr/employees/${row.employee_id}/revoke-access`);
    showSnack(data?.message ?? 'Portal access revoked.');
    await fetchAccounts();
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to revoke access.', 'error');
  }
}

const roleItems = computed(() => [
  { title: 'All Roles', value: null },
  ...roles.value.map((role) => ({ title: role.name, value: role.id })),
]);

const departmentItems = computed(() => [
  { title: 'All Departments', value: null },
  ...departments.value.map((department) => ({ title: department.name, value: department.id })),
]);

watch(
  () => [filters.q, filters.access, filters.role_id, filters.department_id],
  () => fetchAccounts()
);

onMounted(fetchAccounts);
</script>

<template>
  <BaseBreadcrumb title="Portal Accounts" subtitle="Manage employee login access" :breadcrumbs="breadcrumbs" />

  <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-2">
    <div>
      <h2 class="text-h4 mb-1">Portal Accounts</h2>
      <p class="text-medium-emphasis mb-0">Create, reset, and revoke employee access without leaving HR portal.</p>
    </div>
  </div>

  <v-row class="mb-2">
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined" class="rounded-lg">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Total Employees</div>
          <div class="text-h5 font-weight-bold">{{ stats.total_employees }}</div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined" class="rounded-lg">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">Active Portal Accounts</div>
          <div class="text-h5 font-weight-bold text-success">{{ stats.active_accounts }}</div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined" class="rounded-lg">
        <v-card-text>
          <div class="text-caption text-medium-emphasis">No Access</div>
          <div class="text-h5 font-weight-bold text-warning">{{ stats.no_access }}</div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined" class="rounded-lg h-100">
        <v-card-text>
          <div class="text-caption text-medium-emphasis mb-2">Roles Breakdown</div>
          <div class="d-flex flex-wrap ga-1">
            <v-chip
              v-for="item in stats.role_breakdown"
              :key="item.role"
              size="x-small"
              :color="item.color"
              variant="tonal"
            >
              {{ item.role }} - {{ item.count }}
            </v-chip>
            <div v-if="stats.role_breakdown.length === 0" class="text-caption text-medium-emphasis">
              No active accounts
            </div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card variant="outlined" class="rounded-lg mb-4">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4">
          <v-text-field
            v-model="filters.q"
            placeholder="Search by name or email..."
            variant="outlined"
            hide-details
            prepend-inner-icon="mdi-magnify"
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.access"
            :items="accessOptions"
            variant="outlined"
            label="Access Filter"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.role_id"
            :items="roleItems"
            variant="outlined"
            label="Role"
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filters.department_id"
            :items="departmentItems"
            variant="outlined"
            label="Department"
            hide-details
          />
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-card variant="outlined" class="rounded-lg">
    <v-data-table
      :headers="[
        { title: 'Employee', key: 'employee' },
        { title: 'Department', key: 'department' },
        { title: 'Email', key: 'email' },
        { title: 'Portal Access', key: 'access' },
        { title: 'Role', key: 'role' },
        { title: 'Account Created', key: 'created_at' },
        { title: 'Actions', key: 'actions', sortable: false },
      ]"
      :items="rows"
      :loading="loading"
      item-key="employee_id"
    >
      <template #item.employee="{ item }">
        <div class="d-flex align-center ga-2 py-1">
          <v-avatar size="34" color="primary">
            <v-img v-if="tableRow(item).avatar_url" :src="tableRow(item).avatar_url" />
            <span v-else class="text-caption text-white">{{ employeeInitials(tableRow(item).employee_name) }}</span>
          </v-avatar>
          <div>
            <div class="text-body-2 font-weight-medium">{{ tableRow(item).employee_name }}</div>
            <div class="text-caption text-medium-emphasis">{{ tableRow(item).designation }} - {{ tableRow(item).employee_code }}</div>
          </div>
        </div>
      </template>

      <template #item.email="{ item }">
        <span>{{ tableRow(item).user_email || tableRow(item).default_email || '-' }}</span>
      </template>

      <template #item.access="{ item }">
        <v-chip :color="tableRow(item).has_account ? 'success' : 'grey'" size="small" variant="tonal">
          {{ tableRow(item).has_account ? 'Active' : 'No Access' }}
        </v-chip>
      </template>

      <template #item.role="{ item }">
        <v-chip v-if="tableRow(item).role" :color="tableRow(item).role_color || 'primary'" size="small" variant="tonal">
          {{ tableRow(item).role }}
        </v-chip>
        <span v-else class="text-medium-emphasis">-</span>
      </template>

      <template #item.created_at="{ item }">
        {{ tableRow(item).created_at || '-' }}
      </template>

      <template #item.actions="{ item }">
        <div class="d-flex ga-1">
          <v-btn
            v-if="!tableRow(item).has_account"
            color="success"
            size="small"
            variant="tonal"
            prepend-icon="mdi-account-plus"
            @click="openCreateDialog(tableRow(item))"
          >
            Create Account
          </v-btn>
          <template v-else>
            <v-btn
              color="warning"
              size="small"
              variant="tonal"
              prepend-icon="mdi-lock-reset"
              @click="resetPassword(tableRow(item))"
            >
              Reset Password
            </v-btn>
            <v-btn
              color="error"
              size="small"
              variant="tonal"
              prepend-icon="mdi-account-cancel"
              @click="revokeAccess(tableRow(item))"
            >
              Revoke Access
            </v-btn>
          </template>
        </div>
      </template>
    </v-data-table>
  </v-card>

  <CreatePortalAccountDialog
    v-model="createDialog"
    :employee="targetEmployee ? {
      id: targetEmployee.employee_id,
      full_name: targetEmployee.employee_name,
      first_name: targetEmployee.employee_name?.split(' ')[0],
      last_name: targetEmployee.employee_name?.split(' ').slice(1).join(' '),
      work_email: targetEmployee.work_email,
      personal_email: targetEmployee.personal_email,
    } : null"
    :roles="roles"
    :submitting="creating"
    @create="createAccount"
  />

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="4500">
    {{ snackbar.message }}
  </v-snackbar>
</template>
