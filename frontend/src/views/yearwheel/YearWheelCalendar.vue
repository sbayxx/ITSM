<template>
  <div class="bg-surface-800 rounded-xl border border-surface-700/50 overflow-hidden">
    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-px bg-surface-700/50">
      <div v-for="m in 12" :key="m" class="bg-surface-800 p-3 min-h-[200px]">
        <div class="text-sm font-semibold text-surface-300 mb-2 pb-2 border-b border-surface-700/50">
          {{ monthNames[m - 1] }}
        </div>
        <div v-if="monthTasks(m).length === 0" class="text-xs text-surface-600 text-center py-4">No tasks</div>
        <div v-for="task in monthTasks(m)" :key="task.id"
          class="group relative mb-1.5 p-2 rounded-lg cursor-pointer transition-all hover:scale-[1.02]"
          :style="{ backgroundColor: hexToRgba(task.category_color || '#3B82F6', 0.15), borderLeft: '3px solid ' + (task.category_color || '#3B82F6') }"
          @click="$emit('edit', task)">
          <div class="text-xs font-medium text-surface-200 truncate">{{ task.title }}</div>
          <div class="text-[10px] text-surface-500 mt-0.5">
            {{ formatDate(task.start_date) }}
            <span v-if="task.end_date">– {{ formatDate(task.end_date) }}</span>
          </div>
          <div v-if="task.assigned_to_name" class="text-[10px] text-surface-500 mt-0.5">{{ task.assigned_to_name }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  tasks: Array,
  categories: Array,
  year: Number,
})

defineEmits(['edit'])

const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

function monthTasks(month) {
  const zeroIdx = month - 1
  return props.tasks.filter(t => {
    const start = new Date(t.start_date)
    const end = t.end_date ? new Date(t.end_date) : start
    return start.getMonth() <= zeroIdx && end.getMonth() >= zeroIdx
  })
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })
}

function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r},${g},${b},${alpha})`
}
</script>
