<template>
  <div v-if="loading" class="flex items-center justify-center py-12">
    <p class="text-gray-400">Loading customer...</p>
  </div>

  <div v-else-if="!customer" class="text-center py-12">
    <p class="text-gray-500">Customer not found.</p>
    <router-link to="/customers" class="btn-secondary mt-4">Back to Customers</router-link>
  </div>

  <div v-else>
    <div class="flex items-center justify-between mb-6">
      <div>
        <router-link to="/customers" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Customers</router-link>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ customer.name }}</h1>
        <p class="text-sm text-gray-500 font-mono">{{ customer.slug }}</p>
      </div>
      <div class="flex gap-2">
        <button v-if="auth.isAdmin" @click="deleteCustomer" class="btn-danger">Delete</button>
      </div>
    </div>

    <div class="mb-6">
      <div class="border-b border-gray-200">
        <nav class="flex gap-6">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            class="pb-3 text-sm font-medium border-b-2 transition-colors"
            :class="activeTab === tab.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
            {{ tab.label }}
          </button>
        </nav>
      </div>
    </div>

    <!-- Details Tab -->
    <div v-if="activeTab === 'details'" class="card">
      <form @submit.prevent="updateDetails">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="label">Name</label>
            <input v-model="form.name" type="text" class="input" required />
          </div>
          <div>
            <label class="label">Slug</label>
            <input v-model="form.slug" type="text" class="input" required />
          </div>
          <div class="md:col-span-2">
            <label class="label">Description</label>
            <textarea v-model="form.description" class="input" rows="3"></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-gray-300" :true-value="true" :false-value="false" />
            <label for="is_active" class="text-sm text-gray-700">Active</label>
          </div>
        </div>
        <div class="flex justify-end mt-6">
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
        </div>
      </form>
    </div>

    <!-- Settings Tab -->
    <div v-if="activeTab === 'settings'" class="space-y-6">
      <div class="card">
        <h2 class="text-lg font-bold mb-4">Azure AD Configuration</h2>
        <form @submit.prevent="saveSettings">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Tenant ID</label>
              <input v-model="settings.azure_ad.tenant_id" type="text" class="input font-mono text-sm" placeholder="00000000-0000-0000-0000-000000000000" />
            </div>
            <div>
              <label class="label">Client ID</label>
              <input v-model="settings.azure_ad.client_id" type="text" class="input font-mono text-sm" placeholder="00000000-0000-0000-0000-000000000000" />
            </div>
            <div>
              <label class="label">Client Secret</label>
              <input v-model="settings.azure_ad.client_secret" type="password" class="input font-mono text-sm" placeholder="Enter client secret" />
            </div>
            <div class="flex items-center gap-2 pt-6">
              <input v-model="settings.azure_ad.sync_users" type="checkbox" id="sync_users" class="rounded border-gray-300" />
              <label for="sync_users" class="text-sm text-gray-700">Enable user sync</label>
            </div>
            <div>
              <label class="label">User type filter</label>
              <select v-model="settings.azure_ad.user_type_filter" class="select">
                <option value="">All user types</option>
                <option value="Member">Member</option>
                <option value="Guest">Guest</option>
              </select>
            </div>
            <div class="flex items-center gap-2 pt-6">
              <input v-model="settings.azure_ad.licensed_only" type="checkbox" id="licensed_only" class="rounded border-gray-300" />
              <label for="licensed_only" class="text-sm text-gray-700">Licensed users only</label>
            </div>
          </div>
          <hr class="my-6 border-gray-200" />
          <h3 class="text-md font-semibold mb-4">Intune Configuration</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex items-center gap-2">
              <input v-model="settings.intune.enabled" type="checkbox" id="intune_enabled" class="rounded border-gray-300" />
              <label for="intune_enabled" class="text-sm text-gray-700">Enable Intune sync</label>
            </div>
          </div>
          <hr class="my-6 border-gray-200" />
          <h3 class="text-md font-semibold mb-4">Sync Schedule</h3>
          <div>
            <select v-model="settings.sync.schedule" class="select max-w-xs">
              <option value="manual">Manual only</option>
              <option value="hourly">Every hour</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
            </select>
          </div>
          <div class="flex justify-end mt-6">
            <button type="submit" class="btn-primary" :disabled="savingSettings">{{ savingSettings ? 'Saving...' : 'Save Settings' }}</button>
          </div>
        </form>
      </div>

      <div class="card" v-if="isConfigured">
        <h2 class="text-lg font-bold mb-4">Actions</h2>
        <div class="flex gap-3">
          <button @click="syncAzure" class="btn-secondary" :disabled="syncing">{{ syncing ? 'Syncing...' : 'Sync Users from Azure AD' }}</button>
          <button @click="syncIntune" class="btn-secondary" :disabled="syncingIntune">{{ syncingIntune ? 'Syncing...' : 'Sync Intune Devices' }}</button>
        </div>
        <p v-if="syncMessage" class="mt-3 text-sm" :class="syncMessage.startsWith('Error') ? 'text-red-600' : 'text-green-600'">{{ syncMessage }}</p>
      </div>
    </div>

    <!-- Users Tab -->
    <div v-if="activeTab === 'users'" class="card">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Assigned Users</h2>
      </div>
      <div v-if="!availableUsers.length" class="text-sm text-gray-500 py-4">No users available. Create users first.</div>
      <div v-else class="space-y-2 max-h-96 overflow-y-auto">
        <div v-for="user in availableUsers" :key="user.id"
          class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50">
          <input type="checkbox" :value="user.id" v-model="selectedUserIds"
            class="rounded border-gray-300" />
          <div>
            <div class="text-sm font-medium text-gray-900">{{ user.name }}</div>
            <div class="text-xs text-gray-500">{{ user.email }} <span class="badge badge-low ml-1">{{ formatLabel(user.role) }}</span></div>
          </div>
        </div>
      </div>
      <div class="flex justify-end mt-4">
        <button @click="assignUsers" class="btn-primary" :disabled="savingUsers">{{ savingUsers ? 'Saving...' : 'Save Assignments' }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import AppIcon from '../../components/common/AppIcon.vue'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import client from '../../api/client'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const loading = ref(true)
const customer = ref(null)
const activeTab = ref('details')
const saving = ref(false)
const savingSettings = ref(false)
const savingUsers = ref(false)
const syncing = ref(false)
const syncingIntune = ref(false)
const syncMessage = ref('')

const form = ref({ name: '', slug: '', description: '', is_active: true })
const settings = ref({
  azure_ad: { tenant_id: '', client_id: '', client_secret: '', sync_users: false, licensed_only: true, user_type_filter: '' },
  intune: { enabled: false },
  sync: { schedule: 'manual' },
})
const availableUsers = ref([])
const selectedUserIds = ref([])

const tabs = [
  { key: 'details', label: 'Details' },
  { key: 'settings', label: 'Settings & Sync' },
  { key: 'users', label: 'Users' },
]

const isConfigured = computed(() => {
  return settings.value.azure_ad?.tenant_id && settings.value.azure_ad?.client_id
})

async function fetchCustomer() {
  loading.value = true
  try {
    const res = await client.get(`/customers/${route.params.id}`)
    customer.value = res.data
    form.value = {
      name: res.data.name,
      slug: res.data.slug,
      description: res.data.description || '',
      is_active: !!res.data.is_active,
    }
    settings.value = res.data.settings
    selectedUserIds.value = res.data.assigned_user_ids || []
  } catch {
    customer.value = null
  } finally {
    loading.value = false
  }
}

async function fetchUsers() {
  try {
    const res = await client.get(`/customers/${route.params.id}/users`)
    availableUsers.value = res.data.users
    selectedUserIds.value = res.data.assigned_ids
  } catch { /* ignore */ }
}

async function updateDetails() {
  saving.value = true
  try {
    const res = await client.put(`/customers/${route.params.id}`, form.value)
    customer.value = res.data
  } finally {
    saving.value = false
  }
}

async function saveSettings() {
  savingSettings.value = true
  try {
    const res = await client.put(`/customers/${route.params.id}/settings`, settings.value)
    settings.value = res.data
    syncMessage.value = 'Settings saved.'
  } catch {
    syncMessage.value = 'Error saving settings.'
  } finally {
    savingSettings.value = false
  }
}

async function assignUsers() {
  savingUsers.value = true
  try {
    await client.post(`/customers/${route.params.id}/users`, { user_ids: selectedUserIds.value })
    syncMessage.value = 'User assignments saved.'
  } catch {
    syncMessage.value = 'Error saving user assignments.'
  } finally {
    savingUsers.value = false
  }
}

async function syncAzure() {
  syncing.value = true
  syncMessage.value = ''
  try {
    const res = await client.post('/users/sync-azure', {
      customer_id: parseInt(route.params.id),
      licensed_only: settings.value.azure_ad?.licensed_only ?? true,
      enabled_only: false,
      user_type: settings.value.azure_ad?.user_type_filter || '',
      department: '',
    })
    syncMessage.value = res?.message || 'Azure sync completed.'
  } catch (e) {
    syncMessage.value = 'Error: ' + (e?.message || e?.detail || 'Sync failed.')
  } finally {
    syncing.value = false
  }
}

async function syncIntune() {
  syncingIntune.value = true
  syncMessage.value = ''
  try {
    const res = await client.post('/intune/sync', { customer_id: parseInt(route.params.id) })
    syncMessage.value = res?.message || 'Intune sync completed.'
  } catch (e) {
    syncMessage.value = 'Error: ' + (e?.message || e?.detail || 'Sync failed.')
  } finally {
    syncingIntune.value = false
  }
}

async function deleteCustomer() {
  if (!confirm(`Delete customer "${customer.value.name}"?`)) return
  try {
    await client.delete(`/customers/${route.params.id}`)
    router.push('/customers')
  } catch { /* ignore */ }
}

onMounted(async () => {
  await fetchCustomer()
  if (customer.value) {
    await fetchUsers()
  }
})
</script>
