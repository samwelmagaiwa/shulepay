import { defineStore } from 'pinia'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { i18n } from '@/i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { SETUP_STEPS, targetFor } from '@/utils/examSetup'

/**
 * The guided "what next?" card of the exam module. One card at a time, mounted once in the layout
 * (components/exams/ExamFlowDialog.vue). A card has a title, a text, where the user is in the journey
 * (`trail`: 'setup' | 'exam', `current`: step key) and, unless it is only information, Yes / Not now.
 *
 * The set-up journey crosses several pages (academic year, terms, classes, subjects...), so it is switched
 * on when the user starts a step from the exam guide and remembered for the browser tab.
 */
const t = (key, params) => i18n.global.t(key, params)
const FLAG = 'exam_setup_flow'
const LAST = 'exam_setup_last'

const safe = (fn) => { try { return fn() } catch { return null } }

export const useExamFlowStore = defineStore('examFlow', () => {
  const card = ref(null)
  const busy = ref(false)
  const error = ref('')
  let onYes = null
  let onNo = null

  function ask(config, yes = null, no = null) {
    card.value = config
    onYes = yes
    onNo = no
    error.value = ''
  }

  function close() {
    card.value = null
    onYes = null
    onNo = null
    error.value = ''
  }

  async function yes() {
    busy.value = true
    error.value = ''
    try {
      await onYes?.()
    } catch (e) {
      error.value = e?.response?.data?.message || t('examx.flow.failed')
    } finally {
      busy.value = false
    }
  }

  async function no() {
    const callback = onNo
    close()
    await callback?.()
  }

  const setupActive = () => safe(() => sessionStorage.getItem(FLAG) === '1') === true

  function startSetup() {
    safe(() => { sessionStorage.setItem(FLAG, '1'); sessionStorage.removeItem(LAST) })
  }

  function stopSetup() {
    safe(() => { sessionStorage.removeItem(FLAG); sessionStorage.removeItem(LAST) })
  }

  /**
   * Called after a set-up step was saved on any page. When the journey is on and that step is now done,
   * offer to carry on with the next one (or, when everything is ready, to create the first exam).
   */
  async function afterSetupStep(step, router) {
    if (!setupActive()) return
    if (safe(() => sessionStorage.getItem(LAST)) === step) return

    let readiness
    try {
      readiness = (await api.get('/exams/readiness')).data.data
    } catch {
      return
    }
    if (!readiness.steps.find((s) => s.key === step)?.done) return
    safe(() => sessionStorage.setItem(LAST, step))

    const next = readiness.next
    const stepName = t(`exams.guide.steps.${step}.name`)

    if (next && SETUP_STEPS.includes(next)) {
      const nextName = t(`exams.guide.steps.${next}.name`)
      const to = targetFor(next, useAuthStore().role)
      if (!to) {
        ask({ trail: 'setup', current: step, finished: true, info: true, title: t('examx.flow.stepDone', { step: stepName }), text: t('examx.flow.askAdmin', { next: nextName }) })
        return
      }
      ask({
        trail: 'setup', current: step, finished: true,
        title: t('examx.flow.stepDone', { step: stepName }),
        text: t('examx.flow.nextText', { next: nextName, hint: t(`exams.guide.steps.${next}.hint`) }),
      }, () => { close(); router.push(to) }, () => stopSetup())
      return
    }

    // Everything the module needs is in place.
    stopSetup()
    ask({
      trail: 'setup', current: 'teachers', finished: true,
      title: t('examx.flow.setupDoneTitle'),
      text: t('examx.flow.setupDoneText'),
    }, () => { close(); router.push({ path: '/mitihani', query: { new: '1' } }) })
  }

  return { card, busy, error, ask, close, yes, no, startSetup, stopSetup, afterSetupStep, setupActive }
})

/** For views that already live inside a router: the store plus a ready-made "after this step" call. */
export function useExamFlow() {
  const router = useRouter()
  const flow = useExamFlowStore()

  /**
   * "Something is missing here": explain it in a card and offer to go where it is fixed.
   * config: { title, text, to, yesLabel?, noLabel?, onNo?, info? }; an info card has no destination.
   */
  function guide({ title, text, to, yesLabel, noLabel, onNo, info }) {
    flow.ask({ title, text, yesLabel, noLabel, info: info || !to },
      () => { flow.close(); router.push(to) },
      onNo)
  }

  return {
    flow,
    guide,
    afterSetupStep: (step) => flow.afterSetupStep(step, router),
  }
}
