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

        <!-- Export Excel Button -->
        <CButton color="success" size="sm" variant="outline" :disabled="isExportingExcel" @click="exportExcel">
          <CSpinner v-if="isExportingExcel" size="sm" class="me-1" />
          <CIcon v-else icon="cilCloudDownload" class="me-1" />
          {{ t('reports.exportExcel') }}
        </CButton>

        <!-- Print Button -->
        <CButton color="secondary" size="sm" variant="outline" @click="printReport">
          <CIcon icon="cilPrint" class="me-1" /> {{ t('reports.print') }}
        </CButton>
      </div>
    </div>

    <div class="border border-top-0 rounded-bottom p-2 p-md-3" id="print-area">

      <!-- Print-only header -->
      <div class="print-only mb-3 pb-2" style="border-bottom:2px solid #333;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:4px;">
          <img v-if="branding.logoUrl" :src="branding.logoUrl"
               style="height:40px; width:40px; object-fit:contain; flex-shrink:0;" />
          <div class="fw-bold fs-5">{{ schoolStore.activeSchool?.name || branding.appName }}</div>
        </div>
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
          <CRow class="g-2 mb-3 no-print">
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ colStats.total_payments || 0 }} | {{ formatTZS(colStats.total_collected) }}</div>
                  <div class="text-muted small">{{ t('reports.totalCollected') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ colStats.debt_invoice_count || 0 }} | {{ formatTZS(colStats.total_outstanding) }}</div>
                  <div class="text-muted small">{{ t('reports.totalDebt') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6">{{ colStats.paid_count || 0 }} | {{ formatTZS(colStats.paid_amount) }}</div>
                  <div class="text-muted small">{{ t('reports.fullyPaid') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #ffc107 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-warning">{{ colStats.partial_count || 0 }} | {{ formatTZS(colStats.partial_paid_amount) }}</div>
                  <div class="text-muted small">{{ t('reports.partialPaid') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <!-- Discounts & Sponsorships breakdown -->
          <div class="no-print mb-3" v-if="byDiscountType.length || bySponsorshipType.length">
            <div class="fw-semibold text-muted small mb-1">{{ t('reports.discountsByType') }}</div>
            <CRow class="g-2 mb-2">
              <CCol xs="6" md="3" v-for="d in byDiscountType" :key="'disc-'+d.type">
                <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #6366f1 !important">
                  <CCardBody class="p-2">
                    <div class="fw-bold fs-6" style="color:#6366f1">{{ d.count || 0 }} | {{ formatTZS(d.amount_cents) }}</div>
                    <div class="text-muted small">{{ discountTypeLabel(d.type) }}</div>
                  </CCardBody>
                </CCard>
              </CCol>
            </CRow>

            <div class="fw-semibold text-muted small mb-1">{{ t('reports.sponsorshipsByType') }}</div>
            <CRow class="g-2">
              <CCol xs="6" md="4" v-for="s in bySponsorshipType" :key="'spon-'+s.type">
                <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #0ea5e9 !important">
                  <CCardBody class="p-2">
                    <div class="fw-bold fs-6" style="color:#0ea5e9">
                      {{ s.count || 0 }}<span v-if="s.type !== 'full'"> | {{ formatTZS(s.amount_cents) }}</span>
                    </div>
                    <div class="text-muted small">{{ sponsorshipTypeLabel(s.type) }}</div>
                  </CCardBody>
                </CCard>
              </CCol>
            </CRow>
          </div>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.collectionsCol') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.totalDebt') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('reports.debtorsColumn') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.totalPartialPaid') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.paymentCount') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="row in colRows" :key="row.date">
                <CTableDataCell>{{ row.date }}</CTableDataCell>
                <CTableDataCell class="fw-semibold text-success">{{ formatTZS(row.amount_cents) }}</CTableDataCell>
                <CTableDataCell class="fw-semibold text-danger">{{ formatTZS(row.total_debt_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">
                  <div v-if="!row.debtors || !row.debtors.length" class="text-muted">—</div>
                  <div v-for="(d, i) in row.debtors" :key="i">{{ d }}</div>
                </CTableDataCell>
                <CTableDataCell class="fw-semibold text-warning">{{ formatTZS(row.total_partial_paid_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell">{{ row.count }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!colRows.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Madeni (Debtors aging) -->
      <div v-if="activeTab === 'debtors'">
        <div v-if="loadingDebt" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3 no-print">
            <CCol xs="6" sm="3" v-for="bucket in debtBuckets" :key="bucket.label">
              <CCard class="text-center border-0 shadow-sm h-100" :style="`border-left:3px solid ${bucket.color} !important`">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6" :style="`color:${bucket.color}`">{{ formatTZS(bucket.total) }}</div>
                  <div class="text-muted small">{{ bucket.label }}</div>
                  <div class="text-muted" style="font-size:.72rem;">{{ bucket.count }} {{ t('reports.invoicesShort') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('reports.student') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('common.class') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('reports.guardian') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('reports.village') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('invoices.balance') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.termsNotPaid') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.daysOverdue') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.agingStatus') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="d in debtors" :key="d.id">
                <CTableDataCell>
                  <div class="fw-semibold">{{ d.full_name }}</div>
                  <div class="text-muted small">{{ d.admission_number }}</div>
                </CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell small">{{ d.school_class }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">
                  <div>{{ d.guardian_name }}</div>
                  <div class="text-muted">{{ d.guardian_phone }}</div>
                </CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell small">{{ d.village_street }}</CTableDataCell>
                <CTableDataCell class="fw-bold text-danger">{{ formatTZS(d.outstanding_cents) }}</CTableDataCell>
                <CTableDataCell class="d-none d-md-table-cell small">{{ d.terms_not_paid }}</CTableDataCell>
                <CTableDataCell class="small">{{ d.oldest_age || 0 }}</CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="agingColor(d.oldest_age)" shape="rounded-pill">{{ agingLabel(d.oldest_age) }}</CBadge>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!debtors.length">
                <CTableDataCell colspan="8" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Darasa (By Class) -->
      <div v-if="activeTab === 'byclass'">
        <div v-if="loadingClass" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3 no-print">
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #0d6efd !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-primary">{{ classData.length }}</div>
                  <div class="text-muted small">{{ t('reports.classReport.classCount') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #6c757d !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-secondary">{{ formatTZS(classStats.totalBilled) }}</div>
                  <div class="text-muted small">{{ t('reports.classReport.totalBilled') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ formatTZS(classStats.totalCollected) }}</div>
                  <div class="text-muted small">{{ t('reports.classReport.totalCollected') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="3">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ formatTZS(classStats.totalDebt) }}</div>
                  <div class="text-muted small">{{ t('reports.classReport.totalDebt') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>

          <CTable responsive hover class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('reports.classReport.class') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.students') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.invoiced') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('reports.classReport.collected') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-md-table-cell">{{ t('reports.classReport.debt') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell text-center">{{ t('reports.classReport.paidInv') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell text-center">{{ t('reports.classReport.partialInv') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell text-center">{{ t('reports.classReport.unpaidInv') }}</CTableHeaderCell>
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
                <CTableDataCell class="d-none d-lg-table-cell text-center text-success">{{ c.paid_count || 0 }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell text-center text-warning">{{ c.partial_count || 0 }}</CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell text-center text-danger">{{ c.unpaid_count || 0 }}</CTableDataCell>
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
                <CTableDataCell colspan="9" class="text-center text-muted py-4">{{ t('reports.noData') }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </div>

      <!-- Tab: Gharama vs Makusanyo -->
      <div v-if="activeTab === 'vs'">
        <div v-if="loadingVs" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else>
          <CRow class="g-2 mb-3 no-print">
            <CCol xs="6" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #198754 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-success">{{ formatTZS(vsStats.totalCollections) }}</div>
                  <div class="text-muted small">{{ t('reports.vsReport.totalCollections') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="6" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" style="border-left:3px solid #dc3545 !important">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6 text-danger">{{ formatTZS(vsStats.totalExpenses) }}</div>
                  <div class="text-muted small">{{ t('reports.vsReport.totalExpenses') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
            <CCol xs="12" md="4">
              <CCard class="text-center border-0 shadow-sm h-100" :style="`border-left:3px solid ${vsStats.surplus >= 0 ? '#198754' : '#dc3545'} !important`">
                <CCardBody class="p-2">
                  <div class="fw-bold fs-6" :class="vsStats.surplus >= 0 ? 'text-success' : 'text-danger'">
                    {{ formatTZS(vsStats.surplus) }}
                  </div>
                  <div class="text-muted small">{{ t('reports.vsReport.surplusDeficit') }}</div>
                </CCardBody>
              </CCard>
            </CCol>
          </CRow>
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
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useSchoolStore } from '@/stores/school'
import { useBrandingStore } from '@/stores/branding'
import api from '@/services/api'

const { t } = useI18n()
const schoolStore = useSchoolStore()
const branding = useBrandingStore()

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
const byDiscountType = ref([])
const bySponsorshipType = ref([])
const discountTypeLabel = (type) => t(`reports.discountTypes.${type}`, type)
const sponsorshipTypeLabel = (type) => t(`reports.sponsorshipTypes.${type}`, type)
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
  // An overpaid class (collected > billed) would otherwise show e.g. "40000%" —
  // this is a % of fee collected, capped at 100 like any completion metric.
  return Math.min(100, Math.round((c.total_collected_cents / c.total_billed_cents) * 100))
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

async function loadCollections() {
  loadingCol.value = true
  try {
    const { data } = await api.get('/reports/collections', {
      params: { from: colFilters.value.date_from, to: colFilters.value.date_to },
    })
    const d = data.data || data
    colRows.value = (d.rows || []).map(r => ({
      date: r.period,
      amount_cents: r.amount_cents,
      total_debt_cents: r.total_debt_cents,
      debtors: r.debtors || [],
      total_partial_paid_cents: r.total_partial_paid_cents,
      count: r.payment_count,
    }))
    colStats.value = {
      total_payments: d.summary?.total_payments || 0,
      total_collected: d.summary?.total_amount_cents || 0,
      total_outstanding: d.summary?.total_outstanding_cents || 0,
      debt_invoice_count: d.summary?.debt_invoice_count || 0,
      paid_count: d.summary?.paid_count || 0,
      paid_amount: d.summary?.paid_amount_cents || 0,
      partial_count: d.summary?.partial_count || 0,
      partial_paid_amount: d.summary?.partial_paid_amount_cents || 0,
    }
    byDiscountType.value = d.by_discount_type || []
    bySponsorshipType.value = d.by_sponsorship_type || []
  } catch {} finally { loadingCol.value = false }
}

async function loadDebtors() {
  loadingDebt.value = true
  try {
    const { data } = await api.get('/reports/debtor-aging')
    const buckets = data.buckets || {}
    // Backend has 5 buckets (current = not-yet-due, plus 4 aging ranges); the
    // UI only has 4 slots, so "current" folds into the 1-30 days slot.
    const slotOf = { current: 0, days_1_30: 0, days_31_60: 1, days_61_90: 2, over_90: 3 }
    const counts = [0, 0, 0, 0]
    const totals = [0, 0, 0, 0]
    const flat = []
    Object.entries(slotOf).forEach(([key, slot]) => {
      const b = buckets[key] || { count: 0, amount_cents: 0, students: [] }
      counts[slot] += b.count || 0
      totals[slot] += b.amount_cents || 0
      ;(b.students || []).forEach(s => flat.push({ ...s, bucket: key }))
    })
    // Most overdue first — otherwise every "current" (0 days) debtor lists
    // before any genuinely overdue one, and a table full of "0" up top reads
    // as if aging were broken even though the underlying numbers are correct.
    debtors.value = flat.sort((a, b) => (b.oldest_age || 0) - (a.oldest_age || 0))
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
  } catch {} finally { loadingClass.value = false }
}

async function loadVsData() {
  loadingVs.value = true
  try {
    const { data } = await api.get('/reports/expenses-vs-collections', { params: { year: vsYear.value } })
    const d = data.data || data
    vsData.value = d.rows || d || []
  } catch {} finally { loadingVs.value = false }
}

// ── Export current tab to Excel ────────────────────────────────────────────
const isExportingExcel = ref(false)
const reportTypeByTab = { collections: 'collections', debtors: 'debtor-aging', byclass: 'by-class', vs: 'vs' }

async function exportExcel() {
  const type = reportTypeByTab[activeTab.value]
  if (!type) return
  isExportingExcel.value = true
  try {
    const params = activeTab.value === 'collections'
      ? { from: colFilters.value.date_from, to: colFilters.value.date_to }
      : activeTab.value === 'vs'
        ? { year: vsYear.value }
        : {}
    const response = await api.get(`/reports/${type}/xlsx`, { params, responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `${type}_${new Date().toISOString().slice(0, 10)}.xlsx`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    console.error('exportExcel error', e)
  } finally {
    isExportingExcel.value = false
  }
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

// Every report is scoped server-side by the active school (via X-School-Id) —
// switching schools while a tab is already populated must refresh it instead
// of silently keeping the previous school's numbers on screen.
watch(() => schoolStore.activeSchoolId, () => {
  if (activeTab.value === 'collections') loadCollections()
  else if (activeTab.value === 'debtors') loadDebtors()
  else if (activeTab.value === 'byclass') loadByClass()
  else if (activeTab.value === 'vs') loadVsData()
})

onMounted(async () => { try { await loadCollections() } catch {} })
</script>

<style>
.print-only { display: none; }

@media print {
  /* Hide everything except the print area. #print-area is nested several
     layout levels deep (sidebar/header wrapper > router-view > this page),
     not a direct child of #app — display:none on an ancestor can't be
     undone by a display override on a descendant, which is why the old
     `body > *` / `#app > *` rules produced a blank printed page. visibility
     doesn't have that problem: a hidden ancestor doesn't force its
     visible-again descendants to stay hidden. */
  body * { visibility: hidden !important; }
  #print-area, #print-area * { visibility: visible !important; }
  .no-print { display: none !important; }
  .print-only { display: block !important; }

  /* Make print area fill the page */
  #print-area {
    position: absolute;
    left: 0;
    top: 0;
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
