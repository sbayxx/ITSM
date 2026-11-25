<template>
  <div>
    <div class="mb-6">
      <router-link to="/requests" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Requests</router-link>
      <h1 class="text-2xl font-bold text-gray-900 mt-1">New Service Request</h1>
    </div>

    <div class="card max-w-2xl">
      <form @submit.prevent="handleSubmit">
        <div class="space-y-4">
          <div>
            <label class="label">Catalog Item <span class="text-red-500">*</span></label>
            <select v-model="form.catalog_item_id" class="select" @change="onCatalogItemChange" required>
              <option :value="null">Select a catalog item...</option>
              <option v-for="item in catalogItems" :key="item.id" :value="item.id">
                {{ item.name }}
              </option>
            </select>
          </div>

          <div v-if="selectedItem">
            <p class="text-sm text-gray-500 mb-4">{{ selectedItem.description }}</p>

            <div v-for="field in selectedFields" :key="field.name" class="mb-4">
              <label class="label">
                {{ field.label }}
                <span v-if="field.required" class="text-red-500">*</span>
              </label>
              <input v-if="field.type === 'text'" v-model="form.dynamic_fields[field.name]" type="text" class="input"
                :placeholder="field.placeholder || ''" :required="field.required" />
              <textarea v-else-if="field.type === 'textarea'" v-model="form.dynamic_fields[field.name]" class="textarea"
                :placeholder="field.placeholder || ''" :required="field.required" rows="3"></textarea>
              <select v-else-if="field.type === 'select'" v-model="form.dynamic_fields[field.name]" class="select"
                :required="field.required">
                <option value="">Select...</option>
                <option v-for="opt in field.options" :key="opt" :value="opt">{{ opt }}</option>
              </select>
            </div>
          </div>

          <div>
            <label class="label">Title <span class="text-red-500">*</span></label>
            <input v-model="form.title" type="text" class="input" placeholder="Summary of your request" required />
          </div>

          <CategorySelect type="service_request" v-model:category="form.category" v-model:subcategory="form.subcategory" />

          <div>
            <label class="label">Description <span class="text-red-500">*</span></label>
            <textarea v-model="form.description" class="textarea" placeholder="Detailed description of your request" rows="5" required></textarea>
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
            {{ submitting ? 'Submitting...' : 'Submit Request' }}
          </button>
          <router-link to="/requests" class="btn-secondary">Cancel</router-link>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import CategorySelect from '../../components/CategorySelect.vue'
import AppIcon from '../../components/common/AppIcon.vue'
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const auth = useAuthStore()
const submitting = ref(false)
const catalogItems = ref([])
const users = ref([])
const customers = ref([])

const form = reactive({
  category: '',
  subcategory: '',
  catalog_item_id: null,
  title: '',
  description: '',
  dynamic_fields: {},
  reported_by: auth.user?.id || '',
  requester_id: auth.user?.id || '',
  customer_id: '',
})

const selectedItem = computed(() => {
  return catalogItems.value.find(i => i.id === form.catalog_item_id) || null
})

// service_catalog.fields is a JSON column and arrives either as an array or
// as a raw JSON string depending on the driver — v-for needs a real array.
const selectedFields = computed(() => {
  const fields = selectedItem.value?.fields
  if (Array.isArray(fields)) return fields
  if (typeof fields === 'string') {
    try {
      const parsed = JSON.parse(fields)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }
  return []
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

async function fetchCatalogItems() {
  try {
    const data = await client.get('/service-catalog')
    catalogItems.value = data.data || data || []
  } catch (err) {
    console.error('Failed to fetch catalog items:', err)
  }
}

function onCatalogItemChange() {
  form.dynamic_fields = {}
}

async function handleSubmit() {
  if (!form.title || !form.description || !form.catalog_item_id) return
  submitting.value = true
  try {
    await client.post('/tickets', {
      title: form.title,
      description: form.description,
      category: form.category || undefined,
      subcategory: form.subcategory || undefined,
      service_catalog_item_id: form.catalog_item_id,
      // The backend only reads type_data; answers sent as a top-level
      // dynamic_fields key were silently discarded.
      type_data: { dynamic_fields: form.dynamic_fields },
      reported_by: form.reported_by || undefined,
      requester_id: form.requester_id || undefined,
      customer_id: form.customer_id || undefined,
      type: 'service_request',
    })
    router.push('/requests')
  } catch (err) {
    console.error('Failed to create request:', err)
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  fetchCatalogItems()
  if (auth.isAgent) fetchUsers()
  fetchCustomers()
})
</script>
