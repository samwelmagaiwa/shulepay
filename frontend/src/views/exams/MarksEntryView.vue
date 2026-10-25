<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute, useRouter, onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import ExamProgressPanel from '@/components/exams/ExamProgressPanel.vue'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'
import { useAuthStore } from '@/stores/auth'
import { useExamsStore } from '@/stores/exams'
import { useExamFlowStore, useExamFlow } from '@/stores/examFlow'
import { useNotify } from '@/composables/useNotify'
import { STATUS_COLOR, canManageExams, canPublishExams } from '@/utils/examRoles'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useExamsStore()
const flow = useExamFlowStore()
const { guide } = useExamFlow()
const notify = useNotify()

const examId = route.params.id
const canManage = computed(() => canManageExams(auth.role))
const canPublish = computed(() => canPublishExams(auth.role))

const exam = ref(null)
const classes = ref([])
const classSubjects = ref([])
const subjects = ref([])
const ranges = ref([])
const mine = ref({ restricted: false, assignments: [] })

const classId = ref('')
const subjectId = ref('')
const grid = ref(null)
const rows = ref([])
const rowErrors = ref({})
const loadingGrid = ref(false)
const saving = ref(false)
const busy = ref(false)

const asRows = (d) => (Array.isArray(d) ? d : d?.data ?? [])
const classLabel = (c) => [c.name, c.stream].filter(Boolean).join(' ')
const outOf = computed(() => grid.value?.out_of ?? exam.value?.out_of ?? 100)

// ── Setup data ──────────────────────────────────────────────────────────────
async function loadSetup() {
  try {
    const [examRes, classRes, csRes, subjectRes, scaleRes, mineRes] = await Promise.all([
      store.getExam(examId),
      api.get('/school-classes'),
      store.listClassSubjects(),
      store.listSubjects({ active: 1 }),
      store.getGradingScale(),
      store.getMyAssignments(),
    ])
    exam.value = examRes.data
    classes.value = asRows(classRes.data)
    classSubjects.value = csRes.data
    subjects.value = subjectRes.data
    ranges.value = scaleRes.data.ranges
    mine.value = mineRes.data
  } catch (e) {
    await notify.fromError(e)
    router.replace('/mitihani')
    return
  }
  if (checkMissing()) return
  // The progress screen sends us here with a class (and maybe a subject) already chosen.
  const qc = Number(route.query.class)
  const qs = Number(route.query.subject)
  if (qc && classOptions.value.some((c) => c.id === qc)) {
    classId.value = qc
    lastClass = classId.value
    pickOnlySubject()
    if (qs && subjectOptions.value.some((s) => s.id === qs)) {
      subjectId.value = qs
      lastClass = classId.value
      lastSubject = subjectId.value
      await loadGrid()
    }
  } else if (classOptions.value.length === 1) {
    // Only one class to choose from: save the click.
    classId.value = classOptions.value[0].id
    lastClass = classId.value
    pickOnlySubject()
    await loadGrid()
  }
}

// One subject in the class: no need to ask which.
function pickOnlySubject() {
  if (!subjectId.value && subjectOptions.value.length === 1) {
    subjectId.value = subjectOptions.value[0].id
    lastSubject = subjectId.value
  }
}

// What is missing before marks can be entered, said out loud with a way to fix it. True when a card was shown.
function checkMissing() {
  if (exam.value.status === 'draft') {
    if (canManage.value) {
      flow.ask({ title: t('examx.missing.notOpenTitle'), text: t('examx.missing.notOpenManage'), yesLabel: t('exams.list.open') }, async () => {
        await store.openExam(examId)
        exam.value = (await store.getExam(examId)).data
        flow.close()
        if (classOptions.value.length === 1) { classId.value = classOptions.value[0].id; lastClass = classId.value; pickOnlySubject(); await loadGrid() }
      }, () => router.push('/mitihani'))
    } else {
      guide({ title: t('examx.missing.notOpenTitle'), text: t('examx.missing.notOpenOther'), onNo: () => router.push('/mitihani') })
    }
    return true
  }
  if (mine.value.restricted && !mine.value.assignments.length) {
    guide({ title: t('examx.missing.noAssignmentsTitle'), text: t('examx.missing.noAssignmentsText'), onNo: () => router.push('/mitihani') })
    return true
  }
  if (!classOptions.value.length && exam.value.status === 'open') {
    guide({
      title: t('examx.missing.noSubjectsTitle'), text: t('examx.missing.noSubjectsText'),
      to: canManage.value ? '/mitihani/mipangilio?tab=classSubjects' : null, onNo: () => router.push('/mitihani'),
    })
    return true
  }
  return false
}

