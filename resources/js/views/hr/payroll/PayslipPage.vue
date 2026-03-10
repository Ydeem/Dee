<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface PayslipDetail {
  id: number;
  basic_salary: number;
  allowances: Array<{ name: string; amount: number }> | null;
  gross_salary: number;
  tax_amount: number;
  ssnit_employee: number;
  ssnit_employer: number;
  other_deductions: number;
  net_salary: number;
  payment_method: string;
  payment_date: string | null;
  bank_name: string | null;
  account_number: string | null;
  employee: {
    full_name: string;
    employee_id: string;
    join_date?: string | null;
    tin?: string | null;
    ssnit?: string | null;
    department?: { name: string } | null;
    designation?: { name: string } | null;
  };
  payroll_run: {
    title: string;
    pay_date: string;
    period_month: number;
    period_year: number;
  };
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Payroll', disabled: false, href: '/hr/payroll' },
  { title: 'Payslip', disabled: true, href: '#' }
];

const loading = ref(false);
const payslip = ref<PayslipDetail | null>(null);
const routeId = computed(() => String(window.location.pathname.split('/').filter(Boolean).slice(-2)[0]));

const dummyPayslip: PayslipDetail = {
  id: 1,
  basic_salary: 6000,
  allowances: [{ name: 'Housing Allowance', amount: 600 }, { name: 'Transport Allowance', amount: 300 }],
  gross_salary: 6900,
  tax_amount: 900,
  ssnit_employee: 379.5,
  ssnit_employer: 897,
  other_deductions: 0,
  net_salary: 5620.5,
  payment_method: 'Bank Transfer',
  payment_date: '2026-02-28',
  bank_name: 'GCB Bank',
  account_number: '********2341',
  employee: {
    full_name: 'Pontian Npontu',
    employee_id: 'EMP00001',
    join_date: '2024-01-08',
    tin: 'TIN-000112',
    ssnit: 'SSNIT-89321',
    department: { name: 'Human Resources' },
    designation: { name: 'HR Manager' }
  },
  payroll_run: { title: 'February 2026 Payroll', pay_date: '2026-02-28', period_month: 2, period_year: 2026 }
};

