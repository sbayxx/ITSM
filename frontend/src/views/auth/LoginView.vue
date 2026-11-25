<template>
  <div class="min-h-screen flex items-center justify-center bg-surface px-4 relative overflow-hidden">
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(6,182,212,0.08),transparent_50%),radial-gradient(ellipse_at_bottom_left,_rgba(14,116,144,0.05),transparent_50%)]"></div>
    <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-primary-500/5 rounded-full blur-3xl"></div>

    <div class="card-glass w-full max-w-md p-8 relative z-10">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-primary-500 to-cyan-500 mb-4 shadow-glow">
          <span class="text-white font-bold text-lg">OC</span>
        </div>
        <h1 class="text-xl font-semibold text-surface-100">OC ITSM</h1>
        <p class="text-xs text-surface-400 mt-1 font-mono">IT Service Management Platform</p>
      </div>

      <div v-if="error" class="mb-4 p-3 rounded-lg bg-red-900/30 border border-red-700/30 text-xs text-red-300 font-mono flex items-start gap-2">
        <AppIcon name="circle-alert" :size="14" class="text-red-400 mt-0.5" />
        <span>{{ error }}</span>
      </div>

      <form @submit.prevent="handleLogin">
        <div class="space-y-4">
          <div>
            <label class="label" for="email">Email</label>
            <div class="relative">
              <AppIcon name="mail" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400" />
              <input id="email" v-model="email" type="email" class="input pl-9" placeholder="you@example.com" required autocomplete="email" />
            </div>
          </div>
          <div>
            <label class="label" for="password">Password</label>
            <div class="relative">
              <AppIcon name="key-round" :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-surface-400" />
              <input id="password" v-model="password" type="password" class="input pl-9" placeholder="Enter your password" required autocomplete="current-password" />
            </div>
          </div>
        </div>
        <button type="submit" class="btn-primary w-full mt-6 gap-2" :disabled="auth.loading">
          <AppIcon v-if="auth.loading" name="loader" :size="16" class="animate-spin -ml-1" />
          {{ auth.loading ? 'Authenticating...' : 'Sign In' }}
        </button>
      </form>

      <!-- Microsoft SSO temporarily disabled -->
      <!--
      <div class="relative my-6">
        <div class="absolute inset-0 flex items-center">
          <div class="w-full border-t border-surface-700"></div>
        </div>
        <div class="relative flex justify-center text-xs">
          <span class="bg-surface px-2 text-surface-400">or</span>
        </div>
      </div>

      <button class="btn-secondary w-full flex items-center justify-center gap-2" @click="loginMicrosoft">
        <svg viewBox="0 0 23 23" class="w-5 h-5"><path fill="#f25022" d="M11 1H1v10h10V1z"/><path fill="#7fba00" d="M22 1H12v10h10V1z"/><path fill="#00a4ef" d="M11 12H1v10h10V12z"/><path fill="#ffb900" d="M22 12H12v10h10V12z"/></svg>
        Sign in with Microsoft
      </button>
      -->

      <p class="text-center text-[10px] text-surface-400 mt-6 font-mono">OC ITSM v1.0.0 — ITIL 4 Compliant</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/common/AppIcon.vue'

const router = useRouter()
const auth = useAuthStore()
const email = ref('')
const password = ref('')
const error = ref('')

async function handleLogin() {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push('/')
  } catch (err) {
    error.value = err?.message || 'Authentication failed. Check your credentials.'
  }
}
</script>
