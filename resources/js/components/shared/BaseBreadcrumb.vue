<script setup lang="ts">
import SvgSprite from '@/components/shared/SvgSprite.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { appUrl } from '@/utils/appUrl';

type Breadcrumb = {
  title: string;
  disabled: boolean;
  href: string;
};

const props = defineProps({
  title: String,
  breadcrumbs: Array as () => Breadcrumb[],
  icon: String,
  subtitle: String
});

const normalizedBreadcrumbs = computed(() =>
  (props.breadcrumbs ?? []).map((item) => ({
    ...item,
    href: item.href && item.href !== '#' ? appUrl(item.href) : item.href
  }))
);
</script>

// ===============================|| Theme Breadcrumb ||=============================== //
<template>
  <v-row class="page-breadcrumb mb-0 mt-n2">
    <v-col cols="12" md="12">
      <v-card elevation="0" variant="text">
        <v-row no-gutters class="align-center">
          <v-col sm="12">
            <v-breadcrumbs :items="normalizedBreadcrumbs" class="text-h6 pa-1 mb-0">
              <template v-slot:divider>
                <div class="d-flex align-center">
                  <SvgSprite name="custom-chevron-outline" style="width: 12px; height: 12px" />
                </div>
              </template>
              <template v-slot:prepend>
                <Link :href="appUrl('/dashboard')" class="text-darkText text-h6 text-decoration-none"> Home </Link>
                <div class="d-flex align-center px-2">
                  <SvgSprite name="custom-chevron-outline" style="width: 12px; height: 12px" />
                </div>
              </template>
              <template #title="{ item }">
                <Link
                  v-if="item.href && item.href !== '#' && !item.disabled"
                  :href="item.href"
                  class="text-darkText text-h6 text-decoration-none"
                >
                  {{ item.title }}
                </Link>
                <span v-else class="text-h6 text-medium-emphasis">
                  {{ item.title }}
                </span>
              </template>
            </v-breadcrumbs>
          </v-col>
        </v-row>
      </v-card>
    </v-col>
  </v-row>
</template>

<style lang="scss">
.page-breadcrumb {
  .v-toolbar {
    background: transparent;
  }
}
</style>
