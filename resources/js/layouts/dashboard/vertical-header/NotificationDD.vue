<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import SvgSprite from '@/components/shared/SvgSprite.vue';

type NotificationItem = {
  id: string
  title: string
  detail: string
  time: string
  icon: string
  link: string
};

const menuOpen = ref(false);
const pendingLeaveCount = ref(3);
const notifications = ref<NotificationItem[]>([
  {
    id: 'leave-pending',
    title: '3 leave requests pending',
    detail: 'Needs your approval from managers',
    time: '5 min ago',
    icon: 'custom-calendar',
    link: '/hr/leave-management'
  },
  {
    id: 'payroll-adjustment',
    title: '2 payroll adjustments submitted',
    detail: 'Review before payroll lock',
    time: '20 min ago',
    icon: 'custom-wallet',
    link: '/hr/payroll'
  },
  {
    id: 'attendance-anomaly',
    title: '1 attendance anomaly detected',
    detail: 'Late check-in flagged for today',
    time: '1 hour ago',
    icon: 'custom-clock-circle-outline',
    link: '/hr/attendance'
  }
]);

const pendingChipLabel = computed(() => {
  const count = Math.max(0, Number(pendingLeaveCount.value || 0));
  return `${count} Leave Requests Pending`;
});

async function fetchPendingLeaveCount() {
  try {
    const { data } = await axios.get('/api/hr/reports/leave', {
      params: { year: new Date().getFullYear() }
    });
    pendingLeaveCount.value = Number(data?.pending_count ?? 0);

    notifications.value = notifications.value.map((item) =>
      item.id === 'leave-pending'
        ? { ...item, title: `${pendingLeaveCount.value} leave requests pending` }
        : item
    );
  } catch (error) {
    // Keep fallback values if API fails.
  }
}

function openNotification(item: NotificationItem) {
  menuOpen.value = false;
  router.visit(item.link);
}

function goToPendingLeaves() {
  menuOpen.value = false;
  router.visit('/hr/leave-management');
}

function markAllRead() {
  notifications.value = notifications.value.map((item) => ({
    ...item,
    time: 'Read'
  }));
}

function viewAllNotifications() {
  menuOpen.value = false;
  router.visit('/hr/dashboard');
}

onMounted(() => {
  fetchPendingLeaveCount();
});
</script>

<template>
  <!-- ---------------------------------------------- -->
  <!-- notifications DD -->
  <!-- ---------------------------------------------- -->
  <v-menu v-model="menuOpen" :close-on-content-click="false" offset="6, 0">
    <template v-slot:activator="{ props }">
      <v-btn class="text-secondary ms-sm-2 ms-1" color="secondary" rounded="sm" variant="text" v-bind="props">
        <SvgSprite name="custom-notification" />
        <v-chip
          size="small"
          color="warning"
          class="ms-2 d-none d-md-inline-flex"
          variant="flat"
          @click.stop="goToPendingLeaves"
        >
          {{ pendingChipLabel }}
        </v-chip>
      </v-btn>
    </template>
    <v-sheet rounded="md" width="420" class="notification-dropdown py-6">
      <div class="d-flex align-center justify-space-between pb-4 px-6">
        <h5 class="text-h5 mb-0">HR Notifications</h5>
        <button type="button" class="plain-link-btn text-primary link-hover text-h6 text-decoration-none" @click="markAllRead">
          Mark all read
        </button>
      </div>
      <perfect-scrollbar style="height: calc(100vh - 300px); max-height: 430px">
        <v-list class="py-0 px-6" lines="two" aria-label="notification list" aria-busy="true">
          <v-list-item
            v-for="notification in notifications"
            :key="notification.id"
            color="secondary"
            class="no-spacer py-5 mb-3 px-3 cursor-pointer"
            rounded="md"
            @click="openNotification(notification)"
          >
            <template v-slot:prepend>
              <v-avatar size="40" variant="tonal" color="primary" class="me-3 py-2">
                <SvgSprite :name="notification.icon" />
              </v-avatar>
            </template>
            <div class="d-inline-flex justify-space-between w-100">
              <h6 class="text-h6 text-lightText mb-0">{{ notification.title }}</h6>
            </div>
            <p class="text-caption text-lightText my-0">{{ notification.detail }}</p>
            <p class="text-caption text-lightText my-0">{{ notification.time }}</p>
          </v-list-item>
        </v-list>
      </perfect-scrollbar>
      <div class="pt-2 text-center">
        <button type="button" class="plain-link-btn text-primary text-h6 link-hover text-decoration-none" @click="viewAllNotifications">
          View All
        </button>
      </div>
    </v-sheet>
  </v-menu>
</template>

<style lang="scss">
.v-tooltip {
  > .v-overlay__content.custom-tooltip {
    padding: 2px 6px;
  }
}

.plain-link-btn {
  background: transparent;
  border: 0;
  cursor: pointer;
  padding: 0;
  font: inherit;
}
</style>
