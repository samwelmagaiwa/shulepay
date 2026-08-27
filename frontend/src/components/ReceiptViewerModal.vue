<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const props = defineProps({
  visible: { type: Boolean, default: false },
  /** Endpoint that returns the PDF, e.g. `/receipts/12/download`. */
  src: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  /** Filename used when saving. */
  filename: { type: String, default: 'document.pdf' },
})
const emit = defineEmits(['close'])

const loading = ref(false)
const error = ref('')
const blobUrl = ref('')
const frameRef = ref(null)

function release() {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value)
    blobUrl.value = ''
  }
}

async function load() {
  if (!props.src) return
  loading.value = true
  error.value = ''
  release()
  try {
    // Fetched through axios so the bearer token is attached — pointing an iframe
    // straight at the API URL sends no Authorization header and fails.
    const { data } = await api.get(props.src, { responseType: 'blob' })
    if (data.type && !data.type.includes('pdf')) throw new Error('Not a PDF')
    // toolbar=0 hides the viewer's own chrome so our buttons are the only ones;
    // view=Fit scales the page to the frame instead of rendering it at natural
    // size, which left an A5 page as a small block in a wide grey field.
    blobUrl.value = URL.createObjectURL(data) + '#toolbar=0&navpanes=0&view=Fit'
  } catch (e) {
    error.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    loading.value = false
  }
}

watch(() => [props.visible, props.src], ([vis]) => {
  if (vis) load()
  else release()
}, { immediate: true })

onBeforeUnmount(release)

function printIt() {
  const frame = frameRef.value
  if (!frame) return
  try {
    frame.contentWindow.focus()
    frame.contentWindow.print()
  } catch {
    // Some browsers block scripted printing of an embedded PDF.
    window.open(blobUrl.value, '_blank', 'noopener')
  }
}

function download() {
  if (!blobUrl.value) return
  const a = document.createElement('a')
  a.href = blobUrl.value
  a.download = props.filename
  document.body.appendChild(a)
  a.click()
  a.remove()
}
</script>

<template>
  <CModal :visible="visible" @close="emit('close')" size="lg" alignment="center" scrollable>
    <CModalHeader style="border-bottom:2px solid #007f3e;">
      <CModalTitle class="d-flex align-items-center gap-2">
        <span>🧾</span>
        <span>
          <span class="fw-bold">{{ title || t('payments.receipt') }}</span>
          <span v-if="subtitle" class="text-muted ms-2" style="font-size:.85rem; font-weight:400;">
            {{ subtitle }}
          </span>
        </span>
      </CModalTitle>
    </CModalHeader>

    <CModalBody class="p-0" style="background:var(--cui-tertiary-bg, #f1f3f5);">
      <div v-if="loading" class="d-flex flex-column align-items-center justify-content-center"
           style="height:70vh;">
        <CSpinner color="success" />
        <div class="text-muted small mt-3">{{ t('common.loading') }}</div>
      </div>

      <CAlert v-else-if="error" color="danger" class="m-4">{{ error }}</CAlert>

      <!-- The PDF itself. The iframe scrolls internally; the modal body scrolls
           too on short viewports, so the document is reachable either way. -->
      <div v-else class="receipt-frame-wrap">
        <iframe
          ref="frameRef"
          :src="blobUrl"
          class="receipt-frame"
          title="PDF"
        ></iframe>
      </div>
    </CModalBody>

    <CModalFooter class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
      <small class="text-muted d-none d-md-block">
        {{ t('payments.receiptViewerHint') }}
      </small>
      <div class="d-flex gap-2 ms-auto">
        <CButton color="secondary" variant="outline" @click="emit('close')" style="min-height:44px;">
          {{ t('common.close') }}
        </CButton>
        <CButton color="primary" variant="outline" :disabled="!blobUrl || loading"
                 @click="download" style="min-height:44px;">
          ⬇ {{ t('payments.downloadReceipt') }}
        </CButton>
        <CButton color="success" :disabled="!blobUrl || loading"
                 @click="printIt" style="min-height:44px; min-width:130px;">
          🖨 {{ t('payments.printReceipt') }}
        </CButton>
      </div>
    </CModalFooter>
  </CModal>
</template>

<style scoped>
.receipt-frame-wrap {
  /* Centres the page rather than stretching it across the full modal width —
     a portrait document in a wide frame reads as a small block floating in
     empty space. Scrolls for viewports too short for the whole sheet. */
  height: 76vh;
  min-height: 420px;
  overflow: auto;
  padding: 14px;
  text-align: center;
}

.receipt-frame {
  /* A-series portrait (1 : 1.414). Receipts are A4 and consolidated statements
     A5, but both share this ratio, so one box suits either. */
  height: 100%;
  aspect-ratio: 1 / 1.414;
  width: auto;
  max-width: 100%;
  border: 0;
  border-radius: 4px;
  background: #fff;
  box-shadow: 0 3px 14px rgba(0, 0, 0, .18);
}

@media (max-width: 767px) {
  .receipt-frame-wrap {
    height: 66vh;
    padding: 6px;
  }
  /* Too narrow for the portrait box to stay legible — fill the width instead. */
  .receipt-frame {
    aspect-ratio: auto;
    width: 100%;
    height: 100%;
  }
}
</style>
