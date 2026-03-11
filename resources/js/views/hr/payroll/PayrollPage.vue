<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Payroll', disabled: true, href: '#' }
];

const activeTab = ref<'runs' | 'payslips' | 'structures'>('runs');
const loading = ref(false);
const runs = ref<any[]>([]);
const stats = ref({
  total_paid_year: 'GHS 0.00',
  pending_approval: 0,
  current_month_status: 'Not Started',
  employees_on_payroll: 0
});
const years = ref<number[]>([]);

const filters = reactive({
  year: new Date().getFullYear(),
  status: ''
});

const runStatuses = ['Draft', 'Pending Approval', 'Approved', 'Paid', 'Cancelled'];
const approving = ref<number | null>(null);

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const months = [
  { title: 'January', value: 1 },
  { title: 'February', value: 2 },
  { title: 'March', value: 3 },
  { title: 'April', value: 4 },
  { title: 'May', value: 5 },
  { title: 'June', value: 6 },
  { title: 'July', value: 7 },
  { title: 'August', value: 8 },
  { title: 'September', value: 9 },
  { title: 'October', value: 10 },
  { title: 'November', value: 11 },
  { title: 'December', value: 12 }
];

const newRunDialog = ref(false);
const newRunSaving = ref(false);
const newRunForm = reactive({
  month: new Date().getMonth() + 1,
  year: new Date().getFullYear(),
  pay_date: '',
  notes: ''
});

const payslipsDialog = ref(false);
const selectedRun = ref<any>(null);
const runPayslips = ref<any[]>([]);
const payslipsLoading = ref(false);
const payslipSearch = ref('');
const payslipsPagination = reactive({
  page: 1,
  perPage: 20,
  total: 0
});

const allPayslips = ref<any[]>([]);
const allPayslipsLoading = ref(false);
const slipFilters = reactive({
  search: '',
  month: null as number | null,
  year: new Date().getFullYear(),
  status: ''
});
const slipPagination = reactive({
  page: 1,
  perPage: 15,
  total: 0
});

const structures = ref<any[]>([]);
const structuresLoading = ref(false);
const structureDialog = ref(false);
const structureSaving = ref(false);
const editingStructure = ref<any>(null);
const structureForm = reactive({
  name: '',
  basic_salary: 0,
  housing_allowance: 0,
  transport_allowance: 0,
  meal_allowance: 0,
  other_allowances: 0,
  ssnit_employee: 5.5,
  ssnit_employer: 13.0,
  income_tax_rate: 0,
  status: 'Active'
});

const runStatusColor = (status: string) => {
  if (status === 'Paid') return 'success';
  if (status === 'Approved') return 'primary';
  if (status === 'Pending Approval') return 'warning';
  if (status === 'Cancelled') return 'error';
  return 'default';
};

const selectedRunNet = computed(() => selectedRun.value?.total_net ?? '0.00');

function endOfMonthDate(year: number, month: number): string {
  return new Date(year, month, 0).toISOString().split('T')[0];
}

function resetStructureForm() {
  structureForm.name = '';
  structureForm.basic_salary = 0;
  structureForm.housing_allowance = 0;
  structureForm.transport_allowance = 0;
  structureForm.meal_allowance = 0;
  structureForm.other_allowances = 0;
  structureForm.ssnit_employee = 5.5;
  structureForm.ssnit_employer = 13.0;
  structureForm.income_tax_rate = 0;
  structureForm.status = 'Active';
}

async function fetchPayrollRuns() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/payroll-runs', {
      params: {
        year: filters.year,
        status: filters.status || undefined
      }
    });

    runs.value = data?.runs ?? [];
    stats.value = data?.stats ?? stats.value;
    years.value = data?.years ?? [];
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load payroll.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}

async function approveRun(run: any) {
  approving.value = run.id;
  try {
    const { data } = await axios.patch(`/api/hr/payroll-runs/${run.id}/approve`);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Payroll approved.',
      color: 'success'
    };
    await fetchPayrollRuns();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Approval failed.',
      color: 'error'
    };
  } finally {
    approving.value = null;
  }
}

async function markPaid(run: any) {
  try {
    const { data } = await axios.patch(`/api/hr/payroll-runs/${run.id}/paid`);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Payroll marked as paid.',
      color: 'success'
    };
    await fetchPayrollRuns();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed.',
      color: 'error'
    };
  }
}

