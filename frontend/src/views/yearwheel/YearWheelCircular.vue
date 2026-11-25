<template>
  <div class="flex flex-col items-center">
    <svg :width="size" :height="size" class="max-w-full">
      <g :transform="`translate(${cx}, ${cy})`">
        <!-- Category arcs -->
        <g v-for="(cat, ci) in categories" :key="cat.id">
          <template v-for="(seg, si) in getCategoryArcs(cat, ci)" :key="si">
            <path :d="seg.path" :fill="seg.color" stroke="#1e293b" stroke-width="2"
              class="transition-all duration-200 cursor-pointer hover:opacity-80"
              @click="seg.task && $emit('edit', seg.task)" />
            <text v-if="seg.task && seg.textAngle !== undefined"
              :x="seg.textX" :y="seg.textY"
              :transform="`rotate(${seg.textAngle}, ${seg.textX}, ${seg.textY})`"
              text-anchor="middle" dominant-baseline="central"
              class="text-[9px] fill-white pointer-events-none select-none"
              :style="{ textShadow: '0 1px 2px rgba(0,0,0,0.6)' }">
              {{ truncateTitle(seg.task.title) }}
            </text>
          </template>
        </g>

        <!-- Month rings -->
        <circle v-for="m in 12" :key="m"
          :r="ringRadius(m)" fill="none" stroke="#334155" stroke-width="1"
          stroke-dasharray="4,4" />

        <!-- Month labels -->
        <text v-for="m in 12" :key="'l'+m"
          :x="monthLabelX(m)" :y="monthLabelY(m)"
          class="text-xs fill-surface-400" text-anchor="middle" dominant-baseline="central">
          {{ monthNames[m - 1] }}
        </text>

        <!-- Center year -->
        <text text-anchor="middle" dominant-baseline="central"
          class="text-2xl font-bold fill-surface-100">{{ year }}</text>
      </g>
    </svg>

    <div class="flex items-center gap-4 mt-4 flex-wrap justify-center">
      <div v-for="cat in categories" :key="cat.id" class="flex items-center gap-1.5 text-xs text-surface-400">
        <span class="w-3 h-3 rounded-sm" :style="{ backgroundColor: cat.color }"></span>
        {{ cat.name }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  tasks: Array,
  categories: Array,
  year: Number,
})

defineEmits(['edit'])

const size = 600
const cx = size / 2
const cy = size / 2
const innerR = 80
const outerR = 270
const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

const segmentWidth = computed(() => (outerR - innerR) / (props.categories?.length || 1))

function ringRadius(month) {
  return innerR + (month / 12) * (outerR - innerR)
}

function monthAngle(month) {
  return ((month - 1) / 12) * Math.PI * 2 - Math.PI / 2
}

function monthLabelX(month) {
  const angle = monthAngle(month)
  return (outerR + 25) * Math.cos(angle)
}

function monthLabelY(month) {
  const angle = monthAngle(month)
  return (outerR + 25) * Math.sin(angle)
}

function getCategoryArcs(cat, catIndex) {
  const segments = []
  const sw = segmentWidth.value
  const rInner = innerR + catIndex * sw
  const rOuter = rInner + sw

  const catTasks = props.tasks.filter(t => t.category_id === cat.id)
  if (catTasks.length === 0) {
    const fullPath = describeArc(0, 0, rInner, rOuter, 0, 360)
    segments.push({ path: fullPath, color: hexToRgba(cat.color, 0.15), task: null })
    return segments
  }

  const months = new Array(12).fill(false)
  catTasks.forEach(task => {
    const start = new Date(task.start_date)
    const end = task.end_date ? new Date(task.end_date) : start
    for (let m = start.getMonth(); m <= end.getMonth(); m++) {
      months[m] = true
    }
  })

  let arcStart = 0
  let active = false
  for (let m = 0; m <= 12; m++) {
    const isActive = months[m] || false
    if (isActive && !active) {
      arcStart = m
      active = true
    } else if (!isActive && active) {
      const startAngle = (arcStart / 12) * 360
      const endAngle = (m / 12) * 360
      const midAngle = (startAngle + endAngle) / 2
      const path = describeArc(0, 0, rInner, rOuter, startAngle, endAngle - startAngle)
      const task = catTasks.find(t => {
        const tStart = new Date(t.start_date).getMonth()
        return tStart >= arcStart && tStart < m
      })
      const textPos = polarToCartesian(0, 0, (rInner + rOuter) / 2, midAngle)
      let textAngle = midAngle
      if (midAngle > 90 && midAngle < 270) textAngle += 180
      segments.push({
        path, color: cat.color, task: task || null,
        textX: textPos.x, textY: textPos.y, textAngle,
      })
      active = false
    }
  }

  return segments
}

function polarToCartesian(cx, cy, r, angleDeg) {
  const rad = ((angleDeg - 90) * Math.PI) / 180
  return { x: cx + r * Math.cos(rad), y: cy + r * Math.sin(rad) }
}

function describeArc(cx, cy, rInner, rOuter, startAngle, sweepAngle) {
  const endAngle = startAngle + sweepAngle
  const s1 = polarToCartesian(cx, cy, rInner, startAngle)
  const s2 = polarToCartesian(cx, cy, rInner, endAngle)
  const e1 = polarToCartesian(cx, cy, rOuter, startAngle)
  const e2 = polarToCartesian(cx, cy, rOuter, endAngle)
  const large = sweepAngle > 180 ? 1 : 0
  return [
    `M ${s1.x} ${s1.y}`,
    `A ${rInner} ${rInner} 0 ${large} 1 ${s2.x} ${s2.y}`,
    `L ${e2.x} ${e2.y}`,
    `A ${rOuter} ${rOuter} 0 ${large} 0 ${e1.x} ${e1.y}`,
    'Z',
  ].join(' ')
}

function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r},${g},${b},${alpha})`
}

function truncateTitle(title, max = 18) {
  if (!title) return ''
  return title.length > max ? title.slice(0, max - 1) + '\u2026' : title
}
</script>
