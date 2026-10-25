<template>
  <div>
    <CNav variant="pills" class="mb-3">
      <CNavItem v-for="r in reports" :key="r">
        <CNavLink :active="active === r" @click="select(r)">{{ t('purchasing.reports.' + r) }}</CNavLink>
      </CNavItem>
    </CNav>

    <Teleport to="#sup-toolbar" defer>
      <CButton color="secondary" variant="outline" size="sm" :disabled="!data" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
    </Teleport>

    <!-- the date range goes into the shared tab row, after Settings -->
    <Teleport v-if="active !== 'aging'" to="#sup-toolbar" defer>
      <label class="small mb-0 text-nowrap">{{ t('purchasing.common.from') }}</label>
      <CFormInput v-model="from" type="date" size="sm" style="width:150px;" />
      <label class="small mb-0 text-nowrap">{{ t('purchasing.common.to') }}</label>
      <CFormInput v-model="to" type="date" size="sm" style="width:150px;" />
      <CButton size="sm" color="primary" @click="load">{{ t('purchasing.reports.run') }}</CButton>
    </Teleport>

    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <!-- Aging -->
    <CCard v-else-if="active === 'aging' && data">
      <CCardBody class="p-0">
        <CTable responsive class="sup-grid mb-0 align-middle">
          <CTableHead class="table-light"><CTableRow>
            <CTableHeaderCell>{{ t('purchasing.reports.supplier') }}</CTableHeaderCell>
            <CTableHeaderCell v-for="b in buckets" :key="b" class="text-end">{{ t('purchasing.reports.' + b) }}</CTableHeaderCell>
            <CTableHeaderCell class="text-end">{{ t('purchasing.common.total') }}</CTableHeaderCell>
          </CTableRow></CTableHead>
          <CTableBody>
            <CTableRow v-for="r in data.suppliers" :key="r.supplier_id">
              <CTableDataCell>{{ r.supplier }}</CTableDataCell>
              <CTableDataCell v-for="b in buckets" :key="b" class="text-end" :class="b !== 'current' && r[b] > 0 ? 'text-danger' : ''">{{ r[b] ? formatMoney(r[b]) : '—' }}</CTableDataCell>
              <CTableDataCell class="text-end fw-bold">{{ formatMoney(r.total_cents) }}</CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!data.suppliers.length"><CTableDataCell :colspan="buckets.length + 2" class="text-center text-muted py-4">{{ t('purchasing.common.noData') }}</CTableDataCell></CTableRow>
            <CTableRow v-else class="fw-bold table-light">
              <CTableDataCell>{{ t('purchasing.common.total') }}</CTableDataCell>
              <CTableDataCell v-for="b in buckets" :key="b" class="text-end">{{ formatMoney(data.totals[b]) }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ formatMoney(data.totals.total_cents) }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Spend -->
    <div v-else-if="active === 'spend' && data">
      <div class="fs-5 fw-bold mb-3">{{ t('purchasing.common.total') }}: {{ formatMoney(data.total_cents) }}</div>
      <CRow class="g-3">
        <CCol lg="6">
          <CCard><CCardHeader>{{ t('purchasing.reports.bySupplier') }}</CCardHeader><CCardBody class="p-0">
            <CTable small class="sup-grid mb-0"><CTableBody>
              <CTableRow v-for="r in data.by_supplier" :key="r.supplier_id">
                <CTableDataCell>{{ r.supplier }}<div class="small text-muted">{{ r.bills }} {{ t('purchasing.reports.bills') }}</div></CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(r.total_cents) }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!data.by_supplier.length"><CTableDataCell class="text-center text-muted">{{ t('purchasing.common.noData') }}</CTableDataCell></CTableRow>
            </CTableBody></CTable>
          </CCardBody></CCard>
        </CCol>
        <CCol lg="6">
          <CCard class="mb-3"><CCardHeader>{{ t('purchasing.reports.byCategory') }}</CCardHeader><CCardBody class="p-0">
            <CTable small class="sup-grid mb-0"><CTableBody>
              <CTableRow v-for="r in data.by_category" :key="r.category">
                <CTableDataCell>{{ r.category || t('purchasing.reports.uncategorised') }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(r.total_cents) }}</CTableDataCell>
              </CTableRow>
            </CTableBody></CTable>
          </CCardBody></CCard>
          <CCard><CCardHeader>{{ t('purchasing.reports.byMonth') }}</CCardHeader><CCardBody class="p-0">
            <CTable small class="sup-grid mb-0"><CTableBody>
              <CTableRow v-for="r in data.by_month" :key="r.month">
                <CTableDataCell>{{ r.month }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(r.total_cents) }}</CTableDataCell>
              </CTableRow>
            </CTableBody></CTable>
          </CCardBody></CCard>
        </CCol>
      </CRow>
    </div>

    <!-- Tax -->
    <div v-else-if="active === 'tax' && data">
      <CCard class="mb-3">
        <CCardHeader>{{ t('purchasing.reports.vatPaid') }} — {{ t('purchasing.reports.totalVat') }}: <strong>{{ formatMoney(data.total_vat_cents) }}</strong></CCardHeader>
        <CCardBody class="p-0">
          <CTable small responsive class="sup-grid mb-0">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell>{{ t('purchasing.common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.tin') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.billNumber') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.reports.subtotal') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.reports.vat') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody>
              <CTableRow v-for="(r, i) in data.vat" :key="i">
                <CTableDataCell>{{ r.date }}</CTableDataCell><CTableDataCell>{{ r.supplier }}</CTableDataCell><CTableDataCell>{{ r.tin || '—' }}</CTableDataCell>
                <CTableDataCell>{{ r.bill_number }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(r.subtotal_cents) }}</CTableDataCell><CTableDataCell class="text-end">{{ formatMoney(r.vat_cents) }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!data.vat.length"><CTableDataCell colspan="6" class="text-center text-muted">{{ t('purchasing.common.noData') }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>
      <CCard>
        <CCardHeader>{{ t('purchasing.reports.whtWithheld') }} — {{ t('purchasing.reports.totalWht') }}: <strong>{{ formatMoney(data.total_wht_cents) }}</strong></CCardHeader>
        <CCardBody class="p-0">
          <CTable small responsive class="sup-grid mb-0">
            <CTableHead class="table-light"><CTableRow>
              <CTableHeaderCell>{{ t('purchasing.common.date') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.tin') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.reports.efd') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.reports.paymentAmount') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.reports.wht') }}</CTableHeaderCell>
            </CTableRow></CTableHead>
            <CTableBody>
              <CTableRow v-for="(r, i) in data.wht" :key="i">
                <CTableDataCell>{{ r.date }}</CTableDataCell><CTableDataCell>{{ r.supplier }}</CTableDataCell><CTableDataCell>{{ r.tin || '—' }}</CTableDataCell>
                <CTableDataCell>{{ r.efd_number || '—' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ formatMoney(r.amount_cents) }}</CTableDataCell><CTableDataCell class="text-end">{{ formatMoney(r.wht_cents) }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!data.wht.length"><CTableDataCell colspan="6" class="text-center text-muted">{{ t('purchasing.common.noData') }}</CTableDataCell></CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>
    </div>

    <PrintSheet v-model="printing" :title="printModel.title" :lines="printModel.lines" :columns="printModel.columns" :rows="printModel.rows" :footer="printModel.footer" landscape />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError } from '@/services/purchasing'

