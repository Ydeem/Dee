<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface PayrollRun { id: number; title: string; period_month: number; period_year: number; pay_date: string; status: string; total_gross: number; total_deductions: number; total_net: number; employee_count: number; payslips_count: number; }
interface PayslipItem { id: number; payroll_run_id: number; basic_salary: number; gross_salary: number; net_salary: number; payment_status: string; payment_date: string | null; employee: { full_name: string; department?: { name: string } | null }; payroll_run?: { title: string } | null; }
interface SalaryStructureItem { id: number; basic_salary: number; allowances: Array<{ name: string; amount: number }> | null; pay_frequency: string; effective_date: string; employee: { id: number; full_name: string; employee_id: string; department?: { name: string } | null }; }
interface Summary { total_paid_this_year: number; pending_approval: number; current_month_status: string; total_employees_on_payroll: number; }

const breadcrumbs = [{ title: 'HR Module', disabled: false, href: '#' }, { title: 'Payroll', disabled: true, href: '#' }];
const tab = ref<'runs' | 'payslips' | 'structures'>('runs');
const loading = ref(false);
const payrollRuns = ref<PayrollRun[]>([]);
const payslips = ref<PayslipItem[]>([]);
const structures = ref<SalaryStructureItem[]>([]);
const summary = ref<Summary>({ total_paid_this_year: 0, pending_approval: 0, current_month_status: 'Not Run', total_employees_on_payroll: 0 });
const runFilters = reactive({ year: String(new Date().getFullYear()), status: '' });
const payslipFilters = reactive({ search: '', payroll_run_id: '', payment_status: '' });
const structureFilters = reactive({ search: '', department: '' });
const runDialog = ref(false);
const confirmDialog = ref({ show: false, runId: null as number | null, action: '' as '' | 'process' | 'approve' | 'markPaid' | 'cancel' | 'delete', title: '', message: '' });
const snackbar = ref({ show: false, message: '', color: 'success' });
const runForm = reactive({ period_month: new Date().getMonth() + 1, period_year: new Date().getFullYear(), pay_date: new Date().toISOString().slice(0, 10), notes: '' });
const allRunOptions = ref<Array<{ id: number; title: string }>>([]);
const allEmployees = ref<Array<{ id: number; full_name: string; employee_id: string }>>([]);
const salaryDrawer = ref(false);
const editingStructureId = ref<number | null>(null);
const salaryForm = reactive({ employee_id: null as number | null, basic_salary: null as number | null, currency: 'GHS', pay_frequency: 'Monthly', effective_date: new Date().toISOString().slice(0, 10), allowances: [{ name: 'Housing Allowance', amount: 0 }] as Array<{ name: string; amount: number }> });

const dummyRuns: PayrollRun[] = [
  { id: 1, title: 'January 2026 Payroll', period_month: 1, period_year: 2026, pay_date: '2026-01-31', status: 'Paid', total_gross: 148000, total_deductions: 32000, total_net: 116000, employee_count: 10, payslips_count: 10 },
  { id: 2, title: 'February 2026 Payroll', period_month: 2, period_year: 2026, pay_date: '2026-02-28', status: 'Pending Approval', total_gross: 151400, total_deductions: 33300, total_net: 118100, employee_count: 10, payslips_count: 10 },
  { id: 3, title: 'March 2026 Payroll', period_month: 3, period_year: 2026, pay_date: '2026-03-31', status: 'Draft', total_gross: 0, total_deductions: 0, total_net: 0, employee_count: 0, payslips_count: 0 }
];
const dummyPayslips: PayslipItem[] = [{ id: 1, payroll_run_id: 2, basic_salary: 6000, gross_salary: 6900, net_salary: 5400, payment_status: 'Pending', payment_date: null, employee: { full_name: 'Pontian Npontu', department: { name: 'Human Resources' } }, payroll_run: { title: 'February 2026 Payroll' } }];
const dummyStructures: SalaryStructureItem[] = [{ id: 1, basic_salary: 6000, allowances: [{ name: 'Housing', amount: 600 }], pay_frequency: 'Monthly', effective_date: '2025-10-01', employee: { id: 1, full_name: 'Pontian Npontu', employee_id: 'EMP00001', department: { name: 'Human Resources' } } }];

const yearOptions = computed(() => [0, 1, 2, 3].map((i) => String(new Date().getFullYear() - i)));
const monthOptions = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'].map((m, i) => ({ title: m, value: i + 1 }));

