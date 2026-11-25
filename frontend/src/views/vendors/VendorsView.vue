<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Vendors</h1>
        <p class="text-sm text-gray-500 mt-1">External suppliers that cases can be sent to by email or by API.</p>
      </div>
      <button v-if="canManage" @click="openForm()" class="btn btn-primary gap-1.5">
        <AppIcon name="plus" :size="16" />
        New vendor
      </button>
    </div>

    <div v-if="error" class="mb-4 p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-sm text-red-400">{{ error }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Name</th>
            <th class="table-header" v-if="auth.isAdmin">Customer</th>
            <th class="table-header">Mode</th>
            <th class="table-header">Contact</th>
            <th class="table-header">Awaiting</th>
            <th class="table-header">API key</th>
            <th class="table-header text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="v in vendors" :key="v.id" class="hover:bg-gray-50" :class="{ 'opacity-50': !v.is_active }">
            <td class="table-cell font-medium">
              {{ v.name }}
              <span v-if="!v.is_active" class="badge badge-info ml-2">Inactive</span>
            </td>
            <td class="table-cell" v-if="auth.isAdmin">{{ v.customer_name }}</td>
            <td class="table-cell">{{ modeLabel(v.integration_mode) }}</td>
            <td class="table-cell">
              <div>{{ v.email || '—' }}</div>
              <div v-if="v.api_base_url" class="text-xs text-gray-500 truncate max-w-xs">{{ v.api_base_url }}</div>
            </td>
            <td class="table-cell">{{ v.awaiting_count || 0 }}</td>
            <td class="table-cell">
              <template v-if="v.api_key">
                <code class="text-xs">{{ v.api_key.key_prefix }}…</code>
                <div class="text-xs text-gray-500">Last used: {{ formatDate(v.api_key.last_used_at) }}</div>
              </template>
              <span v-else class="text-gray-400 text-sm">None</span>
            </td>
            <td class="table-cell text-right">
              <div class="flex items-center justify-end gap-1" v-if="canManage">
                <button @click="openForm(v)" class="btn btn-sm btn-ghost" title="Edit">
                  <AppIcon name="pencil" :size="14" />
                </button>
                <button v-if="v.is_active && (v.integration_mode !== 'email')" @click="issueKey(v)" class="btn btn-sm btn-ghost" title="Issue new API key">
                  <AppIcon name="key-round" :size="14" />
                </button>
                <button v-if="v.api_key" @click="revokeKey(v)" class="btn btn-sm btn-ghost" title="Revoke API key">
                  <AppIcon name="lock" :size="14" />
                </button>
                <button v-if="v.is_active" @click="deactivate(v)" class="btn btn-sm btn-ghost text-red-400" title="Deactivate">
                  <AppIcon name="trash-2" :size="14" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!vendors.length && !loading">
            <td colspan="7" class="table-cell text-center text-gray-400">No vendors yet.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Vendor form -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeForm">
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700/50">
          <h3 class="text-lg font-semibold text-surface-100">{{ editing ? 'Edit vendor' : 'New vendor' }}</h3>
          <button @click="closeForm" class="text-surface-500 hover:text-surface-300"><AppIcon name="x" :size="18" /></button>
        </div>
        <form @submit.prevent="save" class="px-6 py-4 space-y-4">
          <div v-if="formError" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-sm text-red-400">{{ formError }}</div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Customer *</label>
              <select v-model="form.customer_id" class="select w-full" required :disabled="!!editing">
                <option value="" disabled>Select customer</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Name *</label>
              <input v-model="form.name" class="input w-full" required maxlength="255" />
            </div>
            <div>
              <label class="label">Integration</label>
              <select v-model="form.integration_mode" class="select w-full">
                <option value="email">Email</option>
                <option value="api">API</option>
                <option value="both">API, fall back to email</option>
              </select>
            </div>
            <div>
              <label class="label">Phone</label>
              <input v-model="form.phone" class="input w-full" maxlength="50" />
            </div>
            <div>
              <label class="label">Email{{ needsEmail ? ' *' : '' }}</label>
              <input v-model="form.email" type="email" class="input w-full" :required="needsEmail" />
            </div>
            <div>
              <label class="label">API base URL (https){{ needsApi ? ' *' : '' }}</label>
              <input v-model="form.api_base_url" class="input w-full" placeholder="https://vendor.example.com/api" :required="needsApi" />
            </div>
          </div>
          <div>
            <label class="label">Notes</label>
            <textarea v-model="form.notes" class="input w-full" rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closeForm" class="btn btn-ghost">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">{{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}</button>
          </div>
        </form>
      </div>
    </div>

    <!-- One-time key display -->
    <div v-if="issuedKey" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-lg p-6 space-y-4">
        <h3 class="text-lg font-semibold text-surface-100">API key for {{ issuedKey.vendor }}</h3>
        <p class="text-sm text-amber-400">Copy this key now. It is not stored and cannot be shown again. Issuing a new key revokes this one.</p>
        <code class="block break-all bg-surface-900 border border-surface-700 rounded p-3 text-sm text-surface-100">{{ issuedKey.key }}</code>
        <div class="flex justify-end gap-2">
          <button @click="copyKey" class="btn btn-ghost">{{ copied ? 'Copied' : 'Copy' }}</button>
          <button @click="issuedKey = null" class="btn btn-primary">Done</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/common/AppIcon.vue'

const auth = useAuthStore()
const vendors = ref([])
const customers = ref([])
const loading = ref(true)
const error = ref('')
const formError = ref('')
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const issuedKey = ref(null)
const copied = ref(false)

const canManage = computed(() => auth.isAdmin || auth.isCustomerAdmin)
const emptyForm = () => ({
  customer_id: auth.user?.customer_id || '', name: '', integration_mode: 'email',
  email: '', api_base_url: '', phone: '', notes: '',
})
const form = ref(emptyForm())
const needsEmail = computed(() => ['email', 'both'].includes(form.value.integration_mode))
const needsApi = computed(() => ['api', 'both'].includes(form.value.integration_mode))

const MODE_LABELS = { email: 'Email', api: 'API', both: 'API + email' }
function modeLabel(m) { return MODE_LABELS[m] || m }
function formatDate(d) {
  if (!d) return 'never'
  try { return new Date(d.replace(' ', 'T')).toLocaleString() } catch { return d }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/vendors')
    vendors.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = e?.message || 'Could not load vendors'
  } finally {
    loading.value = false
  }
}

async function loadCustomers() {
  try {
    const { data } = await client.get('/customers')
    customers.value = Array.isArray(data) ? data : []
  } catch { customers.value = [] }
}

function openForm(v = null) {
  formError.value = ''
  editing.value = v ? v.id : null
  form.value = v ? {
    customer_id: v.customer_id, name: v.name, integration_mode: v.integration_mode,
    email: v.email || '', api_base_url: v.api_base_url || '', phone: v.phone || '', notes: v.notes || '',
  } : emptyForm()
  showForm.value = true
}

function closeForm() { showForm.value = false; editing.value = null }

async function save() {
  saving.value = true
  formError.value = ''
  const payload = { ...form.value }
  if (!needsEmail.value) payload.email = null
  if (!needsApi.value) payload.api_base_url = null
  try {
    if (editing.value) await client.put(`/vendors/${editing.value}`, payload)
    else await client.post('/vendors', payload)
    closeForm()
    await load()
  } catch (e) {
    formError.value = e?.message || 'Could not save vendor'
  } finally {
    saving.value = false
  }
}

async function issueKey(v) {
  if (!confirm(`Issue a new API key for ${v.name}? Any existing key stops working.`)) return
  try {
    const { data } = await client.post(`/vendors/${v.id}/api-key`)
    issuedKey.value = { vendor: v.name, key: data.api_key }
    copied.value = false
    await load()
  } catch (e) {
    error.value = e?.message || 'Could not issue key'
  }
}

async function revokeKey(v) {
  if (!confirm(`Revoke the API key for ${v.name}?`)) return
  try {
    await client.delete(`/vendors/${v.id}/api-key`)
    await load()
  } catch (e) {
    error.value = e?.message || 'Could not revoke key'
  }
}

async function deactivate(v) {
  if (!confirm(`Deactivate ${v.name}? Its API key is revoked. Existing cases keep the reference.`)) return
  try {
    await client.delete(`/vendors/${v.id}`)
    await load()
  } catch (e) {
    error.value = e?.message || 'Could not deactivate vendor'
  }
}

async function copyKey() {
  try {
    await navigator.clipboard.writeText(issuedKey.value.key)
    copied.value = true
  } catch {
    copied.value = false
  }
}

onMounted(async () => {
  await Promise.all([load(), canManage.value ? loadCustomers() : Promise.resolve()])
})
</script>
