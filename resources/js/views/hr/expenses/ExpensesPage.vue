<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface Expense {
  id: number
  category: string
  title: string
  amount: number
  amount_raw?: number
  currency: string
  expense_date: string
  expense_date_raw?: string | null
  description: string | null
  receipt_path: string | null
  receipt_url?: string | null
  has_receipt?: boolean
  status: string
  status_color?: string
  can_approve?: boolean
  can_reject?: boolean
  can_pay?: boolean
  rejection_reason: string | null
  paid_at: string | null
  payment_method: string | null
  reference_number: string | null
  employee: {
    id: number
    full_name: string
    employee_id: string
    avatar_url: string | null
    initials?: string
    dept?: string
    department?: { name: string } | null
  }
  approvedBy?: { full_name: string } | null
  approved_at: string | null
  created_at: string
}

interface Summary {
  total_submitted: number
  total_approved: number
  total_pending: number
  total_paid: number
}

interface EmployeeOption {
  id: number
  full_name: string
  employee_id: string
  avatar_url: string | null
  department?: { name: string } | null
  personal_email?: string | null
  work_email?: string | null
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Expenses', disabled: true, href: '#' }
];

const categoryMeta: Record<string, { color: string; icon: string }> = {
  Travel: { color: 'primary', icon: 'mdi-airplane' },
  Meals: { color: 'warning', icon: 'mdi-food' },
  Accommodation: { color: 'indigo', icon: 'mdi-bed' },
  Equipment: { color: 'teal', icon: 'mdi-laptop' },
  Training: { color: 'success', icon: 'mdi-school' },
  Medical: { color: 'error', icon: 'mdi-medical-bag' },
  Communication: { color: 'cyan', icon: 'mdi-cellphone' },
  Other: { color: 'grey', icon: 'mdi-dots-horizontal' }
};

const statusMeta: Record<string, string> = {
  Pending: 'warning',
  Approved: 'success',
  Rejected: 'error',
  Paid: 'primary'
};

const page = usePage();
const today = new Date().toISOString().slice(0, 10);
const defaultCategories = ['Travel', 'Meals', 'Accommodation', 'Equipment', 'Training', 'Medical', 'Communication', 'Other'];
const defaultDepartments = ['People Operations', 'Engineering', 'Finance', 'Sales'];

const dummyEmployees: EmployeeOption[] = [
  { id: 1, full_name: 'Pontian Npontu', employee_id: 'EMP00001', avatar_url: null, department: { name: 'People Operations' }, personal_email: 'pontian@npontu.com', work_email: 'pontian@company.com' },
  { id: 2, full_name: 'Sarah Oti', employee_id: 'EMP00002', avatar_url: null, department: { name: 'Finance' }, personal_email: 'sarah.oti@example.com', work_email: 'sarah@company.com' },
  { id: 3, full_name: 'Daniel Kofi', employee_id: 'EMP00003', avatar_url: null, department: { name: 'Engineering' }, personal_email: 'daniel.kofi@example.com', work_email: 'daniel@company.com' },
  { id: 4, full_name: 'Amanda Boateng', employee_id: 'EMP00004', avatar_url: null, department: { name: 'Sales' }, personal_email: 'amanda.boateng@example.com', work_email: 'amanda@company.com' }
];

const dummyExpenses: Expense[] = [
  {
    id: 9001,
    category: 'Travel',
    title: 'Uber to client site',
    amount: 180,
    currency: 'GHS',
    expense_date: today,
    description: 'Transport to enterprise client meeting.',
    receipt_path: null,
    status: 'Submitted',
    rejection_reason: null,
    paid_at: null,
    payment_method: null,
    reference_number: null,
    employee: {
      id: 1,
      full_name: 'Pontian Npontu',
      employee_id: 'EMP00001',
      avatar_url: null,
      department: { name: 'People Operations' }
    },
    approvedBy: null,
    approved_at: null,
    created_at: `${today}T08:30:00`
  },
  {
    id: 9002,
    category: 'Meals',
    title: 'Team lunch with applicants',
    amount: 420,
    currency: 'GHS',
    expense_date: today,
    description: 'Recruitment panel lunch meeting.',
    receipt_path: null,
    status: 'Approved',
    rejection_reason: null,
    paid_at: null,
    payment_method: null,
    reference_number: null,
    employee: {
      id: 2,
      full_name: 'Sarah Oti',
      employee_id: 'EMP00002',
      avatar_url: null,
      department: { name: 'Finance' }
    },
    approvedBy: { full_name: 'Pontian Npontu' },
    approved_at: `${today}T11:00:00`,
    created_at: `${today}T09:00:00`
  },
  {
    id: 9003,
    category: 'Training',
    title: 'Leadership workshop fee',
    amount: 1250,
    currency: 'GHS',
    expense_date: today,
    description: 'External workshop for managers.',
    receipt_path: null,
    status: 'Paid',
    rejection_reason: null,
    paid_at: `${today}T14:30:00`,
    payment_method: 'Bank Transfer',
    reference_number: 'TRX-20391',
    employee: {
      id: 3,
      full_name: 'Daniel Kofi',
      employee_id: 'EMP00003',
      avatar_url: null,
      department: { name: 'Engineering' }
    },
    approvedBy: { full_name: 'Pontian Npontu' },
    approved_at: `${today}T10:30:00`,
    created_at: `${today}T08:00:00`
  },
  {
    id: 9004,
    category: 'Medical',
    title: 'Annual health screening',
    amount: 640,
    currency: 'GHS',
    expense_date: today,
    description: 'Routine medical reimbursement.',
    receipt_path: null,
    status: 'Rejected',
    rejection_reason: 'Receipt amount does not match submitted total.',
    paid_at: null,
    payment_method: null,
    reference_number: null,
    employee: {
      id: 4,
      full_name: 'Amanda Boateng',
      employee_id: 'EMP00004',
      avatar_url: null,
      department: { name: 'Sales' }
    },
    approvedBy: { full_name: 'Pontian Npontu' },
    approved_at: `${today}T12:00:00`,
    created_at: `${today}T07:45:00`
  }
];

