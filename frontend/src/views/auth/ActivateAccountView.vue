<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()
const router = useRouter()

// 1 = enter the code from the SMS, 2 = choose the password, 3 = done
const step = ref(1)
const identifier = ref('')
const code = ref('')
const token = ref('')
const password = ref('')
const confirmation = ref('')
const busy = ref(false)
const error = ref('')
const info = ref('')
const fieldErrors = ref({})
const cooldown = ref(0)
let timer = null

const codeValid = computed(() => /^\d{6}$/.test(code.value))
const canVerify = computed(() => identifier.value.trim().length >= 5 && codeValid.value)

const rules = computed(() => [
  { ok: password.value.length >= 8, label: t('activate.ruleLength') },
  { ok: /[A-Za-z]/.test(password.value), label: t('activate.ruleLetter') },
  { ok: /\d/.test(password.value), label: t('activate.ruleNumber') },
])
const strongEnough = computed(() => rules.value.every(r => r.ok))
const matches = computed(() => !!password.value && password.value === confirmation.value)
const canSave = computed(() => strongEnough.value && matches.value)

const strength = computed(() => {
  const p = password.value
  const score = [p.length >= 8, /[A-Z]/.test(p) && /[a-z]/.test(p), /\d/.test(p), /[^A-Za-z0-9]/.test(p)].filter(Boolean).length
  const colors = ['#e0e0e0', '#dc3545', '#fd7e14', '#ffc107', '#198754']
  const labels = ['', t('changePassword.weak'), t('changePassword.fair'), t('changePassword.good'), t('changePassword.strong')]
  return { score, color: colors[score], label: labels[score] }
})

function startCooldown() {
  cooldown.value = 60
  clearInterval(timer)
  timer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) clearInterval(timer)
  }, 1000)
}
onBeforeUnmount(() => clearInterval(timer))

function messageOf(e, fallback) {
  if (e?.response?.status === 429) return t('activate.tooMany')
  return e?.response?.data?.message || fallback
}

async function sendCode() {
  if (identifier.value.trim().length < 5 || cooldown.value > 0) return
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    const { data } = await api.post('/auth/activate/resend', { identifier: identifier.value.trim() })
    info.value = data.message
    startCooldown()
  } catch (e) {
    error.value = messageOf(e, t('common.saveError'))
  } finally {
    busy.value = false
  }
}

async function verifyCode() {
  if (!canVerify.value) return
  busy.value = true
  error.value = ''
  info.value = ''
  try {
    const { data } = await api.post('/auth/activate/verify', { identifier: identifier.value.trim(), code: code.value })
    token.value = data.token
    step.value = 2
  } catch (e) {
    error.value = messageOf(e, t('activate.codeWrong'))
  } finally {
    busy.value = false
  }
}

