<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTransportStore } from '@/stores/transport'
import { useAuthStore }      from '@/stores/auth'
import { useSchoolsStore }   from '@/stores/schools'
import ExpiryAlertsBanner from '@/components/transport/ExpiryAlertsBanner.vue'
import VehicleDocumentsTab from '@/components/transport/VehicleDocumentsTab.vue'
import FuelLogsTab from '@/components/transport/FuelLogsTab.vue'
import XStats from '@/components/XStats.vue'
import XGrid from '@/components/XGrid.vue'

const { t }       = useI18n()
const store       = useTransportStore()
const auth        = useAuthStore()
const schoolsStore = useSchoolsStore()
const activeTab   = ref(0)
const alertsKey   = ref(0)   // bumped when a document changes, so the banner reloads

const isSuperAdmin = computed(() => auth.isSuperAdmin)
const schools      = computed(() => schoolsStore.schools)

// ─── Confirm dialog ──────────────────────────────────────────────────────────
const confirm = ref({ visible: false, message: '', onOk: null })
function askConfirm(message, onOk) {
  confirm.value = { visible: true, message, onOk }
}
function confirmOk() {
  confirm.value.onOk?.()
  confirm.value.visible = false
}


// ─── List design (same as the Expenses page) ────────────────────────────────
const selVehicleId = ref(null)
const selRouteId = ref(null)
const selSubId = ref(null)
const selMaintId = ref(null)
const cards = computed(() => [
  { label: t('transport.totalVehicles'), value: store.summaryStats.total_vehicles ?? 0, tone: 'blue' },
  { label: t('transport.activeVehicles'), value: store.summaryStats.active_vehicles ?? 0, tone: 'green' },
  { label: t('transport.inMaintenance'), value: store.summaryStats.maintenance_vehicles ?? 0, tone: 'amber' },
  { label: t('transport.studentsOnTransport'), value: store.summaryStats.students_on_transport ?? 0, tone: 'gray' },
])
const vehicleColumns = computed(() => [
  { key: 'plate_number', label: t('transport.plateNumber'), w: 'minmax(110px, 1fr)' },
  { key: 'model', label: t('transport.makeModelYear'), w: 'minmax(170px, 2fr)' },
  { key: 'capacity', label: t('transport.capacity'), w: 'minmax(110px, 1fr)' },
  { key: 'driver_name', label: t('transport.driverName'), w: 'minmax(130px, 1.2fr)' },
  { key: 'status', label: t('transport.status'), w: 'minmax(100px, .9fr)' },
  { key: 'subscribed_students', label: t('transport.studentsOnTransport'), w: 'minmax(100px, .9fr)', align: 'end' },
])
const routeColumns = computed(() => [
  { key: 'name', label: t('transport.routeName'), w: 'minmax(150px, 1.6fr)' },
  { key: 'start_point', label: t('transport.from'), w: 'minmax(110px, 1fr)' },
  { key: 'end_point', label: t('transport.to'), w: 'minmax(110px, 1fr)' },
  { key: 'distance_km', label: t('transport.distance'), w: 'minmax(80px, .7fr)', align: 'end' },
  { key: 'monthly_fare_cents', label: t('transport.farePerMonth'), w: 'minmax(120px, 1fr)', align: 'end' },
  { key: 'is_active', label: t('transport.status'), w: 'minmax(90px, .8fr)' },
])
const subColumns = computed(() => [
  { key: 'student', label: t('transport.student'), w: 'minmax(160px, 2fr)' },
  { key: 'vehicle', label: t('transport.vehicle'), w: 'minmax(100px, 1fr)' },
  { key: 'route', label: t('transport.route'), w: 'minmax(130px, 1.2fr)' },
  { key: 'direction', label: t('transport.direction'), w: 'minmax(100px, 1fr)' },
  { key: 'start_date', label: t('transport.startDate'), w: 'minmax(100px, 1fr)' },
])
const maintColumns = computed(() => [
  { key: 'service_date', label: t('transport.serviceDate'), w: 'minmax(100px, 1fr)' },
  { key: 'type', label: t('transport.typeLabel'), w: 'minmax(100px, 1fr)' },
  { key: 'description', label: t('transport.descriptionLabel'), w: 'minmax(200px, 2.5fr)' },
  { key: 'cost_cents', label: t('transport.cost'), w: 'minmax(110px, 1fr)', align: 'end' },
  { key: 'next_service_date', label: t('transport.nextService'), w: 'minmax(110px, 1fr)' },
])
const vehicleMenu = () => [
  { label: '✏️ ' + t('common.edit'), run: openEditVehicle },
  { label: '🗑️ ' + t('common.delete'), run: deleteVehicle, danger: true },
]
const routeMenu = () => [{ label: '✏️ ' + t('common.edit'), run: openEditRoute }]
const subMenu = () => [{ label: '🗑️ ' + t('transport.remove'), run: removeSub, danger: true }]
const vehiclePill = (st) => ({ active: 'xg-pill--green', maintenance: 'xg-pill--amber', inactive: 'xg-pill--gray' }[st] || 'xg-pill--gray')

