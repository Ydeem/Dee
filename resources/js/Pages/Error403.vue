<template>
  <DashboardLayout>
    <ForbiddenView />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import ForbiddenView from '@/views/errors/403.vue';
</script>
