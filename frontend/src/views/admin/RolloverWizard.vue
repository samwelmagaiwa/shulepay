<template>
  <CContainer fluid class="p-2 p-md-3">
    <CRow class="align-items-center mb-3">
      <CCol>
        <h5 class="fw-bold mb-0">{{ t('rollover.title') }}</h5>
        <p class="text-muted small mb-0">{{ t('rollover.subtitle') }}</p>
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

    <!-- Step 1: Setup -->
    <CCard v-if="step === 0" class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('rollover.step1Header') }}</CCardHeader>
      <CCardBody class="p-3">
        <CRow class="g-3">
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('rollover.school') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="setup.school_id">
              <option value="">— {{ t('rollover.selectSchool') }} —</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('rollover.from') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="setup.from_year_id">
              <option value="">— {{ t('rollover.selectYear') }} —</option>
              <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6">
            <label class="form-label fw-semibold">{{ t('rollover.newYearName') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="setup.new_year_name" placeholder="e.g. 2025/2026" />
          </CCol>
        </CRow>
        <CAlert v-if="setupError" color="danger" class="mt-3">{{ setupError }}</CAlert>
      </CCardBody>
      <CCardFooter>
        <CButton color="primary" :disabled="previewing" @click="fetchPreview" style="min-height:44px; min-width:120px;">
          <CSpinner v-if="previewing" size="sm" class="me-1" />
          {{ t('rollover.previewStudents') }}
        </CButton>
      </CCardFooter>
    </CCard>

    <!-- Step 2: Students -->
    <div v-if="step === 1">
      <CCard class="mb-3">
        <CCardHeader class="d-flex justify-content-between align-items-center">
          <span class="fw-semibold">{{ t('rollover.step2Header', { count: previewStudents.length }) }}</span>
          <div class="d-flex gap-2">
            <CButton size="sm" color="success" variant="outline" @click="setAllAction('promoted')">{{ t('rollover.promoteAll') }}</CButton>
            <CButton size="sm" color="secondary" variant="outline" @click="setAllAction('graduated')">{{ t('rollover.graduateAll') }}</CButton>
          </div>
        </CCardHeader>
        <!-- Desktop table -->
        <CCardBody class="p-0 d-none d-md-block">
          <CTable responsive hover class="mb-0 align-middle">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('rollover.student') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('rollover.currentClass') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('rollover.action') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('rollover.newClass') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="(s, idx) in previewStudents" :key="s.id">
                <CTableDataCell>
                  <div class="fw-semibold">{{ s.full_name }}</div>
                  <div class="text-muted small">{{ s.admission_number }}</div>
                </CTableDataCell>
                <CTableDataCell>{{ s.school_class?.name || '—' }}</CTableDataCell>
                <CTableDataCell style="min-width:140px;">
                  <CFormSelect v-model="studentActions[idx].action" size="sm">
                    <option value="promoted">{{ t('rollover.actions.promoted') }}</option>
                    <option value="repeated">{{ t('rollover.actions.repeated') }}</option>
                    <option value="graduated">{{ t('rollover.actions.graduated') }}</option>
                    <option value="dropped">{{ t('rollover.actions.dropped') }}</option>
                  </CFormSelect>
                </CTableDataCell>
                <CTableDataCell style="min-width:140px;">
                  <CFormSelect v-if="studentActions[idx].action === 'promoted' || studentActions[idx].action === 'repeated'"
                               v-model="studentActions[idx].new_class_id" size="sm">
                    <option value="">— {{ t('rollover.selectClass') }} —</option>
                    <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                  </CFormSelect>
                  <span v-else class="text-muted small">—</span>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
        <!-- Mobile cards -->
        <CCardBody class="d-md-none">
          <div v-for="(s, idx) in previewStudents" :key="s.id" class="mb-3 p-3 border rounded">
            <div class="fw-bold mb-1">{{ s.full_name }}</div>
            <div class="text-muted small mb-2">{{ s.admission_number }} — {{ s.school_class?.name }}</div>
            <CRow class="g-2">
              <CCol xs="6">
                <CFormSelect v-model="studentActions[idx].action" size="sm">
                  <option value="promoted">{{ t('rollover.actions.promoted') }}</option>
                  <option value="repeated">{{ t('rollover.actions.repeated') }}</option>
                  <option value="graduated">{{ t('rollover.actions.graduated') }}</option>
                  <option value="dropped">{{ t('rollover.actions.dropped') }}</option>
                </CFormSelect>
              </CCol>
              <CCol xs="6">
                <CFormSelect v-if="studentActions[idx].action === 'promoted' || studentActions[idx].action === 'repeated'"
                             v-model="studentActions[idx].new_class_id" size="sm">
                  <option value="">— {{ t('rollover.selectClass') }} —</option>
                  <option v-for="c in classes" :key="c.id" :value="c.id">{{ c.name }}</option>
                </CFormSelect>
              </CCol>
            </CRow>
          </div>
        </CCardBody>
        <CCardFooter class="d-flex gap-2">
          <CButton color="secondary" variant="outline" @click="step=0">{{ t('common.back') }}</CButton>
          <CButton color="primary" @click="step=2" style="min-height:44px;">{{ t('rollover.confirm') }}</CButton>
        </CCardFooter>
      </CCard>
    </div>

    <!-- Step 3: Summary & Confirm -->
    <CCard v-if="step === 2" class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('rollover.step3Header') }}</CCardHeader>
      <CCardBody class="p-3">
        <CRow class="g-3 mb-3">
          <CCol xs="6" sm="3">
            <div class="text-center p-3 border rounded">
              <div class="fw-bold fs-4 text-success">{{ countByAction('promoted') }}</div>
              <div class="text-muted small">{{ t('rollover.actions.promoted') }}</div>
            </div>
          </CCol>
          <CCol xs="6" sm="3">
            <div class="text-center p-3 border rounded">
              <div class="fw-bold fs-4 text-warning">{{ countByAction('repeated') }}</div>
              <div class="text-muted small">{{ t('rollover.actions.repeated') }}</div>
            </div>
          </CCol>
          <CCol xs="6" sm="3">
            <div class="text-center p-3 border rounded">
              <div class="fw-bold fs-4 text-primary">{{ countByAction('graduated') }}</div>
              <div class="text-muted small">{{ t('rollover.actions.graduated') }}</div>
            </div>
          </CCol>
          <CCol xs="6" sm="3">
            <div class="text-center p-3 border rounded">
              <div class="fw-bold fs-4 text-danger">{{ countByAction('dropped') }}</div>
              <div class="text-muted small">{{ t('rollover.actions.dropped') }}</div>
            </div>
          </CCol>
        </CRow>
        <CAlert color="warning" class="mb-0">
          <strong>{{ t('common.warning') }}:</strong> {{ t('rollover.warningMsg') }}
        </CAlert>
        <CAlert v-if="executeError" color="danger" class="mt-2">{{ executeError }}</CAlert>
        <div v-if="executeResult" class="mt-3">
          <CAlert color="success">
            <strong>{{ t('common.success') }}!</strong> {{ executeResult.message }}
          </CAlert>
        </div>
      </CCardBody>
      <CCardFooter class="d-flex gap-2">
        <CButton color="secondary" variant="outline" @click="step=1" :disabled="executing">{{ t('common.back') }}</CButton>
        <CButton color="danger" :disabled="executing || !!executeResult" @click="executeRollover" style="min-height:44px; min-width:140px;">
          <CSpinner v-if="executing" size="sm" class="me-1" />
          {{ t('rollover.execute') }}
        </CButton>
      </CCardFooter>
    </CCard>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const stepLabels = computed(() => [
  t('rollover.steps.setup'),
  t('rollover.steps.students'),
  t('rollover.steps.confirm'),
])

