<template>
  <CModal :visible="!!flow.card" alignment="center" backdrop="static" @close="flow.no()">
    <CModalHeader>
      <CModalTitle>{{ flow.card?.title }}</CModalTitle>
    </CModalHeader>
    <CModalBody v-if="flow.card">
      <!-- where the user is in the journey -->
      <div v-if="trail.length" class="d-flex align-items-center flex-wrap gap-1 mb-3 small">
        <template v-for="(s, i) in trail" :key="s.key">
          <span class="badge rounded-pill" :class="badgeClass(s)">
            <span v-if="s.state === 'done'">✓ </span>{{ label(s.key) }}
          </span>
          <span v-if="i < trail.length - 1" class="text-muted">›</span>
        </template>
      </div>
      <p class="mb-1">{{ flow.card.text }}</p>
      <div v-if="flow.error" class="text-danger small mt-2">{{ flow.error }}</div>
    </CModalBody>
    <CModalFooter v-if="flow.card">
      <CButton v-if="flow.card.info" color="primary" @click="flow.no()">{{ t('examx.flow.ok') }}</CButton>
      <template v-else>
        <CButton color="secondary" variant="outline" :disabled="flow.busy" @click="flow.no()">{{ flow.card.noLabel || t('examx.flow.no') }}</CButton>
        <CButton color="primary" :disabled="flow.busy" @click="flow.yes()">
          <CSpinner v-if="flow.busy" size="sm" class="me-1" />{{ flow.card.yesLabel || t('examx.flow.yes') }}
        </CButton>
      </template>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExamFlowStore } from '@/stores/examFlow'
import { SETUP_STEPS } from '@/utils/examSetup'

// The card of the guided exam journey: set-up steps first, then the life of one exam.
const flow = useExamFlowStore()
const { t } = useI18n()

const LIFE = ['create', 'open', 'marks', 'comments', 'publish', 'cards']

const keys = computed(() => (flow.card?.trail === 'exam' ? LIFE : flow.card?.trail === 'setup' ? SETUP_STEPS : []))
const trail = computed(() => {
  const at = keys.value.indexOf(flow.card?.current)
  return keys.value.map((key, i) => ({ key, state: i < at || (flow.card?.finished && i <= at) ? 'done' : i === at ? 'now' : 'todo' }))
})
const label = (key) => (flow.card?.trail === 'exam' ? t(`examx.flow.life.${key}`) : t(`exams.guide.steps.${key}.name`))
const badgeClass = (s) => ({ done: 'bg-success', now: 'bg-primary', todo: 'bg-light text-muted border' }[s.state])
</script>
