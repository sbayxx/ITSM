import { defineStore } from 'pinia'
import client from '../api/client'

export const useTicketStore = defineStore('tickets', {
  state: () => ({
    items: [],
    meta: null,
    current: null,
    loading: false,
  }),
  actions: {
    async fetchTickets(params = {}) {
      this.loading = true
      try {
        const res = await client.get('/tickets', { params })
        this.items = res.data
        this.meta = res.meta
      } finally {
        this.loading = false
      }
    },
    async fetchTicket(id) {
      this.loading = true
      try {
        const res = await client.get(`/tickets/${id}`)
        this.current = res.data
        return res.data
      } finally {
        this.loading = false
      }
    },
    async createTicket(data) {
      const res = await client.post('/tickets', data)
      return res.data
    },
    async updateTicket(id, data) {
      const res = await client.put(`/tickets/${id}`, data)
      return res.data
    },
    async deleteTicket(id) {
      await client.delete(`/tickets/${id}`)
    },
    async addComment(id, body, isInternal = false) {
      const res = await client.post(`/tickets/${id}/comments`, { body, is_internal: isInternal })
      return res.data
    },
    async fetchDashboard() {
      const res = await client.get('/dashboard')
      return res.data
    },
  },
})
