<template>
  <DashboardLayout>
    <v-container fluid class="py-6">
      <AnnouncementsPage />
    </v-container>
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import AnnouncementsPage from '@/views/hr/announcements/AnnouncementsPage.vue';
</script>
