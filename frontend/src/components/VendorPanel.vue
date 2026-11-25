<template>
  <div class="card">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Vendor</h2>
      <span v-if="ticket.status === 'awaiting_vendor'" class="badge badge-medium">Awaiting vendor</span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
      <div>
        <div class="text-xs text-gray-500 mb-1">Sent to</div>
        <div class="text-sm text-gray-900">{{ vendorName || '—' }}</div>
      </div>
      <div>
        <div class="text-xs text-gray-500 mb-1">Sent at</div>
        <div class="text-sm text-gray-900">{{ formatDate(ticket.vendor_requested_at) }}</div>
      </div>
    </div>

    <form @submit.prevent="saveExternalRef" class="flex items-end gap-2 mb-4">
      <div class="flex-1">
        <label class="label" :for="'ext-' + ticket.id">External ID (vendor's case number)</label>
        <input :id="'ext-' + ticket.id" v-model="externalRef" class="input w-full" maxlength="255" placeholder="e.g. VEND-12345" />
      </div>
      <button type="submit" class="btn btn-ghost" :disabled="savingRef || externalRef === (ticket.external_ref || '')">
        {{ savingRef ? 'Saving...' : 'Save' }}
      </button>
    </form>

    <div class="flex items-center justify-between gap-3">
      <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
      <p v-else-if="notice" class="text-sm text-emerald-400">{{ notice }}</p>
      <span v-else></span>
      <button v-if="canSend" @click="openModal" class="btn btn-primary gap-1.5">
        <AppIcon name="truck" :size="16" />
        Send to vendor
      </button>
    </div>

    <!-- Send modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeModal">
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700/50">
          <h3 class="text-lg font-semibold text-surface-100">Send {{ ticket.ticket_number }} to vendor</h3>
          <button @click="closeModal" class="text-surface-500 hover:text-surface-300">
            <AppIcon name="x" :size="18" />
          </button>
        </div>
        <form @submit.prevent="send" class="p-6 space-y-4">
          <div v-if="sendError" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-sm text-red-400">{{ sendError }}</div>

          <div>
            <label class="label">Recipient</label>
            <select v-model="choice" class="select w-full" required>
              <option value="" disabled>Choose a vendor or enter an address</option>
              <option v-for="v in activeVendors" :key="v.id" :value="String(v.id)">
                {{ v.name }} ({{ modeLabel(v.integration_mode) }})
              </option>
              <option value="__email__">Other email address…</option>
            </select>
            <p v-if="!activeVendors.length" class="text-xs text-surface-500 mt-1">No vendors are set up for this customer yet.</p>
          </div>

          <div v-if="choice === '__email__'">
            <label class="label">Email address</label>
            <input v-model="freeEmail" type="email" class="input w-full" required placeholder="vendor@example.com" />
          </div>

          <div>
            <label class="label">Message to vendor</label>
            <textarea v-model="message" class="input w-full" rows="5" required maxlength="5000"></textarea>
          </div>

          <p class="text-xs text-surface-500">
            The ticket is set to "Awaiting vendor". A reply from the vendor is added to the ticket and moves it back to "In progress".
          </p>

          <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="closeModal" class="btn btn-ghost">Cancel</button>
            <button type="submit" class="btn btn-primary" :disabled="sending">{{ sending ? 'Sending...' : 'Send' }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import client from '../api/client'
import { useAuthStore } from '../stores/auth'
import AppIcon from './common/AppIcon.vue'

const props = defineProps({ ticket: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const auth = useAuthStore()

const vendors = ref([])
const externalRef = ref(props.ticket.external_ref || '')
const savingRef = ref(false)
const error = ref('')
const notice = ref('')

const showModal = ref(false)
const choice = ref('')
const freeEmail = ref('')
const message = ref('')
const sending = ref(false)
const sendError = ref('')

const SENDABLE_ROLES = ['admin', 'agent', 'manager', 'customer_admin']
const CLOSED = ['resolved', 'closed', 'cancelled', 'rejected']

const canSend = computed(() =>
  SENDABLE_ROLES.includes(auth.user?.role) && !CLOSED.includes(props.ticket.status)
)
const activeVendors = computed(() => vendors.value.filter(v => v.is_active))
const vendorName = computed(() => {
  if (!props.ticket.vendor_id) return ''
  return vendors.value.find(v => v.id === props.ticket.vendor_id)?.name || `Vendor #${props.ticket.vendor_id}`
})

const MODE_LABELS = { email: 'Email', api: 'API', both: 'API + email' }
function modeLabel(m) { return MODE_LABELS[m] || m }

function formatDate(d) {
  if (!d) return '—'
  try { return new Date(d.replace(' ', 'T')).toLocaleString() } catch { return d }
}

watch(() => props.ticket.external_ref, v => { externalRef.value = v || '' })

async function loadVendors() {
  try {
    const { data } = await client.get('/vendors', { params: { customer_id: props.ticket.customer_id } })
    vendors.value = Array.isArray(data) ? data : []
  } catch {
    vendors.value = []
  }
}

async function saveExternalRef() {
  savingRef.value = true
  error.value = ''
  notice.value = ''
  try {
    await client.put(`/tickets/${props.ticket.id}`, { external_ref: externalRef.value.trim() || null })
    notice.value = 'External ID saved'
    emit('changed')
  } catch (e) {
    error.value = e?.message || 'Could not save External ID'
  } finally {
    savingRef.value = false
  }
}

function openModal() {
  sendError.value = ''
  choice.value = ''
  freeEmail.value = ''
  message.value = ''
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function send() {
  sending.value = true
  sendError.value = ''
  const payload = { message: message.value.trim() }
  if (choice.value === '__email__') payload.email = freeEmail.value.trim()
  else payload.vendor_id = Number(choice.value)
  try {
    await client.post(`/tickets/${props.ticket.id}/vendor-request`, payload)
    showModal.value = false
    notice.value = 'Sent to vendor'
    emit('changed')
  } catch (e) {
    sendError.value = e?.message || 'Could not send to vendor'
  } finally {
    sending.value = false
  }
}

onMounted(loadVendors)
</script>
