<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

type Announcement = {
  id: number
  title: string
  body: string
  type: string
  type_icon: string
  priority: string
  priority_color: string
  audience: string
  author: string
  published_at: string | null
  expires_at: string | null
  time_ago: string | null
};

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Communications', disabled: false, href: '#' },
  { title: 'Announcements', disabled: true, href: '#' },
];

const loading = ref(false);
const creating = ref(false);
const dialog = ref(false);
const filterType = ref('');
const announcements = ref<Announcement[]>([]);
const departments = ref<Array<{ id: number; label: string; sublabel?: string }>>([]);
const employees = ref<Array<{ id: number; label: string; sublabel?: string }>>([]);

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
});

const form = ref({
  title: '',
  body: '',
  type: 'general',
  audience: 'all',
  target_departments: [] as number[],
  target_employees: [] as number[],
  priority: 'normal',
  send_email: false,
  send_notification: true,
  expires_at: '',
});

function showSnack(message: string, color: 'success' | 'error' = 'success') {
  snackbar.value = { show: true, message, color };
}

function resetForm() {
  form.value = {
    title: '',
    body: '',
    type: 'general',
    audience: 'all',
    target_departments: [],
    target_employees: [],
    priority: 'normal',
    send_email: false,
    send_notification: true,
    expires_at: '',
  };
}

async function fetchAnnouncements() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/announcements', {
      params: {
        type: filterType.value || undefined,
      },
    });
    announcements.value = data.announcements?.data ?? [];
  } catch {
    showSnack('Failed to load announcements.', 'error');
  } finally {
    loading.value = false;
  }
}

async function fetchRecipients() {
  try {
    const { data } = await axios.get('/api/hr/messages/recipients');
    departments.value = data.departments ?? [];
    employees.value = data.employees ?? [];
  } catch {
    departments.value = [];
    employees.value = [];
  }
}

async function submitAnnouncement(publishNow: boolean) {
  creating.value = true;
  try {
    await axios.post('/api/hr/announcements', {
      ...form.value,
      expires_at: form.value.expires_at || null,
      publish_now: publishNow,
    });

    showSnack(publishNow ? 'Announcement published.' : 'Draft saved.');
    dialog.value = false;
    resetForm();
    await fetchAnnouncements();
  } catch (error: any) {
    showSnack(error?.response?.data?.message ?? 'Failed to save announcement.', 'error');
  } finally {
    creating.value = false;
  }
}

watch(filterType, fetchAnnouncements);

onMounted(async () => {
  await Promise.all([fetchAnnouncements(), fetchRecipients()]);
});
</script>

