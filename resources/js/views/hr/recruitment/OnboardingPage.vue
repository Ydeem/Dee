<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface TaskProgress {
  id: number;
  onboarding_task_id: number;
  status: string;
  completed_at: string | null;
  notes: string | null;
  task: { id: number; title: string; description: string | null; category: string | null; due_days: number; assigned_to_role: string | null; is_required: boolean; };
}

interface EmployeeOnboarding {
  id: number;
  start_date: string;
  expected_end_date: string | null;
  completed_date: string | null;
  status: string;
  notes: string | null;
  progress: number;
  employee: { id: number; full_name: string; employee_id: string; avatar_url: string | null; department?: { name: string } | null; };
  template: { id: number; name: string };
  buddy?: { id: number; full_name: string; avatar_url: string | null } | null;
  task_progress: TaskProgress[];
}

interface OnboardingTemplate {
  id: number;
  name: string;
  description: string | null;
  status: string;
  tasks_count: number;
  department?: { name: string } | null;
  tasks: Array<{ id: number; title: string; description: string | null; category: string | null; due_days: number; assigned_to_role: string | null; is_required: boolean; sort_order: number; }>;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Recruitment', disabled: false, href: '#' },
  { title: 'Onboarding', disabled: true, href: '#' }
];

const loading = ref(false);
const tab = ref('list');
const onboardings = ref<EmployeeOnboarding[]>([]);
const templates = ref<Array<{ id: number; name: string; tasks_count?: number }>>([]);
const departments = ref<string[]>([]);
const summary = ref({ not_started: 0, in_progress: 0, completed: 0, overdue: 0 });
const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', department: '', status: '' });
const snackbar = ref({ show: false, message: '', color: 'success' });

const drawerOpen = ref(false);
const detailDialog = ref(false);
const detailLoading = ref(false);
const selected = ref<EmployeeOnboarding | null>(null);
const selectedTaskNote = ref('');
const selectedTaskNoteOpen = ref(false);
const selectedTaskMeta = ref<{ onboardingId: number; taskId: number } | null>(null);

const templatesDialog = ref(false);
const templatesData = ref<OnboardingTemplate[]>([]);
const selectedTemplateId = ref<number | null>(null);
const templateForm = reactive({ id: null as number | null, name: '', description: '', department_id: null as number | null, designation_id: null as number | null, status: 'Active' });
const taskForm = reactive({ title: '', description: '', category: 'HR Documents', due_days: 1, assigned_to_role: 'HR', is_required: true });

const employees = ref<any[]>([]);
const form = reactive({ employee_id: null as number | null, onboarding_template_id: null as number | null, start_date: new Date().toISOString().slice(0, 10), assigned_buddy_id: null as number | null, notes: '' });

const headers = [
  { title: 'Employee', key: 'employee' }, { title: 'Template', key: 'template' }, { title: 'Buddy', key: 'buddy' },
  { title: 'Start Date', key: 'start_date' }, { title: 'Expected End', key: 'expected_end_date' }, { title: 'Progress', key: 'progress' }, { title: 'Status', key: 'status' }, { title: 'Actions', key: 'actions' }
];
const statuses = ['Not Started', 'In Progress', 'Completed', 'Overdue', 'Cancelled'];
const categories = ['IT Setup', 'HR Documents', 'Orientation', 'Training', 'Equipment', 'Access & Security'];
const roles = ['HR', 'IT', 'Manager', 'Employee'];
const boardColumns = computed(() => ['Not Started', 'In Progress', 'Completed', 'Overdue'].map((status) => ({ status, items: onboardings.value.filter((o) => o.status === status) })));
const activeTemplate = computed(() => templatesData.value.find((t) => t.id === selectedTemplateId.value) || null);
const employeeOptions = computed(() => employees.value.map((e) => ({ title: `${e.full_name} (${e.employee_id})`, value: e.id })));
const templateOptions = computed(() => templates.value.map((t) => ({ title: t.name, value: t.id })));
const buddyOptions = computed(() => employees.value.map((e) => ({ title: e.full_name, value: e.id })));
const templatePreview = computed(() => templatesData.value.find((t) => t.id === form.onboarding_template_id) || null);

