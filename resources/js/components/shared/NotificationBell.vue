<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

type NotificationItem = {
  id: string | number
  title?: string
  message?: string
  time?: string
  created_at?: string
  read?: boolean
  link?: string | null
};

type NormalizedNotification = NotificationItem & {
  title: string
  message: string
  read: boolean
  link: string | null
  displayTime: string
};

const props = withDefaults(
  defineProps<{
    notifications: NotificationItem[]
    showClearAll?: boolean
  }>(),
  {
    notifications: () => [],
    showClearAll: true,
  }
);

const emit = defineEmits<{
  markRead: [id: NotificationItem['id']]
  markAllRead: []
  clearAll: []
  open: [notification: NotificationItem]
}>();

const isOpen = ref(false);
const rootEl = ref<HTMLElement | null>(null);
const optimisticReadIds = ref<Set<NotificationItem['id']>>(new Set());
const gradientId = `notification-bell-gradient-${Math.random().toString(36).slice(2, 9)}`;

const normalizedNotifications = computed<NormalizedNotification[]>(
  () =>
    props.notifications.map((notification) => ({
      ...notification,
      title: notification.title?.trim() || 'Notification',
      message: notification.message?.trim() || 'No additional details.',
      read: Boolean(notification.read) || optimisticReadIds.value.has(notification.id),
      link: notification.link ?? null,
      displayTime: formatTime(notification.time, notification.created_at),
    }))
);

const unreadCount = computed(() => normalizedNotifications.value.filter((notification) => !notification.read).length);

function formatTime(time?: string, createdAt?: string): string {
  if (time?.trim()) return time;
  if (!createdAt) return 'Just now';

  const date = new Date(createdAt);
  const now = new Date();
  const diffSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (Number.isNaN(diffSeconds) || diffSeconds < 60) return 'Just now';
  if (diffSeconds < 3600) return `${Math.floor(diffSeconds / 60)}m ago`;
  if (diffSeconds < 86400) return `${Math.floor(diffSeconds / 3600)}h ago`;
  return `${Math.floor(diffSeconds / 86400)}d ago`;
}

function toggleDropdown() {
  isOpen.value = !isOpen.value;
}

function closeDropdown() {
  isOpen.value = false;
}

function markNotificationRead(notification: NotificationItem & { read?: boolean }) {
  if (notification.read || optimisticReadIds.value.has(notification.id)) return;

  optimisticReadIds.value = new Set(optimisticReadIds.value).add(notification.id);
  emit('markRead', notification.id);
}

function handleNotificationClick(notification: NotificationItem & { read?: boolean; link?: string | null }) {
  markNotificationRead(notification);

  if (notification.link) {
    emit('open', notification);
    closeDropdown();
  }
}

function handleMarkAllRead() {
  if (unreadCount.value === 0) return;

  optimisticReadIds.value = new Set(
    normalizedNotifications.value.map((notification) => notification.id)
  );
  emit('markAllRead');
}

function handleClearAll() {
  emit('clearAll');
}

function handleDocumentClick(event: MouseEvent) {
  const target = event.target as Node | null;
  if (!rootEl.value || !target) return;

  if (!rootEl.value.contains(target)) {
    closeDropdown();
  }
}

function handleEscape(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    closeDropdown();
  }
}

watch(
  () => props.notifications,
  () => {
    optimisticReadIds.value = new Set();
  },
  { deep: true }
);

onMounted(() => {
  document.addEventListener('click', handleDocumentClick);
  document.addEventListener('keydown', handleEscape);
});

