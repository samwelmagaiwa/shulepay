<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useTransportStore } from '@/stores/transport'
import { useAuthStore }      from '@/stores/auth'
import { useSchoolsStore }   from '@/stores/schools'

const { t }       = useI18n()
const store       = useTransportStore()
const auth        = useAuthStore()
const schoolsStore = useSchoolsStore()
const activeTab   = ref(0)

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
  <CContainer fluid class="py-3">
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

    <!-- Summary Cards -->
    <CRow class="mb-4 g-3">
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold text-primary">{{ store.summaryStats.total_vehicles ?? 0 }}</div>
            <div class="small text-muted">{{ t('transport.totalVehicles') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold text-success">{{ store.summaryStats.active_vehicles ?? 0 }}</div>
            <div class="small text-muted">{{ t('transport.activeVehicles') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold text-warning">{{ store.summaryStats.maintenance_vehicles ?? 0 }}</div>
            <div class="small text-muted">{{ t('transport.inMaintenance') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold text-info">{{ store.summaryStats.students_on_transport ?? 0 }}</div>
            <div class="small text-muted">{{ t('transport.studentsOnTransport') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <!-- Tabs -->
    <CCard class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <CTabs :active-item-key="activeTab" @update:active-item-key="activeTab = $event">
          <CTabList variant="underline-border" class="px-3">
            <CTab :item-key="0">🚌 {{ t('transport.tabVehicles') }}</CTab>
            <CTab :item-key="1">🗺️ {{ t('transport.tabRoutes') }}</CTab>
            <CTab :item-key="2">👦 {{ t('transport.tabStudents') }}</CTab>
            <CTab :item-key="3">🔧 {{ t('transport.tabMaintenance') }}</CTab>
          </CTabList>

          <CTabContent class="p-3">

            <!-- Tab 1: Vehicles -->
            <CTabPanel :item-key="0">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddVehicle">+ {{ t('transport.addVehicle') }}</CButton>
              </div>
              <div v-if="store.loading" class="text-center py-4"><CSpinner /></div>
              <CTable v-else responsive hover class="align-middle">
                <CTableHead class="table-light">
                  <CTableRow>
                    <CTableHeaderCell>#</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.plateNumber') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.makeModelYear') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.capacity') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.driverName') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.status') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.studentsOnTransport') }}</CTableHeaderCell>
                    <CTableHeaderCell></CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-for="(v, i) in store.vehicles" :key="v.id">
                    <CTableDataCell>{{ i + 1 }}</CTableDataCell>
                    <CTableDataCell class="fw-semibold">{{ v.plate_number }}</CTableDataCell>
                    <CTableDataCell>{{ v.make }} {{ v.model }} ({{ v.year }})</CTableDataCell>
                    <CTableDataCell>{{ v.capacity }} {{ t('transport.passengers') }}</CTableDataCell>
                    <CTableDataCell>{{ v.driver_name || '—' }}</CTableDataCell>
                    <CTableDataCell>
                      <CBadge :color="statusBadge(v.status)">{{ t(`transport.status_${v.status}`) }}</CBadge>
                    </CTableDataCell>
                    <CTableDataCell>{{ v.subscribed_students || 0 }}</CTableDataCell>
                    <CTableDataCell>
                      <CButtonGroup size="sm">
                        <CButton color="primary" variant="ghost" @click="openEditVehicle(v)">✏️</CButton>
                        <CButton color="danger"  variant="ghost" @click="deleteVehicle(v)">🗑️</CButton>
                      </CButtonGroup>
                    </CTableDataCell>
                  </CTableRow>
                  <CTableRow v-if="!store.vehicles.length">
                    <CTableDataCell colspan="8" class="text-center text-muted py-4">{{ t('transport.noVehicles') }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
            </CTabPanel>

            <!-- Tab 2: Routes -->
            <CTabPanel :item-key="1">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddRoute">+ {{ t('transport.addRoute') }}</CButton>
              </div>
              <CTable responsive hover class="align-middle">
                <CTableHead class="table-light">
                  <CTableRow>
                    <CTableHeaderCell>#</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.routeName') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.from') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.to') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.distance') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.farePerMonth') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.status') }}</CTableHeaderCell>
                    <CTableHeaderCell></CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-for="(r, i) in store.routes" :key="r.id">
                    <CTableDataCell>{{ i + 1 }}</CTableDataCell>
                    <CTableDataCell class="fw-semibold">{{ r.name }}</CTableDataCell>
                    <CTableDataCell>{{ r.start_point }}</CTableDataCell>
                    <CTableDataCell>{{ r.end_point }}</CTableDataCell>
                    <CTableDataCell>{{ r.distance_km ? r.distance_km + ' km' : '—' }}</CTableDataCell>
                    <CTableDataCell>{{ formatMoney(r.monthly_fare_cents) }}</CTableDataCell>
                    <CTableDataCell>
                      <CBadge :color="r.is_active ? 'success' : 'secondary'">
                        {{ r.is_active ? t('transport.statusOpen') : t('transport.statusClosed') }}
                      </CBadge>
                    </CTableDataCell>
                    <CTableDataCell>
                      <CButton size="sm" color="primary" variant="ghost" @click="openEditRoute(r)">✏️</CButton>
                    </CTableDataCell>
                  </CTableRow>
                  <CTableRow v-if="!store.routes.length">
                    <CTableDataCell colspan="8" class="text-center text-muted py-4">{{ t('transport.noRoutes') }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
            </CTabPanel>

            <!-- Tab 3: Subscriptions -->
            <CTabPanel :item-key="2">
              <div class="d-flex justify-content-end mb-3">
                <CButton color="success" @click="openAddSub">+ {{ t('transport.enrollStudent') }}</CButton>
              </div>
              <CTable responsive hover class="align-middle">
                <CTableHead class="table-light">
                  <CTableRow>
                    <CTableHeaderCell>#</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.student') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.vehicle') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.route') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.direction') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.startDate') }}</CTableHeaderCell>
                    <CTableHeaderCell></CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-for="(s, i) in store.subscriptions" :key="s.id">
                    <CTableDataCell>{{ i + 1 }}</CTableDataCell>
                    <CTableDataCell class="fw-semibold">
                      {{ s.student ? `${s.student.first_name} ${s.student.last_name}` : '—' }}
                    </CTableDataCell>
                    <CTableDataCell>{{ s.vehicle?.plate_number || '—' }}</CTableDataCell>
                    <CTableDataCell>{{ s.route?.name || '—' }}</CTableDataCell>
                    <CTableDataCell>{{ t(`transport.direction_${s.direction}`) }}</CTableDataCell>
                    <CTableDataCell>{{ s.start_date }}</CTableDataCell>
                    <CTableDataCell>
                      <CButton size="sm" color="danger" variant="ghost" @click="removeSub(s)">{{ t('transport.remove') }}</CButton>
                    </CTableDataCell>
                  </CTableRow>
                  <CTableRow v-if="!store.subscriptions.length">
                    <CTableDataCell colspan="7" class="text-center text-muted py-4">{{ t('transport.noStudents') }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
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
              <CTable v-if="store.maintenance.length" responsive hover class="align-middle">
                <CTableHead class="table-light">
                  <CTableRow>
                    <CTableHeaderCell>{{ t('transport.serviceDate') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.typeLabel') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.descriptionLabel') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.cost') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('transport.nextService') }}</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-for="m in store.maintenance" :key="m.id">
                    <CTableDataCell>{{ m.service_date }}</CTableDataCell>
                    <CTableDataCell>{{ t(`transport.maint_${m.type}`) }}</CTableDataCell>
                    <CTableDataCell>{{ m.description }}</CTableDataCell>
                    <CTableDataCell>{{ formatMoney(m.cost_cents) }}</CTableDataCell>
                    <CTableDataCell>{{ m.next_service_date || '—' }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
              <div v-else class="text-center text-muted py-5">
                {{ selectedVehicleId ? t('transport.noRecords') : t('transport.selectVehicleFirst') }}
              </div>
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