const { t } = useI18n()

const reports = ['aging', 'spend', 'tax']
const buckets = ['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus']
const active = ref('aging')
const data = ref(null)
const loading = ref(false)
const error = ref('')
const from = ref('')
const to = ref('')

function select(r) { active.value = r; data.value = null; load() }

async function load() {
  loading.value = true
  error.value = ''
  try {
    const params = { from: from.value || undefined, to: to.value || undefined }
    data.value = active.value === 'aging' ? await purchasing.aging()
      : active.value === 'spend' ? await purchasing.spend(params)
        : await purchasing.tax(params)
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ── Print view of the report on screen (all its tables, one after the other) ──
const printing = ref(false)
const printModel = computed(() => {
  const d = data.value
  const money = (c) => formatMoney(c)
  const period = active.value === 'aging' ? [] : [`${t('purchasing.common.from')} ${from.value || '—'}  ${t('purchasing.common.to')} ${to.value || '—'}`]
  const title = t('purchasing.reports.' + active.value)
  if (!d) return { title, lines: period, columns: [{ label: title, value: () => '' }], rows: [], footer: [] }

  if (active.value === 'aging') {
    return {
      title, lines: period,
      columns: [
        { label: t('purchasing.reports.supplier'), value: (r) => r.supplier },
        ...buckets.map((b) => ({ label: t('purchasing.reports.' + b), value: (r) => (r[b] ? money(r[b]) : '—'), align: 'end' })),
        { label: t('purchasing.common.total'), value: (r) => money(r.total_cents), align: 'end', bold: true },
      ],
      rows: d.suppliers,
      footer: [[t('purchasing.common.total'), ...buckets.map((b) => money(d.totals[b])), money(d.totals.total_cents)]],
    }
  }
  if (active.value === 'spend') {
    return {
      title, lines: [...period, `${t('purchasing.common.total')}: ${money(d.total_cents)}`],
      columns: [
        { label: t('purchasing.reports.group'), value: (r) => r.group },
        { label: t('purchasing.reports.item'), value: (r) => r.item },
        { label: t('purchasing.common.amount'), value: (r) => money(r.cents), align: 'end' },
      ],
      rows: [
        ...d.by_supplier.map((r) => ({ group: t('purchasing.reports.bySupplier'), item: r.supplier, cents: r.total_cents })),
        ...d.by_category.map((r) => ({ group: t('purchasing.reports.byCategory'), item: r.category || t('purchasing.reports.uncategorised'), cents: r.total_cents })),
        ...d.by_month.map((r) => ({ group: t('purchasing.reports.byMonth'), item: r.month, cents: r.total_cents })),
      ],
      footer: [],
    }
  }
  // tax: the VAT paid on bills, then the tax withheld from payments
  return {
    title, lines: [...period, `${t('purchasing.reports.totalVat')}: ${money(d.total_vat_cents)}`, `${t('purchasing.reports.totalWht')}: ${money(d.total_wht_cents)}`],
    columns: [
      { label: t('purchasing.reports.kind'), value: (r) => r.kind },
      { label: t('purchasing.common.date'), value: (r) => r.date },
      { label: t('purchasing.reports.supplier'), value: (r) => r.supplier },
      { label: t('purchasing.reports.tin'), value: (r) => r.tin },
      { label: t('purchasing.reports.billNumber') + ' / ' + t('purchasing.reports.efd'), value: (r) => r.ref },
      { label: t('purchasing.reports.subtotal') + ' / ' + t('purchasing.reports.paymentAmount'), value: (r) => money(r.base), align: 'end' },
      { label: t('purchasing.reports.vat') + ' / ' + t('purchasing.reports.wht'), value: (r) => money(r.tax), align: 'end' },
    ],
    rows: [
      ...d.vat.map((r) => ({ kind: t('purchasing.reports.vat'), date: r.date, supplier: r.supplier, tin: r.tin, ref: r.bill_number, base: r.subtotal_cents, tax: r.vat_cents })),
      ...d.wht.map((r) => ({ kind: t('purchasing.reports.wht'), date: r.date, supplier: r.supplier, tin: r.tin, ref: r.efd_number, base: r.amount_cents, tax: r.wht_cents })),
    ],
    footer: [],
  }
})
</script>
