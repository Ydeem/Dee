<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue'

interface JobItem {
  id: number
  title: string
  employment_type: string
  vacancies: number
  salary_range: string
  min_salary: number | null
  max_salary: number | null
  location: string | null
  description: string | null
  requirements: string | null
  responsibilities: string | null
  benefits: string | null
  status: string
  status_color: string
  is_expired: boolean
  days_until_deadline: number | null
  deadline: string | null
  deadline_raw: string | null
  created_at: string
  applicants_count: number
  department: { id: number; name: string } | null
  designation: { id: number; name: string } | null
}

interface DesignationOption {
  id: number
  name: string
  department_id: number | null
}

interface DepartmentOption {
  id: number
  name: string
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Recruitment', disabled: false, href: '#' },
  { title: 'Job Openings', disabled: true, href: '#' },
]

const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const jobs = ref<JobItem[]>([])
const deptOptions = ref<string[]>([])
const departmentOptions = ref<DepartmentOption[]>([])
const desigOptions = ref<DesignationOption[]>([])
const deleteDialog = ref(false)
const deletingJob = ref<JobItem | null>(null)
const jobDrawer = ref(false)
const editingJob = ref<JobItem | null>(null)
const jobTab = ref('basic')
const jobErrors = ref<Record<string, string[]>>({})

const stats = ref({
  open: 0,
  draft: 0,
  closed: 0,
  total_applicants: 0,
})

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0,
})

const filters = reactive({
  search: '',
  department: '',
  type: '',
  status: '',
})

const sort = reactive({
  sortBy: 'created_at',
  sortDir: 'desc',
})

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

const jobForm = reactive({
  title: '',
  department_id: null as number | null,
  designation_id: null as number | null,
  employment_type: 'Full-time',
  vacancies: 1,
  min_salary: null as number | null,
  max_salary: null as number | null,
  location: '',
  deadline: '',
  description: '',
  requirements: '',
  responsibilities: '',
  benefits: '',
  status: 'Draft',
})

const employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Intern']
const statusOptions = ['Draft', 'Open', 'Closed', 'On Hold']

const filteredDesignations = computed(() => {
  if (!jobForm.department_id) {
    return desigOptions.value
  }

  return desigOptions.value.filter((designation) => designation.department_id === jobForm.department_id)
})

async function fetchJobs() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/hr/job-openings', {
      params: {
        search: filters.search || undefined,
        department: filters.department || undefined,
        type: filters.type || undefined,
        status: filters.status || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
        sort_by: sort.sortBy,
        sort_dir: sort.sortDir,
      },
    })

    jobs.value = data.jobs?.data ?? []
    pagination.total = data.jobs?.total ?? 0
    stats.value = data.stats ?? stats.value
    deptOptions.value = data.filters?.departments ?? []
    departmentOptions.value = data.filters?.department_options ?? []
    desigOptions.value = data.filters?.designations ?? []
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: 'Failed to load job openings.',
      color: 'error',
    }
  } finally {
    loading.value = false
  }
}

function resetForm() {
  editingJob.value = null
  jobTab.value = 'basic'
  jobErrors.value = {}
  Object.assign(jobForm, {
    title: '',
    department_id: null,
    designation_id: null,
    employment_type: 'Full-time',
    vacancies: 1,
    min_salary: null,
    max_salary: null,
    location: '',
    deadline: '',
    description: '',
    requirements: '',
    responsibilities: '',
    benefits: '',
    status: 'Draft',
  })
}

function openCreateDrawer() {
  resetForm()
  jobDrawer.value = true
}

function openEditDrawer(job: JobItem) {
  editingJob.value = job
  jobTab.value = 'basic'
  jobErrors.value = {}

  Object.assign(jobForm, {
    title: job.title,
    department_id: job.department?.id ?? null,
    designation_id: job.designation?.id ?? null,
    employment_type: job.employment_type,
    vacancies: job.vacancies,
    min_salary: job.min_salary,
    max_salary: job.max_salary,
    location: job.location ?? '',
    deadline: job.deadline_raw ?? '',
    description: job.description ?? '',
    requirements: job.requirements ?? '',
    responsibilities: job.responsibilities ?? '',
    benefits: job.benefits ?? '',
    status: job.status,
  })

  jobDrawer.value = true
}

