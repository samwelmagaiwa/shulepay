<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useInventoryStore }  from '@/stores/inventory'
import { useSchoolsStore }    from '@/stores/schools'
import { useSchoolStore }     from '@/stores/school'
import { useAuthStore }       from '@/stores/auth'
import { useEmployeesStore }  from '@/stores/employees'

const { t }        = useI18n()
const store        = useInventoryStore()
const schoolStore  = useSchoolsStore()
const navSchool    = useSchoolStore()
const auth         = useAuthStore()
const empStore     = useEmployeesStore()
const canAdjust    = computed(() => auth.hasPermission('inventory.adjustment'))

const schools = computed(() => schoolStore.schools)

// ── Main tab (0 = Consumables, 1 = Fixed Assets) ─────────────────────────────
const activeMainTab = ref(0)

onMounted(async () => {
  try { await schoolStore.fetchSchools() } catch {}
  const initSchool = navSchool.activeSchoolId ? { school_id: navSchool.activeSchoolId } : {}
  await Promise.all([store.fetchItems(initSchool), store.fetchSummary()])
  await loadAssets()
})

// ══════════════════════════════════════════════════════════════════════════════
//  CONSUMABLES TAB
// ══════════════════════════════════════════════════════════════════════════════

// ── Consumable search + filter ────────────────────────────────────────────────
const itemSearch    = ref('')
const itemStockFilter = ref('')   // '' | 'low' | 'ok'
const filteredItems = computed(() => {
  let list = store.items
  const q = itemSearch.value.trim().toLowerCase()
  if (q) list = list.filter(i => i.name.toLowerCase().includes(q))
  if (itemStockFilter.value === 'low') list = list.filter(i => i.is_low_stock)
  if (itemStockFilter.value === 'ok')  list = list.filter(i => !i.is_low_stock)
  return list
})

// ── Add/Edit consumable modal ─────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem   = ref(null)
const itemForm = ref({ name: '', description: '', unit: '', category_id: '', quantity: 0, reorder_level: 5, unit_cost_cents: 0, location: '' })
const itemSaving = ref(false)
const itemError  = ref('')

function openAddItem() {
  editingItem.value = null
  itemForm.value = { name: '', description: '', unit: '', category_id: '', quantity: 0, reorder_level: 5, unit_cost_cents: 0, location: '' }
  itemError.value = ''
  showItemModal.value = true
}

function openEditItem(item) {
  editingItem.value = item
  itemForm.value = {
    name: item.name, description: item.description || '', unit: item.unit,
    category_id: item.category_id || '', quantity: item.quantity,
    reorder_level: item.reorder_level, unit_cost_cents: item.unit_cost_cents,
    location: item.location || '',
  }
  itemError.value = ''
  showItemModal.value = true
}

async function saveItem() {
  itemSaving.value = true
  itemError.value = ''
  try {
    if (editingItem.value) {
      await store.updateItem(editingItem.value.id, itemForm.value)
    } else {
      await store.createItem(itemForm.value)
    }
    showItemModal.value = false
    await store.fetchSummary()
  } catch (e) {
    itemError.value = e?.response?.data?.message || t('common.saveFailed')
  } finally {
    itemSaving.value = false
  }
}

const itemToDelete  = ref(null)
const showDeleteItemModal = ref(false)
const deletingItem  = ref(false)

function confirmDeleteItem(item) { itemToDelete.value = item; showDeleteItemModal.value = true }

async function doDeleteItem() {
  deletingItem.value = true
  try {
    await store.deleteItem(itemToDelete.value.id)
    showDeleteItemModal.value = false
    await store.fetchSummary()
  } catch (e) {
    alert(e?.response?.data?.message || t('common.deleteFailed'))
  } finally {
    deletingItem.value = false
  }
}

// ── Row action reveal ────────────────────────────────────────────────────────
const expandedItemId = ref(null)
function toggleItemActions(id) {
  expandedItemId.value = expandedItemId.value === id ? null : id
}

// ── Transaction panel ─────────────────────────────────────────────────────────
const selectedItem     = ref(null)
const showTxnPanel     = ref(false)
const itemTransactions = ref([])
const txnLoading       = ref(false)
const txnForm = ref({ type: 'in', quantity: 1, issued_to_employee_id: '', notes: '', reference: '', transaction_date: new Date().toISOString().slice(0, 10) })
const txnSaving = ref(false)
const txnError  = ref('')

const selectedTxnEmployee = computed(() =>
  txnForm.value.issued_to_employee_id
    ? empStore.activeEmployees.find(e => e.id == txnForm.value.issued_to_employee_id)
    : null
)

// ── Staff usage modal ─────────────────────────────────────────────────────────
const showStaffUsage     = ref(false)
const staffUsageItem     = ref(null)
const staffUsageData     = ref([])
const staffUsageLoading  = ref(false)

async function openStaffUsage(item) {
  staffUsageItem.value    = item
  showStaffUsage.value    = true
  staffUsageLoading.value = true
  staffUsageData.value    = []
  try {
    const res = await store.fetchStaffUsage(item.id)
    staffUsageData.value = res.staff_usage || []
  } finally {
    staffUsageLoading.value = false
  }
}

async function openItemPanel(item) {
  selectedItem.value = item
  showTxnPanel.value = true
  txnLoading.value = true
  txnForm.value = { type: 'in', quantity: 1, issued_to_employee_id: '', notes: '', reference: '', transaction_date: new Date().toISOString().slice(0, 10) }
  // Load active employees for this school so dropdown is ready
  empStore.fetchActiveEmployees(item.school_id || navSchool.activeSchoolId)
  try {
    itemTransactions.value = await store.fetchTransactions(item.id)
  } finally {
    txnLoading.value = false
  }
}

const txnFieldErrors = ref({ quantity: '', transaction_date: '', issued_to_employee_id: '' })

function validateTxnForm() {
  txnFieldErrors.value = { quantity: '', transaction_date: '', issued_to_employee_id: '' }
  let ok = true
  const qty = Number(txnForm.value.quantity)
  if (!qty || qty <= 0) {
    txnFieldErrors.value.quantity = t('inventory.errQtyRequired')
    ok = false
  }
  if (txnForm.value.type === 'out' && qty > Number(selectedItem.value?.quantity ?? 0)) {
    txnFieldErrors.value.quantity = t('inventory.errQtyExceeds', { max: selectedItem.value?.quantity })
    ok = false
  }
  if (!txnForm.value.transaction_date) {
    txnFieldErrors.value.transaction_date = t('inventory.errDateRequired')
    ok = false
  }
  return ok
}

async function recordTransaction() {
  if (!validateTxnForm()) return
  txnSaving.value = true
  txnError.value = ''
  try {
    const result = await store.recordTransaction(selectedItem.value.id, txnForm.value)
    if (result.item) selectedItem.value = result.item
    itemTransactions.value = await store.fetchTransactions(selectedItem.value.id)
    await store.fetchSummary()
    txnForm.value = { type: 'in', quantity: 1, issued_to_employee_id: '', notes: '', reference: '', transaction_date: new Date().toISOString().slice(0, 10) }
    txnFieldErrors.value = { quantity: '', transaction_date: '', issued_to_employee_id: '' }
  } catch (e) {
    txnError.value = e?.response?.data?.message || t('common.saveFailed')
  } finally {
    txnSaving.value = false
  }
}

// ══════════════════════════════════════════════════════════════════════════════
//  FIXED ASSETS TAB
// ══════════════════════════════════════════════════════════════════════════════

