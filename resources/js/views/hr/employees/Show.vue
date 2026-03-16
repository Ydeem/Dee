<template>
  <EmployeeProfilePage :employee-id="employeeId" />
</template>

<script setup lang="ts">
import EmployeeProfilePage from '@/views/hr/employees/EmployeeProfilePage.vue';

defineProps<{
  employeeId: number;
}>();
</script>

