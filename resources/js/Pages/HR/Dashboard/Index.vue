<template>
  <DashboardLayout>
    <template v-if="isEmployeeOnly">
      <BaseBreadcrumb title="My Dashboard" subtitle="Self-service overview" :breadcrumbs="breadcrumbs" />
      <EmployeeSelfServiceDashboard />
    </template>

    <template v-else>
    <BaseBreadcrumb title="HR Dashboard" subtitle="Overview of your workforce" :breadcrumbs="breadcrumbs" />

    <v-container fluid class="pa-0 mt-4">
      <v-skeleton-loader v-if="loading" type="card, card, card, table" />

      <template v-else>
        <v-row class="mb-4" dense>
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg cursor-pointer" @click="goToLeaveToday">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">Employees on Leave Today</div>
                  <div class="text-h4 font-weight-bold">{{ summary.on_leave }}</div>
                </div>
                <v-avatar color="primary" variant="tonal">
                  <v-icon>mdi-beach</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg cursor-pointer" @click="goToPendingApprovals">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">Pending Approvals</div>
                  <div class="text-h4 font-weight-bold">{{ summary.pending_approvals }}</div>
                </div>
                <v-avatar color="warning" variant="tonal">
                  <v-icon>mdi-alert-circle</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg cursor-pointer" @click="goToJobs">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">Open Job Positions</div>
                  <div class="text-h4 font-weight-bold">{{ summary.open_positions }}</div>
                </div>
                <v-avatar color="success" variant="tonal">
                  <v-icon>mdi-briefcase</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row dense>
          <v-col cols="12" md="8">
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3 cursor-pointer" @click="goToEmployees">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">Total Employees</div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">{{ stats?.total_employees?.value ?? 0 }}</div>
                      <v-chip size="small" color="primary" variant="tonal">
                        {{ stats?.total_employees?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3 cursor-pointer" @click="goToLeaveToday">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">On Leave Today</div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">{{ stats?.on_leave_today?.value ?? 0 }}</div>
                      <v-chip size="small" color="info" variant="tonal">
                        {{ stats?.on_leave_today?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3 cursor-pointer" @click="goToJobs">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">Open Positions</div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">{{ stats?.open_positions?.value ?? 0 }}</div>
                      <v-chip size="small" color="success" variant="tonal">
                        {{ stats?.open_positions?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3 cursor-pointer" @click="goToPendingApprovals">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">Pending Approvals</div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">{{ stats?.pending_approvals?.value ?? 0 }}</div>
                      <v-chip size="small" color="warning" variant="tonal">
                        {{ stats?.pending_approvals?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <v-card variant="outlined" class="rounded-lg">
              <v-card-title class="d-flex justify-space-between align-center">
                <span>Attendance Overview</span>
                <v-chip size="small" color="primary" variant="tonal">
                  {{ currentAttendanceRate }}% · {{ attendanceChange }}
                </v-chip>
              </v-card-title>
              <v-card-text>
                <apexchart
                  v-if="chartSeries.length"
                  type="area"
                  height="300"
                  :options="chartOptions"
                  :series="chartSeries"
                />
                <div
                  v-else
                  class="d-flex flex-column align-center justify-center text-medium-emphasis"
                  style="height: 300px"
                >
                  <v-icon size="42">mdi-chart-line</v-icon>
                  <div class="text-caption mt-2">No attendance data available</div>
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg mb-4">
              <v-card-title>HR Tasks & Approvals</v-card-title>
              <v-card-text>
                <div class="mb-2 text-body-2">
                  <span class="font-weight-medium">{{ taskCompleted }} of {{ taskTotal }} tasks completed today</span>
                </div>
                <v-progress-linear :model-value="taskPercent" color="primary" height="8" rounded class="mb-4" />

                <div
                  v-for="action in pendingActions"
                  :key="action.id"
                  class="mb-3 py-2 px-2 rounded cursor-pointer d-flex justify-space-between align-center"
                  @click="goToAction(action)"
                >
                  <div>
                    <div class="text-body-2 font-weight-medium">{{ action.title }}</div>
                    <div class="text-caption text-medium-emphasis">{{ action.subtitle }}</div>
                  </div>
                  <v-chip :color="action.color" size="small" variant="tonal">{{ action.status }}</v-chip>
                </div>

                <div class="mt-2 text-right">
                  <v-btn variant="text" size="small" @click="router.visit('/hr/attendance')">View HR Calendar →</v-btn>
                </div>
              </v-card-text>
            </v-card>

            <v-card variant="outlined" class="rounded-lg">
              <v-card-title>Upcoming Events</v-card-title>
              <v-card-text>
                <div v-if="!upcomingEvents.length" class="text-caption text-medium-emphasis">No upcoming events.</div>

                <div
                  v-for="event in upcomingEvents"
                  :key="event.title + event.date"
                  class="mb-3 cursor-pointer"
                  @click="goToEventLink(event.link)"
                >
                  <v-chip size="x-small" :color="event.color" class="mr-2" variant="tonal">
                    {{ event.category }}
                  </v-chip>
                  <span class="font-weight-medium">{{ event.title }}</span>
                  <span class="text-caption text-medium-emphasis ml-2">{{ event.date }}</span>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <v-row class="mt-4">
          <v-col cols="12">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-title class="d-flex justify-space-between align-center">
                <span>Recent Hires</span>
                <v-btn variant="text" size="small" @click="router.visit('/hr/employees')">View All Employees →</v-btn>
              </v-card-title>
              <v-card-text>
                <v-table density="compact">
                  <thead>
                    <tr>
                      <th>Employee</th>
                      <th>Department</th>
                      <th>Join Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="hire in recentHires" :key="hire.id" class="cursor-pointer" @click="viewEmployee(hire.id)">
                      <td>
                        <div class="d-flex align-center ga-3">
                          <v-avatar size="32" :color="hire.avatar_url ? undefined : 'primary'">
                            <v-img v-if="hire.avatar_url" :src="hire.avatar_url" />
                            <span v-else class="text-white text-caption">{{ hire.initials }}</span>
                          </v-avatar>
                          <span>{{ hire.full_name }}</span>
                        </div>
                      </td>
                      <td>{{ hire.department }}</td>
                      <td>{{ hire.join_date }}</td>
                      <td>
                        <v-chip
                          size="small"
                          :color="
                            hire.status === 'Active'
                              ? 'success'
                              : hire.status === 'Probation'
                                ? 'warning'
                                : hire.status === 'On Leave'
                                  ? 'info'
                                  : 'default'
                          "
                          variant="tonal"
                        >
                          {{ hire.status }}
                        </v-chip>
                      </td>
                    </tr>
                  </tbody>
                </v-table>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </template>
    </v-container>
    </template>
  </DashboardLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import EmployeeSelfServiceDashboard from '@/views/hr/dashboard/EmployeeSelfServiceDashboard.vue';
import { usePermissions } from '@/composables/usePermissions';

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Dashboard', disabled: true, href: '#' }
];

const { can, hasRole } = usePermissions();
const isEmployeeOnly = computed(() =>
  !can('view employees') && hasRole('Employee')
);

const summary = ref({
  on_leave: 0,
  pending_approvals: 0,
  open_positions: 0
});
const stats = ref<any | null>(null);
const chartSeries = ref<any[]>([]);
const chartCategories = ref<string[]>([]);
const currentAttendanceRate = ref(0);
const attendanceChange = ref('0%');
const attendanceTrend = ref<'up' | 'down' | 'neutral'>('neutral');
const pendingActions = ref<any[]>([]);
const taskTotal = ref(0);
const taskCompleted = ref(0);
const taskPercent = ref(0);
const recentHires = ref<any[]>([]);
const upcomingEvents = ref<any[]>([]);
const loading = ref(true);

const chartOptions = computed(() => ({
  chart: {
    type: 'area',
    toolbar: { show: false },
    sparkline: { enabled: false }
  },
  stroke: { curve: 'smooth', width: 2 },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.05
    }
  },
  colors: ['#4f6ef7', '#f77c4f'],
  xaxis: {
    categories: chartCategories.value,
    labels: { style: { fontSize: '11px' } }
  },
  yaxis: {
    min: 0,
    max: 100,
    labels: {
      formatter: (v: number) => `${v}%`
    }
  },
  tooltip: {
    y: {
      formatter: (v: number) => `${v}%`
    }
  },
  legend: { position: 'top' },
  dataLabels: { enabled: false },
  grid: { borderColor: '#f5f5f5' }
}));

onMounted(async () => {
  if (isEmployeeOnly.value) {
    loading.value = false;
    return;
  }

  try {
    await Promise.all([
      fetchSummary(),
      fetchStats(),
      fetchAttendanceChart(),
      fetchPendingActions(),
      fetchRecentHires(),
      fetchUpcomingEvents()
    ]);
  } finally {
    loading.value = false;
  }
});

async function fetchSummary() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/summary');
    summary.value = data;
  } catch (e) {
    console.error(e);
  }
}

async function fetchStats() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/stats');
    stats.value = data;
  } catch (e) {
    console.error(e);
  }
}

async function fetchAttendanceChart() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/attendance-chart');
    chartSeries.value = [
      { name: 'Attendance %', data: data.attendance ?? [] },
      { name: 'Leave %', data: data.leave ?? [] }
    ];
    chartCategories.value = data.months ?? [];
    currentAttendanceRate.value = data.current_rate ?? 0;
    attendanceChange.value = data.change ?? '0%';
    attendanceTrend.value = data.trend ?? 'neutral';
  } catch (e) {
    console.error(e);
  }
}

