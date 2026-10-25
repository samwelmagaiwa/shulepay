<script setup>
/**
 * The order the exam module must be set up in, drawn as one progress row of
 * round, clickable steps: academic year, term, classes, subjects, class subjects.
 * A finished step stays clickable so it can be revisited; an unfinished one only
 * opens once every step before it is done. The row disappears when all are done.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useExamFlowStore } from '@/stores/examFlow'
import { targetFor } from '@/utils/examSetup'

const props = defineProps({
  /** { steps: [{ key, done, count, locked }], next, ready } from GET /exams/readiness */
  readiness: { type: Object, default: null },
})

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()

const flow = useExamFlowStore()

const steps = computed(() => (props.readiness?.steps ?? []).map((step, index) => {
  const to = targetFor(step.key, auth.role)
  const isNext = step.key === props.readiness.next
  return {
    ...step,
    index: index + 1,
    isNext,
    to,
    // Finished steps and the next step are open; anything further is locked.
    clickable: !!to && (step.done || isNext),
  }
}))

const current = computed(() => steps.value.find((s) => s.isNext))
// Stays until the recommended teacher step is done too; once the core steps are done it says so and is optional.
const visible = computed(() => props.readiness && props.readiness.complete !== true && !(props.readiness.ready && !current.value))

function go(step) {
  if (!step.clickable) return
  flow.startSetup()
  router.push(step.to)
}
</script>

<template>
  <CCard v-if="visible" class="guide mb-3">
    <CCardBody class="py-2">
      <div class="guide-head d-flex flex-wrap align-items-center gap-2">
        <h6 class="fw-bold mb-0">🧭 {{ t('exams.guide.title') }}</h6>
        <span v-if="current" class="guide-hint ms-md-auto">
          <span class="text-body-secondary">{{ t(`exams.guide.steps.${current.key}.hint`) }}</span>
          <CButton v-if="current.to" size="sm" color="primary" class="ms-2 py-0" @click="go(current)">
            {{ t('exams.guide.go') }} ›
          </CButton>
          <span v-else class="text-warning ms-2">{{ t('exams.guide.askAdmin') }}</span>
        </span>
      </div>

      <ol class="stepper list-unstyled mb-0">
        <li v-for="step in steps" :key="step.key" class="stepper-item"
            :class="{ done: step.done, next: step.isNext, locked: !step.done && !step.isNext }">
          <button type="button" class="stepper-dot" :disabled="!step.clickable"
                  :aria-current="step.isNext ? 'step' : undefined"
                  :title="t(`exams.guide.steps.${step.key}.hint`)" @click="go(step)">
            {{ step.done ? '✓' : step.index }}
          </button>
          <span class="stepper-label">{{ t(`exams.guide.steps.${step.key}.name`) }}</span>
        </li>
      </ol>
    </CCardBody>
  </CCard>
</template>

<style scoped>
.guide { border-left: 6px solid #2563eb; box-shadow: 0 10px 30px rgba(15, 23, 42, .18); }

.stepper { display: flex; overflow-x: auto; margin-top: .5rem; padding-bottom: .1rem; }
.stepper-item {
  position: relative; flex: 1 0 5.5rem; min-width: 5.5rem;
  display: flex; flex-direction: column; align-items: center; gap: .2rem; text-align: center;
}
/* The connector runs from this dot back to the previous one. */
.stepper-item + .stepper-item::before {
  content: ''; position: absolute; top: .95rem; right: 50%; width: 100%; height: 3px;
  transform: translateY(-50%); background: var(--cui-border-color, #d1d5db); z-index: 0;
}
.stepper-item.done + .stepper-item::before { background: #16a34a; }

.stepper-dot {
  position: relative; z-index: 1; width: 1.9rem; height: 1.9rem; border-radius: 50%; font-size: .85rem;
  border: 2px solid var(--cui-border-color, #d1d5db); font-weight: 700;
  background: var(--cui-body-bg, #fff); color: var(--cui-secondary-color, #6b7280);
  display: grid; place-items: center; cursor: pointer; transition: transform .12s, box-shadow .12s;
}
.stepper-dot:not(:disabled):hover { transform: scale(1.08); box-shadow: 0 4px 12px rgba(15, 23, 42, .25); }
.stepper-dot:disabled { cursor: not-allowed; }
.stepper-item.done .stepper-dot { background: #16a34a; border-color: #16a34a; color: #fff; }
.stepper-item.next .stepper-dot { background: #2563eb; border-color: #2563eb; color: #fff; box-shadow: 0 0 0 4px rgba(37, 99, 235, .2); }
.stepper-item.locked .stepper-dot { opacity: .55; }

.stepper-label { font-size: .75rem; font-weight: 600; line-height: 1.2; overflow-wrap: anywhere; }
.stepper-item.locked .stepper-label { color: var(--cui-secondary-color, #6b7280); }

.guide-hint { font-size: .9rem; }
</style>
