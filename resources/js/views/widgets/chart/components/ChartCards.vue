<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

type StatPayload = {
  value: number
  change: string
  trend: 'up' | 'down' | 'neutral' | string
}

type StatsResponse = {
  total_employees?: StatPayload
  on_leave_today?: StatPayload
  open_positions?: StatPayload
  pending_approvals?: StatPayload
}

type HrStatCard = {
  title: string
  key: keyof StatsResponse
  color: string
  icon: string
  route: string
}

const isLoading = ref(true);
const stats = ref<Record<string, StatPayload>>({
  total_employees: { value: 0, change: '+0% from last month', trend: 'neutral' },
  on_leave_today: { value: 0, change: '+0 from yesterday', trend: 'neutral' },
  open_positions: { value: 0, change: '0 new this week', trend: 'neutral' },
  pending_approvals: { value: 0, change: 'Requires attention', trend: 'neutral' }
});

const cards: HrStatCard[] = [
  { title: 'Total Employees', key: 'total_employees', color: '#4f6ef7', icon: 'mdi-account-group', route: '/hr/employees' },
  { title: 'On Leave Today', key: 'on_leave_today', color: '#f59e0b', icon: 'mdi-calendar-remove', route: '/hr/leave-management' },
  { title: 'Open Positions', key: 'open_positions', color: '#22c55e', icon: 'mdi-briefcase-outline', route: '/hr/job-openings' },
  { title: 'Pending Approvals', key: 'pending_approvals', color: '#ef4444', icon: 'mdi-clock-alert-outline', route: '/hr/leave-management' }
];

const sparklineSeries = computed(() => ({
  total_employees: buildSeries(stats.value.total_employees.value, 6),
  on_leave_today: buildSeries(stats.value.on_leave_today.value, 3),
  open_positions: buildSeries(stats.value.open_positions.value, 2),
  pending_approvals: buildSeries(stats.value.pending_approvals.value, 4)
}));

function buildSeries(value: number, variation: number) {
  const safeValue = Math.max(value, 0);
  return Array.from({ length: 8 }, (_, index) => Math.max(safeValue - variation + index, 0));
}

function trendColor(trend: string) {
  if (trend === 'up') return 'text-success';
  if (trend === 'down') return 'text-error';
  return 'text-warning';
}

function trendIcon(trend: string) {
  if (trend === 'up') return 'mdi-arrow-up';
  if (trend === 'down') return 'mdi-arrow-down';
  return 'mdi-minus';
}

function chartOptions(color: string) {
  return {
    chart: {
      type: 'bar',
      height: 50,
      fontFamily: 'inherit',
      sparkline: { enabled: true }
    },
    dataLabels: { enabled: false },
    plotOptions: {
      bar: {
        borderRadius: 2,
        columnWidth: '80%'
      }
    },
    colors: [color],
    stroke: {
      curve: 'smooth',
      width: 0
    },
    tooltip: {
      fixed: { enabled: false },
      x: { show: false }
    }
  };
}

async function fetchStats() {
  isLoading.value = true;

  try {
    const { data } = await axios.get<StatsResponse>('/api/hr/dashboard/stats');
    stats.value = {
      total_employees: data?.total_employees ?? stats.value.total_employees,
      on_leave_today: data?.on_leave_today ?? stats.value.on_leave_today,
      open_positions: data?.open_positions ?? stats.value.open_positions,
      pending_approvals: data?.pending_approvals ?? stats.value.pending_approvals
    };
  } catch (error) {
    console.error('Stats fetch failed', error);
  } finally {
    isLoading.value = false;
  }
}

onMounted(fetchStats);
</script>

<template>
  <v-row class="mb-0">
    <template v-if="isLoading">
      <v-col v-for="index in 4" :key="index" cols="12" sm="6" lg="3">
        <v-skeleton-loader type="card" class="rounded-lg" />
      </v-col>
    </template>

    <template v-else>
      <v-col v-for="card in cards" :key="card.key" cols="12" sm="6" lg="3">
        <v-card variant="outlined" elevation="0" class="bg-surface hr-stat-card cursor-pointer" rounded="lg" @click="router.visit(card.route)">
          <v-card-text>
            <v-list class="pt-0" aria-label="hr stat content">
              <v-list-item class="pa-0">
                <template #prepend>
                  <v-avatar rounded="md" :style="{ backgroundColor: `${card.color}1A`, color: card.color }">
                    <v-icon :icon="card.icon" />
                  </v-avatar>
                </template>
                <h6 class="text-subtitle-1 mb-0">{{ card.title }}</h6>
              </v-list-item>
            </v-list>

            <v-sheet class="pa-6 pb-3 mt-1" color="containerBg" rounded="lg">
              <v-row class="widget-grid align-center">
                <v-col cols="7">
                  <apexchart
                    type="bar"
                    height="50"
                    :options="chartOptions(card.color)"
                    :series="[{ name: card.title, data: sparklineSeries[card.key] }]"
                  />
                </v-col>
                <v-col cols="5">
                  <h5 class="text-h5">{{ stats[card.key].value }}</h5>
                  <p class="text-body-1 mb-0 d-flex align-center" :class="trendColor(stats[card.key].trend)">
                    <v-icon :icon="trendIcon(stats[card.key].trend)" size="16" class="me-1" />
                    {{ stats[card.key].change }}
                  </p>
                </v-col>
              </v-row>
            </v-sheet>
          </v-card-text>
        </v-card>
      </v-col>
    </template>
  </v-row>
</template>

<style lang="scss">
.widget-grid {
  > div {
    @media (max-width: 1540px) and (min-width: 1280px) {
      flex: 0 0 100%;
      max-width: 100%;
      text-align: center;
    }
  }
}

.hr-stat-card {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
