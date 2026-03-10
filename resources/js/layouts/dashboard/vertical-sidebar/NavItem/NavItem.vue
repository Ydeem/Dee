<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SvgSprite from '@/components/shared/SvgSprite.vue';

const props = defineProps({ item: Object, level: Number });

const baseURL = import.meta.env.BASE_URL;

const isInternal = computed(() => {
  if (props.item.getURL || props.item.type === 'external') return false;
  return true;
});

const linkHref = computed(() => {
  if (props.item.getURL) return baseURL + props.item.to.replace(/^\//, '');
  if (props.item.type === 'external') return props.item.to;
  return props.item.to;
});

const isExternal = computed(() => props.item.getURL || props.item.type === 'external');
</script>

<template>
  <!---Single Item - Internal (Inertia Link)-->
  <Link
    v-if="isInternal"
    :href="linkHref"
    class="v-list-item v-list-item--rounded mb-1 text-primary d-flex align-center"
    :class="{ 'v-list-item--disabled': item.disabled }"
  >
    <div class="v-list-item__prepend me-3">
      <SvgSprite :name="item.icon || ''" :level="props.level" />
    </div>
    <div class="v-list-item__content">
      <span class="v-list-item-title">
        {{ item.title }}
        <v-badge v-if="item.chipColor === 'success'" :color="item.chipColor" :aria-label="item.chip" inline dot />
      </span>
      <span v-if="item.subCaption" class="v-list-item-subtitle text-caption mt-0 hide-menu d-block">{{ item.subCaption }}</span>
    </div>
    <div v-if="item.chip && item.chipColor !== 'success'" class="v-list-item__append ms-auto">
      <v-chip
        :color="item.chipColor"
        :border="item.chipVariant === 'tonal' ? `${item.chipColor} solid thin opacity-50` : ''"
        class="sidebarchip hide-menu"
        size="x-small"
        :variant="item.chipVariant"
        :prepend-icon="item.chipIcon"
      >
        {{ item.chip }}
      </v-chip>
    </div>
  </Link>
  <!---Single Item - External-->
  <v-list-item
    v-else
    :href="linkHref"
    :target="'_blank'"
    rounded
    class="mb-1"
    color="primary"
    :disabled="item.disabled"
  >
    <template v-slot:prepend>
      <SvgSprite :name="item.icon || ''" :level="props.level" />
    </template>
    <v-list-item-title>
      {{ item.title }}
      <v-badge v-if="item.chipColor === 'success'" :color="item.chipColor" :aria-label="item.chip" inline dot />
    </v-list-item-title>
    <v-list-item-subtitle v-if="item.subCaption" class="text-caption mt-0 hide-menu">{{ item.subCaption }}</v-list-item-subtitle>
    <template v-if="item.chip && item.chipColor !== 'success'" #append>
      <v-chip
        :color="item.chipColor"
        :border="item.chipVariant === 'tonal' ? `${item.chipColor} solid thin opacity-50` : ''"
        class="sidebarchip hide-menu"
        size="x-small"
        :variant="item.chipVariant"
        :prepend-icon="item.chipIcon"
      >
        {{ item.chip }}
      </v-chip>
    </template>
  </v-list-item>
</template>
