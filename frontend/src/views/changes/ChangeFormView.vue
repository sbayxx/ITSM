<template>
  <div>
    <div class="mb-6">
      <router-link to="/changes" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Changes</router-link>
      <h1 class="text-2xl font-bold text-gray-900">Create Change</h1>
    </div>

    <div class="card max-w-2xl">
      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="label">Title</label>
          <input v-model="form.title" class="input" placeholder="Brief title of the change" required />
        </div>

          <CategorySelect type="change" v-model:category="form.category" v-model:subcategory="form.subcategory" />

          <div>
            <label class="label">Description</label>
            <textarea v-model="form.description" class="textarea" rows="3" placeholder="Detailed description of the change"></textarea>
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

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Change Type</label>
            <select v-model="form.change_type" class="select" required>
              <option value="normal">Normal</option>
              <option value="standard">Standard</option>
              <option value="emergency">Emergency</option>
            </select>
          </div>
          <div>
            <label class="label">Risk Level</label>
            <select v-model="form.risk_level" class="select" required>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>

        <div>
          <label class="label">Impact Assessment</label>
          <textarea v-model="form.impact_assessment" class="textarea" rows="3" placeholder="What is the impact of this change?"></textarea>
        </div>

        <div>
          <label class="label">Implementation Plan</label>
          <textarea v-model="form.implementation_plan" class="textarea" rows="3" placeholder="Steps to implement the change"></textarea>
        </div>

        <div>
          <label class="label">Rollback Plan</label>
          <textarea v-model="form.rollback_plan" class="textarea" rows="3" placeholder="How to roll back if the change fails"></textarea>
        </div>

        <div>
          <label class="label">Test Plan</label>
          <textarea v-model="form.test_plan" class="textarea" rows="3" placeholder="How will this change be tested?"></textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="label">Scheduled Start</label>
            <input v-model="form.scheduled_start" type="datetime-local" class="input" />
          </div>
          <div>
            <label class="label">Scheduled End</label>
            <input v-model="form.scheduled_end" type="datetime-local" class="input" />
          </div>
        </div>

        <div class="flex items-center gap-2">
          <input id="cab_required" v-model="typeData.cab_required" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
          <label for="cab_required" class="text-sm text-gray-700">CAB Approval Required</label>
        </div>

        <div class="border-t pt-4">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-semibold text-gray-900">Approval Flow</h3>
          </div>
          <p class="text-xs text-gray-400 mb-3">Add one or more approval steps. Standard changes are pre-approved.</p>
          <div class="space-y-2">
            <div v-for="(step, i) in approvalSteps" :key="i" class="flex items-start gap-3 p-3 bg-gray-50 rounded-lg">
              <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                <select v-model="step.type" class="select text-sm">
                  <option value="role">Role</option>
                  <option value="user">Specific user</option>
                </select>
                <select v-if="step.type === 'role'" v-model="step.role" class="select text-sm">
                  <option value="manager">Manager</option>
                  <option value="customer_admin">Customer admin</option>
                  <option value="admin">Admin</option>
                  <option value="agent">Agent</option>
                </select>
                <select v-else v-model="step.user_id" class="select text-sm">
                  <option value="" disabled>Select user...</option>
                  <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
              </div>
              <button type="button" class="text-red-500 hover:text-red-700 mt-1.5" @click="removeApprovalStep(i)"><AppIcon name="x" :size="16" /></button>
            </div>
            <button type="button" class="btn-secondary btn-sm gap-1.5" @click="addApprovalStep"><AppIcon name="plus" :size="14" />Add step</button>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-4">
          <router-link to="/changes" class="btn-secondary">Cancel</router-link>
          <button type="submit" class="btn-primary" :disabled="submitting">{{ submitting ? 'Creating...' : 'Create Change' }}</button>
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
  category: '',
  subcategory: '',
  title: '',
  description: '',
  reported_by: auth.user?.id || '',
  requester_id: auth.user?.id || '',
  customer_id: '',
})

const typeData = reactive({
  change_type: 'normal',
  risk_level: 'medium',
  impact_assessment: '',
  implementation_plan: '',
  rollback_plan: '',
  test_plan: '',
  cab_required: false,
  scheduled_start: '',
  scheduled_end: '',
})

const approvalSteps = ref([{ type: 'user', role: 'manager', user_id: '' }])

function addApprovalStep() {
  approvalSteps.value.push({ type: 'role', role: 'manager', user_id: '' })
}

function removeApprovalStep(i) {
  approvalSteps.value.splice(i, 1)
}

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || res
  } catch (_) {}
}

async function fetchCustomers() {
  try {
    const res = await client.get('/customers')
    customers.value = res.data || []
  } catch {}
}

onMounted(() => {
  fetchUsers()
  fetchCustomers()
})

async function submit() {
  submitting.value = true
  try {
    const payload = {
      title: form.title,
      description: form.description,
      type: 'change',
      type_data: { ...typeData },
      reported_by: form.reported_by || undefined,
      requester_id: form.requester_id || undefined,
      category: form.category || undefined,
      subcategory: form.subcategory || undefined,
      customer_id: form.customer_id || undefined,
    }
    if (payload.type_data.scheduled_start) {
      payload.type_data.scheduled_start = new Date(payload.type_data.scheduled_start).toISOString()
    }
    if (payload.type_data.scheduled_end) {
      payload.type_data.scheduled_end = new Date(payload.type_data.scheduled_end).toISOString()
    }
    payload.type_data.approval_chain = approvalSteps.value
      .filter(s => (s.type === 'user' && s.user_id) || (s.type === 'role' && s.role))
      .map(s => ({
        type: s.type,
        role: s.type === 'role' ? s.role : null,
        user_id: s.type === 'user' ? s.user_id : null,
      }))
    const res = await client.post('/tickets', payload)
    router.push(`/changes/${res.data.id}`)
  } catch (err) {
    console.error('Failed to create change:', err)
  } finally {
    submitting.value = false
  }
}
</script>
