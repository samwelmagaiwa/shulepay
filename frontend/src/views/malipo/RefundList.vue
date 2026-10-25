<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />
    <CAlert v-if="store.error" color="danger" class="xg-alert">{{ store.error }}</CAlert>

    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="hasFilters" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <CButton color="success" size="sm" @click="openAddModal"><CIcon icon="cilPlus" class="me-1" /> {{ t('refunds.add') }}</CButton>
      </template>
      <template #filters>
        <CFormInput v-model="filters.search" class="xg-grow" :placeholder="t('refunds.searchPlaceholder')" @input="debouncedLoad" />
        <CFormInput v-model="filters.date_from" type="date" @update:modelValue="goPage(1)" />
        <CFormInput v-model="filters.date_to" type="date" @update:modelValue="goPage(1)" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters">{{ t('refunds.resetFilters') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && openView(selected)">🔍 {{ t('common.view') }}</button>
        <button v-if="auth.isOwner" class="xg-tb-btn is-danger" :disabled="!selected" @click="selected && confirmDelete(selected)">🗑️ {{ t('common.delete') }}</button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="store.refunds" :loading="store.loading" :empty="t('refunds.noRefunds')"
           :min-width="900" :menu="menuFor" row-class="xg-row--warn" @open="openView">
      <template #cell-refunded_at="{ row }"><span class="xg-mono">{{ row.refunded_at?.slice(0, 10) }}</span></template>
      <template #cell-student="{ row }"><span class="xg-name">{{ row.student?.full_name || '—' }}</span></template>
      <template #cell-invoice_number="{ row }"><span class="xg-mono">{{ row.invoice_number || '—' }}</span></template>
      <template #cell-method="{ row }"><span class="xg-pill xg-pill--blue">{{ methodLabel(row.method) }}</span></template>
      <template #cell-amount_cents="{ row }"><span class="xg-out">{{ formatTZS(row.amount_cents) }}</span></template>
    </XGrid>

    <!-- View Refund Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>💸 {{ t('refunds.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.student') }}</div>
            <div class="fw-bold">{{ viewTarget.student?.full_name || viewTarget.invoice?.student?.full_name || '—' }}</div>
            <div class="text-muted small">{{ viewTarget.student?.admission_number || viewTarget.invoice?.student?.admission_number }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('refunds.invoiceNo') }}</div>
            <div>{{ viewTarget.invoice?.invoice_number || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.amount') }}</div>
            <div class="fw-bold fs-5 text-warning">{{ formatTZS(viewTarget.amount_cents) }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.method') }}</div>
            <CBadge color="info" shape="rounded-pill">{{ methodLabel(viewTarget.method) }}</CBadge>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.date') }}</div>
            <div>{{ viewTarget.refunded_at?.slice(0,10) || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.reason') }}</div>
            <div>{{ viewTarget.reason || '—' }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
      </CModalFooter>
    </CModal>

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
import { useRefundsStore } from '@/stores/refunds'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t } = useI18n()

const store = useRefundsStore()
const auth = useAuthStore()
const showViewModal = ref(false)
const viewTarget = ref(null)
const filters = ref({ search: '', date_from: '', date_to: '' })
const page = ref(1)
const meta = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })

const perPage = ref('20')
const selectedId = ref(null)
const selected = computed(() => store.refunds.find(r => r.id === selectedId.value) || null)
const hasFilters = computed(() => Object.values(filters.value).some(Boolean))
const summary = computed(() => store.summary || {})
const columns = computed(() => [
  { key: 'refunded_at', label: t('common.date'), w: 'minmax(100px, .9fr)' },
  { key: 'student', label: t('common.student'), w: 'minmax(170px, 2fr)' },
  { key: 'invoice_number', label: t('refunds.invoiceNo'), w: 'minmax(120px, 1.1fr)' },
  { key: 'reason', label: t('common.reason'), w: 'minmax(180px, 2fr)' },
  { key: 'method', label: t('common.method'), w: 'minmax(90px, .9fr)' },
  { key: 'refunded_by', label: t('expensex.col.recordedBy'), w: 'minmax(110px, 1fr)' },
  { key: 'amount_cents', label: t('common.amount'), w: 'minmax(120px, 1.1fr)', align: 'end' },
])
const cards = computed(() => [
  { label: t('refunds.title'), value: (summary.value.count ?? 0).toLocaleString(), tone: 'gray' },
  { label: t('common.total'), value: formatTZS(summary.value.total_cents), tone: 'red' },
  { label: t('expensex.cards.month'), value: formatTZS(summary.value.month_cents), tone: 'amber' },
])
// only the owner / admin removes a refund from the books
const menuFor = () => [
  { label: '🔍 ' + t('common.view'), run: openView },
  ...(auth.isOwner ? [{ label: '🗑️ ' + t('common.delete'), run: confirmDelete, danger: true }] : []),
]
function goPage(p) { page.value = typeof p === 'number' ? p : 1; loadData() }
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
  const params = { page: page.value, per_page: perPage.value }
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

function openView(refund) { viewTarget.value = refund; showViewModal.value = true }

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
