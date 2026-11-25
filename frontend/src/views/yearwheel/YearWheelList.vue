<template>
  <div class="space-y-3">
    <div v-for="cat in categories" :key="cat.id" class="bg-surface-800 rounded-xl border border-surface-700/50 overflow-hidden">
      <div class="flex items-center gap-3 px-4 py-3 border-b border-surface-700/50"
        :style="{ borderLeftColor: cat.color, borderLeftWidth: '3px' }">
        <AppIcon v-if="cat.icon" :name="cat.icon" :size="18" :style="{ color: cat.color }" />
        <h3 class="font-semibold text-surface-200">{{ cat.name }}</h3>
        <span class="text-xs text-surface-500 ml-auto">{{ categoryTasks(cat.id).length }} tasks</span>
      </div>
      <div v-if="categoryTasks(cat.id).length === 0" class="px-4 py-6 text-center text-sm text-surface-500">
        No tasks in this category
      </div>
      <div v-for="task in categoryTasks(cat.id)" :key="task.id"
        class="flex items-center gap-4 px-4 py-3 border-b border-surface-700/30 last:border-0 hover:bg-surface-700/30 transition-colors cursor-pointer"
        @click="$emit('edit', task)">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-surface-200 truncate">{{ task.title }}</span>
            <AppIcon v-if="task.is_recurring" name="refresh" :size="14" class="text-primary-400" />
          </div>
          <p v-if="task.description" class="text-xs text-surface-500 truncate mt-0.5">{{ task.description }}</p>
        </div>
        <div class="text-xs text-surface-400 whitespace-nowrap">
          {{ formatDate(task.start_date) }}
          <span v-if="task.end_date"> – {{ formatDate(task.end_date) }}</span>
        </div>
        <span v-if="task.assigned_to_name" class="text-xs text-surface-500">{{ task.assigned_to_name }}</span>
        <span class="badge" :class="'badge-' + statusClass(task.status)">{{ formatLabel(task.status) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
const props = defineProps({
  tasks: Array,
  categories: Array,
})

defineEmits(['edit', 'delete'])
import AppIcon from '../../components/common/AppIcon.vue'

function categoryTasks(catId) {
  return props.tasks.filter(t => t.category_id === catId)
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function statusClass(s) {
  return s === 'completed' ? 'success' : s === 'in_progress' ? 'warning' : s === 'cancelled' ? 'danger' : 'info'
}
</script>