// ─── Vehicles ────────────────────────────────────────────────────────────────
const showVehicleModal = ref(false)
const editingVehicle   = ref(null)
const vehicleSaving    = ref(false)
const vehicleError     = ref('')
const vehicleForm      = ref(blankVehicle())

function blankVehicle() {
  return {
    school_id: '', plate_number: '', make: '', model: '', year: new Date().getFullYear(),
    capacity: '', type: 'bus', color: '', status: 'active',
    driver_name: '', driver_phone: '', notes: '',
  }
}

function openAddVehicle() {
  editingVehicle.value = null
  vehicleForm.value    = blankVehicle()
  vehicleError.value   = ''
  showVehicleModal.value = true
}

function openEditVehicle(v) {
  editingVehicle.value   = v
  vehicleForm.value      = { ...v }
  vehicleError.value     = ''
  showVehicleModal.value = true
}

async function saveVehicle() {
  vehicleSaving.value = true
  vehicleError.value  = ''
  try {
    if (editingVehicle.value) {
      await store.updateVehicle(editingVehicle.value.id, vehicleForm.value)
    } else {
      await store.createVehicle(vehicleForm.value)
    }
    showVehicleModal.value = false
    await store.fetchSummary()
  } catch (e) {
    vehicleError.value = e?.response?.data?.message || t('transport.saveVehicleError')
  } finally {
    vehicleSaving.value = false
  }
}

function deleteVehicle(v) {
  askConfirm(t('transport.confirmDeleteVehicle', { plate: v.plate_number }), async () => {
    await store.deleteVehicle(v.id)
    await store.fetchSummary()
  })
}

// ─── Routes ──────────────────────────────────────────────────────────────────
const showRouteModal = ref(false)
const editingRoute   = ref(null)
const routeSaving    = ref(false)
const routeError     = ref('')
const routeForm      = ref(blankRoute())

function blankRoute() {
  return {
    school_id: '', name: '', description: '', start_point: '', end_point: '',
    distance_km: '', estimated_minutes: '', monthly_fare_cents: 0, is_active: true,
  }
}

function openAddRoute() {
  editingRoute.value   = null
  routeForm.value      = blankRoute()
  routeError.value     = ''
  showRouteModal.value = true
}

function openEditRoute(r) {
  editingRoute.value   = r
  routeForm.value      = { ...r }
  routeError.value     = ''
  showRouteModal.value = true
}

async function saveRoute() {
  routeSaving.value = true
  routeError.value  = ''
  try {
    if (editingRoute.value) {
      await store.updateRoute(editingRoute.value.id, routeForm.value)
    } else {
      await store.createRoute(routeForm.value)
    }
    showRouteModal.value = false
  } catch (e) {
    routeError.value = e?.response?.data?.message || t('transport.saveRouteError')
  } finally {
    routeSaving.value = false
  }
}

// ─── Subscriptions ───────────────────────────────────────────────────────────
const showSubModal = ref(false)
const subSaving    = ref(false)
const subError     = ref('')
const subForm      = ref(blankSub())

function blankSub() {
  return {
    student_id: '', vehicle_id: '', route_id: '',
    direction: 'both', start_date: new Date().toISOString().slice(0, 10), notes: '',
  }
}

function openAddSub() {
  subForm.value      = blankSub()
  subError.value     = ''
  showSubModal.value = true
}

