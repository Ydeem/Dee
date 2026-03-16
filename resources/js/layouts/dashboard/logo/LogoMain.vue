<template>
  <div class="logo">
    <Link href="/dashboard" aria-label="Wilson HR Management" class="brand-link">
      <span class="brand-title">Wilson</span>
      <span class="brand-subtitle">HR Management</span>
    </Link>
  </div>
</template>

<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<style scoped lang="scss">
.brand-link {
  display: inline-flex;
  flex-direction: column;
  text-decoration: none;
  line-height: 1.05;
  color: rgb(var(--v-theme-primary));
}

.brand-title {
  font-size: 2rem;
  font-weight: 700;
  letter-spacing: 0.01em;
}

.brand-subtitle {
  font-size: 0.85rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  opacity: 0.9;
}
</style>