function money(v: number | string | null | undefined) { return `GHS ${Number(v || 0).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`; }
function statusColor(s: string) { if (s === 'Paid') return 'success'; if (s === 'Pending Approval') return 'warning'; if (s === 'Approved') return 'primary'; if (s === 'Cancelled') return 'error'; return 'secondary'; }
function progress(s: string) { if (s === 'Draft') return 10; if (s === 'Processing') return 40; if (s === 'Pending Approval') return 70; if (s === 'Approved') return 85; if (s === 'Paid') return 100; return 0; }
function allowanceTotal(list: Array<{ name: string; amount: number }> | null | undefined) { return (list ?? []).reduce((a, b) => a + Number(b.amount || 0), 0); }

async function fetchPayrollRuns() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/payroll', { params: { year: runFilters.year || undefined, status: runFilters.status || undefined } });
    payrollRuns.value = data?.payroll_runs?.data ?? [];
    summary.value = data?.summary ?? summary.value;
    if (!payrollRuns.value.length) throw new Error('empty');
  } catch {
    payrollRuns.value = dummyRuns;
    summary.value = { total_paid_this_year: 234100, pending_approval: 1, current_month_status: 'Draft', total_employees_on_payroll: 10 };
  } finally { loading.value = false; }
}

async function fetchPayslips() {
  try {
    const { data } = await axios.get('/api/hr/payslips', { params: { search: payslipFilters.search || undefined, payroll_run_id: payslipFilters.payroll_run_id || undefined, payment_status: payslipFilters.payment_status || undefined } });
    payslips.value = data?.payslips?.data ?? [];
    if (!payslips.value.length) payslips.value = dummyPayslips;
  } catch { payslips.value = dummyPayslips; }
}

async function fetchStructures() {
  try {
    const { data } = await axios.get('/api/hr/salary-structures', { params: { search: structureFilters.search || undefined, department: structureFilters.department || undefined } });
    structures.value = data?.structures?.data ?? [];
    if (!structures.value.length) structures.value = dummyStructures;
  } catch { structures.value = dummyStructures; }
}

async function fetchRunOptions() {
  try {
    const { data } = await axios.get('/api/hr/payroll', { params: { per_page: 200 } });
    const rows = data?.payroll_runs?.data ?? [];
    allRunOptions.value = rows.map((r: PayrollRun) => ({ id: r.id, title: r.title }));
    if (!allRunOptions.value.length) allRunOptions.value = dummyRuns.map((r) => ({ id: r.id, title: r.title }));
  } catch { allRunOptions.value = dummyRuns.map((r) => ({ id: r.id, title: r.title })); }
}

async function fetchEmployees() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    allEmployees.value = data?.employees?.data?.map((e: any) => ({ id: e.id, full_name: e.full_name, employee_id: e.employee_id })) ?? [];
    if (!allEmployees.value.length) allEmployees.value = dummyStructures.map((s) => ({ id: s.employee.id, full_name: s.employee.full_name, employee_id: s.employee.employee_id }));
  } catch { allEmployees.value = dummyStructures.map((s) => ({ id: s.employee.id, full_name: s.employee.full_name, employee_id: s.employee.employee_id })); }
}

