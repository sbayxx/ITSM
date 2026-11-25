<template>
  <header class="h-16 bg-surface-900/80 backdrop-blur-md border-b border-surface-700/50 flex items-center justify-between px-4 md:px-6 shrink-0">
    <div class="flex items-center gap-3">
      <button @click="$emit('toggle-sidebar')" class="md:hidden btn-ghost btn-sm text-surface-400 hover:text-surface-100">
        <AppIcon name="menu" :size="20" />
      </button>
      <div class="flex items-center gap-2 text-surface-300 text-xs font-mono">
        <span class="text-primary-400">~</span>
        <span class="text-surface-400">/</span>
        <span class="text-surface-100">{{ pageTitle }}</span>
      </div>
    </div>
    <div class="flex items-center gap-4">
      <div class="flex items-center gap-2 text-sm">
        <div class="w-6 h-6 rounded-full bg-primary-500/20 border border-primary-500/30 flex items-center justify-center">
          <span class="text-xs font-mono text-primary-400 font-semibold">{{ initials }}</span>
        </div>
        <div class="text-right hidden sm:block">
          <p class="text-xs font-medium text-surface-200">{{ auth.user?.name }}</p>
          <p class="text-[10px] font-mono text-surface-400">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span>
            {{ auth.user?.role }}
          </p>
        </div>
      </div>
      <button @click="handleLogout" class="btn-ghost btn-sm text-surface-400 hover:text-red-400">
        <AppIcon name="power" :size="16" />
      </button>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../common/AppIcon.vue'

defineEmits(['toggle-sidebar'])

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const pageTitle = computed(() => {
  const map = {
    'Dashboard': 'dashboard',
    'Incidents': 'incidents',
    'IncidentDetail': 'incidents/detail',
    'IncidentCreate': 'incidents/create',
    'Requests': 'service-requests',
    'RequestDetail': 'service-requests/detail',
    'RequestCreate': 'service-requests/create',
    'Problems': 'problems',
    'ProblemDetail': 'problems/detail',
    'ProblemCreate': 'problems/create',
    'Changes': 'changes',
    'ChangeDetail': 'changes/detail',
    'ChangeCreate': 'changes/create',
    'CSI': 'csi',
    'CsiDetail': 'csi/detail',
    'CsiCreate': 'csi/create',
    'CMDB': 'cmdb',
    'CiDetail': 'cmdb/detail',
    'CiCreate': 'cmdb/create',
    'KnowledgeBase': 'knowledge-base',
    'KnowledgeDetail': 'knowledge-base/article',
    'KnowledgeCreate': 'knowledge-base/create',
    'SLA': 'sla-contracts',
    'IntuneDevices': 'intune-devices',
    'YearWheel': 'year-wheel',
    'Tasks': 'tasks',
    'Customers': 'customers',
    'Vendors': 'vendors',
    'CustomerDetail': 'customers/detail',
    'Reports': 'reports',
    'AdminUsers': 'admin/users',
    'AuditLog': 'admin/audit-log',
    'Impersonate': 'admin/impersonate',
    'GDPR': 'gdpr',
    'Settings': 'settings',
  }
  return map[route.name] || 'unknown'
})

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2)
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
