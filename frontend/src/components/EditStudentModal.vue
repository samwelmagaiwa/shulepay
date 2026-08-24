<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()
const emit = defineEmits(['close', 'saved'])
const props = defineProps({
  visible: { type: Boolean, default: false },
  student: { type: Object, default: null },
})

const form = ref(initForm())
const errors = ref({})
const saving = ref(false)
const loading = ref(false)
const photoPreview = ref('')
const photoInput = ref(null)

function initForm() {
  return {
    first_name: '',
    middle_name: '',
    last_name: '',
    gender: '',
    date_of_birth: '',
    birth_certificate_no: '',
    status: '',
    address: '',
    region: '',
    district: '',
    ward: '',
    street: '',
    photo: null,
  }
}

watch(() => props.visible, (val) => {
  if (val && props.student) {
    loadStudentData()
  }
})

async function loadStudentData() {
  loading.value = true
  errors.value = {}
  try {
    const res = await api.get(`/students/${props.student.id}`)
    const student = res.data.data || res.data

    form.value = {
      first_name: student.first_name || '',
      middle_name: student.middle_name || '',
      last_name: student.last_name || '',
      gender: student.gender || '',
      date_of_birth: student.date_of_birth ? student.date_of_birth.substring(0, 10) : '',
      birth_certificate_no: student.birth_certificate_no || '',
      status: student.status || '',
      address: student.address || '',
      region: student.region || '',
      district: student.district || '',
      ward: student.ward || '',
      street: student.street || '',
      photo: null,
    }

    if (student.photo) {
      photoPreview.value = student.photo
    }
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || t('common.loadFailed')
    errors.value = { general: msg }
    console.error('Failed to load student:', msg)
  } finally {
    loading.value = false
  }
}

function onPhotoChange(e) {
  const file = e.target.files?.[0]
  if (file) {
    form.value.photo = file
    const reader = new FileReader()
    reader.onload = (event) => {
      photoPreview.value = event.target.result
    }
    reader.readAsDataURL(file)
  }
}

function removePhoto() {
  form.value.photo = null
  photoPreview.value = ''
  if (photoInput.value) photoInput.value.value = ''
}

