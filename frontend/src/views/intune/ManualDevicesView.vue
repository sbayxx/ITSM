<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Manual Devices</h1>
        <p class="text-sm text-gray-500 mt-1">Manually registered equipment per customer. These records persist and are not overwritten by auto-discovery.</p>
      </div>
      <button @click="openForm()" class="btn btn-primary gap-1.5">
        <AppIcon name="plus" :size="16" />
        Add Device
      </button>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
      <div class="flex flex-wrap gap-3 items-center">
        <select v-model="filterCustomer" @change="fetchDevices" class="select w-auto" v-if="auth.isAdmin || auth.isCustomerAdmin">
          <option value="">All Customers</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <select v-model="filterType" @change="fetchDevices" class="select w-auto">
          <option value="">All Types</option>
          <option v-for="t in deviceTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
        </select>
        <select v-model="filterStatus" @change="fetchDevices" class="select w-auto">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="retired">Retired</option>
          <option value="in_repair">In Repair</option>
        </select>
        <input v-model="search" @input="debouncedFetch" placeholder="Search name, model, serial, IP..." class="input w-64" />
      </div>
    </div>

    <!-- Table -->
    <div class="card p-0 overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="table-header">Name</th>
            <th class="table-header" v-if="auth.isAdmin || auth.isCustomerAdmin">Customer</th>
            <th class="table-header">Type</th>
            <th class="table-header">Manufacturer / Model</th>
            <th class="table-header">Serial Number</th>
            <th class="table-header">Assigned To</th>
            <th class="table-header">Location</th>
            <th class="table-header">Status</th>
            <th class="table-header text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr v-for="d in devices" :key="d.id" class="hover:bg-gray-50">
            <td class="table-cell font-medium">
              <div class="flex items-center gap-2">
                <AppIcon :name="typeIcon(d.device_type)" :size="16" class="text-gray-400" />
                {{ d.name }}
              </div>
            </td>
            <td class="table-cell" v-if="auth.isAdmin || auth.isCustomerAdmin">{{ d.customer_name }}</td>
            <td class="table-cell">
              <span class="badge badge-info">{{ typeLabel(d.device_type) }}</span>
            </td>
            <td class="table-cell">{{ [d.manufacturer, d.model].filter(Boolean).join(' ') || '-' }}</td>
            <td class="table-cell">{{ d.serial_number || '-' }}</td>
            <td class="table-cell">{{ d.assigned_to_name || '-' }}</td>
            <td class="table-cell">{{ d.location || '-' }}</td>
            <td class="table-cell">
              <span class="badge" :class="d.status === 'active' ? 'badge-low' : 'badge-medium'">{{ formatLabel(d.status) }}</span>
            </td>
            <td class="table-cell text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openForm(d)" class="btn btn-sm btn-ghost">
                  <AppIcon name="pencil" :size="14" />
                </button>
                <button @click="deleteDevice(d)" class="btn btn-sm btn-ghost text-red-600">
                  <AppIcon name="trash-2" :size="14" />
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="!devices.length && !loading">
            <td :colspan="auth.isAdmin || auth.isCustomerAdmin ? 9 : 8" class="table-cell text-center text-gray-400">
              No manual devices registered yet.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8 text-gray-400">Loading...</div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeForm">
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700/50">
          <h3 class="text-lg font-semibold text-surface-100">{{ editing ? 'Edit Device' : 'Add Manual Device' }}</h3>
          <button @click="closeForm" class="text-surface-500 hover:text-surface-300">
            <AppIcon name="x" :size="18" />
          </button>
        </div>
        <form @submit.prevent="saveDevice" class="px-6 py-4 space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Customer *</label>
              <select v-model="form.customer_id" class="select w-full" required :disabled="!auth.isAdmin && !auth.isCustomerAdmin">
                <option value="">Select customer</option>
                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Device Name *</label>
              <input v-model="form.name" class="input w-full" required />
            </div>
            <div>
              <label class="label">Device Type</label>
              <select v-model="form.device_type" class="select w-full">
                <option v-for="t in deviceTypes" :key="t" :value="t">{{ typeLabel(t) }}</option>
              </select>
            </div>
            <div>
              <label class="label">Status</label>
              <select v-model="form.status" class="select w-full">
                <option value="active">Active</option>
                <option value="retired">Retired</option>
                <option value="in_repair">In Repair</option>
              </select>
            </div>
            <div>
              <label class="label">Manufacturer</label>
              <input v-model="form.manufacturer" class="input w-full" />
            </div>
            <div>
              <label class="label">Model</label>
              <input v-model="form.model" class="input w-full" />
            </div>
            <div>
              <label class="label">Serial Number</label>
              <input v-model="form.serial_number" class="input w-full" />
            </div>
            <div>
              <label class="label">Operating System</label>
              <input v-model="form.operating_system" class="input w-full" />
            </div>
            <div>
              <label class="label">OS Version</label>
              <input v-model="form.os_version" class="input w-full" />
            </div>
            <div>
              <label class="label">Assigned To</label>
              <select v-model="form.assigned_to" class="select w-full">
                <option value="">Unassigned</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Location</label>
              <input v-model="form.location" class="input w-full" />
            </div>
            <div>
              <label class="label">IP Address</label>
              <input v-model="form.ip_address" class="input w-full" />
            </div>
            <div>
              <label class="label">MAC Address</label>
              <input v-model="form.mac_address" class="input w-full" />
            </div>
          </div>
          <div>
            <label class="label">Notes</label>
            <textarea v-model="form.notes" class="input w-full" rows="3"></textarea>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closeForm" class="btn btn-ghost">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="saving">
              {{ saving ? 'Saving...' : (editing ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/common/AppIcon.vue'

const auth = useAuthStore()
const devices = ref([])
const customers = ref([])
const users = ref([])
const loading = ref(true)
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const search = ref('')
const filterCustomer = ref('')
const filterType = ref('')
const filterStatus = ref('')
const deviceTypes = ['pc', 'mobile', 'printer', 'network', 'other']

const form = ref({
  customer_id: '',
  name: '',
  device_type: 'pc',
  manufacturer: '',
  model: '',
  serial_number: '',
  operating_system: '',
  os_version: '',
  assigned_to: '',
  location: '',
  ip_address: '',
  mac_address: '',
  notes: '',
  status: 'active',
})

const typeLabels = {
  pc: 'PC',
  mobile: 'Mobile',
  printer: 'Printer',
  network: 'Network',
  other: 'Other',
}
const typeIcons = {
  pc: 'monitor',
  mobile: 'smartphone',
  printer: 'printer',
  network: 'wifi',
  other: 'box',
}

function typeLabel(t) { return typeLabels[t] || formatLabel(t) }
function typeIcon(t) { return typeIcons[t] || 'box' }

let searchTimer = null
function debouncedFetch() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchDevices, 300)
}

async function fetchDevices() {
  loading.value = true
  try {
    const params = {}
    if (filterCustomer.value) params.customer_id = filterCustomer.value
    if (filterType.value) params.device_type = filterType.value
    if (filterStatus.value) params.status = filterStatus.value
    if (search.value) params.search = search.value
    const { data } = await client.get('/manual-devices', { params })
    devices.value = (data && data.items) ? data.items : (Array.isArray(data) ? data : [])
  } catch (e) {
    devices.value = []
  } finally {
    loading.value = false
  }
}

async function fetchCustomers() {
  if (auth.isAdmin || auth.isCustomerAdmin) {
    try {
      const { data } = await client.get('/customers')
      customers.value = data || []
    } catch {}
  } else if (auth.user?.customer_id) {
    try {
      const { data } = await client.get('/customers')
      customers.value = (data || []).filter(c => c.id === auth.user.customer_id)
    } catch {}
  }
}

async function fetchUsers() {
  try {
    const { data } = await client.get('/users')
    users.value = Array.isArray(data) ? data : (data?.items || [])
  } catch {}
}

function openForm(device = null) {
  if (device) {
    editing.value = device.id
    form.value = {
      customer_id: device.customer_id,
      name: device.name || '',
      device_type: device.device_type || 'pc',
      manufacturer: device.manufacturer || '',
      model: device.model || '',
      serial_number: device.serial_number || '',
      operating_system: device.operating_system || '',
      os_version: device.os_version || '',
      assigned_to: device.assigned_to || '',
      location: device.location || '',
      ip_address: device.ip_address || '',
      mac_address: device.mac_address || '',
      notes: device.notes || '',
      status: device.status || 'active',
    }
  } else {
    editing.value = null
    form.value = {
      customer_id: auth.user?.customer_id || (customers.value[0]?.id || ''),
      name: '',
      device_type: 'pc',
      manufacturer: '',
      model: '',
      serial_number: '',
      operating_system: '',
      os_version: '',
      assigned_to: '',
      location: '',
      ip_address: '',
      mac_address: '',
      notes: '',
      status: 'active',
    }
  }
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editing.value = null
}

async function saveDevice() {
  saving.value = true
  try {
    const payload = { ...form.value }
    if (payload.assigned_to === '') payload.assigned_to = null
    if (payload.customer_id === '') payload.customer_id = null
    if (editing.value) {
      await client.put(`/manual-devices/${editing.value}`, payload)
    } else {
      await client.post('/manual-devices', payload)
    }
    closeForm()
    await fetchDevices()
  } catch (e) {
    alert(e?.detail || e?.message || 'Failed to save device')
  } finally {
    saving.value = false
  }
}

async function deleteDevice(d) {
  if (!confirm(`Delete "${d.name}"?`)) return
  try {
    await client.delete(`/manual-devices/${d.id}`)
    await fetchDevices()
  } catch (e) {
    alert(e?.detail || e?.message || 'Failed to delete')
  }
}

onMounted(async () => {
  await Promise.all([fetchCustomers(), fetchUsers()])
  await fetchDevices()
})
</script>