async function cancelRun(run: any) {
  if (!confirm(`Cancel ${run.month_label}?`)) return;
  try {
    const { data } = await axios.patch(`/api/hr/payroll-runs/${run.id}/cancel`);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Payroll cancelled.',
      color: 'success'
    };
    await fetchPayrollRuns();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed.',
      color: 'error'
    };
  }
}

async function openPayslipsDialog(run: any) {
  selectedRun.value = run;
  payslipsDialog.value = true;
  payslipSearch.value = '';
  payslipsPagination.page = 1;
  await loadRunPayslips(run.id);
}

async function loadRunPayslips(runId: number) {
  payslipsLoading.value = true;
  try {
    const { data } = await axios.get(`/api/hr/payroll-runs/${runId}/payslips`, {
      params: {
        search: payslipSearch.value || undefined,
        page: payslipsPagination.page,
        per_page: payslipsPagination.perPage
      }
    });
    runPayslips.value = data?.payslips?.data ?? [];
    payslipsPagination.total = data?.payslips?.total ?? 0;
  } catch {
    runPayslips.value = [];
    payslipsPagination.total = 0;
    snackbar.value = {
      show: true,
      message: 'Failed to load run payslips.',
      color: 'error'
    };
  } finally {
    payslipsLoading.value = false;
  }
}

async function generatePayrollRun() {
  newRunSaving.value = true;
  try {
    const { data } = await axios.post('/api/hr/payroll-runs/generate', newRunForm);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Payroll generated.',
      color: 'success'
    };
    newRunDialog.value = false;
    await fetchPayrollRuns();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to generate payroll.',
      color: 'error'
    };
  } finally {
    newRunSaving.value = false;
  }
}

async function fetchAllPayslips() {
  allPayslipsLoading.value = true;
  try {
    const { data } = await axios.get('/api/hr/payslips', {
      params: {
        search: slipFilters.search || undefined,
        month: slipFilters.month || undefined,
        year: slipFilters.year || undefined,
        status: slipFilters.status || undefined,
        page: slipPagination.page,
        per_page: slipPagination.perPage
      }
    });
    allPayslips.value = data?.payslips?.data ?? [];
    slipPagination.total = data?.payslips?.total ?? 0;
  } catch {
    allPayslips.value = [];
    slipPagination.total = 0;
    snackbar.value = {
      show: true,
      message: 'Failed to load payslips.',
      color: 'error'
    };
  } finally {
    allPayslipsLoading.value = false;
  }
}

async function fetchSalaryStructures() {
  structuresLoading.value = true;
  try {
    const { data } = await axios.get('/api/hr/salary-structures');
    structures.value = data?.structures ?? [];
  } catch {
    structures.value = [];
    snackbar.value = {
      show: true,
      message: 'Failed to load salary structures.',
      color: 'error'
    };
  } finally {
    structuresLoading.value = false;
  }
}

function openStructureDialog(item?: any) {
  if (!item) {
    editingStructure.value = null;
    resetStructureForm();
  } else {
    editingStructure.value = item;
    structureForm.name = item.name ?? '';
    structureForm.basic_salary = Number(item.basic_salary ?? 0);
    structureForm.housing_allowance = Number(item.housing_allowance ?? 0);
    structureForm.transport_allowance = Number(item.transport_allowance ?? 0);
    structureForm.meal_allowance = Number(item.meal_allowance ?? 0);
    structureForm.other_allowances = Number(item.other_allowances ?? 0);
    structureForm.ssnit_employee = Number(item.ssnit_employee ?? 5.5);
    structureForm.ssnit_employer = Number(item.ssnit_employer ?? 13);
    structureForm.income_tax_rate = Number(item.income_tax_rate ?? 0);
    structureForm.status = item.status ?? 'Active';
  }
  structureDialog.value = true;
}

async function saveStructure() {
  structureSaving.value = true;
  try {
    if (editingStructure.value) {
      await axios.put(`/api/hr/salary-structures/${editingStructure.value.id}`, structureForm);
    } else {
      await axios.post('/api/hr/salary-structures', structureForm);
    }
    snackbar.value = {
      show: true,
      message: 'Salary structure saved.',
      color: 'success'
    };
    structureDialog.value = false;
    await fetchSalaryStructures();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to save.',
      color: 'error'
    };
  } finally {
    structureSaving.value = false;
  }
}

async function deleteStructure(item: any) {
  if (!confirm(`Delete salary structure "${item.name}"?`)) return;
  try {
    const { data } = await axios.delete(`/api/hr/salary-structures/${item.id}`);
    snackbar.value = {
      show: true,
      message: data?.message ?? 'Salary structure deleted.',
      color: 'success'
    };
    await fetchSalaryStructures();
  } catch (err: any) {
    snackbar.value = {
      show: true,
      message: err?.response?.data?.message ?? 'Failed to delete.',
      color: 'error'
    };
  }
}

