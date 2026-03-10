<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

interface Applicant {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  source: string | null;
  status: string;
  stage: number;
  rating: number | null;
  resume_path: string | null;
  notes: string | null;
  experience_years: number | null;
  created_at: string;
  job_opening?: { id: number; title: string; department?: { name: string } | null } | null;
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Recruitment', disabled: false, href: '#' },
  { title: 'Applicants', disabled: true, href: '#' }
];

const loading = ref(false);
const tab = ref('all');
const viewMode = ref<'table' | 'grid'>('table');
const applicants = ref<Applicant[]>([]);
const jobOpenings = ref<{ id: number; title: string }[]>([]);
const summary = ref({ total: 0, new: 0, shortlisted: 0, interviewed: 0, hired: 0 });
const pagination = reactive({ page: 1, perPage: 10, total: 0 });
const filters = reactive({ search: '', job_opening_id: '', status: '', source: '', rating: '' });
const sort = reactive({ sortBy: 'created_at', sortDir: 'desc' });
const snackbar = ref({ show: false, message: '', color: 'success' });

const drawerOpen = ref(false);
const editingId = ref<number | null>(null);
const drawerTab = ref('personal');
const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  location: '',
  job_opening_id: null as number | null,
  source: '',
  experience_years: null as number | null,
  education_level: 'Any',
  status: 'New',
  stage: 1,
  rating: null as number | null,
  cover_letter: '',
  notes: '',
  resume: null as File | null
});

const detailDialog = ref(false);
const detailApplicant = ref<Applicant | null>(null);
const noteDialog = ref({ show: false, id: null as number | null, notes: '' });
const rejectDialog = ref({ show: false, id: null as number | null, reason: '' });
const scheduleDialog = ref({ show: false, id: null as number | null, interview_date: '' });
const convertDialog = ref({ show: false, id: null as number | null, start_date: new Date().toISOString().slice(0, 10) });
const confirmDelete = ref({ show: false, id: null as number | null, name: '' });

