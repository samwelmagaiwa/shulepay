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
      <div v-if="result.cleared">
        <!-- Status bar -->
        <CCard class="border-success mb-3">
          <CCardBody class="p-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
              <div style="font-size:2rem; color:#198754; line-height:1;">✓</div>
              <div>
                <div class="fw-bold text-success" style="font-size:1.1rem;">{{ t('clearance.clearedTitle') }}</div>
                <div class="text-muted small">{{ t('clearance.clearedMsg', { name: selectedStudent?.full_name }) }}</div>
              </div>
            </div>
            <div class="d-flex gap-2">
              <CButton color="outline-success" size="sm" :disabled="issuing" @click="loadPreview" style="min-height:38px;">
                <CSpinner v-if="issuing && !pdfUrl" size="sm" class="me-1" />
                <CIcon v-else icon="cilMagnifyingGlass" class="me-1" />
                Preview
              </CButton>
              <CButton color="success" size="sm" :disabled="issuing" @click="downloadCert" style="min-height:38px;">
                <CSpinner v-if="issuing && pdfUrl" size="sm" class="me-1" />
                <CIcon v-else icon="cilCloudDownload" class="me-1" />
                {{ t('clearance.downloadCert') }}
              </CButton>
            </div>
          </CCardBody>
        </CCard>

        <!-- 3D Certificate Preview -->
        <div v-if="pdfUrl" class="cert-stage mb-4">
          <div class="cert-3d">
            <iframe :src="pdfUrl" class="cert-frame" frameborder="0"></iframe>
          </div>
        </div>
      </div>

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

<style scoped>
.cert-stage {
  perspective: 1200px;
  display: flex;
  justify-content: center;
  padding: 32px 0 48px;
  background: linear-gradient(160deg, #e8f5e9 0%, #f0f4fb 100%);
  border-radius: 16px;
}
.cert-3d {
  transform: rotateX(4deg) rotateY(-3deg) scale(1);
  transform-origin: center center;
  transition: transform 0.4s ease, box-shadow 0.4s ease;
  box-shadow:
    0 20px 60px rgba(0,0,0,0.22),
    0 6px 18px rgba(0,0,0,0.14),
    6px 12px 32px rgba(26,60,110,0.18);
  border-radius: 4px;
  background: #fff;
}
.cert-3d:hover {
  transform: rotateX(0deg) rotateY(0deg) scale(1.02);
  box-shadow:
    0 28px 70px rgba(0,0,0,0.26),
    0 8px 24px rgba(0,0,0,0.16);
}
.cert-frame {
  width: 794px;
  height: 1123px;
  max-width: 90vw;
  max-height: 75vh;
  display: block;
  border: none;
  border-radius: 4px;
}
</style>

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
const pdfUrl = ref(null)
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
  pdfUrl.value = null
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

async function fetchPdf() {
  const params = { student_id: selectedStudent.value.id }
  if (selectedYear.value) params.academic_year_id = selectedYear.value
  const response = await api.post('/clearance/issue', params, { responseType: 'blob' })
  return window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
}

async function loadPreview() {
  if (pdfUrl.value) { pdfUrl.value = null; return } // toggle off
  issuing.value = true
  checkError.value = ''
  try {
    pdfUrl.value = await fetchPdf()
  } catch (e) {
    checkError.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakua hati'
  } finally {
    issuing.value = false
  }
}

async function downloadCert() {
  issuing.value = true
  checkError.value = ''
  try {
    const url = pdfUrl.value || await fetchPdf()
    const a = document.createElement('a')
    a.href = url
    a.download = `clearance-${selectedStudent.value?.full_name?.replace(/\s+/g, '-')}.pdf`
    a.click()
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
