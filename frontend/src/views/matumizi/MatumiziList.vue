<template>
  <CContainer fluid class="ex-page">

    <!-- ── Summary stat cards (scroll away) ── -->
    <div class="ex-stats">
      <div class="ex-stat ex-stat--gray">
        <div class="ex-stat-label">{{ t('expensex.cards.count') }}</div>
        <div class="ex-stat-value">{{ (summary.count ?? 0).toLocaleString() }}</div>
      </div>
      <div class="ex-stat ex-stat--red">
        <div class="ex-stat-label">{{ t('expensex.cards.spent') }}</div>
        <div class="ex-stat-value ex-stat-value--red">{{ formatTZS(summary.approved_cents) }}</div>
      </div>
      <div class="ex-stat ex-stat--green">
        <div class="ex-stat-label">{{ t('expensex.cards.month') }}</div>
        <div class="ex-stat-value ex-stat-value--green">{{ formatTZS(summary.this_month_cents) }}</div>
      </div>
      <div class="ex-stat ex-stat--amber">
        <div class="ex-stat-label">{{ t('expensex.cards.pending') }}</div>
        <div class="ex-stat-value ex-stat-value--amber">
          {{ formatTZS(summary.pending_cents) }}
          <small v-if="summary.pending_count">· {{ t('expensex.pendingCount', { count: summary.pending_count }) }}</small>
        </div>
      </div>
      <div class="ex-stat ex-stat--gray" :title="t('expensex.refundsNote')">
        <div class="ex-stat-label">{{ t('expensex.cards.refunds') }}</div>
        <div class="ex-stat-value">{{ formatTZS(summary.refunds_cents) }}</div>
      </div>
      <div class="ex-stat ex-stat--blue" :title="t('expensex.creditNote')">
        <div class="ex-stat-label">{{ t('expensex.cards.credit') }}</div>
        <div class="ex-stat-value ex-stat-value--blue">{{ formatTZS(summary.credit_cents) }}</div>
      </div>
    </div>

    <CAlert v-if="store.error" color="danger" class="ex-alert">{{ store.error }}</CAlert>
    <CAlert v-if="actionError" color="danger" dismissible class="ex-alert" @close="actionError = ''">{{ actionError }}</CAlert>
    <CAlert v-if="budgetWarning" color="warning" dismissible class="ex-alert" @close="budgetWarning = null">
      ⚠️ {{ t('expensex.overBudget', { category: budgetWarning.category, budget: budgetWarning.budget, over: formatTZS(budgetWarning.over_cents), planned: formatTZS(budgetWarning.planned_cents) }) }}
    </CAlert>

    <!-- ── Sticky header ── -->
    <div class="ex-sticky-header">

      <div class="ex-topbar">
        <div class="ex-topbar-left">
          <span class="ex-count">{{ t('common.showing', { from: showFrom, to: showTo, total: meta.total || 0 }) }}</span>
          <select v-model="perPage" class="ex-perpage" @change="fetchData(1)">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="ex-count">{{ t('common.perPage') }}</span>
          <button class="ex-filter-btn" :class="{ active: showFilters || hasActiveFilters }" @click="showFilters = !showFilters">
            {{ t('expensex.filter') }}<span v-if="hasActiveFilters" class="ex-filter-dot"></span>
          </button>
        </div>
        <div class="ex-topbar-right">
          <CButton color="secondary" variant="outline" size="sm" class="ex-action-btn" @click="showCategoriesModal = true">
            {{ t('expenseCategories.openButton') }}
          </CButton>
          <CButton color="secondary" variant="outline" size="sm" class="ex-action-btn" :disabled="printLoading" @click="openPrint">
            🖨 {{ t('printing.button') }}
          </CButton>
          <CButton color="success" size="sm" class="ex-action-btn" @click="openAddModal">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('expenses.add') }}
          </CButton>
          <div v-if="meta.last_page > 1" class="ex-pager">
            <button class="ex-page-btn" :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">{{ t('common.prev') }}</button>
            <button v-for="p in visiblePages" :key="p" class="ex-page-btn" :class="{ active: p === meta.current_page }" @click="goPage(p)">{{ p }}</button>
            <button class="ex-page-btn" :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">{{ t('common.next') }}</button>
          </div>
        </div>
      </div>

      <div v-if="showFilters" class="ex-filters">
        <CFormSelect v-model="filters.category_id" class="ex-filter-sel" @update:modelValue="fetchData(1)">
          <option value="">{{ t('expenses.allCategories') }}</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" class="ex-filter-sel" @update:modelValue="fetchData(1)">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="pending">{{ t('expenses.statuses.pending') }}</option>
          <option value="approved">{{ t('expenses.statuses.approved') }}</option>
          <option value="rejected">{{ t('expenses.statuses.rejected') }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.payment_mode" class="ex-filter-sel" @update:modelValue="fetchData(1)">
          <option value="">{{ t('expensex.allModes') }}</option>
          <option value="paid">{{ t('expensex.mode.paid') }}</option>
          <option value="credit">{{ t('expensex.mode.credit') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.date_from" type="date" class="ex-filter-sel" :title="t('expenses.dateFrom')" @update:modelValue="fetchData(1)" />
        <CFormInput v-model="filters.date_to" type="date" class="ex-filter-sel" :title="t('expenses.dateTo')" @update:modelValue="fetchData(1)" />
        <CFormInput v-model="filters.search" class="ex-filter-inp" :placeholder="t('expensex.search')" @input="debouncedFetch" />
        <CButton color="secondary" variant="outline" size="sm" class="ex-filter-reset" @click="resetFilters">{{ t('common.reset') }}</CButton>
      </div>

      <div class="ex-toolbar">
        <div class="ex-toolbar-actions">
          <button class="ex-tb-btn ex-tb-view" :disabled="!selected" @click="selected && openView(selected)">🔍 {{ t('common.view') }}</button>
          <button class="ex-tb-btn ex-tb-ok" :disabled="!canApprove(selected)" @click="canApprove(selected) && approve(selected)">✅ {{ t('common.approve') }}</button>
          <button class="ex-tb-btn ex-tb-no" :disabled="!canApprove(selected)" @click="canApprove(selected) && openReject(selected)">⛔ {{ t('expensex.reject') }}</button>
          <button class="ex-tb-btn ex-tb-edit" :disabled="!canEdit(selected)" @click="canEdit(selected) && openEditModal(selected)">✏️ {{ t('common.edit') }}</button>
          <button class="ex-tb-btn" :disabled="!canVoucher(selected)" @click="canVoucher(selected) && voucher(selected)">🧾 {{ t('expensex.voucher') }}</button>
          <button class="ex-tb-btn ex-tb-del" :disabled="!canDelete(selected)" @click="canDelete(selected) && confirmDelete(selected)">🗑️ {{ t('common.delete') }}</button>
        </div>
        <span class="ex-toolbar-hint">{{ t('expensex.hint') }}</span>
      </div>
    </div><!-- /ex-sticky-header -->

    <!-- ── Grid ── -->
    <div class="ex-grid-wrap">
      <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
      <div v-else class="ex-scroll">
        <div class="ex-grid">
          <div class="ex-head">
            <div class="ex-cell">{{ t('common.date') }}</div>
            <div class="ex-cell">{{ t('expenses.description') }}</div>
            <div class="ex-cell">{{ t('expenses.vendor') }}</div>
            <div class="ex-cell">{{ t('expenses.category') }}</div>
            <div class="ex-cell">{{ t('expensex.col.mode') }}</div>
            <div class="ex-cell ex-num">{{ t('common.amount') }}</div>
            <div class="ex-cell">{{ t('common.status') }}</div>
            <div class="ex-cell">{{ t('expensex.col.recordedBy') }}</div>
            <div class="ex-cell">{{ t('common.actions') }}</div>
          </div>

          <div
            v-for="(e, idx) in store.expenses" :key="e.id"
            class="ex-row"
            :class="[`ex-row--${e.status}`, { 'ex-row--selected': selectedId === e.id, 'ex-row--alt': idx % 2 === 1 }]"
            @click="selectedId = e.id"
            @dblclick="openView(e)"
            @contextmenu.prevent="openCtxMenu(e, $event)"
          >
            <div class="ex-cell ex-mono">{{ e.expense_date?.slice(0, 10) }}</div>
            <div class="ex-cell ex-desc">
              <span class="ex-name">{{ e.description }}</span>
              <span v-if="e.receipt_reference" class="ex-sub">#{{ e.receipt_reference }}</span>
            </div>
            <div class="ex-cell">{{ e.vendor || '—' }}</div>
            <div class="ex-cell">{{ e.category_name || '—' }}</div>
            <div class="ex-cell">
              <span class="ex-mode" :class="e.payment_mode === 'credit' ? 'ex-mode--credit' : 'ex-mode--paid'">
                {{ e.payment_mode === 'credit' ? t('expensex.mode.credit') : t('expensex.mode.paid') }}
              </span>
            </div>
            <div class="ex-cell ex-num ex-amount">{{ formatTZS(e.amount_cents) }}</div>
            <div class="ex-cell"><CBadge :color="statusColor(e.status)" shape="rounded-pill">{{ statusLabel(e.status) }}</CBadge></div>
            <div class="ex-cell">{{ e.recorder_name || '—' }}</div>
            <div class="ex-cell ex-actions-cell">
              <button class="ex-dots-btn" @click.stop="openCtxMenu(e, $event)">⋮</button>
            </div>
          </div>

          <div v-if="!store.expenses.length" class="ex-empty">{{ t('expensex.none') }}</div>
        </div>
      </div>
    </div>

    <!-- Right-click / ⋮ menu -->
    <Teleport to="body">
      <div v-if="ctx.show" class="ex-ctx-menu" :style="{ top: ctx.y + 'px', left: ctx.x + 'px' }" @click.stop>
        <button class="ex-ctx-item" @click="runCtx(openView)">🔍 {{ t('common.view') }}</button>
        <button v-if="canApprove(ctx.row)" class="ex-ctx-item" @click="runCtx(approve)">✅ {{ t('common.approve') }}</button>
        <button v-if="canApprove(ctx.row)" class="ex-ctx-item" @click="runCtx(openReject)">⛔ {{ t('expensex.reject') }}</button>
        <button v-if="canVoucher(ctx.row)" class="ex-ctx-item" @click="runCtx(voucher)">🧾 {{ t('expensex.voucher') }}</button>
        <button v-if="canEdit(ctx.row)" class="ex-ctx-item" @click="runCtx(openEditModal)">✏️ {{ t('common.edit') }}</button>
        <button v-if="canDelete(ctx.row)" class="ex-ctx-item ex-ctx-danger" @click="runCtx(confirmDelete)">🗑️ {{ t('common.delete') }}</button>
      </div>
    </Teleport>

    <!-- View -->
    <CModal :visible="showViewModal" size="lg" class="modal-fullscreen-sm-down" @close="showViewModal = false">
      <CModalHeader><CModalTitle>📋 {{ t('expenses.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.description') }}</div>
            <div class="fw-bold">{{ viewTarget.description }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.category') }}</div>
            <CBadge color="info" shape="rounded-pill">{{ viewTarget.category_name || '—' }}</CBadge>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.amount') }}</div>
            <div class="fw-bold fs-5 text-primary">{{ formatTZS(viewTarget.amount_cents) }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.status') }}</div>
            <CBadge :color="statusColor(viewTarget.status)" shape="rounded-pill">{{ statusLabel(viewTarget.status) }}</CBadge>
            <span class="ms-2 ex-mode" :class="viewTarget.payment_mode === 'credit' ? 'ex-mode--credit' : 'ex-mode--paid'">
              {{ viewTarget.payment_mode === 'credit' ? t('expensex.mode.credit') : t('expensex.mode.paid') }}
            </span>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.date') }}</div>
            <div>{{ viewTarget.expense_date?.slice(0, 10) || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expensex.receipt') }}</div>
            <div>{{ viewTarget.receipt_reference || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.vendorName') }}</div>
            <div>{{ viewTarget.vendor || '—' }}<span v-if="viewTarget.supplier_code" class="text-muted small"> ({{ viewTarget.supplier_code }})</span></div>
          </CCol>
          <CCol v-if="viewTarget.bill_number" xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expensex.bill') }}</div>
            <div>{{ viewTarget.bill_number }} <span v-if="viewTarget.bill_status" class="text-muted small">· {{ viewTarget.bill_status }}</span></div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expensex.recordedBy') }}</div>
            <div>{{ viewTarget.recorder_name || '—' }}</div>
          </CCol>
          <CCol v-if="viewTarget.approver_name" xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('expensex.decidedBy') }}</div>
            <div>{{ viewTarget.approver_name }} <span class="text-muted small">· {{ viewTarget.approved_at?.slice(0, 10) }}</span></div>
          </CCol>
          <CCol v-if="viewTarget.rejection_reason" xs="12">
            <div class="text-muted small fw-semibold mb-1">{{ t('expensex.rejectionReason') }}</div>
            <div class="p-2 rounded ex-reason">{{ viewTarget.rejection_reason }}</div>
          </CCol>
          <CCol v-if="viewTarget.notes" xs="12">
            <div class="text-muted small fw-semibold mb-1">{{ t('expenses.additionalNotes') }}</div>
            <div class="p-2 bg-light rounded">{{ viewTarget.notes }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showViewModal = false">{{ t('common.close') }}</CButton>
        <CButton v-if="canApprove(viewTarget)" color="danger" variant="outline" @click="showViewModal = false; openReject(viewTarget)">⛔ {{ t('expensex.reject') }}</CButton>
        <CButton v-if="canApprove(viewTarget)" color="success" @click="approve(viewTarget); showViewModal = false">✅ {{ t('common.approve') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add / edit -->
    <CModal :visible="showAddModal" size="lg" class="modal-fullscreen-sm-down" backdrop="static" @close="showAddModal = false">
      <CModalHeader><CModalTitle>{{ editingId ? t('expenses.editTitle') : t('expenses.addTitle') }}</CModalTitle></CModalHeader>
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
            <!-- type=text, not number: a number input cannot show grouping separators; inputmode keeps the numeric keypad -->
            <CFormInput v-model="amountDisplay" type="text" inputmode="numeric" autocomplete="off" placeholder="e.g. 500,000" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expenses.vendorName') }}</label>
            <SupplierPicker v-model="addForm.supplier_id" v-model:name="addForm.vendor_name" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('common.date') }}</label>
            <CFormInput v-model="addForm.expense_date" type="date" />
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('expensex.receipt') }}</label>
            <CFormInput v-model="addForm.receipt_reference" :placeholder="t('expensex.receiptPlaceholder')" />
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('expenses.additionalNotes') }}</label>
            <CFormTextarea v-model="addForm.notes" rows="2" :placeholder="t('expenses.notesPlaceholder')" />
          </CCol>
        </CRow>
        <CAlert v-if="addError" color="danger" class="mt-3">{{ addError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showAddModal = false">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" style="min-width:100px;" @click="submitExpense">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Reject -->
    <CModal :visible="showRejectModal" class="modal-fullscreen-sm-down" backdrop="static" @close="showRejectModal = false">
      <CModalHeader><CModalTitle>⛔ {{ t('expensex.rejectTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div v-if="rejectTarget" class="mb-2 small text-muted">{{ rejectTarget.description }} · {{ formatTZS(rejectTarget.amount_cents) }}</div>
        <label class="form-label fw-semibold">{{ t('expensex.rejectReason') }}</label>
        <CFormTextarea v-model="rejectReason" rows="3" />
        <CAlert v-if="rejectError" color="danger" class="mt-3 mb-0 py-2">{{ rejectError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showRejectModal = false">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doReject">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('expensex.rejectConfirm') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete -->
    <CModal :visible="showDeleteModal" size="sm" class="modal-fullscreen-sm-down" @close="showDeleteModal = false">
      <CModalHeader><CModalTitle>{{ t('expenses.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('expenses.confirmDeleteMsg') }}
        <template v-if="deleteTarget?.status === 'approved'">
          <label class="form-label fw-semibold mt-2 mb-1 small">{{ t('expensex.deleteReason') }}</label>
          <CFormTextarea v-model="deleteReason" rows="2" />
        </template>
        <CAlert v-if="deleteTarget?.status === 'approved'" color="warning" class="py-2 mt-2 mb-0 small">
          {{ t('expenses.confirmDeleteApproved') }}
        </CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDeleteModal = false">{{ t('common.no') }}</CButton>
        <CButton color="danger" :disabled="saving || (deleteTarget?.status === 'approved' && deleteReason.trim().length < 5)" @click="doDelete">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <ExpenseCategoriesModal v-model:visible="showCategoriesModal" @changed="fetchData()" />

    <PrintSheet v-model="printing" :title="t('expensex.printTitle')" :lines="printLines" :columns="printColumns" :rows="printRows" :footer="printFooter" landscape />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExpensesStore } from '@/stores/expenses'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import ExpenseCategoriesModal from '@/components/ExpenseCategoriesModal.vue'
import SupplierPicker from '@/components/SupplierPicker.vue'
import PrintSheet from '@/components/PrintSheet.vue'
import { openVoucher } from '@/services/voucher'

const { t } = useI18n()
const store = useExpensesStore()
const auth = useAuthStore()

const blankFilters = () => ({ category_id: '', status: '', payment_mode: '', date_from: '', date_to: '', search: '' })
const filters = ref(blankFilters())
const showFilters = ref(false)
const perPage = ref('20')
const page = ref(1)
const actionError = ref('')
const budgetWarning = ref(null)

const categories = computed(() => store.categories)
const summary = computed(() => store.summary || {})
const meta = computed(() => ({ total: 0, last_page: 1, per_page: Number(perPage.value), current_page: 1, ...(store.pagination || {}) }))
const hasActiveFilters = computed(() => Object.values(filters.value).some(Boolean))
const showFrom = computed(() => (meta.value.total ? (meta.value.current_page - 1) * meta.value.per_page + 1 : 0))
const showTo = computed(() => Math.min(meta.value.current_page * meta.value.per_page, meta.value.total))
const visiblePages = computed(() => {
  const start = Math.max(1, meta.value.current_page - 2)
  const end = Math.min(meta.value.last_page, meta.value.current_page + 2)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })
}
function statusColor(s) { return { pending: 'warning', approved: 'success', rejected: 'danger' }[s] || 'secondary' }
function statusLabel(s) { return t(`expenses.statuses.${s}`) }

function params(extra = {}) {
  const p = { per_page: perPage.value, page: page.value, ...extra }
  for (const [k, v] of Object.entries(filters.value)) if (v) p[k] = v
  return p
}
async function fetchData(p) {
  if (p) page.value = p
  await store.fetchExpenses(params())
  if (selectedId.value && !store.expenses.some(e => e.id === selectedId.value)) selectedId.value = null
}
function goPage(p) { fetchData(p) }
function resetFilters() { filters.value = blankFilters(); fetchData(1) }
let timer
function debouncedFetch() { clearTimeout(timer); timer = setTimeout(() => fetchData(1), 350) }

// ── selection + menu ──
const selectedId = ref(null)
const selected = computed(() => store.expenses.find(e => e.id === selectedId.value) || null)
const ctx = ref({ show: false, x: 0, y: 0, row: null })

// An expense booked from a supplier bill is decided (and removed) through that bill.
const canApprove = (e) => !!e && e.status === 'pending' && auth.isOwner && !e.supplier_bill_id
const canVoucher = (e) => !!e && e.status === 'approved'
async function voucher(e) {
  actionError.value = (await openVoucher('expense', e.id)) || ''
}
const canEdit = (e) => !!e && e.status === 'pending'
// only the owner / admin deletes spending (the accountant records it); an approved one needs a reason
const canDelete = (e) => !!e && auth.isOwner && !e.supplier_bill_id

function openCtxMenu(e, ev) {
  selectedId.value = e.id
  const w = 190
  const h = 40 + 34 * 5
  const x = Math.min(ev.clientX || 0, window.innerWidth - w - 8)
  const y = Math.min(ev.clientY || 0, window.innerHeight - h - 8)
  ctx.value = { show: true, x: Math.max(8, x), y: Math.max(8, y), row: e }
}
function runCtx(fn) { const row = ctx.value.row; ctx.value.show = false; if (row) fn(row) }
const closeCtx = () => { ctx.value.show = false }

// ── view ──
const showViewModal = ref(false)
const viewTarget = ref(null)
function openView(e) { viewTarget.value = e; showViewModal.value = true }

// ── add / edit ──
const showAddModal = ref(false)
const showCategoriesModal = ref(false)
const saving = ref(false)
const addError = ref('')
const editingId = ref(null)
const today = new Date().toISOString().slice(0, 10)
const blankForm = () => ({ description: '', category_id: '', amount: '', vendor_name: '', supplier_id: '', expense_date: today, receipt_reference: '', notes: '' })
const addForm = ref(blankForm())

// The field shows a grouped figure (500,000) while the form keeps a plain number; separators are stripped on the way in.
const amountDisplay = computed({
  get: () => {
    const v = addForm.value.amount
    return v === '' || v === null || v === undefined ? '' : Number(v).toLocaleString('en-US')
  },
  set: (raw) => {
    const digits = String(raw ?? '').replace(/[^0-9]/g, '')
    addForm.value.amount = digits === '' ? '' : Number(digits)
  },
})

function openAddModal() {
  editingId.value = null
  addForm.value = blankForm()
  addError.value = ''
  showAddModal.value = true
}
function openEditModal(e) {
  editingId.value = e.id
  addForm.value = {
    description: e.description || '',
    category_id: e.category_id || '',
    amount: Math.round((e.amount_cents || 0) / 100),
    vendor_name: e.vendor || '',
    supplier_id: e.supplier_id || '',
    expense_date: (e.expense_date || today).slice(0, 10),
    receipt_reference: e.receipt_reference || '',
    notes: e.notes || '',
  }
  addError.value = ''
  showAddModal.value = true
}
async function submitExpense() {
  addError.value = ''
  if (!addForm.value.description.trim()) { addError.value = t('expenses.errorDescription'); return }
  if (!addForm.value.amount || addForm.value.amount <= 0) { addError.value = t('expenses.errorAmount'); return }
  if (!addForm.value.category_id) { addError.value = t('expenses.errorCategory'); return }
  saving.value = true
  const payload = {
    description: addForm.value.description,
    category_id: addForm.value.category_id,
    amount_cents: Math.round(addForm.value.amount * 100),
    vendor: addForm.value.vendor_name || null,
    supplier_id: addForm.value.supplier_id || undefined,
    expense_date: addForm.value.expense_date,
    receipt_reference: addForm.value.receipt_reference || null,
    notes: addForm.value.notes || null,
  }
  try {
    const saved = editingId.value ? await store.updateExpense(editingId.value, payload) : await store.createExpense(payload)
    budgetWarning.value = saved?.budget_warning || null
    showAddModal.value = false
    editingId.value = null
    fetchData()
  } catch (e) {
    addError.value = e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})[0]?.[0] || 'Hitilafu'
  } finally {
    saving.value = false
  }
}

// ── decide ──
async function approve(e) {
  actionError.value = ''
  try { const done = await store.approveExpense(e.id); budgetWarning.value = done?.budget_warning || null; fetchData() } catch (err) { actionError.value = err?.response?.data?.message || 'Hitilafu' }
}
const showRejectModal = ref(false)
const rejectTarget = ref(null)
const rejectReason = ref('')
const rejectError = ref('')
function openReject(e) { rejectTarget.value = e; rejectReason.value = ''; rejectError.value = ''; showRejectModal.value = true }
async function doReject() {
  if (rejectReason.value.trim().length < 5) { rejectError.value = t('expensex.reasonShort'); return }
  saving.value = true
  try {
    await store.rejectExpense(rejectTarget.value.id, rejectReason.value.trim())
    showRejectModal.value = false
    fetchData()
  } catch (err) {
    rejectError.value = err?.response?.data?.message || Object.values(err?.response?.data?.errors || {})[0]?.[0] || 'Hitilafu'
  } finally {
    saving.value = false
  }
}

// ── delete ──
const showDeleteModal = ref(false)
const deleteTarget = ref(null)
const deleteReason = ref('')
function confirmDelete(e) { deleteTarget.value = e; deleteReason.value = ''; showDeleteModal.value = true }
async function doDelete() {
  saving.value = true
  actionError.value = ''
  try { await store.deleteExpense(deleteTarget.value.id, deleteReason.value.trim()); showDeleteModal.value = false; fetchData() }
  catch (err) { showDeleteModal.value = false; actionError.value = err?.response?.data?.message || 'Hitilafu' }
  finally { saving.value = false }
}

// ── print: every row the filters match (not just the page on screen), as the table shows it ──
const printing = ref(false)
const printLoading = ref(false)
const printRows = ref([])
const printColumns = computed(() => [
  { label: t('common.date'), value: r => r.expense_date?.slice(0, 10) },
  { label: t('expenses.description'), value: r => r.description },
  { label: t('expensex.col.receipt'), value: r => r.receipt_reference },
  { label: t('expenses.vendor'), value: r => r.vendor },
  { label: t('expenses.category'), value: r => r.category_name },
  { label: t('expensex.col.mode'), value: r => (r.payment_mode === 'credit' ? t('expensex.mode.credit') : t('expensex.mode.paid')) },
  { label: t('common.status'), value: r => statusLabel(r.status) },
  { label: t('common.amount'), value: r => formatTZS(r.amount_cents), align: 'end' },
])
const printFooter = computed(() => {
  const cents = printRows.value.filter(r => r.status === 'approved').reduce((s, r) => s + (r.amount_cents || 0), 0)
  return printRows.value.length ? [[t('expensex.cards.spent'), '', '', '', '', '', '', formatTZS(cents)]] : []
})
const printLines = computed(() => {
  const f = filters.value
  const out = []
  if (f.category_id) out.push(`${t('expenses.category')}: ${categories.value.find(c => c.id === f.category_id)?.name || ''}`)
  if (f.status) out.push(`${t('common.status')}: ${statusLabel(f.status)}`)
  if (f.payment_mode) out.push(`${t('expensex.col.mode')}: ${t(`expensex.mode.${f.payment_mode}`)}`)
  if (f.date_from || f.date_to) out.push(`${f.date_from || '…'} → ${f.date_to || '…'}`)
  if (f.search) out.push(`“${f.search}”`)
  return out
})
async function openPrint() {
  printLoading.value = true
  try {
    const { data } = await api.get('/expenses', { params: params({ per_page: 500, page: 1 }) })
    printRows.value = data.data || []
    printing.value = true
  } catch (err) {
    actionError.value = err?.response?.data?.message || 'Hitilafu'
  } finally {
    printLoading.value = false
  }
}

onMounted(async () => {
  document.addEventListener('click', closeCtx)
  window.addEventListener('scroll', closeCtx, true)
  try { await store.fetchCategories() } catch {}
  try { await fetchData() } catch {}
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closeCtx)
  window.removeEventListener('scroll', closeCtx, true)
  clearTimeout(timer)
})
</script>

<style scoped>
.ex-page { padding: 8px 12px; }

/* ── Stat cards ── */
.ex-stats { display: flex; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.ex-stat { flex: 1; min-width: 160px; padding: 10px 14px; border-radius: 6px; border-width: 2px; border-style: solid; }
.ex-stat--gray  { border-color: #6c757d; background: rgba(108,117,125,.07); box-shadow: 0 4px 12px rgba(108,117,125,.25); }
.ex-stat--red   { border-color: #dc3545; background: rgba(220,53,69,.07);   box-shadow: 0 4px 12px rgba(220,53,69,.25); }
.ex-stat--green { border-color: #198754; background: rgba(25,135,84,.07);   box-shadow: 0 4px 12px rgba(25,135,84,.25); }
.ex-stat--amber { border-color: #d97706; background: rgba(255,193,7,.1);    box-shadow: 0 4px 12px rgba(217,119,6,.25); }
.ex-stat--blue  { border-color: #1565c0; background: rgba(21,101,192,.07);  box-shadow: 0 4px 12px rgba(21,101,192,.25); }
.ex-stat-label { font-size: 12px; color: #6c757d; margin-bottom: 2px; }
.ex-stat-value { font-size: 18px; font-weight: 700; color: #1a2a3a; }
.ex-stat-value small { font-size: 11px; font-weight: 600; }
.ex-stat-value--red   { color: #dc3545; }
.ex-stat-value--green { color: #198754; }
.ex-stat-value--amber { color: #b45309; }
.ex-stat-value--blue  { color: #1565c0; }
.ex-alert { margin-bottom: 6px; padding: 8px 12px; }

/* ── Sticky header ── */
.ex-sticky-header { position: sticky; top: 0; z-index: 100; background: #f4f6f8; }
.ex-topbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; padding: 6px 4px 4px; }
.ex-topbar-left  { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #4a5568; flex-wrap: wrap; }
.ex-topbar-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.ex-count { white-space: nowrap; }
.ex-perpage { padding: 2px 4px; font-size: 13px; border: 1px solid #c8d3e0; border-radius: 3px; background: #fff; cursor: pointer; }
.ex-action-btn { white-space: nowrap; }
.ex-filter-btn {
  position: relative; border: 1px solid #c8d3e0; border-radius: 4px; padding: 3px 10px;
  font-size: 13px; background: #fff; cursor: pointer; color: #1a2a3a; white-space: nowrap;
}
.ex-filter-btn:hover { background: #e8f0fe; border-color: #1565c0; }
.ex-filter-btn.active { background: #e8f0fe; border-color: #1565c0; color: #1565c0; font-weight: 600; }
.ex-filter-dot { position: absolute; top: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: #e53935; }
.ex-pager { display: flex; gap: 2px; }
.ex-page-btn { background: #fff; border: 1px solid #c8d3e0; color: #1a2a3a; padding: 3px 9px; font-size: 12px; border-radius: 3px; cursor: pointer; }
.ex-page-btn:hover:not(:disabled) { background: #e8f0fe; }
.ex-page-btn.active { background: #1565c0; color: #fff; border-color: #1565c0; }
.ex-page-btn:disabled { opacity: .45; cursor: default; }

.ex-filters { display: flex; align-items: center; gap: 6px; padding: 6px 4px 5px; flex-wrap: wrap; background: #eef2f7; border-bottom: 1px solid #c8d3e0; }
.ex-filters :deep(.ex-filter-sel) { flex: 1 1 140px; min-width: 0; width: auto !important; font-size: 13px; height: 38px; }
.ex-filters :deep(.ex-filter-inp) { flex: 1.4 1 190px; min-width: 0; width: auto !important; font-size: 13px; height: 38px; }
.ex-filters :deep(.ex-filter-reset) { flex: 0 0 auto; height: 38px; }

.ex-toolbar {
  display: flex; align-items: center; justify-content: space-between; padding: 5px 8px;
  background: #f0f4f8; border: 1px solid #c8d3e0; border-bottom: none; border-radius: 4px 4px 0 0; gap: 8px; flex-wrap: wrap;
}
.ex-toolbar-actions { display: flex; gap: 4px; flex-wrap: wrap; }
.ex-tb-btn { border: 1px solid #b0bec5; border-radius: 3px; padding: 4px 12px; font-size: 13px; cursor: pointer; background: #fff; white-space: nowrap; }
.ex-tb-btn:disabled { opacity: .38; cursor: default; }
.ex-tb-view:not(:disabled):hover { background: #e3f2fd; }
.ex-tb-ok:not(:disabled):hover   { background: #e8f5e9; }
.ex-tb-no:not(:disabled):hover   { background: #ffebee; }
.ex-tb-edit:not(:disabled):hover { background: #fff8e1; }
.ex-tb-del:not(:disabled):hover  { background: #ffebee; }
.ex-toolbar-hint { font-size: 11px; color: #90a4ae; }

/* ── Grid ── */
.ex-grid-wrap { border: 1px solid #c8d3e0; border-radius: 0 0 4px 4px; background: #fff; font-size: 13px; }
.ex-scroll { overflow-x: auto; }
.ex-grid { display: flex; flex-direction: column; min-width: 980px; }
.ex-head, .ex-row {
  display: grid;
  grid-template-columns:
    minmax(92px, .9fr)    /* Date */
    minmax(170px, 2.2fr)  /* Description + receipt */
    minmax(110px, 1.3fr)  /* Vendor */
    minmax(100px, 1.1fr)  /* Category */
    minmax(84px, .8fr)    /* Payment */
    minmax(110px, 1.1fr)  /* Amount */
    minmax(92px, .9fr)    /* Status */
    minmax(100px, 1fr)    /* Recorded by */
    84px;                 /* Actions */
  align-items: center;
  border-bottom: 1px solid #d0dae6;
}
.ex-head {
  background: #1565c0; color: #fff; font-weight: 700; font-size: 12px; text-transform: uppercase;
  letter-spacing: .03em; user-select: none; border-bottom: 2px solid #0d47a1; position: sticky; top: 0; z-index: 40;
}
.ex-cell { padding: 5px 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-right: 1px solid #c8d3e0; line-height: 1.3; }
.ex-cell:last-child { border-right: none; }
.ex-num { text-align: right; }

.ex-row { cursor: pointer; background: #fff; transition: background .07s; }
.ex-row--alt { background: #f9fbff; }
.ex-row--pending  { background: #fffef0; }
.ex-row--pending.ex-row--alt { background: #fffce8; }
.ex-row--rejected { background: #fff5f5; }
.ex-row--rejected.ex-row--alt { background: #fff0f0; }
.ex-row--selected { background: #1565c0 !important; color: #fff !important; }
.ex-row:hover:not(.ex-row--selected) { background: #dbeeff !important; }

.ex-mono { font-family: 'Consolas','Courier New',monospace; font-size: 12px; color: #0d47a1; }
.ex-row--selected .ex-mono { color: #fff; }
.ex-desc { display: flex; flex-direction: column; gap: 0; }
.ex-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.ex-sub { font-size: 11px; color: #6c757d; }
.ex-row--selected .ex-sub { color: #dbe7f7; }
.ex-amount { font-weight: 700; }
.ex-mode { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; line-height: 1.5; }
.ex-mode--paid { background: #e8f5e9; color: #1b7a3e; }
.ex-mode--credit { background: #fff3e0; color: #b45309; }
.ex-reason { background: #fff5f5; border: 1px solid #f5c2c7; }

.ex-actions-cell { display: flex; align-items: center; justify-content: center; overflow: visible; }
.ex-dots-btn {
  border: 1px solid #c8d3e0; border-radius: 4px; padding: 2px 8px; font-size: 18px; font-weight: 700; line-height: 1;
  cursor: pointer; background: #fff; color: #1a2a3a;
}
.ex-dots-btn:hover { background: #e8f0fe; border-color: #1565c0; }
.ex-row--selected .ex-dots-btn { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.4); color: #fff; }
.ex-empty { padding: 32px; text-align: center; color: #6c757d; font-size: 14px; }
</style>

<style>
/* Context menu — teleported to body */
.ex-ctx-menu {
  position: fixed; background: #fff; border: 1px solid #c8d3e0; border-radius: 5px;
  box-shadow: 0 6px 20px rgba(0,0,0,.18); z-index: 9999; min-width: 175px; padding: 3px; display: flex; flex-direction: column;
}
.ex-ctx-item {
  background: none; border: none; text-align: left; padding: 7px 12px; font-size: 13px; cursor: pointer;
  border-radius: 3px; color: #1a2a3a; white-space: nowrap; width: 100%;
}
.ex-ctx-item:hover { background: #eaf2ff; }
.ex-ctx-danger { color: #c62828; }
</style>
