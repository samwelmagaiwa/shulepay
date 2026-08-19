<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()

const years    = ref([])
const schools  = ref([])
const loading  = ref(false)
const error    = ref('')

const showForm   = ref(false)
const editTarget = ref(null)
const saving     = ref(false)
const formError  = ref('')
const form       = ref(blankForm())

const confirmDelete = ref(null)
const deleting      = ref(false)

function blankForm() {
  return { name: '', school_id: '', start_date: '', end_date: '', is_current: false }
}

// superadmin can pick any school; owner uses their own school
const isSuperAdmin = computed(() => auth.isSuperAdmin)

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [yRes, sRes] = await Promise.all([
      api.get('/academic-years'),
      isSuperAdmin.value ? api.get('/schools') : Promise.resolve(null),
    ])
    years.value   = yRes.data.data ?? yRes.data
    if (sRes) schools.value = sRes.data.data ?? sRes.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Imeshindwa kupakia miaka ya masomo'
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editTarget.value = null
  form.value = blankForm()
  formError.value  = ''
  showForm.value   = true
}

function openEdit(year) {
  editTarget.value = year
  form.value = {
    name:       year.name,
    school_id:  year.school_id,
    start_date: year.start_date,
    end_date:   year.end_date,
    is_current: !!year.is_current,
  }
  formError.value = ''
  showForm.value  = true
}

