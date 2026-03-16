<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

type DashboardSummary = {
  on_leave: number
  pending_approvals: number
  open_positions: number
}

type AuthUser = {
  id: number
  name: string
  email: string
  employee_id?: number | null
  avatar_url?: string | null
}

const page = usePage();
const isLoading = ref(true);
const summary = ref<DashboardSummary>({
  on_leave: 0,
  pending_approvals: 0,
  open_positions: 0
});

const authUser = computed<AuthUser | null>(() => ((page.props as any)?.auth?.user as AuthUser) || null);
const userFullName = computed(() => authUser.value?.name || 'Team Lead');
const userInitials = computed(() =>
  userFullName.value
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('') || 'HR'
);

const profileAvatarUrl = ref<string | null>(null);
const resolvedEmployeeId = ref<number | null>(null);
const avatarLoadFailed = ref(false);
const avatarUploading = ref(false);
const avatarDialog = ref(false);
const avatarInputRef = ref<HTMLInputElement | null>(null);
const snackbar = ref({
  show: false,
  message: '',
  color: 'success' as 'success' | 'error' | 'warning'
});

const todayLabel = new Intl.DateTimeFormat('en-US', {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  year: 'numeric'
}).format(new Date());

function showSnackbar(message: string, color: 'success' | 'error' | 'warning' = 'success') {
  snackbar.value = { show: true, message, color };
}

function onAvatarError() {
  avatarLoadFailed.value = true;
}

function openAvatarDialog() {
  if (avatarUploading.value) {
    return;
  }

  avatarDialog.value = true;
}

function triggerAvatarPicker() {
  if (avatarUploading.value) {
    return;
  }

  avatarDialog.value = false;
  avatarInputRef.value?.click();
}

