<script setup lang="ts">
import { computed } from 'vue';
import { useCustomizerStore } from '../../../stores/customizer';
import sidebarItems from './sidebarItem';
import { usePermissions } from '@/composables/usePermissions';

import NavGroup from './NavGroup/NavGroup.vue';
import NavItem from './NavItem/NavItem.vue';
import NavCollapse from './NavCollapse/NavCollapse.vue';
import Logo from '../logo/LogoMain.vue';

const customizer = useCustomizerStore();
const { can, isAdmin } = usePermissions();

const permissionMap: Record<string, string> = {
  '/hr/dashboard': 'view hr dashboard',
  '/hr/employees': 'view employees',
  '/hr/departments': 'view departments',
  '/hr/designations': 'view designations',
  '/hr/attendance': 'view attendance',
  '/hr/leave-management': 'view leave requests',
  '/hr/shifts': 'view shifts',
  '/hr/job-openings': 'view job openings',
  '/hr/applicants': 'view applicants',
  '/hr/onboarding': 'view onboarding',
  '/hr/payroll': 'view payroll',
  '/hr/expenses': 'view expenses',
  '/hr/reports': 'view reports',
  '/hr/settings': 'view hr settings',
  '/hr/roles-permissions': 'manage roles'
};

function filterItems(items: typeof sidebarItems): typeof sidebarItems {
  return items
    .map((item) => {
      if (item.children?.length) {
        const children = filterItems(item.children as typeof sidebarItems);
        return children.length ? { ...item, children } : null;
      }

      if (!item.to) return item;
      const permission = permissionMap[item.to];
      if (!permission) return item;

      return can(permission) || isAdmin() ? item : null;
    })
    .filter(Boolean) as typeof sidebarItems;
}

const sidebarMenu = computed(() => filterItems(sidebarItems));
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
