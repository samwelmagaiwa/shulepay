<template>
  <CModal :visible="visible" size="lg" scrollable backdrop="static" class="modal-fullscreen-sm-down" @close="close">
    <CModalHeader><CModalTitle>{{ t('purchasing.payment.add') }}</CModalTitle></CModalHeader>
    <CModalBody>
      <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>

      <CRow class="g-3">
        <CCol md="6">
          <label class="form-label fw-semibold">{{ t('purchasing.payment.supplier') }} *</label>
          <CFormSelect v-model="form.supplier_id" :invalid="!!errs.supplier_id">
            <option value="">—</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
          <div v-if="errs.supplier_id" class="text-danger small">{{ errs.supplier_id }}</div>
        </CCol>
        <CCol md="6" class="d-flex align-items-end">
          <div v-if="supplier"><span class="text-muted small">{{ t('purchasing.payment.owed') }}</span>
            <div class="fw-bold fs-5" :class="supplier.balance_cents > 0 ? 'text-danger' : 'text-success'">{{ formatMoney(supplier.balance_cents) }}</div></div>
        </CCol>

        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.payment.amount') }} *</label>
          <CFormInput v-model="form.amount" type="number" min="1" step="any" :invalid="!!errs.amount" />
          <div v-if="errs.amount" class="text-danger small">{{ errs.amount }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.payment.method') }} *</label>
          <CFormSelect v-model="form.method">
            <option v-for="m in methods" :key="m" :value="m">{{ t('purchasing.methods.' + m) }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.payment.date') }} *</label>
          <CFormInput v-model="form.payment_date" type="date" :max="todayStr" :invalid="!!errs.payment_date" />
        </CCol>
        <CCol md="6"><label class="form-label">{{ t('purchasing.payment.reference') }}</label><CFormInput v-model="form.reference" /></CCol>
        <CCol md="6"><label class="form-label">{{ t('purchasing.payment.efd') }}</label><CFormInput v-model="form.efd_number" /></CCol>

        <!-- Withholding tax -->
        <CCol md="6">
          <label class="form-label">{{ t('purchasing.payment.wht') }}</label>
          <CFormInput v-model="form.wht" type="number" min="0" step="any" :invalid="!!errs.wht_cents" />
          <div class="form-text">{{ t('purchasing.payment.whtHelp') }}</div>
          <div v-if="errs.wht_cents" class="text-danger small">{{ errs.wht_cents }}</div>
        </CCol>
        <CCol md="6" class="d-flex flex-column justify-content-end pb-4">
          <div v-if="suggested > 0" class="small">
            {{ t('purchasing.payment.whtSuggest', { amount: formatMoney(suggested) }) }}
            <CButton size="sm" color="link" class="p-0 ms-1" @click="form.wht = toTzs(suggested)">{{ t('purchasing.payment.useSuggestion') }}</CButton>
          </div>
          <div class="fw-semibold">{{ t('purchasing.payment.netPaid') }}: {{ formatMoney(Math.max(0, toCents(form.amount) - toCents(form.wht))) }}</div>
        </CCol>

        <!-- Which bills it settles -->
        <CCol md="12" v-if="supplier">
          <div class="fw-semibold">{{ t('purchasing.payment.allocate') }}</div>
          <div class="small text-muted mb-1">{{ openBills.length ? t('purchasing.payment.allocateAuto') : t('purchasing.payment.noOpenBills') }}</div>
          <div v-if="errs.allocations" class="text-danger small">{{ errs.allocations }}</div>
          <table v-if="openBills.length" class="sup-grid table table-sm align-middle">
            <thead class="table-light"><tr>
              <th>{{ t('purchasing.bill.number') }}</th><th>{{ t('purchasing.bill.dueDate') }}</th>
              <th class="text-end">{{ t('purchasing.bill.balance') }}</th><th style="width:150px">{{ t('purchasing.payment.allocateAmount') }}</th>
            </tr></thead>
            <tbody>
              <tr v-for="b in openBills" :key="b.id">
                <td>{{ b.bill_number }}</td>
                <td :class="late(b) ? 'text-danger' : ''">{{ b.due_date ? String(b.due_date).slice(0, 10) : '—' }}</td>
                <td class="text-end">{{ formatMoney(b.total_cents - b.paid_cents) }}</td>
                <td><CFormInput v-model="alloc[b.id]" type="number" min="0" step="any" size="sm" /></td>
              </tr>
            </tbody>
          </table>
          <div v-if="openBills.length" class="small text-muted">{{ t('purchasing.payment.advance') }}</div>
        </CCol>

        <CCol md="12"><label class="form-label">{{ t('purchasing.common.notes') }}</label><CFormTextarea v-model="form.notes" rows="2" /></CCol>
      </CRow>

      <div v-if="needsApproval" class="alert alert-warning mt-3 mb-0 py-2 small">
        {{ t('purchasing.payment.needsApproval', { limit: formatMoney(limitCents) }) }}
      </div>
    </CModalBody>
    <CModalFooter class="gap-2">
      <CButton color="secondary" @click="close">{{ t('purchasing.common.cancel') }}</CButton>
      <CButton color="primary" :disabled="saving" @click="save">
        <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('purchasing.common.save') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, toCents, toTzs, today, access } from '@/services/purchasing'

const props = defineProps({
  visible: Boolean,
  suppliers: { type: Array, default: () => [] },
  presetSupplierId: { type: [Number, String], default: '' },
})
const emit = defineEmits(['update:visible', 'saved'])
const { t } = useI18n()

const methods = ['cash', 'bank', 'mpesa', 'tigo_pesa', 'airtel_money', 'halopesa', 'cheque', 'other']
const todayStr = today()
const form = reactive({ supplier_id: '', amount: '', method: 'bank', payment_date: todayStr, reference: '', efd_number: '', wht: '', notes: '' })
const alloc = reactive({})
const errs = reactive({})
const formError = ref('')
const saving = ref(false)
const openBills = ref([])
const suggested = ref(0)
const limitCents = ref(0)

const supplier = computed(() => props.suppliers.find(s => s.id === Number(form.supplier_id)))
const needsApproval = computed(() => !access.can.payment_approve && toCents(form.amount) > limitCents.value)
const late = (b) => b.due_date && String(b.due_date).slice(0, 10) < todayStr

watch(() => props.visible, async (open) => {
  if (!open) return
  Object.keys(errs).forEach(k => delete errs[k])
  Object.keys(alloc).forEach(k => delete alloc[k])
  formError.value = ''
  const before = form.supplier_id
  Object.assign(form, { supplier_id: props.presetSupplierId ? String(props.presetSupplierId) : '', amount: '', method: 'bank', payment_date: todayStr, reference: '', efd_number: '', wht: '', notes: '' })
  openBills.value = []
  suggested.value = 0
  try { limitCents.value = Number((await purchasing.settings()).payment_approval_limit_cents) } catch { limitCents.value = 0 }
  // A changed supplier is picked up by the watcher below; the same one as last time needs a nudge.
  if (form.supplier_id && form.supplier_id === before) await onSupplier()
})

watch(() => form.supplier_id, () => onSupplier())
watch(() => form.amount, () => onAmount())

async function onSupplier() {
  Object.keys(alloc).forEach(k => delete alloc[k])
  openBills.value = []
  suggested.value = 0
  const s = supplier.value
  if (!s) return
  if (s.preferred_method) form.method = s.preferred_method
  if (!form.amount && s.balance_cents > 0) form.amount = toTzs(s.balance_cents)
  try {
    openBills.value = (await purchasing.bills({ supplier_id: s.id, open: 1, per_page: 100 })).data
      .sort((a, b) => String(a.due_date || a.bill_date).localeCompare(String(b.due_date || b.bill_date)))
  } catch { /* payment can still be saved without the list */ }
  await onAmount()
}

let timer = null
function onAmount() {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    const cents = toCents(form.amount)
    if (!supplier.value || cents < 1) { suggested.value = 0; return }
    try { suggested.value = (await purchasing.whtSuggestion(supplier.value.id, cents)).wht_cents } catch { suggested.value = 0 }
  }, 250)
}

