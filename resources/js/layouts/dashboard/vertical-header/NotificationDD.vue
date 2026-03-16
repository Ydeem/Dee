<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import NotificationBell from '@/components/shared/NotificationBell.vue';

type NavbarNotification = {
  id: string | number
  message?: string
  title?: string
  link?: string | null
  read?: boolean
  created_at?: string
  time?: string
};

const notifications = ref<NavbarNotification[]>([]);

async function fetchNotifications() {
  try {
    const { data } = await axios.get('/api/notifications');
    notifications.value = data.notifications ?? data ?? [];
  } catch (error) {
    console.error('Failed to load notifications', error);
  }
}

async function handleMarkRead(id: string | number) {
  try {
    await axios.patch(`/api/notifications/${id}/read`);
    const notification = notifications.value.find((item) => item.id === id);
    if (notification) {
      notification.read = true;
    }
    router.reload({ only: ['auth'] });
  } catch (error) {
    console.error(error);
  }
}

async function handleMarkAllRead() {
  try {
    await axios.post('/api/notifications/mark-all-read');
    notifications.value = notifications.value.map((notification) => ({
      ...notification,
      read: true,
    }));
    router.reload({ only: ['auth'] });
  } catch (error) {
    console.error(error);
  }
}

async function handleClearAll() {
  try {
    await axios.delete('/api/notifications');
    notifications.value = [];
    router.reload({ only: ['auth'] });
  } catch (error) {
    console.error(error);
  }
}

async function handleOpen(notification: NavbarNotification) {
  await handleMarkRead(notification.id);

  if (notification.link) {
    router.visit(notification.link);
  }
}

let notifInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  fetchNotifications();
  notifInterval = setInterval(() => {
    fetchNotifications();
    router.reload({ only: ['auth'] });
  }, 60000);
});

onUnmounted(() => {
  if (notifInterval) {
    clearInterval(notifInterval);
  }
});
</script>

<template>
  <NotificationBell
    :notifications="notifications"
    @mark-read="handleMarkRead"
    @mark-all-read="handleMarkAllRead"
    @clear-all="handleClearAll"
    @open="handleOpen"
  />
</template>
