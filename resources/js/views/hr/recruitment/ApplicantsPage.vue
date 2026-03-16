<script setup lang="ts">
import { onMounted, reactive, ref, watch } from 'vue'
import axios from 'axios'
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue'
import SendMessageDialog from '@/components/HR/SendMessageDialog.vue'

interface ApplicantRow {
  id: number
  full_name: string
  first_name: string
  last_name: string
  email: string
  phone: string | null
  initials: string
  source: string | null
  experience_years: string
  current_company: string | null
  current_position: string | null
  expected_salary: number | null
  stage: number
  stage_label: string
  status: string
  status_color: string
  rating: number | null
  notes: string | null
  resume_url: string | null
  is_converted: boolean
  applied_date: string
  job_opening: null | {
    id: number
    title: string
    department: string
  }
}

interface ApplicantDetail extends ApplicantRow {
  cover_letter?: string | null
  rejected_reason?: string | null
  interviewed_at?: string | null
  hired_at?: string | null
  converted_employee?: null | {
    id: number
    first_name?: string
    last_name?: string
  }
}

interface PipelineApplicant {
  id: number
  full_name: string
  initials: string
  email: string
  source: string | null
  rating: number | null
  status: string
  status_color: string
  experience: string
  applied_date: string
  job_opening: string
  stage: number
  is_converted: boolean
}

interface PipelineColumn {
  stage: number
  label: string
  count: number
  color: string
  applicants: PipelineApplicant[]
}

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Recruitment', disabled: false, href: '#' },
  { title: 'Applicants', disabled: true, href: '#' },
]

const loading = ref(false)
const pipelineLoading = ref(false)
const viewLoading = ref(false)
const activeTab = ref('table')

const applicants = ref<ApplicantRow[]>([])
const pipeline = ref<PipelineColumn[]>([])
const jobOptions = ref<Array<{ id: number; title: string }>>([])

const stats = ref({
  total: 0,
  new: 0,
  shortlisted: 0,
  interviewed: 0,
  hired: 0,
})

const pagination = reactive({
  page: 1,
  perPage: 10,
  total: 0,
})

const filters = reactive({
  search: '',
  jobId: '',
  status: '',
  source: '',
  rating: '',
})

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
})

const viewDialog = ref(false)
const viewApplicant = ref<ApplicantDetail | null>(null)

const rejectDialog = ref(false)
const rejectingApplicant = ref<ApplicantRow | null>(null)
const rejectReason = ref('')

const convertDialog = ref(false)
const converting = ref(false)
const convertingApplicant = ref<{ id: number; full_name: string } | null>(null)

const deleteDialog = ref(false)
const deleting = ref(false)
const deletingApplicant = ref<ApplicantRow | null>(null)
const messageDialog = ref(false)
const messageTarget = ref<{ id: number; full_name: string; email: string } | null>(null)

const statuses = ['New', 'Reviewing', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Offer Sent', 'Hired', 'Rejected', 'Withdrawn']
const sources = ['Website', 'LinkedIn', 'Referral', 'Job Board', 'Walk-in', 'Other']

function formatDate(value: string | null | undefined) {
  if (!value) return '-'

  const date = new Date(value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  })
}

async function fetchApplicants() {
  loading.value = true
  try {
    const { data } = await axios.get('/api/hr/applicants', {
      params: {
        search: filters.search || undefined,
        job_opening_id: filters.jobId || undefined,
        status: filters.status || undefined,
        source: filters.source || undefined,
        rating: filters.rating || undefined,
        page: pagination.page,
        per_page: pagination.perPage,
      },
    })

    applicants.value = data.applicants?.data ?? []
    pagination.total = data.applicants?.total ?? 0
    stats.value = data.stats ?? stats.value
    jobOptions.value = data.job_openings ?? []
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to load applicants.',
      color: 'error',
    }
  } finally {
    loading.value = false
  }
}

async function fetchPipeline() {
  pipelineLoading.value = true
  try {
    const { data } = await axios.get('/api/hr/applicants/pipeline', {
      params: {
        job_opening_id: filters.jobId || undefined,
      },
    })
    pipeline.value = data.pipeline ?? []
  } finally {
    pipelineLoading.value = false
  }
}

