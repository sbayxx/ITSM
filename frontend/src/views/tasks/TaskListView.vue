<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-surface-100">Tasks</h1>
        <p class="text-surface-400 text-sm mt-1">All your work in one place, with or without the Year Wheel</p>
      </div>
      <div class="flex items-center gap-3">
        <select v-if="auth.isAdmin" v-model="selectedCustomerId" @change="fetchTasks" class="input text-sm w-auto">
          <option value="">All Customers</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <button @click="openForm()" class="btn btn-primary gap-1.5">
          <AppIcon name="plus" :size="16" />
          New Task
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 p-4">
        <p class="text-xs text-surface-500">Planned</p>
        <p class="text-2xl font-bold text-surface-100 mt-1">{{ counts.planned }}</p>
      </div>
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 p-4">
        <p class="text-xs text-surface-500">In Progress</p>
        <p class="text-2xl font-bold text-warning-500 mt-1">{{ counts.in_progress }}</p>
      </div>
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 p-4">
        <p class="text-xs text-surface-500">Overdue</p>
        <p class="text-2xl font-bold mt-1" :class="counts.overdue ? 'text-red-500' : 'text-surface-100'">{{ counts.overdue }}</p>
      </div>
      <div class="bg-surface-800 rounded-xl border border-surface-700/50 p-4">
        <p class="text-xs text-surface-500">On Year Wheel</p>
        <p class="text-2xl font-bold text-surface-100 mt-1">{{ counts.wheel }}</p>
      </div>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
      <div class="flex items-center gap-2 bg-surface-800 rounded-lg p-1">
        <button v-for="s in scopes" :key="s.key" @click="scope = s.key; fetchTasks()"
          class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
          :class="scope === s.key ? 'bg-primary-500 text-white shadow-sm' : 'text-surface-400 hover:text-surface-200'">
          {{ s.label }}
        </button>
      </div>
      <select v-model="filters.status" @change="fetchTasks" class="input text-sm w-auto">
        <option value="">All statuses</option>
        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
      <select v-model="filters.category_id" @change="fetchTasks" class="input text-sm w-auto">
        <option value="">All categories</option>
        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <select v-if="scope === 'all'" v-model="filters.assigned_to" @change="fetchTasks" class="input text-sm w-auto">
        <option value="">Anyone</option>
        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
      </select>
      <select v-model="wheelFilter" @change="fetchTasks" class="input text-sm w-auto">
        <option value="">Wheel: all</option>
        <option value="1">On the wheel</option>
        <option value="0">Off the wheel</option>
      </select>
    </div>

    <div v-if="loading" class="text-center py-16">
      <AppIcon name="loader" :size="30" class="animate-spin text-surface-500 mb-3 inline-block" />
      <p class="text-surface-500 text-sm">Loading tasks...</p>
    </div>

    <div v-else-if="tasks.length === 0" class="bg-surface-800 rounded-xl border border-surface-700/50 py-16 text-center">
      <div class="mb-3 flex justify-center"><AppIcon name="check-circle" :size="40" class="text-surface-600" /></div>
      <h3 class="text-surface-300 font-medium">
        {{ scope === 'mine' ? 'No tasks assigned to you' : 'No tasks found' }}
      </h3>
      <p class="text-surface-500 text-sm mt-1">
        {{ scope === 'mine' ? 'Switch to All Tasks to see your team\'s work.' : 'Adjust the filters or create a task.' }}
      </p>
      <button v-if="scope === 'mine'" @click="scope = 'all'; fetchTasks()" class="btn btn-ghost mt-4">
        Show all tasks
      </button>
    </div>

    <div v-else class="bg-surface-800 rounded-xl border border-surface-700/50 overflow-hidden">
      <table class="w-full">
        <thead class="border-b border-surface-700/50">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-surface-400">Task</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-surface-400">Due</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-surface-400">Priority</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-surface-400">Status</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-surface-400">Assigned To</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in tasks" :key="task.id" class="border-b border-surface-700/30 last:border-0 hover:bg-surface-700/20">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-surface-200 truncate">{{ task.title }}</span>
                <span v-if="task.is_recurring" class="inline-flex" title="Recurring annually"><AppIcon name="refresh" :size="14" class="text-primary-400" /></span>
                <span v-if="task.show_in_wheel" class="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-primary-500/15 text-primary-400 border border-primary-500/30" title="Shown on the Year Wheel"><AppIcon name="circle-dashed" :size="11" />Wheel</span>
              </div>
              <div v-if="task.category_name" class="flex items-center gap-1.5 mt-1">
                <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: task.category_color }"></span>
                <span class="text-xs text-surface-500">{{ task.category_name }}</span>
              </div>
            </td>
            <td class="px-4 py-3 text-xs whitespace-nowrap" :class="isOverdue(task) ? 'text-red-400 font-medium' : 'text-surface-400'">
              <span v-if="task.start_date || task.end_date">
                {{ formatDate(task.start_date) }}<span v-if="task.end_date"> – {{ formatDate(task.end_date) }}</span>
              </span>
              <span v-else class="text-surface-600">No due date</span>
            </td>
            <td class="px-4 py-3">
              <span class="badge" :class="'badge-' + priorityClass(task.priority)">{{ formatLabel(task.priority) }}</span>
            </td>
            <td class="px-4 py-3">
              <select :value="task.status" @change="updateTask(task, 'status', $event.target.value)" class="input text-xs py-1">
                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
              </select>
            </td>
            <td class="px-4 py-3">
              <select :value="task.assigned_to || ''" @change="updateTask(task, 'assigned_to', $event.target.value)"
                class="input text-xs py-1">
                <option value="">Unassigned</option>
                <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
              </select>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <button @click="openForm(task)" class="text-xs text-surface-400 hover:text-surface-200">Edit</button>
              <button @click="removeTask(task)" class="text-xs text-surface-500 hover:text-red-400 ml-3">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <YearWheelTaskForm v-if="showForm" :task="editingTask" :categories="categories"
      :customer-id="selectedCustomerId" :default-show-in-wheel="false"
      @close="closeForm" @saved="onSaved" />
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import YearWheelTaskForm from '../yearwheel/YearWheelTaskForm.vue'
import AppIcon from '../../components/common/AppIcon.vue'

