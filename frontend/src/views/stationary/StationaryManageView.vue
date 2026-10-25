<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <CAlert v-if="summary && summary.low_stock?.length" color="warning" class="xg-alert small">
      ⚠️ {{ t('stationary.lowStock') }}:
      <span v-for="(ls, i) in summary.low_stock" :key="ls.id">
        <strong>{{ ls.name }}</strong> ({{ ls.quantity }} {{ ls.unit }}){{ i < summary.low_stock.length - 1 ? ', ' : '' }}
      </span>
    </CAlert>
    <CAlert v-if="error" color="danger" dismissible class="xg-alert" @close="error=''">{{ error }}</CAlert>

    <XBar :meta="{}" no-per-page :total="filtered.length">
      <template #toolbar>
        <button v-for="tab in tabs" :key="tab.value" class="xg-tb-btn" :class="{ 'is-active': activeTab === tab.value }" @click="activeTab = tab.value">
          {{ tab.label }}<span v-if="tab.count"> ({{ tab.count }})</span>
        </button>
        <span class="mx-1">|</span>
        <button class="xg-tb-btn is-ok" :disabled="!canApprove(sel)" @click="canApprove(sel) && doApprove(sel)">✅ {{ t('stationary.approve') }}</button>
        <button class="xg-tb-btn is-ok" :disabled="!canProvide(sel)" @click="canProvide(sel) && openProvide(sel)">📦 {{ t('stationary.provide') }}</button>
        <button class="xg-tb-btn is-danger" :disabled="!canApprove(sel)" @click="canApprove(sel) && openReject(sel)">⛔ {{ t('common.reject') }}</button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selId" :columns="columns" :rows="filtered" :loading="loading" :empty="t('stationary.noRequests')"
           :min-width="860" :menu="menuFor" :row-class="rowClass">
      <template #cell-teacher="{ row }">
        <div class="xg-two"><span class="xg-name">{{ row.user?.name }}</span><span class="xg-sub">{{ fmtDate(row.created_at) }}</span></div>
      </template>
      <template #cell-qty="{ row }">
        <div class="xg-two"><span>{{ row.quantity_requested }} {{ row.unit }}</span><span v-if="row.quantity_provided" class="xg-sub">✓ {{ row.quantity_provided }} {{ t('stationary.given') }}</span></div>
      </template>
      <template #cell-reason="{ row }">
        <div class="xg-two"><span>{{ row.reason || '—' }}</span><span v-if="row.rejection_reason" class="xg-sub">{{ row.rejection_reason }}</span></div>
      </template>
      <template #cell-status="{ row }">
        <div class="xg-two"><span class="xg-pill" :class="statusPill(row.status)">{{ t(`stationary.${row.status}`) }}</span><span v-if="row.reviewer" class="xg-sub">{{ row.reviewer.name }}</span></div>
      </template>
    </XGrid>

    <!-- Provide modal -->
    <CModal :visible="!!provideTarget" @close="provideTarget=null" backdrop="static" size="sm">
      <CModalHeader><CModalTitle>✅ {{ t('stationary.provide') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="modalError" color="danger" class="py-2 small">{{ modalError }}</CAlert>
        <p class="mb-2 small text-muted">
          {{ provideTarget?.user?.name }} — <strong>{{ provideTarget?.item_name }}</strong>
          ({{ t('stationary.requested') }}: {{ provideTarget?.quantity_requested }} {{ provideTarget?.unit }})
        </p>
        <CFormLabel class="fw-semibold small">{{ t('stationary.qtyToGive') }} <span class="text-danger">*</span></CFormLabel>
        <CFormInput v-model.number="provideQty" type="number" min="0.5" step="0.5" size="sm" />
      </CModalBody>
      <CModalFooter class="py-2">
        <CButton color="secondary" variant="ghost" size="sm" @click="provideTarget=null">{{ t('common.cancel') }}</CButton>
        <CButton color="success" size="sm" :disabled="saving || !provideQty" @click="doProvide">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('stationary.confirmProvide') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Reject modal -->
    <CModal :visible="!!rejectTarget" @close="rejectTarget=null" backdrop="static" size="sm">
      <CModalHeader><CModalTitle>❌ {{ t('common.reject') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="modalError" color="danger" class="py-2 small">{{ modalError }}</CAlert>
        <p class="mb-2 small text-muted">{{ rejectTarget?.user?.name }} — <strong>{{ rejectTarget?.item_name }}</strong></p>
        <CFormLabel class="fw-semibold small">{{ t('stationary.rejectReason') }}</CFormLabel>
        <CFormTextarea v-model="rejectReason" rows="2" size="sm" :placeholder="t('stationary.reasonPlaceholder')" />
      </CModalBody>
      <CModalFooter class="py-2">
        <CButton color="secondary" variant="ghost" size="sm" @click="rejectTarget=null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" size="sm" :disabled="saving" @click="doReject">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.reject') }}
        </CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const requests = ref([])
const summary  = ref(null)
const loading  = ref(true)
const error    = ref('')
const activeTab = ref('all')

const actionId      = ref(null)
const saving        = ref(false)
const modalError    = ref('')

const provideTarget = ref(null)
const provideQty    = ref(1)
const rejectTarget  = ref(null)
const rejectReason  = ref('')


// ── List design (same as the Expenses page) ──
const selId = ref(null)
const sel = computed(() => filtered.value.find(r => r.id === selId.value) || null)
const canApprove = (r) => !!r && r.status === 'pending'
const canProvide = (r) => !!r && ['pending', 'approved'].includes(r.status)
const rowClass = (r) => ({ pending: 'xg-row--warn', provided: 'xg-row--good', rejected: 'xg-row--bad' }[r.status] || '')
const statusPill = (st) => ({ pending: 'xg-pill--amber', approved: 'xg-pill--blue', provided: 'xg-pill--green', rejected: 'xg-pill--red' }[st] || 'xg-pill--gray')
const columns = computed(() => [
  { key: 'teacher', label: t('stationary.teacher'), w: 'minmax(150px, 1.6fr)' },
  { key: 'item_name', label: t('stationary.item'), w: 'minmax(130px, 1.3fr)' },
  { key: 'qty', label: t('stationary.qty'), w: 'minmax(110px, 1fr)' },
  { key: 'reason', label: t('stationary.reason'), w: 'minmax(170px, 2fr)' },
  { key: 'status', label: t('common.status'), w: 'minmax(120px, 1.1fr)' },
])
const cards = computed(() => {
  const c = summary.value?.counts || {}
  const n = (k) => c[k] ?? requests.value.filter(r => r.status === k).length
  return [
    { label: t('stationary.pending'), value: n('pending'), tone: 'amber' },
    { label: t('stationary.approved'), value: n('approved'), tone: 'blue' },
    { label: t('stationary.provided'), value: n('provided'), tone: 'green' },
    { label: t('stationary.rejected'), value: n('rejected'), tone: 'red' },
  ]
})
const menuFor = (r) => [
  ...(canApprove(r) ? [{ label: '✅ ' + t('stationary.approve'), run: doApprove }] : []),
  ...(canProvide(r) ? [{ label: '📦 ' + t('stationary.provide'), run: openProvide }] : []),
  ...(canApprove(r) ? [{ label: '⛔ ' + t('common.reject'), run: openReject, danger: true }] : []),
]

const statCards = computed(() => [
  { key: 'pending',  label: t('stationary.pending'),  cls: 'text-warning' },
  { key: 'approved', label: t('stationary.approved'), cls: 'text-info'    },
  { key: 'provided', label: t('stationary.provided'), cls: 'text-success' },
  { key: 'rejected', label: t('stationary.rejected'), cls: 'text-danger'  },
])

const tabs = computed(() => {
  const counts = { pending: 0, approved: 0, provided: 0, rejected: 0 }
  requests.value.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++ })
  return [
    { value: 'all',      label: t('common.all') },
    { value: 'pending',  label: t('stationary.pending'),  count: counts.pending  },
    { value: 'approved', label: t('stationary.approved'), count: counts.approved },
    { value: 'provided', label: t('stationary.provided'), count: counts.provided },
    { value: 'rejected', label: t('stationary.rejected'), count: counts.rejected },
  ]
})

const filtered = computed(() =>
  activeTab.value === 'all' ? requests.value : requests.value.filter(r => r.status === activeTab.value)
)

function statusColor(s) {
  return { pending: 'warning', approved: 'info', provided: 'success', rejected: 'danger' }[s] ?? 'secondary'
}
function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('sw-TZ', { day: 'numeric', month: 'short' }) : '—'
}

async function load() {
  loading.value = true
  try {
    const calls = [api.get('/stationary')]
    if (auth.isOwner || auth.isSuperAdmin) calls.push(api.get('/stationary/summary'))
    const [reqRes, sumRes] = await Promise.all(calls)
    requests.value = reqRes.data
    summary.value  = sumRes?.data ?? null
  } catch (e) {
    error.value = e?.response?.data?.message || t('common.loadError')
  } finally {
    loading.value = false
  }
}

async function doApprove(r) {
  actionId.value = r.id
  try {
    const { data } = await api.post(`/stationary/${r.id}/approve`)
    Object.assign(r, data)
  } catch (e) {
    error.value = e?.response?.data?.message || t('common.saveError')
  } finally {
    actionId.value = null
  }
}

function openProvide(r) {
  provideTarget.value = r
  provideQty.value    = r.quantity_requested
  modalError.value    = ''
}

async function doProvide() {
  saving.value = true
  modalError.value = ''
  try {
    const { data } = await api.post(`/stationary/${provideTarget.value.id}/provide`, { quantity_provided: provideQty.value })
    const idx = requests.value.findIndex(r => r.id === data.id)
    if (idx !== -1) requests.value[idx] = data
    provideTarget.value = null
    if (summary.value) await loadSummary()
  } catch (e) {
    modalError.value = e?.response?.data?.message || t('common.saveError')
  } finally {
    saving.value = false
  }
}

function openReject(r) {
  rejectTarget.value = r
  rejectReason.value = ''
  modalError.value   = ''
}

async function doReject() {
  saving.value = true
  modalError.value = ''
  try {
    const { data } = await api.post(`/stationary/${rejectTarget.value.id}/reject`, { rejection_reason: rejectReason.value })
    const idx = requests.value.findIndex(r => r.id === data.id)
    if (idx !== -1) requests.value[idx] = data
    rejectTarget.value = null
  } catch (e) {
    modalError.value = e?.response?.data?.message || t('common.saveError')
  } finally {
    saving.value = false
  }
}

async function loadSummary() {
  try { summary.value = (await api.get('/stationary/summary')).data } catch {}
}

onMounted(load)
</script>
