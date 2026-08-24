<template>
  <CContainer fluid>
    <!-- Filters -->
    <CCard class="mb-2">
      <CCardBody class="py-2">
        <CRow class="g-2">
          <CCol sm="4" md="3">
            <CFormInput v-model="filters.search" :placeholder="t('students.searchPlaceholder')" @input="debouncedFetch" />
          </CCol>
          <CCol sm="3" md="2">
            <CFormSelect v-model="filters.school_id" @update:modelValue="page = 1; fetchData()">
              <option value="">{{ t('common.allSchools') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="3" md="2">
            <CFormSelect v-model="filters.status" @update:modelValue="page = 1; fetchData()">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option value="active">{{ t('students.statuses.active') }}</option>
              <option value="sponsored">{{ t('students.statuses.sponsored') }}</option>
              <option value="orphaned">{{ t('students.statuses.orphaned') }}</option>
              <option value="transferred">{{ t('students.statuses.transferred') }}</option>
              <option value="graduated">{{ t('students.statuses.graduated') }}</option>
              <option value="dropped">{{ t('students.statuses.dropped') }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="3" md="3">
            <CFormSelect v-model="filters.has_debt" @update:modelValue="page = 1; fetchData()">
              <option value="">💰 {{ t('students.allPaymentStatus') }}</option>
              <option value="1">🔴 {{ t('students.hasDebt') }}</option>
              <option value="0">✅ {{ t('students.noDebt') }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="2" md="2">
            <CButton color="secondary" variant="outline" @click="resetFilters" class="w-100">{{ t('common.reset') }}</CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <!-- Count + per-page + Add button + Pagination — all on one row -->
    <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
      <div class="d-flex align-items-center gap-2">
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
      </div>
      <div class="d-flex align-items-center gap-2">
        <CButton color="primary" size="sm" @click="showAddModal = true">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('students.add') }}
        </CButton>
        <CPagination v-if="meta.last_page > 1" aria-label="Page" size="sm" class="mb-0">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; fetchData()">{{ t('common.prev') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; fetchData()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; fetchData()">{{ t('common.next') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- Table -->
    <CCard>
      <CCardBody class="p-0">
        <div v-if="studentsStore.loading" class="text-center py-5">
          <CSpinner color="primary" />
        </div>
        <CTable v-else responsive hover class="mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('students.admission') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('students.fullName') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.class') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('students.gender') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('students.debt') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:56px;">{{ t('common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow
              v-for="s in studentsStore.students"
              :key="s.id"
              style="cursor:pointer"
              @click="openDetail(s)"
            >
              <CTableDataCell class="fw-medium">{{ s.admission_number }}</CTableDataCell>
              <CTableDataCell>{{ s.full_name }}</CTableDataCell>
              <CTableDataCell>{{ s.school_class?.name || '—' }}</CTableDataCell>
              <CTableDataCell>{{ s.gender === 'male' || s.gender === 'me' ? t('students.male') : s.gender === 'female' || s.gender === 'ke' ? t('students.female') : '—' }}</CTableDataCell>
              <CTableDataCell><StatusBadge :status="s.status" /></CTableDataCell>
              <CTableDataCell>
                <span v-if="!s.outstanding_balance_cents || s.outstanding_balance_cents <= 0"
                      class="d-inline-flex align-items-center gap-1 px-2 py-1 rounded-pill fw-semibold"
                      style="background:rgba(25,135,84,0.1); color:#198754; font-size:.75rem;">
                  ✓ Amelipa
                </span>
                <span v-else class="fw-semibold text-danger">
                  {{ formatMoney(s.outstanding_balance_cents) }}
                </span>
              </CTableDataCell>
              <CTableDataCell style="position:relative; min-width:56px; text-align:center;">
                <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === s.id ? null : s.id">👁️</CButton>
                <div v-if="activeRow === s.id"
                     style="position:absolute; bottom:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:100; min-width:160px;"
                     @click.stop>
                  <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openDetail(s); activeRow = null">👁️ {{ t('common.view') }}</CButton>
                  <CButton size="sm" color="primary" variant="ghost" class="text-start" @click="openEdit(s); activeRow = null">✏️ {{ t('common.edit') }}</CButton>
                  <CButton size="sm" color="warning" variant="ghost" class="text-start" @click="router.push({ name: 'MwanafunziDetail', params: { id: s.id }, query: { tab: 'ahadi' } }); activeRow = null">🤝 {{ t('students.summary.recordPromise') }}</CButton>
                  <CButton size="sm" color="danger" variant="ghost" class="text-start" @click="confirmDelete(s); activeRow = null">🗑️ {{ t('common.delete') }}</CButton>
                </div>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!studentsStore.loading && studentsStore.students.length === 0">
              <CTableDataCell colspan="7" class="text-center text-muted py-4">
                {{ t('students.noStudents') }}
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>


    <!-- Student Detail Drawer -->
    <MwanafunziDrawer v-if="selectedStudent" :student="selectedStudent" @close="selectedStudent = null" />

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('students.deleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('students.confirmDeleteMsg', { name: deleteTarget?.full_name }) }}</CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Add Student Modal -->
    <AddStudentModal
      :visible="showAddModal"
      @close="showAddModal = false"
      @saved="onStudentSaved"
      @registered="onStudentSaved"
    />

    <!-- Edit Student Modal -->
    <EditStudentModal
      :visible="showEditModal"
      :student="editStudent"
      @close="showEditModal = false"
      @saved="onStudentSaved"
    />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useStudentsStore } from '@/stores/students'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import StatusBadge         from '@/components/StatusBadge.vue'
import MwanafunziDrawer    from '@/components/MwanafunziDrawer.vue'
import AddStudentModal     from '@/components/AddStudentModal.vue'
import EditStudentModal    from '@/components/EditStudentModal.vue'

const { t } = useI18n()
const router = useRouter()
const studentsStore = useStudentsStore()
const schoolsStore  = useSchoolsStore()
const schoolStore   = useSchoolStore()

const filters        = ref({ search: '', school_id: '', status: '', has_debt: '' })
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
  filters.value = { search: '', school_id: '', status: '', has_debt: '' }
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

function confirmDelete(student) {
  deleteTarget.value = student
  showDeleteModal.value = true
}

async function doDelete() {
  deleting.value = true
  try {
    await studentsStore.deleteStudent(deleteTarget.value.id)
    showDeleteModal.value = false
    fetchData()
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kufuta.')
  } finally {
    deleting.value = false
  }
}

function onStudentSaved() {
  showAddModal.value = false
  fetchData()
}

function onDocClick() { activeRow.value = null }

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
