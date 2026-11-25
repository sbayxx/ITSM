<template>
  <div v-if="auth.isImpersonating" class="impersonation-banner">
    <div class="banner-content">
      <svg class="icon" viewBox="0 0 20 20" fill="currentColor">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
      </svg>
      <span>
        Impersonating <strong>{{ auth.user?.name }}</strong>
        <span v-if="auth.impersonator"> ({{ auth.impersonator.name }})</span>
      </span>
      <button class="btn-stop" @click="stop">
        Stop Impersonating
      </button>
    </div>
  </div>
</template>

<script setup>
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()

async function stop() {
  await auth.stopImpersonate()
}
</script>

<style scoped>
.impersonation-banner {
  background: #dc2626;
  color: #fff;
  text-align: center;
  padding: 8px 16px;
  position: sticky;
  top: 0;
  z-index: 1000;
}
.banner-content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
}
.icon {
  width: 20px;
  height: 20px;
}
.btn-stop {
  background: #fff;
  color: #dc2626;
  border: none;
  padding: 4px 12px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 13px;
}
.btn-stop:hover {
  background: #fef2f2;
}
</style>
