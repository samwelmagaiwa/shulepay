<template>
  <div>
    <Teleport to="#sup-toolbar" defer>
        <CButton color="secondary" variant="outline" style="min-height:40px;" :title="t('printing.print')" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormInput v-model="filters.search" :placeholder="t('purchasing.receipt.searchPlaceholder')" style="max-width:200px;min-height:40px;" @input="debouncedReload" />
        <CFormSelect v-model="filters.supplier_id" style="max-width:220px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.common.all') }} — {{ t('purchasing.receipt.supplier') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CButton color="success" style="min-height:40px;" @click="openNew()">
          <CIcon icon="cilPlus" class="me-1" />{{ t('purchasing.receipt.add') }}
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
              <CTableHeaderCell>{{ t('purchasing.receipt.number') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.receipt.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.receipt.date') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.receipt.deliveryNote') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.receipt.lines') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(r, i) in rows" :key="r.id">
              <CTableDataCell><a href="#" class="fw-semibold text-decoration-none" @click.prevent="openView(r)">{{ r.grn_number }}</a></CTableDataCell>
              <CTableDataCell>{{ r.supplier?.name }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">{{ String(r.receipt_date).slice(0, 10) }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">{{ r.delivery_note_no || '—' }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ r.items_count }}</CTableDataCell>
              <CTableDataCell><CBadge :color="r.status === 'received' ? 'success' : 'secondary'">{{ r.status === 'received' ? t('purchasing.receipt.received') : t('purchasing.receipt.voided') }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown :placement="menuPlacement(i, rows.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('purchasing.common.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem @click="openView(r)">{{ t('purchasing.common.view') }}</CDropdownItem>
                    <CDropdownItem v-if="r.status === 'received' && access.can.grn_void" @click="askVoid(r)">{{ t('purchasing.receipt.void') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!rows.length"><CTableDataCell colspan="7" class="text-center text-muted py-4">{{ t('purchasing.receipt.noReceipts') }}</CTableDataCell></CTableRow>
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

    <!-- New receipt -->
    <CModal :visible="showForm" size="lg" scrollable backdrop="static" class="modal-fullscreen-sm-down" @close="showForm = false">
      <CModalHeader><CModalTitle>{{ t('purchasing.receipt.add') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
        <div v-if="!stockItems.length" class="alert alert-warning py-2 small">{{ t('purchasing.receipt.noItems') }}</div>
        <CRow class="g-3 mb-3">
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('purchasing.receipt.supplier') }} *</label>
            <CFormSelect v-model="form.supplier_id" :invalid="!!errs.supplier_id">
              <option value="">—</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('purchasing.receipt.date') }} *</label>
            <CFormInput v-model="form.receipt_date" type="date" :max="todayStr" />
          </CCol>
          <CCol md="6"><label class="form-label">{{ t('purchasing.receipt.deliveryNote') }}</label><CFormInput v-model="form.delivery_note_no" /></CCol>
          <CCol md="6">
            <label class="form-label">{{ t('purchasing.receipt.linkBill') }}</label>
            <CFormSelect v-model="form.supplier_bill_id">
              <option value="">—</option>
              <option v-for="b in bills" :key="b.id" :value="b.id">{{ b.bill_number }}</option>
            </CFormSelect>
          </CCol>
        </CRow>

        <h6 class="text-uppercase text-muted small">{{ t('purchasing.receipt.items') }}</h6>
        <div v-if="errs.items" class="text-danger small mb-1">{{ errs.items }}</div>
        <table class="sup-grid table table-sm align-middle">
          <thead class="table-light"><tr>
            <th>{{ t('purchasing.receipt.item') }}</th><th style="width:100px">{{ t('purchasing.receipt.quantity') }}</th>
            <th style="width:160px">{{ t('purchasing.receipt.unitCost') }}</th><th style="width:40px"></th>
          </tr></thead>
          <tbody>
            <tr v-for="(l, i) in form.items" :key="i">
              <td>
                <CFormSelect v-model="l.inventory_item_id" size="sm">
                  <option value="">—</option>
                  <option v-for="it in stockItems" :key="it.id" :value="it.id">{{ it.name }}{{ it.unit ? ' (' + it.unit + ')' : '' }}</option>
                </CFormSelect>
              </td>
              <td><CFormInput v-model="l.quantity" type="number" min="0.01" step="any" size="sm" /></td>
              <td><CFormInput v-model="l.unit_cost" type="number" min="0" step="any" size="sm" /></td>
              <td><CButton size="sm" color="danger" variant="ghost" :disabled="form.items.length === 1" :aria-label="t('purchasing.common.remove')" @click="form.items.splice(i, 1)">✕</CButton></td>
            </tr>
          </tbody>
        </table>
        <CButton size="sm" color="secondary" variant="outline" @click="form.items.push({ inventory_item_id: '', quantity: 1, unit_cost: '' })">+ {{ t('purchasing.receipt.addLine') }}</CButton>
        <div class="small text-muted mt-2">{{ t('purchasing.receipt.stockNote') }}</div>
        <div class="mt-3"><label class="form-label">{{ t('purchasing.common.notes') }}</label><CFormTextarea v-model="form.notes" rows="2" /></div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showForm = false">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save"><CSpinner v-if="saving" size="sm" class="me-1" />{{ t('purchasing.common.save') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Details -->
    <CModal :visible="!!viewing" scrollable @close="viewing = null">
      <CModalHeader><CModalTitle>{{ viewing?.grn_number }} — {{ viewing?.supplier?.name }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewing">
        <div class="small text-muted mb-2">{{ String(viewing.receipt_date).slice(0, 10) }} · {{ viewing.delivery_note_no }}<span v-if="viewing.bill"> · {{ viewing.bill.bill_number }}</span></div>
        <CTable class="sup-grid" small>
          <CTableBody>
            <CTableRow v-for="i in viewing.items" :key="i.id">
              <CTableDataCell>{{ i.inventory_item?.name }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ Number(i.quantity) }} {{ i.inventory_item?.unit }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ i.unit_cost_cents ? formatMoney(i.unit_cost_cents) : '' }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <div v-if="viewing.status === 'void'" class="text-danger small">{{ viewing.void_reason }}</div>
      </CModalBody>
      <CModalFooter><CButton color="secondary" @click="viewing = null">{{ t('purchasing.common.close') }}</CButton></CModalFooter>
    </CModal>

    <!-- Void -->
    <CModal :visible="!!voiding" @close="voiding = null">
      <CModalHeader><CModalTitle>{{ t('purchasing.receipt.void') }} — {{ voiding?.grn_number }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="small text-muted">{{ t('purchasing.receipt.voidHelp') }}</p>
        <label class="form-label fw-semibold">{{ t('purchasing.common.reason') }} *</label>
        <CFormInput v-model="reason" :invalid="reasonError" />
        <div v-if="reasonError" class="text-danger small">{{ t('purchasing.common.reasonRequired') }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="voiding = null">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="confirmVoid">{{ t('purchasing.common.confirm') }}</CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('purchasing.tabs.receipts')" :lines="printLines" :columns="printColumns" :rows="rows" />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, reactive, onMounted, watch, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, toCents, today, access } from '@/services/purchasing'

const props = defineProps({ preset: { type: Object, default: null } })
const emit = defineEmits(['changed'])
const { t } = useI18n()

const todayStr = today()
const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const suppliers = ref([])
const stockItems = ref([])
const bills = ref([])
const loading = ref(false)
const error = ref('')
const note = ref('')
const saving = ref(false)
const page = ref(1)
const filters = reactive({ search: '', supplier_id: '' })

const showForm = ref(false)
const form = reactive({ supplier_id: '', receipt_date: todayStr, delivery_note_no: '', supplier_bill_id: '', notes: '', items: [] })
const errs = reactive({})
const formError = ref('')
const viewing = ref(null)
const voiding = ref(null)
const reason = ref('')
const reasonError = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await purchasing.receipts({ page: page.value, search: filters.search || undefined, supplier_id: filters.supplier_id || undefined })
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

async function loadBills() {
  form.supplier_bill_id = ''
  bills.value = []
  if (!form.supplier_id) return
  try { bills.value = (await purchasing.bills({ supplier_id: form.supplier_id, per_page: 100 })).data } catch { /* the link is optional */ }
}

async function openNew(supplierId = '') {
  Object.keys(errs).forEach(k => delete errs[k])
  formError.value = ''
  Object.assign(form, { supplier_id: supplierId ? String(supplierId) : '', receipt_date: todayStr, delivery_note_no: '', supplier_bill_id: '', notes: '', items: [{ inventory_item_id: '', quantity: 1, unit_cost: '' }] })
  showForm.value = true
  if (!stockItems.value.length) {
    try { stockItems.value = await purchasing.stockItems() } catch { stockItems.value = [] }
  }
}

async function openView(r) {
  try { viewing.value = await purchasing.receipt(r.id) } catch (e) { error.value = apiError(e, t('purchasing.common.loadFailed')) }
}

async function save() {
  Object.keys(errs).forEach(k => delete errs[k])
  if (!form.supplier_id) errs.supplier_id = t('purchasing.common.fixErrors')
  if (form.items.some(l => !l.inventory_item_id || !(Number(l.quantity) > 0))) errs.items = t('purchasing.common.fixErrors')
  if (Object.keys(errs).length) return
  saving.value = true
  formError.value = ''
  try {
    await purchasing.createReceipt({
      supplier_id: Number(form.supplier_id),
      receipt_date: form.receipt_date,
      delivery_note_no: form.delivery_note_no || null,
      supplier_bill_id: form.supplier_bill_id || null,
      notes: form.notes || null,
      items: form.items.map(l => ({
        inventory_item_id: Number(l.inventory_item_id), quantity: Number(l.quantity), unit_cost_cents: l.unit_cost === '' ? 0 : toCents(l.unit_cost),
      })),
    })
    showForm.value = false
    note.value = t('purchasing.receipt.saved')
    await load()
    emit('changed')
  } catch (e) {
    formError.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

function askVoid(r) { voiding.value = r; reason.value = ''; reasonError.value = false }

async function confirmVoid() {
  if (!reason.value.trim()) { reasonError.value = true; return }
  saving.value = true
  try {
    await purchasing.voidReceipt(voiding.value.id, reason.value.trim())
    voiding.value = null
    note.value = ''
    await load()
    emit('changed')
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
    voiding.value = null
  } finally {
    saving.value = false
  }
}

watch(() => form.supplier_id, () => loadBills())

function applyPreset() {
  if (props.preset?.openNew) openNew(props.preset.supplier?.id || '')
}
watch(() => props.preset, applyPreset)

onMounted(async () => {
  load()
  try { suppliers.value = (await purchasing.suppliers({ usable: 1, per_page: 500 })).data } catch { /* picker stays empty */ }
  applyPreset()
})

// ── Print view: the table as it is on screen, with the school's header ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('purchasing.receipt.number'), value: (r) => r.grn_number },
  { label: t('purchasing.receipt.supplier'), value: (r) => r.supplier?.name },
  { label: t('purchasing.receipt.date'), value: (r) => String(r.receipt_date).slice(0, 10) },
  { label: t('purchasing.receipt.deliveryNote'), value: (r) => r.delivery_note_no },
  { label: t('purchasing.receipt.lines'), value: (r) => r.items_count, align: 'end' },
  { label: t('purchasing.common.status'), value: (r) => (r.status === 'received' ? t('purchasing.receipt.received') : t('purchasing.receipt.voided')) },
])
const printLines = computed(() => {
  const lines = []
  const who = suppliers.value.find((x) => String(x.id) === String(filters.supplier_id))?.name
  if (who) lines.push(t('purchasing.receipt.supplier') + ': ' + who)
  if (filters.search) lines.push(t('purchasing.common.search') + ': ' + filters.search)
  return lines
})
</script>