const loading = ref(true);
const saving = ref(false);
const activeTab = ref<'all' | 'my'>('all');
const drawerOpen = ref(false);
const detailDialog = ref(false);
const rejectDialog = ref(false);
const paymentDialog = ref(false);

const expenses = ref<Expense[]>([]);
const summary = ref<Summary>({
  total_submitted: 0,
  total_approved: 0,
  total_pending: 0,
  total_paid: 0
});
const departments = ref<string[]>([]);
const categories = ref<string[]>([]);
const employeeOptions = ref<EmployeeOption[]>([]);
const selectedExpense = ref<Expense | null>(null);

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0
});

const filters = reactive({
  search: '',
  category: '',
  department: '',
  status: '',
  month: ''
});

const form = reactive({
  employee_id: null as number | null,
  title: '',
  category: '',
  amount: null as number | null,
  currency: 'GHS',
  expense_date: today,
  description: '',
  status: 'Draft',
  receipt: null as File | null
});

const editingId = ref<number | null>(null);
const receiptPreview = ref<string | null>(null);

const rejectForm = reactive({
  id: null as number | null,
  employee: '',
  title: '',
  amount: 0,
  rejection_reason: ''
});

const paymentForm = reactive({
  id: null as number | null,
  employee: '',
  amount: 0,
  payment_method: 'Bank Transfer',
  reference_number: ''
});

