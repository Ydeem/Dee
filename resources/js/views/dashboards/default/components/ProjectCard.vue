<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import CardHeader from '@/components/shared/CardHeader.vue';

type PendingAction = {
  type: string
  title: string
  subtitle: string
  status: string
  color: string
  id: number
  link: string
}

type PendingActionsResponse = {
  actions?: PendingAction[]
  total?: number
  completed?: number
  percent?: number
}

const isLoading = ref(true);
const showRequestDialog = ref(false);
const actions = ref<PendingAction[]>([]);
const taskTotal = ref(0);
const taskCompleted = ref(0);
const taskPercent = ref(0);

const requestOptions = [
  { title: 'Leave Request', route: '/hr/leave-management' },
  { title: 'Onboarding', route: '/hr/onboarding' },
  { title: 'Payroll', route: '/hr/payroll' },
  { title: 'Expense', route: '/hr/expenses' }
];

const completionPercent = computed(() => taskPercent.value || (taskTotal.value ? Math.round((taskCompleted.value / taskTotal.value) * 100) : 0));

function statusColor(status: string, color?: string) {
  if (color) return color;
  if (status === 'Done') return 'success';
  if (status === 'In Progress') return 'primary';
  return 'warning';
}

async function fetchPendingActions() {
  isLoading.value = true;

  try {
    const { data } = await axios.get<PendingActionsResponse>('/api/hr/dashboard/pending-actions');
    actions.value = data?.actions ?? [];
    taskTotal.value = Number(data?.total ?? 0);
    taskCompleted.value = Number(data?.completed ?? 0);
    taskPercent.value = Number(data?.percent ?? 0);
  } catch (error) {
    console.error('Actions fetch failed', error);
    actions.value = [];
    taskTotal.value = 0;
    taskCompleted.value = 0;
    taskPercent.value = 0;
  } finally {
    isLoading.value = false;
  }
}

function openRequest(route: string) {
  showRequestDialog.value = false;
  router.visit(route);
}

onMounted(fetchPendingActions);
</script>

<template>
  <v-skeleton-loader v-if="isLoading" type="card" class="rounded-lg" />

  <CardHeader v-else title="HR Tasks & Approvals" class="overflow-hidden hr-card-shadow">
    <div class="pa-6">
      <div class="d-flex justify-space-between mb-2">
        <p class="text-body-1 mb-0">{{ taskCompleted }} of {{ taskTotal }} tasks completed today</p>
        <p class="text-body-1 mb-0">{{ completionPercent }}%</p>
      </div>
      <v-progress-linear aria-label="progressbar" rounded color="primary" :model-value="completionPercent" height="6" />

      <v-list class="py-5" aria-label="pending hr actions">
        <template v-if="actions.length">
          <v-list-item
            v-for="action in actions"
            :key="`${action.type}-${action.id}`"
            rounded="md"
            class="px-0 cursor-pointer"
            @click="router.visit(action.link)"
          >
            <template #prepend>
              <v-avatar size="10" :color="statusColor(action.status, action.color)" />
            </template>
            <v-list-item-title class="text-body-1 font-weight-bold">{{ action.title }}</v-list-item-title>
            <v-list-item-subtitle class="text-caption text-lightText mt-1">{{ action.subtitle }}</v-list-item-subtitle>
            <template #append>
              <v-chip :color="statusColor(action.status, action.color)" variant="tonal" size="small" rounded="md">{{ action.status }}</v-chip>
            </template>
          </v-list-item>
        </template>

        <v-list-item v-else>
          <v-list-item-title class="text-lightText">No pending HR actions.</v-list-item-title>
        </v-list-item>
      </v-list>

      <v-btn color="primary" variant="flat" rounded="md" block @click="showRequestDialog = true">+ New Request</v-btn>
    </div>
  </CardHeader>

  <v-dialog v-model="showRequestDialog" max-width="420">
    <v-card rounded="lg">
      <v-card-title class="text-h5">Create Request</v-card-title>
      <v-card-text>
        <v-list density="comfortable">
          <v-list-item
            v-for="option in requestOptions"
            :key="option.route"
            rounded="md"
            class="cursor-pointer"
            @click="openRequest(option.route)"
          >
            <v-list-item-title>{{ option.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-card-text>
      <v-card-actions class="px-6 pb-5">
        <v-spacer />
        <v-btn variant="text" @click="showRequestDialog = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<style lang="scss">
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
