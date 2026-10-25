<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ExamClassesTab from '@/components/exams/ExamClassesTab.vue'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'
import ExamTeachersTab from '@/components/exams/ExamTeachersTab.vue'
import { useI18n } from 'vue-i18n'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'
import { useExamFlow } from '@/stores/examFlow'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const store = useExamsStore()
const notify = useNotify()
const { flow, afterSetupStep } = useExamFlow()

// ?tab= lets the setup guide on the exam list open the step that comes next.
const TABS = ['classes', 'subjects', 'classSubjects', 'teachers', 'grading']
const tab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'classes')
const loading = ref(true)

// ── Subjects ────────────────────────────────────────────────────────────────
const subjects = ref([])
const subjectFormOpen = ref(false)
const editingSubject = ref(null)
const subjectForm = ref({ name: '', code: '', is_active: true })
const subjectErrors = ref({})
const savingSubject = ref(false)
const firstError = (field) => subjectErrors.value[field]?.[0]

async function loadSubjects() {
  subjects.value = (await store.listSubjects()).data
}

function openSubjectForm(subject = null) {
  editingSubject.value = subject
  subjectErrors.value = {}
  subjectForm.value = subject
    ? { name: subject.name, code: subject.code ?? '', is_active: subject.is_active }
    : { name: '', code: '', is_active: true }
  subjectFormOpen.value = true
}

async function saveSubject() {
  savingSubject.value = true
  subjectErrors.value = {}
  try {
    const payload = { ...subjectForm.value, code: subjectForm.value.code || null }
    const res = editingSubject.value
      ? await store.updateSubject(editingSubject.value.id, payload)
      : await store.createSubject(payload)
    subjectFormOpen.value = false
    notify.success(res.message)
    await Promise.all([loadSubjects(), loadClassSubjects()])
    if (!editingSubject.value) afterSetupStep('subjects')
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) subjectErrors.value = e.response.data.errors
    else await notify.fromError(e)
  } finally {
    savingSubject.value = false
  }
}

async function removeSubject(subject) {
  const ok = await notify.confirm({
    title: t('exams.settings.deleteSubjectTitle'),
    message: t('exams.settings.deleteSubjectMessage', { name: subject.name }),
    confirmText: t('exams.list.delete'),
    cancelText: t('exams.confirm.cancel'),
    danger: true,
  })
  if (!ok) return
  try {
    notify.success((await store.deleteSubject(subject.id)).message)
    await Promise.all([loadSubjects(), loadClassSubjects()])
  } catch (e) {
    await notify.fromError(e)
  }
}

// ── Class subjects ──────────────────────────────────────────────────────────
const classes = ref([])
const selection = ref({}) // classId -> array of subject ids being edited
const saved = ref({}) // classId -> array of subject ids on the server
const savingClass = ref(null)

const activeSubjects = computed(() => subjects.value.filter((s) => s.is_active))
const sameSet = (a, b) => a.length === b.length && a.every((id) => b.includes(id))
const classDirty = (id) => !sameSet(selection.value[id] ?? [], saved.value[id] ?? [])

async function loadClassSubjects() {
  classes.value = (await store.listClassSubjects()).data
  saved.value = Object.fromEntries(classes.value.map((c) => [c.id, [...c.subject_ids]]))
  selection.value = Object.fromEntries(classes.value.map((c) => [c.id, [...c.subject_ids]]))
}

function toggleSubject(classId, subjectId) {
  const set = new Set(selection.value[classId] ?? [])
  set.has(subjectId) ? set.delete(subjectId) : set.add(subjectId)
  selection.value[classId] = [...set]
}

async function saveClass(c) {
  savingClass.value = c.id
  try {
    const res = await store.syncClassSubjects(c.id, selection.value[c.id] ?? [])
    saved.value[c.id] = [...res.data.subject_ids]
    selection.value[c.id] = [...res.data.subject_ids]
    notify.success(res.message)
    afterSetupStep('class_subjects')
  } catch (e) {
    await notify.fromError(e)
  } finally {
    savingClass.value = null
  }
}

async function onClassesChanged() {
  await loadClassSubjects()
  afterSetupStep('classes')
}

// ── Grading scale ───────────────────────────────────────────────────────────
const bands = ref([])
const savingScale = ref(false)

async function loadScale() {
  bands.value = (await store.getGradingScale()).data.bands.map((b) => ({ ...b, points: b.points ?? '', remark: b.remark ?? '' }))
}

const sortedBands = computed(() =>
  [...bands.value].sort((a, b) => Number(b.min_marks) - Number(a.min_marks)),
)

// The read-only "Range" column: each grade runs up to the next grade's start.
const rangeFor = (band) => {
  const higher = sortedBands.value.filter((b) => Number(b.min_marks) > Number(band.min_marks))
  const upper = higher.length ? Math.min(...higher.map((b) => Number(b.min_marks))) - 0.01 : 100
  return band.min_marks === '' ? '' : `${Number(band.min_marks)} – ${Number(upper.toFixed(2))}`
}

