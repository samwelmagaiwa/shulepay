<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Search Form Toolbar -->
    <CCard class="mb-3" style="overflow: visible;">
      <CCardBody class="p-3" style="overflow: visible;">
        <CRow class="g-2 align-items-end">
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold small mb-1">{{ t('clearance.searchStudent') }}</label>
            <div class="position-relative">
              <CFormInput v-model="searchQuery" :placeholder="t('clearance.searchPlaceholder')" @input="debouncedSearch" style="min-height:40px;" autocomplete="off" />
              <div v-if="searchResults.length" class="border rounded shadow-sm position-absolute w-100 bg-white"
                   style="z-index:1055; max-height:220px; overflow-y:auto; top:100%; left:0;">
                <div v-for="s in searchResults" :key="s.id"
                     class="p-2 border-bottom" style="cursor:pointer;"
                     @click="selectStudent(s)">
                  <div class="fw-semibold">{{ s.full_name }}</div>
                  <div class="small text-muted">{{ s.admission_number }} — {{ s.school_class?.name }}</div>
                </div>
              </div>
            </div>
          </CCol>
          <CCol xs="12" sm="6" md="4">
            <label class="form-label fw-semibold small mb-1">{{ t('common.year') }}</label>
            <CFormSelect v-model="selectedYear" style="min-height:40px;">
              <option value="">— {{ t('common.selectYear') }} —</option>
              <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CButton color="primary" class="w-100" :disabled="!selectedStudent || checking"
                     @click="checkClearance" style="min-height:40px;">
              <CSpinner v-if="checking" size="sm" class="me-1" />
              {{ t('clearance.check') }}
            </CButton>
          </CCol>
        </CRow>

        <!-- Selected student chip -->
        <div v-if="selectedStudent" class="mt-2 d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-primary text-white small">
          <CIcon icon="cilUser" />
          {{ selectedStudent.full_name }} ({{ selectedStudent.admission_number }})
          <span style="cursor:pointer;" @click="clearStudent">✕</span>
        </div>
      </CCardBody>
    </CCard>

    <!-- Result -->
    <div v-if="result">
      <!-- Cleared -->
      <CCard v-if="result.cleared" class="border-success mb-3">
        <CCardBody class="p-4 text-center">
          <div class="mb-3" style="font-size:4rem; color:#198754;">✓</div>
          <h4 class="text-success fw-bold">{{ t('clearance.clearedTitle') }}</h4>
          <p class="text-muted mb-0">{{ t('clearance.clearedMsg', { name: selectedStudent?.full_name }) }}</p>
          <div class="mt-3">
            <CBadge color="success" class="px-3 py-2" style="font-size:.9rem;">{{ t('clearance.cleared') }}</CBadge>
          </div>
          <CButton color="success" class="mt-4" :disabled="issuing" @click="issueCertificate" style="min-height:44px; min-width:160px;">
            <CSpinner v-if="issuing" size="sm" class="me-1" />
            <CIcon v-else icon="cilCloudDownload" class="me-1" />
            {{ t('clearance.downloadCert') }}
          </CButton>
        </CCardBody>
      </CCard>

      <!-- Not Cleared -->
      <CCard v-else class="border-danger mb-3">
        <CCardBody class="p-4 text-center">
          <div class="mb-3" style="font-size:4rem; color:#dc3545;">✗</div>
          <h4 class="text-danger fw-bold">{{ t('clearance.notClearedTitle') }}</h4>
          <p class="text-muted">{{ t('clearance.notClearedMsg', { name: selectedStudent?.full_name }) }}</p>
        </CCardBody>

        <!-- Outstanding invoices -->
        <CTable responsive class="mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('invoices.invoiceNo') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.total') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('invoices.amountPaid') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('invoices.debt') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="inv in result.outstanding_invoices" :key="inv.id">
              <CTableDataCell class="small">{{ inv.invoice_number }}</CTableDataCell>
              <CTableDataCell class="small">{{ inv.term?.name }}</CTableDataCell>
              <CTableDataCell>{{ formatTZS(inv.total_amount_cents) }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell text-success">{{ formatTZS(inv.paid_cents) }}</CTableDataCell>
              <CTableDataCell class="fw-bold text-danger">{{ formatTZS(inv.balance_due_cents) }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>

        <CCardFooter class="text-center">
          <div class="fw-bold text-danger fs-5">
            {{ t('clearance.totalDebt') }}: {{ formatTZS(result.total_outstanding_cents) }}
          </div>
        </CCardFooter>
      </CCard>
    </div>

    <CAlert v-if="checkError" color="danger">{{ checkError }}</CAlert>
  </CContainer>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const searchQuery = ref('')
const searchResults = ref([])
const selectedStudent = ref(null)
const selectedYear = ref('')
const academicYears = ref([])
const result = ref(null)
const checking = ref(false)
const issuing = ref(false)
const checkError = ref('')
let srDebouncer = null

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

function debouncedSearch() {
  clearTimeout(srDebouncer)
  srDebouncer = setTimeout(async () => {
    if (!searchQuery.value.trim()) { searchResults.value = []; return }
    try {
      const { data } = await api.get('/students', { params: { search: searchQuery.value, per_page: 10 } })
      searchResults.value = data.data || data
    } catch { searchResults.value = [] }
  }, 350)
}

function selectStudent(s) {
  selectedStudent.value = s
  searchQuery.value = s.full_name
  searchResults.value = []
  result.value = null
}

function clearStudent() {
  selectedStudent.value = null
  searchQuery.value = ''
  result.value = null
}

async function checkClearance() {
  checkError.value = ''
  result.value = null
  checking.value = true
  try {
    const params = { student_id: selectedStudent.value.id }
    if (selectedYear.value) params.academic_year_id = selectedYear.value
    const { data } = await api.get('/clearance/check', { params })
    result.value = data.data || data
  } catch (e) {
    checkError.value = e?.response?.data?.message || 'Hitilafu wakati wa kuangalia hali'
  } finally {
    checking.value = false
  }
}

async function issueCertificate() {
  issuing.value = true
  try {
    const params = { student_id: selectedStudent.value.id }
    if (selectedYear.value) params.academic_year_id = selectedYear.value
    const response = await api.post('/clearance/issue', params, { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
  } catch (e) {
    checkError.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakua hati'
  } finally {
    issuing.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/academic-years')
    academicYears.value = data.data || data
  } catch {}
})
</script>
