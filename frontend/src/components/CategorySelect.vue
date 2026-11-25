<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
      <label class="label" :for="uid + '-cat'">Category</label>
      <select :id="uid + '-cat'" :value="category || ''" class="select w-full" @change="onCategory($event.target.value)">
        <option value="">Select category…</option>
        <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
      </select>
    </div>
    <div>
      <label class="label" :for="uid + '-sub'">Subcategory</label>
      <select :id="uid + '-sub'" :value="subcategory || ''" class="select w-full" :disabled="!category"
        @change="$emit('update:subcategory', $event.target.value)">
        <option value="">{{ category ? 'Select subcategory…' : 'Choose a category first' }}</option>
        <option v-for="s in subOptions" :key="s.value" :value="s.value">{{ s.label }}</option>
      </select>
    </div>
    <p v-if="loadError" class="sm:col-span-2 text-xs text-red-400">{{ loadError }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import client from '../api/client'

// Two-level picklist (category -> subcategory) served by GET /api/ticket-categories.
// Values that are not in the tree (free text stored before the tree existed) are still shown,
// marked "(existing)", so opening an old ticket never silently drops its value.
const props = defineProps({
  type: { type: String, required: true },
  category: { type: String, default: '' },
  subcategory: { type: String, default: '' },
})
const emit = defineEmits(['update:category', 'update:subcategory'])

const tree = ref([])
const loadError = ref('')
const uid = 'cs' + Math.random().toString(36).slice(2, 7)

const selectedRoot = computed(() => tree.value.find(c => c.name === props.category) || null)

const categoryOptions = computed(() => {
  const opts = tree.value.map(c => ({ value: c.name, label: c.name }))
  if (props.category && !selectedRoot.value) {
    opts.unshift({ value: props.category, label: `${props.category} (existing)` })
  }
  return opts
})

const subOptions = computed(() => {
  const opts = (selectedRoot.value?.children || []).map(s => ({ value: s.name, label: s.name }))
  if (props.subcategory && !opts.some(o => o.value === props.subcategory)) {
    opts.unshift({ value: props.subcategory, label: `${props.subcategory} (existing)` })
  }
  return opts
})

function onCategory(value) {
  emit('update:category', value)
  if (value !== props.category) emit('update:subcategory', '')
}

async function load() {
  loadError.value = ''
  try {
    const res = await client.get('/ticket-categories', { params: { type: props.type } })
    tree.value = Array.isArray(res.data) ? res.data : []
  } catch (e) {
    tree.value = []
    loadError.value = e?.message || 'Could not load categories'
  }
}

onMounted(load)
watch(() => props.type, load)
</script>
