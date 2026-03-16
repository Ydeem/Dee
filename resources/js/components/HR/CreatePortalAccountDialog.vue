<script setup lang="ts">
import { computed, ref, watch } from 'vue';

interface RoleOption {
  id: number;
  name: string;
  color?: string | null;
}

interface EmployeeAccountTarget {
  id: number;
  first_name?: string | null;
  last_name?: string | null;
  full_name?: string | null;
  work_email?: string | null;
  personal_email?: string | null;
}

const props = defineProps<{
  modelValue: boolean;
  employee: EmployeeAccountTarget | null;
  roles: RoleOption[];
  submitting?: boolean;
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void;
  (event: 'create', payload: {
    name: string;
    email: string;
    username: string;
    password: string;
    role_id: number;
    send_email: boolean;
  }): void;
}>();

const showPassword = ref(false);
const form = ref({
  name: '',
  email: '',
  username: '',
  password: '',
  role_id: null as number | null,
  send_email: true,
});

const selectedRole = computed(() => props.roles.find((role) => role.id === form.value.role_id) ?? null);
const currentYear = new Date().getFullYear();

function normalizePart(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '.')
    .replace(/\.+/g, '.')
    .replace(/^\./, '')
    .replace(/\.$/, '');
}

function buildName(employee: EmployeeAccountTarget): string {
  const byFullName = (employee.full_name ?? '').trim();
  if (byFullName) return byFullName;
  return `${employee.first_name ?? ''} ${employee.last_name ?? ''}`.trim();
}

function generateUsername(employee: EmployeeAccountTarget): string {
  const fullName = buildName(employee);
  const fromFullName = normalizePart(fullName);
  if (fromFullName) return fromFullName;

  const first = normalizePart(employee.first_name ?? '');
  const last = normalizePart(employee.last_name ?? '');
  if (first && last) return `${first}.${last}`;
  if (first) return first;
  return `user.${Date.now().toString().slice(-4)}`;
}

function generatePassword(employee: EmployeeAccountTarget): string {
  const rawLast = (employee.last_name ?? employee.first_name ?? 'Welcome')
    .replace(/[^A-Za-z0-9]/g, '');
  const base = rawLast ? rawLast.charAt(0).toUpperCase() + rawLast.slice(1).toLowerCase() : 'Welcome';
  return `${base}@${currentYear}`;
}

function setDefaults() {
  const employee = props.employee;
  if (!employee) return;

  form.value.name = buildName(employee);
  form.value.email = (employee.work_email || employee.personal_email || '').trim();
  form.value.username = generateUsername(employee);
  form.value.password = generatePassword(employee);
  form.value.send_email = true;

  const defaultRole = props.roles.find((role) => role.name === 'Employee');
  form.value.role_id = defaultRole?.id ?? props.roles[0]?.id ?? null;
}

function close() {
  emit('update:modelValue', false);
}

function copyPassword() {
  if (!form.value.password) return;
  navigator.clipboard?.writeText(form.value.password);
}

function submit() {
  if (!form.value.name || !form.value.email || !form.value.password || !form.value.role_id) {
    return;
  }

  emit('create', {
    name: form.value.name,
    email: form.value.email.trim().toLowerCase(),
    username: form.value.username.trim(),
    password: form.value.password,
    role_id: form.value.role_id,
    send_email: form.value.send_email,
  });
}

watch(
  () => [props.modelValue, props.employee?.id, props.roles.length] as const,
  ([isOpen]) => {
    if (isOpen) setDefaults();
  },
  { immediate: true }
);
</script>

<template>
  <v-dialog :model-value="modelValue" max-width="620" persistent @update:model-value="emit('update:modelValue', $event)">
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-2">
        <div class="text-h6">Create Portal Account</div>
        <div class="text-caption text-medium-emphasis mt-1">
          for {{ form.name || 'Employee' }}
        </div>
      </v-card-title>

      <v-card-text class="pa-6">
        <v-text-field
          v-model="form.name"
          label="Full Name"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          readonly
        />

        <v-text-field
          v-model="form.email"
          label="Email Address"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          type="email"
          hint="This email will be used for login."
          persistent-hint
        />

        <v-text-field
          v-model="form.username"
          label="Username (display identifier)"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          hint="Login still uses email + password."
          persistent-hint
        />

        <v-text-field
          v-model="form.password"
          :type="showPassword ? 'text' : 'password'"
          label="Temporary Password"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          :append-inner-icon="showPassword ? 'mdi-eye-off' : 'mdi-eye'"
          @click:append-inner="showPassword = !showPassword"
        />

        <v-card class="mb-4" color="warning" variant="tonal" rounded="lg">
          <v-card-text class="d-flex align-center justify-space-between ga-2">
            <div>
              <div class="text-caption text-medium-emphasis">Share this temporary password with staff</div>
              <div class="text-subtitle-2 font-weight-bold">{{ form.password || '-' }}</div>
            </div>
            <v-btn size="small" variant="outlined" color="warning" prepend-icon="mdi-content-copy" @click="copyPassword">
              Copy
            </v-btn>
          </v-card-text>
        </v-card>

        <v-select
          v-model="form.role_id"
          label="Assign Role"
          variant="outlined"
          density="comfortable"
          :items="roles"
          item-title="name"
          item-value="id"
          class="mb-3"
        >
          <template #item="{ props: itemProps, item }">
            <v-list-item v-bind="itemProps">
              <template #prepend>
                <span
                  class="mr-3"
                  :style="{
                    width: '10px',
                    height: '10px',
                    borderRadius: '50%',
                    display: 'inline-block',
                    background: item.raw.color || '#4f6ef7',
                  }"
                />
              </template>
            </v-list-item>
          </template>
        </v-select>

        <v-switch
          v-model="form.send_email"
          color="primary"
          label="Send Welcome Email"
          hide-details
          density="compact"
          class="mb-1"
        />
        <div class="text-caption text-medium-emphasis">
          Credentials will be emailed to {{ form.email || 'the employee' }}.
        </div>
      </v-card-text>

      <v-card-actions class="pa-6 pt-2">
        <v-btn variant="text" @click="close">Cancel</v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          :loading="submitting"
          :disabled="!form.name || !form.email || !form.password || !selectedRole"
          @click="submit"
        >
          Create Account
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

