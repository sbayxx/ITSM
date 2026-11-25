<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-900">Reports Dashboard</h1>
      <div class="flex items-center gap-3">
        <select
          v-if="(auth.isAdmin || auth.isCustomerAdmin) && (tab === 'compliance' || tab === 'checklists')"
          v-model="selectedCustomerId"
          @change="onCustomerChange"
          class="select w-auto"
        >
          <option value="">All Customers</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <label v-if="tab !== 'checklists'" class="text-sm text-gray-600">Period:</label>
        <select v-if="tab !== 'checklists'" v-model="period" @change="fetchAll" class="select w-auto">
          <option :value="7">Last 7 days</option>
          <option :value="30">Last 30 days</option>
          <option :value="90">Last 90 days</option>
        </select>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex items-center gap-1 mb-6 border-b border-gray-200">
      <button
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="tab === 'overview' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        @click="tab = 'overview'"
      >Overview</button>
      <button
        v-if="auth.isAdmin || auth.isCustomerAdmin"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="tab === 'compliance' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        @click="switchTab('compliance')"
      >Compliance</button>
      <button
        v-if="auth.isAdmin || auth.isCustomerAdmin"
        type="button"
        class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
        :class="tab === 'checklists' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-800'"
        @click="switchTab('checklists')"
      >Checklists</button>
    </div>

    <!-- ======================= Overview ======================= -->
    <div v-show="tab === 'overview'">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Ticket Volume Over Time</h3>
          <div v-if="ticketVolume.labels" class="chart-wrapper" style="height:300px"><Line :data="ticketVolume" :options="chartOptions" /></div>
          <p v-else class="text-gray-400 text-sm">Loading...</p>
        </div>

        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">Status Distribution</h3>
          <div v-if="statusDist.labels" class="chart-wrapper" style="height:300px"><Pie :data="statusDist" :options="pieOptions" /></div>
          <p v-else class="text-gray-400 text-sm">Loading...</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">SLA Compliance</h3>
          <div v-if="slaCompliance.labels" class="chart-wrapper" style="height:300px"><Bar :data="slaCompliance" :options="chartOptions" /></div>
          <p v-else class="text-gray-400 text-sm">Loading...</p>
        </div>

        <div class="card">
          <h3 class="text-lg font-semibold text-gray-800 mb-4">CSI Progress</h3>
          <div v-if="csiProgress.labels" class="chart-wrapper" style="height:300px"><Doughnut :data="csiProgress" :options="pieOptions" /></div>
          <p v-else class="text-gray-400 text-sm">Loading...</p>
        </div>
      </div>

      <div class="card p-0 overflow-x-auto">
        <div class="px-6 py-4 border-b border-gray-200">
          <h3 class="text-lg font-semibold text-gray-800">Agent Performance</h3>
        </div>
        <table class="w-full">
          <thead>
            <tr class="bg-gray-50 border-b border-gray-200">
              <th class="table-header">Agent</th>
              <th class="table-header">Tickets Assigned</th>
              <th class="table-header">Resolved</th>
              <th class="table-header">Avg Resolution (h)</th>
              <th class="table-header">SLA Compliance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="agent in agentPerformance" :key="agent.id" class="hover:bg-gray-50">
              <td class="table-cell font-medium">{{ agent.name }}</td>
              <td class="table-cell">{{ agent.assigned }}</td>
              <td class="table-cell">{{ agent.resolved }}</td>
              <td class="table-cell">{{ agent.avg_resolution_hours }}</td>
              <td class="table-cell">
                <span class="badge" :class="agent.sla_compliance >= 90 ? 'badge-low' : agent.sla_compliance >= 70 ? 'badge-medium' : 'badge-critical'">{{ agent.sla_compliance }}%</span>
              </td>
            </tr>
            <tr v-if="!agentPerformance.length">
              <td colspan="5" class="table-cell text-center text-gray-400">No agent data available.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ======================= Compliance ======================= -->
    <div v-if="tab === 'compliance'">
      <p v-if="complianceLoading" class="text-gray-400 text-sm">Loading compliance data...</p>

      <template v-else-if="frameworks.length">
        <!-- Framework summaries -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <div v-for="fw in frameworks" :key="fw.id" class="card">
            <div class="flex items-start justify-between mb-3">
              <div class="flex items-center gap-3">
                <div class="p-2.5 rounded-xl bg-gray-100">
                  <AppIcon :name="fw.id === 'gdpr' ? 'shield' : 'gauge'" :size="20" />
                </div>
                <div>
                  <h3 class="text-lg font-semibold text-gray-800">{{ fw.name }}</h3>
                  <p class="text-xs text-gray-500">{{ fw.reference }}</p>
                </div>
              </div>
              <span class="badge" :class="statusBadge(fw.status)">{{ statusLabel(fw.status) }}</span>
            </div>
            <div class="flex items-baseline gap-2 mb-2">
              <span class="text-3xl font-bold" :style="{ color: statusColor(fw.status) }">{{ fw.score }}%</span>
              <span class="text-xs text-gray-500">overall · {{ fw.assessed }}/{{ fw.total }} controls assessed</span>
            </div>
            <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
              <div class="h-full rounded-full transition-all" :style="{ width: fw.score + '%', backgroundColor: statusColor(fw.status) }"></div>
            </div>
          </div>
        </div>

        <!-- Control cards per framework -->
        <div v-for="fw in frameworks" :key="fw.id + '-cards'" class="mb-8">
          <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-semibold text-gray-800">{{ fw.name }} — control reports</h2>
            <span class="text-xs text-gray-400">Point-in-time snapshot {{ formatTs(generatedAt) }} · last {{ period }} days</span>
          </div>
          <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div v-for="c in fw.cards" :key="c.id" class="card">
              <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <AppIcon :name="c.icon" :size="16" class="text-gray-400" />
                  <div class="min-w-0">
                    <h4 class="text-sm font-semibold text-gray-800 truncate">{{ c.title }}</h4>
                    <span class="chip mt-0.5 inline-block">{{ c.reference }}</span>
                  </div>
                </div>
                <span class="badge shrink-0" :class="statusBadge(c.status)">{{ statusLabel(c.status) }}</span>
              </div>

              <div class="flex items-center gap-3 mb-1">
                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full rounded-full" :style="{ width: c.score + '%', backgroundColor: statusColor(c.status) }"></div>
                </div>
                <span class="text-xs font-semibold" :style="{ color: statusColor(c.status) }">{{ c.score }}</span>
              </div>

              <div class="grid grid-cols-2 gap-x-4 gap-y-3 mt-4">
                <div v-for="m in c.metrics" :key="m.label">
                  <div class="text-xs text-gray-500 leading-tight">{{ m.label }}</div>
                  <div
                    class="text-lg font-semibold leading-tight"
                    :class="m.tone === 'bad' ? 'text-red-600' : m.tone === 'good' ? 'text-emerald-600' : 'text-gray-900'"
                  >{{ m.value }}</div>
                </div>
              </div>

              <div v-if="c.detail && c.detail.rows && c.detail.rows.length" class="mt-4 pt-3 border-t border-gray-100">
                <div class="text-xs font-medium text-gray-500 mb-1.5">{{ c.detail.title }}</div>
                <div class="space-y-1">
                  <div v-for="(r, i) in c.detail.rows" :key="i" class="flex items-center justify-between gap-2 text-xs">
                    <span class="text-gray-600 truncate">{{ r.left }}</span>
                    <span class="text-gray-800 font-medium shrink-0">{{ r.right }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>

      <p v-else class="text-gray-400 text-sm">No compliance data available.</p>
    </div>

    <!-- ======================= Checklists ======================= -->
    <div v-if="tab === 'checklists'">
      <p v-if="checklistLoading" class="text-gray-400 text-sm">Loading checklists...</p>

      <template v-else-if="checklistFrameworks.length">
        <!-- Framework selector -->
        <div class="flex items-center gap-2 mb-6">
          <button
            v-for="fw in checklistFrameworks"
            :key="fw.id"
            type="button"
            class="px-4 py-2 text-sm font-medium rounded-lg border transition-colors"
            :class="checklistFramework === fw.id
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
            @click="checklistFramework = fw.id"
          >{{ fw.name }}</button>
        </div>

        <template v-for="fw in checklistFrameworks" :key="fw.id + '-cl'">
          <div v-if="fw.id === checklistFramework">
            <!-- Framework header -->
            <div class="card mb-6">
              <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="p-2.5 rounded-xl bg-gray-100">
                    <AppIcon :name="fw.id === 'gdpr' ? 'shield' : fw.id === 'iso27001' ? 'check-circle' : 'award'" :size="20" />
                  </div>
                  <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ fw.name }}</h3>
                    <p class="text-xs text-gray-500">{{ fw.reference }}</p>
                  </div>
                </div>
                <span class="badge" :class="statusBadge(fw.status)">{{ statusLabel(fw.status) }}</span>
              </div>
              <div class="flex items-baseline gap-2 mb-2">
                <span class="text-3xl font-bold" :style="{ color: statusColor(fw.status) }">{{ fw.score }}%</span>
                <span class="text-xs text-gray-500">dækningsgrad</span>
              </div>
              <div class="h-2 bg-gray-100 rounded-full overflow-hidden mb-4">
                <div class="h-full rounded-full transition-all" :style="{ width: fw.score + '%', backgroundColor: statusColor(fw.status) }"></div>
              </div>
              <!-- Stats row -->
              <div class="flex items-center gap-4 text-sm">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Dækket: {{ fw.stats.covered }}</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Delvist: {{ fw.stats.partial }}</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Mangler: {{ fw.stats.outstanding }}</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-400"></span> Ikke vurderet: {{ fw.stats.not_assessed }}</span>
              </div>
            </div>

            <!-- Checklist items -->
            <div class="space-y-3">
              <div v-for="item in fw.items" :key="item.id" class="card">
                <div class="flex items-start justify-between gap-3">
                  <div class="flex items-start gap-3 min-w-0 flex-1">
                    <!-- Status icon -->
                    <div class="mt-0.5 shrink-0">
                      <AppIcon
                        :name="item.status === 'covered' ? 'check-circle' : item.status === 'partial' ? 'clock' : item.status === 'outstanding' ? 'circle-alert' : 'help-circle'"
                        :size="18"
                        :style="{ color: statusColor(item.status) }"
                      />
                    </div>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-center gap-2 flex-wrap">
                        <h4 class="text-sm font-semibold text-gray-800">{{ item.title }}</h4>
                        <span class="chip text-xs">{{ item.reference }}</span>
                        <span class="badge text-xs" :class="statusBadge(item.status)">{{ statusLabel(item.status) }}</span>
                      </div>
                      <p class="text-xs text-gray-500 mt-1">{{ item.description }}</p>
                      <p v-if="item.evidence" class="text-xs text-gray-600 mt-2 flex items-start gap-1.5">
                        <AppIcon name="search" :size="12" class="text-gray-400 mt-0.5 shrink-0" />
                        <span>{{ item.evidence }}</span>
                      </p>
                      <div v-if="item.hint && item.status !== 'covered'" class="mt-2 p-3 bg-blue-50 border border-blue-100 rounded-lg">
                        <div class="flex items-start gap-2">
                          <AppIcon name="info" :size="14" class="text-blue-500 mt-0.5 shrink-0" />
                          <div>
                            <div class="text-xs font-semibold text-blue-700">Sådan løses det</div>
                            <p class="text-xs text-blue-600 mt-0.5">{{ item.hint }}</p>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </template>

      <p v-else class="text-gray-400 text-sm">No checklist data available.</p>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../../components/common/AppIcon.vue'
