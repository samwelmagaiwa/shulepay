<template>
  <CContainer fluid class="py-3 px-3" style="max-width:500px;">

    <h5 class="fw-bold mb-3">🔐 {{ t('changePassword.title') }}</h5>

    <CCard class="border-0 shadow-sm">
      <CCardBody class="p-4">
        <CAlert v-if="error" color="danger" class="py-2 small" dismissible @close="error=''">{{ error }}</CAlert>
        <CAlert v-if="success" color="success" class="py-2 small" dismissible @close="success=''">{{ success }}</CAlert>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.current') }} <span class="text-danger">*</span></CFormLabel>
          <CFormInput v-model="form.current_password" type="password" size="sm"
            autocomplete="current-password" :placeholder="t('changePassword.currentPlaceholder')" />
          <div v-if="errors.current_password" class="text-danger small mt-1">{{ errors.current_password }}</div>
        </div>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.new') }} <span class="text-danger">*</span></CFormLabel>
          <CFormInput v-model="form.password" type="password" size="sm"
            autocomplete="new-password" :placeholder="t('changePassword.newPlaceholder')" />
          <div v-if="errors.password" class="text-danger small mt-1">{{ errors.password }}</div>

          <!-- Strength bar -->
          <div v-if="form.password" class="mt-2">
            <div class="d-flex gap-1 mb-1">
              <div v-for="i in 4" :key="i" class="flex-fill rounded" style="height:4px;"
                :style="{ background: i <= strength.score ? strength.color : '#e0e0e0' }" />
            </div>
            <div class="small" :style="{ color: strength.color }">{{ strength.label }}</div>
          </div>
        </div>

        <div class="mb-4">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.confirm') }} <span class="text-danger">*</span></CFormLabel>
          <CFormInput v-model="form.password_confirmation" type="password" size="sm"
            autocomplete="new-password" :placeholder="t('changePassword.confirmPlaceholder')" />
          <div v-if="errors.password_confirmation" class="text-danger small mt-1">{{ errors.password_confirmation }}</div>
          <div v-else-if="form.password_confirmation && form.password" class="small mt-1"
            :class="passwordsMatch ? 'text-success' : 'text-danger'">
            {{ passwordsMatch ? '✓ ' + t('changePassword.match') : '✗ ' + t('changePassword.noMatch') }}
          </div>
        </div>

        <CButton color="success" size="sm" :disabled="saving || !canSubmit" @click="submit">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ t('changePassword.save') }}
        </CButton>
      </CCardBody>
    </CCard>

  </CContainer>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const form    = ref({ current_password: '', password: '', password_confirmation: '' })
const saving  = ref(false)
const error   = ref('')
const success = ref('')
const errors  = ref({})

const passwordsMatch = computed(() =>
  form.value.password && form.value.password_confirmation &&
  form.value.password === form.value.password_confirmation
)

const canSubmit = computed(() =>
  form.value.current_password && form.value.password &&
  form.value.password_confirmation && passwordsMatch.value
)

const strength = computed(() => {
  const p = form.value.password
  let score = 0
  if (p.length >= 8) score++
  if (/[A-Z]/.test(p)) score++
  if (/[0-9]/.test(p)) score++
  if (/[^A-Za-z0-9]/.test(p)) score++
  const labels = ['', t('changePassword.weak'), t('changePassword.fair'), t('changePassword.good'), t('changePassword.strong')]
  const colors = ['', '#dc3545', '#fd7e14', '#ffc107', '#198754']
  return { score, label: labels[score] || '', color: colors[score] || '#ccc' }
})

async function submit() {
  saving.value = true
  error.value  = ''
  errors.value = {}
  try {
    await api.post('/settings/change-password', form.value)
    success.value = t('changePassword.saved')
    form.value = { current_password: '', password: '', password_confirmation: '' }
  } catch (e) {
    const errs = e?.response?.data?.errors
    if (errs) {
      errors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]))
    } else {
      error.value = e?.response?.data?.message || t('common.saveError')
    }
  } finally {
    saving.value = false
  }
}
</script>