const dummyEmployees = [
  { id: 101, full_name: 'Ama Mensah', employee_id: 'EMP00101', avatar_url: null, department: { name: 'Engineering' } },
  { id: 102, full_name: 'Kofi Boateng', employee_id: 'EMP00102', avatar_url: null, department: { name: 'Engineering' } },
  { id: 103, full_name: 'Akosua Owusu', employee_id: 'EMP00103', avatar_url: null, department: { name: 'Human Resources' } },
  { id: 104, full_name: 'Yaw Darko', employee_id: 'EMP00104', avatar_url: null, department: { name: 'Finance' } },
  { id: 105, full_name: 'Nana Adjei', employee_id: 'EMP00105', avatar_url: null, department: { name: 'Sales' } },
  { id: 106, full_name: 'Kwame Asare', employee_id: 'EMP00106', avatar_url: null, department: { name: 'Operations' } },
  { id: 107, full_name: 'Efua Sarpong', employee_id: 'EMP00107', avatar_url: null, department: { name: 'Marketing' } },
  { id: 108, full_name: 'Kojo Frimpong', employee_id: 'EMP00108', avatar_url: null, department: { name: 'Engineering' } },
  { id: 109, full_name: 'Abena Osei', employee_id: 'EMP00109', avatar_url: null, department: { name: 'Customer Support' } },
  { id: 110, full_name: 'Fiifi Ankrah', employee_id: 'EMP00110', avatar_url: null, department: { name: 'Human Resources' } }
];

const dummyTemplateTasks = [
  { id: 1, title: 'Send welcome email', description: null, category: 'HR Documents', due_days: 1, assigned_to_role: 'HR', is_required: true, sort_order: 1 },
  { id: 2, title: 'Set up work email account', description: null, category: 'IT Setup', due_days: 1, assigned_to_role: 'IT', is_required: true, sort_order: 2 },
  { id: 3, title: 'Provide laptop and equipment', description: null, category: 'Equipment', due_days: 1, assigned_to_role: 'IT', is_required: true, sort_order: 3 },
  { id: 4, title: 'Office tour and introductions', description: null, category: 'Orientation', due_days: 2, assigned_to_role: 'Manager', is_required: true, sort_order: 4 },
  { id: 5, title: 'Complete HR policy orientation', description: null, category: 'Orientation', due_days: 3, assigned_to_role: 'HR', is_required: true, sort_order: 5 },
  { id: 6, title: 'Set up payroll details', description: null, category: 'HR Documents', due_days: 4, assigned_to_role: 'HR', is_required: true, sort_order: 6 }
];

const dummyTemplatesData: OnboardingTemplate[] = [
  {
    id: 1,
    name: 'Standard New Hire Checklist',
    description: 'Default onboarding checklist for all new hires',
    status: 'Active',
    tasks_count: dummyTemplateTasks.length,
    department: { name: 'General' },
    tasks: dummyTemplateTasks
  },
  {
    id: 2,
    name: 'Engineering Onboarding',
    description: 'Technical onboarding for engineering hires',
    status: 'Active',
    tasks_count: dummyTemplateTasks.length,
    department: { name: 'Engineering' },
    tasks: dummyTemplateTasks
  }
];

function buildDummyTaskProgress(completedCount: number): TaskProgress[] {
  return dummyTemplateTasks.map((task, index) => ({
    id: 1000 + task.id + completedCount * 10,
    onboarding_task_id: task.id,
    status: index < completedCount ? 'Completed' : index === completedCount ? 'In Progress' : 'Pending',
    completed_at: index < completedCount ? new Date().toISOString() : null,
    notes: null,
    task
  }));
}

function buildDummyOnboardings(): EmployeeOnboarding[] {
  const scenarios = [
    { status: 'Not Started', progress: 0, startOffset: 2, endOffset: 25, completeOn: null, completedTasks: 0 },
    { status: 'In Progress', progress: 33, startOffset: 7, endOffset: 21, completeOn: null, completedTasks: 2 },
    { status: 'In Progress', progress: 67, startOffset: 12, endOffset: 18, completeOn: null, completedTasks: 4 },
    { status: 'Completed', progress: 100, startOffset: 32, endOffset: 2, completeOn: 1, completedTasks: 6 },
    { status: 'Overdue', progress: 50, startOffset: 40, endOffset: -3, completeOn: null, completedTasks: 3 },
    { status: 'Not Started', progress: 0, startOffset: 1, endOffset: 20, completeOn: null, completedTasks: 0 },
    { status: 'In Progress', progress: 20, startOffset: 5, endOffset: 16, completeOn: null, completedTasks: 1 },
    { status: 'Completed', progress: 100, startOffset: 20, endOffset: 3, completeOn: 2, completedTasks: 6 },
    { status: 'Overdue', progress: 40, startOffset: 29, endOffset: -5, completeOn: null, completedTasks: 2 },
    { status: 'In Progress', progress: 80, startOffset: 14, endOffset: 7, completeOn: null, completedTasks: 5 }
  ];

  const today = new Date();
  return dummyEmployees.map((employee, index) => {
    const scenario = scenarios[index];
    const start = new Date(today);
    start.setDate(today.getDate() - scenario.startOffset);
    const expected = new Date(today);
    expected.setDate(today.getDate() + scenario.endOffset);
    const completed = scenario.completeOn !== null ? new Date(today) : null;
    if (completed && scenario.completeOn !== null) completed.setDate(today.getDate() - scenario.completeOn);

    return {
      id: index + 1,
      start_date: start.toISOString().slice(0, 10),
      expected_end_date: expected.toISOString().slice(0, 10),
      completed_date: completed ? completed.toISOString().slice(0, 10) : null,
      status: scenario.status,
      notes: null,
      progress: scenario.progress,
      employee,
      template: { id: index % 2 === 0 ? 1 : 2, name: index % 2 === 0 ? 'Standard New Hire Checklist' : 'Engineering Onboarding' },
      buddy: index % 3 === 0 ? null : { id: 201, full_name: 'Senior Buddy', avatar_url: null },
      task_progress: buildDummyTaskProgress(scenario.completedTasks)
    };
  });
}

