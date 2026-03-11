<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import { usePermissions } from '@/composables/usePermissions';

interface Permission {
  id: number
  name: string
}

interface Role {
  id: number
  name: string
  users_count: number
  permissions: Permission[]
}

interface UserWithRole {
  id: number
  name: string
  email: string
  roles: Array<{ name: string; permissions?: Permission[] }>
  last_login_at?: string | null
}

interface PermissionGroupPreview {
  key: string
  permissions: Permission[]
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Settings', disabled: false, href: '#' },
  { title: 'Roles & Permissions', disabled: true, href: '#' }
];

const { can, isAdmin } = usePermissions();

const activeTab = ref<'roles' | 'assignments'>('roles');
const loading = ref(true);
const userLoading = ref(true);
const savingRole = ref(false);
const assigningRole = ref(false);
const drawerOpen = ref(false);
const userSearch = ref('');
const expandedRoles = ref<number[]>([]);
const roles = ref<Role[]>([]);
const permissionsByGroup = ref<Record<string, Permission[]>>({});
const users = ref<UserWithRole[]>([]);
const pagination = reactive({ page: 1, perPage: 15, total: 0 });
const snackbar = ref({ show: false, message: '', color: 'success' as 'success' | 'error' | 'warning' });

const fallbackPermissions: Record<string, Permission[]> = {
  dashboard: [{ id: 1, name: 'view hr dashboard' }],
  employees: [
    { id: 2, name: 'view employees' },
    { id: 3, name: 'create employees' },
    { id: 4, name: 'edit employees' },
    { id: 5, name: 'delete employees' },
    { id: 6, name: 'export employees' }
  ],
  departments: [
    { id: 7, name: 'view departments' },
    { id: 8, name: 'create departments' },
    { id: 9, name: 'edit departments' },
    { id: 10, name: 'delete departments' }
  ],
  designations: [
    { id: 11, name: 'view designations' },
    { id: 12, name: 'create designations' },
    { id: 13, name: 'edit designations' },
    { id: 14, name: 'delete designations' }
  ],
  attendance: [
    { id: 15, name: 'view attendance' },
    { id: 16, name: 'create attendance' },
    { id: 17, name: 'edit attendance' },
    { id: 18, name: 'delete attendance' },
    { id: 19, name: 'mark bulk attendance' }
  ],
  leave: [
    { id: 20, name: 'view leave requests' },
    { id: 21, name: 'create leave requests' },
    { id: 22, name: 'approve leave requests' },
    { id: 23, name: 'reject leave requests' },
    { id: 24, name: 'delete leave requests' },
    { id: 25, name: 'manage leave types' }
  ],
  shifts: [
    { id: 26, name: 'view shifts' },
    { id: 27, name: 'create shifts' },
    { id: 28, name: 'edit shifts' },
    { id: 29, name: 'delete shifts' },
    { id: 30, name: 'assign shifts' }
  ],
  recruitment: [
    { id: 31, name: 'view job openings' },
    { id: 32, name: 'create job openings' },
    { id: 33, name: 'edit job openings' },
    { id: 34, name: 'delete job openings' },
    { id: 35, name: 'view applicants' },
    { id: 36, name: 'create applicants' },
    { id: 37, name: 'edit applicants' },
    { id: 38, name: 'delete applicants' },
    { id: 39, name: 'move applicant stage' },
    { id: 40, name: 'convert to employee' },
    { id: 41, name: 'view onboarding' },
    { id: 42, name: 'manage onboarding' }
  ],
  payroll: [
    { id: 43, name: 'view payroll' },
    { id: 44, name: 'create payroll' },
    { id: 45, name: 'process payroll' },
    { id: 46, name: 'approve payroll' },
    { id: 47, name: 'delete payroll' },
    { id: 48, name: 'view payslips' },
    { id: 49, name: 'edit payslips' },
    { id: 50, name: 'manage salary structures' }
  ],
  expenses: [
    { id: 51, name: 'view expenses' },
    { id: 52, name: 'create expenses' },
    { id: 53, name: 'approve expenses' },
    { id: 54, name: 'reject expenses' },
    { id: 55, name: 'delete expenses' },
    { id: 56, name: 'mark expenses paid' }
  ],
  reports: [
    { id: 57, name: 'view reports' },
    { id: 58, name: 'export reports' }
  ],
  settings: [
    { id: 59, name: 'view hr settings' },
    { id: 60, name: 'edit hr settings' },
    { id: 61, name: 'manage roles' },
    { id: 62, name: 'manage permissions' },
    { id: 63, name: 'assign roles' }
  ]
};

