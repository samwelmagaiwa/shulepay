<template>
  <div>
    <div v-if="note" class="alert alert-success py-2">{{ note }}</div>
    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>

    <!-- Approval rules and tax rates -->
    <CCard class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('purchasing.settings.rules') }}</CCardHeader>
      <CCardBody>
        <div class="alert alert-info py-2 small">{{ t('purchasing.settings.ownerApproval') }}</div>
        <div v-if="!auth.isOwner" class="text-muted small mb-2">{{ t('purchasing.settings.ownerOnly') }}</div>
        <CRow class="g-3">
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('purchasing.settings.approvalLimit') }}</label>
            <CFormInput v-model="rules.limit" type="number" min="0" :disabled="!auth.isOwner" />
            <div class="form-text">{{ t('purchasing.settings.approvalLimitHelp') }}</div>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('purchasing.settings.whtThreshold') }}</label>
            <CFormInput v-model="rules.threshold" type="number" min="0" :disabled="!auth.isOwner" />
          </CCol>
          <CCol md="4"><label class="form-label">{{ t('purchasing.settings.vatRate') }}</label><CFormInput v-model="rules.vat" type="number" min="0" max="100" step="any" :disabled="!auth.isOwner" /></CCol>
          <CCol md="4"><label class="form-label">{{ t('purchasing.settings.whtGoods') }}</label><CFormInput v-model="rules.goods" type="number" min="0" max="100" step="any" :disabled="!auth.isOwner" /></CCol>
          <CCol md="4"><label class="form-label">{{ t('purchasing.settings.whtServices') }}</label><CFormInput v-model="rules.services" type="number" min="0" max="100" step="any" :disabled="!auth.isOwner" /></CCol>
        </CRow>
        <CButton v-if="auth.isOwner" color="primary" class="mt-3" :disabled="saving" @click="saveRules">{{ t('purchasing.common.save') }}</CButton>
      </CCardBody>
    </CCard>

    <!-- Who can do what -->
    <CCard class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('purchasing.access.title') }}</CCardHeader>
      <CCardBody>
        <p class="text-muted small mb-2">{{ t('purchasing.access.help') }}</p>
        <div v-if="!auth.isOwner" class="text-muted small mb-2">{{ t('purchasing.access.readOnly') }}</div>
        <CTable responsive class="sup-grid align-middle mb-2">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>{{ t('purchasing.access.action') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:120px">{{ t('purchasing.access.owner') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center" style="width:120px">{{ t('purchasing.access.accountant') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="a in accessActions" :key="a">
              <CTableDataCell>{{ t('purchasing.access.actions.' + a) }}</CTableDataCell>
              <CTableDataCell class="text-center"><CBadge color="success">{{ t('purchasing.access.always') }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center">
                <CFormSwitch v-model="accountantCan[a]" class="d-inline-block m-0" :disabled="!auth.isOwner" :aria-label="t('purchasing.access.actions.' + a)" />
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <CButton v-if="auth.isOwner" color="primary" :disabled="savingAccess" @click="saveAccess">{{ t('purchasing.access.save') }}</CButton>
      </CCardBody>
    </CCard>

    <!-- Categories -->
    <CCard class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('purchasing.settings.categories') }}</CCardHeader>
      <CCardBody>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <CBadge v-for="c in categories" :key="c.id" :color="c.is_active ? 'primary' : 'secondary'" class="p-2 fw-normal">
            {{ c.label }} <span class="opacity-75">· {{ t('purchasing.settings.inUse', { n: c.suppliers_count }) }}</span>
            <a href="#" class="text-white ms-2" @click.prevent="toggleCategory(c)">{{ c.is_active ? t('purchasing.settings.hide') : t('purchasing.settings.show') }}</a>
          </CBadge>
        </div>
        <div class="d-flex gap-2" style="max-width:420px;">
          <CFormInput v-model="newCategory" :placeholder="t('purchasing.settings.categoryName')" @keyup.enter="addCategory" />
          <CButton color="success" :disabled="!newCategory.trim()" @click="addCategory">{{ t('purchasing.common.add') }}</CButton>
        </div>
      </CCardBody>
    </CCard>

    <!-- Import -->
    <CCard class="mb-3">
      <CCardHeader class="fw-semibold">{{ t('purchasing.settings.importTitle') }}</CCardHeader>
      <CCardBody>
        <p class="text-muted small">{{ t('purchasing.settings.importHelp') }}</p>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <CButton size="sm" color="secondary" variant="outline" @click="downloadTemplate">{{ t('purchasing.settings.template') }}</CButton>
          <input type="file" class="form-control form-control-sm" style="max-width:280px;" accept=".csv,.xlsx,.xls" @change="onFile">
          <CButton size="sm" color="primary" variant="outline" :disabled="!file || importing" @click="runImport(true)">{{ t('purchasing.settings.check') }}</CButton>
          <CButton size="sm" color="success" :disabled="!file || importing || !checked" @click="runImport(false)">{{ t('purchasing.settings.importNow') }}</CButton>
        </div>
        <div v-if="importResult" class="mt-3">
          <div class="fw-semibold">
            {{ importResult.dry_run ? t('purchasing.settings.wouldCreate', { n: importResult.to_create }) : t('purchasing.settings.imported', { n: importResult.created }) }}
          </div>
          <CTable v-if="importResult.problems.length" small class="sup-grid mt-2">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell style="width:70px">{{ t('purchasing.settings.row') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.supplier.name') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.settings.problem') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody>
              <CTableRow v-for="p in importResult.problems" :key="p.row">
                <CTableDataCell>{{ p.row }}</CTableDataCell><CTableDataCell>{{ p.name }}</CTableDataCell><CTableDataCell class="text-danger">{{ p.error }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

    <!-- Link typed vendors -->
    <CCard>
      <CCardHeader class="fw-semibold">{{ t('purchasing.settings.vendorsTitle') }}</CCardHeader>
      <CCardBody>
        <p class="text-muted small">{{ t('purchasing.settings.vendorsHelp') }}</p>
        <CTable v-if="vendors.length" small responsive class="sup-grid align-middle">
          <CTableHead class="table-light"><CTableRow>
            <CTableHeaderCell>{{ t('purchasing.settings.vendorName') }}</CTableHeaderCell>
            <CTableHeaderCell>{{ t('purchasing.settings.usedOn') }}</CTableHeaderCell>
            <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
          </CTableRow></CTableHead>
          <CTableBody>
            <CTableRow v-for="v in vendors" :key="v.name">
              <CTableDataCell class="fw-semibold">{{ v.name }}</CTableDataCell>
              <CTableDataCell class="small">
                <span v-if="v.expenses">{{ v.expenses }} {{ t('purchasing.settings.expenses') }} </span>
                <span v-if="v.assets">{{ v.assets }} {{ t('purchasing.settings.assets') }} </span>
                <span v-if="v.items">{{ v.items }} {{ t('purchasing.settings.items') }}</span>
              </CTableDataCell>
              <CTableDataCell class="text-nowrap">
                <CButton v-if="v.match_supplier_id" size="sm" color="primary" variant="outline" class="me-1" @click="link(v, v.match_supplier_id)">{{ t('purchasing.settings.linkExisting') }}</CButton>
                <CButton size="sm" color="success" variant="outline" @click="link(v, null)">{{ t('purchasing.settings.createFrom') }}</CButton>
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <div v-else class="text-muted">{{ t('purchasing.settings.noVendors') }}</div>
      </CCardBody>
    </CCard>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, apiError, downloadFile, toCents, toTzs } from '@/services/purchasing'
import { useAuthStore } from '@/stores/auth'

const emit = defineEmits(['changed'])
const { t } = useI18n()
const auth = useAuthStore()

const note = ref('')
const error = ref('')
const saving = ref(false)
const savingAccess = ref(false)
// Every sensitive action the accountant's access can be switched for (the server enforces them).
const accessActions = ['bill_approve', 'payment_approve', 'payment_void', 'bill_cancel', 'grn_void', 'supplier_merge', 'supplier_deactivate', 'document_delete']
const accountantCan = reactive({})
const rules = reactive({ limit: '', threshold: '', vat: '', goods: '', services: '' })
const categories = ref([])
const newCategory = ref('')
const file = ref(null)
const checked = ref(false)
const importing = ref(false)
const importResult = ref(null)
const vendors = ref([])

async function loadAll() {
  try {
    const s = await purchasing.settings()
    Object.assign(rules, { limit: toTzs(s.payment_approval_limit_cents), threshold: toTzs(s.wht_threshold_cents), vat: s.vat_rate, goods: s.wht_goods_rate, services: s.wht_services_rate })
    Object.assign(accountantCan, s.accountant_can || {})
    categories.value = await purchasing.categories()
    vendors.value = await purchasing.vendors()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  }
}

async function saveRules() {
  saving.value = true
  error.value = ''
  note.value = ''
  try {
    await purchasing.saveSettings({
      payment_approval_limit_cents: toCents(rules.limit),
      wht_threshold_cents: toCents(rules.threshold),
      vat_rate: Number(rules.vat),
      wht_goods_rate: Number(rules.goods),
      wht_services_rate: Number(rules.services),
    })
    note.value = t('purchasing.settings.saved')
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function saveAccess() {
  savingAccess.value = true
  error.value = ''
  note.value = ''
  try {
    const saved = await purchasing.saveSettings({ accountant_can: { ...accountantCan } })
    Object.assign(accountantCan, saved.accountant_can || {})
    note.value = t('purchasing.access.saved')
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    savingAccess.value = false
  }
}

async function addCategory() {
  if (!newCategory.value.trim()) return
  try {
    await purchasing.addCategory(newCategory.value.trim())
    newCategory.value = ''
    categories.value = await purchasing.categories()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  }
}

async function toggleCategory(c) {
  try {
    await purchasing.updateCategory(c.id, { is_active: !c.is_active })
    categories.value = await purchasing.categories()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  }
}

async function downloadTemplate() {
  try { await downloadFile('/suppliers/import/template', 'suppliers-template.csv') } catch (e) { error.value = apiError(e, t('purchasing.common.loadFailed')) }
}

function onFile(ev) {
  file.value = ev.target.files[0] || null
  checked.value = false
  importResult.value = null
}

async function runImport(dryRun) {
  importing.value = true
  error.value = ''
  try {
    importResult.value = await purchasing.importSuppliers(file.value, dryRun)
    checked.value = dryRun && importResult.value.to_create > 0
    if (!dryRun) {
      checked.value = false
      emit('changed')
    }
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    importing.value = false
  }
}

async function link(v, supplierId) {
  error.value = ''
  note.value = ''
  try {
    const res = await purchasing.linkVendor(v.name, supplierId)
    note.value = t('purchasing.settings.linked', { n: res.linked })
    vendors.value = await purchasing.vendors()
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  }
}

onMounted(loadAll)
</script>
