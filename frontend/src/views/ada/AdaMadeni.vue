<template>
  <CContainer fluid class="p-2 p-md-3" style="max-width:100%; padding-left:12px; padding-right:12px;">
    <!-- Summary bar — sticky -->
    <div style="position:sticky; top:0; z-index:20; background:var(--cui-body-bg, #fff); padding-bottom:0;">
    <CRow class="g-2">
      <CCol xs="6" md="3">
        <CCard class="h-100" style="border:2px solid #6c757d; background:rgba(108,117,125,0.07); box-shadow:0 8px 24px rgba(108,117,125,0.35), 0 2px 6px rgba(0,0,0,0.12);">
          <CCardBody class="p-2 p-md-3">
            <div class="text-muted small">{{ t('invoices.allInvoices') }}</div>
            <div class="fw-bold fs-5">{{ pagination.total || invoices.length }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100" style="border:2px solid #dc3545; background:rgba(220,53,69,0.07); box-shadow:0 8px 24px rgba(220,53,69,0.35), 0 2px 6px rgba(0,0,0,0.12);">
          <CCardBody class="p-2 p-md-3">
            <div class="text-muted small">{{ t('invoices.totalDebt') }}</div>
            <div class="fw-bold fs-5 text-danger">{{ formatMoney(totalOutstanding) }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100" style="border:2px solid #198754; background:rgba(25,135,84,0.07); box-shadow:0 8px 24px rgba(25,135,84,0.35), 0 2px 6px rgba(0,0,0,0.12);">
          <CCardBody class="p-2 p-md-3">
            <div class="text-muted small">{{ t('invoices.collected') }}</div>
            <div class="fw-bold fs-5 text-success">{{ formatMoney(totalCollected) }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100" style="border:2px solid #d97706; background:rgba(255,193,7,0.1); box-shadow:0 8px 24px rgba(217,119,6,0.35), 0 2px 6px rgba(0,0,0,0.12);">
          <CCardBody class="p-2 p-md-3">
            <div class="text-muted small">{{ t('invoices.promisedToPay') }}</div>
            <div class="fw-bold fs-5" style="color:#b45309;">
              <span v-if="promisesLoading" class="spinner-border spinner-border-sm"></span>
              <span v-else>{{ promisedCount.toLocaleString() }} {{ t('invoices.promises') }}</span>
            </div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <!-- Showing X-Y of Z + per-page — part of the same sticky block as the summary
         cards, not the filters bar below, so this bar's height doesn't have to be
         predicted via a CSS variable that was never actually being set. -->
    <div class="d-flex align-items-center justify-content-between gap-2 mt-2 flex-wrap">
      <small class="text-medium-emphasis text-nowrap">
        {{ t('common.showing', {
          from: (pagination.total || 0) === 0 ? 0 : ((pagination.current_page || 1) - 1) * (pagination.per_page || perPage) + 1,
          to: Math.min((pagination.current_page || 1) * (pagination.per_page || perPage), pagination.total || 0),
          total: pagination.total || 0,
        }) }}
      </small>
      <div class="d-flex align-items-center gap-2">
        <CFormSelect v-model="perPage" @update:modelValue="fetchData(1)" size="sm" style="width:80px;">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </CFormSelect>
        <small class="text-medium-emphasis text-nowrap">{{ t('common.perPage') }}</small>
      </div>
    </div>
    </div><!-- end sticky wrapper -->

    <CAlert v-if="receiptError" color="danger" dismissible class="mt-2 py-2"
            @close="receiptError = ''">
      {{ receiptError }}
    </CAlert>

    <!-- Filters + Table unified card -->
    <CCard style="margin-top:-2px; border-top-left-radius:0; border-top-right-radius:0;">
      <!-- Filters bar — no longer sticky on its own. Stacking two independent sticky
           elements (this one + the summary block above) required predicting the exact
           pixel height of the first to offset the second, which broke as soon as either
           block's content changed height — the filter bar would then cover the first
           table row(s). A single sticky block (the summary bar above) is robust; this
           one simply scrolls with the table. -->
      <CCardBody class="p-2 border-bottom">
        <div class="d-flex align-items-center gap-2 flex-nowrap overflow-auto">
          <CFormSelect v-model="filters.school_id" @update:modelValue="fetchData(1)" size="sm" style="min-width:160px; flex:2;">
            <option value="">{{ t('common.allSchools') }}</option>
            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.class_id" @update:modelValue="fetchData(1)" size="sm" style="min-width:110px; flex:1;">
            <option value="">{{ t('invoices.allClasses') }}</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.term_number" @update:modelValue="fetchData(1)" size="sm" style="min-width:100px; flex:1;">
            <option value="">{{ t('invoices.allTerms') }}</option>
            <option value="1">{{ t('invoices.term1') }}</option>
            <option value="2">{{ t('invoices.term2') }}</option>
            <option value="3">{{ t('invoices.term3') }}</option>
            <option value="4">{{ t('invoices.term4') }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.status" @update:modelValue="fetchData(1)" size="sm" style="min-width:110px; flex:1;">
            <option value="">{{ t('invoices.allStatuses') }}</option>
            <option value="unpaid">{{ t('invoices.statusFull.unpaid') }}</option>
            <option value="partial">{{ t('invoices.statusFull.partial') }}</option>
            <option value="paid">{{ t('invoices.statusFull.paid') }}</option>
          </CFormSelect>
          <CFormInput v-model="filters.search" :placeholder="t('invoices.searchStudent')" @input="debouncedFetch" size="sm" style="min-width:140px; flex:2;" />
          <CButton color="warning" size="sm" @click="showSmsModal = true" style="white-space:nowrap; flex-shrink:0;">
            <CIcon icon="cilSend" class="me-1" /> {{ t('invoices.sendSms') }}
          </CButton>
          <CButton color="success" size="sm" @click="showGenerateModal = true" style="white-space:nowrap; flex-shrink:0;">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('invoices.generate') }}
          </CButton>
        </div>
      </CCardBody>

      <!-- Loading -->
      <div v-if="loading" class="text-center py-5">
        <CSpinner color="primary" />
      </div>

      <!-- MOBILE: card list view -->
      <div v-else class="d-md-none p-2">
      <div v-if="invoices.length === 0" class="text-center text-muted py-5">
        {{ t('invoices.noInvoices') }}
      </div>
      <div v-for="inv in invoices" :key="inv.id"
           class="mb-2 p-3 rounded border"
           :class="rowBgClass(inv)">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div>
            <div class="fw-bold">{{ inv.student?.full_name }}</div>
            <div class="small text-muted">{{ inv.invoice_number }} &middot; {{ inv.term?.name }}</div>
            <div class="small text-muted">{{ inv.student?.school_class?.name }}</div>
          </div>
          <StatusBadge :status="inv.status" />
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div>
            <span class="small text-muted">{{ t('invoices.debt') }}: </span>
            <span class="fw-semibold" :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
              {{ formatMoney(inv.balance_due_cents) }}
            </span>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <CButton size="sm" color="info" variant="outline" @click="openDrawer(inv.student)"
                     style="min-height:44px; min-width:44px;">
              <CIcon icon="cilMagnifyingGlass" />
            </CButton>
            <CButton v-if="inv.status !== 'paid'" size="sm" color="primary"
                     @click="openPayment(inv)" style="min-height:44px;">
              {{ t('invoices.payNow') }}
            </CButton>
            <CButton v-for="r in receiptsFor(inv)" :key="r.id" size="sm" color="success" variant="outline"
                     @click="printReceipt(r.id)" :disabled="printingReceiptId === r.id"
                     style="min-height:44px;">
              <CSpinner v-if="printingReceiptId === r.id" size="sm" class="me-1" />
              <span v-else>🖨 </span>{{ t('payments.printReceipt') }}
              <span v-if="receiptsFor(inv).length > 1" class="small ms-1">{{ formatMoney(r.amount_cents) }}</span>
            </CButton>
          </div>
        </div>
      </div>
      </div><!-- end mobile v-else -->

      <!-- DESKTOP: full table (inside the same card) -->
      <div class="d-none d-md-block">
        <CTable responsive hover class="mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('invoices.invoiceNo') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('invoices.student') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('invoices.class') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('invoices.term') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('common.total') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('invoices.amountPaid') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('invoices.debt') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="inv in invoices" :key="inv.id" :class="rowBgClass(inv)">
              <CTableDataCell class="fw-medium">{{ inv.invoice_number }}</CTableDataCell>
              <CTableDataCell>{{ inv.student?.full_name }}</CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell">{{ inv.student?.school_class?.name }}</CTableDataCell>
              <CTableDataCell>{{ inv.term?.name }}</CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell">{{ formatMoney(inv.total_amount_cents) }}</CTableDataCell>
              <CTableDataCell>{{ formatMoney(inv.paid_cents) }}</CTableDataCell>
              <CTableDataCell class="fw-semibold" :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
                {{ formatMoney(inv.balance_due_cents) }}
              </CTableDataCell>
              <CTableDataCell><StatusBadge :status="inv.status" /></CTableDataCell>
              <CTableDataCell>
                <div class="d-flex gap-1">
                  <CButton size="sm" color="info" variant="outline" @click="openDrawer(inv.student)"
                           style="min-height:36px; min-width:36px;">
                    <CIcon icon="cilMagnifyingGlass" />
                  </CButton>
                  <CButton v-if="inv.status !== 'paid'" size="sm" color="primary"
                           @click="openPayment(inv)" style="min-height:36px;">
                    {{ t('invoices.payNow') }}
                  </CButton>
                  <!-- Any invoice with a receipted payment can be reprinted, including
                       partials — a parent who paid half still needs their receipt. -->
                  <CButton v-if="receiptsFor(inv).length === 1" size="sm" color="success" variant="outline"
                           @click="printReceipt(receiptsFor(inv)[0].id)"
                           :disabled="printingReceiptId === receiptsFor(inv)[0].id"
                           :title="t('payments.printReceipt')" style="min-height:36px; min-width:40px;">
                    <CSpinner v-if="printingReceiptId === receiptsFor(inv)[0].id" size="sm" />
                    <span v-else>🖨</span>
                  </CButton>
                  <CDropdown v-else-if="receiptsFor(inv).length > 1" variant="btn-group">
                    <CDropdownToggle size="sm" color="success" variant="outline" style="min-height:36px;">
                      🖨 {{ receiptsFor(inv).length }}
                    </CDropdownToggle>
                    <CDropdownMenu>
                      <CDropdownHeader>{{ t('payments.receipt') }}</CDropdownHeader>
                      <CDropdownItem v-for="r in receiptsFor(inv)" :key="r.id"
                                     style="cursor:pointer;" @click="printReceipt(r.id)">
                        <CSpinner v-if="printingReceiptId === r.id" size="sm" class="me-1" />
                        {{ r.receipt_number }}
                        <span class="text-muted small ms-1">{{ formatMoney(r.amount_cents) }}</span>
                      </CDropdownItem>
                    </CDropdownMenu>
                  </CDropdown>
                </div>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!loading && invoices.length === 0">
              <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('invoices.noInvoices') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </div><!-- end desktop table -->
    </CCard><!-- end unified card -->

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="d-flex justify-content-center mt-3">
      <CPagination>
        <CPaginationItem :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">
          &laquo;
        </CPaginationItem>
        <CPaginationItem
          v-for="p in pageNumbers"
          :key="p"
          :active="p === pagination.current_page"
          @click="goPage(p)">
          {{ p }}
        </CPaginationItem>
        <CPaginationItem :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">
          &raquo;
        </CPaginationItem>
      </CPagination>
    </div>

    <!-- Payment Modal -->
    <LipiaModal
      :visible="showPayModal"
      :invoice="selectedInvoice"
      @close="closePayModal"
      @paid="onPaid"
    />

    <!-- Student Drawer -->
    <MwanafunziDrawer
      v-if="drawerStudent"
      :student="drawerStudent"
      @close="drawerStudent = null"
    />

    <!-- Generate Invoice Modal -->
    <GenerateInvoiceModal
      :visible="showGenerateModal"
      @close="showGenerateModal = false"
      @generated="onGenerated"
    />

    <!-- SMS Blast Modal -->
    <SmsBlastModal
      :visible="showSmsModal"
      :student-ids="debtorIds"
      @close="showSmsModal = false"
    />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInvoicesStore } from '@/stores/invoices'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import StatusBadge           from '@/components/StatusBadge.vue'
import LipiaModal            from '@/components/LipiaModal.vue'
import MwanafunziDrawer      from '@/components/MwanafunziDrawer.vue'
import GenerateInvoiceModal  from '@/components/GenerateInvoiceModal.vue'
import SmsBlastModal         from '@/components/SmsBlastModal.vue'
import api                   from '@/services/api'
import { printReceipt as printReceiptPdf, cleanupReceiptFrame } from '@/utils/receipt'

const { t } = useI18n()
const invoicesStore = useInvoicesStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters          = ref({ school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' })
const selectedInvoice  = ref(null)
const showPayModal     = ref(false)
const drawerStudent    = ref(null)
const showGenerateModal = ref(false)
const showSmsModal     = ref(false)
const classes          = ref([])
const promisedCount    = ref(0)
const promisesLoading  = ref(false)
const perPage          = ref('20')
let   debounceTimer    = null

const invoices   = computed(() => invoicesStore.invoices)
const loading    = computed(() => invoicesStore.loading)
const pagination = computed(() => invoicesStore.pagination || {})
const schools    = computed(() => schoolsStore.schools)

watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  fetchData(1)
})

const totalOutstanding = computed(() =>
  invoices.value.reduce((s, i) => s + (i.balance_due_cents || 0), 0)
)
const totalCollected = computed(() =>
  invoices.value.reduce((s, i) => s + (i.paid_cents || 0), 0)
)
const debtorIds = computed(() =>
  invoices.value.filter(i => i.status !== 'paid').map(i => i.student?.id).filter(Boolean)
)
const pageNumbers = computed(() => {
  const total = pagination.value.last_page || 1
  const cur   = pagination.value.current_page || 1
  const pages = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(total, cur + 2); i++) pages.push(i)
  return pages
})

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

