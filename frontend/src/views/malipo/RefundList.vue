<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Header -->
    <CRow class="align-items-center mb-3 g-2">
      <CCol>
        <h5 class="fw-bold mb-0">{{ t('refunds.title') }}</h5>
        <p class="text-muted small mb-0">{{ t('refunds.subtitle') }}</p>
      </CCol>
      <CCol xs="auto">
        <CButton color="primary" size="sm" @click="openAddModal" style="min-height:44px;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('refunds.add') }}
        </CButton>
      </CCol>
    </CRow>

    <!-- Filters -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2">
          <CCol xs="12" sm="6" md="4">
            <CFormInput v-model="filters.search" :placeholder="t('refunds.searchPlaceholder')" size="sm" @input="debouncedLoad" />
          </CCol>
          <CCol xs="6" sm="3" md="2">
            <CFormInput v-model="filters.date_from" type="date" size="sm" @update:modelValue="page = 1; loadData()" />
          </CCol>
          <CCol xs="6" sm="3" md="2">
            <CFormInput v-model="filters.date_to" type="date" size="sm" @update:modelValue="page = 1; loadData()" />
          </CCol>
          <CCol xs="6" sm="3" md="2">
            <CButton size="sm" color="secondary" variant="outline" class="w-100" @click="resetFilters">{{ t('refunds.resetFilters') }}</CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <!-- Loading / Error -->
    <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <CAlert v-else-if="store.error" color="danger">{{ store.error }}</CAlert>

    <!-- Desktop Table -->
    <CCard v-else class="d-none d-md-block">
      <CCardBody class="p-0">
        <CTable responsive hover class="mb-0 align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('common.student') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('refunds.invoiceNo') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.reason') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.method') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.amount') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
              <CTableHeaderCell></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="r in store.refunds" :key="r.id">
              <CTableDataCell>
                <div class="fw-semibold">{{ r.student?.full_name || r.invoice?.student?.full_name }}</div>
                <div class="text-muted small">{{ r.student?.admission_number || r.invoice?.student?.admission_number }}</div>
              </CTableDataCell>
              <CTableDataCell class="small">{{ r.invoice?.invoice_number || '—' }}</CTableDataCell>
              <CTableDataCell class="small">{{ r.reason }}</CTableDataCell>
              <CTableDataCell><CBadge color="info" shape="rounded-pill">{{ methodLabel(r.method) }}</CBadge></CTableDataCell>
              <CTableDataCell class="fw-bold text-warning">{{ formatTZS(r.amount_cents) }}</CTableDataCell>
              <CTableDataCell class="small">{{ r.refunded_at?.slice(0,10) }}</CTableDataCell>
              <CTableDataCell>
                <CButton size="sm" color="danger" variant="ghost" @click="confirmDelete(r)" :title="t('common.delete')">
                  <CIcon icon="cilTrash" />
                </CButton>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!store.refunds.length">
              <CTableDataCell colspan="7" class="text-center text-muted py-5">{{ t('refunds.noRefunds') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
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

    <!-- Mobile Cards -->
    <div v-if="!store.loading && !store.error" class="d-md-none">
      <div v-if="!store.refunds.length" class="text-center text-muted py-5">{{ t('refunds.noRefunds') }}</div>
      <div v-for="r in store.refunds" :key="r.id" class="mb-2">
        <CCard>
          <CCardBody class="p-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">{{ r.student?.full_name || r.invoice?.student?.full_name }}</div>
                <div class="text-muted small">{{ r.invoice?.invoice_number }}</div>
              </div>
              <div class="fw-bold text-warning fs-5">{{ formatTZS(r.amount_cents) }}</div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div>
                <CBadge color="info" shape="rounded-pill" class="me-2">{{ methodLabel(r.method) }}</CBadge>
                <span class="text-muted small">{{ r.refunded_at?.slice(0,10) }}</span>
              </div>
              <CButton size="sm" color="danger" variant="ghost" @click="confirmDelete(r)">
                <CIcon icon="cilTrash" />
              </CButton>
            </div>
            <div v-if="r.reason" class="text-muted small mt-1">{{ r.reason }}</div>
          </CCardBody>
        </CCard>
      </div>
    </div>

    <!-- Add Refund Modal -->
    <CModal :visible="showAddModal" @close="showAddModal=false" size="lg" class="modal-fullscreen-sm-down" backdrop="static">
      <CModalHeader><CModalTitle>{{ t('refunds.addTitle') }}</CModalTitle></CModalHeader>
      <CModalBody class="p-3">
        <CRow class="g-3">
          <!-- Left column -->
          <CCol xs="12" md="6">
            <!-- Invoice search -->
            <div class="mb-3" style="position:relative;">
              <label class="form-label fw-semibold">{{ t('refunds.searchInvoice') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="invoiceSearch" :placeholder="t('refunds.invoicePlaceholder')" @input="debouncedInvoiceSearch" />
              <div v-if="invoiceResults.length" class="border rounded mt-1 bg-white"
                   style="position:absolute; z-index:1055; width:100%; max-height:200px; overflow-y:auto; top:100%; left:0; box-shadow:0 4px 12px rgba(0,0,0,.12);">
                <div v-for="inv in invoiceResults" :key="inv.id"
                     class="p-2 border-bottom" style="cursor:pointer;"
                     @click="selectInvoice(inv)">
                  <div class="fw-semibold">{{ inv.student?.full_name }}</div>
                  <div class="small text-muted">{{ inv.invoice_number }} — {{ t('common.balance') }}: {{ formatTZS(inv.balance_due_cents) }}</div>
                </div>
              </div>
            </div>

            <!-- Selected invoice chip -->
            <div v-if="addForm.invoice_id" class="p-2 rounded mb-3 border bg-light">
              <div class="fw-bold small">{{ selectedInv?.student?.full_name }}</div>
              <div class="small text-muted">{{ selectedInv?.invoice_number }} — {{ t('refunds.amountPaid') }}: {{ formatTZS(selectedInv?.paid_cents) }}</div>
            </div>

            <!-- Amount -->
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ t('refunds.amountLabel') }} <span class="text-danger">*</span></label>
              <CFormInput type="number" v-model.number="addForm.amount" min="1" placeholder="e.g. 20000" />
            </div>
          </CCol>

          <!-- Right column -->
          <CCol xs="12" md="6">
            <!-- Reason -->
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ t('refunds.reasonLabel') }} <span class="text-danger">*</span></label>
              <CFormTextarea v-model="addForm.reason" :placeholder="t('refunds.reasonPlaceholder')" rows="3" />
            </div>

            <!-- Return method -->
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ t('refunds.returnMethod') }}</label>
              <CFormSelect v-model="addForm.method">
                <option value="cash">{{ t('refunds.methods.cash') }}</option>
                <option value="mpesa">{{ t('refunds.methods.mpesa') }}</option>
                <option value="bank">{{ t('refunds.methods.bank') }}</option>
              </CFormSelect>
            </div>

            <!-- Date -->
            <div class="mb-3">
              <label class="form-label fw-semibold">{{ t('refunds.dateLabel') }}</label>
              <CFormInput type="date" v-model="addForm.refunded_at" />
            </div>
          </CCol>
        </CRow>

        <CAlert v-if="addError" color="danger" class="mb-0">{{ addError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showAddModal=false">{{ t('refunds.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="submitRefund" style="min-height:44px; min-width:100px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('refunds.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal=false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('refunds.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('refunds.confirmDeleteMsg') }}</CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDeleteModal=false">{{ t('refunds.no') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('refunds.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useRefundsStore } from '@/stores/refunds'
import api from '@/services/api'

const { t } = useI18n()

const store = useRefundsStore()
const filters = ref({ search: '', date_from: '', date_to: '' })
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
const showAddModal = ref(false)
const showDeleteModal = ref(false)
const saving = ref(false)
const addError = ref('')
const deleteTarget = ref(null)
const invoiceSearch = ref('')
const invoiceResults = ref([])
const selectedInv = ref(null)
let debouncer = null
let invDebouncer = null

const today = new Date().toISOString().slice(0, 10)
const addForm = ref({ invoice_id: '', amount: '', reason: '', method: 'cash', refunded_at: today })

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}
function methodLabel(m) {
  const map = { cash: 'refunds.methods.cash', mpesa: 'refunds.methods.mpesa', bank: 'refunds.methods.bank' }
  return map[m] ? t(map[m]) : (m || '—')
}

