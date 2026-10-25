<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />
    <CAlert v-if="error" color="danger" dismissible class="xg-alert" @close="error=''">{{ error }}</CAlert>

    <XBar :meta="{}" no-per-page :total="filtered.length">
      <template #actions>
        <CButton color="success" size="sm" @click="openRequest">+ {{ t('stationary.request') }}</CButton>
      </template>
      <template #toolbar>
        <button v-for="tab in tabs" :key="tab.value" class="xg-tb-btn" :class="{ 'is-active': activeTab === tab.value }" @click="activeTab = tab.value">
          {{ tab.label }}<span v-if="tab.count"> ({{ tab.count }})</span>
        </button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selId" :columns="columns" :rows="filtered" :loading="loading" :empty="t('stationary.noRequests')" :min-width="720" :row-class="rowClass">
      <template #cell-item_name="{ row }"><span class="xg-name">{{ row.item_name }}</span></template>
      <template #cell-qty="{ row }">
        <div class="xg-two"><span>{{ row.quantity_requested }} {{ row.unit }}</span><span v-if="row.quantity_provided" class="xg-sub">✓ {{ row.quantity_provided }} {{ t('stationary.given') }}</span></div>
      </template>
      <template #cell-status="{ row }">
        <div class="xg-two"><span class="xg-pill" :class="statusPill(row.status)">{{ t(`stationary.${row.status}`) }}</span><span v-if="row.status === 'rejected' && row.rejection_reason" class="xg-sub">{{ row.rejection_reason }}</span></div>
      </template>
      <template #cell-created_at="{ row }"><span class="xg-mono">{{ fmtDate(row.created_at) }}</span></template>
    </XGrid>

    <!-- Request modal -->
    <CModal :visible="showForm" @close="showForm=false" backdrop="static" size="sm">
      <CModalHeader><CModalTitle>📎 {{ t('stationary.request') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="mb-2 py-2">{{ formError }}</CAlert>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('stationary.item') }} <span class="text-danger">*</span></CFormLabel>
          <CFormSelect v-model="form.item_id" size="sm">
            <option value="">— {{ t('stationary.selectItem') }} —</option>
            <option v-for="i in items" :key="i.id" :value="i.id">
              {{ i.name }} ({{ i.quantity }} {{ i.unit }} {{ t('stationary.inStock') }})
            </option>
          </CFormSelect>
          <div v-if="!items.length" class="text-muted small mt-1">{{ t('stationary.noStock') }}</div>
        </div>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('stationary.qty') }} <span class="text-danger">*</span></CFormLabel>
          <CFormInput v-model.number="form.quantity_requested" type="number" min="0.5" step="0.5" size="sm" />
        </div>

        <div class="mb-1">
          <CFormLabel class="fw-semibold small">{{ t('stationary.reasonLabel') }}</CFormLabel>
          <CFormTextarea v-model="form.reason" rows="2" size="sm" :placeholder="t('stationary.reasonPlaceholder')" />
        </div>
      </CModalBody>
      <CModalFooter class="py-2">
        <CButton color="secondary" variant="ghost" size="sm" @click="showForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" size="sm"
          :disabled="saving || !form.item_id || !form.quantity_requested"
          @click="submit">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ t('stationary.sendRequest') }}
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

const { t } = useI18n()

const requests = ref([])
const items    = ref([])
const loading  = ref(true)
const error    = ref('')
const activeTab = ref('all')

const showForm  = ref(false)
const saving    = ref(false)
const formError = ref('')
const form      = ref({ item_id: '', quantity_requested: 1, reason: '' })


// ── List design (same as the Expenses page) ──
const selId = ref(null)
const rowClass = (r) => ({ pending: 'xg-row--warn', provided: 'xg-row--good', rejected: 'xg-row--bad' }[r.status] || '')
const statusPill = (st) => ({ pending: 'xg-pill--amber', approved: 'xg-pill--blue', provided: 'xg-pill--green', rejected: 'xg-pill--red' }[st] || 'xg-pill--gray')
const columns = computed(() => [
  { key: 'item_name', label: t('stationary.item'), w: 'minmax(150px, 1.6fr)' },
  { key: 'qty', label: t('stationary.qty'), w: 'minmax(110px, 1fr)' },
  { key: 'reason', label: t('stationary.reason'), w: 'minmax(170px, 2fr)' },
  { key: 'status', label: t('common.status'), w: 'minmax(130px, 1.1fr)' },
  { key: 'created_at', label: t('common.date'), w: 'minmax(90px, .8fr)' },
])
const cards = computed(() => ['pending', 'approved', 'provided', 'rejected'].map((k, i) => ({
  label: t(`stationary.${k}`), value: requests.value.filter(r => r.status === k).length, tone: ['amber', 'blue', 'green', 'red'][i],
})))

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
  return d ? new Date(d).toLocaleDateString('sw-TZ', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

async function load() {
  loading.value = true
  try {
    const [reqRes, itemRes] = await Promise.all([api.get('/stationary'), api.get('/stationary/items')])
    requests.value = reqRes.data
    items.value    = itemRes.data
  } catch (e) {
    error.value = e?.response?.data?.message || t('common.loadError')
  } finally {
    loading.value = false
  }
}

function openRequest() {
  form.value = { item_id: '', quantity_requested: 1, reason: '' }
  formError.value = ''
  showForm.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const { data } = await api.post('/stationary', form.value)
    requests.value.unshift(data)
    showForm.value = false
  } catch (e) {
    const errs = e?.response?.data?.errors
    formError.value = errs ? Object.values(errs).flat().join(' | ') : (e?.response?.data?.message || t('common.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
