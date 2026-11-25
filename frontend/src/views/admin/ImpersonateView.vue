<template>
  <div>
    <h1 class="text-xl font-bold mb-4">Impersonate User</h1>

    <div class="mb-3">
      <input
        v-model="search"
        type="text"
        placeholder="Search users by name or email..."
        class="w-full max-w-md px-3 py-2 border rounded-lg"
        @input="searchUsers"
      />
    </div>

    <div v-if="loading" class="text-surface-500 py-4">Loading users...</div>

    <div v-else-if="users.length === 0" class="text-surface-500 py-4">
      No users found.
    </div>

    <div v-else class="space-y-2">
      <div
        v-for="user in users"
        :key="user.id"
        class="flex items-center justify-between p-3 bg-white rounded-lg border hover:shadow-sm"
      >
        <div>
          <div class="font-medium">{{ user.name }}</div>
          <div class="text-sm text-surface-500">{{ user.email }}</div>
          <div class="text-xs text-surface-400">Role: {{ formatLabel(user.role) }}</div>
        </div>
        <button
          class="px-3 py-1.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 text-sm font-medium disabled:opacity-50"
          :disabled="impersonating === user.id"
          @click="startImpersonate(user)"
        >
          {{ impersonating === user.id ? 'Switching...' : 'Impersonate' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import client from '../../api/client'

const auth = useAuthStore()
const router = useRouter()
const search = ref('')
const users = ref([])
const loading = ref(false)
const impersonating = ref(null)

async function fetchUsers() {
  loading.value = true
  try {
    const params = search.value ? { search: search.value } : {}
    const res = await client.get('/users', { params })
    users.value = res.data.filter(u => u.role !== 'admin' && u.id !== auth.user?.id)
  } finally {
    loading.value = false
  }
}

function searchUsers() {
  clearTimeout(window._userSearchTimer)
  window._userSearchTimer = setTimeout(fetchUsers, 300)
}

async function startImpersonate(user) {
  impersonating.value = user.id
  try {
    await auth.impersonate(user.id)
    router.push('/')
  } catch {
    impersonating.value = null
  }
}

onMounted(fetchUsers)
</script>
