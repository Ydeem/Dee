<template>
  <DashboardLayout>
    <UnauthorizedPage v-if="!isAdmin" />
    <HRSettingsPage v-else />
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue';
import HRSettingsPage from '@/views/hr/settings/HRSettingsPage.vue';
import { usePermissions } from '@/composables/usePermissions';

const { isAdmin } = usePermissions();
</script>
