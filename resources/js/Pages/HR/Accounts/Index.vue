<template>
  <DashboardLayout>
    <UnauthorizedPage v-if="!isAdmin" />
    <v-container v-else fluid class="py-6">
      <AccountsPage />
    </v-container>
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue';
import AccountsPage from '@/views/hr/accounts/AccountsPage.vue';
import { usePermissions } from '@/composables/usePermissions';

const { isAdmin } = usePermissions();
</script>
