<template>
  <div class="sup-page">
    <div v-if="loading" class="text-center py-4"><CSpinner color="primary" /></div>
    <template v-else-if="data">
      <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
        <div class="flex-grow-1" style="min-width:200px;">
          <div class="small text-body-secondary mb-1">
            {{ t('examx.progress.overall', { entered: data.entered, total: data.total }) }}
          </div>
          <CProgress style="height:10px;" :color="data.total && data.entered >= data.total ? 'success' : 'primary'"
                     :value="data.total ? (data.entered / data.total) * 100 : 0" />
        </div>
        <CBadge v-if="data.deadline" :color="data.deadline_passed ? 'danger' : 'info'">
          {{ data.deadline_passed ? t('examx.progress.deadlinePassed', { date: data.deadline }) : t('examx.progress.deadline', { date: data.deadline }) }}
        </CBadge>
        <CButton size="sm" color="secondary" variant="outline" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CButton v-if="canRemind && data.entered < data.total" size="sm" color="warning" :disabled="reminding" @click="remind">
          <CSpinner v-if="reminding" size="sm" class="me-1" />🔔 {{ t('examx.progress.remind') }}
        </CButton>
      </div>

      <div v-if="!data.classes.length" class="text-center text-body-secondary py-4">{{ t('examx.progress.noClasses') }}</div>

      <CCard v-for="c in data.classes" :key="c.id" class="mb-2">
        <CCardBody class="p-0">
          <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
            <strong>{{ c.label }}</strong>
            <span class="small text-body-secondary">{{ t('examx.progress.students', { count: c.students }) }} · {{ c.entered }} / {{ c.total }}</span>
          </div>
          <CTable responsive hover class="sup-grid mb-0 align-middle">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>{{ t('examx.progress.subject') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('examx.progress.teacher') }}</CTableHeaderCell>
                <CTableHeaderCell style="width:150px;">{{ t('examx.progress.marks') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('examx.progress.status') }}</CTableHeaderCell>
                <CTableHeaderCell v-if="selectable" class="text-center sup-actions"></CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="s in c.subjects" :key="s.id">
                <CTableDataCell class="fw-semibold">{{ s.name }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell small">
                  {{ s.teachers.map((x) => x.name).join(', ') || '—' }}
                </CTableDataCell>
                <CTableDataCell>
                  <div class="d-flex align-items-center gap-2">
                    <CProgress class="flex-grow-1" style="height:6px;" :color="s.complete ? 'success' : 'warning'"
                               :value="s.total ? (s.entered / s.total) * 100 : 0" />
                    <span class="small">{{ s.entered }}/{{ s.total }}</span>
                  </div>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge v-if="s.submitted" color="success">{{ t('examx.progress.submitted') }}</CBadge>
                  <CBadge v-else-if="s.complete" color="info">{{ t('examx.progress.complete') }}</CBadge>
                  <CBadge v-else-if="s.entered > 0" color="warning">{{ t('examx.progress.partial') }}</CBadge>
                  <CBadge v-else color="secondary">{{ t('examx.progress.notStarted') }}</CBadge>
                </CTableDataCell>
                <CTableDataCell v-if="selectable" class="text-center sup-actions">
                  <CButton size="sm" color="primary" variant="outline" @click="$emit('select', { classId: c.id, subjectId: s.id })">
                    {{ t('examx.progress.open') }}
                  </CButton>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>
    </template>
    <PrintSheet v-if="data" v-model="printing" :title="t('examx.list.progress')" :lines="printLines" :columns="printColumns" :rows="printRows" />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { ref, watch, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'

// Class-by-subject view of one exam: how many marks are in, who teaches it, who has handed in.
const props = defineProps({
  examId: { type: [Number, String], required: true },
  canRemind: { type: Boolean, default: false },
  selectable: { type: Boolean, default: false },
  /** Bump to reload from outside (after saving marks). */
  refreshKey: { type: Number, default: 0 },
})
const emit = defineEmits(['select', 'loaded'])

const { t } = useI18n()
const store = useExamsStore()
const notify = useNotify()

const data = ref(null)
const loading = ref(false)
const reminding = ref(false)

// ── Print view: every class and subject, one row each ──
const printing = ref(false)
const printRows = computed(() => (data.value?.classes ?? []).flatMap((c) => c.subjects.map((s) => ({ class: c.label, ...s }))))
const printLines = computed(() => [
  t('examx.progress.overall', { entered: data.value?.entered ?? 0, total: data.value?.total ?? 0 }),
  ...(data.value?.deadline ? [t('examx.progress.deadline', { date: data.value.deadline })] : []),
])
const printColumns = computed(() => [
  { label: t('exams.results.class'), value: (r) => r.class },
  { label: t('examx.progress.subject'), value: (r) => r.name },
  { label: t('examx.progress.teacher'), value: (r) => r.teachers.map((x) => x.name).join(', ') },
  { label: t('examx.progress.marks'), value: (r) => `${r.entered} / ${r.total}`, align: 'center' },
  { label: t('examx.progress.status'), value: (r) => (r.submitted ? t('examx.progress.submitted') : r.complete ? t('examx.progress.complete') : r.entered > 0 ? t('examx.progress.partial') : t('examx.progress.notStarted')) },
])

async function load() {
  loading.value = true
  try {
    data.value = (await store.getProgress(props.examId)).data
    emit('loaded', data.value)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

async function remind() {
  reminding.value = true
  try {
    notify.success((await store.remindTeachers(props.examId)).message)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    reminding.value = false
  }
}

watch(() => [props.examId, props.refreshKey], load)
onMounted(load)
</script>