async function submit() {
  if (!props.student?.id) {
    errors.value = { general: 'Student ID is missing' }
    return
  }

  saving.value = true
  errors.value = {}

  try {
    const formData = new FormData()

    // Add regular fields
    Object.keys(form.value).forEach(key => {
      if (key !== 'photo' && form.value[key] !== null) {
        formData.append(key, form.value[key])
      }
    })

    // Add photo if changed
    if (form.value.photo instanceof File) {
      formData.append('photo', form.value.photo)
    }

    formData.append('_method', 'PUT')

    const response = await api.post(`/students/${props.student.id}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    emit('saved')
    emit('close')
  } catch (e) {
    console.error('Save error:', e)
    const errs = e?.response?.data?.errors
    if (errs) {
      errors.value = errs
    } else {
      const msg = e?.response?.data?.message || e?.message || t('common.saveFailed')
      errors.value = { general: msg }
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <CModal :visible="visible" @close="$emit('close')" size="lg" class="modal-fullscreen-sm-down" backdrop="static" scrollable>
    <CModalHeader>
      <CModalTitle class="fw-bold">✏️ {{ t('students.edit') }}</CModalTitle>
    </CModalHeader>

    <CModalBody class="p-3">
      <!-- Error alert -->
      <CAlert v-if="errors.general" color="danger" class="mb-3">
        {{ errors.general }}
      </CAlert>

      <CRow class="g-3">
        <!-- Photo column -->
        <CCol xs="12" md="3" class="d-flex flex-column align-items-center">
          <div class="mb-2 position-relative" style="width:120px;height:120px;">
            <img
              v-if="photoPreview"
              :src="photoPreview"
              class="rounded-circle border shadow-sm"
              style="width:120px;height:120px;object-fit:cover;"
              alt="Picha"
            />
            <div v-else class="rounded-circle border d-flex align-items-center justify-content-center bg-light shadow-sm"
                 style="width:120px;height:120px;font-size:3rem;color:#adb5bd;">👤</div>
            <button v-if="photoPreview" type="button"
                    class="btn btn-sm btn-danger rounded-circle position-absolute p-0 d-flex align-items-center justify-content-center"
                    style="width:24px;height:24px;top:4px;right:4px;font-size:.7rem;"
                    @click="removePhoto" title="Ondoa picha">✕</button>
          </div>
          <label class="btn btn-outline-primary btn-sm w-100" style="cursor:pointer;">
            📷 {{ t('students.uploadPhoto') }}
            <input type="file" accept="image/*" class="d-none" @change="onPhotoChange" ref="photoInput" />
          </label>
        </CCol>

        <!-- Form fields -->
        <CCol xs="12" md="9">
          <CRow class="g-3">
            <!-- Names -->
            <CCol xs="12" sm="4">
              <label class="form-label fw-semibold">{{ t('students.firstName') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="form.first_name" :class="{'is-invalid': errors.first_name}" />
              <div v-if="errors.first_name" class="invalid-feedback d-block">{{ errors.first_name }}</div>
            </CCol>
            <CCol xs="12" sm="4">
              <label class="form-label">{{ t('students.middleName') }}</label>
              <CFormInput v-model="form.middle_name" />
            </CCol>
            <CCol xs="12" sm="4">
              <label class="form-label fw-semibold">{{ t('students.lastName') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="form.last_name" :class="{'is-invalid': errors.last_name}" />
              <div v-if="errors.last_name" class="invalid-feedback d-block">{{ errors.last_name }}</div>
            </CCol>

            <!-- Gender & DOB -->
            <CCol xs="6" md="3">
              <label class="form-label fw-semibold">{{ t('students.gender') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="form.gender" :class="{'is-invalid': errors.gender}">
                <option value="">—</option>
                <option value="male">{{ t('students.male') }}</option>
                <option value="female">{{ t('students.female') }}</option>
              </CFormSelect>
              <div v-if="errors.gender" class="invalid-feedback d-block">{{ errors.gender }}</div>
            </CCol>
            <CCol xs="6" md="3">
              <label class="form-label fw-semibold">{{ t('students.dob') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="form.date_of_birth" type="date" :class="{'is-invalid': errors.date_of_birth}" />
              <div v-if="errors.date_of_birth" class="invalid-feedback d-block">{{ errors.date_of_birth }}</div>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('students.birthCertNo') }} <span class="text-danger">*</span></label>
              <CFormInput v-model="form.birth_certificate_no" :class="{'is-invalid': errors.birth_certificate_no}" />
              <div v-if="errors.birth_certificate_no" class="invalid-feedback d-block">{{ errors.birth_certificate_no }}</div>
            </CCol>


            <!-- Status -->
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('common.status') }} <span class="text-danger">*</span></label>
              <CFormSelect v-model="form.status" :class="{'is-invalid': errors.status}">
                <option value="">—</option>
                <option value="active">{{ t('students.statuses.active') }}</option>
                <option value="transferred">{{ t('students.statuses.transferred') }}</option>
                <option value="graduated">{{ t('students.statuses.graduated') }}</option>
                <option value="dropped">{{ t('students.statuses.dropped') }}</option>
                <option value="sponsored">{{ t('students.statuses.sponsored') }}</option>
                <option value="orphaned">{{ t('students.statuses.orphaned') }}</option>
              </CFormSelect>
              <div v-if="errors.status" class="invalid-feedback d-block">{{ errors.status }}</div>
            </CCol>

            <!-- Address -->
            <CCol xs="12" sm="6">
              <label class="form-label">{{ t('common.address') }}</label>
              <CFormInput v-model="form.address" />
            </CCol>
            <CCol xs="12" sm="3">
              <label class="form-label">{{ t('common.region') }}</label>
              <CFormInput v-model="form.region" />
            </CCol>
            <CCol xs="12" sm="3">
              <label class="form-label">{{ t('common.district') }}</label>
              <CFormInput v-model="form.district" />
            </CCol>
            <CCol xs="12" sm="3">
              <label class="form-label">{{ t('common.ward') }}</label>
              <CFormInput v-model="form.ward" />
            </CCol>
            <CCol xs="12" sm="9">
              <label class="form-label">{{ t('common.street') }}</label>
              <CFormInput v-model="form.street" />
            </CCol>
          </CRow>
        </CCol>
      </CRow>
    </CModalBody>

    <CModalFooter class="gap-2">
      <CButton color="secondary" @click="$emit('close')" style="min-height:44px;" :disabled="saving">
        {{ t('common.cancel') }}
      </CButton>
      <CButton
        color="success"
        :disabled="saving || loading || !form.first_name || !form.last_name || !form.gender || !form.date_of_birth || !form.birth_certificate_no || !form.status"
        @click="submit"
        style="min-height:44px;"
      >
        <CSpinner v-if="saving" size="sm" class="me-1" />
        {{ saving ? t('common.saving') : t('common.save') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
