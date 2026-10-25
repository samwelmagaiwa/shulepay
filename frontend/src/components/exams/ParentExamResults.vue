<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'
import ReportCardView from '@/components/exams/ReportCardView.vue'

const props = defineProps({
  visible: { type: Boolean, default: false },
  /** { id, name } of the child whose published results are shown. */
  child: { type: Object, default: null },
})
const emit = defineEmits(['close'])

const { t } = useI18n()
const store = useExamsStore()
const notify = useNotify()

const exams = ref([])
const card = ref(null)
const activeExamId = ref(null)
const loading = ref(false)
const downloading = ref(false)

async function loadList() {
  card.value = null
  exams.value = []
  loading.value = true
  try {
    exams.value = (await store.parentExams(props.child.id)).data
  } catch (e) {
    emit('close')
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

async function openCard(exam) {
  loading.value = true
  try {
    card.value = (await store.parentReportCard(exam.id, props.child.id)).data
    activeExamId.value = exam.id
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

async function download() {
  downloading.value = true
  try {
    await store.downloadPdf(
      `/parent/exam-results/${activeExamId.value}/pdf`,
      { student_id: props.child.id },
      'Report-Card.pdf',
    )
  } catch (e) {
    await notify.fromError(e)
  } finally {
    downloading.value = false
  }
}

watch(() => props.visible, (open) => { if (open && props.child) loadList() })
</script>

<template>
  <CModal :visible="visible" size="lg" alignment="center" scrollable @close="emit('close')">
    <CModalHeader>
      <CModalTitle>🎓 {{ t('exams.parent.title') }} — {{ child?.name }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

      <template v-else-if="card">
        <CButton color="link" class="p-0 mb-3" @click="card = null">‹ {{ t('exams.parent.back') }}</CButton>
        <ReportCardView :card="card" />
      </template>

      <div v-else-if="!exams.length" class="text-center text-body-secondary py-5">{{ t('exams.parent.none') }}</div>

      <CListGroup v-else>
        <CListGroupItem v-for="e in exams" :key="e.id" class="d-flex justify-content-between align-items-center gap-2">
          <div>
            <div class="fw-semibold">{{ e.name }}</div>
            <div class="small text-body-secondary">{{ [e.term, e.academic_year].filter(Boolean).join(' · ') }}</div>
          </div>
          <CBadge v-if="e.held" color="warning" :title="t('examx.parent.heldHint')">💰 {{ t('examx.parent.held') }}</CBadge>
          <CButton v-else size="sm" color="primary" variant="outline" @click="openCard(e)">{{ t('exams.parent.view') }}</CButton>
        </CListGroupItem>
      </CListGroup>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" variant="outline" @click="emit('close')">{{ t('exams.notify.ok') }}</CButton>
      <CButton v-if="card" color="primary" :disabled="downloading" @click="download">
        <CSpinner v-if="downloading" size="sm" class="me-1" />📄 {{ t('exams.parent.download') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
