<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps<{
  modelValue: boolean
  recipientType?: string
  recipientId?: number | null
  recipientName?: string
  recipientEmail?: string
  defaultCategory?: string
}>();

const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'sent'): void
}>();

const sending = ref(false);
const searchQuery = ref('');
const searchResults = ref<any[]>([]);
const searching = ref(false);
const templates = ref<any[]>([]);
const snackbar = ref({
  show: false,
  text: '',
  color: 'success',
});

const form = ref({
  recipients: [] as any[],
  subject: '',
  body: '',
  type: 'internal',
});

const selectedTemplate = ref<any>(null);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function searchRecipients(query: string) {
  if (!query || query.length < 2) {
    searchResults.value = [];
    return;
  }

  searching.value = true;
  try {
    const { data } = await axios.get('/api/hr/messages/recipients', {
      params: { q: query },
    });
    searchResults.value = data.recipients ?? data ?? [];
  } catch (error) {
    console.error(error);
  } finally {
    searching.value = false;
  }
}

function onSearchInput(val: string) {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  searchTimeout = setTimeout(() => {
    searchRecipients(val);
  }, 300);
}

function addRecipient(person: any) {
  const exists = form.value.recipients.find((recipient) => (
    recipient.id === person.id && recipient.type === person.type
  ));

  if (!exists) {
    form.value.recipients.push(person);
  }

  searchQuery.value = '';
  searchResults.value = [];
}

function removeRecipient(index: number) {
  form.value.recipients.splice(index, 1);
}

async function addDepartment(department: any) {
  try {
    const { data } = await axios.get('/api/hr/messages/recipients', {
      params: { department_id: department.id },
    });

    const employees = data.recipients ?? [];
    employees.forEach((employee: any) => {
      const exists = form.value.recipients.find((recipient) => recipient.id === employee.id);
      if (!exists) {
        form.value.recipients.push(employee);
      }
    });

    showSnack(
      `${department.name} team added (${employees.length} members)`,
      'success',
    );
  } catch {
    showSnack('Failed to add team', 'error');
  }

  searchQuery.value = '';
  searchResults.value = [];
}

async function fetchTemplates() {
  try {
    const { data } = await axios.get('/api/hr/messages/templates');

    if (Array.isArray(data?.templates)) {
      templates.value = data.templates;
      return;
    }

    templates.value = Object.values(data?.templates ?? data ?? {})
      .flat() as any[];
  } catch (error) {
    console.error(error);
  }
}

function applyTemplate(template: any) {
  if (!template) {
    return;
  }

  form.value.subject = template.subject ?? '';
  form.value.body = template.body ?? '';
  selectedTemplate.value = template;
}

async function sendMessage() {
  if (form.value.recipients.length === 0 || !form.value.body.trim()) {
    showSnack('Please add a recipient and write a message.', 'error');
    return;
  }

  sending.value = true;
  try {
    const requests = form.value.recipients.map((recipient) => axios.post('/api/hr/messages/send', {
      recipient_type: recipient.type ?? 'employee',
      recipient_id: recipient.id,
      recipient_email: recipient.email,
      subject: form.value.subject,
      body: form.value.body,
      type: form.value.type,
    }));

    await Promise.all(requests);

    const count = form.value.recipients.length;
    showSnack(
      `Message sent to ${count} recipient${count > 1 ? 's' : ''} successfully!`,
      'success',
    );

    emit('sent');

    setTimeout(() => {
      closeDialog();
    }, 1500);
  } catch (error: any) {
    showSnack(
      error?.response?.data?.message ?? 'Failed to send message.',
      'error',
    );
  } finally {
    sending.value = false;
  }
}

function showSnack(text: string, color = 'success') {
  snackbar.value = {
    show: true,
    text,
    color,
  };
}

function closeDialog() {
  emit('update:modelValue', false);
  form.value = {
    recipients: [],
    subject: '',
    body: '',
    type: 'internal',
  };
  searchQuery.value = '';
  searchResults.value = [];
  selectedTemplate.value = null;
}

const canSend = computed(() => (
  form.value.recipients.length > 0
  && form.value.body.trim().length > 0
  && !sending.value
));

