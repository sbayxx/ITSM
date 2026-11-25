<template>
  <aside :class="[
    'flex flex-col bg-surface-900 border-r border-surface-700/50 shrink-0 transition-transform duration-300 z-40',
    'fixed md:static inset-y-0 left-0 w-64',
    open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
  ]">
    <div class="h-16 flex items-center gap-3 px-5 border-b border-surface-700/50">
      <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-primary-500 to-cyan-500 flex items-center justify-center">
        <span class="text-white font-bold text-sm">OC</span>
      </div>
      <div>
        <h1 class="text-sm font-semibold text-surface-100">OC ITSM</h1>
        <p class="text-[10px] text-surface-300 font-mono">v1.0.0</p>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
      <div class="text-[10px] font-mono text-surface-400 uppercase tracking-widest px-3 pb-2" v-if="mainItems.length">Main</div>
      <router-link v-for="item in mainItems" :key="item.path" :to="item.path"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group relative"
        :class="$route.path === item.path ? 'text-primary-400 bg-primary-500/10' : 'text-surface-200 hover:text-surface-100 hover:bg-surface-800/50'"
        @click="$emit('close')">
        <span class="w-5 flex justify-center" :class="$route.path === item.path ? '' : 'text-surface-400 group-hover:text-surface-200'"><AppIcon :name="item.icon" :size="18" /></span>
        {{ item.label }}
        <span v-if="item.badge" class="ml-auto badge badge-critical text-[10px] px-1.5 py-0.5">{{ item.badge }}</span>
        <div v-if="$route.path === item.path" class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 rounded-full bg-primary-500 shadow-glow-sm"></div>
      </router-link>

      <div class="text-[10px] font-mono text-surface-400 uppercase tracking-widest px-3 pt-4 pb-2" v-if="adminItems.length">Administration</div>
      <template v-for="item in adminItems" :key="item.path">
        <a v-if="item.external" :href="item.path" target="_blank" rel="noopener noreferrer"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group relative text-surface-200 hover:text-surface-100 hover:bg-surface-800/50"
          @click="$emit('close')">
          <span class="w-5 flex justify-center text-surface-400 group-hover:text-surface-200"><AppIcon :name="item.icon" :size="18" /></span>
          {{ item.label }}
          <AppIcon name="arrow-left" :size="12" class="ml-auto text-surface-500 rotate-180" />
        </a>
        <router-link v-else :to="item.path"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-all duration-150 group relative"
          :class="$route.path === item.path ? 'text-primary-400 bg-primary-500/10' : 'text-surface-200 hover:text-surface-100 hover:bg-surface-800/50'"
          @click="$emit('close')">
          <span class="w-5 flex justify-center" :class="$route.path === item.path ? '' : 'text-surface-400 group-hover:text-surface-200'"><AppIcon :name="item.icon" :size="18" /></span>
          {{ item.label }}
          <div v-if="$route.path === item.path" class="absolute left-0 top-1/2 -translate-y-1/2 w-0.5 h-5 rounded-full bg-primary-500 shadow-glow-sm"></div>
        </router-link>
      </template>
    </nav>

    <div class="p-3 border-t border-surface-700/50">
      <router-link to="/settings"
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-surface-200 hover:text-surface-100 hover:bg-surface-800/50 transition-all duration-150 group"
        @click="$emit('close')">
        <span class="w-5 flex justify-center text-surface-400 group-hover:text-surface-200"><AppIcon name="settings" :size="18" /></span>
        Settings
      </router-link>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../common/AppIcon.vue'

defineProps({ open: Boolean })
defineEmits(['close'])

const auth = useAuthStore()

const mainItems = computed(() => [
  { path: '/', label: 'Dashboard', icon: 'gauge' },
  { path: '/incidents', label: 'Incidents', icon: 'siren' },
  { path: '/requests', label: 'Service Requests', icon: 'clipboard-list' },
  { path: '/problems', label: 'Problems', icon: 'search' },
  { path: '/changes', label: 'Changes', icon: 'arrow-left-right' },
  { path: '/csi', label: 'CSI', icon: 'trending-up' },
  { path: '/cmdb', label: 'CMDB', icon: 'boxes' },
  { path: '/intune', label: 'Intune Devices', icon: 'monitor-smartphone' },
  { path: '/manual-devices', label: 'Manual Devices', icon: 'monitor' },
  { path: '/knowledge', label: 'Knowledge Base', icon: 'book-open' },
  { path: '/sla', label: 'SLA Contracts', icon: 'timer' },
  { path: '/reports', label: 'Reports', icon: 'bar-chart' },
  { path: '/year-wheel', label: 'Year Wheel', icon: 'circle-dashed' },
  { path: '/tasks', label: 'Tasks', icon: 'check-circle' },
])

const adminItems = computed(() => {
  if (!auth.isAdmin && !auth.isCustomerAdmin) return []
  const items = []
  if (auth.isAdmin) {
    items.push(
      { path: '/admin/users', label: 'User Management', icon: 'users' },
      { path: '/admin/impersonate', label: 'Impersonate', icon: 'user-cog' },
      { path: '/admin/audit-log', label: 'Audit Log', icon: 'scroll-text' },
    )
  }
  items.push({ path: '/customers', label: 'Customers', icon: 'building' })
  items.push({ path: '/vendors', label: 'Vendors', icon: 'truck' })
  if (auth.isAdmin || auth.isCustomerAdmin) {
    items.push({ path: '/inventory/', label: 'Inventory', icon: 'boxes', external: true })
  }
  if (auth.isAdmin) {
    items.push({ path: '/gdpr', label: 'GDPR', icon: 'shield' })
  }
  return items
})
</script>