const addBand = () => bands.value.push({ grade: '', min_marks: '', points: '', remark: '' })
const removeBand = (index) => bands.value.splice(index, 1)

async function saveScale() {
  savingScale.value = true
  try {
    const payload = bands.value.map((b) => ({
      grade: b.grade,
      min_marks: b.min_marks === '' ? null : Number(b.min_marks),
      points: b.points === '' ? null : Number(b.points),
      remark: b.remark || null,
    }))
    const res = await store.saveGradingScale(payload)
    bands.value = res.data.bands.map((b) => ({ ...b, points: b.points ?? '', remark: b.remark ?? '' }))
    notify.success(res.message)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    savingScale.value = false
  }
}

const tabList = computed(() => TABS.map((k) => ({ key: k, label: t(`exams.settings.${k}`) })))
const cards = computed(() => [
  { label: t('exams.settings.classes'), value: classes.value.length },
  { label: t('exams.settings.subjects'), value: activeSubjects.value.length, hint: t('examx.settings.activeOf', { total: subjects.value.length }) },
  { label: t('exams.settings.classSubjects'), value: `${classes.value.filter((c) => (c.subject_ids ?? []).length).length} / ${classes.value.length}`, warn: classes.value.some((c) => !(c.subject_ids ?? []).length), hint: t('examx.settings.withSubjects') },
  { label: t('exams.settings.grading'), value: bands.value.length, hint: t('examx.settings.bands') },
])

// Each tab says out loud what it still needs and offers to jump there (inside this page).
function checkTab() {
  if (loading.value) return
  const go = (target, title, text, then) => flow.ask({ title: t(title), text: t(text) }, () => { flow.close(); tab.value = target; then?.() })
  if (tab.value === 'classSubjects' || tab.value === 'teachers') {
    if (!classes.value.length) return go('classes', 'examx.missing.tabNoClassesTitle', 'examx.missing.tabNoClassesText')
    if (!activeSubjects.value.length) return go('subjects', 'examx.missing.tabNoSubjectsTitle', 'examx.missing.tabNoSubjectsText', () => openSubjectForm())
  }
  if (tab.value === 'teachers' && classes.value.every((c) => !(c.subject_ids ?? []).length)) {
    return go('classSubjects', 'examx.missing.tabNoClassSubjectsTitle', 'examx.missing.tabNoClassSubjectsText')
  }
  if (tab.value === 'subjects' && !subjects.value.length) {
    flow.ask({ title: t('examx.missing.tabNoSubjectsTitle'), text: t('examx.missing.tabAddFirstSubject') }, () => { flow.close(); openSubjectForm() })
  }
}
watch([tab, loading], checkTab)

