<template>
  <CModal :visible="visible" @close="$emit('close')" size="lg" class="modal-fullscreen-sm-down">
    <CModalHeader>
      <CModalTitle>{{ t('generateInvoice.title') }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <!-- Step indicator -->
      <div class="d-flex align-items-center mb-4">
        <div v-for="(step, idx) in steps" :key="idx" class="d-flex align-items-center flex-grow-1">
          <div class="d-flex align-items-center">
            <div
              class="rounded-circle d-flex align-items-center justify-content-center fw-bold"
              :class="currentStep > idx ? 'bg-success text-white' : currentStep === idx ? 'bg-primary text-white' : 'bg-secondary text-white'"
              style="width:32px; height:32px; font-size:0.85rem; flex-shrink:0;">
              {{ idx + 1 }}
            </div>
            <span class="ms-2 d-none d-sm-inline small fw-semibold"
                  :class="currentStep === idx ? 'text-primary' : 'text-muted'">
              {{ step }}
            </span>
          </div>
          <div v-if="idx < steps.length - 1" class="flex-grow-1 border-top mx-2" />
        </div>
      </div>

      <!-- Step 1: Configure -->
      <div v-if="currentStep === 0">
        <CAlert v-if="error" color="danger" class="mb-3">{{ error }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('generateInvoice.year') }} *</label>
            <CFormSelect v-model="form.academic_year_id" style="min-height:44px;">
              <option value="">{{ t('common.select') }}...</option>
              <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name ?? y.year }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('generateInvoice.term') }} *</label>
            <CFormSelect v-model="form.term_id" style="min-height:44px;">
              <option value="">{{ t('common.selectTerm') }}</option>
              <option v-for="trm in terms" :key="trm.id" :value="trm.id">{{ trm.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('generateInvoice.studentOptional') }}</label>
            <CFormInput v-model="form.student_search" :placeholder="t('generateInvoice.searchStudent')" @input="searchStudents" style="min-height:44px;" />
            <div v-if="studentResults.length" class="border rounded mt-1" style="max-height:180px; overflow-y:auto;">
              <div v-for="s in studentResults" :key="s.id"
                   class="px-3 py-2 cursor-pointer hover-bg"
                   style="cursor:pointer;"
                   @click="selectStudent(s)">
                {{ s.full_name }} ({{ s.admission_number }})
              </div>
            </div>
            <div v-if="form.student_id" class="mt-2">
              <CBadge color="info" class="p-2">
                {{ form.student_name }}
                <span class="ms-2 cursor-pointer" @click="clearStudent" style="cursor:pointer;">&#x2715;</span>
              </CBadge>
            </div>
          </CCol>
        </CRow>
      </div>

      <!-- Step 2: Preview -->
      <div v-if="currentStep === 1">
        <CAlert color="info" class="mb-3">
          <div class="fw-bold mb-1">{{ t('generateInvoice.summary') }}</div>
          <div>{{ t('generateInvoice.year') }}: <strong>{{ selectedYearName }}</strong></div>
          <div>{{ t('generateInvoice.term') }}: <strong>{{ selectedTermName }}</strong></div>
          <div v-if="form.student_name">{{ t('clearance.student') }}: <strong>{{ form.student_name }}</strong></div>
          <div v-else>{{ t('generateInvoice.allStudents') }}</div>
        </CAlert>
        <CAlert v-if="previewData" color="success">
          {{ t('generateInvoice.willGenerate', { count: previewData.count }) }}
        </CAlert>
        <div v-if="previewLoading" class="text-center py-3"><CSpinner size="sm" /> {{ t('common.loading') }}</div>
      </div>

      <!-- Step 3: Success -->
      <div v-if="currentStep === 2" class="text-center py-3">
        <div style="font-size:3rem;">✅</div>
        <h5 class="fw-bold mt-2">{{ t('common.success') }}!</h5>
        <p class="text-muted">{{ t('generateInvoice.successMsg', { count: result?.count || 0 }) }}</p>
      </div>
    </CModalBody>
    <CModalFooter class="flex-wrap gap-2">
      <CButton color="secondary" @click="$emit('close')" style="min-height:44px;">{{ t('common.close') }}</CButton>
      <div class="ms-auto d-flex gap-2">
        <CButton v-if="currentStep > 0 && currentStep < 2" color="light" @click="currentStep--" style="min-height:44px;">
          {{ t('common.back') }}
        </CButton>
        <CButton v-if="currentStep === 0" color="primary" :disabled="!canNext" @click="goPreview" style="min-height:44px;">
          {{ t('generateInvoice.preview') }} &rarr;
        </CButton>
        <CButton v-if="currentStep === 1" color="success" :disabled="generating" @click="doGenerate" style="min-height:44px;">
          <CSpinner v-if="generating" size="sm" class="me-1" />{{ t('generateInvoice.generate') }}
        </CButton>
        <CButton v-if="currentStep === 2" color="primary" @click="$emit('generated')" style="min-height:44px;">
          {{ t('common.done') }}
        </CButton>
      </div>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useSchoolStore } from '@/stores/school'