async function saveSub() {
  subSaving.value = true
  subError.value  = ''
  try {
    await store.subscribe(subForm.value)
    showSubModal.value = false
  } catch (e) {
    subError.value = e?.response?.data?.message || t('transport.saveSubError')
  } finally {
    subSaving.value = false
  }
}

function removeSub(sub) {
  const name = sub.student ? `${sub.student.first_name} ${sub.student.last_name}` : '?'
  askConfirm(t('transport.confirmRemoveSub', { name }), async () => {
    await store.unsubscribe(sub.id)
  })
}

// ─── Maintenance ─────────────────────────────────────────────────────────────
const selectedVehicleId = ref('')
const showMaintModal    = ref(false)
const maintSaving       = ref(false)
const maintError        = ref('')
const maintForm         = ref(blankMaint())

function blankMaint() {
  return {
    type: 'service', description: '', cost_cents: 0,
    service_date: new Date().toISOString().slice(0, 10),
    next_service_date: '', odometer_km: '', notes: '',
  }
}

async function loadMaintenance() {
  if (!selectedVehicleId.value) return
  await store.fetchMaintenance(selectedVehicleId.value)
}

async function saveMaintenance() {
  maintSaving.value = true
  maintError.value  = ''
  try {
    await store.addMaintenance(selectedVehicleId.value, maintForm.value)
    showMaintModal.value = false
    maintForm.value      = blankMaint()
  } catch (e) {
    maintError.value = e?.response?.data?.message || t('transport.saveMaintError')
  } finally {
    maintSaving.value = false
  }
}

// ─── School filter ───────────────────────────────────────────────────────────
const selectedSchoolId = ref('')

async function loadAll() {
  const params = selectedSchoolId.value ? { school_id: selectedSchoolId.value } : {}
  await Promise.all([
    store.fetchVehicles(params),
    store.fetchRoutes(params),
    store.fetchSubscriptions(params),
    store.fetchSummary(params),
  ])
}

