<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
    <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700/50">
        <h2 class="text-lg font-semibold text-surface-100">{{ task ? 'Edit Task' : 'New Task' }}</h2>
        <button @click="$emit('close')" class="text-surface-500 hover:text-surface-300"><AppIcon name="x" :size="18" /></button>
      </div>
      <form @submit.prevent="submit" class="p-6 space-y-4">
        <div v-if="error" class="p-3 bg-red-500/10 border border-red-500/30 rounded-lg text-sm text-red-400">
          {{ error }}
        </div>
        <div>
          <label class="label">Title</label>
          <input v-model="form.title" class="input" required placeholder="e.g. ISO 27001 Internal Audit" />
        </div>
        <div>
          <label class="label">Description</label>
          <textarea v-model="form.description" class="input" rows="3" placeholder="Optional description"></textarea>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Category</label>
            <select v-model="form.category_id" class="input">
              <option :value="null">No category</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>
          <div>
            <label class="label">Assigned To</label>
            <select v-model="form.assigned_to" class="input">
              <option :value="null">Unassigned</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Status</label>
            <select v-model="form.status" class="input">
              <option value="planned">Planned</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div>
            <label class="label">Priority</label>
            <select v-model="form.priority" class="input">
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Start Date</label>
            <input v-model="form.start_date" type="date" class="input" :required="form.show_in_wheel" />
          </div>
          <div>
            <label class="label">End Date</label>
            <input v-model="form.end_date" type="date" class="input" />
          </div>
        </div>
        <div class="flex items-center gap-3">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.show_in_wheel" class="sr-only peer" />
            <div class="w-9 h-5 bg-surface-700 rounded-full peer peer-checked:bg-primary-500 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
          </label>
          <span class="text-sm text-surface-400">Show in Year Wheel</span>
        </div>
        <div v-if="form.show_in_wheel" class="flex items-center gap-3">
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="form.is_recurring" class="sr-only peer" />
            <div class="w-9 h-5 bg-surface-700 rounded-full peer peer-checked:bg-primary-500 peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all"></div>
          </label>
          <span class="text-sm text-surface-400">Recurring annually</span>
        </div>
        <div v-if="!task && form.show_in_wheel" class="flex items-center gap-2 p-3 bg-surface-700/50 rounded-lg text-sm text-surface-400">
          <AppIcon name="info" :size="16" /> Task will be created for {{ form.start_date ? new Date(form.start_date).getFullYear() : 'current' }} year. Use rollover to create next year's instances.
        </div>
        <div v-else-if="!task && !form.show_in_wheel" class="flex items-center gap-2 p-3 bg-surface-700/50 rounded-lg text-sm text-surface-400">
          <AppIcon name="info" :size="16" /> This task will only appear in Tasks, not on the Year Wheel.
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="$emit('close')" class="btn btn-ghost">Cancel</button>
          <button type="submit" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Saving...' : (task ? 'Update' : 'Create') }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import client from '../../api/client'
import AppIcon from '../../components/common/AppIcon.vue'

const props = defineProps({
  task: Object,
  categories: Array,
  customerId: [String, Number],
  // The Tasks page creates work items that stay off the wheel unless the user
  // explicitly opts in, so the default is inverted there.
  defaultShowInWheel: { type: Boolean, default: true },
})

const emit = defineEmits(['close', 'saved'])

const saving = ref(false)
const error = ref('')
const users = ref([])

const form = ref({
  title: '',
  description: '',
  category_id: null,
  assigned_to: null,
  status: 'planned',
  priority: 'medium',
  start_date: '',
  end_date: '',
  is_recurring: false,
  show_in_wheel: true,
})

onMounted(async () => {
  if (props.task) {
    form.value = { ...props.task }
    if (!props.task.start_date) form.value.start_date = ''
    else form.value.start_date = props.task.start_date.split(' ')[0] || props.task.start_date
    if (!props.task.end_date) form.value.end_date = ''
    else form.value.end_date = props.task.end_date.split(' ')[0] || props.task.end_date
    form.value.is_recurring = !!props.task.is_recurring
    form.value.show_in_wheel = props.task.show_in_wheel === undefined ? true : !!props.task.show_in_wheel
    if (!form.value.assigned_to) form.value.assigned_to = null
  } else {
    form.value.start_date = new Date().toISOString().split('T')[0]
    form.value.show_in_wheel = props.defaultShowInWheel
  }
  try {
    const res = await client.get('/users', { params: { limit: 500 } })
    users.value = res.data || []
  } catch (e) {
    console.error('Failed to load users', e)
  }
})

async function submit() {
  error.value = ''
  if (!form.value.title.trim()) {
    error.value = 'Title is required'
    return
  }
  if (form.value.show_in_wheel && !form.value.start_date) {
    error.value = 'A start date is required for tasks on the Year Wheel'
    return
  }
  saving.value = true
  try {
    const payload = {
      title: form.value.title,
      description: form.value.description || null,
      category_id: form.value.category_id || null,
      assigned_to: form.value.assigned_to || null,
      status: form.value.status,
      priority: form.value.priority,
      start_date: form.value.start_date || null,
      end_date: form.value.end_date || null,
      is_recurring: form.value.is_recurring ? 1 : 0,
      show_in_wheel: form.value.show_in_wheel ? 1 : 0,
    }
    if (!props.task && props.customerId) {
      payload.customer_id = props.customerId
    }
    if (props.task) {
      await client.put(`/year-wheel/tasks/${props.task.id}`, payload)
    } else {
      await client.post('/year-wheel/tasks', payload)
    }
    emit('saved')
  } catch (e) {
    console.error('Failed to save task', e)
    error.value = e?.response?.data?.message || 'Failed to save task'
  } finally {
    saving.value = false
  }
}
</script>
