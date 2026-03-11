<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import ApexCharts from 'apexcharts';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface ReportFilters {
  year: number
  month: number
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Reports', disabled: true, href: '#' }
];

const COLORS = {
  primary: '#4f6ef7',
  success: '#4caf50',
  warning: '#f77c4f',
  error: '#f44336',
  secondary: '#9c27b0',
  pink: '#ec4899',
  grey: '#94a3b8',
  teal: '#14b8a6'
};

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const currentDate = new Date();
const filters = reactive<ReportFilters>({
  year: currentDate.getFullYear(),
  month: currentDate.getMonth() + 1
});
const activeTab = ref('workforce');

const loading = reactive({
  workforce: true,
  attendance: true,
  leave: true,
  payroll: true,
  recruitment: true,
  expenses: true
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const workforce = ref<any>({
  by_department: [],
  by_type: [],
  monthly_hires: [],
  by_gender: [],
  total_active: 0,
  total_inactive: 0,
  new_this_month: 0
});

const attendance = ref<any>({
  daily_summary: [],
  by_department: [],
  totals: { present: 0, absent: 0, late: 0 }
});

const leaveReport = ref<any>({
  by_type: [],
  monthly: [],
  by_department: [],
  pending_count: 0,
  total_days_approved: 0
});

const payroll = ref<any>({
  monthly: [],
  year_total_gross: 0,
  year_total_net: 0,
  year_total_deductions: 0,
  by_department: []
});

const recruitment = ref<any>({
  monthly_applications: [],
  by_status: [],
  by_source: [],
  open_jobs: 0,
  total_applications: 0,
  total_hired: 0,
  avg_time_to_hire: 0
});

const expenses = ref<any>({
  monthly: [],
  by_category: [],
  by_department: [],
  total_year: 0,
  pending_count: 0
});

const yearOptions = computed(() => {
  const year = currentDate.getFullYear();
  return [year, year - 1, year - 2];
});

const tabs = [
  { value: 'workforce', label: 'Workforce', icon: 'mdi-account-group' },
  { value: 'attendance', label: 'Attendance', icon: 'mdi-calendar-check' },
  { value: 'leave', label: 'Leave', icon: 'mdi-calendar-remove' },
  { value: 'payroll', label: 'Payroll', icon: 'mdi-cash' },
  { value: 'recruitment', label: 'Recruitment', icon: 'mdi-briefcase' },
  { value: 'expenses', label: 'Expenses', icon: 'mdi-receipt' }
];

function showSnackbar(message: string, color: 'success' | 'error' | 'warning' = 'success') {
  snackbar.value = { show: true, message, color };
}

function formatCurrency(value: number | string | null | undefined) {
  return Number(value ?? 0).toLocaleString('en-GH', {
    style: 'currency',
    currency: 'GHS'
  });
}

function monthSeries(items: Array<{ month: number; [key: string]: any }>, key: string) {
  const base = new Array(12).fill(0);
  items.forEach((item) => {
    const index = Number(item.month) - 1;
    if (index >= 0 && index < 12) base[index] = Number(item[key] ?? 0);
  });
  return base;
}

function hasValues(values: any[]) {
  return values.some((value) => Number(value ?? 0) > 0);
}

function exportAllPdf() {
  window.print();
}

function chartOptions(overrides: Record<string, any> = {}) {
  return {
    chart: {
      toolbar: { show: false },
      fontFamily: 'Inter, sans-serif',
      foreColor: '#64748b'
    },
    stroke: {
      curve: 'smooth',
      width: 3
    },
    dataLabels: {
      enabled: false
    },
    grid: {
      borderColor: '#e5e7eb',
      strokeDashArray: 4
    },
    legend: {
      position: 'bottom'
    },
    xaxis: {
      categories: monthLabels,
      labels: { style: { colors: '#64748b' } }
    },
    yaxis: {
      labels: { style: { colors: '#64748b' } }
    },
    tooltip: {
      theme: 'light'
    },
    ...overrides
  };
}

async function downloadChart(chartId: string, fileName: string) {
  try {
    const result = await ApexCharts.exec(chartId, 'dataURI');
    const link = document.createElement('a');
    link.href = result.imgURI;
    link.download = `${fileName}.png`;
    link.click();
  } catch (error) {
    showSnackbar('Chart download failed.', 'error');
  }
}

async function fetchWorkforce() {
  loading.workforce = true;
  try {
    const { data } = await axios.get('/api/hr/reports/workforce', { params: { year: filters.year } });
    workforce.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load workforce report.', 'error');
  } finally {
    loading.workforce = false;
  }
}

async function fetchAttendance() {
  loading.attendance = true;
  try {
    const { data } = await axios.get('/api/hr/reports/attendance', {
      params: { year: filters.year, month: filters.month }
    });
    attendance.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load attendance report.', 'error');
  } finally {
    loading.attendance = false;
  }
}

async function fetchLeave() {
  loading.leave = true;
  try {
    const { data } = await axios.get('/api/hr/reports/leave', { params: { year: filters.year } });
    leaveReport.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load leave report.', 'error');
  } finally {
    loading.leave = false;
  }
}

async function fetchPayroll() {
  loading.payroll = true;
  try {
    const { data } = await axios.get('/api/hr/reports/payroll', { params: { year: filters.year } });
    payroll.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load payroll report.', 'error');
  } finally {
    loading.payroll = false;
  }
}

async function fetchRecruitment() {
  loading.recruitment = true;
  try {
    const { data } = await axios.get('/api/hr/reports/recruitment', { params: { year: filters.year } });
    recruitment.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load recruitment report.', 'error');
  } finally {
    loading.recruitment = false;
  }
}

async function fetchExpenses() {
  loading.expenses = true;
  try {
    const { data } = await axios.get('/api/hr/reports/expenses', { params: { year: filters.year } });
    expenses.value = data;
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load expenses report.', 'error');
  } finally {
    loading.expenses = false;
  }
}

async function fetchAllReports() {
  await Promise.all([
    fetchWorkforce(),
    fetchAttendance(),
    fetchLeave(),
    fetchPayroll(),
    fetchRecruitment(),
    fetchExpenses()
  ]);
}

const workforceDepartmentSeries = computed(() => workforce.value.by_department.map((item) => Number(item.active ?? item.total ?? 0)));
const workforceDepartmentOptions = computed(() =>
  chartOptions({
    chart: { id: 'workforce-department', toolbar: { show: false } },
    colors: [COLORS.primary],
    xaxis: { categories: workforce.value.by_department.map((item) => item.name) }
  })
);
const workforceTypeOptions = computed(() =>
  chartOptions({
    chart: { id: 'workforce-type' },
    labels: workforce.value.by_type.map((item) => item.employment_type || 'Unknown'),
    colors: [COLORS.primary, COLORS.warning, COLORS.success, COLORS.secondary]
  })
);
const workforceHiresOptions = computed(() =>
  chartOptions({
    chart: { id: 'workforce-hires' },
    colors: [COLORS.primary]
  })
);
const workforceGenderOptions = computed(() =>
  chartOptions({
    chart: { id: 'workforce-gender' },
    labels: workforce.value.by_gender.map((item) => item.gender || 'Other'),
    colors: [COLORS.primary, COLORS.pink, COLORS.grey]
  })
);

const attendanceDailyOptions = computed(() =>
  chartOptions({
    chart: { id: 'attendance-daily', stacked: true },
    colors: [COLORS.success, COLORS.error, COLORS.warning, COLORS.primary],
    plotOptions: { bar: { columnWidth: '55%' } },
    xaxis: {
      categories: attendance.value.daily_summary.map((item) =>
        new Date(item.date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short' })
      )
    }
  })
);
const attendanceRateOptions = computed(() =>
  chartOptions({
    chart: { id: 'attendance-rate' },
    colors: [COLORS.success],
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.95, opacityTo: 0.75, stops: [0, 100], colorStops: [] }
    },
    plotOptions: { bar: { borderRadius: 6 } },
    xaxis: { categories: attendance.value.by_department.map((item) => item.department) },
    yaxis: { max: 100 }
  })
);

const leaveTypeOptions = computed(() =>
  chartOptions({
    chart: { id: 'leave-type' },
    colors: [COLORS.primary],
    xaxis: { categories: leaveReport.value.by_type.map((item) => item.name) }
  })
);
const leaveTrendOptions = computed(() =>
  chartOptions({
    chart: { id: 'leave-trend' },
    colors: [COLORS.warning]
  })
);
const leaveDepartmentOptions = computed(() =>
  chartOptions({
    chart: { id: 'leave-department' },
    colors: [COLORS.primary],
    xaxis: { categories: leaveReport.value.by_department.map((item) => item.department) }
  })
);

const payrollMonthlyOptions = computed(() =>
  chartOptions({
    chart: { id: 'payroll-monthly', stacked: false },
    colors: [COLORS.primary, COLORS.success, COLORS.error],
    plotOptions: { bar: { columnWidth: '45%' } }
  })
);
const payrollDepartmentOptions = computed(() =>
  chartOptions({
    chart: { id: 'payroll-department' },
    colors: [COLORS.primary],
    xaxis: { categories: payroll.value.by_department.map((item) => item.department) }
  })
);

const recruitmentMonthlyOptions = computed(() =>
  chartOptions({
    chart: { id: 'recruitment-monthly' },
    colors: [COLORS.primary]
  })
);
const recruitmentSourceOptions = computed(() =>
  chartOptions({
    chart: { id: 'recruitment-source' },
    labels: recruitment.value.by_source.map((item) => item.source || 'Unknown'),
    colors: [COLORS.primary, COLORS.warning, COLORS.success, COLORS.secondary, COLORS.teal]
  })
);
const recruitmentStatusOptions = computed(() =>
  chartOptions({
    chart: { id: 'recruitment-status' },
    colors: [COLORS.primary, COLORS.warning, COLORS.success, COLORS.error, COLORS.secondary, COLORS.teal],
    plotOptions: { bar: { horizontal: true, borderRadius: 6, distributed: true } },
    xaxis: { categories: recruitment.value.by_status.map((item) => item.status || 'Unknown') }
  })
);

const expenseMonthlyOptions = computed(() =>
  chartOptions({
    chart: { id: 'expense-monthly' },
    colors: [COLORS.warning]
  })
);
const expenseCategoryOptions = computed(() =>
  chartOptions({
    chart: { id: 'expense-category' },
    labels: expenses.value.by_category.map((item) => item.category),
    colors: [COLORS.primary, COLORS.warning, COLORS.error, COLORS.success, COLORS.secondary, COLORS.teal, COLORS.grey]
  })
);
const expenseDepartmentOptions = computed(() =>
  chartOptions({
    chart: { id: 'expense-department' },
    colors: [COLORS.secondary],
    fill: {
      type: 'gradient',
      gradient: { shadeIntensity: 1, opacityFrom: 0.95, opacityTo: 0.75, stops: [0, 100], colorStops: [] }
    },
    xaxis: { categories: expenses.value.by_department.map((item) => item.department) }
  })
);

const topExpenseCategory = computed(() => expenses.value.by_category[0]?.category ?? '-');

watch(
  () => filters.year,
  () => {
    fetchAllReports();
  }
);

watch(
  () => filters.month,
  () => {
    fetchAttendance();
  }
);

onMounted(() => {
  fetchAllReports();
});
</script>

<template>
  <BaseBreadcrumb title="HR Reports" subtitle="Analytics and insights across your workforce" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">HR Reports</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Analytics and insights across your workforce</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-file-pdf" @click="exportAllPdf">Export All (PDF)</v-btn>
      <v-select
        v-model="filters.year"
        :items="yearOptions"
        label="Year"
        variant="outlined"
        hide-details
        density="comfortable"
        style="max-width: 140px;"
      />
    </div>
  </div>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item class="pb-0">
      <v-tabs v-model="activeTab" color="primary" grow>
        <v-tab v-for="tab in tabs" :key="tab.value" :value="tab.value">
          <v-icon start :icon="tab.icon" />
          {{ tab.label }}
        </v-tab>
      </v-tabs>
    </v-card-item>

    <v-divider />

    <v-card-text>
      <v-window v-model="activeTab">
        <v-window-item value="workforce">
          <v-row v-if="!loading.workforce">
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Active Employees</div><div class="text-h4 font-weight-bold">{{ workforce.total_active }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Inactive</div><div class="text-h4 font-weight-bold">{{ workforce.total_inactive }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">New Hires This Month</div><div class="text-h4 font-weight-bold">{{ workforce.new_this_month }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Departments</div><div class="text-h4 font-weight-bold">{{ workforce.by_department.length }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Employees by Department</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('workforce-department', 'workforce-by-department')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(workforceDepartmentSeries)"
                    type="bar"
                    height="320"
                    :options="workforceDepartmentOptions"
                    :series="[{ name: 'Employees', data: workforceDepartmentSeries }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Employment Type Breakdown</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('workforce-type', 'employment-type-breakdown')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(workforce.by_type.map((item) => item.total))"
                    type="donut"
                    height="320"
                    :options="workforceTypeOptions"
                    :series="workforce.by_type.map((item) => Number(item.total))"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Monthly Hires This Year</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('workforce-hires', 'monthly-hires')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(monthSeries(workforce.monthly_hires, 'count'))"
                    type="line"
                    height="320"
                    :options="workforceHiresOptions"
                    :series="[{ name: 'Hires', data: monthSeries(workforce.monthly_hires, 'count') }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Gender Distribution</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('workforce-gender', 'gender-distribution')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(workforce.by_gender.map((item) => item.total))"
                    type="donut"
                    height="320"
                    :options="workforceGenderOptions"
                    :series="workforce.by_gender.map((item) => Number(item.total))"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article, article" />
        </v-window-item>

        <v-window-item value="attendance">
          <div class="d-flex justify-end mb-4">
            <v-select
              v-model="filters.month"
              :items="monthLabels.map((label, index) => ({ title: label, value: index + 1 }))"
              label="Month"
              variant="outlined"
              hide-details
              style="max-width: 180px;"
            />
          </div>

          <v-row v-if="!loading.attendance">
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Present</div><div class="text-h4 font-weight-bold">{{ attendance.totals.present }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Absent</div><div class="text-h4 font-weight-bold">{{ attendance.totals.absent }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Late</div><div class="text-h4 font-weight-bold">{{ attendance.totals.late }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Daily Attendance This Month</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('attendance-daily', 'daily-attendance')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="attendance.daily_summary.length"
                    type="bar"
                    height="330"
                    :options="attendanceDailyOptions"
                    :series="[
                      { name: 'Present', data: attendance.daily_summary.map((item) => Number(item.present)) },
                      { name: 'Absent', data: attendance.daily_summary.map((item) => Number(item.absent)) },
                      { name: 'Late', data: attendance.daily_summary.map((item) => Number(item.late)) },
                      { name: 'On Leave', data: attendance.daily_summary.map((item) => Number(item.on_leave)) }
                    ]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Attendance Rate by Department</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('attendance-rate', 'attendance-rate-by-department')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="attendance.by_department.length"
                    type="bar"
                    height="320"
                    :options="attendanceRateOptions"
                    :series="[{ name: 'Attendance %', data: attendance.by_department.map((item) => Number(item.present_rate)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article" />
        </v-window-item>

        <v-window-item value="leave">
          <v-row v-if="!loading.leave">
            <v-col cols="12" sm="6">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Pending Leave Requests</div><div class="text-h4 font-weight-bold">{{ leaveReport.pending_count }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Days Approved This Year</div><div class="text-h4 font-weight-bold">{{ leaveReport.total_days_approved }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Leave Days Taken by Type</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('leave-type', 'leave-days-by-type')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="leaveReport.by_type.length"
                    type="bar"
                    height="320"
                    :options="leaveTypeOptions"
                    :series="[{ name: 'Days', data: leaveReport.by_type.map((item) => Number(item.total_days)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Monthly Leave Trend</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('leave-trend', 'leave-trend')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(monthSeries(leaveReport.monthly, 'days'))"
                    type="line"
                    height="320"
                    :options="leaveTrendOptions"
                    :series="[{ name: 'Leave Days', data: monthSeries(leaveReport.monthly, 'days') }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Leave by Department</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('leave-department', 'leave-by-department')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="leaveReport.by_department.length"
                    type="bar"
                    height="320"
                    :options="leaveDepartmentOptions"
                    :series="[{ name: 'Leave Days', data: leaveReport.by_department.map((item) => Number(item.total_days)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article" />
        </v-window-item>

        <v-window-item value="payroll">
          <v-row v-if="!loading.payroll">
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Gross This Year (GHS)</div><div class="text-h5 font-weight-bold">{{ formatCurrency(payroll.year_total_gross) }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Net This Year (GHS)</div><div class="text-h5 font-weight-bold">{{ formatCurrency(payroll.year_total_net) }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Deductions This Year (GHS)</div><div class="text-h5 font-weight-bold">{{ formatCurrency(payroll.year_total_deductions) }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Monthly Payroll Summary</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('payroll-monthly', 'monthly-payroll-summary')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="payroll.monthly.length"
                    type="bar"
                    height="330"
                    :options="payrollMonthlyOptions"
                    :series="[
                      { name: 'Gross', data: monthSeries(payroll.monthly.map((item) => ({ month: item.period_month, total_gross: item.total_gross })), 'total_gross') },
                      { name: 'Net', data: monthSeries(payroll.monthly.map((item) => ({ month: item.period_month, total_net: item.total_net })), 'total_net') },
                      { name: 'Deductions', data: monthSeries(payroll.monthly.map((item) => ({ month: item.period_month, total_deductions: item.total_deductions })), 'total_deductions') }
                    ]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Average Salary by Department</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('payroll-department', 'average-salary-by-department')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="payroll.by_department.length"
                    type="bar"
                    height="320"
                    :options="payrollDepartmentOptions"
                    :series="[{ name: 'Average Salary', data: payroll.by_department.map((item) => Number(item.avg_salary)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article" />
        </v-window-item>

        <v-window-item value="recruitment">
          <v-row v-if="!loading.recruitment">
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Open Positions</div><div class="text-h4 font-weight-bold">{{ recruitment.open_jobs }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Applications</div><div class="text-h4 font-weight-bold">{{ recruitment.total_applications }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Hired This Year</div><div class="text-h4 font-weight-bold">{{ recruitment.total_hired }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="3">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Avg Time to Hire (days)</div><div class="text-h4 font-weight-bold">{{ recruitment.avg_time_to_hire }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Monthly Applications</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('recruitment-monthly', 'monthly-applications')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(monthSeries(recruitment.monthly_applications, 'total'))"
                    type="line"
                    height="320"
                    :options="recruitmentMonthlyOptions"
                    :series="[{ name: 'Applications', data: monthSeries(recruitment.monthly_applications, 'total') }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Applications by Source</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('recruitment-source', 'applications-by-source')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="recruitment.by_source.length"
                    type="donut"
                    height="320"
                    :options="recruitmentSourceOptions"
                    :series="recruitment.by_source.map((item) => Number(item.total))"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Applicants by Status</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('recruitment-status', 'applicants-by-status')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="recruitment.by_status.length"
                    type="bar"
                    height="320"
                    :options="recruitmentStatusOptions"
                    :series="[{ name: 'Applicants', data: recruitment.by_status.map((item) => Number(item.total)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article" />
        </v-window-item>

        <v-window-item value="expenses">
          <v-row v-if="!loading.expenses">
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Total Approved This Year (GHS)</div><div class="text-h5 font-weight-bold">{{ formatCurrency(expenses.total_year) }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Pending Expenses</div><div class="text-h4 font-weight-bold">{{ expenses.pending_count }}</div></v-card-text></v-card>
            </v-col>
            <v-col cols="12" sm="6" md="4">
              <v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="text-caption text-lightText">Top Category</div><div class="text-h5 font-weight-bold">{{ topExpenseCategory }}</div></v-card-text></v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Monthly Expense Total</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('expense-monthly', 'monthly-expense-total')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="hasValues(monthSeries(expenses.monthly, 'total'))"
                    type="line"
                    height="320"
                    :options="expenseMonthlyOptions"
                    :series="[{ name: 'Expenses', data: monthSeries(expenses.monthly, 'total') }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" lg="6">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Expenses by Category</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('expense-category', 'expenses-by-category')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="expenses.by_category.length"
                    type="donut"
                    height="320"
                    :options="expenseCategoryOptions"
                    :series="expenses.by_category.map((item) => Number(item.total))"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12">
              <v-card class="rounded-lg hr-card-shadow chart-card" variant="outlined" elevation="0">
                <v-card-item>
                  <template #title>Total Expenses by Department</template>
                  <template #append><v-btn icon="mdi-download" variant="text" @click="downloadChart('expense-department', 'expenses-by-department')" /></template>
                </v-card-item>
                <v-card-text>
                  <apexchart
                    v-if="expenses.by_department.length"
                    type="bar"
                    height="320"
                    :options="expenseDepartmentOptions"
                    :series="[{ name: 'Expenses', data: expenses.by_department.map((item) => Number(item.total)) }]"
                  />
                  <div v-else class="empty-state">No data available</div>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
          <v-skeleton-loader v-else type="article, article" />
        </v-window-item>
      </v-window>
    </v-card-text>
  </v-card>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.chart-card {
  min-height: 390px;
}

.empty-state {
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  border: 1px dashed rgba(100, 116, 139, 0.25);
  border-radius: 12px;
  background: linear-gradient(180deg, rgba(79, 110, 247, 0.03), rgba(247, 124, 79, 0.03));
}
</style>
