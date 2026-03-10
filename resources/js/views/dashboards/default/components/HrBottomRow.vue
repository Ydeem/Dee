<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';

type Hire = {
  name: string;
  avatar?: string;
  department: string;
  join_date: string;
  status: 'Active' | 'Probation' | 'Onboarding' | string;
};

type HrEvent = {
  category: 'Meeting' | 'Deadline' | 'Review' | 'Holiday' | string;
  title: string;
  date: string;
};

const isLoadingHires = ref(true);
const isLoadingEvents = ref(true);

const recentHires = ref<Hire[]>([
  { name: 'Sarah Oti', department: 'People Operations', join_date: 'Mar 03, 2026', status: 'Onboarding' },
  { name: 'Daniel Kofi', department: 'Engineering', join_date: 'Mar 01, 2026', status: 'Active' },
  { name: 'Amanda Boateng', department: 'Finance', join_date: 'Feb 26, 2026', status: 'Probation' },
  { name: 'Kwame Asare', department: 'Customer Success', join_date: 'Feb 25, 2026', status: 'Active' },
  { name: 'Naana Mensah', department: 'Product', join_date: 'Feb 22, 2026', status: 'Onboarding' }
]);

const upcomingEvents = ref<HrEvent[]>([
  { category: 'Meeting', title: 'All-hands HR Meeting', date: 'Mar 12, 2026' },
  { category: 'Deadline', title: 'Payroll Cutoff', date: 'Mar 14, 2026' },
  { category: 'Review', title: 'Q1 Performance Reviews', date: 'Mar 20, 2026' },
  { category: 'Holiday', title: 'Public Holiday', date: 'Mar 25, 2026' },
  { category: 'Meeting', title: 'Onboarding Kickoff', date: 'Mar 27, 2026' }
]);

function statusColor(status: string) {
  if (status === 'Active') return 'success';
  if (status === 'Probation') return 'warning';
  return 'primary';
}

function categoryColor(category: string) {
  if (category === 'Meeting') return 'primary';
  if (category === 'Deadline') return 'error';
  if (category === 'Review') return 'warning';
  return 'success';
}

function initials(name: string) {
  const segments = name.split(' ').filter(Boolean);
  return segments.slice(0, 2).map((segment) => segment[0]).join('').toUpperCase();
}

async function loadRecentHires() {
  isLoadingHires.value = true;
  try {
    const { data } = await axios.get('/api/hr/dashboard/recent-hires');
    const hires = Array.isArray(data) ? data : data?.hires;
    if (Array.isArray(hires) && hires.length) {
      recentHires.value = hires.slice(0, 5).map((hire: any) => ({
        name: hire.name ?? hire.full_name ?? 'Unknown',
        avatar: hire.avatar,
        department: hire.department ?? 'N/A',
        join_date: hire.join_date ?? hire.joinDate ?? '-',
        status: hire.status ?? 'Onboarding'
      }));
    }
  } catch (error) {
    recentHires.value = recentHires.value.slice(0, 5);
  } finally {
    isLoadingHires.value = false;
  }
}

async function loadUpcomingEvents() {
  isLoadingEvents.value = true;
  try {
    const { data } = await axios.get('/api/hr/dashboard/upcoming-events');
    const events = Array.isArray(data) ? data : data?.events;
    if (Array.isArray(events) && events.length) {
      upcomingEvents.value = events.slice(0, 5).map((event: any) => ({
        category: event.category ?? 'Meeting',
        title: event.title ?? 'Untitled Event',
        date: event.date ?? '-'
      }));
    }
  } catch (error) {
    upcomingEvents.value = upcomingEvents.value.slice(0, 5);
  } finally {
    isLoadingEvents.value = false;
  }
}

onMounted(() => {
  loadRecentHires();
  loadUpcomingEvents();
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
            <tr v-for="(hire, index) in recentHires" :key="index">
              <td>
                <div class="d-flex align-center">
                  <v-avatar size="34" color="primary" variant="tonal" class="me-2">
                    <img v-if="hire.avatar" :src="hire.avatar" :alt="hire.name" />
                    <span v-else class="text-caption font-weight-bold">{{ initials(hire.name) }}</span>
                  </v-avatar>
                  <span class="font-weight-medium">{{ hire.name }}</span>
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
          <a href="#" class="text-primary text-decoration-none font-weight-medium">View All Employees -></a>
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
          <v-list-item v-for="(event, index) in upcomingEvents" :key="index" rounded="md" class="px-2">
            <template v-slot:prepend>
              <v-chip :color="categoryColor(event.category)" variant="tonal" size="small" rounded="md" class="me-2">
                {{ event.category }}
              </v-chip>
            </template>
            <v-list-item-title class="font-weight-medium">{{ event.title }}</v-list-item-title>
            <v-list-item-subtitle>{{ event.date }}</v-list-item-subtitle>
          </v-list-item>
        </v-list>
        <v-card-actions class="justify-end px-6 pb-5">
          <a href="#" class="text-primary text-decoration-none font-weight-medium">View HR Calendar -></a>
        </v-card-actions>
      </v-card>
    </v-col>
  </v-row>
</template>

<style lang="scss">
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}
</style>