async function changeStatus(job: JobItem, newStatus: string) {
  try {
    const { data } = await axios.patch(`/api/hr/job-openings/${job.id}/status`, { status: newStatus })
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    await fetchJobs()
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to update status.',
      color: 'error',
    }
  }
}

async function duplicateJob(job: JobItem) {
  try {
    const { data } = await axios.post(`/api/hr/job-openings/${job.id}/duplicate`)
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    await fetchJobs()
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to duplicate.',
      color: 'error',
    }
  }
}

function askDelete(job: JobItem) {
  deletingJob.value = job
  deleteDialog.value = true
}

async function confirmDelete() {
  if (!deletingJob.value) return

  deleting.value = true
  try {
    await axios.delete(`/api/hr/job-openings/${deletingJob.value.id}`)
    snackbar.value = {
      show: true,
      message: 'Job posting deleted.',
      color: 'success',
    }
    deleteDialog.value = false
    await fetchJobs()
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to delete.',
      color: 'error',
    }
    deleteDialog.value = false
  } finally {
    deleting.value = false
  }
}

async function saveJob() {
  saving.value = true
  try {
    const payload = {
      ...jobForm,
      location: jobForm.location || undefined,
      deadline: jobForm.deadline || undefined,
      description: jobForm.description || undefined,
      requirements: jobForm.requirements || undefined,
      responsibilities: jobForm.responsibilities || undefined,
      benefits: jobForm.benefits || undefined,
    }

    if (editingJob.value) {
      const { data } = await axios.put(`/api/hr/job-openings/${editingJob.value.id}`, payload)
      snackbar.value = { show: true, message: data.message, color: 'success' }
    } else {
      const { data } = await axios.post('/api/hr/job-openings', payload)
      snackbar.value = { show: true, message: data.message, color: 'success' }
    }

    jobDrawer.value = false
    await fetchJobs()
  } catch (error: any) {
    if (error?.response?.status === 422) {
      jobErrors.value = error.response.data.errors ?? {}
    }
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save job.',
      color: 'error',
    }
  } finally {
    saving.value = false
  }
}

function exportJobs() {
  const headers = ['Title', 'Department', 'Designation', 'Type', 'Vacancies', 'Salary Range', 'Deadline', 'Applicants', 'Status']
  const rows = jobs.value.map((job) => [
    job.title,
    job.department?.name ?? '-',
    job.designation?.name ?? '-',
    job.employment_type,
    job.vacancies,
    job.salary_range,
    job.deadline ?? '-',
    job.applicants_count,
    job.status,
  ])

  const csv = [headers, ...rows]
    .map((row) => row.map((cell) => `"${String(cell ?? '').replace(/"/g, '""')}"`).join(','))
    .join('\n')

  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'job-openings.csv'
  a.click()
  URL.revokeObjectURL(url)
}

watch(
  () => [filters.search, filters.department, filters.type, filters.status],
  () => {
    pagination.page = 1
    fetchJobs()
  },
)

watch(
  () => [pagination.page, pagination.perPage, sort.sortBy, sort.sortDir],
  () => {
    fetchJobs()
  },
)

watch(
  () => jobForm.department_id,
  () => {
    if (!filteredDesignations.value.some((item) => item.id === jobForm.designation_id)) {
      jobForm.designation_id = null
    }
  },
)

onMounted(async () => {
  await fetchJobs()
})
</script>