const consumableSchoolId = ref(navSchool.activeSchoolId || '')

const assetFilters = ref({
  school_id: navSchool.activeSchoolId || '',
  category: '', condition: '', status: '', search: '',
})

watch(() => navSchool.activeSchoolId, (id) => {
  consumableSchoolId.value = id || ''
  store.fetchItems(id ? { school_id: id } : {})
  assetFilters.value.school_id = id || ''
  loadAssets(1)
})

const categoryOptions = computed(() => [
  { value: 'furniture',   label: t('assets.categories.furniture') },
  { value: 'technology',  label: t('assets.categories.technology') },
  { value: 'equipment',   label: t('assets.categories.equipment') },
  { value: 'vehicles',    label: t('assets.categories.vehicles') },
  { value: 'buildings',   label: t('assets.categories.buildings') },
  { value: 'sports',      label: t('assets.categories.sports') },
  { value: 'other',       label: t('assets.categories.other') },
])

const formTabs = computed(() => [
  t('assets.tabs.identity'),
  t('assets.tabs.purchase'),
  t('assets.tabs.depreciation'),
  t('assets.tabs.condition'),
])

// Modal state
const showDetail       = ref(false)
const showModal        = ref(false)
const showDisposeModal = ref(false)
const showDeleteModal  = ref(false)
const detailAsset      = ref(null)
const editAsset        = ref(null)
const disposeTarget    = ref(null)
const toDelete         = ref(null)
const activeTab        = ref(0)
const saving           = ref(false)
const disposing        = ref(false)
const deleting         = ref(false)
const loadingTag       = ref(false)
const formError        = ref('')
const disposeError     = ref('')
const photoFile        = ref(null)
const photoPreview     = ref('')
const depPreview       = ref(null)
const form             = ref(defaultForm())
const disposeForm      = ref({ disposal_date: '', disposal_value: '', disposal_reason: '' })

const assetSummaryCards = computed(() => {
  const list = store.assets
  return [
    { label: t('assets.summary.totalAssets'),  value: store.summaryStats.total_fixed_assets ?? 0,                                                                                  color: 'primary' },
    { label: t('assets.summary.totalCost'),    value: formatTZS(list.reduce((s, a) => s + (a.purchase_cost_cents || 0), 0)),                                                        color: 'info'    },
    { label: t('assets.summary.currentValue'), value: formatTZS(store.summaryStats.fixed_assets_value_cents ?? list.reduce((s, a) => s + (a.current_value_cents || 0), 0)),         color: 'success' },
    { label: t('assets.summary.inUse'),        value: store.summaryStats.fixed_assets_in_use ?? list.filter(a => a.status === 'in_use').length,                                     color: 'warning' },
  ]
})

function defaultForm() {
  return {
    asset_tag: '', name: '', category: '', school_id: navSchool.activeSchoolId || '', quantity: 1,
    serial_no: '', purchase_cost: '', purchase_date: '', supplier_name: '',
    invoice_no: '', funding_source: '', depreciation_method: '', useful_life_years: '',
    depreciation_rate: '', salvage_value: '', custodian: '', custodian_employee_id: '', location: '',
    condition: 'good', status: 'in_use', warranty_expiry: '', notes: '',
  }
}

let searchTimer = null
function debouncedLoadAssets() {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => loadAssets(1), 400)
}

async function loadAssets(page = 1) {
  const params = { page }
  if (assetFilters.value.school_id) params.school_id = assetFilters.value.school_id
  if (assetFilters.value.category)  params.category  = assetFilters.value.category
  if (assetFilters.value.condition) params.condition  = assetFilters.value.condition
  if (assetFilters.value.status)    params.status     = assetFilters.value.status
  if (assetFilters.value.search)    params.search     = assetFilters.value.search
  await store.fetchAssets(params)
}

function goPage(p) { loadAssets(p) }

function handlePhotoChange(e) {
  const file = e.target.files[0]
  if (!file) return
  photoFile.value = file
  const reader = new FileReader()
  reader.onload = ev => { photoPreview.value = ev.target.result }
  reader.readAsDataURL(file)
}

function updateDepPreview() {
  const cost = (form.value.purchase_cost || 0) * 100
  if (!cost || !form.value.purchase_date) { depPreview.value = cost || null; return }
  const years   = (new Date() - new Date(form.value.purchase_date)) / (365.25 * 24 * 3600 * 1000)
  const salvage = (form.value.salvage_value || 0) * 100
  let dep = 0
  if (form.value.depreciation_method === 'straight_line' && form.value.useful_life_years > 0) {
    dep = Math.min(((cost - salvage) / form.value.useful_life_years) * years, cost - salvage)
  } else if (form.value.depreciation_method === 'reducing_balance' && form.value.depreciation_rate > 0) {
    let val = cost; const r = form.value.depreciation_rate / 100; const wy = Math.floor(years)
    for (let i = 0; i < wy; i++) { const d = val * r; dep += d; val -= d }
    dep += val * r * (years - wy)
  }
  depPreview.value = Math.max(salvage, cost - Math.round(dep))
}

async function autoFillTag() {
  const sid = form.value.school_id || assetFilters.value.school_id || navSchool.activeSchoolId
  if (!sid) { alert(t('assets.errors.autoFillNoSchool')); return }
  loadingTag.value = true
  try { form.value.asset_tag = await store.nextTag(sid) }
  catch { alert(t('assets.errors.autoFillFailed')) }
  finally { loadingTag.value = false }
}

function openDetail(a) { detailAsset.value = a; showDetail.value = true }

function openAdd() {
  editAsset.value = null; formError.value = ''; photoFile.value = null; photoPreview.value = ''
  depPreview.value = null; activeTab.value = 0
  form.value = defaultForm()
  empStore.fetchActiveEmployees(navSchool.activeSchoolId)
  showModal.value = true
}

function openEdit(a) {
  editAsset.value = a; formError.value = ''; photoFile.value = null; photoPreview.value = ''
  depPreview.value = null; activeTab.value = 0
  empStore.fetchActiveEmployees(a.school_id || navSchool.activeSchoolId)
  form.value = {
    asset_tag:           a.asset_tag || '',
    name:                a.name || '',
    category:            a.category || '',
    school_id:           a.school_id || '',
    quantity:            a.quantity ?? 1,
    serial_no:           a.serial_no || a.serial_number || '',
    purchase_cost:       Math.round((a.purchase_cost_cents || 0) / 100),
    purchase_date:       a.purchase_date || '',
    supplier_name:       a.supplier_name || '',
    invoice_no:          a.invoice_no || '',
    funding_source:      a.funding_source || '',
    depreciation_method: a.depreciation_method || '',
    useful_life_years:   a.useful_life_years || '',
    depreciation_rate:   a.depreciation_rate || '',
    salvage_value:       Math.round((a.salvage_value_cents || 0) / 100),
    custodian:              a.custodian || '',
    custodian_employee_id:  a.custodian_employee_id || '',
    location:               a.location || '',
    condition:           a.condition || 'good',
    status:              a.status || 'in_use',
    warranty_expiry:     a.warranty_expiry || '',
    notes:               a.notes || '',
  }
  updateDepPreview()
  showModal.value = true
}

function openDispose(a) {
  disposeTarget.value  = a; disposeError.value = ''
  disposeForm.value    = { disposal_date: new Date().toISOString().slice(0, 10), disposal_value: '', disposal_reason: '' }
  showDisposeModal.value = true
}

function confirmDelete(a) { toDelete.value = a; showDeleteModal.value = true }