function applyDummyData(snackbarMessage?: string) {
  const source = buildDummyOnboardings();
  const filtered = source.filter((item) => {
    if (filters.status && item.status !== filters.status) return false;
    if (filters.department && (item.employee.department?.name ?? '') !== filters.department) return false;
    if (filters.search) {
      const text = `${item.employee.full_name} ${item.employee.employee_id}`.toLowerCase();
      if (!text.includes(filters.search.toLowerCase())) return false;
    }
    return true;
  });

  onboardings.value = filtered;
  pagination.total = filtered.length;
  summary.value = {
    not_started: source.filter((item) => item.status === 'Not Started').length,
    in_progress: source.filter((item) => item.status === 'In Progress').length,
    completed: source.filter((item) => item.status === 'Completed').length,
    overdue: source.filter((item) => item.status === 'Overdue').length
  };
  templates.value = dummyTemplatesData.map((item) => ({ id: item.id, name: item.name, tasks_count: item.tasks_count }));
  departments.value = [...new Set(source.map((item) => item.employee.department?.name).filter(Boolean) as string[])];
  templatesData.value = dummyTemplatesData;
  employees.value = dummyEmployees;
  if (!selectedTemplateId.value && templatesData.value.length) selectedTemplateId.value = templatesData.value[0].id;
  if (snackbarMessage) snackbar.value = { show: true, message: snackbarMessage, color: 'warning' };
}

function initials(name: string) { return name.split(' ').filter(Boolean).slice(0, 2).map((s) => s[0]).join('').toUpperCase(); }
function statusColor(status: string) { if (status === 'Completed') return 'success'; if (status === 'In Progress') return 'primary'; if (status === 'Overdue') return 'error'; return 'secondary'; }
function progressColor(item: EmployeeOnboarding) { if (item.progress >= 100) return 'success'; if (item.status === 'Overdue') return 'error'; return 'primary'; }
function isLate(item: EmployeeOnboarding) { return !!item.expected_end_date && item.status !== 'Completed' && new Date(item.expected_end_date).getTime() < new Date().setHours(0, 0, 0, 0); }
function resetFilters() { filters.search = ''; filters.department = ''; filters.status = ''; }

async function fetchEmployees() {
  try {
    const { data } = await axios.get('/api/hr/employees', { params: { per_page: 200 } });
    employees.value = data?.employees?.data?.length ? data.employees.data : dummyEmployees;
  } catch {
    employees.value = dummyEmployees;
  }
}

async function fetchOnboardings() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/onboarding', { params: { search: filters.search || undefined, department: filters.department || undefined, status: filters.status || undefined, page: pagination.page, per_page: pagination.perPage } });
    const apiRows = data?.onboardings?.data ?? [];
    if (!apiRows.length) {
      applyDummyData('Showing dummy onboarding data.');
      return;
    }

    onboardings.value = apiRows;
    pagination.total = data?.onboardings?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    templates.value = data?.templates ?? [];
    departments.value = data?.departments ?? [];
  } catch {
    applyDummyData('API unavailable. Showing dummy onboarding data.');
  } finally { loading.value = false; }
}

