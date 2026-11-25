<template>
  <div v-if="loading" class="text-center py-12 text-gray-500">Loading...</div>
  <div v-else-if="!request" class="text-center py-12 text-gray-500">Request not found.</div>
  <template v-else>
    <div class="flex items-center justify-between mb-6">
      <div>
        <router-link to="/requests" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Requests</router-link>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">#{{ request.ticket_number }} - {{ request.title }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button @click="editing = true" class="btn-primary btn-sm">Edit</button>
        <button @click="deleteItem" class="btn-danger btn-sm">Delete</button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <VendorPanel v-if="request" :ticket="request" @changed="fetchRequest" />
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Details</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Status</label>
              <p class="text-sm text-gray-900">
                <span class="badge badge-medium">{{ formatLabel(request.status) }}</span>
              </p>
            </div>
            <div>
              <label class="label">Approval Status</label>
              <p class="text-sm">
                <span v-if="request.type_data?.approval_status" class="badge" :class="approvalClass(request.type_data.approval_status)">
                  {{ formatLabel(request.type_data.approval_status) }}
                </span>
                <span v-else class="text-gray-400">N/A</span>
              </p>
            </div>
            <div class="col-span-2">
              <label class="label">Description</label>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ request.description }}</p>
            </div>
            <div>
              <label class="label">Assigned To</label>
              <p class="text-sm text-gray-900">{{ request.assigned_to_name || 'Unassigned' }}</p>
            </div>
            <div>
              <label class="label">Category</label>
              <p class="text-sm text-gray-900">{{ [formatLabel(request.category), formatLabel(request.subcategory)].filter(Boolean).join(' › ') || 'N/A' }}</p>
            </div>
          </div>
        </div>

        <ApprovalFlow
          :approvals="request.approvals"
          :currentUser="currentUser"
          :noApprovalNeeded="noApprovalNeeded"
          noApprovalLabel="Approval not required for this item."
          @approve="handleApprove"
          @rejected="fetchRequest"
        />

        <div v-if="request.catalog_item" class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Catalog Item</h2>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">Item Name</label>
              <p class="text-sm text-gray-900">{{ request.catalog_item.name }}</p>
            </div>
            <div>
              <label class="label">Category</label>
              <p class="text-sm text-gray-900">{{ formatLabel(request.catalog_item.category) || 'N/A' }}</p>
            </div>
            <div class="col-span-2" v-if="request.catalog_item.description">
              <label class="label">Description</label>
              <p class="text-sm text-gray-700">{{ request.catalog_item.description }}</p>
            </div>
          </div>
        </div>

        <div v-if="Object.keys(dynamicAnswers).length" class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Request Details</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="(value, name) in dynamicAnswers" :key="name">
              <label class="label">{{ fieldLabel(name) }}</label>
              <p class="text-sm text-gray-900 whitespace-pre-wrap">{{ value }}</p>
            </div>
          </div>
        </div>

        <div v-if="request.type_data?.fulfillment_notes" class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Fulfillment Notes</h2>
          <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ request.type_data.fulfillment_notes }}</p>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Updates</h2>
          <div class="space-y-4 mb-6">
            <div v-for="comment in request.comments" :key="comment.id" class="border-l-2 pl-4" :class="comment.is_internal == 1 ? 'border-yellow-400' : 'border-gray-200'">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="comment.is_internal == 1" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Internal</span>
              </div>
              <p class="text-sm text-gray-700">{{ comment.body }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ comment.user_name }} - {{ formatDateTime(comment.created_at) }}</p>
            </div>
            <div v-if="!request.comments?.length" class="text-sm text-gray-500">No updates yet.</div>
          </div>
          <div class="space-y-3">
            <form @submit.prevent="addComment">
              <label class="block text-sm font-medium text-gray-700 mb-1">Public update</label>
              <textarea v-model="newComment" class="textarea mb-2" placeholder="Visible to requester and all agents..." rows="2"></textarea>
              <button type="submit" class="btn-primary btn-sm" :disabled="!newComment.trim()">Post Public Update</button>
            </form>
            <form v-if="auth.canSeeInternalNotes" @submit.prevent="addInternalNote">
              <label class="block text-sm font-medium text-gray-700 mb-1">Internal note <span class="text-xs text-yellow-600 font-normal">(agents only)</span></label>
              <textarea v-model="newInternalNote" class="textarea mb-2" placeholder="Only visible to agents and admins..." rows="2"></textarea>
              <button type="submit" class="btn-secondary btn-sm" :disabled="!newInternalNote.trim()">Post Internal Note</button>
            </form>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-900 mb-3">Ticket Info</h3>
          <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
              <dt class="text-gray-500">Created</dt>
              <dd class="text-gray-900">{{ formatDate(request.created_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Updated</dt>
              <dd class="text-gray-900">{{ formatDate(request.updated_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Reporter</dt>
              <dd class="text-gray-900">{{ request.reported_by_name || 'N/A' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Requester</dt>
              <dd class="text-gray-900">{{ request.requester_name || 'N/A' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Type</dt>
              <dd class="text-gray-900">{{ formatLabel(request.type) }}</dd>
            </div>
            <div class="flex justify-between" v-if="request.type_data?.approved_by">
              <dt class="text-gray-500">Approved By</dt>
              <dd class="text-gray-900">{{ request.type_data.approved_by }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
    <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="editing = false">
      <div class="card w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Edit Service Request</h2>
        <div class="space-y-4">
          <div>
            <label class="label">Title</label>
            <input v-model="form.title" class="input" />
          </div>
          <div>
            <label class="label">Description</label>
            <textarea v-model="form.description" class="textarea" rows="3"></textarea>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Status</label>
              <select v-model="form.status" class="select">
                <option value="new">New</option>
                <option value="pending_approval">Pending Approval</option>
                <option value="in_fulfillment">In Fulfillment</option>
                <option value="fulfilled">Fulfilled</option>
                <option value="closed">Closed</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div>
              <label class="label">Priority</label>
              <select v-model="form.priority" class="select">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
              </select>
            </div>
          </div>
            <CategorySelect type="service_request" v-model:category="form.category" v-model:subcategory="form.subcategory" />
          <div>
            <label class="label">Assignee</label>
            <select v-model="form.assigned_to" class="select">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Fulfillment Notes</label>
            <textarea v-model="form.fulfillment_notes" class="textarea" rows="3"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button class="btn-secondary" @click="editing = false">Cancel</button>
          <button class="btn-primary" :disabled="saving" @click="saveRequest">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </div>
    </div>
  </template>
</template>

<script setup>
import CategorySelect from '../../components/CategorySelect.vue'
import VendorPanel from '../../components/VendorPanel.vue'
import { formatLabel } from '../../utils/format'
import AppIcon from '../../components/common/AppIcon.vue'
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import ApprovalFlow from '../../components/ApprovalFlow.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const request = ref(null)
const loading = ref(true)
const newComment = ref('')
const newInternalNote = ref('')
const editing = ref(false)
const saving = ref(false)
const users = ref([])

const currentUser = computed(() => auth.user)

const form = reactive({
  title: '',
  description: '',
  status: 'new',
  priority: 'medium',
  assigned_to: null,
  fulfillment_notes: '',
  category: '',
  subcategory: '',
})

const noApprovalNeeded = computed(() => {
  if (!request.value) return false
  const sr = request.value.type_data
  return sr && !sr.approval_status && !request.value.approvals?.length
})

// Catalog item answers live in service_requests.fulfillment_data. The JSON column
// may arrive either as an object or as a raw JSON string depending on driver.
const dynamicAnswers = computed(() => {
  const raw = request.value?.type_data?.fulfillment_data
  if (!raw) return {}
  if (typeof raw === 'string') {
    try {
      return JSON.parse(raw) || {}
    } catch {
      return {}
    }
  }
  return typeof raw === 'object' ? raw : {}
})

// Prefer the label the catalog item defined for the field, fall back to a
// humanised version of the field name. `fields` is a JSON column and may arrive
// as an array or as a raw JSON string depending on the driver.
function catalogFields() {
  const fields = request.value?.catalog_item?.fields
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
}

function fieldLabel(name) {
  const match = catalogFields().find((f) => f && f.name === name)
  if (match?.label) return match.label
  return String(name)
    .replace(/[_-]+/g, ' ')
    .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
    .replace(/^./, (c) => c.toUpperCase())
}

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || res
  } catch (_) {}
}

async function fetchRequest() {
  loading.value = true
  try {
    const data = await client.get(`/tickets/${route.params.id}`)
    request.value = data.data || data
    const td = request.value.type_data || {}
    form.title = request.value.title || ''
    form.description = request.value.description || ''
    form.status = request.value.status || 'new'
    form.priority = request.value.priority || 'medium'
    form.category = request.value.category || ''
    form.subcategory = request.value.subcategory || ''
    form.assigned_to = request.value.assigned_to || null
    form.fulfillment_notes = td.fulfillment_notes || ''
  } catch (err) {
    console.error('Failed to fetch request:', err)
    request.value = null
  } finally {
    loading.value = false
  }
}

async function handleApprove(approvalId) {
  try {
    await client.post(`/approvals/${approvalId}/approve`)
    await fetchRequest()
  } catch (err) {
    console.error('Failed to approve:', err)
  }
}

async function addComment() {
  if (!newComment.value.trim()) return
  try {
    await client.post(`/tickets/${request.value.id}/comments`, { body: newComment.value, is_internal: false })
    newComment.value = ''
    await fetchRequest()
  } catch (err) {
    console.error('Failed to add comment:', err)
  }
}

async function addInternalNote() {
  if (!newInternalNote.value.trim()) return
  try {
    await client.post(`/tickets/${request.value.id}/comments`, { body: newInternalNote.value, is_internal: true })
    newInternalNote.value = ''
    await fetchRequest()
  } catch (err) {
    console.error('Failed to add internal note:', err)
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this request?')) return
  try {
    await client.delete(`/tickets/${route.params.id}`)
    router.push('/requests')
  } catch (err) {
    console.error('Failed to delete request:', err)
  }
}

async function saveRequest() {
  saving.value = true
  try {
    const payload = {
      title: form.title,
      description: form.description,
      status: form.status,
      priority: form.priority,
      category: form.category,
      subcategory: form.subcategory,
      assigned_to: form.assigned_to,
      type_data: {
        fulfillment_notes: form.fulfillment_notes,
      },
    }
    const res = await client.put(`/tickets/${route.params.id}`, payload)
    request.value = res.data || res
    editing.value = false
  } catch (err) {
    console.error('Failed to update request:', err)
  } finally {
    saving.value = false
  }
}

function approvalClass(status) {
  const map = { approved: 'badge-low', pending: 'badge-medium', rejected: 'badge-critical', cancelled: 'badge-medium' }
  return map[status] || 'badge-medium'
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => { fetchRequest(); fetchUsers() })
</script>
