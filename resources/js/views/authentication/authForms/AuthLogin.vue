<script setup lang="ts">
import { ref } from 'vue';
import SvgSprite from '@/components/shared/SvgSprite.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { Form } from 'vee-validate';

const checkbox = ref(false);
const show1 = ref(false);

const form = useForm({
  email: 'pontian@npontu.com',
  password: 'password12#',
  remember: false,
});

// Password validation rules
const passwordRules = ref([
  (v: string) => !!v || 'Password is required',
  (v: string) => v === v.trim() || 'Password cannot start or end with spaces',
  (v: string) => v.length <= 10 || 'Password must be less than 10 characters'
]);
// Email validation rules
const emailRules = ref([
  (v: string) => !!v?.trim() || 'E-mail is required',
  (v: string) => {
    const trimmedEmail = (v || '').trim();
    return !/\s/.test(trimmedEmail) || 'E-mail must not contain spaces';
  },
  (v: string) => /.+@.+\..+/.test((v || '').trim()) || 'E-mail must be valid'
]);

function validate(_values: unknown, { setErrors }: { setErrors: (e: Record<string, string>) => void }) {
  form.email = form.email.trim();
  form.remember = checkbox.value;
  form.post('/login', {
    onError: (errors) => setErrors({ apiError: Object.values(errors).flat().join(' ') }),
  });
}
</script>

<template>
  <div class="d-flex justify-space-between align-center mt-4">
    <h3 class="text-h3 text-center mb-0">Login</h3>
    <Link href="/register1" class="text-primary text-decoration-none">Don't Have an account?</Link>
  </div>
  <Form @submit="validate" class="mt-7 loginForm" v-slot="{ errors, isSubmitting }">
    <div class="mb-6">
      <v-label>Email Address</v-label>
      <v-text-field
        aria-label="email address"
        v-model="form.email"
        :rules="emailRules"
        class="mt-2"
        density="comfortable"
        required
        hide-details="auto"
        variant="outlined"
        color="primary"
      ></v-text-field>
    </div>
    <div>
      <v-label>Password</v-label>
      <v-text-field
        aria-label="password"
        v-model="form.password"
        :rules="passwordRules"
        required
        variant="outlined"
        density="comfortable"
        color="primary"
        hide-details="auto"
        :type="show1 ? 'text' : 'password'"
        class="pwdInput mt-2"
      >
        <template v-slot:append-inner>
          <v-btn color="secondary" aria-label="icon" icon rounded variant="text">
            <SvgSprite name="custom-eye-invisible" style="width: 20px; height: 20px" v-if="show1 == false" @click="show1 = !show1" />
            <SvgSprite name="custom-eye" style="width: 20px; height: 20px" v-if="show1 == true" @click="show1 = !show1" />
          </v-btn>
        </template>
      </v-text-field>
    </div>

    <div class="d-flex align-center mt-4 mb-7 mb-sm-0">
      <v-checkbox
        v-model="checkbox"
        :rules="[(v: any) => !!v || 'You must agree to continue!']"
        label="Keep me sign in"
        required
        color="primary"
        class="ms-n2"
        hide-details
      ></v-checkbox>
      <div class="ml-auto">
        <Link href="/forgot-pwd1" class="text-darkText link-hover">Forgot Password?</Link>
      </div>
    </div>
    <v-btn
      color="primary"
      :loading="form.processing"
      block
      class="mt-5"
      variant="flat"
      size="large"
      rounded="md"
      :disabled="form.processing"
      type="submit"
    >
      Login</v-btn
    >
    <div v-if="errors.apiError" class="mt-2">
      <v-alert color="error">{{ errors.apiError }}</v-alert>
    </div>
  </Form>
</template>
