<template>
  <CContainer fluid class="p-2 p-md-3">
    <CRow class="align-items-center mb-3">
      <CCol>
        <h5 class="fw-bold mb-0">{{ t('bulkImport.title') }}</h5>
        <p class="text-muted small mb-0">{{ t('bulkImport.subtitle') }}</p>
      </CCol>
    </CRow>

    <!-- Stepper -->
    <div class="d-flex align-items-center mb-4 px-2">
      <template v-for="(label, i) in stepLabels" :key="i">
        <div class="d-flex flex-column align-items-center">
          <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
               :class="step > i ? 'bg-success text-white' : step === i ? 'bg-primary text-white' : 'bg-light text-muted border'"
               style="width:40px; height:40px; font-size:.9rem; flex-shrink:0;">
            {{ step > i ? '✓' : i + 1 }}
          </div>
          <div class="small mt-1 d-none d-sm-block" :class="step === i ? 'text-primary fw-bold' : 'text-muted'">{{ label }}</div>
        </div>
        <div v-if="i < 2" class="flex-grow-1 mx-2" style="height:2px;"
             :class="step > i ? 'bg-success' : 'bg-light border-top'"></div>
      </template>
    </div>

    <!-- Step 1: Upload -->
    <CCard v-if="step === 0" class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('bulkImport.step1Header') }}</CCardHeader>
      <CCardBody class="p-3">
        <CRow class="g-3">
          <CCol xs="12">
            <div class="p-3 border rounded" style="background:var(--cui-tertiary-bg, #f8f9fa);">
              <div class="fw-semibold mb-2"><CIcon icon="cilCloudDownload" class="me-2" />{{ t('bulkImport.downloadTemplate') }}</div>
              <p class="text-muted small mb-2">{{ t('bulkImport.templateHint') }}</p>
              <a href="/api/bulk-import/template" class="btn btn-outline-primary btn-sm" download style="min-height:40px; display:inline-flex; align-items:center;">
                <CIcon icon="cilCloudDownload" class="me-1" /> {{ t('bulkImport.template') }}
              </a>
            </div>
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('bulkImport.uploadFile') }} <span class="text-danger">*</span></label>
            <input type="file" class="form-control" accept=".csv" ref="fileInput" @change="onFileChange" style="min-height:44px;" />
            <div class="text-muted small mt-1">{{ t('bulkImport.fileHint') }}</div>
          </CCol>
        </CRow>
        <CAlert v-if="uploadError" color="danger" class="mt-3">{{ uploadError }}</CAlert>
      </CCardBody>
      <CCardFooter>
        <CButton color="primary" :disabled="!selectedFile || previewing" @click="previewImport" style="min-height:44px; min-width:120px;">
          <CSpinner v-if="previewing" size="sm" class="me-1" />
          {{ t('bulkImport.previewData') }}
        </CButton>
      </CCardFooter>
    </CCard>

    <!-- Step 2: Preview -->
    <div v-if="step === 1">
      <!-- Valid records -->
      <CCard class="mb-3 border-success">
        <CCardHeader class="bg-success text-white fw-semibold">
          <CIcon icon="cilCheck" class="me-2" />{{ t('bulkImport.validRecords', { count: previewData.valid?.length || 0 }) }}
        </CCardHeader>
        <CCardBody class="p-0">
          <div style="overflow-x:auto; max-height:300px; overflow-y:auto;">
            <CTable responsive hover class="mb-0 align-middle" style="font-size:.82rem;">
              <CTableHead class="table-light sticky-top">
                <CTableRow>
                  <CTableHeaderCell>#</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('students.fullName') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('students.admissionNo') }}</CTableHeaderCell>
                  <CTableHeaderCell class="d-none d-md-table-cell">{{ t('students.gender') }}</CTableHeaderCell>
                  <CTableHeaderCell class="d-none d-md-table-cell">{{ t('common.class') }}</CTableHeaderCell>
                  <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('students.guardian') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="(r, i) in previewData.valid" :key="i">
                  <CTableDataCell>{{ i + 1 }}</CTableDataCell>
                  <CTableDataCell>{{ r.full_name }}</CTableDataCell>
                  <CTableDataCell>{{ r.admission_number }}</CTableDataCell>
                  <CTableDataCell class="d-none d-md-table-cell">{{ r.gender }}</CTableDataCell>
                  <CTableDataCell class="d-none d-md-table-cell">{{ r.class_name || r.class_id }}</CTableDataCell>
                  <CTableDataCell class="d-none d-lg-table-cell">{{ r.guardian_name }}</CTableDataCell>
                </CTableRow>
                <CTableRow v-if="!previewData.valid?.length">
                  <CTableDataCell colspan="6" class="text-center text-muted">{{ t('bulkImport.noValidRecords') }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>
        </CCardBody>
      </CCard>

      <!-- Error records -->
      <CCard v-if="previewData.errors?.length" class="mb-3 border-danger">
        <CCardHeader class="bg-danger text-white fw-semibold">
          <CIcon icon="cilXCircle" class="me-2" />{{ t('bulkImport.errorRecords', { count: previewData.errors.length }) }}
        </CCardHeader>
        <CCardBody class="p-0">
          <div style="overflow-x:auto; max-height:200px; overflow-y:auto;">
            <CTable responsive class="mb-0" style="font-size:.82rem;">
              <CTableHead class="table-light sticky-top">
                <CTableRow>
                  <CTableHeaderCell>{{ t('bulkImport.row') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.name') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('bulkImport.error') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="(e, i) in previewData.errors" :key="i" class="table-danger">
                  <CTableDataCell>{{ e.row }}</CTableDataCell>
                  <CTableDataCell>{{ e.full_name }}</CTableDataCell>
                  <CTableDataCell>{{ e.error }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>
        </CCardBody>
      </CCard>

      <div class="d-flex gap-2">
        <CButton color="secondary" variant="outline" @click="step=0">{{ t('common.back') }}</CButton>
        <CButton color="success" :disabled="!previewData.valid?.length || importing" @click="executeImport" style="min-height:44px; min-width:140px;">
          <CSpinner v-if="importing" size="sm" class="me-1" />
          {{ t('bulkImport.importN', { count: previewData.valid?.length }) }}
        </CButton>
      </div>
    </div>

    <!-- Step 3: Done -->
    <CCard v-if="step === 2" class="mb-3">
      <CCardBody class="p-4 text-center">
        <div style="font-size:3rem; color:#198754;" class="mb-3">✓</div>
        <h4 class="text-success fw-bold">{{ t('common.success') }}!</h4>
        <p class="text-muted mb-3">{{ importResult?.message }}</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
          <div class="text-center">
            <div class="fw-bold fs-3 text-success">{{ importResult?.imported || 0 }}</div>
            <div class="text-muted small">{{ t('bulkImport.imported') }}</div>
          </div>
          <div class="text-center" v-if="importResult?.skipped">
            <div class="fw-bold fs-3 text-warning">{{ importResult.skipped }}</div>
            <div class="text-muted small">{{ t('bulkImport.skipped') }}</div>
          </div>
        </div>
        <CButton color="primary" class="mt-4" @click="resetWizard">{{ t('bulkImport.importMore') }}</CButton>
      </CCardBody>
    </CCard>

    <CAlert v-if="importError" color="danger">{{ importError }}</CAlert>
  </CContainer>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const stepLabels = computed(() => [
  t('bulkImport.steps.upload'),
  t('bulkImport.steps.review'),
  t('bulkImport.steps.done'),
])

const step = ref(0)
const selectedFile = ref(null)
const fileInput = ref(null)
const previewing = ref(false)
const importing = ref(false)
const uploadError = ref('')
const importError = ref('')
const previewData = ref({ valid: [], errors: [] })
const importResult = ref(null)

function onFileChange(e) {
  selectedFile.value = e.target.files[0] || null
  uploadError.value = ''
}

async function previewImport() {
  uploadError.value = ''
  previewing.value = true
  try {
    const fd = new FormData()
    fd.append('file', selectedFile.value)
    const { data } = await api.post('/bulk-import/preview', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    previewData.value = data.data || data
    step.value = 1
  } catch (e) {
    uploadError.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia faili'
  } finally {
    previewing.value = false
  }
}

async function executeImport() {
  importError.value = ''
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', selectedFile.value)
    const { data } = await api.post('/bulk-import/import', fd, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    importResult.value = data.data || data
    step.value = 2
  } catch (e) {
    importError.value = e?.response?.data?.message || 'Hitilafu wakati wa kuingiza data'
  } finally {
    importing.value = false
  }
}

function resetWizard() {
  step.value = 0
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
  previewData.value = { valid: [], errors: [] }
  importResult.value = null
  uploadError.value = ''
  importError.value = ''
}
</script>
