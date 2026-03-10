<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import axios from 'axios';

interface OptionItem {
  id: number;
  name: string;
}

interface EmployeeRecord {
  id?: number;
  employee_id?: string;
  first_name?: string;
  last_name?: string;
  date_of_birth?: string | null;
  gender?: string | null;
  national_id?: string | null;
  phone?: string;
  personal_email?: string;
  work_email?: string | null;
  address?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  department_id?: number | null;
  designation_id?: number | null;
  employment_type?: string;
  employment_status?: string;
  join_date?: string | null;
  reporting_manager_id?: number | null;
  work_location?: string | null;
  shift_id?: number | null;
  basic_salary?: number | null;
  pay_frequency?: string | null;
  bank_name?: string | null;
  account_number?: string | null;
  account_name?: string | null;
  tin?: string | null;
  ssnit?: string | null;
  allowances?: Array<{ type: string; amount: number | null }>;
  bio?: string | null;
  notes?: string | null;
}

const props = defineProps<{
  modelValue: boolean;
  employee?: EmployeeRecord | null;
  departments: OptionItem[];
  designations: OptionItem[];
  shifts: OptionItem[];
  managers: Array<{ id: number; first_name: string; last_name: string; employee_id: string }>;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void;
  (e: 'saved'): void;
}>();

const activeTab = ref('personal');
const loading = ref(false);
const snackbar = ref({ show: false, message: '', color: 'success' });
const profilePhoto = ref<File | null>(null);
const photoPreview = ref<string | null>(null);
const documents = ref<Array<{ file: File; category: string }>>([]);

const form = ref<EmployeeRecord>({});

const managerOptions = computed(() =>
  props.managers.map((manager) => ({
    title: `${manager.first_name} ${manager.last_name} (${manager.employee_id})`,
    value: manager.id
  }))
);

const designationOptions = computed(() => props.designations.map((designation) => ({ title: designation.name, value: designation.id })));
const departmentOptions = computed(() => props.departments.map((department) => ({ title: department.name, value: department.id })));
const shiftOptions = computed(() => props.shifts.map((shift) => ({ title: shift.name, value: shift.id })));

const employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Intern'];
const employmentStatuses = ['Active', 'Probation', 'Inactive', 'On Leave'];
const payFrequencies = ['Monthly', 'Bi-weekly', 'Weekly'];
const workLocations = ['Office', 'Remote', 'Hybrid'];
const genders = ['Male', 'Female', 'Other'];
const docCategories = ['CV/Resume', 'National ID', 'Certificates', 'Offer Letter', 'Contract', 'Other'];

function resetForm() {
  form.value = {
    first_name: '',
    last_name: '',
    date_of_birth: null,
    gender: null,
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
    join_date: null,
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
  };
  documents.value = [];
  profilePhoto.value = null;
  photoPreview.value = null;
  activeTab.value = 'personal';
}

watch(
  () => props.modelValue,
  (open) => {
    if (!open) return;

    if (props.employee) {
      form.value = {
        ...form.value,
        ...props.employee,
        allowances: props.employee.allowances?.length ? props.employee.allowances : [{ type: '', amount: null }]
      };
      photoPreview.value = null;
      documents.value = [];
    } else {
      resetForm();
    }
  }
);

function closeDrawer() {
  emit('update:modelValue', false);
}

function addAllowanceRow() {
  if (!form.value.allowances) {
    form.value.allowances = [];
  }
  form.value.allowances.push({ type: '', amount: null });
}

function removeAllowanceRow(index: number) {
  form.value.allowances?.splice(index, 1);
  if (!form.value.allowances?.length) {
    form.value.allowances = [{ type: '', amount: null }];
  }
}

function handleProfilePhoto(file: File | File[] | null) {
  const selected = Array.isArray(file) ? file[0] : file;
  profilePhoto.value = selected ?? null;
  photoPreview.value = selected ? URL.createObjectURL(selected) : null;
}

function addDocuments(files: File[] | File | null) {
  const input = Array.isArray(files) ? files : files ? [files] : [];
  input.forEach((file) => documents.value.push({ file, category: 'Other' }));
}

