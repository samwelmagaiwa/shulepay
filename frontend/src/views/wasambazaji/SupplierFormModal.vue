<template>
  <CModal :visible="visible" size="lg" scrollable backdrop="static" class="modal-fullscreen-sm-down" @close="close">
    <CModalHeader>
      <CModalTitle>{{ supplier ? t('purchasing.supplier.edit') : t('purchasing.supplier.add') }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

      <div v-if="duplicates.length" class="alert alert-warning">
        <div class="fw-semibold">{{ t('purchasing.supplier.duplicate.title') }}</div>
        <div class="small">{{ t('purchasing.supplier.duplicate.body') }}</div>
        <ul class="mb-0 small"><li v-for="d in duplicates" :key="d.id">{{ d.name }} ({{ d.code }})<span v-if="d.phone"> · {{ d.phone }}</span></li></ul>
      </div>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.supplier.sections.identity') }}</h6>
      <CRow class="g-3 mb-3">
        <CCol md="8">
          <FieldLabel :text="t('purchasing.supplier.name')" required />
          <CFormInput v-model="form.name" :invalid="!!errs.name" />
          <div v-if="errs.name" class="text-danger small">{{ errs.name }}</div>
        </CCol>
        <CCol md="4">
          <FieldLabel :text="t('purchasing.supplier.category')" required />
          <CFormSelect v-model="form.supplier_category_id" :invalid="!!errs.supplier_category_id">
            <option value="">{{ t('purchasing.supplier.choose') }}</option>
            <option v-for="c in categories.filter(c => c.is_active)" :key="c.id" :value="String(c.id)">{{ c.label }}</option>
          </CFormSelect>
          <div v-if="errs.supplier_category_id" class="text-danger small">{{ errs.supplier_category_id }}</div>
        </CCol>
      </CRow>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.supplier.sections.contact') }}</h6>
      <CRow class="g-3 mb-3">
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.contactName')" />
          <CFormInput v-model="form.contact_name" :invalid="!!errs.contact_name" />
          <div v-if="errs.contact_name" class="text-danger small">{{ errs.contact_name }}</div>
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.phone')" required />
          <CFormInput v-model="form.phone" :invalid="!!errs.phone" placeholder="+255712345678" />
          <div v-if="errs.phone" class="text-danger small">{{ errs.phone }}</div>
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.email')" />
          <CFormInput v-model="form.email" type="email" :invalid="!!errs.email" />
          <div v-if="errs.email" class="text-danger small">{{ errs.email }}</div>
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.address')" />
          <CFormInput v-model="form.address" />
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.region')" />
          <CFormSelect v-if="regions.length" v-model="form.region" @change="onRegion">
            <option value="">{{ t('purchasing.supplier.chooseRegion') }}</option>
            <option v-for="r in regions" :key="r.id" :value="r.name">{{ r.name }}</option>
          </CFormSelect>
          <CFormInput v-else v-model="form.region" />
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.district')" />
          <CFormSelect v-if="regions.length" v-model="form.district" :disabled="!districts.length">
            <option value="">{{ t('purchasing.supplier.chooseDistrict') }}</option>
            <option v-for="d in districts" :key="d.id" :value="d.name">{{ d.name }}</option>
          </CFormSelect>
          <CFormInput v-else v-model="form.district" />
        </CCol>
      </CRow>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.supplier.sections.tax') }}</h6>
      <CRow class="g-3 mb-3">
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.tin')" :required="taxed" />
          <CFormInput v-model="form.tin" :invalid="!!errs.tin" :placeholder="t('purchasing.supplier.tinHint')" />
          <div v-if="errs.tin" class="text-danger small">{{ errs.tin }}</div>
        </CCol>
        <CCol md="6" class="d-flex flex-column justify-content-end">
          <CFormCheck v-model="form.vat_registered" :label="t('purchasing.supplier.vatRegistered')" />
          <CFormCheck v-model="form.wht_applicable" :label="t('purchasing.supplier.whtApplicable')" />
        </CCol>
        <CCol v-if="form.vat_registered" md="6">
          <FieldLabel :text="t('purchasing.supplier.vrn')" required />
          <CFormInput v-model="form.vrn" :invalid="!!errs.vrn" :placeholder="t('purchasing.supplier.vrnHint')" />
          <div v-if="errs.vrn" class="text-danger small">{{ errs.vrn }}</div>
        </CCol>
        <CCol v-if="form.wht_applicable" md="6">
          <FieldLabel :text="t('purchasing.supplier.whtType')" required />
          <CFormSelect v-model="form.wht_type" :invalid="!!errs.wht_type">
            <option value="">{{ t('purchasing.supplier.choose') }}</option>
            <option value="goods">{{ t('purchasing.supplier.whtGoods') }}</option>
            <option value="services">{{ t('purchasing.supplier.whtServices') }}</option>
          </CFormSelect>
          <div v-if="errs.wht_type" class="text-danger small">{{ errs.wht_type }}</div>
        </CCol>
      </CRow>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.supplier.sections.payment') }}</h6>
      <CRow class="g-3 mb-3">
        <CCol md="12">
          <FieldLabel :text="t('purchasing.supplier.preferredMethod')" />
          <CFormSelect v-model="form.preferred_method">
            <option value="">{{ t('purchasing.supplier.choose') }}</option>
            <option v-for="m in methods" :key="m" :value="m">{{ t('purchasing.methods.' + m) }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="4">
          <FieldLabel :text="t('purchasing.supplier.bankName')" :required="bankNeeded" />
          <CFormInput v-model="form.bank_name" :invalid="!!errs.bank_name" />
          <div v-if="errs.bank_name" class="text-danger small">{{ errs.bank_name }}</div>
        </CCol>
        <CCol md="4">
          <FieldLabel :text="t('purchasing.supplier.bankAccountName')" />
          <CFormInput v-model="form.bank_account_name" />
        </CCol>
        <CCol md="4">
          <FieldLabel :text="t('purchasing.supplier.bankAccountNumber')" :required="bankNeeded" />
          <CFormInput v-model="form.bank_account_number" :invalid="!!errs.bank_account_number" inputmode="numeric" />
          <div v-if="errs.bank_account_number" class="text-danger small">{{ errs.bank_account_number }}</div>
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.mobileProvider')" :required="mobileNeeded" />
          <CFormSelect v-model="form.mobile_money_provider" :invalid="!!errs.mobile_money_provider">
            <option value="">{{ t('purchasing.supplier.choose') }}</option>
            <option v-for="m in ['mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'other']" :key="m" :value="m">{{ t('purchasing.methods.' + m) }}</option>
          </CFormSelect>
          <div v-if="errs.mobile_money_provider" class="text-danger small">{{ errs.mobile_money_provider }}</div>
        </CCol>
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.mobileNumber')" :required="mobileNeeded" />
          <CFormInput v-model="form.mobile_money_number" :invalid="!!errs.mobile_money_number" placeholder="0712345678" />
          <div v-if="errs.mobile_money_number" class="text-danger small">{{ errs.mobile_money_number }}</div>
        </CCol>
      </CRow>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.supplier.sections.terms') }}</h6>
      <CRow class="g-3">
        <CCol md="6">
          <FieldLabel :text="t('purchasing.supplier.paymentTermsDays')" />
          <CFormInput v-model="form.payment_terms_days" type="number" min="0" max="365" :invalid="!!errs.payment_terms_days" />
          <div v-if="errs.payment_terms_days" class="text-danger small">{{ errs.payment_terms_days }}</div>
        </CCol>
        <CCol v-if="!supplier" md="6">
          <FieldLabel :text="t('purchasing.supplier.openingBalance')" />
          <CFormInput v-model="form.opening_balance" type="number" min="0" />
          <div class="form-text">{{ t('purchasing.supplier.openingBalanceHelp') }}</div>
        </CCol>
        <CCol md="12">
          <FieldLabel :text="t('purchasing.supplier.notes')" />
          <CFormTextarea v-model="form.notes" rows="2" />
        </CCol>
      </CRow>
    </CModalBody>
    <CModalFooter class="gap-2">
      <CButton color="secondary" @click="close">{{ t('purchasing.common.cancel') }}</CButton>
      <CButton v-if="duplicates.length" color="warning" :disabled="saving" @click="save(true)">{{ t('purchasing.supplier.duplicate.saveAnyway') }}</CButton>
      <CButton v-else color="primary" :disabled="saving" @click="save(false)">
        <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('purchasing.common.save') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, apiError, toCents } from '@/services/purchasing'
import FieldLabel from './FieldLabel.vue'

const props = defineProps({
  visible: Boolean,
  supplier: { type: Object, default: null },
  categories: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:visible', 'saved'])
const { t } = useI18n()

const methods = ['cash', 'bank', 'mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'cheque', 'other']
const mobileMethods = ['mpesa', 'tigo_pesa', 'airtel_money', 'halopesa']

// Only what a school really needs to know about a supplier. Everything except name, category and phone is optional.
const blank = () => ({
  name: '', supplier_category_id: '', contact_name: '', phone: '', email: '', address: '', region: '', district: '',
  tin: '', vat_registered: false, vrn: '', wht_applicable: false, wht_type: '',
  preferred_method: '', bank_name: '', bank_account_name: '', bank_account_number: '', mobile_money_provider: '', mobile_money_number: '',
  payment_terms_days: '', opening_balance: '', notes: '',
})

const form = reactive(blank())
const errs = reactive({})
const formError = ref('')
const duplicates = ref([])
const saving = ref(false)
const regions = ref([])
const districts = ref([])

const taxed = computed(() => form.vat_registered || form.wht_applicable)
const bankNeeded = computed(() => form.preferred_method === 'bank' || !!form.bank_name.trim() || !!form.bank_account_number.trim())
const mobileNeeded = computed(() => mobileMethods.includes(form.preferred_method) || !!form.mobile_money_provider || !!form.mobile_money_number.trim())

async function loadDistricts(regionName) {
  districts.value = []
  const r = regions.value.find(x => x.name === regionName)
  if (!r) return
  try { districts.value = await purchasing.districts(r.id) } catch { /* the field stays empty */ }
}
function onRegion() { form.district = ''; loadDistricts(form.region) }

onMounted(async () => {
  try { regions.value = await purchasing.regions() } catch { regions.value = [] } // without the list, region/district are typed
})

watch(() => props.visible, async (open) => {
  if (!open) return
  Object.assign(form, blank())
  Object.keys(errs).forEach(k => delete errs[k])
  formError.value = ''
  duplicates.value = []
  districts.value = []
  const s = props.supplier
  if (s) {
    for (const k of Object.keys(form)) {
      if (s[k] !== undefined && s[k] !== null) form[k] = s[k]
    }
    form.supplier_category_id = s.supplier_category_id ? String(s.supplier_category_id) : ''
    form.vat_registered = !!s.vat_registered
    form.wht_applicable = !!s.wht_applicable
    form.wht_type = s.wht_type || ''
    form.payment_terms_days = s.payment_terms_days || ''
    if (form.region) await loadDistricts(form.region)
  }
})

// The same rules the server applies, so mistakes show before saving.
function validate() {
  Object.keys(errs).forEach(k => delete errs[k])
  const e = (k) => t('purchasing.supplier.errors.' + k)
  const phoneRx = /^\+?[0-9\s-]{9,20}$/
  const f = (k) => String(form[k] ?? '').trim()

  if (f('name').length < 2) errs.name = e('nameRequired')
  if (!form.supplier_category_id) errs.supplier_category_id = e('categoryRequired')
  if (!f('phone')) errs.phone = e('phoneRequired')
  else if (!phoneRx.test(f('phone'))) errs.phone = e('phoneInvalid')
  if (f('contact_name') && !/\p{L}/u.test(f('contact_name'))) errs.contact_name = e('contactNameLetters')
  if (f('email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f('email'))) errs.email = e('emailInvalid')

  if (!f('tin')) { if (taxed.value) errs.tin = e('tinRequired') } else if (!/^\d{3}-?\d{3}-?\d{3}$/.test(f('tin'))) errs.tin = e('tinInvalid')
  if (form.vat_registered) {
    if (!f('vrn')) errs.vrn = e('vrnRequired')
    else if (!/^\d{2}-?\d{6}-?[A-Za-z]$/.test(f('vrn'))) errs.vrn = e('vrnInvalid')
  }
  if (form.wht_applicable && !form.wht_type) errs.wht_type = e('whtTypeRequired')

  if (bankNeeded.value) {
    if (!f('bank_name')) errs.bank_name = e('bankNameRequired')
    if (!f('bank_account_number')) errs.bank_account_number = e('bankNumberRequired')
    else if (!/^[0-9\s-]{6,40}$/.test(f('bank_account_number'))) errs.bank_account_number = e('bankNumberInvalid')
  }
  if (mobileNeeded.value) {
    if (!form.mobile_money_provider) errs.mobile_money_provider = e('mobileProviderRequired')
    if (!f('mobile_money_number')) errs.mobile_money_number = e('mobileNumberRequired')
    else if (!phoneRx.test(f('mobile_money_number'))) errs.mobile_money_number = e('phoneInvalid')
  }
  if (f('payment_terms_days') !== '' && !(Number.isInteger(Number(form.payment_terms_days)) && Number(form.payment_terms_days) >= 0 && Number(form.payment_terms_days) <= 365)) {
    errs.payment_terms_days = e('termsInvalid')
  }
  return !Object.keys(errs).length
}

function payload(confirmDuplicate) {
  const body = {}
  for (const [k, v] of Object.entries(form)) {
    if (k === 'opening_balance') continue
    body[k] = typeof v === 'string' ? (v.trim() === '' ? null : v.trim()) : v
  }
  body.vat_registered = !!form.vat_registered
  body.wht_applicable = !!form.wht_applicable
  body.vrn = form.vat_registered ? body.vrn : null
  body.wht_type = form.wht_applicable ? body.wht_type : null
  if (!props.supplier) body.opening_balance_cents = String(form.opening_balance) === '' ? 0 : toCents(form.opening_balance)
  if (confirmDuplicate) body.confirm_duplicate = true

  return body
}

async function save(confirmDuplicate) {
  if (!validate()) {
    formError.value = t('purchasing.common.fixErrors')
    return
  }
  formError.value = ''
  saving.value = true
  try {
    const body = payload(confirmDuplicate)
    const saved = props.supplier ? await purchasing.updateSupplier(props.supplier.id, body) : await purchasing.createSupplier(body)
    emit('saved', saved)
    close()
  } catch (e) {
    if (e?.response?.status === 409) {
      duplicates.value = e.response.data.duplicates || []
    } else if (e?.response?.data?.errors) {
      for (const [k, v] of Object.entries(e.response.data.errors)) errs[k] = v[0]
      formError.value = t('purchasing.common.fixErrors')
    } else {
      formError.value = apiError(e, t('purchasing.common.saveFailed'))
    }
  } finally {
    saving.value = false
  }
}

function close() { emit('update:visible', false) }
</script>
