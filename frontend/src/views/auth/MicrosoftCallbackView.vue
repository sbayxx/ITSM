<template>
  <div class="min-h-screen flex items-center justify-center bg-surface">
    <div class="card p-8 text-center">
      <div v-if="error" class="text-red-400 mb-4">{{ error }}</div>
      <div v-else class="text-surface-300">
        <svg class="animate-spin mx-auto h-8 w-8 text-primary-400 mb-4" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <p>Completing sign in...</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const error = ref('')

onMounted(async () => {
  const code = route.query.code
  const state = route.query.state

  if (!code) {
    error.value = 'No authorization code received from Microsoft.'
    return
  }

  try {
    const res = await client.post('/auth/microsoft/callback', { code, state })
    auth.user = res.data.user
    auth.csrfToken = res.data.csrf_token
    const { setCsrfToken } = await import('../../api/client')
    setCsrfToken(auth.csrfToken)
    router.push('/')
  } catch (e) {
    error.value = e?.message || e?.detail || 'Microsoft sign in failed.'
    setTimeout(() => router.push('/login'), 3000)
  }
})
</script>
