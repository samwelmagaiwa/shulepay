<template>
  <div class="receipt-preview border rounded p-3"
       style="font-family:'Courier New',monospace; background:#fff; color:#000; font-size:.8rem; min-height:280px;">
    <div class="mb-2">
      <!-- Logo left + school name right -->
      <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
        <img v-if="branding.logoUrl" :src="branding.logoUrl"
             style="height:44px; width:44px; object-fit:contain; flex-shrink:0;" />
        <div style="text-align:left;">
          <div class="fw-bold" style="font-size:1rem; letter-spacing:1px;">{{ branding.appName }}</div>
          <div v-if="branding.appTagline" style="font-size:.7rem; color:#555;">{{ branding.appTagline }}</div>
        </div>
      </div>
      <div class="fw-bold text-center">{{ t('payments.receipt').toUpperCase() }}</div>
      <div style="border-top:2px solid #000; border-bottom:1px solid #000; margin:4px 0; padding:2px 0;">
        PAYMENT RECEIPT
      </div>
    </div>
    <div class="d-flex justify-content-between small mb-1">
      <span>{{ t('payments.receiptNo') }}:</span>
      <span class="fw-bold">{{ data.receipt_number || 'RCP-PREVIEW' }}</span>
    </div>
    <div class="d-flex justify-content-between small mb-1">
      <span>{{ t('common.date') }}:</span>
      <span>{{ data.date || '—' }}</span>
    </div>
    <div style="border-top:1px dashed #000; margin:6px 0;"></div>
    <div class="small mb-1"><strong>{{ t('common.name') }}:</strong> {{ data.student || '—' }}</div>
    <div class="small mb-1"><strong>{{ t('students.admissionNo') }}:</strong> {{ data.admission || '—' }}</div>
    <div class="small mb-1"><strong>{{ t('invoices.invoiceNo') }}:</strong> {{ data.invoice_number || '—' }}</div>
    <div style="border-top:1px dashed #000; margin:6px 0;"></div>
    <div class="d-flex justify-content-between mb-1">
      <span class="small">{{ t('payments.amount') }}:</span>
      <span class="fw-bold" style="font-size:1.1rem;">{{ formatTZS(data.amount_tzs) }}</span>
    </div>
    <div class="small mb-1"><strong>{{ t('payments.method') }}:</strong> {{ methodLabel(data.method) }}</div>
    <div v-if="data.reference" class="small mb-1"><strong>{{ t('payments.reference') }}:</strong> {{ data.reference }}</div>
    <div style="border-top:2px solid #000; margin:6px 0;"></div>
    <div class="text-center small" style="color:#555;">Asante kwa malipo yako &bull; Thank you</div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding'

const { t } = useI18n()
const branding = useBrandingStore()
defineProps({ data: { type: Object, default: () => ({}) } })

function formatTZS(tzs) {
  if (!tzs) return 'TZS 0'
  return 'TZS ' + Math.round(tzs).toLocaleString('en-TZ', { maximumFractionDigits: 0 })
}
function methodLabel(m) {
  const map = { cash: 'common.cash', mpesa: 'common.mpesa', bank: 'common.bank', cheque: 'common.cheque' }
  return map[m] ? t(map[m]) : (m || '—')
}
</script>
