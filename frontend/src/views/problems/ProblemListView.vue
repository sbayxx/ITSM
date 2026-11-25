<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Problems</h1>
      <router-link to="/problems/create" class="btn-primary">New Problem</router-link>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <input v-model="filters.search" type="text" placeholder="Search problems..." class="input w-full sm:flex-1" @input="fetchProblems" />
        <select v-model="filters.status" class="select w-full sm:w-48" @change="fetchProblems">
          <option value="">All Statuses</option>
          <option value="open">Open</option>
          <option value="in_progress">In Progress</option>
          <option value="awaiting_vendor">Awaiting vendor</option>
          <option value="resolved">Resolved</option>
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
            <th class="table-header">Priority</th>
            <th class="table-header">Status</th>
            <th class="table-header">Known Error</th>
            <th class="table-header">Created</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="problem in problems" :key="problem.id" class="hover:bg-gray-50 transition-colors cursor-pointer" @click="$router.push(`/problems/${problem.id}`)">
            <td class="table-cell font-medium text-primary-600">{{ problem.ticket_number }}</td>
            <td class="table-cell">{{ problem.title }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ problem.customer_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ problem.reported_by_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ problem.requester_name || '-' }}</td>
            <td class="table-cell">
              <span :class="priorityBadge(problem.priority)">{{ formatLabel(problem.priority) }}</span>
            </td>
            <td class="table-cell">
              <span :class="statusBadge(problem.status)">{{ statusLabel(problem.status) }}</span>
            </td>
            <td class="table-cell">
              <span v-if="problem.known_error" class="badge bg-purple-100 text-purple-800">Known Error</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="table-cell text-gray-500">{{ formatDate(problem.created_at) }}</td>
          </tr>
          <tr v-if="!loading && problems.length === 0">
            <td colspan="9" class="table-cell text-center text-gray-400 py-8">No problems found</td>
          </tr>
          <tr v-if="loading">
            <td colspan="9" class="table-cell text-center text-gray-400 py-8">Loading...</td>
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

const problems = ref([])
const loading = ref(false)
const filters = reactive({
  search: '',
  status: '',
})

onMounted(fetchProblems)

async function fetchProblems() {
  loading.value = true
  try {
    const params = { type: 'problem' }
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    const res = await client.get('/tickets', { params })
    problems.value = res.data
  } catch (err) {
    console.error('Failed to fetch problems:', err)
  } finally {
    loading.value = false
  }
}

function priorityBadge(priority) {
  switch (priority) {
    case 'critical': return 'badge-critical'
    case 'high': return 'badge-high'
    case 'medium': return 'badge-medium'
    case 'low': return 'badge-low'
    default: return 'badge-medium'
  }
}

function statusBadge(status) {
  const map = {
    open: 'badge bg-blue-100 text-blue-800',
    in_progress: 'badge bg-yellow-100 text-yellow-800',
    awaiting_vendor: 'badge bg-amber-100 text-amber-800',
    resolved: 'badge bg-green-100 text-green-800',
    closed: 'badge bg-gray-100 text-gray-800',
  }
  return map[status] || 'badge bg-gray-100 text-gray-800'
}

function statusLabel(status) {
  const map = {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
  }
  return map[status] || formatLabel(status)
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}
</script>