function removeDocument(index: number) {
  documents.value.splice(index, 1);
}

function appendFormData(isDraft: boolean) {
  const payload = new FormData();
  const values = form.value;

  Object.entries(values).forEach(([key, value]) => {
    if (value === null || value === undefined) return;
    if (key === 'allowances' || key === 'skills') return;
    payload.append(key, String(value));
  });

  (values.allowances ?? []).forEach((allowance, index) => {
    payload.append(`allowances[${index}][type]`, allowance.type ?? '');
    payload.append(`allowances[${index}][amount]`, allowance.amount ? String(allowance.amount) : '0');
  });

  if (profilePhoto.value) {
    payload.append('profile_photo', profilePhoto.value);
  }

  documents.value.forEach((doc, index) => {
    payload.append(`documents[${index}]`, doc.file);
    payload.append(`document_categories[${index}]`, doc.category);
  });

  if (isDraft) {
    payload.append('employment_status', 'Probation');
  }

  return payload;
}

async function saveEmployee(isDraft = false) {
  loading.value = true;
  try {
    const payload = appendFormData(isDraft);
    if (props.employee?.id) {
      payload.append('_method', 'PUT');
      await axios.post(`/api/hr/employees/${props.employee.id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
    } else {
      await axios.post('/api/hr/employees', payload, {
        headers: { 'Content-Type': 'multipart/form-data' }
      });
    }

    snackbar.value = {
      show: true,
      message: isDraft ? 'Draft saved successfully.' : 'Employee saved successfully.',
      color: 'success'
    };

    emit('saved');
    closeDrawer();
  } catch (error: any) {
    snackbar.value = {
      show: true,
      message: error?.response?.data?.message ?? 'Failed to save employee.',
      color: 'error'
    };
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <v-navigation-drawer :model-value="modelValue" location="right" temporary width="600" class="employee-drawer" @update:model-value="emit('update:modelValue', $event)">
    <div class="d-flex align-center justify-space-between px-6 py-4 border-b">
      <div>
        <h4 class="text-h5 mb-1">{{ employee?.id ? 'Edit Employee' : 'Add Employee' }}</h4>
        <p class="text-body-2 text-lightText mb-0">Complete all required fields marked with *</p>
      </div>
      <v-btn icon="mdi-close" variant="text" @click="closeDrawer" />
    </div>

    <v-tabs v-model="activeTab" color="primary" grow>
      <v-tab value="personal">Personal Information</v-tab>
      <v-tab value="employment">Employment Details</v-tab>
      <v-tab value="payroll">Salary & Payroll</v-tab>
      <v-tab value="documents">Documents</v-tab>
    </v-tabs>

    <div class="drawer-body px-6 py-4">
      <v-window v-model="activeTab">
        <v-window-item value="personal">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.first_name" label="First Name *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.last_name" label="Last Name *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.date_of_birth" label="Date of Birth" type="date" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.gender" :items="genders" label="Gender" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.national_id" label="National ID / Ghana Card Number" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.phone" label="Phone Number *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.personal_email" label="Personal Email *" type="email" variant="outlined" /></v-col>
            <v-col cols="12" sm="6">
              <v-file-input accept="image/png,image/jpg,image/jpeg" label="Profile Photo" prepend-icon="mdi-camera" variant="outlined" @update:model-value="handleProfilePhoto" />
              <v-avatar v-if="photoPreview" size="56" rounded="circle"><img :src="photoPreview" alt="photo preview" /></v-avatar>
            </v-col>
            <v-col cols="12"><v-textarea v-model="form.address" label="Address" rows="2" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.emergency_contact_name" label="Emergency Contact Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.emergency_contact_phone" label="Emergency Contact Phone" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="employment">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.employee_id" label="Employee ID *" variant="outlined" hint="Leave blank to auto-generate" persistent-hint /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.work_email" label="Work Email *" type="email" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.department_id" :items="departmentOptions" label="Department *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.designation_id" :items="designationOptions" label="Designation *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.employment_type" :items="employmentTypes" label="Employment Type *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.employment_status" :items="employmentStatuses" label="Employment Status" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.join_date" type="date" label="Join Date *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.reporting_manager_id" :items="managerOptions" label="Reporting Manager" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.work_location" :items="workLocations" label="Work Location" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.shift_id" :items="shiftOptions" label="Shift" variant="outlined" /></v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="payroll">
          <v-row>
            <v-col cols="12" sm="6"><v-text-field v-model="form.basic_salary" type="number" label="Basic Salary *" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-select v-model="form.pay_frequency" :items="payFrequencies" label="Pay Frequency" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.bank_name" label="Bank Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.account_number" label="Account Number" variant="outlined" /></v-col>
            <v-col cols="12" sm="4"><v-text-field v-model="form.account_name" label="Account Name" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.tin" label="Tax Identification Number (TIN)" variant="outlined" /></v-col>
            <v-col cols="12" sm="6"><v-text-field v-model="form.ssnit" label="Social Security Number (SSNIT)" variant="outlined" /></v-col>

            <v-col cols="12">
              <div class="d-flex justify-space-between align-center mb-2">
                <h6 class="text-subtitle-1 mb-0">Allowances</h6>
                <v-btn variant="text" color="primary" prepend-icon="mdi-plus" @click="addAllowanceRow">Add Row</v-btn>
              </div>
              <v-row v-for="(allowance, index) in form.allowances" :key="`allowance-${index}`" class="mb-1">
                <v-col cols="12" sm="6"><v-text-field v-model="allowance.type" label="Allowance Type" variant="outlined" density="comfortable" /></v-col>
                <v-col cols="10" sm="5"><v-text-field v-model="allowance.amount" type="number" label="Amount" variant="outlined" density="comfortable" /></v-col>
                <v-col cols="2" sm="1" class="d-flex align-center justify-center">
                  <v-btn icon="mdi-delete-outline" color="error" variant="text" @click="removeAllowanceRow(index)" />
                </v-col>
              </v-row>
            </v-col>
          </v-row>
        </v-window-item>

        <v-window-item value="documents">
          <v-file-input
            multiple
            label="Upload documents (PDF, JPG, PNG - max 5MB each)"
            prepend-icon="mdi-file-upload-outline"
            accept=".pdf,.png,.jpg,.jpeg"
            variant="outlined"
            @update:model-value="addDocuments"
          />

          <v-list class="mt-4" v-if="documents.length">
            <v-list-item v-for="(doc, index) in documents" :key="`doc-${index}`" rounded="md" class="px-2">
              <template #prepend><v-icon icon="mdi-file-document-outline" class="me-2" /></template>
              <v-list-item-title>{{ doc.file.name }}</v-list-item-title>
              <template #append>
                <div class="d-flex align-center ga-2">
                  <v-select v-model="doc.category" :items="docCategories" density="compact" hide-details style="width: 160px" />
                  <v-btn icon="mdi-delete-outline" color="error" variant="text" @click="removeDocument(index)" />
                </div>
              </template>
            </v-list-item>
          </v-list>

          <v-alert v-else type="info" variant="tonal" class="mt-4">No documents selected yet.</v-alert>
        </v-window-item>
      </v-window>
    </div>

    <div class="drawer-footer pa-4 border-t d-flex justify-end ga-2">
      <v-btn variant="outlined" @click="closeDrawer">Cancel</v-btn>
      <v-btn color="secondary" variant="flat" :loading="loading" @click="saveEmployee(true)">Save Draft</v-btn>
      <v-btn color="primary" variant="flat" :loading="loading" @click="saveEmployee(false)">Save Employee</v-btn>
    </div>
  </v-navigation-drawer>

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.message }}</v-snackbar>
</template>

<style scoped>
.employee-drawer {
  box-shadow: 0 12px 28px rgba(16, 24, 40, 0.16);
}

.drawer-body {
  height: calc(100% - 170px);
  overflow-y: auto;
}

.drawer-footer {
  position: sticky;
  bottom: 0;
  background: #fff;
}

.border-b {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.border-t {
  border-top: 1px solid rgba(0, 0, 0, 0.08);
}
</style>
