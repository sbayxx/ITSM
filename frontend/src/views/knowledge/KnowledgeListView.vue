<script setup>
import { formatLabel } from '../../utils/format'
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import client from '../../api/client'

const router = useRouter()
const articles = ref([])
const loading = ref(true)
const search = ref('')
const filterCategory = ref('')

const categories = computed(() => [...new Set(articles.value.map(a => a.category))])

const filtered = computed(() =>
  articles.value.filter(a => {
    const matchSearch = !search.value ||
      a.title.toLowerCase().includes(search.value.toLowerCase()) ||
      a.body?.toLowerCase().includes(search.value.toLowerCase())
    const matchCategory = !filterCategory.value || a.category === filterCategory.value
    return matchSearch && matchCategory
  })
)

async function fetchData() {
  loading.value = true
  try {
    const res = await client.get('/knowledge-base')
    articles.value = res.data
  } finally {
    loading.value = false
  }
}

function newArticle() {
  router.push('/knowledge/new')
}

function viewDetail(id) {
  router.push(`/knowledge/${id}`)
}

onMounted(fetchData)
</script>

<template>
  <div class="p-4 md:p-6">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Knowledge Base</h1>
      <button class="btn-primary" @click="newArticle">New Article</button>
    </div>

    <div class="flex flex-col sm:flex-row gap-4 mb-4">
      <input v-model="search" class="input w-full sm:w-80" placeholder="Search articles..." />
      <select v-model="filterCategory" class="input w-full sm:w-48">
        <option value="">All Categories</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <div class="card overflow-x-auto">
      <table class="w-full text-left">
        <thead>
          <tr class="border-b bg-gray-50">
            <th class="p-3 font-medium">Title</th>
            <th class="p-3 font-medium">Category</th>
            <th class="p-3 font-medium">Status</th>
            <th class="p-3 font-medium">Views</th>
            <th class="p-3 font-medium">Helpful %</th>
            <th class="p-3 font-medium">Author</th>
            <th class="p-3 font-medium">Published</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7" class="p-6 text-center text-gray-400">Loading...</td></tr>
          <tr v-else-if="!filtered.length"><td colspan="7" class="p-6 text-center text-gray-400">No articles found.</td></tr>
          <tr v-for="a in filtered" :key="a.id" class="border-b hover:bg-gray-50 cursor-pointer" @click="viewDetail(a.id)">
            <td class="p-3 font-medium">{{ a.title }}</td>
            <td class="p-3"><span class="badge-info">{{ formatLabel(a.category) }}</span></td>
            <td class="p-3"><span :class="'badge-' + (a.status === 'published' ? 'success' : a.status === 'draft' ? 'default' : 'warning')">{{ formatLabel(a.status) }}</span></td>
            <td class="p-3">{{ a.view_count ?? 0 }}</td>
            <td class="p-3">
              <template v-if="(a.helpful_count ?? 0) + (a.not_helpful_count ?? 0) > 0">
                {{ Math.round((a.helpful_count / (a.helpful_count + a.not_helpful_count)) * 100) }}%
              </template>
              <span v-else>-</span>
            </td>
            <td class="p-3">{{ a.author || '-' }}</td>
            <td class="p-3">{{ a.published_at ? new Date(a.published_at).toLocaleDateString() : '-' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
