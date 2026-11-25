<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import client from '../../api/client'

const devices = ref([])
const loading = ref(true)
const syncing = ref(false)
const error = ref('')
const search = ref('')
const filterOs = ref('')
const filterCompliance = ref('')
const filterDeviceType = ref('')
const filterOptions = ref({ operating_systems: [], device_types: [] })
const summary = ref({ compliance: [], os_breakdown: [], total_devices: 0, last_synced_at: null })
const showSummary = ref(false)

const complianceStates = computed(() => [...new Set(devices.value.map(d => d.compliance_state).filter(Boolean))])

const filtered = computed(() =>
  devices.value.filter(d => {
    if (filterOs.value && d.operating_system !== filterOs.value) return false
    if (filterCompliance.value && d.compliance_state !== filterCompliance.value) return false
    if (filterDeviceType.value && d.device_type !== filterDeviceType.value) return false
    if (search.value) {
      const q = search.value.toLowerCase()
      return (d.device_name && d.device_name.toLowerCase().includes(q)) ||
        (d.user_display_name && d.user_display_name.toLowerCase().includes(q)) ||
        (d.user_principal_name && d.user_principal_name.toLowerCase().includes(q)) ||
        (d.serial_number && d.serial_number.toLowerCase().includes(q)) ||
        (d.model && d.model.toLowerCase().includes(q))
    }
    return true
  })
)

const complianceBadge = (state) => {
  if (state === 'compliant') return 'badge-success'
  if (state === 'noncompliant') return 'badge-warning'
  if (state === 'conflict') return 'badge-critical'
  if (state === 'error') return 'badge-danger'
  return 'badge-info'
}

async function fetchDevices() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/intune/devices')
    devices.value = data || []
  } catch (e) {
    error.value = e?.detail || 'Failed to load devices.'
  } finally {
    loading.value = false
  }
}

async function fetchFilters() {
  try {
    const { data } = await client.get('/intune/filters')
    filterOptions.value = data || { operating_systems: [], device_types: [] }
  } catch {}
}

async function fetchSummary() {
  try {
    const { data } = await client.get('/intune/summary')
    summary.value = data || { compliance: [], os_breakdown: [], total_devices: 0, last_synced_at: null }
  } catch {}
}

async function syncDevices() {
  syncing.value = true
  error.value = ''
  try {
    await client.post('/intune/sync')
    await Promise.all([fetchDevices(), fetchSummary(), fetchFilters()])
  } catch (e) {
    error.value = e?.message || e?.detail || 'Sync failed.'
  } finally {
    syncing.value = false
  }
}

async function clearDevices() {
  if (!confirm('Clear all Intune devices from local cache?')) return
  error.value = ''
  try {
    await client.post('/intune/clear')
    devices.value = []
    summary.value = { compliance: [], os_breakdown: [], total_devices: 0, last_synced_at: null }
  } catch (e) {
    error.value = e?.detail || 'Failed to clear devices.'
  }
}

