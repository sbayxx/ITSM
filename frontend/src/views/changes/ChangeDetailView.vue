<template>
  <div v-if="loading" class="text-center py-12 text-gray-400">Loading...</div>

  <div v-else-if="!change" class="text-center py-12 text-gray-400">Change not found</div>

  <div v-else>
    <div class="flex items-center justify-between mb-6">
      <div>
        <router-link to="/changes" class="text-sm text-primary-600 hover:text-primary-700 mb-1 inline-flex items-center gap-1"><AppIcon name="arrow-left" :size="14" />Back to Changes</router-link>
        <h1 class="text-2xl font-bold text-gray-900">{{ change.ticket_number }}: {{ change.title }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <button v-if="canImplement" class="btn-success" @click="implementChange">Implement</button>
        <button class="btn-primary" @click="editing = true">Edit</button>
        <button class="btn-danger" @click="deleteItem">Delete</button>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 space-y-6">
        <VendorPanel v-if="change" :ticket="change" @changed="fetchChange" />
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Change Details</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
              <span class="label">Change Type</span>
              <span :class="changeTypeBadge(change.change_type)">{{ formatLabel(change.change_type) }}</span>
            </div>
            <div>
              <span class="label">Risk Level</span>
              <span :class="riskBadge(change.risk_level)">{{ change.risk_level }}</span>
            </div>
            <div>
              <span class="label">Status</span>
              <span :class="statusBadge(change.status)">{{ statusLabel(change.status) }}</span>
            </div>
            <div>
              <span class="label">CAB Required</span>
              <span class="text-sm text-gray-900">{{ change.cab_required ? 'Yes' : 'No' }}</span>
            </div>
            <div>
              <span class="label">CAB Decision</span>
              <span v-if="change.cab_required" class="badge" :class="change.cab_decision === 'approved' ? 'badge-low' : change.cab_decision === 'rejected' ? 'badge-critical' : 'badge-medium'">{{ change.cab_decision }}</span>
              <span v-else class="text-sm text-gray-400">N/A</span>
            </div>
            <div>
              <span class="label">Scheduled Start</span>
              <span class="text-sm text-gray-900">{{ formatDate(change.scheduled_start) || '—' }}</span>
            </div>
            <div>
              <span class="label">Scheduled End</span>
              <span class="text-sm text-gray-900">{{ formatDate(change.scheduled_end) || '—' }}</span>
            </div>
          </div>
          <div class="mb-4">
            <span class="label">Description</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ change.description || 'No description' }}</p>
          </div>
          <div class="mb-4">
            <span class="label">Impact Assessment</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ change.impact_assessment || '—' }}</p>
          </div>
          <div class="mb-4">
            <span class="label">Implementation Plan</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ change.implementation_plan || '—' }}</p>
          </div>
          <div class="mb-4">
            <span class="label">Rollback Plan</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ change.rollback_plan || '—' }}</p>
          </div>
          <div>
            <span class="label">Test Plan</span>
            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ change.test_plan || '—' }}</p>
          </div>
        </div>

        <ApprovalFlow
          :approvals="change.approvals"
          :currentUser="currentUser"
          :noApprovalNeeded="change.change_type === 'standard'"
          noApprovalLabel="Standard change — pre-approved."
          @approve="handleApprove"
          @rejected="fetchChange"
        />

        <div class="card" v-if="change.cab_required">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">CAB Voting</h2>
          <div v-if="change.cab_votes && change.cab_votes.length > 0" class="space-y-2 mb-4">
            <div v-for="vote in change.cab_votes" :key="vote.id" class="flex items-center justify-between p-2 bg-gray-50 rounded-lg text-sm">
              <span class="font-medium text-gray-700">{{ vote.user_name }}</span>
              <span :class="voteBadge(vote.vote)">{{ vote.vote }}</span>
            </div>
          </div>
          <p v-else class="text-sm text-gray-400 mb-4">No votes yet.</p>
          <div v-if="change.status === 'pending_approval' || change.cab_decision === 'pending'" class="flex gap-2">
            <button class="btn-success btn-sm" @click="castVote('approve')">Approve</button>
            <button class="btn-danger btn-sm" @click="castVote('reject')">Reject</button>
            <button class="btn-secondary btn-sm" @click="castVote('abstain')">Abstain</button>
          </div>
        </div>

        <div class="card" v-if="change.status === 'implemented'">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Post-Implementation Review</h2>
          <form @submit.prevent="submitReview" class="space-y-3">
            <label class="label">Notes</label>
            <textarea v-model="review.notes" class="textarea" rows="3" placeholder="Any issues or observations..."></textarea>
            <button type="submit" class="btn-primary" :disabled="reviewing">{{ reviewing ? 'Submitting...' : 'Submit Review' }}</button>
          </form>
        </div>

        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Updates</h2>
          <div class="space-y-4 mb-6">
            <div v-for="comment in change.comments" :key="comment.id" class="border-l-2 pl-4" :class="comment.is_internal == 1 ? 'border-yellow-400' : 'border-gray-200'">
              <div class="flex items-center gap-2 mb-1">
                <span v-if="comment.is_internal == 1" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">Internal</span>
              </div>
              <p class="text-sm text-gray-700">{{ comment.body }}</p>
              <p class="text-xs text-gray-500 mt-1">{{ comment.user_name }} - {{ formatDateTime(comment.created_at) }}</p>
            </div>
            <div v-if="!change.comments?.length" class="text-sm text-gray-500">No updates yet.</div>
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
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Metadata</h2>
          <div class="space-y-2 text-sm">
            <div><span class="text-gray-500">Assignee:</span> {{ change.assigned_to_name || change.assignee?.name || 'Unassigned' }}</div>
            <div><span class="text-gray-500">Reporter:</span> {{ change.reported_by_name || '—' }}</div>
            <div><span class="text-gray-500">Requester:</span> {{ change.requester_name || '—' }}</div>
            <div><span class="text-gray-500">Category:</span> {{ [formatLabel(change.category), formatLabel(change.subcategory)].filter(Boolean).join(' › ') || '—' }}</div>
            <div><span class="text-gray-500">Created:</span> {{ formatDate(change.created_at) }}</div>
            <div><span class="text-gray-500">Updated:</span> {{ formatDate(change.updated_at) }}</div>
            <div v-if="change.implemented_at"><span class="text-gray-500">Implemented:</span> {{ formatDate(change.implemented_at) }}</div>
            <div v-if="change.reviewed_at"><span class="text-gray-500">Reviewed:</span> {{ formatDate(change.reviewed_at) }}</div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="editing = false">
      <div class="card w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Edit Change</h2>
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
                <option value="draft">Draft</option>
                <option value="pending_approval">Pending Approval</option>
                <option value="approved">Approved</option>
                <option value="in_progress">In Progress</option>
                <option value="implemented">Implemented</option>
                <option value="reviewed">Reviewed</option>
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
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label">Change Type</label>
              <select v-model="form.change_type" class="select">
                <option value="normal">Normal</option>
                <option value="standard">Standard</option>
                <option value="emergency">Emergency</option>
              </select>
            </div>
            <div>
              <label class="label">Risk Level</label>
              <select v-model="form.risk_level" class="select">
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
          <CategorySelect type="change" v-model:category="form.category" v-model:subcategory="form.subcategory" />
          <div>
            <label class="label">Impact Assessment</label>
            <textarea v-model="form.impact_assessment" class="textarea" rows="2"></textarea>
          </div>
          <div>
            <label class="label">Implementation Plan</label>
            <textarea v-model="form.implementation_plan" class="textarea" rows="2"></textarea>
          </div>
          <div>
            <label class="label">Rollback Plan</label>
            <textarea v-model="form.rollback_plan" class="textarea" rows="2"></textarea>
          </div>
          <div>
            <label class="label">Test Plan</label>
            <textarea v-model="form.test_plan" class="textarea" rows="2"></textarea>
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
            <input id="edit_cab_required" v-model="form.cab_required" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
            <label for="edit_cab_required" class="text-sm text-gray-700">CAB Approval Required</label>
          </div>
        </div>
        <div class="flex justify-end gap-2 mt-6">
          <button class="btn-secondary" @click="editing = false">Cancel</button>
          <button class="btn-primary" :disabled="saving" @click="saveChange">{{ saving ? 'Saving...' : 'Save' }}</button>
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
import ApprovalFlow from '../../components/ApprovalFlow.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const change = ref(null)
const loading = ref(true)
const editing = ref(false)
const saving = ref(false)
const reviewing = ref(false)
const newComment = ref('')
const newInternalNote = ref('')