async function fetchTemplatesDialog() {
  try {
    const { data } = await axios.get('/api/hr/onboarding-templates');
    templatesData.value = data?.templates?.length ? data.templates : dummyTemplatesData;
    if (!selectedTemplateId.value && templatesData.value.length) selectedTemplateId.value = templatesData.value[0].id;
  } catch {
    templatesData.value = dummyTemplatesData;
    if (!selectedTemplateId.value && templatesData.value.length) selectedTemplateId.value = templatesData.value[0].id;
  }
}

function openStart() {
  form.employee_id = null; form.onboarding_template_id = null; form.start_date = new Date().toISOString().slice(0, 10); form.assigned_buddy_id = null; form.notes = '';
  drawerOpen.value = true;
}

async function startOnboarding() {
  try {
    await axios.post('/api/hr/onboarding', { ...form, notes: form.notes || null, assigned_buddy_id: form.assigned_buddy_id || null });
    drawerOpen.value = false;
    snackbar.value = { show: true, message: 'Onboarding started.', color: 'success' };
    fetchOnboardings();
  } catch (e: any) {
    snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Failed to start onboarding.', color: 'error' };
  }
}

async function viewDetails(item: EmployeeOnboarding) {
  detailLoading.value = true;
  detailDialog.value = true;
  try {
    const { data } = await axios.get(`/api/hr/onboarding/${item.id}`);
    selected.value = data?.onboarding ?? item;
  } catch { selected.value = item; } finally { detailLoading.value = false; }
}

function openTaskNote(onboardingId: number, taskId: number, currentNotes: string | null) {
  selectedTaskMeta.value = { onboardingId, taskId };
  selectedTaskNote.value = currentNotes || '';
  selectedTaskNoteOpen.value = true;
}

async function setTaskStatus(onboardingId: number, task: TaskProgress, status: string, notes?: string) {
  try {
    await axios.patch(`/api/hr/onboarding/${onboardingId}/tasks/${task.onboarding_task_id}`, { status, notes: notes ?? task.notes ?? null });
    if (selected.value?.id === onboardingId) {
      const local = selected.value.task_progress.find((p) => p.onboarding_task_id === task.onboarding_task_id);
      if (local) {
        local.status = status;
        local.notes = notes ?? local.notes;
        local.completed_at = status === 'Completed' ? new Date().toISOString() : null;
      }
      const total = selected.value.task_progress.length;
      const completed = selected.value.task_progress.filter((p) => p.status === 'Completed').length;
      selected.value.progress = total > 0 ? Math.round((completed / total) * 100) : 0;
      if (selected.value.progress === 100) selected.value.status = 'Completed';
      if (selected.value.status === 'Not Started' && completed > 0) selected.value.status = 'In Progress';
    }
    const row = onboardings.value.find((o) => o.id === onboardingId);
    if (row && selected.value) {
      row.progress = selected.value.progress;
      row.status = selected.value.status;
    }
    fetchOnboardings();
  } catch {
    snackbar.value = { show: true, message: 'Failed to update task status.', color: 'error' };
  }
}

async function saveTaskNote() {
  if (!selectedTaskMeta.value) return;
  const { onboardingId, taskId } = selectedTaskMeta.value;
  const task = selected.value?.task_progress.find((p) => p.onboarding_task_id === taskId);
  if (!task) return;
  await setTaskStatus(onboardingId, task, task.status, selectedTaskNote.value);
  selectedTaskNoteOpen.value = false;
}

async function updateOnboardingStatus(item: EmployeeOnboarding, status: string) {
  try {
    await axios.put(`/api/hr/onboarding/${item.id}`, { status, assigned_buddy_id: item.buddy?.id ?? null, notes: item.notes ?? null });
    snackbar.value = { show: true, message: 'Status updated.', color: 'success' };
    fetchOnboardings();
  } catch {
    snackbar.value = { show: true, message: 'Failed to update status.', color: 'error' };
  }
}

async function removeOnboarding(item: EmployeeOnboarding) {
  try {
    await axios.delete(`/api/hr/onboarding/${item.id}`);
    snackbar.value = { show: true, message: 'Onboarding deleted.', color: 'success' };
    fetchOnboardings();
  } catch {
    snackbar.value = { show: true, message: 'Delete failed.', color: 'error' };
  }
}

async function saveTemplate() {
  try {
    if (templateForm.id) await axios.put(`/api/hr/onboarding-templates/${templateForm.id}`, templateForm);
    else await axios.post('/api/hr/onboarding-templates', templateForm);
    await fetchTemplatesDialog();
    await fetchOnboardings();
  } catch { snackbar.value = { show: true, message: 'Failed to save template.', color: 'error' }; }
}

