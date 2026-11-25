<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '../../api/client'
import AppIcon from '../../components/common/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const item = ref(null)
const loading = ref(true)
const saving = ref(false)
const editing = ref(false)
const error = ref('')
const ciTypes = ref([])
const auditLog = ref([])
const showAudit = ref(false)

const environments = ['production', 'staging', 'development', 'testing', 'disaster_recovery']

const relationships = computed(() => item.value?.relationships || [])
const parents = computed(() => relationships.value.filter(r => r.relationship_type === 'parent_of'))
const children = computed(() => relationships.value.filter(r => r.relationship_type === 'child_of'))
const dependencies = computed(() => relationships.value.filter(r => !['parent_of', 'child_of'].includes(r.relationship_type)))

async function fetchItem() {
  loading.value = true
  try {
    const [itemRes, typesRes, auditRes] = await Promise.allSettled([
      client.get(`/ci-items/${route.params.id}`),
      client.get('/ci-types'),
      client.get(`/ci-items/${route.params.id}/audit`)
    ])
    if (itemRes.status === 'fulfilled') {
      item.value = itemRes.value.data
    }
    if (typesRes.status === 'fulfilled') {
      ciTypes.value = typesRes.value.data || []
    }
    if (auditRes.status === 'fulfilled') {
      auditLog.value = auditRes.value.data || []
    }
  } finally {
    loading.value = false
  }
}

async function saveChanges() {
  saving.value = true
  error.value = ''
  try {
    const res = await client.put(`/ci-items/${route.params.id}`, item.value)
    item.value = res.data
    editing.value = false
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to save.'
  } finally {
    saving.value = false
  }
}

async function deleteItem() {
  if (!confirm('Are you sure you want to delete this CI item?')) return
  error.value = ''
  try {
    await client.delete(`/ci-items/${route.params.id}`)
    router.push('/cmdb')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to delete.'
  }
}

function findCiName(id) {
  return id
}

onMounted(fetchItem)

function getTypeName(slug) {
  const t = ciTypes.value.find(ct => ct.slug === slug)
  return t ? t.name : slug
}
</script>