const confirmDialog = ref({
  show: false,
  title: '',
  message: '',
  action: '' as '' | 'approve' | 'delete',
  id: null as number | null,
  color: 'primary'
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

const headers = [
  { title: 'Employee', key: 'employee', sortable: false },
  { title: 'Category', key: 'category', sortable: false },
  { title: 'Title', key: 'title', sortable: false },
  { title: 'Amount', key: 'amount', sortable: false },
  { title: 'Expense Date', key: 'expense_date', sortable: false },
  { title: 'Receipt', key: 'receipt', sortable: false },
  { title: 'Status', key: 'status', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false }
];

const perPageOptions = [10, 25, 50];
const categoryOptions = computed(() => [
  { title: 'All', value: '' },
  ...categories.value.map((item) => ({ title: item, value: item }))
]);
const departmentOptions = computed(() => [
  { title: 'All Departments', value: '' },
  ...departments.value.map((item) => ({ title: item, value: item }))
]);
const statusOptions = [
  { title: 'All', value: '' },
  { title: 'Pending', value: 'Pending' },
  { title: 'Approved', value: 'Approved' },
  { title: 'Rejected', value: 'Rejected' },
  { title: 'Paid', value: 'Paid' }
];
const currencyOptions = ['GHS', 'USD', 'EUR'];
const paymentMethods = ['Bank Transfer', 'Cash', 'Mobile Money'];

const employeeSelectItems = computed(() =>
  employeeOptions.value.map((item) => ({
    title: `${item.full_name} (${item.employee_id})`,
    value: item.id,
    subtitle: item.department?.name ?? 'No department'
  }))
);

const resolvedEmployeeId = computed<number | null>(() => {
  const userId = (page.props as any)?.auth?.user?.id;
  const userName = String((page.props as any)?.auth?.user?.name ?? '').trim().toLowerCase();
  const userEmail = String((page.props as any)?.auth?.user?.email ?? '').trim().toLowerCase();

  if (typeof userId === 'number' && employeeOptions.value.some((item) => item.id === userId)) {
    return userId;
  }

  const matchedEmployee = employeeOptions.value.find((item) => {
    const fullName = item.full_name.trim().toLowerCase();
    const personalEmail = String(item.personal_email ?? '').trim().toLowerCase();
    const workEmail = String(item.work_email ?? '').trim().toLowerCase();

    return Boolean(
      (userName && fullName === userName) ||
      (userEmail && (personalEmail === userEmail || workEmail === userEmail))
    );
  });

  return matchedEmployee?.id ?? employeeOptions.value[0]?.id ?? null;
});

function parseCurrencyStat(value: string | number | null | undefined): number {
  if (typeof value === 'number') return value;
  if (!value) return 0;
  return Number(String(value).replace(/[^\d.-]/g, '')) || 0;
}

function normalizeExpense(item: any): Expense {
  const employeeName = item?.employee?.full_name
    ?? item?.employee?.name
    ?? 'Unknown Employee';
  const employeeDept = item?.employee?.department?.name
    ?? item?.employee?.dept
    ?? '-';
  const amountRaw = typeof item?.amount_raw === 'number'
    ? item.amount_raw
    : Number(String(item?.amount ?? '0').replace(/,/g, ''));

  return {
    id: Number(item?.id ?? 0),
    category: item?.category ?? 'Other',
    title: item?.title ?? '',
    amount: Number.isFinite(amountRaw) ? amountRaw : 0,
    amount_raw: Number.isFinite(amountRaw) ? amountRaw : 0,
    currency: item?.currency ?? 'GHS',
    expense_date: item?.expense_date_raw ?? item?.expense_date ?? '',
    expense_date_raw: item?.expense_date_raw ?? null,
    description: item?.description ?? null,
    receipt_path: item?.receipt_url ?? item?.receipt_path ?? null,
    receipt_url: item?.receipt_url ?? null,
    has_receipt: Boolean(item?.has_receipt ?? item?.receipt_url ?? item?.receipt_path),
    status: item?.status ?? 'Pending',
    status_color: item?.status_color ?? statusColor(item?.status ?? 'Pending'),
    can_approve: Boolean(item?.can_approve),
    can_reject: Boolean(item?.can_reject),
    can_pay: Boolean(item?.can_pay),
    rejection_reason: item?.rejection_reason ?? null,
    paid_at: item?.paid_at ?? null,
    payment_method: item?.payment_method ?? null,
    reference_number: item?.reference_number ?? null,
    employee: {
      id: Number(item?.employee?.id ?? 0),
      full_name: employeeName,
      employee_id: item?.employee?.emp_id ?? item?.employee?.employee_id ?? '',
      avatar_url: item?.employee?.avatar_url ?? item?.employee?.avatar ?? null,
      initials: item?.employee?.initials ?? initials(employeeName),
      dept: employeeDept,
      department: { name: employeeDept }
    },
    approvedBy: item?.approvedBy ?? null,
    approved_at: item?.approved_at ?? null,
    created_at: item?.created_at ?? ''
  };
}

function rowData(item: any): Expense {
  return (item?.raw ?? item) as Expense;
}

function formatCurrency(amount: number | string | null | undefined, currency = 'GHS') {
  return Number(amount ?? 0).toLocaleString('en-GH', {
    style: 'currency',
    currency
  });
}

function formatDate(value: string | null) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value: string | null) {
  if (!value) return '-';
  return new Date(value).toLocaleString('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function statusColor(status: string) {
  return statusMeta[status] ?? 'grey';
}

function categoryColor(category: string) {
  return categoryMeta[category]?.color ?? 'grey';
}

function categoryIcon(category: string) {
  return categoryMeta[category]?.icon ?? 'mdi-dots-horizontal';
}

function initials(name: string) {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
}

function receiptUrl(path: string | null) {
  if (!path) return null;
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('/')) {
    return path;
  }
  return `/storage/${path}`;
}

function isImageReceipt(path: string | null) {
  return /\.(png|jpe?g|gif|webp)$/i.test(path ?? '');
}

function showSnackbar(message: string, color: 'success' | 'error' | 'warning' = 'success') {
  snackbar.value = { show: true, message, color };
}

function resetFilters() {
  filters.search = '';
  filters.category = '';
  filters.department = '';
  filters.status = '';
  filters.month = '';
}

function openCreateDrawer() {
  editingId.value = null;
  form.employee_id = resolvedEmployeeId.value;
  form.title = '';
  form.category = '';
  form.amount = null;
  form.currency = 'GHS';
  form.expense_date = today;
  form.description = '';
  form.status = 'Draft';
  form.receipt = null;
  revokeReceiptPreview();
  drawerOpen.value = true;
}

function openEditDrawer(item: Expense) {
  editingId.value = item.id;
  form.employee_id = item.employee.id;
  form.title = item.title;
  form.category = item.category;
  form.amount = Number(item.amount);
  form.currency = item.currency || 'GHS';
  form.expense_date = item.expense_date;
  form.description = item.description ?? '';
  form.status = item.status === 'Submitted' ? 'Submitted' : 'Draft';
  form.receipt = null;
  selectedExpense.value = item;
  revokeReceiptPreview();
  receiptPreview.value = receiptUrl(item.receipt_path);
  drawerOpen.value = true;
}

function viewDetails(item: Expense) {
  selectedExpense.value = item;
  detailDialog.value = true;
}

function onReceiptChange(files: File | File[] | null) {
  const file = Array.isArray(files) ? (files[0] ?? null) : files;
  form.receipt = file;
  revokeReceiptPreview();

  if (file && file.type.startsWith('image/')) {
    receiptPreview.value = URL.createObjectURL(file);
    return;
  }

  if (editingId.value && selectedExpense.value?.receipt_path) {
    receiptPreview.value = receiptUrl(selectedExpense.value.receipt_path);
    return;
  }

  receiptPreview.value = null;
}

function revokeReceiptPreview() {
  if (receiptPreview.value?.startsWith('blob:')) {
    URL.revokeObjectURL(receiptPreview.value);
  }
  receiptPreview.value = null;
}

function buildFormData() {
  const payload = new FormData();
  payload.append('employee_id', String(form.employee_id ?? ''));
  payload.append('title', form.title);
  payload.append('category', form.category);
  payload.append('amount', String(form.amount ?? ''));
  payload.append('currency', form.currency);
  payload.append('expense_date', form.expense_date);
  payload.append('description', form.description || '');
  if (form.receipt) payload.append('receipt', form.receipt);
  return payload;
}

async function fetchEmployeeOptions() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    employeeOptions.value = (data?.employees?.data ?? []).map((item: any) => ({
      id: item.id,
      full_name: item.full_name ?? `${item.first_name ?? ''} ${item.last_name ?? ''}`.trim(),
      employee_id: item.employee_id ?? '',
      avatar_url: item.avatar_url ?? null,
      department: item.department ?? null,
      personal_email: item.personal_email ?? null,
      work_email: item.work_email ?? null
    }));

    if (!employeeOptions.value.length) {
      employeeOptions.value = dummyEmployees;
    }
  } catch (error) {
    employeeOptions.value = dummyEmployees;
    showSnackbar('Failed to load employee list.', 'warning');
  }
}

