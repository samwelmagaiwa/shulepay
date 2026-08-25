<template>
  <CContainer fluid class="p-2 p-md-3">
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile: compact toolbar + cards -->
      <div class="d-md-none">
        <div class="d-flex gap-2 mb-3 flex-wrap">
          <CFormSelect v-model="filters.status" @update:modelValue="page = 1; load()" size="sm" style="flex:1; min-width:120px;">
            <option value="">{{ t('common.allStatuses') }}</option>
            <option value="active">{{ t('installments.ongoing') }}</option>
            <option value="completed">{{ t('installments.completed') }}</option>
          </CFormSelect>
          <CFormInput v-model="filters.search" :placeholder="t('installments.searchPlaceholder')" @input="debouncedLoad" size="sm" style="flex:2; min-width:140px;" />
          <CButton color="primary" size="sm" @click="showBulkModal = true" style="white-space:nowrap;">📋 {{ t('installments.bulkByClass') }}</CButton>
        </div>
      </div>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!installments.length" class="text-center text-muted py-5">{{ t('installments.noPlans') }}</div>
        <div v-for="plan in installments" :key="plan.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">{{ plan.student?.full_name }}</div>
              <div class="small text-muted">{{ t('installments.invoice') }}: {{ plan.invoice?.invoice_number }}</div>
              <div class="small text-muted">{{ plan.installments_paid || 0 }}/{{ plan.total_installments }} (Invoice paid)</div>
              <div class="small text-muted">{{ t('installments.nextDue') }}: {{ plan.next_due_date || '—' }}</div>
            </div>
            <CBadge :color="plan.status === 'completed' || plan.status === 'paid' ? 'success' : 'warning'">
              {{ plan.status === 'completed' || plan.status === 'paid' ? t('installments.completed', 'Paid') : (plan.status === 'partial' ? 'Partial' : t('installments.ongoing', 'Ongoing')) }}
            </CBadge>
          </div>
          <CProgress :value="progressPct(plan)" color="success" class="mt-2" style="height:6px;" />
          <div class="small text-muted mt-1 text-center">{{ formatAmount(plan.paid_amount_cents) }} / {{ formatAmount(plan.installment_amount_cents) }}</div>
          <div class="mt-2 d-flex justify-content-between align-items-center">
            <span class="small fw-semibold text-danger">Due: {{ formatAmount(plan.installment_amount_cents - (plan.paid_amount_cents || 0)) }}</span>
            <CButton v-if="plan.status !== 'completed' && plan.status !== 'paid'" size="sm" color="primary"
                     @click="recordPayment(plan)" style="min-height:44px;">
              {{ t('installments.recordPayment') }}i 
            </CButton>
          </div>
        </div>
      </div>

      <!-- Pagination (mobile) -->
      <div v-if="meta.last_page > 1" class="d-md-none d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block">
        <!-- Filter bar -->
        <CCardBody class="p-2 border-bottom">
          <div class="d-flex align-items-center gap-2 flex-nowrap overflow-auto">
            <CFormSelect v-model="filters.status" @update:modelValue="page = 1; load()" size="sm" style="min-width:130px; flex:1;">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option value="active">{{ t('installments.ongoing') }}</option>
              <option value="completed">{{ t('installments.completed') }}</option>
            </CFormSelect>
            <CFormInput v-model="filters.search" :placeholder="t('installments.searchPlaceholder')" @input="debouncedLoad" size="sm" style="min-width:180px; flex:2;" />
            <CButton color="primary" size="sm" @click="showBulkModal = true" style="white-space:nowrap; flex-shrink:0;">📋 {{ t('installments.bulkByClass') }}</CButton>
          </div>
        </CCardBody>
        <CCardBody class="p-0">
          <CTable hover class="mb-0" style="width:100%;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.student') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.invoice') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.totalInst') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.paid', 'Paid') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.amountPerInst', 'Amount/Installment') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.nextDue') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('installments.progress') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap;">{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell style="white-space:nowrap; width:56px;">{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="plan in installments" :key="plan.id">
                <CTableDataCell>
                  <div class="fw-semibold" style="white-space:nowrap;">{{ plan.student?.full_name }}</div>
                  <div class="small text-muted">{{ plan.student?.admission_number }}</div>
                </CTableDataCell>
                <CTableDataCell style="white-space:nowrap;">{{ plan.invoice?.invoice_number }}</CTableDataCell>
                <CTableDataCell style="white-space:nowrap;">{{ plan.installments_paid || 0 }} / {{ plan.total_installments }}</CTableDataCell>
                <CTableDataCell style="white-space:nowrap;">{{ formatAmount(plan.paid_amount_cents || 0) }}</CTableDataCell>
                <CTableDataCell class="text-danger fw-semibold" style="white-space:nowrap;">{{ formatAmount(plan.installment_amount_cents - (plan.paid_amount_cents || 0)) }}</CTableDataCell>
                <CTableDataCell style="white-space:nowrap;">{{ plan.next_due_date || '—' }}</CTableDataCell>
                <CTableDataCell style="min-width:120px;">
                  <CProgress :value="progressPct(plan)" color="success" style="height:8px;" />
                  <span class="small text-muted">{{ progressPct(plan) }}%</span>
                </CTableDataCell>
                <CTableDataCell style="white-space:nowrap;">
                  <CBadge :color="plan.status === 'completed' || plan.status === 'paid' ? 'success' : 'warning'">
                    {{ plan.status === 'completed' || plan.status === 'paid' ? t('installments.completed', 'Paid') : (plan.status === 'partial' ? 'Partial' : t('installments.ongoing', 'Ongoing')) }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell style="position:relative; min-width:56px; text-align:center; white-space:nowrap;">
                  <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === plan.id ? null : plan.id">👁️</CButton>
                  <div v-if="activeRow === plan.id"
                       style="position:absolute; bottom:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:100; min-width:160px;"
                       @click.stop>
                    <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openView(plan); activeRow = null">👁️ {{ t('common.view') }}</CButton>
                    <CButton v-if="plan.status !== 'completed' && plan.status !== 'paid'" size="sm" color="primary" variant="ghost" class="text-start" @click="recordPayment(plan); activeRow = null">💳 {{ t('installments.recordPayment') }}</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!installments.length">
                <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('installments.noPlans') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Pagination (desktop) -->
      <div v-if="meta.last_page > 1" class="d-none d-md-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- View Installment Plan Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>📋 {{ t('installments.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <div class="p-3 bg-light rounded mb-4">
          <div class="fw-bold fs-5">{{ viewTarget.student?.full_name }}</div>
          <div class="text-muted small">{{ viewTarget.student?.admission_number }} · {{ viewTarget.student?.school_class?.name }}</div>
        </div>
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.invoice') }}</div>
            <div class="fw-semibold">{{ viewTarget.invoice?.invoice_number || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.status') }}</div>
            <CBadge :color="viewTarget.status === 'completed' || viewTarget.status === 'paid' ? 'success' : 'warning'" shape="rounded-pill">
              {{ viewTarget.status === 'completed' || viewTarget.status === 'paid' ? t('installments.completed', 'Paid') : (viewTarget.status === 'partial' ? 'Partial' : t('installments.ongoing', 'Ongoing')) }}
            </CBadge>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.installmentsPaidOf') }}</div>
            <div class="fw-bold">{{ viewTarget.installments_paid || 0 }} / {{ viewTarget.total_installments }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.nextDue') }}</div>
            <div>{{ viewTarget.next_due_date || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.amountPaid') }}</div>
            <div class="fw-bold text-success">{{ formatAmount(viewTarget.paid_amount_cents || 0) }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.amountRemaining') }}</div>
            <div class="fw-bold text-danger">{{ formatAmount(viewTarget.installment_amount_cents - (viewTarget.paid_amount_cents || 0)) }}</div>
          </CCol>
          <CCol xs="12">
            <div class="text-muted small fw-semibold mb-1">{{ t('installments.totalAmount') }}</div>
            <div class="fw-bold">{{ formatAmount(viewTarget.installment_amount_cents) }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton v-if="viewTarget?.status !== 'completed' && viewTarget?.status !== 'paid'" color="primary" @click="recordPayment(viewTarget); showViewModal = false" style="min-height:44px;">💳 {{ t('installments.recordPayment') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Bulk installment modal — self-contained, fetches its own schools/classes/terms -->
    <BulkInstallmentModal
      :visible="showBulkModal"
      @close="showBulkModal = false"
      @done="onBulkDone"
    />

    <!-- Payment confirm modal -->
    <CModal :visible="showPayModal" @close="closePayModal" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('installments.confirmPayment') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="selectedPlan">
        <CAlert v-if="payError" color="danger" class="py-2">{{ payError }}</CAlert>

        <!-- Student & installment info -->
        <div class="mb-3">
          <div class="fw-bold fs-6">{{ selectedPlan.student?.full_name }}</div>
          <div class="text-muted small">
            {{ t('installments.installmentN', { paid: (selectedPlan.paid_installments || 0) + 1, total: selectedPlan.total_installments }) }}
          </div>
        </div>

        <!-- Expected amount display (shows remaining balance) -->
        <div class="rounded p-3 mb-3" style="background:#f0f4ff; border-left:4px solid #6366f1">
          <div class="small text-muted mb-1">Balance to Pay for this Installment</div>
          <div class="fs-4 fw-bold text-primary">{{ formatAmount(selectedPlan.installment_amount_cents - (selectedPlan.paid_amount_cents || 0)) }}</div>
        </div>

        <!-- Customize Amount Toggle -->
        <div class="mb-2 d-flex align-items-center justify-content-between">
          <label class="form-label fw-semibold mb-0 small">Customize Amount</label>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch"
                   id="customAmountToggle" v-model="customizeAmount"
                   @change="onToggleCustomize"
                   style="width:2em; height:1.2em; cursor:pointer;" />
          </div>
        </div>

        <!-- Custom amount input (visible when toggled) -->
        <Transition name="slide-fade">
          <div v-if="customizeAmount" class="mb-1">
            <div class="d-flex align-items-center gap-2">
              <div class="flex-grow-1">
                <CFormInput
                  id="customAmountInput"
                  type="number"
                  v-model.number="customAmountTzs"
                  :min="1"
                  :max="maxCustomAmountTzs"
                  :placeholder="`Max: ${formatAmount(selectedPlan.installment_amount_cents - (selectedPlan.paid_amount_cents||0))}`"
                  @input="validateCustomAmount"
                  style="min-height:44px; font-size:1rem;"
                />
              </div>
              <span class="text-muted small" style="white-space:nowrap;">TZS</span>
            </div>
            <!-- Real-time validation feedback -->
            <div v-if="customAmountError" class="text-danger small mt-1">
              <CIcon icon="cilWarning" class="me-1" size="sm" />{{ customAmountError }}
            </div>
            <div v-else-if="customAmountTzs > 0" class="text-success small mt-1">
              ✔ Recording TZS {{ customAmountTzs.toLocaleString() }} ({{ pctOfExpected }}% of expected)
            </div>
            <div class="text-muted" style="font-size:.72rem; margin-top:3px;">
              Max allowed (Remaining Balance): {{ formatAmount(selectedPlan.installment_amount_cents - (selectedPlan.paid_amount_cents || 0)) }}
            </div>
          </div>
        </Transition>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="closePayModal" style="min-height:44px;">
          {{ t('common.close') }}
        </CButton>
        <CButton color="primary" :disabled="paying || (customizeAmount && !!customAmountError) || (customizeAmount && !customAmountTzs)"
                 @click="confirmPayment" style="min-height:44px; min-width:110px;">
          <CSpinner v-if="paying" size="sm" class="me-1" />
          {{ customizeAmount ? `Pay TZS ${(customAmountTzs||0).toLocaleString()}` : t('common.confirm') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useInstallmentsStore } from '@/stores/installments'
import BulkInstallmentModal from '@/components/BulkInstallmentModal.vue'

const { t } = useI18n()
const store = useInstallmentsStore()
const installments = ref([])
const loading = ref(false)
const activeRow = ref(null)
const showViewModal = ref(false)
const viewTarget = ref(null)
const filters = ref({ status: '', search: '' })
const page = ref(1)
const meta = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
const showBulkModal = ref(false)

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})
const showPayModal = ref(false)
const selectedPlan = ref(null)
const paying = ref(false)
const payError = ref('')
const customizeAmount = ref(false)
const customAmountTzs = ref('')
const customAmountError = ref('')
let debounceTimer = null

// Computed helpers for the custom amount input
const maxCustomAmountTzs = computed(() => {
  if (!selectedPlan.value) return 0
  const cents = (selectedPlan.value.installment_amount_cents || 0) - (selectedPlan.value.paid_amount_cents || 0)
  return Math.max(0, Math.round(cents / 100))
})
const pctOfExpected = computed(() => {
  if (!maxCustomAmountTzs.value || !customAmountTzs.value) return 0
  return Math.round((customAmountTzs.value / maxCustomAmountTzs.value) * 100)
})

function formatAmount(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}
function progressPct(plan) {
  if (!plan.installment_amount_cents) return 0
  return Math.min(100, Math.round(((plan.paid_amount_cents || 0) / plan.installment_amount_cents) * 100))
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.search) params.search = filters.value.search
    await store.fetchInstallments(params)
    installments.value = store.installments
    meta.value = store.pagination || meta.value
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 350)
}

function openView(plan) { viewTarget.value = plan; showViewModal.value = true }

function recordPayment(plan) {
  selectedPlan.value = plan
  payError.value = ''
  customizeAmount.value = false
  customAmountTzs.value = ''
  customAmountError.value = ''
  showPayModal.value = true
}

function closePayModal() {
  showPayModal.value = false
  customizeAmount.value = false
  customAmountTzs.value = ''
  customAmountError.value = ''
  payError.value = ''
}

function onToggleCustomize() {
  customAmountTzs.value = ''
  customAmountError.value = ''
}

function validateCustomAmount() {
  const max = maxCustomAmountTzs.value
  const val = customAmountTzs.value
  if (!val && val !== 0) {
    customAmountError.value = 'Please enter an amount.'
  } else if (val <= 0) {
    customAmountError.value = 'Amount must be greater than 0.'
  } else if (val > max) {
    customAmountError.value = `Amount cannot exceed the expected ${formatAmount(selectedPlan.value?.installment_amount_cents)}.`
  } else {
    customAmountError.value = ''
  }
}

async function confirmPayment() {
  if (!selectedPlan.value) return

  // Validate custom amount if toggle is on
  if (customizeAmount.value) {
    validateCustomAmount()
    if (customAmountError.value || !customAmountTzs.value) return
  }

  paying.value = true
  payError.value = ''
  try {
    const installNum = (selectedPlan.value.paid_installments || 0) + 1
    const customCents = customizeAmount.value ? Math.round(customAmountTzs.value * 100) : null
    await store.markPaid(selectedPlan.value.id, installNum, customCents)
    closePayModal()
    await load()
  } catch (e) {
    payError.value = e?.response?.data?.message || 'Imeshindwa'
  } finally {
    paying.value = false
  }
}

function onBulkDone() {
  showBulkModal.value = false
  load()
}

function onDocClick() { activeRow.value = null }

onMounted(() => {
  document.addEventListener('click', onDocClick)
  load()
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
:deep(.card) { overflow: visible; }
/* Smooth reveal animation for the custom amount input */
.slide-fade-enter-active {
  transition: all 0.2s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.15s ease-in;
}
.slide-fade-enter-from,
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
