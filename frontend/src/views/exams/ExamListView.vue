<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ExamSetupGuide from '@/components/exams/ExamSetupGuide.vue'
import ExamProgressPanel from '@/components/exams/ExamProgressPanel.vue'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useExamsStore } from '@/stores/exams'
import { useExamFlowStore } from '@/stores/examFlow'
import { useNotify } from '@/composables/useNotify'
import { canManageExams, canPublishExams, EXAM_TYPES, STATUS_COLOR } from '@/utils/examRoles'
import { targetFor } from '@/utils/examSetup'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const store = useExamsStore()
const flow = useExamFlowStore()
const notify = useNotify()

const canManage = computed(() => canManageExams(auth.role))
const canPublish = computed(() => canPublishExams(auth.role))

// ── List ────────────────────────────────────────────────────────────────────
const exams = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const terms = ref([])
const classes = ref([])
const readiness = ref(null)
const ready = computed(() => readiness.value?.ready !== false)
const filters = ref({ search: '', status: '', term_id: '' })
const page = ref(1)

const counts = ref({ all: 0, draft: 0, open: 0, published: 0 })

// The status tabs drive the status filter ('' = all).
const statusTab = computed({
  get: () => filters.value.status || 'all',
  set: (v) => { filters.value.status = v === 'all' ? '' : v },
})
const tabList = computed(() => ['all', 'draft', 'open', 'published'].map((k) => ({
  key: k,
  label: k === 'all' ? t('examx.list.tabAll') : t(`exams.status.${k}`),
  badge: k === 'open' && counts.value.open ? counts.value.open : 0,
})))
const cards = computed(() => [
  { label: t('examx.list.cardAll'), value: counts.value.all },
  { label: t('exams.status.draft'), value: counts.value.draft },
  { label: t('examx.list.cardOpen'), value: counts.value.open, warn: true },
  { label: t('exams.status.published'), value: counts.value.published, cls: 'text-success' },
  { label: t('examx.list.cardSetup'), value: readiness.value ? (readiness.value.complete ? '✓' : t('examx.list.cardSetupTodo')) : '—', warn: !!readiness.value && !readiness.value.complete, cls: readiness.value?.complete ? 'text-success' : '' },
  { label: t('examx.list.cardClasses'), value: classes.value.length },
])

const asRows = (d) => (Array.isArray(d) ? d : d?.data ?? [])
const termLabel = (term) => [term.name, term.academic_year?.name ?? term.academicYear?.name].filter(Boolean).join(' · ')

async function load() {
  loading.value = true
  try {
    const params = { page: page.value }
    for (const [key, value] of Object.entries(filters.value)) if (value) params[key] = value
    const res = await store.listExams(params)
    exams.value = res.data
    if (res.counts) counts.value = res.counts
    meta.value = res.meta ?? { current_page: 1, last_page: 1, total: res.data.length }
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

async function loadLookups() {
  try {
    const [termRes, classRes, readyRes] = await Promise.all([
      api.get('/terms'), api.get('/school-classes'), store.getReadiness(),
    ])
    terms.value = asRows(termRes.data)
    classes.value = asRows(classRes.data)
    readiness.value = readyRes.data
  } catch (e) {
    await notify.fromError(e)
  }
}

const classLabel = (c) => [c.name, c.stream].filter(Boolean).join(' ')

function toggleClass(id) {
  const ids = form.value.class_ids
  form.value.class_ids = ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]
}

/** "Whole school", or the classes an exam was limited to. */
const scopeLabel = (exam) => (exam.all_classes ? t('exams.form.wholeSchool') : (exam.classes ?? []).join(', '))

let searchTimer = null
watch(() => filters.value.search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { page.value = 1; load() }, 350)
})
watch(() => [filters.value.status, filters.value.term_id], () => { page.value = 1; load() })

const goToPage = (p) => { page.value = p; load() }

