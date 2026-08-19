import { defineStore } from 'pinia'
import { computed } from 'vue'
import { useAuthStore } from './auth'

export const useUserStore = defineStore('user', () => {
  const auth = useAuthStore()
  const role = computed(() => auth.user?.role || '')
  const isOwner      = computed(() => role.value === 'owner')
  const isAccountant = computed(() => role.value === 'accountant')
  const isParent     = computed(() => role.value === 'parent')
  return { role, isOwner, isAccountant, isParent }
})
