<template>
  <CContainer fluid class="am-page">

    <!-- ── Summary stat cards (scroll away) ── -->
    <div class="am-stats">
      <div class="am-stat am-stat--gray">
        <!-- The list paginates by student, so this counts students, not invoices. -->
        <div class="am-stat-label">{{ t('invoices.studentsWithInvoices') }}</div>
        <div class="am-stat-value">{{ pagination.total || groupedInvoices.length }}</div>
      </div>
      <div class="am-stat am-stat--red">
        <div class="am-stat-label">{{ t('invoices.totalDebt') }}</div>
        <div class="am-stat-value am-stat-value--red">{{ formatMoney(totalOutstanding) }}</div>
      </div>
      <div class="am-stat am-stat--green">
        <div class="am-stat-label">{{ t('invoices.collected') }}</div>
        <div class="am-stat-value am-stat-value--green">{{ formatMoney(totalCollected) }}</div>
      </div>
      <div class="am-stat am-stat--amber">
        <div class="am-stat-label">{{ t('invoices.promisedToPay') }}</div>
        <div class="am-stat-value am-stat-value--amber">
          <span v-if="promisesLoading" class="spinner-border spinner-border-sm"></span>
          <span v-else>{{ promisedCount.toLocaleString() }} {{ t('invoices.promises') }}</span>
        </div>
      </div>
    </div>

    <!-- ── Alerts ── -->
    <CAlert v-if="receiptError" color="danger" dismissible class="am-alert" @close="receiptError = ''">{{ receiptError }}</CAlert>
    <CAlert v-if="bulkPrinting" color="dark" class="am-alert d-flex align-items-center gap-2">
      <CSpinner size="sm" />
      {{ t('invoices.bulkPrintingBatch', { current: bulkBatchIndex + 1, total: bulkCount?.batch_count || 1, count: bulkCurrentBatchSize }) }}
    </CAlert>
    <CAlert v-else-if="bulkBatchIndex > 0 && bulkBatchIndex < (bulkCount?.batch_count || 0)"
            color="success" dismissible class="am-alert" @close="resetBulkPrintProgress">
      {{ t('invoices.bulkPrintBatchDone', { current: bulkBatchIndex, total: bulkCount.batch_count }) }}
    </CAlert>

    <!-- ── Sticky header ──
         Pinned under the app header, whose height is measured live (it wraps to
         two rows on narrow screens), so this block never slides underneath it. -->
    <div ref="stickyBar" class="am-sticky-header" :style="{ top: headerH + 'px' }">

      <!-- Topbar -->
      <div class="am-topbar">
        <div class="am-topbar-left">
          <span class="am-count">{{ t('common.showing', {
            from: (pagination.total || 0) === 0 ? 0 : ((pagination.current_page || 1) - 1) * (pagination.per_page || perPage) + 1,
            to: Math.min((pagination.current_page || 1) * (pagination.per_page || perPage), pagination.total || 0),
            total: pagination.total || 0,
          }) }}</span>
          <select v-model="perPage" @change="fetchData(1)" class="am-perpage">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
          <span class="am-count">{{ t('common.perPage') }}</span>
          <button class="am-filter-btn" :class="{ active: showFilters || hasActiveFilters }" @click="showFilters = !showFilters">
            ☰ {{ t('common.filter') }}<span v-if="hasActiveFilters" class="am-filter-dot"></span>
          </button>
        </div>
        <div class="am-topbar-right">
          <CButton color="warning" size="sm" @click="showSmsModal = true" class="am-action-btn">
            <CIcon icon="cilSend" class="me-1" /> {{ t('invoices.sendSms') }}
          </CButton>
          <CButton color="success" size="sm" @click="showGenerateModal = true" class="am-action-btn">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('invoices.generate') }}
          </CButton>
          <div v-if="pagination.last_page > 1" class="am-pager">
            <button class="am-page-btn" :disabled="(pagination.current_page || 1) <= 1" @click="goPage((pagination.current_page || 1) - 1)">{{ t('common.prev') }}</button>
            <button v-for="p in pageNumbers" :key="p" class="am-page-btn" :class="{ active: p === pagination.current_page }" @click="goPage(p)">{{ p }}</button>
            <button class="am-page-btn" :disabled="(pagination.current_page || 1) >= pagination.last_page" @click="goPage((pagination.current_page || 1) + 1)">{{ t('common.next') }}</button>
          </div>
        </div>
      </div>

      <!-- Collapsible filter panel. Opens by itself while a filter is set, so an
           active filter is never hidden behind a closed panel. -->
      <div v-if="showFilters" class="am-filters">
        <CFormSelect v-model="filters.school_id" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.class_id" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allClasses') }}</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.term_number" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allTerms') }}</option>
          <option value="1">{{ t('invoices.term1') }}</option>
          <option value="2">{{ t('invoices.term2') }}</option>
          <option value="3">{{ t('invoices.term3') }}</option>
          <option value="4">{{ t('invoices.term4') }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" @update:modelValue="fetchData(1)" class="am-filter-sel">
          <option value="">{{ t('invoices.allStatuses') }}</option>
          <option value="unpaid">{{ t('invoices.statusFull.unpaid') }}</option>
          <option value="partial">{{ t('invoices.statusFull.partial') }}</option>
          <option value="paid">{{ t('invoices.statusFull.paid') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.search" :placeholder="t('invoices.searchStudent')" @input="debouncedFetch" class="am-filter-inp" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters" class="am-filter-reset">{{ t('common.reset') }}</CButton>
      </div>

      <!-- Action toolbar. Bulk print and the deleted-students list live here, not
           inside the filter panel: both stay reachable while it is closed. -->
      <div class="am-toolbar">
        <div class="am-toolbar-actions">
          <button class="am-tb-btn am-tb-view" :disabled="!selectedRow" @click="selectedRow && openDrawer(selectedRow.student)">🔍 {{ t('common.view') }}</button>
          <button class="am-tb-btn am-tb-pay" :disabled="!selectedRow || selectedRow.status === 'paid'" @click="selectedRow && selectedRow.status !== 'paid' && openPayment(selectedRow)">💳 {{ t('invoices.payNow') }}</button>
          <button class="am-tb-btn am-tb-print" :disabled="!selectedRow || printingStatementFor === selectedRow.student?.id" @click="selectedRow && printStatement(selectedRow.student?.id)">🖨 {{ t('invoices.printAllReceipt') }}</button>
          <CButton color="secondary" variant="outline" size="sm" @click="showOrphanModal = true" class="am-tb-extra">
            {{ t('orphanInvoices.openButton') }}
          </CButton>
          <!-- Only meaningful once the Status filter narrows to one debt status.
               The count is fetched live, so the button always says exactly how
               many invoices — and how many batches — a click will produce. -->
          <CButton
            v-if="filters.status === 'unpaid' || filters.status === 'partial'"
            color="dark" variant="outline" size="sm"
            :disabled="bulkPrinting || bulkCountLoading || !bulkCount?.count"
            @click="printBulkByStatus" class="am-tb-extra"
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
        </div>
        <span class="am-toolbar-hint">{{ t('invoices.gridHint') }}</span>
      </div>

    </div><!-- /am-sticky-header -->

    <!-- ── MOBILE: card list ── -->
    <div class="d-md-none">
      <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
      <div v-else class="pt-2">
        <div v-if="invoices.length === 0" class="text-center text-muted py-5">{{ t('invoices.noInvoices') }}</div>
        <div v-for="inv in invoices" :key="inv.id" class="am-card mb-2 p-3 rounded border" :class="rowStatusClass(inv)">
          <div class="d-flex justify-content-between align-items-start mb-1">
            <div>
              <div class="fw-bold">{{ inv.student?.full_name }}</div>
              <div class="small text-muted">{{ inv.invoice_number }} &middot; {{ inv.term?.name }}</div>
              <div class="small text-muted">{{ inv.student?.school_class?.name }}</div>
            </div>
            <StatusBadge :status="inv.status" />
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
            <div>
              <span class="small text-muted">{{ t('invoices.debt') }}: </span>
              <span class="fw-semibold" :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
                {{ formatMoney(inv.balance_due_cents) }}
              </span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <CButton size="sm" color="info" variant="outline" @click="openDrawer(inv.student)" style="min-height:44px; min-width:44px;">
                <CIcon icon="cilMagnifyingGlass" />
              </CButton>
              <CButton v-if="inv.status !== 'paid'" size="sm" color="primary" @click="openPayment(inv)" style="min-height:44px;">
                {{ t('invoices.payNow') }}
              </CButton>
              <!-- A receipt belongs to a payment, so an invoice settled in
                   instalments has one receipt per instalment. -->
              <CButton v-for="r in receiptsFor(inv)" :key="r.id" size="sm" color="success" variant="outline"
                       @click="printReceipt(r.id)" :disabled="printingReceiptId === r.id" style="min-height:44px;">
                <CSpinner v-if="printingReceiptId === r.id" size="sm" class="me-1" />
                <span v-else>🖨 </span>{{ t('payments.printReceipt') }}
                <span v-if="receiptsFor(inv).length > 1" class="small ms-1">{{ formatMoney(r.amount_cents) }}</span>
              </CButton>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── DESKTOP: grid table ── -->
    <div class="am-grid-wrap d-none d-md-block" tabindex="0" @keydown="onGridKey">
      <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
      <template v-else>
        <div class="am-grid">
          <!-- Header: click a column to sort the rows on this page. -->
          <div class="am-head" :style="{ top: headerH + barH + 'px' }">
            <div v-for="c in columns" :key="c.key" class="am-cell"
                 :class="{ 'am-cell--sortable': !c.noSort }"
                 @click="!c.noSort && toggleSort(c.key)">
              {{ c.label }}
              <span v-if="!c.noSort" class="am-sort">{{ sortKey === c.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
            </div>
          </div>
          <!-- Rows -->
          <div
            v-for="(group, idx) in sortedGroups"
            :key="group.primary.id"
            class="am-row"
            :class="[rowStatusClass(group.primary), { 'am-row--selected': selectedRowId === group.primary.id, 'am-row--alt': idx % 2 === 1 }]"
            @click="selectRow(group.primary)"
            @dblclick="openDrawer(group.primary.student)"
            @contextmenu.prevent="openMenu(group.primary, group.studentId, $event)"
          >
            <div class="am-cell am-mono" :title="group.primary.invoice_number">{{ group.primary.invoice_number }}</div>
            <div class="am-cell am-student-cell">
              <span class="am-name" :title="group.primary.student?.full_name">{{ group.primary.student?.full_name }}</span>
              <!-- The student's other invoices; teleported so the menu is not
                   clipped by the grid. -->
              <CDropdown v-if="group.others.length" variant="btn-group" class="am-more-dd" teleport>
                <CDropdownToggle size="sm" color="secondary" variant="outline" class="am-more-btn" @click.stop>
                  +{{ group.others.length }} {{ t('invoices.moreInvoices') }}
                </CDropdownToggle>
                <CDropdownMenu style="min-width:290px;">
                  <CDropdownHeader>{{ t('invoices.otherInvoicesFor', { name: group.primary.student?.full_name }) }}</CDropdownHeader>
                  <CDropdownItem v-for="inv in group.others" :key="inv.id" style="cursor:default;" class="py-2">
                    <div class="d-flex justify-content-between align-items-center gap-2">
                      <div>
                        <div class="small fw-semibold">{{ inv.term?.name }} — {{ inv.invoice_number }}</div>
                        <div class="small d-flex align-items-center gap-1">
                          <StatusBadge :status="inv.status" />
                          <span :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">{{ formatMoney(inv.balance_due_cents) }}</span>
                        </div>
                      </div>
                      <div class="d-flex gap-1">
                        <CButton size="sm" color="info" variant="outline" @click="openDrawer(inv.student)" style="min-height:28px; min-width:28px;"><CIcon icon="cilMagnifyingGlass" /></CButton>
                        <CButton v-if="inv.status !== 'paid'" size="sm" color="primary" @click="openPayment(inv)" style="min-height:28px;">{{ t('invoices.payNow') }}</CButton>
                      </div>
                    </div>
                  </CDropdownItem>
                </CDropdownMenu>
              </CDropdown>
            </div>
            <div class="am-cell" :title="group.primary.student?.school_class?.name">{{ group.primary.student?.school_class?.name || '—' }}</div>
            <div class="am-cell" :title="group.primary.term?.name">{{ group.primary.term?.name || '—' }}</div>
            <div class="am-cell">{{ formatMoney(group.primary.total_amount_cents) }}</div>
            <div class="am-cell am-paid">{{ formatMoney(group.primary.paid_cents) }}</div>
            <div class="am-cell" :class="group.primary.balance_due_cents > 0 ? 'am-debt' : 'am-zerodebt'">
              {{ formatMoney(group.primary.balance_due_cents) }}
            </div>
            <div class="am-cell"><StatusBadge :status="group.primary.status" /></div>
            <div class="am-cell am-actions-cell">
              <button class="am-dots-btn" :title="t('common.actions')"
                      @click.stop="openMenu(group.primary, group.studentId, $event, 'button')">⋮</button>
            </div>
          </div>
          <div v-if="sortedGroups.length === 0" class="am-empty">{{ t('invoices.noInvoices') }}</div>
        </div>
      </template>
    </div>

    <!-- Row actions: one menu serves both right-click and the ⋮ button. -->
    <Teleport to="body">
      <div v-if="menu" ref="menuEl" class="am-ctx-menu" :style="{ top: menu.top + 'px', left: menu.left + 'px' }" @click.stop>
        <button class="am-ctx-item" @click="openDrawer(menu.inv.student); menu = null">🔍 {{ t('common.view') }}</button>
        <button v-if="menu.inv.status !== 'paid'" class="am-ctx-item" @click="openPayment(menu.inv); menu = null">💳 {{ t('invoices.payNow') }}</button>
        <button class="am-ctx-item" @click="printStatement(menu.studentId); menu = null">🖨 {{ t('invoices.printAllReceipt') }}</button>
      </div>
    </Teleport>

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
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
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
import { useStickyOffsets }  from '@/composables/useStickyOffsets'
import { printReceipt as printReceiptPdf, printStudentStatement as printStudentStatementPdf, printBulkInvoices, cleanupReceiptFrame } from '@/utils/receipt'

const { t } = useI18n()
const invoicesStore = useInvoicesStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters          = ref({ school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' })
const showFilters      = ref(false)
// The school follows the header's school switcher, so it is not a filter the
// user set here — counting it would light the dot on every page load.
const hasActiveFilters = computed(() =>
  !!(filters.value.class_id || filters.value.term_number || filters.value.status || filters.value.search)
)
watch(hasActiveFilters, (on) => { if (on) showFilters.value = true }, { immediate: true })

const selectedRowId    = ref(null)
const selectedRow      = ref(null)
const menu             = ref(null)
const menuEl           = ref(null)
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
let   debounceTimer    = null

// Live heights of the app header and this page's pinned block.
const { stickyBar, headerH, barH } = useStickyOffsets()

function selectRow(inv) {
  selectedRowId.value = inv.id
  selectedRow.value = inv
}

function clearSelection() {
  selectedRowId.value = null
  selectedRow.value = null
  menu.value = null
}

function rowStatusClass(inv) {
  if (inv.status === 'paid')    return 'am-row--paid'
  if (inv.status === 'partial') return 'am-row--partial'
  if (inv.status === 'unpaid')  return 'am-row--unpaid'
  return ''
}

// One menu serves the right-click and the ⋮ button, so the two can never act on
// different rows. Opened at the click point (or under the button), then clamped
// to the viewport and flipped upward once its real height is known.
const MENU_WIDTH = 185
async function openMenu(inv, studentId, event, source = 'pointer') {
  selectRow(inv)
  const rect = event.currentTarget.getBoundingClientRect()
  const x = source === 'button' ? rect.right - MENU_WIDTH : event.clientX
  const y = source === 'button' ? rect.bottom + 4 : event.clientY + 4
  menu.value = { inv, studentId, top: y, left: Math.max(8, Math.min(x, window.innerWidth - MENU_WIDTH - 8)) }

  await nextTick()
  const h = menuEl.value?.offsetHeight || 0
  if (y + h > window.innerHeight) {
    const anchor = source === 'button' ? rect.top : event.clientY
    menu.value = { ...menu.value, top: Math.max(8, anchor - h - 4) }
  }
}

function resetFilters() {
  filters.value = { school_id: schoolStore.activeSchoolId || '', class_id: '', term_number: '', status: '', search: '' }
  fetchData(1)
}

function onDocClick() { menu.value = null }
// The menu is fixed at the click point, so close it on scroll rather than let
// it float away from its row.
function onScroll() { menu.value = null }

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

// ── Column sorting: this page's rows only; paging and filters stay server-side ──
const sortKey = ref('')
const sortDir = ref('asc')

const columns = computed(() => [
  { key: 'invoice_number', label: t('invoices.invoiceNo') },
  { key: 'student', label: t('invoices.student') },
  { key: 'class', label: t('invoices.class') },
  { key: 'term', label: t('invoices.term') },
  { key: 'total', label: t('common.total') },
  { key: 'paid', label: t('invoices.amountPaid') },
  { key: 'debt', label: t('invoices.debt') },
  { key: 'status', label: t('common.status') },
  { key: 'actions', label: t('common.actions'), noSort: true },
])

const sortValue = (g, key) => ({
  invoice_number: g.primary.invoice_number || '',
  student: g.primary.student?.full_name || '',
  class: g.primary.student?.school_class?.name || '',
  term: g.primary.term?.name || '',
  total: g.primary.total_amount_cents || 0,
  paid: g.primary.paid_cents || 0,
  debt: g.primary.balance_due_cents || 0,
  status: g.primary.status || '',
}[key])

// With no column chosen the server's order stands: most-owed invoices first.
const sortedGroups = computed(() => {
  if (!sortKey.value) return groupedInvoices.value
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

function onGridKey(e) {
  const list = sortedGroups.value
  if (!list.length) return
  const i = list.findIndex((g) => g.primary.id === selectedRowId.value)
  if (e.key === 'ArrowDown') { e.preventDefault(); selectRow(list[Math.min(list.length - 1, i + 1)].primary) }
  else if (e.key === 'ArrowUp') { e.preventDefault(); selectRow(list[Math.max(0, i - 1)].primary) }
  else if (e.key === 'Enter' && selectedRow.value) openDrawer(selectedRow.value.student)
}

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
  // The selection belongs to the list being replaced; keeping it would leave the
  // toolbar acting on an invoice that is no longer on screen.
  clearSelection()
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
  document.addEventListener('click', onDocClick)
  window.addEventListener('scroll', onScroll, true)
  try {
    await schoolsStore.fetchSchools()
    await loadClasses()
    await Promise.all([fetchData(), fetchPromisedCount()])
  } catch (e) {
    console.error('AdaMadeni mount error', e)
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('scroll', onScroll, true)
  cleanupReceiptFrame()
  clearTimeout(bulkCountTimer)
})
</script>

<style scoped>
/* ── Page wrapper ── */
.am-page { padding: 8px 12px; }

/* ── Stat cards row (scrolls away) ── */
.am-stats { display: flex; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.am-stat { flex: 1; min-width: 160px; padding: 10px 14px; border-radius: 6px; border-width: 2px; border-style: solid; }
.am-stat--gray  { border-color: #6c757d; background: rgba(108,117,125,.07); box-shadow: 0 4px 12px rgba(108,117,125,.25); }
.am-stat--red   { border-color: #dc3545; background: rgba(220,53,69,.07);   box-shadow: 0 4px 12px rgba(220,53,69,.25); }
.am-stat--green { border-color: #198754; background: rgba(25,135,84,.07);   box-shadow: 0 4px 12px rgba(25,135,84,.25); }
.am-stat--amber { border-color: #d97706; background: rgba(255,193,7,.1);    box-shadow: 0 4px 12px rgba(217,119,6,.25); }
.am-stat-label { font-size: 12px; color: #6c757d; margin-bottom: 2px; }
.am-stat-value { font-size: 18px; font-weight: 700; color: #1a2a3a; }
.am-stat-value--red   { color: #dc3545; }
.am-stat-value--green { color: #198754; }
.am-stat-value--amber { color: #b45309; }

.am-alert { margin-bottom: 6px; padding: 8px 12px; }

/* ── Sticky header (top offset is set inline from the app header's height) ── */
.am-sticky-header {
  position: sticky;
  z-index: 100;
  background: #f4f6f8;
  margin-bottom: 0;
}

/* ── Topbar ── */
.am-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 6px 4px 4px;
}
.am-topbar-left  { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #4a5568; flex-wrap: wrap; }
.am-topbar-right { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.am-count { white-space: nowrap; }
.am-perpage {
  padding: 2px 4px; font-size: 13px; border: 1px solid #c8d3e0;
  border-radius: 3px; background: #fff; cursor: pointer;
}
.am-action-btn { white-space: nowrap; }

.am-filter-btn {
  position: relative;
  border: 1px solid #c8d3e0; border-radius: 4px; padding: 3px 10px;
  font-size: 13px; background: #fff; cursor: pointer; color: #1a2a3a; white-space: nowrap;
  transition: background 0.1s, border-color 0.1s;
}
.am-filter-btn:hover { background: #e8f0fe; border-color: #1565c0; }
.am-filter-btn.active { background: #e8f0fe; border-color: #1565c0; color: #1565c0; font-weight: 600; }
.am-filter-dot { position: absolute; top: 3px; right: 3px; width: 6px; height: 6px; border-radius: 50%; background: #e53935; }

.am-pager { display: flex; gap: 2px; }
.am-page-btn {
  background: #fff; border: 1px solid #c8d3e0; color: #1a2a3a;
  padding: 3px 9px; font-size: 12px; border-radius: 3px; cursor: pointer;
}
.am-page-btn:hover:not(:disabled) { background: #e8f0fe; }
.am-page-btn.active { background: #1565c0; color: #fff; border-color: #1565c0; }
.am-page-btn:disabled { opacity: 0.45; cursor: default; }

/* ── Filter panel ── */
.am-filters {
  display: flex; align-items: center; gap: 6px; padding: 6px 4px 5px;
  flex-wrap: wrap; background: #eef2f7; border-bottom: 1px solid #c8d3e0;
}
.am-filters .am-filter-sel,
.am-filters .am-filter-sel select { flex: 1 1 150px; min-width: 0; width: auto !important; font-size: 13px; }
.am-filters .am-filter-inp,
.am-filters .am-filter-inp input { flex: 1.2 1 170px; min-width: 0; width: auto !important; font-size: 13px; }
.am-filter-reset { flex-shrink: 0; white-space: nowrap; font-size: 13px; }

/* ── Toolbar ── */
.am-toolbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 5px 8px; background: #f0f4f8; border: 1px solid #c8d3e0;
  border-bottom: none; border-radius: 4px 4px 0 0; gap: 8px; flex-wrap: wrap;
}
.am-toolbar-actions { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; }
.am-tb-btn {
  border: 1px solid #b0bec5; border-radius: 3px; padding: 4px 12px;
  font-size: 13px; cursor: pointer; background: #fff; white-space: nowrap;
  transition: background 0.1s, opacity 0.1s;
}
.am-tb-btn:disabled { opacity: 0.38; cursor: default; }
.am-tb-view:not(:disabled):hover  { background: #e3f2fd; }
.am-tb-pay:not(:disabled):hover   { background: #e8f5e9; }
.am-tb-print:not(:disabled):hover { background: #fff8e1; }
.am-tb-extra { font-size: 13px; white-space: nowrap; }
.am-toolbar-hint { font-size: 11px; color: #90a4ae; }

/* ── Grid ── */
.am-grid-wrap {
  border: 1px solid #c8d3e0;
  border-radius: 0 0 4px 4px;
  background: #fff;
  font-size: 13px;
  outline: none;
  /* Narrow screens scroll the grid itself rather than the whole page. */
  overflow-x: auto;
}
.am-grid { display: flex; flex-direction: column; min-width: 1000px; }

/* 9-column layout */
.am-head, .am-row {
  display: grid;
  grid-template-columns:
    minmax(120px, 1.4fr)  /* Invoice # */
    minmax(140px, 2fr)    /* Student */
    minmax(80px,  1fr)    /* Class */
    minmax(90px,  1.1fr)  /* Term */
    minmax(90px,  1fr)    /* Total */
    minmax(90px,  1fr)    /* Paid */
    minmax(90px,  1fr)    /* Debt */
    96px                  /* Status */
    80px;                 /* Actions */
  align-items: center;
  border-bottom: 1px solid #d0dae6;
}
.am-head {
  background: #1565c0;
  color: #fff;
  font-weight: 700;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: .03em;
  user-select: none;
  border-bottom: 2px solid #0d47a1;
  position: sticky;
  z-index: 40;
}
.am-cell {
  padding: 5px 8px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  border-right: 1px solid #c8d3e0;
  line-height: 1.3;
}
.am-cell:last-child { border-right: none; }
.am-cell--sortable { cursor: pointer; }
.am-cell--sortable:hover { background: #0d47a1; }
.am-sort { margin-left: 3px; color: #cfe0f7; }

/* Row colours by status */
.am-row { cursor: pointer; background: #fff; transition: background 0.07s; }
.am-row--alt                           { background: #f9fbff; }
.am-row--unpaid                        { background: #fff5f5; }
.am-row--unpaid.am-row--alt            { background: #fff0f0; }
.am-row--partial                       { background: #fffef0; }
.am-row--partial.am-row--alt           { background: #fffce8; }
.am-row--paid                          { background: #f0fbf4; }
.am-row--paid.am-row--alt              { background: #e8f8ee; }
.am-row--selected                      { background: #1565c0 !important; color: #fff !important; }
.am-row:hover:not(.am-row--selected)  { background: #dbeeff !important; }

.am-mono { font-family: 'Consolas','Courier New',monospace; font-size: 12px; color: #0d47a1; }
.am-row--selected .am-mono { color: #fff; }
.am-name  { font-weight: 600; overflow: hidden; text-overflow: ellipsis; }
.am-paid  { color: #1b7a3e; }
.am-debt  { color: #c62828; font-weight: 700; }
.am-zerodebt { color: #1b7a3e; font-weight: 600; }
.am-row--selected .am-paid,
.am-row--selected .am-debt,
.am-row--selected .am-zerodebt { color: #fff !important; }

.am-student-cell { display: flex; align-items: center; gap: 4px; overflow: hidden; }
.am-more-dd { flex-shrink: 0; }
.am-more-btn { font-size: 11px !important; padding: 1px 5px !important; min-height: 22px !important; }

/* Dots button */
.am-actions-cell { display: flex; align-items: center; justify-content: center; overflow: visible; }
.am-dots-btn {
  border: 1px solid #c8d3e0; border-radius: 4px; padding: 2px 8px;
  font-size: 18px; font-weight: 700; line-height: 1; cursor: pointer;
  background: #fff; color: #1a2a3a; transition: background 0.1s;
  display: flex; align-items: center; justify-content: center;
}
.am-dots-btn:hover { background: #e8f0fe; border-color: #1565c0; }
.am-row--selected .am-dots-btn { background: rgba(255,255,255,.15); border-color: rgba(255,255,255,.4); color: #fff; }

.am-empty { padding: 32px; text-align: center; color: #6c757d; font-size: 14px; }

/* Mobile cards reuse the same status tints as the grid rows. */
.am-card.am-row--unpaid  { background: #fff5f5; }
.am-card.am-row--partial { background: #fffef0; }
.am-card.am-row--paid    { background: #f0fbf4; }
</style>

<style>
/* Teleported to <body>, so this cannot be scoped to the component. */
.am-ctx-menu {
  position: fixed;
  background: #fff;
  border: 1px solid #c8d3e0;
  border-radius: 5px;
  box-shadow: 0 6px 20px rgba(0,0,0,.18);
  z-index: 9999;
  min-width: 175px;
  padding: 3px;
  display: flex;
  flex-direction: column;
}
.am-ctx-item {
  background: none; border: none; text-align: left;
  padding: 7px 12px; font-size: 13px; cursor: pointer;
  border-radius: 3px; color: #1a2a3a; white-space: nowrap; width: 100%;
}
.am-ctx-item:hover { background: #eaf2ff; }
</style>
