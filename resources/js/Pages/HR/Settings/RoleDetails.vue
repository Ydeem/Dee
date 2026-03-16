<template>
  <DashboardLayout>
    <RoleDetailsPage :id="id" />
  </DashboardLayout>
</template>

<script setup lang="ts">
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue'
import RoleDetailsPage from '@/views/hr/settings/RoleDetailsPage.vue'

defineProps<{
  id: number
}>()
</script>
