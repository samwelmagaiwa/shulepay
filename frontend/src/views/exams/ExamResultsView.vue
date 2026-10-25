<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'
import { useExamFlow } from '@/stores/examFlow'
import { STATUS_COLOR } from '@/utils/examRoles'
import ReportCardView from '@/components/exams/ReportCardView.vue'
import ExamAnalysisPanel from '@/components/exams/ExamAnalysisPanel.vue'
import ExamCommentsPanel from '@/components/exams/ExamCommentsPanel.vue'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useExamsStore()
const notify = useNotify()
const { guide } = useExamFlow()

const examId = route.params.id

const exam = ref(null)
const classes = ref([])
const classId = ref('')
const results = ref(null)
const loading = ref(false)
const downloading = ref('')
const tab = ref(['results', 'analysis', 'comments'].includes(route.query.tab) ? route.query.tab : 'results')
const commentsKey = ref(0)

const asRows = (d) => (Array.isArray(d) ? d : d?.data ?? [])
const classLabel = (c) => [c.name, c.stream].filter(Boolean).join(' ')
const fmt = (n) => (n === null || n === undefined ? '—' : Number(Number(n).toFixed(2)))

async function loadSetup() {
  try {
    const [examRes, classRes] = await Promise.all([store.getExam(examId), api.get('/school-classes')])
    exam.value = examRes.data
    classes.value = asRows(classRes.data)
  } catch (e) {
    await notify.fromError(e)
    router.replace('/mitihani')
    return
  }
  const qc = Number(route.query.class)
  if (qc && classOptions.value.some((c) => c.id === qc)) classId.value = qc
  if (exam.value.status === 'draft') {
    guide({ title: t('examx.missing.notOpenResultsTitle'), text: t('examx.missing.notOpenResultsText'), to: '/mitihani', onNo: () => router.push('/mitihani') })
  }
}

