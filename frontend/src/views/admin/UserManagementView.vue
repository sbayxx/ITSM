<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
      <div class="flex gap-2">
        <button @click="showSyncModal = true" class="btn-secondary">Sync from Azure</button>
        <button @click="openCreateModal" class="btn-primary">Add User</button>
      </div>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <input v-model="search" type="text" placeholder="Search by name or email..." class="input" @input="fetchUsers" />
        </div>
        <div>
          <select v-model="roleFilter" class="select" @change="fetchUsers">
            <option value="">All Roles</option>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="agent">Agent</option>
            <option value="user">User</option>
          </select>
        </div>
      </div>
    </div>

    <div v-if="syncMessage" class="mb-4 p-3 rounded-lg text-sm" :class="syncMessage.startsWith('Error') ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200'">{{ syncMessage }}</div>

    <div class="card p-0 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Name</th>
            <th class="table-header">Email</th>
            <th class="table-header">Role</th>
            <th class="table-header">Customer</th>
            <th class="table-header">Active</th>
            <th class="table-header">Last Login</th>
            <th class="table-header">Created</th>
            <th class="table-header">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50">
            <td class="table-cell font-medium">{{ user.name }}</td>
            <td class="table-cell text-gray-500">{{ user.email }}</td>
            <td class="table-cell">
              <span class="badge" :class="roleBadge(user.role)">{{ formatLabel(user.role) }}</span>
            </td>
            <td class="table-cell text-gray-500 text-sm">{{ user.customer_name || '-' }}</td>
            <td class="table-cell">
              <span class="badge" :class="user.is_active ? 'badge-low' : 'badge-critical'">{{ user.is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="table-cell text-gray-500">{{ user.last_login ? formatDate(user.last_login) : '-' }}</td>
            <td class="table-cell text-gray-500">{{ formatDate(user.created_at) }}</td>
            <td class="table-cell">
              <button @click="openEditModal(user)" class="btn-secondary btn-sm mr-2">Edit</button>
              <button @click="confirmDelete(user)" class="btn-danger btn-sm">Delete</button>
            </td>
          </tr>
          <tr v-if="!users.length">
            <td colspan="8" class="table-cell text-center text-gray-400">No users found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeModal">
      <div class="card w-full max-w-md mx-4">
        <h2 class="text-lg font-bold mb-4">{{ editing ? 'Edit User' : 'Add User' }}</h2>
        <form @submit.prevent="saveUser">
          <div class="space-y-4">
            <div>
              <label class="label">Name</label>
              <input v-model="form.name" type="text" class="input" required />
            </div>
    <div>
      <label class="label">Email</label>
      <input v-model="form.email" type="email" class="input" required />
    </div>
    <div>
      <label class="label">{{ editing ? 'New Password (leave blank to keep)' : 'Password' }}</label>
      <input v-model="form.password" type="password" class="input" :required="!editing" />
    </div>
    <div>
      <label class="label">Role</label>
      <select v-model="form.role" class="select" required>
        <option value="user">User</option>
        <option value="agent">Agent</option>
        <option value="manager">Manager</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div>
      <label class="label">Primary Customer</label>
      <select v-model="form.customer_id" class="select">
        <option :value="null">None</option>
        <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
    </div>
    <div>
      <label class="label">Multi-Customer Access</label>
      <div class="space-y-1.5 max-h-40 overflow-y-auto border border-gray-200 rounded-lg p-2">
        <div v-for="c in customers" :key="c.id" class="flex items-center gap-2">
          <input type="checkbox" :value="c.id" v-model="form.assigned_customer_ids"
            class="rounded border-gray-300" />
          <label class="text-sm text-gray-700 cursor-pointer">{{ c.name }}</label>
        </div>
      </div>
      <p class="text-xs text-gray-400 mt-1">Agents/managers checked here can access multiple customers.</p>
    </div>
    <div class="flex items-center gap-2">
      <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded border-gray-300" :true-value="true" :false-value="false" />
      <label for="is_active" class="text-sm text-gray-700">Active</label>
    </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeModal" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showDeleteConfirm = false">
      <div class="card w-full max-w-sm mx-4">
        <h2 class="text-lg font-bold text-red-600 mb-2">Delete User</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong>{{ deletingUser?.name }}</strong>? This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="showDeleteConfirm = false" class="btn-secondary">Cancel</button>
          <button @click="deleteUser" class="btn-danger" :disabled="deleting">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>

    <div v-if="showSyncModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showSyncModal = false">
      <div class="card w-full max-w-md mx-4">
        <h2 class="text-lg font-bold mb-2">Sync Users from Azure AD</h2>
        <p class="text-sm text-gray-500 mb-4">Choose which Azure AD users to import into the local user database.</p>

        <div class="space-y-3">
          <div class="p-3 rounded-lg border border-gray-200">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="syncFilters.licensed_only" class="rounded border-gray-300" />
              <div>
                <div class="text-sm font-medium text-gray-900">Licensed users only</div>
                <div class="text-xs text-gray-500">Only import users with an active license assigned</div>
              </div>
            </label>
          </div>

          <div class="p-3 rounded-lg border border-gray-200">
            <label class="flex items-center gap-3 cursor-pointer">
              <input type="checkbox" v-model="syncFilters.enabled_only" class="rounded border-gray-300" />
              <div>
                <div class="text-sm font-medium text-gray-900">Active accounts only</div>
                <div class="text-xs text-gray-500">Only import users whose account is enabled in Azure AD</div>
              </div>
            </label>
          </div>

          <div>
            <label class="label text-xs">User type</label>
            <select v-model="syncFilters.user_type" class="input">
              <option value="">All user types</option>
              <option value="Member">Member</option>
              <option value="Guest">Guest</option>
            </select>
          </div>

          <div>
            <label class="label text-xs">Department</label>
            <input v-model="syncFilters.department" type="text" class="input" placeholder="e.g. Engineering, Sales..." />
          </div>
        </div>

        <div class="flex justify-end gap-3 mt-6">
          <button @click="showSyncModal = false" class="btn-secondary">Cancel</button>
          <button @click="syncAzure" class="btn-primary" :disabled="syncing">{{ syncing ? 'Syncing...' : 'Start Sync' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import client from '../../api/client'

const users = ref([])
const customers = ref([])
const search = ref('')
const roleFilter = ref('')
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const showDeleteConfirm = ref(false)
const deletingUser = ref(null)
const deleting = ref(false)
const syncing = ref(false)
const syncMessage = ref('')
const showSyncModal = ref(false)
const syncFilters = ref({
  licensed_only: true,
  enabled_only: false,
  user_type: '',
  department: '',
})
const form = ref({ name: '', email: '', password: '', role: 'user', is_active: true, customer_id: null, assigned_customer_ids: [] })

function roleBadge(role) {
  return { admin: 'badge-critical', manager: 'badge-medium', agent: 'badge-high', user: 'badge-low' }[role] || 'badge-low'
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

async function fetchUsers() {
  try {
    const params = {}
    if (search.value) params.search = search.value
    if (roleFilter.value) params.role = roleFilter.value
    const res = await client.get('/users', { params })
    users.value = res.data || res
  } catch { /* ignore */ }
}

function openCreateModal() {
  editing.value = null
  form.value = { name: '', email: '', password: '', role: 'user', is_active: true, customer_id: null, assigned_customer_ids: [] }
  showModal.value = true
}

async function openEditModal(user) {
  editing.value = user
  form.value = { name: user.name, email: user.email, role: user.role, is_active: !!user.is_active, password: '', customer_id: user.customer_id, assigned_customer_ids: [] }
  try {
    const res = await client.get(`/users/${user.id}/customers`)
    if (res.data?.assigned_customers) {
      form.value.assigned_customer_ids = res.data.assigned_customers.map(c => c.id)
    }
  } catch { /* ignore */ }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
}

async function saveUser() {
  saving.value = true
  try {
    const payload = { ...form.value }
    delete payload.assigned_customer_ids
    if (editing.value && !payload.password) delete payload.password
    if (editing.value) {
      await client.put(`/users/${editing.value.id}`, payload)
    } else {
      const res = await client.post('/users', payload)
      editing.value = { id: res.data?.id }
    }
    if (editing.value) {
      await client.put(`/users/${editing.value.id}/customers`, {
        assigned_customer_ids: form.value.assigned_customer_ids,
      })
    }
    closeModal()
    await fetchUsers()
  } finally {
    saving.value = false
  }
}

function confirmDelete(user) {
  deletingUser.value = user
  showDeleteConfirm.value = true
}

async function deleteUser() {
  deleting.value = true
  try {
    await client.delete(`/users/${deletingUser.value.id}`)
    showDeleteConfirm.value = false
    deletingUser.value = null
    await fetchUsers()
  } finally {
    deleting.value = false
  }
}

async function syncAzure() {
  syncing.value = true
  syncMessage.value = ''
  try {
    const res = await client.post('/users/sync-azure', syncFilters.value)
    syncMessage.value = res?.message || 'Azure sync completed.'
    showSyncModal.value = false
    await fetchUsers()
  } catch (e) {
    syncMessage.value = 'Error: ' + (e?.message || e?.detail || 'Sync failed.')
  } finally {
    syncing.value = false
  }
}

async function fetchCustomers() {
  try {
    const res = await client.get('/customers')
    customers.value = res.data || []
  } catch { /* ignore */ }
}

onMounted(() => {
  fetchUsers()
  fetchCustomers()
})
</script>
