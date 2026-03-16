<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';
import SendMessageDialog from '@/components/HR/SendMessageDialog.vue';

type MessageRow = {
  id: number
  thread_id: string
  subject: string
  preview: string
  body: string
  type: string
  status: string
  read: boolean
  sender: {
    id: number
    name: string
    initials: string
  }
  created_at: string
  time: string
};

type ThreadMessage = {
  id: number
  thread_id: string
  subject: string
  body: string
  read: boolean
  mine: boolean
  sender: {
    id: number
    name: string
    initials: string
  }
  created_at: string
};

const breadcrumbs = [
  { title: 'HR Module', disabled: false, href: '#' },
  { title: 'Communications', disabled: false, href: '#' },
  { title: 'Messages', disabled: true, href: '#' },
];

const activeTab = ref<'inbox' | 'sent'>('inbox');
const messages = ref<MessageRow[]>([]);
const selected = ref<MessageRow | null>(null);
const thread = ref<ThreadMessage[]>([]);
const loading = ref(false);
const threadLoading = ref(false);
const unread = ref(0);
const composeDialog = ref(false);
const sendingReply = ref(false);
const replyBody = ref('');
const replyType = ref<'internal' | 'email'>('internal');

const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
});


const canReply = computed(() => Boolean(selected.value?.thread_id));

function showSnack(message: string, color: 'success' | 'error' = 'success') {
  snackbar.value = {
    show: true,
    message,
    color,
  };
}

async function fetchMessages() {
  loading.value = true;
  try {
    const url = activeTab.value === 'inbox'
      ? '/api/hr/messages/inbox'
      : '/api/hr/messages/sent';

    const { data } = await axios.get(url);
    messages.value = data.messages?.data ?? [];
    unread.value = Number(data.unread_count ?? 0);
  } catch {
    showSnack('Failed to load messages.', 'error');
  } finally {
    loading.value = false;
  }
}

async function openMessage(message: MessageRow) {
  selected.value = message;
  threadLoading.value = true;

  try {
    const { data } = await axios.get(`/api/hr/messages/thread/${message.thread_id}`);
    thread.value = data.messages ?? [];

    if (!message.read) {
      await axios.patch(`/api/hr/messages/${message.id}/read`);
      message.read = true;
      unread.value = Math.max(0, unread.value - 1);
    }
  } catch {
    showSnack('Failed to load thread.', 'error');
  } finally {
    threadLoading.value = false;
  }
}





async function replyToThread() {
  if (!selected.value || !replyBody.value.trim()) {
    return;
  }

  sendingReply.value = true;
  try {
    await axios.post(`/api/hr/messages/thread/${selected.value.thread_id}/reply`, {
      body: replyBody.value,
      type: replyType.value,
      subject: selected.value.subject,
    });

    replyBody.value = '';
    showSnack('Reply sent.');
    await openMessage(selected.value);
    await fetchMessages();
  } catch {
    showSnack('Failed to send reply.', 'error');
  } finally {
    sendingReply.value = false;
  }
}

watch(activeTab, async () => {
  selected.value = null;
  thread.value = [];
  await fetchMessages();
});

onMounted(async () => {
  await fetchMessages();
});
</script>

