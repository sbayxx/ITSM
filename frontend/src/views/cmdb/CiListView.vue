<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import AppIcon from '../../components/common/AppIcon.vue'

const router = useRouter()
const items = ref([])
const loading = ref(true)
const filterType = ref('')
const filterStatus = ref('')
const filterEnvironment = ref('')
const search = ref('')
const environments = ['', 'production', 'staging', 'development', 'testing', 'disaster_recovery']

const types = computed(() => [...new Set(items.value.map(i => i.type))])
const statuses = computed(() => [...new Set(items.value.map(i => i.status))])

const filtered = computed(() =>
  items.value.filter(i => {
    if (filterType.value && i.type !== filterType.value) return false
    if (filterStatus.value && i.status !== filterStatus.value) return false
    if (filterEnvironment.value && i.environment !== filterEnvironment.value) return false
    if (search.value) {
      const q = search.value.toLowerCase()
      return i.name.toLowerCase().includes(q) ||
        i.serial_number?.toLowerCase().includes(q) ||
        i.asset_tag?.toLowerCase().includes(q) ||
        i.fqdn?.toLowerCase().includes(q)
    }
    return true
  })
)

async function fetchData() {
  loading.value = true
  try {
    const res = await client.get('/ci-items')
    items.value = res.data
  } finally {
    loading.value = false
  }
}

function newItem() { router.push('/cmdb/new') }
function viewDetail(id) { router.push(`/cmdb/${id}`) }

onMounted(fetchData)
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Configuration Items</h1>
      <button class="btn-primary" @click="newItem">New CI Item</button>
    </div>

    <div class="flex gap-4 mb-4 flex-wrap">
      <input v-model="search" class="input w-64" placeholder="Search name, serial, tag, fqdn..." />
      <select v-model="filterType" class="input w-40">
        <option value="">All Types</option>
        <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
      </select>
      <select v-model="filterStatus" class="input w-40">
        <option value="">All Statuses</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
      </select>
      <select v-model="filterEnvironment" class="input w-40">
        <option value="">All Environments</option>
        <option v-for="e in environments" :key="e" :value="e" v-if="e">{{ e }}</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-3 font-medium">Name</th>
            <th class="p-3 font-medium">Type</th>
            <th class="p-3 font-medium">Status</th>
            <th class="p-3 font-medium">Environment</th>
            <th class="p-3 font-medium">Location</th>
            <th class="p-3 font-medium">Business Service</th>
            <th class="p-3 font-medium">Asset Tag</th>
            <th class="p-3 font-medium">Version</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="p-6 text-center text-gray-400">Loading...</td></tr>
          <tr v-else-if="!filtered.length"><td colspan="8" class="p-6 text-center text-gray-400">No items found.</td></tr>
          <tr v-for="item in filtered" :key="item.id" class="border-b hover:bg-gray-50 cursor-pointer" @click="viewDetail(item.id)">
            <td class="p-3 font-medium">{{ item.name }}</td>
            <td class="p-3"><span class="badge-info inline-flex items-center gap-1"><AppIcon v-if="item.ci_type_icon" :name="item.ci_type_icon" :size="14" />{{ item.ci_type_name || item.type }}</span></td>
            <td class="p-3"><span :class="'badge-' + (item.status === 'active' ? 'success' : item.status === 'retired' ? 'default' : item.status === 'maintenance' ? 'warning' : 'info')">{{ formatLabel(item.status) }}</span></td>
            <td class="p-3">{{ item.environment || '-' }}</td>
            <td class="p-3">{{ item.location || '-' }}</td>
            <td class="p-3">{{ item.business_service || '-' }}</td>
            <td class="p-3">{{ item.asset_tag || '-' }}</td>
            <td class="p-3">{{ item.version || '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
