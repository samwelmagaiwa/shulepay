<template>
  <CContainer fluid class="p-2 p-md-3">
    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-end mb-3">
      <CButton color="success" @click="openAdd" style="min-height:40px;">
        <CIcon icon="cilPlus" class="me-1" /> {{ t('suppliers.add') }}
      </CButton>
    </div>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!suppliers.length" class="text-center text-muted py-5">{{ t('suppliers.noSuppliers') }}</div>
        <div v-for="sup in suppliers" :key="sup.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-bold">{{ sup.name }}</div>
              <div class="small text-muted">{{ sup.contact_name }}</div>
              <div class="small text-muted">{{ sup.phone }}</div>
            </div>
            <div class="text-end">
              <div class="fw-bold" :class="(sup.balance_cents || 0) > 0 ? 'text-danger' : 'text-muted'">
                {{ formatMoney(sup.balance_cents) }}
              </div>
              <div class="small text-muted">{{ t('suppliers.debt') }}</div>
            </div>
          </div>
          <div class="d-flex gap-2 mt-2 flex-wrap">
            <CButton v-if="(sup.balance_cents || 0) > 0" size="sm" color="primary"
                     @click="openPayment(sup)" style="min-height:44px;">
              {{ t('suppliers.pay') }}
            </CButton>
            <CButton size="sm" color="secondary" variant="outline" @click="openEdit(sup)"
                     style="min-height:44px; min-width:44px;">
              <CIcon icon="cilPencil" />
            </CButton>
          </div>
        </div>
      </div>

      <!-- Pagination (mobile) -->
      <div v-if="meta.last_page > 1" class="d-md-none d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block">
        <CCardBody class="p-0">
          <CTable responsive hover class="mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('suppliers.name') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('suppliers.contactPerson') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('suppliers.phone') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('suppliers.email') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('suppliers.debt') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="sup in suppliers" :key="sup.id">
                <CTableDataCell>
                  <div class="fw-semibold">{{ sup.name }}</div>
                  <div class="small text-muted">{{ sup.address }}</div>
                </CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell">{{ sup.contact_name }}</CTableDataCell>
                <CTableDataCell>{{ sup.phone }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell">{{ sup.email || '—' }}</CTableDataCell>
                <CTableDataCell>
                  <span class="fw-bold" :class="(sup.balance_cents || 0) > 0 ? 'text-danger' : 'text-success'">
                    {{ formatMoney(sup.balance_cents) }}
                  </span>
                </CTableDataCell>
                <CTableDataCell>
                  <div class="d-flex gap-1">
                    <CButton v-if="(sup.balance_cents || 0) > 0" size="sm" color="primary"
                             @click="openPayment(sup)" style="min-height:36px;">
                      {{ t('suppliers.pay') }}
                    </CButton>
                    <CButton size="sm" color="secondary" variant="outline" @click="openEdit(sup)"
                             style="min-height:36px; min-width:36px;">
                      <CIcon icon="cilPencil" />
                    </CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!suppliers.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">{{ t('suppliers.noSuppliers') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Pagination (desktop) -->
      <div v-if="meta.last_page > 1" class="d-none d-md-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
        <small class="text-medium-emphasis">
          {{ t('common.showing', { from: (meta.current_page - 1) * meta.per_page + 1, to: Math.min(meta.current_page * meta.per_page, meta.total), total: meta.total }) }}
        </small>
        <CPagination aria-label="Page" size="sm">
          <CPaginationItem :disabled="meta.current_page <= 1" @click="page = meta.current_page - 1; load()">{{ t('common.first') }}</CPaginationItem>
          <CPaginationItem v-for="p in visiblePages" :key="p" :active="p === meta.current_page" @click="page = p; load()">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="page = meta.current_page + 1; load()">{{ t('common.last') }}</CPaginationItem>
        </CPagination>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <CModal :visible="showModal" @close="showModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader>
        <CModalTitle>{{ editSupplier ? t('suppliers.editTitle') : t('suppliers.addTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger">{{ formError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('suppliers.companyName') }} <span class="text-danger">*</span></label>
            <CFormInput
              v-model="form.name"
              :class="{ 'is-invalid': fieldErrors.name }"
              @blur="validateField('name')"
              style="min-height:44px;" />
            <div v-if="fieldErrors.name" class="invalid-feedback">{{ fieldErrors.name }}</div>
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('suppliers.contactPerson') }}</label>
            <CFormInput v-model="form.contact_name" style="min-height:44px;" />
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('suppliers.phone') }}</label>
            <CFormInput
              v-model="form.phone"
              placeholder="+255712345678"
              :class="{ 'is-invalid': fieldErrors.phone }"
              @blur="validateField('phone')"
              style="min-height:44px;" />
            <div v-if="fieldErrors.phone" class="invalid-feedback">{{ fieldErrors.phone }}</div>
            <div class="form-text text-muted">{{ t('suppliers.phoneHint') }}</div>
          </CCol>
          <CCol xs="12" md="6">
            <label class="form-label fw-semibold">{{ t('suppliers.email') }}</label>
            <CFormInput
              type="email"
              v-model="form.email"
              placeholder="email@example.com"
              :class="{ 'is-invalid': fieldErrors.email }"
              @blur="validateField('email')"
              style="min-height:44px;" />
            <div v-if="fieldErrors.email" class="invalid-feedback">{{ fieldErrors.email }}</div>
          </CCol>
          <CCol xs="12">
            <label class="form-label fw-semibold">{{ t('suppliers.address') }}</label>
            <CFormInput v-model="form.address" :maxlength="500" style="min-height:44px;" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="flex-wrap gap-2">
        <CButton color="secondary" @click="showModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" :disabled="saving || !form.name.trim()" @click="submitSupplier"
                 class="ms-auto" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editSupplier ? t('common.save') : t('suppliers.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Payment Modal -->
    <CModal :visible="showPayModal" @close="showPayModal = false" size="md" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('suppliers.payTitle') }} — {{ payTarget?.name }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="payError" color="danger">{{ payError }}</CAlert>
        <div class="mb-3 p-3 bg-light rounded border">
          <div class="text-muted small mb-1">{{ t('suppliers.currentDebt') }}</div>
          <div class="fw-bold fs-4 text-danger">{{ formatMoney(payTarget?.balance_cents) }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.amount') }} (TZS) <span class="text-danger">*</span></label>
          <CFormInput
            type="number"
            v-model.number="payForm.amount_tzs"
            min="1"
            :class="{ 'is-invalid': payErrors.amount_tzs }"
            @blur="validatePayField('amount_tzs')"
            style="min-height:44px;" />
          <div v-if="payErrors.amount_tzs" class="invalid-feedback">{{ payErrors.amount_tzs }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.paymentMethod') }} <span class="text-danger">*</span></label>
          <CFormSelect v-model="payForm.method" style="min-height:44px;">
            <option value="cash">{{ t('common.cash') }}</option>
            <option value="mpesa">M-Pesa</option>
            <option value="bank">{{ t('common.bank') }}</option>
            <option value="cheque">{{ t('common.cheque') }}</option>
          </CFormSelect>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('suppliers.reference') }}</label>
          <CFormInput v-model="payForm.reference" placeholder="LPO-001, CHQ-045..." style="min-height:44px;" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.date') }} <span class="text-danger">*</span></label>
          <CFormInput
            type="date"
            v-model="payForm.payment_date"
            :class="{ 'is-invalid': payErrors.payment_date }"
            @blur="validatePayField('payment_date')"
            style="min-height:44px;" />
          <div v-if="payErrors.payment_date" class="invalid-feedback">{{ payErrors.payment_date }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('common.notes') }}</label>
          <CFormTextarea v-model="payForm.notes" rows="2" />
        </div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showPayModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="primary" :disabled="paying || !payForm.amount_tzs || !payForm.payment_date" @click="submitPayment"
                 style="min-height:44px;">
          <CSpinner v-if="paying" size="sm" class="me-1" />{{ t('suppliers.pay') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { CPagination, CPaginationItem } from '@coreui/vue'
import { useSuppliersStore } from '@/stores/suppliers'

const { t } = useI18n()

const store     = useSuppliersStore()
const suppliers = ref([])
const loading   = ref(false)
const page      = ref(1)
const meta      = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })

const visiblePages = computed(() => {
  const total = meta.value.last_page
  const cur   = meta.value.current_page
  const delta = 2
  const start = Math.max(1, cur - delta)
  const end   = Math.min(total, cur + delta)
  return Array.from({ length: end - start + 1 }, (_, i) => start + i)
})

const showModal   = ref(false)
const showPayModal = ref(false)
const saving      = ref(false)
const paying      = ref(false)
const formError   = ref('')
const payError    = ref('')
const editSupplier = ref(null)
const payTarget    = ref(null)

const form = ref({ name: '', contact_name: '', phone: '', email: '', address: '' })
const fieldErrors = ref({ name: '', phone: '', email: '' })

const payForm = ref({
  amount_tzs: '',
  method: 'bank',
  reference: '',
  payment_date: new Date().toISOString().split('T')[0],
  notes: '',
})
const payErrors = ref({ amount_tzs: '', payment_date: '' })

function validateField(field) {
  fieldErrors.value[field] = ''
  if (field === 'name' && !form.value.name.trim()) {
    fieldErrors.value.name = t('suppliers.errors.nameRequired')
  }
  if (field === 'phone' && form.value.phone) {
    const phoneRx = /^\+?[0-9\s\-]{7,20}$/
    if (!phoneRx.test(form.value.phone.trim())) {
      fieldErrors.value.phone = t('suppliers.errors.phoneInvalid')
    }
  }
  if (field === 'email' && form.value.email) {
    const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRx.test(form.value.email.trim())) {
      fieldErrors.value.email = t('suppliers.errors.emailInvalid')
    }
  }
}

