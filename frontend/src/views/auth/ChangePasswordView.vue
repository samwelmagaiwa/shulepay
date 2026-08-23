<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light">
    <CCard style="width:100%; max-width:420px;" class="shadow border-0">
      <CCardBody class="p-4">
        <div class="text-center mb-4">
          <div style="font-size:2rem;">🔐</div>
          <h5 class="fw-bold mb-1">{{ t('changePassword.title') }}</h5>
          <p class="text-muted small mb-0">{{ t('changePassword.subtitle') }}</p>
        </div>

        <CAlert v-if="error" color="danger" class="py-2 small">{{ error }}</CAlert>
        <CAlert v-if="success" color="success" class="py-2 small">{{ success }}</CAlert>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.current') }}</CFormLabel>
          <CFormInput v-model="form.current_password" type="password" size="sm"
            :placeholder="t('changePassword.currentPlaceholder')" autocomplete="current-password" />
          <div v-if="errors.current_password" class="text-danger small mt-1">{{ errors.current_password }}</div>
        </div>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.new') }}</CFormLabel>
          <CFormInput v-model="form.password" type="password" size="sm"
            :placeholder="t('changePassword.newPlaceholder')" autocomplete="new-password" />
          <div v-if="errors.password" class="text-danger small mt-1">{{ errors.password }}</div>
        </div>

        <div class="mb-4">
          <CFormLabel class="fw-semibold small">{{ t('changePassword.confirm') }}</CFormLabel>
          <CFormInput v-model="form.password_confirmation" type="password" size="sm"
            :placeholder="t('changePassword.confirmPlaceholder')" autocomplete="new-password" />
          <div v-if="errors.password_confirmation" class="text-danger small mt-1">{{ errors.password_confirmation }}</div>
          <div v-else-if="form.password_confirmation && form.password" class="small mt-1"
            :class="passwordsMatch ? 'text-success' : 'text-danger'">
            {{ passwordsMatch ? '✓ ' + t('changePassword.match') : '✗ ' + t('changePassword.noMatch') }}
          </div>
        </div>

        <!-- Strength indicator -->
        <div v-if="form.password" class="mb-3">
          <div class="d-flex gap-1 mb-1">
            <div v-for="i in 4" :key="i" class="flex-fill rounded" style="height:4px;"
              :style="{ background: i <= strength.score ? strength.color : '#e0e0e0' }" />
          </div>
          <div class="small" :style="{ color: strength.color }">{{ strength.label }}</div>
        </div>

        <CButton color="success" class="w-100" :disabled="saving || !canSubmit" @click="submit">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ t('changePassword.save') }}
        </CButton>
      </CCardBody>
    </CCard>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const form = ref({ current_password: '', password: '', password_confirmation: '' })
const saving = ref(false)
const error = ref('')
const success = ref('')
const errors = ref({})

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
  return { score, label: labels[score] || '', color: colors[score] || '#e0e0e0' }
})

async function submit() {
  saving.value = true
  error.value = ''
  errors.value = {}
  try {
    await api.post('/settings/change-password', form.value)
    auth.user.must_change_password = false
    const stored = JSON.parse(localStorage.getItem('shulepay_user') || '{}')
    stored.must_change_password = false
    localStorage.setItem('shulepay_user', JSON.stringify(stored))
    success.value = t('changePassword.saved')
    setTimeout(() => router.replace('/dashibodi'), 1200)
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
