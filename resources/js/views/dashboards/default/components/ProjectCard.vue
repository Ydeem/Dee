<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import CardHeader from '@/components/shared/CardHeader.vue';

type PendingAction = {
  title: string;
  subtitle: string;
  status: 'Pending' | 'In Progress' | 'Done' | string;
};

type PendingActionsResponse = {
  completed_tasks?: number;
  total_tasks?: number;
  tasks_completed?: number;
  tasks_total?: number;
  actions?: PendingAction[];
  items?: PendingAction[];
};

const isLoading = ref(true);
const showRequestDialog = ref(false);
const requestType = ref<string | null>(null);

const actions = ref<PendingAction[]>([
  {
    title: 'Leave Request',
    subtitle: 'John Mensah - Mar 11-13',
    status: 'Pending'
  },
  {
    title: 'Onboarding',
    subtitle: 'New Hire: Sarah Oti - Starts Mar 15',
    status: 'In Progress'
  },
  {
    title: 'Performance Review',
    subtitle: 'Engineering Dept - Due Mar 20',
    status: 'In Progress'
  },
  {
    title: 'Payroll Approval',
    subtitle: 'March 2026 Payroll - Approved',
    status: 'Done'
  },
  {
    title: 'Exit Interview',
    subtitle: 'Emmanuel Doe - Mar 12',
    status: 'Pending'
  }
]);

const completedTasks = ref(4);
const totalTasks = ref(9);

const requestTypes = ['Leave', 'Onboarding', 'Performance Review', 'Payroll', 'Other'];

const completionPercent = computed(() => {
  if (!totalTasks.value) return 0;
  return Math.round((completedTasks.value / totalTasks.value) * 100);
});

function statusColor(status: string) {
  if (status === 'Done') return 'success';
  if (status === 'In Progress') return 'primary';
  return 'warning';
}

async function loadPendingActions() {
  isLoading.value = true;
  try {
    const { data } = await axios.get<PendingActionsResponse>('/api/hr/dashboard/pending-actions');
    completedTasks.value = Number(data?.completed_tasks ?? data?.tasks_completed ?? completedTasks.value);
    totalTasks.value = Number(data?.total_tasks ?? data?.tasks_total ?? totalTasks.value);

    const responseActions = (Array.isArray(data?.actions) ? data.actions : data?.items) ?? [];
    if (Array.isArray(responseActions) && responseActions.length) {
      actions.value = responseActions.map((item) => ({
        title: item.title,
        subtitle: item.subtitle,
        status: item.status
      }));
    }
  } catch (error) {
    completedTasks.value = 4;
    totalTasks.value = 9;
  } finally {
    isLoading.value = false;
  }
}

function submitRequest() {
  showRequestDialog.value = false;
}

onMounted(loadPendingActions);
</script>

<template>
  <v-skeleton-loader v-if="isLoading" type="card" class="rounded-lg" />

  <CardHeader v-else title="HR Tasks & Approvals" class="overflow-hidden hr-card-shadow">
    <div class="pa-6">
      <div class="d-flex justify-space-between mb-2">
        <p class="text-body-1 mb-0">{{ completedTasks }} of {{ totalTasks }} tasks completed today</p>
        <p class="text-body-1 mb-0">{{ completionPercent }}%</p>
      </div>
      <v-progress-linear aria-label="progressbar" rounded color="primary" :model-value="completionPercent" height="6" />

      <v-list class="py-5" aria-label="pending hr actions">
        <v-list-item v-for="(item, index) in actions" :key="index" :value="index" rounded="md" class="px-0">
          <template v-slot:prepend>
            <v-avatar size="10" :color="statusColor(item.status)" />
          </template>
          <v-list-item-title class="text-body-1 font-weight-bold">{{ item.title }}</v-list-item-title>
          <v-list-item-subtitle class="text-caption text-lightText mt-1">{{ item.subtitle }}</v-list-item-subtitle>
          <template v-slot:append>
            <v-chip :color="statusColor(item.status)" variant="tonal" size="small" rounded="md">{{ item.status }}</v-chip>
          </template>
        </v-list-item>
      </v-list>

      <v-btn color="primary" variant="flat" rounded="md" block @click="showRequestDialog = true"> + New Request </v-btn>
    </div>
  </CardHeader>

  <v-dialog v-model="showRequestDialog" max-width="420">
    <v-card rounded="lg">
      <v-card-title class="text-h5">Create Request</v-card-title>
      <v-card-text>
        <v-select v-model="requestType" label="Request type" :items="requestTypes" variant="outlined" hide-details />
      </v-card-text>
      <v-card-actions class="px-6 pb-5">
        <v-spacer />
        <v-btn variant="text" @click="showRequestDialog = false">Cancel</v-btn>
        <v-btn color="primary" variant="flat" :disabled="!requestType" @click="submitRequest">Continue</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style lang="scss">
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}
</style>
