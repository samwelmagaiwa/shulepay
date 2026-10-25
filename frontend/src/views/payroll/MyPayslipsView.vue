<template>
  <CContainer fluid class="xg-page">
    <XStats v-if="employee" :cards="cards" />

    <CAlert v-if="error" color="danger" dismissible class="xg-alert" @close="error=''">{{ error }}</CAlert>
    <CAlert v-if="!loading && !employee" color="warning">{{ $t('ui.myPayslipsView.yourAccountIsNotLinked') }}</CAlert>

    <template v-if="employee || loading">
      <XBar :meta="{}" no-per-page :total="entries.length">
        <template #actions>
          <select v-model="filterYear" class="xg-perpage" @change="load">
            <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
          </select>
          <CButton color="secondary" variant="outline" size="sm" :disabled="dlAnnual" @click="downloadAnnual">
            <CSpinner v-if="dlAnnual" size="sm" class="me-1" />{{ $t('ui.employeeList.annualStatement') }} {{ filterYear }}
          </CButton>
        </template>
        <template #toolbar>
          <button class="xg-tb-btn" :disabled="!sel" @click="sel && openEntry(sel)">🔍 {{ $t('common.view') }}</button>
          <button class="xg-tb-btn" :disabled="!sel || dlSlip[sel?.id]" @click="sel && downloadSlip(sel)">📄 {{ $t('ui.myPayslipsView.pdf') }}</button>
        </template>
      </XBar>

      <XGrid v-model:selected-id="selId" :columns="columns" :rows="entries" :loading="loading" :empty="$t('ui.myPayslipsView.noPayrollEntriesFoundFor') + ' ' + filterYear + '.'"
             :min-width="760" :menu="menuFor" :row-class="(e) => (e.status === 'paid' ? 'xg-row--good' : 'xg-row--warn')" @open="openEntry">
        <template #cell-period="{ row }"><span class="xg-name">{{ monthName(row.month) }} {{ row.year }}</span></template>
        <template #cell-gross="{ row }">{{ fmt((row.basic_salary_cents || 0) + (row.allowances_cents || 0)) }}</template>
        <template #cell-paye_cents="{ row }"><span class="xg-out">{{ fmt(row.paye_cents) }}</span></template>
        <template #cell-nssf_employee_cents="{ row }"><span class="xg-out">{{ fmt(row.nssf_employee_cents) }}</span></template>
        <template #cell-net_salary_cents="{ row }"><span class="xg-in">{{ fmt(row.net_salary_cents) }}</span></template>
        <template #cell-status="{ row }"><span class="xg-pill" :class="row.status === 'paid' ? 'xg-pill--green' : 'xg-pill--amber'">{{ row.status === 'paid' ? $t('common.paid') : $t('common.pending') }}</span></template>
      </XGrid>
    </template>

    <!-- Detail modal -->
    <CModal :visible="!!selected" size="lg" @close="selected=null">
      <CModalHeader><CModalTitle>{{ $t('ui.myPayslipsView.payslip2') }} {{ selected ? monthName(selected.month) + ' ' + selected.year : '' }}</CModalTitle></CModalHeader>
      <CModalBody v-if="selected">
        <!-- Employee -->
        <div class="row g-2 mb-3">
          <div class="col-6"><span class="text-muted small d-block">{{ $t('common.name') }}</span>{{ selected.employee?.full_name }}</div>
          <div class="col-6"><span class="text-muted small d-block">{{ $t('payroll.staffNo') }}</span>{{ selected.employee?.staff_number }}</div>
          <div class="col-6"><span class="text-muted small d-block">{{ $t('ui.myPayslipsView.payMethod') }}</span>{{ ucfirst(selected.payment_method) }}</div>
          <div class="col-6"><span class="text-muted small d-block">{{ $t('ui.myPayslipsView.payDate') }}</span>{{ selected.payment_date ?? $t('common.pending') }}</div>
        </div>
        <hr>
        <!-- Earnings -->
        <h6 class="text-success fw-bold">{{ $t('ui.myPayslipsView.earnings') }}</h6>
        <table class="table table-sm table-bordered mb-3">
          <tbody>
            <tr><td>{{ $t('payroll.basic') }}</td><td class="text-end">{{ fmt(selected.basic_salary_cents) }}</td></tr>
            <tr v-if="(selected.allowances_cents||0) > 0"><td>{{ $t('payroll.allowances') }}</td><td class="text-end">{{ fmt(selected.allowances_cents) }}</td></tr>
            <tr class="fw-bold table-light"><td>{{ $t('ui.myPayslipsView.grossSalary') }}</td><td class="text-end">{{ fmt((selected.basic_salary_cents||0) + (selected.allowances_cents||0)) }}</td></tr>
          </tbody>
        </table>
        <!-- Deductions -->
        <h6 class="text-danger fw-bold">{{ $t('ui.myPayslipsView.statutoryDeductionsEmployee') }}</h6>
        <table class="table table-sm table-bordered mb-3">
          <tbody>
            <tr><td>{{ $t('ui.myPayslipsView.payeIncomeTax') }}</td><td class="text-end text-danger">{{ fmt(selected.paye_cents) }}</td></tr>
            <tr><td>{{ $t('ui.myPayslipsView.nssfEmployee10') }}</td><td class="text-end text-danger">{{ fmt(selected.nssf_employee_cents) }}</td></tr>
            <tr class="fw-bold table-light"><td>{{ $t('ui.myPayslipsView.totalDeductions') }}</td><td class="text-end text-danger">{{ fmt(selected.deductions_cents) }}</td></tr>
          </tbody>
        </table>
        <!-- Net -->
        <div class="alert alert-success d-flex justify-content-between fw-bold fs-5 mb-3">
          <span>{{ $t('ui.myPayslipsView.netPay') }}</span><span>{{ fmt(selected.net_salary_cents) }}</span>
        </div>
        <!-- Employer contributions (informational) -->
        <h6 class="text-muted fw-bold">{{ $t('ui.myPayslipsView.employerContributionsInformational') }}</h6>
        <table class="table table-sm table-bordered">
          <tbody>
            <tr><td>{{ $t('ui.myPayslipsView.nssfEmployer10') }}</td><td class="text-end">{{ fmt(selected.nssf_employer_cents) }}</td></tr>
            <tr><td>{{ $t('ui.myPayslipsView.wcf05') }}</td><td class="text-end">{{ fmt(selected.wcf_cents) }}</td></tr>
            <tr><td>{{ $t('ui.myPayslipsView.sdl45') }}</td><td class="text-end">{{ fmt(selected.sdl_cents) }}</td></tr>
          </tbody>
        </table>
        <p v-if="selected.notes" class="text-muted small mt-2">Notes: {{ selected.notes }}</p>
      </CModalBody>
      <CModalFooter>
        <button class="btn btn-outline-secondary btn-sm" @click="selected=null">{{ $t('common.close') }}</button>
        <button class="btn btn-primary btn-sm" :disabled="dlSlip[selected?.id]" @click="downloadSlip(selected)">
          <span v-if="dlSlip[selected?.id]" class="spinner-border spinner-border-sm me-1"></span>
          {{ $t('ui.myPayslipsView.downloadPayslipPdf') }}
        </button>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'
