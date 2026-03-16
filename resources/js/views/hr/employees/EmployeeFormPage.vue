<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import { usePermissions } from '@/composables/usePermissions';

interface OptionItem {
  id: number;
  name: string;
}

const props = defineProps<{
  mode: 'create' | 'edit';
  employeeId?: number;
}>();

const isEdit = computed(() => props.mode === 'edit');
const { can } = usePermissions();
const canViewPayroll = computed(() => can('view payroll'));
const loading = ref(true);
const saving = ref(false);
const tab = ref('personal');

const snackbar = ref({ show: false, message: '', color: 'success' });

const departments = ref<OptionItem[]>([]);
const designations = ref<OptionItem[]>([]);
const shifts = ref<OptionItem[]>([]);
const managers = ref<Array<{ id: number; first_name: string; last_name: string; employee_id: string }>>([]);
const lastUpdatedMeta = ref('');

const profilePhoto = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
const docs = ref<Array<{ file: File; category: string }>>([]);

const breadcrumbs = computed(() => {
  const tail = isEdit.value ? 'Edit Employee' : 'Add Employee';
  return [
    { title: 'HR Module', disabled: false, href: '#' },
    { title: 'Employees', disabled: false, href: '/hr/employees' },
    { title: tail, disabled: true, href: '#' }
  ];
});

const form = ref<any>({
  first_name: '',
  last_name: '',
  date_of_birth: '',
  gender: '',
  national_id: '',
  phone: '',
  personal_email: '',
  work_email: '',
  address: '',
  emergency_contact_name: '',
  emergency_contact_phone: '',

  employee_id: '',
  department_id: null,
  designation_id: null,
  employment_type: 'Full-time',
  employment_status: 'Active',
  join_date: '',
  reporting_manager_id: null,
  work_location: 'Office',
  shift_id: null,

  basic_salary: null,
  pay_frequency: 'Monthly',
  bank_name: '',
  account_number: '',
  account_name: '',
  tin: '',
  ssnit: '',
  allowances: [{ type: '', amount: null }],

  bio: '',
  notes: ''
});

const genders = ['Male', 'Female', 'Other'];
const employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Intern'];
const statuses = ['Active', 'Probation', 'Inactive', 'On Leave'];
const payFrequencies = ['Monthly', 'Bi-weekly', 'Weekly'];
const workLocations = ['Office', 'Remote', 'Hybrid'];
const docCategories = ['CV/Resume', 'National ID', 'Certificates', 'Offer Letter', 'Contract', 'Other'];
const managerOptions = computed(() =>
  managers.value.map((manager) => ({
    ...manager,
    full_name: ((manager.first_name ?? '') + ' ' + (manager.last_name ?? '')).trim(),
  }))
);

function addAllowance() {
  form.value.allowances.push({ type: '', amount: null });
}

function removeAllowance(index: number) {
  form.value.allowances.splice(index, 1);
  if (!form.value.allowances.length) {
    form.value.allowances.push({ type: '', amount: null });
  }
}

function setProfilePhoto(file: File | File[] | null) {
  const selected = Array.isArray(file) ? file[0] : file;
  profilePhoto.value = selected ?? null;
  photoPreview.value = selected ? URL.createObjectURL(selected) : null;
}

function addDocuments(fileInput: File[] | File | null) {
  const selected = Array.isArray(fileInput) ? fileInput : fileInput ? [fileInput] : [];
  selected.forEach((file) => docs.value.push({ file, category: 'Other' }));
}

function removeDoc(index: number) {
  docs.value.splice(index, 1);
}

async function fetchOptions() {
  const { data } = await axios.get('/api/hr/employees/options');
  departments.value = data.departments ?? [];
  designations.value = data.designations ?? [];
  shifts.value = data.shifts ?? [];
  managers.value = data.managers ?? [];
}

