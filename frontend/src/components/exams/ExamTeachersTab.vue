<script setup>
/**
 * Who teaches which subject in which class. A classroom teacher can only enter
 * marks for the subjects ticked here; coordinators, heads and the owner are not
 * limited by this list.
 */
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'
import { useExamFlow } from '@/stores/examFlow'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['saved'])

const props = defineProps({
  /** [{ id, label, subject_ids }] from GET /class-subjects */
  classes: { type: Array, required: true },
  /** [{ id, name, is_active }] */
  subjects: { type: Array, required: true },
})

const { t } = useI18n()
const store = useExamsStore()
const notify = useNotify()

const teachers = ref([])
const loading = ref(true)
const openId = ref(null)
const selection = ref({}) // teacherId -> ["classId-subjectId", ...]
const saved = ref({})
const savingId = ref(null)

const key = (classId, subjectId) => `${classId}-${subjectId}`
const toKeys = (assignments) => assignments.map((a) => key(a.school_class_id, a.subject_id))
const subjectName = computed(() => Object.fromEntries(props.subjects.map((s) => [s.id, s.name])))
const classSubjectsOf = (c) => c.subject_ids.filter((id) => subjectName.value[id])

const sameSet = (a, b) => a.length === b.length && a.every((k) => b.includes(k))
const dirty = (id) => !sameSet(selection.value[id] ?? [], saved.value[id] ?? [])

async function load() {
  teachers.value = (await store.listTeachers()).data
  saved.value = Object.fromEntries(teachers.value.map((x) => [x.id, toKeys(x.assignments)]))
  selection.value = Object.fromEntries(teachers.value.map((x) => [x.id, toKeys(x.assignments)]))
}

function toggle(teacherId, classId, subjectId) {
  const set = new Set(selection.value[teacherId] ?? [])
  const k = key(classId, subjectId)
  set.has(k) ? set.delete(k) : set.add(k)
  selection.value[teacherId] = [...set]
}

async function save(teacher) {
  savingId.value = teacher.id
  try {
    const assignments = (selection.value[teacher.id] ?? []).map((k) => {
      const [school_class_id, subject_id] = k.split('-').map(Number)
      return { school_class_id, subject_id }
    })
    const res = await store.saveTeacherAssignments(teacher.id, assignments)
    saved.value[teacher.id] = [...selection.value[teacher.id]]
    notify.success(res.message)
    emit('saved')
  } catch (e) {
    await notify.fromError(e)
  } finally {
    savingId.value = null
  }
}

const { guide } = useExamFlow()
const auth = useAuthStore()

onMounted(async () => {
  try { await load() } catch (e) { await notify.fromError(e) } finally { loading.value = false }
  // No classroom teacher accounts yet: only the owner can create them.
  if (!teachers.value.length) {
    guide({
      title: t('examx.missing.noTeachersTitle'), text: t('examx.missing.noTeachersText'),
      to: ['owner', 'superadmin'].includes(auth.role) ? '/admin/wafanyakazi' : null,
    })
  }
})
</script>

<template>
  <div>
    <div class="text-body-secondary small mb-3">{{ t('exams.teachers.hint') }}</div>
    <div v-if="loading" class="text-center py-4"><CSpinner color="primary" /></div>
    <div v-else-if="!teachers.length" class="text-center text-body-secondary py-4">{{ t('exams.teachers.none') }}</div>

    <CCard v-for="teacher in loading ? [] : teachers" :key="teacher.id" class="mb-2">
      <CCardHeader class="d-flex flex-wrap justify-content-between align-items-center gap-2" style="cursor:pointer;"
                   @click="openId = openId === teacher.id ? null : teacher.id">
        <span class="fw-semibold">{{ teacher.name }}</span>
        <span>
          <CBadge v-if="dirty(teacher.id)" color="warning" class="me-1">{{ t('exams.marks.unsaved') }}</CBadge>
          <CBadge color="secondary">{{ t('exams.teachers.assigned', { count: (selection[teacher.id] ?? []).length }) }}</CBadge>
        </span>
      </CCardHeader>

      <CCardBody v-if="openId === teacher.id">
        <CRow class="g-3">
          <CCol v-for="c in classes" :key="c.id" md="6" xl="4">
            <div class="fw-semibold mb-1">{{ c.label }}</div>
            <div v-if="!classSubjectsOf(c).length" class="small text-body-secondary">{{ t('exams.teachers.noSubjects') }}</div>
            <div v-for="sid in classSubjectsOf(c)" :key="sid" class="form-check">
              <input :id="`ta-${teacher.id}-${c.id}-${sid}`" class="form-check-input" type="checkbox"
                     :checked="(selection[teacher.id] ?? []).includes(key(c.id, sid))" @change="toggle(teacher.id, c.id, sid)" />
              <label class="form-check-label" :for="`ta-${teacher.id}-${c.id}-${sid}`">{{ subjectName[sid] }}</label>
            </div>
          </CCol>
        </CRow>
        <div class="text-end mt-3">
          <CButton size="sm" color="primary" :disabled="!dirty(teacher.id) || savingId === teacher.id" @click="save(teacher)">
            <CSpinner v-if="savingId === teacher.id" size="sm" class="me-1" />{{ t('exams.teachers.save') }}
          </CButton>
        </div>
      </CCardBody>
    </CCard>
  </div>
</template>