import { Line, Pie, Bar, Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler } from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, BarElement, ArcElement, Title, Tooltip, Legend, Filler)

const auth = useAuthStore()
const period = ref(30)
const tab = ref('overview')
const ticketVolume = ref({})
const statusDist = ref({})
const slaCompliance = ref({})
const csiProgress = ref({})
const agentPerformance = ref([])
const loading = ref(true)

const frameworks = ref([])
const generatedAt = ref('')
const complianceLoaded = ref(false)
const complianceLoading = ref(false)
const customers = ref([])
const selectedCustomerId = ref('')

const checklistFrameworks = ref([])
const checklistFramework = ref('gdpr')
const checklistLoaded = ref(false)
const checklistLoading = ref(false)

const STATUS_LABELS = {
  compliant: 'Compliant',
  partial: 'Partially compliant',
  non_compliant: 'Non-compliant',
  not_assessed: 'Not assessed',
}
const STATUS_BADGES = {
  compliant: 'badge-low',
  partial: 'badge-medium',
  non_compliant: 'badge-critical',
  not_assessed: 'badge-info',
}
const STATUS_COLORS = {
  compliant: '#10b981',
  partial: '#f59e0b',
  non_compliant: '#ef4444',
  not_assessed: '#94a3b8',
}

function statusLabel(s) { return STATUS_LABELS[s] || formatLabel(s) }
function statusBadge(s) { return STATUS_BADGES[s] || 'badge-info' }
function statusColor(s) { return STATUS_COLORS[s] || '#94a3b8' }
function formatTs(ts) {
  if (!ts) return ''
  try { return new Date(ts).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) } catch { return ts }
}

const chartOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: { legend: { position: 'bottom' } },
}

const pieOptions = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: { legend: { position: 'bottom' } },
}

function buildChartData(raw, labelKey, valueKey, bgColors) {
  const defaultColors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899']
  return {
    labels: raw.map(r => formatLabel(r[labelKey])),
    datasets: [{
      data: raw.map(r => r[valueKey]),
      backgroundColor: bgColors || raw.map((_, i) => defaultColors[i % defaultColors.length]),
      borderWidth: 1,
    }],
  }
}

async function fetchTicketVolume() {
  try {
    const res = await client.get('/reports/ticket-volume', { params: { period: period.value } })
    const data = res.data || res
    if (!data || !data.labels) { ticketVolume.value = {}; return }
    ticketVolume.value = {
      labels: data.labels,
      datasets: data.datasets.map(d => ({
        label: formatLabel(d.label),
        data: d.data,
        borderColor: d.label === 'incident' ? '#3b82f6' : d.label === 'service_request' ? '#10b981' : d.label === 'problem' ? '#f59e0b' : '#8b5cf6',
        backgroundColor: d.label === 'incident' ? 'rgba(59, 130, 246, 0.1)' : d.label === 'service_request' ? 'rgba(16, 185, 129, 0.1)' : d.label === 'problem' ? 'rgba(245, 158, 11, 0.1)' : 'rgba(139, 92, 246, 0.1)',
        fill: true,
        tension: 0.4,
      })),
    }
  } catch { ticketVolume.value = {} }
}

