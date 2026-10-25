<template>
  <CModal :visible="visible" size="xl" scrollable backdrop="static" class="modal-fullscreen-sm-down" @close="close">
    <CModalHeader><CModalTitle>{{ bill ? t('purchasing.bill.edit') : t('purchasing.bill.add') }}</CModalTitle></CModalHeader>
    <CModalBody>
      <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
      <div class="alert alert-info py-2 small mb-3">{{ t('purchasing.bill.approveHelp') }}</div>

      <CRow class="g-3 mb-3">
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.supplier') }} *</label>
          <CFormSelect v-model="form.supplier_id" :disabled="!!bill" :invalid="!!errs.supplier_id">
            <option value="">—</option>
            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
          <div v-if="errs.supplier_id" class="text-danger small">{{ errs.supplier_id }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.number') }} *</label>
          <CFormInput v-model="form.bill_number" :invalid="!!errs.bill_number" />
          <div v-if="errs.bill_number" class="text-danger small">{{ errs.bill_number }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.expenseCategory') }} *</label>
          <CFormSelect v-model="form.expense_category_id" :invalid="!!errs.expense_category_id">
            <option value="">—</option>
            <option v-for="c in expenseCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </CFormSelect>
          <div v-if="errs.expense_category_id" class="text-danger small">{{ errs.expense_category_id }}</div>
          <div v-if="!expenseCategories.length" class="form-text text-warning">{{ t('purchasing.bill.noCategories') }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.billDate') }} *</label>
          <CFormInput v-model="form.bill_date" type="date" :max="todayStr" :invalid="!!errs.bill_date" />
          <div v-if="errs.bill_date" class="text-danger small">{{ errs.bill_date }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.dueDate') }}</label>
          <CFormInput v-model="form.due_date" type="date" :invalid="!!errs.due_date" />
          <div class="form-text">{{ t('purchasing.bill.dueHelp') }}</div>
        </CCol>
        <CCol md="4">
          <label class="form-label fw-semibold">{{ t('purchasing.bill.attachment') }}</label>
          <input type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @change="file = $event.target.files[0] || null">
        </CCol>
      </CRow>

      <h6 class="text-uppercase text-muted small">{{ t('purchasing.bill.items') }}</h6>
      <div v-if="errs.items" class="text-danger small mb-1">{{ errs.items }}</div>
      <div class="table-responsive">
        <table class="sup-grid table table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th style="min-width:200px">{{ t('purchasing.bill.description') }}</th>
              <th style="width:90px">{{ t('purchasing.bill.quantity') }}</th>
              <th style="width:110px">{{ t('purchasing.bill.unit') }}</th>
              <th style="width:150px">{{ t('purchasing.bill.unitPrice') }}</th>
              <th class="text-end" style="width:130px">{{ t('purchasing.bill.lineTotal') }}</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(l, i) in form.items" :key="i">
              <td><CFormInput v-model="l.description" size="sm" /></td>
              <td><CFormInput v-model="l.quantity" type="number" min="0.01" step="any" size="sm" /></td>
              <td><CFormInput v-model="l.unit" size="sm" /></td>
              <td><CFormInput v-model="l.unit_price" type="number" min="0" step="any" size="sm" /></td>
              <td class="text-end">{{ formatMoney(lineCents(l)) }}</td>
              <td><CButton size="sm" color="danger" variant="ghost" :disabled="form.items.length === 1" :aria-label="t('purchasing.common.remove')" @click="form.items.splice(i, 1)">✕</CButton></td>
            </tr>
          </tbody>
        </table>
      </div>
      <CButton size="sm" color="secondary" variant="outline" class="mb-3" @click="addLine">+ {{ t('purchasing.bill.addLine') }}</CButton>

      <div class="ms-auto" style="max-width:340px;">
        <div class="d-flex justify-content-between"><span>{{ t('purchasing.bill.subtotal') }}</span><span>{{ formatMoney(subtotal) }}</span></div>
        <div class="d-flex justify-content-between align-items-center">
          <span>{{ t('purchasing.bill.vat') }}</span>
          <span>{{ formatMoney(vat) }}</span>
        </div>
        <div class="small text-muted mb-1">{{ t('purchasing.bill.vatAuto') }}</div>
        <div class="d-flex justify-content-between fw-bold border-top pt-1"><span>{{ t('purchasing.bill.total') }}</span><span>{{ formatMoney(subtotal + vat) }}</span></div>
      </div>

      <div class="mt-3">
        <label class="form-label">{{ t('purchasing.common.notes') }}</label>
        <CFormTextarea v-model="form.notes" rows="2" />
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
import { purchasing, formatMoney, apiError, toCents, toTzs, today } from '@/services/purchasing'

const props = defineProps({
  visible: Boolean,
  bill: { type: Object, default: null },
  suppliers: { type: Array, default: () => [] },
  expenseCategories: { type: Array, default: () => [] },
  presetSupplierId: { type: [Number, String], default: '' },
})
const emit = defineEmits(['update:visible', 'saved'])
const { t } = useI18n()

const todayStr = today()
const blankLine = () => ({ description: '', quantity: 1, unit: '', unit_price: '' })
const form = reactive({ supplier_id: '', bill_number: '', bill_date: todayStr, due_date: '', expense_category_id: '', notes: '', items: [blankLine()] })
const errs = reactive({})
const formError = ref('')
const saving = ref(false)
const file = ref(null)

const vatRate = ref(18)
const supplier = computed(() => props.suppliers.find(s => s.id === Number(form.supplier_id)))
const lineCents = (l) => Math.round(Number(l.quantity || 0) * toCents(l.unit_price))
const subtotal = computed(() => form.items.reduce((sum, l) => sum + lineCents(l), 0))
// Mirrors the server: VAT is added only for a VAT-registered supplier, at the school's rate (shown by the server on save).
const vat = computed(() => (supplier.value?.vat_registered ? Math.round(subtotal.value * (vatRate.value / 100)) : 0))

watch(() => props.visible, async (open) => {
  if (!open) return
  Object.keys(errs).forEach(k => delete errs[k])
  formError.value = ''
  file.value = null
  try { vatRate.value = Number((await purchasing.settings()).vat_rate) } catch { /* default 18 */ }
  const b = props.bill
  if (b) {
    Object.assign(form, {
      supplier_id: String(b.supplier_id), bill_number: b.bill_number, bill_date: String(b.bill_date).slice(0, 10),
      due_date: b.due_date ? String(b.due_date).slice(0, 10) : '', expense_category_id: b.expense_category_id ? String(b.expense_category_id) : '', notes: b.notes || '',
      items: (b.items || []).map(i => ({ description: i.description, quantity: Number(i.quantity), unit: i.unit || '', unit_price: toTzs(i.unit_price_cents) })),
    })
    if (!form.items.length) form.items = [blankLine()]
  } else {
    Object.assign(form, { supplier_id: props.presetSupplierId ? String(props.presetSupplierId) : '', bill_number: '', bill_date: todayStr, due_date: '', expense_category_id: '', notes: '', items: [blankLine()] })
  }
})

function addLine() { form.items.push(blankLine()) }

function validate() {
  Object.keys(errs).forEach(k => delete errs[k])
  const need = t('purchasing.common.fixErrors')
  if (!form.supplier_id) errs.supplier_id = need
  if (!form.bill_number.trim()) errs.bill_number = need
  if (!form.expense_category_id) errs.expense_category_id = need
  if (!form.bill_date) errs.bill_date = need
  if (form.items.some(l => !l.description.trim() || !(Number(l.quantity) > 0) || l.unit_price === '')) errs.items = need
  return !Object.keys(errs).length
}

async function save() {
  if (!validate()) return
  saving.value = true
  formError.value = ''
  const body = {
    supplier_id: Number(form.supplier_id),
    bill_number: form.bill_number.trim(),
    bill_date: form.bill_date,
    due_date: form.due_date || null,
    expense_category_id: Number(form.expense_category_id),
    notes: form.notes || null,
    items: form.items.map(l => ({
      description: l.description.trim(), quantity: Number(l.quantity), unit: l.unit || null, unit_price_cents: toCents(l.unit_price),
    })),
  }
  try {
    const saved = props.bill ? await purchasing.updateBill(props.bill.id, body) : await purchasing.createBill(body)
    if (file.value) await purchasing.attachBill(saved.id, file.value)
    emit('saved', saved)
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