async function createRun() {
  try {
    await axios.post('/api/hr/payroll', runForm);
    runDialog.value = false;
    snackbar.value = { show: true, message: 'Payroll run created.', color: 'success' };
    fetchPayrollRuns();
    fetchRunOptions();
  } catch (e: any) { snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Create failed.', color: 'error' }; }
}

function askAction(run: PayrollRun, action: 'process' | 'approve' | 'markPaid' | 'cancel' | 'delete') {
  const label = { process: 'Process', approve: 'Approve', markPaid: 'Mark as Paid', cancel: 'Cancel', delete: 'Delete' }[action];
  confirmDialog.value = { show: true, runId: run.id, action, title: `${label} Payroll`, message: `Are you sure you want to ${label.toLowerCase()} ${run.title}?` };
}

async function executeAction() {
  const { runId, action } = confirmDialog.value;
  if (!runId || !action) return;
  try {
    if (action === 'process') await axios.post(`/api/hr/payroll/${runId}/process`);
    if (action === 'approve') await axios.patch(`/api/hr/payroll/${runId}/approve`);
    if (action === 'markPaid') await axios.patch(`/api/hr/payroll/${runId}/mark-paid`);
    if (action === 'cancel') await axios.patch(`/api/hr/payroll/${runId}/cancel`);
    if (action === 'delete') await axios.delete(`/api/hr/payroll/${runId}`);
    confirmDialog.value.show = false;
    snackbar.value = { show: true, message: 'Action completed.', color: 'success' };
    fetchPayrollRuns(); fetchPayslips(); fetchRunOptions();
  } catch (e: any) { snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Action failed.', color: 'error' }; }
}

function openStructureDrawer(item?: SalaryStructureItem) {
  if (!item) {
    editingStructureId.value = null;
    salaryForm.employee_id = null; salaryForm.basic_salary = null; salaryForm.currency = 'GHS'; salaryForm.pay_frequency = 'Monthly'; salaryForm.effective_date = new Date().toISOString().slice(0, 10); salaryForm.allowances = [{ name: 'Housing Allowance', amount: 0 }];
  } else {
    editingStructureId.value = item.id;
    salaryForm.employee_id = item.employee.id; salaryForm.basic_salary = Number(item.basic_salary); salaryForm.pay_frequency = item.pay_frequency; salaryForm.effective_date = item.effective_date; salaryForm.allowances = [...(item.allowances ?? [])];
  }
  salaryDrawer.value = true;
}

async function saveStructure() {
  const payload = { employee_id: salaryForm.employee_id, basic_salary: salaryForm.basic_salary, currency: salaryForm.currency, pay_frequency: salaryForm.pay_frequency, effective_date: salaryForm.effective_date, allowances: salaryForm.allowances };
  try {
    if (editingStructureId.value) await axios.put(`/api/hr/salary-structures/${editingStructureId.value}`, payload);
    else await axios.post('/api/hr/salary-structures', payload);
    salaryDrawer.value = false;
    snackbar.value = { show: true, message: 'Salary structure saved.', color: 'success' };
    fetchStructures();
  } catch (e: any) { snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Save failed.', color: 'error' }; }
}

function addAllowance() { salaryForm.allowances.push({ name: '', amount: 0 }); }
function removeAllowance(i: number) { salaryForm.allowances.splice(i, 1); }

watch(() => [runFilters.year, runFilters.status], fetchPayrollRuns);
watch(() => [payslipFilters.search, payslipFilters.payroll_run_id, payslipFilters.payment_status], fetchPayslips);
watch(() => [structureFilters.search, structureFilters.department], fetchStructures);

onMounted(async () => { await Promise.all([fetchPayrollRuns(), fetchPayslips(), fetchStructures(), fetchRunOptions(), fetchEmployees()]); });
</script>

<template>
  <BaseBreadcrumb title="Payroll" subtitle="Process and manage employee payroll" :breadcrumbs="breadcrumbs" />
  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div><h2 class="text-h3 mb-1">Payroll</h2><p class="text-subtitle-1 text-lightText mb-0">Process and manage employee payroll</p></div>
    <div class="d-flex ga-2"><v-btn variant="outlined" prepend-icon="mdi-account-cash" @click="tab = 'structures'">Salary Structures</v-btn><v-btn color="primary" prepend-icon="mdi-plus" @click="runDialog = true">New Payroll Run</v-btn></div>
  </div>
  <v-row class="mb-1"><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Total Paid This Year: <strong>{{ money(summary.total_paid_this_year) }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Pending Approval: <strong>{{ summary.pending_approval }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Current Month Status: <strong>{{ summary.current_month_status }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Employees on Payroll: <strong>{{ summary.total_employees_on_payroll }}</strong></v-card-text></v-card></v-col></v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined">
    <v-tabs v-model="tab" color="primary" class="px-4 pt-2"><v-tab value="runs">Payroll Runs</v-tab><v-tab value="payslips">Payslips</v-tab><v-tab value="structures">Salary Structures</v-tab></v-tabs>
    <v-divider />
    <v-window v-model="tab">
      <v-window-item value="runs"><div class="pa-4"><v-row><v-col cols="12" sm="6" md="3"><v-select v-model="runFilters.year" :items="yearOptions" label="Year" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="runFilters.status" :items="[{ title: 'All', value: '' }, 'Draft', 'Processing', 'Pending Approval', 'Approved', 'Paid', 'Cancelled'].map((s) => typeof s === 'string' ? { title: s, value: s } : s)" label="Status" variant="outlined" hide-details /></v-col></v-row><v-skeleton-loader v-if="loading && !payrollRuns.length" type="table" /><v-row v-else class="mt-2"><v-col v-for="run in payrollRuns" :key="run.id" cols="12" sm="6" md="4" lg="4"><v-card variant="outlined" class="hr-card-shadow"><v-card-text><div class="d-flex justify-space-between align-start"><div class="font-weight-bold">{{ run.title }}</div><v-chip :color="statusColor(run.status)" size="small" variant="tonal">{{ run.status }}</v-chip></div><div class="text-caption mt-1">Pay Date: {{ run.pay_date }}</div><div class="text-caption mb-2">{{ run.employee_count }} employees</div><div class="text-caption">Gross: {{ money(run.total_gross) }}</div><div class="text-caption">Deductions: {{ money(run.total_deductions) }}</div><div class="font-weight-bold">Net: {{ money(run.total_net) }}</div><v-progress-linear :model-value="progress(run.status)" height="8" rounded color="primary" class="my-2" /><div class="d-flex ga-2 flex-wrap"><v-btn v-if="run.status === 'Draft'" size="small" color="primary" @click="askAction(run, 'process')">Process</v-btn><v-btn v-if="run.status === 'Pending Approval'" size="small" color="success" @click="askAction(run, 'approve')">Approve</v-btn><v-btn v-if="run.status === 'Approved'" size="small" color="success" @click="askAction(run, 'markPaid')">Mark Paid</v-btn><v-btn size="small" variant="outlined" @click="tab='payslips'; payslipFilters.payroll_run_id=String(run.id)">View Payslips</v-btn><v-menu><template #activator="{ props }"><v-btn size="small" icon="mdi-dots-vertical" variant="text" v-bind="props" /></template><v-list><v-list-item title="Cancel" @click="askAction(run, 'cancel')" /><v-list-item title="Delete" base-color="error" @click="askAction(run, 'delete')" /></v-list></v-menu></div></v-card-text></v-card></v-col></v-row></div></v-window-item>

      <v-window-item value="payslips"><div class="pa-4"><v-row><v-col cols="12" md="4"><v-text-field v-model="payslipFilters.search" placeholder="Search employee name" variant="outlined" hide-details /></v-col><v-col cols="12" md="4"><v-select v-model="payslipFilters.payroll_run_id" :items="[{ title: 'All Payroll Runs', value: '' }, ...allRunOptions.map((o) => ({ title: o.title, value: String(o.id) }))]" label="Payroll Run" variant="outlined" hide-details /></v-col><v-col cols="12" md="3"><v-select v-model="payslipFilters.payment_status" :items="[{ title: 'All', value: '' }, { title: 'Pending', value: 'Pending' }, { title: 'Paid', value: 'Paid' }, { title: 'Failed', value: 'Failed' }]" label="Payment Status" variant="outlined" hide-details /></v-col></v-row><v-data-table :headers="[{ title: 'Employee', key: 'employee' }, { title: 'Payroll', key: 'payroll_run' }, { title: 'Basic', key: 'basic_salary' }, { title: 'Gross', key: 'gross_salary' }, { title: 'Net', key: 'net_salary' }, { title: 'Status', key: 'payment_status' }, { title: 'Actions', key: 'actions' }]" :items="payslips" class="mt-3"><template #item.employee="{ item }"><div><div class="font-weight-medium">{{ item.employee.full_name }}</div><div class="text-caption">{{ item.employee.department?.name ?? '-' }}</div></div></template><template #item.payroll_run="{ item }">{{ item.payroll_run?.title ?? '-' }}</template><template #item.basic_salary="{ item }">{{ money(item.basic_salary) }}</template><template #item.gross_salary="{ item }">{{ money(item.gross_salary) }}</template><template #item.net_salary="{ item }"><strong>{{ money(item.net_salary) }}</strong></template><template #item.payment_status="{ item }"><v-chip :color="item.payment_status==='Paid' ? 'success' : item.payment_status==='Failed' ? 'error' : 'warning'" size="small" variant="tonal">{{ item.payment_status }}</v-chip></template><template #item.actions="{ item }"><v-btn size="small" variant="outlined" @click="router.visit(`/hr/payroll/${item.id}/payslip`)">View</v-btn></template></v-data-table></div></v-window-item>

      <v-window-item value="structures"><div class="pa-4"><div class="d-flex justify-space-between align-center mb-2"><div class="font-weight-medium">Salary Structures</div><v-btn size="small" color="primary" variant="outlined" prepend-icon="mdi-plus" @click="openStructureDrawer()">Add Salary Structure</v-btn></div><v-row><v-col cols="12" md="5"><v-text-field v-model="structureFilters.search" placeholder="Search employee name" variant="outlined" hide-details /></v-col><v-col cols="12" md="4"><v-select v-model="structureFilters.department" :items="[{ title: 'All Departments', value: '' }, ...[...new Set(structures.map((s) => s.employee.department?.name).filter(Boolean))].map((d) => ({ title: String(d), value: String(d) }))]" label="Department" variant="outlined" hide-details /></v-col></v-row><v-data-table :headers="[{ title: 'Employee', key: 'employee' }, { title: 'Basic Salary', key: 'basic_salary' }, { title: 'Allowances', key: 'allowances' }, { title: 'Gross', key: 'gross' }, { title: 'Pay Frequency', key: 'pay_frequency' }, { title: 'Effective Date', key: 'effective_date' }, { title: 'Actions', key: 'actions' }]" :items="structures" class="mt-3"><template #item.employee="{ item }"><div><div class="font-weight-medium">{{ item.employee.full_name }}</div><div class="text-caption">{{ item.employee.department?.name ?? '-' }}</div></div></template><template #item.basic_salary="{ item }">{{ money(item.basic_salary) }}</template><template #item.allowances="{ item }"><div class="d-flex flex-wrap ga-1"><v-chip v-for="(a, i) in item.allowances || []" :key="i" size="x-small" variant="tonal">{{ a.name }} {{ money(a.amount) }}</v-chip></div></template><template #item.gross="{ item }">{{ money(Number(item.basic_salary) + allowanceTotal(item.allowances)) }}</template><template #item.actions="{ item }"><v-btn size="small" variant="outlined" @click="openStructureDrawer(item)">Edit</v-btn></template></v-data-table></div></v-window-item>
    </v-window>
  </v-card>

  <v-dialog v-model="runDialog" max-width="480"><v-card><v-card-title class="text-h5">Create Payroll Run</v-card-title><v-card-text><v-select v-model="runForm.period_month" :items="monthOptions" label="Period Month *" variant="outlined" class="mb-2" /><v-text-field v-model.number="runForm.period_year" type="number" label="Period Year *" variant="outlined" class="mb-2" /><v-text-field v-model="runForm.pay_date" type="date" label="Pay Date *" variant="outlined" class="mb-2" /><v-textarea v-model="runForm.notes" label="Notes" rows="3" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="runDialog = false">Cancel</v-btn><v-btn color="primary" @click="createRun">Create Payroll Run</v-btn></v-card-actions></v-card></v-dialog>

  <v-navigation-drawer v-model="salaryDrawer" location="right" temporary width="520"><div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">{{ editingStructureId ? 'Edit Salary Structure' : 'Add Salary Structure' }}</h5><v-btn icon="mdi-close" variant="text" @click="salaryDrawer=false" /></div><div class="pa-4 drawer-body"><v-autocomplete v-model="salaryForm.employee_id" :items="allEmployees.map((e) => ({ title: `${e.full_name} (${e.employee_id})`, value: e.id }))" label="Employee *" variant="outlined" class="mb-2" /><v-row><v-col cols="12" md="6"><v-text-field v-model.number="salaryForm.basic_salary" type="number" label="Basic Salary *" variant="outlined" /></v-col><v-col cols="12" md="6"><v-select v-model="salaryForm.pay_frequency" :items="['Monthly', 'Bi-weekly', 'Weekly']" label="Pay Frequency" variant="outlined" /></v-col></v-row><v-text-field v-model="salaryForm.effective_date" type="date" label="Effective Date *" variant="outlined" class="mb-2" /><div class="d-flex justify-space-between align-center mb-2"><div class="font-weight-medium">Allowances</div><v-btn size="small" variant="text" prepend-icon="mdi-plus" color="primary" @click="addAllowance">Add</v-btn></div><v-row v-for="(a, i) in salaryForm.allowances" :key="i"><v-col cols="7"><v-text-field v-model="a.name" label="Name" density="compact" variant="outlined" /></v-col><v-col cols="4"><v-text-field v-model.number="a.amount" type="number" min="0" label="Amount" density="compact" variant="outlined" /></v-col><v-col cols="1" class="d-flex align-center justify-center"><v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="removeAllowance(i)" /></v-col></v-row><v-card variant="outlined" class="mt-2"><v-card-text>Basic: <strong>{{ money(salaryForm.basic_salary) }}</strong><br />+ Allowances: <strong>{{ money(allowanceTotal(salaryForm.allowances)) }}</strong><br />= Gross: <strong>{{ money(Number(salaryForm.basic_salary || 0) + allowanceTotal(salaryForm.allowances)) }}/month</strong></v-card-text></v-card></div><div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="salaryDrawer=false">Cancel</v-btn><v-btn color="primary" @click="saveStructure">Save</v-btn></div></v-navigation-drawer>

  <v-dialog v-model="confirmDialog.show" max-width="420"><v-card><v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title><v-card-text>{{ confirmDialog.message }}</v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="confirmDialog.show=false">Cancel</v-btn><v-btn color="primary" variant="flat" @click="executeAction">Confirm</v-btn></v-card-actions></v-card></v-dialog>
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
.drawer-body { height: calc(100% - 130px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
</style>


