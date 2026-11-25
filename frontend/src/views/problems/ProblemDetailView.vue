<template>
  <div v-if="loading" class="text-center py-12 text-gray-400">Loading...</div>

  <div v-else-if="!problem" class="text-center py-12 text-gray-400">Problem not found</div>

  <div v-else>
    <div class="flex items-center justify-between mb-6">
      <div>
        <router-link to="/problems" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Problems</router-link>
        <h1 class="text-2xl font-bold text-gray-900">{{ problem.ticket_number }}: {{ problem.title }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="!problem.known_error" class="btn-secondary" @click="markAsKnownError">Mark as Known Error</button>
        <button class="btn-primary" @click="editing = true">Edit</button>
        <button class="btn-danger" @click="deleteItem">Delete</button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <VendorPanel v-if="problem" :ticket="problem" @changed="fetchProblem" />
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Details</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
              <span class="label">Priority</span>
              <span :class="priorityBadge(problem.priority)">{{ formatLabel(problem.priority) }}</span>
            </div>
            <div>
              <span class="label">Status</span>
              <span :class="statusBadge(problem.status)">{{ statusLabel(problem.status) }}</span>
            </div>
            <div>
              <span class="label">Known Error</span>
              <span v-if="problem.known_error" class="badge bg-purple-100 text-purple-800">Yes</span>
              <span v-else class="text-sm text-gray-500">No</span>
            </div>
            <div>
              <span class="label">Created</span>
              <span class="text-sm text-gray-900">{{ formatDate(problem.created_at) }}</span>
            </div>
          </div>
          <div class="mb-4">
            <span class="label">Description</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ problem.description || 'No description' }}</p>
          </div>
          <div class="mb-4">
            <span class="label">Root Cause</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ problem.root_cause || 'No root cause identified' }}</p>
          </div>
          <div>
            <span class="label">Workaround</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ problem.workaround || 'No workaround available' }}</p>
          </div>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Status Updates</h2>
          <div v-if="problem.timeline && problem.timeline.length > 0" class="space-y-3">
            <div v-for="entry in problem.timeline" :key="entry.id" class="flex gap-3 text-sm">
              <div class="w-2 h-2 rounded-full bg-primary-500 mt-1.5 shrink-0"></div>
              <div>
                <p class="text-gray-700">{{ entry.description }}</p>
                <p class="text-gray-400 text-xs">{{ formatDate(entry.created_at) }}</p>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400">No status updates yet.</p>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Updates</h2>
          <div class="space-y-4 mb-6">
            <div v-for="comment in problem.comments" :key="comment.id" class="border-l-2 pl-4" :class="comment.is_internal == 1 ? 'border-yellow-400' : 'border-gray-200'">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="comment.is_internal == 1" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Internal</span>
              </div>
              <p class="text-sm text-gray-700">{{ comment.body }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ comment.user_name }} - {{ formatDateTime(comment.created_at) }}</p>
            </div>
            <div v-if="!problem.comments?.length" class="text-sm text-gray-500">No updates yet.</div>
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
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900">Related Incidents</h2>
            <button class="btn-secondary btn-sm" @click="showLinkModal = true">Link</button>
          </div>
          <div v-if="problem.related_incidents && problem.related_incidents.length > 0" class="space-y-2">
            <router-link v-for="incident in problem.related_incidents" :key="incident.id" :to="`/incidents/${incident.id}`" class="block p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
              <p class="text-sm font-medium text-primary-600">{{ incident.ticket_number }}</p>
              <p class="text-xs text-gray-500 truncate">{{ incident.title }}</p>
            </router-link>
          </div>
          <p v-else class="text-sm text-gray-400">No related incidents.</p>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Metadata</h2>
          <div class="space-y-2 text-sm">
            <div><span class="text-gray-500">Assignee:</span> {{ problem.assignee?.name || 'Unassigned' }}</div>
            <div><span class="text-gray-500">Category:</span> {{ [formatLabel(problem.category), formatLabel(problem.subcategory)].filter(Boolean).join(' › ') || '—' }}</div>
            <div><span class="text-gray-500">Reporter:</span> {{ problem.reported_by_name || '—' }}</div>
            <div><span class="text-gray-500">Requester:</span> {{ problem.requester_name || '—' }}</div>
            <div><span class="text-gray-500">Updated:</span> {{ formatDate(problem.updated_at) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="editing = false">
      <div class="card w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Edit Problem</h2>
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
                <option value="open">Open</option>
                <option value="in_progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
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
          <div>
            <label class="label">Assignee</label>
            <select v-model="form.assigned_to" class="select">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
          <CategorySelect type="problem" v-model:category="form.category" v-model:subcategory="form.subcategory" />
          <div>
            <label class="label">Root Cause</label>
            <textarea v-model="form.root_cause" class="textarea" rows="3"></textarea>
          </div>
          <div>
            <label class="label">Workaround</label>
            <textarea v-model="form.workaround" class="textarea" rows="3"></textarea>
          </div>
          <div class="flex items-center gap-2">
            <input id="known_error" v-model="form.known_error" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
            <label for="known_error" class="text-sm text-gray-700">Known Error</label>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button class="btn-secondary" @click="editing = false">Cancel</button>
          <button class="btn-primary" :disabled="saving" @click="saveProblem">{{ saving ? 'Saving...' : 'Save' }}</button>
        </div>
      </div>
    </div>

    <div v-if="showLinkModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showLinkModal = false">
      <div class="card w-full max-w-lg mx-4">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Link Incident</h2>
        <div>
          <label class="label">Incident Ticket# or ID</label>
          <input v-model="linkIncidentId" class="input" placeholder="Enter incident ticket number or ID" />
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button class="btn-secondary" @click="showLinkModal = false">Cancel</button>
          <button class="btn-primary" :disabled="linking" @click="linkIncident">{{ linking ? 'Linking...' : 'Link' }}</button>
        </div>
      </div>
    </div>
  </div>
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
const problem = ref(null)
const loading = ref(true)
const editing = ref(false)
const saving = ref(false)
const showLinkModal = ref(false)
const linking = ref(false)
const linkIncidentId = ref('')
const newComment = ref('')
const newInternalNote = ref('')


const form = reactive({
  title: '',
  description: '',
  root_cause: '',
  workaround: '',
  status: 'open',
  priority: 'medium',
  known_error: false,
  assigned_to: null,
  category: '',
  subcategory: '',
})

const users = ref([])

onMounted(() => { fetchProblem(); fetchUsers() })

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || res
  } catch (_) {}
}

