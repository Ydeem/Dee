<template>
  <div class="forbidden-page">
    <v-card class="forbidden-card rounded-lg hr-card-shadow" variant="outlined" elevation="0">
      <v-card-text class="text-center py-10">
        <v-avatar color="error" variant="tonal" size="72" class="mb-4">
          <v-icon icon="mdi-lock-outline" size="36" />
        </v-avatar>
        <h1 class="text-h4 mb-3">Access Denied</h1>
        <p class="text-body-1 text-lightText mb-6">You do not have permission to view this page.</p>
        <v-btn variant="outlined" prepend-icon="mdi-arrow-left" @click="window.history.back()">Go Back</v-btn>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.forbidden-page {
  min-height: 70vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

.forbidden-card {
  max-width: 520px;
  width: 100%;
}

.hr-card-shadow {
  box-shadow: 0 8px 24px rgba(16, 24, 40, 0.06);
}
</style>
