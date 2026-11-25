<template>
  <div v-if="loading" class="text-center py-12 text-gray-500">Loading...</div>
  <div v-else-if="!incident" class="text-center py-12 text-gray-500">Incident not found.</div>
  <template v-else>
    <div class="flex items-center justify-between mb-6">
      <div>
        <router-link to="/incidents" class="text-sm text-primary-600 hover:underline inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Incidents</router-link>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">#{{ incident.ticket_number }} - {{ incident.title }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="canEscalate" @click="handleEscalate" class="btn-danger btn-sm">Escalate</button>
        <button @click="linkToProblem" class="btn-secondary btn-sm">Link to Problem</button>
        <button @click="editing = true" class="btn-primary btn-sm">Edit</button>
        <button @click="deleteItem" class="btn-danger btn-sm">Delete</button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <VendorPanel v-if="incident" :ticket="incident" @changed="fetchIncident" />
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Details</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Status</label>
              <select v-model="incident.status" @change="updateStatus" class="select">
                <option v-for="s in statuses" :key="s" :value="s">{{ formatLabel(s) }}</option>
              </select>
            </div>
            <div>
              <label class="label">Priority</label>
              <span class="badge mt-2 inline-block" :class="priorityClass(incident.priority)">{{ formatLabel(incident.priority) }}</span>
            </div>
            <div>
              <label class="label">Impact</label>
              <p class="text-sm text-gray-900">{{ incident.impact || 'N/A' }}</p>
            </div>
            <div>
              <label class="label">Urgency</label>
              <p class="text-sm text-gray-900">{{ incident.urgency || 'N/A' }}</p>
            </div>
            <div class="col-span-2">
              <label class="label">Description</label>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ incident.description }}</p>
            </div>
            <div class="col-span-2" v-if="incident.workaround">
              <label class="label">Workaround</label>
              <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ incident.workaround }}</p>
            </div>
            <div>
              <label class="label">Assigned To</label>
              <p class="text-sm text-gray-900">{{ incident.assigned_to_name || 'Unassigned' }}</p>
            </div>
            <div>
              <label class="label">Category</label>
              <p class="text-sm text-gray-900">{{ [formatLabel(incident.category), formatLabel(incident.subcategory)].filter(Boolean).join(' › ') || 'N/A' }}</p>
            </div>
            <div v-if="incident.ci_item">
              <label class="label">CI Item</label>
              <router-link :to="`/cmdb/${incident.ci_item.id}`" class="text-sm text-primary-600 hover:underline">
                {{ incident.ci_item.name }}
              </router-link>
            </div>
            <div v-if="incident.sla_deadline">
              <label class="label">SLA Deadline</label>
              <p class="text-sm" :class="slaClass(incident.sla_deadline)">{{ formatDate(incident.sla_deadline) }}</p>
            </div>
          </div>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Timeline</h2>
          <div class="space-y-4">
            <div v-for="entry in timeline" :key="entry.id" class="flex gap-3">
              <div class="flex flex-col items-center">
                <div class="w-2.5 h-2.5 rounded-full bg-primary-500 mt-1.5"></div>
                <div class="w-px flex-1 bg-gray-200"></div>
              </div>
              <div class="pb-4">
                <p class="text-sm text-gray-900">{{ entry.description }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ formatDateTime(entry.created_at) }} by {{ entry.user?.name }}</p>
              </div>
            </div>
            <div v-if="!timeline.length" class="text-sm text-gray-500">No timeline entries yet.</div>
          </div>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Updates</h2>
          <div class="space-y-4 mb-6">
            <div v-for="comment in comments" :key="comment.id" class="border-l-2 pl-4" :class="comment.is_internal == 1 ? 'border-yellow-400' : 'border-gray-200'">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="comment.is_internal == 1" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Internal</span>
              </div>
              <p class="text-sm text-gray-700">{{ comment.body }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ comment.user_name }} - {{ formatDateTime(comment.created_at) }}</p>
            </div>
            <div v-if="!comments.length" class="text-sm text-gray-500">No updates yet.</div>
          </div>
          <div class="space-y-3">
            <form @submit.prevent="addComment">
              <label class="block text-sm font-medium text-gray-700 mb-1">Public update</label>
              <textarea v-model="newComment" class="textarea mb-2" placeholder="Visible to requester and all agents..." rows="2"></textarea>
              <button type="submit" class="btn-primary btn-sm" :disabled="!newComment.trim()">Post Public Update</button>
            </form>
            <form v-if="canSeeInternal" @submit.prevent="addInternalNote">
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
              <dd class="text-gray-900">{{ formatDate(incident.created_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Updated</dt>
              <dd class="text-gray-900">{{ formatDate(incident.updated_at) }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Reporter</dt>
              <dd class="text-gray-900">{{ incident.reported_by_name || 'N/A' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Requester</dt>
              <dd class="text-gray-900">{{ incident.requester_name || 'N/A' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-gray-500">Type</dt>
              <dd class="text-gray-900">{{ formatLabel(incident.type) }}</dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
    <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="editing = false">
      <div class="card w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Edit Incident</h2>
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
                <option v-for="s in statuses" :key="s" :value="s.toLowerCase()">{{ formatLabel(s) }}</option>
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Impact</label>
              <select v-model="form.impact" class="select">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>
            <div>
              <label class="label">Urgency</label>
              <select v-model="form.urgency" class="select">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Assignee</label>
            <select v-model="form.assigned_to" class="select">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <CategorySelect type="incident" v-model:category="form.category" v-model:subcategory="form.subcategory" />
          <div>
            <label class="label">Workaround</label>
            <textarea v-model="form.workaround" class="textarea" rows="2"></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input id="edit_is_major" v-model="form.is_major" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
            <label for="edit_is_major" class="text-sm text-gray-700">Major Incident</label>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button class="btn-secondary" @click="editing = false">Cancel</button>
          <button class="btn-primary" :disabled="saving" @click="saveIncident">{{ saving ? 'Saving...' : 'Save' }}</button>
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

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const incident = ref(null)
const comments = ref([])
const timeline = ref([])
const loading = ref(true)
const newComment = ref('')
const newInternalNote = ref('')
const editing = ref(false)
const saving = ref(false)
const users = ref([])

const canSeeInternal = computed(() => auth.canSeeInternalNotes)

const statuses = ['New', 'In Progress', 'Resolved', 'Closed', 'On Hold']

const form = reactive({
  title: '',
  description: '',
  status: 'new',
  priority: 'medium',
  impact: 'medium',
  urgency: 'medium',
  assigned_to: null,
  category: '',
  subcategory: '',
  workaround: '',
  is_major: false,
})

const canEscalate = computed(() => {
  return incident.value && ['New', 'In Progress', 'On Hold'].includes(incident.value.status)
})

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || res
  } catch (_) {}
}

async function fetchIncident() {
  loading.value = true
  try {
    const data = await client.get(`/tickets/${route.params.id}`)
    incident.value = data.data || data
    comments.value = incident.value.comments || []
    timeline.value = incident.value.timeline || []
    const td = incident.value.type_data || {}
    form.title = incident.value.title || ''
    form.description = incident.value.description || ''
    form.status = incident.value.status || 'new'
    form.priority = incident.value.priority || 'medium'
    form.impact = td.impact || 'medium'
    form.urgency = td.urgency || 'medium'
    form.assigned_to = incident.value.assigned_to || null
    form.category = incident.value.category || ''
    form.subcategory = incident.value.subcategory || ''
    form.workaround = td.workaround || ''
    form.is_major = td.is_major === 1 || td.is_major === '1'
  } catch (err) {
    console.error('Failed to fetch incident:', err)
    incident.value = null
  } finally {
    loading.value = false
  }
}

async function updateStatus() {
  try {
    await client.patch(`/tickets/${incident.value.id}`, { status: incident.value.status })
  } catch (err) {
    console.error('Failed to update status:', err)
  }
}

async function saveIncident() {
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
        impact: form.impact,
        urgency: form.urgency,
        workaround: form.workaround,
        is_major: form.is_major ? 1 : 0,
      },
    }
    const res = await client.put(`/tickets/${route.params.id}`, payload)
    incident.value = res.data || res
    comments.value = (incident.value.comments || []).filter(c => auth.canSeeInternalNotes || c.is_internal != 1)
    timeline.value = incident.value.timeline || []
    editing.value = false
  } catch (err) {
    console.error('Failed to update incident:', err)
  } finally {
    saving.value = false
  }
}

async function handleEscalate() {
  try {
    await client.post(`/incidents/${incident.value.id}/escalate`)
    await fetchIncident()
  } catch (err) {
    console.error('Failed to escalate:', err)
  }
}

function linkToProblem() {
  router.push(`/problems/create?incident_id=${incident.value.id}`)
}

async function addComment() {
  if (!newComment.value.trim()) return
  try {
    await client.post(`/tickets/${incident.value.id}/comments`, { body: newComment.value, is_internal: false })
    newComment.value = ''
    await fetchIncident()
  } catch (err) {
    console.error('Failed to add comment:', err)
  }
}

async function addInternalNote() {
  if (!newInternalNote.value.trim()) return
  try {
    await client.post(`/tickets/${incident.value.id}/comments`, { body: newInternalNote.value, is_internal: true })
    newInternalNote.value = ''
    await fetchIncident()
  } catch (err) {
    console.error('Failed to add internal note:', err)
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this incident?')) return
  try {
    await client.delete(`/tickets/${route.params.id}`)
    router.push('/incidents')
  } catch (err) {
    console.error('Failed to delete incident:', err)
  }
}

function priorityClass(priority) {
  const map = { Critical: 'badge-critical', High: 'badge-high', Medium: 'badge-medium', Low: 'badge-low' }
  return map[priority] || 'badge-medium'
}

function slaClass(deadline) {
  const now = new Date()
  const sla = new Date(deadline)
  const diff = sla - now
  if (diff < 0) return 'text-red-600 font-medium'
  if (diff < 3600000 * 4) return 'text-orange-600 font-medium'
  return 'text-green-600'
}

function formatDate(date) {
  if (!date) return ''
  return new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => { fetchIncident(); fetchUsers() })
</script>
