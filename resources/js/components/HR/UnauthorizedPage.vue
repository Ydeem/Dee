<script setup lang="ts">
import { router } from '@inertiajs/vue3';
</script>

<template>
  <v-card class="rounded-lg" variant="outlined" elevation="0">
    <v-card-text class="py-12 text-center">
      <v-icon size="72" color="error" class="mb-4">mdi-lock</v-icon>
      <h2 class="text-h4 font-weight-bold mb-2">Access Denied</h2>
      <p class="text-body-1 text-medium-emphasis mx-auto mb-6" style="max-width: 560px;">
        You don't have permission to view this page. Contact your HR Admin to request access.
      </p>
      <v-btn color="primary" variant="flat" prepend-icon="mdi-view-dashboard" @click="router.visit('/hr/dashboard')">
        Go to Dashboard
      </v-btn>
    </v-card-text>
  </v-card>
</template>
