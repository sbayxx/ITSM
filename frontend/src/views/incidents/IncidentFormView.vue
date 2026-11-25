<template>
  <div>
    <div class="mb-6">
      <router-link to="/incidents" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Incidents</router-link>
      <h1 class="text-2xl font-bold text-gray-900 mt-1">Report Incident</h1>
    </div>

    <div class="card max-w-2xl">
      <form @submit.prevent="handleSubmit">
        <div class="space-y-4">
          <div>
            <label class="label">Title <span class="text-red-500">*</span></label>
            <input v-model="form.title" type="text" class="input" placeholder="Brief description of the incident" required />
          </div>

          <div>
            <label class="label">Description <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" class="textarea" placeholder="Detailed description of the incident" rows="5" required></textarea>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="label">Impact</label>
              <select v-model="form.impact" class="select">
                <option value="">Select impact...</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
              </select>
            </div>
            <div>
              <label class="label">Urgency</label>
              <select v-model="form.urgency" class="select">
                <option value="">Select urgency...</option>
                <option value="High">High</option>
                <option value="Medium">Medium</option>
                <option value="Low">Low</option>
              </select>
            </div>
          </div>

          <CategorySelect type="incident" v-model:category="form.category" v-model:subcategory="form.subcategory" />

          <div>
            <label class="label">CI Item</label>
            <select v-model="form.ci_item_id" class="select">
              <option :value="null">Select CI item...</option>
              <option v-for="ci in ciItems" :key="ci.id" :value="ci.id">{{ ci.name }} ({{ ci.fqdn || ci.type }})</option>
            </select>
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
        </div>

        <div class="flex items-center gap-3 mt-6 pt-6 border-t border-gray-200">
          <button type="submit" class="btn-primary" :disabled="submitting">
            {{ submitting ? 'Submitting...' : 'Submit Incident' }}
          </button>
          <router-link to="/incidents" class="btn-secondary">Cancel</router-link>
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
const ciItems = ref([])
const users = ref([])
const customers = ref([])

const form = reactive({
  title: '',
  description: '',
  impact: '',
  urgency: '',
  category: '',
  subcategory: '',
  ci_item_id: null,
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

async function fetchCiItems() {
  try {
    const data = await client.get('/ci-items')
    ciItems.value = data.data || data || []
  } catch {
    ciItems.value = []
  }
}

async function handleSubmit() {
  if (!form.title || !form.description) return
  submitting.value = true
  try {
    const payload = { ...form, type: 'incident' }
    if (!payload.reported_by) delete payload.reported_by
    if (!payload.requester_id) delete payload.requester_id
    if (!payload.customer_id) delete payload.customer_id
    await client.post('/tickets', payload)
    router.push('/incidents')
  } catch (err) {
    console.error('Failed to create incident:', err)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchCiItems()
  if (auth.isAgent) fetchUsers()
  fetchCustomers()
})
</script>
