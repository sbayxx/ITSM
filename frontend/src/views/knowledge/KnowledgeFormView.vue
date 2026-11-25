<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import client from '../../api/client'

const route = useRoute()
const router = useRouter()
const saving = ref(false)
const error = ref('')
const isEdit = ref(false)

const form = ref({
  title: '',
  body: '',
  category: '',
  tags: '',
  status: 'draft',
  is_internal: false
})

onMounted(async () => {
  if (route.params.id) {
    isEdit.value = true
    try {
      const res = await client.get(`/knowledge-base/${route.params.id}`)
      form.value = {
        title: res.data.title,
        body: res.data.body,
        category: res.data.category || '',
        tags: Array.isArray(res.data.tags) ? res.data.tags.join(', ') : (res.data.tags || ''),
        status: res.data.status,
        is_internal: res.data.is_internal ?? false
      }
    } catch (e) {
      error.value = 'Failed to load article.'
    }
  }
})

async function submit() {
  saving.value = true
  error.value = ''
  const payload = {
    ...form.value,
    tags: form.value.tags.split(',').map(t => t.trim()).filter(Boolean)
  }
  try {
    if (isEdit.value) {
      await client.put(`/knowledge-base/${route.params.id}`, payload)
    } else {
      await client.post('/knowledge-base', payload)
    }
    router.push('/knowledge')
  } catch (e) {
    error.value = e.response?.data?.detail || 'Failed to save article.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="p-4 md:p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-6">{{ isEdit ? 'Edit Article' : 'New Article' }}</h1>

    <div v-if="error" class="card bg-red-50 text-red-700 p-4 mb-4">{{ error }}</div>

    <form @submit.prevent="submit" class="card p-6 space-y-4">
      <div>
        <label class="block text-sm font-medium mb-1">Title</label>
        <input v-model="form.title" class="input w-full" required />
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Body</label>
        <textarea v-model="form.body" class="input w-full" rows="12" required></textarea>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Category</label>
          <input v-model="form.category" class="input w-full" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="form.status" class="input w-full">
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tags (comma-separated)</label>
        <input v-model="form.tags" class="input w-full" placeholder="tag1, tag2, tag3" />
      </div>
      <div class="flex items-center gap-2">
        <input v-model="form.is_internal" type="checkbox" id="is_internal" class="h-4 w-4" />
        <label for="is_internal" class="text-sm font-medium">Internal (visible to staff only)</label>
      </div>
      <div class="flex gap-3 pt-4">
        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Saving...' : isEdit ? 'Update Article' : 'Create Article' }}</button>
        <button type="button" class="btn-secondary" @click="router.back()">Cancel</button>
      </div>
    </form>
  </div>
</template>