<template>
  <div class="p-4 md:p-6 max-w-6xl">
    <div v-if="loading" class="text-center text-gray-400 py-12">Loading...</div>
    <template v-else-if="item">
      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold">{{ item.name }}</h1>
          <span v-if="item.ci_type_name" class="badge-info text-xs inline-flex items-center gap-1"><AppIcon v-if="item.ci_type_icon" :name="item.ci_type_icon" :size="14" />{{ item.ci_type_name }}</span>
          <span :class="'badge-' + (item.status === 'active' ? 'success' : item.status === 'retired' ? 'default' : item.status === 'maintenance' ? 'warning' : 'info')">{{ formatLabel(item.status) }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="!editing" class="btn-secondary" @click="editing = true">Edit</button>
          <button class="btn-secondary" @click="showAudit = !showAudit">{{ showAudit ? 'Hide Audit' : 'Audit Log' }}</button>
          <button class="btn-danger" @click="deleteItem">Delete</button>
          <button class="btn-secondary" @click="router.push('/cmdb')">Back</button>
        </div>
      </div>

      <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
          <form v-if="editing" @submit.prevent="saveChanges" class="card p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium mb-1">Name</label>
                <input v-model="item.name" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select v-model="item.type" class="input w-full">
                  <option value="server">Server</option>
                  <option value="network">Network</option>
                  <option value="application">Application</option>
                  <option value="storage">Storage</option>
                  <option value="database">Database</option>
                  <option value="workstation">Workstation</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Status</label>
                <select v-model="item.status" class="input w-full">
                  <option value="active">Active</option>
                  <option value="maintenance">Maintenance</option>
                  <option value="retired">Retired</option>
                  <option value="decommissioned">Decommissioned</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Environment</label>
                <select v-model="item.environment" class="input w-full">
                  <option v-for="e in environments" :key="e" :value="e">{{ e }}</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Serial Number</label>
                <input v-model="item.serial_number" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Asset Tag</label>
                <input v-model="item.asset_tag" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Vendor</label>
                <input v-model="item.vendor" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Model</label>
                <input v-model="item.model" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">FQDN</label>
                <input v-model="item.fqdn" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">IP Address</label>
                <input v-model="item.ip_address" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Location</label>
                <input v-model="item.location" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Department</label>
                <input v-model="item.department" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Business Service</label>
                <input v-model="item.business_service" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Support Group</label>
                <input v-model="item.support_group" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">OS</label>
                <input v-model="item.os" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">OS Version</label>
                <input v-model="item.os_version" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">CPU</label>
                <input v-model="item.cpu" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">RAM</label>
                <input v-model="item.ram" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Disk Space</label>
                <input v-model="item.disk_space" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Version</label>
                <input v-model="item.version" class="input w-full" />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Owner ID</label>
                <input v-model="item.owner_id" class="input w-full" />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Description</label>
              <textarea v-model="item.description" class="input w-full" rows="3"></textarea>
            </div>
            <div class="flex gap-3">
              <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
              <button type="button" class="btn-secondary" @click="editing = false">Cancel</button>
            </div>
          </form>

          <div v-else class="card p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
              <div><span class="text-sm text-gray-500">Type</span><div class="font-medium">{{ formatLabel(item.type) }}</div></div>
              <div><span class="text-sm text-gray-500">Status</span><div class="font-medium">{{ formatLabel(item.status) }}</div></div>
              <div><span class="text-sm text-gray-500">Environment</span><div class="font-medium">{{ item.environment || 'production' }}</div></div>
              <div><span class="text-sm text-gray-500">Serial Number</span><div class="font-medium">{{ item.serial_number || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Asset Tag</span><div class="font-medium">{{ item.asset_tag || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Vendor</span><div class="font-medium">{{ item.vendor || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Model</span><div class="font-medium">{{ item.model || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Version</span><div class="font-medium">{{ item.version || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">FQDN</span><div class="font-medium">{{ item.fqdn || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">IP Address</span><div class="font-medium">{{ item.ip_address || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">MAC Address</span><div class="font-medium">{{ item.mac_address || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">OS</span><div class="font-medium">{{ item.os || '-' }}{{ item.os_version ? ' ' + item.os_version : '' }}</div></div>
              <div><span class="text-sm text-gray-500">CPU</span><div class="font-medium">{{ item.cpu || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">RAM</span><div class="font-medium">{{ item.ram || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Disk Space</span><div class="font-medium">{{ item.disk_space || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Location</span><div class="font-medium">{{ item.location || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Department</span><div class="font-medium">{{ item.department || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Support Group</span><div class="font-medium">{{ item.support_group || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Business Service</span><div class="font-medium">{{ item.business_service || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Purchase Date</span><div class="font-medium">{{ item.purchase_date || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Warranty End</span><div class="font-medium">{{ item.warranty_end || '-' }}</div></div>
              <div><span class="text-sm text-gray-500">Owner</span><div class="font-medium">{{ item.owner_id || '-' }}</div></div>
            </div>
            <div v-if="item.description" class="mt-4">
              <span class="text-sm text-gray-500">Description</span>
              <div class="mt-1">{{ item.description }}</div>
            </div>
          </div>

          <div v-if="item.notes" class="card p-4">
            <h3 class="text-sm font-semibold text-gray-500 mb-2">Notes</h3>
            <p>{{ item.notes }}</p>
          </div>

          <div v-if="showAudit" class="card p-4">
            <h3 class="text-lg font-semibold mb-3">Change History</h3>
            <div v-if="auditLog.length" class="space-y-2">
              <div v-for="log in auditLog" :key="log.id" class="text-sm flex gap-3 py-2 border-b border-gray-700 last:border-0">
                <span class="text-gray-400 w-20 shrink-0">{{ log.created_at?.split(' ')[0] }}</span>
                <span class="badge-info text-xs shrink-0">{{ formatLabel(log.action) }}</span>
                <span class="text-gray-400">{{ log.field_name ? log.field_name + ': ' : '' }}{{ log.summary }}</span>
                <span class="text-gray-500 ml-auto text-xs">{{ log.user_name }}</span>
              </div>
            </div>
            <p v-else class="text-gray-400 text-sm">No changes recorded.</p>
          </div>
        </div>

        <div class="space-y-6">
          <div class="card p-4">
            <h2 class="text-lg font-semibold mb-2">Relationships</h2>
            <div v-if="relationships.length" class="space-y-2 text-sm">
              <div v-for="r in relationships" :key="r.id + r.relationship_type" class="flex items-center justify-between py-1 border-b border-gray-700 last:border-0">
                <div class="flex items-center gap-2">
                  <span class="text-xs px-1.5 py-0.5 rounded bg-gray-700 text-gray-300">{{ formatLabel(r.relationship_type) }}</span>
                  <span>{{ r.name || 'CI #' + r.related_ci_id }}</span>
                </div>
              </div>
            </div>
            <p v-else class="text-gray-400 text-sm">No relationships defined.</p>
          </div>

          <div v-if="item.tags && item.tags.length" class="card p-4">
            <h2 class="text-lg font-semibold mb-2">Tags</h2>
            <div class="flex flex-wrap gap-1">
              <span v-for="tag in item.tags" :key="tag.id" class="text-xs px-2 py-0.5 rounded-full" :style="{ backgroundColor: tag.color + '30', color: tag.color, border: '1px solid ' + tag.color }">{{ tag.name }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
