<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();
const isOpen = ref(false);
const loading = ref(false);
const inputMsg = ref('');
const chatBody = ref<HTMLElement | null>(null);
const seenMessageCount = ref(0);

interface Action {
  label: string
  route: string
  icon: string
  keywords?: string[]
}

interface Message {
  role: 'user' | 'assistant'
  content: string
  timestamp: string
  actions?: Action[]
}

const authUser = computed<any>(() => (page.props as any)?.auth?.user ?? null);
const messages = ref<Message[]>([]);

const storageKey = computed(() => `hr-chatbot:${authUser.value?.id ?? 'guest'}`);
const unreadCount = computed(() => {
  if (isOpen.value) return 0;
  return Math.max(messages.value.length - seenMessageCount.value, 0);
});

const permissionSet = computed<Set<string>>(() => {
  const permissions = Array.isArray(authUser.value?.permissions)
    ? authUser.value.permissions
    : [];

  return new Set(
    permissions
      .map((permission: unknown) => String(permission || '').trim().toLowerCase())
      .filter(Boolean)
  );
});

function hasRole(roleName: string): boolean {
  const roles = Array.isArray(authUser.value?.roles) ? authUser.value.roles : [];
  const target = roleName.trim().toLowerCase();
  return roles.some((role: unknown) => String(role || '').trim().toLowerCase() === target);
}

function isAdmin(): boolean {
  return authUser.value?.is_admin === true || hasRole('HR Admin') || hasRole('super-admin');
}

function canAny(...permissions: string[]): boolean {
  if (isAdmin()) return true;
  return permissions.some((permission) => permissionSet.value.has(permission.toLowerCase()));
}

function getNavigationActions(): Action[] {
  const actions: Action[] = [
    {
      label: 'Dashboard',
      route: '/hr/dashboard',
      icon: 'mdi-view-dashboard',
      keywords: ['dashboard', 'home', 'main page'],
    },
  ];

  if (canAny('view leave', 'view leave requests')) {
    actions.push({
      label: 'Leave Management',
      route: '/hr/leave-management',
      icon: 'mdi-calendar-clock',
      keywords: ['leave', 'leave management', 'time off'],
    });
  }

  if (canAny('view payroll', 'view payslips')) {
    actions.push({
      label: 'Payroll',
      route: '/hr/payroll',
      icon: 'mdi-cash-multiple',
      keywords: ['payroll', 'payslip', 'payslips', 'salary'],
    });
  }

  if (canAny('view employees')) {
    actions.push({
      label: 'Employees',
      route: '/hr/employees',
      icon: 'mdi-account-group',
      keywords: ['employee', 'employees', 'staff', 'worker', 'workers'],
    });
  }

  if (canAny('view attendance')) {
    actions.push({
      label: 'Attendance',
      route: '/hr/attendance',
      icon: 'mdi-calendar-check',
      keywords: ['attendance', 'time', 'timesheet'],
    });
  }

  if (canAny('view job openings', 'view applicants', 'view recruitment')) {
    actions.push({
      label: 'Recruitment',
      route: '/hr/job-openings',
      icon: 'mdi-briefcase',
      keywords: ['recruitment', 'jobs', 'job openings', 'hiring', 'applicants'],
    });
  }

  if (canAny('view reports')) {
    actions.push({
      label: 'Reports',
      route: '/hr/reports',
      icon: 'mdi-chart-bar',
      keywords: ['reports', 'report', 'analytics'],
    });
  }

  if (canAny('view expenses')) {
    actions.push({
      label: 'Expenses',
      route: '/hr/expenses',
      icon: 'mdi-receipt-text',
      keywords: ['expenses', 'expense', 'claims'],
    });
  }

  if (canAny('view messages', 'send messages')) {
    actions.push({
      label: 'Messages',
      route: '/hr/messages',
      icon: 'mdi-email-outline',
      keywords: ['messages', 'message', 'inbox'],
    });
  }

  return actions;
}

function getQuickActions(): Action[] {
  return getNavigationActions().slice(0, 3);
}

const navigationActions = computed<Action[]>(() => getNavigationActions());
const quickSuggestions = computed<string[]>(() => navigationActions.value.slice(0, 4).map((action) => action.label));

