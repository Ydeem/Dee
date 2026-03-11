<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import SvgSprite from '@/components/shared/SvgSprite.vue';

interface SearchResult {
  id: number | string
  type: string
  icon: string
  title: string
  subtitle?: string
  link: string
}

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const searching = ref(false);
const showResults = ref(false);
const highlightedIndex = ref(-1);

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const groupedResults = computed(() => {
  const groups: Record<string, SearchResult[]> = {};
  for (const result of searchResults.value) {
    if (!groups[result.type]) {
      groups[result.type] = [];
    }
    groups[result.type].push(result);
  }
  return groups;
});

const flattenedResults = computed(() => searchResults.value);

watch(searchQuery, (value) => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }

  if (!value || value.length < 2) {
    searchResults.value = [];
    showResults.value = false;
    searching.value = false;
    highlightedIndex.value = -1;
    return;
  }

  searchTimeout = setTimeout(() => {
    runSearch(value);
  }, 350);
});

watch(searchResults, (results) => {
  highlightedIndex.value = results.length > 0 ? 0 : -1;
});

watch(showResults, (visible) => {
  if (!visible) {
    highlightedIndex.value = -1;
  } else if (searchResults.value.length > 0 && highlightedIndex.value < 0) {
    highlightedIndex.value = 0;
  }
});

onBeforeUnmount(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
});

async function runSearch(query: string) {
  searching.value = true;
  try {
    const { data } = await axios.get('/api/hr/search', {
      params: { q: query }
    });
    searchResults.value = data?.results ?? [];
    showResults.value = true;
  } catch (error) {
    searchResults.value = [];
    showResults.value = false;
  } finally {
    searching.value = false;
  }
}

function selectResult(result: SearchResult) {
  showResults.value = false;
  searchQuery.value = '';
  searchResults.value = [];
  highlightedIndex.value = -1;
  router.visit(result.link);
}

function clearSearch() {
  searchQuery.value = '';
  searchResults.value = [];
  showResults.value = false;
  highlightedIndex.value = -1;
}

function handleSearch() {
  if (searchQuery.value.length >= 2) {
    runSearch(searchQuery.value);
  }
}

function moveSelection(direction: 1 | -1) {
  const total = flattenedResults.value.length;
  if (!showResults.value || total === 0) return;

  if (highlightedIndex.value < 0) {
    highlightedIndex.value = 0;
    return;
  }

  highlightedIndex.value = (highlightedIndex.value + direction + total) % total;
}

function handleEnter() {
  const selected = flattenedResults.value[highlightedIndex.value];
  if (showResults.value && selected) {
    selectResult(selected);
    return;
  }

  handleSearch();
}

function getResultIndex(result: SearchResult) {
  return flattenedResults.value.findIndex(
    (item) => item.id === result.id && item.type === result.type
  );
}
</script>

<template>
  <div class="search-wrapper">
    <v-text-field
      v-model="searchQuery"
      persistent-placeholder
      placeholder="Search employees, leaves, jobs..."
      color="primary"
      variant="outlined"
      hide-details
      clearable
      :loading="searching"
      @keydown.down.prevent="moveSelection(1)"
      @keydown.up.prevent="moveSelection(-1)"
      @keydown.enter.prevent="handleEnter"
      @keyup.esc="clearSearch"
      @click:clear="clearSearch"
      @focus="showResults = searchResults.length > 0"
      @blur="setTimeout(() => (showResults = false), 200)"
    >
      <template #prepend-inner>
        <div class="text-lightText d-flex align-center">
          <SvgSprite name="custom-search" style="width: 16px; height: 16px" />
        </div>
      </template>
    </v-text-field>

    <v-card
      v-if="showResults && (searchResults.length > 0 || (searchQuery.length >= 2 && !searching))"
      class="search-results"
      elevation="8"
      rounded="lg"
    >
      <v-list density="compact">
        <template v-if="searchResults.length > 0">
          <template v-for="(group, type) in groupedResults" :key="type">
            <v-list-subheader class="text-uppercase text-caption font-weight-bold text-primary">
              {{ type }}
            </v-list-subheader>

            <v-list-item
              v-for="result in group"
              :key="`${result.id}-${result.type}`"
              :prepend-icon="result.icon"
              :title="result.title"
              :subtitle="result.subtitle"
              :active="highlightedIndex === getResultIndex(result)"
              active-color="primary"
              rounded="lg"
              @mouseenter="highlightedIndex = getResultIndex(result)"
              @mousedown.prevent="selectResult(result)"
            />
          </template>
        </template>

        <v-list-item v-else>
          <v-list-item-title class="text-medium-emphasis text-center py-2">No results found</v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card>
  </div>
</template>

<style scoped>
.search-wrapper {
  position: relative;
}

.search-results {
  position: absolute;
  top: 44px;
  left: 0;
  right: 0;
  z-index: 9999;
  max-height: 360px;
  overflow-y: auto;
}
</style>
