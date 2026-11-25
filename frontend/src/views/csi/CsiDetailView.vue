<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const item = ref(null)
const loading = ref(true)
const saving = ref(false)
const editing = ref(false)
const error = ref('')
const actionInput = ref('')
const customers = ref([])

async function fetchItem() {
  loading.value = true
  try {
    const res = await client.get(`/csi/${route.params.id}`)
    item.value = res.data
  } finally {
    loading.value = false
  }
}

async function fetchCustomers() {
  try {
    const res = await client.get('/customers')
    customers.value = res.data
  } catch {}
}

onMounted(() => {
  fetchItem()
  if (auth.isAdmin || auth.isCustomerAdmin) fetchCustomers()
})

async function updateStatus(status) {
  saving.value = true
  try {
    const res = await client.patch(`/csi/${route.params.id}`, { status })
    item.value = res.data
  } finally {
    saving.value = false
  }
}

async function saveChanges() {
  saving.value = true
  error.value = ''
  try {
    const res = await client.put(`/csi/${route.params.id}`, item.value)
    item.value = res.data
    editing.value = false
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this CSI?')) return
  error.value = ''
  try {
    await client.delete(`/csi/${route.params.id}`)
    router.push('/csi')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to delete.'
  }
}

function addAction() {
  if (!actionInput.value.trim()) return
  if (!item.value.action_items) item.value.action_items = []
  item.value.action_items.push({ text: actionInput.value.trim(), done: false })
  actionInput.value = ''
}

function removeAction(idx) {
  item.value.action_items.splice(idx, 1)
}

</script>

<template>
  <div class="p-4 md:p-6 max-w-4xl">
    <div v-if="loading" class="text-center text-gray-400 py-12">Loading...</div>
    <template v-else-if="item">
      <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">{{ item.title }}</h1>
        <div class="flex flex-wrap gap-2">
          <button v-if="!editing" class="btn-secondary" @click="editing = true">Edit</button>
          <button class="btn-danger" @click="deleteItem">Delete</button>
          <button class="btn-secondary" @click="router.push('/csi')">Back</button>
        </div>
      </div>

      <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

      <div class="flex flex-wrap gap-2 mb-4">
        <span class="text-sm text-gray-500">Status:</span>
        <button v-for="s in ['draft', 'active', 'on_track', 'behind', 'achieved', 'closed']" :key="s" :class="['px-3 py-1 rounded text-sm', item.status === s ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200']" @click="updateStatus(s)">{{ formatLabel(s) }}</button>
      </div>

      <form v-if="editing" @submit.prevent="saveChanges" class="card p-6 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Title</label>
            <input v-model="item.title" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Category</label>
            <input v-model="item.category" class="input w-full" />
          </div>
          <div v-if="auth.isAdmin || auth.isCustomerAdmin">
            <label class="block text-sm font-medium mb-1">Customer</label>
            <select v-model="item.customer_id" class="input w-full">
              <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Priority</label>
            <select v-model="item.priority" class="input w-full">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Source</label>
            <input v-model="item.source" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Target Value</label>
            <input v-model="item.target_value" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Current Value</label>
            <input v-model="item.current_value" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Baseline Value</label>
            <input v-model="item.baseline_value" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Assigned To</label>
            <input v-model="item.assigned_to" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Review Date</label>
            <input v-model="item.review_date" type="date" class="input w-full" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Expected Benefit</label>
            <input v-model="item.expected_benefit" class="input w-full" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea v-model="item.description" class="input w-full" rows="3"></textarea>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">KPI Measure</label>
          <input v-model="item.kpi_measure" class="input w-full" />
        </div>
        <div class="flex gap-3">
          <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
          <button type="button" class="btn-secondary" @click="editing = false">Cancel</button>
        </div>
      </form>

      <div v-else class="grid grid-cols-2 gap-4 mb-6">
        <div class="card p-4">
          <div class="text-sm text-gray-500">Customer</div>
          <div class="font-medium">{{ item.customer_name || '-' }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Category</div>
          <div class="font-medium">{{ formatLabel(item.category) || '-' }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Priority</div>
          <div class="font-medium">{{ formatLabel(item.priority) }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Target Value</div>
          <div class="font-medium">{{ item.target_value || '-' }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Current Value</div>
          <div class="font-medium">{{ item.current_value || '-' }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Assigned To</div>
          <div class="font-medium">{{ item.assigned_to || '-' }}</div>
        </div>
        <div class="card p-4">
          <div class="text-sm text-gray-500">Review Date</div>
          <div class="font-medium">{{ item.review_date ? new Date(item.review_date).toLocaleDateString() : '-' }}</div>
        </div>
      </div>

      <div class="card p-6">
        <h2 class="text-lg font-semibold mb-3">Action Items</h2>
        <div class="flex gap-2 mb-3">
          <input v-model="actionInput" class="input flex-1" placeholder="Add action item..." @keyup.enter="addAction" />
          <button class="btn-primary" @click="addAction">Add</button>
        </div>
        <ul v-if="item.action_items?.length" class="space-y-2">
          <li v-for="(a, i) in item.action_items" :key="i" class="flex items-center gap-2">
            <input type="checkbox" v-model="a.done" class="h-4 w-4" />
            <span :class="a.done ? 'line-through text-gray-400' : ''">{{ a.text }}</span>
            <button class="ml-auto text-red-500 text-sm hover:underline" @click="removeAction(i)">Remove</button>
          </li>
        </ul>
        <p v-else class="text-gray-400 text-sm">No action items yet.</p>
      </div>
    </template>
  </div>
</template>
