<template>
  <div class="flex h-screen overflow-hidden">
    <div v-if="sidebarOpen"
      class="fixed inset-0 bg-black/50 z-30 md:hidden transition-opacity"
      @click="sidebarOpen = false"></div>
    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
    <div class="flex-1 flex flex-col overflow-hidden">
      <ImpersonationBanner />
      <AppHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <main class="flex-1 overflow-y-auto p-4 md:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppSidebar from './AppSidebar.vue'
import AppHeader from './AppHeader.vue'
import ImpersonationBanner from './ImpersonationBanner.vue'

const sidebarOpen = ref(false)
</script>