function selectTemplate(template: OnboardingTemplate) {
  selectedTemplateId.value = template.id;
  templateForm.id = template.id;
  templateForm.name = template.name;
  templateForm.description = template.description || '';
  templateForm.department_id = null;
  templateForm.designation_id = null;
  templateForm.status = template.status;
}

function newTemplate() {
  selectedTemplateId.value = null;
  templateForm.id = null;
  templateForm.name = '';
  templateForm.description = '';
  templateForm.department_id = null;
  templateForm.designation_id = null;
  templateForm.status = 'Active';
}

async function deleteTemplate(template: OnboardingTemplate) {
  await axios.delete(`/api/hr/onboarding-templates/${template.id}`);
  await fetchTemplatesDialog();
  await fetchOnboardings();
}

async function addTemplateTask() {
  if (!selectedTemplateId.value) return;
  await axios.post(`/api/hr/onboarding-templates/${selectedTemplateId.value}/tasks`, taskForm);
  taskForm.title = ''; taskForm.description = ''; taskForm.category = 'HR Documents'; taskForm.due_days = 1; taskForm.assigned_to_role = 'HR'; taskForm.is_required = true;
  await fetchTemplatesDialog();
}

async function deleteTemplateTask(taskId: number) {
  if (!selectedTemplateId.value) return;
  await axios.delete(`/api/hr/onboarding-templates/${selectedTemplateId.value}/tasks/${taskId}`);
  await fetchTemplatesDialog();
}

function groupedTaskProgress() {
  const source = selected.value?.task_progress ?? [];
  return source.reduce((acc: Record<string, TaskProgress[]>, item) => {
    const key = item.task.category || 'General';
    if (!acc[key]) acc[key] = [];
    acc[key].push(item);
    return acc;
  }, {});
}

function handleTableOptions(o: any) {
  pagination.page = o.page;
  pagination.perPage = o.itemsPerPage;
  fetchOnboardings();
}

watch(() => [filters.search, filters.department, filters.status], () => { pagination.page = 1; fetchOnboardings(); });
onMounted(async () => { await Promise.all([fetchEmployees(), fetchOnboardings()]); });
</script>

