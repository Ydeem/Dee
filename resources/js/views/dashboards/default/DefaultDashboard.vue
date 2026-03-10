<script setup lang="ts">
import { ref, shallowRef } from 'vue';
import BaseBreadcrumb from '@/components/shared/BaseBreadcrumb.vue';

// imported components
import WelcomeBanner from './components/WelcomeBanner.vue';
import ChartCards from '@/views/widgets/chart/components/ChartCards.vue';
import RepeatCustomer from '@/views/widgets/chart/components/RepeatCustomer.vue';
import ProjectOverview from '@/views/widgets/chart/components/ProjectOverview.vue';
import UserCard from '@/views/widgets/statistics/components/UserCard.vue';
import ProjectCard from './components/ProjectCard.vue';
import TransactionCard from '@/views/widgets/data/components/TransactionCard.vue';
import TotalIncome from '@/views/widgets/chart/components/TotalIncome.vue';

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
  <!-- Chart widgets -->
  <!-- -------------------------------------------------------------------- -->
  <ChartCards />

  <v-row class="mb-0">
    <v-col cols="12" xl="9" md="8">
      <v-row>
        <!-- -------------------------------------------------------------------- -->
        <!-- Repeat customer -->
        <!-- -------------------------------------------------------------------- -->
        <v-col cols="12">
          <RepeatCustomer />
        </v-col>

        <!-- -------------------------------------------------------------------- -->
        <!-- Project overview -->
        <!-- -------------------------------------------------------------------- -->
        <v-col cols="12">
          <ProjectOverview />
        </v-col>
      </v-row>
    </v-col>

    <v-col cols="12" xl="3" md="4">
      <v-row>
        <!-- -------------------------------------------------------------------- -->
        <!-- Project -->
        <!-- -------------------------------------------------------------------- -->
        <v-col cols="12">
          <ProjectCard />
        </v-col>

        <!-- -------------------------------------------------------------------- -->
        <!-- User card -->
        <!-- -------------------------------------------------------------------- -->
        <v-col cols="12">
          <UserCard />
        </v-col>
      </v-row>
    </v-col>
  </v-row>

  <v-row>
    <!-- -------------------------------------------------------------------- -->
    <!-- Transactions -->
    <!-- -------------------------------------------------------------------- -->
    <v-col cols="12" md="6">
      <TransactionCard />
    </v-col>

    <!-- -------------------------------------------------------------------- -->
    <!-- Total income -->
    <!-- -------------------------------------------------------------------- -->
    <v-col cols="12" md="6">
      <TotalIncome />
    </v-col>
  </v-row>
</template>