const allFallbackPermissionNames = Object.values(fallbackPermissions).flat().map((item) => item.name);

const roleHeaders = [
  { title: 'User', key: 'user', sortable: false },
  { title: 'Current Role', key: 'role', sortable: false },
  { title: 'Permissions Count', key: 'permissions_count', sortable: false },
  { title: 'Last Active', key: 'last_active', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false }
];

const roleForm = reactive({
  id: null as number | null,
  name: '',
  description: '',
  permissions: [] as string[]
});

const assignDialog = ref({
  show: false,
  user: null as UserWithRole | null,
  role: ''
});

const permissionsDialog = ref({
  show: false,
  user: null as UserWithRole | null
});

const confirmDialog = ref({
  show: false,
  title: '',
  message: '',
  action: '' as '' | 'delete-role' | 'remove-role',
  roleId: null as number | null,
  userId: null as number | null
});

const templateConfirm = ref({
  show: false,
  template: ''
});

const moduleMeta: Record<string, { title: string; icon: string }> = {
  dashboard: { title: 'Dashboard', icon: 'mdi-view-dashboard' },
  employees: { title: 'Employees', icon: 'mdi-account-group' },
  departments: { title: 'Departments', icon: 'mdi-office-building' },
  designations: { title: 'Designations', icon: 'mdi-medal-outline' },
  attendance: { title: 'Attendance', icon: 'mdi-calendar-check' },
  leave: { title: 'Leave', icon: 'mdi-palm-tree' },
  shifts: { title: 'Shifts', icon: 'mdi-clock-outline' },
  recruitment: { title: 'Recruitment', icon: 'mdi-briefcase' },
  payroll: { title: 'Payroll', icon: 'mdi-cash' },
  expenses: { title: 'Expenses', icon: 'mdi-receipt-text-outline' },
  reports: { title: 'Reports', icon: 'mdi-chart-box-outline' },
  settings: { title: 'Settings', icon: 'mdi-cog-outline' },
  other: { title: 'Other', icon: 'mdi-shape-outline' }
};

const roleTemplates: Record<string, string[]> = {
  'HR Admin': allFallbackPermissionNames,
  'HR Manager': allFallbackPermissionNames.filter((item) => !item.includes('delete') && item !== 'edit hr settings' && item !== 'manage roles' && item !== 'manage permissions'),
  'Payroll Officer': ['view hr dashboard', 'view employees', 'view payroll', 'create payroll', 'process payroll', 'approve payroll', 'view payslips', 'edit payslips', 'manage salary structures', 'view expenses', 'approve expenses', 'reject expenses', 'mark expenses paid', 'view reports', 'export reports'],
  Recruiter: ['view hr dashboard', 'view employees', 'view job openings', 'create job openings', 'edit job openings', 'view applicants', 'create applicants', 'edit applicants', 'move applicant stage', 'convert to employee', 'view onboarding', 'manage onboarding', 'view reports'],
  Employee: ['view hr dashboard', 'view employees', 'view leave requests', 'create leave requests', 'view attendance', 'view payslips', 'create expenses', 'view expenses']
};

const canManageRoles = computed(() => can('manage roles') || can('manage permissions') || isAdmin());
const canAssignRoles = computed(() => can('assign roles') || can('manage roles') || isAdmin());
const totalPermissions = computed(() => Object.values(permissionsByGroup.value).flat().length);
const usersWithRoles = computed(() => users.value.filter((user) => user.roles.length > 0).length);
const selectedPermissionsCount = computed(() => roleForm.permissions.length);
const roleOptions = computed(() => roles.value.map((role) => ({ title: `${role.name} (${role.users_count} users)`, value: role.name })));
const selectedRole = computed(() => roles.value.find((role) => role.name === assignDialog.value.role) ?? null);

