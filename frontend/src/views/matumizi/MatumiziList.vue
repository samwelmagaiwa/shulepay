<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Filters & Action Toolbar -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2 align-items-center">
          <CCol xs="12" sm="6" md="3">
            <CFormSelect v-model="filters.category_id" size="sm" @update:modelValue="page = 1; loadData()" style="min-height:36px;">
              <option value="">{{ t('expenses.allCategories') }}</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="6" sm="6" md="2">
            <CFormSelect v-model="filters.status" size="sm" @update:modelValue="page = 1; loadData()" style="min-height:36px;">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option value="pending">{{ t('expenses.statuses.pending') }}</option>
              <option value="approved">{{ t('expenses.statuses.approved') }}</option>
              <option value="rejected">{{ t('expenses.statuses.rejected') }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="6" sm="4" md="2">
            <CFormInput v-model="filters.date_from" type="date" size="sm" @update:modelValue="page = 1; loadData()" placeholder="Tarehe ya kwanza" style="min-height:36px;" />
          </CCol>
          <CCol xs="6" sm="4" md="2">
            <CFormInput v-model="filters.date_to" type="date" size="sm" @update:modelValue="page = 1; loadData()" placeholder="Tarehe ya mwisho" style="min-height:36px;" />
          </CCol>
          <CCol xs="6" sm="4" md="1">
            <CButton size="sm" color="secondary" variant="outline" class="w-100" @click="resetFilters" style="min-height:36px;">{{ t('expenses.resetFilters') }}</CButton>
          </CCol>
          <CCol xs="12" md="2" class="ms-auto">
            <CButton color="primary" size="sm" @click="openAddModal" class="w-100" style="min-height:36px;">
              <CIcon icon="cilPlus" class="me-1" /> {{ t('expenses.add') }}
            </CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <CAlert v-else-if="store.error" color="danger">{{ store.error }}</CAlert>

    <!-- Desktop Table -->
    <CCard v-else class="d-none d-md-block mb-3">
      <CCardBody class="p-0">
        <CTable responsive hover class="mb-0 align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('expenses.description') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('expenses.vendor') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('expenses.category') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.amount') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:56px;">{{ t('common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="e in store.expenses" :key="e.id">
              <CTableDataCell class="small">{{ e.expense_date?.slice(0,10) }}</CTableDataCell>
              <CTableDataCell>
                <div class="fw-semibold">{{ e.description }}</div>
                <div v-if="e.notes" class="text-muted small">{{ e.notes }}</div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell small">{{ e.vendor_name || '—' }}</CTableDataCell>
              <CTableDataCell><CBadge color="info" shape="rounded-pill">{{ e.category?.name || '—' }}</CBadge></CTableDataCell>
              <CTableDataCell class="fw-bold">{{ formatTZS(e.amount_cents) }}</CTableDataCell>
              <CTableDataCell><CBadge :color="statusColor(e.status)" shape="rounded-pill">{{ statusLabel(e.status) }}</CBadge></CTableDataCell>
              <CTableDataCell style="position:relative; min-width:56px; text-align:center;">
                <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === e.id ? null : e.id">👁️</CButton>
                <div v-if="activeRow === e.id"
                     style="position:absolute; bottom:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:100; min-width:160px;"
                     @click.stop>
                  <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openView(e); activeRow = null">👁️ {{ t('common.view') }}</CButton>
                  <CButton v-if="e.status === 'pending' && auth.isOwner" size="sm" color="success" variant="ghost" class="text-start" @click="approve(e); activeRow = null">✅ {{ t('common.approve') }}</CButton>
                  <CButton v-if="e.status === 'pending'" size="sm" color="danger" variant="ghost" class="text-start" @click="confirmDelete(e); activeRow = null">🗑️ {{ t('common.delete') }}</CButton>
                </div>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!store.expenses.length">
              <CTableDataCell colspan="7" class="text-center text-muted py-5">{{ t('expenses.noExpenses') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Mobile cards -->
    <div v-if="!store.loading && !store.error" class="d-md-none mb-3">
      <div v-if="!store.expenses.length" class="text-center text-muted py-5">{{ t('expenses.noExpenses') }}</div>
      <div v-for="e in store.expenses" :key="e.id" class="mb-2">
        <CCard>
          <CCardBody class="p-3">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">{{ e.description }}</div>
                <div class="text-muted small">{{ e.expense_date?.slice(0,10) }} • {{ e.category?.name }}</div>
              </div>
              <CBadge :color="statusColor(e.status)" shape="rounded-pill">{{ statusLabel(e.status) }}</CBadge>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
              <div class="fw-bold fs-5">{{ formatTZS(e.amount_cents) }}</div>
              <div class="d-flex gap-1">
                <CButton v-if="e.status === 'pending' && auth.isOwner" size="sm" color="success" variant="ghost" @click="approve(e)">
                  <CIcon icon="cilCheck" />
                </CButton>
                <CButton v-if="e.status === 'pending'" size="sm" color="danger" variant="ghost" @click="confirmDelete(e)">
                  <CIcon icon="cilTrash" />
                </CButton>
              </div>
            </div>
          </CCardBody>
        </CCard>
      </div>
    </div>

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

    <!-- Monthly total -->
    <CCard class="border-success">
      <CCardBody class="p-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">{{ t('expenses.monthlyTotal') }}</span>
        <span class="fw-bold fs-5 text-success">{{ formatTZS(monthlyApprovedTotal) }}</span>
      </CCardBody>
    </CCard>

    <!-- View Expense Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>📋 {{ t('expenses.viewTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.description') }}</div>
            <div class="fw-bold">{{ viewTarget.description }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.category') }}</div>
            <CBadge color="info" shape="rounded-pill">{{ viewTarget.category?.name || '—' }}</CBadge>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.amount') }}</div>
            <div class="fw-bold fs-5 text-primary">{{ formatTZS(viewTarget.amount_cents) }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.status') }}</div>
            <CBadge :color="statusColor(viewTarget.status)" shape="rounded-pill">{{ statusLabel(viewTarget.status) }}</CBadge>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.date') }}</div>
            <div>{{ viewTarget.expense_date?.slice(0,10) || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.vendorName') }}</div>
            <div>{{ viewTarget.vendor_name || '—' }}</div>
          </CCol>
          <CCol v-if="viewTarget.notes" xs="12">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.additionalNotes') }}</div>
            <div class="p-2 bg-light rounded">{{ viewTarget.notes }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton v-if="viewTarget?.status === 'pending' && auth.isOwner" color="success" @click="approve(viewTarget); showViewModal = false" style="min-height:44px;">✅ {{ t('common.approve') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add Modal -->
    <CModal :visible="showAddModal" @close="showAddModal=false" size="lg" class="modal-fullscreen-sm-down" backdrop="static">
      <CModalHeader><CModalTitle>{{ t('expenses.addTitle') }}</CModalTitle></CModalHeader>
      <CModalBody class="p-3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expenses.description') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="addForm.description" :placeholder="t('expenses.descriptionPlaceholder')" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expenses.category') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="addForm.category_id">
              <option value="">— {{ t('common.select') }} —</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expenses.amount') }} <span class="text-danger">*</span></label>
            <CFormInput type="number" v-model.number="addForm.amount" min="1" placeholder="e.g. 50000" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expenses.vendorName') }}</label>
            <CFormInput v-model="addForm.vendor_name" :placeholder="t('expenses.vendorPlaceholder')" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('common.date') }}</label>
            <CFormInput type="date" v-model="addForm.expense_date" />
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('expenses.additionalNotes') }}</label>
            <CFormTextarea v-model="addForm.notes" rows="2" :placeholder="t('expenses.notesPlaceholder')" />
          </CCol>
        </CRow>
        <CAlert v-if="addError" color="danger" class="mt-3">{{ addError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showAddModal=false">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="submitExpense" style="min-height:44px; min-width:100px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal=false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('expenses.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('expenses.confirmDeleteMsg') }}</CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDeleteModal=false">{{ t('common.no') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useExpensesStore } from '@/stores/expenses'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const store = useExpensesStore()
const auth = useAuthStore()
const filters = ref({ category_id: '', status: '', date_from: '', date_to: '' })
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
const activeRow = ref(null)
const showViewModal = ref(false)
const viewTarget = ref(null)
const saving = ref(false)
const addError = ref('')
const deleteTarget = ref(null)

const today = new Date().toISOString().slice(0, 10)
const addForm = ref({ description: '', category_id: '', amount: '', vendor_name: '', expense_date: today, notes: '' })

const categories = computed(() => store.categories)

const monthlyApprovedTotal = computed(() => {
  const now = new Date()
  const month = now.getMonth()
  const year = now.getFullYear()
  return store.expenses
    .filter(e => e.status === 'approved' && new Date(e.expense_date).getMonth() === month && new Date(e.expense_date).getFullYear() === year)
    .reduce((s, e) => s + (e.amount_cents || 0), 0)
})

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}
function statusColor(s) { return { pending: 'warning', approved: 'success', rejected: 'danger' }[s] || 'secondary' }
function statusLabel(s) {
  const map = { pending: 'expenses.statuses.pending', approved: 'expenses.statuses.approved', rejected: 'expenses.statuses.rejected' }
  return map[s] ? t(map[s]) : s
}

async function loadData() {
  const params = { page: page.value }
  if (filters.value.category_id) params.category_id = filters.value.category_id
  if (filters.value.status) params.status = filters.value.status
  if (filters.value.date_from) params.date_from = filters.value.date_from
  if (filters.value.date_to) params.date_to = filters.value.date_to
  await store.fetchExpenses(params)
  meta.value = store.pagination || meta.value
}
function resetFilters() { filters.value = { category_id: '', status: '', date_from: '', date_to: '' }; page.value = 1; loadData() }

function openView(expense) { viewTarget.value = expense; showViewModal.value = true }

function openAddModal() {
  addForm.value = { description: '', category_id: '', amount: '', vendor_name: '', expense_date: today, notes: '' }
  addError.value = ''
  showAddModal.value = true
}

async function submitExpense() {
  addError.value = ''
  if (!addForm.value.description.trim()) { addError.value = 'Weka maelezo'; return }
  if (!addForm.value.amount || addForm.value.amount <= 0) { addError.value = 'Weka kiasi'; return }
  if (!addForm.value.category_id) { addError.value = 'Chagua kategoria'; return }
  saving.value = true
  try {
    await store.createExpense({
      description: addForm.value.description,
      category_id: addForm.value.category_id,
      amount_cents: Math.round(addForm.value.amount * 100),
      vendor_name: addForm.value.vendor_name || null,
      expense_date: addForm.value.expense_date,
      notes: addForm.value.notes || null,
    })
    showAddModal.value = false
    loadData()
  } catch (e) {
    addError.value = e?.response?.data?.message || 'Hitilafu'
  } finally {
    saving.value = false
  }
}

async function approve(e) {
  try { await store.approveExpense(e.id); loadData() } catch {}
}

function confirmDelete(e) { deleteTarget.value = e; showDeleteModal.value = true }
async function doDelete() {
  saving.value = true
  try { await store.deleteExpense(deleteTarget.value.id); showDeleteModal.value = false; loadData() }
  catch {} finally { saving.value = false }
}

onMounted(async () => {
  try { await store.fetchCategories() } catch {}
  try { await loadData() } catch {}
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
:deep(.card) { overflow: visible; }
</style>
