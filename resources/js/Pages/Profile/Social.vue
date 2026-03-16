<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';
import DashboardLayout from '@/layouts/dashboard/DashboardLayout.vue';

type SocialLinks = {
  linkedin: string
  twitter: string
  github: string
  instagram: string
  website: string
};

const loading = ref(false);
const saving = ref(false);
const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});
const form = ref<SocialLinks>({
  linkedin: '',
  twitter: '',
  github: '',
  instagram: '',
  website: ''
});

function showSnackbar(message: string, color: 'success' | 'error' = 'success') {
  snackbar.value = { show: true, message, color };
}

async function loadProfile() {
  loading.value = true;

  try {
    const { data } = await axios.get('/api/profile');
    form.value = {
      linkedin: data?.employee?.socials?.linkedin ?? '',
      twitter: data?.employee?.socials?.twitter ?? '',
      github: data?.employee?.socials?.github ?? '',
      instagram: data?.employee?.socials?.instagram ?? '',
      website: data?.employee?.socials?.website ?? ''
    };
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to load social profile.', 'error');
  } finally {
    loading.value = false;
  }
}

async function saveSocials() {
  saving.value = true;

  try {
    const { data } = await axios.post('/api/profile/social', form.value);
    form.value = {
      linkedin: data?.socials?.linkedin ?? form.value.linkedin,
      twitter: data?.socials?.twitter ?? form.value.twitter,
      github: data?.socials?.github ?? form.value.github,
      instagram: data?.socials?.instagram ?? form.value.instagram,
      website: data?.socials?.website ?? form.value.website
    };
    showSnackbar(data?.message ?? 'Social links saved.');
  } catch (error: any) {
    showSnackbar(error?.response?.data?.message ?? 'Failed to save social links.', 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(() => {
  loadProfile();
});
</script>

<template>
  <DashboardLayout>
    <v-container fluid class="py-6">
      <v-card rounded="lg" elevation="0" class="pa-4 pa-md-6">
        <div class="d-flex align-center justify-space-between mb-6">
          <div>
            <h2 class="text-h4 mb-1">Social Profile</h2>
            <p class="text-body-2 text-medium-emphasis mb-0">Update your public profile links.</p>
          </div>
          <v-btn color="primary" :loading="saving" :disabled="loading" @click="saveSocials">Save Links</v-btn>
        </div>

        <v-row>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.linkedin" label="LinkedIn URL" variant="outlined" hide-details="auto" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.twitter" label="Twitter/X URL" variant="outlined" hide-details="auto" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.github" label="GitHub URL" variant="outlined" hide-details="auto" />
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field v-model="form.instagram" label="Instagram URL" variant="outlined" hide-details="auto" />
          </v-col>
          <v-col cols="12">
            <v-text-field v-model="form.website" label="Website URL" variant="outlined" hide-details="auto" />
          </v-col>
        </v-row>
      </v-card>
    </v-container>

    <v-snackbar v-model="snackbar.show" :color="snackbar.color" timeout="3200">
      {{ snackbar.message }}
    </v-snackbar>
  </DashboardLayout>
</template>
