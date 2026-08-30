<template>
  <CModal :visible="visible" backdrop="static" alignment="center" :keyboard="false" @close="() => {}">
    <CModalHeader :closeButton="false">
      <CModalTitle>⏳ {{ t('session.title') }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <p class="mb-2">{{ t('session.message') }}</p>
      <p class="text-center fw-bold fs-3 text-danger mb-0">{{ formattedTime }}</p>
      <p class="text-center text-muted small mt-1">{{ t('session.countdownHint') }}</p>
    </CModalBody>
    <CModalFooter>
      <CButton color="danger" variant="outline" @click="$emit('logout')">
        {{ t('session.logoutNow') }}
      </CButton>
      <CButton color="primary" @click="$emit('stay')">
        {{ t('session.stay') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps({
  visible: { type: Boolean, default: false },
  secondsLeft: { type: Number, default: 60 },
})
defineEmits(['stay', 'logout'])

const formattedTime = computed(() => {
  const m = Math.floor(props.secondsLeft / 60)
  const s = props.secondsLeft % 60
  return `${m}:${String(s).padStart(2, '0')}`
})
</script>
