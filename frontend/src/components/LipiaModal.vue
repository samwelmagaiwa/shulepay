<template>
  <CModal :visible="visible" @close="$emit('close')" size="xl" class="modal-fullscreen-sm-down" backdrop="static">
    <CModalHeader>
      <CModalTitle>{{ t('payments.pay') }} — {{ invoice?.invoice_number }}</CModalTitle>
    </CModalHeader>
    <CModalBody class="p-2 p-md-4">
      <CRow class="g-3">
        <!-- Form column -->
        <CCol xs="12" md="6">
          <!-- Invoice summary -->
          <div class="p-3 rounded mb-3" style="background:var(--cui-tertiary-bg, #f8f9fa);">
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small">{{ t('common.student') }}</span>
              <strong>{{ invoice?.student?.full_name }}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small">{{ t('students.admissionNo') }}</span>
              <span class="small">{{ invoice?.student?.admission_number }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small">{{ t('common.total') }}</span>
              <span>{{ formatTZS(invoice?.total_amount_cents) }}</span>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span class="text-muted small">{{ t('invoices.amountPaid') }}</span>
              <span class="text-success">{{ formatTZS(invoice?.paid_cents) }}</span>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span class="fw-bold">{{ t('payments.balanceDue') }}</span>
              <span class="fw-bold fs-5 text-danger">{{ formatTZS(invoice?.balance_due_cents) }}</span>
            </div>
          </div>

          <!-- Payment form -->
          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('payments.amount') }} (TZS) <span class="text-danger">*</span></label>
            <CFormInput
              type="number"
              v-model.number="form.amount"
              :max="maxAmount"
              min="1"
              placeholder="e.g. 50000"
              size="lg"
              @input="updatePreview"
            />
            <div class="text-muted small mt-1">{{ t('payments.maxAmount') }}: {{ formatTZS(invoice?.balance_due_cents) }}</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('payments.method') }} <span class="text-danger">*</span></label>
            <CFormSelect v-model="form.method" @change="updatePreview" size="lg">
              <option value="cash">{{ t('common.cash') }}</option>
              <option value="mpesa">{{ t('common.mpesa') }}</option>
              <option value="bank">{{ t('common.bank') }}</option>
              <option value="cheque">{{ t('common.cheque') }}</option>
            </CFormSelect>
          </div>

          <div class="mb-3" v-if="form.method !== 'cash'">
            <label class="form-label fw-semibold">{{ t('payments.reference') }} <span class="text-danger">*</span></label>
            <CFormInput
              v-model="form.reference_number"
              :placeholder="form.method === 'mpesa' ? 'e.g. QHX123456' : 'e.g. BENKI-0001234'"
              @input="updatePreview"
            />
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">{{ t('payments.paidAt') }}</label>
            <CFormInput type="date" v-model="form.paid_at" @change="updatePreview" />
          </div>

          <!-- Mobile preview toggle -->
          <CButton class="d-md-none w-100 mb-3" color="secondary" variant="outline" size="sm"
                   @click="showPreviewMobile = !showPreviewMobile">
            {{ showPreviewMobile ? t('payments.hidePreview') : t('payments.showPreview') }}
          </CButton>

          <!-- Mobile preview -->
          <div v-if="showPreviewMobile" class="d-md-none mb-3">
            <ReceiptPreview :data="previewData" />
          </div>

          <CAlert v-if="error" color="danger" class="mb-3">{{ error }}</CAlert>
        </CCol>

        <!-- Desktop receipt preview -->
        <CCol md="6" class="d-none d-md-block">
          <div class="sticky-top" style="top:10px;">
            <div class="text-muted small fw-semibold mb-2 text-uppercase ls-1">{{ t('payments.receiptPreview') }}</div>
            <ReceiptPreview :data="previewData" />
          </div>
        </CCol>
      </CRow>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" variant="outline" @click="$emit('close')" :disabled="saving">{{ t('common.cancel') }}</CButton>
      <CButton color="success" :disabled="saving || !form.amount || form.amount <= 0" @click="submit"
               style="min-height:44px; min-width:120px;">
        <CSpinner v-if="saving" size="sm" class="me-1" />
        <span v-else>{{ t('payments.payBtn', { amount: form.amount ? formatTZS(form.amount * 100) : '' }) }}</span>
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import ReceiptPreview from '@/components/ReceiptPreview.vue'

const { t } = useI18n()

const props = defineProps({ visible: Boolean, invoice: Object })
const emit = defineEmits(['close', 'paid'])

const saving = ref(false)
const error = ref('')
const showPreviewMobile = ref(false)

const today = new Date().toISOString().slice(0, 10)
const form = ref({ amount: '', method: 'cash', reference_number: '', paid_at: today })

const maxAmount = computed(() =>
  props.invoice ? Math.round((props.invoice.balance_due_cents || 0) / 100) : 0
)

function formatTZS(cents) {
  if (!cents && cents !== 0) return '—'
  return 'TZS ' + Math.round(cents / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

const previewData = ref({})

function updatePreview() {
  const inv = props.invoice
  const paid = form.value.amount ? Number(form.value.amount) : 0
  const balanceBefore = Math.round((inv?.balance_due_cents || 0) / 100)
  previewData.value = {
    student:        inv?.student?.full_name || '',
    admission:      inv?.student?.admission_number || '',
    school_class:   inv?.student?.school_class?.name || inv?.school_class?.name || '',
    invoice_number: inv?.invoice_number || '',
    term:           inv?.term?.name || '',
    academic_year:  inv?.academic_year?.name || inv?.academic_year?.year || '',
    total_tzs:      Math.round((inv?.total_amount_cents || 0) / 100),
    paid_before_tzs:Math.round((inv?.paid_cents || 0) / 100),
    balance_before_tzs: balanceBefore,
    amount_tzs:     paid,
    balance_after_tzs: Math.max(0, balanceBefore - paid),
    method:         form.value.method,
    reference:      form.value.reference_number,
    date:           form.value.paid_at,
    receipt_number: 'RCP-PREVIEW',
  }
}

watch(() => props.visible, (v) => {
  if (v) {
    form.value = { amount: '', method: 'cash', reference_number: '', paid_at: today }
    error.value = ''
    showPreviewMobile.value = false
    updatePreview()
  }
})

watch(() => props.invoice, () => { updatePreview() })

async function submit() {
  error.value = ''
  if (!form.value.amount || Number(form.value.amount) <= 0) {
    error.value = t('payments.errorNoAmount')
    return
  }
  if (Number(form.value.amount) * 100 > (props.invoice?.balance_due_cents || 0)) {
    error.value = t('payments.errorOverpay')
    return
  }
  if (form.value.method !== 'cash' && !form.value.reference_number.trim()) {
    error.value = t('payments.errorNoRef')
    return
  }
  saving.value = true
  try {
    await api.post('/payments', {
      invoice_id: props.invoice.id,
      amount_cents: Math.round(Number(form.value.amount) * 100),
      method: form.value.method,
      reference_number: form.value.reference_number || null,
      paid_at: form.value.paid_at,
    })
    emit('paid')
  } catch (e) {
    error.value = e?.response?.data?.message || t('payments.errorGeneral')
  } finally {
    saving.value = false
  }
}
</script>