async function openViewDrawer(applicant: { id: number }) {
  viewLoading.value = true
  viewDialog.value = true

  try {
    const { data } = await axios.get(`/api/hr/applicants/${applicant.id}`)
    const item = data.applicant

    viewApplicant.value = {
      id: item.id,
      full_name: item.full_name,
      first_name: item.first_name,
      last_name: item.last_name,
      email: item.email,
      phone: item.phone,
      initials: item.initials,
      source: item.source,
      experience_years: item.experience_years ? `${item.experience_years} yrs` : '-',
      current_company: item.current_company,
      current_position: item.current_position,
      expected_salary: item.expected_salary,
      stage: item.stage,
      stage_label: item.stage_label,
      status: item.status,
      status_color: item.status_color,
      rating: item.rating,
      notes: item.notes,
      resume_url: item.resume_url,
      is_converted: !!item.converted_employee_id,
      applied_date: formatDate(item.created_at),
      job_opening: item.job_opening
        ? {
            id: item.job_opening.id,
            title: item.job_opening.title,
            department: item.job_opening.department?.name ?? '-',
          }
        : null,
      cover_letter: item.cover_letter,
      rejected_reason: item.rejected_reason,
      interviewed_at: item.interviewed_at,
      hired_at: item.hired_at,
      converted_employee: item.converted_employee,
    }
  } catch {
    viewDialog.value = false
    snackbar.value = {
      show: true,
      message: 'Failed to load applicant details.',
      color: 'error',
    }
  } finally {
    viewLoading.value = false
  }
}

async function moveToNextStage(applicant: { id: number; stage: number }) {
  if (applicant.stage >= 5) return
  await moveToStage(applicant, applicant.stage + 1)
}

async function moveToStage(applicant: { id: number }, stage: number) {
  try {
    const { data } = await axios.patch(`/api/hr/applicants/${applicant.id}/stage`, { stage })
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    await fetchApplicants()
    if (activeTab.value === 'pipeline') {
      await fetchPipeline()
    }
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to move stage.',
      color: 'error',
    }
  }
}

async function updateStatus(applicant: { id: number }, status: string, reason = '') {
  try {
    const { data } = await axios.patch(`/api/hr/applicants/${applicant.id}/status`, { status, reason })
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    await fetchApplicants()
    if (activeTab.value === 'pipeline') {
      await fetchPipeline()
    }
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to update status.',
      color: 'error',
    }
  }
}

function openRejectDialog(applicant: ApplicantRow) {
  rejectingApplicant.value = applicant
  rejectReason.value = ''
  rejectDialog.value = true
}

async function submitReject() {
  if (!rejectingApplicant.value) return
  await updateStatus(rejectingApplicant.value, 'Rejected', rejectReason.value)
  rejectDialog.value = false
}

function openConvertDialog(applicant: { id: number; full_name: string }) {
  convertingApplicant.value = applicant
  convertDialog.value = true
}

async function convertToEmployee(applicant: { id: number }) {
  converting.value = true
  try {
    const { data } = await axios.post(`/api/hr/applicants/${applicant.id}/convert`)
    snackbar.value = {
      show: true,
      message: data.message,
      color: 'success',
    }
    convertDialog.value = false
    await fetchApplicants()
    if (activeTab.value === 'pipeline') {
      await fetchPipeline()
    }
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Conversion failed.',
      color: 'error',
    }
  } finally {
    converting.value = false
  }
}

function askDelete(applicant: ApplicantRow) {
  deletingApplicant.value = applicant
  deleteDialog.value = true
}

function openMessageDialog(applicant: ApplicantRow) {
  messageTarget.value = {
    id: applicant.id,
    full_name: applicant.full_name,
    email: applicant.email,
  }
  messageDialog.value = true
}

