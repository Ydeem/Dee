<template>
  <DashboardLayout>
    <UnauthorizedPage v-if="!isAdmin" />
    <RolesPermissionsPage v-else />
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue';
import RolesPermissionsPage from '@/views/hr/settings/RolesPermissionsPage.vue';
import { usePermissions } from '@/composables/usePermissions';

const { isAdmin } = usePermissions();
</script>