import { useI18n } from 'vue-i18n'
import { formatMonth } from '@/i18n'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'

const { t } = useI18n()

const loading   = ref(false)
const error     = ref('')
const entries   = ref([])
const employee  = ref(null)
const selected  = ref(null)
const dlSlip    = ref({})
const dlAnnual  = ref(false)

const currentYear = new Date().getFullYear()
const filterYear  = ref(currentYear)
const yearOptions = computed(() => {
  const years = []
  for (let y = currentYear; y >= currentYear - 4; y--) years.push(y)
  return years
})

const monthName = (m) => (m ? formatMonth(m) : m)

function fmt(cents) {
  if (cents == null) return 'TZS 0'
  return 'TZS ' + Number(Math.round(cents / 100)).toLocaleString()
}

function ucfirst(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : '' }


// ── List design (same as the Expenses page) ──
const selId = ref(null)
const sel = computed(() => entries.value.find(e => e.id === selId.value) || null)
const columns = computed(() => [
  { key: 'period', label: t('ui.billingHistoryView.period'), w: 'minmax(130px, 1.3fr)' },
  { key: 'gross', label: t('ui.myPayslipsView.gross'), w: 'minmax(110px, 1fr)', align: 'end' },
  { key: 'paye_cents', label: 'PAYE', w: 'minmax(100px, .9fr)', align: 'end' },
  { key: 'nssf_employee_cents', label: 'NSSF', w: 'minmax(100px, .9fr)', align: 'end' },
  { key: 'net_salary_cents', label: t('ui.myPayslipsView.netPay'), w: 'minmax(120px, 1.1fr)', align: 'end' },
  { key: 'status', label: t('common.status'), w: 'minmax(90px, .8fr)' },
])
const cards = computed(() => {
  const paid = entries.value.filter(e => e.status === 'paid')
  const sum = (list, k) => list.reduce((s, e) => s + (e[k] || 0), 0)
  return [
    { label: t('payroll.basic'), value: fmt(employee.value?.basic_salary_cents), tone: 'blue', sub: employee.value?.staff_number },
    { label: t('ui.myPayslipsView.netPay') + ' ' + filterYear.value, value: fmt(sum(paid, 'net_salary_cents')), tone: 'green', sub: String(paid.length) },
    { label: t('ui.myPayslipsView.totalDeductions'), value: fmt(sum(entries.value, 'deductions_cents')), tone: 'red' },
    { label: t('common.pending'), value: String(entries.value.length - paid.length), tone: 'amber' },
  ]
})
const menuFor = () => [
  { label: '🔍 ' + t('common.view'), run: openEntry },
  { label: '📄 ' + t('ui.myPayslipsView.pdf'), run: downloadSlip },
]

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/my-payroll', { params: { year: filterYear.value } })
    entries.value = data.data ?? []
    if (entries.value.length) {
      employee.value = entries.value[0].employee
    } else {
      // Try fetching without year to see if employee exists at all
      const all = await api.get('/my-payroll')
      if ((all.data.data ?? []).length) {
        employee.value = all.data.data[0].employee
      }
    }
  } catch (e) {
    if (e?.response?.status === 404) {
      employee.value = null
    } else {
      error.value = e?.response?.data?.message ?? 'Failed to load payslips.'
    }
  } finally {
    loading.value = false
  }
}

function openEntry(e) { selected.value = e }

async function downloadSlip(entry) {
  if (!entry) return
  dlSlip.value = { ...dlSlip.value, [entry.id]: true }
  try {
    const res = await api.get(`/my-payroll/${entry.id}/slip`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `payslip_${entry.employee?.staff_number}_${String(entry.month).padStart(2,'0')}_${entry.year}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = t('ui.myPayslipsView.failedToDownloadPayslipPdf')
  } finally {
    dlSlip.value = { ...dlSlip.value, [entry.id]: false }
  }
}

async function downloadAnnual() {
  dlAnnual.value = true
  try {
    const res = await api.get('/my-payroll/annual', { params: { year: filterYear.value }, responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `annual_statement_${filterYear.value}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = t('ui.employeeList.failedToDownloadAnnualStatement')
  } finally {
    dlAnnual.value = false
  }
}

onMounted(load)
</script>