// A classroom teacher only sees the classes and subjects they are assigned to.
const isAssigned = (cId, sId) => mine.value.assignments.some((a) => a.school_class_id === cId && a.subject_id === sId)

// Only the classes this exam covers that have subjects, and (for a classroom teacher) only their own.
const hasSubjects = (id) => (classSubjects.value.find((c) => c.id === id)?.subject_ids ?? []).length > 0
const classOptions = computed(() => classes.value.filter((c) => {
  const inExam = !exam.value || exam.value.all_classes || (exam.value.class_ids ?? []).includes(c.id)
  const mineOnly = !mine.value.restricted || mine.value.assignments.some((a) => a.school_class_id === c.id)
  return inExam && mineOnly && hasSubjects(c.id)
}))

const subjectOptions = computed(() => {
  const cId = Number(classId.value)
  const cs = classSubjects.value.find((c) => c.id === cId)
  if (!cs) return []
  return subjects.value.filter((s) => cs.subject_ids.includes(s.id) && (!mine.value.restricted || isAssigned(cId, s.id)))
})

// ── Dirty tracking ──────────────────────────────────────────────────────────
const isDirty = computed(() => rows.value.some((r) => r.marks !== r.origMarks || r.absent !== r.origAbsent || r.remarks !== r.origRemarks))

async function confirmDiscard() {
  if (!isDirty.value) return true
  return notify.confirm({
    title: t('exams.confirm.leaveTitle'),
    message: t('exams.confirm.leaveMessage'),
    confirmText: t('exams.confirm.leaveAction'),
    cancelText: t('exams.confirm.cancel'),
    danger: true,
  })
}

onBeforeRouteLeave(async () => (await confirmDiscard()) || false)

const warnOnUnload = (e) => { if (isDirty.value) { e.preventDefault(); e.returnValue = '' } }
window.addEventListener('beforeunload', warnOnUnload)
onBeforeUnmount(() => window.removeEventListener('beforeunload', warnOnUnload))

// ── Grid ────────────────────────────────────────────────────────────────────
function applyGrid(data) {
  grid.value = data
  rows.value = data.students.map((s) => {
    const marks = s.marks === null ? '' : String(s.marks)
    const remarks = s.remarks ?? ''
    return { ...s, marks, remarks, origMarks: marks, origAbsent: s.absent, origRemarks: remarks }
  })
  rowErrors.value = {}
}

async function loadGrid() {
  if (!classId.value || !subjectId.value) return
  loadingGrid.value = true
  try {
    applyGrid((await store.getMarksGrid(examId, classId.value, subjectId.value)).data)
    if (!rows.value.length) offerPupils()
  } catch (e) {
    grid.value = null
    rows.value = []
    await notify.fromError(e)
  } finally {
    loadingGrid.value = false
  }
}

// A class with nobody in it: offer to register pupils (only for roles that can open that page).
function offerPupils() {
  const mayRegister = ['owner', 'superadmin', 'accountant'].includes(auth.role)
  guide({ title: t('examx.missing.noPupilsTitle'), text: t('examx.missing.noPupilsText'), to: mayRegister ? '/wanafunzi' : null })
}