watch(() => [filters.year, filters.status], fetchPayrollRuns);

watch(() => activeTab.value, (tab) => {
  if (tab === 'payslips') fetchAllPayslips();
  if (tab === 'structures') fetchSalaryStructures();
});

watch(() => [slipFilters.search, slipFilters.month, slipFilters.year, slipFilters.status], () => {
  slipPagination.page = 1;
  if (activeTab.value === 'payslips') fetchAllPayslips();
});

watch(() => payslipSearch.value, () => {
  payslipsPagination.page = 1;
  if (payslipsDialog.value && selectedRun.value?.id) loadRunPayslips(selectedRun.value.id);
});

watch(() => [newRunForm.month, newRunForm.year], () => {
  newRunForm.pay_date = endOfMonthDate(newRunForm.year, newRunForm.month);
});

onMounted(() => {
  newRunForm.pay_date = endOfMonthDate(newRunForm.year, newRunForm.month);
  fetchPayrollRuns();
});
</script>

<template>
  <BaseBreadcrumb title="Payroll" subtitle="Manage payroll runs, payslips and salary structures" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h4 mb-1">Payroll</h2>
      <p class="text-medium-emphasis mb-0">Run monthly payroll and manage salary structures.</p>
    </div>
    <v-btn color="primary" prepend-icon="mdi-plus" @click="newRunDialog = true">
      New Payroll Run
    </v-btn>
  </div>

  <v-row class="mb-1">
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined"><v-card-text>Total Paid Year: <strong>{{ stats.total_paid_year }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined"><v-card-text>Pending Approval: <strong>{{ stats.pending_approval }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined"><v-card-text>Current Month: <strong>{{ stats.current_month_status }}</strong></v-card-text></v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card variant="outlined"><v-card-text>Employees: <strong>{{ stats.employees_on_payroll }}</strong></v-card-text></v-card>
    </v-col>
  </v-row>

  <v-card variant="outlined">
    <v-tabs v-model="activeTab" color="primary" class="px-4 pt-2">
      <v-tab value="runs">Payroll Runs</v-tab>
      <v-tab value="payslips">Payslips</v-tab>
      <v-tab value="structures">Salary Structures</v-tab>
    </v-tabs>
    <v-divider />

    <v-window v-model="activeTab">
      <v-window-item value="runs">
        <div class="pa-4">
          <v-row>
            <v-col cols="12" md="3">
              <v-select v-model="filters.year" :items="years" label="Year" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filters.status"
                :items="[{ title: 'All Statuses', value: '' }, ...runStatuses.map((s) => ({ title: s, value: s }))]"
                label="Status"
                variant="outlined"
                hide-details
              />
            </v-col>
          </v-row>

          <v-row class="mt-1">
            <v-col v-for="run in runs" :key="run.id" cols="12" md="6" lg="4">
              <v-card variant="outlined" class="h-100">
                <v-card-text>
                  <div class="d-flex justify-space-between align-start mb-2">
                    <div class="font-weight-bold">{{ run.month_label }}</div>
                    <v-chip :color="run.status_color || runStatusColor(run.status)" size="small" variant="tonal">
                      {{ run.status }}
                    </v-chip>
                  </div>

                  <p class="text-body-2 text-medium-emphasis mb-2">Pay Date: {{ run.pay_date }}</p>
                  <div class="text-body-2 mb-1">Gross: GHS {{ run.total_gross }}</div>
                  <div class="text-body-2 mb-1">Deductions: GHS {{ run.total_deductions }}</div>
                  <div class="text-body-2 font-weight-bold mb-3">Net: GHS {{ run.total_net }}</div>

                  <div class="d-flex flex-wrap ga-2">
                    <v-btn
                      v-if="run.status === 'Pending Approval'"
                      color="success"
                      variant="flat"
                      size="small"
                      :loading="approving === run.id"
                      @click="approveRun(run)"
                    >
                      Approve
                    </v-btn>

                    <v-btn
                      v-if="run.status === 'Approved'"
                      color="primary"
                      variant="flat"
                      size="small"
                      @click="markPaid(run)"
                    >
                      Mark as Paid
                    </v-btn>

                    <v-btn variant="outlined" size="small" @click="openPayslipsDialog(run)">
                      View Payslips
                    </v-btn>

                    <v-btn
                      v-if="run.status !== 'Paid' && run.status !== 'Cancelled'"
                      color="error"
                      variant="text"
                      size="small"
                      @click="cancelRun(run)"
                    >
                      Cancel
                    </v-btn>
                  </div>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col v-if="!loading && !runs.length" cols="12">
              <div class="text-medium-emphasis text-center py-8">No payroll runs found.</div>
            </v-col>
          </v-row>
        </div>
      </v-window-item>

      <v-window-item value="payslips">
        <div class="pa-4">
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field v-model="slipFilters.search" label="Search Employee" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" md="2">
              <v-select
                v-model="slipFilters.month"
                :items="[{ title: 'All Months', value: null }, ...months]"
                label="Month"
                variant="outlined"
                hide-details
              />
            </v-col>
            <v-col cols="12" md="2">
              <v-select v-model="slipFilters.year" :items="years" label="Year" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="slipFilters.status"
                :items="[{ title: 'All Statuses', value: '' }, { title: 'Draft', value: 'Draft' }, { title: 'Approved', value: 'Approved' }, { title: 'Paid', value: 'Paid' }]"
                label="Status"
                variant="outlined"
                hide-details
              />
            </v-col>
          </v-row>

          <v-data-table
            :headers="[
              { title: 'Employee', key: 'employee' },
              { title: 'Period', key: 'period' },
              { title: 'Gross', key: 'gross_salary' },
              { title: 'Deductions', key: 'total_deductions' },
              { title: 'Net', key: 'net_salary' },
              { title: 'Status', key: 'status' }
            ]"
            :items="allPayslips"
            :loading="allPayslipsLoading"
            class="mt-3"
          >
            <template #item.employee="{ item }">
              <div class="d-flex align-center ga-2">
                <v-avatar size="28" color="primary" variant="tonal">
                  <span class="text-caption">{{ item.employee?.initials }}</span>
                </v-avatar>
                <div>
                  <div class="text-body-2 font-weight-medium">{{ item.employee?.name }}</div>
                  <div class="text-caption text-medium-emphasis">{{ item.employee?.department }}</div>
                </div>
              </div>
            </template>
            <template #item.gross_salary="{ item }">GHS {{ item.gross_salary }}</template>
            <template #item.total_deductions="{ item }">GHS {{ item.total_deductions }}</template>
            <template #item.net_salary="{ item }"><span class="text-success font-weight-bold">GHS {{ item.net_salary }}</span></template>
            <template #item.status="{ item }">
              <v-chip :color="runStatusColor(item.status)" size="small" variant="tonal">{{ item.status }}</v-chip>
            </template>
          </v-data-table>
        </div>
      </v-window-item>

      <v-window-item value="structures">
        <div class="pa-4">
          <div class="d-flex justify-space-between align-center mb-3">
            <h3 class="text-h6 mb-0">Salary Structures</h3>
            <v-btn color="primary" variant="flat" size="small" @click="openStructureDialog()">Add Structure</v-btn>
          </div>

          <v-data-table
            :headers="[
              { title: 'Name', key: 'name' },
              { title: 'Basic', key: 'basic_salary' },
              { title: 'Gross', key: 'gross_salary' },
              { title: 'Estimated Net', key: 'estimated_net' },
              { title: 'Status', key: 'status' },
              { title: 'Employees', key: 'employees_count' },
              { title: 'Actions', key: 'actions', sortable: false }
            ]"
            :items="structures"
            :loading="structuresLoading"
          >
            <template #item.basic_salary="{ item }">GHS {{ Number(item.basic_salary).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</template>
            <template #item.gross_salary="{ item }">GHS {{ item.gross_salary }}</template>
            <template #item.estimated_net="{ item }"><span class="text-success font-weight-bold">GHS {{ item.estimated_net }}</span></template>
            <template #item.status="{ item }">
              <v-chip :color="item.status === 'Active' ? 'success' : 'default'" size="small" variant="tonal">
                {{ item.status }}
              </v-chip>
            </template>
            <template #item.actions="{ item }">
              <div class="d-flex ga-1">
                <v-btn size="x-small" variant="outlined" @click="openStructureDialog(item)">Edit</v-btn>
                <v-btn size="x-small" color="error" variant="text" @click="deleteStructure(item)">Delete</v-btn>
              </div>
            </template>
          </v-data-table>
        </div>
      </v-window-item>
    </v-window>
  </v-card>

  <v-dialog v-model="payslipsDialog" max-width="900" scrollable>
    <v-card>
      <v-card-title class="pa-4">
        <div class="d-flex align-center justify-space-between w-100">
          <div>
            <div class="text-h6">{{ selectedRun?.month_label }} Payslips</div>
            <div class="text-caption text-medium-emphasis">{{ selectedRun?.employee_count }} employees</div>
          </div>
          <v-chip :color="selectedRun?.status_color || runStatusColor(selectedRun?.status)">
            {{ selectedRun?.status }}
          </v-chip>
        </div>
      </v-card-title>

      <v-divider />

      <v-card-text class="pa-4">
        <v-text-field v-model="payslipSearch" label="Search Employee" variant="outlined" hide-details class="mb-3" />

        <v-data-table
          :headers="[
            { title: 'Employee', key: 'employee' },
            { title: 'Department', key: 'dept' },
            { title: 'Basic', key: 'basic_salary' },
            { title: 'Gross', key: 'gross_salary' },
            { title: 'SSNIT', key: 'ssnit_employee' },
            { title: 'Tax', key: 'income_tax' },
            { title: 'Deductions', key: 'total_deductions' },
            { title: 'Net Pay', key: 'net_salary' }
          ]"
          :items="runPayslips"
          :loading="payslipsLoading"
        >
          <template #item.employee="{ item }">
            <div class="d-flex align-center ga-2">
              <v-avatar size="28" color="primary" variant="tonal">
                <span class="text-caption">{{ item.employee?.initials }}</span>
              </v-avatar>
              <div>
                <div class="text-body-2 font-weight-medium">{{ item.employee?.name }}</div>
                <div class="text-caption text-medium-emphasis">{{ item.employee?.emp_id }}</div>
              </div>
            </div>
          </template>

          <template #item.dept="{ item }">{{ item.employee?.department }}</template>
          <template #item.basic_salary="{ item }">GHS {{ item.basic_salary }}</template>
          <template #item.gross_salary="{ item }">GHS {{ item.gross_salary }}</template>
          <template #item.ssnit_employee="{ item }">GHS {{ item.ssnit_employee }}</template>
          <template #item.income_tax="{ item }">GHS {{ item.income_tax }}</template>
          <template #item.total_deductions="{ item }">GHS {{ item.total_deductions }}</template>
          <template #item.net_salary="{ item }"><span class="font-weight-bold text-success">GHS {{ item.net_salary }}</span></template>
        </v-data-table>
      </v-card-text>

      <v-card-actions class="pa-4">
        <div class="text-body-2"><strong>Total Net: GHS {{ selectedRunNet }}</strong></div>
        <v-spacer />
        <v-btn variant="text" @click="payslipsDialog = false">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="newRunDialog" max-width="540">
    <v-card>
      <v-card-title class="text-h6">Generate Payroll Run</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" sm="6">
            <v-select v-model="newRunForm.month" :items="months" label="Month" variant="outlined" />
          </v-col>
          <v-col cols="12" sm="6">
            <v-text-field v-model.number="newRunForm.year" type="number" label="Year" variant="outlined" />
          </v-col>
        </v-row>
        <v-text-field v-model="newRunForm.pay_date" type="date" label="Pay Date" variant="outlined" class="mb-2" />
        <v-textarea v-model="newRunForm.notes" label="Notes" rows="3" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="newRunDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="newRunSaving" @click="generatePayrollRun">Generate</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="structureDialog" max-width="700">
    <v-card>
      <v-card-title class="text-h6">{{ editingStructure ? 'Edit Salary Structure' : 'Add Salary Structure' }}</v-card-title>
      <v-card-text>
        <v-row>
          <v-col cols="12" md="6"><v-text-field v-model="structureForm.name" label="Name" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-select v-model="structureForm.status" :items="['Active', 'Inactive']" label="Status" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.basic_salary" type="number" label="Basic Salary" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.housing_allowance" type="number" label="Housing Allowance" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.transport_allowance" type="number" label="Transport Allowance" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.meal_allowance" type="number" label="Meal Allowance" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.other_allowances" type="number" label="Other Allowances" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.income_tax_rate" type="number" label="Income Tax Rate (%)" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.ssnit_employee" type="number" label="SSNIT Employee (%)" variant="outlined" /></v-col>
          <v-col cols="12" md="6"><v-text-field v-model.number="structureForm.ssnit_employer" type="number" label="SSNIT Employer (%)" variant="outlined" /></v-col>
        </v-row>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="structureDialog = false">Cancel</v-btn>
        <v-btn color="primary" :loading="structureSaving" @click="saveStructure">Save</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>
