<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const userName = computed(() => {
  const sharedUser = (page.props as any)?.auth?.user?.name;
  return sharedUser || 'HR Manager';
});

const todayLabel = computed(() =>
  new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(new Date())
);

const summary = [
  { label: 'Employees on Leave Today', value: 'X' },
  { label: 'Pending Approvals', value: 'X' },
  { label: 'Open Job Positions', value: 'X' }
];
</script>

<template>
  <v-sheet rounded="md" class="pa-4 ExtraBox hide-menu hr-welcome-banner" border>
    <div class="d-flex align-start justify-space-between ga-3">
      <div class="banner-content">
        <h5 class="text-h5 mb-1 banner-title">Welcome back, {{ userName }}</h5>
        <p class="text-caption mb-3 banner-date">{{ todayLabel }}</p>

        <div class="summary-row d-flex flex-column ga-2">
          <div v-for="item in summary" :key="item.label" class="summary-pill">
            <span class="summary-value">{{ item.value }}</span>
            <span class="summary-label">{{ item.label }}</span>
          </div>
        </div>
      </div>

      <div class="banner-illustration" aria-hidden="true">
        <svg viewBox="0 0 160 130" role="img" focusable="false">
          <defs>
            <linearGradient id="teamGradient" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#dbeafe" />
              <stop offset="100%" stop-color="#c7d2fe" />
            </linearGradient>
          </defs>
          <rect x="8" y="24" width="145" height="95" rx="14" fill="url(#teamGradient)" opacity="0.45" />
          <circle cx="40" cy="55" r="12" fill="#ffffff" opacity="0.95" />
          <circle cx="78" cy="49" r="14" fill="#ffffff" opacity="0.95" />
          <circle cx="117" cy="57" r="12" fill="#ffffff" opacity="0.95" />
          <rect x="26" y="72" width="29" height="31" rx="10" fill="#ffffff" opacity="0.92" />
          <rect x="58" y="66" width="39" height="40" rx="12" fill="#ffffff" opacity="0.96" />
          <rect x="102" y="74" width="29" height="29" rx="10" fill="#ffffff" opacity="0.92" />
          <circle cx="137" cy="32" r="7" fill="#60a5fa" />
          <circle cx="20" cy="36" r="5" fill="#818cf8" />
        </svg>
      </div>
    </div>
  </v-sheet>
</template>

<style lang="scss">
.ExtraBox.hr-welcome-banner {
  position: relative;
  overflow: hidden;
  background: linear-gradient(135deg, #2563eb 0%, #4f46e5 100%);
  color: #fff;
  border-color: rgba(255, 255, 255, 0.2) !important;

  .banner-content {
    min-width: 0;
  }

  .banner-title {
    line-height: 1.2;
  }

  .banner-date {
    color: rgba(255, 255, 255, 0.85);
  }

  .summary-row {
    max-width: 100%;
  }

  .summary-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 9px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    width: fit-content;
    max-width: 100%;
  }

  .summary-value {
    font-size: 0.75rem;
    font-weight: 700;
    line-height: 1;
    padding: 4px 6px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.25);
  }

  .summary-label {
    font-size: 0.72rem;
    line-height: 1.2;
    color: rgba(255, 255, 255, 0.95);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .banner-illustration {
    width: 98px;
    flex: 0 0 98px;
    opacity: 0.95;

    svg {
      width: 100%;
      height: auto;
      display: block;
    }
  }
}
</style>
