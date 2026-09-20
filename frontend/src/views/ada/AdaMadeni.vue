<template>
  <CContainer fluid class="p-2 p-md-3" style="max-width:100%; padding-left:12px; padding-right:12px;">
    <!-- Summary cards scroll away; the paging row, filters and grid toolbar
         stay pinned under the app header, as on the students list. -->
    <div>
    <CRow class="g-2">
      <CCol xs="6" md="3">
        <CCard class="h-100" style="border:2px solid #6c757d; background:rgba(108,117,125,0.07); box-shadow:0 8px 24px rgba(108,117,125,0.35), 0 2px 6px rgba(0,0,0,0.12);">
          <CCardBody class="p-2 p-md-3">
            <!-- The list paginates by student, so this total counts students,
                 not invoices — labelling it "All Invoices" would misreport it. -->
            <div class="text-muted small">{{ t('invoices.studentsWithInvoices') }}</div>
            <div class="fw-bold fs-5">{{ pagination.total || groupedInvoices.length }}</div>
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
    </div><!-- end summary cards -->

    <div ref="stickyBar" class="list-sticky" :style="{ top: headerH + 'px' }">
    <div class="list-paging">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <small class="text-medium-emphasis text-nowrap">
        {{ t('common.showing', {
          from: (pagination.total || 0) === 0 ? 0 : ((pagination.current_page || 1) - 1) * (pagination.per_page || perPage) + 1,
          to: Math.min((pagination.current_page || 1) * (pagination.per_page || perPage), pagination.total || 0),
          total: pagination.total || 0,
        }) }}
        </small>
        <CFormSelect v-model="perPage" @update:modelValue="fetchData(1)" size="sm" style="width:80px;">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </CFormSelect>
        <small class="text-medium-emphasis text-nowrap">{{ t('common.perPage') }}</small>
        <button type="button" class="lf-toggle" :class="{ on: showFilters }" @click="showFilters = !showFilters">
          ☰ {{ t('common.filter') }}<span v-if="activeFilterCount" class="lf-count">{{ activeFilterCount }}</span>
        </button>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <CButton color="warning" @click="showSmsModal = true" style="white-space:nowrap; height:38px;">
          <CIcon icon="cilSend" class="me-1" /> {{ t('invoices.sendSms') }}
        </CButton>
        <CButton color="success" class="lf-add" @click="showGenerateModal = true" style="white-space:nowrap; background:#198754 !important; border-color:#198754 !important;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('invoices.generate') }}
        </CButton>
        <CPagination v-if="pagination.last_page > 1" class="mb-0 lf-pages">
          <CPaginationItem :disabled="pagination.current_page <= 1" @click="goPage(pagination.current_page - 1)">{{ t('common.prev') }}</CPaginationItem>
          <CPaginationItem v-for="p in pageNumbers" :key="p" :active="p === pagination.current_page" @click="goPage(p)">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="pagination.current_page >= pagination.last_page" @click="goPage(pagination.current_page + 1)">{{ t('common.next') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <CAlert v-if="receiptError" color="danger" dismissible class="mt-2 py-2"
            @close="receiptError = ''">
      {{ receiptError }}
    </CAlert>

    <!-- Bulk-print progress — inline and non-blocking (no modal, page stays
         fully usable) so a multi-batch job never looks stuck or forces the
         user to wait it out with nothing else to do. -->
    <CAlert v-if="bulkPrinting" color="dark" class="mt-2 py-2 d-flex align-items-center gap-2">
      <CSpinner size="sm" />
      {{ t('invoices.bulkPrintingBatch', { current: bulkBatchIndex + 1, total: bulkCount?.batch_count || 1, count: bulkCurrentBatchSize }) }}
    </CAlert>
    <CAlert
      v-else-if="bulkBatchIndex > 0 && bulkBatchIndex < (bulkCount?.batch_count || 0)"
      color="success" dismissible class="mt-2 py-2"
      @close="resetBulkPrintProgress"
    >
      {{ t('invoices.bulkPrintBatchDone', { current: bulkBatchIndex, total: bulkCount.batch_count }) }}
    </CAlert>

      <!-- Filters: hidden until the Filter button is pressed (opens by itself
           while any filter is set, so an active filter is never hidden). -->
      <div v-if="showFilters" class="list-filters">
          <CFormSelect v-model="filters.school_id" @update:modelValue="fetchData(1)" size="sm" class="lf-input">
            <option value="">{{ t('common.allSchools') }}</option>
            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.class_id" @update:modelValue="fetchData(1)" size="sm" class="lf-input">
            <option value="">{{ t('invoices.allClasses') }}</option>
            <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.term_number" @update:modelValue="fetchData(1)" size="sm" class="lf-input">
            <option value="">{{ t('invoices.allTerms') }}</option>
            <option value="1">{{ t('invoices.term1') }}</option>
            <option value="2">{{ t('invoices.term2') }}</option>
            <option value="3">{{ t('invoices.term3') }}</option>
            <option value="4">{{ t('invoices.term4') }}</option>
          </CFormSelect>
          <CFormSelect v-model="filters.status" @update:modelValue="fetchData(1)" size="sm" class="lf-input">
            <option value="">{{ t('invoices.allStatuses') }}</option>
            <option value="unpaid">{{ t('invoices.statusFull.unpaid') }}</option>
            <option value="partial">{{ t('invoices.statusFull.partial') }}</option>
            <option value="paid">{{ t('invoices.statusFull.paid') }}</option>
          </CFormSelect>
          <CFormInput v-model="filters.search" :placeholder="t('invoices.searchStudent')" @input="debouncedFetch" size="sm" class="lf-input" />
          <!-- Only meaningful once the Status filter narrows to one debt status —
               "print everything, paid included" isn't a real bulk-print use case.
               Count is fetched live (debounced, on every relevant filter change)
               so the button always shows exactly how many invoices — and how
               many print batches — the click is about to produce, before
               committing to it. -->
          <CButton
            v-if="filters.status === 'unpaid' || filters.status === 'partial'"
            color="dark" variant="outline" size="sm"
            :disabled="bulkPrinting || bulkCountLoading || !bulkCount?.count"
            @click="printBulkByStatus"
            style="white-space:nowrap; flex-shrink:0;"
          >
            <CSpinner v-if="bulkPrinting || bulkCountLoading" size="sm" class="me-1" />
            <span v-else>🖨️ </span>
            <template v-if="bulkBatchIndex > 0 && bulkBatchIndex < (bulkCount?.batch_count || 0)">
              {{ t('invoices.printNextBatch', { current: bulkBatchIndex + 1, total: bulkCount.batch_count }) }}
            </template>
            <template v-else>
              {{ t('invoices.printByStatus', { status: t('invoices.statusFull.' + filters.status) }) }}
              <span v-if="bulkCount?.count">({{ bulkCount.count }})</span>
            </template>
          </CButton>
          <CButton color="warning" size="sm" @click="showSmsModal = true" style="white-space:nowrap; flex-shrink:0;">
            <CIcon icon="cilSend" class="me-1" /> {{ t('invoices.sendSms') }}
          </CButton>
          <CButton color="success" size="sm" @click="showGenerateModal = true" style="white-space:nowrap; flex-shrink:0;">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('invoices.generate') }}
          </CButton>
          <!-- Invoices left behind by deleted students. They no longer vanish
               with the student, so there has to be somewhere to clear them. -->
          <CButton color="secondary" variant="outline" size="sm"
                   @click="showOrphanModal = true" style="white-space:nowrap; flex-shrink:0;">
            {{ t('orphanInvoices.openButton') }}
          </CButton>
              </div>

      <div class="grid-toolbar">
        <button type="button" class="grid-btn" :disabled="!selectedGroup" @click="openDrawer(selectedGroup.primary.student)">• {{ t('common.view') }}</button>
        <button type="button" class="grid-btn" :disabled="!selectedGroup || selectedGroup.primary.status === 'paid'" @click="openPayment(selectedGroup.primary)">• {{ t('invoices.payNow') }}</button>
        <button type="button" class="grid-btn" :disabled="!selectedGroup || printingStatementFor === selectedGroup.studentId" @click="printStatement(selectedGroup.studentId)">▣ {{ t('invoices.printAllReceipt') }}</button>
        <CButton color="secondary" variant="outline" size="sm" @click="showOrphanModal = true" style="white-space:nowrap;">
          {{ t('orphanInvoices.openButton') }}
        </CButton>
        <span class="grid-hint">{{ t('invoices.gridHint') }}</span>
      </div>
    </div><!-- end pinned bar -->

    <!-- MOBILE: card list view (unchanged) -->
    <div class="d-md-none">
      <div v-if="loading" class="text-center py-5">
        <CSpinner color="primary" />
      </div>
      <div v-else class="p-2">
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
    </div><!-- end mobile block -->

    <!-- DESKTOP: worklist grid, same design as the students list. One row per
         student; their other invoices stay behind the "+N more" menu. -->
    <div class="worklist worklist--invoices d-none d-md-block" tabindex="0"
         :style="{ '--grid-head-top': headerH + barH + 'px' }" @keydown="onGridKey">
      <div v-if="loading" class="worklist-loading"><CSpinner size="sm" color="primary" /></div>
      <table class="worklist-table">
        <colgroup>
          <col v-for="c in columns" :key="c.key" :style="{ width: c.width }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="c in columns" :key="c.key" :style="c.noSort ? { cursor: 'default' } : null"
                @click="!c.noSort && toggleSort(c.key)">
              <span class="th-label">{{ c.label }}</span>
              <span v-if="!c.noSort" class="sort-arrow">{{ sortKey === c.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="group in sortedGroups"
            :key="group.primary.id"
            :class="[rowStatusClass(group.primary), { selected: selectedGroup?.primary.id === group.primary.id }]"
            @click="selectedGroup = group"
            @dblclick="openDrawer(group.primary.student)"
            @contextmenu.prevent="openContext($event, group)"
          >
            <td class="inv-number">{{ group.primary.invoice_number }}</td>
            <td class="inv-student" :title="group.primary.student?.full_name">
              {{ group.primary.student?.full_name }}
              <!-- The student's other invoices; teleported so the menu is not
                   clipped by the grid. -->
              <CDropdown v-if="group.others.length" variant="btn-group" class="d-inline-block ms-1" teleport>
                <CDropdownToggle class="more-chip" :custom-class-name="'more-chip'" @click.stop>
                  +{{ group.others.length }} {{ t('invoices.moreInvoices') }}
                </CDropdownToggle>
                <CDropdownMenu style="min-width:280px;">
                  <CDropdownHeader>{{ t('invoices.otherInvoicesFor', { name: group.primary.student?.full_name }) }}</CDropdownHeader>
                  <CDropdownItem v-for="inv in group.others" :key="inv.id" style="cursor:default;" class="py-2">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                      <div>
                        <div class="small fw-semibold">{{ inv.term?.name }} — {{ inv.invoice_number }}</div>
                        <div class="small">
                          <StatusBadge :status="inv.status" />
                          <span class="ms-1" :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
                            {{ formatMoney(inv.balance_due_cents) }}
                          </span>
                        </div>
                      </div>
                      <div class="d-flex gap-1 flex-shrink-0">
                        <CButton size="sm" color="info" variant="outline" @click="openDrawer(inv.student)" style="min-height:30px; min-width:30px;">
                          <CIcon icon="cilMagnifyingGlass" />
                        </CButton>
                        <CButton v-if="inv.status !== 'paid'" size="sm" color="primary" @click="openPayment(inv)" style="min-height:30px;">
                          {{ t('invoices.payNow') }}
                        </CButton>
                      </div>
                    </div>
                  </CDropdownItem>
                </CDropdownMenu>
              </CDropdown>
            </td>
            <td>{{ group.primary.student?.school_class?.name }}</td>
            <td>{{ group.primary.term?.name }}</td>
            <td>{{ formatMoney(group.primary.total_amount_cents) }}</td>
            <td class="paid-amount">{{ formatMoney(group.primary.paid_cents) }}</td>
            <td :class="group.primary.balance_due_cents > 0 ? 'debt-cell' : 'paid-cell'">
              {{ formatMoney(group.primary.balance_due_cents) }}
            </td>
            <td>
              <span class="status-pill" :class="'status-pill--' + group.primary.status">
                {{ t('invoices.statusFull.' + group.primary.status, group.primary.status) }}
              </span>
            </td>
            <td class="text-center">
              <button type="button" class="row-kebab" :title="t('common.actions')"
                      @click.stop="openContext($event, group)">⋮</button>
            </td>
          </tr>
          <tr v-if="!loading && !sortedGroups.length" class="empty-note">
            <td :colspan="columns.length">{{ t('invoices.noInvoices') }}</td>
          </tr>
          <!-- Blank ruled rows so the pane reads as a full grid. -->
          <tr v-for="n in fillerRows" :key="'f' + n" class="filler">
            <td v-for="c in columns" :key="c.key">&nbsp;</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Right-click menu for a row -->
    <div v-if="ctx" class="grid-context" :style="{ top: ctx.y + 'px', left: ctx.x + 'px' }" @click.stop>
      <button type="button" @click="openDrawer(ctx.group.primary.student); ctx = null">• {{ t('common.view') }}</button>
      <button type="button" :disabled="ctx.group.primary.status === 'paid'" @click="openPayment(ctx.group.primary); ctx = null">• {{ t('invoices.payNow') }}</button>
      <button type="button" @click="printStatement(ctx.group.studentId); ctx = null">▣ {{ t('invoices.printAllReceipt') }}</button>
    </div>

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
    <OrphanedInvoicesModal v-model:visible="showOrphanModal" @purged="fetchData(1)" />

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
import OrphanedInvoicesModal from '@/components/OrphanedInvoicesModal.vue'
import api                   from '@/services/api'
import { useStickyOffsets } from '@/composables/useStickyOffsets'
import '@/styles/worklist-grid.css'
import { printReceipt as printReceiptPdf, printStudentStatement as printStudentStatementPdf, printBulkInvoices, cleanupReceiptFrame } from '@/utils/receipt'

const { t } = useI18n()
const invoicesStore = useInvoicesStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters          = ref({ school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' })
const selectedInvoice  = ref(null)
const showPayModal     = ref(false)
const drawerStudent    = ref(null)
const showGenerateModal = ref(false)
const showOrphanModal = ref(false)
const showSmsModal     = ref(false)
const classes          = ref([])
const promisedCount    = ref(0)
const promisesLoading  = ref(false)
const perPage          = ref('20')
const showFilters      = ref(false)
let   debounceTimer    = null

const invoices   = computed(() => invoicesStore.invoices)
const loading    = computed(() => invoicesStore.loading)
const pagination = computed(() => invoicesStore.pagination || {})
const schools    = computed(() => schoolsStore.schools)

watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  filters.value.class_id = ''  // a class picked under the previous school no longer applies
  fetchData(1)
  loadClasses()
})

// The Class filter dropdown was fetched once on mount with no school scoping
// at all (fell back to the logged-in user's fixed home school, ignoring the
// active-school switcher) and never refreshed on school switch — same bug
// class as the /admin/terms page.
async function loadClasses() {
  try {
    const { data: cd } = await api.get('/school-classes', {
      params: filters.value.school_id ? { school_id: filters.value.school_id } : {},
    })
    classes.value = cd.data || cd
  } catch { classes.value = [] }
}

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

// ── Worklist grid ─────────────────────────────────────────────────────────
const selectedGroup = ref(null)
const ctx = ref(null)
const sortKey = ref('invoice_number')
const sortDir = ref('desc')

const { stickyBar, headerH, barH } = useStickyOffsets()

const columns = computed(() => [
  { key: 'invoice_number', label: t('invoices.invoiceNo'), width: '15%' },
  { key: 'student', label: t('invoices.student'), width: '23%' },
  { key: 'class', label: t('invoices.class'), width: '10%' },
  { key: 'term', label: t('invoices.term'), width: '12%' },
  { key: 'total', label: t('common.total'), width: '10%' },
  { key: 'paid', label: t('invoices.amountPaid'), width: '10%' },
  { key: 'debt', label: t('invoices.debt'), width: '10%' },
  { key: 'status', label: t('common.status'), width: '9%' },
  { key: 'actions', label: t('common.actions'), width: '6%', noSort: true },
])

// Filters the user set themselves; the school follows the header switcher.
const activeFilterCount = computed(() => {
  const f = filters.value
  return [f.class_id, f.term_number, f.status, f.search].filter((v) => v !== '' && v != null).length
})
watch(activeFilterCount, (n) => { if (n) showFilters.value = true }, { immediate: true })

const sortValue = (g, key) => ({
  invoice_number: g.primary.invoice_number || '',
  student: g.primary.student?.full_name || '',
  class: g.primary.student?.school_class?.name || '',
  term: g.primary.term?.name || '',
  total: g.primary.total_amount_cents || 0,
  paid: g.primary.paid_cents || 0,
  debt: g.primary.balance_due_cents || 0,
  status: g.primary.status || '',
  actions: '',
}[key])

// Sorts the rows on the current page; paging and filters stay server-side.
const sortedGroups = computed(() => {
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...groupedInvoices.value].sort((x, y) => {
    const a = sortValue(x, sortKey.value)
    const b = sortValue(y, sortKey.value)
    if (typeof a === 'number' && typeof b === 'number') return (a - b) * dir
    return String(a).localeCompare(String(b), undefined, { numeric: true }) * dir
  })
})

function toggleSort(key) {
  if (sortKey.value === key) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  else { sortKey.value = key; sortDir.value = 'asc' }
}

// Enough blank rows to fill the pane, like a desktop list view.
const fillerRows = computed(() => Math.max(0, 25 - (sortedGroups.value.length || 1)))

function openContext(e, group) {
  selectedGroup.value = group
  ctx.value = { x: e.clientX, y: e.clientY, group }
}

function onGridKey(e) {
  const list = sortedGroups.value
  if (!list.length) return
  const i = list.findIndex((g) => g.primary.id === selectedGroup.value?.primary.id)
  if (e.key === 'ArrowDown') { e.preventDefault(); selectedGroup.value = list[Math.min(list.length - 1, i + 1)] }
  else if (e.key === 'ArrowUp') { e.preventDefault(); selectedGroup.value = list[Math.max(0, i - 1)] }
  else if (e.key === 'Enter' && selectedGroup.value) openDrawer(selectedGroup.value.primary.student)
}

function onGridDocClick() { ctx.value = null }

// Row tint by payment status: unpaid pink, partial cream, paid mint.
function rowStatusClass(inv) {
  return 'row-' + (inv.status || 'unpaid')
}

function rowBgClass(inv) {
  if (inv.status === 'paid') return 'table-success'
  if (inv.status === 'partial') return 'table-warning'
  if (inv.status === 'unpaid') return 'table-danger'
  return ''
}

// ── Group invoices by student — one visible row per student, the rest
// tucked behind a dropdown, so a student with e.g. 4 terms' invoices
// doesn't fill the table with 4 near-identical rows. The most relevant
// invoice (unpaid/partial over paid, then by balance) leads the row; the
// dropdown lists every invoice with the same actions the full table had.
const groupedInvoices = computed(() => {
  const byStudent = new Map()
  for (const inv of invoices.value) {
    const sid = inv.student?.id
    if (sid == null) continue
    if (!byStudent.has(sid)) byStudent.set(sid, [])
    byStudent.get(sid).push(inv)
  }

  const statusRank = { unpaid: 0, partial: 1, paid: 2 }
  return Array.from(byStudent.values()).map(group => {
    const sorted = [...group].sort((a, b) => {
      const r = (statusRank[a.status] ?? 3) - (statusRank[b.status] ?? 3)
      return r !== 0 ? r : (b.balance_due_cents || 0) - (a.balance_due_cents || 0)
    })
    return { primary: sorted[0], others: sorted.slice(1), studentId: sorted[0].student.id }
  })
})

async function fetchData(page) {
  // The table renders one row per student, so ask the API to paginate by student
  // too — otherwise 20 invoices collapse into ~5 rows and the page looks empty.
  const params = {
    page: page ?? pagination.value.current_page ?? 1,
    per_page: perPage.value,
    group_by_student: 1,
  }
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

const receiptError = ref('')

// Direct print — a hidden iframe loads the PDF and fires the browser's print
// dialog immediately, no in-app preview. (An in-app previewer was tried and
// removed: its "fit to width" PDF view clipped page content off the right
// edge of the modal.)
const printingReceiptId = ref(null)
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

// One consolidated receipt for every invoice a student has — all terms'
// debts, payments made, and the remaining balance on a single printout.
const printingStatementFor = ref(null)
async function printStatement(studentId) {
  if (!studentId) return
  printingStatementFor.value = studentId
  receiptError.value = ''
  try {
    await printStudentStatementPdf(studentId)
  } catch (e) {
    receiptError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    printingStatementFor.value = null
  }
}

// ── Bulk print: count-first, then batched ────────────────────────────────
// Printing every invoice matching a status filter in one PDF worked but was
// slow to load for a school with hundreds of debtors, and gave no sense of
// how much was about to print or whether it fit the server's per-request
// cap. Instead: a fast count check tells the button exactly how many
// invoices (and how many print batches, at 50/batch) a click will produce;
// clicking prints one batch at a time — small enough to load quickly — and
// leaves the page fully interactive between batches (no modal, nothing
// blocks navigation) so "print everything" for a large filtered set is a
// few clicks instead of one slow, uncertain wait.
const bulkPrinting = ref(false)
const bulkCountLoading = ref(false)
const bulkCount = ref(null) // { count, batch_size, batch_count, max_batch }
const bulkBatchIndex = ref(0) // batches already printed this session
const bulkCurrentBatchSize = ref(0)
let bulkCountTimer = null

function bulkParams() {
  const params = { status: filters.value.status }
  if (filters.value.school_id)   params.school_id = filters.value.school_id
  if (filters.value.class_id)    params.school_class_id = filters.value.class_id
  if (filters.value.term_number) params.term_number = filters.value.term_number
  return params
}

function resetBulkPrintProgress() {
  bulkBatchIndex.value = 0
}

async function fetchBulkCount() {
  resetBulkPrintProgress()
  if (filters.value.status !== 'unpaid' && filters.value.status !== 'partial') {
    bulkCount.value = null
    return
  }
  bulkCountLoading.value = true
  try {
    const { data } = await api.get('/invoices/bulk-receipt/count', { params: bulkParams() })
    bulkCount.value = data
  } catch {
    bulkCount.value = null
  } finally {
    bulkCountLoading.value = false
  }
}

function debouncedBulkCount() {
  clearTimeout(bulkCountTimer)
  bulkCountTimer = setTimeout(fetchBulkCount, 300)
}

// school_id/class_id/term_number already trigger fetchData(1) on their own
// @update:modelValue — piggyback the count refresh on the same triggers plus
// a watcher, so it stays correct however the filter changed.
watch(() => [filters.value.status, filters.value.school_id, filters.value.class_id, filters.value.term_number], debouncedBulkCount)

async function printBulkByStatus() {
  if (bulkPrinting.value || !bulkCount.value?.count) return
  bulkPrinting.value = true
  receiptError.value = ''
  try {
    const batchSize = bulkCount.value.batch_size
    const offset = bulkBatchIndex.value * batchSize
    const remaining = bulkCount.value.count - offset
    bulkCurrentBatchSize.value = Math.min(batchSize, remaining)

    await printBulkInvoices({ ...bulkParams(), offset, limit: batchSize })

    bulkBatchIndex.value += 1
    if (bulkBatchIndex.value >= bulkCount.value.batch_count) {
      // Whole filtered set is done — re-check in case something changed
      // (e.g. a payment recorded elsewhere) while batches were printing.
      await fetchBulkCount()
    }
  } catch (e) {
    receiptError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    bulkPrinting.value = false
  }
}

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
  document.addEventListener('click', onGridDocClick)
  try {
    await schoolsStore.fetchSchools()
    await loadClasses()
    await Promise.all([fetchData(), fetchPromisedCount()])
  } catch (e) {
    console.error('AdaMadeni mount error', e)
  }
})

onBeforeUnmount(() => document.removeEventListener('click', onGridDocClick))
onBeforeUnmount(() => {
  cleanupReceiptFrame()
  clearTimeout(bulkCountTimer)
})
</script>

<style scoped>
@media (max-width: 767px) {
  .btn { min-height: 44px; }
}

/* The "+N more invoices" menu is teleported to <body> (see the CDropdown above),
   so it is no longer inside .table-responsive and cannot be clipped by it.
   The earlier attempt here set `overflow-y: visible` on that wrapper, but the
   overflow spec turns a `visible` axis into `auto` whenever the other axis is
   not visible — so on a horizontally scrolling table it never took effect.
   Only the stacking order still needs raising, above the sticky summary bar. */
:deep(.dropdown-menu) {
  z-index: 1060;
}
</style>
