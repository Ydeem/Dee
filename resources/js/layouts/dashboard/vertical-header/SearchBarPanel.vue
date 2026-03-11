<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const searchWrapper = ref<HTMLElement | null>(null);
const dropdownStyle = ref<Record<string, string>>({});

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
const shouldShowResults = computed(
  () => showResults.value && (searchResults.value.length > 0 || (searchQuery.value.length >= 2 && !searching.value))
);

function updateDropdownPosition() {
  if (!searchWrapper.value) return;
  const rect = searchWrapper.value.getBoundingClientRect();
  const viewportPadding = 12;

  const width = Math.min(Math.round(rect.width), window.innerWidth - viewportPadding * 2);
  const left = Math.min(
    Math.max(Math.round(rect.left), viewportPadding),
    Math.max(viewportPadding, window.innerWidth - width - viewportPadding)
  );
  const top = Math.round(rect.bottom + 6);
  const maxHeight = Math.max(180, window.innerHeight - top - viewportPadding);

  dropdownStyle.value = {
    top: `${top}px`,
    left: `${left}px`,
    width: `${width}px`,
    maxHeight: `${maxHeight}px`
  };
}

const syncDropdownOnViewportChange = () => {
  if (shouldShowResults.value) {
    updateDropdownPosition();
  }
};

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

watch(
  shouldShowResults,
  async (visible) => {
    if (!visible) return;
    await nextTick();
    updateDropdownPosition();
  },
  { flush: 'post' }
);

onMounted(() => {
  window.addEventListener('resize', syncDropdownOnViewportChange);
  window.addEventListener('scroll', syncDropdownOnViewportChange, true);
});

onBeforeUnmount(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout);
  }
  window.removeEventListener('resize', syncDropdownOnViewportChange);
  window.removeEventListener('scroll', syncDropdownOnViewportChange, true);
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
  <div ref="searchWrapper" class="search-wrapper">
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

  </div>

  <teleport to="body">
    <v-card v-if="shouldShowResults" class="search-results" :style="dropdownStyle" elevation="8" rounded="lg">
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
  </teleport>
</template>

<style scoped>
.search-wrapper {
  position: relative;
}

.search-results {
  position: fixed;
  z-index: 99999 !important;
  max-height: 360px;
  overflow-y: auto;
  background: rgb(var(--v-theme-surface));
  border: 1px solid rgba(15, 23, 42, 0.08);
  box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
  backdrop-filter: blur(2px);
}
</style>
