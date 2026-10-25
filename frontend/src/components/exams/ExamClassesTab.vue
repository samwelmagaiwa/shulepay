<script setup>
/**
 * Create, rename and delete the school's classes: the first step of exam setup,
 * kept here so nobody has to leave the setup flow to add a class.
 */
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useExamsStore } from '@/stores/exams'
import { useNotify } from '@/composables/useNotify'

const emit = defineEmits(['changed'])

const { t } = useI18n()
const store = useExamsStore()
const notify = useNotify()

const classes = ref([])
const loading = ref(true)
const formOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({ name: '', stream: '', capacity: '' })
const errors = ref({})
const firstError = (field) => errors.value[field]?.[0]

async function load() {
  const res = await api.get('/school-classes')
  classes.value = Array.isArray(res.data) ? res.data : res.data?.data ?? []
}

function openForm(c = null) {
  editing.value = c
  errors.value = {}
  form.value = c
    ? { name: c.name, stream: c.stream ?? '', capacity: c.capacity ?? '' }
    : { name: '', stream: '', capacity: '' }
  formOpen.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const payload = {
    name: form.value.name.trim(),
    stream: form.value.stream.trim() || null,
    capacity: form.value.capacity === '' ? null : Number(form.value.capacity),
    // New classes go to the end of the list.
    ...(editing.value ? {} : { sort_order: classes.value.length + 1 }),
  }
  try {
    editing.value ? await store.updateClass(editing.value.id, payload) : await store.createClass(payload)
    formOpen.value = false
    notify.success(t('common.saved', 'Saved'))
    await load()
    emit('changed')
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) errors.value = e.response.data.errors
    else await notify.fromError(e)
  } finally {
    saving.value = false
  }
}

async function remove(c) {
  const ok = await notify.confirm({
    title: t('exams.classes.deleteTitle'),
    message: t('exams.classes.deleteMessage', { name: c.name }),
    confirmText: t('exams.list.delete'),
    cancelText: t('exams.confirm.cancel'),
    danger: true,
  })
  if (!ok) return
  try {
    await store.deleteClass(c.id)
    await load()
    emit('changed')
  } catch (e) {
    await notify.fromError(e)
  }
}

onMounted(async () => {
  try { await load() } catch (e) { await notify.fromError(e) } finally { loading.value = false }
})
</script>

<template>
  <CCard>
    <CCardBody>
      <div class="d-flex justify-content-end mb-3">
        <CButton color="primary" @click="openForm()">＋ {{ t('exams.classes.add') }}</CButton>
      </div>
      <div v-if="loading" class="text-center py-4"><CSpinner color="primary" /></div>
      <div v-else-if="!classes.length" class="text-center text-body-secondary py-4">{{ t('exams.classes.none') }}</div>
      <CTable v-else responsive hover align="middle" class="mb-0">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>{{ t('exams.classes.name') }}</CTableHeaderCell>
            <CTableHeaderCell>{{ t('exams.classes.stream') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-center">{{ t('exams.classes.capacity') }}</CTableHeaderCell>
            <CTableHeaderCell></CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-for="c in classes" :key="c.id">
            <CTableDataCell class="fw-semibold">{{ c.name }}</CTableDataCell>
            <CTableDataCell>{{ c.stream || '—' }}</CTableDataCell>
            <CTableDataCell class="text-center">{{ c.capacity ?? '—' }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton size="sm" color="secondary" variant="ghost" @click="openForm(c)">✏️</CButton>
              <CButton size="sm" color="danger" variant="ghost" @click="remove(c)">🗑️</CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
    </CCardBody>
  </CCard>

  <CModal :visible="formOpen" alignment="center" backdrop="static" @close="formOpen = false">
    <CModalHeader><CModalTitle>{{ editing ? t('exams.classes.edit') : t('exams.classes.add') }}</CModalTitle></CModalHeader>
    <CModalBody>
      <div class="mb-3">
        <label class="form-label fw-semibold">{{ t('exams.classes.name') }} <span class="text-danger">*</span></label>
        <CFormInput v-model="form.name" :placeholder="t('exams.classes.namePlaceholder')" :invalid="!!firstError('name')" />
        <div v-if="firstError('name')" class="invalid-feedback d-block">{{ firstError('name') }}</div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">{{ t('exams.classes.stream') }}</label>
        <CFormInput v-model="form.stream" :placeholder="t('exams.classes.streamPlaceholder')" maxlength="50" />
        <div class="small text-body-secondary">{{ t('exams.classes.streamHint') }}</div>
      </div>
      <div>
        <label class="form-label fw-semibold">{{ t('exams.classes.capacity') }}</label>
        <CFormInput v-model="form.capacity" type="number" min="1" :invalid="!!firstError('capacity')" />
        <div v-if="firstError('capacity')" class="invalid-feedback d-block">{{ firstError('capacity') }}</div>
      </div>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" variant="outline" @click="formOpen = false">{{ t('exams.confirm.cancel') }}</CButton>
      <CButton color="primary" :disabled="saving" @click="save">
        <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('exams.classes.save') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