async function fetchProblem() {
  loading.value = true
  try {
    const res = await client.get(`/tickets/${route.params.id}`)
    problem.value = res.data
    const td = problem.value.type_data || {}
    form.title = res.data.title || ''
    form.description = res.data.description || ''
    form.status = res.data.status || 'open'
    form.priority = res.data.priority || 'medium'
    form.assigned_to = res.data.assigned_to || null
    form.category = res.data.category || ''
    form.subcategory = res.data.subcategory || ''
    form.root_cause = td.root_cause || ''
    form.workaround = td.workaround || ''
    form.known_error = td.known_error || false
  } catch (err) {
    console.error('Failed to fetch problem:', err)
  } finally {
    loading.value = false
  }
}

async function saveProblem() {
  saving.value = true
  try {
    const payload = {
      title: form.title,
      description: form.description,
      status: form.status,
      priority: form.priority,
      assigned_to: form.assigned_to,
      category: form.category,
      subcategory: form.subcategory,
      type_data: {
        root_cause: form.root_cause,
        workaround: form.workaround,
        known_error: form.known_error ? 1 : 0,
      },
    }
    const res = await client.put(`/tickets/${route.params.id}`, payload)
    problem.value = res.data
    editing.value = false
  } catch (err) {
    console.error('Failed to update problem:', err)
  } finally {
    saving.value = false
  }
}

async function markAsKnownError() {
  try {
    const res = await client.put(`/tickets/${route.params.id}`, { type_data: { known_error: 1 } })
    problem.value = res.data
  } catch (err) {
    console.error('Failed to mark as known error:', err)
  }
}

async function addComment() {
  if (!newComment.value.trim()) return
  try {
    await client.post(`/tickets/${problem.value.id}/comments`, { body: newComment.value, is_internal: false })
    newComment.value = ''
    await fetchProblem()
  } catch (err) {
    console.error('Failed to add comment:', err)
  }
}

async function addInternalNote() {
  if (!newInternalNote.value.trim()) return
  try {
    await client.post(`/tickets/${problem.value.id}/comments`, { body: newInternalNote.value, is_internal: true })
    newInternalNote.value = ''
    await fetchProblem()
  } catch (err) {
    console.error('Failed to add internal note:', err)
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this problem?')) return
  try {
    await client.delete(`/tickets/${route.params.id}`)
    router.push('/problems')
  } catch (err) {
    console.error('Failed to delete problem:', err)
  }
}

async function linkIncident() {
  if (!linkIncidentId.value) return
  linking.value = true
  try {
    const res = await client.post(`/problems/${route.params.id}/link-incident`, { incident_id: linkIncidentId.value })
    problem.value = res.data
    showLinkModal.value = false
    linkIncidentId.value = ''
  } catch (err) {
    console.error('Failed to link incident:', err)
  } finally {
    linking.value = false
  }
}

function priorityBadge(priority) {
  switch (priority) {
    case 'critical': return 'badge-critical'
    case 'high': return 'badge-high'
    case 'medium': return 'badge-medium'
    case 'low': return 'badge-low'
    default: return 'badge-medium'
  }
}

function statusBadge(status) {
  const map = {
    open: 'badge bg-blue-100 text-blue-800',
    in_progress: 'badge bg-yellow-100 text-yellow-800',
    awaiting_vendor: 'badge bg-amber-100 text-amber-800',
    resolved: 'badge bg-green-100 text-green-800',
    closed: 'badge bg-gray-100 text-gray-800',
  }
  return map[status] || 'badge bg-gray-100 text-gray-800'
}

function statusLabel(status) {
  const map = {
    open: 'Open',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    closed: 'Closed',
  }
  return map[status] || formatLabel(status)
}

function formatDate(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString()
}

function formatDateTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
