<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

type UserSettings = {
  language?: string | null
  timezone?: string | null
  currency?: string | null
  email_notifications?: boolean | null
  sms_notifications?: boolean | null
  desktop_notifications?: boolean | null
  two_factor_enabled?: boolean | null
  show_email?: boolean | null
  show_phone?: boolean | null
  show_email_to_colleagues?: boolean | null
  show_phone_to_colleagues?: boolean | null
};

type AuthUser = {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  avatar?: string | null
  initials?: string
  designation?: string | null
  department?: string | null
  employee_id?: number | null
  employee_code?: string | null
  phone?: string | null
  status?: string | null
  joined?: string | null
  roles?: string[]
  is_admin?: boolean
  has_socials?: boolean
  settings?: UserSettings | null
};

const page = usePage();
const activeTab = ref('profile');
const loggingOut = ref(false);
const avatarInput = ref<HTMLInputElement | null>(null);
const uploadingAvatar = ref(false);

const savingAccount = ref(false);
const savingPassword = ref(false);
const savingNotifications = ref(false);
const savingPrivacy = ref(false);

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
});

const languageOptions = [
  { title: 'English', value: 'en' },
  { title: 'French', value: 'fr' },
  { title: 'Spanish', value: 'es' },
];

const timezoneOptions = ['UTC', 'Africa/Accra', 'Africa/Lagos', 'Europe/London', 'America/New_York'];
const currencyOptions = ['GHS', 'USD', 'EUR', 'GBP', 'NGN'];

const authUser = computed<AuthUser | null>(() => ((page.props as any)?.auth?.user as AuthUser) ?? null);
const user = authUser;
const avatarUrl = computed(() => user.value?.avatar_url ?? user.value?.avatar ?? null);
const primaryRole = computed(() => user.value?.roles?.[0] ?? '');
const displayDesignation = computed(() => user.value?.designation || primaryRole.value || '');
const displayEmployeeCode = computed(() => user.value?.employee_code || '');
const displayStatus = computed(() => user.value?.status || 'Active');
const displayJoined = computed(() => user.value?.joined || '');
const hasSocialLinks = computed(() => Boolean(user.value?.has_socials));

const accountForm = ref({
  name: '',
  email: '',
  language: 'en',
  timezone: 'UTC',
  currency: 'GHS',
});