<template>
  <BaseBreadcrumb
    title="Messages"
    subtitle="Internal HR communications"
    :breadcrumbs="breadcrumbs"
  />

  <div class="d-flex align-center justify-space-between mb-4">
    <div>
      <h2 class="text-h3 mb-1">Messages</h2>
      <p class="text-subtitle-1 text-lightText mb-0">
        Staff and HR internal inbox.
      </p>
    </div>

    <div class="d-flex ga-2">
      <v-btn
        variant="tonal"
        color="primary"
        prepend-icon="mdi-account-group-outline"
        @click="composeDialog = true"
      >
        Bulk Message
      </v-btn>
      <v-btn
        color="primary"
        variant="flat"
        prepend-icon="mdi-pencil"
        @click="composeDialog = true"
      >
        Compose
      </v-btn>
    </div>
  </div>

  <v-row>
    <v-col cols="12" md="4">
      <v-card variant="outlined" class="rounded-lg">
        <v-tabs v-model="activeTab" color="primary" density="compact">
          <v-tab value="inbox">
            <v-badge
              :content="unread"
              :model-value="unread > 0"
              color="error"
              inline
            >
              Inbox
            </v-badge>
          </v-tab>
          <v-tab value="sent">Sent</v-tab>
        </v-tabs>

        <v-divider />

        <v-skeleton-loader
          v-if="loading"
          type="list-item-avatar-two-line,list-item-avatar-two-line,list-item-avatar-two-line"
        />

        <v-list v-else lines="two" density="compact" class="py-1">
          <v-list-item
            v-for="message in messages"
            :key="message.id"
            :active="selected?.id === message.id"
            active-color="primary"
            rounded="lg"
            class="mx-1 my-1"
            @click="openMessage(message)"
          >
            <template #prepend>
              <v-avatar size="34" color="primary" class="mr-2">
                <span class="text-white text-caption font-weight-bold">
                  {{ message.sender.initials }}
                </span>
              </v-avatar>
            </template>

            <v-list-item-title :class="!message.read ? 'font-weight-bold' : ''">
              {{ message.sender.name }}
            </v-list-item-title>
            <v-list-item-subtitle>
              {{ message.preview }}
            </v-list-item-subtitle>

            <template #append>
              <div class="d-flex flex-column align-end">
                <span class="text-caption text-medium-emphasis">
                  {{ message.time }}
                </span>
                <v-icon
                  v-if="!message.read && activeTab === 'inbox'"
                  size="8"
                  color="primary"
                  class="mt-1"
                >
                  mdi-circle
                </v-icon>
              </div>
            </template>
          </v-list-item>

          <div
            v-if="messages.length === 0"
            class="text-center py-10 text-medium-emphasis"
          >
            <v-icon size="44" color="grey-lighten-2">
              mdi-inbox-outline
            </v-icon>
            <div class="mt-2">No messages</div>
          </div>
        </v-list>
      </v-card>
    </v-col>

    <v-col cols="12" md="8">
      <v-card variant="outlined" class="rounded-lg" style="min-height: 560px">
        <div v-if="!selected" class="d-flex flex-column align-center justify-center" style="height: 560px">
          <v-icon size="64" color="grey-lighten-2">mdi-message-outline</v-icon>
          <div class="text-body-1 text-medium-emphasis mt-3">
            Select a message to read
          </div>
        </div>

        <template v-else>
          <div class="pa-4 border-b">
            <div class="text-subtitle-1 font-weight-bold">
              {{ selected.subject }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              {{ selected.created_at }}
            </div>
          </div>

          <v-skeleton-loader v-if="threadLoading" type="article,article" class="pa-4" />

          <div v-else class="pa-4" style="max-height: 360px; overflow-y: auto">
            <div v-for="item in thread" :key="item.id" class="mb-4">
              <div :class="item.mine ? 'd-flex justify-end' : 'd-flex justify-start'">
                <div
                  :style="{
                    maxWidth: '72%',
                    background: item.mine ? '#4f6ef7' : '#f5f6f9',
                    color: item.mine ? '#fff' : '#111',
                    borderRadius: '12px',
                    padding: '12px 14px',
                  }"
                >
                  <div v-if="!item.mine" class="text-caption font-weight-bold mb-1">
                    {{ item.sender.name }}
                  </div>
                  <div class="text-body-2" style="white-space: pre-wrap">
                    {{ item.body }}
                  </div>
                  <div class="text-caption mt-1" :style="{ opacity: 0.75 }">
                    {{ item.created_at }}
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="pa-4 border-t">
            <v-row class="mb-2">
              <v-col cols="12" md="4">
                <v-select
                  v-model="replyType"
                  :items="[
                    { title: 'Internal Reply', value: 'internal' },
                    { title: 'Email Reply', value: 'email' },
                  ]"
                  label="Reply Type"
                  density="compact"
                  variant="outlined"
                  hide-details
                />
              </v-col>
            </v-row>

            <v-textarea
              v-model="replyBody"
              placeholder="Type a reply..."
              variant="outlined"
              density="compact"
              rows="2"
              auto-grow
              hide-details
            />

            <div class="d-flex justify-end mt-2">
              <v-btn
                color="primary"
                variant="flat"
                size="small"
                prepend-icon="mdi-send"
                :loading="sendingReply"
                :disabled="!canReply || !replyBody.trim()"
                @click="replyToThread"
              >
                Reply
              </v-btn>
            </div>
          </div>
        </template>
      </v-card>
    </v-col>
  </v-row>

  <SendMessageDialog
    v-model="composeDialog"
    default-category="general"
    @sent="fetchMessages"
  />

  <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
    {{ snackbar.message }}
  </v-snackbar>
</template>