function validate() {
  Object.keys(errs).forEach(k => delete errs[k])
  const need = t('purchasing.common.fixErrors')
  if (!form.supplier_id) errs.supplier_id = need
  if (!(toCents(form.amount) > 0)) errs.amount = need
  if (!form.payment_date) errs.payment_date = need
  if (toCents(form.wht) > toCents(form.amount)) errs.wht_cents = need
  return !Object.keys(errs).length
}

async function save() {
  if (!validate()) return
  saving.value = true
  formError.value = ''
  const allocations = openBills.value
    .filter(b => Number(alloc[b.id]) > 0)
    .map(b => ({ supplier_bill_id: b.id, amount_cents: toCents(alloc[b.id]) }))
  try {
    const payment = await purchasing.createPayment({
      supplier_id: Number(form.supplier_id),
      amount_cents: toCents(form.amount),
      wht_cents: toCents(form.wht),
      method: form.method,
      payment_date: form.payment_date,
      reference: form.reference || null,
      efd_number: form.efd_number || null,
      notes: form.notes || null,
      allocations: allocations.length ? allocations : undefined,
    })
    emit('saved', payment)
    close()
  } catch (e) {
    if (e?.response?.data?.errors) {
      for (const [k, v] of Object.entries(e.response.data.errors)) errs[k.split('.')[0]] = v[0]
    }
    formError.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

function close() { emit('update:visible', false) }
</script>
