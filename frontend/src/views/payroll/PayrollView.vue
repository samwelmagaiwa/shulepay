<template>
  <CContainer fluid class="p-2 p-md-4">
    <CRow class="align-items-center mb-3 g-2">
      <CCol xs="12" md="">
        <h4 class="fw-bold mb-0 fs-5">{{ t('payroll.title') }}</h4>
        <p class="text-muted mb-0 small">{{ t('payroll.subtitle') }}</p>
      </CCol>
      <CCol xs="12" md="auto">
        <CButton color="success" :disabled="generating" @click="generatePayroll"
                 style="min-height:44px; width:100%;">
          <CSpinner v-if="generating" size="sm" class="me-1" />
          <CIcon icon="cilPlus" class="me-1" /> {{ t('payroll.generate') }}
        </CButton>
      </CCol>
    </CRow>

    <!-- Filters -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2">
          <CCol xs="6" sm="4" md="2">
            <CFormSelect v-model="filters.month" @update:modelValue="page = 1; load()" style="min-height:44px;">
              <option v-for="(m, i) in months" :key="i" :value="String(i+1)">{{ m }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="6" sm="4" md="2">
            <CFormInput type="number" v-model.number="filters.year" @update:modelValue="page = 1; load()"
                        min="2020" max="2030" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" sm="4" md="3">
            <CFormSelect v-model="filters.school_id" @update:modelValue="page = 1; load()" style="min-height:44px;">
              <option value="">{{ t('common.allSchools') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="4" md="2">
            <CFormSelect v-model="filters.status" @update:modelValue="page = 1; load()" style="min-height:44px;">
              <option value="">{{ t('common.allStatuses') }}</option>
              <option value="pending">{{ t('payroll.pending') }}</option>
              <option value="paid">{{ t('payroll.paid') }}</option>
            </CFormSelect>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <CAlert v-if="error" color="danger" class="mb-3">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!entries.length" class="text-center text-muted py-5">{{ t('payroll.noEntries') }}</div>
        <div v-for="e in entries" :key="e.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">{{ e.employee?.full_name }}</div>
              <div class="small text-muted">{{ e.employee?.staff_number }} &middot; {{ e.employee?.role }}</div>
            </div>
            <CBadge :color="e.status === 'paid' ? 'success' : 'warning'">
              {{ e.status === 'paid' ? t('payroll.paid') : t('payroll.pending') }}
            </CBadge>
          </div>
          <div class="d-flex justify-content-between mt-2 small">
            <div>
              <div>{{ t('payroll.basic') }}: {{ formatMoney(e.basic_salary_cents) }}</div>
              <div v-if="e.allowances_cents">{{ t('payroll.allowances') }}: +{{ formatMoney(e.allowances_cents) }}</div>
              <div v-if="e.deductions_cents">{{ t('payroll.deductions') }}: -{{ formatMoney(e.deductions_cents) }}</div>
            </div>
            <div class="text-end">
              <div class="text-muted small">{{ t('payroll.net') }}:</div>
              <div class="fw-bold fs-5">{{ formatMoney(e.net_salary_cents) }}</div>
            </div>
          </div>
          <div class="mt-2">
            <CButton v-if="e.status !== 'paid'" size="sm" color="primary"
                     @click="markPaid(e.id)" style="min-height:44px; width:100%;">
              {{ t('payroll.pay') }}
            </CButton>
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
                <CTableHeaderCell>{{ t('payroll.staffNo') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('payroll.fullName') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('payroll.role') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('payroll.basic') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('payroll.allowances') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('payroll.deductions') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('payroll.net') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="e in entries" :key="e.id">
                <CTableDataCell class="small">{{ e.employee?.staff_number }}</CTableDataCell>
                <CTableDataCell>
                  <div class="fw-semibold">{{ e.employee?.full_name }}</div>
                  <div class="small text-muted d-lg-none">{{ e.employee?.role }}</div>
                </CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">{{ e.employee?.role }}</CTableDataCell>
                <CTableDataCell>{{ formatMoney(e.basic_salary_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell text-success">{{ formatMoney(e.allowances_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell text-danger">{{ formatMoney(e.deductions_cents) }}</CTableDataCell>
                <CTableDataCell class="fw-bold">{{ formatMoney(e.net_salary_cents) }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="e.status === 'paid' ? 'success' : 'warning'">
                    {{ e.status === 'paid' ? t('payroll.paid') : t('payroll.pending') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell style="position:relative; min-width:56px; text-align:center;">
                  <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow === e.id ? null : e.id">👁️</CButton>
                  <div v-if="activeRow === e.id"
                       style="position:absolute; top:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:100; min-width:160px;"
                       @click.stop>
                    <CButton v-if="e.status !== 'paid'" size="sm" color="primary" variant="ghost" class="text-start" @click="markPaid(e.id); activeRow = null">💵 {{ t('payroll.pay') }}</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!entries.length">
                <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('payroll.noEntries') }}</CTableDataCell>
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

    <!-- Generate confirm -->
    <CModal :visible="showGenerateModal" @close="showGenerateModal = false" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('payroll.generateTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="generateError" color="danger">{{ generateError }}</CAlert>
        <p>
          {{ t('payroll.generateMsg', { month: months[filters.month - 1], year: filters.year }) }}
        </p>
        <p class="text-muted small">{{ t('payroll.generateInfo') }}</p>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showGenerateModal = false" style="min-height:44px;">{{ t('payroll.close') }}</CButton>
        <CButton color="success" :disabled="generating" @click="confirmGenerate" style="min-height:44px;">
          <CSpinner v-if="generating" size="sm" class="me-1" />{{ t('payroll.confirm') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { usePayrollStore }  from '@/stores/payroll'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'

const { t, te, tm } = useI18n()

const payrollStore = usePayrollStore()
const schoolStore  = useSchoolsStore()
const navSchool    = useSchoolStore()

const entries  = ref([])
const loading  = ref(false)
const activeRow = ref(null)
const error    = ref('')
const generating = ref(false)
const showGenerateModal = ref(false)
const generateError     = ref('')
const schools  = computed(() => schoolStore.schools)
const page     = ref(1)
const meta     = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const now = new Date()
const filters = ref({
  month: String(now.getMonth() + 1),
  year:  now.getFullYear(),
  school_id: navSchool.activeSchoolId || '',
  status: '',
})

watch(() => navSchool.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  load()
})

const monthKeys = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const months = computed(() => {
  if (te('payroll.months')) {
    const list = tm('payroll.months')
    if (Array.isArray(list) && list.length) return list
  }
  return monthKeys.map(k => t(`common.months.${k}`))
})

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const params = { month: filters.value.month, year: filters.value.year, page: page.value }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    if (filters.value.status)    params.status    = filters.value.status
    await payrollStore.fetchPayroll(params)
    entries.value = payrollStore.payroll
    meta.value = payrollStore.pagination || meta.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Imeshindwa kupakia.'
  } finally {
    loading.value = false
  }
}

function generatePayroll() {
  generateError.value  = ''
  showGenerateModal.value = true
}

async function confirmGenerate() {
  generating.value = true
  generateError.value = ''
  try {
    await payrollStore.bulkGenerate({
      month:     filters.value.month,
      year:      filters.value.year,
      school_id: filters.value.school_id || undefined,
    })
    showGenerateModal.value = false
    await load()
  } catch (e) {
    generateError.value = e?.response?.data?.message || 'Imeshindwa kutengeneza mishahara.'
  } finally {
    generating.value = false
  }
}

async function markPaid(id) {
  try {
    await payrollStore.markPaid(id)
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kulipa.')
  }
}

onMounted(async () => {
  try {
    await schoolStore.fetchSchools()
    await load()
  } catch {}
})
</script>
