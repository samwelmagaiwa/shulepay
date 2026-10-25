<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'
import { useExamFlow } from '@/stores/examFlow'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'

const { t } = useI18n()
const router = useRouter()
const store = useExamsStore()
const notify = useNotify()
const { guide } = useExamFlow()

const classes = ref([])
const exams = ref([])
const classId = ref('')
const picks = ref([{ id: '', weight: 40 }, { id: '', weight: 60 }])
const result = ref(null)
const loading = ref(false)
const showSetup = ref(false)

const asRows = (d) => (Array.isArray(d) ? d : d?.data ?? [])
const classLabel = (c) => [c.name, c.stream].filter(Boolean).join(' ')
const fmt = (n) => (n === null || n === undefined ? '—' : Number(Number(n).toFixed(2)))

// Only exams that have marks to combine, and only those covering the chosen class.
const options = computed(() => exams.value.filter((e) => e.status !== 'draft'
  && (!classId.value || e.all_classes || (e.class_ids ?? []).includes(Number(classId.value)))))

const cards = computed(() => {
  const r = result.value
  const top = r?.rows?.[0]
  return [
    { label: t('exams.results.class'), value: r ? r.class.label : '—' },
    { label: t('examx.combined.exams'), value: r ? r.exams.length : '—', hint: r ? r.exams.map((e) => `${e.name} ${fmt(e.weight)}%`).join(' + ') : '' },
    { label: t('exams.results.classSize'), value: r ? r.rows.length : '—' },
    { label: t('examx.combined.topPupil'), value: top ? top.full_name : '—', hint: top ? `${fmt(top.average)}%` : '', cls: 'text-success' },
    { label: t('examx.combined.classAverage'), value: r && r.rows.length ? fmt(r.rows.reduce((a, x) => a + (x.average ?? 0), 0) / r.rows.length) : '—' },
    { label: t('examx.combined.weightsTotal'), value: `${fmt(total.value)}%`, warn: Math.abs(total.value - 100) > 0.01 },
  ]
})

// ── Print view ──
const printing = ref(false)
const printLines = computed(() => [
  `${t('exams.results.class')}: ${result.value?.class.label ?? ''}`,
  (result.value?.exams ?? []).map((e) => `${e.name} (${fmt(e.weight)}%)`).join(' + '),
])
const printColumns = computed(() => [
  { label: t('exams.results.position'), value: (r) => r.position, align: 'center' },
  { label: t('exams.results.student'), value: (r) => r.full_name },
  ...(result.value?.exams ?? []).map((e) => ({ label: e.name, value: (r) => (r.per_exam[e.id] === null ? null : fmt(r.per_exam[e.id])), align: 'center' })),
  ...(result.value?.subjects ?? []).map((s) => ({ label: s.name, value: (r) => (r.cells[s.id].marks === null ? null : `${fmt(r.cells[s.id].marks)} ${r.cells[s.id].grade ?? ''}`), align: 'center' })),
  { label: t('examx.combined.final'), value: (r) => (r.average === null ? null : fmt(r.average)), align: 'center', bold: true },
  { label: t('exams.results.grade'), value: (r) => r.grade, align: 'center', bold: true },
])

const total = computed(() => picks.value.reduce((sum, p) => sum + (Number(p.weight) || 0), 0))
const valid = computed(() => classId.value && picks.value.length >= 2 && picks.value.every((p) => p.id && Number(p.weight) > 0)
  && new Set(picks.value.map((p) => p.id)).size === picks.value.length && Math.abs(total.value - 100) < 0.01)

const addPick = () => { if (picks.value.length < 6) picks.value.push({ id: '', weight: 0 }) }
const removePick = (i) => { if (picks.value.length > 2) picks.value.splice(i, 1) }
function evenWeights() {
  const n = picks.value.length
  const base = Math.floor((100 / n) * 100) / 100
  picks.value.forEach((p, i) => { p.weight = i === n - 1 ? Number((100 - base * (n - 1)).toFixed(2)) : base })
}

async function calculate() {
  loading.value = true
  result.value = null
  showSetup.value = false
  try {
    result.value = (await store.getCombined(classId.value, picks.value.map((p) => ({ id: p.id, weight: p.weight })))).data
  } catch (e) {
    showSetup.value = true
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    const [c, e] = await Promise.all([api.get('/school-classes'), store.listExams({ page: 1 })])
    classes.value = asRows(c.data)
    exams.value = e.data
  } catch (e) {
    await notify.fromError(e)
    return
  }

  await openSetup()
})

