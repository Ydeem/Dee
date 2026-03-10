<template>
  <DashboardLayout>
    <EmployeeProfilePage :employee-id="Number(id)" />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import EmployeeProfilePage from '@/views/hr/employees/EmployeeProfilePage.vue';

defineProps({
  id: {
    type: [String, Number],
    required: true
  }
});
</script>
