<template>
  <CContainer fluid>
    <!-- Paging row and grid toolbar stay pinned under the app header while the
         page scrolls; the grid's column headers pin directly beneath them. -->
    <div ref="stickyBar" class="list-sticky" :style="{ top: headerH + 'px' }">
      <div class="list-paging">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <small class="text-medium-emphasis text-nowrap">
            {{ t('common.showing', { from: meta.total === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
          </small>
          <CFormInput v-model="search" :placeholder="t('common.search') + '...'" @input="debouncedLoad" size="sm" style="min-width:160px; max-width:240px;" />
          <CButton color="secondary" variant="outline" size="sm" @click="search = ''; page = 1; loadData()">{{ t('common.reset') }}</CButton>
        </div>
        <div class="d-flex align-items-center gap-2">
          <CButton color="primary" class="lf-add" @click="openAdd" style="white-space:nowrap;"><CIcon icon="cilPlus" class="me-1" />{{ t('guardians.add') }}</CButton>
          <CPagination v-if="meta.last_page > 1" aria-label="Ukurasa" class="mb-0 lf-pages">
            <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; loadData()">{{ t('common.prev') }}</CPaginationItem>
            <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; loadData()">{{ p }}</CPaginationItem>
            <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; loadData()">{{ t('common.next') }}</CPaginationItem>
          </CPagination>
        </div>
      </div>

      <div class="grid-toolbar">
        <button type="button" class="grid-btn" :disabled="!selectedRow" @click="openView(selectedRow)">• {{ t('common.view') }}</button>
        <button type="button" class="grid-btn" :disabled="!selectedRow" @click="openEdit(selectedRow)">✎ {{ t('common.edit') }}</button>
        <button type="button" class="grid-btn grid-btn--danger" :disabled="!selectedRow" @click="remove(selectedRow)">▣ {{ t('common.delete') }}</button>
        <span class="grid-hint">{{ t('students.gridHint') }}</span>
      </div>
    </div>

    <!-- Desktop worklist grid (same design as the students list). -->
    <div class="worklist" tabindex="0" :style="{ '--grid-head-top': headerH + barH + 'px' }" @keydown="onGridKey">
      <div v-if="store.loading" class="worklist-loading"><CSpinner size="sm" color="primary" /></div>
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
            v-for="g in sortedGuardians"
            :key="g.id"
            :class="{ selected: selectedRow?.id === g.id }"
            @click="selectedRow = g"
            @dblclick="openView(g)"
            @contextmenu.prevent="openContext($event, g)"
          >
            <td>{{ guardianName(g) }}</td>
            <td>{{ g.phone || g.user?.phone || '' }}</td>
            <td>{{ g.email || g.user?.email || '' }}</td>
            <td>{{ relationLabel(g.relation) }}</td>
            <td :title="childNames(g)">{{ childNames(g) }}</td>
            <td>{{ g.students?.length || 0 }}</td>
          </tr>
          <tr v-if="!store.loading && !sortedGuardians.length" class="empty-note">
            <td :colspan="columns.length">{{ t('guardians.noGuardians') }}</td>
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
      <button type="button" @click="openView(ctx.g); ctx = null">👁️ {{ t('common.view') }}</button>
      <button type="button" @click="openEdit(ctx.g); ctx = null">✏️ {{ t('common.edit') }}</button>
      <button type="button" class="danger" @click="remove(ctx.g); ctx = null">🗑️ {{ t('common.delete') }}</button>
    </div>

    <!-- View Guardian Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>👨‍👩‍👧 {{ t('guardians.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded">
          <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center fw-bold"
               style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0;">
            {{ (viewTarget.full_name || viewTarget.user?.name || '?').charAt(0).toUpperCase() }}
          </div>
          <div>
            <div class="fw-bold fs-5">{{ viewTarget.full_name || viewTarget.user?.name || '—' }}</div>
            <div class="text-muted small">{{ viewTarget.relation || '—' }}</div>
          </div>
        </div>
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('guardians.phone') }}</div>
            <div>{{ viewTarget.phone || viewTarget.user?.phone || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.email') }}</div>
            <div>{{ viewTarget.email || viewTarget.user?.email || '—' }}</div>
          </CCol>
          <CCol xs="12">
            <div class="text-muted small fw-semibold mb-2">{{ t('guardians.children') }}</div>
            <div v-if="viewTarget.students?.length" class="d-flex flex-wrap gap-2">
              <CBadge v-for="s in viewTarget.students" :key="s.id" color="info" shape="rounded-pill" class="px-3 py-2">
                {{ s.full_name }} <span class="ms-1 opacity-75">{{ s.school_class?.name }}</span>
              </CBadge>
            </div>
            <div v-else class="text-muted">—</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(viewTarget); showViewModal = false" style="min-height:44px;">✏️ {{ t('common.edit') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add/Edit Modal -->
    <CModal :visible="showModal" @close="closeModal" size="lg">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('common.edit') : t('guardians.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CRow class="g-3">
          <CCol sm="6">
            <label class="form-label">{{ t('common.name') }} *</label>
            <CFormInput v-model="form.name" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('guardians.phone') }}</label>
            <CFormInput v-model="form.phone" type="tel" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('common.email') }}</label>
            <CFormInput v-model="form.email" type="email" />
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('guardians.relation') }}</label>
            <CFormSelect v-model="form.relation">
              <option value="">{{ t('common.select') }}</option>
              <option value="baba">{{ t('guardians.father') }}</option>
              <option value="mama">{{ t('guardians.mother') }}</option>
              <option value="mlezi">{{ t('guardians.guardian') }}</option>
              <option value="ndugu">{{ t('guardians.relative') }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="12">
            <label class="form-label">{{ t('guardians.children') }} *</label>
            <div class="border rounded p-2" style="max-height:180px;overflow-y:auto">
              <div v-if="!students.length" class="text-muted small">{{ t('common.loading') }}</div>
              <CFormCheck
                v-for="s in students" :key="s.id"
                :value="s.id"
                :label="`${s.full_name} (${s.admission_number || '—'})`"
                v-model="form.student_ids"
              />
            </div>
          </CCol>
        </CRow>
        <CAlert v-if="modalError" color="danger" class="mt-3">{{ modalError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="closeModal">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>🗑️ {{ t('common.delete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="mb-1 text-muted small">{{ t('common.confirmDelete') || 'Are you sure you want to delete?' }}</p>
        <p class="fw-bold mb-0">{{ deleteTarget?.full_name }}</p>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useI18n } from 'vue-i18n'
import { useGuardiansStore } from '@/stores/guardians'
import { useStudentsStore }  from '@/stores/students'
import { useStickyOffsets } from '@/composables/useStickyOffsets'
import '@/styles/worklist-grid.css'

const { t } = useI18n()
const store        = useGuardiansStore()
const studentsStore = useStudentsStore()

const search = ref('')

// ── Worklist grid ─────────────────────────────────────────────────────────
const selectedRow = ref(null)
const ctx = ref(null)
const sortKey = ref('name')
const sortDir = ref('asc')

const guardianName = (g) => g.full_name || g.user?.name || ''
const childNames = (g) => (g.students || []).map((s) => s.full_name).join(', ')
const relationLabel = (r) => ({
  baba: t('guardians.father'),
  mama: t('guardians.mother'),
  mlezi: t('guardians.guardian'),
  ndugu: t('guardians.relative'),
}[r] || r || '')

const columns = computed(() => [
  { key: 'name', label: t('common.name'), width: '22%' },
  { key: 'phone', label: t('guardians.phone'), width: '13%' },
  { key: 'email', label: t('common.email'), width: '18%' },
  { key: 'relation', label: t('guardians.relation'), width: '10%' },
  { key: 'children', label: t('guardians.children'), width: '29%' },
  { key: 'count', label: t('guardians.childCount'), width: '8%' },
])

const sortValue = (g, key) => ({
  name: guardianName(g),
  phone: g.phone || g.user?.phone || '',
  email: g.email || g.user?.email || '',
  relation: relationLabel(g.relation),
  children: childNames(g),
  count: g.students?.length || 0,
}[key])

// Sorts the rows on the current page; paging and search stay server-side.
const sortedGuardians = computed(() => {
  const dir = sortDir.value === 'asc' ? 1 : -1
  return [...(store.guardians || [])].sort((x, y) => {
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
const fillerRows = computed(() => Math.max(0, 25 - (sortedGuardians.value.length || 1)))

function openContext(e, g) {
  selectedRow.value = g
  ctx.value = { x: e.clientX, y: e.clientY, g }
}

function onGridKey(e) {
  const list = sortedGuardians.value
  if (!list.length) return
  const i = list.findIndex((g) => g.id === selectedRow.value?.id)
  if (e.key === 'ArrowDown') { e.preventDefault(); selectedRow.value = list[Math.min(list.length - 1, i + 1)] }
  else if (e.key === 'ArrowUp') { e.preventDefault(); selectedRow.value = list[Math.max(0, i - 1)] }
  else if (e.key === 'Enter' && selectedRow.value) openView(selectedRow.value)
}

// Heights for the pinned bar and column headers.
const { stickyBar, headerH, barH } = useStickyOffsets()

const showViewModal   = ref(false)
const viewTarget      = ref(null)
const showDeleteModal = ref(false)
const deleteTarget    = ref(null)
const deleting        = ref(false)
const showModal       = ref(false)
const editing     = ref(null)
const saving      = ref(false)
const modalError  = ref('')
const students    = ref([])
const page        = ref(1)
const meta        = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
let   debouncer   = null

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const emptyForm = () => ({ name:'', phone:'', email:'', relation:'', student_ids:[] })
const form = ref(emptyForm())

function openView(guardian) { viewTarget.value = guardian; showViewModal.value = true }

async function loadData() {
  const params = { page: page.value }
  if (search.value) params.search = search.value
  try {
    await store.fetchGuardians(params)
    meta.value = store.pagination || meta.value
  } catch {}
}

function debouncedLoad() {
  clearTimeout(debouncer)
  debouncer = setTimeout(() => { page.value = 1; loadData() }, 350)
}

function openAdd() {
  editing.value   = null
  form.value      = emptyForm()
  modalError.value = ''
  showModal.value = true
}

function openEdit(g) {
  editing.value   = g
  modalError.value = ''
  form.value = {
    name:        g.user?.name || '',
    phone:       g.user?.phone || '',
    email:       g.user?.email || '',
    relation:    g.relation || '',
    student_ids: g.students?.map(s => s.id) || [],
  }
  showModal.value = true
}

function closeModal() { showModal.value = false }

async function save() {
  modalError.value = ''
  saving.value = true
  try {
    if (editing.value) {
      await store.updateGuardian(editing.value.id, form.value)
    } else {
      await store.createGuardian(form.value)
    }
    closeModal()
    await loadData()
  } catch (e) {
    modalError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

function remove(g) {
  deleteTarget.value = { id: g.id, full_name: g.full_name || g.user?.name }
  showDeleteModal.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await store.deleteGuardian(deleteTarget.value.id)
    showDeleteModal.value = false
    await loadData()
  } catch (e) {
    alert(e?.response?.data?.message || t('common.error'))
  } finally {
    deleting.value = false
  }
}

function onDocClick() { ctx.value = null }

// The right-click menu is position:fixed at the click point, so close it on
// scroll rather than leave it floating away from its row.
function onScroll() { ctx.value = null }

// The Children checklist (Edit/Add modal) must offer every student in the
// school, not just one page — /students is paginated (max 100/page), so a
// single fetchStudents() call left this list silently truncated to the
// first 20/100 students while Guardian Details (which reads the guardian's
// own `students` relation, not this list) showed the guardian's real,
// complete set of children. Loop every page to build the full roster.
async function loadAllStudents() {
  const all = []
  let page = 1
  let lastPage = 1
  do {
    await studentsStore.fetchStudents({ page, per_page: 100 })
    all.push(...studentsStore.students)
    lastPage = studentsStore.pagination?.last_page || 1
    page++
  } while (page <= lastPage)
  students.value = all
}

onMounted(async () => {
  document.addEventListener('click', onDocClick)
  window.addEventListener('scroll', onScroll, true)
  await loadData()
  try {
    await loadAllStudents()
  } catch {}
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('scroll', onScroll, true)
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
:deep(.card) { overflow: visible; }
</style>
