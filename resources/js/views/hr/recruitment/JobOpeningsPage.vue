<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface JobOpening {
  id: number; title: string; employment_type: string; location: string | null; vacancies: number;
  salary_from: number | null; salary_to: number | null; salary_currency: string; status: string;
  deadline: string | null; applicants_count: number; description: string | null; requirements: string | null;
  benefits: string | null; experience_years: number | null; education_level: string | null; created_at: string;
  department?: { id: number; name: string } | null; designation?: { id: number; name: string } | null; posted_by?: { full_name: string } | null;
}
interface Summary { total_open: number; total_draft: number; total_closed: number; total_applicants: number; }

const breadcrumbs = [{ title: 'HR Module', disabled: false, href: '#' }, { title: 'Recruitment', disabled: false, href: '#' }, { title: 'Job Openings', disabled: true, href: '#' }];
const loading = ref(false);
const viewMode = ref<'table' | 'grid'>('table');
const jobs = ref<JobOpening[]>([]);
const summary = ref<Summary>({ total_open: 0, total_draft: 0, total_closed: 0, total_applicants: 0 });
const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', department: '', type: '', status: '' });
const sort = reactive({ sortBy: 'created_at', sortDir: 'desc' });
const snackbar = ref({ show: false, message: '', color: 'success' });
const confirmDelete = ref({ show: false, id: null as number | null, name: '' });
const detailDialog = ref(false);
const detailJob = ref<JobOpening | null>(null);
const drawerOpen = ref(false);
const editingId = ref<number | null>(null);
const departmentNames = ref<string[]>([]);
const departmentOptions = ref<{ id: number; name: string }[]>([]);
const designationOptions = ref<{ id: number; name: string; department_id?: number | null }[]>([]);

const form = reactive({
  title: '', department_id: null as number | null, designation_id: null as number | null, employment_type: 'Full-time', location: '',
  vacancies: 1, deadline: '', status: 'Draft', description: '', requirements: '', benefits: '', experience_years: null as number | null,
  education_level: 'Any', salary_currency: 'GHS', salary_from: null as number | null, salary_to: null as number | null
});

const headers = [
  { title: 'Job Title', key: 'title', sortable: true }, { title: 'Department', key: 'department', sortable: false }, { title: 'Designation', key: 'designation', sortable: false },
  { title: 'Vacancies', key: 'vacancies', sortable: true }, { title: 'Salary Range', key: 'salary', sortable: false }, { title: 'Deadline', key: 'deadline', sortable: true },
  { title: 'Applicants', key: 'applicants_count', sortable: true }, { title: 'Status', key: 'status', sortable: true }, { title: 'Actions', key: 'actions', sortable: false }
];
const typeOptions = [{ title: 'All', value: '' }, { title: 'Full-time', value: 'Full-time' }, { title: 'Part-time', value: 'Part-time' }, { title: 'Contract', value: 'Contract' }, { title: 'Intern', value: 'Intern' }, { title: 'Remote', value: 'Remote' }];
const statusOptions = [{ title: 'All', value: '' }, { title: 'Open', value: 'Open' }, { title: 'Draft', value: 'Draft' }, { title: 'Closed', value: 'Closed' }, { title: 'On Hold', value: 'On Hold' }];
const createStatus = ['Draft', 'Open', 'Closed', 'On Hold'];

const filteredDesignations = computed(() => !form.department_id ? designationOptions.value : designationOptions.value.filter((d) => d.department_id === form.department_id));
const dummyJobs: JobOpening[] = [{ id: 1, title: 'Senior Software Engineer', employment_type: 'Full-time', location: 'Accra, Ghana', vacancies: 2, salary_from: 5000, salary_to: 8000, salary_currency: 'GHS', status: 'Open', deadline: new Date(Date.now() + 12096e5).toISOString().slice(0, 10), applicants_count: 12, description: 'Build and scale ERP modules.', requirements: 'Laravel + Vue', benefits: 'Health + bonus', experience_years: 4, education_level: 'Bachelors', created_at: new Date().toISOString(), department: { id: 1, name: 'Engineering' }, designation: { id: 1, name: 'Senior Engineer' }, posted_by: { full_name: 'Pontian Npontu' } }];