function validatePayField(field) {
  payErrors.value[field] = ''
  if (field === 'amount_tzs' && (!payForm.value.amount_tzs || payForm.value.amount_tzs < 1)) {
    payErrors.value.amount_tzs = t('suppliers.errors.amountRequired')
  }
  if (field === 'payment_date' && !payForm.value.payment_date) {
    payErrors.value.payment_date = t('suppliers.errors.dateRequired')
  }
}

function validateSupplierForm() {
  ['name', 'phone', 'email'].forEach(f => validateField(f))
  return !Object.values(fieldErrors.value).some(Boolean)
}

function validatePayForm() {
  ['amount_tzs', 'payment_date'].forEach(f => validatePayField(f))
  return !Object.values(payErrors.value).some(Boolean)
}

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function load() {
  loading.value = true
  try {
    await store.fetchSuppliers({ page: page.value })
    suppliers.value = store.suppliers
    meta.value = store.pagination || meta.value
  } catch {} finally { loading.value = false }
}

function openAdd() {
  editSupplier.value = null
  formError.value    = ''
  fieldErrors.value  = { name: '', phone: '', email: '' }
  form.value = { name: '', contact_name: '', phone: '', email: '', address: '' }
  showModal.value    = true
}

function openEdit(sup) {
  editSupplier.value = sup
  formError.value    = ''
  fieldErrors.value  = { name: '', phone: '', email: '' }
  form.value = { name: sup.name, contact_name: sup.contact_name || '', phone: sup.phone || '',
    email: sup.email || '', address: sup.address || '' }
  showModal.value = true
}

function openPayment(sup) {
  payTarget.value = sup
  payError.value  = ''
  payErrors.value = { amount_tzs: '', payment_date: '' }
  payForm.value = {
    amount_tzs:   Math.round((sup.balance_cents || 0) / 100),
    method:       'bank',
    reference:    '',
    payment_date: new Date().toISOString().split('T')[0],
    notes:        '',
  }
  showPayModal.value = true
}

async function submitSupplier() {
  if (!validateSupplierForm()) return
  formError.value = ''
  saving.value    = true
  try {
    if (editSupplier.value) {
      await store.updateSupplier(editSupplier.value.id, form.value)
    } else {
      await store.createSupplier(form.value)
    }
    showModal.value = false
    await load()
  } catch (e) {
    const errors = e?.response?.data?.errors
    if (errors) {
      if (errors.name)  fieldErrors.value.name  = errors.name[0]
      if (errors.phone) fieldErrors.value.phone = errors.phone[0]
      if (errors.email) fieldErrors.value.email = errors.email[0]
      formError.value = t('common.fixErrors')
    } else {
      formError.value = e?.response?.data?.message || t('common.saveFailed')
    }
  } finally {
    saving.value = false
  }
}

async function submitPayment() {
  if (!validatePayForm()) return
  payError.value = ''
  paying.value   = true
  try {
    await store.createPayment({
      supplier_id:  payTarget.value.id,
      amount_cents: Math.round(payForm.value.amount_tzs * 100),
      method:       payForm.value.method,
      reference:    payForm.value.reference || undefined,
      payment_date: payForm.value.payment_date,
      notes:        payForm.value.notes || undefined,
    })
    showPayModal.value = false
    await load()
  } catch (e) {
    const errors = e?.response?.data?.errors
    if (errors?.amount_cents) payErrors.value.amount_tzs   = errors.amount_cents[0]
    if (errors?.payment_date) payErrors.value.payment_date = errors.payment_date[0]
    payError.value = e?.response?.data?.message || t('common.saveFailed')
  } finally {
    paying.value = false
  }
}

onMounted(() => { try { load() } catch {} })
</script>
