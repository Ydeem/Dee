<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { useCustomizerStore } from '../../../stores/customizer';
import sidebarItems from './sidebarItem';
import { usePermissions } from '@/composables/usePermissions';

import NavGroup from './NavGroup/NavGroup.vue';
import NavItem from './NavItem/NavItem.vue';
import NavCollapse from './NavCollapse/NavCollapse.vue';
import Logo from '../logo/LogoMain.vue';

const customizer = useCustomizerStore();
const { canAny, isAdmin } = usePermissions();
const unreadMessages = ref(0);

function hasAccessToRoute(route?: string): boolean {
  if (!route || route === '/dashboard' || route === '/hr/dashboard') {
    return true;
  }

  if (route === '/hr/roles-permissions' || route === '/hr/settings' || route === '/hr/accounts') {
    return isAdmin.value;
  }

  const routeRules: Record<string, string[]> = {
    '/hr/employees': ['view employees'],
    '/hr/departments': ['view departments'],
    '/hr/designations': ['view designations'],
    '/hr/attendance': ['view attendance'],
    '/hr/leave-management': ['view leave', 'view leave requests'],
    '/hr/shifts': ['view shifts'],
    '/hr/payroll': ['view payroll'],
    '/hr/expenses': ['view expenses'],
    '/hr/job-openings': ['view recruitment', 'view job openings'],
    '/hr/applicants': ['view recruitment', 'view applicants'],
    '/hr/onboarding': ['view onboarding', 'manage onboarding'],
    '/hr/reports': ['view reports'],
    '/hr/messages': ['view messages', 'send messages'],
    '/hr/announcements': ['view announcements'],
  };

  const requiredPermissions = routeRules[route];
  if (!requiredPermissions?.length) {
    return true;
  }

  return canAny(...requiredPermissions);
}

const itemsWithBadges = computed(() =>
  sidebarItems.map((item) => {
    if (item.to === '/hr/messages') {
      return {
        ...item,
        chip: unreadMessages.value > 0 ? String(unreadMessages.value) : undefined,
        chipColor: unreadMessages.value > 0 ? 'error' : undefined,
        chipVariant: unreadMessages.value > 0 ? 'flat' : undefined,
      };
    }

    return item;
  })
);

function filterItems(items: typeof sidebarItems): typeof sidebarItems {
  const filtered: typeof sidebarItems = [];
  let pendingHeader: (typeof sidebarItems)[number] | null = null;
  let pendingDivider: (typeof sidebarItems)[number] | null = null;

  items.forEach((item) => {
    if (item.header) {
      pendingHeader = item;
      pendingDivider = null;
      return;
    }

    if (item.divider) {
      if (filtered.length > 0) {
        pendingDivider = item;
      }
      return;
    }

    let visibleItem: (typeof sidebarItems)[number] | null = item;

    if (item.children?.length) {
      const children = filterItems(item.children as typeof sidebarItems);
      visibleItem = children.length ? { ...item, children } : null;
    } else if (item.to && !hasAccessToRoute(item.to)) {
      visibleItem = null;
    }

    if (!visibleItem) {
      return;
    }

    if (pendingHeader) {
      filtered.push(pendingHeader);
      pendingHeader = null;
    }

    if (pendingDivider) {
      filtered.push(pendingDivider);
      pendingDivider = null;
    }

    filtered.push(visibleItem);
  });

  while (filtered.length && filtered[filtered.length - 1].divider) {
    filtered.pop();
  }

  return filtered;
}

async function fetchUnreadCount() {
  try {
    const { data } = await axios.get('/api/hr/messages/inbox', {
      params: { per_page: 1 },
    });

    unreadMessages.value = Number(data?.unread_count ?? 0);
  } catch {
    unreadMessages.value = 0;
  }
}

let unreadInterval: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
  fetchUnreadCount();
  unreadInterval = setInterval(fetchUnreadCount, 60000);
});

onUnmounted(() => {
  if (unreadInterval) {
    clearInterval(unreadInterval);
  }
});

const sidebarMenu = computed(() => filterItems(itemsWithBadges.value as typeof sidebarItems));
</script>

<template>
  <v-navigation-drawer
    left
    v-model="customizer.Sidebar_drawer"
    elevation="0"
    rail-width="90"
    mobile-breakpoint="lg"
    width="279"
    app
    class="leftSidebar"
    :rail="customizer.mini_sidebar"
    expand-on-hover
  >
    <!---Logo part -->

    <div class="pa-5">
      <Logo />
    </div>
    <!-- ---------------------------------------------- -->
    <!---Navigation -->
    <!-- ---------------------------------------------- -->
    <perfect-scrollbar class="scrollnavbar" :options="{ suppressScrollX: true }">
      <v-list aria-busy="true" class="px-2" aria-label="menu list">
        <!---Menu Loop -->
        <template v-for="(item, i) in sidebarMenu" :key="i">
          <!---Item Sub Header -->
          <NavGroup :item="item" v-if="item.header" :key="item.title" />
          <!---Item Divider -->
          <v-divider class="my-3" v-else-if="item.divider" />
          <!---If Has Child -->
          <NavCollapse class="leftPadding" :item="item" :level="0" v-else-if="item.children" />
          <!---Single Item-->
          <NavItem :item="item" v-else />
          <!---End Single Item-->
        </template>
      </v-list>
    </perfect-scrollbar>
  </v-navigation-drawer>
</template>
