<template>
  <CModal :visible="visible" size="xl" scrollable class="modal-fullscreen-sm-down" @close="close">
    <CModalHeader>
      <CModalTitle>
        {{ s?.name }}
        <small class="text-muted ms-2">{{ s?.code }}</small>
        <CBadge v-if="s && s.status !== 'active'" :color="s.status === 'on_hold' ? 'warning' : 'secondary'" class="ms-2">
          {{ t('purchasing.supplier.status.' + s.status) }}
        </CBadge>
      </CModalTitle>
    </CModalHeader>
    <CModalBody>
      <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
      <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
      <template v-else-if="s">
        <CNav variant="pills" class="mb-3 flex-wrap">
          <CNavItem v-for="tab in tabs" :key="tab">
            <CNavLink :active="active === tab" @click="switchTab(tab)">{{ t('purchasing.supplier.detail.' + tab) }}</CNavLink>
          </CNavItem>
        </CNav>

        <!-- Overview -->
        <div v-if="active === 'overview'">
          <CRow class="g-2 mb-3">
            <CCol v-for="c in summaryCards" :key="c.label" xs="6" md="3">
              <div class="border rounded p-2 h-100">
                <div class="small text-muted">{{ c.label }}</div>
                <div class="fw-bold" :class="c.cls">{{ c.value }}</div>
              </div>
            </CCol>
          </CRow>
          <CRow class="g-3 small">
            <CCol v-for="f in facts" :key="f.label" md="4">
              <div class="text-muted">{{ f.label }}</div>
              <div class="fw-semibold">{{ f.value || '—' }}</div>
            </CCol>
          </CRow>
        </div>

        <!-- Bills -->
        <div v-else-if="active === 'bills'">
          <CTable small responsive hover class="sup-grid align-middle">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell>{{ t('purchasing.bill.number') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.bill.billDate') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.bill.dueDate') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.bill.total') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.bill.balance') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody>
              <CTableRow v-for="b in bills" :key="b.id">
                <CTableDataCell>{{ b.bill_number }}</CTableDataCell>
                <CTableDataCell>{{ fmtDate(b.bill_date) }}</CTableDataCell>
                <CTableDataCell>{{ fmtDate(b.due_date) }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(b.total_cents) }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(b.total_cents - b.paid_cents) }}</CTableDataCell>
                <CTableDataCell><CBadge :color="billColor(b.status)">{{ t('purchasing.bill.status.' + b.status) }}</CBadge></CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!bills.length"><CTableDataCell colspan="6" class="text-center text-muted">{{ t('purchasing.bill.noBills') }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </div>

        <!-- Payments -->
        <div v-else-if="active === 'payments'">
          <CTable small responsive hover class="sup-grid align-middle">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell>{{ t('purchasing.common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.payment.method') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.reference') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.common.amount') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody>
              <CTableRow v-for="p in payments" :key="p.id">
                <CTableDataCell>{{ fmtDate(p.payment_date) }}</CTableDataCell>
                <CTableDataCell>{{ t('purchasing.methods.' + p.method) }}</CTableDataCell>
                <CTableDataCell>{{ p.reference || '—' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(p.amount_cents) }}</CTableDataCell>
                <CTableDataCell><CBadge :color="payColor(p.status)">{{ t('purchasing.payment.status.' + p.status) }}</CBadge></CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!payments.length"><CTableDataCell colspan="5" class="text-center text-muted">{{ t('purchasing.payment.noPayments') }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </div>

        <!-- Statement -->
        <div v-else-if="active === 'statement'">
          <div class="d-flex flex-wrap gap-2 align-items-end mb-3">
            <div><label class="form-label small mb-0">{{ t('purchasing.common.from') }}</label><CFormInput v-model="stFrom" type="date" size="sm" @change="loadStatement" /></div>
            <div><label class="form-label small mb-0">{{ t('purchasing.common.to') }}</label><CFormInput v-model="stTo" type="date" size="sm" @change="loadStatement" /></div>
            <CButton size="sm" color="primary" variant="outline" :disabled="pdfBusy" @click="downloadPdf">{{ t('purchasing.supplier.detail.statementPdf') }}</CButton>
          </div>
          <CTable small responsive class="sup-grid align-middle">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell>{{ t('purchasing.common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.reference') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.supplier.detail.billed') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.supplier.detail.paid') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.bill.balance') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody v-if="statement">
              <CTableRow class="table-light"><CTableDataCell colspan="4">{{ t('purchasing.supplier.detail.opening') }}</CTableDataCell><CTableDataCell class="text-end">{{ formatMoney(statement.opening_balance_cents) }}</CTableDataCell></CTableRow>
              <CTableRow v-for="(l, i) in statement.lines" :key="i">
                <CTableDataCell>{{ fmtDate(l.date) }}</CTableDataCell>
                <CTableDataCell>{{ l.ref || '—' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ l.debit_cents ? formatMoney(l.debit_cents) : '' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ l.credit_cents ? formatMoney(l.credit_cents) : '' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(l.balance_cents) }}</CTableDataCell>
              </CTableRow>
              <CTableRow class="fw-bold"><CTableDataCell colspan="4">{{ t('purchasing.supplier.detail.closing') }}</CTableDataCell><CTableDataCell class="text-end">{{ formatMoney(statement.closing_balance_cents) }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </div>

        <!-- Documents -->
        <div v-else-if="active === 'documents'">
          <CTable small responsive class="sup-grid align-middle">
            <CTableBody>
              <CTableRow v-for="d in documents" :key="d.id">
                <CTableDataCell>{{ t('purchasing.doc.types.' + d.type) }}<div class="small text-muted">{{ d.title || d.reference_number }}</div></CTableDataCell>
                <CTableDataCell>{{ d.expiry_date ? fmtDate(d.expiry_date) : t('purchasing.doc.noExpiry') }}</CTableDataCell>
                <CTableDataCell><CBadge :color="docColor(d.status)">{{ t('purchasing.doc.status.' + d.status) }}</CBadge></CTableDataCell>
                <CTableDataCell><a v-if="d.file_url" :href="d.file_url" target="_blank" rel="noopener">{{ t('purchasing.doc.open') }}</a></CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!documents.length"><CTableDataCell class="text-center text-muted">{{ t('purchasing.doc.noDocs') }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </template>
    </CModalBody>
    <CModalFooter><CButton color="secondary" @click="close">{{ t('purchasing.common.close') }}</CButton></CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, downloadFile } from '@/services/purchasing'

const props = defineProps({ visible: Boolean, supplierId: { type: Number, default: null } })
const emit = defineEmits(['update:visible', 'changed'])
const { t } = useI18n()

const tabs = ['overview', 'bills', 'payments', 'statement', 'documents']
const active = ref('overview')
const s = ref(null)
const loading = ref(false)
const error = ref('')
const bills = ref([])
const payments = ref([])
const documents = ref([])
const statement = ref(null)
const stFrom = ref('')
const stTo = ref('')
const pdfBusy = ref(false)

const fmtDate = (d) => (d ? String(d).slice(0, 10) : '—')
const billColor = (st) => ({ pending_approval: 'warning', approved: 'info', part_paid: 'primary', paid: 'success', rejected: 'danger', cancelled: 'secondary' }[st] || 'secondary')
const payColor = (st) => ({ pending_approval: 'warning', approved: 'success', rejected: 'danger', void: 'secondary' }[st] || 'secondary')
const docColor = (st) => ({ valid: 'success', expiring: 'warning', expired: 'danger' }[st] || 'secondary')

const summaryCards = computed(() => {
  const x = s.value?.summary || {}
  return [
    { label: t('purchasing.supplier.balance'), value: formatMoney(s.value?.balance_cents), cls: s.value?.balance_cents > 0 ? 'text-danger' : 'text-success' },
    { label: t('purchasing.supplier.overdue'), value: formatMoney(x.overdue_cents), cls: x.overdue_cents > 0 ? 'text-danger' : '' },
    { label: t('purchasing.supplier.detail.totalBilled'), value: formatMoney(x.total_billed_cents) },
    { label: t('purchasing.supplier.detail.totalPaid'), value: formatMoney(x.total_paid_cents) },
    { label: t('purchasing.supplier.detail.openBills'), value: x.open_bills ?? 0 },
    { label: t('purchasing.supplier.detail.pendingBills'), value: x.pending_bills ?? 0 },
    { label: t('purchasing.supplier.detail.pendingPayments'), value: x.pending_payments ?? 0 },
    { label: t('purchasing.supplier.detail.expiringDocs'), value: x.expiring_documents ?? 0 },
  ]
})

const facts = computed(() => {
  const v = s.value || {}
  return [
    { label: t('purchasing.supplier.category'), value: v.category?.label },
    { label: t('purchasing.supplier.contactName'), value: [v.contact_name, v.contact_title].filter(Boolean).join(' — ') },
    { label: t('purchasing.supplier.phone'), value: [v.phone, v.alt_phone].filter(Boolean).join(' / ') },
    { label: t('purchasing.supplier.email'), value: v.email },
    { label: t('purchasing.supplier.address'), value: [v.address, v.district, v.region].filter(Boolean).join(', ') },
    { label: t('purchasing.supplier.tin'), value: v.tin },
    { label: t('purchasing.supplier.vrn'), value: v.vrn },
    { label: t('purchasing.supplier.bankName'), value: [v.bank_name, v.bank_account_number].filter(Boolean).join(' · ') },
    { label: t('purchasing.supplier.mobileNumber'), value: v.mobile_money_number ? `${v.mobile_money_number} (${v.mobile_money_provider ? t('purchasing.methods.' + v.mobile_money_provider) : ''})` : '' },
    { label: t('purchasing.supplier.preferredMethod'), value: v.preferred_method ? t('purchasing.methods.' + v.preferred_method) : '' },
    { label: t('purchasing.supplier.paymentTermsDays'), value: v.payment_terms_days },
    { label: t('purchasing.supplier.creditLimit'), value: v.credit_limit_cents ? formatMoney(v.credit_limit_cents) : '' },
  ]
})

watch(() => [props.visible, props.supplierId], async ([open, id]) => {
  if (!open || !id) return
  active.value = 'overview'
  bills.value = []; payments.value = []; documents.value = []; statement.value = null
  stFrom.value = ''; stTo.value = ''
  loading.value = true
  error.value = ''
  try { s.value = await purchasing.supplier(id) } catch (e) { error.value = apiError(e, t('purchasing.common.loadFailed')) } finally { loading.value = false }
})

async function switchTab(tab) {
  active.value = tab
  const id = props.supplierId
  try {
    if (tab === 'bills' && !bills.value.length) bills.value = (await purchasing.bills({ supplier_id: id, per_page: 100 })).data
    if (tab === 'payments' && !payments.value.length) payments.value = (await purchasing.payments({ supplier_id: id, per_page: 100 })).data
    if (tab === 'documents' && !documents.value.length) documents.value = await purchasing.documents({ supplier_id: id })
    if (tab === 'statement' && !statement.value) await loadStatement()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  }
}

async function loadStatement() {
  statement.value = await purchasing.statement(props.supplierId, { from: stFrom.value || undefined, to: stTo.value || undefined })
}

async function downloadPdf() {
  pdfBusy.value = true
  try {
    await downloadFile(`/suppliers/${props.supplierId}/statement/pdf`, `supplier-statement-${s.value.code}.pdf`, { from: stFrom.value || undefined, to: stTo.value || undefined })
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  } finally {
    pdfBusy.value = false
  }
}

function close() { emit('update:visible', false); emit('changed') }
</script>