const statuses = ['New', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Offer Sent', 'Hired', 'Rejected', 'Withdrawn'];
const sources = ['LinkedIn', 'Website', 'Referral', 'Job Board', 'Walk-in', 'Other'];
const headers = [
  { title: 'Applicant', key: 'applicant' },
  { title: 'Applied For', key: 'job' },
  { title: 'Source', key: 'source' },
  { title: 'Experience', key: 'experience_years' },
  { title: 'Rating', key: 'rating' },
  { title: 'Stage', key: 'stage' },
  { title: 'Status', key: 'status' },
  { title: 'Applied Date', key: 'created_at' },
  { title: 'Actions', key: 'actions' }
];
const stages = [
  { id: 1, title: 'Applied' },
  { id: 2, title: 'Screening' },
  { id: 3, title: 'Interview' },
  { id: 4, title: 'Offer' },
  { id: 5, title: 'Hired' }
];
const pipeline = computed(() => stages.map((stage) => ({ ...stage, items: applicants.value.filter((a) => a.stage === stage.id) })));

const dummyApplicants: Applicant[] = [
  { id: 1, first_name: 'Kofi', last_name: 'Boateng', full_name: 'Kofi Boateng', email: 'kofi.boateng@email.com', source: 'LinkedIn', status: 'Shortlisted', stage: 2, rating: 4, resume_path: null, notes: 'Good profile', experience_years: 4, created_at: new Date().toISOString(), job_opening: { id: 1, title: 'Senior Software Engineer', department: { name: 'Engineering' } } }
];

function fullName(a: Applicant) { return a.full_name || `${a.first_name} ${a.last_name}`; }
function initials(a: Applicant) { return `${a.first_name?.[0] || ''}${a.last_name?.[0] || ''}`.toUpperCase(); }
function statusColor(s: string) { if (s === 'Hired') return 'success'; if (s === 'Rejected') return 'error'; if (s === 'Shortlisted') return 'purple'; if (s === 'Interview Scheduled') return 'teal'; if (s === 'Interviewed') return 'cyan'; if (s === 'Offer Sent') return 'orange'; if (s === 'Reviewing') return 'blue'; return 'secondary'; }
function stageLabel(stage: number) { return stages.find((s) => s.id === stage)?.title ?? 'Applied'; }
function resetFilters() { filters.search = ''; filters.job_opening_id = ''; filters.status = ''; filters.source = ''; filters.rating = ''; }
function openCreate() { editingId.value = null; Object.assign(form, { first_name: '', last_name: '', email: '', phone: '', location: '', job_opening_id: null, source: '', experience_years: null, education_level: 'Any', status: 'New', stage: 1, rating: null, cover_letter: '', notes: '', resume: null }); drawerOpen.value = true; }
function openEdit(a: Applicant) { editingId.value = a.id; Object.assign(form, { first_name: a.first_name, last_name: a.last_name, email: a.email, job_opening_id: a.job_opening?.id || null, source: a.source || '', experience_years: a.experience_years, status: a.status, stage: a.stage, rating: a.rating, notes: a.notes || '' }); drawerOpen.value = true; }
function openDetail(a: Applicant) { detailApplicant.value = a; detailDialog.value = true; }

async function fetchApplicants() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/applicants', { params: { search: filters.search || undefined, job_opening_id: filters.job_opening_id || undefined, status: filters.status || undefined, source: filters.source || undefined, rating: filters.rating || undefined, page: pagination.page, per_page: pagination.perPage, sort_by: sort.sortBy, sort_dir: sort.sortDir } });
    applicants.value = data?.applicants?.data ?? [];
    pagination.total = data?.applicants?.total ?? 0;
    summary.value = data?.summary ?? summary.value;
    jobOpenings.value = data?.job_openings ?? [];
    if (!applicants.value.length) { applicants.value = dummyApplicants; pagination.total = dummyApplicants.length; }
  } catch {
    applicants.value = dummyApplicants;
    pagination.total = dummyApplicants.length;
    summary.value = { total: 1, new: 0, shortlisted: 1, interviewed: 0, hired: 0 };
    snackbar.value = { show: true, message: 'Using dummy applicants data.', color: 'warning' };
  } finally {
    loading.value = false;
  }
}

async function saveApplicant() {
  const payload = new FormData();
  Object.entries(form).forEach(([k, v]) => {
    if (v !== null && v !== '' && k !== 'resume') payload.append(k, String(v));
  });
  if (form.resume) payload.append('resume', form.resume);

  try {
    if (editingId.value) {
      payload.append('_method', 'PUT');
      await axios.post(`/api/hr/applicants/${editingId.value}`, payload, { headers: { 'Content-Type': 'multipart/form-data' } });
    } else {
      await axios.post('/api/hr/applicants', payload, { headers: { 'Content-Type': 'multipart/form-data' } });
    }
    drawerOpen.value = false;
    snackbar.value = { show: true, message: 'Applicant saved.', color: 'success' };
    fetchApplicants();
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Save failed.', color: 'error' };
  }
}

async function updateStatus(a: Applicant, status: string, stage?: number) { await axios.patch(`/api/hr/applicants/${a.id}/status`, { status, stage }); fetchApplicants(); }
async function moveForward(a: Applicant) { const next = Math.min(5, a.stage + 1); const map: Record<number, string> = { 1: 'Reviewing', 2: 'Shortlisted', 3: 'Interviewed', 4: 'Offer Sent', 5: 'Hired' }; await updateStatus(a, map[next], next); }
async function saveNote() { if (!noteDialog.value.id) return; await axios.patch(`/api/hr/applicants/${noteDialog.value.id}/note`, { notes: noteDialog.value.notes }); noteDialog.value.show = false; fetchApplicants(); }
async function scheduleInterview() { if (!scheduleDialog.value.id) return; await axios.patch(`/api/hr/applicants/${scheduleDialog.value.id}/status`, { status: 'Interview Scheduled', stage: 3, interview_date: scheduleDialog.value.interview_date }); scheduleDialog.value.show = false; fetchApplicants(); }
async function rejectApplicant() { if (!rejectDialog.value.id) return; await axios.patch(`/api/hr/applicants/${rejectDialog.value.id}/status`, { status: 'Rejected', stage: 5, rejection_reason: rejectDialog.value.reason }); rejectDialog.value.show = false; fetchApplicants(); }
async function convertToEmployee() { if (!convertDialog.value.id) return; const { data } = await axios.post(`/api/hr/applicants/${convertDialog.value.id}/convert`, { start_date: convertDialog.value.start_date }); convertDialog.value.show = false; if (data?.employee_id) router.visit(`/hr/employees/${data.employee_id}`); }
async function removeApplicant() { if (!confirmDelete.value.id) return; await axios.delete(`/api/hr/applicants/${confirmDelete.value.id}`); confirmDelete.value.show = false; fetchApplicants(); }