const currentUser = computed(() => auth.user)

const form = reactive({
  title: '',
  description: '',
  status: 'draft',
  priority: 'medium',
  change_type: 'normal',
  risk_level: 'medium',
  assigned_to: null,
  category: '',
  subcategory: '',
  impact_assessment: '',
  implementation_plan: '',
  rollback_plan: '',
  test_plan: '',
  cab_required: false,
  scheduled_start: '',
  scheduled_end: '',
})

const users = ref([])

const review = reactive({
  notes: '',
})

const canImplement = computed(() => {
  const s = change.value?.status
  return s === 'approved' || s === 'in_progress'
})

onMounted(() => {
  fetchChange()
  fetchUsers()
})

async function fetchUsers() {
  try {
    const res = await client.get('/users')
    users.value = res.data || res
  } catch (_) {}
}

function toDatetimeLocal(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

async function fetchChange() {
  loading.value = true
  try {
    const res = await client.get(`/tickets/${route.params.id}`)
    change.value = res.data || res
    const td = change.value.type_data || {}
    form.title = change.value.title || ''
    form.description = change.value.description || ''
    form.status = change.value.status || 'draft'
    form.priority = change.value.priority || 'medium'
    form.assigned_to = change.value.assigned_to || null
    form.category = change.value.category || ''
    form.subcategory = change.value.subcategory || ''
    form.change_type = td.change_type || 'normal'
    form.risk_level = td.risk_level || 'medium'
    form.impact_assessment = td.impact_assessment || ''
    form.implementation_plan = td.implementation_plan || ''
    form.rollback_plan = td.rollback_plan || ''
    form.test_plan = td.test_plan || ''
    form.cab_required = td.cab_required === 1 || td.cab_required === '1'
    form.scheduled_start = toDatetimeLocal(td.scheduled_start)
    form.scheduled_end = toDatetimeLocal(td.scheduled_end)
  } catch (err) {
    console.error('Failed to fetch change:', err)
  } finally {
    loading.value = false
  }
}

async function handleApprove(approvalId) {
  try {
    await client.post(`/approvals/${approvalId}/approve`)
    await fetchChange()
  } catch (err) {
    console.error('Failed to approve:', err)
  }
}

async function saveChange() {
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
        change_type: form.change_type,
        risk_level: form.risk_level,
        impact_assessment: form.impact_assessment,
        implementation_plan: form.implementation_plan,
        rollback_plan: form.rollback_plan,
        test_plan: form.test_plan,
        cab_required: form.cab_required ? 1 : 0,
      },
    }
    if (form.scheduled_start) {
      payload.type_data.scheduled_start = new Date(form.scheduled_start).toISOString()
    }
    if (form.scheduled_end) {
      payload.type_data.scheduled_end = new Date(form.scheduled_end).toISOString()
    }
    const res = await client.put(`/tickets/${route.params.id}`, payload)
    change.value = res.data || res
    editing.value = false
  } catch (err) {
    console.error('Failed to update change:', err)
  } finally {
    saving.value = false
  }
}