async function fetchExpenses() {
  loading.value = true;
  try {
    const monthPart = filters.month ? Number(filters.month.split('-')[1]) : undefined;
    const yearPart = filters.month ? Number(filters.month.split('-')[0]) : undefined;

    const params: Record<string, string | number | undefined> = {
      search: filters.search || undefined,
      category: filters.category || undefined,
      department: filters.department || undefined,
      status: filters.status || undefined,
      month: monthPart,
      year: yearPart,
      page: pagination.page,
      per_page: pagination.perPage
    };

    if (activeTab.value === 'my' && resolvedEmployeeId.value) {
      params.employee_id = resolvedEmployeeId.value;
    }

    const { data } = await axios.get('/api/hr/expenses', { params });
    expenses.value = (data?.expenses?.data ?? []).map((item: any) => normalizeExpense(item));
    pagination.total = data?.expenses?.total ?? 0;
    summary.value = {
      total_submitted: Number(data?.stats?.awaiting_review ?? 0),
      total_approved: parseCurrencyStat(data?.stats?.total_approved),
      total_pending: parseCurrencyStat(data?.stats?.total_pending),
      total_paid: parseCurrencyStat(data?.stats?.paid_this_month)
    };
    departments.value = (data?.filters?.departments?.length ? data.filters.departments : defaultDepartments) as string[];
    categories.value = (data?.filters?.categories?.length ? data.filters.categories : defaultCategories) as string[];

    if (selectedExpense.value) {
      selectedExpense.value = expenses.value.find((item) => item.id === selectedExpense.value?.id) ?? selectedExpense.value;
    }
  } catch (error: any) {
    expenses.value = [];
    pagination.total = 0;
    showSnackbar(error?.response?.data?.message ?? 'Failed to load expenses.', 'error');
  } finally {
    loading.value = false;
  }
}

function handleTableOptions(options: any) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;
  fetchExpenses();
}

async function saveExpense() {
  if (!form.employee_id || !form.title || !form.category || !form.amount || !form.expense_date) {
    showSnackbar('Complete all required fields.', 'error');
    return;
  }

  saving.value = true;
  try {
    const payload = buildFormData();
    const config = { headers: { 'Content-Type': 'multipart/form-data' } };

    await axios.post('/api/hr/expenses', payload, config);
    showSnackbar('Expense submitted.');

    drawerOpen.value = false;
    await fetchExpenses();
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to save expense.', 'error');
  } finally {
    saving.value = false;
  }
}

function askAction(action: 'approve' | 'delete', item: Expense) {
  const titleMap = {
    approve: 'Approve Expense',
    delete: 'Delete Expense'
  };
  const colorMap = {
    approve: 'success',
    delete: 'error'
  };

  confirmDialog.value = {
    show: true,
    title: titleMap[action],
    message:
      action === 'delete'
        ? `Delete "${item.title}" for ${item.employee.full_name}? This cannot be undone.`
        : `Approve "${item.title}" for ${item.employee.full_name}?`,
    action,
    id: item.id,
    color: colorMap[action]
  };
}

async function confirmAction() {
  const current = confirmDialog.value;
  if (!current.id) return;

  try {
    if (current.action === 'approve') {
      await axios.patch(`/api/hr/expenses/${current.id}/approve`);
      showSnackbar('Expense approved.');
    }

    if (current.action === 'delete') {
      await axios.delete(`/api/hr/expenses/${current.id}`);
      detailDialog.value = false;
      showSnackbar('Expense deleted.');
    }

    confirmDialog.value.show = false;
    await fetchExpenses();
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Action failed.', 'error');
  }
}

