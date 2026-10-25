<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <CRow class="g-3 mb-3">
      <!-- Left: Form -->
      <CCol lg="7">
        <CCard>
          <CCardHeader><strong>{{ t('payments.newPayment') }}</strong></CCardHeader>
          <CCardBody class="p-3" style="overflow:visible;">

            <!-- Step 1: Search student / invoice -->
            <div class="mb-3" style="position:relative;">
              <label class="form-label fw-semibold">
                {{ t('payments.searchStudent') }} <span class="text-danger">*</span>
              </label>
              <CFormInput
                v-model="searchQuery"
                :placeholder="t('payments.searchPlaceholder')"
                @input="debouncedSearch"
                :disabled="!!resolvedInvoice"
                autocomplete="off"
              />
              <!-- Dropdown results -->
              <div v-if="searchResults.length && !resolvedInvoice"
                   class="border rounded bg-white"
                   style="position:absolute; z-index:1055; width:100%; top:100%; left:0; max-height:240px; overflow-y:auto; box-shadow:0 4px 14px rgba(0,0,0,.12);">
                <div v-for="inv in searchResults" :key="inv.id"
                     class="p-2 border-bottom d-flex justify-content-between align-items-center"
                     style="cursor:pointer;"
                     @click="selectInvoice(inv)">
                  <div>
                    <div class="fw-semibold">{{ inv.student?.full_name }}</div>
                    <div class="small text-muted">
                      {{ inv.invoice_number }} &nbsp;·&nbsp;
                      {{ inv.student?.admission_number }} &nbsp;·&nbsp;
                      {{ inv.student?.school_class?.name }}
                    </div>
                  </div>
                  <div class="text-end ms-3 flex-shrink-0">
                    <div class="fw-bold text-danger small">{{ formatMoney(inv.balance_due_cents) }}</div>
                    <div class="text-muted" style="font-size:.7rem;">{{ t('payments.balance') }}</div>
                  </div>
                </div>
              </div>
              <div v-if="searching" class="text-muted small mt-1">
                <CSpinner size="sm" class="me-1" />{{ t('common.loading') }}…
              </div>
            </div>

            <!-- Selected invoice card -->
            <div v-if="resolvedInvoice" class="rounded border p-3 mb-3 d-flex justify-content-between align-items-start"
                 style="background:var(--cui-tertiary-bg, #f8f9fa);">
              <div>
                <div class="fw-bold">{{ resolvedInvoice.student?.full_name }}</div>
                <div class="small text-muted">
                  {{ resolvedInvoice.invoice_number }} &nbsp;·&nbsp;
                  {{ resolvedInvoice.student?.admission_number }}
                </div>
                <div class="mt-1 d-flex gap-3">
                  <span class="small">{{ t('payments.totalFee') }}: <strong>{{ formatMoney(resolvedInvoice.total_amount_cents) }}</strong></span>
                  <span class="small">{{ t('payments.paid') }}: <strong class="text-success">{{ formatMoney(resolvedInvoice.paid_cents) }}</strong></span>
                  <span class="small">{{ t('payments.balance') }}: <strong class="text-danger">{{ formatMoney(resolvedInvoice.balance_due_cents) }}</strong></span>
                </div>
              </div>
              <CButton size="sm" color="secondary" variant="ghost" @click="clearInvoice" class="ms-2 p-1">✕</CButton>
            </div>

            <!-- Payment fields — two columns -->
            <CRow class="g-3">
              <CCol sm="6">
                <label class="form-label fw-semibold">{{ t('payments.amount') }} (TZS) <span class="text-danger">*</span></label>
                <CFormInput
                  type="number" v-model.number="form.amount_tzs"
                  min="1" :max="resolvedInvoice ? Math.round((resolvedInvoice.balance_due_cents||0)/100) : undefined"
                  placeholder="e.g. 50000"
                  :disabled="saving || !resolvedInvoice"
                />
                <div v-if="resolvedInvoice" class="text-muted small mt-1">
                  {{ t('payments.maxAmount') }}: {{ formatMoney(resolvedInvoice.balance_due_cents) }}
                </div>
              </CCol>

              <CCol sm="6">
                <label class="form-label fw-semibold">{{ t('payments.method') }}</label>
                <CFormSelect v-model="form.method" :disabled="saving || !resolvedInvoice">
                  <option value="cash">{{ t('payments.methods.cash') }}</option>
                  <option value="mpesa">{{ t('payments.methods.mpesa') }}</option>
                  <option value="bank">{{ t('payments.methods.bank') }}</option>
                  <option value="cheque">{{ t('payments.methods.cheque') }}</option>
                </CFormSelect>
              </CCol>

              <CCol sm="6" v-if="form.method !== 'cash'">
                <label class="form-label fw-semibold">{{ t('payments.reference') }} <span class="text-danger">*</span></label>
                <CFormInput v-model="form.reference_number"
                  :placeholder="form.method === 'mpesa' ? 'e.g. QHX123456' : 'e.g. BENKI-0001234'"
                  :disabled="saving" />
              </CCol>

              <CCol sm="6">
                <label class="form-label fw-semibold">{{ t('payments.paidAt') }}</label>
                <CFormInput type="date" v-model="form.paid_at" :disabled="saving" />
              </CCol>

              <CCol sm="12">
                <label class="form-label fw-semibold">{{ t('payments.notes') }}</label>
                <CFormTextarea v-model="form.notes" rows="2" :disabled="saving" />
              </CCol>

              <CCol sm="12">
                <CAlert v-if="formError" color="danger" class="py-2 mb-2">{{ formError }}</CAlert>
                <CButton color="primary" :disabled="saving || !resolvedInvoice || !form.amount_tzs"
                         class="w-100" style="min-height:44px;" @click="submitPayment">
                  <CSpinner v-if="saving" size="sm" class="me-2" />
                  {{ saving ? t('payments.saving') : t('payments.submit') }}
                </CButton>
              </CCol>
            </CRow>

          </CCardBody>
        </CCard>
      </CCol>

      <!-- Right: Payment confirmation + Student details -->
      <CCol lg="5">
        <div v-if="lastPayment">
          <!-- Receipt header -->
          <CCard class="border-success mb-3">
            <CCardHeader class="bg-success text-white d-flex justify-content-between align-items-center">
              <strong>✔ {{ t('payments.receipt') }}</strong>
              <span class="small fw-bold">{{ lastPayment.receipt?.receipt_number }}</span>
            </CCardHeader>
            <CCardBody class="p-0">
              <!-- Student info -->
              <div class="px-3 pt-3 pb-2 border-bottom">
                <div class="d-flex align-items-center gap-2 mb-2">
                  <span class="badge bg-primary-subtle text-primary" style="font-size:.7rem;">{{ t('payments.student') }}</span>
                </div>
                <div class="fw-bold fs-6">{{ lastPayment.student?.full_name || lastPayment.student_name }}</div>
                <div class="small text-muted mt-1 d-flex flex-wrap gap-3">
                  <span v-if="lastPayment.student?.admission_number">
                    Namba: <strong>{{ lastPayment.student.admission_number }}</strong>
                  </span>
                  <span v-if="lastPayment.student?.school_class?.name">
                    Darasa: <strong>{{ lastPayment.student.school_class.name }}</strong>
                  </span>
                  <span v-if="lastPayment.student?.school?.name">
                    Shule: <strong>{{ lastPayment.student.school.name }}</strong>
                  </span>
                </div>
              </div>

              <!-- Invoice details -->
              <div class="px-3 py-2 border-bottom">
                <div class="mb-1">
                  <span class="badge bg-secondary-subtle text-secondary" style="font-size:.7rem;">{{ t('nav.invoices') }}</span>
                </div>
                <div class="row g-1 small">
                  <div class="col-6 text-muted">{{ t('orphanInvoices.invoiceNo') }}</div>
                  <div class="col-6 fw-semibold text-end">{{ lastPayment.invoice?.invoice_number }}</div>

                  <div class="col-6 text-muted">{{ t('nav.terms') }}</div>
                  <div class="col-6 fw-semibold text-end">{{ lastPayment.invoice?.term || '—' }}</div>

                  <div v-if="lastPayment.invoice?.academic_year" class="col-6 text-muted">{{ t('common.year') }}</div>
                  <div v-if="lastPayment.invoice?.academic_year" class="col-6 fw-semibold text-end">{{ lastPayment.invoice.academic_year }}</div>

                  <div class="col-6 text-muted">{{ t('ui.rekodaMalipo.totalFee') }}</div>
                  <div class="col-6 fw-semibold text-end">{{ formatMoney(lastPayment.invoice?.total_amount_cents) }}</div>

                  <div class="col-6 text-muted">{{ t('ui.rekodaMalipo.previouslyPaid') }}</div>
                  <div class="col-6 fw-semibold text-success text-end">{{ formatMoney((lastPayment.invoice?.paid_cents || 0) - lastPayment.amount_cents) }}</div>

                  <div class="col-6 text-muted">{{ t('ui.rekodaMalipo.remainingBalance') }}</div>
                  <div class="col-6 fw-bold text-end" :class="(lastPayment.invoice?.balance_due_cents || 0) <= 0 ? 'text-success' : 'text-danger'">
                    {{ formatMoney(lastPayment.invoice?.balance_due_cents) }}
                  </div>
                </div>
              </div>

              <!-- Payment details -->
              <div class="px-3 py-2 border-bottom">
                <div class="mb-1">
                  <span class="badge bg-success-subtle text-success" style="font-size:.7rem;">{{ t('ui.rekodaMalipo.currentPayment') }}</span>
                </div>
                <div class="row g-1 small">
                  <div class="col-6 text-muted">{{ t('common.amount') }}</div>
                  <div class="col-6 fw-bold text-success text-end fs-6">{{ formatMoney(lastPayment.amount_cents) }}</div>

                  <div class="col-6 text-muted">{{ t('common.method') }}</div>
                  <div class="col-6 fw-semibold text-end">{{ methodMap[lastPayment.method] || lastPayment.method }}</div>

                  <template v-if="lastPayment.reference_number">
                    <div class="col-6 text-muted">{{ t('ui.rekodaMalipo.refNo') }}</div>
                    <div class="col-6 fw-semibold text-end">{{ lastPayment.reference_number }}</div>
                  </template>

                  <div class="col-6 text-muted">{{ t('common.date') }}</div>
                  <div class="col-6 fw-semibold text-end">
                    {{ lastPayment.paid_at ? new Date(lastPayment.paid_at).toLocaleDateString('sw-TZ') : '—' }}
                  </div>

                  <div class="col-6 text-muted">{{ t('payments.receiptNo') }}</div>
                  <div class="col-6 fw-semibold text-end text-primary">{{ lastPayment.receipt?.receipt_number || '—' }}</div>
                </div>
              </div>

              <!-- Guardians -->
              <div v-if="lastPayment.student?.guardians?.length" class="px-3 py-2">
                <div class="mb-1">
                  <span class="badge bg-info-subtle text-info" style="font-size:.7rem;">{{ t('nav.guardians') }}</span>
                </div>
                <div v-for="g in lastPayment.student.guardians" :key="g.phone" class="small d-flex justify-content-between">
                  <span class="text-muted">{{ g.name }}<span v-if="g.relationship"> ({{ g.relationship }})</span></span>
                  <span class="fw-semibold">{{ g.phone }}</span>
                </div>
              </div>

              <div v-if="lastPayment.notes" class="px-3 pb-3 small text-muted fst-italic">
                {{ lastPayment.notes }}
              </div>
            </CCardBody>
          </CCard>
        </div>
        <div v-else class="text-center text-muted py-5">
          <div style="font-size:2.5rem;">💳</div>
          <div class="mt-2 small">{{ t('payments.receiptHint') }}</div>
        </div>
      </CCol>
    </CRow>

    <!-- Payment history -->
    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="hasFilters" @page="goPage" @update:per-page="goPage(1)">
      <template #filters>
        <CFormSelect v-model="filters.method" @update:modelValue="goPage(1)">
          <option value="">{{ t('payments.method') }}</option>
          <option v-for="(label, key) in methodMap" :key="key" :value="key">{{ label }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.date_from" type="date" @update:modelValue="goPage(1)" />
        <CFormInput v-model="filters.date_to" type="date" @update:modelValue="goPage(1)" />
        <CFormInput v-model="filters.search" class="xg-grow" :placeholder="t('payments.searchPlaceholder')" @input="debouncedHistory" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters">{{ t('common.reset') }}</CButton>
      </template>
      <template #toolbar><strong class="small">{{ t('nav.payments') }}</strong></template>
    </XBar>
    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="history" :loading="historyLoading" :min-width="900" :row-class="() => 'xg-row--good'">
      <template #cell-paid_at="{ row }"><span class="xg-mono">{{ row.paid_at ? new Date(row.paid_at).toLocaleDateString('en-GB') : '—' }}</span></template>
      <template #cell-student="{ row }">
        <div class="xg-two"><span class="xg-name">{{ row.student?.full_name || '—' }}</span><span class="xg-sub">{{ row.student?.admission_number }} {{ row.student?.school_class?.name }}</span></div>
      </template>
      <template #cell-invoice="{ row }"><span class="xg-mono">{{ row.invoice?.invoice_number || '—' }}</span></template>
      <template #cell-method="{ row }"><span class="xg-pill xg-pill--blue">{{ methodMap[row.method] || row.method_label || row.method }}</span></template>
      <template #cell-receipt="{ row }"><span class="xg-mono">{{ row.receipt?.receipt_number || '—' }}</span></template>
      <template #cell-amount_cents="{ row }"><span class="xg-in">{{ formatMoney(row.amount_cents) }}</span></template>
    </XGrid>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePaymentsStore } from '@/stores/payments'
