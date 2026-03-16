<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue'
import { usePermissions } from '@/composables/usePermissions'

const { isAdmin } = usePermissions()

const activeTab = ref<'roles' | 'assignments'>('roles')

const loadingRoles = ref(false)
const loadingAssignments = ref(false)
const saving = ref(false)

const roles = ref<any[]>([])
const allPermissions = ref<any[]>([])
const users = ref<any[]>([])
const allRoles = ref<any[]>([])
const stats = ref<any>({})

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

const createRoleDialog = ref(false)
const editPermissionsDialog = ref(false)
const assignRoleDialog = ref(false)
const confirmDeleteDialog = ref(false)

const selectedRole = ref<any>(null)
const roleToDelete = ref<any>(null)

const newRoleForm = ref({
  name: '',
  description: '',
  color: 'primary',
  permission_ids: [] as number[],
})

const editPermissionIds = ref<number[]>([])

const assignForm = ref({
  user_id: null as number | null,
  role_id: null as number | null,
})

const userSearch = ref('')

const colorOptions = [
  { label: 'Blue', value: 'primary' },
  { label: 'Cyan', value: 'cyan' },
  { label: 'Green', value: 'success' },
  { label: 'Purple', value: 'purple' },
  { label: 'Amber', value: 'warning' },
  { label: 'Red', value: 'error' },
  { label: 'Grey', value: 'secondary' },
]

