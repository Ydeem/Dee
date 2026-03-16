<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import UnauthorizedPage from '@/components/HR/UnauthorizedPage.vue';
import { usePermissions } from '@/composables/usePermissions';

type SectionKey = 'company' | 'payroll' | 'leave' | 'attendance' | 'recruitment';

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Settings', disabled: false, href: '#' },
  { title: 'HR Settings', disabled: true, href: '#' }
];

const { isAdmin } = usePermissions();

const loading = ref(false);
const saving = ref(false);
const activeSection = ref<SectionKey>('company');
const snackbar = ref({ show: false, message: '', color: 'success' as 'success' | 'error' });

const companyForm = reactive({
  company_name: '',
  company_phone: '',
  company_address: '',
  hr_email: '',
  default_currency: 'GHS',
  timezone: 'Africa/Accra',
  fiscal_year_start: 'January'
});

const payrollForm = reactive({
  pay_cycle: 'Monthly',
  pay_day: 28,
  ssnit_employee_rate: 5.5,
  ssnit_employer_rate: 13.0,
  overtime_rate: 1.5,
  payslip_template: 'Standard',
  payroll_approval_required: true
});

const leaveForm = reactive({
  leave_approval_levels: '1',
  leave_carry_forward: true,
  max_carry_forward_days: 5,
  leave_accrual: 'Annual',
  leave_calendar_public: false,
  notify_on_leave_request: true,
  auto_reject_after_days: 14
});

const attendanceForm = reactive({
  work_start_time: '08:00',
  work_end_time: '17:00',
  work_days: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as string[],
  lateness_threshold_mins: 15,
  overtime_threshold_mins: 30,
  allow_remote_checkin: false,
  attendance_geofencing: false,
  working_hours_per_day: 8
});

const recruitmentForm = reactive({
  max_resume_size_mb: 5,
  allowed_resume_formats: 'pdf,doc,docx',
  auto_reject_days: 30,
  careers_page_enabled: true,
  notify_on_application: true,
  default_pipeline_stages: 'Applied,Screening,Interview,Offer,Hired'
});

const sections = [
  {
    key: 'company' as const,
    label: 'Company Information',
    icon: 'mdi-office-building',
    description: 'Maintain company-level details used across payroll, leave, and employee records.'
  },
  {
    key: 'payroll' as const,
    label: 'Payroll Configuration',
    icon: 'mdi-cash-multiple',
    description: 'Set pay cycle defaults, statutory rates, overtime rules, and payslip template.'
  },
  {
    key: 'leave' as const,
    label: 'Leave Policies',
    icon: 'mdi-calendar-check',
    description: 'Control approval levels, carry-forward options, and leave calendar.'
  },
  {
    key: 'attendance' as const,
    label: 'Attendance Rules',
    icon: 'mdi-clock-check',
    description: 'Configure work hours, lateness thresholds, weekends, and check-in settings.'
  },
  {
    key: 'recruitment' as const,
    label: 'Recruitment Settings',
    icon: 'mdi-briefcase-account',
    description: 'Define resume limits, auto-rejection timelines, and careers page.'
  }
];

const canEdit = computed(() => isAdmin.value);

function showSnackbar(message: string, color: 'success' | 'error' = 'success') {
  snackbar.value = { show: true, message, color };
}

async function fetchSettings() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/hr/settings');
    const settings = data?.settings ?? {};

    Object.assign(companyForm, settings.company ?? {});
    Object.assign(payrollForm, settings.payroll ?? {});
    Object.assign(leaveForm, settings.leave ?? {});

    const attendance = settings.attendance ?? {};
    Object.assign(attendanceForm, attendance);
    if (Array.isArray(attendance.work_days)) {
      attendanceForm.work_days = attendance.work_days;
    }

    Object.assign(recruitmentForm, settings.recruitment ?? {});
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load settings.', 'error');
  } finally {
    loading.value = false;
  }
}

async function saveSection(section: SectionKey, payload: Record<string, any>) {
  if (!canEdit.value) {
    showSnackbar('You do not have permission to edit HR settings.', 'error');
    return;
  }

  saving.value = true;
  try {
    const { data } = await axios.post(`/api/hr/settings/${section}`, payload);
    showSnackbar(data?.message ?? 'Settings saved.', 'success');
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to save settings.', 'error');
  } finally {
    saving.value = false;
  }
}

async function saveAllChanges() {
  const sectionMap: Record<SectionKey, Record<string, any>> = {
    company: companyForm,
    payroll: payrollForm,
    leave: leaveForm,
    attendance: attendanceForm,
    recruitment: recruitmentForm
  };

  await saveSection(activeSection.value, sectionMap[activeSection.value]);
}

function toggleWorkDay(day: string) {
  const idx = attendanceForm.work_days.indexOf(day);
  if (idx > -1) {
    attendanceForm.work_days.splice(idx, 1);
  } else {
    attendanceForm.work_days.push(day);
  }
}

