<script setup>
import { onMounted } from 'vue'
import { useNotificationsStore } from '@/stores/notifications'

const store = useNotificationsStore()

function formatTime(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleString('sw-TZ', { dateStyle: 'medium', timeStyle: 'short' })
}

function notifIcon(type) {
  const icons = {
    absence_alert:  '🔔',
    low_stock:      '📦',
    payment:        '💰',
    general:        '📢',
  }
  return icons[type] || '🔔'
}

async function handleClickNotif(notif) {
  if (!notif.is_read) {
    await store.markRead(notif.id)
  }
}

onMounted(async () => {
  await store.fetchNotifications()
})
</script>

<template>
  <CContainer class="py-3" style="max-width:800px;">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0 fw-bold">
        🔔 Arifa
        <CBadge v-if="store.unreadCount > 0" color="danger" class="ms-2">{{ store.unreadCount }}</CBadge>
      </h4>
      <CButton
        v-if="store.notifications.length > 0"
        color="secondary"
        variant="ghost"
        size="sm"
        @click="store.markAllRead"
      >
        Weka Zote Zimesomwa
      </CButton>
    </div>

    <div v-if="store.loading" class="text-center py-5">
      <CSpinner />
    </div>

    <template v-else-if="store.notifications.length > 0">
      <CCard
        v-for="notif in store.notifications"
        :key="notif.id"
        class="mb-2 border-0 shadow-sm"
        :style="notif.is_read ? '' : 'background-color:#eff8ff; border-left:4px solid #0d6efd !important;'"
        style="cursor:pointer; transition:all .2s;"
        @click="handleClickNotif(notif)"
      >
        <CCardBody class="py-3 px-3">
          <div class="d-flex align-items-start gap-3">
            <div class="fs-3 flex-shrink-0">{{ notifIcon(notif.type) }}</div>
            <div class="flex-grow-1">
              <div class="fw-bold" :class="notif.is_read ? '' : 'text-primary'">{{ notif.title }}</div>
              <div class="text-muted mt-1">{{ notif.body }}</div>
              <div class="text-muted small mt-1">{{ formatTime(notif.created_at) }}</div>
            </div>
            <div v-if="!notif.is_read" class="flex-shrink-0">
              <span class="badge rounded-pill bg-primary">Mpya</span>
            </div>
          </div>
        </CCardBody>
      </CCard>
    </template>

    <div v-else class="text-center text-muted py-5">
      <div class="display-6 mb-2">🔔</div>
      <div>Hakuna arifa mpya</div>
    </div>
  </CContainer>
</template>
