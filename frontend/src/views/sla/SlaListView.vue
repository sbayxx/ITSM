<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">SLA Contracts</h1>
      <button v-if="auth.isAdmin" @click="openCreateModal" class="btn-primary">New SLA Contract</button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      <div class="card">
        <p class="text-sm text-gray-500">Overall Compliance</p>
        <p class="text-2xl font-bold" :class="compliance.overall >= 90 ? 'text-green-600' : 'text-red-600'">{{ compliance.overall }}%</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">Within Response Target</p>
        <p class="text-2xl font-bold text-blue-600">{{ compliance.within_response }}%</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">Within Resolution Target</p>
        <p class="text-2xl font-bold text-blue-600">{{ compliance.within_resolution }}%</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">Breached</p>
        <p class="text-2xl font-bold text-red-600">{{ compliance.breached }}</p>
      </div>
    </div>

    <div class="card p-0 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Name</th>
            <th class="table-header">Priority</th>
            <th class="table-header">Response Target</th>
            <th class="table-header">Resolution Target</th>
            <th class="table-header">Escalation</th>
            <th class="table-header">Active</th>
            <th v-if="auth.isAdmin" class="table-header">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="contract in contracts" :key="contract.id" class="hover:bg-gray-50">
            <td class="table-cell font-medium">{{ contract.name }}</td>
            <td class="table-cell">
              <span class="badge" :class="priorityBadge(contract.priority)">{{ formatLabel(contract.priority) }}</span>
            </td>
            <td class="table-cell">{{ contract.response_target }}h</td>
            <td class="table-cell">{{ contract.resolution_target }}h</td>
            <td class="table-cell">{{ contract.escalation ? `${contract.escalation}h` : '-' }}</td>
            <td class="table-cell">
              <span class="badge" :class="contract.active ? 'badge-low' : 'badge-critical'">{{ contract.active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td v-if="auth.isAdmin" class="table-cell">
              <button @click="openEditModal(contract)" class="btn-secondary btn-sm mr-2">Edit</button>
              <button @click="toggleActive(contract)" class="btn-sm" :class="contract.active ? 'btn-danger' : 'btn-success'">{{ contract.active ? 'Deactivate' : 'Activate' }}</button>
            </td>
          </tr>
          <tr v-if="!contracts.length">
            <td :colspan="auth.isAdmin ? 7 : 6" class="table-cell text-center text-gray-400">No SLA contracts found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="closeModal">
      <div class="card w-full max-w-lg mx-4">
        <h2 class="text-lg font-bold mb-4">{{ editing ? 'Edit SLA Contract' : 'New SLA Contract' }}</h2>
        <form @submit.prevent="saveContract">
          <div class="space-y-4">
            <div>
              <label class="label">Name</label>
              <input v-model="form.name" type="text" class="input" required />
            </div>
            <div>
              <label class="label">Priority</label>
              <select v-model="form.priority" class="select" required>
                <option value="critical">Critical</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">Response Target (hours)</label>
                <input v-model.number="form.response_target" type="number" min="0" step="0.5" class="input" required />
              </div>
              <div>
                <label class="label">Resolution Target (hours)</label>
                <input v-model.number="form.resolution_target" type="number" min="0" step="0.5" class="input" required />
              </div>
            </div>
            <div>
              <label class="label">Escalation (hours)</label>
              <input v-model.number="form.escalation" type="number" min="0" step="0.5" class="input" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.active" type="checkbox" id="active" class="rounded border-gray-300" />
              <label for="active" class="text-sm text-gray-700">Active</label>
            </div>
          </div>
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="closeModal" class="btn-secondary">Cancel</button>
            <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const auth = useAuthStore()
const contracts = ref([])
const compliance = ref({ overall: 0, within_response: 0, within_resolution: 0, breached: 0 })
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({ name: '', priority: 'medium', response_target: 4, resolution_target: 8, escalation: null, active: true })

function priorityBadge(priority) {
  return { critical: 'badge-critical', high: 'badge-high', medium: 'badge-medium', low: 'badge-low' }[priority] || 'badge-low'
}

async function fetchCompliance() {
  try {
    const res = await client.get('/sla/compliance')
    compliance.value = res.data || res
  } catch { /* ignore */ }
}

async function fetchContracts() {
  try {
    const res = await client.get('/sla-contracts')
    contracts.value = res.data || res
  } catch { /* ignore */ }
}

function openCreateModal() {
  editing.value = null
  form.value = { name: '', priority: 'medium', response_target: 4, resolution_target: 8, escalation: null, active: true }
  showModal.value = true
}

function openEditModal(contract) {
  editing.value = contract
  form.value = { ...contract }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editing.value = null
}

async function saveContract() {
  saving.value = true
  try {
    if (editing.value) {
      await client.put(`/sla-contracts/${editing.value.id}`, form.value)
    } else {
      await client.post('/sla-contracts', form.value)
    }
    closeModal()
    await fetchContracts()
  } finally {
    saving.value = false
  }
}

async function toggleActive(contract) {
  try {
    await client.put(`/sla-contracts/${contract.id}`, { active: !contract.active })
    await fetchContracts()
  } catch { /* ignore */ }
}

onMounted(() => {
  fetchCompliance()
  fetchContracts()
})
</script>
