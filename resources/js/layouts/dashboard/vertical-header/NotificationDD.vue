<script setup>
import SvgSprite from '@/components/shared/SvgSprite.vue';

const notifications = [
  {
    title: '3 leave requests pending',
    detail: 'Needs your approval from managers',
    time: '5 min ago',
    icon: 'custom-calendar'
  },
  {
    title: '2 payroll adjustments submitted',
    detail: 'Review before payroll lock',
    time: '20 min ago',
    icon: 'custom-wallet'
  },
  {
    title: '1 attendance anomaly detected',
    detail: 'Late check-in flagged for today',
    time: '1 hour ago',
    icon: 'custom-clock-circle-outline'
  }
];
</script>

<template>
  <!-- ---------------------------------------------- -->
  <!-- notifications DD -->
  <!-- ---------------------------------------------- -->
  <v-menu :close-on-content-click="false" offset="6, 0">
    <template v-slot:activator="{ props }">
      <v-btn class="text-secondary ms-sm-2 ms-1" color="secondary" rounded="sm" variant="text" v-bind="props">
        <SvgSprite name="custom-notification" />
        <v-chip size="small" color="warning" class="ms-2 d-none d-md-inline-flex" variant="flat">3 leave requests pending</v-chip>
      </v-btn>
    </template>
    <v-sheet rounded="md" width="420" class="notification-dropdown py-6">
      <div class="d-flex align-center justify-space-between pb-4 px-6">
        <h5 class="text-h5 mb-0">HR Notifications</h5>
        <a href="#" class="text-primary link-hover text-h6"> Mark all read </a>
      </div>
      <perfect-scrollbar style="height: calc(100vh - 300px); max-height: 430px">
        <v-list class="py-0 px-6" lines="two" aria-label="notification list" aria-busy="true">
          <v-list-item
            v-for="(notification, index) in notifications"
            :key="index"
            color="secondary"
            class="no-spacer py-5 mb-3 px-3"
            rounded="md"
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
        <a href="#" class="text-primary text-h6 link-hover">View All</a>
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
</style>