async function savePassword() {
  if (!canSave.value) return
  busy.value = true
  error.value = ''
  fieldErrors.value = {}
  try {
    await api.post('/auth/activate/complete', {
      token: token.value,
      password: password.value,
      password_confirmation: confirmation.value,
    })
    step.value = 3
    setTimeout(() => router.replace('/login'), 2500)
  } catch (e) {
    const errs = e?.response?.data?.errors
    if (errs) {
      fieldErrors.value = Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, v[0]]))
    } else {
      error.value = messageOf(e, t('common.saveError'))
      if (e?.response?.data?.code === 'ACTIVATION_EXPIRED') {
        step.value = 1
        token.value = ''
        code.value = ''
      }
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center bg-light p-3">
    <CCard style="width:100%; max-width:440px;" class="shadow border-0">
      <CCardBody class="p-4">
        <div class="text-center mb-4">
          <div style="font-size:2rem;">{{ step === 3 ? '✅' : step === 2 ? '🔐' : '📱' }}</div>
          <h5 class="fw-bold mb-1">{{ t('activate.title') }}</h5>
          <p class="text-muted small mb-0">{{ t(`activate.subtitle${step}`) }}</p>
        </div>

        <!-- progress -->
        <div v-if="step < 3" class="d-flex gap-2 mb-4" aria-hidden="true">
          <div v-for="n in 2" :key="n" class="flex-fill rounded" style="height:4px;"
            :style="{ background: n <= step ? '#198754' : '#dee2e6' }" />
        </div>

        <CAlert v-if="error" color="danger" class="py-2 small" role="alert">{{ error }}</CAlert>
        <CAlert v-if="info" color="success" class="py-2 small">{{ info }}</CAlert>

        <!-- Step 1: the code from the SMS -->
        <form v-if="step === 1" @submit.prevent="verifyCode">
          <div class="mb-3">
            <CFormLabel class="fw-semibold small" for="act-id">{{ t('activate.identifier') }}</CFormLabel>
            <CFormInput id="act-id" v-model="identifier" size="sm" autocomplete="username" inputmode="email"
              :placeholder="t('activate.identifierPlaceholder')" />
          </div>
          <div class="mb-2">
            <CFormLabel class="fw-semibold small" for="act-code">{{ t('activate.code') }}</CFormLabel>
            <CFormInput id="act-code" v-model="code" size="sm" inputmode="numeric" maxlength="6" autocomplete="one-time-code"
              class="text-center fw-bold" style="letter-spacing:.4em; font-size:1.2rem;" placeholder="••••••" />
            <div v-if="code && !codeValid" class="text-danger small mt-1">{{ t('activate.codeFormat') }}</div>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-3 small">
            <span class="text-muted">{{ t('activate.noCode') }}</span>
            <CButton type="button" color="link" size="sm" class="p-0" :disabled="busy || cooldown > 0 || identifier.trim().length < 5" @click="sendCode">
              {{ cooldown > 0 ? t('activate.resendIn', { s: cooldown }) : t('activate.sendCode') }}
            </CButton>
          </div>
          <CButton type="submit" color="success" class="w-100" :disabled="busy || !canVerify">
            <CSpinner v-if="busy" size="sm" class="me-1" />
            {{ t('activate.verify') }}
          </CButton>
        </form>

        <!-- Step 2: choose the password -->
        <form v-else-if="step === 2" @submit.prevent="savePassword">
          <div class="mb-3">
            <CFormLabel class="fw-semibold small" for="act-pw">{{ t('changePassword.new') }}</CFormLabel>
            <CFormInput id="act-pw" v-model="password" type="password" size="sm" autocomplete="new-password"
              :placeholder="t('changePassword.newPlaceholder')" />
            <ul class="list-unstyled small mt-2 mb-0">
              <li v-for="r in rules" :key="r.label" :class="r.ok ? 'text-success' : 'text-muted'">
                {{ r.ok ? '✓' : '○' }} {{ r.label }}
              </li>
            </ul>
            <div v-if="fieldErrors.password" class="text-danger small mt-1">{{ fieldErrors.password }}</div>
          </div>

          <div v-if="password" class="mb-3">
            <div class="d-flex gap-1 mb-1">
              <div v-for="i in 4" :key="i" class="flex-fill rounded" style="height:4px;"
                :style="{ background: i <= strength.score ? strength.color : '#e0e0e0' }" />
            </div>
            <div class="small" :style="{ color: strength.color }">{{ strength.label }}</div>
          </div>

          <div class="mb-4">
            <CFormLabel class="fw-semibold small" for="act-pw2">{{ t('changePassword.confirm') }}</CFormLabel>
            <CFormInput id="act-pw2" v-model="confirmation" type="password" size="sm" autocomplete="new-password"
              :placeholder="t('changePassword.confirmPlaceholder')" :invalid="!!confirmation && !matches" />
            <div v-if="confirmation" class="small mt-1" :class="matches ? 'text-success' : 'text-danger'" aria-live="polite">
              {{ matches ? '✓ ' + t('changePassword.match') : '✗ ' + t('changePassword.noMatch') }}
            </div>
          </div>

          <CButton type="submit" color="success" class="w-100" :disabled="busy || !canSave">
            <CSpinner v-if="busy" size="sm" class="me-1" />
            {{ t('activate.save') }}
          </CButton>
        </form>

        <!-- Step 3: done -->
        <div v-else class="text-center">
          <p class="mb-3">{{ t('activate.done') }}</p>
          <CButton color="success" class="w-100" @click="router.replace('/login')">{{ t('activate.goLogin') }}</CButton>
        </div>

        <div v-if="step < 3" class="text-center mt-4">
          <router-link to="/login" class="small">← {{ t('activate.backToLogin') }}</router-link>
        </div>
      </CCardBody>
    </CCard>
  </div>
</template>