function exportCsv() {
  const rows = [['Name', 'Email', 'Job', 'Status', 'Stage', 'Rating'], ...applicants.value.map((a) => [fullName(a), a.email, a.job_opening?.title ?? '-', a.status, stageLabel(a.stage), a.rating ?? '-'])];
  const csv = rows.map((r) => r.map((c) => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'applicants.csv';
  link.click();
  URL.revokeObjectURL(url);
}

function handleTableOptions(options: any) {
  pagination.page = options.page;
  pagination.perPage = options.itemsPerPage;
  if (options.sortBy?.length) { sort.sortBy = options.sortBy[0].key; sort.sortDir = options.sortBy[0].order ?? 'asc'; } else { sort.sortBy = 'created_at'; sort.sortDir = 'desc'; }
  fetchApplicants();
}

watch(() => [filters.search, filters.job_opening_id, filters.status, filters.source, filters.rating], () => { pagination.page = 1; fetchApplicants(); });
onMounted(fetchApplicants);
</script>

<template>
  <BaseBreadcrumb title="Applicants" subtitle="Track and manage job applicants" :breadcrumbs="breadcrumbs" />
  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4"><div><h2 class="text-h3 mb-1">Applicants</h2><p class="text-subtitle-1 text-lightText mb-0">Track and manage job applicants</p></div><div class="d-flex ga-2"><v-btn variant="outlined" prepend-icon="mdi-download" @click="exportCsv">Export CSV</v-btn><v-btn color="primary" prepend-icon="mdi-plus" @click="openCreate">Add Applicant</v-btn></div></div>
  <v-row class="mb-1"><v-col cols="12" sm="6" md="2"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Total: <strong>{{ summary.total }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="2"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>New: <strong>{{ summary.new }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="2"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Shortlisted: <strong>{{ summary.shortlisted }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="2"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Interviewed: <strong>{{ summary.interviewed }}</strong></v-card-text></v-card></v-col><v-col cols="12" sm="6" md="2"><v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-card-text>Hired: <strong>{{ summary.hired }}</strong></v-card-text></v-card></v-col></v-row>
  <v-card class="bg-surface rounded-lg hr-card-shadow" variant="outlined"><v-tabs v-model="tab" color="primary" class="px-4 pt-2"><v-tab value="all">All Applicants</v-tab><v-tab value="pipeline">Pipeline View</v-tab></v-tabs><v-divider />
    <v-window v-model="tab">
      <v-window-item value="all"><div class="pa-4">
        <v-card class="bg-surface rounded-lg hr-card-shadow mb-4" variant="outlined"><v-card-text><v-row><v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by name or email..." variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="filters.job_opening_id" :items="[{ title: 'All Jobs', value: '' }, ...jobOpenings.map((j) => ({ title: j.title, value: String(j.id) }))]" label="Job Opening" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="[{ title:'All', value:'' }, ...statuses.map((s) => ({ title: s, value: s }))]" label="Status" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="2"><v-select v-model="filters.source" :items="[{ title:'All', value:'' }, ...sources.map((s) => ({ title: s, value: s }))]" label="Source" variant="outlined" hide-details /></v-col><v-col cols="12" sm="6" md="1"><v-select v-model="filters.rating" :items="[{ title:'All', value:'' }, { title:'★', value:'1' }, { title:'★★', value:'2' }, { title:'★★★', value:'3' }, { title:'★★★★', value:'4' }, { title:'★★★★★', value:'5' }]" label="Rating" variant="outlined" hide-details /></v-col><v-col cols="12" md="1" class="d-flex align-center justify-end ga-1"><v-btn :color="viewMode === 'table' ? 'primary' : 'default'" icon="mdi-view-list" variant="text" @click="viewMode = 'table'" /><v-btn :color="viewMode === 'grid' ? 'primary' : 'default'" icon="mdi-view-grid" variant="text" @click="viewMode = 'grid'" /></v-col></v-row><div class="d-flex justify-end mt-2"><v-btn variant="text" color="primary" @click="resetFilters">Reset Filters</v-btn></div></v-card-text></v-card>
        <v-skeleton-loader v-if="loading && !applicants.length" type="table" />
        <v-data-table-server v-else-if="viewMode === 'table'" :headers="headers" :items="applicants" :items-length="pagination.total" :items-per-page="pagination.perPage" :page="pagination.page" :items-per-page-options="[10,25,50]" @update:options="handleTableOptions"><template #item.applicant="{ item }"><div class="d-flex align-center ga-3 cursor-pointer" @click="openDetail(item)"><v-avatar color="primary" variant="tonal" size="34"><span class="text-caption font-weight-bold">{{ initials(item) }}</span></v-avatar><div><div class="font-weight-medium">{{ fullName(item) }}</div><div class="text-caption text-lightText">{{ item.email }}</div></div></div></template><template #item.job="{ item }"><div>{{ item.job_opening?.title ?? '-' }}</div><v-chip size="x-small" variant="tonal">{{ item.job_opening?.department?.name ?? 'No Dept' }}</v-chip></template><template #item.source="{ item }"><v-chip size="small" variant="tonal">{{ item.source ?? '-' }}</v-chip></template><template #item.experience_years="{ item }">{{ item.experience_years ? `${item.experience_years} yrs` : '—' }}</template><template #item.rating="{ item }"><v-rating :model-value="item.rating || 0" density="compact" size="x-small" readonly /></template><template #item.stage="{ item }"><v-chip size="small" variant="outlined">{{ item.stage }} - {{ stageLabel(item.stage) }}</v-chip></template><template #item.status="{ item }"><v-chip :color="statusColor(item.status)" size="small" variant="tonal">{{ item.status }}</v-chip></template><template #item.created_at="{ item }">{{ item.created_at.slice(0, 10) }}</template><template #item.actions="{ item }"><v-menu><template #activator="{ props }"><v-btn icon="mdi-dots-vertical" variant="text" v-bind="props" /></template><v-list><v-list-item title="View Profile" @click="openDetail(item)" /><v-list-item title="Edit Applicant" @click="openEdit(item)" /><v-list-item title="Move to Next Stage" @click="moveForward(item)" /><v-list-item title="Schedule Interview" @click="scheduleDialog = { ...scheduleDialog, show: true, id: item.id }" /><v-list-item title="Add Note" @click="noteDialog = { show: true, id: item.id, notes: item.notes ?? '' }" /><v-list-item v-if="item.status === 'Hired'" title="Convert to Employee" @click="convertDialog = { ...convertDialog, show: true, id: item.id }" /><v-list-item title="Reject" base-color="error" @click="rejectDialog = { show: true, id: item.id, reason: '' }" /><v-list-item title="Delete" base-color="error" @click="confirmDelete = { show: true, id: item.id, name: fullName(item) }" /></v-list></v-menu></template></v-data-table-server>
        <v-row v-else><v-col v-for="item in applicants" :key="item.id" cols="12" sm="6" md="4"><v-card class="rounded-lg hr-card-shadow" variant="outlined"><v-card-text><div class="d-flex justify-space-between align-center mb-2"><div class="font-weight-medium">{{ fullName(item) }}</div><v-chip :color="statusColor(item.status)" size="x-small" variant="tonal">{{ item.status }}</v-chip></div><div class="text-caption text-lightText mb-1">{{ item.email }}</div><div class="text-caption mb-2">{{ item.job_opening?.title ?? '-' }}</div><v-rating :model-value="item.rating || 0" density="compact" size="x-small" readonly /><div class="d-flex justify-space-between mt-2"><v-btn size="small" variant="outlined" @click="openDetail(item)">View</v-btn><v-btn size="small" icon="mdi-arrow-right" variant="text" @click="moveForward(item)" /></div></v-card-text></v-card></v-col></v-row>
      </div></v-window-item>
      <v-window-item value="pipeline"><div class="pa-4"><v-row><v-col v-for="column in pipeline" :key="column.id" cols="12" sm="6" md="4" lg="2"><v-card variant="outlined"><v-card-title>{{ column.title }} <v-chip size="x-small">{{ column.items.length }}</v-chip></v-card-title><v-divider /><v-card-text class="pipeline-column"><v-card v-for="a in column.items" :key="a.id" class="mb-2" variant="outlined"><v-card-text class="pa-2"><div class="font-weight-medium">{{ fullName(a) }}</div><div class="text-caption text-lightText">{{ a.job_opening?.title ?? '-' }}</div><v-rating :model-value="a.rating || 0" density="compact" size="x-small" readonly /><div class="d-flex justify-space-between mt-1"><v-btn size="x-small" variant="text" @click="openDetail(a)">View</v-btn><v-btn size="x-small" icon="mdi-arrow-right" variant="text" @click="moveForward(a)" /></div></v-card-text></v-card></v-card-text></v-card></v-col></v-row></div></v-window-item>
    </v-window>
  </v-card>

  <v-navigation-drawer v-model="drawerOpen" location="right" temporary width="600"><div class="pa-4 border-b d-flex justify-space-between align-center"><h5 class="text-h5 mb-0">{{ editingId ? 'Edit Applicant' : 'Add Applicant' }}</h5><v-btn icon="mdi-close" variant="text" @click="drawerOpen = false" /></div><div class="pa-4 drawer-body"><v-tabs v-model="drawerTab" color="primary" class="mb-4"><v-tab value="personal">Personal Info</v-tab><v-tab value="application">Application</v-tab><v-tab value="documents">Documents</v-tab></v-tabs><v-window v-model="drawerTab"><v-window-item value="personal"><v-row><v-col cols="12" md="6"><v-text-field v-model="form.first_name" label="First Name *" variant="outlined" /></v-col><v-col cols="12" md="6"><v-text-field v-model="form.last_name" label="Last Name *" variant="outlined" /></v-col><v-col cols="12"><v-text-field v-model="form.email" label="Email *" variant="outlined" /></v-col></v-row></v-window-item><v-window-item value="application"><v-select v-model="form.job_opening_id" :items="jobOpenings.map((j) => ({ title: j.title, value: j.id }))" label="Job Opening *" variant="outlined" class="mb-2" /><v-row><v-col cols="12" md="6"><v-select v-model="form.source" :items="sources" label="Source" variant="outlined" /></v-col><v-col cols="12" md="6"><v-text-field v-model.number="form.experience_years" type="number" min="0" label="Experience Years" variant="outlined" /></v-col><v-col cols="12" md="6"><v-select v-model="form.status" :items="statuses" label="Status *" variant="outlined" /></v-col><v-col cols="12" md="6"><div class="pt-2">Rating</div><v-rating v-model="form.rating" hover /></v-col></v-row><v-textarea v-model="form.cover_letter" rows="3" label="Cover Letter" variant="outlined" /></v-window-item><v-window-item value="documents"><v-file-input label="Resume Upload" accept=".pdf,.doc,.docx" variant="outlined" @update:model-value="(v:any) => form.resume = v" /><v-textarea v-model="form.notes" rows="3" label="Internal Notes" variant="outlined" /></v-window-item></v-window></div><div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer"><v-btn variant="outlined" @click="drawerOpen = false">Cancel</v-btn><v-btn color="primary" @click="saveApplicant">Save Applicant</v-btn></div></v-navigation-drawer>
  <v-dialog v-model="detailDialog" max-width="780"><v-card><v-card-title class="text-h5">Applicant Profile</v-card-title><v-card-text v-if="detailApplicant"><div class="d-flex align-center ga-3 mb-3"><v-avatar size="46" color="primary" variant="tonal"><span class="font-weight-bold">{{ initials(detailApplicant) }}</span></v-avatar><div><div class="text-h6">{{ fullName(detailApplicant) }}</div><div class="text-caption text-lightText">{{ detailApplicant.email }}</div></div><v-chip class="ms-auto" :color="statusColor(detailApplicant.status)" variant="tonal">{{ detailApplicant.status }}</v-chip></div><v-progress-linear :model-value="detailApplicant.stage * 20" color="primary" rounded class="mb-3" /><v-row><v-col cols="12" md="8"><div class="text-caption text-lightText">Applied For</div><div class="mb-2">{{ detailApplicant.job_opening?.title ?? '-' }}</div><div class="text-caption text-lightText">Notes</div><v-textarea v-model="detailApplicant.notes" rows="3" variant="outlined" /></v-col><v-col cols="12" md="4"><div class="d-grid ga-2"><v-btn variant="outlined" color="primary" @click="moveForward(detailApplicant)">Move to Next Stage</v-btn><v-btn variant="outlined" @click="scheduleDialog = { ...scheduleDialog, show: true, id: detailApplicant.id }">Schedule Interview</v-btn><v-btn v-if="detailApplicant.status === 'Hired'" variant="outlined" color="success" @click="convertDialog = { ...convertDialog, show: true, id: detailApplicant.id }">Convert to Employee</v-btn><v-btn variant="outlined" color="error" @click="rejectDialog = { show: true, id: detailApplicant.id, reason: '' }">Reject</v-btn></div></v-col></v-row></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="detailDialog = false">Close</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="noteDialog.show" max-width="460"><v-card><v-card-title class="text-h5">Add Note</v-card-title><v-card-text><v-textarea v-model="noteDialog.notes" rows="4" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="noteDialog.show = false">Cancel</v-btn><v-btn color="primary" @click="saveNote">Save</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="scheduleDialog.show" max-width="460"><v-card><v-card-title class="text-h5">Schedule Interview</v-card-title><v-card-text><v-text-field v-model="scheduleDialog.interview_date" type="datetime-local" label="Interview Date *" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="scheduleDialog.show = false">Cancel</v-btn><v-btn color="primary" @click="scheduleInterview">Schedule</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="rejectDialog.show" max-width="460"><v-card><v-card-title class="text-h5">Reject Applicant</v-card-title><v-card-text><v-textarea v-model="rejectDialog.reason" rows="4" label="Rejection Reason *" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="rejectDialog.show = false">Cancel</v-btn><v-btn color="error" @click="rejectApplicant">Reject</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="convertDialog.show" max-width="460"><v-card><v-card-title class="text-h5">Convert to Employee</v-card-title><v-card-text><v-text-field v-model="convertDialog.start_date" type="date" label="Start Date" variant="outlined" /></v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="convertDialog.show = false">Cancel</v-btn><v-btn color="success" @click="convertToEmployee">Convert</v-btn></v-card-actions></v-card></v-dialog>
  <v-dialog v-model="confirmDelete.show" max-width="420"><v-card><v-card-title class="text-h5">Delete Applicant</v-card-title><v-card-text>Delete {{ confirmDelete.name }}? This cannot be undone.</v-card-text><v-card-actions><v-spacer /><v-btn variant="text" @click="confirmDelete.show = false">Cancel</v-btn><v-btn color="error" @click="removeApplicant">Delete</v-btn></v-card-actions></v-card></v-dialog>
  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow { box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06); }
.cursor-pointer { cursor: pointer; }
.drawer-body { height: calc(100% - 130px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
.pipeline-column { max-height: 65vh; overflow-y: auto; }
</style>

