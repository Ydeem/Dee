// ===============================|| Blank Layout ||=============================== //
<template>
  <v-app>
    <!-- Loader start -->
    <LoaderWrapper />
    <!-- Loader end -->
    <slot />
  </v-app>
</template>
<script setup lang="ts">
import LoaderWrapper from '../dashboard/LoaderWrapper.vue';
</script>