async function onAvatarSelected(event: Event) {
  const target = event.target as HTMLInputElement;
  const file = target.files?.[0];
  const employeeId = resolvedEmployeeId.value ?? authUser.value?.employee_id ?? null;
  const uploadUrl = employeeId ? `/api/hr/employees/${employeeId}/avatar` : '/api/hr/profile/avatar';

  if (!file) {
    target.value = '';
    return;
  }

  avatarUploading.value = true;

  try {
    const formData = new FormData();
    formData.append('avatar', file);

    const { data } = await axios.post(uploadUrl, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    if (typeof data?.employee_id === 'number') {
      resolvedEmployeeId.value = data.employee_id;
    }
    profileAvatarUrl.value = data?.avatar_url ?? null;
    avatarLoadFailed.value = false;
    showSnackbar(data?.message ?? 'Profile photo updated.');
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to upload profile photo.', 'error');
  } finally {
    avatarUploading.value = false;
    target.value = '';
  }
}

async function fetchSummary() {
  isLoading.value = true;

  try {
    const { data } = await axios.get('/api/hr/dashboard/summary');
    summary.value = {
      on_leave: Number(data?.on_leave ?? 0),
      pending_approvals: Number(data?.pending_approvals ?? 0),
      open_positions: Number(data?.open_positions ?? 0)
    };
  } catch (error) {
    console.error('Summary fetch failed', error);
    summary.value = {
      on_leave: 0,
      pending_approvals: 0,
      open_positions: 0
    };
  } finally {
    isLoading.value = false;
  }
}

onMounted(() => {
  resolvedEmployeeId.value = authUser.value?.employee_id ?? null;
  profileAvatarUrl.value = authUser.value?.avatar_url ?? null;
  fetchSummary();
});
</script>

<template>
  <v-skeleton-loader v-if="isLoading" type="article" class="rounded-lg" />

  <v-card v-else class="hr-welcome-banner text-surface overflow-hidden" elevation="0" rounded="lg">
    <v-card-text class="py-6 px-md-12 px-6">
      <v-row class="align-center">
        <v-col cols="12" md="8">
          <h2 class="text-sm-h2 text-h3 mb-2">Welcome back, {{ userFullName }} &#128075;</h2>
          <p class="text-h6 mb-5">{{ todayLabel }}</p>

          <div class="d-flex flex-wrap ga-3 mb-6">
            <v-chip color="warning" variant="flat" rounded="pill" class="cursor-pointer" @click="router.visit('/hr/leave-management')">
              {{ summary.on_leave }} Employees on Leave Today
            </v-chip>
            <v-chip color="info" variant="flat" rounded="pill" class="cursor-pointer" @click="router.visit('/hr/leave-management')">
              {{ summary.pending_approvals }} Pending Approvals
            </v-chip>
            <v-chip color="success" variant="flat" rounded="pill" class="cursor-pointer" @click="router.visit('/hr/job-openings')">
              {{ summary.open_positions }} Open Job Positions
            </v-chip>
          </div>

          <v-btn color="white" class="text-none" variant="outlined" rounded="md" @click="router.visit('/hr/employees')">
            View HR Overview ->
          </v-btn>
        </v-col>

        <v-col cols="12" md="4" class="d-flex justify-center justify-md-end">
          <div class="workforce-circle">
            <v-tooltip text="Click to update profile photo" location="top">
              <template #activator="{ props }">
                <v-avatar
                  v-bind="props"
                  size="150"
                  class="welcome-avatar"
                  color="white"
                  variant="tonal"
                  role="button"
                  tabindex="0"
                  @click="openAvatarDialog"
                >
                  <v-img
                    v-if="profileAvatarUrl && !avatarLoadFailed"
                    :src="profileAvatarUrl || ''"
                    :alt="`${userFullName} profile photo`"
                    cover
                    @error="onAvatarError"
                  />
                  <span v-else class="text-h2 font-weight-bold text-white">{{ userInitials }}</span>
                </v-avatar>
              </template>
            </v-tooltip>

            <v-progress-circular
              v-if="avatarUploading"
              class="avatar-loader"
              indeterminate
              color="white"
              size="36"
              width="4"
            />

            <input
              ref="avatarInputRef"
              type="file"
              class="d-none"
              accept="image/png,image/jpeg,image/webp"
              @change="onAvatarSelected"
            />
          </div>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-dialog v-model="avatarDialog" max-width="420">
    <v-card rounded="lg">
      <v-card-title class="text-h6">Update Profile Picture</v-card-title>
      <v-card-text>
        <p class="text-body-2 text-medium-emphasis mb-4">Select a new photo for your avatar.</p>
        <div class="d-flex justify-center mb-2">
          <v-avatar size="96" class="dialog-avatar-preview" color="primary" variant="tonal">
            <v-img
              v-if="profileAvatarUrl && !avatarLoadFailed"
              :src="profileAvatarUrl || ''"
              :alt="`${userFullName} profile photo preview`"
              cover
            />
            <span v-else class="text-h5 font-weight-bold">{{ userInitials }}</span>
          </v-avatar>
        </div>
      </v-card-text>
      <v-card-actions class="justify-end pb-4 px-4">
        <v-btn variant="text" :disabled="avatarUploading" @click="avatarDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="avatarUploading" @click="triggerAvatarPicker">Choose Picture</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3200">
    {{ snackbar.message }}
  </v-snackbar>
</template>

<style lang="scss">
.hr-welcome-banner {
  background: linear-gradient(120deg, #4f6ef7 0%, #6a3de8 100%);
}

.workforce-circle {
  width: 180px;
  height: 180px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.18);
  border: 1px solid rgba(255, 255, 255, 0.28);
  position: relative;
}

.welcome-avatar {
  cursor: pointer;
  border: 2px solid rgba(255, 255, 255, 0.42);
  background: rgba(255, 255, 255, 0.12);
}

.dialog-avatar-preview {
  border: 1px solid rgba(0, 0, 0, 0.08);
}

.avatar-loader {
  position: absolute;
  z-index: 2;
}

.cursor-pointer {
  cursor: pointer;
}
</style>
