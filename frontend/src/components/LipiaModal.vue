<template>
  <CModal :visible="visible" @close="$emit('close')" size="xl" class="modal-fullscreen-sm-down" backdrop="static">
    <CModalHeader :style="result ? 'border-bottom:2px solid #007f3e;' : ''">
      <CModalTitle>
        <template v-if="result">✅ {{ t('payments.successTitle') }}</template>
        <template v-else>{{ t('payments.pay') }} — {{ invoice?.invoice_number }}</template>
      </CModalTitle>
    </CModalHeader>

    <!-- ═══════════ SUCCESS: payment recorded ═══════════ -->
    <CModalBody v-if="result" class="p-3 p-md-4">
      <div class="text-center mb-4">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-2"
             style="width:64px;height:64px;background:rgba(0,127,62,.1);font-size:2rem;">✅</div>
        <div class="fw-bold" style="font-size:1.75rem;color:#007f3e;">
          {{ formatTZS(result.amount_cents) }}
        </div>
        <div class="text-muted small">
          {{ t('payments.receivedFrom') }} <strong>{{ result.student?.full_name }}</strong>
        </div>
        <CBadge color="success" class="mt-2 px-3 py-2" style="font-size:.8rem;">
          {{ t('payments.receipt') }}: {{ result.receipt?.receipt_number }}
        </CBadge>
      </div>

      <div class="rounded-3 p-3 mb-3" style="background:var(--cui-tertiary-bg, #f8f9fa);">
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('common.term') }}</span>
          <strong>{{ result.invoice?.term || '—' }}<span v-if="result.invoice?.academic_year" class="text-muted fw-normal"> · {{ result.invoice.academic_year }}</span></strong>
        </div>
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('invoices.number') }}</span>
          <strong>{{ result.invoice?.invoice_number }}</strong>
        </div>
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('payments.method') }}</span>
          <strong>{{ result.method_label }}</strong>
        </div>
        <div v-if="result.reference_number" class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('payments.referenceNumber') }}</span>
          <strong>{{ result.reference_number }}</strong>
        </div>

        <hr class="my-2" />

        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('common.total') }}</span>
          <span>{{ formatTZS(result.invoice?.total_amount_cents) }}</span>
        </div>
        <div class="d-flex justify-content-between py-1 small">
          <span class="text-muted">{{ t('invoices.amountPaid') }}</span>
          <span class="text-success fw-semibold">{{ formatTZS(result.invoice?.paid_cents) }}</span>
        </div>
        <div class="d-flex justify-content-between pt-2 border-top">
          <span class="fw-bold">{{ t('payments.balanceDue') }}</span>
          <span class="fw-bold" :class="result.invoice?.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
            {{ formatTZS(result.invoice?.balance_due_cents) }}
          </span>
        </div>
      </div>

      <CAlert v-if="result.invoice?.balance_due_cents === 0" color="success" class="py-2 small mb-0 text-center">
        🎉 {{ t('payments.invoiceFullySettled') }}
      </CAlert>
      <CAlert v-else color="warning" class="py-2 small mb-0 text-center">
        {{ t('payments.remainingBalanceNote', { amount: formatTZS(result.invoice?.balance_due_cents) }) }}
      </CAlert>
    </CModalBody>

    <CModalFooter v-if="result" class="gap-2">
      <CButton color="secondary" variant="outline" @click="closeAfterPayment" style="min-height:44px;">
        {{ t('common.close') }}
      </CButton>
      <CButton color="primary" variant="outline" :disabled="!result.receipt?.id"
               @click="downloadReceipt" style="min-height:44px;">
        ⬇ {{ t('payments.downloadReceipt') }}
      </CButton>
      <CButton color="success" :disabled="!result.receipt?.id"
               @click="printReceipt" style="min-height:44px; min-width:140px;">
        🖨 {{ t('payments.printReceipt') }}
      </CButton>
    </CModalFooter>

    <!-- ═══════════ FORM: record a payment ═══════════ -->
    <CModalBody v-if="!result" class="p-2 p-md-4">
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
    <CModalFooter v-if="!result">
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

// Set once the payment succeeds — switches the modal to its confirmation state.
const result = ref(null)

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
    result.value = null
    updatePreview()
  } else {
    cleanupPrintFrame()
  }
})

// ── Receipt actions ─────────────────────────────────────────────────────────
const printFrame = ref(null)

function receiptUrl(asDownload = false) {
  const id = result.value?.receipt?.id
  return id ? `/api/receipts/${id}/download${asDownload ? '?download=1' : ''}` : null
}

function cleanupPrintFrame() {
  if (printFrame.value) {
    printFrame.value.remove()
    printFrame.value = null
  }
}

function printReceipt() {
  const url = receiptUrl(false)
  if (!url) return

  // The PDF is same-origin, so it can be loaded into a hidden iframe and printed
  // directly — no extra tab, and the printed output is the real branded receipt
  // rather than a screen-styled copy of the card. If the browser refuses (some
  // block scripted printing of embedded PDFs), fall back to opening it in a tab.
  cleanupPrintFrame()
  const frame = document.createElement('iframe')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  frame.src = url
  frame.onload = () => {
    try {
      frame.contentWindow.focus()
      frame.contentWindow.print()
    } catch {
      window.open(url, '_blank', 'noopener')
    }
  }
  frame.onerror = () => window.open(url, '_blank', 'noopener')
  document.body.appendChild(frame)
  printFrame.value = frame
}

function downloadReceipt() {
  const url = receiptUrl(true)
  if (url) window.open(url, '_blank', 'noopener')
}

function closeAfterPayment() {
  cleanupPrintFrame()
  emit('close')
}

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
    const { data } = await api.post('/payments', {
      invoice_id: props.invoice.id,
      amount_cents: Math.round(Number(form.value.amount) * 100),
      method: form.value.method,
      reference_number: form.value.reference_number || null,
      paid_at: form.value.paid_at,
    })
    // The response already carries the issued receipt and the recalculated invoice —
    // keep it so the confirmation card needs no extra round-trip.
    result.value = data.data ?? data

    // Let the parent refresh its list now; the modal stays open on the receipt card.
    emit('paid')
  } catch (e) {
    error.value = e?.response?.data?.message || t('payments.errorGeneral')
  } finally {
    saving.value = false
  }
}
</script>