import api from '@/services/api'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t } = useI18n()
const paymentsStore = usePaymentsStore()

const today = new Date().toISOString().slice(0, 10)

const searchQuery    = ref('')
const searchResults  = ref([])
const searching      = ref(false)
const resolvedInvoice = ref(null)
const lastPayment    = ref(null)
const saving         = ref(false)
const formError      = ref('')

const form = ref({ amount_tzs: '', method: 'cash', reference_number: '', notes: '', paid_at: today })
const amountPlaceholder = ref('e.g. 50000')

const methodMap = { cash: 'Cash', mpesa: 'M-Pesa', bank: 'Bank Transfer', cheque: 'Cheque' }

function formatMoney(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  searchResults.value = []
  if (!searchQuery.value.trim()) return
  searchTimer = setTimeout(doSearch, 350)
}

async function doSearch() {
  searching.value = true
  try {
    const { data } = await api.get('/invoices', {
      params: { search: searchQuery.value, status: 'partial,unpaid', per_page: 15, with: 'student.schoolClass' }
    })
    searchResults.value = (data.data || []).filter(inv => (inv.balance_due_cents || 0) > 0)
  } catch { searchResults.value = [] }
  finally { searching.value = false }
}

function selectInvoice(inv) {
  resolvedInvoice.value = inv
  searchQuery.value = inv.student?.full_name || inv.invoice_number
  searchResults.value = []
  // Show balance as hint via placeholder but don't prefill — user must enter amount explicitly
  form.value.amount_tzs = ''
  // Focus the amount input on next tick
  const maxTzs = Math.round((inv.balance_due_cents || 0) / 100)
  amountPlaceholder.value = `Max: ${maxTzs.toLocaleString()} TZS`
}

