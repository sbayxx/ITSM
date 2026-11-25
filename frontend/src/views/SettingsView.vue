<template>
  <div class="max-w-2xl">
    <div class="flex gap-1 mb-6 border-b border-gray-200">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors"
        :class="activeTab === tab.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
      >
        {{ tab.label }}
      </button>
    </div>

    <div v-if="message" class="mb-4 p-3 rounded-lg text-sm" :class="messageType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'">
      {{ message }}
    </div>

    <form v-if="activeTab === 'profile'" @submit.prevent="saveProfile" class="card space-y-4">
      <div>
        <label class="label" for="name">Name</label>
        <input id="name" v-model="profile.name" type="text" class="input" required />
      </div>
      <div>
        <label class="label" for="phone">Phone</label>
        <input id="phone" v-model="profile.phone" type="text" class="input" />
      </div>
      <div>
        <label class="label" for="job_title">Job Title</label>
        <input id="job_title" v-model="profile.job_title" type="text" class="input" />
      </div>
      <div>
        <label class="label" for="language">Language</label>
        <select id="language" v-model="profile.language" class="select">
          <option value="en">English</option>
          <option value="de">German</option>
          <option value="fr">French</option>
          <option value="es">Spanish</option>
        </select>
      </div>
      <div class="pt-2">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : 'Save Profile' }}
        </button>
      </div>
    </form>

    <form v-else @submit.prevent="savePassword" class="card space-y-4">
      <div>
        <label class="label" for="current_password">Current Password</label>
        <input id="current_password" v-model="password.current" type="password" class="input" required autocomplete="current-password" />
      </div>
      <div>
        <label class="label" for="new_password">New Password</label>
        <input id="new_password" v-model="password.new" type="password" class="input" required autocomplete="new-password" minlength="8" />
      </div>
      <div>
        <label class="label" for="confirm_password">Confirm New Password</label>
        <input id="confirm_password" v-model="password.confirm" type="password" class="input" required autocomplete="new-password" />
      </div>
      <div class="pt-2">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Updating...' : 'Update Password' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()

const activeTab = ref('profile')
const saving = ref(false)
const message = ref('')
const messageType = ref('success')

const tabs = [
  { key: 'profile', label: 'Profile' },
  { key: 'password', label: 'Password' },
]

const profile = reactive({
  name: auth.user?.name || '',
  phone: auth.user?.phone || '',
  job_title: auth.user?.job_title || '',
  language: auth.user?.language || 'en',
})

const password = reactive({
  current: '',
  new: '',
  confirm: '',
})

function setMessage(text, type = 'success') {
  message.value = text
  messageType.value = type
}

async function saveProfile() {
  saving.value = true
  message.value = ''
  try {
    await auth.updateProfile({
      name: profile.name,
      phone: profile.phone,
      job_title: profile.job_title,
      language: profile.language,
    })
    setMessage('Profile updated successfully.')
  } catch (err) {
    setMessage(err?.message || 'Failed to update profile.', 'error')
  } finally {
    saving.value = false
  }
}

async function savePassword() {
  saving.value = true
  message.value = ''

  if (password.new !== password.confirm) {
    setMessage('New passwords do not match.', 'error')
    saving.value = false
    return
  }

  try {
    await auth.updatePassword({
      current_password: password.current,
      new_password: password.new,
      new_password_confirmation: password.confirm,
    })
    setMessage('Password updated successfully.')
    password.current = ''
    password.new = ''
    password.confirm = ''
  } catch (err) {
    setMessage(err?.message || 'Failed to update password.', 'error')
  } finally {
    saving.value = false
  }
}
</script>