async function fetchStatusDistribution() {
  try {
    const res = await client.get('/reports/status-distribution')
    const data = res.data || res
    if (!Array.isArray(data)) { statusDist.value = {}; return }
    statusDist.value = buildChartData(data, 'status', 'count')
  } catch { statusDist.value = {} }
}

async function fetchSlaCompliance() {
  try {
    const res = await client.get('/reports/sla-compliance')
    const data = res.data || res
    if (!Array.isArray(data)) { slaCompliance.value = {}; return }
    slaCompliance.value = buildChartData(data, 'priority', 'compliant_percent', ['#10b981', '#f59e0b', '#ef4444'])
  } catch { slaCompliance.value = {} }
}

async function fetchCsiProgress() {
  try {
    const res = await client.get('/reports/csi-progress')
    const data = res.data || res
    const stageData = [
      { stage: 'Completed', count: 0 },
      { stage: 'In Progress', count: 0 },
      { stage: 'Planned', count: 0 },
      { stage: 'Cancelled', count: 0 },
    ]
    if (Array.isArray(data)) {
      data.forEach(r => {
        stageData[0].count += r.completed || 0
        stageData[3].count += r.cancelled || 0
        const inProgress = (r.total || 0) - (r.completed || 0) - (r.cancelled || 0)
        if (inProgress > 0) stageData[1].count += inProgress
      })
    }
    csiProgress.value = buildChartData(stageData.filter(s => s.count > 0), 'stage', 'count', ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'])
  } catch { csiProgress.value = {} }
}

async function fetchAgentPerformance() {
  try {
    const res = await client.get('/reports/agent-performance')
    const data = res.data || res
    agentPerformance.value = (Array.isArray(data) ? data : []).map(a => ({
      id: a.id,
      name: a.name,
      assigned: a.assigned_count || 0,
      resolved: Number(a.resolved_count) || 0,
      avg_resolution_hours: a.avg_resolution_hours ? Number(a.avg_resolution_hours).toFixed(1) : '-',
      sla_compliance: a.sla_compliance ?? Math.round((Number(a.resolved_count || 0) / Math.max(Number(a.assigned_count || 1), 1)) * 100),
    }))
  } catch { agentPerformance.value = [] }
}

async function fetchCompliance() {
  complianceLoading.value = true
  try {
    const params = { period: period.value }
    if (selectedCustomerId.value) params.customer_id = selectedCustomerId.value
    const res = await client.get('/reports/compliance', { params })
    const data = res.data || res
    frameworks.value = (data && data.frameworks) || []
    generatedAt.value = (data && data.generated_at) || ''
    complianceLoaded.value = true
  } catch {
    frameworks.value = []
  } finally {
    complianceLoading.value = false
  }
}

async function fetchChecklists() {
  checklistLoading.value = true
  try {
    const params = {}
    if (selectedCustomerId.value) params.customer_id = selectedCustomerId.value
    const res = await client.get('/reports/checklists', { params })
    const data = res.data || res
    checklistFrameworks.value = (data && data.frameworks) || []
    checklistLoaded.value = true
  } catch {
    checklistFrameworks.value = []
  } finally {
    checklistLoading.value = false
  }
}

function onCustomerChange() {
  if (tab.value === 'compliance') fetchCompliance()
  else if (tab.value === 'checklists') fetchChecklists()
}

function switchTab(t) {
  tab.value = t
  if (t === 'compliance' && !complianceLoaded.value && !complianceLoading.value) fetchCompliance()
  if (t === 'checklists' && !checklistLoaded.value && !checklistLoading.value) fetchChecklists()
}

async function fetchAll() {
  loading.value = true
  try {
    await Promise.all([fetchTicketVolume(), fetchStatusDistribution(), fetchSlaCompliance(), fetchCsiProgress(), fetchAgentPerformance()])
    if (complianceLoaded.value && tab.value === 'compliance') await fetchCompliance()
  } finally { loading.value = false }
}

onMounted(async () => {
  if (auth.isAdmin || auth.isCustomerAdmin) {
    try {
      const res = await client.get('/customers')
      customers.value = res.data || []
    } catch { /* ignore */ }
  }
  fetchAll()
})
</script>
