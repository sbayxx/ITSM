<script setup>
import { formatLabel } from '../../utils/format'
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { marked } from 'marked'
import DOMPurify from 'dompurify'
import client from '../../api/client'

const route = useRoute()
const router = useRouter()
const article = ref(null)
const loading = ref(true)
const feedbackMsg = ref('')

const user = ref({ role: '' })

const renderedBody = computed(() => {
  if (!article.value) return ''
  const html = marked(article.value.body, { breaks: true })
  return DOMPurify.sanitize(html)
})

async function fetchArticle() {
  loading.value = true
  try {
    const [artRes, userRes] = await Promise.all([
      client.get(`/knowledge-base/${route.params.id}`),
      client.get('/auth/me').catch(() => ({ data: { role: '' } }))
    ])
    article.value = artRes.data
    user.value = userRes.data
  } finally {
    loading.value = false
  }
}

async function sendFeedback(helpful) {
  feedbackMsg.value = ''
  try {
    await client.post(`/knowledge-base/${route.params.id}/feedback`, { helpful })
    feedbackMsg.value = 'Thanks for your feedback!'
    if (helpful) {
      article.value.helpful_count = (article.value.helpful_count || 0) + 1
    } else {
      article.value.not_helpful_count = (article.value.not_helpful_count || 0) + 1
    }
  } catch (e) {
    feedbackMsg.value = 'Failed to submit feedback.'
  }
}

async function deleteArticle() {
  if (!confirm('Delete this article?')) return
  try {
    await client.delete(`/knowledge-base/${route.params.id}`)
    router.push('/knowledge')
  } catch (e) {
    feedbackMsg.value = 'Failed to delete article.'
  }
}

const canEdit = () => user.value.role === 'admin' || user.value.role === 'author'

onMounted(fetchArticle)
</script>

<template>
  <div class="p-4 md:p-6 max-w-4xl">
    <div v-if="loading" class="text-center text-gray-400 py-12">Loading...</div>
    <template v-else-if="article">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold">{{ article.title }}</h1>
          <div class="flex gap-3 mt-1 text-sm text-gray-500">
            <span>{{ formatLabel(article.category) }}</span>
            <span :class="'badge-' + (article.status === 'published' ? 'success' : article.status === 'draft' ? 'default' : 'warning')">{{ formatLabel(article.status) }}</span>
            <span>{{ article.view_count ?? 0 }} views</span>
          </div>
        </div>
        <div class="flex flex-wrap gap-2">
          <button v-if="canEdit()" class="btn-secondary" @click="router.push(`/knowledge/${article.id}/edit`)">Edit</button>
          <button v-if="canEdit()" class="btn-secondary text-red-600" @click="deleteArticle">Delete</button>
          <button class="btn-secondary" @click="router.push('/knowledge')">Back</button>
        </div>
      </div>

      <div class="card p-4 md:p-6 mb-6 article-body" v-html="renderedBody"></div>

      <div class="card p-4 flex items-center gap-3">
        <span class="text-sm text-gray-600">Was this helpful?</span>
        <button class="btn-primary text-sm px-4" @click="sendFeedback(true)">Yes (+{{ article.helpful_count ?? 0 }})</button>
        <button class="btn-secondary text-sm px-4" @click="sendFeedback(false)">No (+{{ article.not_helpful_count ?? 0 }})</button>
        <span v-if="feedbackMsg" class="text-sm text-green-600 ml-2">{{ feedbackMsg }}</span>
      </div>

      <div v-if="article.tags?.length" class="flex gap-2 mt-4">
        <span v-for="tag in article.tags" :key="tag" class="badge-info text-sm">{{ tag }}</span>
      </div>
    </template>
  </div>
</template>

<style scoped>
.article-body img {
  max-width: 220px;
  height: auto;
  border-radius: 8px;
  margin-bottom: 1rem;
  box-shadow: 0 1px 6px rgba(0,0,0,0.1);
}
.article-body h1 { font-size: 1.5rem; font-weight: 700; margin: 2rem 0 1rem; }
.article-body h2 { font-size: 1.25rem; font-weight: 600; margin: 1.75rem 0 0.75rem; }
.article-body h3 { font-size: 1.1rem; font-weight: 600; margin: 1.5rem 0 0.5rem; }
.article-body p { margin: 1rem 0; line-height: 1.8; }
.article-body ul, .article-body ol { margin: 1rem 0; padding-left: 1.5rem; }
.article-body li { margin: 0.5rem 0; line-height: 1.8; }
.article-body strong { font-weight: 600; }
.article-body hr { margin: 2rem 0; border: none; border-top: 1px solid #e2e8f0; }
.article-body table { border-collapse: collapse; width: 100%; margin: 1.5rem 0; }
.article-body th, .article-body td { border: 1px solid #e2e8f0; padding: 0.5rem 0.75rem; text-align: left; }
.article-body th { background: #f8fafc; font-weight: 600; }
.article-body code { background: #f1f5f9; padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.875rem; }
.article-body pre { background: #f8fafc; border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; overflow-x: auto; margin: 1.5rem 0; }
.article-body blockquote { border-left: 3px solid #cbd5e1; padding-left: 1rem; color: #64748b; margin: 1.5rem 0; line-height: 1.7; }
</style>
