<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import SocialProfileView from '@/views/profile/SocialProfile.vue';
</script>

<template>
  <DashboardLayout>
    <v-container fluid class="py-6">
      <SocialProfileView />
    </v-container>
  </DashboardLayout>
</template>
