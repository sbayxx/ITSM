<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-900 mb-6">GDPR Compliance Dashboard</h1>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Consent Log</h3>
          <button @click="requestErasure" class="btn-danger btn-sm">Request Erasure</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="table-header">Type</th>
                <th class="table-header">Status</th>
                <th class="table-header">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="log in consentLog" :key="log.id" class="hover:bg-gray-50">
                <td class="table-cell">{{ formatLabel(log.consent_type) }}</td>
                <td class="table-cell">
                  <span class="badge" :class="log.granted ? 'badge-low' : 'badge-critical'">{{ log.granted ? 'Granted' : 'Revoked' }}</span>
                </td>
                <td class="table-cell text-gray-500">{{ formatDate(log.created_at) }}</td>
              </tr>
              <tr v-if="!consentLog.length">
                <td colspan="3" class="table-cell text-center text-gray-400">No consent records found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Erasure Requests</h3>
          <button @click="fetchErasureRequests" class="btn-secondary btn-sm">Refresh</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="table-header">User</th>
                <th class="table-header">Status</th>
                <th class="table-header">Requested</th>
                <th class="table-header">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="req in erasureRequests" :key="req.id" class="hover:bg-gray-50">
                <td class="table-cell">{{ req.user?.name || req.user_id }}</td>
                <td class="table-cell">
                  <span class="badge" :class="statusBadge(req.status)">{{ formatLabel(req.status) }}</span>
                </td>
                <td class="table-cell text-gray-500">{{ formatDate(req.created_at) }}</td>
                <td class="table-cell">
                  <button v-if="req.status === 'pending'" @click="processErasure(req)" class="btn-primary btn-sm">Process</button>
                  <span v-else class="text-xs text-gray-400">-</span>
                </td>
              </tr>
              <tr v-if="!erasureRequests.length">
                <td colspan="4" class="table-cell text-center text-gray-400">No erasure requests.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <div class="card">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Data Retention Policies</h3>
          <button @click="applyRetention" class="btn-primary btn-sm">Apply Retention</button>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="table-header">Data Type</th>
                <th class="table-header">Retention Period</th>
                <th class="table-header">Active</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="policy in retentionPolicies" :key="policy.id" class="hover:bg-gray-50">
                <td class="table-cell">{{ formatLabel(policy.data_type) }}</td>
                <td class="table-cell">{{ policy.retention_days }} days</td>
                <td class="table-cell">
                  <span class="badge" :class="policy.active ? 'badge-low' : 'badge-critical'">{{ policy.active ? 'Active' : 'Inactive' }}</span>
                </td>
              </tr>
              <tr v-if="!retentionPolicies.length">
                <td colspan="3" class="table-cell text-center text-gray-400">No retention policies defined.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card flex flex-col items-center justify-center text-center">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Data Export</h3>
        <p class="text-sm text-gray-500 mb-4">Download all personal data stored about you in JSON format.</p>
        <button @click="exportData" class="btn-primary" :disabled="exporting">{{ exporting ? 'Exporting...' : 'Export My Data' }}</button>
      </div>
    </div>

    <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showExportModal = false">
      <div class="card w-full max-w-2xl mx-4 max-h-[80vh] flex flex-col">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800">Your Data Export</h3>
          <button @click="showExportModal = false" class="text-gray-400 hover:text-gray-600"><AppIcon name="x" :size="18" /></button>
        </div>
        <pre class="flex-1 overflow-auto bg-gray-50 rounded-lg p-4 text-xs text-gray-700 border border-gray-200">{{ exportJson }}</pre>
        <div class="flex justify-end mt-4">
          <button @click="showExportModal = false" class="btn-secondary">Close</button>
        </div>
      </div>
    </div>

    <div v-if="showErasureConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showErasureConfirm = false">
      <div class="card w-full max-w-sm mx-4">
        <h2 class="text-lg font-bold text-red-600 mb-2">Request Account Erasure</h2>
        <p class="text-sm text-gray-600 mb-4">This will permanently delete your account and all associated data. This action cannot be undone.</p>
        <div class="flex justify-end gap-3">
          <button @click="showErasureConfirm = false" class="btn-secondary">Cancel</button>
          <button @click="submitErasureRequest" class="btn-danger" :disabled="erasureSubmitting">{{ erasureSubmitting ? 'Submitting...' : 'Confirm Erasure' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import AppIcon from '../../components/common/AppIcon.vue'
import { ref, onMounted } from 'vue'
import client from '../../api/client'

const consentLog = ref([])
const erasureRequests = ref([])
const retentionPolicies = ref([])
const showExportModal = ref(false)
const exportJson = ref('')
const exporting = ref(false)
const showErasureConfirm = ref(false)
const erasureSubmitting = ref(false)

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

function statusBadge(status) {
  return { pending: 'badge-medium', processing: 'badge-high', completed: 'badge-low', failed: 'badge-critical' }[status] || 'badge-low'
}

async function fetchConsentLog() {
  try {
    const res = await client.get('/gdpr/consent-log')
    consentLog.value = res.data || res
  } catch { /* ignore */ }
}

async function fetchErasureRequests() {
  try {
    const res = await client.get('/gdpr/erasure-requests')
    erasureRequests.value = res.data || res
  } catch { /* ignore */ }
}

async function fetchRetentionPolicies() {
  try {
    const res = await client.get('/gdpr/retention-policies')
    retentionPolicies.value = res.data || res
  } catch { /* ignore */ }
}

async function exportData() {
  exporting.value = true
  try {
    const res = await client.get('/gdpr/export')
    exportJson.value = JSON.stringify(res.data || res, null, 2)
    showExportModal.value = true
  } finally {
    exporting.value = false
  }
}

function requestErasure() {
  showErasureConfirm.value = true
}

async function submitErasureRequest() {
  erasureSubmitting.value = true
  try {
    await client.post('/gdpr/erasure-requests')
    showErasureConfirm.value = false
    await fetchErasureRequests()
  } finally {
    erasureSubmitting.value = false
  }
}

async function processErasure(req) {
  try {
    await client.put(`/gdpr/erasure-requests/${req.id}`, { status: 'processing' })
    await fetchErasureRequests()
  } catch { /* ignore */ }
}

async function applyRetention() {
  try {
    await client.post('/gdpr/apply-retention')
    await fetchRetentionPolicies()
  } catch { /* ignore */ }
}

onMounted(() => {
  fetchConsentLog()
  fetchErasureRequests()
  fetchRetentionPolicies()
})
</script>
