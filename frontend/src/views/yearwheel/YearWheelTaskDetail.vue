<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="$emit('close')">
    <div class="bg-surface-800 rounded-xl border border-surface-700/50 w-full max-w-lg max-h-[90vh] overflow-y-auto">
      <div class="flex items-center justify-between px-6 py-4 border-b border-surface-700/50">
        <h2 class="text-lg font-semibold text-surface-100">{{ task.title }}</h2>
        <button @click="$emit('close')" class="text-surface-500 hover:text-surface-300"><AppIcon name="x" :size="18" /></button>
      </div>
      <div class="p-6 space-y-4">
        <div class="flex items-center gap-3">
          <span class="badge" :class="'badge-' + statusClass(task.status)">{{ formatLabel(task.status) }}</span>
          <span class="badge" :class="'badge-' + priorityClass(task.priority)">{{ formatLabel(task.priority) }}</span>
          <span v-if="task.is_recurring" class="inline-flex items-center gap-1 text-xs text-primary-400"><AppIcon name="refresh" :size="13" />Recurring</span>
        </div>

        <div v-if="task.description" class="text-sm text-surface-300 bg-surface-700/30 rounded-lg p-3">
          {{ task.description }}
        </div>

        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-surface-500 block text-xs">Category</span>
            <span class="text-surface-200">{{ task.category_name || 'Uncategorized' }}</span>
          </div>
          <div>
            <span class="text-surface-500 block text-xs">Assigned To</span>
            <span class="text-surface-200">{{ task.assigned_to_name || 'Unassigned' }}</span>
          </div>
          <div>
            <span class="text-surface-500 block text-xs">Start Date</span>
            <span class="text-surface-200">{{ formatDate(task.start_date) }}</span>
          </div>
          <div>
            <span class="text-surface-500 block text-xs">End Date</span>
            <span class="text-surface-200">{{ task.end_date ? formatDate(task.end_date) : 'Single day' }}</span>
          </div>
          <div>
            <span class="text-surface-500 block text-xs">Created By</span>
            <span class="text-surface-200">{{ task.created_by_name || '-' }}</span>
          </div>
          <div>
            <span class="text-surface-500 block text-xs">Completed At</span>
            <span class="text-surface-200">{{ task.completed_at ? formatDate(task.completed_at) : '-' }}</span>
          </div>
        </div>

        <div v-if="task.subtasks && task.subtasks.length > 0" class="pt-2">
          <h4 class="text-sm font-medium text-surface-300 mb-2">Subtasks ({{ task.subtasks.length }})</h4>
          <div v-for="st in task.subtasks" :key="st.id"
            class="flex items-center gap-2 px-3 py-2 bg-surface-700/30 rounded-lg mb-1 cursor-pointer hover:bg-surface-700/50"
            @click="$emit('edit', st)">
            <span class="text-sm text-surface-200">{{ st.title }}</span>
            <span class="badge ml-auto text-xs" :class="'badge-' + statusClass(st.status)">{{ formatLabel(st.status) }}</span>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2 border-t border-surface-700/50">
          <button @click="$emit('delete', task)" class="btn btn-ghost text-red-400">Delete</button>
          <button @click="$emit('edit', task)" class="btn btn-primary">Edit</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import AppIcon from '../../components/common/AppIcon.vue'

defineProps({
  task: Object,
})

defineEmits(['close', 'edit', 'delete'])

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
}

function statusClass(s) {
  return s === 'completed' ? 'success' : s === 'in_progress' ? 'warning' : s === 'cancelled' ? 'danger' : 'info'
}

function priorityClass(p) {
  return p === 'high' ? 'danger' : p === 'medium' ? 'warning' : 'info'
}
</script>
