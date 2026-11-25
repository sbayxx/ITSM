<script setup>
import { computed } from 'vue'
import { icons, resolveIconName } from '../../utils/icons'

const props = defineProps({
  // Kebab-case name from utils/icons.js; emoji (from DB data) is also accepted
  // and resolved transparently. Unknown values fall back to a neutral circle.
  name: { type: String, default: '' },
  size: { type: [Number, String], default: 18 },
  strokeWidth: { type: [Number, String], default: 1.5 },
})

const component = computed(() => icons[resolveIconName(props.name)] || icons.circle)
</script>

<template>
  <!-- Lucide sizes via the `size` prop; width/height attrs are ignored by Icon.js -->
  <component
    :is="component"
    :size="size"
    :stroke-width="strokeWidth"
    class="shrink-0"
    aria-hidden="true"
  />
</template>