const step = ref(0)
const schools = ref([])
const academicYears = ref([])
const classes = ref([])
const previewStudents = ref([])
const studentActions = ref([])
const previewing = ref(false)
const executing = ref(false)
const setupError = ref('')
const executeError = ref('')
const executeResult = ref(null)

const setup = ref({ school_id: '', from_year_id: '', new_year_name: '' })

function setAllAction(action) {
  studentActions.value.forEach(a => { a.action = action })
}

function countByAction(action) {
  return studentActions.value.filter(a => a.action === action).length
}

async function fetchPreview() {
  setupError.value = ''
  if (!setup.value.school_id || !setup.value.from_year_id || !setup.value.new_year_name.trim()) {
    setupError.value = 'Jaza sehemu zote zinazohitajika'
    return
  }
  previewing.value = true
  try {
    const { data } = await api.get('/rollover/preview', { params: setup.value })
    previewStudents.value = data.data || data
    studentActions.value = previewStudents.value.map(s => ({ student_id: s.id, action: 'promoted', new_class_id: '' }))
    step.value = 1
  } catch (e) {
    setupError.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia wanafunzi'
  } finally {
    previewing.value = false
  }
}

async function executeRollover() {
  executeError.value = ''
  executing.value = true
  try {
    const { data } = await api.post('/rollover/execute', {
      ...setup.value,
      students: studentActions.value,
    })
    executeResult.value = data
  } catch (e) {
    executeError.value = e?.response?.data?.message || 'Hitilafu wakati wa kutekeleza rollover'
  } finally {
    executing.value = false
  }
}

onMounted(async () => {
  try {
    const [s, y, c] = await Promise.all([
      api.get('/schools'),
      api.get('/academic-years'),
      api.get('/school-classes'),
    ])
    schools.value = s.data.data || s.data
    academicYears.value = y.data.data || y.data
    classes.value = c.data.data || c.data
  } catch {}
})
</script>
