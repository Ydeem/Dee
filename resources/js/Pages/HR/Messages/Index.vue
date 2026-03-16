<template>
  <DashboardLayout>
    <v-container fluid class="py-6">
      <MessagesPage />
    </v-container>
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import MessagesPage from '@/views/hr/messages/MessagesPage.vue';
</script>
