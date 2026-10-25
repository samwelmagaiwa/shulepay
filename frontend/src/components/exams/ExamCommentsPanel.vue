<template>
  <div class="sup-page">
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <div v-else-if="!rows.length" class="text-center text-body-secondary py-4">{{ t('exams.marks.noStudents') }}</div>
    <template v-else>
      <!-- Where this class stands: class teacher first, then the head -->
      <CCard class="mb-3">
        <CCardBody>
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="fw-semibold">{{ t('examx.comments.stages') }}</span>
            <CButton size="sm" color="secondary" variant="outline" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
            <CBadge :color="stage?.teacher_signed ? 'success' : 'warning'">
              {{ stage?.teacher_signed ? `✓ ${t('examx.comments.teacherDone', { name: stage.teacher_signed.by || '' })}` : t('examx.comments.teacherWaiting') }}
            </CBadge>
            <span class="text-muted">›</span>
            <CBadge :color="stage?.head_signed ? 'success' : stage?.teacher_signed ? 'warning' : 'secondary'">
              {{ stage?.head_signed ? `✓ ${t('examx.comments.headDone', { name: stage.head_signed.by || '' })}` : t('examx.comments.headWaiting') }}
            </CBadge>
            <span class="small text-body-secondary ms-auto" v-if="stage">
              {{ t('examx.comments.missing', { teacher: stage.teacher_missing, head: stage.head_missing }) }}
            </span>
          </div>
          <div v-if="!editable" class="alert alert-info small mb-0">{{ t('examx.comments.locked') }}</div>
          <div v-else class="d-flex flex-wrap gap-2">
            <CButton v-if="!stage?.teacher_signed" color="primary" size="sm" :disabled="busy" @click="submitStage('teacher')">
              ✓ {{ t('examx.comments.submitTeacher') }}
            </CButton>
            <CButton v-if="canHead && stage?.teacher_signed && !stage?.head_signed" color="success" size="sm" :disabled="busy" @click="submitStage('head')">
              ✓ {{ t('examx.comments.approveHead') }}
            </CButton>
            <CButton v-if="canHead && (stage?.teacher_signed || stage?.head_signed)" color="warning" variant="outline" size="sm" :disabled="busy" @click="reopenStage">
              ↩ {{ t('examx.comments.reopen') }}
            </CButton>
          </div>
        </CCardBody>
      </CCard>

      <!-- One comment for everyone in a score band -->
      <CCard class="mb-3">
        <CCardBody class="p-0">
          <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom">
            <span class="fw-semibold">{{ t('examx.comments.bandsTitle') }}</span>
            <span class="small text-body-secondary">{{ t('examx.comments.bandsHint') }}</span>
            <CButton v-if="canManage" size="sm" color="secondary" variant="outline" class="ms-auto" @click="openBands">✎ {{ t('examx.comments.editBands') }}</CButton>
          </div>
          <CTable responsive class="sup-grid mb-0 align-top">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell style="width:110px;">{{ t('examx.comments.range') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('examx.comments.teacher') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('examx.comments.head') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="b in bands" :key="b.from">
                <CTableDataCell class="fw-semibold">{{ b.from }}–{{ b.to }}%</CTableDataCell>
                <CTableDataCell class="small">{{ b.teacher }}</CTableDataCell>
                <CTableDataCell class="small">{{ b.head }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
          <div v-if="editable" class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-top">
            <CFormSwitch v-model="overwrite" :label="t('examx.comments.overwrite')" class="me-2" />
            <CButton color="primary" size="sm" :disabled="busy" @click="apply('teacher', false)">{{ t('examx.comments.fillTeacher') }}</CButton>
            <CButton v-if="canHead" color="success" size="sm" :disabled="busy" @click="apply('head', false)">{{ t('examx.comments.fillHead') }}</CButton>
            <CButton v-if="canManage" color="secondary" variant="outline" size="sm" :disabled="busy" @click="apply(canHead ? 'both' : 'teacher', true)">
              {{ t('examx.comments.fillAllClasses') }}
            </CButton>
          </div>
        </CCardBody>
      </CCard>

      <!-- Exceptions: change a single pupil -->
      <CCard>
        <CCardBody class="p-0">
          <div class="px-3 py-2 border-bottom small text-body-secondary">{{ t('examx.comments.single') }}</div>
          <CTable responsive class="sup-grid mb-0 align-top">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell style="width:28%;">{{ t('exams.marks.student') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('examx.comments.teacher') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('examx.comments.head') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="r in rows" :key="r.student_id">
                <CTableDataCell>
                  <div class="fw-semibold">{{ r.full_name }}</div>
                  <div class="small text-body-secondary">{{ r.admission_number }}</div>
                </CTableDataCell>
                <CTableDataCell>
                  <CFormTextarea v-model="r.teacher_comment" rows="2" maxlength="500" :disabled="!editable || (stage?.teacher_signed && !canHead)" :invalid="editable && !r.teacher_comment" />
                </CTableDataCell>
                <CTableDataCell>
                  <CFormTextarea v-model="r.head_comment" rows="2" maxlength="500" :disabled="!editable || !canHead"
                                 :placeholder="canHead ? '' : t('examx.comments.headOnly')" :invalid="editable && canHead && !r.head_comment" />
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <div v-if="editable" class="d-flex justify-content-end mt-3">
        <CButton color="primary" :disabled="saving || !dirty" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('exams.marks.save') }}
        </CButton>
      </div>
    </template>

    <PrintSheet v-model="printing" :title="t('examx.results.tabs.comments')" :columns="printColumns" :rows="rows" />

    <!-- Edit the wording of the bands -->
    <CModal :visible="bandsOpen" size="lg" scrollable alignment="center" backdrop="static" @close="bandsOpen = false">
      <CModalHeader><CModalTitle>{{ t('examx.comments.editBands') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div class="small text-body-secondary mb-3">{{ t('examx.comments.bandsEditHint') }}</div>
        <div v-for="(b, i) in draft" :key="i" class="border rounded p-2 mb-2">
          <div class="d-flex align-items-center gap-2 mb-2">
            <label class="small mb-0">{{ t('examx.comments.from') }}</label>
            <CFormInput v-model="b.from" type="number" min="0" max="99" :disabled="i === 0" style="width:90px;" />
            <span class="small text-body-secondary">% {{ t('examx.comments.upwards') }}</span>
            <CButton v-if="draft.length > 1 && i > 0" size="sm" color="secondary" variant="ghost" class="ms-auto" @click="draft.splice(i, 1)">✕</CButton>
          </div>
          <label class="form-label small mb-1">{{ t('examx.comments.teacher') }}</label>
          <CFormTextarea v-model="b.teacher" rows="2" maxlength="500" class="mb-2" />
          <label class="form-label small mb-1">{{ t('examx.comments.head') }}</label>
          <CFormTextarea v-model="b.head" rows="2" maxlength="500" />
        </div>
        <CButton size="sm" color="secondary" variant="outline" :disabled="draft.length >= 8" @click="draft.push({ from: '', teacher: '', head: '' })">＋ {{ t('examx.comments.addBand') }}</CButton>
        <div v-if="bandsError" class="text-danger small mt-2">{{ bandsError }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="bandsOpen = false">{{ t('exams.confirm.cancel') }}</CButton>
        <CButton color="primary" :disabled="busy" @click="saveBands">{{ t('exams.marks.save') }}</CButton>
      </CModalFooter>
    </CModal>
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useExamsStore } from '@/stores/exams'
import { useExamFlowStore } from '@/stores/examFlow'
import { useNotify } from '@/composables/useNotify'
import { canManageExams, canPublishExams } from '@/utils/examRoles'

// Report-card comments: ready-made comments per score band, the single exceptions, and the two sign-offs
// (class teacher submits, head approves) that an exam may require before it can be published.
const props = defineProps({
  examId: { type: [Number, String], required: true },
  classId: { type: [Number, String], default: '' },
})
const emit = defineEmits(['saved', 'pick-class'])

const { t } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const store = useExamsStore()
const flow = useExamFlowStore()
const notify = useNotify()

const canManage = computed(() => canManageExams(auth.role))

const rows = ref([])
const original = ref('')
const editable = ref(false)
const canHead = ref(false)
const loading = ref(false)
const saving = ref(false)
const busy = ref(false)
const overwrite = ref(false)

const bands = ref([])
const statusAll = ref(null)
const stage = computed(() => statusAll.value?.classes.find((c) => String(c.id) === String(props.classId)) ?? null)

const snapshot = () => JSON.stringify(rows.value.map((r) => [r.teacher_comment ?? '', r.head_comment ?? '']))
const dirty = computed(() => snapshot() !== original.value)

// ── Print view: every pupil with both comments ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('exams.marks.student'), value: (r) => r.full_name },
  { label: t('exams.marks.admission'), value: (r) => r.admission_number },
  { label: t('examx.comments.teacher'), value: (r) => r.teacher_comment },
  { label: t('examx.comments.head'), value: (r) => r.head_comment },
])

async function load() {
  rows.value = []
  if (!props.classId) return
  loading.value = true
  try {
    const [c, b, s] = await Promise.all([store.getComments(props.examId, props.classId), store.getCommentBands(), store.getCommentStatus(props.examId)])
    rows.value = c.data.students
    editable.value = c.data.editable
    canHead.value = c.data.can_head_comment
    bands.value = b.data
    statusAll.value = s.data
    original.value = snapshot()
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

async function refresh() {
  const [c, s] = await Promise.all([store.getComments(props.examId, props.classId), store.getCommentStatus(props.examId)])
  rows.value = c.data.students
  statusAll.value = s.data
  original.value = snapshot()
}

async function save() {
  saving.value = true
  try {
    const res = await store.saveComments(props.examId, props.classId, rows.value.map((r) => ({
      student_id: r.student_id, teacher_comment: r.teacher_comment || null, head_comment: r.head_comment || null,
    })))
    notify.success(res.message)
    await refresh()
    emit('saved')
  } catch (e) {
    await notify.fromError(e)
  } finally {
    saving.value = false
  }
}

// Fill from the bands. 'both' = the teacher's and the head's comments, for every class.
async function apply(role, allClasses) {
  if (dirty.value) await save()
  busy.value = true
  try {
    const roles = role === 'both' ? ['teacher', 'head'] : [role]
    let last = null
    for (const r of roles) {
      last = await store.applyComments(props.examId, { role: r, school_class_id: allClasses ? null : props.classId, overwrite: overwrite.value })
    }
    notify.success(last.message)
    await refresh()
    emit('saved')
  } catch (e) {
    await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

async function submitStage(which) {
  if (dirty.value) await save()
  busy.value = true
  try {
    const res = await store.submitComments(props.examId, props.classId, which)
    statusAll.value = res.data
    notify.success(res.message)
    await refresh()
    emit('saved')
    which === 'teacher' ? afterTeacher() : afterHead()
  } catch (e) {
    await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

async function reopenStage() {
  busy.value = true
  try {
    const which = stage.value?.head_signed ? 'head' : 'teacher'
    const res = await store.reopenComments(props.examId, props.classId, which)
    statusAll.value = res.data
    notify.success(res.message)
  } catch (e) {
    await notify.fromError(e)
  } finally {
    busy.value = false
  }
}

// ── "what next?" ────────────────────────────────────────────────────────────
function afterTeacher() {
  if (canHead.value) {
    flow.ask({
      trail: 'exam', current: 'comments', finished: false,
      title: t('examx.flow.teacherDoneTitle'), text: t('examx.flow.teacherDoneHeadText'), yesLabel: t('examx.comments.fillAndApprove'),
    }, async () => {
      await store.applyComments(props.examId, { role: 'head', school_class_id: props.classId, overwrite: false })
      const res = await store.submitComments(props.examId, props.classId, 'head')
      statusAll.value = res.data
      await refresh()
      afterHead()
    })
  } else {
    flow.ask({ trail: 'exam', current: 'comments', finished: false, info: true, title: t('examx.flow.teacherDoneTitle'), text: t('examx.flow.teacherDoneWaitText') })
  }
}

function afterHead() {
  const pending = (statusAll.value?.classes ?? []).filter((c) => !c.teacher_signed || !c.head_signed)
  if (pending.length) {
    const next = pending[0]
    flow.ask({
      trail: 'exam', current: 'comments', finished: false,
      title: t('examx.flow.classApprovedTitle'), text: t('examx.flow.classApprovedText', { next: next.label, left: pending.length }),
    }, () => { flow.close(); emit('pick-class', next.id) })
    return
  }
  if (canPublishExams(auth.role)) {
    flow.ask({
      trail: 'exam', current: 'comments', finished: true,
      title: t('examx.flow.commentsDoneTitle'), text: t('examx.flow.commentsDoneText'),
    }, () => { flow.close(); router.push({ path: '/mitihani', query: { publish: props.examId } }) })
  } else {
    flow.ask({ trail: 'exam', current: 'comments', finished: true, info: true, title: t('examx.flow.commentsDoneTitle'), text: t('examx.flow.commentsDoneWaitText') })
  }
}

// ── editing the bands ───────────────────────────────────────────────────────
const bandsOpen = ref(false)
const draft = ref([])
const bandsError = ref('')

function openBands() {
  draft.value = bands.value.map((b) => ({ from: b.from, teacher: b.teacher, head: b.head }))
  bandsError.value = ''
  bandsOpen.value = true
}

async function saveBands() {
  busy.value = true
  bandsError.value = ''
  try {
    const res = await store.saveCommentBands(draft.value.map((b) => ({ from: Number(b.from), teacher: b.teacher, head: b.head })))
    bands.value = res.data
    bandsOpen.value = false
    notify.success(res.message)
  } catch (e) {
    bandsError.value = e.response?.data?.errors ? Object.values(e.response.data.errors).flat()[0] : (e.response?.data?.message || t('examx.flow.failed'))
  } finally {
    busy.value = false
  }
}

watch(() => [props.examId, props.classId], load)
onMounted(load)
</script>
