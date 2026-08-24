<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useSchoolStore } from '@/stores/school'

const { t } = useI18n()
const schoolStore = useSchoolStore()

const terms        = ref([])
const academicYears= ref([])
const loading      = ref(false)
const error        = ref('')

const showForm   = ref(false)
const editTarget = ref(null)
const saving     = ref(false)
const formError  = ref('')
const form       = ref(blankForm())

const confirmDelete = ref(null)

function blankForm() {
  return { academic_year_id: '', name: '', number: '', start_date: '', end_date: '', is_current: false }
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const schoolId = schoolStore.activeSchoolId
    const ayRes = await api.get('/academic-years', { params: schoolId ? { school_id: schoolId } : {} })
    const ay = ayRes.data.data ?? ayRes.data
    academicYears.value = ay

    // fetch each year's terms in parallel — one failing year must not blank the whole list
    const results = await Promise.allSettled(
      ay.map(y => api.get('/terms', { params: { academic_year_id: y.id } })),
    )
    const allTerms = []
    results.forEach((res, i) => {
      if (res.status !== 'fulfilled') return
      const rows = res.value.data.data ?? res.value.data ?? []
      rows.forEach(t => allTerms.push({ ...t, academic_year: ay[i] }))
    })
    terms.value = allTerms

    // Every request failing must not look like "no terms exist"
    const firstFailure = results.find(r => r.status === 'rejected')
    if (results.length && firstFailure && !results.some(r => r.status === 'fulfilled')) {
      error.value = firstFailure.reason?.response?.data?.message || t('common.loadFailed')
    }
  } catch (e) {
    error.value = e?.response?.data?.message || t('common.loadFailed')
  } finally {
    loading.value = false
  }
}

// Reload terms when school changes
import { watch } from 'vue'
watch(() => schoolStore.activeSchoolId, () => {
  load()
}, { immediate: false })

onMounted(load)

function openCreate() {
  editTarget.value = null
  form.value       = blankForm()
  formError.value  = ''
  showForm.value   = true
}

function openEdit(term) {
  editTarget.value = term
  form.value = {
    academic_year_id: term.academic_year_id,
    name:       term.name,
    number:     term.number,
    start_date: term.start_date?.substring(0, 10) ?? '',
    end_date:   term.end_date?.substring(0, 10) ?? '',
    is_current: !!term.is_current,
  }
  formError.value = ''
  showForm.value  = true
}