const passwordForm = ref({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const notificationsForm = ref({
  email_notifications: true,
  sms_notifications: false,
  desktop_notifications: false,
});

const privacyForm = ref({
  two_factor_enabled: false,
  show_email: false,
  show_phone: false,
});

watch(
  user,
  (value) => {
    if (!value) {
      return;
    }

    const settings = value.settings ?? {};

    accountForm.value = {
      name: value.name ?? '',
      email: value.email ?? '',
      language: settings.language ?? 'en',
      timezone: settings.timezone ?? 'UTC',
      currency: settings.currency ?? 'GHS',
    };

    notificationsForm.value = {
      email_notifications: Boolean(settings.email_notifications ?? true),
      sms_notifications: Boolean(settings.sms_notifications ?? false),
      desktop_notifications: Boolean(settings.desktop_notifications ?? false),
    };

    privacyForm.value = {
      two_factor_enabled: Boolean(settings.two_factor_enabled ?? false),
      show_email: Boolean(settings.show_email ?? settings.show_email_to_colleagues ?? false),
      show_phone: Boolean(settings.show_phone ?? settings.show_phone_to_colleagues ?? false),
    };
  },
  { immediate: true }
);

function showSnack(message: string, color: 'success' | 'error' = 'success') {
  snackbar.value = {
    show: true,
    message,
    color,
  };
}

function getErrorMessage(error: any, fallback: string): string {
  const serverMessage = error?.response?.data?.message;
  if (typeof serverMessage === 'string' && serverMessage.trim() !== '') {
    return serverMessage;
  }

  const errors = error?.response?.data?.errors;
  if (errors && typeof errors === 'object') {
    const first = Object.values(errors)[0] as string[] | undefined;
    if (Array.isArray(first) && first[0]) {
      return first[0];
    }
  }

  return fallback;
}

function logout() {
  loggingOut.value = true;
  router.post('/logout', {}, {
    onFinish: () => {
      loggingOut.value = false;
    },
  });
}

function triggerAvatarUpload() {
  avatarInput.value?.click();
}

async function handleAvatarChange(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  if (!file) {
    return;
  }

  uploadingAvatar.value = true;
  const formData = new FormData();
  formData.append('avatar', file);

  try {
    await axios.post('/api/profile/avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    showSnack('Settings saved successfully', 'success');
    router.reload({ only: ['auth'] });
  } catch (error: any) {
    showSnack(getErrorMessage(error, 'Failed to upload photo.'), 'error');
  } finally {
    uploadingAvatar.value = false;
    if (avatarInput.value) {
      avatarInput.value.value = '';
    }
  }
}

function goToEditProfile() {
  const employeeId = Number(authUser.value?.employee_id ?? 0);
  if (employeeId) {
    router.visit('/hr/employees/' + employeeId + '/edit');
    return;
  }
  router.visit('/profile/settings');
}

function goToViewProfile() {
  const employeeId = Number(authUser.value?.employee_id ?? 0);
  if (employeeId) {
    router.visit('/hr/employees/' + employeeId);
    return;
  }
  router.visit('/profile/settings');
}

function goToSocialProfile() {
  router.visit('/profile/social');
}

async function saveAccountSettings() {
  savingAccount.value = true;
  try {
    await axios.post('/api/account/update', {
      name: accountForm.value.name,
      email: accountForm.value.email,
      language: accountForm.value.language,
      timezone: accountForm.value.timezone,
      currency: accountForm.value.currency,
    });
    showSnack('Settings saved successfully', 'success');
    router.reload({ only: ['auth'] });
  } catch (error: any) {
    showSnack(getErrorMessage(error, 'Failed to save account settings.'), 'error');
  } finally {
    savingAccount.value = false;
  }
}

async function changePassword() {
  savingPassword.value = true;
  try {
    await axios.post('/api/account/password', passwordForm.value);
    showSnack('Settings saved successfully', 'success');
    passwordForm.value = {
      current_password: '',
      password: '',
      password_confirmation: '',
    };
    router.reload({ only: ['auth'] });
  } catch (error: any) {
    showSnack(getErrorMessage(error, 'Failed to change password.'), 'error');
  } finally {
    savingPassword.value = false;
  }
}

async function saveNotificationPreferences() {
  savingNotifications.value = true;
  try {
    await axios.post('/api/account/notifications', notificationsForm.value);
    showSnack('Settings saved successfully', 'success');
    router.reload({ only: ['auth'] });
  } catch (error: any) {
    showSnack(getErrorMessage(error, 'Failed to save notification preferences.'), 'error');
  } finally {
    savingNotifications.value = false;
  }
}

async function savePrivacySettings() {
  savingPrivacy.value = true;
  try {
    await axios.post('/api/account/privacy', privacyForm.value);
    showSnack('Settings saved successfully', 'success');
    router.reload({ only: ['auth'] });
  } catch (error: any) {
    showSnack(getErrorMessage(error, 'Failed to save privacy settings.'), 'error');
  } finally {
    savingPrivacy.value = false;
  }
}
</script>

<template>
  <v-card min-width="340" max-width="380" rounded="xl" elevation="12">
    <div class="pa-4 pb-2">
      <div class="d-flex align-start justify-space-between ga-2">
        <div class="d-flex align-start ga-3">
          <div style="position: relative; display: inline-block">
            <v-avatar
              size="52"
              color="primary"
              style="border: 2px solid #e8ecff; cursor: pointer"
              @click="triggerAvatarUpload"
            >
              <v-img v-if="avatarUrl" :src="avatarUrl" cover />
              <span v-else class="text-white font-weight-bold">
                {{ user?.initials ?? 'U' }}
              </span>
            </v-avatar>

            <v-btn
              icon
              size="x-small"
              color="primary"
              variant="flat"
              style="position: absolute; bottom: -2px; right: -2px; width: 20px; height: 20px; border: 2px solid white; border-radius: 50%; min-width: unset"
              :loading="uploadingAvatar"
              @click="triggerAvatarUpload"
            >
              <v-icon size="10">mdi-pencil</v-icon>
            </v-btn>

            <input
              ref="avatarInput"
              type="file"
              accept="image/*"
              style="display: none"
              @change="handleAvatarChange"
            >
          </div>

          <div>
            <div class="text-body-2 font-weight-bold text-grey-darken-3 d-flex align-center ga-1">
              <span>{{ user?.name ?? 'User' }}</span>
              <v-icon v-if="user?.is_admin" size="14" color="warning">mdi-crown</v-icon>
            </div>

            <div v-if="displayDesignation" class="text-caption text-medium-emphasis">
              {{ displayDesignation }}
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex ga-2 mt-3 flex-wrap">
        <v-chip v-if="displayEmployeeCode" size="x-small" color="primary" variant="tonal">
          {{ displayEmployeeCode }}
        </v-chip>
        <v-chip size="x-small" :color="displayStatus === 'Active' ? 'success' : 'warning'" variant="tonal">
          {{ displayStatus }}
        </v-chip>
        <v-chip v-if="displayJoined" size="x-small" color="grey" variant="tonal">
          {{ displayJoined }}
        </v-chip>
      </div>
    </div>

    <v-tabs v-model="activeTab" density="compact" color="primary" class="px-2">
      <v-tab value="profile">
        <v-icon start size="15">mdi-account-outline</v-icon>
        Profile
      </v-tab>
      <v-tab value="setting">
        <v-icon start size="15">mdi-cog-outline</v-icon>
        Setting
      </v-tab>
    </v-tabs>

    <v-divider />

    <v-window v-model="activeTab">
      <v-window-item value="profile">
        <div class="px-4 pt-3 pb-2">
          <div class="d-flex align-center ga-2 text-body-2 mb-2">
            <v-icon size="16" color="medium-emphasis">mdi-email-outline</v-icon>
            <span>{{ user?.email }}</span>
          </div>
          <div
            v-if="user?.phone && user.phone !== '' && user.phone !== null"
            class="d-flex align-center ga-2 text-body-2 mb-2"
          >
            <v-icon size="16" color="medium-emphasis">mdi-phone-outline</v-icon>
            <span>{{ user.phone }}</span>
          </div>
          <v-chip
            v-if="authUser?.department && authUser.department !== '-' && authUser.department !== null"
            size="x-small"
            color="secondary"
            variant="tonal"
            class="mb-4"
          >
            {{ authUser.department }}
          </v-chip>
        </div>

        <v-divider class="my-2 mx-4" />

        <v-list density="compact" class="py-1">
          <v-list-item
            prepend-icon="mdi-account-edit"
            title="Edit Profile"
            subtitle="Update your information"
            rounded="lg"
            class="mx-2"
            @click="goToEditProfile()"
          />

          <v-list-item
            prepend-icon="mdi-account"
            title="View Profile"
            subtitle="See your HR profile"
            rounded="lg"
            class="mx-2"
            @click="goToViewProfile()"
          />

          <v-list-item
            prepend-icon="mdi-web"
            title="Social Profile"
            subtitle="LinkedIn, GitHub & more"
            rounded="lg"
            class="mx-2"
            @click="goToSocialProfile()"
          >
            <template #append>
              <v-icon v-if="hasSocialLinks" size="8" color="success">mdi-circle</v-icon>
            </template>
          </v-list-item>

          <v-list-item
            prepend-icon="mdi-logout"
            title="Logout"
            rounded="lg"
            class="mx-2"
            base-color="error"
            :disabled="loggingOut"
            @click="logout()"
          >
            <template #append>
              <v-progress-circular
                v-if="loggingOut"
                size="14"
                width="2"
                indeterminate
                color="error"
              />
            </template>
          </v-list-item>
        </v-list>
      </v-window-item>

      <v-window-item value="setting">
        <div style="max-height: 68vh; overflow-y: auto" class="pa-3">
          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            ACCOUNT
          </div>

          <v-text-field
            v-model="accountForm.name"
            label="Full Name"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-text-field
            v-model="accountForm.email"
            label="Email"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-select
            v-model="accountForm.language"
            label="Language"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
            :items="languageOptions"
          />
          <v-select
            v-model="accountForm.timezone"
            label="Timezone"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
            :items="timezoneOptions"
          />
          <v-select
            v-model="accountForm.currency"
            label="Currency"
            variant="outlined"
            density="compact"
            class="mb-3"
            hide-details
            :items="currencyOptions"
          />
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            block
            :loading="savingAccount"
            @click="saveAccountSettings"
          >
            Save Account Settings
          </v-btn>

          <v-divider class="my-4" />

          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            CHANGE PASSWORD
          </div>
          <v-text-field
            v-model="passwordForm.current_password"
            label="Current Password"
            type="password"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-text-field
            v-model="passwordForm.password"
            label="New Password"
            type="password"
            variant="outlined"
            density="compact"
            class="mb-2"
            hide-details
          />
          <v-text-field
            v-model="passwordForm.password_confirmation"
            label="Confirm Password"
            type="password"
            variant="outlined"
            density="compact"
            class="mb-3"
            hide-details
          />
          <v-btn
            color="warning"
            variant="flat"
            size="small"
            block
            :loading="savingPassword"
            @click="changePassword"
          >
            Change Password
          </v-btn>

          <v-divider class="my-4" />

          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            NOTIFICATIONS
          </div>
          <v-switch
            v-model="notificationsForm.email_notifications"
            label="Email Notifications"
            color="primary"
            density="compact"
            hide-details
            class="mb-1"
          />
          <v-switch
            v-model="notificationsForm.sms_notifications"
            label="SMS Notifications"
            color="primary"
            density="compact"
            hide-details
            class="mb-1"
          />
          <v-switch
            v-model="notificationsForm.desktop_notifications"
            label="Desktop Notifications"
            color="primary"
            density="compact"
            hide-details
            class="mb-3"
          />
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            block
            :loading="savingNotifications"
            @click="saveNotificationPreferences"
          >
            Save Notification Preferences
          </v-btn>

          <v-divider class="my-4" />

          <div class="text-caption font-weight-bold text-medium-emphasis mb-2">
            PRIVACY & SECURITY
          </div>
          <v-switch
            v-model="privacyForm.two_factor_enabled"
            label="Two-Factor Authentication"
            color="primary"
            density="compact"
            hide-details
            class="mb-1"
          />
          <v-switch
            v-model="privacyForm.show_email"
            label="Show Email"
            color="primary"
            density="compact"
            hide-details
            class="mb-1"
          />
          <v-switch
            v-model="privacyForm.show_phone"
            label="Show Phone"
            color="primary"
            density="compact"
            hide-details
            class="mb-3"
          />
          <v-btn
            color="primary"
            variant="flat"
            size="small"
            block
            :loading="savingPrivacy"
            @click="savePrivacySettings"
          >
            Save Privacy Settings
          </v-btn>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    timeout="3000"
    location="bottom right"
  >
    {{ snackbar.message }}
  </v-snackbar>
</template>