<template>
  <BaseBreadcrumb title="Onboarding" subtitle="Manage new hire onboarding journeys" :breadcrumbs="breadcrumbs" />
  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4"><div><h2 class="text-h3 mb-1">Onboarding</h2><p class="text-subtitle-1 text-lightText mb-0">Manage new hire onboarding journeys</p></div><div class="d-flex ga-2"><v-btn variant="outlined" prepend-icon="mdi-clipboard-list" @click="templatesDialog = true; fetchTemplatesDialog()">Manage Templates</v-btn><v-btn color="primary" prepend-icon="mdi-plus" @click="openStart">Start Onboarding</v-btn></div></div>
  <v-row class="mb-1"><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Not Started: <strong>{{ summary.not_started }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>In Progress: <strong>{{ summary.in_progress }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Completed: <strong>{{ summary.completed }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Overdue: <strong>{{ summary.overdue }}</strong></v-card-text></v-card></v-col></v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined">
    <v-tabs v-model="tab" color="primary" class="px-4 pt-2"><v-tab value="list">Onboarding List</v-tab><v-tab value="board">Board View</v-tab></v-tabs><v-divider />
    <v-window v-model="tab">
      <v-window-item value="list"><div class="pa-4">
        <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined"><v-card-text><v-row><v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by employee name..." variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="filters.department" :items="[{ title:'All Departments', value:'' }, ...departments.map((d) => ({ title:d, value:d }))]" label="Department" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="filters.status" :items="[{ title:'All', value:'' }, ...statuses.map((s) => ({ title:s, value:s }))]" label="Status" variant="outlined" hide-details /></v-col></v-row><div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div></v-card-text></v-card>
        <v-skeleton-loader v-if="loading && !onboardings.length" type="table" />
        <v-data-table-server v-else :headers="headers" :items="onboardings" :items-length="pagination.total" :items-per-page="pagination.perPage" :page="pagination.page" :items-per-page-options="[10,25,50]" @update:options="handleTableOptions">
          <template #item.employee="{ item }"><div class="d-flex align-center ga-3 cursor-pointer" @click="viewDetails(item)"><v-avatar size="34" color="primary" variant="tonal"><img v-if="item.employee.avatar_url" :src="item.employee.avatar_url" :alt="item.employee.full_name" /><span v-else class="text-caption font-weight-bold">{{ initials(item.employee.full_name) }}</span></v-avatar><div><div class="font-weight-medium">{{ item.employee.full_name }}</div><v-chip size="x-small" variant="tonal">{{ item.employee.department?.name ?? 'No Dept' }}</v-chip></div></div></template>
          <template #item.template="{ item }"><v-chip size="small" variant="tonal">{{ item.template.name }}</v-chip></template>
          <template #item.buddy="{ item }"><span v-if="item.buddy">{{ item.buddy.full_name }}</span><span v-else>Not Assigned</span></template>
          <template #item.expected_end_date="{ item }"><span :class="{ 'text-error': isLate(item) }">{{ item.expected_end_date ?? '-' }}</span></template>
          <template #item.progress="{ item }"><div class="d-flex align-center ga-2"><v-progress-linear :model-value="item.progress || 0" :color="progressColor(item)" height="8" rounded class="flex-grow-1" /><span class="text-caption">{{ item.progress || 0 }}%</span></div></template>
          <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small" variant="tonal">{{ item.status }}</v-chip></template>
          <template #item.actions="{ item }"><v-menu><template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template><v-list><v-list-item title="View Details" @click="viewDetails(item)" /><v-list-item title="Mark Complete" @click="updateOnboardingStatus(item, 'Completed')" /><v-list-item title="Cancel" @click="updateOnboardingStatus(item, 'Cancelled')" /><v-list-item title="Delete" base-color="error" @click="removeOnboarding(item)" /></v-list></v-menu></template>
        </v-data-table-server>
      </div></v-window-item>

      <v-window-item value="board"><div class="pa-4"><v-row><v-col v-for="col in boardColumns" :key="col.status" cols="12" sm="6" md="4" lg="3"><v-card variant="outlined"><v-card-title>{{ col.status }} <v-chip size="x-small">{{ col.items.length }}</v-chip></v-card-title><v-divider /><v-card-text class="board-col"><v-card v-for="item in col.items" :key="item.id" class="mb-2" variant="outlined"><v-card-text><div class="d-flex align-center ga-2"><v-avatar size="26" color="primary" variant="tonal"><span class="text-caption">{{ initials(item.employee.full_name) }}</span></v-avatar><div class="text-body-2 font-weight-medium">{{ item.employee.full_name }}</div></div><v-chip size="x-small" variant="tonal" class="mt-1">{{ item.employee.department?.name ?? 'No Dept' }}</v-chip><div class="text-caption mt-1">{{ item.template.name }}</div><div class="d-flex align-center ga-2 mt-1"><v-progress-linear :model-value="item.progress || 0" :color="progressColor(item)" height="6" rounded class="flex-grow-1" /><span class="text-caption">{{ item.progress || 0 }}%</span></div><div class="text-caption mt-1">Start: {{ item.start_date }} | End: {{ item.expected_end_date ?? '-' }}</div><div class="text-caption">{{ item.buddy ? `Buddy: ${item.buddy.full_name}` : 'No buddy' }}</div><v-btn size="small" class="mt-2" variant="outlined" @click="viewDetails(item)">View Details</v-btn></v-card-text></v-card></v-card-text></v-card></v-col></v-row></div></v-window-item>
    </v-window>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="560"><div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">Start Onboarding</h5><v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" /></div><div class="pa-4 drawer-body"><v-autocomplete v-model="form.employee_id" :items="employeeOptions" label="Employee *" variant="outlined" class="mb-3" /><v-select v-model="form.onboarding_template_id" :items="templateOptions" label="Onboarding Template *" variant="outlined" class="mb-3" /><v-text-field v-model="form.start_date" type="date" label="Start Date *" variant="outlined" class="mb-3" /><v-autocomplete v-model="form.assigned_buddy_id" :items="buddyOptions" label="Assign Buddy" variant="outlined" class="mb-3" /><v-textarea v-model="form.notes" label="Notes" rows="3" variant="outlined" class="mb-3" /><v-card v-if="templatePreview" variant="outlined"><v-card-text><div class="font-weight-medium mb-1">This template includes {{ templatePreview.tasks.length }} tasks over {{ Math.max(...templatePreview.tasks.map((t) => t.due_days), 0) }} days</div><div v-for="t in templatePreview.tasks.slice(0,5)" :key="t.id" class="text-caption">- [{{ t.category || 'General' }}] {{ t.title }} (Day {{ t.due_days }})</div></v-card-text></v-card></div><div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn><v-btn color="primary" @click="startOnboarding">Start Onboarding</v-btn></div></v-navigation-drawer>

  <v-dialog v-model="detailDialog" max-width="860"><v-card><v-card-title class="text-h5">Onboarding Details</v-card-title><v-card-text v-if="detailLoading"><v-skeleton-loader type="article" /></v-card-text><v-card-text v-else-if="selected"><div class="d-flex align-center ga-3 mb-3"><v-avatar size="50" color="primary" variant="tonal"><img v-if="selected.employee.avatar_url" :src="selected.employee.avatar_url" :alt="selected.employee.full_name" /><span v-else class="font-weight-bold">{{ initials(selected.employee.full_name) }}</span></v-avatar><div><h4 class="text-h5 mb-1">{{ selected.employee.full_name }}</h4><div class="text-caption text-lightText">{{ selected.employee.department?.name ?? 'No Department' }}</div></div><v-chip class="ms-auto" :color="statusColor(selected.status)" variant="tonal">{{ selected.status }}</v-chip></div><div class="d-flex align-center ga-2 mb-3"><v-progress-linear :model-value="selected.progress || 0" :color="progressColor(selected)" height="10" rounded class="flex-grow-1" /><span class="text-caption">{{ selected.progress || 0 }}%</span></div><div class="text-caption mb-2">{{ selected.task_progress.filter((p) => p.status === 'Completed').length }} of {{ selected.task_progress.length }} tasks completed</div><v-row><v-col cols="12" md="8"><div v-for="(tasks, category) in groupedTaskProgress()" :key="category" class="mb-3"><v-expansion-panels><v-expansion-panel><v-expansion-panel-title>{{ category }} ({{ tasks.length }})</v-expansion-panel-title><v-expansion-panel-text><div v-for="tp in tasks" :key="tp.id" class="task-row"><div class="d-flex align-center ga-2 flex-wrap"><v-menu><template #activator="{ props }"><v-chip size="small" variant="outlined" v-bind="props">{{ tp.status }}</v-chip></template><v-list><v-list-item title="Mark Pending" @click="setTaskStatus(selected.id, tp, 'Pending')" /><v-list-item title="Mark In Progress" @click="setTaskStatus(selected.id, tp, 'In Progress')" /><v-list-item title="Mark Complete" @click="setTaskStatus(selected.id, tp, 'Completed')" /><v-list-item title="Skip Task" @click="setTaskStatus(selected.id, tp, 'Skipped')" /></v-list></v-menu><div class="font-weight-medium" :class="{ 'text-decoration-line-through': tp.status === 'Completed' }">{{ tp.task.title }}</div><v-chip size="x-small" variant="tonal">Day {{ tp.task.due_days }}</v-chip><v-chip v-if="tp.task.is_required" size="x-small" color="error" variant="outlined">Required</v-chip><v-btn size="x-small" variant="text" icon="mdi-note-edit-outline" @click="openTaskNote(selected.id, tp.onboarding_task_id, tp.notes)" /></div><div class="text-caption text-lightText ms-1">{{ tp.task.assigned_to_role || 'Unassigned' }} <span v-if="tp.completed_at">- completed {{ tp.completed_at }}</span></div></div></v-expansion-panel-text></v-expansion-panel></v-expansion-panels></div></v-col><v-col cols="12" md="4"><v-card variant="outlined" class="mb-2"><v-card-text><div class="text-caption text-lightText">Template</div><div class="mb-2">{{ selected.template.name }}</div><div class="text-caption text-lightText">Start</div><div class="mb-2">{{ selected.start_date }}</div><div class="text-caption text-lightText">Expected End</div><div class="mb-2">{{ selected.expected_end_date ?? '-' }}</div><div class="text-caption text-lightText">Completed Date</div><div class="mb-2">{{ selected.completed_date ?? '-' }}</div><div class="text-caption text-lightText">Buddy</div><div>{{ selected.buddy?.full_name ?? 'Not assigned' }}</div></v-card-text></v-card><v-card variant="outlined"><v-card-text><div class="font-weight-medium mb-2">Progress Breakdown</div><div v-for="(tasks, category) in groupedTaskProgress()" :key="`bd-${category}`" class="mb-2"><div class="text-caption">{{ category }}: {{ tasks.filter((t) => t.status === 'Completed').length }}/{{ tasks.length }}</div><v-progress-linear :model-value="tasks.length ? Math.round((tasks.filter((t) => t.status === 'Completed').length / tasks.length) * 100) : 0" height="6" rounded /></div></v-card-text></v-card></v-col></v-row></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog = false">Close</v-btn><v-btn variant="outlined" color="primary" @click="selected && updateOnboardingStatus(selected, 'Completed')" v-if="selected?.status === 'In Progress'">Mark Complete</v-btn><v-btn variant="outlined" color="error" @click="selected && updateOnboardingStatus(selected, 'Cancelled')">Cancel Onboarding</v-btn></v-card-actions></v-card></v-dialog>

  <v-dialog v-model="selectedTaskNoteOpen" max-width="500"><v-card><v-card-title class="text-h5">Task Notes</v-card-title><v-card-text><v-textarea v-model="selectedTaskNote" label="Notes" rows="4" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="selectedTaskNoteOpen = false">Cancel</v-btn><v-btn color="primary" @click="saveTaskNote">Save</v-btn></v-card-actions></v-card></v-dialog>

  <v-dialog v-model="templatesDialog" max-width="980"><v-card><v-card-title class="text-h5">Onboarding Templates</v-card-title><v-card-text><v-row><v-col cols="12" md="4"><v-btn size="small" variant="outlined" prepend-icon="mdi-plus" class="mb-2" @click="newTemplate">New Template</v-btn><v-card v-for="t in templatesData" :key="t.id" class="mb-2" variant="outlined"><v-card-text><div class="d-flex justify-space-between align-center"><div class="font-weight-medium">{{ t.name }}</div><div class="d-flex ga-1"><v-btn icon="mdi-pencil" variant="text" size="small" @click="selectTemplate(t)" /><v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="deleteTemplate(t)" /></div></div><div class="text-caption text-lightText">{{ t.department?.name ?? 'No Department' }}</div><v-chip size="x-small" class="mt-1">{{ t.tasks_count }} tasks</v-chip><v-chip size="x-small" class="mt-1 ms-1" :color="t.status === 'Active' ? 'success' : 'secondary'" variant="tonal">{{ t.status }}</v-chip></v-card-text></v-card></v-col><v-col cols="12" md="8"><v-card variant="outlined" class="mb-3"><v-card-text><v-row><v-col cols="12" md="8"><v-text-field v-model="templateForm.name" label="Template Name *" variant="outlined" /></v-col><v-col cols="12" md="4"><v-select v-model="templateForm.status" :items="['Active','Inactive']" label="Status" variant="outlined" /></v-col><v-col cols="12"><v-textarea v-model="templateForm.description" label="Description" rows="2" variant="outlined" /></v-col></v-row><div class="d-flex justify-end"><v-btn color="primary" @click="saveTemplate">Save Template</v-btn></div></v-card-text></v-card><v-card variant="outlined" class="mb-2" v-if="activeTemplate"><v-card-title class="text-subtitle-1">Tasks</v-card-title><v-card-text><div v-for="task in activeTemplate.tasks" :key="task.id" class="d-flex align-center justify-space-between mb-2"><div><v-chip size="x-small" class="me-1">{{ task.category || 'General' }}</v-chip><span class="font-weight-medium">{{ task.title }}</span><span class="text-caption text-lightText ms-2">Day {{ task.due_days }} | {{ task.assigned_to_role || 'Unassigned' }}</span></div><v-btn icon="mdi-delete" size="small" variant="text" color="error" @click="deleteTemplateTask(task.id)" /></div></v-card-text></v-card><v-card variant="outlined"><v-card-title class="text-subtitle-1">Add Task</v-card-title><v-card-text><v-text-field v-model="taskForm.title" label="Title *" variant="outlined" class="mb-2" /><v-row><v-col cols="12" md="4"><v-select v-model="taskForm.category" :items="categories" label="Category" variant="outlined" /></v-col><v-col cols="12" md="4"><v-text-field v-model.number="taskForm.due_days" type="number" min="1" label="Due Days" variant="outlined" /></v-col><v-col cols="12" md="4"><v-select v-model="taskForm.assigned_to_role" :items="roles" label="Assigned Role" variant="outlined" /></v-col></v-row><v-switch v-model="taskForm.is_required" label="Required" color="primary" /><v-textarea v-model="taskForm.description" label="Description" rows="2" variant="outlined" class="mb-2" /><div class="d-flex justify-end"><v-btn color="primary" @click="addTemplateTask" :disabled="!selectedTemplateId">Add Task</v-btn></div></v-card-text></v-card></v-col></v-row></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="templatesDialog = false">Close</v-btn></v-card-actions></v-card></v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.cursor-pointer { cursor: pointer; }
.drawer-body { height: calc(100% - 130px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
.board-col { max-height: 68vh; overflow-y: auto; }
.task-row { border: 1px solid rgba(0, 0, 0, 0.06); border-radius: 8px; padding: 8px; margin-bottom: 8px; }
</style>