onMounted(async () => {
  try {
    await Promise.all([loadSubjects(), loadClassSubjects(), loadScale()])
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />

    <SupBar v-model="tab" :tabs="tabList">
      <CButton color="secondary" variant="outline" @click="router.push('/mitihani')">‹ {{ t('exams.backToExams') }}</CButton>
    </SupBar>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <!-- Classes -->
    <ExamClassesTab v-else-if="tab === 'classes'" @changed="onClassesChanged" />

    <!-- Teachers -->
    <ExamTeachersTab v-else-if="tab === 'teachers'" :classes="classes" :subjects="activeSubjects" @saved="afterSetupStep('teachers')" />

    <!-- Subjects -->
    <CCard v-else-if="tab === 'subjects'">
      <CCardBody>
        <div class="d-flex justify-content-end mb-3">
          <CButton color="primary" @click="openSubjectForm()">＋ {{ t('exams.settings.addSubject') }}</CButton>
        </div>
        <div v-if="!subjects.length" class="text-center text-body-secondary py-4">{{ t('exams.settings.noSubjects') }}</div>
        <CTable v-else responsive hover align="middle" class="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>{{ t('exams.settings.subjectName') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.settings.subjectCode') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('exams.settings.classesUsing') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('exams.list.status') }}</CTableHeaderCell>
              <CTableHeaderCell></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="s in subjects" :key="s.id">
              <CTableDataCell class="fw-semibold">{{ s.name }}</CTableDataCell>
              <CTableDataCell>{{ s.code || '—' }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ s.classes_count }}</CTableDataCell>
              <CTableDataCell>
                <CBadge :color="s.is_active ? 'success' : 'secondary'">{{ s.is_active ? t('exams.settings.active') : t('exams.settings.inactive') }}</CBadge>
              </CTableDataCell>
              <CTableDataCell class="text-end">
                <CButton size="sm" color="secondary" variant="ghost" @click="openSubjectForm(s)">✏️</CButton>
                <CButton size="sm" color="danger" variant="ghost" @click="removeSubject(s)">🗑️</CButton>
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Class subjects -->
    <div v-else-if="tab === 'classSubjects'">
      <div class="text-body-secondary small mb-3">{{ t('exams.settings.classSubjectsHint') }}</div>
      <div v-if="!activeSubjects.length" class="alert alert-warning small">{{ t('exams.settings.noSubjects') }}</div>
      <CRow class="g-3">
        <CCol v-for="c in classes" :key="c.id" md="6" xl="4">
          <CCard class="h-100">
            <CCardHeader class="fw-semibold d-flex justify-content-between align-items-center" style="min-height: 3.4rem;">
              {{ c.label }}
              <CBadge v-if="classDirty(c.id)" color="warning">{{ t('exams.marks.unsaved') }}</CBadge>
            </CCardHeader>
            <CCardBody>
              <div v-for="s in activeSubjects" :key="s.id" class="form-check mb-1">
                <input :id="`cs-${c.id}-${s.id}`" class="form-check-input" type="checkbox"
                       :checked="(selection[c.id] ?? []).includes(s.id)" @change="toggleSubject(c.id, s.id)" />
                <label class="form-check-label" :for="`cs-${c.id}-${s.id}`">{{ s.name }}</label>
              </div>
            </CCardBody>
            <CCardFooter class="text-end">
              <CButton size="sm" color="primary" :disabled="!classDirty(c.id) || savingClass === c.id" @click="saveClass(c)">
                <CSpinner v-if="savingClass === c.id" size="sm" class="me-1" />{{ t('exams.settings.saveClass') }}
              </CButton>
            </CCardFooter>
          </CCard>
        </CCol>
      </CRow>
    </div>

    <!-- Grading scale -->
    <CCard v-else>
      <CCardBody>
        <div class="text-body-secondary small mb-3">{{ t('exams.settings.gradingHint') }}</div>
        <CTable responsive align="middle">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell style="width:110px;">{{ t('exams.settings.grade') }}</CTableHeaderCell>
              <CTableHeaderCell style="width:150px;">{{ t('exams.settings.minMarks') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-sm-table-cell">{{ t('exams.settings.range') }}</CTableHeaderCell>
              <CTableHeaderCell style="width:110px;">{{ t('exams.settings.points') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('exams.settings.remark') }}</CTableHeaderCell>
              <CTableHeaderCell style="width:50px;"></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(band, i) in bands" :key="i">
              <CTableDataCell><input v-model="band.grade" class="form-control form-control-sm text-uppercase" maxlength="5" /></CTableDataCell>
              <CTableDataCell><input v-model="band.min_marks" class="form-control form-control-sm" inputmode="decimal" /></CTableDataCell>
              <CTableDataCell class="d-none d-sm-table-cell text-body-secondary">{{ rangeFor(band) }}</CTableDataCell>
              <CTableDataCell><input v-model="band.points" class="form-control form-control-sm" inputmode="numeric" /></CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell"><input v-model="band.remark" class="form-control form-control-sm" maxlength="100" /></CTableDataCell>
              <CTableDataCell><CButton size="sm" color="danger" variant="ghost" :title="t('exams.settings.removeBand')" @click="removeBand(i)">✕</CButton></CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <div class="d-flex justify-content-between">
          <CButton color="secondary" variant="outline" @click="addBand">＋ {{ t('exams.settings.addBand') }}</CButton>
          <CButton color="primary" :disabled="savingScale" @click="saveScale">
            <CSpinner v-if="savingScale" size="sm" class="me-1" />{{ t('exams.settings.saveScale') }}
          </CButton>
        </div>
      </CCardBody>
    </CCard>

    <!-- Subject form -->
    <CModal :visible="subjectFormOpen" alignment="center" backdrop="static" @close="subjectFormOpen = false">
      <CModalHeader>
        <CModalTitle>{{ editingSubject ? t('exams.settings.editSubject') : t('exams.settings.addSubject') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('exams.settings.subjectName') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="subjectForm.name" :invalid="!!firstError('name')" />
          <div v-if="firstError('name')" class="invalid-feedback d-block">{{ firstError('name') }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('exams.settings.subjectCode') }}</label>
          <CFormInput v-model="subjectForm.code" maxlength="20" :invalid="!!firstError('code')" />
          <div v-if="firstError('code')" class="invalid-feedback d-block">{{ firstError('code') }}</div>
        </div>
        <CFormSwitch v-model="subjectForm.is_active" :label="t('exams.settings.active')" />
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="subjectFormOpen = false">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="primary" :disabled="savingSubject" @click="saveSubject">
          <CSpinner v-if="savingSubject" size="sm" class="me-1" />{{ t('exams.settings.saveClass') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>
