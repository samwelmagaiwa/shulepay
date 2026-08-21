<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Consolidated Header & Filter Toolbar -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2 align-items-center">
          <CCol xs="12" sm="6" md="3">
            <CFormSelect v-model="filters.school_id" @update:modelValue="page = 1; load()" style="min-height:40px;">
              <option value="">{{ t('common.allSchools') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CFormSelect v-model="filters.status" @update:modelValue="page = 1; load()" style="min-height:40px;">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option value="active">{{ t('employees.working') }}</option>
              <option value="inactive">{{ t('employees.notWorking') }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" md="4">
            <CFormInput v-model="filters.search" :placeholder="t('employees.searchPlaceholder')"
                        @input="debouncedLoad" style="min-height:40px;" />
          </CCol>
          <CCol xs="12" md="auto" class="ms-auto">
            <CButton color="success" @click="openAdd" style="min-height:40px; width:100%;">
              <CIcon icon="cilPlus" class="me-1" /> {{ t('employees.add') }}
            </CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!employees.length" class="text-center text-muted py-5">{{ t('employees.noEmployees') }}</div>
        <div v-for="emp in employees" :key="emp.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">{{ emp.full_name }}</div>
              <div class="small text-muted">{{ emp.staff_number }} &middot; {{ emp.role }}</div>
              <div class="small text-muted">{{ emp.department }}</div>
            </div>
            <CBadge :color="emp.status === 'active' ? 'success' : 'secondary'">
              {{ emp.status === 'active' ? t('employees.working') : t('employees.notWorking') }}
            </CBadge>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-2">
            <div class="fw-semibold">{{ formatMoney(emp.basic_salary_cents) }}</div>
            <div class="d-flex gap-2">
              <CButton size="sm" color="primary" variant="outline" @click="openEdit(emp)"
                       style="min-height:44px; min-width:44px;">
                <CIcon icon="cilPencil" />
              </CButton>
              <CButton size="sm" color="danger" variant="ghost" @click="confirmDelete(emp)"
                       style="min-height:44px; min-width:44px;">
                <CIcon icon="cilTrash" />
              </CButton>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination (mobile) -->
      <div v-if="meta.last_page > 1" class="d-md-none d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block">
        <CCardBody class="p-0">
          <CTable responsive hover class="mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('employees.staffNo') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('employees.fullName') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('employees.role') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('employees.department') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('employees.basicSalary') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('employees.hireDate') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="emp in employees" :key="emp.id">
                <CTableDataCell class="small">{{ emp.staff_number }}</CTableDataCell>
                <CTableDataCell class="fw-semibold">{{ emp.full_name }}</CTableDataCell>
                <CTableDataCell>{{ emp.role }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell">{{ emp.department }}</CTableDataCell>
                <CTableDataCell>{{ formatMoney(emp.basic_salary_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">{{ emp.hire_date }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="emp.status === 'active' ? 'success' : 'secondary'">
                    {{ emp.status === 'active' ? t('employees.working') : t('employees.notWorking') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell style="position:relative; min-width:56px; text-align:center;">
                  <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === emp.id ? null : emp.id">👁️</CButton>
                  <div v-if="activeRow === emp.id"
                       style="position:absolute; top:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:100; min-width:160px;"
                       @click.stop>
                    <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openView(emp); activeRow = null">👁️ {{ t('common.view') }}</CButton>
                    <CButton size="sm" color="primary" variant="ghost" class="text-start" @click="openEdit(emp); activeRow = null">✏️ {{ t('common.edit') }}</CButton>
                    <CButton size="sm" color="danger" variant="ghost" class="text-start" @click="confirmDelete(emp); activeRow = null">🗑️ {{ t('common.delete') }}</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!employees.length">
                <CTableDataCell colspan="8" class="text-center text-muted py-4">{{ t('employees.noEmployees2') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Pagination (desktop) -->
      <div v-if="meta.last_page > 1" class="d-none d-md-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- View Employee Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>👤 {{ t('employees.viewTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded">
          <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
               style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0;">
            {{ (viewTarget.full_name || '?').charAt(0).toUpperCase() }}
          </div>
          <div>
            <div class="fw-bold fs-5">{{ viewTarget.full_name }}</div>
            <div class="text-muted small">{{ viewTarget.staff_number }} · {{ viewTarget.role }}</div>
            <CBadge :color="viewTarget.status === 'active' ? 'success' : 'secondary'" class="mt-1">
              {{ viewTarget.status === 'active' ? t('employees.working') : t('employees.notWorking') }}
            </CBadge>
          </div>
        </div>
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('employees.department') }}</div>
            <div>{{ viewTarget.department || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('employees.basicSalary') }}</div>
            <div class="fw-bold text-primary">{{ formatMoney(viewTarget.basic_salary_cents) }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('employees.hireDate') }}</div>
            <div>{{ viewTarget.hire_date || '—' }}</div>
          </CCol>
          <CCol xs="12" sm="6">
            <div class="text-muted small fw-semibold mb-1">{{ t('common.school') }}</div>
            <div>{{ viewTarget.school?.name || '—' }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(viewTarget); showViewModal = false" style="min-height:44px;">✏️ {{ t('common.edit') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add/Edit Modal -->
    <CModal :visible="showModal" @close="showModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>{{ editEmployee ? t('employees.editTitle') : t('employees.addTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger">{{ formError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.staffNo') }}</label>
            <CFormInput v-model="form.staff_number" :placeholder="t('employees.staffNoPlaceholder')" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.fullName') }}</label>
            <CFormInput v-model="form.full_name" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.role') }}</label>
            <CFormInput v-model="form.role" :placeholder="t('employees.rolePlaceholder')" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.department') }}</label>
            <CFormInput v-model="form.department" :placeholder="t('employees.departmentPlaceholder')" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.basicSalary') }}</label>
            <CFormInput type="number" v-model.number="form.basic_salary_tzs" min="0" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('employees.hireDate') }}</label>
            <CFormInput type="date" v-model="form.hire_date" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('common.status') }}</label>
            <CFormSelect v-model="form.status" style="min-height:44px;">
              <option value="active">{{ t('employees.working') }}</option>
              <option value="inactive">{{ t('employees.notWorking') }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('common.school') }}</label>
            <CFormSelect v-model="form.school_id" style="min-height:44px;">
              <option value="">{{ t('employees.selectSchool') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="flex-wrap gap-2">
        <CButton color="secondary" @click="showModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" :disabled="saving || !canSubmit" @click="submitEmployee"
                 class="ms-auto" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editEmployee ? t('employees.saveChanges') : t('common.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('employees.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('employees.confirmDeleteMsg') }}</CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useEmployeesStore } from '@/stores/employees'
import { useSchoolsStore }   from '@/stores/schools'
import { useSchoolStore }    from '@/stores/school'

const { t } = useI18n()

const empStore    = useEmployeesStore()
const schoolStore = useSchoolsStore()
const navSchool   = useSchoolStore()

const employees  = ref([])
const loading    = ref(false)
const schools    = computed(() => schoolStore.schools)
const filters    = ref({ school_id: navSchool.activeSchoolId || '', status: '', search: '' })

watch(() => navSchool.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  page.value = 1
  load()
})
const page       = ref(1)
const meta       = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const showModal       = ref(false)
const showDeleteModal = ref(false)
const activeRow       = ref(null)
const showViewModal   = ref(false)
const viewTarget      = ref(null)
const saving          = ref(false)
const deleting        = ref(false)
const formError       = ref('')
const editEmployee    = ref(null)
const toDelete        = ref(null)
let   debounceTimer   = null

const form = ref({
  staff_number: '', full_name: '', role: '', department: '',
  basic_salary_tzs: '', hire_date: '', status: 'active', school_id: '',
})

const canSubmit = computed(() =>
  form.value.staff_number && form.value.full_name && form.value.role && form.value.basic_salary_tzs
)

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    if (filters.value.status)    params.status    = filters.value.status
    if (filters.value.search)    params.search    = filters.value.search
    await empStore.fetchEmployees(params)
    employees.value = empStore.employees
    meta.value = empStore.pagination || meta.value
  } catch {} finally { loading.value = false }
}

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 350)
}

function openView(emp) { viewTarget.value = emp; showViewModal.value = true }

function openAdd() {
  editEmployee.value = null
  formError.value    = ''
  form.value = { staff_number: '', full_name: '', role: '', department: '',
    basic_salary_tzs: '', hire_date: '', status: 'active', school_id: '' }
  showModal.value = true
}

function openEdit(emp) {
  editEmployee.value = emp
  formError.value    = ''
  form.value = {
    staff_number:      emp.staff_number,
    full_name:         emp.full_name,
    role:              emp.role,
    department:        emp.department || '',
    basic_salary_tzs:  Math.round((emp.basic_salary_cents || 0) / 100),
    hire_date:         emp.hire_date || '',
    status:            emp.status || 'active',
    school_id:         emp.school_id || '',
  }
  showModal.value = true
}

async function submitEmployee() {
  formError.value = ''
  saving.value    = true
  const payload = {
    staff_number:       form.value.staff_number,
    full_name:          form.value.full_name,
    role:               form.value.role,
    department:         form.value.department || undefined,
    basic_salary_cents: Math.round(form.value.basic_salary_tzs * 100),
    hire_date:          form.value.hire_date  || undefined,
    status:             form.value.status,
    school_id:          form.value.school_id  || undefined,
  }
  try {
    if (editEmployee.value) {
      await empStore.updateEmployee(editEmployee.value.id, payload)
    } else {
      await empStore.createEmployee(payload)
    }
    showModal.value = false
    await load()
  } catch (e) {
    formError.value = e?.response?.data?.message || 'Imeshindwa. Jaribu tena.'
  } finally {
    saving.value = false
  }
}

function confirmDelete(emp) { toDelete.value = emp; showDeleteModal.value = true }

async function doDelete() {
  deleting.value = true
  try {
    await empStore.deleteEmployee(toDelete.value.id)
    showDeleteModal.value = false
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa.')
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  try {
    await schoolStore.fetchSchools()
    await load()
  } catch {}
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible; }
</style>