let lastClass = ''
let lastSubject = ''
async function onSelectionChange() {
  if (!(await confirmDiscard())) {
    classId.value = lastClass
    subjectId.value = lastSubject
    return
  }
  // A subject from the previous class may not exist in the new one.
  if (!subjectOptions.value.some((s) => s.id === Number(subjectId.value))) subjectId.value = ''
  pickOnlySubject()
  lastClass = classId.value
  lastSubject = subjectId.value
  grid.value = null
  rows.value = []
  await loadGrid()
}

// ── Row helpers ─────────────────────────────────────────────────────────────
// The grade preview is worked out on the percentage, so an exam out of 50 grades like one out of 100.
const gradeFor = (marks) => {
  if (marks === '' || marks === null || Number.isNaN(Number(marks))) return ''
  const value = Number(marks)
  if (value < 0 || value > outOf.value) return ''
  const percent = (value / outOf.value) * 100
  return ranges.value.find((r) => percent >= r.from)?.grade ?? ''
}

// An absent pupil's mark box is disabled and cleared, so only typed marks need checking.
function clientError(row) {
  if (row.absent || row.marks === '') return ''
  const n = Number(row.marks)
  if (Number.isNaN(n)) return t('exams.marks.errNumber')
  if (n < 0 || n > outOf.value) return t('examx.marks.errRange', { max: outOf.value })
  if (!/^\d{1,4}(\.\d{1,2})?$/.test(String(row.marks).trim())) return t('exams.marks.errDecimals')
  return ''
}

const errorFor = (index) => rowErrors.value[index] || clientError(rows.value[index])

const editable = computed(() => grid.value?.editable === true)
const entered = computed(() => rows.value.filter((r) => r.marks !== '' || r.absent).length)
const allEntered = computed(() => rows.value.length > 0 && entered.value === rows.value.length)

function onAbsentToggle(row) {
  if (row.absent) row.marks = ''
}

async function focusNext(index) {
  await nextTick()
  document.querySelector(`[data-mark-row="${index + 1}"]`)?.focus()
}

// ── Save ────────────────────────────────────────────────────────────────────
async function save() {
  const changed = rows.value
    .map((r, i) => ({ r, i }))
    .filter(({ r }) => r.marks !== r.origMarks || r.absent !== r.origAbsent || r.remarks !== r.origRemarks)

  if (!changed.length) return notify.info(t('exams.marks.nothingToSave'), { title: '' })

  // Catch obvious mistakes here, with the same row numbers the teacher sees.
  const problems = changed.map(({ i }) => ({ i, message: clientError(rows.value[i]) })).filter((p) => p.message)
  if (problems.length) {
    rowErrors.value = Object.fromEntries(problems.map((p) => [p.i, p.message]))
    return notify.warning(t('exams.marks.fixMarks'), {
      items: problems.map((p) => t('exams.marks.rowError', { row: p.i + 1, message: p.message })),
    })
  }

  saving.value = true
  try {
    const res = await store.saveMarks(examId, classId.value, subjectId.value, changed.map(({ r }) => ({
      student_id: r.student_id,
      marks: r.marks === '' ? null : Number(r.marks),
      absent: r.absent,
      remarks: r.remarks || null,
    })))
    applyGrid(res.data)
    notify.success(res.message)
    if (allEntered.value && !grid.value.submitted) offerSubmit()
  } catch (e) {
    const errors = e.response?.status === 422 ? e.response.data?.errors : null
    if (errors) {
      // The server numbers rows within what we sent; map them back to grid rows.
      const items = []
      const mapped = {}
      for (const [key, messages] of Object.entries(errors)) {
        const match = /^entries\.(\d+)\./.exec(key)
        const gridIndex = match ? changed[Number(match[1])]?.i : undefined
        // Drop the server's own "Row 3:" / "Safu ya 3:" prefix; the screen adds the grid row number.
        const text = messages[0].replace(/^[^:\d]{0,12}\d+:\s*/, '')
        if (gridIndex === undefined) items.push(messages[0])
        else { mapped[gridIndex] = text; items.push(t('exams.marks.rowError', { row: gridIndex + 1, message: text })) }
      }
      rowErrors.value = mapped
      notify.error(t('exams.marks.fixMarks'), { items })
    } else {
      await notify.fromError(e)
    }
  } finally {
    saving.value = false
  }
}

