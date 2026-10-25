<template>
  <div class="sup-page">
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <div v-else-if="!data" class="text-center text-body-secondary py-4">{{ t('examx.analysis.pick') }}</div>
    <template v-else>
      <CRow class="g-2 mb-3">
        <CCol xs="6" md="3"><div class="tile"><div class="tile-value">{{ fmt(data.overall.class_average) }}</div><div class="tile-label">{{ t('exams.results.classAverage') }}</div></div></CCol>
        <CCol xs="6" md="3"><div class="tile"><div class="tile-value">{{ data.overall.pass_rate === null ? '—' : data.overall.pass_rate + '%' }}</div><div class="tile-label">{{ t('examx.analysis.passRate') }}</div></div></CCol>
        <CCol xs="6" md="3"><div class="tile"><div class="tile-value">{{ data.overall.ranked }}</div><div class="tile-label">{{ t('exams.results.ranked') }}</div></div></CCol>
        <CCol xs="6" md="3"><div class="tile"><div class="tile-value">{{ fmt(data.pass_mark) }}</div><div class="tile-label">{{ t('examx.analysis.passMark') }}</div></div></CCol>
      </CRow>

      <CRow class="g-3 mb-3">
        <CCol lg="6">
          <CCard class="h-100"><CCardBody>
            <div class="fw-semibold mb-2">{{ t('examx.analysis.gradeSpread') }}</div>
            <div v-for="(count, grade) in data.overall.grades" :key="grade" class="d-flex align-items-center gap-2 mb-1">
              <span class="fw-bold" style="width:24px;">{{ grade }}</span>
              <CProgress class="flex-grow-1" style="height:12px;" color="primary" :value="data.overall.ranked ? (count / data.overall.ranked) * 100 : 0" />
              <span class="small" style="width:28px;text-align:right;">{{ count }}</span>
            </div>
            <div v-if="Object.keys(data.overall.divisions || {}).length" class="mt-3 small">
              <strong>{{ t('examx.analysis.divisions') }}:</strong>
              <span v-for="(count, code) in data.overall.divisions" :key="code" class="ms-2">{{ code }}: {{ count }}</span>
            </div>
          </CCardBody></CCard>
        </CCol>
        <CCol lg="6">
          <CCard class="h-100"><CCardBody>
            <div class="fw-semibold mb-2">{{ t('examx.analysis.trend') }}</div>
            <div v-if="data.trend.length < 2" class="small text-body-secondary">{{ t('examx.analysis.trendNone') }}</div>
            <div v-for="p in data.trend" :key="p.exam_id" class="d-flex align-items-center gap-2 mb-1">
              <span class="small text-truncate" style="width:42%;" :title="p.name">{{ p.name }}</span>
              <CProgress class="flex-grow-1" style="height:12px;" :color="p.exam_id === data.exam.id ? 'success' : 'secondary'" :value="Math.min(100, p.average)" />
              <span class="small" style="width:44px;text-align:right;">{{ fmt(p.average) }}</span>
            </div>
          </CCardBody></CCard>
        </CCol>
      </CRow>

      <CCard class="mb-3">
        <CCardBody class="p-0">
          <div class="px-3 py-2 border-bottom fw-semibold">{{ t('examx.analysis.subjects') }}</div>
          <CTable responsive hover class="sup-grid mb-0 align-middle">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('exams.marks.subject') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('examx.analysis.sat') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('exams.results.average') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center d-none d-md-table-cell">{{ t('examx.analysis.highest') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center d-none d-md-table-cell">{{ t('examx.analysis.lowest') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('examx.analysis.passRate') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('examx.analysis.best') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="s in data.subjects" :key="s.id">
                <CTableDataCell class="fw-semibold">{{ s.name }}</CTableDataCell>
                <CTableDataCell class="text-center">{{ s.sat }}<span v-if="s.absent" class="text-warning small"> (+{{ s.absent }})</span></CTableDataCell>
                <CTableDataCell class="text-center">{{ fmt(s.average) }}</CTableDataCell>
                <CTableDataCell class="text-center d-none d-md-table-cell">{{ fmt(s.highest) }}</CTableDataCell>
                <CTableDataCell class="text-center d-none d-md-table-cell">{{ fmt(s.lowest) }}</CTableDataCell>
                <CTableDataCell class="text-center">
                  <CBadge v-if="s.pass_rate !== null" :color="s.pass_rate >= 70 ? 'success' : s.pass_rate >= 50 ? 'warning' : 'danger'">{{ s.pass_rate }}%</CBadge>
                  <span v-else>—</span>
                </CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">{{ s.best.join(', ') || '—' }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <CRow class="g-3">
        <CCol lg="6">
          <CCard><CCardBody class="p-0">
            <div class="px-3 py-2 border-bottom fw-semibold">🏆 {{ t('examx.analysis.top') }}</div>
            <CTable class="sup-grid mb-0"><CTableBody>
              <CTableRow v-for="r in data.top" :key="r.student_id">
                <CTableDataCell style="width:44px;" class="text-center fw-bold">{{ r.position }}</CTableDataCell>
                <CTableDataCell>{{ r.full_name }}</CTableDataCell>
                <CTableDataCell class="text-center">{{ fmt(r.average) }}</CTableDataCell>
                <CTableDataCell class="text-center fw-bold">{{ r.grade }}</CTableDataCell>
              </CTableRow>
            </CTableBody></CTable>
          </CCardBody></CCard>
        </CCol>
        <CCol lg="6">
          <CCard><CCardBody class="p-0">
            <div class="px-3 py-2 border-bottom fw-semibold">🔎 {{ t('examx.analysis.weakest') }}</div>
            <CTable class="sup-grid mb-0"><CTableBody>
              <CTableRow v-for="r in data.weakest" :key="r.student_id">
                <CTableDataCell style="width:44px;" class="text-center fw-bold">{{ r.position }}</CTableDataCell>
                <CTableDataCell>{{ r.full_name }}</CTableDataCell>
                <CTableDataCell class="text-center">{{ fmt(r.average) }}</CTableDataCell>
                <CTableDataCell class="text-center fw-bold">{{ r.grade }}</CTableDataCell>
              </CTableRow>
            </CTableBody></CTable>
          </CCardBody></CCard>
        </CCol>
      </CRow>
    </template>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'

// How a class did in one exam: subject performance, pass rate, grade spread, best and weakest, and the trend.
const props = defineProps({
  examId: { type: [Number, String], required: true },
  classId: { type: [Number, String], default: '' },
})
const { t } = useI18n()
const store = useExamsStore()
const notify = useNotify()

const data = ref(null)
const loading = ref(false)
const fmt = (n) => (n === null || n === undefined ? '—' : Number(Number(n).toFixed(2)))

async function load() {
  data.value = null
  if (!props.classId) return
  loading.value = true
  try {
    data.value = (await store.getAnalysis(props.examId, props.classId)).data
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

watch(() => [props.examId, props.classId], load, { immediate: true })
</script>

<style scoped>
.tile { border: 1px solid #c8d3e0; border-radius: 4px; padding: 0.5rem 0.75rem; text-align: center; }
.tile-value { font-size: 1.4rem; font-weight: 700; line-height: 1.2; }
.tile-label { font-size: 0.75rem; color: var(--cui-secondary-color, #6b7280); }
</style>
