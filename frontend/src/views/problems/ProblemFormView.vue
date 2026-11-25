<template>
  <div>
    <div class="mb-6">
      <router-link to="/problems" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Problems</router-link>
      <h1 class="text-2xl font-bold text-gray-900">Create Problem</h1>
    </div>

    <div class="card max-w-2xl">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="label">Title</label>
          <input v-model="form.title" class="input" placeholder="Brief title of the problem" required />
        </div>

        <div>
          <label class="label">Description</label>
          <textarea v-model="form.description" class="textarea" rows="4" placeholder="Detailed description of the problem"></textarea>
        </div>

        <div>
          <label class="label">Priority</label>
          <select v-model="form.priority" class="select">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
            <option value="critical">Critical</option>
          </select>
        </div>

        <CategorySelect type="problem" v-model:category="form.category" v-model:subcategory="form.subcategory" />

        <div class="flex items-center gap-2">
          <input id="known_error" v-model="form.known_error" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
          <label for="known_error" class="text-sm text-gray-700">Mark as Known Error</label>
        </div>

        <div>
          <label class="label">Reporter</label>
          <div v-if="auth.isAgent && users.length">
            <select v-model="form.reported_by" class="select">
              <option value="">Select reporter...</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <p v-else class="text-sm text-gray-700 py-2">{{ auth.user?.name || 'You' }}</p>
        </div>

        <div>
          <label class="label">Requester</label>
          <div v-if="auth.isAgent && users.length">
            <select v-model="form.requester_id" class="select">
              <option value="">Select requester...</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <p v-else class="text-sm text-gray-700 py-2">{{ auth.user?.name || 'You' }}</p>
        </div>

        <div v-if="auth.isAgent">
          <label class="label">Customer</label>
          <select v-model="form.customer_id" class="select">
            <option value="">Auto (your customer)</option>
            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <router-link to="/problems" class="btn-secondary">Cancel</router-link>
          <button type="submit" class="btn-primary" :disabled="submitting">{{ submitting ? 'Creating...' : 'Create Problem' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import CategorySelect from '../../components/CategorySelect.vue'
import AppIcon from '../../components/common/AppIcon.vue'
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const submitting = ref(false)
const users = ref([])
const customers = ref([])

const form = reactive({
  title: '',
  description: '',
  priority: 'medium',
  category: '',
  subcategory: '',
  known_error: false,
  reported_by: auth.user?.id || '',
  requester_id: auth.user?.id || '',
  customer_id: '',
})

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || []
  } catch {}
}

async function fetchCustomers() {
  try {
    const res = await client.get('/customers')
    customers.value = res.data || []
  } catch {}
}

onMounted(() => {
  if (auth.isAgent) fetchUsers()
  fetchCustomers()
})

async function submit() {
  submitting.value = true
  try {
    const payload = { ...form, type: 'problem' }
    if (!payload.reported_by) delete payload.reported_by
    if (!payload.requester_id) delete payload.requester_id
    if (!payload.customer_id) delete payload.customer_id
    const res = await client.post('/tickets', payload)
    router.push(`/problems/${res.data.id}`)
  } catch (err) {
    console.error('Failed to create problem:', err)
  } finally {
    submitting.value = false
  }
}
</script>
