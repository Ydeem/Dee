<template>
  <DashboardLayout>
    <EmployeeShow :employee-id="Number(id)" />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import EmployeeShow from '@/views/hr/employees/Show.vue';

defineProps({
  id: {
    type: [String, Number],
    required: true
  }
});
</script>