// Open the choices card, or say what is missing and take the user to where it is fixed.
async function openSetup() {
  if (!classes.value.length) {
    return guide({ title: t('examx.missing.noClassesTitle'), text: t('examx.missing.noClassesText'), to: '/mitihani/mipangilio?tab=classes', noLabel: t('examx.missing.stayHere') })
  }
  if (exams.value.filter((e) => e.status !== 'draft').length < 2) {
    return guide({ title: t('examx.missing.fewExamsTitle'), text: t('examx.missing.fewExamsText'), to: '/mitihani', yesLabel: t('examx.missing.goExams'), noLabel: t('examx.missing.stayHere') })
  }
  await nextTick()
  showSetup.value = true // opened after the page is drawn, so the card always appears
}
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />

    <SupBar :tabs="[{ key: 'combined', label: t('examx.combined.menu') }]" model-value="combined">
      <span class="small text-body-secondary d-none d-lg-inline me-2">{{ t('examx.combined.hint') }}</span>
      <CButton color="secondary" variant="outline" :disabled="!result" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
      <CButton color="secondary" variant="outline" @click="router.push('/mitihani')">‹ {{ t('exams.backToExams') }}</CButton>
      <CButton color="primary" @click="openSetup">⚙ {{ t('examx.combined.change') }}</CButton>
    </SupBar>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <CCard v-else-if="result">
      <CCardBody class="p-0">
        <div class="px-3 py-2 border-bottom small">
          <strong>{{ result.class.label }}</strong> ·
          <span v-for="(e, i) in result.exams" :key="e.id">{{ e.name }} ({{ fmt(e.weight) }}%)<span v-if="i < result.exams.length - 1"> + </span></span>
        </div>
        <CTable responsive hover align="middle" class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell class="text-center">{{ t('exams.results.position') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.results.student') }}</CTableHeaderCell>
              <CTableHeaderCell v-for="e in result.exams" :key="e.id" class="text-center">{{ e.name }}</CTableHeaderCell>
              <CTableHeaderCell v-for="s in result.subjects" :key="s.id" class="text-center">{{ s.name }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('examx.combined.final') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('exams.results.grade') }}</CTableHeaderCell>
              <CTableHeaderCell v-if="result.rows.some((r) => r.division)" class="text-center">{{ t('examx.results.division') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="r in result.rows" :key="r.student_id">
              <CTableDataCell class="text-center fw-bold">{{ r.position ?? '—' }}</CTableDataCell>
              <CTableDataCell class="fw-semibold">{{ r.full_name }}</CTableDataCell>
              <CTableDataCell v-for="e in result.exams" :key="e.id" class="text-center text-body-secondary">{{ fmt(r.per_exam[e.id]) }}</CTableDataCell>
              <CTableDataCell v-for="s in result.subjects" :key="s.id" class="text-center">
                {{ fmt(r.cells[s.id].marks) }} <small class="text-body-secondary">{{ r.cells[s.id].grade }}</small>
              </CTableDataCell>
              <CTableDataCell class="text-center fw-semibold">{{ fmt(r.average) }}</CTableDataCell>
              <CTableDataCell class="text-center fw-bold">{{ r.grade ?? '—' }}</CTableDataCell>
              <CTableDataCell v-if="result.rows.some((x) => x.division)" class="text-center">{{ r.division ? `${r.division.code} · ${r.division.points}` : '—' }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <div v-else class="text-center text-body-secondary py-5">
      <CButton color="primary" @click="openSetup">{{ t('examx.combined.start') }}</CButton>
    </div>

    <!-- The choices live in a card, so the page is not left half empty -->
    <CModal :visible="showSetup" alignment="center" backdrop="static" @close="showSetup = false">
      <CModalHeader><CModalTitle>{{ t('examx.combined.setupTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <label class="form-label fw-semibold">{{ t('exams.results.class') }}</label>
        <CFormSelect v-model="classId" class="mb-3">
          <option value="">{{ t('exams.results.selectClass') }}</option>
          <option v-for="c in classes" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
        </CFormSelect>

        <div class="fw-semibold mb-1">{{ t('examx.combined.exams') }}</div>
        <div class="small text-body-secondary mb-2">{{ t('examx.combined.examsHint') }}</div>
        <div v-for="(p, i) in picks" :key="i" class="border rounded p-2 mb-2">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="badge bg-primary">{{ t('examx.combined.examN', { n: i + 1 }) }}</span>
            <CButton v-if="picks.length > 2" size="sm" color="secondary" variant="ghost" :aria-label="t('examx.combined.remove')" @click="removePick(i)">✕</CButton>
          </div>
          <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
              <label class="form-label small mb-1">{{ t('examx.combined.whichExam') }}</label>
              <CFormSelect v-model="p.id">
                <option value="">{{ t('examx.combined.pickN', { n: i + 1 }) }}</option>
                <option v-for="e in options" :key="e.id" :value="e.id">{{ e.name }} — {{ e.term }}</option>
              </CFormSelect>
            </div>
            <div style="width:120px; flex:0 0 120px;">
              <label class="form-label small mb-1">{{ t('examx.combined.weight') }}</label>
              <div class="input-group">
                <CFormInput v-model="p.weight" type="number" min="1" max="100" step="0.5" />
                <span class="input-group-text">%</span>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
          <CButton size="sm" color="secondary" variant="outline" :disabled="picks.length >= 6" @click="addPick">＋ {{ t('examx.combined.add') }}</CButton>
          <CButton size="sm" color="secondary" variant="outline" @click="evenWeights">{{ t('examx.combined.even') }}</CButton>
          <span class="small" :class="Math.abs(total - 100) < 0.01 ? 'text-success' : 'text-danger'">
            {{ t('examx.combined.total', { total: fmt(total) }) }}
          </span>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="router.push('/mitihani')">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="primary" :disabled="!valid || loading" @click="calculate">
          <CSpinner v-if="loading" size="sm" class="me-1" />{{ t('examx.combined.calculate') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-if="result" v-model="printing" :title="t('examx.combined.menu')" :lines="printLines" :columns="printColumns" :rows="result.rows" landscape />
  </CContainer>
</template>
