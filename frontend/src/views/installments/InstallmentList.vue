<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="hasFilters" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <CButton color="primary" size="sm" @click="showBulkModal = true">📋 {{ t('installments.bulkByClass') }}</CButton>
      </template>
      <template #filters>
        <CFormSelect v-model="filters.status" @update:modelValue="goPage(1)">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="active">{{ t('installments.ongoing') }}</option>
          <option value="completed">{{ t('installments.completed') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.search" class="xg-grow" :placeholder="t('installments.searchPlaceholder')" @input="debouncedLoad" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters">{{ t('common.reset') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && openView(selected)">🔍 {{ t('common.view') }}</button>
        <button class="xg-tb-btn is-ok" :disabled="!canPay(selected)" @click="canPay(selected) && recordPayment(selected)">💳 {{ t('installments.recordPayment') }}</button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="installments" :loading="loading" :empty="t('installments.noPlans')"
           :min-width="1000" :menu="menuFor" :row-class="rowClass" @open="openView">
      <template #cell-student="{ row }">
        <div class="xg-two"><span class="xg-name">{{ row.student?.full_name }}</span><span class="xg-sub">{{ row.student?.admission_number }}</span></div>
      </template>
      <template #cell-invoice="{ row }"><span class="xg-mono">{{ row.invoice?.invoice_number }}</span></template>
      <template #cell-count="{ row }">{{ row.installments_paid || 0 }} / {{ row.total_installments }}</template>
      <template #cell-paid="{ row }"><span class="xg-in">{{ formatAmount(row.paid_amount_cents || 0) }}</span></template>
      <template #cell-due="{ row }"><span class="xg-out">{{ formatAmount(row.installment_amount_cents - (row.paid_amount_cents || 0)) }}</span></template>
      <template #cell-next_due_date="{ row }"><span class="xg-mono">{{ row.next_due_date || '—' }}</span></template>
      <template #cell-progress="{ row }">
        <CProgress :value="progressPct(row)" color="success" style="height:8px;" />
      </template>
      <template #cell-status="{ row }">
        <span class="xg-pill" :class="isDone(row) ? 'xg-pill--green' : 'xg-pill--amber'">{{ isDone(row) ? t('installments.completed', 'Paid') : (row.status === 'partial' ? 'Partial' : t('installments.ongoing', 'Ongoing')) }}</span>
      </template>
    </XGrid>

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
          <div class="small text-muted mb-1">{{ t('ui.installmentList.balanceToPayForThis') }}</div>
          <div class="fs-4 fw-bold text-primary">{{ formatAmount(selectedPlan.installment_amount_cents - (selectedPlan.paid_amount_cents || 0)) }}</div>
        </div>

        <!-- Customize Amount Toggle -->
        <div class="mb-2 d-flex align-items-center justify-content-between">
          <label class="form-label fw-semibold mb-0 small">{{ t('ui.installmentList.customizeAmount') }}</label>
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
              {{ t('ui.installmentList.recordingTzs') }} {{ customAmountTzs.toLocaleString() }} ({{ pctOfExpected }}{{ t('ui.installmentList.ofExpected') }}
            </div>
            <div class="text-muted" style="font-size:.72rem; margin-top:3px;">
              {{ t('ui.installmentList.maxAllowedRemainingBalance') }} {{ formatAmount(selectedPlan.installment_amount_cents - (selectedPlan.paid_amount_cents || 0)) }}
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
import { useInstallmentsStore } from '@/stores/installments'
import BulkInstallmentModal from '@/components/BulkInstallmentModal.vue'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t } = useI18n()
const store = useInstallmentsStore()
const installments = ref([])
const loading = ref(false)
const showViewModal = ref(false)
const viewTarget = ref(null)
const filters = ref({ status: '', search: '' })
const page = ref(1)
const meta = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
const showBulkModal = ref(false)

const perPage = ref('20')
const selectedId = ref(null)
const selected = computed(() => installments.value.find(p => p.id === selectedId.value) || null)
const hasFilters = computed(() => !!(filters.value.status || filters.value.search))
const summary = computed(() => store.summary || {})
const isDone = (p) => p.status === 'completed' || p.status === 'paid'
const canPay = (p) => !!p && !isDone(p)
const rowClass = (p) => (isDone(p) ? 'xg-row--good' : '')
const columns = computed(() => [
  { key: 'student', label: t('installments.student'), w: 'minmax(170px, 2fr)' },
  { key: 'invoice', label: t('installments.invoice'), w: 'minmax(120px, 1.1fr)' },
  { key: 'count', label: t('installments.totalInst'), w: 'minmax(90px, .8fr)' },
  { key: 'paid', label: t('installments.paid', 'Paid'), w: 'minmax(110px, 1fr)', align: 'end' },
  { key: 'due', label: t('installments.amountRemaining'), w: 'minmax(110px, 1fr)', align: 'end' },
  { key: 'next_due_date', label: t('installments.nextDue'), w: 'minmax(100px, .9fr)' },
  { key: 'progress', label: t('installments.progress'), w: 'minmax(100px, 1fr)' },
  { key: 'status', label: t('common.status'), w: 'minmax(90px, .9fr)' },
])
const cards = computed(() => [
  { label: t('installments.title', 'Installments'), value: summary.value.count ?? meta.value.total, tone: 'gray' },
  { label: t('installments.ongoing'), value: summary.value.ongoing ?? 0, tone: 'amber' },
  { label: t('installments.completed'), value: summary.value.completed ?? 0, tone: 'green' },
  { label: t('installments.amountPaid'), value: formatAmount(summary.value.paid_cents), tone: 'blue' },
  { label: t('installments.amountRemaining'), value: formatAmount((summary.value.expected_cents || 0) - (summary.value.paid_cents || 0)), tone: 'red' },
])
const menuFor = (p) => [
  { label: '🔍 ' + t('common.view'), run: openView },
  ...(canPay(p) ? [{ label: '💳 ' + t('installments.recordPayment'), run: recordPayment }] : []),
]
function goPage(n) { page.value = typeof n === 'number' ? n : 1; load() }
function resetFilters() { filters.value = { status: '', search: '' }; goPage(1) }
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
    const params = { page: page.value, per_page: perPage.value }
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
    customAmountError.value = t('payments.noAmount')
  } else if (val <= 0) {
    customAmountError.value = t('ui.installmentList.amountMustBeGreaterThan')
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

onMounted(() => {
  load()
})

onUnmounted(() => clearTimeout(debounceTimer))
</script>

<style scoped>
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
