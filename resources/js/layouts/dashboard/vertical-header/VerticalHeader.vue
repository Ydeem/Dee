<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SvgSprite from '@/components/shared/SvgSprite.vue';
import { useCustomizerStore } from '../../../stores/customizer';

// dropdown imports
import NotificationDD from './NotificationDD.vue';
import ProfileDD from './ProfileDD.vue';
import Searchbar from './SearchBarPanel.vue';

type HeaderAuthUser = {
  avatar?: string | null
  avatar_url?: string | null
  initials?: string
};

const customizer = useCustomizerStore();
const page = usePage();
const hamburgerIcon = '/assets/images/icons/hamburger-menu.svg';
const quickActions = [
  { title: 'Add Employee', route: '/hr/employees/create' },
  { title: 'Post Job', route: '/hr/job-openings' },
  { title: 'Record Attendance', route: '/hr/attendance' },
  { title: 'Submit Expense', route: '/hr/expenses' }
];
const authUser = computed<HeaderAuthUser | null>(() => ((page.props as any)?.auth?.user as HeaderAuthUser) ?? null);
const pendingLeave = computed(() => Number((page.props as any)?.auth?.pending_leave ?? 0));
const priority = ref(customizer.setHorizontalLayout ? 0 : 0);
const profileMenuOpen = ref(false);
watch(priority, (newPriority) => {
  // yes, console.log() is a side effect
  priority.value = newPriority;
});

function goToPendingLeaves() {
  router.visit('/hr/leave-management?status=Pending');
}
</script>

<template>
  <v-app-bar elevation="0" :priority="priority" height="74" class="px-sm-10 px-5 hr-top-bar">
    <v-btn
      class="hidden-md-and-down me-5 ms-0"
      color="secondary"
      icon
      aria-label="sidebar button"
      rounded="sm"
      variant="tonal"
      @click.stop="customizer.SET_SIDEBAR_DRAWER()"
    >
      <img :src="hamburgerIcon" alt="Open menu" class="hamburger-icon" />
    </v-btn>
    <v-btn
      class="hidden-lg-and-up text-secondary"
      color="darkText"
      icon
      rounded="sm"
      variant="text"
      @click.stop="customizer.SET_SIDEBAR_DRAWER()"
      size="small"
    >
      <img :src="hamburgerIcon" alt="Open menu" class="hamburger-icon" />
    </v-btn>

    <!-- search mobile -->
    <v-menu :close-on-content-click="false" class="hidden-lg-and-up" offset="10, 0">
      <template v-slot:activator="{ props }">
        <v-btn class="hidden-lg-and-up ms-1" color="secondary" icon rounded="sm" variant="text" size="small" v-bind="props">
          <div class="text-lightText d-flex align-center">
            <SvgSprite name="custom-search" style="width: 16px; height: 16px" />
          </div>
        </v-btn>
      </template>
      <v-sheet class="search-sheet v-col-12 pa-0" elevation="24" width="320" rounded="md">
        <Searchbar />
      </v-sheet>
    </v-menu>

    <!-- ---------------------------------------------- -->
    <!-- Search part -->
    <!-- ---------------------------------------------- -->
    <v-sheet color="transparent" class="d-none d-lg-block search-host" width="224">
      <Searchbar />
    </v-sheet>

    <!---/Search part -->

    <v-spacer />
    <!-- ---------------------------------------------- -->
    <!---right part -->
    <!-- ---------------------------------------------- -->

    <v-chip
      size="small"
      :color="pendingLeave > 0 ? 'warning' : 'grey-lighten-2'"
      class="ms-2 d-none d-md-inline-flex font-weight-medium"
      variant="flat"
      style="cursor: pointer"
      @click.stop="goToPendingLeaves"
    >
      {{ pendingLeave }} Leave Requests Pending
    </v-chip>

    <!-- ---------------------------------------------- -->
    <!-- Quick Actions -->
    <!-- ---------------------------------------------- -->
    <v-menu offset="8, 0">
      <template v-slot:activator="{ props }">
        <v-btn class="ms-2" color="primary" rounded="sm" variant="tonal" v-bind="props"> + </v-btn>
      </template>
      <v-sheet rounded="md" width="220">
        <v-list density="comfortable" aria-label="quick actions">
          <v-list-item
            v-for="action in quickActions"
            :key="action.title"
            :value="action.title"
            color="primary"
            @click="router.visit(action.route)"
          >
            <v-list-item-title>{{ action.title }}</v-list-item-title>
          </v-list-item>
        </v-list>
      </v-sheet>
    </v-menu>

    <!-- ---------------------------------------------- -->
    <!-- Notification -->
    <!-- ---------------------------------------------- -->
    <NotificationDD />

    <!-- ---------------------------------------------- -->
    <!-- User Profile -->
    <!-- ---------------------------------------------- -->
    <v-menu v-model="profileMenuOpen" :close-on-content-click="false" offset="8, 0">
      <template v-slot:activator="{ props }">
        <v-btn class="profileBtn me-0" aria-label="profile" variant="text" rounded="circle" icon v-bind="props">
          <v-avatar class="py-2" size="40" rounded="circle" color="primary">
            <v-img v-if="authUser?.avatar_url || authUser?.avatar" :src="authUser?.avatar_url || authUser?.avatar || ''" cover />
            <span v-else class="text-white text-caption font-weight-bold">{{ authUser?.initials ?? 'U' }}</span>
          </v-avatar>
        </v-btn>
      </template>
      <v-sheet rounded="md" width="360">
        <ProfileDD />
      </v-sheet>
    </v-menu>
  </v-app-bar>
</template>

<style scoped>
.hr-top-bar {
  overflow: visible !important;
  z-index: 5000 !important;
}

.search-host {
  position: relative;
  z-index: 5200;
  overflow: visible !important;
}

:deep(.v-toolbar__content) {
  overflow: visible !important;
}

.hamburger-icon {
  width: 22px;
  height: 22px;
  display: block;
}
</style>
