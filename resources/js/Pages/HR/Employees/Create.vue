<template>
  <DashboardLayout>
    <EmployeeFormPage mode="create" />
  </DashboardLayout>
</template>

<script setup>
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';
import EmployeeFormPage from '@/views/hr/employees/EmployeeFormPage.vue';
</script>
