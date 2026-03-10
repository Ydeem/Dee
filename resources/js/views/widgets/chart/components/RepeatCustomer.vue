<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

type AttendanceChartResponse = {
  months?: string[];
  attendance?: number[];
  leave?: number[];
};

const isLoading = ref(true);
const months = ref(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']);
const attendanceSeries = ref([88, 90, 91, 92, 90, 91, 93, 94, 95, 95, 96, 96.2]);
const leaveSeries = ref([12, 10, 9, 8, 10, 9, 7, 6, 5, 5, 4, 3.8]);

const currentAttendance = computed(() => {
  return attendanceSeries.value[attendanceSeries.value.length - 1] ?? 0;
});

const deltaAttendance = computed(() => {
  const length = attendanceSeries.value.length;
  const previous = length > 1 ? attendanceSeries.value[length - 2] : currentAttendance.value;
  return Number((currentAttendance.value - previous).toFixed(1));
});

const deltaPrefix = computed(() => (deltaAttendance.value >= 0 ? '+' : ''));

const chartOptions = computed(() => {
  return {
    chart: {
      type: 'area',
      height: 280,
      fontFamily: 'inherit',
      foreColor: 'rgba(var(--v-theme-lightText), var(--v-high-opacity))',
      toolbar: false
    },
    colors: ['#4f6ef7', '#f77c4f'],
    dataLabels: {
      enabled: false
    },
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
      axisBorder: {
        show: false
      },
      axisTicks: {
        show: false
      }
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
  };
});

const areaChart = computed(() => {
  return {
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
  };
});

async function loadAttendanceChart() {
  isLoading.value = true;
  try {
    const { data } = await axios.get<AttendanceChartResponse>('/api/hr/dashboard/attendance-chart');
    const responseMonths = Array.isArray(data?.months) && data.months.length ? data.months : months.value;
    const responseAttendance =
      Array.isArray(data?.attendance) && data.attendance.length ? data.attendance.map((value) => Number(value)) : attendanceSeries.value;
    const responseLeave = Array.isArray(data?.leave) && data.leave.length ? data.leave.map((value) => Number(value)) : leaveSeries.value;

    months.value = responseMonths;
    attendanceSeries.value = responseAttendance;
    leaveSeries.value = responseLeave;
  } catch (error) {
    months.value = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    attendanceSeries.value = [88, 90, 91, 92, 90, 91, 93, 94, 95, 95, 96, 96.2];
    leaveSeries.value = [12, 10, 9, 8, 10, 9, 7, 6, 5, 5, 4, 3.8];
  } finally {
    isLoading.value = false;
  }
}

onMounted(loadAttendanceChart);
</script>

<template>
  <v-skeleton-loader v-if="isLoading" type="card" class="rounded-lg" />

  <v-card v-else variant="outlined" class="bg-surface hr-card-shadow" rounded="lg">
    <v-card-text class="pb-2">
      <div class="d-flex justify-space-between align-center">
        <h5 class="text-h5 mb-0">Attendance Overview</h5>
        <v-chip color="success" variant="flat" size="small" rounded="md">
          {{ currentAttendance.toFixed(1) }}% {{ deltaPrefix }}{{ deltaAttendance.toFixed(1) }}%
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
</style>