function rowBgClass(inv) {
  if (inv.status === 'paid') return 'table-success'
  if (inv.status === 'partial') return 'table-warning'
  if (inv.status === 'unpaid') return 'table-danger'
  return ''
}

async function fetchData(page) {
  const params = { page: page ?? pagination.value.current_page ?? 1, per_page: perPage.value }
  if (filters.value.school_id)   params.school_id   = filters.value.school_id
  if (filters.value.class_id)    params.school_class_id = filters.value.class_id
  if (filters.value.term_number) params.term_number = filters.value.term_number
  if (filters.value.status)      params.status      = filters.value.status
  if (filters.value.search)      params.search      = filters.value.search
  await invoicesStore.fetchInvoices(params)
}

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => fetchData(1), 350)
}

function goPage(p) {
  invoicesStore.pagination.current_page = p
  fetchData()
}

function openPayment(inv) {
  selectedInvoice.value = inv
  showPayModal.value = true
}

// ── Receipts ────────────────────────────────────────────────────────────────
// A receipt belongs to a payment, not to the invoice, so an invoice settled in
// instalments has one receipt per instalment.
function receiptsFor(inv) {
  return (inv?.payments ?? [])
    .filter(p => p.receipt?.id)
    .map(p => ({
      id: p.receipt.id,
      receipt_number: p.receipt.receipt_number,
      amount_cents: p.amount_cents,
    }))
}

