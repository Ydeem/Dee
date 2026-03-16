<script setup lang="ts">
import { onMounted, ref } from 'vue';
import axios from 'axios';

type SocialKey = 'linkedin' | 'twitter' | 'github' | 'instagram' | 'facebook' | 'website';
type SocialForm = Record<SocialKey, string>;

const loading = ref(false);
const saving = ref(false);
const snackbar = ref({
  show: false,
  message: '',
  color: 'success',
});

const socials = ref<SocialForm>({
  linkedin: '',
  twitter: '',
  github: '',
  instagram: '',
  facebook: '',
  website: '',
});

const socialFields: Array<{
  key: SocialKey
  label: string
  icon: string
  color: string
  placeholder: string
}> = [
  {
    key: 'linkedin',
    label: 'LinkedIn',
    icon: 'mdi-linkedin',
    color: '#0077b5',
    placeholder: 'https://linkedin.com/in/...',
  },
  {
    key: 'twitter',
    label: 'Twitter / X',
    icon: 'mdi-twitter',
    color: '#1da1f2',
    placeholder: 'https://twitter.com/...',
  },
  {
    key: 'github',
    label: 'GitHub',
    icon: 'mdi-github',
    color: '#333',
    placeholder: 'https://github.com/...',
  },
  {
    key: 'instagram',
    label: 'Instagram',
    icon: 'mdi-instagram',
    color: '#e1306c',
    placeholder: 'https://instagram.com/...',
  },
  {
    key: 'facebook',
    label: 'Facebook',
    icon: 'mdi-facebook',
    color: '#1877f2',
    placeholder: 'https://facebook.com/...',
  },
  {
    key: 'website',
    label: 'Personal Website',
    icon: 'mdi-web',
    color: '#4f6ef7',
    placeholder: 'https://yourwebsite.com',
  },
];

async function fetchSocials() {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/profile/social');
    socials.value = {
      ...socials.value,
      ...(data?.socials ?? {}),
    };
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
}

async function saveSocials() {
  saving.value = true;
  try {
    await axios.post('/api/profile/social', socials.value);
    snackbar.value = {
      show: true,
      message: 'Social links saved!',
      color: 'success',
    };
  } catch (error) {
    console.error(error);
    snackbar.value = {
      show: true,
      message: 'Failed to save links.',
      color: 'error',
    };
  } finally {
    saving.value = false;
  }
}

onMounted(fetchSocials);
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <h1 class="text-h5 font-weight-bold">
          Social Profile
        </h1>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Manage your public social media links
        </p>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        :loading="saving"
        prepend-icon="mdi-content-save"
        @click="saveSocials"
      >
        Save Links
      </v-btn>
    </div>

    <v-skeleton-loader
      v-if="loading"
      type="card"
    />

    <v-card
      v-else
      rounded="lg"
      elevation="0"
      border
    >
      <v-card-text>
        <div
          v-for="field in socialFields"
          :key="field.key"
          class="mb-4"
        >
          <v-text-field
            v-model="socials[field.key]"
            :label="field.label"
            :placeholder="field.placeholder"
            variant="outlined"
            density="comfortable"
            hide-details
            clearable
          >
            <template #prepend-inner>
              <v-avatar
                size="28"
                :color="field.color"
                class="mr-1"
              >
                <v-icon
                  size="16"
                  color="white"
                >
                  {{ field.icon }}
                </v-icon>
              </v-avatar>
            </template>

            <template #append-inner>
              <v-btn
                v-if="socials[field.key]"
                icon
                size="x-small"
                variant="text"
                :href="socials[field.key]"
                target="_blank"
              >
                <v-icon size="16">
                  mdi-open-in-new
                </v-icon>
              </v-btn>
            </template>
          </v-text-field>
        </div>
      </v-card-text>

      <v-card-actions class="px-4 pb-4">
        <v-spacer />
        <v-btn
          color="primary"
          variant="flat"
          :loading="saving"
          prepend-icon="mdi-content-save"
          @click="saveSocials"
        >
          Save All Links
        </v-btn>
      </v-card-actions>
    </v-card>

    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
      location="bottom right"
    >
      {{ snackbar.message }}
    </v-snackbar>
  </div>
</template>