async function loadData() {
  const params = { page: page.value }
  if (filters.value.search) params.search = filters.value.search
  if (filters.value.date_from) params.date_from = filters.value.date_from
  if (filters.value.date_to) params.date_to = filters.value.date_to
  await store.fetchRefunds(params)
  meta.value = store.pagination || meta.value
}

function debouncedLoad() { clearTimeout(debouncer); debouncer = setTimeout(() => { page.value = 1; loadData() }, 350) }

function resetFilters() {
  filters.value = { search: '', date_from: '', date_to: '' }
  page.value = 1
  loadData()
}

function openAddModal() {
  addForm.value = { invoice_id: '', amount: '', reason: '', method: 'cash', refunded_at: today }
  invoiceSearch.value = ''
  invoiceResults.value = []
  selectedInv.value = null
  addError.value = ''
  showAddModal.value = true
}

function debouncedInvoiceSearch() {
  clearTimeout(invDebouncer)
  invDebouncer = setTimeout(async () => {
    if (!invoiceSearch.value.trim()) { invoiceResults.value = []; return }
    try {
      const { data } = await api.get('/invoices', { params: { search: invoiceSearch.value, status: 'partial,paid' } })
      invoiceResults.value = data.data || data
    } catch { invoiceResults.value = [] }
  }, 400)
}

function selectInvoice(inv) {
  selectedInv.value = inv
  addForm.value.invoice_id = inv.id
  invoiceSearch.value = inv.invoice_number + ' — ' + inv.student?.full_name
  invoiceResults.value = []
  addForm.value.amount = Math.round((inv.paid_cents || 0) / 100)
}

async function submitRefund() {
  addError.value = ''
  if (!addForm.value.invoice_id) { addError.value = t('refunds.errorNoInvoice'); return }
  if (!addForm.value.amount || addForm.value.amount <= 0) { addError.value = t('refunds.errorNoAmount'); return }
  if (!addForm.value.reason.trim()) { addError.value = t('refunds.errorNoReason'); return }
  saving.value = true
  try {
    await store.createRefund({
      invoice_id: addForm.value.invoice_id,
      amount_cents: Math.round(addForm.value.amount * 100),
      reason: addForm.value.reason,
      method: addForm.value.method,
      refunded_at: addForm.value.refunded_at,
    })
    showAddModal.value = false
    loadData()
  } catch (e) {
    addError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(r) { deleteTarget.value = r; showDeleteModal.value = true }

async function doDelete() {
  saving.value = true
  try {
    await store.deleteRefund(deleteTarget.value.id)
    showDeleteModal.value = false
    loadData()
  } catch { } finally { saving.value = false }
}

onMounted(async () => { try { await loadData() } catch {} })
</script>
