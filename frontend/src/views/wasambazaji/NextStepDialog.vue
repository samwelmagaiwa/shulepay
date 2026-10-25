<template>
  <CModal :visible="!!step" alignment="center" backdrop="static" @close="$emit('no')">
    <CModalHeader>
      <CModalTitle>{{ step?.title }}</CModalTitle>
    </CModalHeader>
    <CModalBody v-if="step">
      <!-- where the user is in the purchasing journey -->
      <div class="d-flex align-items-center flex-wrap gap-1 mb-3 small">
        <template v-for="(s, i) in trail" :key="s.key">
          <span class="badge rounded-pill" :class="badgeClass(s)">
            <span v-if="s.state === 'done'">✓ </span>{{ t('purchasing.flow.steps.' + s.key) }}
          </span>
          <span v-if="i < trail.length - 1" class="text-muted">›</span>
        </template>
      </div>
      <p class="mb-1">{{ step.text }}</p>
      <div v-if="error" class="text-danger small mt-2">{{ error }}</div>
    </CModalBody>
    <CModalFooter v-if="step">
      <template v-if="step.info">
        <CButton color="primary" @click="$emit('no')">{{ t('purchasing.flow.ok') }}</CButton>
      </template>
      <template v-else>
        <CButton color="secondary" variant="outline" :disabled="busy" @click="$emit('no')">{{ t('purchasing.flow.no') }}</CButton>
        <CButton color="primary" :disabled="busy" @click="$emit('yes')">
          <CSpinner v-if="busy" size="sm" class="me-1" />{{ t('purchasing.flow.yes') }}
        </CButton>
      </template>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

// One "what next?" card in the guided purchasing flow: supplier -> bill -> approval -> payment.
const props = defineProps({
  step: { type: Object, default: null }, // { title, text, current, info }
  busy: { type: Boolean, default: false },
  error: { type: String, default: '' },
})
defineEmits(['yes', 'no'])
const { t } = useI18n()

const ORDER = ['supplier', 'bill', 'approve', 'pay']
const trail = computed(() => {
  const at = ORDER.indexOf(props.step?.current)
  return ORDER.map((key, i) => ({ key, state: i < at || (props.step?.finished && i <= at) ? 'done' : i === at ? 'now' : 'todo' }))
})
const badgeClass = (s) => ({ done: 'bg-success', now: 'bg-primary', todo: 'bg-light text-muted border' }[s.state])
</script>
