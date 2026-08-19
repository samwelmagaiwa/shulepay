<template>
  <CContainer fluid class="p-2 p-md-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
      <CNav variant="tabs" class="mb-0 flex-nowrap overflow-auto">
        <CNavItem v-for="tab in tabs" :key="tab.id">
          <CNavLink :active="activeTab === tab.id" @click="activeTab = tab.id" style="cursor:pointer; white-space:nowrap;">
            {{ tab.label }}
          </CNavLink>
        </CNavItem>
      </CNav>

      <div class="d-flex align-items-center gap-2 flex-wrap ms-auto">
        <!-- Collections Filters -->
        <template v-if="activeTab === 'collections'">
          <div class="d-flex align-items-center gap-1">
            <span class="small fw-semibold text-muted text-nowrap">{{ t('reports.startDate') }}:</span>
            <CFormInput type="date" v-model="colFilters.date_from" size="sm" @change="loadCollections" style="width:140px" />
          </div>
          <div class="d-flex align-items-center gap-1">
            <span class="small fw-semibold text-muted text-nowrap">{{ t('reports.endDate') }}:</span>
            <CFormInput type="date" v-model="colFilters.date_to" size="sm" @change="loadCollections" style="width:140px" />
          </div>
          <CButton size="sm" color="primary" @click="loadCollections">{{ t('reports.view') }}</CButton>
        </template>

        <!-- VS Filters -->
        <template v-if="activeTab === 'vs'">
          <div class="d-flex align-items-center gap-1">
            <span class="small fw-semibold text-muted text-nowrap">{{ t('common.year') }}:</span>
            <CFormInput type="number" v-model.number="vsYear" size="sm" :min="2020" :max="2035" @change="loadVsData" style="width:90px" />
          </div>
          <CButton size="sm" color="primary" @click="loadVsData" :disabled="loadingVs">
            <CSpinner v-if="loadingVs" size="sm" class="me-1" />{{ t('reports.view') }}
          </CButton>
        </template>

        <!-- Print Button -->
        <CButton color="secondary" size="sm" variant="outline" @click="printReport">
          <CIcon icon="cilPrint" class="me-1" /> {{ t('reports.print') }}
        </CButton>
      </div>
    </div>

    <div class="border border-top-0 rounded-bottom p-2 p-md-3" id="print-area">

      <!-- Print-only header -->
      <div class="print-only mb-3 pb-2" style="border-bottom:2px solid #333;">
        <div class="fw-bold fs-5">ST. MAGRETH PRIMARY SCHOOL</div>
        <div class="fw-semibold">
          {{ tabs.find(t => t.id === activeTab)?.label }} — {{ t('reports.title') }}
        </div>
        <div class="text-muted small mt-1" v-if="activeTab === 'collections'">
          {{ t('reports.startDate') }}: {{ colFilters.date_from }} &nbsp;|&nbsp; {{ t('reports.endDate') }}: {{ colFilters.date_to }}
        </div>
        <div class="text-muted small mt-1" v-if="activeTab === 'vs'">{{ t('common.year') }}: {{ vsYear }}</div>
        <div class="text-muted small mt-1">{{ t('common.printedAt') }}: {{ new Date().toLocaleString() }}</div>
      </div>

      <!-- Tab: Makusanyo (Collections) -->
      <div v-if="activeTab === 'collections'">

        <div v-if="loadingCol" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3">
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ formatTZS(colStats.total_collected) }}</div>
                  <div class="text-muted small">{{ t('reports.totalCollected') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ formatTZS(colStats.total_outstanding) }}</div>
                  <div class="text-muted small">{{ t('reports.totalDebt') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6">{{ colStats.paid_count || 0 }}</div>
                  <div class="text-muted small">{{ t('reports.fullyPaid') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #ffc107 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-warning">{{ colStats.partial_count || 0 }}</div>
                  <div class="text-muted small">{{ t('reports.partialPaid') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <div v-if="colRows.length" style="position:relative; height:260px;" class="mb-3">
            <canvas ref="colChartRef"></canvas>
          </div>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.collectionsCol') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.paymentCount') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="row in colRows" :key="row.date">
                <CTableDataCell>{{ row.date }}</CTableDataCell>
                <CTableDataCell class="fw-semibold text-success">{{ formatTZS(row.amount_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell">{{ row.count }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!colRows.length">
                <CTableDataCell colspan="3" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Madeni (Debtors aging) -->
      <div v-if="activeTab === 'debtors'">
        <div v-if="loadingDebt" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3">
            <CCol xs="6" sm="3" v-for="bucket in debtBuckets" :key="bucket.label">
              <CCard class="text-center border-0 shadow-sm h-100" :style="`border-left:3px solid ${bucket.color} !important`">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6" :style="`color:${bucket.color}`">{{ formatTZS(bucket.total) }}</div>
                  <div class="text-muted small">{{ bucket.label }}</div>
                  <div class="text-muted" style="font-size:.72rem;">{{ bucket.count }} ankara</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('students.student') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('invoices.invoiceNo') }} #</CTableHeaderCell>
                <CTableHeaderCell>{{ t('invoices.balance') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.daysOverdue') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.agingStatus') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="d in debtors" :key="d.id">
                <CTableDataCell>
                  <div class="fw-semibold">{{ d.student?.full_name }}</div>
                  <div class="text-muted small">{{ d.student?.admission_number }}</div>
                </CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell small">{{ d.invoice_number }}</CTableDataCell>
                <CTableDataCell class="fw-bold text-danger">{{ formatTZS(d.balance_due_cents) }}</CTableDataCell>
                <CTableDataCell class="small">{{ d.days_overdue || 0 }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="agingColor(d.days_overdue)" shape="rounded-pill">{{ agingLabel(d.days_overdue) }}</CBadge>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!debtors.length">
                <CTableDataCell colspan="5" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Darasa (By Class) -->
      <div v-if="activeTab === 'byclass'">
        <div v-if="loadingClass" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3">
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #0d6efd !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-primary">{{ classData.length }}</div>
                  <div class="text-muted small">Idadi ya Madarasa</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #6c757d !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-secondary">{{ formatTZS(classStats.totalBilled) }}</div>
                  <div class="text-muted small">Jumla Iliyotozwa</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ formatTZS(classStats.totalCollected) }}</div>
                  <div class="text-muted small">Jumla Iliyokusanywa</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ formatTZS(classStats.totalDebt) }}</div>
                  <div class="text-muted small">Jumla ya Madeni</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <div v-if="classData.length" style="position:relative; height:260px;" class="mb-3">
            <canvas ref="classChartRef"></canvas>
          </div>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('reports.classReport.class') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.students') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.invoiced') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.collected') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.classReport.debt') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.classReport.pct') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="c in classData" :key="c.class_name">
                <CTableDataCell class="fw-semibold">{{ c.class_name }}</CTableDataCell>
                <CTableDataCell>{{ c.student_count }}</CTableDataCell>
                <CTableDataCell>{{ formatTZS(c.total_billed_cents) }}</CTableDataCell>
                <CTableDataCell class="text-success">{{ formatTZS(c.total_collected_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell text-danger">{{ formatTZS(c.total_outstanding_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell">
                  <div class="d-flex align-items-center gap-2">
                    <div class="progress flex-grow-1" style="height:6px;">
                      <div class="progress-bar bg-success" :style="`width:${collectionPct(c)}%`"></div>
                    </div>
                    <span class="small">{{ collectionPct(c) }}%</span>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!classData.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Gharama vs Makusanyo -->
      <div v-if="activeTab === 'vs'">
        <div v-if="loadingVs" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3">
            <CCol xs="6" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ formatTZS(vsStats.totalCollections) }}</div>
                  <div class="text-muted small">Jumla ya Makusanyo</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ formatTZS(vsStats.totalExpenses) }}</div>
                  <div class="text-muted small">Jumla ya Gharama</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="12" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" :style="`border-left:3px solid ${vsStats.surplus >= 0 ? '#198754' : '#dc3545'} !important`">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6" :class="vsStats.surplus >= 0 ? 'text-success' : 'text-danger'">
                    {{ formatTZS(vsStats.surplus) }}
                  </div>
                  <div class="text-muted small">Uzio wa Kifedha (Surplus / Deficit)</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>
          <div v-if="vsData.length" style="position:relative; height:260px;" class="mb-3">
            <canvas ref="vsChartRef"></canvas>
          </div>
          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('reports.month') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.collections') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.vsReport.expenses') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.vsReport.surplus') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="r in vsData" :key="r.month">
                <CTableDataCell>{{ monthName(r.month) }}</CTableDataCell>
                <CTableDataCell class="text-success">{{ formatTZS(r.collections_cents) }}</CTableDataCell>
                <CTableDataCell class="text-danger">{{ formatTZS(r.expenses_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell fw-semibold"
                  :class="(r.collections_cents - r.expenses_cents) >= 0 ? 'text-success' : 'text-danger'">
                  {{ formatTZS(r.collections_cents - r.expenses_cents) }}
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!vsData.length">
                <CTableDataCell colspan="4" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

    </div>
  </CContainer>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

let Chart = null

const activeTab = ref('collections')
const tabs = computed(() => [
  { id: 'collections', label: t('reports.collections') },
  { id: 'debtors', label: t('reports.debtors') },
  { id: 'byclass', label: t('reports.byClass') },
  { id: 'vs', label: t('reports.vsTitle') },
])

// Collections
const loadingCol = ref(false)
const colRows = ref([])
const colStats = ref({})
const colChartRef = ref(null)
let colChart = null
const colFilters = ref({
  date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
  date_to: new Date().toISOString().slice(0, 10),
})

// Debtors
const loadingDebt = ref(false)
const debtors = ref([])
const debtBuckets = computed(() => [
  { label: t('reports.agingBuckets.current'), color: '#198754', total: debtBucketTotals.value[0], count: debtBucketCounts.value[0] },
  { label: t('reports.agingBuckets.days31'),  color: '#ffc107', total: debtBucketTotals.value[1], count: debtBucketCounts.value[1] },
  { label: t('reports.agingBuckets.days61'),  color: '#fd7e14', total: debtBucketTotals.value[2], count: debtBucketCounts.value[2] },
  { label: t('reports.agingBuckets.over90'),  color: '#dc3545', total: debtBucketTotals.value[3], count: debtBucketCounts.value[3] },
])
const debtBucketTotals = ref([0, 0, 0, 0])
const debtBucketCounts = ref([0, 0, 0, 0])

// By class
const loadingClass = ref(false)
const classData = ref([])
const classChartRef = ref(null)
let classChart = null

const classStats = computed(() => {
  let totalBilled = 0
  let totalCollected = 0
  let totalDebt = 0
  classData.value.forEach(c => {
    totalBilled += c.total_billed_cents || 0
    totalCollected += c.total_collected_cents || 0
    totalDebt += c.total_outstanding_cents || 0
  })
  return { totalBilled, totalCollected, totalDebt }
})

// VS
const loadingVs = ref(false)
const vsData = ref([])
const vsYear = ref(new Date().getFullYear())
const vsChartRef = ref(null)
let vsChart = null

const vsStats = computed(() => {
  let totalCollections = 0
  let totalExpenses = 0
  vsData.value.forEach(r => {
    totalCollections += r.collections_cents || 0
    totalExpenses += r.expenses_cents || 0
  })
  return {
    totalCollections,
    totalExpenses,
    surplus: totalCollections - totalExpenses,
  }
})

function monthName(m) {
  const keys = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
  const key = keys[(m - 1) % 12]
  return key ? t(`common.months.${key}`) : String(m)
}

function formatTZS(cents) {
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}
function collectionPct(c) {
  if (!c.total_billed_cents) return 0
  return Math.round((c.total_collected_cents / c.total_billed_cents) * 100)
}
function agingLabel(days) {
  if (!days || days <= 30) return '0-30'
  if (days <= 60) return '31-60'
  if (days <= 90) return '61-90'
  return '90+'
}
function agingColor(days) {
  if (!days || days <= 30) return 'success'
  if (days <= 60) return 'warning'
  if (days <= 90) return 'dark'
  return 'danger'
}

async function initChart() {
  if (!Chart) {
    try {
      const mod = await import('chart.js/auto')
      Chart = mod.default || mod.Chart
    } catch { return null }
  }
  return Chart
}

async function loadCollections() {
  loadingCol.value = true
  try {
    const { data } = await api.get('/reports/collections', { params: colFilters.value })
    const d = data.data || data
    colRows.value = d.rows || []
    colStats.value = d.stats || {}
    await nextTick()
    if (colRows.value.length) await renderColChart()
  } catch {} finally { loadingCol.value = false }
}

async function renderColChart() {
  if (!colChartRef.value) return
  const C = await initChart()
  if (!C) return
  if (colChart) colChart.destroy()
  colChart = new C(colChartRef.value, {
    type: 'bar',
    data: {
      labels: colRows.value.map(r => r.date),
      datasets: [{ label: t('reports.collectionsCol'), data: colRows.value.map(r => Math.round((r.amount_cents || 0) / 100)), backgroundColor: '#198754' }],
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
  })
}

async function loadDebtors() {
  loadingDebt.value = true
  try {
    const { data } = await api.get('/reports/debtors')
    const d = data.data || data
    debtors.value = d.rows || d || []
    const counts = [0, 0, 0, 0]
    const totals = [0, 0, 0, 0]
    debtors.value.forEach(inv => {
      const days = inv.days_overdue || 0
      const idx = days <= 30 ? 0 : days <= 60 ? 1 : days <= 90 ? 2 : 3
      counts[idx]++
      totals[idx] += inv.balance_due_cents || 0
    })
    debtBucketCounts.value = counts
    debtBucketTotals.value = totals
  } catch {} finally { loadingDebt.value = false }
}

async function loadByClass() {
  loadingClass.value = true
  try {
    const { data } = await api.get('/reports/by-class')
    const d = data.data || data
    classData.value = d.rows || d || []
    await nextTick()
    if (classData.value.length) await renderClassChart()
  } catch {} finally { loadingClass.value = false }
}

async function renderClassChart() {
  if (!classChartRef.value) return
  const C = await initChart()
  if (!C) return
  if (classChart) classChart.destroy()
  classChart = new C(classChartRef.value, {
    type: 'doughnut',
    data: {
      labels: classData.value.map(c => c.class_name),
      datasets: [{
        data: classData.value.map(c => Math.round((c.total_collected_cents || 0) / 100)),
        backgroundColor: ['#198754','#0d6efd','#ffc107','#0dcaf0','#dc3545','#6c757d','#20c997','#fd7e14'],
      }],
    },
    options: { responsive: true, maintainAspectRatio: false },
  })
}

async function loadVsData() {
  loadingVs.value = true
  try {
    const { data } = await api.get('/reports/expenses-vs-collections', { params: { year: vsYear.value } })
    const d = data.data || data
    vsData.value = d.rows || d || []
    await nextTick()
    if (vsData.value.length) await renderVsChart()
  } catch {} finally { loadingVs.value = false }
}

async function renderVsChart() {
  if (!vsChartRef.value) return
  const C = await initChart()
  if (!C) return
  if (vsChart) vsChart.destroy()
  vsChart = new C(vsChartRef.value, {
    type: 'bar',
    data: {
      labels: vsData.value.map(r => monthName(r.month)),
      datasets: [
        { label: t('reports.collections'), data: vsData.value.map(r => Math.round((r.collections_cents || 0) / 100)), backgroundColor: '#198754' },
        { label: t('reports.vsReport.expenses'), data: vsData.value.map(r => Math.round((r.expenses_cents || 0) / 100)), backgroundColor: '#dc3545' },
      ],
    },
    options: { responsive: true, maintainAspectRatio: false },
  })
}

function printReport() {
  // ensure data is loaded before printing
  if (activeTab.value === 'collections' && !colRows.value.length && !loadingCol.value) {
    loadCollections().then(() => window.print())
  } else {
    window.print()
  }
}

watch(activeTab, (tab) => {
  if (tab === 'debtors' && !debtors.value.length) loadDebtors()
  if (tab === 'byclass' && !classData.value.length) loadByClass()
  if (tab === 'vs' && !vsData.value.length) loadVsData()
})

onMounted(async () => { try { await loadCollections() } catch {} })
</script>

<style>
.print-only { display: none; }

@media print {
  /* Hide everything except the print area */
  body > * { display: none !important; }
  #app > * { display: none !important; }
  .no-print { display: none !important; }
  .print-only { display: block !important; }

  /* Make print area fill the page */
  #print-area {
    display: block !important;
    position: fixed;
    inset: 0;
    width: 100%;
    padding: 20px;
    background: #fff;
    color: #000;
    font-size: 12px;
  }

  /* Show columns that are hidden on mobile */
  .d-none { display: table-cell !important; }
  .d-md-table-cell { display: table-cell !important; }
  .d-md-block { display: block !important; }

  /* Clean up card borders for print */
  .card { break-inside: avoid; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border: 1px solid #ccc; padding: 4px 8px; }
  thead { background: #f5f5f5 !important; -webkit-print-color-adjust: exact; }

  /* Page settings */
  @page { margin: 15mm; size: A4 portrait; }
}
</style>