function showSnackbar(message: string, color: 'success' | 'error' | 'warning' = 'success') {
  snackbar.value = { show: true, message, color };
}

function roleColor(name: string) {
  if (name === 'HR Admin') return 'primary';
  if (name === 'HR Manager') return 'purple';
  if (name === 'Payroll Officer') return 'success';
  if (name === 'Recruiter') return 'warning';
  if (name === 'Employee') return 'grey';
  if (name === 'Supervisor') return 'teal';
  return 'indigo';
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

function roleInitials(name: string) {
  const words = name.split(/\s+/).filter(Boolean);
  if (!words.length) return 'RL';
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase();
  return `${words[0][0]}${words[1][0]}`.toUpperCase();
}

function canModifyRole(role: Role) {
  return !['super-admin'].includes(role.name);
}

function formatModuleLabel(key: string) {
  return moduleMeta[key]?.title ?? key.charAt(0).toUpperCase() + key.slice(1);
}

function inferPermissionGroup(permissionName: string) {
  const entries = Object.entries(permissionsByGroup.value);
  const matched = entries.find(([, list]) => list.some((permission) => permission.name === permissionName));
  if (matched) return matched[0];

  if (permissionName.includes('dashboard')) return 'dashboard';
  if (permissionName.includes('employees')) return 'employees';
  if (permissionName.includes('departments')) return 'departments';
  if (permissionName.includes('designations')) return 'designations';
  if (permissionName.includes('attendance')) return 'attendance';
  if (permissionName.includes('leave')) return 'leave';
  if (permissionName.includes('shifts')) return 'shifts';
  if (permissionName.includes('job openings') || permissionName.includes('applicants') || permissionName.includes('onboarding') || permissionName.includes('convert to employee')) return 'recruitment';
  if (permissionName.includes('payroll') || permissionName.includes('payslips') || permissionName.includes('salary structures')) return 'payroll';
  if (permissionName.includes('expenses')) return 'expenses';
  if (permissionName.includes('reports')) return 'reports';
  if (permissionName.includes('settings') || permissionName.includes('roles') || permissionName.includes('permissions')) return 'settings';
  return 'other';
}

function groupPermissions(items: Permission[]) {
  return items.reduce<Record<string, Permission[]>>((acc, permission) => {
    const group = inferPermissionGroup(permission.name);
    if (!acc[group]) acc[group] = [];
    acc[group].push(permission);
    return acc;
  }, {});
}

function permissionPreview(role: Role) {
  const limit = expandedRoles.value.includes(role.id) ? Number.POSITIVE_INFINITY : 8;
  const preview: PermissionGroupPreview[] = [];
  let shown = 0;

  Object.entries(groupPermissions(role.permissions)).forEach(([group, permissions]) => {
    if (shown >= limit) return;

    const remaining = limit - shown;
    const visible = permissions.slice(0, remaining);

    if (visible.length) {
      preview.push({ key: group, permissions: visible });
      shown += visible.length;
    }
  });

  return {
    groups: preview,
    remaining: Math.max(role.permissions.length - shown, 0)
  };
}

function toggleExpanded(roleId: number) {
  if (expandedRoles.value.includes(roleId)) {
    expandedRoles.value = expandedRoles.value.filter((id) => id !== roleId);
    return;
  }

  expandedRoles.value = [...expandedRoles.value, roleId];
}

async function fetchRoles() {
  loading.value = true;

  try {
    const { data } = await axios.get('/api/hr/roles');
    roles.value = data?.roles ?? [];
    permissionsByGroup.value = data?.permissions ?? {};
  } catch (error: any) {
    roles.value = [];
    permissionsByGroup.value = {};
    showSnackbar(error?.response?.data?.message ?? 'Failed to load roles from server.', 'error');
  } finally {
    loading.value = false;
  }
}

async function fetchUsers() {
  userLoading.value = true;

  try {
    const { data } = await axios.get('/api/hr/roles/users', {
      params: {
        search: userSearch.value || undefined,
        page: pagination.page,
        per_page: pagination.perPage
      }
    });

    users.value = data?.users?.data ?? [];
    pagination.total = data?.users?.total ?? 0;
  } catch (error: any) {
    users.value = [];
    pagination.total = 0;
    showSnackbar(error?.response?.data?.message ?? 'Failed to load user assignments from server.', 'error');
  } finally {
    userLoading.value = false;
  }
}

function resetRoleForm() {
  roleForm.id = null;
  roleForm.name = '';
  roleForm.description = '';
  roleForm.permissions = [];
}

function openCreateDrawer() {
  resetRoleForm();
  drawerOpen.value = true;
}

function openEditDrawer(role: Role) {
  roleForm.id = role.id;
  roleForm.name = role.name;
  roleForm.description = '';
  roleForm.permissions = role.permissions.map((permission) => permission.name);
  drawerOpen.value = true;
}

async function saveRole() {
  if (!roleForm.name.trim()) {
    showSnackbar('Role name is required.', 'error');
    return;
  }

  savingRole.value = true;

  try {
    const payload = {
      name: roleForm.name.trim(),
      permissions: roleForm.permissions
    };

    if (roleForm.id) {
      await axios.put(`/api/hr/roles/${roleForm.id}`, payload);
      showSnackbar('Role updated successfully.');
    } else {
      await axios.post('/api/hr/roles', payload);
      showSnackbar('Role created successfully.');
    }

    drawerOpen.value = false;
    await Promise.all([fetchRoles(), fetchUsers()]);
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to save role.', 'error');
  } finally {
    savingRole.value = false;
  }
}

function askDelete(role: Role) {
  confirmDialog.value = {
    show: true,
    title: 'Delete Role',
    message: `Delete ${role.name}?`,
    action: 'delete-role',
    roleId: role.id,
    userId: null
  };
}

function askRemoveRole(user: UserWithRole) {
  confirmDialog.value = {
    show: true,
    title: 'Remove Role',
    message: `Remove role assignment from ${user.name}?`,
    action: 'remove-role',
    roleId: null,
    userId: user.id
  };
}

async function confirmAction() {
  try {
    if (confirmDialog.value.action === 'delete-role' && confirmDialog.value.roleId) {
      await axios.delete(`/api/hr/roles/${confirmDialog.value.roleId}`);
      showSnackbar('Role deleted.');
      await fetchRoles();
    }

    if (confirmDialog.value.action === 'remove-role' && confirmDialog.value.userId) {
      await axios.post('/api/hr/roles/assign', {
        user_id: confirmDialog.value.userId,
        role: null
      });
      showSnackbar('Role removed.');
      await fetchUsers();
    }

    confirmDialog.value.show = false;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Action failed.', 'error');
  }
}

function selectAllPermissions() {
  roleForm.permissions = Object.values(permissionsByGroup.value).flat().map((permission) => permission.name);
}

function deselectAllPermissions() {
  roleForm.permissions = [];
}

function selectReadOnly() {
  roleForm.permissions = Object.values(permissionsByGroup.value)
    .flat()
    .map((permission) => permission.name)
    .filter((name) => name.startsWith('view '));
}

function requestTemplate(template: string) {
  templateConfirm.value = { show: true, template };
}

function applyTemplate() {
  roleForm.permissions = [...(roleTemplates[templateConfirm.value.template] ?? [])];
  templateConfirm.value.show = false;
}

function assignmentRoleChip(user: UserWithRole) {
  return user.roles[0]?.name ?? 'No Role';
}

function permissionCountForUser(user: UserWithRole) {
  const direct = user.roles[0]?.permissions?.length;
  if (typeof direct === 'number') return direct;
  return roles.value.find((role) => role.name === user.roles[0]?.name)?.permissions.length ?? 0;
}

function formatLastActive(value?: string | null) {
  return value || 'Never';
}

function openAssignDialog(user: UserWithRole) {
  assignDialog.value = {
    show: true,
    user,
    role: user.roles[0]?.name ?? ''
  };
}

async function assignRole() {
  if (!assignDialog.value.user) return;

  assigningRole.value = true;

  try {
    await axios.post('/api/hr/roles/assign', {
      user_id: assignDialog.value.user.id,
      role: assignDialog.value.role || null
    });

    const assignedRole = assignDialog.value.role;
    assignDialog.value.show = false;
    showSnackbar(assignedRole ? 'Role assigned successfully.' : 'Role removed successfully.');
    await fetchUsers();
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to assign role.', 'error');
  } finally {
    assigningRole.value = false;
  }
}

function openPermissionsDialog(user: UserWithRole) {
  permissionsDialog.value = { show: true, user };
}

const addedPermissions = computed(() => {
  const current = assignDialog.value.user?.roles[0]?.permissions?.map((permission) => permission.name)
    ?? roles.value.find((role) => role.name === assignDialog.value.user?.roles[0]?.name)?.permissions.map((permission) => permission.name)
    ?? [];
  const next = selectedRole.value?.permissions.map((permission) => permission.name) ?? [];

  return next.filter((permission) => !current.includes(permission));
});

const removedPermissions = computed(() => {
  const current = assignDialog.value.user?.roles[0]?.permissions?.map((permission) => permission.name)
    ?? roles.value.find((role) => role.name === assignDialog.value.user?.roles[0]?.name)?.permissions.map((permission) => permission.name)
    ?? [];
  const next = selectedRole.value?.permissions.map((permission) => permission.name) ?? [];

  return current.filter((permission) => !next.includes(permission));
});

function groupedPermissionsForUser(user: UserWithRole | null) {
  const permissionNames = user?.roles[0]?.permissions?.map((permission) => permission.name)
    ?? roles.value.find((role) => role.name === user?.roles[0]?.name)?.permissions.map((permission) => permission.name)
    ?? [];

  return Object.entries(permissionsByGroup.value).reduce<Record<string, string[]>>((acc, [group, permissions]) => {
    const names = permissions
      .map((permission) => permission.name)
      .filter((name) => permissionNames.includes(name));

    if (names.length) acc[group] = names;
    return acc;
  }, {});
}

function handleTableOptions(options: { page: number; itemsPerPage: number }) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;
}

