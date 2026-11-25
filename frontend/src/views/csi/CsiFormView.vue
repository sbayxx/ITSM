<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const saving = ref(false)
const error = ref('')
const auth = useAuthStore()
const customers = ref([])

const form = ref({
  title: '',
  description: '',
  category: '',
  source: '',
  priority: 'medium',
  expected_benefit: '',
  kpi_measure: '',
  baseline_value: '',
  target_value: '',
  assigned_to: '',
  review_date: '',
  customer_id: ''
})

async function fetchCustomers() {
  try {
    const res = await client.get('/customers')
    customers.value = res.data
  } catch {}
}

async function submit() {
  saving.value = true
  error.value = ''
  try {
    await client.post('/csi', form.value)
    router.push('/csi')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to create CSI item.'
  } finally {
    saving.value = false
  }
}

onMounted(fetchCustomers)
</script>

<template>
  <div class="p-4 md:p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">New CSI Item</h1>

    <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

    <form @submit.prevent="submit" class="card p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Title</label>
        <input v-model="form.title" class="input w-full" required />
      </div>
      <div v-if="auth.isAdmin || auth.isCustomerAdmin">
        <label class="block text-sm font-medium mb-1">Customer</label>
        <select v-model="form.customer_id" class="input w-full">
          <option value="">Select customer...</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea v-model="form.description" class="input w-full" rows="3"></textarea>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Category</label>
          <input v-model="form.category" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Source</label>
          <input v-model="form.source" class="input w-full" />
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Priority</label>
          <select v-model="form.priority" class="input w-full">
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Expected Benefit</label>
          <input v-model="form.expected_benefit" class="input w-full" />
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">KPI Measure</label>
        <input v-model="form.kpi_measure" class="input w-full" />
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Baseline Value</label>
          <input v-model="form.baseline_value" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Target Value</label>
          <input v-model="form.target_value" class="input w-full" />
        </div>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Assigned To</label>
          <input v-model="form.assigned_to" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Review Date</label>
          <input v-model="form.review_date" type="date" class="input w-full" />
        </div>
      </div>
      <div class="flex gap-3 pt-4">
        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Create CSI Item' }}</button>
        <button type="button" class="btn-secondary" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>