// "New exam": when the set-up is not finished, say which step is missing and offer to go and do it.
function newExam() {
  if (ready.value) return openForm()
  const next = readiness.value?.next
  const to = next ? targetFor(next, auth.role) : null
  flow.ask({
    title: t('examx.missing.finishSetupTitle'),
    text: t('examx.missing.finishSetupText', { step: next ? t(`exams.guide.steps.${next}.name`) : '', hint: next ? t(`exams.guide.steps.${next}.hint`) : '' }),
    info: !to,
  }, () => { flow.close(); flow.startSetup(); router.push(to) })
}

// ── Create / edit ───────────────────────────────────────────────────────────
const formOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({})
const fieldErrors = ref({})

const blankForm = () => ({
  name: '', type: 'midterm', term_id: '', start_date: '', end_date: '', all_classes: true, class_ids: [],
  out_of: 100, marks_deadline: '', next_term_opens: '', hold_for_fees: false, require_comments: true,
})

function openForm(exam = null) {
  editing.value = exam
  fieldErrors.value = {}
  form.value = exam
    ? {
        name: exam.name, type: exam.type, term_id: exam.term_id, start_date: exam.start_date ?? '', end_date: exam.end_date ?? '',
        all_classes: exam.all_classes, class_ids: [...(exam.class_ids ?? [])],
        out_of: exam.out_of ?? 100, marks_deadline: exam.marks_deadline ?? '', next_term_opens: exam.next_term_opens ?? '',
        hold_for_fees: !!exam.hold_for_fees, require_comments: !!exam.require_comments,
      }
    : blankForm()
  formOpen.value = true
}

const firstError = (field) => fieldErrors.value[field]?.[0]

async function saveForm() {
  saving.value = true
  fieldErrors.value = {}
  const payload = {
    ...form.value,
    start_date: form.value.start_date || null,
    end_date: form.value.end_date || null,
    marks_deadline: form.value.marks_deadline || null,
    next_term_opens: form.value.next_term_opens || null,
    out_of: Number(form.value.out_of) || 100,
  }
  const wasNew = !editing.value
  try {
    const res = editing.value ? await store.updateExam(editing.value.id, payload) : await store.createExam(payload)
    formOpen.value = false
    notify.success(res.message)
    await load()
    if (wasNew) offerOpen(res.data)
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      // Shown next to the fields, so no pop-up on top of the form.
      fieldErrors.value = e.response.data.errors
    } else {
      await notify.fromError(e)
    }
  } finally {
    saving.value = false
  }
}

// ── Guided journey: create -> open -> enter marks -> publish -> report cards ──
function offerOpen(exam) {
  flow.ask({
    trail: 'exam', current: 'create', finished: true,
    title: t('examx.flow.createdTitle'),
    text: t('examx.flow.createdText', { name: exam.name }),
  }, async () => {
    await store.openExam(exam.id)
    await load()
    offerMarks({ ...exam, status: 'open' })
  })
}

function offerMarks(exam) {
  flow.ask({
    trail: 'exam', current: 'open', finished: true,
    title: t('examx.flow.openedTitle'),
    text: t('examx.flow.openedText', { name: exam.name }),
  }, () => { flow.close(); router.push(`/mitihani/${exam.id}/alama`) })
}

function offerResults(exam) {
  flow.ask({
    trail: 'exam', current: 'publish', finished: true,
    title: t('examx.flow.publishedTitle'),
    text: t('examx.flow.publishedText', { name: exam.name }),
  }, () => { flow.close(); router.push(`/mitihani/${exam.id}/matokeo`) })
}

// ── Workflow actions ────────────────────────────────────────────────────────
const busyId = ref(null)

async function run(exam, action, confirmKey) {
  const confirmed = await notify.confirm({
    title: t(`exams.confirm.${confirmKey}Title`),
    message: t(`exams.confirm.${confirmKey}Message`, { name: exam.name }),
    confirmText: t(`exams.confirm.${confirmKey}Action`),
    cancelText: t('exams.confirm.cancel'),
    danger: confirmKey === 'delete',
  })
  if (!confirmed) return null

  busyId.value = exam.id
  try {
    const res = await action(exam.id)
    notify.success(res.message)
    await load()
    return res
  } catch (e) {
    await notify.fromError(e)
    return null
  } finally {
    busyId.value = null
  }
}