const { t } = useI18n()
const schoolStore = useSchoolStore()
defineProps({ visible: { type: Boolean, default: false } })
const emit = defineEmits(['close', 'generated'])

const steps = computed(() => [
  t('generateInvoice.steps.configure'),
  t('generateInvoice.steps.preview'),
  t('generateInvoice.steps.done'),
])
const currentStep = ref(0)
const error       = ref('')
const generating  = ref(false)
const previewLoading = ref(false)
const previewData = ref(null)
const result      = ref(null)
const terms       = ref([])
const studentResults = ref([])

const academicYears = ref([])

const form = ref({
  academic_year_id: '',
  term_id: '',
  student_id: '',
  student_name: '',
  student_search: '',
})

const canNext = computed(() => form.value.academic_year_id && form.value.term_id)
const selectedTermName = computed(() => {
  const trm = terms.value.find(t => String(t.id) === String(form.value.term_id))
  return trm?.name || form.value.term_id
})
const selectedYearName = computed(() => {
  const y = academicYears.value.find(y => String(y.id) === String(form.value.academic_year_id))
  return y ? (y.name ?? y.year) : form.value.academic_year_id
})

let studentTimer = null
function searchStudents() {
  clearTimeout(studentTimer)
  if (!form.value.student_search) { studentResults.value = []; return }
  studentTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/students', { params: { search: form.value.student_search, per_page: 10 } })
      studentResults.value = data.data || data
    } catch {}
  }, 300)
}
function selectStudent(s) {
  form.value.student_id   = s.id
  form.value.student_name = s.full_name
  form.value.student_search = ''
  studentResults.value = []
}
function clearStudent() {
  form.value.student_id   = ''
  form.value.student_name = ''
}

async function goPreview() {
  error.value = ''
  currentStep.value = 1
  previewLoading.value = true
  try {
    const payload = {
      academic_year_id: form.value.academic_year_id,
      term_id: form.value.term_id,
    }
    if (form.value.student_id) payload.student_id = form.value.student_id
    const { data } = await api.post('/invoices/generate-preview', payload)
    previewData.value = data
  } catch {
    previewData.value = null
  } finally {
    previewLoading.value = false
  }
}

async function doGenerate() {
  generating.value = true
  try {
    const payload = {
      academic_year_id: form.value.academic_year_id,
      term_id: form.value.term_id,
    }
    if (form.value.student_id) payload.student_id = form.value.student_id
    const { data } = await api.post('/invoices/generate', payload)
    result.value  = data
    currentStep.value = 2
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to generate invoices.'
    currentStep.value = 0
  } finally {
    generating.value = false
  }
}

import { onMounted, watch } from 'vue'

// Previously fetched /terms and /academic-years with no school scoping at
// all, so this modal listed another school's terms/years whenever the
// logged-in user's fixed home school differed from the active one selected
// in the top switcher — same bug class as the /admin/terms page.
async function loadYearsAndTerms() {
  try {
    const schoolId = schoolStore.activeSchoolId
    const params = schoolId ? { school_id: schoolId } : {}
    const [termsRes, yearsRes] = await Promise.all([
      api.get('/terms', { params }),
      api.get('/academic-years', { params }),
    ])
    terms.value = termsRes.data.data || termsRes.data
    academicYears.value = yearsRes.data.data || yearsRes.data
  } catch {}
}

onMounted(loadYearsAndTerms)
watch(() => schoolStore.activeSchoolId, () => {
  form.value.academic_year_id = ''
  form.value.term_id = ''
  loadYearsAndTerms()
})
</script>
