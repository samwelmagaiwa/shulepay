<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const idleMinutes    = ref(30)
const warningSeconds = ref(60)
const bounds         = ref(null)
const loading        = ref(false)
const saving         = ref(false)
const success        = ref('')
const error           = ref('')
const fieldErrors     = ref({})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/session-settings')
    idleMinutes.value    = data.idle_minutes
    warningSeconds.value = data.warning_seconds
    bounds.value          = data.bounds
  } catch {
    error.value = t('sessionSettings.loadFailed')
  } finally {
    loading.value = false
  }
}

async function save() {
  saving.value = true
  success.value = ''
  error.value = ''
  fieldErrors.value = {}
  try {
    const { data } = await api.put('/session-settings', {
      idle_minutes: idleMinutes.value,
      warning_seconds: warningSeconds.value,
    })
    idleMinutes.value    = data.idle_minutes
    warningSeconds.value = data.warning_seconds
    bounds.value           = data.bounds
    success.value = t('sessionSettings.saved')
    setTimeout(() => { success.value = '' }, 3000)
  } catch (e) {
    fieldErrors.value = e.response?.data?.errors || {}
    const first = Object.values(fieldErrors.value)[0]
    error.value = (Array.isArray(first) ? first[0] : first)
               || e.response?.data?.message
               || t('sessionSettings.saveFailed')
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="container-fluid px-4 py-3">
    <div class="mb-3">
      <h5 class="fw-bold mb-1">{{ t('sessionSettings.title') }}</h5>
      <div class="text-muted small">{{ t('sessionSettings.subtitle') }}</div>
    </div>

    <div class="alert alert-success py-2 small mb-2" v-if="success">✅ {{ success }}</div>
    <div class="alert alert-danger py-2 small mb-2" v-if="error">⚠️ {{ error }}</div>

    <div class="card border-0 shadow-sm" style="max-width:560px;">
      <div class="card-body p-4" style="position:relative;">
        <div
          v-if="loading"
          class="d-flex align-items-center justify-content-center"
          style="position:absolute;inset:0;background:rgba(255,255,255,.75);z-index:10;border-radius:inherit;"
        >
          <div class="text-center text-muted">
            <div class="spinner-border spinner-border-sm mb-1" role="status"></div>
            <div class="small">{{ t('common.loading') }}</div>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold mb-1">{{ t('sessionSettings.idleMinutes') }}</label>
            <div class="input-group" style="max-width:220px;">
              <input
                v-model.number="idleMinutes"
                type="number"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.idle_minutes }"
                :min="bounds?.idle_minutes?.min"
                :max="bounds?.idle_minutes?.max"
                :disabled="loading"
              />
              <span class="input-group-text">{{ t('sessionSettings.minutes') }}</span>
            </div>
            <div class="form-text">
              {{ t('sessionSettings.idleMinutesHint') }}
              <span v-if="bounds">{{ t('sessionSettings.rangeHint', { min: bounds.idle_minutes.min, max: bounds.idle_minutes.max }) }}</span>
            </div>
            <div class="invalid-feedback d-block" v-if="fieldErrors.idle_minutes">{{ fieldErrors.idle_minutes[0] }}</div>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold mb-1">{{ t('sessionSettings.warningSeconds') }}</label>
            <div class="input-group" style="max-width:220px;">
              <input
                v-model.number="warningSeconds"
                type="number"
                class="form-control"
                :class="{ 'is-invalid': fieldErrors.warning_seconds }"
                :min="bounds?.warning_seconds?.min"
                :max="bounds?.warning_seconds?.max"
                :disabled="loading"
              />
              <span class="input-group-text">{{ t('sessionSettings.seconds') }}</span>
            </div>
            <div class="form-text">
              {{ t('sessionSettings.warningSecondsHint') }}
              <span v-if="bounds">{{ t('sessionSettings.rangeHint', { min: bounds.warning_seconds.min, max: bounds.warning_seconds.max }) }}</span>
            </div>
            <div class="invalid-feedback d-block" v-if="fieldErrors.warning_seconds">{{ fieldErrors.warning_seconds[0] }}</div>
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-primary" :disabled="saving || loading" @click="save">
            <span v-if="saving" class="spinner-border spinner-border-sm me-1" role="status"></span>
            {{ t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
