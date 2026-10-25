<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />

    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="hasFilters" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <CButton color="success" size="sm" @click="openAdd"><CIcon icon="cilPlus" class="me-1" /> {{ t('employees.add') }}</CButton>
      </template>
      <template #filters>
        <CFormSelect v-model="filters.school_id" @update:modelValue="goPage(1)">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" @update:modelValue="goPage(1)">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="active">{{ t('employees.working') }}</option>
          <option value="inactive">{{ t('employees.notWorking') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.search" class="xg-grow" :placeholder="t('employees.searchPlaceholder')" @input="debouncedLoad" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters">{{ t('common.reset') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && openView(selected)">🔍 {{ t('common.view') }}</button>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && openEdit(selected)">✏️ {{ t('common.edit') }}</button>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && downloadAnnual(selected)">📄 {{ t('ui.employeeList.annualStatement') }}</button>
        <button class="xg-tb-btn is-danger" :disabled="!selected" @click="selected && confirmDelete(selected)">🗑️ {{ t('common.delete') }}</button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="employees" :loading="loading" :empty="t('employees.noEmployees2')"
           :min-width="980" :menu="menuFor" :row-class="rowClass" @open="openView">
      <template #cell-staff_number="{ row }"><span class="xg-mono">{{ row.staff_number }}</span></template>
      <template #cell-full_name="{ row }"><span class="xg-name">{{ row.full_name }}</span></template>
      <template #cell-basic_salary_cents="{ row }"><span class="xg-strong">{{ formatMoney(row.basic_salary_cents) }}</span></template>
      <template #cell-status="{ row }">
        <span class="xg-pill" :class="row.status === 'active' ? 'xg-pill--green' : 'xg-pill--gray'">{{ row.status === 'active' ? t('employees.working') : t('employees.notWorking') }}</span>
      </template>
    </XGrid>

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
import { useEmployeesStore } from '@/stores/employees'
import { useSchoolsStore }   from '@/stores/schools'
import { useSchoolStore }    from '@/stores/school'
import api from '@/services/api'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

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

const perPage    = ref('20')
const selectedId = ref(null)
const selected   = computed(() => employees.value.find(e => e.id === selectedId.value) || null)
const summary    = computed(() => empStore.summary || {})
const hasFilters = computed(() => !!(filters.value.status || filters.value.search || (filters.value.school_id && filters.value.school_id !== (navSchool.activeSchoolId || ''))))
const rowClass   = (e) => (e.status === 'active' ? '' : 'xg-row--bad')
const columns = computed(() => [
  { key: 'staff_number', label: t('employees.staffNo'), w: 'minmax(100px, .9fr)' },
  { key: 'full_name', label: t('employees.fullName'), w: 'minmax(170px, 2fr)' },
  { key: 'role', label: t('employees.role'), w: 'minmax(110px, 1.2fr)' },
  { key: 'department', label: t('employees.department'), w: 'minmax(110px, 1.2fr)' },
  { key: 'basic_salary_cents', label: t('employees.basicSalary'), w: 'minmax(120px, 1.2fr)', align: 'end' },
  { key: 'hire_date', label: t('employees.hireDate'), w: 'minmax(100px, 1fr)' },
  { key: 'status', label: t('common.status'), w: 'minmax(100px, .9fr)' },
])
const cards = computed(() => [
  { label: t('employees.title'), value: summary.value.total ?? meta.value.total, tone: 'gray' },
  { label: t('employees.working'), value: summary.value.active ?? 0, tone: 'green' },
  { label: t('employees.notWorking'), value: summary.value.not_working ?? 0, tone: 'amber' },
  { label: t('employees.basicSalary'), value: formatMoney(summary.value.monthly_basic_cents), tone: 'blue' },
])
const menuFor = (e) => [
  { label: '🔍 ' + t('common.view'), run: openView },
  { label: '✏️ ' + t('common.edit'), run: openEdit },
  { label: '📄 ' + t('ui.employeeList.annualStatement'), run: downloadAnnual },
  { label: '🗑️ ' + t('common.delete'), run: confirmDelete, danger: true },
]
function goPage(p) { page.value = typeof p === 'number' ? p : 1; load() }
function resetFilters() { filters.value = { school_id: navSchool.activeSchoolId || '', status: '', search: '' }; goPage(1) }

const showModal       = ref(false)
const showDeleteModal = ref(false)
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
    const params = { page: page.value, per_page: perPage.value }
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

async function downloadAnnual(emp) {
  const year = new Date().getFullYear()
  try {
    const res = await api.get(`/payroll/report/annual/${emp.id}`, { params: { year }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `annual_statement_${emp.staff_number}_${year}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    alert(t('ui.employeeList.failedToDownloadAnnualStatement'))
  }
}

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