// ── Submit / reopen ─────────────────────────────────────────────────────────
function offerSubmit() {
  flow.ask({
    trail: 'exam', current: 'marks', finished: false,
    title: t('examx.flow.marksAllInTitle'),
    text: t('examx.flow.marksAllInText', { subject: grid.value.subject.name, class: grid.value.class.label }),
    yesLabel: t('examx.marks.submitNow'),
    noLabel: t('examx.flow.notNow'),
  }, async () => {
    await submit(true)
  })
}

async function submit(fromFlow = false) {
  busy.value = true
  try {
    const res = await store.submitMarks(examId, classId.value, subjectId.value)
    applyGrid(res.data)
    notify.success(res.message)
    if (fromFlow) await offerNext()
    else if (!flow.card) await offerNext()
  } catch (e) {
    if (fromFlow) throw e
    await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

async function reopen() {
  busy.value = true
  try {
    const res = await store.reopenMarks(examId, classId.value, subjectId.value)
    applyGrid(res.data)
    notify.success(res.message)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

/** After handing marks in: carry on with the next subject still waiting, or with publishing when everything is in. */
async function offerNext() {
  let progress
  try {
    progress = (await store.getProgress(examId)).data
  } catch {
    flow.close()
    return
  }
  const mineOnly = mine.value.restricted
  const waiting = []
  for (const c of progress.classes) {
    for (const s of c.subjects) {
      if (s.complete || s.total === 0) continue
      if (mineOnly && !isAssigned(c.id, s.id)) continue
      waiting.push({ classId: c.id, classLabel: c.label, subjectId: s.id, subjectName: s.name, sameClass: String(c.id) === String(classId.value) })
    }
  }
  waiting.sort((a, b) => Number(b.sameClass) - Number(a.sameClass))
  const next = waiting[0]

  if (next) {
    flow.ask({
      trail: 'exam', current: 'marks', finished: false,
      title: t('examx.flow.nextSubjectTitle'),
      text: t('examx.flow.nextSubjectText', { subject: next.subjectName, class: next.classLabel, left: waiting.length }),
    }, async () => {
      flow.close()
      classId.value = next.classId
      await nextTick()
      subjectId.value = next.subjectId
      lastClass = classId.value
      lastSubject = subjectId.value
      await loadGrid()
    })
    return
  }

  const everythingIn = progress.total > 0 && progress.entered >= progress.total
  if (everythingIn && exam.value.require_comments) {
    flow.ask({
      trail: 'exam', current: 'marks', finished: true,
      title: t('examx.flow.allMarksTitle'), text: t('examx.flow.toCommentsText', { name: exam.value.name }),
    }, () => { flow.close(); router.push({ path: `/mitihani/${examId}/matokeo`, query: { tab: 'comments' } }) })
  } else if (everythingIn && canPublish.value) {
    flow.ask({
      trail: 'exam', current: 'marks', finished: true,
      title: t('examx.flow.allMarksTitle'),
      text: t('examx.flow.allMarksText', { name: exam.value.name }),
    }, () => { flow.close(); router.push({ path: '/mitihani', query: { publish: examId } }) })
  } else {
    flow.ask({
      trail: 'exam', current: 'marks', finished: true, info: true,
      title: t('examx.flow.yourMarksDoneTitle'),
      text: everythingIn ? t('examx.flow.waitHeadText') : t('examx.flow.othersWaitingText'),
    })
  }
}

// ── Spreadsheet in / out ────────────────────────────────────────────────────
const fileInput = ref(null)
const importProblems = ref([])

async function downloadTemplate() {
  try {
    await store.downloadMarksTemplate(examId, classId.value, subjectId.value)
  } catch (e) {
    await notify.fromError(e)
  }
}

async function onFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  if (isDirty.value && !(await confirmDiscard())) return
  busy.value = true
  importProblems.value = []
  try {
    const res = await store.importMarks(examId, classId.value, subjectId.value, file)
    applyGrid(res.data)
    notify.success(res.message)
    if (allEntered.value && !grid.value.submitted) offerSubmit()
  } catch (e) {
    const problems = e.response?.data?.details?.problems
    if (problems?.length) importProblems.value = problems
    else await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

const problemText = (p) => p.problem === 'student'
  ? t('examx.marks.problemStudent', { line: p.line, value: p.value })
  : t('examx.marks.problemMarks', { line: p.line, value: p.value, max: p.max })

// ── Tabs: the marks, the progress of every class and subject, and who changed which mark ──
const tab = ref('marks')
const progressKey = ref(0)
const changes = ref([])
const loadingChanges = ref(false)

const tabList = computed(() => [
  { key: 'marks', label: t('examx.marks.tabMarks') },
  { key: 'progress', label: t('examx.list.progress') },
  ...(canManage.value ? [{ key: 'history', label: t('examx.marks.history') }] : []),
])

// The numbers across the top.
const cards = computed(() => [
  { label: t('exams.list.name'), value: exam.value?.name ?? '—', hint: exam.value?.term },
  { label: t('exams.list.status'), value: exam.value ? t(`exams.status.${exam.value.status}`) : '—', hint: exam.value?.out_of && exam.value.out_of !== 100 ? t('examx.list.outOf', { total: exam.value.out_of }) : '' },
  { label: t('examx.marks.cardEntered'), value: grid.value ? `${entered.value} / ${rows.value.length}` : '—', hint: grid.value ? `${grid.value.subject.name} · ${grid.value.class.label}` : '' },
  { label: t('examx.marks.cardSubmitted'), value: grid.value ? (grid.value.submitted ? '✓' : '—') : '—', cls: grid.value?.submitted ? 'text-success' : '' },
  { label: t('examx.list.deadline'), value: exam.value?.marks_deadline || '—', warn: !!grid.value?.lock_reason, cls: grid.value?.lock_reason ? 'text-danger' : '' },
  { label: t('examx.marks.column'), value: `/ ${outOf.value}` },
])

// ── Print view: the marks sheet as it is on screen (blank cells stay blank, to be written by hand) ──
const printing = ref(false)
const printLines = computed(() => [
  [exam.value?.name, exam.value?.term].filter(Boolean).join(' · '),
  `${t('exams.marks.class')}: ${grid.value?.class.label ?? ''}   ${t('exams.marks.subject')}: ${grid.value?.subject.name ?? ''}   ${t('examx.marks.column')}: / ${outOf.value}`,
])
const printColumns = computed(() => [
  { label: '#', value: (r, i) => i + 1, align: 'center' },
  { label: t('exams.marks.student'), value: (r) => r.full_name },
  { label: t('exams.marks.admission'), value: (r) => r.admission_number },
  { label: `${t('examx.marks.column')} / ${outOf.value}`, value: (r) => (r.absent ? null : r.marks === '' ? '' : r.marks), align: 'center', bold: true },
  { label: t('exams.marks.grade'), value: (r) => (r.absent ? null : gradeFor(r.marks)), align: 'center' },
  { label: t('exams.marks.absent'), value: (r) => (r.absent ? '✓' : ''), align: 'center' },
  { label: t('exams.marks.remarks'), value: (r) => r.remarks },
])

function pickFromProgress({ classId: c, subjectId: s }) {
  tab.value = 'marks'
  classId.value = c
  nextTick(async () => {
    subjectId.value = s
    await onSelectionChange()
  })
}

async function loadHistory() {
  loadingChanges.value = true
  try {
    changes.value = (await store.getMarkChanges(examId, subjectId.value)).data
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loadingChanges.value = false
  }
}

watch(tab, (now) => {
  if (now === 'history') loadHistory()
  if (now === 'progress') progressKey.value++
})

const markText = (marks, absent) => (absent ? t('exams.results.absentShort') : marks === null ? '—' : Number(marks))
const when = (iso) => (iso ? new Date(iso).toLocaleString() : '')

onMounted(loadSetup)
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />

    <SupBar v-model="tab" :tabs="tabList">
      <CButton color="secondary" variant="outline" @click="router.push('/mitihani')">‹ {{ t('exams.backToExams') }}</CButton>
      <template v-if="tab === 'marks'">
        <CFormSelect v-model="classId" :aria-label="t('exams.marks.class')" @change="onSelectionChange">
          <option value="">{{ t('exams.marks.selectClass') }}</option>
          <option v-for="c in classOptions" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
        </CFormSelect>
        <CButton color="secondary" variant="outline" :disabled="!grid" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormSelect v-model="subjectId" :disabled="!classId" :aria-label="t('exams.marks.subject')" @change="onSelectionChange">
          <option value="">{{ t('exams.marks.selectSubject') }}</option>
          <option v-for="s in subjectOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
      </template>
    </SupBar>

    <template v-if="tab === 'marks'">
    <div v-if="exam && !classOptions.length" class="alert alert-warning small">{{ t('examx.marks.noClasses') }}</div>
    <div v-else-if="classId && !subjectOptions.length" class="alert alert-warning small">{{ t('exams.marks.noSubjects') }}</div>

    <div v-if="mine.restricted && !mine.assignments.length" class="alert alert-warning small">{{ t('exams.marks.noAssignments') }}</div>

    <div v-if="!classId || !subjectId" class="text-center text-body-secondary py-5">{{ t('exams.marks.pickPrompt') }}</div>
    <div v-else-if="loadingGrid" class="text-center py-5"><CSpinner color="primary" /></div>

    <CCard v-else-if="grid">
      <CCardBody>
        <div v-if="grid.lock_reason" class="alert alert-warning small">🔒 {{ grid.lock_reason }}</div>
        <div v-else-if="!editable" class="alert alert-info small">
          {{ t('exams.marks.readOnly', { status: t(`exams.status.${grid.exam.status}`) }) }}
        </div>
        <div v-if="importProblems.length" class="alert alert-danger small">
          <div class="fw-semibold mb-1">{{ t('examx.marks.importProblems', { count: importProblems.length }) }}</div>
          <ul class="mb-0 ps-3"><li v-for="(p, i) in importProblems.slice(0, 15)" :key="i">{{ problemText(p) }}</li></ul>
          <div v-if="importProblems.length > 15" class="mt-1">{{ t('examx.marks.andMore', { count: importProblems.length - 15 }) }}</div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
          <div class="small text-body-secondary">
            {{ t('exams.marks.progress', { entered, total: rows.length }) }}
            <CBadge v-if="isDirty" color="warning" class="ms-2">{{ t('exams.marks.unsaved') }}</CBadge>
            <CBadge v-if="grid.submitted" color="success" class="ms-2">✓ {{ t('examx.progress.submitted') }}</CBadge>
          </div>
          <CProgress class="flex-grow-1" style="max-width:220px; height:8px;" color="success"
                     :value="rows.length ? (entered / rows.length) * 100 : 0" />
          <div class="sup-toolbar d-flex flex-wrap gap-2">
            <CButton color="secondary" variant="outline" size="sm" @click="downloadTemplate">⬇ {{ t('examx.marks.template') }}</CButton>
            <template v-if="exam?.status === 'open'">
              <CButton color="secondary" variant="outline" size="sm" :disabled="!editable || busy" @click="fileInput.click()">⬆ {{ t('examx.marks.import') }}</CButton>
              <input ref="fileInput" type="file" accept=".csv,.xlsx,.xls,.txt" class="d-none" @change="onFile" />
            </template>
          </div>
        </div>

        <div v-if="!rows.length" class="text-center text-body-secondary py-4">{{ t('exams.marks.noStudents') }}</div>

        <CTable v-else responsive hover align="middle" class="sup-grid mb-3 marks-table">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell style="width:40px;">#</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.marks.student') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('exams.marks.admission') }}</CTableHeaderCell>
              <CTableHeaderCell style="width:140px;">{{ t('examx.marks.column') }} <span class="text-body-secondary fw-normal">/ {{ outOf }}</span></CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:70px;">{{ t('exams.marks.grade') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:90px;">{{ t('exams.marks.absent') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('exams.marks.remarks') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(row, i) in rows" :key="row.student_id" :class="{ 'table-danger': errorFor(i) }">
              <CTableDataCell class="text-body-secondary">{{ i + 1 }}</CTableDataCell>
              <CTableDataCell class="fw-semibold">
                {{ row.full_name }}
                <div v-if="errorFor(i)" class="small text-danger fw-normal">{{ errorFor(i) }}</div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell text-body-secondary">{{ row.admission_number }}</CTableDataCell>
              <CTableDataCell>
                <input v-model="row.marks" class="form-control form-control-sm text-end" :class="{ 'is-invalid': errorFor(i) }"
                       inputmode="decimal" autocomplete="off" :data-mark-row="i"
                       :disabled="!editable || row.absent" @keydown.enter.prevent="focusNext(i)" />
              </CTableDataCell>
              <CTableDataCell class="text-center fw-bold">{{ row.absent ? '—' : gradeFor(row.marks) }}</CTableDataCell>
              <CTableDataCell class="text-center">
                <CFormCheck v-model="row.absent" :disabled="!editable" @change="onAbsentToggle(row)" />
              </CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell">
                <input v-model="row.remarks" class="form-control form-control-sm" maxlength="255" :disabled="!editable" />
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>

        <div v-if="rows.length" class="d-flex flex-wrap justify-content-end gap-2">
          <CButton v-if="canManage && grid.submitted && exam?.status === 'open'" color="warning" variant="outline" :disabled="busy" @click="reopen">
            ↩ {{ t('examx.marks.reopen') }}
          </CButton>
          <CButton v-if="editable && !grid.submitted" color="success" variant="outline"
                   :disabled="busy || isDirty || !allEntered" :title="isDirty ? t('examx.marks.saveFirst') : (allEntered ? '' : t('examx.marks.enterAll'))"
                   @click="submit(false)">
            ✓ {{ t('examx.marks.submit') }}
          </CButton>
          <CButton v-if="editable" color="primary" :disabled="saving || !isDirty" @click="save">
            <CSpinner v-if="saving" size="sm" class="me-1" />{{ saving ? t('exams.marks.saving') : t('exams.marks.save') }}
          </CButton>
        </div>
      </CCardBody>
    </CCard>

    </template>

    <PrintSheet v-if="grid" v-model="printing" :title="t('examx.marks.sheetTitle')" :lines="printLines" :columns="printColumns" :rows="rows" />

    <!-- Progress of every class and subject -->
    <ExamProgressPanel v-else-if="tab === 'progress'" :exam-id="examId" :refresh-key="progressKey" :can-remind="canManage && exam?.status === 'open'"
                       :selectable="exam?.status === 'open'" @select="pickFromProgress" />

    <!-- Who changed which mark -->
    <CCard v-else-if="tab === 'history'">
      <CCardBody class="p-0 sup-page">
        <div v-if="loadingChanges" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else-if="!changes.length" class="text-center text-body-secondary py-4">{{ t('examx.marks.noChanges') }}</div>
        <CTable v-else responsive class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('examx.marks.when') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.marks.student') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.marks.subject') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('examx.marks.from') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('examx.marks.to') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('examx.marks.by') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="c in changes" :key="c.id">
              <CTableDataCell class="small">{{ when(c.at) }}</CTableDataCell>
              <CTableDataCell>{{ c.student }}</CTableDataCell>
              <CTableDataCell>{{ c.subject }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ markText(c.old_marks, c.old_absent) }}</CTableDataCell>
              <CTableDataCell class="text-center fw-semibold">{{ markText(c.new_marks, c.new_absent) }}</CTableDataCell>
              <CTableDataCell class="small">{{ c.changed_by || '—' }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>
  </CContainer>
</template>
