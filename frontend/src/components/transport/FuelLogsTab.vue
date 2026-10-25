<script setup>
/** Fuel logs of one vehicle, with the consumption worked out from full-tank fills. */
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useNotify } from '@/composables/useNotify'

const props = defineProps({
  /** [{ id, plate_number, make, model }] */
  vehicles: { type: Array, required: true },
})

const { t } = useI18n()
const notify = useNotify()

const vehicleId = ref('')
const range = ref({ from: '', to: '' })
const logs = ref([])
const summary = ref(null)
const loading = ref(false)

const formOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({})
const errors = ref({})
const firstError = (field) => errors.value[field]?.[0]

const money = (cents) => 'TZS ' + ((cents || 0) / 100).toLocaleString()
const num = (n, digits = 2) => (n === null || n === undefined ? '—' : Number(Number(n).toFixed(digits)).toLocaleString())
const selected = computed(() => props.vehicles.find((v) => String(v.id) === String(vehicleId.value)))
const today = () => new Date().toISOString().slice(0, 10)

async function load() {
  logs.value = []
  summary.value = null
  if (!vehicleId.value) return
  loading.value = true
  try {
    const params = {}
    if (range.value.from) params.from = range.value.from
    if (range.value.to) params.to = range.value.to
    const res = (await api.get(`/transport/vehicles/${vehicleId.value}/fuel`, { params })).data
    logs.value = res.data
    summary.value = res.summary
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}

// Watched, not read in @change: CFormSelect emits `change` before it updates the v-model.
watch(vehicleId, load)
watch(() => [range.value.from, range.value.to], load)

function openForm(log = null) {
  editing.value = log
  errors.value = {}
  form.value = log
    ? { fuelled_on: log.fuelled_on, litres: log.litres, cost: log.cost_cents / 100, odometer_km: log.odometer_km,
        full_tank: log.full_tank, station: log.station ?? '', driver_name: log.driver_name ?? '', notes: log.notes ?? '' }
    : { fuelled_on: today(), litres: '', cost: '', odometer_km: logs.value[0]?.odometer_km ?? '',
        full_tank: true, station: '', driver_name: selected.value?.driver_name ?? '', notes: '' }
  formOpen.value = true
}

async function save() {
  saving.value = true
  errors.value = {}
  const { cost, ...rest } = form.value
  const payload = {
    ...rest,
    cost_cents: cost === '' ? null : Math.round(Number(cost) * 100),
    station: rest.station || null,
    driver_name: rest.driver_name || null,
    notes: rest.notes || null,
  }
  try {
    const res = editing.value
      ? await api.put(`/transport/fuel/${editing.value.id}`, payload)
      : await api.post(`/transport/vehicles/${vehicleId.value}/fuel`, payload)
    formOpen.value = false
    notify.success(res.data.message)
    await load()
  } catch (e) {
    if (e.response?.status === 422 && e.response.data?.errors) {
      errors.value = e.response.data.errors // shown next to the fields
    } else if (e.response?.status === 422 && e.response.data?.code?.startsWith('ODOMETER')) {
      errors.value = { odometer_km: [e.response.data.message] }
    } else {
      await notify.fromError(e)
    }
  } finally {
    saving.value = false
  }
}

async function remove(log) {
  const ok = await notify.confirm({
    title: t('transport.fuel.deleteTitle'),
    message: t('transport.fuel.deleteMessage', { date: log.fuelled_on, litres: log.litres }),
    confirmText: t('transport.fuel.delete'),
    cancelText: t('transport.cancel'),
    danger: true,
  })
  if (!ok) return
  try {
    notify.success((await api.delete(`/transport/fuel/${log.id}`)).data.message)
    await load()
  } catch (e) {
    await notify.fromError(e)
  }
}
</script>

<template>
  <div>
    <CRow class="g-2 align-items-end mb-3">
      <CCol md="5">
        <label class="form-label fw-semibold">{{ t('transport.docs.vehicle') }}</label>
        <CFormSelect v-model="vehicleId">
          <option value="">{{ t('transport.docs.selectVehicle') }}</option>
          <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }} {{ v.model }}</option>
        </CFormSelect>
      </CCol>
      <CCol sm="6" md="2">
        <label class="form-label fw-semibold">{{ t('transport.fuel.from') }}</label>
        <CFormInput v-model="range.from" type="date" :disabled="!vehicleId" />
      </CCol>
      <CCol sm="6" md="2">
        <label class="form-label fw-semibold">{{ t('transport.fuel.to') }}</label>
        <CFormInput v-model="range.to" type="date" :disabled="!vehicleId" />
      </CCol>
      <CCol md="3" class="text-md-end">
        <CButton color="success" :disabled="!vehicleId" @click="openForm()">＋ {{ t('transport.fuel.add') }}</CButton>
      </CCol>
    </CRow>

    <div v-if="!vehicleId" class="text-center text-body-secondary py-4">{{ t('transport.docs.pickVehicle') }}</div>
    <div v-else-if="loading" class="text-center py-4"><CSpinner /></div>

    <template v-else>
      <CRow v-if="summary" class="g-2 mb-3">
        <CCol v-for="tile in [
          { k: 'fills', v: summary.fills },
          { k: 'litres', v: num(summary.litres) + ' L' },
          { k: 'cost', v: money(summary.cost_cents) },
          { k: 'avg', v: summary.avg_km_per_litre === null ? '—' : num(summary.avg_km_per_litre) + ' km/L' },
          { k: 'costPerKm', v: summary.cost_per_km_cents === null ? '—' : money(summary.cost_per_km_cents) },
        ]" :key="tile.k" xs="6" md="4" xl>
          <CCard class="h-100 text-center"><CCardBody class="p-2">
            <div class="fw-bold">{{ tile.v }}</div>
            <div class="small text-body-secondary">{{ t(`transport.fuel.tiles.${tile.k}`) }}</div>
          </CCardBody></CCard>
        </CCol>
      </CRow>

      <div v-if="!logs.length" class="text-center text-body-secondary py-4">{{ t('transport.fuel.none', { plate: selected?.plate_number }) }}</div>

      <CTable v-else responsive hover align="middle">
        <CTableHead class="table-light">
          <CTableRow>
            <CTableHeaderCell>{{ t('transport.fuel.date') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('transport.fuel.litres') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end d-none d-sm-table-cell">{{ t('transport.fuel.cost') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end d-none d-md-table-cell">{{ t('transport.fuel.odometer') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('transport.fuel.kmPerL') }}</CTableHeaderCell>
            <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('transport.fuel.station') }}</CTableHeaderCell>
            <CTableHeaderCell></CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-for="l in logs" :key="l.id">
            <CTableDataCell>
              {{ l.fuelled_on }}
              <CBadge v-if="!l.full_tank" color="secondary" class="ms-1">{{ t('transport.fuel.partial') }}</CBadge>
            </CTableDataCell>
            <CTableDataCell class="text-end">{{ num(l.litres) }}</CTableDataCell>
            <CTableDataCell class="text-end d-none d-sm-table-cell">{{ money(l.cost_cents) }}</CTableDataCell>
            <CTableDataCell class="text-end d-none d-md-table-cell">{{ num(l.odometer_km, 0) }}</CTableDataCell>
            <CTableDataCell class="text-end fw-semibold">{{ num(l.km_per_litre) }}</CTableDataCell>
            <CTableDataCell class="d-none d-lg-table-cell">{{ l.station || '—' }}</CTableDataCell>
            <CTableDataCell class="text-end text-nowrap">
              <CButton size="sm" color="secondary" variant="ghost" @click="openForm(l)">✏️</CButton>
              <CButton size="sm" color="danger" variant="ghost" @click="remove(l)">🗑️</CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
    </template>

    <CModal :visible="formOpen" alignment="center" backdrop="static" @close="formOpen = false">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('transport.fuel.edit') : t('transport.fuel.add') }} — {{ selected?.plate_number }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.date') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="form.fuelled_on" type="date" :max="today()" :invalid="!!firstError('fuelled_on')" />
            <div v-if="firstError('fuelled_on')" class="invalid-feedback d-block">{{ firstError('fuelled_on') }}</div>
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.odometer') }} (km) <span class="text-danger">*</span></label>
            <CFormInput v-model="form.odometer_km" type="number" min="0" :invalid="!!firstError('odometer_km')" />
            <div v-if="firstError('odometer_km')" class="invalid-feedback d-block">{{ firstError('odometer_km') }}</div>
          </CCol>
        </CRow>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.litres') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="form.litres" type="number" min="0" step="0.01" :invalid="!!firstError('litres')" />
            <div v-if="firstError('litres')" class="invalid-feedback d-block">{{ firstError('litres') }}</div>
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.totalCost') }} (TZS) <span class="text-danger">*</span></label>
            <CFormInput v-model="form.cost" type="number" min="0" :invalid="!!firstError('cost_cents')" />
            <div v-if="firstError('cost_cents')" class="invalid-feedback d-block">{{ firstError('cost_cents') }}</div>
          </CCol>
        </CRow>
        <div class="mb-3">
          <CFormSwitch v-model="form.full_tank" :label="t('transport.fuel.fullTank')" />
          <div class="small text-body-secondary">{{ t('transport.fuel.fullTankHint') }}</div>
        </div>
        <CRow class="g-3 mb-3">
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.station') }}</label>
            <CFormInput v-model="form.station" maxlength="150" />
          </CCol>
          <CCol sm="6">
            <label class="form-label fw-semibold">{{ t('transport.fuel.driver') }}</label>
            <CFormInput v-model="form.driver_name" maxlength="150" />
          </CCol>
        </CRow>
        <div>
          <label class="form-label fw-semibold">{{ t('transport.docs.notes') }}</label>
          <CFormTextarea v-model="form.notes" rows="2" maxlength="1000" />
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="formOpen = false">{{ t('transport.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('transport.fuel.save') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </div>
</template>
