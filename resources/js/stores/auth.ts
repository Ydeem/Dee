import { defineStore } from 'pinia';
import { router } from '@inertiajs/vue3';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as { id: number; name: string; email: string } | null,
    returnUrl: null as string | null,
  }),
  actions: {
    logout() {
      this.user = null;
      router.post('/logout');
    },
  },
});