function openRejectDialog(item: Expense) {
  rejectForm.id = item.id;
  rejectForm.employee = item.employee.full_name;
  rejectForm.title = item.title;
  rejectForm.amount = Number(item.amount);
  rejectForm.rejection_reason = '';
  rejectDialog.value = true;
}

async function rejectExpense() {
  if (!rejectForm.id || !rejectForm.rejection_reason.trim()) {
    showSnackbar('Rejection reason is required.', 'error');
    return;
  }

  try {
    await axios.patch(`/api/hr/expenses/${rejectForm.id}/reject`, {
      reason: rejectForm.rejection_reason.trim()
    });
    rejectDialog.value = false;
    detailDialog.value = false;
    showSnackbar('Expense rejected.');
    await fetchExpenses();
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to reject expense.', 'error');
  }
}

function openPaymentDialog(item: Expense) {
  paymentForm.id = item.id;
  paymentForm.employee = item.employee.full_name;
  paymentForm.amount = Number(item.amount);
  paymentForm.payment_method = 'Bank Transfer';
  paymentForm.reference_number = '';
  paymentDialog.value = true;
}

async function markPaid() {
  if (!paymentForm.id) {
    return;
  }

  try {
    await axios.patch(`/api/hr/expenses/${paymentForm.id}/paid`);
    paymentDialog.value = false;
    detailDialog.value = false;
    showSnackbar('Expense marked as paid.');
    await fetchExpenses();
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to mark expense as paid.', 'error');
  }
}