onUnmounted(() => {
  document.removeEventListener('click', handleDocumentClick);
  document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
  <div ref="rootEl" class="relative inline-flex">
    <button
      type="button"
      class="notification-bell-trigger group relative inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-full border border-white/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.98),rgba(241,245,249,0.92))] shadow-[0_16px_38px_-22px_rgba(15,23,42,0.55)] backdrop-blur-xl transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_20px_42px_-20px_rgba(15,23,42,0.58)] focus:outline-none focus:ring-4 focus:ring-amber-200/70"
      :aria-expanded="isOpen"
      aria-haspopup="true"
      aria-label="Open notifications"
      @click.stop="toggleDropdown"
    >
      <span class="absolute inset-0 bg-[radial-gradient(circle_at_30%_25%,rgba(255,255,255,0.92),transparent_45%),radial-gradient(circle_at_72%_78%,rgba(245,158,11,0.18),transparent_34%)]" />
      <span
        v-if="unreadCount > 0"
        class="absolute right-0.5 top-0.5 z-10 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-bold leading-none text-white ring-2 ring-white shadow-sm"
      >
        {{ unreadCount > 99 ? '99+' : unreadCount }}
      </span>

      <svg
        class="notification-bell-icon relative z-[1] h-6 w-6 drop-shadow-[0_4px_10px_rgba(217,119,6,0.22)]"
        viewBox="0 0 24 24"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
      >
        <defs>
          <linearGradient :id="gradientId" x1="5" y1="4" x2="19" y2="20" gradientUnits="userSpaceOnUse">
            <stop stop-color="#F59E0B" />
            <stop offset="1" stop-color="#D97706" />
          </linearGradient>
        </defs>
        <path
          d="M14.857 18a3 3 0 0 1-5.714 0M18 8.571a6 6 0 1 0-12 0c0 5.143-2.571 6.857-2.571 6.857h17.142S18 13.714 18 8.571Z"
          :stroke="`url(#${gradientId})`"
          stroke-width="1.8"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path
          d="M12 3.25a1.25 1.25 0 1 1 0 2.5a1.25 1.25 0 0 1 0-2.5Z"
          :fill="`url(#${gradientId})`"
        />
      </svg>
    </button>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 scale-95 opacity-0"
      enter-to-class="translate-y-0 scale-100 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 scale-100 opacity-100"
      leave-to-class="translate-y-2 scale-95 opacity-0"
    >
      <div
        v-if="isOpen"
        class="absolute right-0 top-full z-[70] mt-3 w-[25.5rem] max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-[1.75rem] border border-white/80 bg-[linear-gradient(180deg,rgba(255,255,255,0.96),rgba(248,250,252,0.92))] shadow-[0_30px_80px_-30px_rgba(15,23,42,0.5)] backdrop-blur-2xl"
      >
        <div class="h-1.5 bg-[linear-gradient(90deg,#F59E0B,#D97706)]" />

        <div class="relative overflow-hidden border-b border-slate-200/70 px-4 py-4">
          <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(245,158,11,0.16),transparent_34%)]" />
          <div class="relative flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#FEF3C7,#FDE68A)] shadow-inner">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <path
                    d="M14.857 18a3 3 0 0 1-5.714 0M18 8.571a6 6 0 1 0-12 0c0 5.143-2.571 6.857-2.571 6.857h17.142S18 13.714 18 8.571Z"
                    :stroke="`url(#${gradientId})`"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                  />
                  <path
                    d="M12 3.25a1.25 1.25 0 1 1 0 2.5a1.25 1.25 0 0 1 0-2.5Z"
                    :fill="`url(#${gradientId})`"
                  />
                </svg>
              </div>
              <div>
                <p class="text-sm font-semibold tracking-tight text-slate-900">Notifications</p>
                <p class="text-xs text-slate-500">{{ unreadCount }} unread messages</p>
              </div>
            </div>
            <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
              {{ normalizedNotifications.length }}
            </span>
          </div>
          <div class="relative mt-3 flex items-center gap-2">
            <button
              type="button"
              class="rounded-full bg-[linear-gradient(135deg,#FEF3C7,#FDE68A)] px-3.5 py-1.5 text-xs font-semibold text-amber-900 shadow-sm transition hover:brightness-105 disabled:cursor-not-allowed disabled:opacity-50"
              :disabled="unreadCount === 0"
              @click.stop="handleMarkAllRead"
            >
              Mark all read
            </button>
            <button
              v-if="showClearAll && normalizedNotifications.length > 0"
              type="button"
              class="rounded-full border border-slate-200 bg-white/80 px-3.5 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-50"
              @click.stop="handleClearAll"
            >
              Clear all
            </button>
          </div>
        </div>

        <div v-if="normalizedNotifications.length > 0" class="max-h-96 overflow-y-auto px-3 py-3">
          <button
            v-for="notification in normalizedNotifications"
            :key="notification.id"
            type="button"
            class="mb-2 flex w-full items-start gap-3 rounded-[1.25rem] border px-3.5 py-3.5 text-left transition last:mb-0 hover:-translate-y-0.5 hover:shadow-md"
            :class="notification.read ? 'border-transparent bg-white/70 hover:bg-white' : 'border-amber-100 bg-[linear-gradient(135deg,rgba(255,251,235,0.96),rgba(255,255,255,0.96))] shadow-[0_10px_22px_-18px_rgba(217,119,6,0.45)]'"
            @click.stop="handleNotificationClick(notification)"
          >
            <span
              class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-xs font-bold"
              :class="notification.read ? 'bg-slate-100 text-slate-500' : 'bg-[linear-gradient(135deg,#FDE68A,#F59E0B)] text-amber-950'"
            >
              {{ notification.title.charAt(0).toUpperCase() }}
            </span>
            <span class="min-w-0 flex-1">
              <span class="flex items-start justify-between gap-3">
                <span class="min-w-0">
                  <span class="flex items-center gap-2">
                    <span
                      class="h-2.5 w-2.5 shrink-0 rounded-full"
                      :class="notification.read ? 'bg-slate-300' : 'bg-red-500'"
                    />
                    <span class="truncate text-sm font-semibold tracking-tight text-slate-900">
                      {{ notification.title }}
                    </span>
                  </span>
                  <span class="mt-1 line-clamp-2 block text-sm leading-5 text-slate-600">
                    {{ notification.message }}
                  </span>
                </span>
                <span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">
                  {{ notification.displayTime }}
                </span>
              </span>
              <span
                v-if="notification.link"
                class="mt-2 inline-flex items-center gap-1 rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white"
              >
                Open
                <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                  <path fill-rule="evenodd" d="M5.22 14.78a.75.75 0 0 0 1.06 0L12 9.06v4.19a.75.75 0 0 0 1.5 0V7.25a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0 0 1.5h4.19l-5.72 5.72a.75.75 0 0 0 0 1.06Z" clip-rule="evenodd" />
                </svg>
              </span>
            </span>
          </button>
        </div>

        <div v-else class="px-5 py-10 text-center">
          <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-[1.25rem] bg-[linear-gradient(135deg,#FEF3C7,#FFF7ED)] text-amber-500 shadow-inner">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path d="M14.857 18a3 3 0 0 1-5.714 0M18 8.571a6 6 0 1 0-12 0c0 5.143-2.571 6.857-2.571 6.857h17.142S18 13.714 18 8.571Z" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <p class="text-sm font-semibold text-slate-800">No notifications yet</p>
          <p class="mt-1 text-xs leading-5 text-slate-500">You are all caught up for now. New alerts and messages will show up here.</p>
        </div>
      </div>
    </transition>
  </div>
</template>

<style scoped>
.notification-bell-trigger:hover .notification-bell-icon {
  animation: bell-ring 0.8s ease-in-out;
  transform-origin: top center;
}

@keyframes bell-ring {
  0%,
  100% {
    transform: rotate(0deg);
  }
  15% {
    transform: rotate(14deg);
  }
  30% {
    transform: rotate(-12deg);
  }
  45% {
    transform: rotate(10deg);
  }
  60% {
    transform: rotate(-8deg);
  }
  75% {
    transform: rotate(5deg);
  }
}
</style>