onMounted(() => {
  fetchSettings();
});
</script>

<template>
  <BaseBreadcrumb
    title="HR Settings"
    subtitle="Configure your HR system preferences"
    :breadcrumbs="breadcrumbs"
  />

  <UnauthorizedPage v-if="!isAdmin" />

  <template v-else>
  <div class="d-flex justify-space-between align-center flex-wrap ga-3 mb-4">
    <div>
      <h2 class="text-h3 mb-1">HR Settings</h2>
      <p class="text-subtitle-1 text-lightText mb-0">Configure your HR system preferences</p>
    </div>

    <v-btn
      color="primary"
      prepend-icon="mdi-content-save"
      :loading="saving"
      :disabled="!canEdit"
      @click="saveAllChanges"
    >
      Save All Changes
    </v-btn>
  </div>

  <v-row class="align-start">
    <v-col cols="12" lg="3">
      <v-card class="bg-surface rounded-xl hr-card-shadow sticky-nav settings-nav" variant="outlined" elevation="0">
        <v-list nav>
          <v-list-item
            v-for="s in sections"
            :key="s.key"
            :value="s.key"
            :active="activeSection === s.key"
            active-color="primary"
            rounded="lg"
            class="mb-1"
            @click="activeSection = s.key"
          >
            <template #prepend>
              <v-avatar
                size="36"
                :color="activeSection === s.key ? 'primary' : 'grey-lighten-3'"
                variant="tonal"
              >
                <v-icon
                  size="18"
                  :color="activeSection === s.key ? 'primary' : 'grey'"
                >
                  {{ s.icon }}
                </v-icon>
              </v-avatar>
            </template>

            <v-list-item-title class="text-body-2 font-weight-medium">{{ s.label }}</v-list-item-title>
            <v-list-item-subtitle class="text-caption">{{ s.description.substring(0, 50) }}...</v-list-item-subtitle>
          </v-list-item>
        </v-list>
      </v-card>
    </v-col>

    <v-col cols="12" lg="9">
      <v-skeleton-loader v-if="loading" type="article, article" />

      <v-card v-else class="bg-surface rounded-xl hr-card-shadow" variant="outlined" elevation="0">
        <v-card-text>
          <div v-if="activeSection === 'company'">
            <h3 class="text-h6 mb-1">Company Information</h3>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Maintain company-level details used across payroll, leave, and employee records.
            </p>

            <v-row>
              <v-col cols="12" md="6">
                <v-text-field v-model="companyForm.company_name" label="Company Name" variant="outlined" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="companyForm.hr_email" label="HR Email" type="email" variant="outlined" />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field v-model="companyForm.company_phone" label="Company Phone" variant="outlined" />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="companyForm.default_currency"
                  :items="['GHS', 'USD', 'EUR', 'GBP', 'NGN']"
                  label="Default Currency"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="companyForm.timezone"
                  :items="['Africa/Accra', 'Africa/Lagos', 'Europe/London', 'UTC']"
                  label="Timezone"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="companyForm.fiscal_year_start"
                  :items="['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']"
                  label="Fiscal Year Start"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="companyForm.company_address"
                  label="Company Address"
                  rows="3"
                  variant="outlined"
                />
              </v-col>
            </v-row>
          </div>

          <div v-if="activeSection === 'payroll'">
            <h3 class="text-h6 mb-1">Payroll Configuration</h3>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Set pay cycle defaults, statutory rates, overtime rules, and payslip template.
            </p>

            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  v-model="payrollForm.pay_cycle"
                  label="Pay Cycle"
                  variant="outlined"
                  :items="['Weekly', 'Bi-weekly', 'Monthly']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="payrollForm.pay_day"
                  label="Pay Day (day of month)"
                  type="number"
                  min="1"
                  max="31"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="payrollForm.ssnit_employee_rate"
                  label="SSNIT Employee Rate (%)"
                  type="number"
                  step="0.1"
                  variant="outlined"
                  hint="Standard Ghana rate: 5.5%"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="payrollForm.ssnit_employer_rate"
                  label="SSNIT Employer Rate (%)"
                  type="number"
                  step="0.1"
                  variant="outlined"
                  hint="Standard Ghana rate: 13.0%"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="payrollForm.overtime_rate"
                  label="Overtime Multiplier"
                  type="number"
                  step="0.5"
                  variant="outlined"
                  hint="e.g. 1.5 = 150% of hourly rate"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="payrollForm.payslip_template"
                  label="Payslip Template"
                  variant="outlined"
                  :items="['Standard', 'Detailed', 'Compact']"
                />
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="payrollForm.payroll_approval_required"
                  label="Require approval before processing payroll"
                  color="primary"
                  inset
                />
              </v-col>
            </v-row>
          </div>

          <div v-if="activeSection === 'leave'">
            <h3 class="text-h6 mb-1">Leave Policies</h3>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Control approval levels, carry-forward options, and leave calendar.
            </p>

            <v-row>
              <v-col cols="12" md="6">
                <v-select
                  v-model="leaveForm.leave_approval_levels"
                  label="Approval Levels"
                  variant="outlined"
                  :items="[{ title: '1 Level', value: '1' }, { title: '2 Levels', value: '2' }]"
                  item-title="title"
                  item-value="value"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="leaveForm.leave_accrual"
                  label="Leave Accrual"
                  variant="outlined"
                  :items="['Annual', 'Monthly', 'Pro-rated']"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="leaveForm.auto_reject_after_days"
                  label="Auto-reject requests after (days)"
                  type="number"
                  min="1"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="leaveForm.max_carry_forward_days"
                  label="Max Carry Forward Days"
                  type="number"
                  min="0"
                  variant="outlined"
                  :disabled="!leaveForm.leave_carry_forward"
                />
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="leaveForm.leave_carry_forward"
                  label="Allow leave carry forward"
                  color="primary"
                  inset
                />
                <v-switch
                  v-model="leaveForm.notify_on_leave_request"
                  label="Notify HR on new leave request"
                  color="primary"
                  inset
                />
                <v-switch
                  v-model="leaveForm.leave_calendar_public"
                  label="Make leave calendar public to all employees"
                  color="primary"
                  inset
                />
              </v-col>
            </v-row>
          </div>

          <div v-if="activeSection === 'attendance'">
            <h3 class="text-h6 mb-1">Attendance Rules</h3>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Configure work hours, lateness thresholds, weekends, and check-in settings.
            </p>

            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="attendanceForm.work_start_time"
                  label="Work Start Time"
                  type="time"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="attendanceForm.work_end_time"
                  label="Work End Time"
                  type="time"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="attendanceForm.working_hours_per_day"
                  label="Working Hours Per Day"
                  type="number"
                  min="1"
                  max="24"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="attendanceForm.lateness_threshold_mins"
                  label="Lateness Threshold (minutes)"
                  type="number"
                  min="0"
                  variant="outlined"
                  hint="Grace period before marking late"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="attendanceForm.overtime_threshold_mins"
                  label="Overtime Threshold (minutes)"
                  type="number"
                  min="0"
                  variant="outlined"
                  hint="Minutes after end time for overtime"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12">
                <p class="text-body-2 font-weight-medium mb-2">Working Days</p>
                <div class="d-flex ga-2 flex-wrap">
                  <v-chip
                    v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']"
                    :key="day"
                    :color="attendanceForm.work_days.includes(day) ? 'primary' : 'default'"
                    :variant="attendanceForm.work_days.includes(day) ? 'flat' : 'outlined'"
                    class="cursor-pointer"
                    @click="toggleWorkDay(day)"
                  >
                    {{ day }}
                  </v-chip>
                </div>
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="attendanceForm.allow_remote_checkin"
                  label="Allow remote check-in"
                  color="primary"
                  inset
                />
                <v-switch
                  v-model="attendanceForm.attendance_geofencing"
                  label="Enable geofencing for check-in"
                  color="primary"
                  inset
                />
              </v-col>
            </v-row>
          </div>

          <div v-if="activeSection === 'recruitment'">
            <h3 class="text-h6 mb-1">Recruitment Settings</h3>
            <p class="text-body-2 text-medium-emphasis mb-6">
              Define resume limits, auto-rejection timelines, and careers page settings.
            </p>

            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="recruitmentForm.max_resume_size_mb"
                  label="Max Resume Size (MB)"
                  type="number"
                  min="1"
                  max="20"
                  variant="outlined"
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="recruitmentForm.allowed_resume_formats"
                  label="Allowed Resume Formats"
                  variant="outlined"
                  hint="Comma separated: pdf,doc,docx"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="recruitmentForm.auto_reject_days"
                  label="Auto-reject applications after (days)"
                  type="number"
                  min="1"
                  variant="outlined"
                  hint="0 = never auto-reject"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="recruitmentForm.default_pipeline_stages"
                  label="Default Pipeline Stages"
                  variant="outlined"
                  rows="2"
                  hint="Comma separated stage names"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12">
                <v-switch
                  v-model="recruitmentForm.careers_page_enabled"
                  label="Enable public careers page"
                  color="primary"
                  inset
                />
                <v-switch
                  v-model="recruitmentForm.notify_on_application"
                  label="Notify HR on new application"
                  color="primary"
                  inset
                />
              </v-col>
            </v-row>
          </div>
        </v-card-text>
      </v-card>
    </v-col>
  </v-row>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
  </template>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.settings-nav {
  min-width: 260px;
}

.sticky-nav {
  position: sticky;
  top: 88px;
}

@media (max-width: 959px) {
  .sticky-nav {
    position: static;
  }

  .settings-nav {
    min-width: 100%;
  }
}
</style>

