<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

const currentUser = computed<any>(() => ((page.props as any)?.auth?.user ?? null));
const employeeId = computed<number | null>(() => currentUser.value?.employee_id ?? null);

const loading = ref(true);
const attendanceSummary = ref({
  present: 0,
  late: 0,
  absent: 0,
  on_leave: 0,
});
const leaveBalances = ref<any[]>([]);
const payrollHistory = ref<any[]>([]);

const leaveRemaining = computed(() =>
  leaveBalances.value.reduce((total: number, item: any) => total + Math.max(0, (item.total_days ?? 0) - (item.used_days ?? 0)), 0)
);

const recentPayslips = computed(() => payrollHistory.value.slice(0, 3));

async function loadSelfServiceData() {
  if (!employeeId.value) {
    loading.value = false;
    return;
  }

  loading.value = true;
  try {
    const [attendanceResponse, leaveResponse, payrollResponse] = await Promise.all([
      axios.get(`/api/hr/employees/${employeeId.value}/attendance`),
      axios.get(`/api/hr/employees/${employeeId.value}/leave`),
      axios.get(`/api/hr/employees/${employeeId.value}/payroll`),
    ]);

    attendanceSummary.value = attendanceResponse.data?.summary ?? attendanceSummary.value;
    leaveBalances.value = leaveResponse.data?.balances ?? [];
    payrollHistory.value = leaveArray(payrollResponse.data?.history ?? []);
  } catch {
    attendanceSummary.value = { present: 0, late: 0, absent: 0, on_leave: 0 };
    leaveBalances.value = [];
    payrollHistory.value = [];
  } finally {
    loading.value = false;
  }
}

function leaveArray<T>(value: T[] | unknown): T[] {
  return Array.isArray(value) ? value : [];
}

onMounted(loadSelfServiceData);
</script>

<template>
  <v-skeleton-loader v-if="loading" type="card, card, card" />

  <template v-else>
    <v-row class="mb-4">
      <v-col cols="12" md="6" lg="4">
        <v-card class="rounded-lg cursor-pointer h-100" variant="outlined" @click="employeeId ? router.visit('/hr/employees/' + employeeId) : null">
          <v-card-text>
            <div class="text-caption text-medium-emphasis mb-1">My Profile</div>
            <div class="text-h6 font-weight-bold">{{ currentUser?.name }}</div>
            <div class="text-body-2 text-medium-emphasis">{{ currentUser?.designation ?? 'Employee' }}</div>
            <div class="text-caption text-primary mt-2">View my profile</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6" lg="4">
        <v-card class="rounded-lg cursor-pointer h-100" variant="outlined" @click="router.visit('/hr/attendance')">
          <v-card-text>
            <div class="text-caption text-medium-emphasis mb-1">My Attendance</div>
            <div class="text-h6 font-weight-bold">{{ attendanceSummary.present }} Days Present</div>
            <div class="text-body-2 text-medium-emphasis">Late: {{ attendanceSummary.late }} · Absent: {{ attendanceSummary.absent }}</div>
            <div class="text-caption text-primary mt-2">View monthly attendance</div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6" lg="4">
        <v-card class="rounded-lg h-100" variant="outlined">
          <v-card-text>
            <div class="text-caption text-medium-emphasis mb-1">My Leave</div>
            <div class="text-h6 font-weight-bold">{{ leaveRemaining }} Days Remaining</div>
            <div class="text-body-2 text-medium-emphasis">Across {{ leaveBalances.length }} leave type(s)</div>
            <v-btn class="mt-3" color="primary" size="small" variant="flat" @click="router.visit('/hr/leave-management')">
              Apply for Leave
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" lg="7">
        <v-card class="rounded-lg h-100" variant="outlined">
          <v-card-title class="d-flex align-center justify-space-between">
            <span>My Payslips</span>
            <v-btn size="small" variant="text" @click="router.visit('/hr/payroll')">View all</v-btn>
          </v-card-title>
          <v-divider />
          <v-list density="comfortable">
            <v-list-item v-for="slip in recentPayslips" :key="slip.id">
              <v-list-item-title>{{ slip.pay_month }}</v-list-item-title>
              <v-list-item-subtitle>Net: {{ slip.net }} · Status: {{ slip.status }}</v-list-item-subtitle>
            </v-list-item>
            <div v-if="recentPayslips.length === 0" class="text-medium-emphasis text-center py-6">No payslips found.</div>
          </v-list>
        </v-card>
      </v-col>

      <v-col cols="12" lg="5">
        <v-card class="rounded-lg h-100" variant="outlined">
          <v-card-title>My Expenses</v-card-title>
          <v-divider />
          <v-card-text>
            <div class="text-body-2 text-medium-emphasis mb-3">
              Submit and track your reimbursement requests.
            </div>
            <v-btn color="primary" variant="flat" prepend-icon="mdi-plus" @click="router.visit('/hr/expenses')">
              Submit New Expense
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </template>
</template>

<style scoped>
.cursor-pointer {
  cursor: pointer;
}
</style>