const auth = useAuthStore()
const tasks = ref([])
const categories = ref([])
const users = ref([])
const customers = ref([])
const loading = ref(true)
const scope = ref('mine')
const wheelFilter = ref('')
const filters = ref({ status: '', category_id: '', assigned_to: '' })
const selectedCustomerId = ref('')
const showForm = ref(false)
const editingTask = ref(null)

const scopes = [
  { key: 'mine', label: 'My Tasks' },
  { key: 'all', label: 'All Tasks' },
]

const statuses = [
  { value: 'planned', label: 'Planned' },
  { value: 'in_progress', label: 'In Progress' },
  { value: 'completed', label: 'Completed' },
  { value: 'cancelled', label: 'Cancelled' },
]

// Counted over the loaded rows, not the whole store, so the numbers always
// describe exactly what is on screen.
const counts = computed(() => {
  const c = { planned: 0, in_progress: 0, overdue: 0, wheel: 0 }
  for (const t of tasks.value) {
    if (t.status === 'planned') c.planned++
    if (t.status === 'in_progress') c.in_progress++
    if (isOverdue(t)) c.overdue++
    if (t.show_in_wheel) c.wheel++
  }
  return c
})

function isOverdue(task) {
  if (task.status === 'completed' || task.status === 'cancelled') return false
  const due = task.end_date || task.start_date
  if (!due) return false
  return new Date(due) < new Date(new Date().toDateString())
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: '2-digit' })
}

function priorityClass(p) {
  return p === 'high' ? 'danger' : p === 'medium' ? 'warning' : 'info'
}

async function fetchTasks() {
  loading.value = true
  try {
    const params = {}
    if (scope.value === 'mine') params.assigned_to = auth.user?.id
    else if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.category_id) params.category_id = filters.value.category_id
    if (wheelFilter.value !== '') params.wheel_only = wheelFilter.value
    if (selectedCustomerId.value) params.customer_id = selectedCustomerId.value

    const res = await client.get('/year-wheel/tasks', { params })
    tasks.value = res.data || []
  } catch (e) {
    console.error('Failed to load tasks', e)
  } finally {
    loading.value = false
  }
}

async function updateTask(task, field, value) {
  const payload = { [field]: field === 'assigned_to' ? (value || null) : value }
  try {
    const res = await client.put(`/year-wheel/tasks/${task.id}`, payload)
    const idx = tasks.value.findIndex(t => t.id === task.id)
    if (idx !== -1) tasks.value[idx] = res.data
  } catch (e) {
    console.error('Failed to update task', e)
    fetchTasks()
  }
}

function openForm(task) {
  editingTask.value = task || null
  showForm.value = true
}

function closeForm() {
  showForm.value = false
  editingTask.value = null
}

function onSaved() {
  closeForm()
  fetchTasks()
}

function removeTask(task) {
  if (!confirm(`Delete "${task.title}"?`)) return
  client.delete(`/year-wheel/tasks/${task.id}`).then(() => fetchTasks())
}

onMounted(async () => {
  const requests = [
    client.get('/year-wheel/categories').then(r => { categories.value = r.data || [] }).catch(() => {}),
    client.get('/users', { params: { limit: 500 } }).then(r => { users.value = r.data || [] }).catch(() => {}),
  ]
  if (auth.isAdmin || auth.isCustomerAdmin) {
    requests.push(client.get('/customers').then(r => { customers.value = r.data || [] }).catch(() => {}))
  }
  await Promise.all(requests)
  fetchTasks()
})
</script>
