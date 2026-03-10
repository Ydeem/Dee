<template>
  <svg class="pc-icon">
    <use :href="spritePath ? `${spritePath}#${name}` : ''"></use>
  </svg>
</template>

<script setup>
import { ref, onMounted } from 'vue';

defineProps({
  name: { type: String, default: '' },
});

const spritePath = ref('');

onMounted(() => {
  const base = typeof import.meta.env !== 'undefined' && import.meta.env.BASE_URL != null
    ? import.meta.env.BASE_URL
    : '/';
  spritePath.value = base.replace(/\/$/, '') + '/assets/svg/sprite.svg';
});
</script>