async function saveTerm() {
  saving.value    = true
  formError.value = ''
  try {
    const payload = { ...form.value, number: parseInt(form.value.number) }
    if (editTarget.value) {
      await api.put(`/terms/${editTarget.value.id}`, payload)
    } else {
      await api.post('/terms', payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    const errs = e?.response?.data?.errors
    formError.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e?.response?.data?.message || t('common.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function doDelete() {
  if (!confirmDelete.value) return
  const targetId = confirmDelete.value.id
  confirmDelete.value = null
  try {
    await api.delete(`/terms/${targetId}`)
  } catch (e) {
    const status = e?.response?.status
    if (status !== 204) {
      error.value = status === 422
        ? t('terms.deleteWarning')
        : (e?.response?.data?.message || t('common.deleteFailed'))
    }
  } finally {
    await load()
  }
}

function termNumberLabel(n) {
  return ['', 'Kwanza', 'Pili', 'Tatu', 'Nne'][n] ?? n
}
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- Toolbar -->
    <CCard class="border-0 shadow-sm mb-3">
      <CCardBody class="py-2">
        <div class="d-flex align-items-center justify-content-end">
          <CButton color="success" size="sm" @click="openCreate" class="text-nowrap">
            + {{ t('terms.add') }}
          </CButton>
        </div>
      </CCardBody>
    </CCard>

    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>
    <CAlert v-if="!loading && academicYears.length === 0" color="info" class="d-flex align-items-center gap-2">
      <span>📅</span>
      <span>{{ t('terms.selectYear') }} — <router-link to="/admin/academic-years" class="alert-link">{{ t('academicYears.add') }}</router-link></span>
    </CAlert>
    <div v-if="loading" class="text-center py-5"><CSpinner /></div>

    <CCard v-else class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <div class="table-responsive">
          <CTable hover class="align-middle mb-0">
            <CTableHead class="table-dark">
              <CTableRow>
                <CTableHeaderCell>{{ t('common.name') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('terms.number') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('terms.academicYear') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('terms.startDate') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('terms.endDate') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="term in terms" :key="term.id">
                <CTableDataCell class="fw-semibold">{{ term.name }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge color="primary">{{ t('terms.termNumber', { n: term.number }) }}</CBadge>
                </CTableDataCell>
                <CTableDataCell class="text-muted small">{{ term.academic_year?.name ?? '—' }}</CTableDataCell>
                <CTableDataCell>{{ term.start_date?.substring(0, 10) ?? '—' }}</CTableDataCell>
                <CTableDataCell>{{ term.end_date?.substring(0, 10) ?? '—' }}</CTableDataCell>
                <CTableDataCell class="text-center">
                  <CBadge :color="term.is_current ? 'success' : 'secondary'">
                    {{ term.is_current ? '✓ ' + t('terms.current') : t('terms.past') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    <CButton size="sm" color="warning" variant="ghost" @click="openEdit(term)">✏️</CButton>
                    <CButton size="sm" color="danger" variant="ghost" @click="confirmDelete = term">🗑️</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!terms.length">
                <CTableDataCell colspan="7" class="text-center py-4">
                  <div class="text-muted">
                    <p>{{ t('terms.empty') }}</p>
                    <small v-if="academicYears.length === 0" class="text-warning">
                      💡 {{ t('academicYears.add') }} {{ t('terms.termNamePlaceholder', { count: 1 }).toLowerCase() }}
                    </small>
                  </div>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

    <!-- Create / Edit Modal -->
    <CModal :visible="showForm" @close="showForm=false" backdrop="static">
      <CModalHeader>
        <CModalTitle>{{ editTarget ? t('terms.edit') : t('terms.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="small py-2">{{ formError }}</CAlert>

        <CRow class="g-3">
          <CCol md="12" v-if="!editTarget">
            <CFormLabel class="fw-semibold">{{ t('terms.academicYear') }} <span class="text-danger">*</span></CFormLabel>
            <CFormSelect v-model="form.academic_year_id">
              <option value="">— {{ t('terms.selectYear') }} —</option>
              <option v-for="ay in academicYears" :key="ay.id" :value="ay.id">{{ ay.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="8">
            <CFormLabel class="fw-semibold">{{ t('terms.termName') }} <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.name" :placeholder="t('terms.termNamePlaceholder')" />
          </CCol>
          <CCol md="4">
            <CFormLabel class="fw-semibold">{{ t('terms.number') }} <span class="text-danger">*</span></CFormLabel>
            <CFormSelect v-model="form.number">
              <option value="">—</option>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">{{ t('terms.startDate') }} <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.start_date" type="date" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">{{ t('terms.endDate') }} <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.end_date" type="date" />
          </CCol>
          <CCol md="12">
            <CFormCheck v-model="form.is_current" :label="t('terms.isCurrent')" />
            <div class="text-muted small mt-1">{{ t('terms.isCurrentHint') }}</div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showForm=false">{{ t('common.cancel') }}</CButton>
        <CButton
          color="success"
          :disabled="saving || !form.name || !form.number || !form.start_date || !form.end_date || (!editTarget && !form.academic_year_id)"
          @click="saveTerm"
        >
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editTarget ? t('common.save') : t('terms.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete=null">
      <CModalHeader><CModalTitle class="text-danger">{{ t('terms.deleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p>{{ t('terms.confirmDeleteMsg', { name: confirmDelete?.name }) }}</p>
        <CAlert color="warning" class="small py-2">{{ t('terms.deleteWarning') }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="confirmDelete=null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" @click="doDelete">{{ t('common.yesDelete') }}</CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