function buildInitialRecipient() {
  if (!props.recipientId || !props.recipientName) {
    return null;
  }

  const initials = props.recipientName
    .split(' ')
    .filter(Boolean)
    .map((word) => word[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);

  return {
    id: props.recipientId,
    name: props.recipientName,
    email: props.recipientEmail ?? '',
    type: props.recipientType ?? 'employee',
    initials,
  };
}

onMounted(() => {
  fetchTemplates();

  const initial = buildInitialRecipient();
  if (initial) {
    form.value.recipients = [initial];
  }
});

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      return;
    }

    if (form.value.recipients.length === 0) {
      const initial = buildInitialRecipient();
      if (initial) {
        form.value.recipients = [initial];
      }
    }
  },
);
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="620"
    persistent
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <v-card rounded="xl">
      <v-card-title class="pa-5 pb-2">
        <v-icon start color="primary">
          mdi-message-text
        </v-icon>
        Send Message
      </v-card-title>

      <v-card-text class="pa-5">
        <div class="mb-4">
          <div class="text-body-2 font-weight-medium mb-2">
            To <span class="text-error">*</span>
          </div>

          <div
            v-if="form.recipients.length > 0"
            class="d-flex flex-wrap ga-1 mb-2"
          >
            <v-chip
              v-for="(recipient, index) in form.recipients"
              :key="`${recipient.type}-${recipient.id}-${index}`"
              size="small"
              color="primary"
              variant="tonal"
              closable
              @click:close="removeRecipient(index)"
            >
              <template #prepend>
                <v-avatar
                  size="20"
                  color="primary"
                  class="mr-1"
                >
                  <span class="text-white" style="font-size: 9px">
                    {{ recipient.initials ?? recipient.name?.slice(0, 2)?.toUpperCase() }}
                  </span>
                </v-avatar>
              </template>
              {{ recipient.name }}
            </v-chip>
          </div>

          <v-text-field
            v-model="searchQuery"
            placeholder="Search employees or departments..."
            variant="outlined"
            density="comfortable"
            hide-details
            prepend-inner-icon="mdi-account-search"
            :loading="searching"
            @input="onSearchInput(searchQuery)"
          />

          <v-card
            v-if="searchResults.length > 0"
            flat
            border
            rounded="lg"
            class="mt-1"
            style="max-height: 200px; overflow-y: auto"
          >
            <v-list density="compact" nav>
              <v-list-item
                v-for="result in searchResults"
                :key="`${result.id}-${result.type}`"
                rounded="lg"
                @click="result.type === 'department' ? addDepartment(result) : addRecipient(result)"
              >
                <template #prepend>
                  <v-avatar
                    size="32"
                    :color="result.type === 'department' ? 'orange' : 'primary'"
                    variant="tonal"
                    class="mr-2"
                  >
                    <v-icon
                      v-if="result.type === 'department'"
                      size="16"
                    >
                      mdi-office-building
                    </v-icon>
                    <span v-else class="text-caption">
                      {{ result.initials ?? result.name?.slice(0, 2)?.toUpperCase() }}
                    </span>
                  </v-avatar>
                </template>

                <v-list-item-title class="text-body-2">
                  {{ result.name }}
                  <v-chip
                    v-if="result.type === 'department'"
                    size="x-small"
                    color="orange"
                    variant="tonal"
                    class="ml-1"
                  >
                    Department
                  </v-chip>
                </v-list-item-title>

                <v-list-item-subtitle class="text-caption">
                  {{ result.type === 'department' ? `${result.member_count} members` : (result.designation ?? result.email) }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card>

          <div
            v-else-if="searchQuery.length >= 2 && !searching"
            class="text-caption text-medium-emphasis mt-1 px-1"
          >
            No employees found for "{{ searchQuery }}"
          </div>

          <div class="d-flex ga-1 mt-2">
            <v-chip
              size="x-small"
              color="primary"
              variant="outlined"
              prepend-icon="mdi-account-group"
              clickable
              @click="searchRecipients('all'); searchQuery = 'all'"
            >
              Search all
            </v-chip>
          </div>
        </div>

        <v-select
          v-model="selectedTemplate"
          label="Template (optional)"
          :items="templates"
          item-title="name"
          item-value="id"
          return-object
          variant="outlined"
          density="comfortable"
          clearable
          class="mb-3"
          @update:model-value="applyTemplate(selectedTemplate)"
        >
          <template #item="{ props: slotProps, item }">
            <v-list-item v-bind="slotProps">
              <template #subtitle>
                {{ item.raw.category }}
              </template>
            </v-list-item>
          </template>
        </v-select>

        <v-btn-toggle
          v-model="form.type"
          color="primary"
          variant="outlined"
          density="comfortable"
          divided
          class="mb-4 w-100"
        >
          <v-btn value="internal" style="flex: 1">
            <v-icon start size="16">
              mdi-account-group
            </v-icon>
            Internal Only
          </v-btn>
          <v-btn value="email" style="flex: 1">
            <v-icon start size="16">
              mdi-email
            </v-icon>
            Send Email
          </v-btn>
          <v-btn value="both" style="flex: 1">
            <v-icon start size="16">
              mdi-bell-ring
            </v-icon>
            Both
          </v-btn>
        </v-btn-toggle>

        <v-alert
          density="compact"
          variant="tonal"
          :color="form.type === 'internal' ? 'info' : (form.type === 'email' ? 'warning' : 'success')"
          class="mb-4 text-caption"
        >
          <span v-if="form.type === 'internal'">
            Message goes to in-app inbox only. No email is sent.
          </span>
          <span v-else-if="form.type === 'email'">
            Sends a real email to recipient email addresses.
          </span>
          <span v-else>
            Sends both an in-app message and a real email.
          </span>
        </v-alert>

        <v-text-field
          v-model="form.subject"
          label="Subject"
          variant="outlined"
          density="comfortable"
          class="mb-3"
          placeholder="e.g. Leave Policy Update"
        />

        <v-textarea
          v-model="form.body"
          label="Message *"
          variant="outlined"
          density="comfortable"
          rows="5"
          counter="5000"
          maxlength="5000"
          placeholder="Type your message here..."
          :rules="[(value: string) => !!value || 'Message is required']"
        />
      </v-card-text>

      <v-card-actions class="pa-5 pt-0">
        <v-btn
          variant="text"
          @click="closeDialog"
        >
          Cancel
        </v-btn>
        <v-spacer />

        <span
          v-if="form.recipients.length > 0"
          class="text-caption text-medium-emphasis mr-3"
        >
          Sending to {{ form.recipients.length }} person{{ form.recipients.length > 1 ? 's' : '' }}
        </span>

        <v-btn
          color="primary"
          variant="flat"
          :loading="sending"
          :disabled="!canSend"
          prepend-icon="mdi-send"
          @click="sendMessage"
        >
          Send Message
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>

  <v-snackbar
    v-model="snackbar.show"
    :color="snackbar.color"
    timeout="3000"
    location="bottom right"
  >
    {{ snackbar.text }}
  </v-snackbar>
</template>
