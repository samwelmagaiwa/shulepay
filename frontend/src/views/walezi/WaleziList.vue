<template>
  <CContainer fluid>
    <CRow class="align-items-center mb-3">
      <CCol>
        <h4 class="fw-bold mb-0">{{ t('guardians.title') }}</h4>
      </CCol>
      <CCol xs="auto">
        <CButton color="primary" @click="openAdd">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('guardians.add') }}
        </CButton>
      </CCol>
    </CRow>

    <!-- Search -->
    <CCard class="mb-3">
      <CCardBody>
        <CRow class="g-2">
          <CCol sm="6">
            <CFormInput v-model="search" :placeholder="t('common.search') + '...'" @input="debouncedLoad" />
          </CCol>
          <CCol sm="3">
            <CButton color="secondary" variant="outline" @click="search = ''; page = 1; loadData()" class="w-100">
              {{ t('common.reset') }}
            </CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <!-- Table -->
    <CCard>
      <CCardBody class="p-0">
        <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
        <div v-else-if="!store.guardians.length" class="text-center text-muted py-5">
          {{ t('guardians.noGuardians') }}
        </div>
        <CTable v-else responsive hover class="mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('common.name') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('guardians.phone') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('guardians.relation') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('guardians.children') }}</CTableHeaderCell>
              <CTableHeaderCell></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="g in store.guardians" :key="g.id">
              <CTableDataCell class="fw-semibold">{{ g.full_name || g.user?.name || '—' }}</CTableDataCell>
              <CTableDataCell>{{ g.phone || g.user?.phone || '—' }}</CTableDataCell>
              <CTableDataCell>{{ g.relation || '—' }}</CTableDataCell>
              <CTableDataCell>
                <CBadge
                  v-for="s in g.students" :key="s.id"
                  color="info" shape="rounded-pill" class="me-1"
                >{{ s.full_name }}</CBadge>
              </CTableDataCell>
              <CTableDataCell class="text-end">
                <CButton size="sm" color="warning" variant="ghost" class="me-1" @click="openEdit(g)">
                  <CIcon icon="cilPencil" />
                </CButton>
                <CButton size="sm" color="danger" variant="ghost" @click="remove(g)">
                  <CIcon icon="cilTrash" />
                </CButton>
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
            <label class="form-label">{{ t('guardians.phone') }} *</label>
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
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useI18n } from 'vue-i18n'
import { useGuardiansStore } from '@/stores/guardians'
import { useStudentsStore }  from '@/stores/students'

const { t } = useI18n()
const store        = useGuardiansStore()
const studentsStore = useStudentsStore()

const search      = ref('')
const showModal   = ref(false)
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

async function remove(g) {
  if (!confirm(`${t('common.delete')} "${g.user?.name}"?`)) return
  try { await store.deleteGuardian(g.id) } catch {}
}

onMounted(async () => {
  await loadData()
  try {
    await studentsStore.fetchStudents()
    students.value = studentsStore.students
  } catch {}
})
</script>
