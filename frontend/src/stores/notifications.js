import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref([])
  const unreadCount   = ref(0)
  const loading       = ref(false)
  let pollTimer       = null

  async function fetchNotifications() {
    loading.value = true
    try {
      const { data } = await api.get('/notifications')
      notifications.value = data.data || data
    } catch (e) {
      notifications.value = []
    } finally {
      loading.value = false
    }
  }

  async function markRead(id) {
    try {
      const { data } = await api.post(`/notifications/${id}/read`)
      const idx = notifications.value.findIndex(n => n.id === id)
      if (idx !== -1) notifications.value[idx] = data
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    } catch (e) {
      // silent
    }
  }

  async function markAllRead() {
    await api.post('/notifications/mark-all-read')
    notifications.value = notifications.value.map(n => ({ ...n, is_read: true }))
    unreadCount.value = 0
  }

  async function fetchUnreadCount() {
    try {
      const { data } = await api.get('/notifications/unread-count')
      unreadCount.value = data.count ?? 0
    } catch (e) {
      // silent
    }
  }

  function startPolling() {
    fetchUnreadCount()
    pollTimer = setInterval(fetchUnreadCount, 60000)
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer)
      pollTimer = null
    }
  }

  return {
    notifications, unreadCount, loading,
    fetchNotifications, markRead, markAllRead, fetchUnreadCount,
    startPolling, stopPolling,
  }
})
