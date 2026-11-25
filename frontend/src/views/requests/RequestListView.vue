<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Service Requests</h1>
      <router-link to="/requests/create" class="btn-primary">New Request</router-link>
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
          <select v-model="filters.status" class="select" @change="fetchRequests(1)">
            <option value="">All Statuses</option>
            <option v-for="s in statuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
          </select>
        </div>
        <div>
          <label class="label">Approval Status</label>
          <select v-model="filters.approval" class="select" @change="fetchRequests(1)">
            <option value="">All</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
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
            <th class="table-header">Status</th>
            <th class="table-header">Approval</th>
            <th class="table-header">Assigned To</th>
            <th class="table-header">Created</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="req in requests" :key="req.id" @click="$router.push(`/requests/${req.id}`)"
            class="cursor-pointer hover:bg-gray-50 transition-colors">
            <td class="table-cell font-mono text-primary-600">#{{ req.ticket_number }}</td>
            <td class="table-cell font-medium text-gray-900 max-w-xs truncate">{{ req.title }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ req.customer_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ req.reported_by_name || '-' }}</td>
            <td class="table-cell text-gray-500 text-sm">{{ req.requester_name || '-' }}</td>
            <td class="table-cell">
              <span class="badge badge-medium">{{ formatLabel(req.status) }}</span>
            </td>
            <td class="table-cell">
              <span v-if="req.approval_status" class="badge" :class="approvalClass(req.approval_status)">
                {{ formatLabel(req.approval_status) }}
              </span>
              <span v-else class="text-gray-400 text-xs">N/A</span>
            </td>
            <td class="table-cell text-gray-600">{{ req.assigned_to_name || 'Unassigned' }}</td>
            <td class="table-cell text-gray-500">{{ formatDate(req.created_at) }}</td>
          </tr>
          <tr v-if="!loading && requests.length === 0">
            <td colspan="9" class="table-cell text-center text-gray-500 py-12">No service requests found.</td>
          </tr>
          <tr v-if="loading">
            <td colspan="9" class="table-cell text-center text-gray-500 py-12">Loading...</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="totalPages > 1" class="flex items-center justify-between mt-6">
      <p class="text-sm text-gray-600">Showing page {{ currentPage }} of {{ totalPages }}</p>
      <div class="flex gap-2">
        <button :disabled="currentPage <= 1" @click="fetchRequests(currentPage - 1)" class="btn btn-secondary btn-sm">
          Previous
        </button>
        <button :disabled="currentPage >= totalPages" @click="fetchRequests(currentPage + 1)" class="btn btn-secondary btn-sm">
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

const requests = ref([])
const loading = ref(false)
const currentPage = ref(1)
const totalPages = ref(1)

const statuses = ['New', 'In Progress', 'Fulfilled', 'Closed', 'Cancelled']

const filters = reactive({
  search: '',
  status: '',
  approval: '',
})

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => fetchRequests(1), 300)
}

async function fetchRequests(page = 1) {
  loading.value = true
  try {
    const params = { type: 'service_request', page, per_page: 20 }
    if (filters.search) params.search = filters.search
    if (filters.status) params.status = filters.status
    if (filters.approval) params.approval_status = filters.approval
    const data = await client.get('/tickets', { params })
    requests.value = data.data || data
    if (data.meta) {
      currentPage.value = data.meta.current_page
      totalPages.value = data.meta.last_page
    } else {
      currentPage.value = page
    }
  } catch (err) {
    console.error('Failed to fetch requests:', err)
  } finally {
    loading.value = false
  }
}

function approvalClass(status) {
  const map = { Approved: 'badge-low', Pending: 'badge-medium', Rejected: 'badge-critical' }
  return map[status] || 'badge-medium'
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

onMounted(() => fetchRequests())
</script>
