<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Customers</h1>
      <button v-if="auth.isAdmin" @click="openCreateModal" class="btn-primary">Add Customer</button>
    </div>

    <div class="card mb-6">
      <input v-model="search" type="text" placeholder="Search customers..." class="input" @input="fetchCustomers" />
    </div>

    <div class="card p-0 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Name</th>
            <th class="table-header">Slug</th>
            <th class="table-header">Status</th>
            <th class="table-header">Created</th>
            <th class="table-header">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="customer in customers" :key="customer.id" class="hover:bg-gray-50">
            <td class="table-cell font-medium">{{ customer.name }}</td>
            <td class="table-cell text-gray-500 font-mono text-sm">{{ customer.slug }}</td>
            <td class="table-cell">
              <span class="badge" :class="customer.is_active ? 'badge-low' : 'badge-critical'">{{ customer.is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="table-cell text-gray-500">{{ formatDate(customer.created_at) }}</td>
            <td class="table-cell">
              <router-link :to="`/customers/${customer.id}`" class="btn-secondary btn-sm mr-2">Manage</router-link>
              <button v-if="auth.isAdmin" @click="confirmDelete(customer)" class="btn-danger btn-sm">Delete</button>
            </td>
          </tr>
          <tr v-if="!customers.length">
            <td colspan="5" class="table-cell text-center text-gray-400">No customers found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeCreateModal">
      <div class="card w-full max-w-md mx-4">
        <h2 class="text-lg font-bold mb-4">Add Customer</h2>
        <form @submit.prevent="createCustomer">
          <div class="space-y-4">
            <div>
              <label class="label">Name</label>
              <input v-model="form.name" type="text" class="input" required />
            </div>
            <div>
              <label class="label">Slug <span class="text-xs text-gray-400">(leave blank to auto-generate)</span></label>
              <input v-model="form.slug" type="text" class="input" />
            </div>
            <div>
              <label class="label">Description</label>
              <textarea v-model="form.description" class="input" rows="3"></textarea>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeCreateModal" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Creating...' : 'Create' }}</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showDeleteConfirm = false">
      <div class="card w-full max-w-sm mx-4">
        <h2 class="text-lg font-bold text-red-600 mb-2">Delete Customer</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete <strong>{{ deletingCustomer?.name }}</strong>? This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="showDeleteConfirm = false" class="btn-secondary">Cancel</button>
          <button @click="deleteCustomer" class="btn-danger" :disabled="deleting">{{ deleting ? 'Deleting...' : 'Delete' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import client from '../../api/client'

const auth = useAuthStore()
const customers = ref([])
const search = ref('')
const saving = ref(false)
const deleting = ref(false)
const showCreateModal = ref(false)
const showDeleteConfirm = ref(false)
const deletingCustomer = ref(null)
const form = ref({ name: '', slug: '', description: '' })

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

async function fetchCustomers() {
  try {
    const params = {}
    if (search.value) params.search = search.value
    const res = await client.get('/customers', { params })
    customers.value = res.data || res
  } catch { /* ignore */ }
}

function openCreateModal() {
  form.value = { name: '', slug: '', description: '' }
  showCreateModal.value = true
}

function closeCreateModal() {
  showCreateModal.value = false
  form.value = { name: '', slug: '', description: '' }
}

async function createCustomer() {
  saving.value = true
  try {
    await client.post('/customers', form.value)
    closeCreateModal()
    await fetchCustomers()
  } finally {
    saving.value = false
  }
}

function confirmDelete(customer) {
  deletingCustomer.value = customer
  showDeleteConfirm.value = true
}

async function deleteCustomer() {
  deleting.value = true
  try {
    await client.delete(`/customers/${deletingCustomer.value.id}`)
    showDeleteConfirm.value = false
    deletingCustomer.value = null
    await fetchCustomers()
  } finally {
    deleting.value = false
  }
}

onMounted(fetchCustomers)
</script>
