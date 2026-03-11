<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

type DashboardSummary = {
  on_leave: number
  pending_approvals: number
  open_positions: number
}

const page = usePage();
const isLoading = ref(true);
const summary = ref<DashboardSummary>({
  on_leave: 0,
  pending_approvals: 0,
  open_positions: 0
});

const todayLabel = new Intl.DateTimeFormat('en-US', {
  weekday: 'long',
  month: 'long',
  day: 'numeric',
  year: 'numeric'
}).format(new Date());

const userFullName = computed(() => ((page.props as any)?.auth?.user?.name as string) || 'Team Lead');

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

onMounted(fetchSummary);
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
            <v-icon icon="mdi-account-group" size="84" color="white" />
          </div>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
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
}

.cursor-pointer {
  cursor: pointer;
}
</style>