function now(): string {
  return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function defaultWelcomeMessage(): Message {
  const firstName = authUser.value?.name?.split(' ')?.[0] ?? 'there';
  return {
    role: 'assistant',
    content: `Hi ${firstName}! I am your HR Assistant. Type a page name like Payroll or Employees and I will take you there.`,
    timestamp: now(),
    actions: getQuickActions(),
  };
}

function hydrateFromSession() {
  try {
    const raw = window.sessionStorage.getItem(storageKey.value);
    if (!raw) return;

    const parsed = JSON.parse(raw) as { messages?: Message[]; seen?: number } | null;
    if (!parsed) return;

    messages.value = Array.isArray(parsed.messages) ? parsed.messages : [];
    seenMessageCount.value = typeof parsed.seen === 'number' ? parsed.seen : 0;
  } catch {
    messages.value = [];
  }
}

function persistToSession() {
  try {
    window.sessionStorage.setItem(
      storageKey.value,
      JSON.stringify({
        messages: messages.value,
        seen: seenMessageCount.value,
      })
    );
  } catch {
    // Ignore storage failures.
  }
}

function openChat() {
  isOpen.value = true;

  if (messages.value.length === 0) {
    messages.value.push(defaultWelcomeMessage());
  }

  seenMessageCount.value = messages.value.length;
  nextTick(() => scrollToBottom());
}

function closeChat() {
  isOpen.value = false;
  seenMessageCount.value = messages.value.length;
}

function clearChat() {
  messages.value = [defaultWelcomeMessage()];
  seenMessageCount.value = messages.value.length;
  nextTick(() => scrollToBottom());
}

function scrollToBottom() {
  if (chatBody.value) {
    chatBody.value.scrollTop = chatBody.value.scrollHeight;
  }
}

function navigateTo(route: string) {
  router.visit(route);
  closeChat();
}

function askSuggestion(suggestion: string) {
  if (loading.value) return;
  inputMsg.value = suggestion;
  sendMessage();
}

function normalizeText(text: string): string {
  return text
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function findNavigationTarget(message: string): Action | null {
  const normalizedMessage = normalizeText(message);
  if (!normalizedMessage) return null;

  for (const action of navigationActions.value) {
    const keywords = [action.label, ...(action.keywords ?? [])].map(normalizeText);
    if (keywords.some((keyword) => keyword && (normalizedMessage === keyword || normalizedMessage.includes(keyword)))) {
      return action;
    }
  }

  return null;
}

function escapeHtml(text: string): string {
  return text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatMessage(text: string): string {
  const safe = escapeHtml(text ?? '');
  return safe
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\n/g, '<br>');
}

async function sendMessage() {
  const msg = inputMsg.value.trim();
  if (!msg || loading.value) return;

  messages.value.push({
    role: 'user',
    content: msg,
    timestamp: now(),
  });

  inputMsg.value = '';
  loading.value = true;
  nextTick(() => scrollToBottom());

  try {
    const target = findNavigationTarget(msg);

    if (target) {
      messages.value.push({
        role: 'assistant',
        content: `Opening ${target.label}.`,
        timestamp: now(),
        actions: [target],
      });

      await nextTick();
      navigateTo(target.route);
      return;
    }

    const availablePages = navigationActions.value.map((action) => action.label).join(', ');
    messages.value.push({
      role: 'assistant',
      content: `I can open pages for you. Try ${availablePages}.`,
      timestamp: now(),
      actions: getQuickActions(),
    });
  } catch {
    messages.value.push({
      role: 'assistant',
      content: 'Sorry, I could not open that page right now. Please try again.',
      timestamp: now(),
    });
  } finally {
    loading.value = false;
    nextTick(() => scrollToBottom());
  }
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault();
    sendMessage();
  }
}

onMounted(() => {
  hydrateFromSession();
});

watch(storageKey, () => {
  hydrateFromSession();
});

watch(
  [messages, seenMessageCount, storageKey],
  () => {
    persistToSession();
  },
  { deep: true }
);
</script>

<template>
  <div class="chatbot-wrapper">
    <v-card
      v-if="isOpen"
      class="chatbot-window"
      elevation="8"
      rounded="xl"
    >
      <div class="chatbot-header d-flex align-center pa-3">
        <v-avatar
          size="32"
          color="white"
          class="mr-2"
        >
          <v-icon
            color="primary"
            size="20"
          >
            mdi-robot
          </v-icon>
        </v-avatar>
        <div class="flex-1">
          <div class="text-subtitle-2 font-weight-bold text-white">
            HR Assistant
          </div>
          <div
            class="text-caption text-white"
            style="opacity: 0.8"
          >
            Always here to help
          </div>
        </div>
        <v-btn
          icon
          size="small"
          variant="text"
          color="white"
          @click="clearChat"
        >
          <v-icon size="16">
            mdi-refresh
          </v-icon>
        </v-btn>
        <v-btn
          icon
          size="small"
          variant="text"
          color="white"
          @click="closeChat"
        >
          <v-icon size="16">
            mdi-close
          </v-icon>
        </v-btn>
      </div>

      <div
        ref="chatBody"
        class="chatbot-body pa-3"
      >
        <div
          v-for="(msg, index) in messages"
          :key="index"
          class="mb-3"
        >
          <div
            v-if="msg.role === 'assistant'"
            class="d-flex align-start gap-2"
          >
            <v-avatar
              size="28"
              color="primary"
            >
              <v-icon
                size="16"
                color="white"
              >
                mdi-robot
              </v-icon>
            </v-avatar>
            <div class="flex-1">
              <v-card
                color="grey-lighten-4"
                flat
                rounded="lg"
                class="pa-3"
              >
                <div
                  class="text-body-2"
                  style="white-space: pre-wrap; line-height: 1.6"
                  v-html="formatMessage(msg.content)"
                >
                </div>
              </v-card>

              <div
                v-if="msg.actions?.length"
                class="d-flex flex-wrap gap-1 mt-2"
              >
                <v-btn
                  v-for="action in msg.actions"
                  :key="`${action.route}-${action.label}`"
                  size="x-small"
                  color="primary"
                  variant="tonal"
                  :prepend-icon="action.icon"
                  rounded="lg"
                  @click="navigateTo(action.route)"
                >
                  {{ action.label }}
                </v-btn>
              </div>

              <div class="text-caption text-medium-emphasis mt-1">
                {{ msg.timestamp }}
              </div>
            </div>
          </div>

          <div
            v-else
            class="d-flex justify-end"
          >
            <div>
              <v-card
                color="primary"
                flat
                rounded="lg"
                class="pa-3"
              >
                <div
                  class="text-body-2 text-white"
                  style="white-space: pre-wrap"
                >
                  {{ msg.content }}
                </div>
              </v-card>
              <div class="text-caption text-medium-emphasis mt-1 text-right">
                {{ msg.timestamp }}
              </div>
            </div>
          </div>
        </div>

        <div
          v-if="messages.length <= 1 && !loading"
          class="px-1 pb-2"
        >
          <div class="text-caption text-medium-emphasis mb-2">
            Quick pages:
          </div>
          <div class="d-flex flex-wrap gap-1">
            <v-chip
              v-for="suggestion in quickSuggestions"
              :key="suggestion"
              size="x-small"
              color="primary"
              variant="outlined"
              clickable
              @click="askSuggestion(suggestion)"
            >
              {{ suggestion }}
            </v-chip>
          </div>
        </div>

        <div
          v-if="loading"
          class="d-flex align-center gap-2"
        >
          <v-avatar
            size="28"
            color="primary"
          >
            <v-icon
              size="16"
              color="white"
            >
              mdi-robot
            </v-icon>
          </v-avatar>
          <v-card
            color="grey-lighten-4"
            flat
            rounded="lg"
            class="pa-3"
          >
            <div class="typing-dots">
              <span></span>
              <span></span>
              <span></span>
            </div>
          </v-card>
        </div>
      </div>

      <div class="chatbot-footer pa-3">
        <v-text-field
          v-model="inputMsg"
          placeholder="Type a page name..."
          variant="outlined"
          density="compact"
          hide-details
          rounded="lg"
          :disabled="loading"
          @keydown="onKeydown"
        >
          <template #append-inner>
            <v-btn
              icon
              size="small"
              color="primary"
              variant="flat"
              :loading="loading"
              :disabled="!inputMsg.trim()"
              @click="sendMessage"
            >
              <v-icon size="18">
                mdi-send
              </v-icon>
            </v-btn>
          </template>
        </v-text-field>
      </div>
    </v-card>

    <v-tooltip
      location="left"
      text="HR Assistant"
    >
      <template #activator="{ props }">
        <v-badge
          :content="unreadCount"
          :model-value="unreadCount > 0"
          color="error"
          offset-x="6"
          offset-y="6"
        >
          <v-btn
            v-bind="props"
            icon
            size="large"
            color="primary"
            elevation="6"
            class="chatbot-fab"
            @click="isOpen ? closeChat() : openChat()"
          >
            <v-icon v-if="!isOpen">
              mdi-robot
            </v-icon>
            <v-icon v-else>
              mdi-chevron-down
            </v-icon>
          </v-btn>
        </v-badge>
      </template>
    </v-tooltip>
  </div>
</template>

<style scoped>
.chatbot-wrapper {
  position: fixed;
  right: 24px;
  bottom: 24px;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 12px;
}

.chatbot-window {
  width: 380px;
  height: 520px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.chatbot-header {
  background: linear-gradient(135deg, #2f66f5, #2563eb);
  flex-shrink: 0;
}

.chatbot-body {
  flex: 1;
  overflow-y: auto;
  scroll-behavior: smooth;
}

.chatbot-footer {
  flex-shrink: 0;
  border-top: 1px solid #f0f0f0;
}

.chatbot-fab {
  border-radius: 50% !important;
  width: 56px !important;
  height: 56px !important;
}

.typing-dots {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 2px 0;
}

.typing-dots span {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #999;
  animation: typing 1.2s infinite;
}

.typing-dots span:nth-child(2) {
  animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes typing {
  0%, 60%, 100% {
    transform: translateY(0);
    opacity: 0.4;
  }
  30% {
    transform: translateY(-6px);
    opacity: 1;
  }
}

@media (max-width: 600px) {
  .chatbot-wrapper {
    right: 12px;
    bottom: 12px;
    left: 12px;
    align-items: stretch;
  }

  .chatbot-window {
    width: 100%;
    height: min(72vh, 560px);
  }

  .chatbot-fab {
    align-self: flex-end;
  }
}
</style>
