<template>
  <DashboardLayout>
    <BaseBreadcrumb
      title="HR Dashboard"
      subtitle="Overview of your workforce"
      :breadcrumbs="breadcrumbs"
    />

    <v-container fluid class="pa-0 mt-4">
      <v-skeleton-loader
        v-if="loading"
        type="card, card, card, table"
      />

      <template v-else>
        <!-- Welcome pills -->
        <v-row class="mb-4" dense>
          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">
                    Employees on Leave Today
                  </div>
                  <div class="text-h4 font-weight-bold">
                    {{ summary.on_leave }}
                  </div>
                </div>
                <v-avatar color="primary" variant="tonal">
                  <v-icon>mdi-beach</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">
                    Pending Approvals
                  </div>
                  <div class="text-h4 font-weight-bold">
                    {{ summary.pending_approvals }}
                  </div>
                </div>
                <v-avatar color="warning" variant="tonal">
                  <v-icon>mdi-alert-circle</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-text class="d-flex justify-space-between align-center">
                <div>
                  <div class="text-caption text-medium-emphasis mb-1">
                    Open Job Positions
                  </div>
                  <div class="text-h4 font-weight-bold">
                    {{ summary.open_positions }}
                  </div>
                </div>
                <v-avatar color="success" variant="tonal">
                  <v-icon>mdi-briefcase</v-icon>
                </v-avatar>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- KPI cards & attendance -->
        <v-row dense>
          <v-col cols="12" md="8">
            <v-row dense>
              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      Total Employees
                    </div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">
                        {{ stats?.total_employees?.value ?? 0 }}
                      </div>
                      <v-chip size="small" color="primary" variant="tonal">
                        {{ stats?.total_employees?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      On Leave Today
                    </div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">
                        {{ stats?.on_leave_today?.value ?? 0 }}
                      </div>
                      <v-chip size="small" color="info" variant="tonal">
                        {{ stats?.on_leave_today?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      Open Positions
                    </div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">
                        {{ stats?.open_positions?.value ?? 0 }}
                      </div>
                      <v-chip size="small" color="success" variant="tonal">
                        {{ stats?.open_positions?.change }}
                      </v-chip>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12" sm="6">
                <v-card variant="outlined" class="rounded-lg mb-3">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      Pending Approvals
                    </div>
                    <div class="d-flex align-center justify-space-between mt-2">
                      <div class="text-h4 font-weight-bold">
                        {{ stats?.pending_approvals?.value ?? 0 }}
                      </div>
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
                  {{ currentRate }}% · {{ rateChange }}
                </v-chip>
              </v-card-title>
              <v-card-text>
                <!-- You can replace this with your chart component -->
                <div class="text-caption text-medium-emphasis mb-2">
                  Attendance & leave rate for the last 12 months.
                </div>
                <div class="text-caption">
                  Months: {{ chartCategories.join(', ') }}
                </div>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card variant="outlined" class="rounded-lg mb-4">
              <v-card-title>HR Tasks & Approvals</v-card-title>
              <v-card-text>
                <div class="mb-2 text-body-2">
                  <span class="font-weight-medium">
                    {{ taskCompleted }} of {{ taskTotal }} tasks completed today
                  </span>
                </div>
                <v-progress-linear
                  :model-value="taskPercent"
                  color="primary"
                  height="8"
                  rounded
                  class="mb-4"
                />

                <div
                  v-for="action in pendingActions"
                  :key="action.id"
                  class="mb-3 py-2 px-2 rounded cursor-pointer d-flex justify-space-between align-center"
                  @click="router.visit(action.link)"
                  style="cursor: pointer"
                >
                  <div>
                    <div class="text-body-2 font-weight-medium">
                      {{ action.title }}
                    </div>
                    <div class="text-caption text-medium-emphasis">
                      {{ action.subtitle }}
                    </div>
                  </div>
                  <v-chip :color="action.color" size="small" variant="tonal">
                    {{ action.status }}
                  </v-chip>
                </div>
              </v-card-text>
            </v-card>

            <v-card variant="outlined" class="rounded-lg">
              <v-card-title>Upcoming Events</v-card-title>
              <v-card-text>
                <div
                  v-if="!upcomingEvents.length"
                  class="text-caption text-medium-emphasis"
                >
                  No upcoming events.
                </div>

                <div
                  v-for="event in upcomingEvents"
                  :key="event.title + event.date"
                  class="mb-3"
                >
                  <v-chip size="x-small" :color="event.color" class="mr-2" variant="tonal">
                    {{ event.category }}
                  </v-chip>
                  <span class="font-weight-medium">
                    {{ event.title }}
                  </span>
                  <span class="text-caption text-medium-emphasis ml-2">
                    {{ event.date }}
                  </span>
                </div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>

        <!-- Recent hires -->
        <v-row class="mt-4">
          <v-col cols="12">
            <v-card variant="outlined" class="rounded-lg">
              <v-card-title>Recent Hires</v-card-title>
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
                    <tr
                      v-for="hire in recentHires"
                      :key="hire.id"
                      @click="router.visit(`/hr/employees/${hire.id}`)"
                      style="cursor: pointer"
                    >
                      <td>
                        <div class="d-flex align-center ga-3">
                          <v-avatar
                            size="32"
                            :color="hire.avatar_url ? undefined : 'primary'"
                          >
                            <v-img
                              v-if="hire.avatar_url"
                              :src="hire.avatar_url"
                            />
                            <span
                              v-else
                              class="text-white text-caption"
                            >
                              {{ hire.initials }}
                            </span>
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
  </DashboardLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Dashboard', disabled: true, href: '#' }
];

const summary = ref({
  on_leave: 0,
  pending_approvals: 0,
  open_positions: 0
});
const stats = ref<any | null>(null);
const chartSeries = ref<any[]>([]);
const chartCategories = ref<string[]>([]);
const currentRate = ref(0);
const rateChange = ref('0%');
const pendingActions = ref<any[]>([]);
const taskTotal = ref(0);
const taskCompleted = ref(0);
const taskPercent = ref(0);
const recentHires = ref<any[]>([]);
const upcomingEvents = ref<any[]>([]);
const loading = ref(true);

onMounted(async () => {
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
  const { data } = await axios.get('/api/hr/dashboard/summary');
  summary.value = data;
}

async function fetchStats() {
  const { data } = await axios.get('/api/hr/dashboard/stats');
  stats.value = data;
}

async function fetchAttendanceChart() {
  const { data } = await axios.get('/api/hr/dashboard/attendance-chart');
  chartSeries.value = [
    { name: 'Attendance Rate %', data: data.attendance },
    { name: 'Leave Rate %', data: data.leave }
  ];
  chartCategories.value = data.months;
  currentRate.value = data.current_rate;
  rateChange.value = data.change;
}

async function fetchPendingActions() {
  const { data } = await axios.get('/api/hr/dashboard/pending-actions');
  pendingActions.value = data.actions;
  taskTotal.value = data.total;
  taskCompleted.value = data.completed;
  taskPercent.value = data.percent;
}

async function fetchRecentHires() {
  const { data } = await axios.get('/api/hr/dashboard/recent-hires');
  recentHires.value = data.recent_hires;
}

async function fetchUpcomingEvents() {
  const { data } = await axios.get('/api/hr/dashboard/upcoming-events');
  upcomingEvents.value = data.events;
}
</script>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
