<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

type StatsResponse = {
  total_employees?: number;
  employees?: number;
  on_leave_today?: number;
  on_leave?: number;
  open_positions?: number;
  pending_approvals?: number;
};

type HrStatCard = {
  title: string;
  value: number;
  color: string;
  icon: string;
  chipClass: string;
  changeText: string;
  changeIcon?: string;
  series: number[];
};

const isLoading = ref(true);
const stats = ref({
  totalEmployees: 0,
  onLeaveToday: 0,
  openPositions: 0,
  pendingApprovals: 0
});

const barSeries = {
  totalEmployees: [18, 24, 32, 30, 44, 38, 46, 40, 48, 54, 50, 58],
  onLeaveToday: [8, 12, 10, 14, 11, 16, 18, 15, 17, 14, 19, 16],
  openPositions: [5, 6, 7, 6, 8, 7, 9, 10, 8, 9, 7, 8],
  pendingApprovals: [4, 6, 5, 7, 8, 6, 9, 7, 6, 8, 7, 5]
};

const cards = computed<HrStatCard[]>(() => {
  return [
    {
      title: 'Total Employees',
      value: stats.value.totalEmployees,
      color: '#4f6ef7',
      icon: 'mdi-account-group',
      chipClass: 'text-success',
      changeText: '+3.2% from last month',
      changeIcon: 'mdi-arrow-up',
      series: barSeries.totalEmployees
    },
    {
      title: 'On Leave Today',
      value: stats.value.onLeaveToday,
      color: '#f59e0b',
      icon: 'mdi-calendar-remove',
      chipClass: 'text-warning',
      changeText: '+1 from yesterday',
      series: barSeries.onLeaveToday
    },
    {
      title: 'Open Positions',
      value: stats.value.openPositions,
      color: '#22c55e',
      icon: 'mdi-briefcase-outline',
      chipClass: 'text-success',
      changeText: '2 new this week',
      series: barSeries.openPositions
    },
    {
      title: 'Pending Approvals',
      value: stats.value.pendingApprovals,
      color: '#ef4444',
      icon: 'mdi-clock-alert-outline',
      chipClass: 'text-error',
      changeText: 'Requires attention',
      series: barSeries.pendingApprovals
    }
  ];
});

function chartOptions(color: string) {
  return {
    chart: {
      type: 'bar',
      height: 50,
      fontFamily: 'inherit',
      sparkline: {
        enabled: true
      }
    },
    dataLabels: {
      enabled: false
    },
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
      fixed: {
        enabled: false
      },
      x: {
        show: false
      }
    }
  };
}

async function loadStats() {
  isLoading.value = true;
  try {
    const { data } = await axios.get<StatsResponse>('/api/hr/dashboard/stats');
    stats.value = {
      totalEmployees: Number(data?.total_employees ?? data?.employees ?? 0),
      onLeaveToday: Number(data?.on_leave_today ?? data?.on_leave ?? 0),
      openPositions: Number(data?.open_positions ?? 0),
      pendingApprovals: Number(data?.pending_approvals ?? 0)
    };
  } catch (error) {
    stats.value = {
      totalEmployees: 0,
      onLeaveToday: 0,
      openPositions: 0,
      pendingApprovals: 0
    };
  } finally {
    isLoading.value = false;
  }
}

onMounted(loadStats);
</script>

<template>
  <v-row class="mb-0">
    <template v-if="isLoading">
      <v-col v-for="index in 4" :key="index" cols="12" sm="6" lg="3">
        <v-skeleton-loader type="card" class="rounded-lg" />
      </v-col>
    </template>

    <template v-else>
      <v-col v-for="(card, index) in cards" :key="index" cols="12" sm="6" lg="3">
        <v-card variant="outlined" elevation="0" class="bg-surface hr-stat-card" rounded="lg">
          <v-card-text>
            <v-list class="pt-0" aria-label="hr stat content">
              <v-list-item class="pa-0">
                <template v-slot:prepend>
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
                  <apexchart type="bar" height="50" :options="chartOptions(card.color)" :series="[{ name: card.title, data: card.series }]" />
                </v-col>
                <v-col cols="5">
                  <h5 class="text-h5">{{ card.value }}</h5>
                  <p class="text-body-1 mb-0 d-flex align-center" :class="card.chipClass">
                    <v-icon v-if="card.changeIcon" :icon="card.changeIcon" size="16" class="me-1" />
                    {{ card.changeText }}
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
</style>
