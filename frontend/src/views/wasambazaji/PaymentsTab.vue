<template>
  <div>
    <Teleport to="#sup-toolbar" defer>
        <CButton color="secondary" variant="outline" style="min-height:40px;" :title="t('printing.print')" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormInput v-model="filters.search" :placeholder="t('purchasing.payment.searchPlaceholder')" style="max-width:200px;min-height:40px;" @input="debouncedReload" />
        <CFormSelect v-model="filters.supplier_id" style="max-width:220px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.common.all') }} — {{ t('purchasing.payment.supplier') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" style="max-width:200px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.common.all') }} — {{ t('purchasing.common.status') }}</option>
          <option v-for="st in statuses" :key="st" :value="st">{{ t('purchasing.payment.status.' + st) }}</option>
        </CFormSelect>
        <CButton color="success" style="min-height:40px;" @click="openNew()">
          <CIcon icon="cilPlus" class="me-1" />{{ t('purchasing.payment.add') }}
        </CButton>
    </Teleport>

    <div v-if="note" class="alert alert-success py-2">{{ note }}</div>
    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <CCard v-else>
      <CCardBody class="p-0">
        <CTable responsive hover class="sup-grid mb-0 align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('purchasing.common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.payment.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.payment.method') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.common.amount') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('purchasing.payment.bills') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(p, i) in rows" :key="p.id">
              <CTableDataCell>{{ day(p.payment_date) }}</CTableDataCell>
              <CTableDataCell>
                {{ p.supplier?.name }}
                <div class="small text-muted">{{ p.reference }}<span v-if="p.recorder"> · {{ p.recorder.name }}</span></div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">{{ t('purchasing.methods.' + p.method) }}</CTableDataCell>
              <CTableDataCell class="text-end">
                {{ formatMoney(p.amount_cents) }}
                <div v-if="p.wht_cents > 0" class="small text-muted">− {{ formatMoney(p.wht_cents) }} {{ t('purchasing.reports.wht') }}</div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell small">{{ (p.allocations || []).map(a => a.bill?.bill_number).filter(Boolean).join(', ') || '—' }}</CTableDataCell>
              <CTableDataCell><CBadge :color="color(p.status)">{{ t('purchasing.payment.status.' + p.status) }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown v-if="p.status === 'pending_approval' || p.status === 'approved'" :placement="menuPlacement(i, rows.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('purchasing.common.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem v-if="access.can.payment_approve && p.status === 'pending_approval'" :disabled="busy" @click="confirmApprove = p">{{ t('purchasing.payment.approve') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.payment_approve && p.status === 'pending_approval'" :disabled="busy" @click="askReason('reject', p)">{{ t('purchasing.payment.reject') }}</CDropdownItem>
                    <CDropdownItem v-if="!access.can.payment_approve && p.status === 'pending_approval'" disabled>{{ t('purchasing.bill.waitingOwner') }}</CDropdownItem>
                    <CDropdownItem v-if="p.status === 'approved'" @click="voucher(p)">🧾 {{ t('expensex.voucher') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.payment_void && p.status === 'approved'" @click="askReason('void', p)">{{ t('purchasing.payment.void') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!rows.length"><CTableDataCell colspan="7" class="text-center text-muted py-4">{{ t('purchasing.payment.noPayments') }}</CTableDataCell></CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3">
      <small class="text-medium-emphasis">{{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }}</small>
      <CPagination size="sm" class="mb-0">
        <CPaginationItem :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">‹</CPaginationItem>
        <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">›</CPaginationItem>
      </CPagination>
    </div>

    <PaymentFormModal v-model:visible="showForm" :suppliers="suppliers" :preset-supplier-id="presetSupplierId" @saved="onSaved" />

    <CModal :visible="!!confirmApprove" @close="confirmApprove = null">
      <CModalHeader><CModalTitle>{{ t('purchasing.payment.confirmApproveTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="confirmApprove">
        <p class="mb-2">{{ t('purchasing.payment.confirmApproveBody') }}</p>
        <div class="border rounded p-2 bg-light">
          <div class="fw-semibold">{{ confirmApprove.supplier?.name }}</div>
          <div class="small text-muted">{{ t('purchasing.methods.' + confirmApprove.method) }} · {{ String(confirmApprove.payment_date).slice(0, 10) }}</div>
          <div class="fs-5 fw-bold mt-1">{{ formatMoney(confirmApprove.amount_cents) }}</div>
          <div v-if="confirmApprove.wht_cents > 0" class="small text-muted">
            {{ t('purchasing.payment.netPaid') }}: {{ formatMoney(confirmApprove.amount_cents - confirmApprove.wht_cents) }}
          </div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="confirmApprove = null">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="success" :disabled="busy" @click="doApprove">
          <CSpinner v-if="busy" size="sm" class="me-1" />{{ t('purchasing.payment.approve') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <CModal :visible="!!reasonFor" @close="reasonFor = null">
      <CModalHeader><CModalTitle>{{ reasonFor ? t('purchasing.payment.' + reasonFor.action) : '' }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p v-if="reasonFor?.action === 'void'" class="small text-muted">{{ t('purchasing.payment.voidHelp') }}</p>
        <label class="form-label fw-semibold">{{ t('purchasing.common.reason') }} *</label>
        <CFormInput v-model="reason" :invalid="reasonError" />
        <div v-if="reasonError" class="text-danger small">{{ t('purchasing.common.reasonRequired') }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="reasonFor = null">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="danger" :disabled="busy" @click="confirmReason">{{ t('purchasing.common.confirm') }}</CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('purchasing.tabs.payments')" :lines="printLines" :columns="printColumns" :rows="rows" landscape />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { openVoucher } from '@/services/voucher'
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, access } from '@/services/purchasing'
import PaymentFormModal from './PaymentFormModal.vue'

const props = defineProps({ preset: { type: Object, default: null } })
const emit = defineEmits(['changed', 'created'])
const { t } = useI18n()

const statuses = ['pending_approval', 'approved', 'rejected', 'void']
const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const suppliers = ref([])
const loading = ref(false)
const error = ref('')
const note = ref('')
const busy = ref(false)
const page = ref(1)
const filters = reactive({ search: '', supplier_id: '', status: '' })
const showForm = ref(false)
const presetSupplierId = ref('')
const reasonFor = ref(null)
const confirmApprove = ref(null)
const reason = ref('')
const reasonError = ref(false)

const day = (d) => (d ? String(d).slice(0, 10) : '—')
async function voucher(p) {
  const msg = await openVoucher('supplier-payment', p.id)
  if (msg) note.value = msg
}
const color = (st) => ({ pending_approval: 'warning', approved: 'success', rejected: 'danger', void: 'secondary' }[st] || 'secondary')

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await purchasing.payments({ page: page.value, search: filters.search || undefined, supplier_id: filters.supplier_id || undefined, status: filters.status || undefined })
    rows.value = res.data
    meta.value = { current_page: res.current_page, last_page: res.last_page, total: res.total }
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  } finally {
    loading.value = false
  }
}

let timer = null
function debouncedReload() { clearTimeout(timer); timer = setTimeout(reload, 300) }
function reload() { page.value = 1; load() }
function goPage(p) { page.value = p; load() }
function openNew(supplierId = '') { presetSupplierId.value = supplierId; showForm.value = true }

async function refreshSuppliers() {
  try { suppliers.value = (await purchasing.suppliers({ per_page: 500 })).data } catch { /* picker stays empty */ }
}

async function onSaved(payment) {
  note.value = payment?.status === 'pending_approval' ? t('purchasing.payment.savedPending') : t('purchasing.payment.saved')
  await Promise.all([load(), refreshSuppliers()])
  emit('changed')
  if (payment?.id) emit('created', payment)
}

async function doApprove() {
  const p = confirmApprove.value
  if (!p) return
  await approve(p)
  confirmApprove.value = null
}

async function approve(p) {
  busy.value = true
  error.value = ''
  try {
    await purchasing.approvePayment(p.id)
    note.value = t('purchasing.payment.saved')
    await Promise.all([load(), refreshSuppliers()])
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    busy.value = false
  }
}

function askReason(action, payment) { reasonFor.value = { action, payment }; reason.value = ''; reasonError.value = false }

async function confirmReason() {
  if (!reason.value.trim()) { reasonError.value = true; return }
  busy.value = true
  error.value = ''
  try {
    const { action, payment } = reasonFor.value
    if (action === 'reject') await purchasing.rejectPayment(payment.id, reason.value.trim())
    else await purchasing.voidPayment(payment.id, reason.value.trim())
    reasonFor.value = null
    note.value = ''
    await Promise.all([load(), refreshSuppliers()])
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
    reasonFor.value = null
  } finally {
    busy.value = false
  }
}

function applyPreset() {
  if (props.preset?.openNew) openNew(props.preset.supplier?.id || '')
}
watch(() => props.preset, applyPreset)

onMounted(async () => {
  load()
  await refreshSuppliers()
  applyPreset()
})

// ── Print view: the table as it is on screen, with the school's header ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('purchasing.common.date'), value: (r) => day(r.payment_date) },
  { label: t('purchasing.payment.supplier'), value: (r) => r.supplier?.name },
  { label: t('purchasing.payment.reference'), value: (r) => r.reference },
  { label: t('purchasing.payment.method'), value: (r) => t('purchasing.methods.' + r.method) },
  { label: t('purchasing.common.amount'), value: (r) => formatMoney(r.amount_cents), align: 'end' },
  { label: t('purchasing.reports.wht'), value: (r) => (r.wht_cents > 0 ? formatMoney(r.wht_cents) : '—'), align: 'end' },
  { label: t('purchasing.payment.bills'), value: (r) => (r.allocations || []).map((a) => a.bill?.bill_number).filter(Boolean).join(', ') },
  { label: t('purchasing.common.status'), value: (r) => t('purchasing.payment.status.' + r.status) },
])
const printLines = computed(() => {
  const lines = []
  const who = suppliers.value.find((x) => String(x.id) === String(filters.supplier_id))?.name
  if (who) lines.push(t('purchasing.payment.supplier') + ': ' + who)
  if (filters.status) lines.push(t('purchasing.common.status') + ': ' + t('purchasing.payment.status.' + filters.status))
  if (filters.search) lines.push(t('purchasing.common.search') + ': ' + filters.search)
  return lines
})
</script>