async function fetchPendingActions() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/pending-actions');
    pendingActions.value = data.actions ?? [];
    taskTotal.value = data.total ?? 0;
    taskCompleted.value = data.completed ?? 0;
    taskPercent.value = data.percent ?? 0;
  } catch (e) {
    console.error(e);
  }
}

async function fetchRecentHires() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/recent-hires');
    recentHires.value = data.recent_hires ?? [];
  } catch (e) {
    console.error(e);
  }
}

async function fetchUpcomingEvents() {
  try {
    const { data } = await axios.get('/api/hr/dashboard/upcoming-events');
    upcomingEvents.value = data.events ?? [];
  } catch (e) {
    console.error(e);
  }
}

function goToEmployees() {
  router.visit('/hr/employees');
}

function goToLeave() {
  router.visit('/hr/leave-management');
}

function goToLeaveToday() {
  router.visit('/hr/leave-management?status=Approved&today=1');
}

function goToPendingApprovals() {
  router.visit('/hr/leave-management?status=Pending');
}

function goToJobs() {
  router.visit('/hr/job-openings');
}

function quickAddEmployee() {
  router.visit('/hr/employees/create');
}

function quickPostJob() {
  router.visit('/hr/job-openings');
}

function quickRecordAttendance() {
  router.visit('/hr/attendance');
}

function viewEmployee(id: number) {
  router.visit(`/hr/employees/${id}`);
}

function goToEventLink(link: string) {
  if (!link) return;
  router.visit(link);
}

function goToAction(action: any) {
  if (!action?.link) return;
  router.visit(action.link);
}
</script>
