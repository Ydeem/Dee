<script setup lang="ts">
import { ref, shallowRef } from 'vue';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

// imported components
import WelcomeBanner from './components/WelcomeBanner.vue';
import ChartCards from '@/views/widgets/chart/components/ChartCards.vue';
import RepeatCustomer from '@/views/widgets/chart/components/RepeatCustomer.vue';
import ProjectCard from './components/ProjectCard.vue';
import HrBottomRow from './components/HrBottomRow.vue';

const currentMonthYear = new Intl.DateTimeFormat('en-US', {
  month: 'long',
  year: 'numeric'
}).format(new Date());

const page = ref({
  title: 'HR Dashboard',
  subtitle: `Human Resources Management — ${currentMonthYear}`
});

const breadcrumbs = shallowRef([
  {
    title: 'HR Module',
    disabled: false,
    href: '#'
  },
  {
    title: 'Dashboard',
    disabled: true,
    href: '#'
  }
]);
</script>

<template>
  <BaseBreadcrumb :title="page.title" :subtitle="page.subtitle" :breadcrumbs="breadcrumbs" />

  <!-- -------------------------------------------------------------------- -->
  <!-- welcome banner -->
  <!-- -------------------------------------------------------------------- -->
  <v-row class="my-0">
    <v-col cols="12">
      <WelcomeBanner />
    </v-col>
  </v-row>

  <!-- -------------------------------------------------------------------- -->
  <!-- HR Stats -->
  <!-- -------------------------------------------------------------------- -->
  <ChartCards />

  <!-- -------------------------------------------------------------------- -->
  <!-- Attendance + Pending Actions -->
  <!-- -------------------------------------------------------------------- -->
  <v-row class="mb-0">
    <v-col cols="12" xl="8" md="7">
      <RepeatCustomer />
    </v-col>
    <v-col cols="12" xl="4" md="5">
      <ProjectCard />
    </v-col>
  </v-row>

  <!-- -------------------------------------------------------------------- -->
  <!-- Recent Hires + Upcoming Events -->
  <!-- -------------------------------------------------------------------- -->
  <HrBottomRow />
</template>