const printingReceiptId = ref(null)
const receiptError = ref('')

async function printReceipt(receiptId) {
  if (!receiptId) return
  printingReceiptId.value = receiptId
  receiptError.value = ''
  try {
    await printReceiptPdf(receiptId)
  } catch (e) {
    receiptError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    printingReceiptId.value = null
  }
}

onBeforeUnmount(cleanupReceiptFrame)
function closePayModal() {
  showPayModal.value = false
  selectedInvoice.value = null
}
function openDrawer(student) { if (student) drawerStudent.value = student }

function onPaid() {
  showPayModal.value = false
  selectedInvoice.value = null
  fetchData()
}
function onGenerated() {
  showGenerateModal.value = false
  fetchData()
}

async function fetchPromisedCount() {
  promisesLoading.value = true
  try {
    const params = { per_page: 1, status: 'pending' }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    const { data } = await api.get('/payment-promises', { params })
    promisedCount.value = data.total ?? data.meta?.total ?? 0
  } catch {}
  finally { promisesLoading.value = false }
}

watch(() => filters.value.school_id, () => fetchPromisedCount())

onMounted(async () => {
  try {
    await schoolsStore.fetchSchools()
    try {
      const { data: cd } = await api.get('/school-classes')
      classes.value = cd.data || cd
    } catch {}
    await Promise.all([fetchData(), fetchPromisedCount()])
  } catch (e) {
    console.error('AdaMadeni mount error', e)
  }
})
</script>

<style scoped>
@media (max-width: 767px) {
  .btn { min-height: 44px; }
}
</style>
