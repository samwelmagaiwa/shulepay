<template>
  <CModal :visible="visible" @close="$emit('close')" size="lg" class="modal-fullscreen-sm-down">
    <CModalHeader>
      <CModalTitle>{{ t('sms.title') }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <CAlert v-if="success" color="success">
        {{ t('sms.sentSuccess', { count: sentCount }) }}
      </CAlert>

      <!-- Recipients summary -->
      <div class="mb-3">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="fw-semibold">{{ t('sms.recipients') }}:</div>
          <CBadge color="primary" class="p-2">{{ t('sms.recipientCount', { count: studentIds.length }) }}</CBadge>
        </div>
      </div>

      <!-- Template selector -->
      <div class="mb-3">
        <label class="form-label fw-semibold">{{ t('sms.template') }}</label>
        <CFormSelect v-model="selectedTemplate" @change="applyTemplate" size="sm">
          <option value="">{{ t('sms.selectTemplate') }}</option>
          <option value="reminder_soft">{{ t('sms.templates.reminderSoft') }}</option>
          <option value="reminder_hard">{{ t('sms.templates.reminderHard') }}</option>
          <option value="custom">{{ t('sms.templates.custom') }}</option>
        </CFormSelect>
      </div>

      <!-- Variable chips -->
      <div class="mb-2 d-flex flex-wrap align-items-center gap-1">
        <span class="text-muted small me-1">{{ t('sms.variables') }}:</span>
        <CBadge
          v-for="v in variables"
          :key="v"
          color="info"
          shape="rounded-pill"
          style="cursor: pointer; user-select: none;"
          @click="insertVariable(v)"
        >{{ v }}</CBadge>
        <span class="text-muted small ms-1">({{ t('sms.clickToInsert') }})</span>
      </div>

      <!-- Message textarea with char count -->
      <div class="mb-3">
        <label class="form-label fw-semibold">{{ t('sms.message') }} *</label>
        <CFormTextarea
          ref="msgArea"
          v-model="message"
          rows="4"
          maxlength="480"
          style="font-family: monospace; min-height: 100px;"
        />
        <div class="d-flex justify-content-between mt-1">
          <span class="text-muted small">{{ t('sms.recipientCount', { count: studentIds.length }) }}</span>
          <span :class="charCountClass">
            {{ message.length }}/160
            <span v-if="smsPartCount > 1">({{ t('sms.parts', { n: smsPartCount }) }})</span>
          </span>
        </div>
      </div>

      <!-- Message preview -->
      <CCard class="bg-body-secondary border-0">
        <CCardBody class="p-2 p-md-3">
          <div class="small fw-semibold mb-1">{{ t('sms.preview') }}:</div>
          <div class="small" style="white-space: pre-wrap; font-family: monospace;">{{ sampleMessage }}</div>
        </CCardBody>
      </CCard>
    </CModalBody>

    <CModalFooter class="flex-wrap gap-2">
      <CButton color="secondary" @click="$emit('close')" style="min-height: 44px;">{{ t('common.close') }}</CButton>
      <CButton
        color="warning"
        :disabled="sending || !studentIds.length || !message.trim()"
        @click="send"
        class="ms-auto"
        style="min-height: 44px;"
      >
        <CSpinner v-if="sending" size="sm" class="me-1" />
        <CIcon icon="cilSend" class="me-1" />
        {{ t('sms.send') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSmsStore } from '@/stores/sms'

const { t } = useI18n()

const props = defineProps({
  visible:    { type: Boolean, default: false },
  studentIds: { type: Array, default: () => [] },
  invoiceIds: { type: Array, default: () => [] },
})
const emit = defineEmits(['close'])
const smsStore = useSmsStore()

const sending        = ref(false)
const error          = ref('')
const success        = ref(false)
const sentCount      = ref(0)
const selectedTemplate = ref('')
const msgArea        = ref(null)

const message = ref(
  'Mwanafunzi [Jina] ana deni la TZS [Kiasi]. Tafadhali lipa kabla ya [Tarehe]. ShulePay'
)

const variables = ['[Jina]', '[Kiasi]', '[Tarehe]', '[Shule]']

const templates = {
  reminder_soft: 'Habari [Jina]! Ada yako ya TZS [Kiasi] bado haijalipiwa. Mwisho: [Tarehe]. Tafadhali lipa haraka. [Shule]',
  reminder_hard: 'ShulePay MUHIMU: Ada ya [Jina] TZS [Kiasi] imepita tarehe [Tarehe]. Lipa leo! Wasiliana na [Shule].',
}

// SMS part count (each part ≤ 153 chars when multi-part, 160 for single)
const smsPartCount = computed(() => {
  const len = message.value.length
  if (len <= 160) return 1
  return Math.ceil(len / 153)
})

const charCountClass = computed(() =>
  message.value.length > 160 ? 'text-danger small' : 'text-muted small'
)

const sampleMessage = computed(() =>
  message.value
    .replace(/\[Jina\]/g, 'Amina Juma')
    .replace(/\[Kiasi\]/g, '150,000')
    .replace(/\[Tarehe\]/g, '30/08/2026')
    .replace(/\[Shule\]/g, 'Shule ya Mfano')
)

function applyTemplate() {
  if (selectedTemplate.value && templates[selectedTemplate.value]) {
    message.value = templates[selectedTemplate.value]
  } else if (selectedTemplate.value === 'custom') {
    message.value = ''
  }
}

function insertVariable(v) {
  const textarea = msgArea.value?.$el?.querySelector('textarea') ?? msgArea.value
  if (!textarea) {
    message.value += v
    return
  }
  const start = textarea.selectionStart ?? message.value.length
  const end   = textarea.selectionEnd   ?? message.value.length
  message.value = message.value.slice(0, start) + v + message.value.slice(end)
  nextTick(() => {
    textarea.setSelectionRange(start + v.length, start + v.length)
    textarea.focus()
  })
}

async function send() {
  sending.value = false
  error.value   = ''
  sending.value = true
  try {
    const payload = { message: message.value }
    if (props.studentIds.length) payload.student_ids = props.studentIds
    if (props.invoiceIds.length) payload.invoice_ids = props.invoiceIds
    const data = await smsStore.sendBlast(payload)
    sentCount.value = data.sent ?? data.sent_count ?? props.studentIds.length
    success.value   = true
    setTimeout(() => emit('close'), 2500)
  } catch (e) {
    error.value = e?.response?.data?.message || 'Imeshindwa kutuma SMS.'
  } finally {
    sending.value = false
  }
}
</script>
