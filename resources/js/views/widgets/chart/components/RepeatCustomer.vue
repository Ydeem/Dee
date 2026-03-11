<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

type AttendanceChartResponse = {
  months?: string[]
  attendance?: number[]
  leave?: number[]
  current_rate?: number
  change?: string
  trend?: 'up' | 'down' | 'neutral' | string
}

const isLoading = ref(true);
const months = ref(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
const attendanceSeries = ref<number[]>([]);
const leaveSeries = ref<number[]>([]);
const currentRate = ref(0);
const rateChange = ref('+0%');
const trend = ref<'up' | 'down' | 'neutral' | string>('neutral');

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    height: 280,
    fontFamily: 'inherit',
    foreColor: 'rgba(var(--v-theme-lightText), var(--v-high-opacity))',
    toolbar: false
  },
  colors: ['#4f6ef7', '#f77c4f'],
  dataLabels: { enabled: false },
  stroke: {
    curve: 'smooth',
    width: [2.5, 2.5]
  },
  fill: {
    type: ['gradient', 'solid'],
    gradient: {
      shadeIntensity: 1,
      type: 'vertical',
      inverseColors: false,
      opacityFrom: 0.45,
      opacityTo: 0.05
    },
    opacity: [0.4, 0.2]
  },
  grid: {
    borderColor: 'rgba(var(--v-theme-borderLight), var(--v-high-opacity))',
    strokeDashArray: 4
  },
  xaxis: {
    categories: months.value,
    axisBorder: { show: false },
    axisTicks: { show: false }
  },
  yaxis: {
    min: 0,
    max: 100,
    tickAmount: 5,
    labels: {
      formatter: (value: number) => `${Math.round(value)}%`
    }
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'left'
  },
  tooltip: {
    y: {
      formatter: (value: number) => `${value.toFixed(1)}%`
    }
  }
}));

const areaChart = computed(() => ({
  series: [
    {
      name: 'Attendance Rate %',
      data: attendanceSeries.value
    },
    {
      name: 'Leave Rate %',
      data: leaveSeries.value
    }
  ]
}));

const chipColor = computed(() => {
  if (trend.value === 'up') return 'success';
  if (trend.value === 'down') return 'error';
  return 'warning';
});

async function fetchAttendanceChart() {
  isLoading.value = true;

  try {
    const { data } = await axios.get<AttendanceChartResponse>('/api/hr/dashboard/attendance-chart');
    months.value = Array.isArray(data?.months) && data.months.length ? data.months : months.value;
    attendanceSeries.value = Array.isArray(data?.attendance) ? data.attendance.map(Number) : [];
    leaveSeries.value = Array.isArray(data?.leave) ? data.leave.map(Number) : [];
    currentRate.value = Number(data?.current_rate ?? 0);
    rateChange.value = data?.change ?? '+0%';
    trend.value = data?.trend ?? 'neutral';
  } catch (error) {
    console.error('Chart fetch failed', error);
    attendanceSeries.value = [];
    leaveSeries.value = [];
    currentRate.value = 0;
    rateChange.value = '+0%';
    trend.value = 'neutral';
  } finally {
    isLoading.value = false;
  }
}

onMounted(fetchAttendanceChart);
</script>

<template>
  <v-skeleton-loader v-if="isLoading" type="card" class="rounded-lg" />

  <v-card v-else variant="outlined" class="bg-surface hr-card-shadow" rounded="lg">
    <v-card-text class="pb-2">
      <div class="d-flex justify-space-between align-center">
        <h5 class="text-h5 mb-0 cursor-pointer" @click="router.visit('/hr/attendance')">Attendance Overview</h5>
        <v-chip :color="chipColor" variant="flat" size="small" rounded="md">
          {{ currentRate }}% {{ rateChange }}
        </v-chip>
      </div>
    </v-card-text>
    <v-card-item class="pt-0">
      <apexchart type="area" height="280" :options="chartOptions" :series="areaChart.series" />
    </v-card-item>
  </v-card>
</template>

<style lang="scss">
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}
</style>