async function loadResults() {
  results.value = null
  if (!classId.value) return
  loading.value = true
  try {
    results.value = (await store.getClassResults(examId, classId.value)).data
    if (!results.value.rows.some((r) => r.total !== null) && exam.value?.status === 'open') {
      guide({ title: t('examx.missing.noMarksTitle'), text: t('examx.missing.noMarksText'), to: { path: `/mitihani/${examId}/alama`, query: { class: classId.value } } })
    }
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

// Watched rather than read in a @change handler: CFormSelect emits `change` before it
// updates the v-model, so a handler would see the previous (empty) class.
watch(classId, loadResults)

// Only the classes this exam covers.
const classOptions = computed(() => classes.value.filter(
  (c) => !exam.value || exam.value.all_classes || (exam.value.class_ids ?? []).includes(c.id),
))

const tabList = computed(() => ['results', 'analysis', 'comments'].map((k) => ({ key: k, label: t(`examx.results.tabs.${k}`) })))

// The numbers across the top: the exam, then the chosen class.
const cards = computed(() => {
  const sum = results.value?.summary
  return [
    { label: t('exams.list.name'), value: exam.value?.name ?? '—', hint: exam.value?.term },
    { label: t('exams.list.status'), value: exam.value ? t(`exams.status.${exam.value.status}`) : '—', hint: exam.value?.out_of && exam.value.out_of !== 100 ? t('examx.results.percentNote', { total: exam.value.out_of }) : '' },
    { label: t('exams.results.classSize'), value: sum ? sum.class_size : '—' },
    { label: t('exams.results.ranked'), value: sum ? sum.ranked : '—' },
    { label: t('exams.results.classAverage'), value: sum ? fmt(sum.class_average) : '—' },
    sum && !sum.complete
      ? { label: t('exams.results.complete'), value: t('exams.results.incomplete', { count: sum.missing_cells }), warn: true, cls: 'text-warning' }
      : { label: t('exams.results.complete'), value: sum ? '✓' : '—', cls: sum ? 'text-success' : '' },
  ]
})

// ── Print view: the class results as they are on screen ──
const printing = ref(false)
const printLines = computed(() => [
  [exam.value?.name, exam.value?.term, exam.value ? t(`exams.status.${exam.value.status}`) : ''].filter(Boolean).join(' · '),
  `${t('exams.results.class')}: ${results.value?.class?.label ?? ''}`,
])
const printColumns = computed(() => [
  { label: t('exams.results.position'), value: (r) => r.position, align: 'center' },
  { label: t('exams.results.student'), value: (r) => r.full_name },
  ...(results.value?.subjects ?? []).map((s) => ({
    label: s.code || s.name,
    align: 'center',
    value: (r) => (r.cells[s.id].absent ? t('exams.results.absentShort') : r.cells[s.id].marks === null ? null : `${fmt(r.cells[s.id].marks)} ${r.cells[s.id].grade ?? ''}`),
  })),
  { label: t('exams.results.total'), value: (r) => (r.total === null ? null : fmt(r.total)), align: 'center', bold: true },
  { label: t('exams.results.average'), value: (r) => (r.average === null ? null : fmt(r.average)), align: 'center' },
  { label: t('exams.results.grade'), value: (r) => r.grade, align: 'center', bold: true },
  ...(hasDivision.value ? [{ label: t('examx.results.division'), value: (r) => (r.division ? `${r.division.code} · ${r.division.points}` : null), align: 'center' }] : []),
])
const printFooter = computed(() => [[
  t('exams.results.subjectAverage'), '',
  ...(results.value?.subjects ?? []).map((s) => (s.average === null ? '—' : fmt(s.average))),
  '', '', '', ...(hasDivision.value ? [''] : []),
]])

const hasAnyMarks = computed(() => results.value?.rows.some((r) => r.total !== null))
const hasDivision = computed(() => results.value?.rows.some((r) => r.division))

// ── PDFs ────────────────────────────────────────────────────────────────────
async function download(kind, url, params, fallback) {
  downloading.value = kind
  try {
    await store.downloadPdf(url, params, fallback)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    downloading.value = ''
  }
}

const classPdf = () => download('class', `/exams/${examId}/class-results/pdf`, { school_class_id: classId.value }, 'Class-Results.pdf')
// ── Printing report cards: everyone, a chosen batch, or one pupil ──
const selected = ref(new Set())
const allSelected = computed(() => !!results.value?.rows.length && results.value.rows.every((r) => selected.value.has(r.student_id)))
function toggleOne(id) {
  const next = new Set(selected.value)
  next.has(id) ? next.delete(id) : next.add(id)
  selected.value = next
}
function toggleAll() {
  selected.value = allSelected.value ? new Set() : new Set(results.value.rows.map((r) => r.student_id))
}
// A different class means a different list of pupils, so the ticks start over.
watch(classId, () => { selected.value = new Set() })

async function printCards(ids = null) {
  downloading.value = 'cards'
  try {
    await store.openPdf(`/exams/${examId}/report-cards/pdf`, { school_class_id: classId.value, ...(ids ? { student_ids: ids } : {}) })
  } catch (e) {
    await notify.fromError(e)
  } finally {
    downloading.value = ''
  }
}
async function printCard() {
  try {
    await store.openPdf(`/exams/${examId}/students/${cardStudentId.value}/report-card/pdf`, {})
  } catch (e) {
    await notify.fromError(e)
  }
}

const cardsPdf = () => download('cards', `/exams/${examId}/report-cards/pdf`, { school_class_id: classId.value }, 'Report-Cards.pdf')

// ── Report card preview ─────────────────────────────────────────────────────
const cardOpen = ref(false)
const card = ref(null)
const history = ref([])
const cardStudentId = ref(null)
const cardLoading = ref(false)

async function openCard(row) {
  cardStudentId.value = row.student_id
  card.value = null
  history.value = []
  cardOpen.value = true
  cardLoading.value = true
  try {
    card.value = (await store.getReportCard(examId, row.student_id)).data
    // The pupil's path across exams is a bonus; the card works without it.
    store.getStudentHistory(row.student_id).then((r) => { history.value = r.data.history }).catch(() => {})
  } catch (e) {
    cardOpen.value = false
    await notify.fromError(e)
  } finally {
    cardLoading.value = false
  }
}

const cardPdf = () =>
  download('card', `/exams/${examId}/students/${cardStudentId.value}/report-card/pdf`, {}, 'Report-Card.pdf')

onMounted(loadSetup)
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />

    <SupBar v-model="tab" :tabs="tabList">
      <CButton color="secondary" variant="outline" @click="router.push('/mitihani')">‹ {{ t('exams.backToExams') }}</CButton>
      <CFormSelect v-model="classId" :aria-label="t('exams.results.class')">
        <option value="">{{ t('exams.results.selectClass') }}</option>
        <option v-for="c in classOptions" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
      </CFormSelect>
      <template v-if="tab === 'results'">
        <CButton color="secondary" variant="outline" :disabled="!results || !hasAnyMarks" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CButton color="secondary" variant="outline" :disabled="!results || !hasAnyMarks || !!downloading" @click="classPdf">
          <CSpinner v-if="downloading === 'class'" size="sm" class="me-1" />📄 {{ t('exams.results.classPdf') }}
        </CButton>
        <CDropdown placement="bottom-end">
          <CDropdownToggle color="secondary" variant="outline" :disabled="!results || !results.rows.length || !!downloading">
            <CSpinner v-if="downloading === 'cards'" size="sm" class="me-1" />🖨️ {{ t('examx.cards.menu') }}
          </CDropdownToggle>
          <CDropdownMenu>
            <CDropdownItem @click="printCards()">{{ t('examx.cards.printAll') }}</CDropdownItem>
            <CDropdownItem :disabled="!selected.size" @click="printCards([...selected])">{{ t('examx.cards.printSelected', { count: selected.size }) }}</CDropdownItem>
            <CDropdownItem @click="cardsPdf">{{ t('examx.cards.downloadAll') }}</CDropdownItem>
          </CDropdownMenu>
        </CDropdown>
      </template>
    </SupBar>

    <div v-if="!classId" class="text-center text-body-secondary py-5">{{ t('exams.results.pickPrompt') }}</div>

    <ExamAnalysisPanel v-else-if="tab === 'analysis'" :exam-id="examId" :class-id="classId" />
    <ExamCommentsPanel v-else-if="tab === 'comments'" :key="commentsKey" :exam-id="examId" :class-id="classId" @saved="classId && loadResults()" @pick-class="classId = $event" />

    <div v-else-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <template v-else-if="results">
      <div v-if="exam && exam.status !== 'published'" class="alert alert-info small">
        {{ t('exams.results.statusNote', { status: t(`exams.status.${exam.status}`) }) }}
      </div>

      <CCard>
        <CCardBody class="p-0">
          <div class="small text-body-secondary px-3 py-2 border-bottom">
            <strong>{{ t('exams.results.distribution') }}:</strong>
            <span v-for="(count, grade) in results.summary.grade_distribution" :key="grade" class="ms-2">
              <strong>{{ grade }}</strong> {{ count }}
            </span>
          </div>

          <div v-if="!results.rows.length || !hasAnyMarks" class="text-center text-body-secondary py-4">
            {{ t('exams.results.noResults') }}
          </div>

          <CTable v-else responsive hover align="middle" class="sup-grid mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell style="width:34px;"><CFormCheck :checked="allSelected" :aria-label="t('examx.cards.selectAll')" @change="toggleAll" /></CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('exams.results.position') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('exams.results.student') }}</CTableHeaderCell>
                <CTableHeaderCell v-for="s in results.subjects" :key="s.id" class="text-center">{{ s.code || s.name }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('exams.results.total') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('exams.results.average') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('exams.results.grade') }}</CTableHeaderCell>
                <CTableHeaderCell v-if="hasDivision" class="text-center">{{ t('examx.results.division') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center sup-actions">{{ t('exams.list.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="(row, i) in results.rows" :key="row.student_id">
                <CTableDataCell><CFormCheck :checked="selected.has(row.student_id)" @change="toggleOne(row.student_id)" /></CTableDataCell>
                <CTableDataCell class="text-center fw-bold">{{ row.position ?? '—' }}</CTableDataCell>
                <CTableDataCell class="fw-semibold">
                  {{ row.full_name }}
                  <div v-if="row.missing" class="small text-warning fw-normal">{{ t('exams.results.incomplete', { count: row.missing }) }}</div>
                </CTableDataCell>
                <CTableDataCell v-for="s in results.subjects" :key="s.id" class="text-center">
                  <span v-if="row.cells[s.id].absent" class="text-warning fst-italic">{{ t('exams.results.absentShort') }}</span>
                  <template v-else-if="row.cells[s.id].marks !== null">
                    {{ fmt(row.cells[s.id].marks) }} <small class="text-body-secondary">{{ row.cells[s.id].grade }}</small>
                  </template>
                  <span v-else class="text-body-secondary">—</span>
                </CTableDataCell>
                <CTableDataCell class="text-center fw-semibold">{{ fmt(row.total) }}</CTableDataCell>
                <CTableDataCell class="text-center">{{ fmt(row.average) }}</CTableDataCell>
                <CTableDataCell class="text-center fw-bold">{{ row.grade ?? '—' }}</CTableDataCell>
                <CTableDataCell v-if="hasDivision" class="text-center fw-semibold" :title="row.division?.label">{{ row.division ? `${row.division.code} · ${row.division.points}` : '—' }}</CTableDataCell>
                <CTableDataCell class="text-center sup-actions">
                  <CDropdown :placement="menuPlacement(i, results.rows.length)">
                    <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('exams.list.actions')">⋮</CDropdownToggle>
                    <CDropdownMenu>
                      <CDropdownItem :disabled="row.total === null && !row.missing" @click="openCard(row)">{{ t('exams.results.reportCard') }}</CDropdownItem>
                    </CDropdownMenu>
                  </CDropdown>
                </CTableDataCell>
              </CTableRow>
              <CTableRow class="table-light">
                <CTableDataCell colspan="3" class="fw-semibold">{{ t('exams.results.subjectAverage') }}</CTableDataCell>
                <CTableDataCell v-for="s in results.subjects" :key="s.id" class="text-center fw-semibold">{{ fmt(s.average) }}</CTableDataCell>
                <CTableDataCell :colspan="hasDivision ? 5 : 4"></CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>
    </template>

    <CModal :visible="cardOpen" size="lg" alignment="center" scrollable @close="cardOpen = false">
      <CModalHeader><CModalTitle>{{ t('exams.results.reportCard') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div v-if="cardLoading" class="text-center py-5"><CSpinner color="primary" /></div>
        <template v-else-if="card">
          <ReportCardView :card="card" />
          <div v-if="history.length > 1" class="mt-3">
            <div class="fw-semibold mb-1">{{ t('examx.results.history') }}</div>
            <CTable small class="sup-grid mb-0">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('exams.results.title') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('exams.results.average') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('exams.results.grade') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('exams.results.position') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="h in history" :key="h.exam_id" :class="{ 'table-light': String(h.exam_id) === String(examId) }">
                  <CTableDataCell>{{ h.name }}<span v-if="h.term" class="small text-body-secondary"> · {{ h.term }}</span></CTableDataCell>
                  <CTableDataCell class="text-center">{{ fmt(h.average) }}</CTableDataCell>
                  <CTableDataCell class="text-center fw-bold">{{ h.grade ?? '—' }}</CTableDataCell>
                  <CTableDataCell class="text-center">{{ h.position ? `${h.position} / ${h.class_size}` : '—' }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>
        </template>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="cardOpen = false">{{ t('exams.notify.ok') }}</CButton>
        <CButton color="secondary" variant="outline" :disabled="!card" @click="printCard">🖨 {{ t('printing.print') }}</CButton>
        <CButton color="primary" :disabled="!card || downloading === 'card'" @click="cardPdf">
          <CSpinner v-if="downloading === 'card'" size="sm" class="me-1" />📄 {{ t('exams.parent.download') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-if="results" v-model="printing" :title="t('exams.results.title')" :lines="printLines" :columns="printColumns" :rows="results.rows" :footer="printFooter" landscape />
  </CContainer>
</template>
