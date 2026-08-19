<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-end gap-2 mb-3">
      <CButton color="success" size="sm" @click="openModal('top_up')" style="min-height:36px;">
        <CIcon icon="cilPlus" class="me-1" /> {{ t('pettyCash.topUp') }}
      </CButton>
      <CButton color="warning" size="sm" @click="openModal('expense')" style="min-height:36px;">
        <CIcon icon="cilMinus" class="me-1" /> {{ t('pettyCash.expense') }}
      </CButton>
    </div>

    <!-- Summary row -->
    <CRow class="g-3 mb-3">
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100" style="border-left:4px solid #198754 !important">
          <CCardBody class="p-3">
            <div class="text-muted small text-uppercase fw-semibold mb-1">{{ t('pettyCash.balance') }}</div>
            <div :class="balance >= 0 ? 'text-success fw-bold fs-5' : 'text-danger fw-bold fs-5'">{{ formatTZS(balance) }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100" style="border-left:4px solid #0d6efd !important">
          <CCardBody class="p-3">
            <div class="text-muted small text-uppercase fw-semibold mb-1">{{ t('pettyCash.transactions') }}</div>
            <div class="fw-bold fs-5">{{ transactions.length }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100" style="border-left:4px solid #20c997 !important">
          <CCardBody class="p-3">
            <div class="text-muted small text-uppercase fw-semibold mb-1">{{ t('pettyCash.totalIn') }}</div>
            <div class="text-success fw-bold fs-5">{{ formatTZS(totalIn) }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100" style="border-left:4px solid #dc3545 !important">
          <CCardBody class="p-3">
            <div class="text-muted small text-uppercase fw-semibold mb-1">{{ t('pettyCash.totalOut') }}</div>
            <div class="text-danger fw-bold fs-5">{{ formatTZS(totalOut) }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <!-- Transactions List -->
    <div v-if="loading" class="text-center py-4"><CSpinner color="primary" /></div>
    <div v-else>
      <div v-if="!transactions.length" class="text-center text-muted py-5">{{ t('pettyCash.noTransactions') }}</div>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; loadData()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem
            v-for="p in visiblePages"
            :key="p"
            :active="p === meta.current_page"
            @click="page = p; loadData()"
          >{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; loadData()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block mb-3">
        <CCardBody class="p-0">
          <CTable responsive hover class="mb-0 align-middle">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('pettyCash.type') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('pettyCash.description') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.amount') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('pettyCash.balance') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="tx in transactions" :key="tx.id">
                <CTableDataCell class="small">{{ tx.entry_date?.slice(0,10) }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="tx.type === 'top_up' ? 'success' : 'danger'" shape="rounded-pill">
                    {{ tx.type === 'top_up' ? t('pettyCash.topUp') : t('pettyCash.expense') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell>{{ tx.description }}</CTableDataCell>
                <CTableDataCell :class="tx.type === 'top_up' ? 'text-success fw-bold' : 'text-danger fw-bold'">
                  {{ tx.type === 'top_up' ? '+' : '-' }}{{ formatTZS(tx.amount_cents) }}
                </CTableDataCell>
                <CTableDataCell class="fw-semibold">{{ formatTZS(tx.running_balance_cents) }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Mobile list -->
      <div class="d-md-none">
        <div v-for="tx in transactions" :key="tx.id" class="mb-2">
          <CCard :style="`border-left:4px solid ${tx.type === 'top_up' ? '#198754' : '#dc3545'}`">
            <CCardBody class="p-3">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold">{{ tx.description }}</div>
                  <div class="text-muted small">{{ tx.entry_date?.slice(0,10) }}</div>
                </div>
                <div :class="tx.type === 'top_up' ? 'text-success fw-bold fs-5' : 'text-danger fw-bold fs-5'">
                  {{ tx.type === 'top_up' ? '+' : '-' }}{{ formatTZS(tx.amount_cents) }}
                </div>
              </div>
            </CCardBody>
          </CCard>
        </div>
      </div>
    </div>

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
import { CPagination, CPaginationItem } from '@coreui/vue'
import api from '@/services/api'

const { t } = useI18n()

const transactions = ref([])
const loading = ref(false)
const showModal = ref(false)
const modalType = ref('top_up')
const saving = ref(false)
const formError = ref('')
const page = ref(1)
const meta = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const today = new Date().toISOString().slice(0, 10)
const form = ref({ amount: '', description: '', date: today })

const balance = computed(() =>
  transactions.value.reduce((sum, tx) =>
    sum + (tx.type === 'top_up' ? (tx.amount_cents || 0) : -(tx.amount_cents || 0)), 0)
)

const totalIn = computed(() =>
  transactions.value.filter(tx => tx.type === 'top_up').reduce((s, tx) => s + (tx.amount_cents || 0), 0)
)

const totalOut = computed(() =>
  transactions.value.filter(tx => tx.type === 'expense').reduce((s, tx) => s + (tx.amount_cents || 0), 0)
)

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
    const { data } = await api.get('/petty-cash', { params: { page: page.value } })
    transactions.value = data.data || data
    if (data.meta) meta.value = data.meta
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
