<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'

const router = useRouter()
const saving = ref(false)
const error = ref('')
const ciTypes = ref([])

const form = ref({
  name: '',
  type: 'server',
  ci_type_id: '',
  status: 'active',
  serial_number: '',
  asset_tag: '',
  vendor: '',
  model: '',
  fqdn: '',
  ip_address: '',
  mac_address: '',
  version: '',
  location: '',
  department: '',
  support_group: '',
  business_service: '',
  environment: 'production',
  os: '',
  os_version: '',
  cpu: '',
  ram: '',
  disk_space: '',
  purchase_date: '',
  warranty_end: '',
  owner_id: '',
  description: ''
})

const environments = ['production', 'staging', 'development', 'testing', 'disaster_recovery']

async function fetchTypes() {
  try {
    const res = await client.get('/ci-types')
    ciTypes.value = res.data || []
  } catch {
    ciTypes.value = []
  }
}

async function submit() {
  saving.value = true
  error.value = ''
  const payload = { ...form.value }
  if (!payload.ci_type_id) delete payload.ci_type_id
  if (!payload.owner_id) delete payload.owner_id
  if (!payload.purchase_date) delete payload.purchase_date
  if (!payload.warranty_end) delete payload.warranty_end
  try {
    await client.post('/ci-items', payload)
    router.push('/cmdb')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to create CI item.'
  } finally {
    saving.value = false
  }
}

onMounted(fetchTypes)
</script>

<template>
  <div class="p-4 md:p-6 max-w-4xl">
    <h1 class="text-2xl font-bold mb-6">New CI Item</h1>

    <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

    <form @submit.prevent="submit" class="card p-6 space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Name *</label>
          <input v-model="form.name" class="input w-full" required />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Type</label>
          <select v-model="form.type" class="input w-full">
            <option v-for="t in ['server','network','application','storage','database','workstation','mobile','peripheral','virtual','security','other']" :key="t" :value="t">{{ t }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">CI Type (detailed)</label>
          <select v-model="form.ci_type_id" class="input w-full">
            <option value="">Select...</option>
            <option v-for="t in ciTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="form.status" class="input w-full">
            <option value="active">Active</option>
            <option value="maintenance">Maintenance</option>
            <option value="retired">Retired</option>
            <option value="decommissioned">Decommissioned</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Environment</label>
          <select v-model="form.environment" class="input w-full">
            <option v-for="e in environments" :key="e" :value="e">{{ e }}</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Serial Number</label>
          <input v-model="form.serial_number" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Asset Tag</label>
          <input v-model="form.asset_tag" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">FQDN</label>
          <input v-model="form.fqdn" class="input w-full" placeholder="host.example.com" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Vendor</label>
          <input v-model="form.vendor" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Model</label>
          <input v-model="form.model" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Version</label>
          <input v-model="form.version" class="input w-full" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">IP Address</label>
          <input v-model="form.ip_address" class="input w-full" placeholder="10.0.0.1" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">MAC Address</label>
          <input v-model="form.mac_address" class="input w-full" placeholder="00:1A:2B:3C:4D:5E" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Business Service</label>
          <input v-model="form.business_service" class="input w-full" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">OS</label>
          <input v-model="form.os" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">OS Version</label>
          <input v-model="form.os_version" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Location</label>
          <input v-model="form.location" class="input w-full" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">CPU</label>
          <input v-model="form.cpu" class="input w-full" placeholder="8 vCPU" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">RAM</label>
          <input v-model="form.ram" class="input w-full" placeholder="32 GB" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Disk Space</label>
          <input v-model="form.disk_space" class="input w-full" placeholder="500 GB" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Purchase Date</label>
          <input v-model="form.purchase_date" type="date" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Warranty End</label>
          <input v-model="form.warranty_end" type="date" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Department</label>
          <input v-model="form.department" class="input w-full" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Support Group</label>
          <input v-model="form.support_group" class="input w-full" placeholder="IT Operations" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Owner ID</label>
          <input v-model="form.owner_id" type="number" class="input w-full" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea v-model="form.description" class="input w-full" rows="3"></textarea>
      </div>

      <div class="flex gap-3 pt-4">
        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Create CI Item' }}</button>
        <button type="button" class="btn-secondary" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>
