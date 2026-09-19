<template>
  <CContainer fluid>
    <!-- Paging row and grid toolbar stay pinned under the app header while the
         page scrolls; the grid's column headers pin directly beneath them. -->
    <div ref="stickyBar" class="list-sticky" :style="{ top: headerH + 'px' }">
    <!-- Count + per-page + Add button + Pagination — all on one row -->
    <div class="list-paging">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <small class="text-medium-emphasis text-nowrap">
          {{ t('common.showing', { from: meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CFormSelect v-model="perPage" @update:modelValue="onPerPageChange" size="sm" style="width:80px;">
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
      <div class="d-flex align-items-center gap-2">
        <CButton color="primary" class="lf-add" @click="showAddModal = true">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('students.add') }}
        </CButton>
        <CPagination v-if="meta.last_page > 1" aria-label="Page" class="mb-0 lf-pages">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; fetchData()">{{ t('common.prev') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; fetchData()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; fetchData()">{{ t('common.next') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- Desktop-style worklist grid: grey headers with sort arrows, vertical
         column lines, compact rows, full-row blue selection, and blank ruled
         rows filling the rest of the pane. Click selects; double-click or
         Enter opens; right-click or the toolbar acts on the selected row. -->
      <!-- Filters: hidden until the Filter button is pressed (opens by
           itself while any filter is set, so an active filter is never hidden). -->
      <div v-if="showFilters" class="list-filters">
          <div class="lf-field">
            <CFormInput size="sm" v-model="filters.search" :placeholder="t('students.searchPlaceholder')" @input="debouncedFetch" />
          </div>
          <div class="lf-field">
            <CFormSelect size="sm" v-model="filters.school_id" @update:modelValue="page = 1; fetchData()">
                <option value="">{{ t('common.allSchools') }}</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </CFormSelect>
          </div>
          <div class="lf-field">
            <CFormSelect size="sm" v-model="filters.status" @update:modelValue="page = 1; fetchData()">
                <option value="">{{ t('common.allStatuses') }}</option>
                <option value="active">{{ t('students.statuses.active') }}</option>
                <option value="sponsored">{{ t('students.statuses.sponsored') }}</option>
                <option value="half_sponsored">{{ t('students.statuses.half_sponsored') }}</option>
                <option value="orphaned">{{ t('students.statuses.orphaned') }}</option>
                <option value="transferred">{{ t('students.statuses.transferred') }}</option>
                <option value="graduated">{{ t('students.statuses.graduated') }}</option>
                <option value="dropped">{{ t('students.statuses.dropped') }}</option>
              </CFormSelect>
          </div>
          <div class="lf-field">
            <CFormSelect size="sm" v-model="filters.sponsorship_type" @update:modelValue="page = 1; fetchData()">
                <option value="">🎗️ {{ t('students.allSponsorshipTypes') }}</option>
                <option value="none">{{ t('students.notSponsored') }}</option>
                <option value="half">{{ t('students.halfSponsored') }}</option>
                <option value="full_paid">{{ t('students.fullySponsoredPaid') }}</option>
                <option value="full">{{ t('students.fullySponsoredFree') }}</option>
              </CFormSelect>
          </div>
          <div class="lf-field">
            <CFormSelect size="sm" v-model="filters.has_debt" @update:modelValue="page = 1; fetchData()">
                <option value="">💰 {{ t('students.allPaymentStatus') }}</option>
                <option value="1">🔴 {{ t('students.hasDebt') }}</option>
                <option value="partial">🟡 {{ t('students.partialPaid') }}</option>
                <option value="0">✅ {{ t('students.noDebt') }}</option>
              </CFormSelect>
          </div>
          <CButton color="secondary" variant="outline" size="sm" @click="resetFilters" style="height:38px;">{{ t('common.reset') }}</CButton>
      </div>
    <div class="grid-toolbar">
      <button type="button" class="grid-btn" :disabled="!selectedRow" @click="openDetail(selectedRow)">• {{ t('common.view') }}</button>
      <button type="button" class="grid-btn" :disabled="!selectedRow" @click="openEdit(selectedRow)">✎ {{ t('common.edit') }}</button>
      <button type="button" class="grid-btn" :disabled="!selectedRow" @click="openPromise(selectedRow)">• {{ t('students.summary.recordPromise') }}</button>
      <button type="button" class="grid-btn grid-btn--danger" :disabled="!selectedRow" @click="confirmDelete(selectedRow)">▣ {{ t('common.delete') }}</button>
      <span class="grid-hint">{{ t('students.gridHint') }}</span>
    </div>
    </div>

    <div class="worklist" tabindex="0" :style="{ '--grid-head-top': headerH + barH + 'px' }" @keydown="onGridKey">
      <div v-if="studentsStore.loading" class="worklist-loading"><CSpinner size="sm" color="primary" /></div>
      <table class="worklist-table">
        <colgroup>
          <col v-for="c in columns" :key="c.key" :style="{ width: c.width }" />
        </colgroup>
        <thead>
          <tr>
            <th v-for="c in columns" :key="c.key" @click="toggleSort(c.key)">
              <span class="th-label">{{ c.label }}</span>
              <span class="sort-arrow">{{ sortKey === c.key ? (sortDir === 'asc' ? '↑' : '↓') : '↕' }}</span>
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="s in sortedStudents"
            :key="s.id"
            :class="{ selected: selectedRow?.id === s.id }"
            @click="selectedRow = s"
            @dblclick="openDetail(s)"
            @contextmenu.prevent="openContext($event, s)"
          >
            <td>{{ s.full_name }}</td>
            <td>{{ s.admission_number || '' }}</td>
            <td>{{ fmtDate(s.date_of_birth) }}</td>
            <td>{{ s.school_class?.name || '' }}</td>
            <td>{{ s.school?.name || '' }}</td>
            <td>{{ genderLabel(s.gender) }}</td>
            <td>{{ s.sponsorship_type ? sponsorshipLabel(s.sponsorship_type) : '' }}</td>
            <td>{{ fmtDate(s.admitted_at) }}</td>
            <td :class="{ 'debt-cell': s.outstanding_balance_cents > 0, 'paid-cell': s.outstanding_balance_cents === 0 }">
              <!-- Blank when the balance is unknown; never assume "paid up". -->
              <template v-if="s.outstanding_balance_cents == null"></template>
              <template v-else-if="s.outstanding_balance_cents > 0">{{ formatMoney(s.outstanding_balance_cents) }}</template>
              <template v-else>{{ t('students.paidUp') }}</template>
            </td>
            <td><span v-if="s.status" class="status-pill" :class="'status-pill--' + s.status">{{ statusLabel(s.status) }}</span></td>
          </tr>
          <tr v-if="!studentsStore.loading && !sortedStudents.length" class="empty-note">
            <td :colspan="columns.length">{{ t('students.noStudents') }}</td>
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
      <button type="button" @click="openDetail(ctx.s); ctx = null">👁️ {{ t('common.view') }}</button>
      <button type="button" @click="openEdit(ctx.s); ctx = null">✏️ {{ t('common.edit') }}</button>
      <button type="button" @click="openPromise(ctx.s); ctx = null">🤝 {{ t('students.summary.recordPromise') }}</button>
      <button type="button" class="danger" @click="confirmDelete(ctx.s); ctx = null">🗑️ {{ t('common.delete') }}</button>
    </div>


    <!-- Student Detail Drawer -->
    <MwanafunziDrawer v-if="selectedStudent" :student="selectedStudent" @close="selectedStudent = null" />

    <!-- Invoices left behind by the student just deleted. -->
    <OrphanedInvoicesModal v-model:visible="showOrphanModal" />

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('students.deleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="mb-2">{{ t('students.confirmDeleteMsg', { name: deleteTarget?.full_name }) }}</p>

        <div v-if="previewLoading" class="text-center py-3">
          <CSpinner size="sm" />
        </div>

        <!-- What the deletion leaves behind. Invoices are no longer destroyed
             with the student, so this is a statement of what survives, not a
             warning that it is about to be lost. -->
        <template v-else-if="preview && preview.invoice_count">
          <CTable small responsive class="mb-2" style="font-size:.82rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('students.invoiceNoColumn') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('students.billedColumn') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('students.paidColumn') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="inv in preview.invoices" :key="inv.id">
                <CTableDataCell>{{ inv.invoice_number }}</CTableDataCell>
                <CTableDataCell>{{ inv.term }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ fmtCents(inv.total_cents) }}</CTableDataCell>
                <CTableDataCell class="text-end text-success">{{ fmtCents(inv.paid_cents) }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>

          <CAlert :color="preview.total_paid_cents > 0 ? 'warning' : 'info'" class="py-2 mb-0 small">
            {{ t('students.deleteKeepsInvoices', {
              invoices: preview.invoice_count,
              billed: fmtCents(preview.total_billed_cents),
              payments: preview.payment_count,
              paid: fmtCents(preview.total_paid_cents),
            }) }}
          </CAlert>
        </template>

        <CAlert v-else-if="preview" color="info" class="py-2 mb-0 small">
          {{ t('students.deleteNoInvoices') }}
        </CAlert>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Add / Edit Student Modal — the full registration wizard doubles as the
         edit flow when opened with mode="edit" and an editStudentId. -->
    <AddStudentModal
      :visible="showAddModal || showEditModal"
      :mode="showEditModal ? 'edit' : 'create'"
      :edit-student-id="showEditModal ? editStudent?.id : null"
      @close="showAddModal = false; showEditModal = false"
      @saved="onStudentSaved"
      @registered="onStudentRegistered"
    />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useStudentsStore } from '@/stores/students'
import api from '@/services/api'
import OrphanedInvoicesModal from '@/components/OrphanedInvoicesModal.vue'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import StatusBadge         from '@/components/StatusBadge.vue'
import MwanafunziDrawer    from '@/components/MwanafunziDrawer.vue'
import AddStudentModal     from '@/components/AddStudentModal.vue'
import { useStickyOffsets } from '@/composables/useStickyOffsets'
import '@/styles/worklist-grid.css'

const { t } = useI18n()
const router = useRouter()
const studentsStore = useStudentsStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const showFilters    = ref(false)
const filters        = ref({ search: '', school_id: '', status: '', sponsorship_type: '', has_debt: '' })
const selectedStudent  = ref(null)
const showAddModal     = ref(false)
const showEditModal    = ref(false)
const editStudent      = ref(null)
const activeRow        = ref(null)
const showDeleteModal  = ref(false)
const deleteTarget     = ref(null)
const deleting         = ref(false)
const page            = ref(1)
const perPage         = ref('20')
const meta            = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
let   debounceTimer   = null

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const schools = computed(() => schoolsStore.schools)

// Filters the user set themselves. The school follows the header switcher, so
// it is not counted as a user filter.
const activeFilterCount = computed(() => {
  const f = filters.value
  return [f.search, f.status, f.sponsorship_type, f.has_debt].filter((v) => v !== '' && v != null).length
})
watch(activeFilterCount, (n) => { if (n) showFilters.value = true }, { immediate: true })

// Sync with nav school switcher
watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id ? String(id) : ''
  page.value = 1
  fetchData()
})

function formatMoney(cents) {
  return 'TZS ' + Number(cents / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function fetchData() {
  const params = { page: page.value, per_page: perPage.value }
  if (filters.value.search)    params.search    = filters.value.search
  if (filters.value.school_id) params.school_id = filters.value.school_id
  if (filters.value.status)    params.status    = filters.value.status
  if (filters.value.sponsorship_type) params.sponsorship_type = filters.value.sponsorship_type
  if (filters.value.has_debt !== '') params.has_debt = filters.value.has_debt
  await studentsStore.fetchStudents(params)
  meta.value = studentsStore.pagination || meta.value
}

function onPerPageChange() {
  page.value = 1
  fetchData()
}

function debouncedFetch() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; fetchData() }, 350)
}

function resetFilters() {
  filters.value = { search: '', school_id: '', status: '', sponsorship_type: '', has_debt: '' }
  if (schoolStore.activeSchoolId) {
    filters.value.school_id = String(schoolStore.activeSchoolId)
  }
  page.value = 1
  fetchData()
}

function openDetail(student) {
  selectedStudent.value = student
}

function openEdit(student) {
  editStudent.value = student
  showEditModal.value = true
}

const showOrphanModal = ref(false)
const preview = ref(null)
const previewLoading = ref(false)

const fmtCents = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

async function confirmDelete(student) {
  deleteTarget.value = student
  preview.value = null
  showDeleteModal.value = true

  // Fetched per open rather than cached: an invoice may have been raised or paid
  // since the list was loaded, and this is the number the decision rests on.
  previewLoading.value = true
  try {
    const { data } = await api.get(`/students/${student.id}/deletion-preview`)
    preview.value = data
  } catch {
    // A failed preview must not block the delete — it is context, not a gate.
    preview.value = null
  } finally {
    previewLoading.value = false
  }
}

async function doDelete() {
  deleting.value = true
  // Captured before the request, because the preview is cleared with the modal
  // and this decides whether there is anything left to review afterwards.
  const hadInvoices = (preview.value?.invoice_count || 0) > 0
  try {
    await studentsStore.deleteStudent(deleteTarget.value.id)
    showDeleteModal.value = false
    fetchData()

    // The student is gone but their invoices are not. Open the list of invoices
    // left behind so they can be cleared now, rather than leaving the user to
    // find the screen later and remember why they wanted it.
    if (hadInvoices) showOrphanModal.value = true
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kufuta.')
  } finally {
    deleting.value = false
  }
}

// A completed registration refreshes the list but leaves the modal open on its
// confirmation card. Closing here would put the operator straight back on the
// list with nothing said, which is the ambiguity that produced duplicate
// registrations; the modal closes when they acknowledge it.
function onStudentRegistered() {
  fetchData()
}

function onStudentSaved() {
  showAddModal.value = false
  showEditModal.value = false
  fetchData()
}

// ── Worklist grid ─────────────────────────────────────────────────────────
const selectedRow = ref(null)
const ctx = ref(null)
const sortKey = ref('full_name')
const sortDir = ref('asc')

const columns = computed(() => [
  { key: 'full_name', label: t('students.fullName'), width: '16%' },
  { key: 'admission_number', label: t('students.admission'), width: '12%' },
  { key: 'date_of_birth', label: t('students.dob'), width: '9%' },
  { key: 'class', label: t('common.class'), width: '10%' },
  { key: 'school', label: t('common.school'), width: '12%' },
  { key: 'gender', label: t('students.gender'), width: '6%' },
  { key: 'sponsorship_type', label: t('students.sponsorshipCol'), width: '9%' },
  { key: 'admitted_at', label: t('students.admittedOn'), width: '9%' },
  { key: 'debt', label: t('students.debt'), width: '9%' },
  { key: 'status', label: t('common.status'), width: '8%' },
])

const sortValue = (s, key) => {
  if (key === 'class') return s.school_class?.name || ''
  if (key === 'school') return s.school?.name || ''
  if (key === 'debt') return Number(s.outstanding_balance_cents) || 0
  return s[key] ?? ''
}

// Sorts the rows on the current page; paging and filters stay server-side.
const sortedStudents = computed(() => {
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...(studentsStore.students || [])].sort((x, y) => {
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
const fillerRows = computed(() => {
  const used = sortedStudents.value.length || 1
  return Math.max(0, 25 - used)
})

const fmtDate = (d) => {
  if (!d) return ''
  const dt = new Date(d)
  if (isNaN(dt)) return d
  return `${dt.getDate()}-${dt.toLocaleString('en-GB', { month: 'long' })}-${dt.getFullYear()}`
}
const genderLabel = (g) => (g === 'male' || g === 'me' ? t('students.male') : g === 'female' || g === 'ke' ? t('students.female') : '')
const sponsorshipLabel = (v) => ({
  none: t('students.notSponsored'),
  half: t('students.halfSponsored'),
  full_paid: t('students.fullySponsoredPaid'),
  full: t('students.fullySponsoredFree'),
}[v] || v)
const statusLabel = (v) => (v ? t('students.statuses.' + v) : '')

function openPromise(s) {
  router.push({ name: 'MwanafunziDetail', params: { id: s.id }, query: { tab: 'ahadi' } })
}

function openContext(e, s) {
  selectedRow.value = s
  ctx.value = { x: e.clientX, y: e.clientY, s }
}

function onGridKey(e) {
  const list = sortedStudents.value
  if (!list.length) return
  const i = list.findIndex((s) => s.id === selectedRow.value?.id)
  if (e.key === 'ArrowDown') { e.preventDefault(); selectedRow.value = list[Math.min(list.length - 1, i + 1)] }
  else if (e.key === 'ArrowUp') { e.preventDefault(); selectedRow.value = list[Math.max(0, i - 1)] }
  else if (e.key === 'Enter' && selectedRow.value) openDetail(selectedRow.value)
}

function onDocClick() { activeRow.value = null; ctx.value = null }

// Heights for the pinned bar and column headers.
const { stickyBar, headerH, barH } = useStickyOffsets()

onMounted(async () => {
  document.addEventListener('click', onDocClick)
  // Initialize school_id filter from store
  if (schoolStore.activeSchoolId) {
    filters.value.school_id = String(schoolStore.activeSchoolId)
  }
  try { await schoolsStore.fetchSchools() } catch {}
  try { await fetchData() } catch {}
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
:deep(.card) { overflow: visible; }

</style>
