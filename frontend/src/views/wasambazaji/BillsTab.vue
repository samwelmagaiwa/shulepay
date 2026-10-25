<template>
  <div>
    <Teleport to="#sup-toolbar" defer>
        <CButton color="secondary" variant="outline" style="min-height:40px;" :title="t('printing.print')" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormInput v-model="filters.search" :placeholder="t('purchasing.bill.number')" style="max-width:200px;min-height:40px;" @input="debouncedReload" />
        <CFormSelect v-model="filters.supplier_id" style="max-width:220px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.common.all') }} — {{ t('purchasing.bill.supplier') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" style="max-width:190px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.common.all') }} — {{ t('purchasing.common.status') }}</option>
          <option v-for="st in statuses" :key="st" :value="st">{{ t('purchasing.bill.status.' + st) }}</option>
        </CFormSelect>
        <CFormCheck v-model="filters.overdue" :label="t('purchasing.bill.overdueOnly')" @change="reload" />
        <CButton color="success" style="min-height:40px;" @click="openNew()">
          <CIcon icon="cilPlus" class="me-1" />{{ t('purchasing.bill.add') }}
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
              <CTableHeaderCell>{{ t('purchasing.bill.number') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.bill.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.bill.dueDate') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.bill.total') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end d-none d-md-table-cell">{{ t('purchasing.bill.balance') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(b, i) in rows" :key="b.id">
              <CTableDataCell>
                <a href="#" class="fw-semibold text-decoration-none" @click.prevent="openView(b)">{{ b.bill_number }}</a>
                <div class="small text-muted">{{ day(b.bill_date) }}</div>
              </CTableDataCell>
              <CTableDataCell>{{ b.supplier?.name }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell" :class="isLate(b) ? 'text-danger fw-semibold' : ''">{{ day(b.due_date) }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ formatMoney(b.total_cents) }}</CTableDataCell>
              <CTableDataCell class="text-end d-none d-md-table-cell">{{ formatMoney(b.total_cents - b.paid_cents) }}</CTableDataCell>
              <CTableDataCell><CBadge :color="color(b.status)">{{ t('purchasing.bill.status.' + b.status) }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown :placement="menuPlacement(i, rows.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('purchasing.common.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem @click="openView(b)">{{ t('purchasing.common.view') }}</CDropdownItem>
                    <CDropdownItem v-if="b.status === 'pending_approval' && access.can.bill_approve" :disabled="busy" @click="confirmApprove = b">{{ t('purchasing.bill.approve') }}</CDropdownItem>
                    <CDropdownItem v-if="b.status === 'pending_approval' && access.can.bill_approve" :disabled="busy" @click="askReason('reject', b)">{{ t('purchasing.bill.reject') }}</CDropdownItem>
                    <CDropdownItem v-else-if="b.status === 'pending_approval'" disabled>{{ t('purchasing.bill.waitingOwner') }}</CDropdownItem>
                    <CDropdownItem v-if="['pending_approval', 'rejected'].includes(b.status)" @click="openEdit(b)">{{ t('purchasing.common.edit') }}</CDropdownItem>
                    <CDropdownItem v-if="['pending_approval', 'rejected', 'approved'].includes(b.status) && !b.paid_cents && access.can.bill_cancel" @click="askReason('cancel', b)">{{ t('purchasing.bill.cancel') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!rows.length"><CTableDataCell colspan="7" class="text-center text-muted py-4">{{ t('purchasing.bill.noBills') }}</CTableDataCell></CTableRow>
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

    <BillFormModal v-model:visible="showForm" :bill="editing" :suppliers="suppliers" :expense-categories="expenseCategories"
                   :preset-supplier-id="presetSupplierId" @saved="onSaved" />

    <!-- Bill details -->
    <CModal :visible="!!viewing" size="lg" scrollable @close="viewing = null">
      <CModalHeader><CModalTitle>{{ viewing?.bill_number }} — {{ viewing?.supplier?.name }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewing">
        <CBadge :color="color(viewing.status)" class="mb-2">{{ t('purchasing.bill.status.' + viewing.status) }}</CBadge>
        <CTable class="sup-grid" small responsive>
          <CTableHead class="table-light"><CTableRow>
            <CTableHeaderCell>{{ t('purchasing.bill.description') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('purchasing.bill.quantity') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('purchasing.bill.unitPrice') }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('purchasing.bill.lineTotal') }}</CTableHeaderCell>
          </CTableRow></CTableHead>
          <CTableBody>
            <CTableRow v-for="i in viewing.items" :key="i.id">
              <CTableDataCell>{{ i.description }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ Number(i.quantity) }} {{ i.unit }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ formatMoney(i.unit_price_cents) }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ formatMoney(i.line_total_cents) }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <div class="ms-auto" style="max-width:300px;">
          <div class="d-flex justify-content-between"><span>{{ t('purchasing.bill.subtotal') }}</span><span>{{ formatMoney(viewing.subtotal_cents) }}</span></div>
          <div class="d-flex justify-content-between"><span>{{ t('purchasing.bill.vat') }}</span><span>{{ formatMoney(viewing.vat_cents) }}</span></div>
          <div class="d-flex justify-content-between fw-bold border-top"><span>{{ t('purchasing.bill.total') }}</span><span>{{ formatMoney(viewing.total_cents) }}</span></div>
          <div class="d-flex justify-content-between"><span>{{ t('purchasing.bill.paid') }}</span><span>{{ formatMoney(viewing.paid_cents) }}</span></div>
          <div class="d-flex justify-content-between fw-semibold"><span>{{ t('purchasing.bill.balance') }}</span><span>{{ formatMoney(viewing.total_cents - viewing.paid_cents) }}</span></div>
        </div>
        <div v-if="viewing.allocations?.length" class="mt-3">
          <div class="fw-semibold small">{{ t('purchasing.bill.paidOn') }}</div>
          <div v-for="a in viewing.allocations" :key="a.id" class="small">
            {{ day(a.payment?.payment_date) }} · {{ a.payment ? t('purchasing.methods.' + a.payment.method) : '' }} · {{ formatMoney(a.amount_cents) }}
            <CBadge v-if="a.payment?.status === 'void'" color="secondary">{{ t('purchasing.payment.status.void') }}</CBadge>
          </div>
        </div>
        <div class="small text-muted mt-3">
          <div v-if="viewing.recorder">{{ t('purchasing.bill.recordedBy') }}: {{ viewing.recorder.name }}</div>
          <div v-if="viewing.approver">{{ t('purchasing.bill.approvedBy') }}: {{ viewing.approver.name }}</div>
          <div v-if="viewing.notes">{{ viewing.notes }}</div>
          <div v-if="viewing.status === 'rejected' && viewing.cancel_reason" class="text-danger">{{ viewing.cancel_reason }}</div>
        </div>
        <a v-if="viewing.attachment_path" class="d-inline-block mt-2" :href="'/storage/' + viewing.attachment_path" target="_blank" rel="noopener">{{ t('purchasing.bill.viewAttachment') }}</a>
      </CModalBody>
      <CModalFooter><CButton color="secondary" @click="viewing = null">{{ t('purchasing.common.close') }}</CButton></CModalFooter>
    </CModal>

    <!-- Approve: ask first, the debt and the expense are booked on approval -->
    <CModal :visible="!!confirmApprove" @close="confirmApprove = null">
      <CModalHeader><CModalTitle>{{ t('purchasing.bill.confirmApproveTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="confirmApprove">
        <p class="mb-2">{{ t('purchasing.bill.confirmApproveBody') }}</p>
        <div class="border rounded p-2 bg-light">
          <div class="fw-semibold">{{ confirmApprove.supplier?.name }}</div>
          <div class="small text-muted">{{ t('purchasing.bill.number') }}: {{ confirmApprove.bill_number }}</div>
          <div class="fs-5 fw-bold mt-1">{{ formatMoney(confirmApprove.total_cents) }}</div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="confirmApprove = null">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="success" :disabled="busy" @click="doApprove">
          <CSpinner v-if="busy" size="sm" class="me-1" />{{ t('purchasing.bill.approve') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Reason (reject / cancel) -->
    <CModal :visible="!!reasonFor" @close="reasonFor = null">
      <CModalHeader><CModalTitle>{{ reasonFor?.action === 'reject' ? t('purchasing.bill.reject') : t('purchasing.bill.cancel') }} — {{ reasonFor?.bill.bill_number }}</CModalTitle></CModalHeader>
      <CModalBody>
        <label class="form-label fw-semibold">{{ t('purchasing.common.reason') }} *</label>
        <CFormInput v-model="reason" :invalid="reasonError" />
        <div v-if="reasonError" class="text-danger small">{{ t('purchasing.common.reasonRequired') }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="reasonFor = null">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="danger" :disabled="busy" @click="confirmReason">{{ t('purchasing.common.confirm') }}</CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('purchasing.tabs.bills')" :lines="printLines" :columns="printColumns" :rows="rows" landscape />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, today, access } from '@/services/purchasing'
import api from '@/services/api'
import BillFormModal from './BillFormModal.vue'

const props = defineProps({ preset: { type: Object, default: null } })
const emit = defineEmits(['changed', 'created'])
const { t } = useI18n()

const statuses = ['pending_approval', 'approved', 'part_paid', 'paid', 'rejected', 'cancelled']
const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const suppliers = ref([])
const expenseCategories = ref([])
const loading = ref(false)
const error = ref('')
const note = ref('')
const busy = ref(false)
const page = ref(1)
const filters = reactive({ search: '', supplier_id: '', status: '', overdue: false })

const showForm = ref(false)
const editing = ref(null)
const presetSupplierId = ref('')
const viewing = ref(null)
const reasonFor = ref(null)
const confirmApprove = ref(null)
const reason = ref('')
const reasonError = ref(false)

const day = (d) => (d ? String(d).slice(0, 10) : '—')
const color = (st) => ({ pending_approval: 'warning', approved: 'info', part_paid: 'primary', paid: 'success', rejected: 'danger', cancelled: 'secondary' }[st] || 'secondary')
const isLate = (b) => ['approved', 'part_paid'].includes(b.status) && b.due_date && String(b.due_date).slice(0, 10) < today()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await purchasing.bills({
      page: page.value, search: filters.search || undefined, supplier_id: filters.supplier_id || undefined,
      status: filters.status || undefined, overdue: filters.overdue ? 1 : undefined,
    })
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

function openNew(supplierId = '') { editing.value = null; presetSupplierId.value = supplierId; showForm.value = true }
async function openEdit(b) {
  try { editing.value = await purchasing.bill(b.id); showForm.value = true } catch (e) { error.value = apiError(e, t('purchasing.common.loadFailed')) }
}
async function openView(b) {
  try { viewing.value = await purchasing.bill(b.id) } catch (e) { error.value = apiError(e, t('purchasing.common.loadFailed')) }
}

function onSaved(saved) {
  const wasNew = !editing.value
  if (wasNew && saved?.id) emit('created', saved)
  note.value = access.can.bill_approve ? '' : t('purchasing.bill.submittedNote')
  load()
  emit('changed')
}

async function doApprove() {
  const b = confirmApprove.value
  if (!b) return
  await approve(b)
  confirmApprove.value = null
}

async function approve(b) {
  busy.value = true
  error.value = ''
  try {
    await purchasing.approveBill(b.id)
    note.value = t('purchasing.bill.approvedNote')
    await load()
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    busy.value = false
  }
}

function askReason(action, bill) { reasonFor.value = { action, bill }; reason.value = ''; reasonError.value = false }

async function confirmReason() {
  if (!reason.value.trim()) { reasonError.value = true; return }
  busy.value = true
  error.value = ''
  try {
    const { action, bill } = reasonFor.value
    if (action === 'reject') await purchasing.rejectBill(bill.id, reason.value.trim())
    else await purchasing.cancelBill(bill.id, reason.value.trim())
    reasonFor.value = null
    note.value = ''
    await load()
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
  try { suppliers.value = (await purchasing.suppliers({ usable: 1, per_page: 500 })).data } catch { /* picker stays empty */ }
  try {
    const { data } = await api.get('/expense-categories')
    expenseCategories.value = data.data || data
  } catch { /* the required field will explain */ }
  applyPreset()
})

// ── Print view: the table as it is on screen, with the school's header ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('purchasing.bill.number'), value: (r) => r.bill_number },
  { label: t('purchasing.bill.supplier'), value: (r) => r.supplier?.name },
  { label: t('purchasing.bill.billDate'), value: (r) => day(r.bill_date) },
  { label: t('purchasing.bill.dueDate'), value: (r) => day(r.due_date) },
  { label: t('purchasing.bill.total'), value: (r) => formatMoney(r.total_cents), align: 'end' },
  { label: t('purchasing.bill.paid'), value: (r) => formatMoney(r.paid_cents), align: 'end' },
  { label: t('purchasing.bill.balance'), value: (r) => formatMoney(r.total_cents - r.paid_cents), align: 'end' },
  { label: t('purchasing.common.status'), value: (r) => t('purchasing.bill.status.' + r.status) },
])
const printLines = computed(() => {
  const lines = []
  const who = suppliers.value.find((x) => String(x.id) === String(filters.supplier_id))?.name
  if (who) lines.push(t('purchasing.bill.supplier') + ': ' + who)
  if (filters.status) lines.push(t('purchasing.common.status') + ': ' + t('purchasing.bill.status.' + filters.status))
  if (filters.search) lines.push(t('purchasing.common.search') + ': ' + filters.search)
  return lines
})
</script>
