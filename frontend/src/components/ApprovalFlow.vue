<template>
  <div class="card">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Approval Flow</h2>

    <div v-if="!approvals || !approvals.length" class="text-sm text-gray-400">
      <template v-if="noApprovalNeeded">{{ noApprovalLabel }}</template>
      <template v-else>No approval steps configured.</template>
    </div>

    <div v-else class="space-y-0">
      <div
        v-for="(step, i) in approvals"
        :key="step.id"
        class="relative flex gap-4 pb-6"
        :class="{ 'opacity-50': step.status === 'skipped' }"
      >
        <div class="flex flex-col items-center">
          <div
            class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
            :class="stepIcon(step.status)"
          >
            <AppIcon v-if="step.status === 'approved'" name="check" :size="14" />
            <AppIcon v-else-if="step.status === 'rejected'" name="x" :size="14" />
            <span v-else>{{ i + 1 }}</span>
          </div>
          <div v-if="i < approvals.length - 1" class="w-0.5 flex-1 mt-1" :class="stepLine(step.status)"></div>
        </div>

        <div class="flex-1 min-w-0 pt-1">
          <div class="flex items-center justify-between">
            <p class="text-sm font-medium text-gray-900">{{ stepLabel(step) }}</p>
            <span class="badge text-xs" :class="statusBadge(step.status)">{{ formatLabel(step.status) }}</span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5">
            <template v-if="step.status === 'pending'">
              <template v-if="step.id === activeStepId">
                Waiting for {{ step.approver_name || step.approver_role || 'approver' }}
              </template>
              <template v-else>
                Blocked &mdash; waiting for step {{ approvals.findIndex(s =&gt; s.status === 'pending') + 1 }} to complete first
              </template>
            </template>
            <template v-else-if="step.status === 'approved' && step.decided_by_name">
              Approved by {{ step.decided_by_name }}{{ step.decided_at ? ' at ' + formatDateTime(step.decided_at) : '' }}
            </template>
            <template v-else-if="step.status === 'rejected' && step.decided_by_name">
              Rejected by {{ step.decided_by_name }} &mdash; {{ step.comments || 'No reason given' }}
            </template>
            <template v-else-if="step.status === 'skipped'">Skipped</template>
          </p>

          <div v-if="step.status === 'pending' && canDecide(step)" class="flex gap-2 mt-2">
            <button
              class="btn-success btn-xs"
              :disabled="acting === step.id"
              @click="$emit('approve', step.id)"
            >{{ acting === step.id ? '...' : 'Approve' }}</button>
            <button
              class="btn-danger btn-xs"
              :disabled="acting === step.id"
              @click="rejectStep(step)"
            >{{ acting === step.id ? '...' : 'Reject' }}</button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="rejecting" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="rejecting = false">
      <div class="card w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Reject Approval</h3>
        <textarea v-model="rejectReason" class="textarea mb-4" rows="3" placeholder="Reason for rejection..."></textarea>
        <div class="flex justify-end gap-2">
          <button class="btn-secondary" @click="rejecting = false">Cancel</button>
          <button class="btn-danger" :disabled="!rejectReason.trim() || rejectingSubmitting" @click="submitReject">
            {{ rejectingSubmitting ? 'Submitting...' : 'Reject' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../utils/format'
import { ref, computed } from 'vue'
import client from '../api/client'
import AppIcon from './common/AppIcon.vue'

const props = defineProps({
  approvals: { type: Array, default: () => [] },
  currentUser: { type: Object, default: null },
  noApprovalLabel: { type: String, default: 'Approval not required.' },
  noApprovalNeeded: { type: Boolean, default: false },
})

const emit = defineEmits(['approve', 'rejected', 'updated'])

const acting = ref(null)
const rejecting = ref(false)
const rejectStepId = ref(null)
const rejectReason = ref('')
const rejectingSubmitting = ref(false)

function canDecide(step) {
  const user = props.currentUser
  if (!user) return false
  // Separation of duties - mirrors Approval::guardDecision on the server
  if (Number(user.id) === Number(step.requester_id || 0) || Number(user.id) === Number(step.reported_by || 0)) return false
  // Only the lowest still-pending step is active
  if (activeStepId.value !== step.id) return false
  if (user.role === 'admin') return true
  if (step.approver_type === 'user' && Number(step.approver_id) === Number(user.id)) return true
  if (step.approver_type === 'role' && step.approver_role === user.role) return true
  return false
}

const activeStepId = computed(() => {
  const pending = props.approvals.find((s) => s.status === 'pending')
  return pending ? pending.id : null
})

function stepIcon(status) {
  switch (status) {
    case 'approved': return 'bg-green-500 text-white'
    case 'rejected': return 'bg-red-500 text-white'
    case 'skipped': return 'bg-gray-300 text-gray-600'
    default: return 'bg-gray-200 text-gray-600'
  }
}

function stepLine(status) {
  if (status === 'approved') return 'bg-green-300'
  if (status === 'rejected') return 'bg-red-300'
  return 'bg-gray-200'
}

function statusBadge(status) {
  switch (status) {
    case 'approved': return 'badge-low'
    case 'rejected': return 'badge-critical'
    case 'skipped': return 'bg-gray-100 text-gray-600'
    default: return 'badge-medium'
  }
}

function stepLabel(step) {
  if (step.approver_name) return step.approver_name
  if (step.approver_role) return step.approver_role.charAt(0).toUpperCase() + step.approver_role.slice(1)
  return 'Approver'
}

function formatDateTime(date) {
  if (!date) return ''
  return new Date(date).toLocaleString()
}

function rejectStep(step) {
  rejectStepId.value = step.id
  rejectReason.value = ''
  rejecting.value = true
}

async function submitReject() {
  rejectingSubmitting.value = true
  try {
    await client.post(`/approvals/${rejectStepId.value}/reject`, { comments: rejectReason.value })
    emit('rejected')
  } catch (err) {
    console.error('Failed to reject:', err)
  } finally {
    rejectingSubmitting.value = false
    rejecting.value = false
  }
}
</script>
