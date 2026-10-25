<script setup>
/** Compliance documents of one vehicle at a time: insurance, road licence, inspection... */
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useNotify } from '@/composables/useNotify'

const props = defineProps({
  /** [{ id, plate_number, make, model }] */
  vehicles: { type: Array, required: true },
})
const emit = defineEmits(['changed'])

const { t } = useI18n()
const notify = useNotify()

const TYPES = ['insurance', 'road_licence', 'inspection', 'permit', 'driver_licence', 'other']
const STATUS_COLOR = { valid: 'success', expiring: 'warning', expired: 'danger' }

const vehicleId = ref('')
const documents = ref([])
const loading = ref(false)

const formOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({})
const errors = ref({})
const firstError = (field) => errors.value[field]?.[0]

const money = (cents) => 'TZS ' + ((cents || 0) / 100).toLocaleString()
const typeLabel = (type) => t(`transport.docs.types.${type}`)
const daysText = (d) => (d.days_left < 0
  ? t('transport.docs.expiredAgo', { days: Math.abs(d.days_left) })
  : d.days_left === 0 ? t('transport.docs.today') : t('transport.docs.inDays', { days: d.days_left }))

const selected = computed(() => props.vehicles.find((v) => String(v.id) === String(vehicleId.value)))

async function load() {
  documents.value = []
  if (!vehicleId.value) return
  loading.value = true
  try {
    documents.value = (await api.get(`/transport/vehicles/${vehicleId.value}/documents`)).data.data
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

// Watched, not read in @change: CFormSelect emits `change` before it updates the v-model.
watch(vehicleId, load)

function openForm(doc = null) {
  editing.value = doc
  errors.value = {}
  form.value = doc
    ? { type: doc.type, reference_number: doc.reference_number ?? '', provider: doc.provider ?? '', issue_date: doc.issue_date ?? '',
        expiry_date: doc.expiry_date, cost: doc.cost_cents ? doc.cost_cents / 100 : '', notes: doc.notes ?? '' }
    : { type: 'insurance', reference_number: '', provider: '', issue_date: '', expiry_date: '', cost: '', notes: '' }
  formOpen.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const { cost, ...rest } = form.value
  const payload = {
    ...rest,
    issue_date: rest.issue_date || null,
    reference_number: rest.reference_number || null,
    provider: rest.provider || null,
    notes: rest.notes || null,
    cost_cents: cost === '' ? 0 : Math.round(Number(cost) * 100),
  }
  try {
    const res = editing.value
      ? await api.put(`/transport/documents/${editing.value.id}`, payload)
      : await api.post(`/transport/vehicles/${vehicleId.value}/documents`, payload)
    formOpen.value = false
    notify.success(res.data.message)
    await load()
    emit('changed')
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) errors.value = e.response.data.errors
    else await notify.fromError(e)
  } finally {
    saving.value = false
  }
}

async function remove(doc) {
  const ok = await notify.confirm({
    title: t('transport.docs.deleteTitle'),
    message: t('transport.docs.deleteMessage', { type: typeLabel(doc.type), plate: doc.plate_number }),
    confirmText: t('transport.docs.delete'),
    cancelText: t('transport.cancel'),
    danger: true,
  })
  if (!ok) return
  try {
    notify.success((await api.delete(`/transport/documents/${doc.id}`)).data.message)
    await load()
    emit('changed')
  } catch (e) {
    await notify.fromError(e)
  }
}

defineExpose({ select: (id) => { vehicleId.value = String(id) } })
</script>

<template>
  <div>
    <CRow class="g-2 align-items-end mb-3">
      <CCol md="6">
        <label class="form-label fw-semibold">{{ t('transport.docs.vehicle') }}</label>
        <CFormSelect v-model="vehicleId">
          <option value="">{{ t('transport.docs.selectVehicle') }}</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
        </CFormSelect>
      </CCol>
      <CCol md="6" class="text-md-end">
        <CButton color="success" :disabled="!vehicleId" @click="openForm()">＋ {{ t('transport.docs.add') }}</CButton>
      </CCol>
    </CRow>

    <div v-if="!vehicleId" class="text-center text-body-secondary py-4">{{ t('transport.docs.pickVehicle') }}</div>
    <div v-else-if="loading" class="text-center py-4"><CSpinner /></div>
    <div v-else-if="!documents.length" class="text-center text-body-secondary py-4">{{ t('transport.docs.none', { plate: selected?.plate_number }) }}</div>

    <CTable v-else responsive hover align="middle">
      <CTableHead class="table-light">
        <CTableRow>
          <CTableHeaderCell>{{ t('transport.docs.type') }}</CTableHeaderCell>
          <CTableHeaderCell class="d-none d-md-table-cell">{{ t('transport.docs.provider') }}</CTableHeaderCell>
          <CTableHeaderCell>{{ t('transport.docs.expiry') }}</CTableHeaderCell>
          <CTableHeaderCell>{{ t('transport.docs.status') }}</CTableHeaderCell>
          <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('transport.docs.cost') }}</CTableHeaderCell>
          <CTableHeaderCell></CTableHeaderCell>
        </CTableRow>
      </CTableHead>
      <CTableBody>
        <CTableRow v-for="d in documents" :key="d.id">
          <CTableDataCell>
            <div class="fw-semibold">{{ typeLabel(d.type) }}</div>
            <div class="small text-body-secondary">{{ d.reference_number || '—' }}</div>
          </CTableDataCell>
          <CTableDataCell class="d-none d-md-table-cell">{{ d.provider || '—' }}</CTableDataCell>
          <CTableDataCell>
            {{ d.expiry_date }}
            <div class="small text-body-secondary">{{ daysText(d) }}</div>
          </CTableDataCell>
          <CTableDataCell><CBadge :color="STATUS_COLOR[d.status]">{{ t(`transport.docs.statuses.${d.status}`) }}</CBadge></CTableDataCell>
          <CTableDataCell class="d-none d-lg-table-cell">{{ d.cost_cents ? money(d.cost_cents) : '—' }}</CTableDataCell>
          <CTableDataCell class="text-end text-nowrap">
            <CButton size="sm" color="secondary" variant="ghost" @click="openForm(d)">✏️</CButton>
            <CButton size="sm" color="danger" variant="ghost" @click="remove(d)">🗑️</CButton>
          </CTableDataCell>
        </CTableRow>
      </CTableBody>
    </CTable>

    <CModal :visible="formOpen" alignment="center" backdrop="static" @close="formOpen = false">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('transport.docs.edit') : t('transport.docs.add') }} — {{ selected?.plate_number }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('transport.docs.type') }} <span class="text-danger">*</span></label>
          <CFormSelect v-model="form.type" :invalid="!!firstError('type')">
            <option v-for="type in TYPES" :key="type" :value="type">{{ typeLabel(type) }}</option>
          </CFormSelect>
          <div v-if="firstError('type')" class="invalid-feedback d-block">{{ firstError('type') }}</div>
        </div>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.docs.provider') }}</label>
            <CFormInput v-model="form.provider" maxlength="150" :invalid="!!firstError('provider')" />
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.docs.reference') }}</label>
            <CFormInput v-model="form.reference_number" maxlength="100" :invalid="!!firstError('reference_number')" />
          </CCol>
        </CRow>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.docs.issued') }}</label>
            <CFormInput v-model="form.issue_date" type="date" :invalid="!!firstError('issue_date')" />
            <div v-if="firstError('issue_date')" class="invalid-feedback d-block">{{ firstError('issue_date') }}</div>
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.docs.expiry') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="form.expiry_date" type="date" :invalid="!!firstError('expiry_date')" />
            <div v-if="firstError('expiry_date')" class="invalid-feedback d-block">{{ firstError('expiry_date') }}</div>
          </CCol>
        </CRow>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('transport.docs.cost') }} (TZS)</label>
          <CFormInput v-model="form.cost" type="number" min="0" :invalid="!!firstError('cost_cents')" />
          <div v-if="firstError('cost_cents')" class="invalid-feedback d-block">{{ firstError('cost_cents') }}</div>
        </div>
        <div>
          <label class="form-label fw-semibold">{{ t('transport.docs.notes') }}</label>
          <CFormTextarea v-model="form.notes" rows="2" maxlength="1000" />
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="formOpen = false">{{ t('transport.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('transport.docs.save') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </div>
</template>
