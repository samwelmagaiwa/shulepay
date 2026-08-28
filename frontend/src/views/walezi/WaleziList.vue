<template>
  <CContainer fluid>
    <!-- Table -->
    <CCard style="position:relative;">
      <!-- Toolbar overlay: top-right above Actions column -->
      <div style="position:absolute; top:6px; right:8px; z-index:10; display:flex; align-items:center; gap:6px;">
        <CFormInput v-model="search" :placeholder="t('common.search') + '...'" @input="debouncedLoad" size="sm" style="min-width:160px; max-width:240px;" />
        <CButton color="secondary" variant="outline" size="sm" @click="search = ''; page = 1; loadData()">{{ t('common.reset') }}</CButton>
        <CButton color="primary" size="sm" @click="openAdd" style="white-space:nowrap;"><CIcon icon="cilPlus" class="me-1" />{{ t('guardians.add') }}</CButton>
      </div>
      <CCardBody class="p-0">
        <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
        <div v-else-if="!store.guardians.length" class="text-muted py-5 px-3">
          <div class="text-center mt-4">{{ t('guardians.noGuardians') }}</div>
        </div>
        <CTable v-else hover class="mb-0" style="table-layout:auto; width:100%;">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell style="white-space:nowrap;">{{ t('common.name') }}</CTableHeaderCell>
              <CTableHeaderCell style="white-space:nowrap;">{{ t('guardians.phone') }}</CTableHeaderCell>
              <CTableHeaderCell style="white-space:nowrap;">{{ t('guardians.relation') }}</CTableHeaderCell>
              <CTableHeaderCell style="white-space:nowrap;">{{ t('guardians.children') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:56px; white-space:nowrap; padding-top:38px;">{{ t('common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="g in store.guardians" :key="g.id">
              <CTableDataCell class="fw-semibold" style="white-space:nowrap;">{{ g.full_name || g.user?.name || '—' }}</CTableDataCell>
              <CTableDataCell style="white-space:nowrap;">{{ g.phone || g.user?.phone || '—' }}</CTableDataCell>
              <CTableDataCell style="white-space:nowrap;">{{ g.relation || '—' }}</CTableDataCell>
              <!-- Children: single badge inline; multiple → collapsible dropdown -->
              <CTableDataCell style="position:relative; white-space:nowrap;">
                <template v-if="!g.students?.length">—</template>
                <template v-else-if="g.students.length === 1">
                  <CBadge color="info" shape="rounded-pill">{{ g.students[0].full_name }}</CBadge>
                </template>
                <template v-else>
                  <CButton size="sm" color="info" variant="outline"
                           style="font-size:.75rem; padding:2px 8px;"
                           @click.stop="activeChildRow = activeChildRow === g.id ? null : g.id">
                    {{ g.students.length }} watoto ▾
                  </CButton>
                  <div v-if="activeChildRow === g.id"
                       style="position:absolute; top:100%; left:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:6px 8px; z-index:200; min-width:180px;"
                       @click.stop>
                    <div v-for="s in g.students" :key="s.id"
                         style="padding:3px 0; font-size:.85rem; border-bottom:1px solid #f0f0f0;">
                      <CBadge color="info" shape="rounded-pill" class="me-1">{{ s.full_name }}</CBadge>
                    </div>
                  </div>
                </template>
              </CTableDataCell>
              <CTableDataCell style="position:relative; min-width:56px; text-align:center; white-space:nowrap;">
                <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === g.id ? null : g.id">👁️</CButton>
                <div v-if="activeRow === g.id"
                     style="position:absolute; top:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 4px 16px rgba(0,0,0,.14); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:200; min-width:160px;"
                     @click.stop>
                  <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openView(g); activeRow = null">👁️ {{ t('common.view') }}</CButton>
                  <CButton size="sm" color="secondary" variant="ghost" class="text-start" @click="openEdit(g); activeRow = null">✏️ {{ t('common.edit') }}</CButton>
                  <CButton size="sm" color="danger" variant="ghost" class="text-start" @click="remove(g); activeRow = null">🗑️ {{ t('common.delete') }}</CButton>
                </div>
              </CTableDataCell>
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
      <CPagination aria-label="Ukurasa" size="sm">
        <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; loadData()">{{ t('common.prev') }}</CPaginationItem>
        <CPaginationItem
          v-for="p in visiblePages"
          :key="p"
          :active="p === meta.current_page"
          @click="page = p; loadData()"
        >{{ p }}</CPaginationItem>
        <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; loadData()">{{ t('common.next') }}</CPaginationItem>
      </CPagination>
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

const { t } = useI18n()
const store        = useGuardiansStore()
const studentsStore = useStudentsStore()

const search         = ref('')
const activeRow      = ref(null)
const activeChildRow = ref(null)
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

function onDocClick() { activeRow.value = null; activeChildRow.value = null }

onMounted(async () => {
  document.addEventListener('click', onDocClick)
  await loadData()
  try {
    await studentsStore.fetchStudents()
    students.value = studentsStore.students
  } catch {}
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
:deep(.card) { overflow: visible; }
</style>
