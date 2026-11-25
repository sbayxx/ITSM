import { defineStore } from 'pinia'
import client, { setCsrfToken } from '../api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    csrfToken: null,
    loading: false,
  }),
  getters: {
    isLoggedIn: (state) => !!state.user,
    isAdmin: (state) => state.user?.role === 'admin',
    isCustomerAdmin: (state) => state.user?.role === 'customer_admin' || state.user?.role === 'admin',
    isAgent: (state) => ['agent', 'admin', 'manager', 'customer_admin'].includes(state.user?.role),
    // Mirrors Ticket::INTERNAL_NOTE_ROLES on the server. Managers and customer_admins
    // work the queue but are deliberately excluded from privileged commentary.
    canSeeInternalNotes: (state) => ['agent', 'admin'].includes(state.user?.role),
    isImpersonating: (state) => !!state.user?._impersonating,
    impersonator: (state) => state.user?._impersonator || null,
  },
  actions: {
    async login(email, password) {
      this.loading = true
      try {
        const res = await client.post('/auth/login', { email, password })
        this.user = res.data.user
        this.csrfToken = res.data.csrf_token
        setCsrfToken(this.csrfToken)
        return res
      } finally {
        this.loading = false
      }
    },
    async logout() {
      await client.post('/auth/logout')
      this.user = null
      this.csrfToken = null
    },
    async fetchUser() {
      try {
        const res = await client.get('/auth/me')
        this.user = res.data
      } catch {
        this.user = null
      }
    },
    async updateProfile(data) {
      const res = await client.put('/auth/profile', data)
      this.user = res.data
      return res
    },
    async updatePassword(data) {
      return await client.put('/auth/password', data)
    },
    async fetchCsrfToken() {
      const res = await client.get('/auth/csrf-token')
      this.csrfToken = res.data.csrf_token
      setCsrfToken(this.csrfToken)
    },
    async impersonate(userId) {
      const res = await client.post(`/admin/impersonate/${userId}`)
      this.user = res.data.user
      this.csrfToken = res.data.csrf_token
      setCsrfToken(this.csrfToken)
      return res
    },
    async stopImpersonate() {
      const res = await client.post('/admin/impersonate/stop')
      this.user = res.data.user
      this.csrfToken = res.data.csrf_token
      setCsrfToken(this.csrfToken)
      return res
    },
  },
})