function exportCsv() {
  if (!expenses.value.length) return;

  const headers = ['Employee', 'Department', 'Category', 'Title', 'Amount', 'Date', 'Status'];
  const rows = expenses.value.map((item) => [
    item.employee.full_name,
    item.employee.department?.name ?? '-',
    item.category,
    item.title,
    String(item.amount),
    item.expense_date,
    item.status
  ]);

  const csv = [headers, ...rows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
    .join('\n');

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'expenses.csv';
  link.click();
  URL.revokeObjectURL(url);
}

function openReceipt(path: string | null) {
  const url = receiptUrl(path);
  if (url) window.open(url, '_blank');
}

function timelineItems(item: Expense) {
  return [
    {
      label: 'Pending',
      active: ['Pending', 'Approved', 'Rejected', 'Paid'].includes(item.status),
      date: ['Pending', 'Approved', 'Rejected', 'Paid'].includes(item.status) ? formatDateTime(item.created_at) : null,
      color: 'warning'
    },
    {
      label: item.status === 'Rejected' ? 'Rejected' : 'Approved',
      active: ['Approved', 'Rejected', 'Paid'].includes(item.status),
      date: item.approved_at ? formatDateTime(item.approved_at) : null,
      color: item.status === 'Rejected' ? 'error' : 'success'
    },
    {
      label: 'Paid',
      active: item.status === 'Paid',
      date: item.paid_at ? formatDateTime(item.paid_at) : null,
      color: 'teal'
    }
  ];
}

function selectedCanEdit() {
  return false;
}

function goToEmployee(item: Expense) {
  if (!item.employee?.id) return;
  router.visit(`/hr/employees/${item.employee.id}`);
}

watch(
  () => [filters.search, filters.category, filters.department, filters.status, filters.month, activeTab.value],
  () => {
    pagination.page = 1;
    fetchExpenses();
  }
);

onMounted(async () => {
  await Promise.all([fetchEmployeeOptions(), fetchExpenses()]);
});

onBeforeUnmount(() => {
  revokeReceiptPreview();
});
</script>

<template>
  <BaseBreadcrumb title="Expenses" subtitle="Track and manage employee expense claims" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Expenses</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Track and manage employee expense claims</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-download" @click="exportCsv">Export CSV</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDrawer">Submit Expense</v-btn>
    </div>
  </div>

  <v-row class="mb-0">
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="warning" variant="tonal"><v-icon icon="mdi-clock-outline" /></v-avatar>
          <div>
            <div class="text-caption text-lightText">Awaiting Review</div>
            <div class="text-h5 font-weight-bold">{{ summary.total_submitted }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="success" variant="tonal"><v-icon icon="mdi-check-circle" /></v-avatar>
          <div>
            <div class="text-caption text-lightText">Total Approved (GHS)</div>
            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.total_approved) }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="primary" variant="tonal"><v-icon icon="mdi-cash-clock" /></v-avatar>
          <div>
            <div class="text-caption text-lightText">Total Pending (GHS)</div>
            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.total_pending) }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
    <v-col cols="12" sm="6" md="3">
      <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text class="d-flex align-center ga-3">
          <v-avatar color="purple" variant="tonal"><v-icon icon="mdi-cash-check" /></v-avatar>
          <div>
            <div class="text-caption text-lightText">Paid This Month (GHS)</div>
            <div class="text-h6 font-weight-bold">{{ formatCurrency(summary.total_paid) }}</div>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0">
    <v-card-item class="pb-0">
      <v-tabs v-model="activeTab" color="primary">
        <v-tab value="all">All Expenses</v-tab>
        <v-tab value="my">My Expenses</v-tab>
      </v-tabs>
    </v-card-item>

    <v-divider />

    <v-card-text>
      <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0">
        <v-card-text>
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field v-model="filters.search" placeholder="Search by title or employee..." variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.category" :items="categoryOptions" label="Category" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.department" :items="departmentOptions" label="Department" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" sm="6" md="2">
              <v-text-field v-model="filters.month" type="month" label="Month" variant="outlined" hide-details />
            </v-col>
            <v-col cols="12" md="1" class="d-flex align-center">
              <v-btn variant="text" color="primary" block @click="resetFilters">Reset</v-btn>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <v-skeleton-loader v-if="loading && !expenses.length" type="table" />

      <template v-else>
        <v-data-table-server
          :headers="headers"
          :items="expenses"
          :items-length="pagination.total"
          :items-per-page="pagination.perPage"
          :page="pagination.page"
          :items-per-page-options="perPageOptions"
          item-value="id"
          @update:options="handleTableOptions"
        >
          <template #item.employee="{ item }">
            <div class="d-flex align-center ga-3 cursor-pointer" @click="goToEmployee(rowData(item))">
              <v-avatar color="primary" variant="tonal" size="38">
                <img v-if="rowData(item).employee.avatar_url" :src="rowData(item).employee.avatar_url || ''" :alt="rowData(item).employee.full_name">
                <span v-else class="text-caption font-weight-bold">{{ initials(rowData(item).employee.full_name) }}</span>
              </v-avatar>
              <div>
                <div class="font-weight-medium">{{ rowData(item).employee.full_name }}</div>
                <div class="text-caption text-lightText">{{ rowData(item).employee.department?.name ?? 'No department' }}</div>
              </div>
            </div>
          </template>

          <template #item.category="{ item }">
            <v-chip :color="categoryColor(rowData(item).category)" size="small" variant="tonal">
              <v-icon start :icon="categoryIcon(rowData(item).category)" />
              {{ rowData(item).category }}
            </v-chip>
          </template>

          <template #item.title="{ item }">
            <v-tooltip location="top">
              <template #activator="{ props }">
                <div class="text-truncate font-weight-bold" style="max-width: 220px;" v-bind="props">{{ rowData(item).title }}</div>
              </template>
              <span>{{ rowData(item).title }}</span>
            </v-tooltip>
          </template>

          <template #item.amount="{ item }">
            <strong>{{ formatCurrency(rowData(item).amount, rowData(item).currency) }}</strong>
          </template>

          <template #item.expense_date="{ item }">{{ formatDate(rowData(item).expense_date) }}</template>

          <template #item.receipt="{ item }">
            <v-btn
              v-if="rowData(item).has_receipt"
              icon="mdi-paperclip"
              variant="text"
              color="primary"
              @click="openReceipt(rowData(item).receipt_path)"
            />
            <span v-else class="text-lightText">-</span>
          </template>

          <template #item.status="{ item }">
            <v-chip :color="rowData(item).status_color || statusColor(rowData(item).status)" size="small" variant="tonal">
              {{ rowData(item).status }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <v-menu>
              <template #activator="{ props }">
                <v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" />
              </template>
              <v-list density="compact">
                <v-list-item title="View Details" @click="viewDetails(rowData(item))" />
                <v-list-item v-if="rowData(item).can_approve" title="Approve" @click="askAction('approve', rowData(item))" />
                <v-list-item v-if="rowData(item).can_reject" title="Reject" @click="openRejectDialog(rowData(item))" />
                <v-list-item v-if="rowData(item).can_pay" title="Mark as Paid" @click="openPaymentDialog(rowData(item))" />
                <v-list-item v-if="rowData(item).has_receipt" title="View Receipt" @click="openReceipt(rowData(item).receipt_path)" />
                <v-divider />
                <v-list-item title="Delete" base-color="error" @click="askAction('delete', rowData(item))" />
              </v-list>
            </v-menu>
          </template>

          <template #no-data>
            <div class="py-10 text-center text-lightText">No expenses found.</div>
          </template>
        </v-data-table-server>
      </template>
    </v-card-text>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="540">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <div>
        <h5 class="text-h5 mb-1">{{ editingId ? 'Edit Expense' : 'Submit Expense' }}</h5>
        <p class="text-body-2 text-lightText mb-0">Capture receipts and claim details</p>
      </div>
      <v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-autocomplete
        v-model="form.employee_id"
        :items="employeeSelectItems"
        item-title="title"
        item-value="value"
        label="Employee *"
        variant="outlined"
        class="mb-3"
      >
        <template #item="{ props, item }">
          <v-list-item v-bind="props" :subtitle="item.raw.subtitle" />
        </template>
      </v-autocomplete>

      <v-text-field v-model="form.title" label="Expense Title *" variant="outlined" class="mb-3" />

      <v-select v-model="form.category" :items="categories" label="Category *" variant="outlined" class="mb-3">
        <template #item="{ props, item }">
          <v-list-item v-bind="props">
            <template #prepend>
              <v-icon :icon="categoryIcon(item.raw)" :color="categoryColor(item.raw)" />
            </template>
          </v-list-item>
        </template>
        <template #selection="{ item }">
          <div class="d-flex align-center ga-2">
            <v-icon :icon="categoryIcon(item.raw)" :color="categoryColor(item.raw)" />
            <span>{{ item.raw }}</span>
          </div>
        </template>
      </v-select>

      <v-row>
        <v-col cols="12" md="6">
          <v-text-field v-model="form.amount" type="number" min="0" step="0.01" label="Amount *" variant="outlined" />
        </v-col>
        <v-col cols="12" md="6">
          <v-select v-model="form.currency" :items="currencyOptions" label="Currency" variant="outlined" />
        </v-col>
      </v-row>

      <v-text-field v-model="form.expense_date" type="date" label="Expense Date *" variant="outlined" class="mb-3" />
      <v-textarea v-model="form.description" label="Description" rows="3" variant="outlined" class="mb-3" />
      <v-file-input
        label="Receipt Upload"
        accept="image/*,.pdf"
        variant="outlined"
        prepend-icon="mdi-paperclip"
        show-size
        clearable
        class="mb-3"
        @update:model-value="onReceiptChange"
      />

      <div v-if="receiptPreview" class="mb-4">
        <v-img v-if="receiptPreview.startsWith('blob:') || isImageReceipt(selectedExpense?.receipt_path ?? null)" :src="receiptPreview" max-height="180" cover class="rounded-lg border" />
        <v-alert v-else type="info" variant="tonal" density="comfortable">
          PDF receipt attached.
          <template #append>
            <v-btn size="small" variant="text" @click="openReceipt(selectedExpense?.receipt_path ?? null)">Open</v-btn>
          </template>
        </v-alert>
      </div>

    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="saveExpense">Submit Expense</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="detailDialog" max-width="960">
    <v-card v-if="selectedExpense">
      <v-card-item>
        <div class="d-flex justify-space-between align-start flex-wrap ga-2">
          <div class="d-flex align-center ga-3">
            <v-avatar :color="categoryColor(selectedExpense.category)" variant="tonal" size="44">
              <v-icon :icon="categoryIcon(selectedExpense.category)" />
            </v-avatar>
            <div>
              <div class="text-h5">{{ selectedExpense.title }}</div>
              <div class="text-body-2 text-lightText">{{ selectedExpense.category }}</div>
            </div>
          </div>
          <v-chip :color="selectedExpense.status_color || statusColor(selectedExpense.status)" variant="tonal">
            {{ selectedExpense.status }}
          </v-chip>
        </div>
      </v-card-item>

      <v-divider />

      <v-card-text>
        <v-row>
          <v-col cols="12" md="7">
            <v-card class="rounded-lg mb-4" variant="outlined">
              <v-card-text class="d-flex align-center ga-3">
                <v-avatar color="primary" variant="tonal" size="44">
                  <img v-if="selectedExpense.employee.avatar_url" :src="selectedExpense.employee.avatar_url || ''" :alt="selectedExpense.employee.full_name">
                  <span v-else class="font-weight-bold">{{ initials(selectedExpense.employee.full_name) }}</span>
                </v-avatar>
                <div>
                  <div class="font-weight-medium">{{ selectedExpense.employee.full_name }}</div>
                  <div class="text-caption text-lightText">{{ selectedExpense.employee.employee_id }} - {{ selectedExpense.employee.department?.name ?? 'No department' }}</div>
                </div>
              </v-card-text>
            </v-card>

            <v-card class="rounded-lg mb-4" variant="outlined">
              <v-card-text>
                <div class="text-h4 font-weight-bold mb-1">{{ formatCurrency(selectedExpense.amount, selectedExpense.currency) }}</div>
                <div class="text-body-2 text-lightText mb-4">{{ formatDate(selectedExpense.expense_date) }}</div>
                <div class="text-caption text-lightText mb-1">Category</div>
                <div class="mb-3">{{ selectedExpense.category }}</div>
                <div class="text-caption text-lightText mb-1">Description</div>
                <div class="text-body-2">{{ selectedExpense.description || 'No description provided.' }}</div>
              </v-card-text>
            </v-card>

            <v-card class="rounded-lg" variant="outlined">
              <v-card-title class="text-subtitle-1">Receipt Preview</v-card-title>
              <v-card-text>
                <v-img
                  v-if="selectedExpense.receipt_path && isImageReceipt(selectedExpense.receipt_path)"
                  :src="receiptUrl(selectedExpense.receipt_path) || ''"
                  max-height="220"
                  cover
                  class="rounded-lg"
                />
                <v-alert v-else-if="selectedExpense.receipt_path" type="info" variant="tonal">
                  Receipt uploaded.
                  <template #append>
                    <v-btn size="small" variant="text" @click="openReceipt(selectedExpense.receipt_path)">Open PDF</v-btn>
                  </template>
                </v-alert>
                <div v-else class="text-body-2 text-lightText">No receipt uploaded.</div>
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="5">
            <v-card class="rounded-lg mb-4" variant="outlined">
              <v-card-title class="text-subtitle-1">Status Timeline</v-card-title>
              <v-card-text>
                <v-timeline side="end" density="compact" line-inset="10">
                  <v-timeline-item
                    v-for="step in timelineItems(selectedExpense)"
                    :key="step.label"
                    :dot-color="step.active ? step.color : 'grey-lighten-1'"
                    size="small"
                    fill-dot
                  >
                    <div class="font-weight-medium">{{ step.label }}</div>
                    <div class="text-caption text-lightText">{{ step.date || 'Pending' }}</div>
                  </v-timeline-item>
                </v-timeline>
              </v-card-text>
            </v-card>

            <v-alert v-if="selectedExpense.status === 'Rejected'" type="error" variant="tonal" class="mb-4">
              {{ selectedExpense.rejection_reason || 'No rejection reason provided.' }}
            </v-alert>

            <v-alert v-if="selectedExpense.status === 'Approved' || selectedExpense.status === 'Paid'" type="success" variant="tonal" class="mb-4">
              Approved by {{ selectedExpense.approvedBy?.full_name ?? 'System' }} on {{ formatDateTime(selectedExpense.approved_at) }}
            </v-alert>

            <v-card v-if="selectedExpense.status === 'Paid'" class="rounded-lg" variant="outlined">
              <v-card-text>
                <div class="text-caption text-lightText mb-1">Payment Method</div>
                <div class="mb-2">{{ selectedExpense.payment_method || '-' }}</div>
                <div class="text-caption text-lightText mb-1">Reference Number</div>
                <div class="mb-2">{{ selectedExpense.reference_number || '-' }}</div>
                <div class="text-caption text-lightText mb-1">Paid At</div>
                <div>{{ formatDateTime(selectedExpense.paid_at) }}</div>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-card-text>

      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="detailDialog = false">Close</v-btn>
        <v-btn v-if="selectedCanEdit()" variant="outlined" color="primary" @click="openEditDrawer(selectedExpense)">Edit</v-btn>
        <v-btn v-if="selectedExpense.can_approve" color="success" variant="flat" @click="askAction('approve', selectedExpense)">
          Approve
        </v-btn>
        <v-btn v-if="selectedExpense.can_reject" color="error" variant="outlined" @click="openRejectDialog(selectedExpense)">
          Reject
        </v-btn>
        <v-btn v-if="selectedExpense.can_pay" color="success" variant="flat" @click="openPaymentDialog(selectedExpense)">
          Mark as Paid
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="rejectDialog" max-width="480">
    <v-card>
      <v-card-title class="text-h5">Reject Expense</v-card-title>
      <v-card-text>
        <p class="mb-1"><strong>{{ rejectForm.employee }}</strong></p>
        <p class="text-body-2 text-lightText mb-3">{{ rejectForm.title }} - {{ formatCurrency(rejectForm.amount) }}</p>
        <v-textarea v-model="rejectForm.rejection_reason" label="Rejection Reason *" rows="3" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" @click="rejectExpense">Reject</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="paymentDialog" max-width="480">
    <v-card>
      <v-card-title class="text-h5">Mark as Paid</v-card-title>
      <v-card-text>
        <p class="mb-1"><strong>{{ paymentForm.employee }}</strong></p>
        <p class="text-body-2 text-lightText mb-3">{{ formatCurrency(paymentForm.amount) }}</p>
        <v-select v-model="paymentForm.payment_method" :items="paymentMethods" label="Payment Method *" variant="outlined" class="mb-3" />
        <v-text-field v-model="paymentForm.reference_number" label="Reference Number" variant="outlined" />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="paymentDialog = false">Cancel</v-btn>
        <v-btn color="success" variant="flat" @click="markPaid">Confirm Payment</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="confirmDialog.show" max-width="420">
    <v-card>
      <v-card-title class="text-h5">{{ confirmDialog.title }}</v-card-title>
      <v-card-text>{{ confirmDialog.message }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="confirmDialog.show = false">Cancel</v-btn>
        <v-btn :color="confirmDialog.color" variant="flat" @click="confirmAction">Confirm</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.cursor-pointer {
  cursor: pointer;
}

.drawer-body {
  height: calc(100% - 140px);
  overflow-y: auto;
}

.sticky-footer {
  position: sticky;
  bottom: 0;
  background: #fff;
}

.border-b {
  border-bottom: 1px solid rgba(15, 23, 42, 0.08);
}

.border-t {
  border-top: 1px solid rgba(15, 23, 42, 0.08);
}

.border {
  border: 1px solid rgba(15, 23, 42, 0.08);
}
</style>