<template>
  <BaseBreadcrumb title="Job Openings" subtitle="Manage recruitment and open positions" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Job Openings</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Manage recruitment and open positions</p>
    </div>
    <div class="d-flex ga-2">
      <v-btn variant="outlined" prepend-icon="mdi-download" @click="exportJobs">Export</v-btn>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDrawer">Post Job</v-btn>
    </div>
  </div>

  <v-row class="mb-1">
    <v-col cols="12" sm="6" md="3"><v-card variant="outlined"><v-card-text>Open: <strong>{{ stats.open }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card variant="outlined"><v-card-text>Draft: <strong>{{ stats.draft }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card variant="outlined"><v-card-text>Closed: <strong>{{ stats.closed }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="3"><v-card variant="outlined"><v-card-text>Total Applicants: <strong>{{ stats.total_applicants }}</strong></v-card-text></v-card></v-col>
  </v-row>

  <v-card variant="outlined" class="mb-4">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by title..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="3"><v-select v-model="filters.department" :items="['', ...deptOptions]" label="Department" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.type" :items="['', ...employmentTypes]" label="Type" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="['', ...statusOptions]" label="Status" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="1"><v-select v-model="pagination.perPage" :items="[10,25,50]" label="Rows" variant="outlined" hide-details /></v-col>
      </v-row>
    </v-card-text>
  </v-card>

  <v-card variant="outlined">
    <v-card-text>
      <v-skeleton-loader v-if="loading" type="table-tbody" />
      <v-table v-else>
        <thead>
          <tr>
            <th>Title</th>
            <th>Department</th>
            <th>Designation</th>
            <th>Type</th>
            <th>Vacancies</th>
            <th>Salary Range</th>
            <th>Deadline</th>
            <th>Applicants</th>
            <th>Status</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="job in jobs" :key="job.id">
            <td>
              <div class="font-weight-medium">{{ job.title }}</div>
              <div class="text-caption text-medium-emphasis">{{ job.location || '-' }}</div>
            </td>
            <td>
              <span v-if="job.department">{{ job.department.name }}</span>
              <span v-else class="text-medium-emphasis">-</span>
            </td>
            <td>
              <span v-if="job.designation">{{ job.designation.name }}</span>
              <span v-else class="text-medium-emphasis">-</span>
            </td>
            <td>{{ job.employment_type }}</td>
            <td>{{ job.vacancies }}</td>
            <td>{{ job.salary_range }}</td>
            <td>
              <span v-if="job.deadline" :class="job.is_expired ? 'text-error font-weight-medium' : ''">
                {{ job.deadline }}
                <v-icon v-if="job.is_expired" size="14" color="error">mdi-alert-circle</v-icon>
              </span>
              <span v-else class="text-medium-emphasis">-</span>
            </td>
            <td>{{ job.applicants_count }}</td>
            <td><v-chip size="small" :color="job.status_color" variant="tonal">{{ job.status }}</v-chip></td>
            <td class="text-right">
              <v-menu>
                <template #activator="{ props }">
                  <v-btn v-bind="props" icon variant="text" size="small">
                    <v-icon>mdi-dots-vertical</v-icon>
                  </v-btn>
                </template>
                <v-list density="compact">
                  <v-list-item prepend-icon="mdi-account-group" title="View Applicants" @click="router.visit('/hr/applicants?job_id=' + job.id)" />
                  <v-list-item prepend-icon="mdi-pencil" title="Edit Job" @click="openEditDrawer(job)" />
                  <v-list-item v-if="job.status !== 'Open'" prepend-icon="mdi-check-circle" title="Mark as Open" @click="changeStatus(job, 'Open')" />
                  <v-list-item v-if="job.status !== 'On Hold'" prepend-icon="mdi-pause-circle" title="Put On Hold" @click="changeStatus(job, 'On Hold')" />
                  <v-list-item v-if="job.status !== 'Closed'" prepend-icon="mdi-close-circle" title="Close Job" @click="changeStatus(job, 'Closed')" />
                  <v-list-item prepend-icon="mdi-content-copy" title="Duplicate" @click="duplicateJob(job)" />
                  <v-divider />
                  <v-list-item prepend-icon="mdi-delete" title="Delete" base-color="error" @click="askDelete(job)" />
                </v-list>
              </v-menu>
            </td>
          </tr>
          <tr v-if="jobs.length === 0">
            <td colspan="10" class="text-center py-8 text-medium-emphasis">No job openings found.</td>
          </tr>
        </tbody>
      </v-table>

      <div class="d-flex justify-end mt-4">
        <v-pagination v-model="pagination.page" :length="Math.max(1, Math.ceil(pagination.total / pagination.perPage))" />
      </div>
    </v-card-text>
  </v-card>

  <v-navigation-drawer v-model="jobDrawer" location="right" temporary width="720">
    <div class="pa-4 border-b d-flex justify-space-between align-center">
      <h5 class="text-h5 mb-0">{{ editingJob ? 'Edit Job Opening' : 'Post Job' }}</h5>
      <v-btn icon="mdi-close" variant="text" @click="jobDrawer = false" />
    </div>

    <div class="pa-4 drawer-body">
      <v-tabs v-model="jobTab" color="primary" class="mb-4">
        <v-tab value="basic">Basic Info</v-tab>
        <v-tab value="details">Job Details</v-tab>
        <v-tab value="compensation">Compensation</v-tab>
      </v-tabs>

      <v-window v-model="jobTab">
        <v-window-item value="basic">
          <v-row>
            <v-col cols="12" md="6"><v-text-field v-model="jobForm.title" label="Title *" variant="outlined" :error-messages="jobErrors.title?.[0]" /></v-col>
            <v-col cols="12" md="6"><v-select v-model="jobForm.employment_type" :items="employmentTypes" label="Employment Type *" variant="outlined" :error-messages="jobErrors.employment_type?.[0]" /></v-col>
            <v-col cols="12" md="4"><v-text-field v-model.number="jobForm.vacancies" type="number" min="1" label="Vacancies *" variant="outlined" :error-messages="jobErrors.vacancies?.[0]" /></v-col>
            <v-col cols="12" md="4"><v-select v-model="jobForm.status" :items="statusOptions" label="Status *" variant="outlined" :error-messages="jobErrors.status?.[0]" /></v-col>
            <v-col cols="12" md="6"><v-select v-model="jobForm.department_id" :items="departmentOptions.map((item) => ({ title: item.name, value: item.id }))" label="Department" variant="outlined" /></v-col>
            <v-col cols="12" md="6"><v-select v-model="jobForm.designation_id" :items="filteredDesignations.map((item) => ({ title: item.name, value: item.id }))" label="Designation" variant="outlined" /></v-col>
            <v-col cols="12" md="6"><v-text-field v-model="jobForm.location" label="Location" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="details">
          <v-textarea v-model="jobForm.description" label="Description" variant="outlined" rows="4" class="mb-3" />
          <v-textarea v-model="jobForm.requirements" label="Requirements" variant="outlined" rows="4" class="mb-3" />
          <v-textarea v-model="jobForm.responsibilities" label="Responsibilities" variant="outlined" rows="4" class="mb-3" />
          <v-textarea v-model="jobForm.benefits" label="Benefits" variant="outlined" rows="4" />
        </v-window-item>

        <v-window-item value="compensation">
          <v-row>
            <v-col cols="12" md="4"><v-text-field v-model.number="jobForm.min_salary" type="number" min="0" label="Min Salary" variant="outlined" /></v-col>
            <v-col cols="12" md="4"><v-text-field v-model.number="jobForm.max_salary" type="number" min="0" label="Max Salary" variant="outlined" /></v-col>
            <v-col cols="12" md="4"><v-text-field v-model="jobForm.deadline" type="date" label="Deadline" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>
      </v-window>
    </div>

    <div class="pa-4 border-t d-flex justify-end ga-2 sticky-footer">
      <v-btn variant="outlined" @click="jobDrawer = false">Cancel</v-btn>
      <v-btn color="primary" :loading="saving" @click="saveJob">Save Job</v-btn>
    </div>
  </v-navigation-drawer>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title class="text-h5">Delete Job</v-card-title>
      <v-card-text>Delete {{ deletingJob?.title }}?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
        <v-btn color="error" variant="flat" :loading="deleting" @click="confirmDelete">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.drawer-body { height: calc(100% - 132px); overflow-y: auto; }
.sticky-footer { position: sticky; bottom: 0; background: #fff; }
.border-b { border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
.border-t { border-top: 1px solid rgba(0, 0, 0, 0.08); }
</style>
