<template>
  <CContainer fluid class="xg-page">
    <XStats :cards="cards" />
    <CAlert v-if="error" color="danger" class="xg-alert">{{ error }}</CAlert>

    <XBar :meta="meta" v-model:per-page="perPage" :filters-active="hasFilters" @page="goPage" @update:per-page="goPage(1)">
      <template #actions>
        <select v-model="filters.month" class="xg-perpage" @change="goPage(1)">
          <option v-for="(m, i) in months" :key="i" :value="String(i + 1)">{{ m }}</option>
        </select>
        <input v-model.number="filters.year" type="number" min="2020" max="2100" class="xg-perpage" style="width:72px" @change="goPage(1)" />
        <CButton color="secondary" variant="outline" size="sm" @click="openRates">{{ t('ui.payrollView.rates') }}</CButton>
        <CButton color="info" variant="outline" size="sm" :disabled="dlMonthly" @click="downloadMonthly">
          <CSpinner v-if="dlMonthly" size="sm" class="me-1" />{{ t('ui.payrollView.monthlyReport') }}
        </CButton>
        <CButton color="success" size="sm" :disabled="generating" @click="generatePayroll">
          <CSpinner v-if="generating" size="sm" class="me-1" /><CIcon icon="cilPlus" class="me-1" /> {{ t('payroll.generate') }}
        </CButton>
      </template>
      <template #filters>
        <CFormSelect v-model="filters.school_id" @update:modelValue="goPage(1)">
          <option value="">{{ t('common.allSchools') }}</option>
          <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.status" @update:modelValue="goPage(1)">
          <option value="">{{ t('common.allStatuses') }}</option>
          <option value="pending">{{ t('payroll.pending') }}</option>
          <option value="paid">{{ t('payroll.paid') }}</option>
        </CFormSelect>
        <CFormInput v-model="filters.search" class="xg-grow" :placeholder="t('employees.searchPlaceholder')" @input="debouncedLoad" />
        <CButton color="secondary" variant="outline" size="sm" @click="resetFilters">{{ t('common.reset') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && openView(selected)">🔍 {{ t('common.view') }}</button>
        <button class="xg-tb-btn is-ok" :disabled="!selected || selected.status === 'paid'" @click="selected && selected.status !== 'paid' && markPaid(selected.id)">💵 {{ t('payroll.pay') }}</button>
        <button class="xg-tb-btn" :disabled="!selected || selected.status !== 'paid'" @click="selected && selected.status === 'paid' && voucher(selected)">🧾 {{ t('expensex.voucher') }}</button>
        <button class="xg-tb-btn" :disabled="!selected" @click="selected && downloadSlip(selected)">📄 {{ t('ui.payrollView.payslip') }}</button>
      </template>
    </XBar>

    <XGrid v-model:selected-id="selectedId" :columns="columns" :rows="entries" :loading="loading" :empty="t('payroll.noEntries')"
           :min-width="1080" :menu="menuFor" :row-class="rowClass" @open="openView">
      <template #cell-staff="{ row }"><span class="xg-mono">{{ row.employee?.staff_number }}</span></template>
      <template #cell-name="{ row }">
        <div class="xg-two"><span class="xg-name">{{ row.employee?.full_name }}</span><span class="xg-sub">{{ row.employee?.role }}</span></div>
      </template>
      <template #cell-basic_salary_cents="{ row }">{{ formatMoney(row.basic_salary_cents) }}</template>
      <template #cell-allowances_cents="{ row }">{{ row.allowances_cents ? '+' + formatMoney(row.allowances_cents) : '—' }}</template>
      <template #cell-deductions="{ row }"><span class="xg-out">-{{ formatMoney((row.paye_cents || 0) + (row.nssf_employee_cents || 0)) }}</span></template>
      <template #cell-net_salary_cents="{ row }"><span class="xg-strong">{{ formatMoney(row.net_salary_cents) }}</span></template>
      <template #cell-status="{ row }">
        <span class="xg-pill" :class="row.status === 'paid' ? 'xg-pill--green' : 'xg-pill--amber'">{{ row.status === 'paid' ? t('payroll.paid') : t('payroll.pending') }}</span>
      </template>
    </XGrid>

    <!-- View Payroll Entry Modal -->
    <CModal :visible="showViewModal" @close="showViewModal = false" size="lg" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>💰 {{ t('payroll.viewTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="viewTarget" class="p-3">
        <div class="d-flex align-items-center gap-3 mb-4 p-3 bg-light rounded">
          <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold"
               style="width:56px;height:56px;font-size:1.4rem;flex-shrink:0;">
            {{ (viewTarget.employee?.full_name || '?').charAt(0).toUpperCase() }}
          </div>
          <div>
            <div class="fw-bold fs-5">{{ viewTarget.employee?.full_name }}</div>
            <div class="text-muted small">{{ viewTarget.employee?.staff_number }} · {{ viewTarget.employee?.role }}</div>
            <CBadge :color="viewTarget.status === 'paid' ? 'success' : 'warning'" class="mt-1">
              {{ viewTarget.status === 'paid' ? t('payroll.paid') : t('payroll.pending') }}
            </CBadge>
          </div>
        </div>

        <!-- Earnings -->
        <div class="fw-semibold text-muted small mb-2 border-bottom pb-1">{{ t('ui.myPayslipsView.earnings') }}</div>
        <CRow class="g-2 mb-3">
          <CCol xs="6">
            <div class="text-muted small">{{ t('payroll.basic') }}</div>
            <div class="fw-bold">{{ formatMoney(viewTarget.basic_salary_cents) }}</div>
          </CCol>
          <CCol xs="6">
            <div class="text-muted small">{{ t('payroll.allowances') }}</div>
            <div class="fw-bold text-success">+ {{ formatMoney(viewTarget.allowances_cents) }}</div>
          </CCol>
          <CCol xs="6">
            <div class="text-muted small">{{ t('ui.myPayslipsView.grossSalary') }}</div>
            <div class="fw-bold">{{ formatMoney((viewTarget.basic_salary_cents || 0) + (viewTarget.allowances_cents || 0)) }}</div>
          </CCol>
        </CRow>

        <!-- Statutory deductions -->
        <div class="fw-semibold text-muted small mb-2 border-bottom pb-1">{{ t('ui.myPayslipsView.statutoryDeductionsEmployee') }}</div>
        <CRow class="g-2 mb-3">
          <CCol xs="6">
            <div class="text-muted small">{{ t('ui.myPayslipsView.payeIncomeTax') }}</div>
            <div class="fw-bold text-danger">- {{ formatMoney(viewTarget.paye_cents) }}</div>
          </CCol>
          <CCol xs="6">
            <div class="text-muted small">{{ t('ui.payrollView.nssfEmployee') }}</div>
            <div class="fw-bold text-danger">- {{ formatMoney(viewTarget.nssf_employee_cents) }}</div>
          </CCol>
          <CCol xs="6">
            <div class="text-muted small">{{ t('ui.myPayslipsView.totalDeductions') }}</div>
            <div class="fw-bold text-danger">- {{ formatMoney(viewTarget.deductions_cents) }}</div>
          </CCol>
          <CCol xs="6">
            <div class="text-muted small">{{ t('ui.myPayslipsView.netPay') }}</div>
            <div class="fw-bold fs-5 text-primary">{{ formatMoney(viewTarget.net_salary_cents) }}</div>
          </CCol>
        </CRow>

        <!-- Employer contributions (informational) -->
        <div class="fw-semibold text-muted small mb-2 border-bottom pb-1">{{ t('ui.myPayslipsView.employerContributionsInformational') }}</div>
        <CRow class="g-2">
          <CCol xs="4">
            <div class="text-muted small">{{ t('ui.payrollView.nssfEmployer') }}</div>
            <div class="fw-semibold">{{ formatMoney(viewTarget.nssf_employer_cents) }}</div>
          </CCol>
          <CCol xs="4">
            <div class="text-muted small">WCF</div>
            <div class="fw-semibold">{{ formatMoney(viewTarget.wcf_cents) }}</div>
          </CCol>
          <CCol xs="4">
            <div class="text-muted small">SDL</div>
            <div class="fw-semibold">{{ formatMoney(viewTarget.sdl_cents) }}</div>
          </CCol>
        </CRow>

        <div v-if="viewTarget.notes" class="mt-3 p-2 bg-light rounded small text-muted">
          <strong>Notes:</strong> {{ viewTarget.notes }}
        </div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showViewModal = false" style="min-height:44px;">{{ t('common.close') }}</CButton>
        <CButton color="info" variant="outline" @click="downloadSlip(viewTarget)" style="min-height:44px;">{{ t('ui.payrollView.payslipPdf') }}</CButton>
        <CButton v-if="viewTarget?.status !== 'paid'" color="primary" @click="markPaid(viewTarget.id); showViewModal = false" style="min-height:44px;">💵 {{ t('payroll.pay') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Generate confirm -->
    <CModal :visible="showGenerateModal" @close="showGenerateModal = false" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('payroll.generateTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="generateError" color="danger">{{ generateError }}</CAlert>
        <p>{{ t('payroll.generateMsg', { month: months[filters.month - 1], year: filters.year }) }}</p>
        <p class="text-muted small">{{ t('payroll.generateInfo') }}</p>
        <CAlert color="info" class="small mb-0">
          {{ t('ui.payrollView.statutoryDeductionsPayeNssfWcf') }}
        </CAlert>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showGenerateModal = false" style="min-height:44px;">{{ t('payroll.close') }}</CButton>
        <CButton color="success" :disabled="generating" @click="confirmGenerate" style="min-height:44px;">
          <CSpinner v-if="generating" size="sm" class="me-1" />{{ t('payroll.confirm') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Rates Settings Modal -->
    <CModal :visible="showRatesModal" @close="showRatesModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>{{ t('ui.payrollView.statutoryRates') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="ratesError" color="danger">{{ ratesError }}</CAlert>
        <p class="text-muted small mb-3">{{ t('ui.payrollView.configureNssfWcfSdlPercentages') }}</p>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('ui.payrollView.nssfEmployee2') }}</label>
          <CFormInput type="number" v-model.number="ratesForm.nssf_employee_pct" min="0" max="100" step="0.5" style="min-height:44px;" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('ui.payrollView.nssfEmployer2') }}</label>
          <CFormInput type="number" v-model.number="ratesForm.nssf_employer_pct" min="0" max="100" step="0.5" style="min-height:44px;" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('ui.payrollView.wcf') }}</label>
          <CFormInput type="number" v-model.number="ratesForm.wcf_pct" min="0" max="100" step="0.1" style="min-height:44px;" />
        </div>
        <div class="mb-0">
          <label class="form-label fw-semibold">{{ t('ui.payrollView.sdl') }}</label>
          <CFormInput type="number" v-model.number="ratesForm.sdl_pct" min="0" max="100" step="0.5" style="min-height:44px;" />
        </div>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showRatesModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="savingRates" @click="saveRates" style="min-height:44px;">
          <CSpinner v-if="savingRates" size="sm" class="me-1" />{{ t('ui.payrollView.saveRates') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePayrollStore }  from '@/stores/payroll'
import { useSchoolsStore }  from '@/stores/schools'
import { useSchoolStore }   from '@/stores/school'
import api from '@/services/api'
import { openVoucher } from '@/services/voucher'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t, te, tm } = useI18n()

const payrollStore = usePayrollStore()
const schoolStore  = useSchoolsStore()
const navSchool    = useSchoolStore()

const entries  = ref([])
const loading  = ref(false)
const showViewModal = ref(false)
const viewTarget = ref(null)
const error    = ref('')
const generating = ref(false)
const showGenerateModal = ref(false)
const generateError     = ref('')
const schools  = computed(() => schoolStore.schools)
const page     = ref(1)
const meta     = ref({ total: 0, last_page: 1, per_page: 20, current_page: 1 })
const dlMonthly = ref(false)

const showRatesModal = ref(false)
const savingRates    = ref(false)
const ratesError     = ref('')
const ratesForm = ref({ nssf_employee_pct: 10, nssf_employer_pct: 10, wcf_pct: 0.5, sdl_pct: 4.5 })

const perPage = ref('50')
const selectedId = ref(null)
const selected = computed(() => entries.value.find(e => e.id === selectedId.value) || null)
const now = new Date()
const filters = ref({
  month: String(now.getMonth() + 1),
  year:  now.getFullYear(),
  school_id: navSchool.activeSchoolId || '',
  status: '',
  search: '',
})

watch(() => navSchool.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  load()
})

const monthKeys = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec']
const months = computed(() => {
  if (te('payroll.months')) {
    const list = tm('payroll.months')
    if (Array.isArray(list) && list.length) return list
  }
  return monthKeys.map(k => t(`common.months.${k}`))
})

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const params = { month: filters.value.month, year: filters.value.year, page: page.value, per_page: perPage.value }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    if (filters.value.status)    params.status    = filters.value.status
    if (filters.value.search)    params.search    = filters.value.search
    await payrollStore.fetchPayroll(params)
    entries.value = payrollStore.payroll
    meta.value = payrollStore.pagination || meta.value
  } catch (e) {
    error.value = e?.response?.data?.message || 'Imeshindwa kupakia.'
  } finally {
    loading.value = false
  }
}

async function voucher(e) {
  const msg = await openVoucher('payroll', e.id)
  if (msg) error.value = msg
}
const summary = computed(() => payrollStore.summary || {})
const hasFilters = computed(() => !!(filters.value.status || filters.value.search))
const rowClass = (e) => (e.status === 'paid' ? 'xg-row--good' : 'xg-row--warn')
const columns = computed(() => [
  { key: 'staff', label: t('payroll.staffNo'), w: 'minmax(90px, .8fr)' },
  { key: 'name', label: t('payroll.fullName'), w: 'minmax(170px, 2fr)' },
  { key: 'basic_salary_cents', label: t('payroll.basic'), w: 'minmax(110px, 1.1fr)', align: 'end' },
  { key: 'allowances_cents', label: t('payroll.allowances'), w: 'minmax(100px, 1fr)', align: 'end' },
  { key: 'deductions', label: 'PAYE + NSSF', w: 'minmax(110px, 1.1fr)', align: 'end' },
  { key: 'net_salary_cents', label: t('payroll.net'), w: 'minmax(120px, 1.2fr)', align: 'end' },
  { key: 'status', label: t('common.status'), w: 'minmax(90px, .9fr)' },
])
const cards = computed(() => [
  { label: t('payroll.title'), value: summary.value.count ?? meta.value.total, tone: 'gray' },
  { label: t('ui.myPayslipsView.grossSalary'), value: formatMoney(summary.value.gross_cents), tone: 'blue' },
  { label: t('ui.myPayslipsView.totalDeductions'), value: formatMoney(summary.value.deductions_cents), tone: 'red' },
  { label: t('payroll.paid'), value: formatMoney(summary.value.paid_cents), tone: 'green', sub: String(summary.value.paid_count ?? 0) },
  { label: t('payroll.pending'), value: formatMoney(summary.value.pending_cents), tone: 'amber', sub: String(summary.value.pending_count ?? 0) },
])
const menuFor = (e) => [
  { label: '🔍 ' + t('common.view'), run: openView },
  { label: '📄 ' + t('ui.payrollView.payslip'), run: downloadSlip },
  ...(e.status === 'paid' ? [{ label: '🧾 ' + t('expensex.voucher'), run: voucher }] : []),
  ...(e.status !== 'paid' ? [{ label: '💵 ' + t('payroll.pay'), run: (r) => markPaid(r.id) }] : []),
]
function goPage(p) { page.value = typeof p === 'number' ? p : 1; load() }
function resetFilters() { filters.value.status = ''; filters.value.search = ''; goPage(1) }
let debounceTimer = null
function debouncedLoad() { clearTimeout(debounceTimer); debounceTimer = setTimeout(() => goPage(1), 350) }

function generatePayroll() {
  generateError.value  = ''
  showGenerateModal.value = true
}

async function confirmGenerate() {
  generating.value = true
  generateError.value = ''
  try {
    await payrollStore.bulkGenerate({
      month:     filters.value.month,
      year:      filters.value.year,
      school_id: filters.value.school_id || undefined,
    })
    showGenerateModal.value = false
    await load()
  } catch (e) {
    generateError.value = e?.response?.data?.message || 'Imeshindwa kutengeneza mishahara.'
  } finally {
    generating.value = false
  }
}

function openView(entry) { viewTarget.value = entry; showViewModal.value = true }

async function markPaid(id) {
  try {
    await payrollStore.markPaid(id)
    await load()
  } catch (e) {
    alert(e?.response?.data?.message || 'Imeshindwa kulipa.')
  }
}

async function downloadSlip(entry) {
  try {
    const res = await api.get(`/payroll/${entry.id}/slip`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `payslip_${entry.employee?.staff_number}_${String(entry.month).padStart(2,'0')}_${entry.year}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert(t('ui.payrollView.failedToDownloadPayslip'))
  }
}

async function downloadMonthly() {
  dlMonthly.value = true
  try {
    const params = { month: filters.value.month, year: filters.value.year }
    if (filters.value.school_id) params.school_id = filters.value.school_id
    const res = await api.get('/payroll/report/monthly', { params, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `payroll_summary_${String(filters.value.month).padStart(2,'0')}_${filters.value.year}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert(t('ui.payrollView.failedToDownloadMonthlyReport'))
  } finally {
    dlMonthly.value = false
  }
}

async function openRates() {
  ratesError.value = ''
  try {
    const { data } = await api.get('/payroll/rates')
    ratesForm.value = {
      nssf_employee_pct: data.nssf_employee_pct ?? 10,
      nssf_employer_pct: data.nssf_employer_pct ?? 10,
      wcf_pct:           data.wcf_pct           ?? 0.5,
      sdl_pct:           data.sdl_pct           ?? 4.5,
    }
  } catch {}
  showRatesModal.value = true
}

async function saveRates() {
  savingRates.value = true
  ratesError.value  = ''
  try {
    await api.put('/payroll/rates', ratesForm.value)
    showRatesModal.value = false
  } catch (e) {
    ratesError.value = e?.response?.data?.message || 'Failed to save rates.'
  } finally {
    savingRates.value = false
  }
}

onMounted(async () => {
  try {
    await schoolStore.fetchSchools()
    await load()
  } catch {}
})
</script>
