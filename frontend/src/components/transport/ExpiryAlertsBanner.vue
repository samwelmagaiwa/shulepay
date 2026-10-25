<script setup>
/**
 * Vehicle documents that need attention: expired, about to expire, or never
 * recorded. Renders nothing when everything is in order.
 */
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const props = defineProps({
  /** Bump to reload, e.g. after a document was saved. */
  refreshKey: { type: Number, default: 0 },
})
const emit = defineEmits(['open-documents'])

const { t } = useI18n()
const alerts = ref(null)
const expanded = ref(false)

async function load() {
  try {
    alerts.value = (await api.get('/transport/documents/alerts')).data.data
  } catch {
    alerts.value = null // the banner is a convenience; a failure must not block the page
  }
}

const counts = computed(() => alerts.value?.counts ?? { total: 0 })
const rows = computed(() => [
  ...(alerts.value?.expired ?? []).map((d) => ({ ...d, tone: 'danger' })),
  ...(alerts.value?.expiring ?? []).map((d) => ({ ...d, tone: 'warning' })),
  ...(alerts.value?.missing ?? []).map((d) => ({ ...d, tone: 'secondary', missing: true })),
])

const typeLabel = (type) => t(`transport.docs.types.${type}`)
const when = (row) => (row.days_left < 0
  ? t('transport.docs.expiredAgo', { days: Math.abs(row.days_left) })
  : t('transport.docs.inDays', { days: row.days_left }))

watch(() => props.refreshKey, load)
onMounted(load)
</script>

<template>
  <div v-if="counts.total" class="alert alert-warning border-0 shadow-sm mb-3" role="alert">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <strong>⚠️ {{ t('transport.docs.alertTitle') }}</strong>
      <CBadge v-if="counts.expired" color="danger">{{ t('transport.docs.expiredCount', { n: counts.expired }) }}</CBadge>
      <CBadge v-if="counts.expiring" color="warning">{{ t('transport.docs.expiringCount', { n: counts.expiring }) }}</CBadge>
      <CBadge v-if="counts.missing" color="secondary">{{ t('transport.docs.missingCount', { n: counts.missing }) }}</CBadge>
      <span class="ms-auto d-flex gap-2">
        <CButton size="sm" color="secondary" variant="outline" @click="expanded = !expanded">
          {{ expanded ? t('transport.docs.hide') : t('transport.docs.details') }}
        </CButton>
        <CButton size="sm" color="primary" @click="emit('open-documents')">{{ t('transport.docs.manage') }}</CButton>
      </span>
    </div>

    <ul v-if="expanded" class="list-unstyled mb-0 mt-2 small">
      <li v-for="(row, i) in rows" :key="`${row.vehicle_id}-${row.type}-${i}`" class="d-flex flex-wrap gap-2 py-1 border-top">
        <CBadge :color="row.tone">{{ row.plate_number }}</CBadge>
        <span class="fw-semibold">{{ typeLabel(row.type) }}</span>
        <span v-if="row.missing" class="text-body-secondary">{{ t('transport.docs.notRecorded') }}</span>
        <span v-else class="text-body-secondary">{{ row.expiry_date }} · {{ when(row) }}</span>
      </li>
    </ul>
  </div>
</template>