async function fetchEmployee() {
  if (!isEdit.value || !props.employeeId) return;
  const { data } = await axios.get(`/api/hr/employees/${props.employeeId}`);
  form.value = {
    ...form.value,
    ...data.employee,
    allowances: data.employee.allowances?.length ? data.employee.allowances : [{ type: '', amount: null }]
  };
  lastUpdatedMeta.value = `Last updated by ${data.employee.updated_by ?? 'System'} on ${data.employee.updated_at ?? '-'}`;
}

function payload() {
  const fd = new FormData();

  Object.entries(form.value).forEach(([key, value]) => {
    if (value === null || value === undefined) return;
    if (key === 'allowances') return;
    fd.append(key, String(value));
  });

  (form.value.allowances ?? []).forEach((item: any, index: number) => {
    fd.append(`allowances[${index}][type]`, item.type ?? '');
    fd.append(`allowances[${index}][amount]`, String(item.amount ?? 0));
  });

  if (profilePhoto.value) {
    fd.append('profile_photo', profilePhoto.value);
  }

  docs.value.forEach((doc, index) => {
    fd.append(`documents[${index}]`, doc.file);
    fd.append(`document_categories[${index}]`, doc.category);
  });

  return fd;
}

async function submit(draft = false) {
  saving.value = true;
  try {
    const fd = payload();
    if (draft) {
      fd.set('employment_status', 'Probation');
    }

    let targetEmployeeId: number | null = null;

    if (isEdit.value && props.employeeId) {
      fd.append('_method', 'PUT');
      const { data } = await axios.post(`/api/hr/employees/${props.employeeId}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      targetEmployeeId = Number(data?.employee?.id ?? props.employeeId ?? 0) || null;
    } else {
      const { data } = await axios.post('/api/hr/employees', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
      targetEmployeeId = Number(data?.employee?.id ?? 0) || null;
    }

    snackbar.value = { show: true, message: draft ? 'Draft saved successfully.' : 'Employee saved successfully.', color: 'success' };
    if (targetEmployeeId) {
      router.visit(`/hr/employees/${targetEmployeeId}`);
    } else {
      router.visit('/hr/employees');
    }
  } catch (error: any) {
    snackbar.value = { show: true, message: error?.response?.data?.message ?? 'Save failed.', color: 'error' };
  } finally {
    saving.value = false;
  }
}
onMounted(async () => {
  loading.value = true;
  try {
    await fetchOptions();
    await fetchEmployee();
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <BaseBreadcrumb :title="isEdit ? 'Edit Employee' : 'Add Employee'" subtitle="Employee profile and HR details" :breadcrumbs="breadcrumbs" />

  <v-skeleton-loader v-if="loading" type="article" />

  <v-card v-else class="bg-surface hr-card-shadow rounded-lg" variant="outlined" elevation="0">
    <v-card-text>
      <p v-if="isEdit && lastUpdatedMeta" class="text-caption text-lightText mb-4">{{ lastUpdatedMeta }}</p>

      <v-tabs v-model="tab" color="primary" grow>
        <v-tab value="personal">Personal Information</v-tab>
        <v-tab value="employment">Employment Details</v-tab>
        <v-tab v-if="canViewPayroll" value="payroll">Salary & Payroll</v-tab>
        <v-tab value="documents">Documents</v-tab>
      </v-tabs>

      <v-divider class="my-3" />

      <v-window v-model="tab">
        <v-window-item value="personal">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.first_name" label="First Name *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.last_name" label="Last Name *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.date_of_birth" type="date" label="Date of Birth" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.gender" :items="genders" label="Gender" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.national_id" label="National ID / Ghana Card Number" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.phone" label="Phone Number *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.personal_email" type="email" label="Personal Email *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6">
              <v-file-input label="Profile Photo" accept="image/png,image/jpg,image/jpeg" variant="outlined" @update:model-value="setProfilePhoto" />
              <v-avatar v-if="photoPreview" size="56"><img :src="photoPreview" alt="profile preview" /></v-avatar>
            </v-col>
            <v-col cols="12"><v-textarea v-model="form.address" label="Address" rows="2" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.emergency_contact_name" label="Emergency Contact Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.emergency_contact_phone" label="Emergency Contact Phone" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="employment">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.employee_id" label="Employee ID *" variant="outlined" hint="Auto-generated if empty" persistent-hint /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.work_email" type="email" label="Work Email *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.department_id" :items="departments" item-title="name" item-value="id" label="Department *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.designation_id" :items="designations" item-title="name" item-value="id" label="Designation *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.employment_type" :items="employmentTypes" label="Employment Type *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.employment_status" :items="statuses" label="Employment Status" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.join_date" type="date" label="Join Date *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.reporting_manager_id" :items="managerOptions" item-title="full_name" item-value="id" label="Reporting Manager" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.work_location" :items="workLocations" label="Work Location" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.shift_id" :items="shifts" item-title="name" item-value="id" label="Shift" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>

        <v-window-item v-if="canViewPayroll" value="payroll">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.basic_salary" type="number" label="Basic Salary *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.pay_frequency" :items="payFrequencies" label="Pay Frequency" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.bank_name" label="Bank Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.account_number" label="Account Number" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.account_name" label="Account Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.tin" label="TIN" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.ssnit" label="SSNIT" variant="outlined" /></v-col>
            <v-col cols="12">
              <div class="d-flex justify-space-between align-center mb-2">
                <h6 class="text-subtitle-1 mb-0">Allowances</h6>
                <v-btn variant="text" color="primary" prepend-icon="mdi-plus" @click="addAllowance">Add Row</v-btn>
              </div>
              <v-row v-for="(allowance, index) in form.allowances" :key="`allowance-${index}`">
                <v-col cols="12" sm="6"><v-text-field v-model="allowance.type" label="Allowance Type" variant="outlined" density="comfortable" /></v-col>
                <v-col cols="10" sm="5"><v-text-field v-model="allowance.amount" type="number" label="Amount" variant="outlined" density="comfortable" /></v-col>
                <v-col cols="2" sm="1" class="d-flex align-center justify-center"><v-btn icon="mdi-delete-outline" color="error" variant="text" @click="removeAllowance(index)" /></v-col>
              </v-row>
            </v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="documents">
          <v-file-input multiple accept=".pdf,.png,.jpg,.jpeg" label="Upload Documents" variant="outlined" prepend-icon="mdi-file-upload-outline" @update:model-value="addDocuments" />
          <v-list class="mt-3" v-if="docs.length">
            <v-list-item v-for="(doc, index) in docs" :key="`doc-${index}`" class="px-0">
              <template #prepend><v-icon icon="mdi-file-document-outline" class="me-2" /></template>
              <v-list-item-title>{{ doc.file.name }}</v-list-item-title>
              <template #append>
                <div class="d-flex align-center ga-2">
                  <v-select v-model="doc.category" :items="docCategories" density="compact" hide-details style="width: 150px" />
                  <v-btn icon="mdi-delete-outline" color="error" variant="text" @click="removeDoc(index)" />
                </div>
              </template>
            </v-list-item>
          </v-list>
        </v-window-item>
      </v-window>
    </v-card-text>

    <v-divider />

    <v-card-actions class="justify-end ga-2 pa-4 sticky-footer">
      <v-btn variant="outlined" @click="router.visit('/hr/employees')">Cancel</v-btn>
      <v-btn color="secondary" variant="flat" :loading="saving" @click="submit(true)">Save Draft</v-btn>
      <v-btn color="primary" variant="flat" :loading="saving" @click="submit(false)">Save Employee</v-btn>
    </v-card-actions>
  </v-card>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}

.sticky-footer {
  position: sticky;
  bottom: 0;
  background: #fff;
}
</style>
