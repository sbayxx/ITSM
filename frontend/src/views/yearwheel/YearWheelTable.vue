<template>
  <div class="bg-surface-800 rounded-xl border border-surface-700/50 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="border-b border-surface-700/50 text-left">
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Task</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Category</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Start</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">End</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Duration</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Assignee</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-xs font-medium text-surface-400 uppercase tracking-wider">Priority</th>
            <th class="px-4 py-3 w-20"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="task in sortedTasks" :key="task.id"
            class="border-b border-surface-700/50 hover:bg-surface-700/30 transition-colors cursor-pointer"
            @click="$emit('edit', task)">
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <AppIcon v-if="task.category_icon" :name="task.category_icon" :size="16" :style="{ color: task.category_color }" />
                <span class="text-sm font-medium text-surface-200">{{ task.title }}</span>
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs"
                :style="{ backgroundColor: task.category_color + '20', color: task.category_color }">
                {{ task.category_name }}
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-surface-400">{{ formatDate(task.start_date) }}</td>
            <td class="px-4 py-3 text-sm text-surface-400">{{ task.end_date ? formatDate(task.end_date) : '-' }}</td>
            <td class="px-4 py-3">
              <div class="w-24 h-2 bg-surface-700 rounded-full overflow-hidden">
                <div class="h-full rounded-full" :style="{ width: durationPct(task) + '%', backgroundColor: task.category_color || '#3B82F6' }"></div>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-surface-400">{{ task.assigned_to_name || '-' }}</td>
            <td class="px-4 py-3">
              <span class="badge" :class="'badge-' + statusClass(task.status)">{{ formatLabel(task.status) }}</span>
            </td>
            <td class="px-4 py-3">
              <span class="badge" :class="'badge-' + priorityClass(task.priority)">{{ formatLabel(task.priority) }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click.stop="$emit('edit', task)" class="btn-ghost text-xs">Edit</button>
              <button @click.stop="$emit('delete', task)" class="btn-ghost text-xs text-red-400 ml-1">Del</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { formatLabel } from '../../utils/format'
import { computed } from 'vue'
import AppIcon from '../../components/common/AppIcon.vue'

const props = defineProps({
  tasks: Array,
  categories: Array,
})

defineEmits(['edit', 'delete'])

const sortedTasks = computed(() => {
  return [...props.tasks].sort((a, b) => {
    const monthA = new Date(a.start_date).getMonth()
    const monthB = new Date(b.start_date).getMonth()
    return monthA - monthB
  })
})

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function durationPct(task) {
  const start = new Date(task.start_date)
  const end = task.end_date ? new Date(task.end_date) : start
  const days = (end - start) / (1000 * 60 * 60 * 24) + 1
  return Math.min(Math.max(days / 90 * 100, 5), 100)
}

function statusClass(s) {
  return s === 'completed' ? 'success' : s === 'in_progress' ? 'warning' : s === 'cancelled' ? 'danger' : 'info'
}

function priorityClass(p) {
  return p === 'high' ? 'danger' : p === 'medium' ? 'warning' : 'info'
}
</script>
