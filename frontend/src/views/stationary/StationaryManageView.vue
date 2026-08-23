<template>
  <CContainer fluid class="py-3 px-3">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h5 class="mb-0 fw-bold">📎 {{ t('stationary.manageTitle') }}</h5>
        <div class="text-muted small">{{ t('stationary.manageSubtitle') }}</div>
      </div>
    </div>

    <!-- Summary stats (owner/superadmin only) -->
    <div v-if="summary && (auth.isOwner || auth.isSuperAdmin)" class="row g-2 mb-3">
      <div class="col-6 col-md-3" v-for="s in statCards" :key="s.key">
        <div class="border rounded p-2 text-center">
          <div class="fw-bold fs-5" :class="s.cls">{{ summary.counts[s.key] ?? 0 }}</div>
          <div class="text-muted small">{{ s.label }}</div>
        </div>
      </div>

      <!-- Low stock warning -->
      <div v-if="summary.low_stock?.length" class="col-12">
        <CAlert color="warning" class="mb-0 py-2 small">
          ⚠️ {{ t('stationary.lowStock') }}:
          <span v-for="(ls, i) in summary.low_stock" :key="ls.id">
            <strong>{{ ls.name }}</strong> ({{ ls.quantity }} {{ ls.unit }}){{ i < summary.low_stock.length - 1 ? ', ' : '' }}
          </span>
        </CAlert>
      </div>
    </div>

    <!-- Tab filter -->
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <CButton
        v-for="tab in tabs" :key="tab.value" size="sm" variant="ghost"
        :color="activeTab === tab.value ? 'primary' : 'secondary'"
        @click="activeTab = tab.value">
        {{ tab.label }}
        <CBadge v-if="tab.count" :color="activeTab === tab.value ? 'light' : 'secondary'" class="ms-1" text-color="dark">
          {{ tab.count }}
        </CBadge>
      </CButton>
    </div>

    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-4"><CSpinner size="sm" /> {{ t('common.loading') }}</div>

    <!-- Requests table -->
    <CCard v-else class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <div class="table-responsive">
          <CTable hover small class="align-middle mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('stationary.teacher') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('stationary.item') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('stationary.qty') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('stationary.reason') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="r in filtered" :key="r.id">
                <CTableDataCell>
                  <div class="fw-semibold small">{{ r.user?.name }}</div>
                  <div class="text-muted" style="font-size:0.7rem;">{{ fmtDate(r.created_at) }}</div>
                </CTableDataCell>
                <CTableDataCell class="small">{{ r.item_name }}</CTableDataCell>
                <CTableDataCell class="text-center small">
                  {{ r.quantity_requested }} {{ r.unit }}
                  <span v-if="r.quantity_provided" class="text-success d-block" style="font-size:0.7rem;">
                    ✓ {{ r.quantity_provided }} {{ t('stationary.given') }}
                  </span>
                </CTableDataCell>
                <CTableDataCell class="text-muted small" style="max-width:160px; white-space:pre-wrap;">
                  {{ r.reason || '—' }}
                  <div v-if="r.rejection_reason" class="text-danger" style="font-size:0.7rem;">
                    {{ r.rejection_reason }}
                  </div>
                </CTableDataCell>
                <CTableDataCell class="text-center">
                  <CBadge :color="statusColor(r.status)" class="px-2">{{ t(`stationary.${r.status}`) }}</CBadge>
                  <div v-if="r.reviewer" class="text-muted" style="font-size:0.7rem;">{{ r.reviewer.name }}</div>
                </CTableDataCell>
                <CTableDataCell class="text-end">
                  <div class="d-flex gap-1 justify-content-end flex-wrap">
                    <CButton v-if="r.status === 'pending'" color="info" size="sm" variant="outline"
                      :disabled="actionId === r.id" @click="doApprove(r)">
                      {{ t('stationary.approve') }}
                    </CButton>
                    <CButton v-if="['pending','approved'].includes(r.status)" color="success" size="sm" variant="outline"
                      :disabled="actionId === r.id" @click="openProvide(r)">
                      {{ t('stationary.provide') }}
                    </CButton>
                    <CButton v-if="r.status === 'pending'" color="danger" size="sm" variant="outline"
                      :disabled="actionId === r.id" @click="openReject(r)">
                      {{ t('common.reject') }}
                    </CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!filtered.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">
                  {{ t('stationary.noRequests') }}
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

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