async function confirmDelete() {
  if (!deletingApplicant.value) return
  deleting.value = true
  try {
    await axios.delete(`/api/hr/applicants/${deletingApplicant.value.id}`)
    snackbar.value = {
      show: true,
      message: 'Applicant deleted.',
      color: 'success',
    }
    deleteDialog.value = false
    await fetchApplicants()
    if (activeTab.value === 'pipeline') {
      await fetchPipeline()
    }
  } catch {
    snackbar.value = {
      show: true,
      message: 'Failed to delete applicant.',
      color: 'error',
    }
  } finally {
    deleting.value = false
  }
}

watch(
  () => [filters.search, filters.jobId, filters.status, filters.source, filters.rating],
  () => {
    pagination.page = 1
    fetchApplicants()
    if (activeTab.value === 'pipeline') {
      fetchPipeline()
    }
  },
)

watch(
  () => activeTab.value,
  (tab) => {
    if (tab === 'pipeline') {
      fetchPipeline()
    }
  },
)

watch(
  () => [pagination.page, pagination.perPage],
  () => {
    fetchApplicants()
  },
)

onMounted(async () => {
  await fetchApplicants()
})
</script>

<template>
  <BaseBreadcrumb title="Applicants" subtitle="Track and manage job applicants" :breadcrumbs="breadcrumbs" />

  <div class="d-flex justify-space-between align-center flex-wrap ga-2 mb-4">
    <div>
      <h2 class="text-h3 mb-1">Applicants</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Track candidates through screening, interviews, and hiring.</p>
    </div>
  </div>

  <v-row class="mb-1">
    <v-col cols="12" sm="6" md="2"><v-card variant="outlined"><v-card-text>Total: <strong>{{ stats.total }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="2"><v-card variant="outlined"><v-card-text>New: <strong>{{ stats.new }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="2"><v-card variant="outlined"><v-card-text>Shortlisted: <strong>{{ stats.shortlisted }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="2"><v-card variant="outlined"><v-card-text>Interviewed: <strong>{{ stats.interviewed }}</strong></v-card-text></v-card></v-col>
    <v-col cols="12" sm="6" md="2"><v-card variant="outlined"><v-card-text>Hired: <strong>{{ stats.hired }}</strong></v-card-text></v-card></v-col>
  </v-row>

  <v-card variant="outlined">
    <v-tabs v-model="activeTab" color="primary" class="px-4 pt-2">
      <v-tab value="table">Applicants</v-tab>
      <v-tab value="pipeline">Pipeline View</v-tab>
    </v-tabs>
    <v-divider />

    <div class="pa-4">
      <v-row class="mb-4">
        <v-col cols="12" md="4"><v-text-field v-model="filters.search" placeholder="Search by name or email..." variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="3"><v-select v-model="filters.jobId" :items="[{ title: 'All Jobs', value: '' }, ...jobOptions.map((item) => ({ title: item.title, value: String(item.id) }))]" label="Job Opening" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.status" :items="['', ...statuses]" label="Status" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="2"><v-select v-model="filters.source" :items="['', ...sources]" label="Source" variant="outlined" hide-details /></v-col>
        <v-col cols="12" sm="6" md="1"><v-select v-model="filters.rating" :items="['', '1', '2', '3', '4', '5']" label="Rate" variant="outlined" hide-details /></v-col>
      </v-row>

      <div v-if="activeTab === 'table'">
        <v-skeleton-loader v-if="loading" type="table-tbody" />
        <v-table v-else>
          <thead>
            <tr>
              <th>Applicant</th>
              <th>Applied For</th>
              <th>Source</th>
              <th>Experience</th>
              <th>Stage</th>
              <th>Status</th>
              <th>Applied Date</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="app in applicants" :key="app.id">
              <td>
                <div class="d-flex align-center ga-3">
                  <v-avatar color="primary" variant="tonal" size="34"><span class="text-caption font-weight-bold">{{ app.initials }}</span></v-avatar>
                  <div>
                    <div class="font-weight-medium">{{ app.full_name }}</div>
                    <div class="text-caption text-medium-emphasis">{{ app.email }}</div>
                  </div>
                </div>
              </td>
              <td>
                <div v-if="app.job_opening">
                  <div class="text-body-2 font-weight-medium">{{ app.job_opening.title }}</div>
                  <v-chip size="x-small" variant="tonal" color="primary" class="mt-1">{{ app.job_opening.department }}</v-chip>
                </div>
                <span v-else class="text-medium-emphasis text-body-2">General Application</span>
              </td>
              <td>{{ app.source || '-' }}</td>
              <td>{{ app.experience_years }}</td>
              <td><v-chip size="small" variant="outlined">{{ app.stage_label }}</v-chip></td>
              <td><v-chip size="small" :color="app.status_color" variant="tonal">{{ app.status }}</v-chip></td>
              <td class="text-body-2 text-medium-emphasis">{{ app.applied_date }}</td>
              <td>
                <v-menu>
                  <template #activator="{ props }">
                    <v-btn v-bind="props" icon variant="text" size="small">
                      <img src="/assets/images/icons/action-menu.svg" alt="Actions" class="action-menu-icon" />
                    </v-btn>
                  </template>
                  <v-list density="compact">
                    <v-list-item prepend-icon="mdi-eye" title="View Details" @click="openViewDrawer(app)" />
                    <v-list-item prepend-icon="mdi-email-outline" title="Send Email" @click="openMessageDialog(app)" />
                    <v-list-item prepend-icon="mdi-arrow-right-circle" title="Move to Next Stage" :disabled="app.stage >= 5" @click="moveToNextStage(app)" />
                    <v-list-item v-if="app.stage < 3" prepend-icon="mdi-calendar-clock" title="Schedule Interview" @click="moveToStage(app, 3)" />
                    <v-list-item v-if="app.stage === 3" prepend-icon="mdi-email-send" title="Send Offer" @click="moveToStage(app, 4)" />
                    <v-list-item v-if="app.stage === 5 && !app.is_converted" prepend-icon="mdi-account-plus" title="Convert to Employee" base-color="success" @click="openConvertDialog(app)" />
                    <v-list-item v-if="app.status === 'New' || app.status === 'Reviewing'" prepend-icon="mdi-star" title="Shortlist" @click="updateStatus(app, 'Shortlisted')" />
                    <v-list-item v-if="app.status !== 'Rejected' && app.status !== 'Hired'" prepend-icon="mdi-close-circle" title="Reject" base-color="error" @click="openRejectDialog(app)" />
                    <v-divider />
                    <v-list-item prepend-icon="mdi-delete" title="Delete" base-color="error" @click="askDelete(app)" />
                  </v-list>
                </v-menu>
              </td>
            </tr>
          </tbody>
        </v-table>
      </div>

      <div v-else>
        <v-skeleton-loader v-if="pipelineLoading" type="article, article, article" />
        <div v-else class="d-flex gap-4 overflow-x-auto pb-4">
          <div v-for="col in pipeline" :key="col.stage" style="min-width:240px; width:240px">
            <div class="d-flex align-center justify-space-between mb-3">
              <div class="d-flex align-center gap-2">
                <v-icon size="16" :color="col.color">mdi-circle</v-icon>
                <span class="text-body-2 font-weight-bold">{{ col.label }}</span>
              </div>
              <v-chip size="x-small" :color="col.color" variant="tonal">{{ col.count }}</v-chip>
            </div>

            <div class="d-flex flex-column gap-2">
              <v-card v-for="app in col.applicants" :key="app.id" variant="outlined" class="pa-3 cursor-pointer" @click="openViewDrawer(app)">
                <div class="d-flex align-center gap-2 mb-2">
                  <v-avatar size="28" :color="col.color" variant="tonal"><span class="text-caption">{{ app.initials }}</span></v-avatar>
                  <div class="flex-1 min-w-0">
                    <div class="text-body-2 font-weight-medium text-truncate">{{ app.full_name }}</div>
                    <div class="text-caption text-medium-emphasis text-truncate">{{ app.job_opening }}</div>
                  </div>
                </div>

                <div class="d-flex align-center justify-space-between">
                  <v-chip size="x-small" :color="app.status_color" variant="tonal">{{ app.status }}</v-chip>
                  <div>
                    <v-icon v-for="s in 5" :key="s" size="12" :color="s <= (app.rating ?? 0) ? 'amber' : 'grey-lighten-3'">mdi-star</v-icon>
                  </div>
                </div>

                <div class="text-caption text-medium-emphasis mt-1">{{ app.source || '-' }} | {{ app.experience }}</div>

                <v-btn v-if="app.stage < 5 && !app.is_converted" size="x-small" variant="tonal" :color="col.color" block class="mt-2" @click.stop="moveToStage(app, app.stage + 1)">
                  Move Forward
                  <v-icon end size="14">mdi-arrow-right</v-icon>
                </v-btn>

                <v-btn v-if="app.stage === 5 && !app.is_converted" size="x-small" color="success" variant="flat" block class="mt-2" @click.stop="openConvertDialog(app)">
                  Convert to Employee
                </v-btn>

                <v-chip v-if="app.is_converted" size="x-small" color="success" variant="tonal" block class="mt-2">Converted to Employee</v-chip>
              </v-card>

              <div v-if="col.applicants.length === 0" class="text-center py-8 text-medium-emphasis text-caption border-dashed rounded">
                No applicants
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </v-card>

  <v-dialog v-model="viewDialog" max-width="640">
    <v-card>
      <v-card-title>{{ viewApplicant?.full_name }}</v-card-title>
      <v-card-text v-if="!viewLoading">
        <div class="mb-2">{{ viewApplicant?.email }}</div>
        <div class="mb-2">Phone: {{ viewApplicant?.phone || '-' }}</div>
        <div class="mb-2">Job: {{ viewApplicant?.job_opening?.title || 'General Application' }}</div>
        <div class="mb-2">Department: {{ viewApplicant?.job_opening?.department || '-' }}</div>
        <div class="mb-2">Status: {{ viewApplicant?.status }}</div>
        <div class="mb-2">Stage: {{ viewApplicant?.stage_label }}</div>
        <div class="mb-2">Applied: {{ viewApplicant?.applied_date }}</div>
        <div class="mb-2">Current Company: {{ viewApplicant?.current_company || '-' }}</div>
        <div class="mb-2">Current Position: {{ viewApplicant?.current_position || '-' }}</div>
        <div class="mb-2">Notes: {{ viewApplicant?.notes || '-' }}</div>
        <div class="mb-2">Rejected Reason: {{ viewApplicant?.rejected_reason || '-' }}</div>
        <v-btn v-if="viewApplicant?.resume_url" variant="outlined" :href="viewApplicant.resume_url" target="_blank">Open Resume</v-btn>
      </v-card-text>
      <v-card-text v-else>
        <v-skeleton-loader type="article" />
      </v-card-text>
    </v-card>
  </v-dialog>

  <v-dialog v-model="rejectDialog" max-width="420">
    <v-card>
      <v-card-title>Reject Applicant</v-card-title>
      <v-card-text><v-textarea v-model="rejectReason" label="Reason" variant="outlined" rows="3" /></v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
        <v-btn color="error" @click="submitReject">Reject</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="convertDialog" max-width="420">
    <v-card>
      <v-card-title>Convert to Employee</v-card-title>
      <v-card-text>Convert {{ convertingApplicant?.full_name }} to an employee record?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="convertDialog = false">Cancel</v-btn>
        <v-btn color="success" :loading="converting" @click="convertingApplicant && convertToEmployee(convertingApplicant)">Convert</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-dialog v-model="deleteDialog" max-width="420">
    <v-card>
      <v-card-title>Delete Applicant</v-card-title>
      <v-card-text>Delete {{ deletingApplicant?.full_name }}?</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
        <v-btn color="error" :loading="deleting" @click="confirmDelete">Delete</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>

  <SendMessageDialog
    v-model="messageDialog"
    recipient-type="applicant"
    :recipient-id="messageTarget?.id"
    :recipient-name="messageTarget?.full_name"
    :recipient-email="messageTarget?.email"
    default-category="recruitment"
    @sent="fetchApplicants"
  />
</template>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