function statusColor(s: string) { if (s === 'Open') return 'success'; if (s === 'Draft') return 'warning'; if (s === 'On Hold') return 'info'; return 'secondary'; }
function salaryRange(j: JobOpening) { if (!j.salary_from && !j.salary_to) return 'Not specified'; if (j.salary_from && j.salary_to) return `${j.salary_currency} ${Number(j.salary_from).toLocaleString()} - ${Number(j.salary_to).toLocaleString()}`; if (j.salary_from) return `${j.salary_currency} ${Number(j.salary_from).toLocaleString()}+`; return `${j.salary_currency} ${Number(j.salary_to || 0).toLocaleString()}`; }
function isExpired(d: string | null) { return !!d && new Date(d).getTime() < new Date().setHours(0, 0, 0, 0); }
function resetForm() { editingId.value = null; Object.assign(form, { title: '', department_id: null, designation_id: null, employment_type: 'Full-time', location: '', vacancies: 1, deadline: '', status: 'Draft', description: '', requirements: '', benefits: '', experience_years: null, education_level: 'Any', salary_currency: 'GHS', salary_from: null, salary_to: null }); }
function openCreate() { resetForm(); drawerOpen.value = true; }
function openEdit(j: JobOpening) { editingId.value = j.id; Object.assign(form, { title: j.title, department_id: j.department?.id ?? null, designation_id: j.designation?.id ?? null, employment_type: j.employment_type, location: j.location ?? '', vacancies: j.vacancies, deadline: j.deadline ?? '', status: j.status, description: j.description ?? '', requirements: j.requirements ?? '', benefits: j.benefits ?? '', experience_years: j.experience_years, education_level: j.education_level ?? 'Any', salary_currency: j.salary_currency || 'GHS', salary_from: j.salary_from, salary_to: j.salary_to }); drawerOpen.value = true; }
function askDelete(j: JobOpening) { confirmDelete.value = { show: true, id: j.id, name: j.title }; }
function openDetails(j: JobOpening) { detailJob.value = j; detailDialog.value = true; }
function editFromDetail() { if (!detailJob.value) return; detailDialog.value = false; openEdit(detailJob.value); }

async function fetchOptions() {
  try {
    const [d, g] = await Promise.all([axios.get('/api/hr/departments', { params: { per_page: 200 } }), axios.get('/api/hr/designations', { params: { per_page: 200 } })]);
    departmentOptions.value = d.data?.departments?.data ?? [];
    designationOptions.value = g.data?.designations?.data ?? [];
  } catch {}
}

async function fetchJobs() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/job-openings', { params: { search: filters.search || undefined, department: filters.department || undefined, type: filters.type || undefined, status: filters.status || undefined, page: pagination.page, per_page: pagination.perPage, sort_by: sort.sortBy, sort_dir: sort.sortDir } });
    jobs.value = data?.jobs?.data ?? [];
    pagination.total = data?.jobs?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    departmentNames.value = data?.departments ?? [];
    if ((data?.department_options ?? []).length) departmentOptions.value = data.department_options;
    if ((data?.designation_options ?? []).length) designationOptions.value = data.designation_options;
    if (!jobs.value.length) { jobs.value = dummyJobs; pagination.total = dummyJobs.length; }
  } catch {
    jobs.value = dummyJobs;
    pagination.total = dummyJobs.length;
    summary.value = { total_open: 1, total_draft: 0, total_closed: 0, total_applicants: 12 };
    snackbar.value = { show: true, message: 'Using dummy job openings data.', color: 'warning' };
  } finally { loading.value = false; }
}