async function onSchoolFilter() {
  selectedVehicleId.value = ''   // reset maintenance selector
  await loadAll()
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function statusBadge(status) {
  return { active: 'success', maintenance: 'warning', retired: 'danger' }[status] || 'secondary'
}
function formatMoney(cents) {
  return 'TZS ' + ((cents || 0) / 100).toLocaleString()
}

onMounted(async () => {
  if (isSuperAdmin.value || auth.isOwner) await schoolsStore.fetchSchools()
  await loadAll()
})
</script>

<template>
  <CContainer fluid class="xg-page">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h4 class="mb-0 fw-bold">🚌 {{ t('transport.title') }}</h4>
      <div v-if="schools.length" class="d-flex align-items-center gap-2">
        <span class="text-muted small fw-semibold text-nowrap">{{ t('common.school') }}:</span>
        <CFormSelect v-model="selectedSchoolId" @update:modelValue="onSchoolFilter" style="min-width:200px;">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
      </div>
    </div>

    <XStats :cards="cards" />

    <ExpiryAlertsBanner :refresh-key="alertsKey" @open-documents="activeTab = 4" />

    <!-- Tabs -->
    <CCard class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <CTabs :active-item-key="activeTab" @update:active-item-key="activeTab = $event">
          <CTabList variant="underline-border" class="px-3">
            <CTab :item-key="0">🚌 {{ t('transport.tabVehicles') }}</CTab>
            <CTab :item-key="1">🗺️ {{ t('transport.tabRoutes') }}</CTab>
            <CTab :item-key="2">👦 {{ t('transport.tabStudents') }}</CTab>
            <CTab :item-key="3">🔧 {{ t('transport.tabMaintenance') }}</CTab>
            <CTab :item-key="4">📄 {{ t('transport.tabDocuments') }}</CTab>
            <CTab :item-key="5">⛽ {{ t('transport.tabFuel') }}</CTab>
          </CTabList>

          <CTabContent class="p-3">

            <!-- Tab 1: Vehicles -->
            <CTabPanel :item-key="0">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddVehicle">+ {{ t('transport.addVehicle') }}</CButton>
              </div>
              <XGrid v-model:selected-id="selVehicleId" :columns="vehicleColumns" :rows="store.vehicles" :loading="store.loading" :empty="t('transport.noVehicles')"
                     :min-width="860" :menu="vehicleMenu" @open="openEditVehicle">
                <template #cell-plate_number="{ row }"><span class="xg-name">{{ row.plate_number }}</span></template>
                <template #cell-model="{ row }">{{ row.make }} {{ row.model }} ({{ row.year }})</template>
                <template #cell-capacity="{ row }">{{ row.capacity }} {{ t('transport.passengers') }}</template>
                <template #cell-status="{ row }"><span class="xg-pill" :class="vehiclePill(row.status)">{{ t(`transport.status_${row.status}`) }}</span></template>
                <template #cell-subscribed_students="{ row }">{{ row.subscribed_students || 0 }}</template>
              </XGrid>
            </CTabPanel>

            <!-- Tab 2: Routes -->
            <CTabPanel :item-key="1">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddRoute">+ {{ t('transport.addRoute') }}</CButton>
              </div>
              <XGrid v-model:selected-id="selRouteId" :columns="routeColumns" :rows="store.routes" :empty="t('transport.noRoutes')"
                     :min-width="860" :menu="routeMenu" @open="openEditRoute">
                <template #cell-name="{ row }"><span class="xg-name">{{ row.name }}</span></template>
                <template #cell-distance_km="{ row }">{{ row.distance_km ? row.distance_km + ' km' : '—' }}</template>
                <template #cell-monthly_fare_cents="{ row }"><span class="xg-strong">{{ formatMoney(row.monthly_fare_cents) }}</span></template>
                <template #cell-is_active="{ row }"><span class="xg-pill" :class="row.is_active ? 'xg-pill--green' : 'xg-pill--gray'">{{ row.is_active ? t('transport.statusOpen') : t('transport.statusClosed') }}</span></template>
              </XGrid>
            </CTabPanel>

            <!-- Tab 3: Subscriptions -->
            <CTabPanel :item-key="2">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddSub">+ {{ t('transport.enrollStudent') }}</CButton>
              </div>
              <XGrid v-model:selected-id="selSubId" :columns="subColumns" :rows="store.subscriptions" :empty="t('transport.noStudents')"
                     :min-width="780" :menu="subMenu">
                <template #cell-student="{ row }"><span class="xg-name">{{ row.student ? `${row.student.first_name} ${row.student.last_name}` : '—' }}</span></template>
                <template #cell-vehicle="{ row }">{{ row.vehicle?.plate_number || '—' }}</template>
                <template #cell-route="{ row }">{{ row.route?.name || '—' }}</template>
                <template #cell-direction="{ row }">{{ t(`transport.direction_${row.direction}`) }}</template>
              </XGrid>
            </CTabPanel>

            <!-- Tab 4: Maintenance -->
            <CTabPanel :item-key="3">
              <div class="d-flex align-items-center gap-3 mb-3">
                <CFormSelect v-model="selectedVehicleId" @change="loadMaintenance" style="max-width:300px;">
                  <option value="">{{ t('transport.selectVehicle') }}</option>
                  <option v-for="v in store.vehicles" :key="v.id" :value="v.id">
                    {{ v.plate_number }} — {{ v.make }} {{ v.model }}
                  </option>
                </CFormSelect>
                <CButton v-if="selectedVehicleId" color="success" @click="showMaintModal = true">
                  + {{ t('transport.addRecord') }}
                </CButton>
              </div>
              <XGrid v-if="store.maintenance.length" v-model:selected-id="selMaintId" :columns="maintColumns" :rows="store.maintenance" :min-width="720">
                <template #cell-type="{ row }">{{ t(`transport.maint_${row.type}`) }}</template>
                <template #cell-cost_cents="{ row }"><span class="xg-strong">{{ formatMoney(row.cost_cents) }}</span></template>
              </XGrid>
              <div v-else class="text-center text-muted py-5">
                {{ selectedVehicleId ? t('transport.noRecords') : t('transport.selectVehicleFirst') }}
              </div>
            </CTabPanel>

            <!-- Tab 5: Vehicle documents -->
            <CTabPanel :item-key="4">
              <VehicleDocumentsTab :vehicles="store.vehicles" @changed="alertsKey++" />
            </CTabPanel>

            <!-- Tab 6: Fuel logs -->
            <CTabPanel :item-key="5">
              <FuelLogsTab :vehicles="store.vehicles" />
            </CTabPanel>
          </CTabContent>
        </CTabs>
      </CCardBody>
    </CCard>

    <!-- ══════════════════ VEHICLE MODAL ══════════════════ -->
    <CModal :visible="showVehicleModal" @close="showVehicleModal = false" size="lg" backdrop="static">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold">
          {{ editingVehicle ? t('transport.editVehicle') : t('transport.newVehicle') }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="vehicleError" color="danger" class="py-2">{{ vehicleError }}</CAlert>
        <CRow class="g-3">
          <CCol v-if="isSuperAdmin" xs="12">
            <label class="form-label fw-semibold">{{ t('common.school') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="vehicleForm.school_id">
              <option value="">{{ t('common.select') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.plateNumber') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="vehicleForm.plate_number" placeholder="T123ABC" />
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.vehicleType') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="vehicleForm.type">
              <option value="bus">{{ t('transport.type_bus') }}</option>
              <option value="minibus">{{ t('transport.type_minibus') }}</option>
              <option value="van">{{ t('transport.type_van') }}</option>
              <option value="car">{{ t('transport.type_car') }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="4">
            <label class="form-label fw-semibold">{{ t('transport.make') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="vehicleForm.make" placeholder="Toyota" />
          </CCol>
          <CCol md="4">
            <label class="form-label fw-semibold">{{ t('transport.model') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="vehicleForm.model" placeholder="Hiace" />
          </CCol>
          <CCol md="4">
            <label class="form-label fw-semibold">{{ t('transport.year') }} <span class="text-danger">*</span></label>
            <CFormInput v-model.number="vehicleForm.year" type="number" />
          </CCol>
          <CCol md="4">
            <label class="form-label fw-semibold">{{ t('transport.capacity') }} <span class="text-danger">*</span></label>
            <CFormInput v-model.number="vehicleForm.capacity" type="number" min="1" />
          </CCol>
          <CCol md="4">
            <label class="form-label">{{ t('transport.color') }}</label>
            <CFormInput v-model="vehicleForm.color" :placeholder="t('transport.colorPlaceholder')" />
          </CCol>
          <CCol md="4">
            <label class="form-label">{{ t('transport.status') }}</label>
            <CFormSelect v-model="vehicleForm.status">
              <option value="active">{{ t('transport.status_active') }}</option>
              <option value="maintenance">{{ t('transport.status_maintenance') }}</option>
              <option value="retired">{{ t('transport.status_retired') }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.driverName') }}</label>
            <CFormInput v-model="vehicleForm.driver_name" />
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.driverPhone') }}</label>
            <CFormInput v-model="vehicleForm.driver_phone" />
          </CCol>
          <CCol xs="12">
            <label class="form-label">{{ t('transport.notes') }}</label>
            <CFormTextarea v-model="vehicleForm.notes" rows="2" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="light" @click="showVehicleModal = false" :disabled="vehicleSaving">{{ t('common.cancel') }}</CButton>
        <CButton color="success" class="fw-semibold px-4" :disabled="vehicleSaving" @click="saveVehicle">
          <CSpinner v-if="vehicleSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════ ROUTE MODAL ══════════════════ -->
    <CModal :visible="showRouteModal" @close="showRouteModal = false" backdrop="static">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold">
          {{ editingRoute ? t('transport.editRoute') : t('transport.newRoute') }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="routeError" color="danger" class="py-2">{{ routeError }}</CAlert>
        <CRow class="g-3">
          <CCol v-if="isSuperAdmin" xs="12">
            <label class="form-label fw-semibold">{{ t('common.school') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="routeForm.school_id">
              <option value="">{{ t('common.select') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('transport.routeName') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="routeForm.name" />
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.from') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="routeForm.start_point" />
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.to') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="routeForm.end_point" />
          </CCol>
          <CCol md="4">
            <label class="form-label">{{ t('transport.distanceKm') }}</label>
            <CFormInput v-model.number="routeForm.distance_km" type="number" step="0.1" />
          </CCol>
          <CCol md="4">
            <label class="form-label">{{ t('transport.durationMin') }}</label>
            <CFormInput v-model.number="routeForm.estimated_minutes" type="number" />
          </CCol>
          <CCol md="4">
            <label class="form-label">{{ t('transport.farePerMonth') }}</label>
            <CFormInput v-model.number="routeForm.monthly_fare_cents" type="number" />
          </CCol>
          <CCol xs="12">
            <label class="form-label">{{ t('transport.descriptionLabel') }}</label>
            <CFormTextarea v-model="routeForm.description" rows="2" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="light" @click="showRouteModal = false" :disabled="routeSaving">{{ t('common.cancel') }}</CButton>
        <CButton color="success" class="fw-semibold px-4" :disabled="routeSaving" @click="saveRoute">
          <CSpinner v-if="routeSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════ SUBSCRIPTION MODAL ══════════════════ -->
    <CModal :visible="showSubModal" @close="showSubModal = false" backdrop="static">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold">{{ t('transport.enrollTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="subError" color="danger" class="py-2">{{ subError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('transport.studentId') }} <span class="text-danger">*</span></label>
            <CFormInput v-model.number="subForm.student_id" type="number" :placeholder="t('transport.studentIdPlaceholder')" />
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.vehicle') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model.number="subForm.vehicle_id">
              <option value="">{{ t('transport.selectVehicle') }}</option>
              <option v-for="v in store.vehicles" :key="v.id" :value="v.id">{{ v.plate_number }} — {{ v.make }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.route') }}</label>
            <CFormSelect v-model.number="subForm.route_id">
              <option value="">{{ t('transport.noRoute') }}</option>
              <option v-for="r in store.routes" :key="r.id" :value="r.id">{{ r.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.direction') }}</label>
            <CFormSelect v-model="subForm.direction">
              <option value="both">{{ t('transport.direction_both') }}</option>
              <option value="morning">{{ t('transport.direction_morning') }}</option>
              <option value="afternoon">{{ t('transport.direction_afternoon') }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.startDate') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="subForm.start_date" type="date" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="light" @click="showSubModal = false" :disabled="subSaving">{{ t('common.cancel') }}</CButton>
        <CButton color="success" class="fw-semibold px-4" :disabled="subSaving" @click="saveSub">
          <CSpinner v-if="subSaving" size="sm" class="me-1" />{{ t('transport.enroll') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════ MAINTENANCE MODAL ══════════════════ -->
    <CModal :visible="showMaintModal" @close="showMaintModal = false" backdrop="static">
      <CModalHeader style="border-bottom:2px solid #007f3e;">
        <CModalTitle class="fw-bold">{{ t('transport.addMaintTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="maintError" color="danger" class="py-2">{{ maintError }}</CAlert>
        <CRow class="g-3">
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.typeLabel') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="maintForm.type">
              <option value="service">{{ t('transport.maint_service') }}</option>
              <option value="repair">{{ t('transport.maint_repair') }}</option>
              <option value="inspection">{{ t('transport.maint_inspection') }}</option>
              <option value="fuel">{{ t('transport.maint_fuel') }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('transport.serviceDate') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="maintForm.service_date" type="date" />
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('transport.descriptionLabel') }} <span class="text-danger">*</span></label>
            <CFormInput v-model="maintForm.description" />
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.cost') }}</label>
            <CFormInput v-model.number="maintForm.cost_cents" type="number" />
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.odometer') }}</label>
            <CFormInput v-model.number="maintForm.odometer_km" type="number" />
          </CCol>
          <CCol md="6">
            <label class="form-label">{{ t('transport.nextService') }}</label>
            <CFormInput v-model="maintForm.next_service_date" type="date" />
          </CCol>
          <CCol xs="12">
            <label class="form-label">{{ t('transport.notes') }}</label>
            <CFormTextarea v-model="maintForm.notes" rows="2" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="light" @click="showMaintModal = false" :disabled="maintSaving">{{ t('common.cancel') }}</CButton>
        <CButton color="success" class="fw-semibold px-4" :disabled="maintSaving" @click="saveMaintenance">
          <CSpinner v-if="maintSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════ CONFIRM DIALOG ══════════════════ -->
    <CModal :visible="confirm.visible" @close="confirm.visible = false" size="sm">
      <CModalHeader><CModalTitle>{{ t('common.confirm') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ confirm.message }}</CModalBody>
      <CModalFooter>
        <CButton color="light" @click="confirm.visible = false">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" class="fw-semibold" @click="confirmOk">{{ t('common.delete') }}</CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
