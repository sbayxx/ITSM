<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Changes</h1>
      <router-link to="/changes/create" class="btn-primary">New Change</router-link>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <select v-model="filters.change_type" class="select w-full sm:w-48" @change="fetchChanges">
          <option value="">All Types</option>
          <option value="normal">Normal</option>
          <option value="standard">Standard</option>
          <option value="emergency">Emergency</option>
        </select>
        <select v-model="filters.status" class="select w-full sm:w-48" @change="fetchChanges">
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="pending_approval">Pending Approval</option>
          <option value="approved">Approved</option>
          <option value="in_progress">In Progress</option>
          <option value="awaiting_vendor">Awaiting vendor</option>
          <option value="implemented">Implemented</option>
          <option value="reviewed">Reviewed</option>
          <option value="closed">Closed</option>
        </select>
      </div>
    </div>

    <div class="card overflow-x-auto p-0">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Ticket#</th>
            <th class="table-header">Title</th>
            <th class="table-header">Customer</th>
            <th class="table-header">Reporter</th>
            <th class="table-header">Requester</th>
            <th class="table-header">Change Type</th>
            <th class="table-header">Risk Level</th>
            <th class="table-header">CAB Status</th>
            <th class="table-header">Scheduled</th>
            <th class="table-header">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="change in changes" :key="change.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="$router.push(`/changes/${change.id}`)">
            <td class="table-cell font-medium text-primary-600">{{ change.ticket_number }}</td>
            <td class="table-cell">{{ change.title }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ change.customer_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ change.reported_by_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ change.requester_name || '-' }}</td>
            <td class="table-cell">
              <span :class="changeTypeBadge(change.change_type)">{{ formatLabel(change.change_type) }}</span>
            </td>
            <td class="table-cell">
              <span :class="riskBadge(change.risk_level)">{{ change.risk_level }}</span>
            </td>
            <td class="table-cell">
              <span :class="cabStatusBadge(change.cab_status)">{{ formatLabel(change.cab_status) || '—' }}</span>
            </td>
            <td class="table-cell text-sm text-gray-500">{{ formatDate(change.scheduled_start) || '—' }}</td>
            <td class="table-cell">
              <span :class="statusBadge(change.status)">{{ statusLabel(change.status) }}</span>
            </td>
          </tr>
          <tr v-if="!loading && changes.length === 0">
            <td colspan="10" class="table-cell text-center text-gray-400 py-8">No changes found</td>
          </tr>
          <tr v-if="loading">
            <td colspan="10" class="table-cell text-center text-gray-400 py-8">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, reactive, onMounted } from 'vue'
import client from '../../api/client'

const changes = ref([])
const loading = ref(false)
const filters = reactive({
  change_type: '',
  status: '',
})

onMounted(fetchChanges)

async function fetchChanges() {
  loading.value = true
  try {
    const params = { type: 'change' }
    if (filters.change_type) params.change_type = filters.change_type
    if (filters.status) params.status = filters.status
    const res = await client.get('/tickets', { params })
    changes.value = res.data
  } catch (err) {
    console.error('Failed to fetch changes:', err)
  } finally {
    loading.value = false
  }
}

function changeTypeBadge(type) {
  const map = {
    normal: 'badge bg-blue-100 text-blue-800',
    standard: 'badge bg-green-100 text-green-800',
    emergency: 'badge bg-red-100 text-red-800',
  }
  return map[type] || 'badge bg-gray-100 text-gray-800'
}

function riskBadge(risk) {
  switch (risk) {
    case 'high': return 'badge-critical'
    case 'medium': return 'badge-high'
    case 'low': return 'badge-low'
    default: return 'badge-medium'
  }
}

function cabStatusBadge(status) {
  const map = {
    pending: 'badge bg-yellow-100 text-yellow-800',
    approved: 'badge bg-green-100 text-green-800',
    rejected: 'badge bg-red-100 text-red-800',
  }
  return map[status] || 'badge bg-gray-100 text-gray-800'
}

function statusBadge(status) {
  const map = {
    draft: 'badge bg-gray-100 text-gray-800',
    pending_approval: 'badge bg-yellow-100 text-yellow-800',
    approved: 'badge bg-green-100 text-green-800',
    in_progress: 'badge bg-blue-100 text-blue-800',
    awaiting_vendor: 'badge bg-amber-100 text-amber-800',
    implemented: 'badge bg-purple-100 text-purple-800',
    reviewed: 'badge bg-teal-100 text-teal-800',
    closed: 'badge bg-gray-100 text-gray-800',
  }
  return map[status] || 'badge bg-gray-100 text-gray-800'
}

function statusLabel(status) {
  const map = {
    draft: 'Draft',
    pending_approval: 'Pending Approval',
    approved: 'Approved',
    in_progress: 'In Progress',
    implemented: 'Implemented',
    reviewed: 'Reviewed',
    closed: 'Closed',
  }
  return map[status] || formatLabel(status)
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}
</script>