async function openExam(exam) {
  if (await run(exam, store.openExam, 'open')) offerMarks(exam)
}
const deleteExam = (exam) => run(exam, store.deleteExam, 'delete')

// Publish: a card with the option to text the parents
const publishFor = ref(null)
const smsParents = ref(false)
const publishing = ref(false)

function askPublish(exam) {
  smsParents.value = false
  publishFor.value = exam
}

async function doPublish() {
  const exam = publishFor.value
  publishing.value = true
  try {
    const res = await store.publishExam(exam.id, { sms_parents: smsParents.value })
    publishFor.value = null
    notify.success(res.sms_sent ? `${res.message} ${t('examx.publish.smsSent', { count: res.sms_sent })}` : res.message)
    await load()
    offerResults(exam)
  } catch (e) {
    const pending = e.response?.data?.code === 'COMMENTS_PENDING' ? e.response.data.details?.classes : null
    if (pending?.length) {
      // The comments are not finished: say so and take the user to the class that is waiting.
      publishFor.value = null
      flow.ask({
        trail: 'exam', current: 'comments', finished: false,
        title: t('examx.flow.commentsPendingTitle'),
        text: t('examx.flow.commentsPendingText', { classes: pending.map((c) => c.class).join(', ') }),
      }, () => { flow.close(); router.push({ path: `/mitihani/${exam.id}/matokeo`, query: { tab: 'comments', class: pending[0].class_id } }) })
    } else {
      await notify.fromError(e)
    }
  } finally {
    publishing.value = false
  }
}

// Unpublish: always with a reason, which goes into the audit log
const unpublishFor = ref(null)
const reason = ref('')
const reasonError = ref('')

function askUnpublish(exam) {
  reason.value = ''
  reasonError.value = ''
  unpublishFor.value = exam
}

async function doUnpublish() {
  if (reason.value.trim().length < 5) {
    reasonError.value = t('examx.unpublish.reasonRequired')
    return
  }
  publishing.value = true
  try {
    const res = await store.unpublishExam(unpublishFor.value.id, reason.value.trim())
    unpublishFor.value = null
    notify.success(res.message)
    await load()
  } catch (e) {
    await notify.fromError(e)
  } finally {
    publishing.value = false
  }
}

// Progress of one exam
const progressFor = ref(null)
const openProgress = (exam) => { progressFor.value = exam }
function goMarks({ classId, subjectId }) {
  const id = progressFor.value.id
  progressFor.value = null
  router.push({ path: `/mitihani/${id}/alama`, query: { class: classId, subject: subjectId } })
}

// ── Print view: the list as it is on screen ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('exams.list.name'), value: (e) => e.name },
  { label: t('exams.form.type'), value: (e) => t(`exams.types.${e.type}`) },
  { label: t('exams.results.class'), value: (e) => scopeLabel(e) },
  { label: t('exams.list.term'), value: (e) => [e.term, e.academic_year].filter(Boolean).join(' · ') },
  { label: t('exams.list.dates'), value: (e) => dates(e) },
  { label: t('examx.list.deadline'), value: (e) => e.marks_deadline },
  { label: t('exams.list.status'), value: (e) => t(`exams.status.${e.status}`) },
  { label: t('exams.list.marksEntered'), value: (e) => e.results_count ?? 0, align: 'end' },
])
const printLines = computed(() => {
  const lines = []
  if (filters.value.status) lines.push(`${t('exams.list.status')}: ${t(`exams.status.${filters.value.status}`)}`)
  const term = terms.value.find((x) => String(x.id) === String(filters.value.term_id))
  if (term) lines.push(`${t('exams.list.term')}: ${termLabel(term)}`)
  if (filters.value.search) lines.push(`${t('printing.page')}: ${filters.value.search}`)
  return lines
})

const dates = (exam) => [exam.start_date, exam.end_date].filter(Boolean).join(' → ') || '—'

