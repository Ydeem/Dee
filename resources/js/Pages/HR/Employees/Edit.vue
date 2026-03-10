<template>
  <DashboardLayout>
    <EmployeeFormPage mode="edit" :employee-id="Number(id)" />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import EmployeeFormPage from '@/views/hr/employees/EmployeeFormPage.vue';

defineProps({
  id: {
    type: [String, Number],
    required: true
  }
});
</script>
