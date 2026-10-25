<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <XBar :meta="meta" v-model:per-page="perPage" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <CButton color="success" size="sm" @click="openModal('top_up')"><CIcon icon="cilPlus" class="me-1" /> {{ t('pettyCash.topUp') }}</CButton>
        <CButton color="warning" size="sm" @click="openModal('expense')"><CIcon icon="cilMinus" class="me-1" /> {{ t('pettyCash.expense') }}</CButton>
      </template>
      <template #toolbar><span class="small text-muted">{{ t('pettyCash.subtitle') }}</span></template>
    </XBar>

    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="transactions" :loading="loading" :empty="t('pettyCash.noTransactions')" :min-width="860" :row-class="rowClass">
      <template #cell-entry_date="{ row }"><span class="xg-mono">{{ row.entry_date?.slice(0, 10) }}</span></template>
      <template #cell-description="{ row }">
        <div class="xg-two"><span class="xg-name">{{ row.description }}</span><span v-if="row.reference" class="xg-sub">#{{ row.reference }}</span></div>
      </template>
      <template #cell-type="{ row }">
        <span class="xg-pill" :class="row.type === 'top_up' ? 'xg-pill--green' : 'xg-pill--red'">{{ row.type === 'top_up' ? t('pettyCash.topUp') : t('pettyCash.expense') }}</span>
      </template>
      <template #cell-amount_cents="{ row }"><span :class="row.type === 'top_up' ? 'xg-in' : 'xg-out'">{{ row.type === 'top_up' ? '+' : '-' }}{{ formatTZS(row.amount_cents) }}</span></template>
      <template #cell-balance_after_cents="{ row }"><span class="xg-strong">{{ formatTZS(row.balance_after_cents) }}</span></template>
    </XGrid>

    <!-- Modal -->
    <CModal :visible="showModal" @close="showModal=false" class="modal-fullscreen-sm-down" backdrop="static">
      <CModalHeader>
        <CModalTitle>{{ modalType === 'top_up' ? t('pettyCash.topUp') : t('pettyCash.expense') }}</CModalTitle>
      </CModalHeader>
      <CModalBody class="p-3">
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('pettyCash.amount') }} <span class="text-danger">*</span></label>
          <CFormInput type="number" v-model.number="form.amount" min="1" placeholder="e.g. 10000" size="lg" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('pettyCash.description') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="form.description" :placeholder="t('pettyCash.description')" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.date') }}</label>
          <CFormInput type="date" v-model="form.date" />
        </div>
        <CAlert v-if="formError" color="danger">{{ formError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showModal=false">{{ t('common.cancel') }}</CButton>
        <CButton :color="modalType === 'top_up' ? 'success' : 'warning'" :disabled="saving" @click="submit"
                 style="min-height:44px; min-width:100px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
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

const transactions = ref([])
const loading = ref(false)
const showModal = ref(false)
const modalType = ref('top_up')
const saving = ref(false)
const formError = ref('')
const page = ref(1)
const meta = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
const summary = ref({ balance_cents: 0, in_cents: 0, out_cents: 0 })

const perPage = ref('20')
const selectedId = ref(null)
const rowClass = (r) => (r.type === 'top_up' ? 'xg-row--good' : '')
const columns = computed(() => [
  { key: 'entry_date', label: t('common.date'), w: 'minmax(100px, .9fr)' },
  { key: 'type', label: t('pettyCash.type'), w: 'minmax(100px, .9fr)' },
  { key: 'description', label: t('pettyCash.description'), w: 'minmax(220px, 3fr)' },
  { key: 'amount_cents', label: t('common.amount'), w: 'minmax(120px, 1.2fr)', align: 'end' },
  { key: 'balance_after_cents', label: t('pettyCash.balance'), w: 'minmax(120px, 1.2fr)', align: 'end' },
  { key: 'recorder_name', label: t('expensex.col.recordedBy'), w: 'minmax(110px, 1fr)' },
])
const cards = computed(() => [
  { label: t('pettyCash.balance'), value: formatTZS(balance.value), tone: balance.value >= 0 ? 'green' : 'red' },
  { label: t('pettyCash.transactions'), value: meta.value.total || transactions.value.length, tone: 'gray' },
  { label: t('pettyCash.totalIn'), value: formatTZS(totalIn.value), tone: 'blue' },
  { label: t('pettyCash.totalOut'), value: formatTZS(totalOut.value), tone: 'red' },
])
function goPage(p) { if (typeof p === 'number') page.value = p; else page.value = 1; loadData() }

const today = new Date().toISOString().slice(0, 10)
const form = ref({ amount: '', description: '', date: today })

const balance = computed(() => summary.value.balance_cents)
const totalIn = computed(() => summary.value.in_cents)
const totalOut = computed(() => summary.value.out_cents)

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

function openModal(type) {
  modalType.value = type
  form.value = { amount: '', description: '', date: today }
  formError.value = ''
  showModal.value = true
}

async function loadData() {
  loading.value = true
  try {
    const { data } = await api.get('/petty-cash', { params: { page: page.value, per_page: perPage.value } })
    transactions.value = data.data || data
    if (data.meta) meta.value = data.meta
    if (data.summary) summary.value = data.summary
  } catch {} finally { loading.value = false }
}

async function submit() {
  formError.value = ''
  if (!form.value.amount || form.value.amount <= 0) { formError.value = t('common.required'); return }
  if (!form.value.description.trim()) { formError.value = t('common.required'); return }
  saving.value = true
  try {
    await api.post('/petty-cash', {
      type: modalType.value,
      amount_cents: Math.round(form.value.amount * 100),
      description: form.value.description,
      entry_date: form.value.date,
    })
    showModal.value = false
    loadData()
  } catch (e) {
    formError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => { try { await loadData() } catch {} })
</script>
