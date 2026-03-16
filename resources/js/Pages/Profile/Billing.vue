<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
</script>

<template>
  <DashboardLayout>
    <v-container fluid class="py-6">
      <v-card rounded="lg" elevation="0" class="pa-6">
        <h2 class="text-h4 mb-2">Billing</h2>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Billing settings are available here. Connect this page to your payment provider workflow when ready.
        </p>
      </v-card>
    </v-container>
  </DashboardLayout>
</template>
