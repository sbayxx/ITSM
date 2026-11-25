<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
      <div>
        <h1 class="text-2xl font-bold text-surface-100">Year Wheel</h1>
        <p class="text-surface-400 text-sm mt-1">Annual recurring tasks and compliance calendar</p>
      </div>
      <div class="flex items-center gap-3">
        <select v-if="auth.isAdmin" v-model="selectedCustomerId" @change="fetchTasks"
          class="input text-sm w-auto">
          <option value="">All Customers</option>
          <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <div class="flex items-center gap-2 bg-surface-800 rounded-lg p-1">
          <button v-for="v in views" :key="v.key" @click="currentView = v.key"
            class="px-3 py-1.5 text-xs font-medium rounded-md transition-all"
            :class="currentView === v.key ? 'bg-primary-500 text-white shadow-sm' : 'text-surface-400 hover:text-surface-200'">
            {{ v.label }}
          </button>
        </div>
        <select v-model="selectedYear" @change="fetchTasks"
          class="input text-sm w-auto">
          <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
        </select>
        <button @click="showForm = true; editingTask = null" class="btn btn-primary gap-1.5">
          <AppIcon name="plus" :size="16" />
          New Task
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-16">
      <AppIcon name="loader" :size="30" class="animate-spin text-surface-500 mb-3 inline-block" />
      <p class="text-surface-500 text-sm">Loading year wheel data...</p>
    </div>

    <div v-else-if="tasks.length === 0" class="text-center py-16">
      <div class="mb-3 flex justify-center"><AppIcon name="circle-dashed" :size="40" class="text-surface-600" /></div>
      <h3 class="text-surface-300 font-medium">No tasks yet</h3>
      <p class="text-surface-500 text-sm mt-1">Create your first annual recurring task to get started.</p>
    </div>

    <YearWheelCircular v-else-if="currentView === 'wheel'"
      :tasks="tasks" :categories="categories" :year="selectedYear" @edit="editTask" />

    <YearWheelTable v-else-if="currentView === 'table'"
      :tasks="tasks" :categories="categories" @edit="editTask" @delete="deleteTask" />

    <YearWheelList v-else-if="currentView === 'list'"
      :tasks="tasks" :categories="categories" @edit="editTask" @delete="deleteTask" />

    <YearWheelCalendar v-else-if="currentView === 'calendar'"
      :tasks="tasks" :categories="categories" :year="selectedYear" @edit="editTask" />

    <YearWheelTaskForm v-if="showForm" :task="editingTask" :categories="categories" :customer-id="selectedCustomerId"
      @close="showForm = false" @saved="onSaved" />

    <YearWheelTaskDetail v-if="selectedTask" :task="selectedTask"
      @close="selectedTask = null" @edit="editTask" @delete="deleteTask" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import YearWheelCircular from './YearWheelCircular.vue'
import YearWheelTable from './YearWheelTable.vue'
import YearWheelList from './YearWheelList.vue'
import YearWheelCalendar from './YearWheelCalendar.vue'
import YearWheelTaskForm from './YearWheelTaskForm.vue'
import YearWheelTaskDetail from './YearWheelTaskDetail.vue'
import AppIcon from '../../components/common/AppIcon.vue'

const auth = useAuthStore()
const tasks = ref([])
const categories = ref([])
const customers = ref([])
const loading = ref(true)
const selectedYear = ref(new Date().getFullYear())
const selectedCustomerId = ref('')
const currentView = ref('wheel')
const showForm = ref(false)
const editingTask = ref(null)
const selectedTask = ref(null)

const views = [
  { key: 'wheel', label: 'Wheel' },
  { key: 'table', label: 'Table' },
  { key: 'list', label: 'List' },
  { key: 'calendar', label: 'Calendar' },
]

const yearOptions = computed(() => {
  const years = []
  for (let i = -1; i <= 3; i++) years.push(selectedYear.value + i)
  return years
})

async function fetchTasks() {
  loading.value = true
  try {
    const params = { year: selectedYear.value, wheel_only: 1 }
    if (auth.isAdmin) {
      if (selectedCustomerId.value) {
        params.customer_id = selectedCustomerId.value
      }
    } else if (auth.user?.customer_id) {
      params.customer_id = auth.user.customer_id
    }

    const catParams = {}
    if (auth.isAdmin) {
      if (selectedCustomerId.value) {
        catParams.customer_id = selectedCustomerId.value
      }
    } else if (auth.user?.customer_id) {
      catParams.customer_id = auth.user.customer_id
    }

    const [taskRes, catRes] = await Promise.all([
      client.get('/year-wheel/tasks', { params }),
      client.get('/year-wheel/categories', { params: catParams }),
    ])
    tasks.value = taskRes.data || []
    categories.value = catRes.data || []
  } catch (e) {
    console.error('Failed to load year wheel data', e)
  } finally {
    loading.value = false
  }
}

function editTask(task) {
  selectedTask.value = null
  editingTask.value = task
  showForm.value = true
}

function deleteTask(task) {
  selectedTask.value = null
  if (confirm(`Delete "${task.title}"?`)) {
    client.delete(`/year-wheel/tasks/${task.id}`).then(() => fetchTasks())
  }
}

function onSaved() {
  showForm.value = false
  editingTask.value = null
  fetchTasks()
}

onMounted(async () => {
  if (auth.isAdmin || auth.isCustomerAdmin) {
    try {
      const res = await client.get('/customers')
      customers.value = res.data || []
    } catch (e) {
      console.error('Failed to load customers', e)
    }
  }
  fetchTasks()
})
</script>
