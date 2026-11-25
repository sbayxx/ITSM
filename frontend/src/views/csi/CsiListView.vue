<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'

const router = useRouter()
const items = ref([])
const loading = ref(true)
const filterStatus = ref('')
const filterCategory = ref('')

const categories = computed(() => [...new Set(items.value.map(i => i.category))])
const statuses = computed(() => [...new Set(items.value.map(i => i.status))])

const filtered = computed(() =>
  items.value.filter(i =>
    (!filterStatus.value || i.status === filterStatus.value) &&
    (!filterCategory.value || i.category === filterCategory.value)
  )
)

const kpiSummary = computed(() => {
  const total = items.value.length
  const met = items.value.filter(i => {
    const cv = parseFloat(i.current_value) || 0
    const tv = parseFloat(i.target_value) || 1
    return cv >= tv
  }).length
  const overdue = items.value.filter(i => {
    if (!i.review_date) return false
    return new Date(i.review_date) < new Date()
  }).length
  const onTrack = total - met - overdue
  return { total, met, onTrack, overdue }
})

async function fetchData() {
  loading.value = true
  try {
    const res = await client.get('/csi')
    items.value = res.data
  } finally {
    loading.value = false
  }
}

function newItem() {
  router.push('/csi/new')
}

function viewDetail(id) {
  router.push(`/csi/${id}`)
}

onMounted(fetchData)
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">CSI Register</h1>
      <button class="btn-primary" @click="newItem">New CSI Item</button>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div class="card p-4">
        <div class="text-sm text-gray-500">Total Items</div>
        <div class="text-2xl font-bold">{{ kpiSummary.total }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500">Target Met</div>
        <div class="text-2xl font-bold text-green-600">{{ kpiSummary.met }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500">On Track</div>
        <div class="text-2xl font-bold text-blue-600">{{ kpiSummary.onTrack }}</div>
      </div>
      <div class="card p-4">
        <div class="text-sm text-gray-500">Overdue Review</div>
        <div class="text-2xl font-bold text-red-600">{{ kpiSummary.overdue }}</div>
      </div>
    </div>

    <div class="flex flex-wrap gap-4 mb-4">
      <select v-model="filterCategory" class="input w-48">
        <option value="">All Categories</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="filterStatus" class="input w-48">
        <option value="">All Statuses</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-3 font-medium">Title</th>
            <th class="p-3 font-medium">Customer</th>
            <th class="p-3 font-medium">Category</th>
            <th class="p-3 font-medium">Status</th>
            <th class="p-3 font-medium">Priority</th>
            <th class="p-3 font-medium">Target Value</th>
            <th class="p-3 font-medium">Current Value</th>
            <th class="p-3 font-medium">Assigned To</th>
            <th class="p-3 font-medium">Review Date</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="9" class="p-6 text-center text-gray-400">Loading...</td></tr>
          <tr v-else-if="!filtered.length"><td colspan="9" class="p-6 text-center text-gray-400">No items found.</td></tr>
          <tr v-for="item in filtered" :key="item.id" class="border-b hover:bg-gray-50 cursor-pointer" @click="viewDetail(item.id)">
            <td class="p-3">{{ item.title }}</td>
            <td class="p-3"><span class="badge-default">{{ item.customer_name || '-' }}</span></td>
            <td class="p-3"><span class="badge-info">{{ formatLabel(item.category) }}</span></td>
            <td class="p-3"><span :class="'badge-' + (item.status === 'achieved' ? 'success' : item.status === 'on_track' ? 'info' : item.status === 'behind' ? 'warning' : 'default')">{{ formatLabel(item.status) }}</span></td>
            <td class="p-3"><span :class="'badge-' + (item.priority === 'high' ? 'danger' : item.priority === 'medium' ? 'warning' : 'info')">{{ formatLabel(item.priority) }}</span></td>
            <td class="p-3">{{ item.target_value }}</td>
            <td class="p-3">{{ item.current_value }}</td>
            <td class="p-3">{{ item.assigned_to }}</td>
            <td class="p-3">{{ item.review_date ? new Date(item.review_date).toLocaleDateString() : '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