watch([userSearch, () => pagination.page, () => pagination.perPage], () => {
  fetchUsers();
});

onMounted(async () => {
  await fetchRoles();
  await fetchUsers();
});
</script>

<template>
  <BaseBreadcrumb
    title="Roles & Permissions"
    subtitle="Control access levels across the HR system"
    :breadcrumbs="breadcrumbs"
  />

  <div class="d-flex justify-space-between align-center flex-wrap ga-3 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Roles & Permissions</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Control access levels across the HR system</p>
    </div>

    <v-btn
      color="primary"
      prepend-icon="mdi-shield-plus"
      @click="openCreateDrawer"
    >
      Create Role
    </v-btn>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-xl hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="tonal">RL</v-avatar>
          <div>
            <div class="text-caption text-lightText">Total Roles</div>
            <div class="text-h5 font-weight-bold">{{ roles.length }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-xl hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="purple" variant="tonal">PM</v-avatar>
          <div>
            <div class="text-caption text-lightText">Total Permissions</div>
            <div class="text-h5 font-weight-bold">{{ totalPermissions }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>

    <v-col cols="12" sm="6" md="4">
      <v-card class="bg-surface rounded-xl hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="success" variant="tonal">UR</v-avatar>
          <div>
            <div class="text-caption text-lightText">Users with Roles</div>
            <div class="text-h5 font-weight-bold">{{ usersWithRoles }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-xl hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item class="pb-0">
      <v-tabs v-model="activeTab" color="primary">
        <v-tab value="roles">Roles</v-tab>
        <v-tab value="assignments">User Assignments</v-tab>
      </v-tabs>
    </v-card-item>

    <v-divider />

    <v-card-text>
      <v-window v-model="activeTab">
        <v-window-item value="roles">
          <v-skeleton-loader v-if="loading" type="article, article" />

          <v-row v-else>
            <v-col v-for="role in roles" :key="role.id" cols="12" md="6">
              <v-card
                class="rounded-xl hr-card-shadow role-card"
                variant="outlined"
                elevation="0"
                :style="{ borderLeft: `4px solid rgb(var(--v-theme-${roleColor(role.name)}))` }"
              >
                <v-card-text>
                  <div class="d-flex justify-space-between align-start ga-3 mb-4">
                    <div class="d-flex align-center ga-3">
                      <v-avatar :color="roleColor(role.name)" variant="tonal">
                        {{ roleInitials(role.name) }}
                      </v-avatar>

                      <div>
                        <div class="text-h6 font-weight-bold">{{ role.name }}</div>
                        <div class="d-flex align-center ga-2 flex-wrap mt-1">
                          <v-chip size="small" variant="tonal">{{ role.users_count }} users</v-chip>
                          <v-chip v-if="!canModifyRole(role)" size="small" color="primary" variant="outlined">System</v-chip>
                        </div>
                      </div>
                    </div>

                    <div class="d-flex ga-1">
                      <v-btn
                        icon="mdi-pencil-outline"
                        variant="text"
                        :disabled="!canModifyRole(role)"
                        @click="openEditDrawer(role)"
                      />
                      <v-btn
                        icon="mdi-trash-can-outline"
                        variant="text"
                        color="error"
                        :disabled="!canModifyRole(role) || !canManageRoles"
                        @click="askDelete(role)"
                      />
                    </div>
                  </div>

                  <div class="text-caption text-lightText mb-3">{{ role.permissions.length }} permissions</div>

                  <div
                    v-for="group in permissionPreview(role).groups"
                    :key="`${role.id}-${group.key}`"
                    class="mb-3"
                  >
                    <div class="text-caption text-lightText mb-2 d-flex align-center ga-2">
                      <v-icon size="16" :icon="moduleMeta[group.key]?.icon ?? moduleMeta.other.icon" />
                      <span>{{ formatModuleLabel(group.key) }}</span>
                    </div>

                    <div class="d-flex flex-wrap ga-2">
                      <v-chip
                        v-for="permission in group.permissions"
                        :key="permission.id"
                        size="small"
                        :color="roleColor(role.name)"
                        variant="tonal"
                      >
                        {{ permission.name }}
                      </v-chip>
                    </div>
                  </div>

                  <v-chip
                    v-if="permissionPreview(role).remaining > 0"
                    size="small"
                    variant="outlined"
                    class="mb-3"
                    @click="toggleExpanded(role.id)"
                  >
                    {{ expandedRoles.includes(role.id) ? 'Show less' : `+${permissionPreview(role).remaining} more` }}
                  </v-chip>

                  <div>
                      <v-btn
                        size="small"
                        variant="outlined"
                        :disabled="!canModifyRole(role)"
                        @click="openEditDrawer(role)"
                      >
                      Edit Permissions
                    </v-btn>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="assignments">
          <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
            <v-text-field
              v-model="userSearch"
              placeholder="Search users by name or email..."
              variant="outlined"
              hide-details
              style="max-width: 360px;"
            />

            <v-btn variant="outlined" prepend-icon="mdi-refresh" @click="fetchUsers">
              Refresh
            </v-btn>
          </div>

          <v-alert
            v-if="!canAssignRoles"
            type="warning"
            variant="tonal"
            class="mb-4"
          >
            You can review roles here, but you do not have permission to assign roles to users.
          </v-alert>

          <v-skeleton-loader v-if="userLoading" type="table" />

          <v-data-table-server
            v-else
            :headers="roleHeaders"
            :items="users"
            :items-length="pagination.total"
            :items-per-page="pagination.perPage"
            :page="pagination.page"
            :items-per-page-options="[15, 30, 50]"
            @update:options="handleTableOptions"
          >
            <template #item.user="{ item }">
              <div class="d-flex align-center ga-3">
                <v-avatar color="primary" variant="tonal">{{ initials(item.name) }}</v-avatar>
                <div>
                  <div class="font-weight-medium">{{ item.name }}</div>
                  <div class="text-caption text-lightText">{{ item.email }}</div>
                </div>
              </div>
            </template>

            <template #item.role="{ item }">
              <v-chip
                size="small"
                :color="item.roles[0]?.name ? roleColor(item.roles[0].name) : 'grey'"
                variant="tonal"
              >
                {{ assignmentRoleChip(item) }}
              </v-chip>
            </template>

            <template #item.permissions_count="{ item }">
              {{ permissionCountForUser(item) }} permissions
            </template>

            <template #item.last_active="{ item }">
              {{ formatLastActive(item.last_login_at) }}
            </template>

            <template #item.actions="{ item }">
              <v-menu>
                <template #activator="{ props }">
                  <v-btn icon variant="text" v-bind="props">
                    <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                  </v-btn>
                </template>

                <v-list density="compact">
                  <v-list-item title="Assign/Change Role" :disabled="!canAssignRoles" @click="openAssignDialog(item)" />
                  <v-list-item title="View Permissions" @click="openPermissionsDialog(item)" />
                  <v-list-item title="Remove Role" base-color="error" :disabled="!item.roles.length || !canAssignRoles" @click="askRemoveRole(item)" />
                </v-list>
              </v-menu>
            </template>
          </v-data-table-server>
        </v-window-item>
      </v-window>
    </v-card-text>
  </v-card>

  <v-dialog v-model="drawerOpen" max-width="1000" scrollable>
    <v-card>
      <div class="pa-4 border-b d-flex justify-space-between align-center">
        <div>
          <h5 class="text-h5 mb-1">{{ roleForm.id ? `Edit Role: ${roleForm.name}` : 'Create Role' }}</h5>
          <p class="text-body-2 text-lightText mb-0">Define role details and assign module permissions.</p>
        </div>

        <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
      </div>

      <v-card-text class="pa-4 drawer-body">
        <div class="text-subtitle-1 font-weight-medium mb-3">Role Details</div>
        <v-text-field v-model="roleForm.name" label="Role Name *" variant="outlined" class="mb-3" />
        <v-textarea v-model="roleForm.description" label="Description" rows="2" variant="outlined" class="mb-5" />

        <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-3">
          <div class="text-subtitle-1 font-weight-medium">Permissions Matrix</div>
          <div class="d-flex ga-2 flex-wrap">
            <v-btn size="small" variant="outlined" @click="selectAllPermissions">Select All</v-btn>
            <v-btn size="small" variant="outlined" @click="deselectAllPermissions">Deselect All</v-btn>
            <v-btn size="small" variant="outlined" @click="selectReadOnly">Select Read-Only</v-btn>
          </div>
        </div>

        <div class="d-flex align-center ga-2 flex-wrap mb-4">
          <span class="text-body-2 text-lightText">Apply template:</span>
          <v-btn
            v-for="template in Object.keys(roleTemplates)"
            :key="template"
            size="small"
            variant="outlined"
            @click="requestTemplate(template)"
          >
            {{ template }}
          </v-btn>
        </div>

        <v-expansion-panels multiple>
          <v-expansion-panel v-for="(permissions, group) in permissionsByGroup" :key="group">
            <v-expansion-panel-title>
              <div class="d-flex align-center justify-space-between w-100 pe-4">
                <div class="d-flex align-center ga-2">
                  <v-icon :icon="moduleMeta[group]?.icon ?? moduleMeta.other.icon" />
                  <span>{{ formatModuleLabel(group) }}</span>
                </div>

                <span class="text-caption text-lightText">
                  {{ permissions.filter((permission) => roleForm.permissions.includes(permission.name)).length }}/{{ permissions.length }} selected
                </span>
              </div>
            </v-expansion-panel-title>

            <v-expansion-panel-text>
              <v-row>
                <v-col
                  v-for="permission in permissions"
                  :key="permission.id"
                  cols="12"
                  md="4"
                >
                  <v-checkbox
                    v-model="roleForm.permissions"
                    :label="permission.name"
                    :value="permission.name"
                    density="compact"
                    hide-details
                    color="primary"
                  />
                </v-col>
              </v-row>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </v-card-text>
      <div class="pa-4 border-t d-flex justify-space-between align-center sticky-footer">
        <div class="text-body-2 text-lightText">{{ selectedPermissionsCount }} permissions selected</div>

        <div class="d-flex ga-2">
          <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
          <v-btn color="primary" :loading="savingRole" @click="saveRole">Save Role</v-btn>
        </div>
      </div>
    </v-card>
  </v-dialog>

  <v-dialog v-model="assignDialog.show" max-width="480">
    <v-card>
      <v-card-title class="text-h5">Assign Role to {{ assignDialog.user?.name }}</v-card-title>

      <v-card-text>
        <div class="mb-3">
          <div class="text-caption text-lightText mb-1">Current role</div>
          <v-chip
            size="small"
            :color="assignDialog.user?.roles[0]?.name ? roleColor(assignDialog.user.roles[0].name) : 'grey'"
            variant="tonal"
          >
            {{ assignDialog.user?.roles[0]?.name ?? 'No Role' }}
          </v-chip>
        </div>

        <v-select
          v-model="assignDialog.role"
          :items="[{ title: 'No Role', value: '' }, ...roleOptions]"
          label="Choose New Role"
          variant="outlined"
          class="mb-3"
        />

        <v-alert v-if="selectedRole" type="info" variant="tonal" class="mb-3">
          This role grants {{ selectedRole.permissions.length }} permissions.
        </v-alert>

        <div v-if="addedPermissions.length || removedPermissions.length">
          <div class="text-caption text-lightText mb-2">Permission diff</div>
          <div class="d-flex ga-2 flex-wrap">
            <v-chip v-if="addedPermissions.length" size="small" color="success" variant="tonal">+ {{ addedPermissions.length }} new permissions</v-chip>
            <v-chip v-if="removedPermissions.length" size="small" color="error" variant="tonal">- {{ removedPermissions.length }} removed permissions</v-chip>
          </div>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="assignDialog.show = false">Cancel</v-btn>
        <v-btn color="primary" :loading="assigningRole" @click="assignRole">Assign Role</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="permissionsDialog.show" max-width="600">
    <v-card>
      <v-card-title class="text-h5">{{ permissionsDialog.user?.name }} - Permissions</v-card-title>

      <v-card-text>
        <v-chip
          size="small"
          :color="permissionsDialog.user?.roles[0]?.name ? roleColor(permissionsDialog.user.roles[0].name) : 'grey'"
          variant="tonal"
          class="mb-4"
        >
          {{ permissionsDialog.user?.roles[0]?.name ?? 'No Role' }}
        </v-chip>

        <div
          v-for="(permissions, group) in groupedPermissionsForUser(permissionsDialog.user)"
          :key="group"
          class="mb-4"
        >
          <div class="text-subtitle-2 mb-2">{{ formatModuleLabel(group) }}</div>
          <div class="d-flex flex-wrap ga-2">
            <v-chip v-for="permission in permissions" :key="permission" size="small" color="success" variant="tonal">
              <v-icon start icon="mdi-check" />
              {{ permission }}
            </v-chip>
          </div>
        </div>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="permissionsDialog.show = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title>
      <v-card-text>{{ confirmDialog.message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn color="error" @click="confirmAction">Confirm</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="templateConfirm.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Apply Template</v-card-title>
      <v-card-text>
        This will replace current permission selections. Continue?
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="templateConfirm.show = false">Cancel</v-btn>
        <v-btn color="primary" @click="applyTemplate">Continue</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.role-card {
  min-height: 290px;
}

.drawer-body {
  max-height: 65vh;
  overflow-y: auto;
}

.sticky-footer {
  position: sticky;
  bottom: 0;
  background: #fff;
}

.border-b {
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.border-t {
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}
</style>