async function submitAsset() {
  formError.value = ''; saving.value = true
  try {
    let payload
    if (photoFile.value) {
      payload = new FormData()
      Object.entries(form.value).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) payload.append(k, v) })
      payload.append('photo', photoFile.value)
    } else {
      payload = { ...form.value }
      Object.keys(payload).forEach(k => { if (payload[k] === '' || payload[k] === undefined) delete payload[k] })
    }
    if (editAsset.value) {
      await store.updateAsset(editAsset.value.id, payload)
    } else {
      await store.createAsset(payload)
    }
    showModal.value = false
    await loadAssets()
    await store.fetchSummary()
  } catch (e) {
    const errors = e?.response?.data?.errors
    formError.value = errors ? Object.values(errors).flat().join(' ') : (e?.response?.data?.message || 'Imeshindwa. Jaribu tena.')
  } finally {
    saving.value = false
  }
}

async function doDispose() {
  disposeError.value = ''; disposing.value = true
  try {
    await store.disposeAsset(disposeTarget.value.id, {
      disposal_date:   disposeForm.value.disposal_date,
      disposal_value:  disposeForm.value.disposal_value || 0,
      disposal_reason: disposeForm.value.disposal_reason,
    })
    showDisposeModal.value = false
    await loadAssets()
  } catch (e) {
    disposeError.value = e?.response?.data?.message || 'Imeshindwa. Jaribu tena.'
  } finally {
    disposing.value = false
  }
}

async function doDelete() {
  deleting.value = true
  try {
    await store.deleteAsset(toDelete.value.id)
    showDeleteModal.value = false
    await loadAssets()
    await store.fetchSummary()
  } catch (e) {
    alert(e?.response?.data?.message || t('assets.errors.deleteFailed'))
  } finally {
    deleting.value = false
  }
}