function showSnack(message: string, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function fetchRoles() {
  loadingRoles.value = true

  try {
    const { data } = await axios.get('/api/hr/roles')
    roles.value = data.roles ?? []
    allPermissions.value = data.all_permissions ?? []
    stats.value = data.stats ?? {}
  } catch {
    showSnack('Failed to load roles.', 'error')
  } finally {
    loadingRoles.value = false
  }
}

async function fetchAssignments() {
  loadingAssignments.value = true

  try {
    const { data } = await axios.get('/api/hr/role-assignments')
    users.value = data.users ?? []
    allRoles.value = data.roles ?? []
  } catch {
    showSnack('Failed to load user assignments.', 'error')
  } finally {
    loadingAssignments.value = false
  }
}

function toggleCreateModule(module: any) {
  const ids = module.items.map((item: any) => item.id)
  const allSelected = ids.every((id: number) =>
    newRoleForm.value.permission_ids.includes(id)
  )

  if (allSelected) {
    newRoleForm.value.permission_ids = newRoleForm.value.permission_ids
      .filter((id: number) => !ids.includes(id))
    return
  }

  ids.forEach((id: number) => {
    if (!newRoleForm.value.permission_ids.includes(id)) {
      newRoleForm.value.permission_ids.push(id)
    }
  })
}

function createModuleAllSelected(module: any) {
  return module.items.every((item: any) =>
    newRoleForm.value.permission_ids.includes(item.id)
  )
}

function createModuleSomeSelected(module: any) {
  return module.items.some((item: any) =>
    newRoleForm.value.permission_ids.includes(item.id)
  ) && !createModuleAllSelected(module)
}

async function createRole() {
  saving.value = true

  try {
    const { data } = await axios.post('/api/hr/roles', newRoleForm.value)
    showSnack(data.message ?? 'Role created.', 'success')

    createRoleDialog.value = false
    newRoleForm.value = {
      name: '',
      description: '',
      color: 'primary',
      permission_ids: [],
    }

    await fetchRoles()
  } catch (error: any) {
    showSnack(
      error?.response?.data?.errors?.name?.[0]
      ?? error?.response?.data?.message
      ?? 'Failed to create role.',
      'error'
    )
  } finally {
    saving.value = false
  }
}

function openEditPermissions(role: any) {
  selectedRole.value = role
  editPermissionIds.value = role.permissions
    .flatMap((group: any) => group.items.map((item: any) => item.id))

  editPermissionsDialog.value = true
}

function togglePermission(permId: number) {
  const index = editPermissionIds.value.indexOf(permId)
  if (index === -1) {
    editPermissionIds.value.push(permId)
    return
  }

  editPermissionIds.value.splice(index, 1)
}

function hasPermission(permId: number) {
  return editPermissionIds.value.includes(permId)
}

function toggleEditModule(module: any) {
  const ids = module.items.map((item: any) => item.id)
  const allSelected = ids.every((id: number) =>
    editPermissionIds.value.includes(id)
  )

  if (allSelected) {
    editPermissionIds.value = editPermissionIds.value
      .filter((id: number) => !ids.includes(id))
    return
  }

  ids.forEach((id: number) => {
    if (!editPermissionIds.value.includes(id)) {
      editPermissionIds.value.push(id)
    }
  })
}

function editModuleAllSelected(module: any) {
  return module.items.every((item: any) =>
    editPermissionIds.value.includes(item.id)
  )
}

function editModuleSomeSelected(module: any) {
  return module.items.some((item: any) =>
    editPermissionIds.value.includes(item.id)
  ) && !editModuleAllSelected(module)
}

async function savePermissions() {
  if (!selectedRole.value) {
    return
  }

  saving.value = true

  try {
    const { data } = await axios.post(
      '/api/hr/roles/' + selectedRole.value.id + '/permissions',
      { permission_ids: editPermissionIds.value }
    )

    showSnack(data.message ?? 'Permissions updated.', 'success')
    editPermissionsDialog.value = false

    await fetchRoles()
  } catch {
    showSnack('Failed to save permissions.', 'error')
  } finally {
    saving.value = false
  }
}

function openAssignDialog(userId?: number) {
  if (users.value.length === 0) {
    fetchAssignments()
  }

  assignForm.value = {
    user_id: userId ?? null,
    role_id: null,
  }

  assignRoleDialog.value = true
}

async function assignRole() {
  if (!assignForm.value.user_id || !assignForm.value.role_id) {
    return
  }

  saving.value = true

  try {
    const { data } = await axios.post('/api/hr/role-assignments', assignForm.value)
    showSnack(data.message ?? 'Role assigned.', 'success')

    assignRoleDialog.value = false
    assignForm.value = {
      user_id: null,
      role_id: null,
    }

    await fetchAssignments()
    await fetchRoles()
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to assign role.', 'error')
  } finally {
    saving.value = false
  }
}

async function removeRole(
  userId: number,
  roleId: number,
  userName: string,
  roleName: string
) {
  const ok = window.confirm(`Remove ${roleName} from ${userName}?`)
  if (!ok) {
    return
  }

  try {
    await axios.delete('/api/hr/role-assignments/' + userId, {
      data: { role_id: roleId },
    })

    showSnack(`${roleName} removed from ${userName}.`, 'success')

    await fetchAssignments()
    await fetchRoles()
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to remove role.', 'error')
  }
}

function confirmDelete(role: any) {
  roleToDelete.value = role
  confirmDeleteDialog.value = true
}

function openRole(roleId: number) {
  router.visit('/hr/roles-permissions/' + roleId)
}

async function deleteRole() {
  if (!roleToDelete.value) {
    return
  }

  try {
    const { data } = await axios.delete('/api/hr/roles/' + roleToDelete.value.id)

    showSnack(data.message ?? 'Role deleted.', 'success')
    confirmDeleteDialog.value = false
    roleToDelete.value = null

    await fetchRoles()
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Cannot delete this role.', 'error')
  }
}

function onTabChange(tab: string) {
  if (tab === 'assignments') {
    fetchAssignments()
  }
}

const filteredUsers = computed(() => {
  if (!userSearch.value) {
    return users.value
  }

  const query = userSearch.value.toLowerCase()

  return users.value.filter((user: any) =>
    user.name.toLowerCase().includes(query)
    || user.email.toLowerCase().includes(query)
    || (user.designation ?? '').toLowerCase().includes(query)
    || (user.department ?? '').toLowerCase().includes(query)
  )
})

onMounted(() => {
  fetchRoles()
})
</script>

<template>
  <UnauthorizedPage v-if="!isAdmin" />

  <template v-else>
  <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
    <div>
      <h1 class="text-h5 font-weight-bold">Roles & Permissions</h1>
      <p class="text-body-2 text-medium-emphasis mt-1">
        Control access levels across the HR system.
      </p>
    </div>

    <v-btn
      color="primary"
      variant="flat"
      prepend-icon="mdi-plus"
      @click="createRoleDialog = true"
    >
      Create Role
    </v-btn>
  </div>

  <v-row class="mb-4">
    <v-col cols="12" md="3">
      <v-card rounded="lg" elevation="0" border class="pa-4">
        <div class="text-h4 font-weight-bold text-primary">{{ stats.total_roles ?? 0 }}</div>
        <div class="text-body-2 text-medium-emphasis">Total Roles</div>
      </v-card>
    </v-col>

    <v-col cols="12" md="3">
      <v-card rounded="lg" elevation="0" border class="pa-4">
        <div class="text-h4 font-weight-bold text-success">{{ stats.system_roles ?? 0 }}</div>
        <div class="text-body-2 text-medium-emphasis">System Roles</div>
      </v-card>
    </v-col>

    <v-col cols="12" md="3">
      <v-card rounded="lg" elevation="0" border class="pa-4">
        <div class="text-h4 font-weight-bold text-warning">{{ stats.custom_roles ?? 0 }}</div>
        <div class="text-body-2 text-medium-emphasis">Custom Roles</div>
      </v-card>
    </v-col>

    <v-col cols="12" md="3">
      <v-card rounded="lg" elevation="0" border class="pa-4">
        <div class="text-h4 font-weight-bold">{{ stats.total_users ?? 0 }}</div>
        <div class="text-body-2 text-medium-emphasis">Total Users</div>
      </v-card>
    </v-col>
  </v-row>

  <v-card rounded="lg" elevation="0" border>
    <v-tabs
      v-model="activeTab"
      color="primary"
      @update:model-value="onTabChange"
    >
      <v-tab value="roles">
        <v-icon start>mdi-shield-account</v-icon>
        Roles
      </v-tab>
      <v-tab value="assignments">
        <v-icon start>mdi-account-key</v-icon>
        User Assignments
      </v-tab>
    </v-tabs>

    <v-divider />

    <v-window v-model="activeTab">
      <v-window-item value="roles">
        <v-skeleton-loader
          v-if="loadingRoles"
          type="table-row,table-row,table-row,table-row"
        />

        <v-list v-else class="pa-2">
          <v-list-item
            v-for="role in roles"
            :key="role.id"
            rounded="lg"
            class="mb-2 pa-4"
            :style="{ border: '1px solid #f0f0f0' }"
            style="cursor: pointer"
            @click="openRole(role.id)"
          >
            <template #prepend>
              <v-avatar :color="role.color" size="42" class="mr-3">
                <v-icon color="white" size="20">mdi-shield-account</v-icon>
              </v-avatar>
            </template>

            <v-list-item-title class="font-weight-bold text-body-1 mb-1">
              {{ role.name }}
              <v-chip
                v-if="role.is_system"
                size="x-small"
                color="primary"
                variant="tonal"
                class="ml-2"
              >
                System
              </v-chip>
            </v-list-item-title>

            <v-list-item-subtitle>
              {{ role.description ?? 'No description' }}
              <span class="mx-1"> - </span>
              <span class="text-primary">{{ role.permission_count }} permissions</span>
              <span class="mx-1"> - </span>
              <span>{{ role.users_count }} users</span>
            </v-list-item-subtitle>

            <div class="mt-2 d-flex flex-wrap ga-1">
              <v-chip
                v-for="module in role.permissions.slice(0, 4)"
                :key="module.module"
                size="x-small"
                :color="role.color"
                variant="tonal"
              >
                {{ module.module }}
              </v-chip>

              <v-chip
                v-if="role.permissions.length > 4"
                size="x-small"
                color="grey"
                variant="tonal"
              >
                +{{ role.permissions.length - 4 }} more
              </v-chip>
            </div>

            <template #append>
              <div class="d-flex ga-2">
                <v-btn
                  size="small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="mdi-key"
                  @click.stop="openEditPermissions(role)"
                >
                  Permissions
                </v-btn>

                <v-btn
                  v-if="!role.is_system"
                  size="small"
                  variant="tonal"
                  color="error"
                  icon
                  @click.stop="confirmDelete(role)"
                >
                  <v-icon size="18">mdi-delete</v-icon>
                </v-btn>
              </div>
            </template>
          </v-list-item>
        </v-list>
      </v-window-item>

      <v-window-item value="assignments">
        <div class="pa-4 d-flex align-center ga-3 flex-wrap">
          <v-text-field
            v-model="userSearch"
            placeholder="Search users..."
            variant="outlined"
            density="compact"
            hide-details
            prepend-inner-icon="mdi-magnify"
            style="max-width: 300px"
          />

          <v-spacer />

          <v-btn
            color="primary"
            variant="flat"
            prepend-icon="mdi-account-plus"
            @click="openAssignDialog()"
          >
            Assign Role
          </v-btn>
        </div>

        <v-divider />

        <v-skeleton-loader
          v-if="loadingAssignments"
          type="table-row,table-row,table-row,table-row"
        />

        <v-table v-else density="comfortable">
          <thead>
            <tr>
              <th>User</th>
              <th>Designation</th>
              <th>Department</th>
              <th>Assigned Roles</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in filteredUsers"
              :key="user.id"
            >
              <td>
                <div class="d-flex align-center ga-2 py-2">
                  <v-avatar size="36" color="primary">
                    <v-img v-if="user.avatar_url" :src="user.avatar_url" />
                    <span v-else class="text-white text-caption font-weight-bold">{{ user.initials }}</span>
                  </v-avatar>

                  <div>
                    <div class="font-weight-medium text-body-2">
                      {{ user.name }}
                      <v-icon
                        v-if="user.is_admin"
                        size="14"
                        color="warning"
                        class="ml-1"
                      >
                        mdi-crown
                      </v-icon>
                    </div>
                    <div class="text-caption text-medium-emphasis">{{ user.email }}</div>
                  </div>
                </div>
              </td>

              <td class="text-body-2">{{ user.designation }}</td>
              <td class="text-body-2">{{ user.department }}</td>

              <td>
                <div class="d-flex flex-wrap ga-1 py-1">
                  <v-chip
                    v-for="role in user.roles"
                    :key="role.id"
                    size="small"
                    :color="role.color"
                    variant="flat"
                    closable
                    @click:close="removeRole(user.id, role.id, user.name, role.name)"
                  >
                    {{ role.name }}
                  </v-chip>

                  <v-chip
                    v-if="user.roles.length === 0"
                    size="small"
                    color="grey"
                    variant="tonal"
                  >
                    No role
                  </v-chip>
                </div>
              </td>

              <td>
                <v-btn
                  size="x-small"
                  variant="tonal"
                  color="primary"
                  prepend-icon="mdi-account-plus"
                  @click="openAssignDialog(user.id)"
                >
                  Add Role
                </v-btn>
              </td>
            </tr>
          </tbody>
        </v-table>
      </v-window-item>
    </v-window>
  </v-card>

  <v-dialog v-model="createRoleDialog" max-width="600" persistent>
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-2">
        <v-icon start color="primary">mdi-shield-plus</v-icon>
        Create New Role
      </v-card-title>

      <v-card-text class="pa-6">
        <v-text-field
          v-model="newRoleForm.name"
          label="Role Name *"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          placeholder="e.g. Finance Manager"
        />

        <v-textarea
          v-model="newRoleForm.description"
          label="Description"
          variant="outlined"
          density="comfortable"
          rows="2"
          class="mb-3"
          placeholder="What can this role do?"
        />

        <div class="mb-4">
          <div class="text-body-2 font-weight-medium mb-2">Role Color</div>
          <div class="d-flex ga-2 flex-wrap">
            <v-btn
              v-for="color in colorOptions"
              :key="color.value"
              :color="color.value"
              icon
              size="small"
              variant="flat"
              :style="{
                outline: newRoleForm.color === color.value ? '3px solid #333' : 'none',
                outlineOffset: '2px',
              }"
              @click="newRoleForm.color = color.value"
            >
              <v-icon
                v-if="newRoleForm.color === color.value"
                size="14"
                color="white"
              >
                mdi-check
              </v-icon>
            </v-btn>
          </div>
        </div>

        <div class="text-body-2 font-weight-medium mb-2">
          Permissions
          <span class="text-primary ml-1">({{ newRoleForm.permission_ids.length }} selected)</span>
        </div>

        <v-expansion-panels variant="accordion" density="compact">
          <v-expansion-panel
            v-for="module in allPermissions"
            :key="module.module"
          >
            <v-expansion-panel-title density="compact">
              <div class="d-flex align-center ga-2">
                <v-checkbox-btn
                  :model-value="createModuleAllSelected(module)"
                  :indeterminate="createModuleSomeSelected(module)"
                  color="primary"
                  @click.stop="toggleCreateModule(module)"
                />
                <span class="font-weight-medium">{{ module.label }}</span>
                <v-chip size="x-small" color="grey" variant="tonal">{{ module.items.length }}</v-chip>
              </div>
            </v-expansion-panel-title>

            <v-expansion-panel-text>
              <div class="d-flex flex-wrap">
                <v-checkbox
                  v-for="permission in module.items"
                  :key="permission.id"
                  v-model="newRoleForm.permission_ids"
                  :value="permission.id"
                  :label="permission.label"
                  color="primary"
                  density="compact"
                  hide-details
                  style="min-width: 200px"
                />
              </div>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </v-card-text>

      <v-card-actions class="pa-6 pt-2">
        <v-btn variant="text" @click="createRoleDialog = false">Cancel</v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!newRoleForm.name"
          @click="createRole"
        >
          Create Role
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="editPermissionsDialog" max-width="680" persistent>
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-2">
        <v-icon start color="primary">mdi-key</v-icon>
        Edit Permissions -
        <span class="text-primary">{{ selectedRole?.name }}</span>
      </v-card-title>

      <v-card-subtitle class="px-6 pb-3">{{ editPermissionIds.length }} permissions selected</v-card-subtitle>

      <v-divider />

      <v-card-text class="pa-4" style="max-height: 500px; overflow-y: auto">
        <v-expansion-panels variant="accordion" multiple>
          <v-expansion-panel
            v-for="module in allPermissions"
            :key="module.module"
          >
            <v-expansion-panel-title>
              <div class="d-flex align-center ga-2 w-100">
                <v-checkbox-btn
                  :model-value="editModuleAllSelected(module)"
                  :indeterminate="editModuleSomeSelected(module)"
                  color="primary"
                  @click.stop="toggleEditModule(module)"
                />

                <span class="font-weight-bold text-body-2">{{ module.label }}</span>

                <v-chip
                  size="x-small"
                  :color="editModuleAllSelected(module) ? 'primary' : 'grey'"
                  variant="tonal"
                >
                  {{ module.items.filter((item: any) => editPermissionIds.includes(item.id)).length }}/{{ module.items.length }}
                </v-chip>
              </div>
            </v-expansion-panel-title>

            <v-expansion-panel-text>
              <div class="d-flex flex-wrap">
                <v-checkbox
                  v-for="permission in module.items"
                  :key="permission.id"
                  :model-value="hasPermission(permission.id)"
                  :label="permission.label"
                  color="primary"
                  density="compact"
                  hide-details
                  style="min-width: 200px"
                  @update:model-value="togglePermission(permission.id)"
                />
              </div>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-btn variant="text" @click="editPermissionsDialog = false">Cancel</v-btn>
        <v-spacer />
        <v-btn color="primary" variant="flat" :loading="saving" @click="savePermissions">Save Permissions</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="assignRoleDialog" max-width="480">
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-2">
        <v-icon start color="primary">mdi-account-key</v-icon>
        Assign Role to User
      </v-card-title>

      <v-card-text class="pa-6">
        <v-autocomplete
          v-model="assignForm.user_id"
          label="Select User *"
          :items="users"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          clearable
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #prepend>
                <v-avatar size="32" color="primary" class="mr-2">
                  <span class="text-white text-caption">{{ item.raw.initials }}</span>
                </v-avatar>
              </template>
              <template #subtitle>
                {{ item.raw.designation }} - {{ item.raw.department }}
              </template>
            </v-list-item>
          </template>
        </v-autocomplete>

        <v-select
          v-model="assignForm.role_id"
          label="Select Role *"
          :items="allRoles"
          item-title="name"
          item-value="id"
          variant="outlined"
          density="comfortable"
        >
          <template #item="{ props, item }">
            <v-list-item v-bind="props">
              <template #prepend>
                <v-avatar :color="item.raw.color" size="28" class="mr-2">
                  <v-icon size="14" color="white">mdi-shield-account</v-icon>
                </v-avatar>
              </template>
            </v-list-item>
          </template>
        </v-select>
      </v-card-text>

      <v-card-actions class="pa-6 pt-0">
        <v-btn variant="text" @click="assignRoleDialog = false">Cancel</v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          :loading="saving"
          :disabled="!assignForm.user_id || !assignForm.role_id"
          @click="assignRole"
        >
          Assign Role
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDeleteDialog" max-width="400">
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-2">
        <v-icon start color="error">mdi-delete-alert</v-icon>
        Delete Role
      </v-card-title>

      <v-card-text class="pa-6 pt-2">
        Are you sure you want to delete
        <strong>{{ roleToDelete?.name }}</strong>?
        This cannot be undone.
      </v-card-text>

      <v-card-actions class="pa-6 pt-0">
        <v-btn variant="text" @click="confirmDeleteDialog = false">Cancel</v-btn>
        <v-spacer />
        <v-btn color="error" variant="flat" @click="deleteRole">Delete Role</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    timeout="3000"
    location="bottom right"
  >
    {{ snackbar.message }}
  </v-snackbar>
  </template>
</template>