async function implementChange() {
  try {
    const res = await client.post(`/changes/${route.params.id}/implement`)
    await fetchChange()
  } catch (err) {
    console.error('Failed to implement change:', err)
  }
}

async function submitReview() {
  reviewing.value = true
  try {
    await client.post(`/changes/${route.params.id}/review`, review)
    review.notes = ''
    await fetchChange()
  } catch (err) {
    console.error('Failed to submit review:', err)
  } finally {
    reviewing.value = false
  }
}

async function castVote(vote) {
  try {
    await client.post(`/changes/${change.value.id}/cab-vote`, { vote })
    await fetchChange()
  } catch (err) {
    console.error('Failed to cast vote:', err)
  }
}

async function addComment() {
  if (!newComment.value.trim()) return
  try {
    await client.post(`/tickets/${change.value.id}/comments`, { body: newComment.value, is_internal: false })
    newComment.value = ''
    await fetchChange()
  } catch (err) {
    console.error('Failed to add comment:', err)
  }
}

async function addInternalNote() {
  if (!newInternalNote.value.trim()) return
  try {
    await client.post(`/tickets/${change.value.id}/comments`, { body: newInternalNote.value, is_internal: true })
    newInternalNote.value = ''
    await fetchChange()
  } catch (err) {
    console.error('Failed to add internal note:', err)
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this change?')) return
  try {
    await client.delete(`/tickets/${route.params.id}`)
    router.push('/changes')
  } catch (err) {
    console.error('Failed to delete change:', err)
  }
}

function changeTypeBadge(type) {
  const map = {
    normal: 'badge bg-blue-100 text-blue-800',
    standard: 'badge bg-green-100 text-green-800',
    emergency: 'badge bg-red-100 text-red-800',
  }
  return map[type] || 'badge bg-gray-100 text-gray-800'
}

function riskBadge(risk) {
  switch (risk) {
    case 'high': return 'badge-critical'
    case 'medium': return 'badge-high'
    case 'low': return 'badge-low'
    default: return 'badge-medium'
  }
}

function statusBadge(status) {
  const map = {
    draft: 'badge bg-gray-100 text-gray-800',
    pending_approval: 'badge bg-yellow-100 text-yellow-800',
    approved: 'badge bg-green-100 text-green-800',
    in_progress: 'badge bg-blue-100 text-blue-800',
    awaiting_vendor: 'badge bg-amber-100 text-amber-800',
    implemented: 'badge bg-purple-100 text-purple-800',
    reviewed: 'badge bg-teal-100 text-teal-800',
    closed: 'badge bg-gray-100 text-gray-800',
  }
  return map[status] || 'badge bg-gray-100 text-gray-800'
}

function statusLabel(status) {
  const map = {
    draft: 'Draft',
    pending_approval: 'Pending Approval',
    approved: 'Approved',
    in_progress: 'In Progress',
    implemented: 'Implemented',
    reviewed: 'Reviewed',
    closed: 'Closed',
  }
  return map[status] || formatLabel(status)
}

function voteBadge(vote) {
  const map = {
    approve: 'badge bg-green-100 text-green-800',
    reject: 'badge bg-red-100 text-red-800',
    abstain: 'badge bg-gray-100 text-gray-800',
  }
  return map[vote] || 'badge bg-gray-100 text-gray-800'
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