async function saveJob(nextStatus?: 'Draft' | 'Open') {
  if (!form.title.trim()) return;
  const payload = { ...form, status: nextStatus ?? form.status, location: form.location || null, deadline: form.deadline || null, description: form.description || null, requirements: form.requirements || null, benefits: form.benefits || null, experience_years: form.experience_years || null, education_level: form.education_level || null, salary_from: form.salary_from || null, salary_to: form.salary_to || null };
  try {
    if (editingId.value) await axios.put(`/api/hr/job-openings/${editingId.value}`, payload); else await axios.post('/api/hr/job-openings', payload);
    drawerOpen.value = false;
    snackbar.value = { show: true, message: 'Job saved.', color: 'success' };
    fetchJobs();
  } catch (e: any) { snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Save failed.', color: 'error' }; }
}

async function changeStatus(j: JobOpening, status: string) { try { await axios.patch(`/api/hr/job-openings/${j.id}/status`, { status }); fetchJobs(); } catch {} }
async function duplicateJob(j: JobOpening) { await axios.post('/api/hr/job-openings', { title: `${j.title} (Copy)`, department_id: j.department?.id ?? null, designation_id: j.designation?.id ?? null, employment_type: j.employment_type, location: j.location, vacancies: j.vacancies, salary_from: j.salary_from, salary_to: j.salary_to, salary_currency: j.salary_currency, description: j.description, requirements: j.requirements, benefits: j.benefits, experience_years: j.experience_years, education_level: j.education_level, status: 'Draft', deadline: j.deadline }); fetchJobs(); }
async function confirmDeleteJob() { if (!confirmDelete.value.id) return; try { await axios.delete(`/api/hr/job-openings/${confirmDelete.value.id}`); confirmDelete.value.show = false; fetchJobs(); } catch (e: any) { snackbar.value = { show: true, message: e?.response?.data?.message ?? 'Delete failed.', color: 'error' }; } }

function exportCsv() {
  if (!jobs.value.length) return;
  const rows = [['Title', 'Department', 'Type', 'Location', 'Status', 'Applicants'], ...jobs.value.map((j) => [j.title, j.department?.name ?? '-', j.employment_type, j.location ?? '-', j.status, j.applicants_count])];
  const csv = rows.map((r) => r.map((c) => `\"${String(c).replace(/\"/g, '\"\"')}\"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' }); const url = URL.createObjectURL(blob); const link = document.createElement('a'); link.href = url; link.download = 'job-openings.csv'; link.click(); URL.revokeObjectURL(url);
}

function handleTableOptions(options: any) { pagination.page = options.page; pagination.perPage = options.itemsPerPage; if (options.sortBy?.length) { sort.sortBy = options.sortBy[0].key; sort.sortDir = options.sortBy[0].order ?? 'asc'; } else { sort.sortBy = 'created_at'; sort.sortDir = 'desc'; } fetchJobs(); }
watch(() => [filters.search, filters.department, filters.type, filters.status], () => { pagination.page = 1; fetchJobs(); });
watch(() => form.department_id, () => { if (!filteredDesignations.value.some((d) => d.id === form.designation_id)) form.designation_id = null; });
onMounted(async () => { await fetchOptions(); await fetchJobs(); });
</script>

<template>
  <BaseBreadcrumb title="Job Openings" subtitle="Manage recruitment and open positions" :breadcrumbs="breadcrumbs" />
  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4"><div><h2 class="text-h3 mb-1">Job Openings</h2><p class="text-subtitle-1 text-lightText mb-0">Manage recruitment and open positions</p></div><div class="d-flex ga-2"><v-btn variant="outlined" prepend-icon="mdi-download" @click="exportCsv">Export</v-btn><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Post Job</v-btn></div></div>

  <v-row class="mb-1"><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Open Positions: <strong>{{ summary.total_open }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Draft Jobs: <strong>{{ summary.total_draft }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Closed Jobs: <strong>{{ summary.total_closed }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="3"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>Total Applicants: <strong>{{ summary.total_applicants }}</strong></v-card-text></v-card></v-col></v-row>

  <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined" elevation="0"><v-card-text><v-row><v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by job title or location..." variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="3"><v-select v-model="filters.department" :items="[{ title: 'All Departments', value: '' }, ...departmentNames.map((name) => ({ title: name, value: name }))]" label="Department" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="filters.type" :items="typeOptions" label="Employment Type" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="statusOptions" label="Status" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="1" class="d-flex align-center justify-end ga-1"><v-btn :color="viewMode === 'table' ? 'primary' : 'default'" icon="mdi-view-list" variant="text" @click="viewMode = 'table'" /><v-btn :color="viewMode === 'grid' ? 'primary' : 'default'" icon="mdi-view-grid" variant="text" @click="viewMode = 'grid'" /></v-col></v-row></v-card-text></v-card>

  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text>
    <v-skeleton-loader v-if="loading && !jobs.length" type="table" />
    <v-data-table-server v-else-if="viewMode === 'table'" :headers="headers" :items="jobs" :items-length="pagination.total" :items-per-page="pagination.perPage" :page="pagination.page" :items-per-page-options="[10, 25, 50]" item-value="id" @update:options="handleTableOptions">
      <template #item.title="{ item }"><div class="cursor-pointer" @click="openDetails(item)"><div class="font-weight-medium text-body-1">{{ item.title }}</div><v-chip size="x-small" color="primary" variant="tonal">{{ item.employment_type }}</v-chip></div></template>
      <template #item.department="{ item }">{{ item.department?.name ?? '-' }}</template><template #item.designation="{ item }">{{ item.designation?.name ?? '-' }}</template>
      <template #item.vacancies="{ item }"><v-chip size="small" color="primary" variant="tonal">{{ item.vacancies }}</v-chip></template><template #item.salary="{ item }">{{ salaryRange(item) }}</template>
      <template #item.deadline="{ item }"><span :class="{ 'text-error': isExpired(item.deadline) }">{{ item.deadline ?? '-' }}</span></template><template #item.applicants_count="{ item }"><v-chip size="small" variant="outlined" class="cursor-pointer" @click="router.visit(`/hr/applicants?job=${item.id}`)">{{ item.applicants_count }}</v-chip></template>
      <template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small" variant="tonal">{{ item.status }}</v-chip></template>
      <template #item.actions="{ item }"><v-menu><template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template><v-list><v-list-item title="View Details" @click="openDetails(item)" /><v-list-item title="Edit Job" @click="openEdit(item)" /><v-list-item title="Open" @click="changeStatus(item, 'Open')" /><v-list-item title="Draft" @click="changeStatus(item, 'Draft')" /><v-list-item title="Closed" @click="changeStatus(item, 'Closed')" /><v-list-item title="On Hold" @click="changeStatus(item, 'On Hold')" /><v-list-item title="View Applicants" @click="router.visit(`/hr/applicants?job=${item.id}`)" /><v-list-item title="Duplicate" @click="duplicateJob(item)" /><v-list-item title="Delete" base-color="error" @click="askDelete(item)" /></v-list></v-menu></template>
    </v-data-table-server>
    <v-row v-else><v-col v-for="item in jobs" :key="item.id" cols="12" sm="6" md="4" lg="3"><v-card class="rounded-lg hr-card-shadow" variant="outlined" elevation="0"><v-card-text><div class="d-flex justify-space-between align-center mb-2"><v-chip size="x-small" variant="tonal">{{ item.department?.name ?? 'No Department' }}</v-chip><v-chip :color="statusColor(item.status)" size="x-small" variant="tonal">{{ item.status }}</v-chip></div><h6 class="text-h6 mb-2">{{ item.title }}</h6><p class="text-body-2 text-lightText mb-1">{{ item.location ?? 'Not specified' }}</p><p class="text-body-2 text-lightText mb-1">{{ item.employment_type }}</p><p class="text-body-2 text-lightText mb-1">{{ salaryRange(item) }}</p><p class="text-body-2 mb-3" :class="{ 'text-error': isExpired(item.deadline) }">{{ item.deadline ?? 'No deadline' }}</p><div class="d-flex justify-space-between align-center"><v-chip size="small" variant="outlined">{{ item.applicants_count }}</v-chip><div class="d-flex ga-1"><v-btn size="small" variant="outlined" @click="openDetails(item)">View</v-btn><v-btn size="small" icon="mdi-pencil" variant="text" @click="openEdit(item)" /></div></div></v-card-text></v-card></v-col></v-row>
  </v-card-text></v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="640"><div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">{{ editingId ? 'Edit Job Opening' : 'Post New Job' }}</h5><v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" /></div><div class="pa-4 drawer-body"><v-text-field v-model="form.title" label="Job Title *" variant="outlined" class="mb-3" /><v-row><v-col cols="12" md="6"><v-select v-model="form.department_id" :items="departmentOptions.map((i) => ({ title: i.name, value: i.id }))" label="Department" variant="outlined" /></v-col><v-col cols="12" md="6"><v-select v-model="form.designation_id" :items="filteredDesignations.map((i) => ({ title: i.name, value: i.id }))" label="Designation" variant="outlined" /></v-col><v-col cols="12" md="6"><v-select v-model="form.employment_type" :items="typeOptions.filter((i) => i.value).map((i) => i.value)" label="Employment Type *" variant="outlined" /></v-col><v-col cols="12" md="6"><v-text-field v-model="form.location" label="Location" variant="outlined" /></v-col><v-col cols="12" md="4"><v-text-field v-model.number="form.vacancies" type="number" min="1" label="Vacancies *" variant="outlined" /></v-col><v-col cols="12" md="4"><v-text-field v-model="form.deadline" type="date" label="Deadline" variant="outlined" /></v-col><v-col cols="12" md="4"><v-select v-model="form.status" :items="createStatus" label="Status *" variant="outlined" /></v-col></v-row><v-textarea v-model="form.description" label="Job Description" rows="4" variant="outlined" class="mb-2" /><v-row><v-col cols="12" md="3"><v-select v-model="form.salary_currency" :items="['GHS', 'USD', 'EUR', 'GBP']" label="Currency" variant="outlined" /></v-col><v-col cols="12" md="4"><v-text-field v-model.number="form.salary_from" type="number" min="0" label="Salary From" variant="outlined" /></v-col><v-col cols="12" md="4"><v-text-field v-model.number="form.salary_to" type="number" min="0" label="Salary To" variant="outlined" /></v-col></v-row></div><div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn><v-btn variant="outlined" @click="saveJob('Draft')">Save as Draft</v-btn><v-btn color="primary" @click="saveJob('Open')">Post Job</v-btn></div></v-navigation-drawer>

  <v-dialog v-model="detailDialog" max-width="720"><v-card><v-card-title class="text-h5">Job Details</v-card-title><v-card-text v-if="detailJob"><h4 class="text-h5 mb-2">{{ detailJob.title }}</h4><p class="text-caption text-lightText mb-3">Posted by {{ detailJob.posted_by?.full_name ?? 'System' }} on {{ detailJob.created_at.slice(0, 10) }}</p><v-row><v-col cols="12" md="7"><h6 class="text-h6 mb-1">Description</h6><p class="text-body-2 mb-3">{{ detailJob.description || 'No description provided.' }}</p><h6 class="text-h6 mb-1">Requirements</h6><p class="text-body-2 mb-3">{{ detailJob.requirements || 'No requirements provided.' }}</p><h6 class="text-h6 mb-1">Benefits</h6><p class="text-body-2">{{ detailJob.benefits || 'No benefits provided.' }}</p></v-col><v-col cols="12" md="5"><v-card variant="outlined"><v-card-text><div class="text-caption text-lightText">Type</div><div class="mb-2">{{ detailJob.employment_type }}</div><div class="text-caption text-lightText">Location</div><div class="mb-2">{{ detailJob.location || 'N/A' }}</div><div class="text-caption text-lightText">Vacancies</div><div class="mb-2">{{ detailJob.vacancies }}</div><div class="text-caption text-lightText">Salary</div><div>{{ salaryRange(detailJob) }}</div></v-card-text></v-card></v-col></v-row></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog = false">Close</v-btn><v-btn variant="outlined" color="primary" @click="editFromDetail">Edit Job</v-btn><v-btn variant="outlined" @click="router.visit(`/hr/applicants?job=${detailJob?.id}`)">View Applicants</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="confirmDelete.show" max-width="420"><v-card><v-card-title class="text-h5">Delete Job Opening</v-card-title><v-card-text>Are you sure you want to delete {{ confirmDelete.name }}? This action cannot be undone.</v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="confirmDelete.show = false">Cancel</v-btn><v-btn color="error" variant="flat" @click="confirmDeleteJob">Delete</v-btn></v-card-actions></v-card></v-dialog>
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.cursor-pointer { cursor: pointer; }
.drawer-body { height: calc(100% - 130px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
</style>