onMounted(async () => {
  await Promise.all([load(), loadLookups()])
  // Links from the guided flow: ?new=1 opens the form, ?publish=<id> opens the publish card.
  if (route.query.new && canManage.value && ready.value) {
    openForm()
    router.replace({ path: route.path })
  } else if (route.query.publish) {
    const exam = exams.value.find((e) => String(e.id) === String(route.query.publish))
    if (exam && exam.status === 'open' && canPublish.value) askPublish(exam)
    router.replace({ path: route.path })
  }
})
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />
    <ExamSetupGuide v-if="canManage" :readiness="readiness" />

    <!-- Tabs by state, and the page's controls, on one row (same bar as /wasambazaji) -->
    <SupBar v-model="statusTab" :tabs="tabList">
      <CFormInput v-model="filters.search" :placeholder="t('exams.list.search')" style="max-width:200px !important;" />
      <CFormSelect v-model="filters.term_id">
        <option value="">{{ t('exams.list.allTerms') }}</option>
        <option v-for="term in terms" :key="term.id" :value="term.id">{{ termLabel(term) }}</option>
      </CFormSelect>
      <CButton color="secondary" variant="outline" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
      <CButton v-if="canManage" color="secondary" variant="outline" @click="router.push('/mitihani/mipangilio')">⚙️ {{ t('exams.setup') }}</CButton>
      <CButton color="secondary" variant="outline" @click="router.push('/mitihani/mchanganyiko')">🧮 {{ t('examx.combined.menu') }}</CButton>
      <CButton v-if="canManage" color="success" @click="newExam">＋ {{ t('exams.list.newExam') }}</CButton>
    </SupBar>
    <div v-if="canManage && readiness && !ready" class="alert alert-info py-2 small">{{ t('examx.list.finishSetup') }}</div>

    <CCard>
      <CCardBody class="p-0">
        <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
        <CTable v-else responsive hover align="middle" class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('exams.list.name') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('exams.list.term') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('exams.list.dates') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-xl-table-cell">{{ t('examx.list.deadline') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.list.status') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-sm-table-cell text-center">{{ t('exams.list.marksEntered') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center sup-actions">{{ t('exams.list.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(exam, i) in exams" :key="exam.id">
              <CTableDataCell>
                <div class="fw-semibold">{{ exam.name }}</div>
                <div class="small text-body-secondary">
                  {{ t(`exams.types.${exam.type}`) }} · {{ scopeLabel(exam) }}
                  <template v-if="exam.out_of && exam.out_of !== 100"> · {{ t('examx.list.outOf', { total: exam.out_of }) }}</template>
                </div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">
                {{ exam.term }}<div class="small text-body-secondary">{{ exam.academic_year }}</div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell small">{{ dates(exam) }}</CTableDataCell>
              <CTableDataCell class="d-none d-xl-table-cell small">{{ exam.marks_deadline || '—' }}</CTableDataCell>
              <CTableDataCell>
                <CBadge :color="STATUS_COLOR[exam.status]">{{ t(`exams.status.${exam.status}`) }}</CBadge>
                <CBadge v-if="exam.hold_for_fees" color="warning" class="ms-1" :title="t('examx.list.holdHint')">💰</CBadge>
              </CTableDataCell>
              <CTableDataCell class="d-none d-sm-table-cell text-center">{{ exam.results_count ?? 0 }}</CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown :placement="menuPlacement(i, exams.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('exams.list.actions')" :disabled="busyId === exam.id">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem v-if="exam.status === 'draft' && canManage" @click="openExam(exam)">{{ t('exams.list.open') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status === 'open'" @click="router.push(`/mitihani/${exam.id}/alama`)">{{ t('exams.list.enterMarks') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status !== 'draft'" @click="openProgress(exam)">{{ t('examx.list.progress') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status !== 'draft'" @click="router.push(`/mitihani/${exam.id}/matokeo`)">{{ t('exams.list.results') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status === 'open' && canPublish" @click="askPublish(exam)">{{ t('exams.list.publish') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status === 'published' && canPublish" @click="askUnpublish(exam)">{{ t('exams.list.unpublish') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status !== 'published' && canManage" @click="openForm(exam)">{{ t('examx.list.edit') }}</CDropdownItem>
                    <CDropdownItem v-if="exam.status !== 'published' && canManage" class="text-danger" @click="deleteExam(exam)">{{ t('examx.list.delete') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!exams.length">
              <CTableDataCell colspan="7" class="text-center text-body-secondary py-4">{{ t('exams.list.noExams') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3">
      <small class="text-medium-emphasis">{{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }}</small>
      <CPagination size="sm" class="mb-0">
        <CPaginationItem :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">‹</CPaginationItem>
        <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">›</CPaginationItem>
      </CPagination>
    </div>

    <!-- Create / edit -->
    <CModal :visible="formOpen" alignment="center" backdrop="static" size="lg" scrollable @close="formOpen = false">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('exams.form.editTitle') : t('exams.form.addTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('exams.form.name') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="form.name" :placeholder="t('exams.form.namePlaceholder')" :invalid="!!firstError('name')" />
          <div v-if="firstError('name')" class="invalid-feedback d-block">{{ firstError('name') }}</div>
        </div>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('exams.form.type') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.type" :invalid="!!firstError('type')">
              <option v-for="type in EXAM_TYPES" :key="type" :value="type">{{ t(`exams.types.${type}`) }}</option>
            </CFormSelect>
            <div v-if="firstError('type')" class="invalid-feedback d-block">{{ firstError('type') }}</div>
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('exams.form.term') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.term_id" :invalid="!!firstError('term_id')">
              <option value="">{{ t('exams.form.selectTerm') }}</option>
              <option v-for="term in terms" :key="term.id" :value="term.id">{{ termLabel(term) }}</option>
            </CFormSelect>
            <div v-if="firstError('term_id')" class="invalid-feedback d-block">{{ firstError('term_id') }}</div>
          </CCol>
        </CRow>
        <div class="mb-3">
          <CFormSwitch v-model="form.all_classes" :label="t('exams.form.wholeSchool')" />
          <div class="small text-body-secondary">{{ t(form.all_classes ? 'exams.form.wholeSchoolHint' : 'exams.form.specificHint') }}</div>
          <div v-if="!form.all_classes" class="mt-2">
            <div class="d-flex flex-wrap gap-2">
              <CFormCheck v-for="c in classes" :key="c.id" :id="`exam-class-${c.id}`" :label="classLabel(c)"
                          :checked="form.class_ids.includes(c.id)" @change="toggleClass(c.id)" />
            </div>
            <div v-if="firstError('class_ids') || firstError('class_ids.0')" class="invalid-feedback d-block">
              {{ firstError('class_ids') || firstError('class_ids.0') }}
            </div>
          </div>
        </div>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('exams.form.startDate') }} <span class="text-body-secondary fw-normal">({{ t('examx.form.optional') }})</span></label>
            <CFormInput v-model="form.start_date" type="date" :invalid="!!firstError('start_date')" />
            <div v-if="firstError('start_date')" class="invalid-feedback d-block">{{ firstError('start_date') }}</div>
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('exams.form.endDate') }} <span class="text-body-secondary fw-normal">({{ t('examx.form.optional') }})</span></label>
            <CFormInput v-model="form.end_date" type="date" :invalid="!!firstError('end_date')" />
            <div v-if="firstError('end_date')" class="invalid-feedback d-block">{{ firstError('end_date') }}</div>
          </CCol>
        </CRow>

        <div class="border rounded p-3">
          <div class="fw-semibold mb-2">{{ t('examx.form.moreTitle') }}</div>
          <CRow class="g-3">
            <CCol sm="4">
              <label class="form-label">{{ t('examx.form.outOf') }}</label>
              <CFormInput v-model="form.out_of" type="number" min="10" max="1000" :disabled="!!editing && (editing.results_count ?? 0) > 0" :invalid="!!firstError('out_of')" />
              <div class="form-text">{{ t('examx.form.outOfHint') }}</div>
              <div v-if="firstError('out_of')" class="invalid-feedback d-block">{{ firstError('out_of') }}</div>
            </CCol>
            <CCol sm="4">
              <label class="form-label">{{ t('examx.form.deadline') }} <span class="text-body-secondary">({{ t('examx.form.optional') }})</span></label>
              <CFormInput v-model="form.marks_deadline" type="date" :invalid="!!firstError('marks_deadline')" />
              <div class="form-text">{{ t('examx.form.deadlineHint') }}</div>
              <div v-if="firstError('marks_deadline')" class="invalid-feedback d-block">{{ firstError('marks_deadline') }}</div>
            </CCol>
            <CCol sm="4">
              <label class="form-label">{{ t('examx.form.nextTerm') }} <span class="text-body-secondary">({{ t('examx.form.optional') }})</span></label>
              <CFormInput v-model="form.next_term_opens" type="date" :invalid="!!firstError('next_term_opens')" />
              <div class="form-text">{{ t('examx.form.nextTermHint') }}</div>
            </CCol>
          </CRow>
          <div class="mt-3">
            <CFormSwitch v-model="form.hold_for_fees" :label="t('examx.form.holdFees')" />
            <div class="small text-body-secondary">{{ t('examx.form.holdFeesHint') }}</div>
          </div>
          <div class="mt-3">
            <CFormSwitch v-model="form.require_comments" :label="t('examx.form.requireComments')" />
            <div class="small text-body-secondary">{{ t('examx.form.requireCommentsHint') }}</div>
          </div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" :disabled="saving" @click="formOpen = false">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="saveForm">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('exams.form.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Publish -->
    <CModal :visible="!!publishFor" alignment="center" backdrop="static" @close="publishFor = null">
      <CModalHeader><CModalTitle>{{ t('exams.confirm.publishTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="publishFor">
        <p>{{ t('exams.confirm.publishMessage', { name: publishFor.name }) }}</p>
        <CFormSwitch v-model="smsParents" :label="t('examx.publish.sms')" />
        <div class="small text-body-secondary">{{ t('examx.publish.smsHint') }}</div>
        <div v-if="publishFor.hold_for_fees" class="small text-warning mt-2">💰 {{ t('examx.publish.holdNote') }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" :disabled="publishing" @click="publishFor = null">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="success" :disabled="publishing" @click="doPublish">
          <CSpinner v-if="publishing" size="sm" class="me-1" />{{ t('exams.confirm.publishAction') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Unpublish needs a reason -->
    <CModal :visible="!!unpublishFor" alignment="center" backdrop="static" @close="unpublishFor = null">
      <CModalHeader><CModalTitle>{{ t('exams.confirm.unpublishTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="unpublishFor">
        <p>{{ t('exams.confirm.unpublishMessage', { name: unpublishFor.name }) }}</p>
        <label class="form-label fw-semibold">{{ t('examx.unpublish.reason') }} <span class="text-danger">*</span></label>
        <CFormTextarea v-model="reason" rows="3" maxlength="500" :invalid="!!reasonError" :placeholder="t('examx.unpublish.placeholder')" />
        <div v-if="reasonError" class="invalid-feedback d-block">{{ reasonError }}</div>
        <div class="form-text">{{ t('examx.unpublish.hint') }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" :disabled="publishing" @click="unpublishFor = null">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="warning" :disabled="publishing" @click="doUnpublish">
          <CSpinner v-if="publishing" size="sm" class="me-1" />{{ t('exams.confirm.unpublishAction') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Progress -->
    <CModal :visible="!!progressFor" size="xl" scrollable alignment="center" @close="progressFor = null">
      <CModalHeader><CModalTitle>{{ t('examx.list.progress') }} — {{ progressFor?.name }}</CModalTitle></CModalHeader>
      <CModalBody v-if="progressFor">
        <ExamProgressPanel :exam-id="progressFor.id" :can-remind="canManage && progressFor.status === 'open'"
                           :selectable="progressFor.status === 'open'" @select="goMarks" />
      </CModalBody>
      <CModalFooter><CButton color="secondary" variant="outline" @click="progressFor = null">{{ t('examx.flow.ok') }}</CButton></CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('exams.title')" :lines="printLines" :columns="printColumns" :rows="exams" landscape />
  </CContainer>
</template>
