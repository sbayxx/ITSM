<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Audit Log</h1>
    </div>

    <div class="card mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
          <input v-model="filters.search" type="text" placeholder="Search summary or username..." class="input" @input="fetchLogs" />
        </div>
        <div>
          <select v-model="filters.table_name" class="input" @change="fetchLogs">
            <option value="">All Tables</option>
            <option v-for="t in tables" :key="t" :value="t">{{ formatLabel(t) }}</option>
          </select>
        </div>
        <div>
          <select v-model="filters.action" class="input" @change="fetchLogs">
            <option value="">All Actions</option>
            <option v-for="a in actions" :key="a" :value="a">{{ formatLabel(a) }}</option>
          </select>
        </div>
        <div>
          <input v-model="filters.date_from" type="date" class="input" @change="fetchLogs" />
        </div>
        <div>
          <input v-model="filters.date_to" type="date" class="input" @change="fetchLogs" />
        </div>
      </div>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-3 font-medium">Time</th>
            <th class="p-3 font-medium">User</th>
            <th class="p-3 font-medium">Action</th>
            <th class="p-3 font-medium">Table</th>
            <th class="p-3 font-medium">Record ID</th>
            <th class="p-3 font-medium">Summary</th>
            <th class="p-3 font-medium">IP Address</th>
            <th class="p-3 font-medium">Changes</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td colspan="8" class="p-6 text-center text-gray-400">Loading...</td>
          </tr>
          <tr v-else-if="!logs.length">
            <td colspan="8" class="p-6 text-center text-gray-400">No log entries found.</td>
          </tr>
          <template v-for="log in logs" :key="log.id">
            <tr class="border-b hover:bg-gray-50">
              <td class="p-3 text-sm whitespace-nowrap">{{ formatDate(log.created_at) }}</td>
              <td class="p-3">
                <div class="flex items-center gap-2">
                  <span class="w-6 h-6 rounded-full bg-gray-200 text-xs flex items-center justify-center font-medium">{{ (log.username || '?')[0] }}</span>
                  <span class="text-sm">{{ log.username || 'System' }}</span>
                </div>
              </td>
              <td class="p-3">
                <span :class="badgeClass(log.action)" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ formatLabel(log.action) }}</span>
              </td>
              <td class="p-3 text-sm text-gray-600">{{ formatLabel(log.table_name) }}</td>
              <td class="p-3 text-sm text-gray-600">{{ log.record_id || '-' }}</td>
              <td class="p-3 text-sm max-w-xs truncate">{{ log.summary || '-' }}</td>
              <td class="p-3 text-sm text-gray-500 font-mono">{{ log.ip_address || '-' }}</td>
              <td class="p-3">
                <button v-if="log.old_value || log.new_value" @click="toggleDiff(log.id)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                  {{ expandedRows.has(log.id) ? 'Hide' : 'View' }}
                </button>
                <span v-else class="text-xs text-gray-400">-</span>
              </td>
            </tr>
            <tr v-if="expandedRows.has(log.id)" class="bg-gray-50 border-b">
              <td colspan="8" class="p-3">
                <div class="grid grid-cols-2 gap-4 text-sm">
                  <div v-if="log.old_value">
                    <h4 class="font-medium text-red-600 mb-1">Before</h4>
                    <pre class="bg-red-50 border border-red-200 rounded p-2 text-xs overflow-x-auto whitespace-pre-wrap">{{ formatJson(log.old_value) }}</pre>
                  </div>
                  <div v-if="log.new_value">
                    <h4 class="font-medium text-green-600 mb-1">After</h4>
                    <pre class="bg-green-50 border border-green-200 rounded p-2 text-xs overflow-x-auto whitespace-pre-wrap">{{ formatJson(log.new_value) }}</pre>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="flex justify-center items-center gap-4 mt-6">
      <button @click="prevPage" :disabled="meta.page <= 1" class="btn-secondary text-sm" :class="{ 'opacity-50 cursor-not-allowed': meta.page <= 1 }">Previous</button>
      <span class="text-sm text-gray-600">Page {{ meta.page }} of {{ meta.last_page }}</span>
      <button @click="nextPage" :disabled="meta.page >= meta.last_page" class="btn-secondary text-sm" :class="{ 'opacity-50 cursor-not-allowed': meta.page >= meta.last_page }">Next</button>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import client from '../../api/client'

const logs = ref([])
const tables = ref([])
const actions = ref([])
const loading = ref(true)
const expandedRows = ref(new Set())
const meta = ref({ total: 0, page: 1, per_page: 50, last_page: 1 })
const filters = ref({
  search: '',
  table_name: '',
  action: '',
  date_from: '',
  date_to: '',
  page: 1,
})

function formatDate(dateStr) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleString('da-DK', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatJson(val) {
  if (!val) return '-'
  try {
    return JSON.stringify(JSON.parse(val), null, 2)
  } catch {
    return val
  }
}

function badgeClass(action) {
  const map = {
    create: 'bg-green-100 text-green-700',
    update: 'bg-blue-100 text-blue-700',
    delete: 'bg-red-100 text-red-700',
    login: 'bg-gray-100 text-gray-700',
    logout: 'bg-gray-100 text-gray-700',
    escalate: 'bg-yellow-100 text-yellow-700',
    approve: 'bg-emerald-100 text-emerald-700',
    reject: 'bg-rose-100 text-rose-700',
  }
  return map[action] || 'bg-gray-100 text-gray-700'
}

function toggleDiff(id) {
  if (expandedRows.value.has(id)) {
    expandedRows.value.delete(id)
  } else {
    expandedRows.value.add(id)
  }
}

async function fetchLogs() {
  loading.value = true
  try {
    const params = { ...filters.value, page: meta.value.page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const res = await client.get('/audit-logs', { params })
    logs.value = res.data || []
    meta.value = res.meta || { total: 0, page: 1, per_page: 50, last_page: 1 }
  } catch (e) {
    logs.value = []
    meta.value = { total: 0, page: 1, per_page: 50, last_page: 1 }
    console.error('Failed to fetch audit logs:', e)
  } finally {
    loading.value = false
  }
}

async function fetchFilterOptions() {
  try {
    const [tRes, aRes] = await Promise.all([
      client.get('/audit-log-tables'),
      client.get('/audit-log-actions'),
    ])
    tables.value = tRes.data || []
    actions.value = aRes.data || []
  } catch {}
}

function prevPage() {
  if (meta.value.page > 1) {
    meta.value.page--
    fetchLogs()
  }
}

function nextPage() {
  if (meta.value.page < meta.value.last_page) {
    meta.value.page++
    fetchLogs()
  }
}

onMounted(() => {
  fetchLogs()
  fetchFilterOptions()
})
</script>
