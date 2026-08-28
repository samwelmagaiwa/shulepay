<script setup>
/**
 * Set, enter, or remove the dashboard privacy code.
 *
 * Nothing is verified here. The code goes to the backend, which holds only a
 * hash of it and decides whether to send the money figures at all — so this
 * dialog cannot be bypassed by editing the page, because there is no value in
 * the page to reveal.
 */
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '@/stores/dashboard'

const props = defineProps({ visible: Boolean })
const emit = defineEmits(['update:visible'])

const { t } = useI18n()
const dashboard = useDashboardStore()

const code = ref('')
const confirmation = ref('')
const password = ref('')
const error = ref('')
const busy = ref(false)
const showRemove = ref(false)

// Three states: no code set yet, locked (needs the code), unlocked (can re-lock).
const mode = computed(() => {
  if (!dashboard.lockConfigured) return 'create'
  return dashboard.isLocked ? 'unlock' : 'relock'
})

function reset() {
  code.value = ''
  confirmation.value = ''
  password.value = ''
  error.value = ''
  showRemove.value = false
}

watch(() => props.visible, (open) => { if (open) reset() })

function close() {
  emit('update:visible', false)
}

async function submit() {
  error.value = ''
  busy.value = true
  try {
    if (mode.value === 'create') {
      if (code.value !== confirmation.value) {
        error.value = t('dashboardLock.mismatch')
        return
      }
      await dashboard.setLock(code.value, confirmation.value)
    } else if (mode.value === 'unlock') {
      await dashboard.unlock(code.value)
    } else {
      // Re-lock uses the code already stored; nothing new is submitted.
      await dashboard.setLock(null, null)
    }
    close()
  } catch (e) {
    error.value = e?.response?.data?.message
      || e?.response?.data?.errors?.code?.[0]
      || t('dashboardLock.failed')
  } finally {
    busy.value = false
  }
}

async function remove() {
  error.value = ''
  busy.value = true
  try {
    await dashboard.removeLock(password.value)
    close()
  } catch (e) {
    error.value = e?.response?.data?.message || t('dashboardLock.failed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <CModal :visible="visible" @close="close" alignment="center" size="sm">
    <CModalHeader>
      <CModalTitle>
        {{ mode === 'create' ? t('dashboardLock.setTitle')
         : mode === 'unlock' ? t('dashboardLock.unlockTitle')
         : t('dashboardLock.relockTitle') }}
      </CModalTitle>
    </CModalHeader>

    <CModalBody>
      <CAlert v-if="error" color="danger" class="py-2 small">{{ error }}</CAlert>

      <template v-if="!showRemove">
        <p class="text-medium-emphasis small">
          {{ mode === 'create' ? t('dashboardLock.setHelp')
           : mode === 'unlock' ? t('dashboardLock.unlockHelp')
           : t('dashboardLock.relockHelp') }}
        </p>

        <template v-if="mode !== 'relock'">
          <CFormInput
            v-model="code"
            type="password"
            inputmode="numeric"
            autocomplete="off"
            :placeholder="t('dashboardLock.codePlaceholder')"
            class="mb-2"
            @keyup.enter="submit"
          />
          <CFormInput
            v-if="mode === 'create'"
            v-model="confirmation"
            type="password"
            inputmode="numeric"
            autocomplete="off"
            :placeholder="t('dashboardLock.confirmPlaceholder')"
            @keyup.enter="submit"
          />
        </template>

        <button
          v-if="mode === 'unlock'"
          type="button"
          class="btn btn-link btn-sm px-0 mt-2"
          @click="showRemove = true"
        >
          {{ t('dashboardLock.forgot') }}
        </button>
      </template>

      <!-- Forgotten code: removing the lock is gated on the account password,
           so a forgotten code never means a permanently hidden dashboard. -->
      <template v-else>
        <p class="text-medium-emphasis small">{{ t('dashboardLock.removeHelp') }}</p>
        <CFormInput
          v-model="password"
          type="password"
          autocomplete="current-password"
          :placeholder="t('dashboardLock.passwordPlaceholder')"
          @keyup.enter="remove"
        />
      </template>
    </CModalBody>

    <CModalFooter>
      <CButton color="secondary" variant="ghost" :disabled="busy" @click="close">
        {{ t('common.cancel') }}
      </CButton>
      <CButton v-if="!showRemove" color="primary" :disabled="busy" @click="submit">
        <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>
        {{ mode === 'create' ? t('dashboardLock.setAction')
         : mode === 'unlock' ? t('dashboardLock.unlockAction')
         : t('dashboardLock.relockAction') }}
      </CButton>
      <CButton v-else color="danger" :disabled="busy" @click="remove">
        <span v-if="busy" class="spinner-border spinner-border-sm me-1"></span>
        {{ t('dashboardLock.removeAction') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
