<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

type Hire = {
  id: number
  full_name: string
  avatar_url?: string | null
  initials: string
  department: string
  join_date: string
  status: string
}

type HrEvent = {
  category: string
  color?: string
  title: string
  date: string
}

const isLoadingHires = ref(true);
const isLoadingEvents = ref(true);
const recentHires = ref<Hire[]>([]);
const upcomingEvents = ref<HrEvent[]>([]);

function statusColor(status: string) {
  if (status === 'Active') return 'success';
  if (status === 'Probation') return 'warning';
  return 'primary';
}

function categoryColor(category: string) {
  if (category === 'Leave') return 'primary';
  if (category === 'Deadline') return 'error';
  if (category === 'Onboarding') return 'success';
  return 'warning';
}

function eventRoute(category: string) {
  if (category === 'Leave') return '/hr/leave-management';
  if (category === 'Onboarding') return '/hr/onboarding';
  return '/hr/payroll';
}

async function fetchRecentHires() {
  isLoadingHires.value = true;

  try {
    const { data } = await axios.get('/api/hr/dashboard/recent-hires');
    recentHires.value = Array.isArray(data?.recent_hires) ? data.recent_hires : [];
  } catch (error) {
    console.error('Recent hires fetch failed', error);
    recentHires.value = [];
  } finally {
    isLoadingHires.value = false;
  }
}

async function fetchUpcomingEvents() {
  isLoadingEvents.value = true;

  try {
    const { data } = await axios.get('/api/hr/dashboard/upcoming-events');
    upcomingEvents.value = Array.isArray(data?.events) ? data.events : [];
  } catch (error) {
    console.error('Events fetch failed', error);
    upcomingEvents.value = [];
  } finally {
    isLoadingEvents.value = false;
  }
}

onMounted(() => {
  fetchRecentHires();
  fetchUpcomingEvents();
});
</script>

<template>
  <v-row class="mb-0">
    <v-col cols="12" lg="7">
      <v-skeleton-loader v-if="isLoadingHires" type="table" class="rounded-lg" />
      <v-card v-else variant="outlined" elevation="0" class="bg-surface hr-card-shadow" rounded="lg">
        <v-card-item>
          <v-card-title class="text-h5">Recent Hires</v-card-title>
        </v-card-item>
        <v-divider />
        <v-table density="comfortable">
          <thead>
            <tr>
              <th class="text-left">Employee</th>
              <th class="text-left">Department</th>
              <th class="text-left">Join Date</th>
              <th class="text-left">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!recentHires.length">
              <td colspan="4" class="text-center text-lightText py-6">No recent hires available.</td>
            </tr>
            <tr v-for="hire in recentHires" :key="hire.id" class="cursor-pointer" @click="router.visit(`/hr/employees/${hire.id}`)">
              <td>
                <div class="d-flex align-center">
                  <v-avatar size="34" color="primary" variant="tonal" class="me-2">
                    <img v-if="hire.avatar_url" :src="hire.avatar_url" :alt="hire.full_name" />
                    <span v-else class="text-caption font-weight-bold">{{ hire.initials }}</span>
                  </v-avatar>
                  <span class="font-weight-medium">{{ hire.full_name }}</span>
                </div>
              </td>
              <td>{{ hire.department }}</td>
              <td>{{ hire.join_date }}</td>
              <td>
                <v-chip :color="statusColor(hire.status)" size="small" rounded="md" variant="tonal">{{ hire.status }}</v-chip>
              </td>
            </tr>
          </tbody>
        </v-table>
        <v-card-actions class="justify-end px-6 pb-5">
          <button class="link-button text-primary font-weight-medium" @click="router.visit('/hr/employees')">View All Employees -></button>
        </v-card-actions>
      </v-card>
    </v-col>

    <v-col cols="12" lg="5">
      <v-skeleton-loader v-if="isLoadingEvents" type="list-item-three-line" class="rounded-lg" />
      <v-card v-else variant="outlined" elevation="0" class="bg-surface hr-card-shadow" rounded="lg">
        <v-card-item>
          <v-card-title class="text-h5">Upcoming HR Events</v-card-title>
        </v-card-item>
        <v-divider />
        <v-list class="py-2 px-4">
          <v-list-item v-if="!upcomingEvents.length">
            <v-list-item-title class="text-lightText">No upcoming HR events.</v-list-item-title>
          </v-list-item>
          <v-list-item
            v-for="(event, index) in upcomingEvents"
            :key="index"
            rounded="md"
            class="px-2 cursor-pointer"
            @click="router.visit(eventRoute(event.category))"
          >
            <template #prepend>
              <v-chip :color="event.color ?? categoryColor(event.category)" variant="tonal" size="small" rounded="md" class="me-2">
                {{ event.category }}
              </v-chip>
            </template>
            <v-list-item-title class="font-weight-medium">{{ event.title }}</v-list-item-title>
            <v-list-item-subtitle>{{ event.date }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
        <v-card-actions class="justify-end px-6 pb-5">
          <button class="link-button text-primary font-weight-medium" @click="router.visit('/hr/attendance')">View HR Calendar -></button>
        </v-card-actions>
      </v-card>
    </v-col>
  </v-row>
</template>

<style lang="scss">
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}

.link-button {
  background: transparent;
  border: 0;
  padding: 0;
  cursor: pointer;
}
</style>