onMounted(async () => {
  await Promise.all([fetchDevices(), fetchSummary(), fetchFilters()])
})
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Intune Managed Devices</h1>
      <div class="flex gap-2">
        <button class="btn-secondary" @click="showSummary = !showSummary">
          {{ showSummary ? 'Hide Summary' : 'Summary' }}
        </button>
        <button class="btn-primary" :disabled="syncing" @click="syncDevices">
          {{ syncing ? 'Syncing...' : 'Sync from Intune' }}
        </button>
        <button class="btn-danger" @click="clearDevices">Clear Cache</button>
      </div>
    </div>

    <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

    <div v-if="showSummary && summary.total_devices" class="card p-4 mb-6">
      <h2 class="text-lg font-semibold mb-3">Summary</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="text-center p-3 bg-surface-800 rounded-lg">
          <div class="text-2xl font-bold text-primary-400">{{ summary.total_devices }}</div>
          <div class="text-sm text-gray-400">Total Devices</div>
        </div>
        <div class="text-center p-3 bg-surface-800 rounded-lg">
          <div class="text-2xl font-bold text-green-400">
            {{ summary.compliance.find(c => c.compliance_state === 'compliant')?.count || 0 }}
          </div>
          <div class="text-sm text-gray-400">Compliant</div>
        </div>
        <div class="text-center p-3 bg-surface-800 rounded-lg">
          <div class="text-sm text-gray-400">
            Last synced: {{ summary.last_synced_at || 'Never' }}
          </div>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <h3 class="text-sm font-semibold text-gray-400 mb-2">Compliance Breakdown</h3>
          <div v-for="c in summary.compliance" :key="c.compliance_state" class="flex items-center gap-2 mb-1">
            <span class="w-24 text-sm">{{ formatLabel(c.compliance_state) || 'Unknown' }}</span>
            <div class="flex-1 h-4 bg-surface-700 rounded overflow-hidden">
              <div class="h-full rounded" :class="'bg-' + (c.compliance_state === 'compliant' ? 'green' : c.compliance_state === 'noncompliant' ? 'yellow' : 'red') + '-500'" :style="{ width: (c.count / Math.max(...summary.compliance.map(x => x.count)) * 100) + '%' }"></div>
            </div>
            <span class="text-sm w-8 text-right">{{ c.count }}</span>
          </div>
        </div>
        <div>
          <h3 class="text-sm font-semibold text-gray-400 mb-2">OS Breakdown</h3>
          <div v-for="o in summary.os_breakdown.slice(0, 10)" :key="o.os_label" class="flex items-center gap-2 mb-1">
            <span class="w-40 text-sm truncate">{{ o.os_label || 'Unknown' }}</span>
            <div class="flex-1 h-4 bg-surface-700 rounded overflow-hidden">
              <div class="h-full bg-primary-500 rounded" :style="{ width: (o.count / Math.max(...summary.os_breakdown.slice(0, 10).map(x => x.count)) * 100) + '%' }"></div>
            </div>
            <span class="text-sm w-8 text-right">{{ o.count }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="flex gap-4 mb-4 flex-wrap">
      <input v-model="search" class="input w-64" placeholder="Search name, user, serial, model..." />
      <select v-model="filterOs" class="input w-40">
        <option value="">All OS</option>
        <option v-for="os in filterOptions.operating_systems" :key="os" :value="os">{{ os }}</option>
      </select>
      <select v-model="filterCompliance" class="input w-40">
        <option value="">All Compliance</option>
        <option v-for="s in complianceStates" :key="s" :value="s">{{ formatLabel(s) }}</option>
      </select>
      <select v-model="filterDeviceType" class="input w-40">
        <option value="">All Types</option>
        <option v-for="t in filterOptions.device_types" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-3 font-medium">Device Name</th>
            <th class="p-3 font-medium">OS</th>
            <th class="p-3 font-medium">Type</th>
            <th class="p-3 font-medium">Compliance</th>
            <th class="p-3 font-medium">User</th>
            <th class="p-3 font-medium">Model</th>
            <th class="p-3 font-medium">Serial</th>
            <th class="p-3 font-medium">Last Sync</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="p-6 text-center text-gray-400">Loading...</td></tr>
          <tr v-else-if="!filtered.length"><td colspan="8" class="p-6 text-center text-gray-400">No devices found. Click "Sync from Intune" to fetch data.</td></tr>
          <tr v-for="device in filtered" :key="device.id" class="border-b hover:bg-gray-50 cursor-pointer">
            <td class="p-3 font-medium">{{ device.device_name || '-' }}</td>
            <td class="p-3 text-sm">{{ device.operating_system || '-' }}{{ device.os_version ? ' ' + device.os_version : '' }}</td>
            <td class="p-3 text-sm">{{ device.device_type || '-' }}</td>
            <td class="p-3"><span :class="'text-xs px-2 py-0.5 rounded ' + complianceBadge(device.compliance_state)">{{ formatLabel(device.compliance_state) || 'Unknown' }}</span></td>
            <td class="p-3 text-sm">{{ device.user_display_name || device.user_principal_name || '-' }}</td>
            <td class="p-3 text-sm">{{ device.model || '-' }}</td>
            <td class="p-3 text-sm">{{ device.serial_number || '-' }}</td>
            <td class="p-3 text-sm text-gray-400">{{ device.last_sync_date_time || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