async function save() {
  if (!form.value.name || !form.value.start_date || !form.value.end_date) {
    formError.value = t('common.fixErrors')
    return
  }
  if (!form.value.school_id && isSuperAdmin.value) {
    formError.value = t('academicYears.schoolRequired')
    return
  }

  saving.value = true
  formError.value = ''
  try {
    const payload = { ...form.value }
    if (!isSuperAdmin.value) delete payload.school_id

    if (editTarget.value) {
      await api.put(`/academic-years/${editTarget.value.id}`, payload)
    } else {
      await api.post('/academic-years', payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = e?.response?.data?.message || t('common.saveFailed')
    if (e?.response?.data?.errors) {
      const errs = Object.values(e.response.data.errors).flat()
      if (errs.length) formError.value = errs[0]
    }
  } finally {
    saving.value = false
  }
}

async function doDelete() {
  if (!confirmDelete.value) return
  deleting.value = true
  try {
    await api.delete(`/academic-years/${confirmDelete.value.id}`)
    confirmDelete.value = null
    await load()
  } catch (e) {
    const status = e?.response?.status
    error.value = status === 422
      ? t('academicYears.deleteWarning')
      : (e?.response?.data?.message || t('common.deleteFailed'))
  } finally {
    deleting.value = false
  }
}

function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('sw-TZ', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- Page header -->
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">
      <div>
        <h4 class="fw-bold mb-0" style="color:#007f3e;">📅 {{ t('nav.academicYears') }}</h4>
        <p class="text-muted small mb-0">{{ t('academicYears.subtitle') }}</p>
      </div>
      <CButton color="success" @click="openCreate" style="font-weight:600;">
        + {{ t('academicYears.add') }}
      </CButton>
    </div>

    <!-- Error banner -->
    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-5">
      <CSpinner color="success" />
    </div>

    <!-- Empty state -->
    <div v-else-if="!years.length" class="text-center py-5">
      <div style="font-size:3rem;">📅</div>
      <h5 class="mt-3">{{ t('academicYears.empty') }}</h5>
      <p class="text-muted">{{ t('academicYears.emptyHint') }}</p>
      <CButton color="success" @click="openCreate">+ {{ t('academicYears.addFirst') }}</CButton>
    </div>

    <!-- Year cards grid -->
    <CRow v-else class="g-3">
      <CCol v-for="year in years" :key="year.id" xs="12" md="6" lg="4">
        <CCard class="h-100 shadow-sm" style="border-radius:12px;border:none;">
          <!-- Card header with school badge + current badge -->
          <CCardHeader class="d-flex align-items-center justify-content-between py-2"
                       style="background:rgba(0,127,62,.06);border-bottom:1px solid rgba(0,127,62,.15);border-radius:12px 12px 0 0;">
            <div>
              <span class="fw-bold" style="font-size:1.05rem;color:#007f3e;">{{ year.name }}</span>
              <CBadge v-if="year.is_current" color="success" class="ms-2">{{ t('academicYears.current') }}</CBadge>
            </div>
            <div class="d-flex gap-1">
              <CButton size="sm" color="light" variant="ghost" @click="openEdit(year)" :title="t('common.edit')">✏️</CButton>
            </div>
          </CCardHeader>

          <CCardBody class="py-2 px-3">
            <!-- Dates -->
            <div class="d-flex gap-3 mb-2">
              <div>
                <div class="text-muted" style="font-size:.7rem;">{{ t('academicYears.startDate').toUpperCase() }}</div>
                <div class="small fw-semibold">{{ fmtDate(year.start_date) }}</div>
              </div>
              <div>
                <div class="text-muted" style="font-size:.7rem;">{{ t('academicYears.endDate').toUpperCase() }}</div>
                <div class="small fw-semibold">{{ fmtDate(year.end_date) }}</div>
              </div>
            </div>

            <!-- Terms list -->
            <div v-if="year.terms?.length" class="mb-1">
              <div class="text-muted mb-1" style="font-size:.7rem;">{{ t('common.term').toUpperCase() }}</div>
              <div class="d-flex flex-wrap gap-1">
                <CBadge
                  v-for="term in year.terms" :key="term.id"
                  :color="term.is_current ? 'primary' : 'secondary'"
                  class="px-2 py-1"
                  style="font-size:.72rem;"
                >
                  {{ term.name }}{{ term.is_current ? ' ●' : '' }}
                </CBadge>
              </div>
            </div>
            <div v-else class="text-muted small">{{ t('academicYears.noTerms') }}</div>
          </CCardBody>

          <CCardFooter class="py-1 px-3 d-flex justify-content-between align-items-center"
                       style="border-top:1px solid #f0f0f0;border-radius:0 0 12px 12px;">
            <span class="text-muted small">{{ year.terms?.length || 0 }} {{ t('common.term') }}</span>
            <CButton size="sm" color="danger" variant="ghost"
                     @click="confirmDelete=year" class="px-2 py-0" style="font-size:.75rem;">
              {{ t('common.delete') }}
            </CButton>
          </CCardFooter>
        </CCard>
      </CCol>
    </CRow>

    <!-- ═══════════════════════════════ CREATE/EDIT MODAL ═══════════════════════════════ -->
    <CModal :visible="showForm" @close="showForm=false" backdrop="static" size="md">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold">
          {{ editTarget ? t('academicYears.edit') : t('academicYears.add') }}
        </CModalTitle>
      </CModalHeader>

      <CModalBody>
        <CAlert v-if="formError" color="danger" class="py-2">{{ formError }}</CAlert>

        <!-- School selector (superadmin only) -->
        <div v-if="isSuperAdmin" class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.school') }} <span class="text-danger">*</span></label>
          <CFormSelect v-model="form.school_id" :class="{'is-invalid': formError && !form.school_id}">
            <option value="">— {{ t('common.selectSchool') }} —</option>
            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
        </div>

        <!-- Year name -->
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('academicYears.yearName') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="form.name" :placeholder="t('academicYears.yearNamePlaceholder')" />
          <div class="form-text">{{ t('academicYears.yearNameHint') }}</div>
        </div>

        <!-- Dates -->
        <CRow class="g-3 mb-3">
          <CCol xs="6">
            <label class="form-label fw-semibold">{{ t('academicYears.startDate') }} <span class="text-danger">*</span></label>
            <CFormInput type="date" v-model="form.start_date" />
          </CCol>
          <CCol xs="6">
            <label class="form-label fw-semibold">{{ t('academicYears.endDate') }} <span class="text-danger">*</span></label>
            <CFormInput type="date" v-model="form.end_date" />
          </CCol>
        </CRow>

        <!-- is_current toggle -->
        <div class="d-flex align-items-center gap-2 p-3 rounded"
             style="background:rgba(0,127,62,.05);border:1px solid rgba(0,127,62,.2);">
          <CFormSwitch v-model="form.is_current" id="isCurrentSwitch" />
          <div>
            <label for="isCurrentSwitch" class="fw-semibold mb-0" style="cursor:pointer;">
              {{ t('academicYears.isCurrent') }}
            </label>
            <div class="text-muted" style="font-size:.75rem;">{{ t('academicYears.isCurrentHint') }}</div>
          </div>
        </div>
      </CModalBody>

      <CModalFooter class="border-top-0">
        <CButton color="light" @click="showForm=false" :disabled="saving">{{ t('common.cancel') }}</CButton>
        <CButton color="success" @click="save" :disabled="saving" class="fw-semibold px-4">
          <CSpinner v-if="saving" size="sm" class="me-1" /> {{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ═══════════════════════════════ DELETE CONFIRM ═══════════════════════════════ -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete=null" size="sm">
      <CModalHeader><CModalTitle>{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p>{{ t('common.confirmDeleteMsg', { name: confirmDelete?.name }) }}</p>
        <CAlert color="warning" class="small py-2">
          {{ t('academicYears.deleteWarning') }}
        </CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="light" @click="confirmDelete=null" :disabled="deleting">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" @click="doDelete" :disabled="deleting">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.yesDelete') }}
        </CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