function clearInvoice() {
  resolvedInvoice.value = null
  searchQuery.value = ''
  searchResults.value = []
  form.value = { amount_tzs: '', method: 'cash', reference_number: '', notes: '', paid_at: today }
  formError.value = ''
}

async function submitPayment() {
  formError.value = ''
  if (!resolvedInvoice.value) { formError.value = t('payments.noInvoice'); return }
  if (!form.value.amount_tzs || form.value.amount_tzs <= 0) { formError.value = t('payments.noAmount'); return }
  if (form.value.method !== 'cash' && !form.value.reference_number.trim()) {
    formError.value = t('payments.noRef'); return
  }
  saving.value = true
  try {
    const payload = {
      invoice_id:       resolvedInvoice.value.id,
      amount_cents:     Math.round(form.value.amount_tzs * 100),
      method:           form.value.method,
      reference_number: form.value.reference_number || undefined,
      paid_at:          form.value.paid_at,
      notes:            form.value.notes || undefined,
    }
    const studentName = resolvedInvoice.value.student?.full_name || '—'
    const payment = await paymentsStore.recordPayment(payload)
    
    lastPayment.value = {
      ...payment,
      student_name: studentName,
      amount_cents: payment?.amount_cents || payload.amount_cents,
      method: payment?.method || payload.method,
      paid_at: payment?.paid_at || payload.paid_at,
    }
    clearInvoice()
    loadHistory()
  } catch (e) {
    formError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

// ── payment history (the rows under the form) ──
const history = ref([])
const historyLoading = ref(false)
const historySummary = ref({})
const meta = ref({})
const perPage = ref('20')
const page = ref(1)
const selectedId = ref(null)
const filters = ref({ method: '', date_from: '', date_to: '', search: '' })
const hasFilters = computed(() => Object.values(filters.value).some(Boolean))
const columns = computed(() => [
  { key: 'paid_at', label: t('common.date'), w: 'minmax(100px, .9fr)' },
  { key: 'student', label: t('payments.student'), w: 'minmax(180px, 2fr)' },
  { key: 'invoice', label: t('orphanInvoices.invoiceNo'), w: 'minmax(120px, 1.1fr)' },
  { key: 'method', label: t('payments.method'), w: 'minmax(100px, .9fr)' },
  { key: 'reference_number', label: t('payments.reference'), w: 'minmax(110px, 1fr)' },
  { key: 'receipt', label: t('payments.receiptNo'), w: 'minmax(110px, 1fr)' },
  { key: 'amount_cents', label: t('common.amount'), w: 'minmax(120px, 1.1fr)', align: 'end' },
])
const cards = computed(() => [
  { label: t('nav.payments'), value: (historySummary.value.count ?? 0).toLocaleString(), tone: 'gray' },
  { label: t('expensex.today'), value: formatMoney(historySummary.value.today_cents), tone: 'green' },
  { label: t('expensex.cards.month'), value: formatMoney(historySummary.value.month_cents), tone: 'blue' },
  { label: t('common.total'), value: formatMoney(historySummary.value.total_cents), tone: 'amber' },
])
async function loadHistory() {
  historyLoading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    for (const [k, v] of Object.entries(filters.value)) if (v) params[k] = v
    const { data } = await api.get('/payments', { params })
    history.value = data.data || []
    meta.value = data.meta || {}
    historySummary.value = data.summary || {}
  } catch { history.value = [] } finally { historyLoading.value = false }
}
function goPage(p) { page.value = typeof p === 'number' ? p : 1; loadHistory() }
function resetFilters() { filters.value = { method: '', date_from: '', date_to: '', search: '' }; goPage(1) }
let historyTimer = null
function debouncedHistory() { clearTimeout(historyTimer); historyTimer = setTimeout(() => goPage(1), 350) }
onMounted(loadHistory)
</script>