<template>
  <BaseBreadcrumb
    title="Announcements"
    subtitle="Company-wide HR broadcasts"
    :breadcrumbs="breadcrumbs"
  />

  <div class="d-flex align-center justify-space-between mb-4">
    <div>
      <h2 class="text-h3 mb-1">Announcements</h2>
      <p class="text-subtitle-1 text-lightText mb-0">
        Publish notices, events, and policy updates.
      </p>
    </div>

    <v-btn
      color="primary"
      variant="flat"
      prepend-icon="mdi-bullhorn"
      @click="dialog = true"
    >
      New Announcement
    </v-btn>
  </div>

  <v-card variant="outlined" class="rounded-lg mb-4">
    <v-card-text class="d-flex flex-wrap ga-3">
      <v-select
        v-model="filterType"
        :items="[
          { title: 'All Types', value: '' },
          { title: 'General', value: 'general' },
          { title: 'Urgent', value: 'urgent' },
          { title: 'Event', value: 'event' },
          { title: 'Policy', value: 'policy' },
        ]"
        label="Filter by Type"
        hide-details
        density="comfortable"
        variant="outlined"
        style="max-width: 280px"
      />
    </v-card-text>
  </v-card>

  <v-row>
    <v-col v-if="loading" cols="12">
      <v-skeleton-loader type="article,article,article" />
    </v-col>

    <v-col
      v-for="announcement in announcements"
      v-else
      :key="announcement.id"
      cols="12"
      md="6"
    >
      <v-card variant="outlined" class="rounded-lg h-100">
        <v-card-text>
          <div class="d-flex align-start justify-space-between">
            <div class="d-flex align-center ga-2 mb-3">
              <v-icon :color="announcement.priority_color">
                {{ announcement.type_icon }}
              </v-icon>
              <div class="text-subtitle-1 font-weight-bold">
                {{ announcement.title }}
              </div>
            </div>
            <v-chip
              size="small"
              :color="announcement.priority_color"
              variant="tonal"
            >
              {{ announcement.priority }}
            </v-chip>
          </div>

          <div class="text-body-2 mb-3" style="white-space: pre-wrap">
            {{ announcement.body }}
          </div>

          <div class="d-flex flex-wrap ga-2">
            <v-chip size="x-small" color="primary" variant="tonal">
              {{ announcement.type }}
            </v-chip>
            <v-chip size="x-small" color="secondary" variant="tonal">
              Audience: {{ announcement.audience }}
            </v-chip>
            <v-chip
              v-if="announcement.expires_at"
              size="x-small"
              color="warning"
              variant="tonal"
            >
              Expires {{ announcement.expires_at }}
            </v-chip>
          </div>
        </v-card-text>

        <v-divider />

        <v-card-actions class="px-4 py-2">
          <span class="text-caption text-medium-emphasis">
            {{ announcement.author }} · {{ announcement.published_at || announcement.time_ago }}
          </span>
        </v-card-actions>
      </v-card>
    </v-col>
  </v-row>

  <v-card v-if="!loading && announcements.length === 0" variant="outlined" class="rounded-lg">
    <v-card-text class="text-center py-12 text-medium-emphasis">
      <v-icon size="56" color="grey-lighten-2">mdi-bullhorn-outline</v-icon>
      <div class="mt-3">No announcements yet.</div>
    </v-card-text>
  </v-card>

  <v-dialog v-model="dialog" max-width="840">
    <v-card>
      <v-card-title>New Announcement</v-card-title>
      <v-divider />

      <v-card-text class="pt-4">
        <v-row>
          <v-col cols="12" md="8">
            <v-text-field v-model="form.title" label="Title" variant="outlined" />
          </v-col>
          <v-col cols="12" md="4">
            <v-select
              v-model="form.type"
              label="Type"
              variant="outlined"
              :items="[
                { title: 'General', value: 'general' },
                { title: 'Urgent', value: 'urgent' },
                { title: 'Event', value: 'event' },
                { title: 'Policy', value: 'policy' },
              ]"
            />
          </v-col>
        </v-row>

        <v-textarea
          v-model="form.body"
          label="Announcement Body"
          variant="outlined"
          rows="6"
          auto-grow
          class="mb-3"
        />

        <v-row>
          <v-col cols="12" md="4">
            <v-select
              v-model="form.audience"
              label="Audience"
              variant="outlined"
              :items="[
                { title: 'All Employees', value: 'all' },
                { title: 'Department', value: 'department' },
                { title: 'Specific Employees', value: 'specific' },
              ]"
            />
          </v-col>

          <v-col cols="12" md="4" v-if="form.audience === 'department'">
            <v-select
              v-model="form.target_departments"
              :items="departments.map((item) => ({ title: item.label, value: item.id }))"
              label="Departments"
              multiple
              chips
              variant="outlined"
            />
          </v-col>

          <v-col cols="12" md="4" v-if="form.audience === 'specific'">
            <v-select
              v-model="form.target_employees"
              :items="employees.map((item) => ({ title: item.label, value: item.id }))"
              label="Employees"
              multiple
              chips
              variant="outlined"
            />
          </v-col>

          <v-col cols="12" md="4">
            <v-select
              v-model="form.priority"
              label="Priority"
              variant="outlined"
              :items="[
                { title: 'Normal', value: 'normal' },
                { title: 'High', value: 'high' },
                { title: 'Urgent', value: 'urgent' },
              ]"
            />
          </v-col>
        </v-row>

        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="form.expires_at"
              type="date"
              label="Expiry Date"
              variant="outlined"
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-switch
              v-model="form.send_email"
              label="Send Email"
              color="primary"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-switch
              v-model="form.send_notification"
              label="Send In-App Notification"
              color="primary"
              hide-details
            />
          </v-col>
        </v-row>
      </v-card-text>

      <v-divider />

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Cancel</v-btn>
        <v-btn
          variant="tonal"
          color="secondary"
          :loading="creating"
          @click="submitAnnouncement(false)"
        >
          Save Draft
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          prepend-icon="mdi-send"
          :loading="creating"
          @click="submitAnnouncement(true)"
        >
          Publish Now
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>
