<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'

const props = defineProps<{
  id: number
}>()

const loading = ref(false)
const role = ref<any>(null)
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

function showSnack(message: string, color = 'success') {
  snackbar.value = { show: true, message, color }
}

async function fetchRole() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/hr/roles/' + props.id)
    role.value = data.role ?? null
  } catch {
    showSnack('Failed to load role details.', 'error')
  } finally {
    loading.value = false
  }
}

const moduleCount = computed(() =>
  role.value?.permissions?.length ?? 0
)

onMounted(fetchRole)
</script>

<template>
  <div class="d-flex align-center justify-space-between mb-6 flex-wrap ga-3">
    <div>
      <h1 class="text-h5 font-weight-bold">
        {{ role?.name ?? 'Role Details' }}
      </h1>
      <p class="text-body-2 text-medium-emphasis mt-1">
        View permissions and assigned users.
      </p>
    </div>

    <div class="d-flex ga-2">
      <v-btn
        variant="tonal"
        color="primary"
        prepend-icon="mdi-arrow-left"
        @click="router.visit('/hr/roles-permissions')"
      >
        Back to Roles
      </v-btn>
    </div>
  </div>

  <v-skeleton-loader
    v-if="loading"
    type="table-heading, table-row, table-row, table-row"
  />

  <template v-else-if="role">
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <div class="text-h4 font-weight-bold" :class="'text-' + role.color">
            {{ role.permission_count }}
          </div>
          <div class="text-body-2 text-medium-emphasis">Permissions</div>
        </v-card>
      </v-col>

      <v-col cols="12" md="3">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <div class="text-h4 font-weight-bold text-primary">{{ moduleCount }}</div>
          <div class="text-body-2 text-medium-emphasis">Modules</div>
        </v-card>
      </v-col>

      <v-col cols="12" md="3">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <div class="text-h4 font-weight-bold text-success">{{ role.users_count }}</div>
          <div class="text-body-2 text-medium-emphasis">Assigned Users</div>
        </v-card>
      </v-col>

      <v-col cols="12" md="3">
        <v-card rounded="lg" elevation="0" border class="pa-4">
          <v-chip
            :color="role.is_system ? 'primary' : 'warning'"
            variant="tonal"
            size="small"
          >
            {{ role.is_system ? 'System Role' : 'Custom Role' }}
          </v-chip>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="7">
        <v-card rounded="lg" elevation="0" border>
          <div class="pa-4 d-flex align-center justify-space-between">
            <div class="text-subtitle-1 font-weight-bold">Permissions</div>
            <v-chip size="small" color="primary" variant="tonal">
              {{ role.permission_count }} total
            </v-chip>
          </div>

          <v-divider />

          <v-expansion-panels multiple>
            <v-expansion-panel
              v-for="module in role.permissions"
              :key="module.module"
            >
              <v-expansion-panel-title>
                <div class="d-flex align-center ga-2">
                  <span class="font-weight-medium">{{ module.label }}</span>
                  <v-chip size="x-small" variant="tonal" color="grey">
                    {{ module.items.length }}
                  </v-chip>
                </div>
              </v-expansion-panel-title>

              <v-expansion-panel-text>
                <div class="d-flex flex-wrap ga-2">
                  <v-chip
                    v-for="permission in module.items"
                    :key="permission.id"
                    size="small"
                    :color="role.color"
                    variant="tonal"
                  >
                    {{ permission.label }}
                  </v-chip>
                </div>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card>
      </v-col>

      <v-col cols="12" md="5">
        <v-card rounded="lg" elevation="0" border>
          <div class="pa-4 d-flex align-center justify-space-between">
            <div class="text-subtitle-1 font-weight-bold">Users With This Role</div>
            <v-chip size="small" color="success" variant="tonal">
              {{ role.users_count }}
            </v-chip>
          </div>

          <v-divider />

          <v-list density="compact" class="py-1">
            <v-list-item
              v-for="user in role.users"
              :key="user.id"
              rounded="lg"
              class="mx-2 my-1"
            >
              <template #prepend>
                <v-avatar size="34" color="primary" class="mr-2">
                  <v-img v-if="user.avatar_url" :src="user.avatar_url" />
                  <span v-else class="text-white text-caption font-weight-bold">
                    {{ user.initials }}
                  </span>
                </v-avatar>
              </template>

              <v-list-item-title class="font-weight-medium">
                {{ user.name }}
              </v-list-item-title>
              <v-list-item-subtitle>
                {{ user.designation }} · {{ user.department }}
              </v-list-item-subtitle>

              <template #append>
                <v-btn
                  v-if="user.employee_id"
                  icon
                  size="x-small"
                  variant="text"
                  @click="router.visit('/hr/employees/' + user.employee_id)"
                >
                  <v-icon size="16">mdi-open-in-new</v-icon>
                </v-btn>
              </template>
            </v-list-item>

            <div
              v-if="role.users.length === 0"
              class="text-center py-8 text-medium-emphasis"
            >
              No users assigned to this role.
            </div>
          </v-list>
        </v-card>
      </v-col>
    </v-row>
  </template>

  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    timeout="3000"
    location="bottom right"
  >
    {{ snackbar.message }}
  </v-snackbar>
</template>