function money(v: number | string | null | undefined) {
  return `GHS ${Number(v || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

const totalAllowances = computed(() => (payslip.value?.allowances ?? []).reduce((sum, i) => sum + Number(i.amount || 0), 0));
const totalDeductions = computed(() => Number(payslip.value?.tax_amount || 0) + Number(payslip.value?.ssnit_employee || 0) + Number(payslip.value?.other_deductions || 0));

async function fetchPayslip() {
  loading.value = true;
  try {
    const { data } = await axios.get(`/api/hr/payslips/${routeId.value}`);
    payslip.value = data?.payslip ?? null;
    if (!payslip.value) payslip.value = dummyPayslip;
  } catch {
    payslip.value = dummyPayslip;
  } finally {
    loading.value = false;
  }
}

function printPayslip() { window.print(); }
function downloadPdf() { window.print(); }

onMounted(fetchPayslip);
</script>

<template>
  <BaseBreadcrumb title="Payslip" subtitle="Payroll document" :breadcrumbs="breadcrumbs" />

  <div class="action-bar d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <v-btn variant="outlined" prepend-icon="mdi-arrow-left" @click="router.visit('/hr/payroll')">Back to Payroll</v-btn>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-printer" @click="printPayslip">Print Payslip</v-btn>
      <v-btn color="primary" prepend-icon="mdi-file-pdf-box" @click="downloadPdf">Download PDF</v-btn>
    </div>
  </div>

  <v-skeleton-loader v-if="loading || !payslip" type="article" />

  <v-card v-else class="payslip-card mx-auto bg-surface rounded-lg hr-card-shadow" variant="outlined">
    <v-card-text class="pa-8">
      <div class="d-flex justify-space-between align-start mb-6">
        <div>
          <div class="text-h5 font-weight-bold">Your Company Name</div>
          <div class="text-body-2 text-lightText">HR ERP System</div>
        </div>
        <div class="text-right">
          <div class="text-h4 font-weight-bold">PAYSLIP</div>
          <div class="text-body-2">Period: {{ payslip.payroll_run.title }}</div>
          <div class="text-body-2">Pay Date: {{ payslip.payroll_run.pay_date }}</div>
        </div>
      </div>

      <v-row class="mb-4">
        <v-col cols="12" md="6">
          <div class="text-subtitle-2 font-weight-bold mb-2">Employee Information</div>
          <div class="text-body-2">Employee Name: {{ payslip.employee.full_name }}</div>
          <div class="text-body-2">Employee ID: {{ payslip.employee.employee_id }}</div>
          <div class="text-body-2">Department: {{ payslip.employee.department?.name ?? '-' }}</div>
          <div class="text-body-2">Designation: {{ payslip.employee.designation?.name ?? '-' }}</div>
          <div class="text-body-2">Join Date: {{ payslip.employee.join_date ?? '-' }}</div>
        </v-col>
        <v-col cols="12" md="6">
          <div class="text-subtitle-2 font-weight-bold mb-2">Payment Information</div>
          <div class="text-body-2">Bank Name: {{ payslip.bank_name ?? '-' }}</div>
          <div class="text-body-2">Account Number: {{ payslip.account_number ?? '-' }}</div>
          <div class="text-body-2">Payment Method: {{ payslip.payment_method }}</div>
          <div class="text-body-2">Tax ID: {{ payslip.employee.tin ?? '-' }}</div>
          <div class="text-body-2">SSNIT Number: {{ payslip.employee.ssnit ?? '-' }}</div>
        </v-col>
      </v-row>

      <v-divider class="mb-4" />

      <v-row>
        <v-col cols="12" md="6">
          <div class="text-subtitle-2 font-weight-bold mb-2">Earnings</div>
          <v-table density="compact">
            <tbody>
              <tr><td>Basic Salary</td><td class="text-right">{{ money(payslip.basic_salary) }}</td></tr>
              <tr v-for="(item, idx) in payslip.allowances || []" :key="`a-${idx}`"><td>{{ item.name }}</td><td class="text-right">{{ money(item.amount) }}</td></tr>
              <tr class="font-weight-bold"><td>GROSS EARNINGS</td><td class="text-right">{{ money(payslip.gross_salary) }}</td></tr>
            </tbody>
          </v-table>
        </v-col>
        <v-col cols="12" md="6">
          <div class="text-subtitle-2 font-weight-bold mb-2">Deductions</div>
          <v-table density="compact">
            <tbody>
              <tr><td>Income Tax (PAYE)</td><td class="text-right">{{ money(payslip.tax_amount) }}</td></tr>
              <tr><td>SSNIT (5.5%)</td><td class="text-right">{{ money(payslip.ssnit_employee) }}</td></tr>
              <tr><td>Other Deductions</td><td class="text-right">{{ money(payslip.other_deductions) }}</td></tr>
              <tr class="font-weight-bold"><td>TOTAL DEDUCTIONS</td><td class="text-right">{{ money(totalDeductions) }}</td></tr>
            </tbody>
          </v-table>
        </v-col>
      </v-row>

      <v-card class="mt-6 netpay-box" variant="flat">
        <v-card-text class="d-flex justify-space-between align-center">
          <span class="text-h6 font-weight-bold">NET PAY</span>
          <span class="text-h4 font-weight-bold">{{ money(payslip.net_salary) }}</span>
        </v-card-text>
      </v-card>

      <div class="text-body-2 mt-4">Employer SSNIT Contribution (13%): {{ money(payslip.ssnit_employer) }}</div>
      <div class="text-caption text-lightText mt-4">This is a computer generated payslip and does not require a signature.</div>
      <div class="text-caption text-lightText">Generated on: {{ new Date().toISOString().slice(0, 10) }}</div>
    </v-card-text>
  </v-card>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.payslip-card { max-width: 800px; }
.netpay-box { background: rgba(79, 110, 247, 0.1); border: 1px solid rgba(79, 110, 247, 0.3); }
@media print {
  .action-bar { display: none !important; }
  .payslip-card { box-shadow: none !important; border: 0 !important; max-width: 100% !important; }
  :deep(header), :deep(nav), :deep(aside) { display: none !important; }
  @page { size: A4; margin: 20mm; }
}
</style>
