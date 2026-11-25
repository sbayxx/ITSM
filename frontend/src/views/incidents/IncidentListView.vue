<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Incidents</h1>
      <router-link to="/incidents/create" class="btn-primary">Report Incident</router-link>
    </div>

    <div class="card mb-6">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="label">Search</label>
          <input v-model="filters.search" type="text" class="input" placeholder="Search by title or ticket #..."
            @input="debouncedSearch" />
        </div>
        <div>
          <label class="label">Status</label>
          <select v-model="filters.status" class="select" @change="fetchIncidents(1)">
            <option value="">All Statuses</option>
            <option v-for="s in statuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
          </select>
        </div>
        <div>
          <label class="label">Priority</label>
          <select v-model="filters.priority" class="select" @change="fetchIncidents(1)">
            <option value="">All Priorities</option>
            <option v-for="p in priorities" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
      </div>
    </div>

    <div class="card p-0 overflow-x-auto">
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
            <th class="table-header">Assigned To</th>
            <th class="table-header">Created</th>
            <th class="table-header">SLA</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="incident in incidents" :key="incident.id" @click="$router.push(`/incidents/${incident.id}`)"
            class="cursor-pointer hover:bg-gray-50 transition-colors">
            <td class="table-cell font-mono text-primary-600">#{{ incident.ticket_number }}</td>
            <td class="table-cell font-medium text-gray-900 max-w-xs truncate">{{ incident.title }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ incident.customer_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ incident.reported_by_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ incident.requester_name || '-' }}</td>
            <td class="table-cell">
              <span class="badge" :class="priorityClass(incident.priority)">{{ formatLabel(incident.priority) }}</span>
            </td>
            <td class="table-cell">
              <span class="badge badge-medium">{{ formatLabel(incident.status) }}</span>
            </td>
            <td class="table-cell text-gray-600">{{ incident.assigned_to_name || 'Unassigned' }}</td>
            <td class="table-cell text-gray-500">{{ formatDate(incident.created_at) }}</td>
            <td class="table-cell">
              <span v-if="incident.sla_deadline" class="text-xs font-medium"
                :class="slaClass(incident.sla_deadline)">
                {{ formatDate(incident.sla_deadline) }}
              </span>
              <span v-else class="text-gray-400 text-xs">N/A</span>
            </td>
          </tr>
          <tr v-if="!loading && incidents.length === 0">
            <td colspan="10" class="table-cell text-center text-gray-500 py-12">No incidents found.</td>
          </tr>
          <tr v-if="loading">
            <td colspan="10" class="table-cell text-center text-gray-500 py-12">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages > 1" class="flex items-center justify-between mt-6">
      <p class="text-sm text-gray-600">Showing page {{ currentPage }} of {{ totalPages }}</p>
      <div class="flex gap-2">
        <button :disabled="currentPage <= 1" @click="fetchIncidents(currentPage - 1)" class="btn btn-secondary btn-sm">
          Previous
        </button>
        <button :disabled="currentPage >= totalPages" @click="fetchIncidents(currentPage + 1)" class="btn btn-secondary btn-sm">
          Next
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, reactive, onMounted } from 'vue'
import client from '../../api/client'

const incidents = ref([])
const loading = ref(false)
const currentPage = ref(1)
const totalPages = ref(1)

const statuses = ['New', 'In Progress', 'Resolved', 'Closed', 'On Hold']
const priorities = ['Critical', 'High', 'Medium', 'Low']

const filters = reactive({
  search: '',
  status: '',
  priority: '',
})

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchIncidents(1), 300)
}

async function fetchIncidents(page = 1) {
  loading.value = true
  try {
    const params = { type: 'incident', page, per_page: 20 }
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.priority) params.priority = filters.priority
    const data = await client.get('/tickets', { params })
    incidents.value = data.data || data
    if (data.meta) {
      currentPage.value = data.meta.current_page
      totalPages.value = data.meta.last_page
    } else {
      currentPage.value = page
    }
  } catch (err) {
    console.error('Failed to fetch incidents:', err)
  } finally {
    loading.value = false
  }
}

function priorityClass(priority) {
  const map = { Critical: 'badge-critical', High: 'badge-high', Medium: 'badge-medium', Low: 'badge-low' }
  return map[priority] || 'badge-medium'
}

function slaClass(deadline) {
  const now = new Date()
  const sla = new Date(deadline)
  const diff = sla - now
  if (diff < 0) return 'text-red-600'
  if (diff < 3600000 * 4) return 'text-orange-600'
  return 'text-green-600'
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(() => fetchIncidents())
</script>