// ── Shared helpers ────────────────────────────────────────────────────────────
function formatMoney(cents) { return 'TZS ' + ((cents || 0) / 100).toLocaleString() }
function formatTZS(cents) { return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 }) }
function txnTypeBadge(type) { return { in: 'success', out: 'danger', adjustment: 'warning' }[type] || 'secondary' }
function txnTypeLabel(type) { return { in: t('inventory.txnIn'), out: t('inventory.txnOut'), adjustment: t('inventory.txnAdjustment') }[type] || type }
function valueColor(asset) {
  const cost = asset.purchase_cost_cents || 0
  if (!cost) return 'text-muted'
  const ratio = (asset.current_value_cents || 0) / cost
  if (ratio > 0.5) return 'text-success fw-bold'
  if (ratio > 0.2) return 'text-warning fw-bold'
  return 'text-danger fw-bold'
}
function conditionBadgeColor(c) { return { excellent: 'success', good: 'info', fair: 'warning', poor: 'danger' }[c] || 'secondary' }
function conditionLabel(c) {
  const map = { excellent: 'assets.conditions.excellent', good: 'assets.conditions.good', fair: 'assets.conditions.fair', poor: 'assets.conditions.poor' }
  return map[c] ? t(map[c]) : (c || '—')
}
function statusBadgeColor(s) { return { in_use: 'success', under_repair: 'warning', disposed: 'secondary', lost: 'danger', written_off: 'dark' }[s] || 'secondary' }
function statusLabel(s) {
  const map = { in_use: 'assets.statuses.in_use', under_repair: 'assets.statuses.under_repair', disposed: 'assets.statuses.disposed', lost: 'assets.statuses.lost', written_off: 'assets.statuses.written_off' }
  return map[s] ? t(map[s]) : (s || '—')
}
function categoryLabel(c) { return categoryOptions.value.find(o => o.value === c)?.label || (c || '—') }
function fundingLabel(f) {
  const map = { fees: 'assets.funding.fees', donation: 'assets.funding.donation', grant: 'assets.funding.grant' }
  return map[f] ? t(map[f]) : (f || '—')
}
function depMethodLabel(m) {
  const map = { straight_line: 'assets.depMethods.straight_line', reducing_balance: 'assets.depMethods.reducing_balance' }
  return map[m] ? t(map[m]) : (m || '—')
}
function canDispose(a) { return ['in_use', 'under_repair'].includes(a.status) }
function canDelete(a)  { return ['disposed', 'lost', 'written_off'].includes(a.status) }
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- ── Unified toolbar: tabs + filters + action button — single row ─────── -->
    <div class="d-flex align-items-center gap-2 mb-3" style="overflow-x:auto; white-space:nowrap; flex-wrap:nowrap;">
      <!-- Tab switcher -->
      <div class="d-flex flex-shrink-0 border rounded" style="overflow:hidden;">
        <button
          @click="activeMainTab = 0"
          :class="['btn btn-sm px-3', activeMainTab === 0 ? 'btn-primary' : 'btn-outline-secondary border-0']"
          style="height:38px; white-space:nowrap; border-radius:0;">
          {{ t('inventory.tabConsumables') }}
        </button>
        <button
          @click="activeMainTab = 1"
          :class="['btn btn-sm px-3', activeMainTab === 1 ? 'btn-primary' : 'btn-outline-secondary border-0']"
          style="height:38px; white-space:nowrap; border-radius:0;">
          {{ t('inventory.tabAssets') }}
        </button>
      </div>

      <!-- Consumable filters (only on Stock tab) -->
      <template v-if="activeMainTab === 0">
        <select v-model="consumableSchoolId" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto; max-width:180px;"
          @change="store.fetchItems(consumableSchoolId ? { school_id: consumableSchoolId } : {})">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="itemStockFilter" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto;">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="low">{{ t('inventory.statusLow') }}</option>
          <option value="ok">{{ t('inventory.statusOk') }}</option>
        </select>
        <input v-model="itemSearch" :placeholder="t('inventory.itemSearchPlaceholder')" class="form-control form-control-sm flex-shrink-0" style="height:38px; width:200px;" />
      </template>

      <!-- Asset filters (only on Fixed Assets tab) -->
      <template v-if="activeMainTab === 1">
        <select v-model="assetFilters.school_id" @change="loadAssets(1)" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto; max-width:180px;">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="assetFilters.category" @change="loadAssets(1)" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto;">
          <option value="">{{ t('assets.allTypes') }}</option>
          <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
        <select v-model="assetFilters.condition" @change="loadAssets(1)" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto;">
          <option value="">{{ t('assets.allConditions') }}</option>
          <option value="excellent">{{ t('assets.conditions.excellent') }}</option>
          <option value="good">{{ t('assets.conditions.good') }}</option>
          <option value="fair">{{ t('assets.conditions.fair') }}</option>
          <option value="poor">{{ t('assets.conditions.poor') }}</option>
        </select>
        <select v-model="assetFilters.status" @change="loadAssets(1)" class="form-select form-select-sm flex-shrink-0" style="height:38px; width:auto;">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="in_use">{{ t('assets.statuses.in_use') }}</option>
          <option value="under_repair">{{ t('assets.statuses.under_repair') }}</option>
          <option value="disposed">{{ t('assets.statuses.disposed') }}</option>
          <option value="lost">{{ t('assets.statuses.lost') }}</option>
          <option value="written_off">{{ t('assets.statuses.written_off') }}</option>
        </select>
        <input v-model="assetFilters.search" @input="debouncedLoadAssets" :placeholder="t('assets.searchPlaceholder')" class="form-control form-control-sm flex-shrink-0" style="height:38px; width:180px;" />
      </template>

      <!-- Action button pushed to the right -->
      <CButton v-if="activeMainTab === 0" color="success" size="sm" @click="openAddItem" class="ms-auto flex-shrink-0" style="height:38px; white-space:nowrap;">
        <CIcon icon="cilPlus" class="me-1" /> {{ t('inventory.addItem') }}
      </CButton>
      <CButton v-else color="success" size="sm" @click="openAdd" class="ms-auto flex-shrink-0" style="height:38px; white-space:nowrap;">
        <CIcon icon="cilPlus" class="me-1" /> {{ t('assets.add') }}
      </CButton>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         CONSUMABLES TAB
    ══════════════════════════════════════════════════════════════════════════ -->
    <div v-show="activeMainTab === 0">

      <!-- Summary Cards -->
      <CRow class="mb-3 g-3">
        <CCol xs="12" md="4">
          <CCard class="border-0 shadow-sm h-100">
            <CCardBody class="text-center">
              <div class="fs-2 fw-bold text-primary">{{ store.summaryStats.total_items ?? 0 }}</div>
              <div class="small text-muted">{{ t('inventory.totalItems') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" md="4">
          <CCard class="border-0 shadow-sm h-100">
            <CCardBody class="text-center">
              <div class="fs-2 fw-bold" :class="(store.summaryStats.low_stock_count ?? 0) > 0 ? 'text-danger' : 'text-success'">
                {{ store.summaryStats.low_stock_count ?? 0 }}
              </div>
              <div class="small text-muted">
                {{ t('inventory.lowStock') }}
                <CBadge v-if="(store.summaryStats.low_stock_count ?? 0) > 0" color="danger" class="ms-1">!</CBadge>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" md="4">
          <CCard class="border-0 shadow-sm h-100">
            <CCardBody class="text-center">
              <div class="fs-5 fw-bold text-info">{{ formatMoney(store.summaryStats.total_value_cents) }}</div>
              <div class="small text-muted">{{ t('inventory.totalValue') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <!-- Items Table -->
      <CCard class="border-0 shadow-sm">
        <CCardBody>
          <div v-if="store.loading" class="text-center py-4"><CSpinner /></div>
          <CTable v-else responsive hover class="align-middle">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>#</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.itemName') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.unit') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.quantity') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.reorderLevel') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.unitCost') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.totalValueCol') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="(item, i) in filteredItems" :key="item.id" :class="item.is_low_stock ? 'table-danger' : ''">
                <CTableDataCell>{{ i + 1 }}</CTableDataCell>
                <CTableDataCell>
                  <span class="fw-semibold">{{ item.name }}</span>
                  <span v-if="item.is_low_stock" class="ms-2 text-danger" title="Inakwisha">⚠️</span>
                </CTableDataCell>
                <CTableDataCell>{{ item.unit }}</CTableDataCell>
                <CTableDataCell :class="item.is_low_stock ? 'text-danger fw-bold' : ''">{{ Number(item.quantity).toLocaleString() }}</CTableDataCell>
                <CTableDataCell>{{ Number(item.reorder_level).toLocaleString() }}</CTableDataCell>
                <CTableDataCell>{{ formatMoney(item.unit_cost_cents) }}</CTableDataCell>
                <CTableDataCell>{{ formatMoney(item.total_value_cents) }}</CTableDataCell>
                <CTableDataCell style="min-width:48px; position:relative;">
                  <CButton size="sm" color="secondary" variant="ghost" @click.stop="toggleItemActions(item.id)">👁️</CButton>
                  <div v-if="expandedItemId === item.id"
                       style="position:absolute; top:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:10; min-width:160px;"
                       @click.stop>
                    <CButton size="sm" color="info" variant="ghost" class="text-start" @click="openItemPanel(item); expandedItemId=null">📋 {{ t('inventory.btnTransactions') }}</CButton>
                    <CButton size="sm" color="primary" variant="ghost" class="text-start" @click="openEditItem(item); expandedItemId=null">✏️ {{ t('common.edit') }}</CButton>
                    <CButton size="sm" color="danger" variant="ghost" class="text-start" @click="confirmDeleteItem(item); expandedItemId=null">🗑️ {{ t('common.delete') }}</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="filteredItems.length === 0">
                <CTableDataCell colspan="8" class="text-center text-muted py-4">{{ t('inventory.noItems') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════════
         FIXED ASSETS TAB
    ══════════════════════════════════════════════════════════════════════════ -->
    <div v-show="activeMainTab === 1">

      <!-- Summary Cards -->
      <CRow class="g-2 mb-3">
        <CCol v-for="card in assetSummaryCards" :key="card.label" xs="6" md="3">
          <CCard class="border-0 shadow-sm h-100">
            <CCardBody class="p-3">
              <div class="text-muted small mb-1">{{ card.label }}</div>
              <div class="fw-bold fs-5" :class="`text-${card.color}`">{{ card.value }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <div v-if="store.assetsLoading" class="text-center py-5"><CSpinner color="primary" /></div>

      <div v-else>
        <!-- Mobile cards -->
        <div class="d-md-none">
          <div v-if="!store.assets.length" class="text-center text-muted py-5">{{ t('assets.noAssets') }}</div>
          <div v-for="a in store.assets" :key="a.id" class="mb-2 border rounded p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                  <CBadge color="secondary" class="font-monospace small">{{ a.asset_tag || '—' }}</CBadge>
                  <CBadge :color="conditionBadgeColor(a.condition)">{{ conditionLabel(a.condition) }}</CBadge>
                </div>
                <div class="fw-bold">{{ a.name }}</div>
                <div class="small text-muted">{{ categoryLabel(a.category) }} · {{ a.location || '—' }}</div>
              </div>
              <CBadge :color="statusBadgeColor(a.status)" class="ms-2">{{ statusLabel(a.status) }}</CBadge>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <div class="small">
                <div>{{ t('assets.cost') }}: <strong>{{ formatTZS(a.purchase_cost_cents) }}</strong></div>
                <div>{{ t('assets.currentValue') }}: <strong :class="valueColor(a)">{{ formatTZS(a.current_value_cents) }}</strong></div>
              </div>
              <div class="d-flex gap-1">
                <CButton size="sm" color="info" variant="outline" @click="openDetail(a)" style="min-height:44px; min-width:44px;"><CIcon icon="cilEyedropper" /></CButton>
                <CButton size="sm" color="primary" variant="outline" @click="openEdit(a)" style="min-height:44px; min-width:44px;"><CIcon icon="cilPencil" /></CButton>
                <CButton v-if="canDispose(a)" size="sm" color="warning" variant="outline" @click="openDispose(a)" style="min-height:44px; min-width:44px;"><CIcon icon="cilXCircle" /></CButton>
                <CButton v-if="canDelete(a)" size="sm" color="danger" variant="ghost" @click="confirmDelete(a)" style="min-height:44px; min-width:44px;"><CIcon icon="cilTrash" /></CButton>
              </div>
            </div>
          </div>
        </div>

        <!-- Desktop table -->
        <CCard class="d-none d-md-block">
          <CCardBody class="p-0">
            <CTable responsive hover class="mb-0">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('assets.assetTag') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('assets.name') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('assets.category') }}</CTableHeaderCell>
                  <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('assets.quantity') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('assets.cost') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('assets.currentValue') }}</CTableHeaderCell>
                  <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('assets.condition') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="a in store.assets" :key="a.id">
                  <CTableDataCell><span class="font-monospace small text-muted">{{ a.asset_tag || '—' }}</span></CTableDataCell>
                  <CTableDataCell>
                    <div class="fw-semibold">{{ a.name }}</div>
                    <div v-if="a.serial_no" class="small text-muted">S/N: {{ a.serial_no }}</div>
                  </CTableDataCell>
                  <CTableDataCell>{{ categoryLabel(a.category) }}</CTableDataCell>
                  <CTableDataCell class="d-none d-lg-table-cell">{{ a.quantity ?? 1 }}</CTableDataCell>
                  <CTableDataCell>{{ formatTZS(a.purchase_cost_cents) }}</CTableDataCell>
                  <CTableDataCell :class="valueColor(a)">{{ formatTZS(a.current_value_cents) }}</CTableDataCell>
                  <CTableDataCell class="d-none d-lg-table-cell">
                    <CBadge :color="conditionBadgeColor(a.condition)">{{ conditionLabel(a.condition) }}</CBadge>
                  </CTableDataCell>
                  <CTableDataCell>
                    <CBadge :color="statusBadgeColor(a.status)">{{ statusLabel(a.status) }}</CBadge>
                  </CTableDataCell>
                  <CTableDataCell>
                    <CDropdown variant="btn-group" placement="bottom-end">
                      <CDropdownToggle color="primary" variant="outline" size="sm" class="py-1 px-2" :caret="false">
                        <CIcon icon="cilLowVision" />
                      </CDropdownToggle>
                      <CDropdownMenu>
                        <CDropdownItem @click="openDetail(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                          <CIcon icon="cilLowVision" class="text-info" /> {{ t('assets.detail.title') }}
                        </CDropdownItem>
                        <CDropdownItem @click="openEdit(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                          <CIcon icon="cilPencil" class="text-primary" /> {{ t('common.edit') }}
                        </CDropdownItem>
                        <CDropdownItem v-if="canDispose(a)" @click="openDispose(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2">
                          <CIcon icon="cilXCircle" class="text-warning" /> {{ t('assets.form.dispose') }}
                        </CDropdownItem>
                        <CDropdownDivider v-if="canDelete(a)" />
                        <CDropdownItem v-if="canDelete(a)" @click="confirmDelete(a)" style="cursor:pointer;" class="d-flex align-items-center gap-2 text-danger">
                          <CIcon icon="cilTrash" /> {{ t('common.delete') }}
                        </CDropdownItem>
                      </CDropdownMenu>
                    </CDropdown>
                  </CTableDataCell>
                </CTableRow>
                <CTableRow v-if="!store.assets.length">
                  <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('assets.noAssets') }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </CCardBody>
        </CCard>

        <!-- Pagination -->
        <div v-if="store.lastPage > 1" class="d-flex justify-content-center mt-3">
          <CPagination>
            <CPaginationItem :disabled="store.currentPage <= 1" @click="goPage(store.currentPage - 1)">&laquo;</CPaginationItem>
            <CPaginationItem v-for="p in store.lastPage" :key="p" :active="p === store.currentPage" @click="goPage(p)">{{ p }}</CPaginationItem>
            <CPaginationItem :disabled="store.currentPage >= store.lastPage" @click="goPage(store.currentPage + 1)">&raquo;</CPaginationItem>
          </CPagination>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════
         CONSUMABLE MODALS
    ════════════════════════════════════════════════ -->

    <!-- Add/Edit Consumable -->
    <CModal :visible="showItemModal" @close="showItemModal = false">
      <CModalHeader>
        <CModalTitle>{{ editingItem ? t('inventory.editItemTitle') : t('inventory.addItemTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="itemError" color="danger">{{ itemError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12"><CFormLabel>{{ t('inventory.itemNameLabel') }}</CFormLabel><CFormInput v-model="itemForm.name" /></CCol>
          <CCol md="6"><CFormLabel>{{ t('inventory.unitLabel') }}</CFormLabel><CFormInput v-model="itemForm.unit" :placeholder="t('inventory.unitPlaceholder')" /></CCol>
          <CCol md="6"><CFormLabel>{{ t('inventory.locationLabel') }}</CFormLabel><CFormInput v-model="itemForm.location" :placeholder="t('inventory.locationPlaceholder')" /></CCol>
          <CCol md="4"><CFormLabel>{{ t('inventory.currentQtyLabel') }}</CFormLabel><CFormInput v-model.number="itemForm.quantity" type="number" step="0.01" /></CCol>
          <CCol md="4"><CFormLabel>{{ t('inventory.reorderLabel') }}</CFormLabel><CFormInput v-model.number="itemForm.reorder_level" type="number" step="0.01" /></CCol>
          <CCol md="4"><CFormLabel>{{ t('inventory.unitCostLabel') }}</CFormLabel><CFormInput v-model.number="itemForm.unit_cost_cents" type="number" /></CCol>
          <CCol xs="12"><CFormLabel>{{ t('inventory.descriptionLabel') }}</CFormLabel><CFormTextarea v-model="itemForm.description" rows="2" /></CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showItemModal = false">{{ t('common.close') }}</CButton>
        <CButton color="success" :disabled="itemSaving" @click="saveItem">
          <CSpinner v-if="itemSaving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Transaction Panel -->
    <CModal :visible="showTxnPanel" @close="showTxnPanel = false" size="lg">
      <CModalHeader>
        <CModalTitle>📋 {{ selectedItem?.name }} — {{ t('inventory.txnPanelTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CRow class="mb-3 g-2" v-if="selectedItem">
          <CCol xs="4"><CCard class="border-0 bg-light text-center py-2"><div class="fw-bold fs-5">{{ Number(selectedItem.quantity).toLocaleString() }}</div><div class="small text-muted">{{ t('inventory.currentQty') }}</div></CCard></CCol>
          <CCol xs="4"><CCard class="border-0 bg-light text-center py-2"><div class="fw-bold fs-5">{{ selectedItem.unit }}</div><div class="small text-muted">{{ t('inventory.unitLabel2') }}</div></CCard></CCol>
          <CCol xs="4"><CCard class="border-0 bg-light text-center py-2"><div class="fw-bold fs-5" :class="selectedItem.is_low_stock ? 'text-danger' : 'text-success'">{{ selectedItem.is_low_stock ? t('inventory.statusLow') : t('inventory.statusOk') }}</div><div class="small text-muted">{{ t('inventory.statusLabel') }}</div></CCard></CCol>
        </CRow>
        <CCard class="border-primary mb-3">
          <CCardHeader class="fw-semibold">{{ t('inventory.recordNew') }}</CCardHeader>
          <CCardBody>
            <CAlert v-if="txnError" color="danger" class="py-2">{{ txnError }}</CAlert>
            <CRow class="g-2">
              <!-- Type -->
              <CCol md="4">
                <CFormLabel>{{ t('inventory.txnType') }} <span class="text-danger">*</span></CFormLabel>
                <CFormSelect v-model="txnForm.type">
                  <option value="in">{{ t('inventory.txnIn') }}</option>
                  <option value="out">{{ t('inventory.txnOut') }}</option>
                  <option v-if="canAdjust" value="adjustment">{{ t('inventory.txnAdjustment') }}</option>
                </CFormSelect>
              </CCol>
              <!-- Quantity -->
              <CCol md="4">
                <CFormLabel>{{ t('inventory.txnQty') }} <span class="text-danger">*</span></CFormLabel>
                <CFormInput
                  v-model.number="txnForm.quantity"
                  type="number" step="0.01" min="0.01"
                  :class="txnFieldErrors.quantity ? 'is-invalid' : ''"
                  @input="txnFieldErrors.quantity = ''"
                />
                <div v-if="txnFieldErrors.quantity" class="invalid-feedback">{{ txnFieldErrors.quantity }}</div>
                <div v-if="txnForm.type === 'out'" class="form-text text-muted">
                  {{ t('inventory.available') }}: {{ Number(selectedItem?.quantity ?? 0).toLocaleString() }} {{ selectedItem?.unit }}
                </div>
              </CCol>
              <!-- Date -->
              <CCol md="4">
                <CFormLabel>{{ t('inventory.txnDate') }} <span class="text-danger">*</span></CFormLabel>
                <CFormInput
                  v-model="txnForm.transaction_date"
                  type="date"
                  :class="txnFieldErrors.transaction_date ? 'is-invalid' : ''"
                  @change="txnFieldErrors.transaction_date = ''"
                />
                <div v-if="txnFieldErrors.transaction_date" class="invalid-feedback">{{ txnFieldErrors.transaction_date }}</div>
              </CCol>
              <!-- Issued To (Stock Out only) -->
              <CCol v-if="txnForm.type === 'out'" xs="12" md="6">
                <CFormLabel>{{ t('inventory.txnIssuedTo') }}</CFormLabel>
                <CFormSelect v-model="txnForm.issued_to_employee_id" style="min-height:38px;">
                  <option value="">{{ t('inventory.txnSelectEmployee') }}</option>
                  <option v-for="emp in empStore.activeEmployees" :key="emp.id" :value="emp.id">
                    {{ emp.full_name }}{{ emp.department ? ' — ' + emp.department : '' }}
                  </option>
                </CFormSelect>
                <div v-if="selectedTxnEmployee" class="mt-1 text-muted small">
                  <span class="badge bg-info text-dark me-1">{{ selectedTxnEmployee.role }}</span>
                  <span v-if="selectedTxnEmployee.department">{{ selectedTxnEmployee.department }}</span>
                </div>
              </CCol>
              <!-- Reference -->
              <CCol :md="txnForm.type === 'out' ? 6 : 6">
                <CFormLabel>{{ t('inventory.txnReference') }}</CFormLabel>
                <CFormInput v-model="txnForm.reference" placeholder="LPO-001..." />
              </CCol>
              <!-- Notes -->
              <CCol xs="12" md="6">
                <CFormLabel>{{ t('inventory.txnNotes') }}</CFormLabel>
                <CFormInput v-model="txnForm.notes" />
              </CCol>
              <CCol xs="12" class="text-end">
                <CButton color="success" :disabled="txnSaving" @click="recordTransaction">
                  <CSpinner v-if="txnSaving" size="sm" class="me-1" />{{ t('inventory.txnRecord') }}
                </CButton>
              </CCol>
            </CRow>
          </CCardBody>
        </CCard>
        <div v-if="txnLoading" class="text-center py-3"><CSpinner /></div>
        <template v-else-if="itemTransactions.length > 0">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold small text-muted">{{ t('inventory.txnHistory') }} ({{ itemTransactions.length }})</span>
            <CButton size="sm" color="info" variant="outline" @click="openStaffUsage(selectedItem)">
              👥 {{ t('inventory.staffUsageBtn') }}
            </CButton>
          </div>
          <CTable responsive small>
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('inventory.txnDateCol') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.txnTypeCol') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.txnQtyCol') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('inventory.txnIssuedTo') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('inventory.txnNotesCol') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('inventory.txnRecordedBy') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="txn in itemTransactions" :key="txn.id">
                <CTableDataCell>{{ txn.transaction_date ? new Date(txn.transaction_date).toLocaleDateString('en-GB') : '—' }}</CTableDataCell>
                <CTableDataCell><CBadge :color="txnTypeBadge(txn.type)">{{ txnTypeLabel(txn.type) }}</CBadge></CTableDataCell>
                <CTableDataCell>{{ Number(txn.quantity).toLocaleString() }}</CTableDataCell>
                <CTableDataCell>
                  <template v-if="txn.issued_to_employee">
                    <div class="fw-semibold small">{{ txn.issued_to_employee.full_name }}</div>
                    <div class="text-muted" style="font-size:0.75rem;">{{ txn.issued_to_employee.department || txn.issued_to_employee.role }}</div>
                  </template>
                  <span v-else class="text-muted">—</span>
                </CTableDataCell>
                <CTableDataCell class="small text-muted d-none d-md-table-cell">{{ txn.notes || txn.reference || '—' }}</CTableDataCell>
                <CTableDataCell class="small text-muted d-none d-md-table-cell">{{ txn.recorder?.name || '—' }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </template>
        <div v-else class="text-center text-muted py-3">{{ t('inventory.noTxnHistory') }}</div>
      </CModalBody>
      <CModalFooter><CButton color="secondary" @click="showTxnPanel = false">{{ t('common.close') }}</CButton></CModalFooter>
    </CModal>

    <!-- Staff Usage History Modal (Consumables) -->
    <CModal :visible="showStaffUsage" @close="showStaffUsage = false" size="lg">
      <CModalHeader>
        <CModalTitle>👥 {{ staffUsageItem?.name }} — {{ t('inventory.staffUsageTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <div v-if="staffUsageLoading" class="text-center py-4"><CSpinner /></div>
        <div v-else-if="staffUsageData.length === 0" class="text-center text-muted py-4">{{ t('inventory.staffUsageEmpty') }}</div>
        <div v-else>
          <div v-for="row in staffUsageData" :key="row.employee?.id" class="mb-4 border rounded p-3">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-bold fs-6">{{ row.employee?.full_name }}</div>
                <div class="text-muted small">
                  <span class="badge bg-secondary me-1">{{ row.employee?.role }}</span>
                  {{ row.employee?.department || '' }}
                  <span v-if="row.employee?.staff_number" class="ms-2 text-muted">· {{ row.employee.staff_number }}</span>
                </div>
              </div>
              <div class="text-end">
                <div class="fw-bold text-danger">{{ Number(row.total_issued).toLocaleString() }} {{ staffUsageItem?.unit }}</div>
                <div class="text-muted small">{{ t('inventory.staffTotalIssued') }}</div>
              </div>
            </div>
            <CTable small responsive class="mb-0">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('inventory.txnDateCol') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('inventory.txnQtyCol') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('inventory.txnNotesCol') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('inventory.txnRecordedBy') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="txn in row.transactions" :key="txn.id">
                  <CTableDataCell>{{ txn.transaction_date ? new Date(txn.transaction_date).toLocaleDateString('en-GB') : '—' }}</CTableDataCell>
                  <CTableDataCell>{{ Number(txn.quantity).toLocaleString() }}</CTableDataCell>
                  <CTableDataCell class="text-muted small">{{ txn.notes || txn.reference || '—' }}</CTableDataCell>
                  <CTableDataCell class="text-muted small">{{ txn.recorder?.name || '—' }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showStaffUsage = false">{{ t('common.close') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- ══════════════════════════════════════════════
         FIXED ASSET MODALS
    ════════════════════════════════════════════════ -->

    <!-- Detail Modal -->
    <CModal :visible="showDetail" @close="showDetail = false" size="xl" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>
          <span class="font-monospace me-2 text-muted small">{{ detailAsset?.asset_tag }}</span>
          {{ detailAsset?.name }}
        </CModalTitle>
      </CModalHeader>
      <CModalBody v-if="detailAsset">
        <div v-if="detailAsset.photo_url" class="mb-3 text-center">
          <img :src="detailAsset.photo_url" class="img-fluid rounded" style="max-height:200px;" alt="Picha ya mali" />
        </div>

        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.identity') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.assetTag') }}</small><div class="fw-semibold font-monospace">{{ detailAsset.asset_tag || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.name') }}</small><div class="fw-semibold">{{ detailAsset.name }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.category') }}</small><div>{{ categoryLabel(detailAsset.category) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.quantity') }}</small><div>{{ detailAsset.quantity ?? 1 }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.serialNo') }}</small><div>{{ detailAsset.serial_no || '—' }}</div></CCol>
        </CRow>

        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.purchase') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.cost') }}</small><div class="fw-semibold">{{ formatTZS(detailAsset.purchase_cost_cents) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.purchaseDate') }}</small><div>{{ detailAsset.purchase_date || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.supplier') }}</small><div>{{ detailAsset.supplier_name || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.invoiceNo') }}</small><div>{{ detailAsset.invoice_no || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.fundingSource') }}</small><div>{{ fundingLabel(detailAsset.funding_source) }}</div></CCol>
        </CRow>

        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.depreciation') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.depMethod') }}</small><div>{{ depMethodLabel(detailAsset.depreciation_method) }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.usefulLife') }}</small><div>{{ detailAsset.useful_life_years ? detailAsset.useful_life_years + ' ' + t('assets.years') : '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.depRate') }}</small><div>{{ detailAsset.depreciation_rate ? detailAsset.depreciation_rate + '%' : '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.salvageValue') }}</small><div>{{ formatTZS(detailAsset.salvage_value_cents) }}</div></CCol>
          <CCol xs="12" md="4"><small class="text-muted">{{ t('assets.currentValue') }}</small><div class="fw-bold fs-4" :class="valueColor(detailAsset)">{{ formatTZS(detailAsset.current_value_cents) }}</div></CCol>
          <CCol xs="6" md="4"><small class="text-muted">{{ t('assets.accumulatedDep') }}</small><div>{{ formatTZS(detailAsset.accumulated_depreciation_cents) }}</div></CCol>
        </CRow>

        <h6 class="text-primary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.condition') }}</h6>
        <CRow class="g-2 mb-3">
          <CCol xs="6" md="3">
            <small class="text-muted">{{ t('assets.custodian') }}</small>
            <div v-if="detailAsset.custodian_employee" class="fw-semibold">
              {{ detailAsset.custodian_employee.full_name }}
              <div class="text-muted small">{{ detailAsset.custodian_employee.department || detailAsset.custodian_employee.role }}</div>
            </div>
            <div v-else>{{ detailAsset.custodian || '—' }}</div>
          </CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.location') }}</small><div>{{ detailAsset.location || '—' }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.condition') }}</small><div><CBadge :color="conditionBadgeColor(detailAsset.condition)">{{ conditionLabel(detailAsset.condition) }}</CBadge></div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('common.status') }}</small><div><CBadge :color="statusBadgeColor(detailAsset.status)">{{ statusLabel(detailAsset.status) }}</CBadge></div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.warranty') }}</small><div>{{ detailAsset.warranty_expiry || '—' }}</div></CCol>
        </CRow>

        <template v-if="detailAsset.disposal_date || detailAsset.status === 'disposed'">
          <h6 class="text-danger fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.disposal') }}</h6>
          <CRow class="g-2 mb-3">
            <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.disposeModal.disposalDate') }}</small><div>{{ detailAsset.disposal_date || '—' }}</div></CCol>
            <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.disposeModal.disposalValue') }}</small><div>{{ formatTZS(detailAsset.disposal_value_cents) }}</div></CCol>
            <CCol xs="12" md="6"><small class="text-muted">{{ t('assets.disposeModal.disposalReason') }}</small><div>{{ detailAsset.disposal_reason || '—' }}</div></CCol>
          </CRow>
        </template>

        <h6 class="text-secondary fw-bold border-bottom pb-1 mb-2">{{ t('assets.detail.audit') }}</h6>
        <CRow class="g-2 mb-2">
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.registeredBy') }}</small><div>{{ typeof detailAsset.registered_by === 'object' ? (detailAsset.registered_by?.name || '—') : (detailAsset.registered_by || '—') }}</div></CCol>
          <CCol xs="6" md="3"><small class="text-muted">{{ t('assets.registeredAt') }}</small><div>{{ detailAsset.registered_at ? detailAsset.registered_at.slice(0,16).replace('T',' ') : '—' }}</div></CCol>
        </CRow>
        <template v-if="detailAsset.notes"><div class="mt-2"><small class="text-muted">{{ t('common.notes') }}</small><div class="border rounded p-2 bg-light small">{{ detailAsset.notes }}</div></div></template>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showDetail = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" @click="openEdit(detailAsset); showDetail = false" style="min-height:44px;"><CIcon icon="cilPencil" class="me-1" /> {{ t('common.edit') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Add / Edit Fixed Asset Modal -->
    <CModal :visible="showModal" @close="showModal = false" size="xl" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>{{ editAsset ? t('assets.edit') : t('assets.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="mb-3">{{ formError }}</CAlert>
        <CNav variant="tabs" class="mb-3">
          <CNavItem v-for="(tab, i) in formTabs" :key="i">
            <CNavLink :active="activeTab === i" @click="activeTab = i" style="cursor:pointer;">{{ tab }}</CNavLink>
          </CNavItem>
        </CNav>

        <!-- Tab 1: Identity -->
        <div v-show="activeTab === 0">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.assetTag') }} *</label>
              <div class="input-group">
                <CFormInput v-model="form.asset_tag" placeholder="mfano: MSG-AST-001" style="min-height:44px;" />
                <CButton color="outline-secondary" @click="autoFillTag" :disabled="loadingTag" style="min-height:44px;">
                  <CSpinner v-if="loadingTag" size="sm" /><span v-else>Auto</span>
                </CButton>
              </div>
            </CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.name') }} *</label><CFormInput v-model="form.name" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.category') }} *</label>
              <CFormSelect v-model="form.category" style="min-height:44px;">
                <option value="">{{ t('common.select') }}...</option>
                <option v-for="c in categoryOptions" :key="c.value" :value="c.value">{{ c.label }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('common.school') }} *</label>
              <CFormSelect v-model="form.school_id" style="min-height:44px;" @update:modelValue="form.asset_tag = ''">
                <option value="">{{ t('common.select') }}...</option>
                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.quantity') }}</label><CFormInput type="number" v-model.number="form.quantity" min="1" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.serialNo') }}</label><CFormInput v-model="form.serial_no" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.location') }}</label><CFormInput v-model="form.location" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.photo') }}</label>
              <input type="file" accept="image/*" class="form-control" style="min-height:44px;" @change="handlePhotoChange" />
              <div v-if="photoPreview" class="mt-2"><img :src="photoPreview" class="img-thumbnail" style="max-height:120px;" /></div>
              <div v-else-if="editAsset?.photo_url" class="mt-2"><img :src="editAsset.photo_url" class="img-thumbnail" style="max-height:120px;" /><div class="small text-muted">{{ t('assets.currentPhoto') }}</div></div>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 2: Purchase -->
        <div v-show="activeTab === 1">
          <CRow class="g-3">
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.cost') }} (TZS)</label><CFormInput type="number" v-model.number="form.purchase_cost" min="0" style="min-height:44px;" @input="updateDepPreview" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.purchaseDate') }}</label><CFormInput type="date" v-model="form.purchase_date" style="min-height:44px;" @change="updateDepPreview" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.supplier') }}</label><CFormInput v-model="form.supplier_name" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.invoiceNo') }}</label><CFormInput v-model="form.invoice_no" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.fundingSource') }}</label>
              <CFormSelect v-model="form.funding_source" style="min-height:44px;">
                <option value="">{{ t('common.select') }}...</option>
                <option value="fees">{{ t('assets.funding.fees') }}</option>
                <option value="donation">{{ t('assets.funding.donation') }}</option>
                <option value="grant">{{ t('assets.funding.grant') }}</option>
              </CFormSelect>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 3: Depreciation -->
        <div v-show="activeTab === 2">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.depMethod') }}</label>
              <CFormSelect v-model="form.depreciation_method" style="min-height:44px;" @update:modelValue="updateDepPreview">
                <option value="">{{ t('common.none') }}</option>
                <option value="straight_line">{{ t('assets.depMethods.straight_line') }}</option>
                <option value="reducing_balance">{{ t('assets.depMethods.reducing_balance') }}</option>
              </CFormSelect>
            </CCol>
            <CCol v-if="form.depreciation_method === 'straight_line'" xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.usefulLife') }}</label><CFormInput type="number" v-model.number="form.useful_life_years" min="1" max="100" style="min-height:44px;" @input="updateDepPreview" /></CCol>
            <CCol v-if="form.depreciation_method === 'reducing_balance'" xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.depRate') }}</label><CFormInput type="number" v-model.number="form.depreciation_rate" min="0.01" max="100" step="0.01" style="min-height:44px;" @input="updateDepPreview" /></CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.salvageValue') }} (TZS)</label><CFormInput type="number" v-model.number="form.salvage_value" min="0" style="min-height:44px;" @input="updateDepPreview" /></CCol>
            <CCol v-if="depPreview !== null" xs="12">
              <CAlert color="info" class="mb-0">
                <strong>{{ t('assets.currentValueEstimated') }}:</strong>
                <span class="fs-5 fw-bold ms-2">{{ formatTZS(depPreview) }}</span>
                <span class="text-muted small ms-2">{{ t('assets.calculatedFromInput') }}</span>
              </CAlert>
            </CCol>
          </CRow>
        </div>

        <!-- Tab 4: Condition -->
        <div v-show="activeTab === 3">
          <CRow class="g-3">
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.custodianEmployee') }}</label>
              <CFormSelect v-model="form.custodian_employee_id" style="min-height:44px;" @update:modelValue="val => { const emp = empStore.activeEmployees.find(e => e.id == val); if (emp) form.custodian = emp.full_name }">
                <option value="">— {{ t('assets.noCustodian') }} —</option>
                <option v-for="emp in empStore.activeEmployees" :key="emp.id" :value="emp.id">
                  {{ emp.full_name }}{{ emp.department ? ' (' + emp.department + ')' : '' }}
                </option>
              </CFormSelect>
              <div v-if="form.custodian_employee_id" class="mt-1 text-muted small">
                {{ empStore.activeEmployees.find(e => e.id == form.custodian_employee_id)?.department || '' }}
              </div>
            </CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.custodian') }} ({{ t('assets.custodianFreeText') }})</label><CFormInput v-model="form.custodian" style="min-height:44px;" /></CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('assets.condition') }}</label>
              <CFormSelect v-model="form.condition" style="min-height:44px;">
                <option value="excellent">{{ t('assets.conditions.excellent') }}</option>
                <option value="good">{{ t('assets.conditions.good') }}</option>
                <option value="fair">{{ t('assets.conditions.fair') }}</option>
                <option value="poor">{{ t('assets.conditions.poor') }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6">
              <label class="form-label fw-semibold">{{ t('common.status') }}</label>
              <CFormSelect v-model="form.status" style="min-height:44px;">
                <option value="in_use">{{ t('assets.statuses.in_use') }}</option>
                <option value="under_repair">{{ t('assets.statuses.under_repair') }}</option>
                <option value="disposed">{{ t('assets.statuses.disposed') }}</option>
                <option value="lost">{{ t('assets.statuses.lost') }}</option>
                <option value="written_off">{{ t('assets.statuses.written_off') }}</option>
              </CFormSelect>
            </CCol>
            <CCol xs="12" md="6"><label class="form-label fw-semibold">{{ t('assets.warranty') }}</label><CFormInput type="date" v-model="form.warranty_expiry" style="min-height:44px;" /></CCol>
            <CCol xs="12"><label class="form-label fw-semibold">{{ t('common.notes') }}</label><CFormTextarea v-model="form.notes" rows="3" /></CCol>
          </CRow>
        </div>
      </CModalBody>
      <CModalFooter class="flex-wrap gap-2">
        <CButton color="secondary" @click="showModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <div class="ms-auto d-flex gap-2">
          <CButton v-if="activeTab > 0" color="outline-secondary" @click="activeTab--" style="min-height:44px;">&larr; {{ t('common.back') }}</CButton>
          <CButton v-if="activeTab < formTabs.length - 1" color="primary" @click="activeTab++" style="min-height:44px;">{{ t('common.next') }} &rarr;</CButton>
          <CButton v-if="activeTab === formTabs.length - 1" color="success"
                   :disabled="saving || !form.name || !form.asset_tag || !form.category || !form.school_id"
                   @click="submitAsset" style="min-height:44px;">
            <CSpinner v-if="saving" size="sm" class="me-1" />
            {{ editAsset ? t('common.save') : t('assets.add') }}
          </CButton>
        </div>
      </CModalFooter>
    </CModal>

    <!-- Dispose Modal -->
    <CModal :visible="showDisposeModal" @close="showDisposeModal = false" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('assets.form.dispose') }}: {{ disposeTarget?.name }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="disposeError" color="danger">{{ disposeError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12"><label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalDate') }} *</label><CFormInput type="date" v-model="disposeForm.disposal_date" style="min-height:44px;" /></CCol>
          <CCol xs="12"><label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalValue') }} (TZS)</label><CFormInput type="number" v-model.number="disposeForm.disposal_value" min="0" style="min-height:44px;" /></CCol>
          <CCol xs="12"><label class="form-label fw-semibold">{{ t('assets.disposeModal.disposalReason') }} *</label><CFormTextarea v-model="disposeForm.disposal_reason" rows="3" :placeholder="t('assets.disposeModal.reasonPlaceholder')" /></CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDisposeModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="warning" :disabled="disposing || !disposeForm.disposal_date || !disposeForm.disposal_reason" @click="doDispose" style="min-height:44px;">
          <CSpinner v-if="disposing" size="sm" class="me-1" />{{ t('assets.form.confirmDisposal') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Consumable Confirm Modal -->
    <CModal :visible="showDeleteItemModal" @close="showDeleteItemModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('common.confirmDeleteMsg', { name: itemToDelete?.name }) }}
        <div class="text-danger small mt-1">{{ t('common.cannotUndo') }}</div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteItemModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="danger" :disabled="deletingItem" @click="doDeleteItem" style="min-height:44px;">
          <CSpinner v-if="deletingItem" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Fixed Asset Confirm -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('assets.confirmDeleteMsg', { name: toDelete?.name }) }}
        <div class="text-danger small mt-1">{{ t('common.cannotUndo') }}</div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>

<style scoped>
:deep(.table-responsive) { overflow: visible !important; }
:deep(.card) { overflow: visible !important; }
:deep(.dropdown-menu) { z-index: 1050 !important; }
</style>
