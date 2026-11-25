<template>
  <div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="card">
        <p class="text-sm text-gray-500">Open Tickets</p>
        <p class="text-3xl font-bold text-gray-800 mt-1">{{ stats.open_tickets }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">SLA Breached</p>
        <p class="text-3xl font-bold text-red-600 mt-1">{{ stats.sla_breached }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">Resolved Today</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ stats.resolved_today }}</p>
      </div>
      <div class="card">
        <p class="text-sm text-gray-500">Open by Status</p>
        <p class="text-3xl font-bold text-primary-600 mt-1">{{ stats.open_by_status_count }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <div class="card">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Tickets by Status</h3>
        <Bar v-if="barData" :data="barData" :options="barOptions" />
        <p v-else class="text-sm text-gray-400">No data available.</p>
      </div>
      <div class="card">
        <h3 class="text-base font-semibold text-gray-800 mb-4">Priority Distribution</h3>
        <Pie v-if="pieData" :data="pieData" :options="pieOptions" />
        <p v-else class="text-sm text-gray-400">No data available.</p>
      </div>
    </div>

    <div class="card">
      <h3 class="text-base font-semibold text-gray-800 mb-4">Recent Open Tickets</h3>
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="table-header">ID</th>
              <th class="table-header">Title</th>
              <th class="table-header">Status</th>
              <th class="table-header">Priority</th>
              <th class="table-header">Assignee</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="ticket in recentTickets" :key="ticket.id" class="hover:bg-gray-50">
              <td class="table-cell text-gray-500">#{{ ticket.id }}</td>
              <td class="table-cell font-medium text-gray-800">{{ ticket.title }}</td>
              <td class="table-cell">
                <span class="badge" :class="statusBadge(ticket.status)">{{ formatLabel(ticket.status) }}</span>
              </td>
              <td class="table-cell">
                <span class="badge" :class="priorityBadge(ticket.priority)">{{ formatLabel(ticket.priority) }}</span>
              </td>
              <td class="table-cell text-gray-500">{{ ticket.assignee?.name || 'Unassigned' }}</td>
            </tr>
            <tr v-if="!recentTickets.length">
              <td colspan="5" class="table-cell text-center text-gray-400">No open tickets.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../utils/format'
import { ref, onMounted } from 'vue'
import { Bar, Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement } from 'chart.js'
import { useTicketStore } from '../stores/tickets'

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement)

const ticketStore = useTicketStore()

const stats = ref({
  open_tickets: 0,
  sla_breached: 0,
  resolved_today: 0,
  open_by_status_count: 0,
})

const recentTickets = ref([])

const barData = ref(null)
const pieData = ref(null)

const barOptions = {
  responsive: true,
  plugins: { legend: { display: false } },
  scales: {
    y: { beginAtZero: true, ticks: { stepSize: 1 } },
    x: { grid: { display: false } },
  },
}

const pieOptions = {
  responsive: true,
  plugins: { legend: { position: 'bottom' } },
}

function statusBadge(status) {
  const map = { Open: 'badge-high', 'In Progress': 'badge-medium', 'On Hold': 'badge-critical', Closed: 'badge-low' }
  return map[status] || 'badge-low'
}

function priorityBadge(priority) {
  const map = { Critical: 'badge-critical', High: 'badge-high', Medium: 'badge-medium', Low: 'badge-low' }
  return map[priority] || 'badge-low'
}

async function loadDashboard() {
  const data = await ticketStore.fetchDashboard()
  if (!data) return

  stats.value = {
    open_tickets: data.open_tickets ?? 0,
    sla_breached: data.sla_breached ?? 0,
    resolved_today: data.resolved_today ?? 0,
    open_by_status_count: data.open_by_status?.reduce((s, g) => s + g.count, 0) ?? 0,
  }

  recentTickets.value = data.recent_tickets ?? []

  if (data.open_by_status?.length) {
    barData.value = {
      labels: data.open_by_status.map((g) => g.status),
      datasets: [
        {
          label: 'Tickets',
          data: data.open_by_status.map((g) => g.count),
          backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6'],
          borderRadius: 4,
        },
      ],
    }
  }

  if (data.priority_distribution?.length) {
    pieData.value = {
      labels: data.priority_distribution.map((g) => g.priority),
      datasets: [
        {
          data: data.priority_distribution.map((g) => g.count),
          backgroundColor: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'],
          borderWidth: 0,
        },
      ],
    }
  }
}

onMounted(loadDashboard)
</script>
