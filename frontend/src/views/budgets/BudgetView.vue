<template>
  <CContainer fluid class="xg-page">

    <!-- ── Stat cards ── -->
    <XStats :cards="cards" />

    <CAlert v-if="store.error" color="danger" dismissible class="xg-alert" @close="store.error = ''">{{ store.error }}</CAlert>
    <CAlert v-if="successMsg" color="success" dismissible class="xg-alert" @close="successMsg = ''">{{ successMsg }}</CAlert>

    <!-- ── Budget picker + actions bar ── -->
    <XBar :meta="{}" no-per-page :total="lines.length">
      <template #actions>
        <select v-model="selectedBudgetId" class="xg-perpage" style="min-width:210px" @change="pickBudget">
          <option v-if="!store.budgets.length" :value="null">{{ t('budgetx.pickBudget') }}</option>
          <option v-for="b in store.budgets" :key="b.id" :value="b.id">{{ b.name }} · {{ statusLabel(b.status) }}</option>
        </select>
        <CButton color="secondary" variant="outline" size="sm" :disabled="!current" @click="printing = true">🖨 {{ t('printing.button') }}</CButton>
        <CButton color="success" size="sm" @click="openCreate"><CIcon icon="cilPlus" class="me-1" />{{ t('budgetx.newBudget') }}</CButton>
      </template>
      <template #toolbar>
        <button class="xg-tb-btn" :disabled="!editable" @click="openLine(null)">➕ {{ t('budgetx.addLine') }}</button>
        <button class="xg-tb-btn" :disabled="!editable || !selLine" @click="selLine && openLine(selLine)">✏️ {{ t('budgetx.editLine') }}</button>
        <button class="xg-tb-btn is-danger" :disabled="!editable || !selLine" @click="selLine && (lineToDelete = selLine)">🗑️ {{ t('budgetx.deleteLine') }}</button>
        <span class="mx-1">|</span>
        <button class="xg-tb-btn" :disabled="!current" @click="openEdit">✏️ {{ t('budgetx.editBudget') }}</button>
        <button class="xg-tb-btn is-ok" :disabled="!current || current.status !== 'draft'" @click="confirmActivate = true">✅ {{ t('budgets.activate') }}</button>
        <button class="xg-tb-btn" :disabled="!current || current.status !== 'active'" @click="confirmClose = true">🔒 {{ t('budgetx.close') }}</button>
        <button class="xg-tb-btn is-danger" :disabled="!current" @click="budgetToDelete = current">🗑️ {{ t('budgetx.deleteBudget') }}</button>
      </template>
    </XBar>

    <!-- ── Budget health panel (shown when a budget is selected) ── -->
    <div v-if="current" class="bud-health-panel mb-3">

      <!-- Header row: name + status + year -->
      <div class="bud-health-header">
        <div class="bud-health-title">
          <span class="bud-budget-name">{{ current.name }}</span>
          <span class="xg-pill" :class="statusPillClass(current.status)">{{ statusLabel(current.status) }}</span>
          <span v-if="current.academic_year" class="bud-year-badge">📅 {{ current.academic_year }}</span>
        </div>
        <div v-if="current.status === 'active'" class="bud-health-badge" :class="healthClass">
          {{ healthLabel }}
        </div>
      </div>

      <!-- Key metrics row + inline progress bars -->
      <div class="bud-metrics">
        <div class="bud-metric">
          <span class="bud-metric-label">Daily burn</span>
          <span class="bud-metric-value">{{ dailyBurnFormatted }}</span>
        </div>
        <div class="bud-metric">
          <span class="bud-metric-label">Days remaining</span>
          <span class="bud-metric-value">{{ daysRemaining !== null ? daysRemaining + ' days' : '—' }}</span>
        </div>
        <div class="bud-metric">
          <span class="bud-metric-label">Projected full-year spend</span>
          <span class="bud-metric-value" :class="projectedSpendCents > summary.planned_cents ? 'text-danger' : 'text-success'">
            {{ projectedSpendCents !== null ? formatTZS(projectedSpendCents) : '—' }}
          </span>
        </div>
        <div v-if="summary.over_count" class="bud-metric bud-metric--alert">
          <span class="bud-metric-label">Over-budget lines</span>
          <span class="bud-metric-value text-danger fw-bold">{{ summary.over_count }} ⚠</span>
        </div>
        <!-- Inline progress bars -->
        <div v-if="yearProgress !== null" class="bud-metric bud-metric--bars">
          <div class="bud-inline-bar-row">
            <span class="bud-inline-label">Year elapsed</span>
            <div class="bud-inline-track">
              <div class="bud-bar-fill bud-bar--gray" :style="{ width: yearProgress + '%' }"></div>
            </div>
            <span class="bud-inline-pct">{{ yearProgress }}%</span>
          </div>
          <div class="bud-inline-bar-row">
            <span class="bud-inline-label">Budget used</span>
            <div class="bud-inline-track">
              <div class="bud-bar-fill" :class="utilisationBarClass" :style="{ width: Math.min(100, usedPct) + '%' }"></div>
            </div>
            <span class="bud-inline-pct" :class="usedPct > 100 ? 'text-danger fw-bold' : ''">{{ usedPct }}%</span>
          </div>
        </div>
      </div>

      <CAlert v-if="current.status === 'closed'" color="light" class="mb-0 mt-2 py-2 small">🔒 {{ t('budgetx.closedLocked') }}</CAlert>
      <CAlert v-else-if="current" color="light" class="mb-0 mt-2 py-2 small">ℹ️ {{ t('budgetx.help') }}</CAlert>
    </div>

    <!-- ── Main grid ── -->
    <XGrid v-model:selected-id="selLineId" :columns="columns" :rows="lines" :loading="store.loading" :min-width="980"
           :empty="current ? t('budgetx.noLines') : t('budgetx.none')" :menu="lineMenu" :row-class="rowClass" @open="(l) => editable && openLine(l)">
      <template #cell-line="{ row }">
        <div class="xg-two"><span class="xg-name">{{ lineName(row) }}</span><span v-if="row.description" class="xg-sub">{{ row.description }}</span></div>
      </template>
      <template #cell-source="{ row }"><span class="xg-pill" :class="sourcePill(row.source)">{{ t('budgetx.source.' + row.source) }}</span></template>
      <template #cell-planned_cents="{ row }">{{ formatTZS(row.planned_cents) }}</template>
      <template #cell-actual_cents="{ row }"><span class="xg-strong">{{ formatTZS(row.actual_cents) }}</span></template>
      <template #cell-remaining_cents="{ row }"><span :class="row.remaining_cents < 0 ? 'xg-out' : 'xg-in'">{{ formatTZS(row.remaining_cents) }}</span></template>
      <template #cell-percent="{ row }">
        <div class="d-flex align-items-center gap-2">
          <div class="progress flex-grow-1" style="height:8px"><div class="progress-bar" :class="barClass(row)" :style="{ width: Math.min(100, row.percent) + '%' }"></div></div>
          <span class="small">{{ row.percent }}%</span>
        </div>
      </template>
      <template #cell-state="{ row }"><span class="xg-pill" :class="statePill(row)">{{ t('budgetx.state.' + stateOf(row)) }}</span></template>
    </XGrid>

    <!-- ── New / rename budget modal ── -->
    <CModal :visible="showForm" class="modal-fullscreen-sm-down" @close="showForm = false">
      <CModalHeader><CModalTitle>{{ editing ? t('budgets.editTitle') : t('budgets.addTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger">{{ formError }}</CAlert>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgets.form.name') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="form.name" :placeholder="t('budgets.form.namePlaceholder')" :invalid="!form.name && formTouched" />
        </div>
        <div v-if="!editing" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgets.form.academicYear') }} <span class="text-danger">*</span></label>
          <CFormSelect v-model="form.academic_year_id" :invalid="!form.academic_year_id && formTouched">
            <option value="">{{ t('budgets.form.selectYear') }}</option>
            <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
          </CFormSelect>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showForm = false">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="saveForm"><CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Add / edit line modal ── -->
    <CModal :visible="showLine" class="modal-fullscreen-sm-down" backdrop="static" @close="showLine = false">
      <CModalHeader><CModalTitle>{{ lineForm.id ? t('budgetx.editLine') : t('budgetx.addLine') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="lineError" color="danger">{{ lineError }}</CAlert>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.type') }}</label>
          <CFormSelect v-model="lineForm.type">
            <option value="expense">{{ t('budgetx.line.typeExpense') }}</option>
            <option value="refunds">{{ t('budgetx.line.typeRefunds') }}</option>
            <option value="manual">{{ t('budgetx.line.typeManual') }}</option>
          </CFormSelect>
        </div>
        <div v-if="lineForm.type === 'expense'" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.category') }} <span class="text-danger">*</span></label>
          <CFormSelect v-model="lineForm.expense_category_id">
            <option value="">— {{ t('common.select') }} —</option>
            <option v-for="c in expenses.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </CFormSelect>
        </div>
        <div v-if="lineForm.type === 'manual'" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.description') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="lineForm.category" />
        </div>
        <div v-if="lineForm.type !== 'manual'" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.description') }}</label>
          <CFormInput v-model="lineForm.description" />
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.planned') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="plannedDisplay" inputmode="numeric" autocomplete="off" placeholder="e.g. 5,000,000" />
        </div>
        <div v-if="lineForm.type === 'manual'" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgetx.line.actualManual') }}</label>
          <CFormInput v-model="actualDisplay" inputmode="numeric" autocomplete="off" />
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showLine = false">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="saveLine"><CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Delete line confirm ── -->
    <CModal :visible="!!lineToDelete" size="sm" @close="lineToDelete = null">
      <CModalHeader><CModalTitle class="text-danger">{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('budgetx.deleteLineConfirm', { name: lineToDelete ? lineName(lineToDelete) : '' }) }}</CModalBody>
      <CModalFooter>
        <CButton color="light" @click="lineToDelete = null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doDeleteLine">{{ t('common.yesDelete') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Delete budget confirm ── -->
    <CModal :visible="!!budgetToDelete" size="sm" @close="budgetToDelete = null">
      <CModalHeader><CModalTitle class="text-danger">{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>{{ t('common.confirmDeleteMsg', { name: budgetToDelete?.name }) }}</CModalBody>
      <CModalFooter>
        <CButton color="light" @click="budgetToDelete = null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="saving" @click="doDeleteBudget">{{ t('common.yesDelete') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Activate confirm ── -->
    <CModal :visible="confirmActivate" size="sm" @close="confirmActivate = false">
      <CModalHeader><CModalTitle>✅ {{ t('budgets.activate') }}</CModalTitle></CModalHeader>
      <CModalBody>Activate <strong>{{ current?.name }}</strong>? This will make it the live budget for the academic year.</CModalBody>
      <CModalFooter>
        <CButton color="light" @click="confirmActivate = false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="saving" @click="doActivate"><CSpinner v-if="saving" size="sm" class="me-1" />Yes, Activate</CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Close budget confirm ── -->
    <CModal :visible="confirmClose" size="sm" @close="confirmClose = false">
      <CModalHeader><CModalTitle>🔒 {{ t('budgetx.close') }}</CModalTitle></CModalHeader>
      <CModalBody>Close <strong>{{ current?.name }}</strong>? A closed budget cannot be edited and becomes a permanent record.</CModalBody>
      <CModalFooter>
        <CButton color="light" @click="confirmClose = false">{{ t('common.cancel') }}</CButton>
        <CButton color="warning" :disabled="saving" @click="doClose"><CSpinner v-if="saving" size="sm" class="me-1" />Yes, Close</CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('budgetx.printTitle') + (current ? ' — ' + current.name : '')" :lines="printLines" :columns="printColumns" :rows="lines" :footer="printFooter" landscape />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBudgetsStore } from '@/stores/budgets'
import { useExpensesStore } from '@/stores/expenses'
import api from '@/services/api'
import XStats from '@/components/XStats.vue'
import XBar from '@/components/XBar.vue'
import XGrid from '@/components/XGrid.vue'
import PrintSheet from '@/components/PrintSheet.vue'

const { t } = useI18n()
const store = useBudgetsStore()
const expenses = useExpensesStore()

const selectedBudgetId = ref(null)
const current   = computed(() => store.budgets.find(b => b.id === selectedBudgetId.value) || null)
const lines     = computed(() => current.value?.items || [])
const summary   = computed(() => current.value?.summary || { planned_cents: 0, actual_cents: 0, over_count: 0 })
const editable  = computed(() => !!current.value && current.value.status !== 'closed')
const successMsg = ref('')
const academicYears = ref([])
const saving = ref(false)
const printing = ref(false)

const selLineId = ref(null)
const selLine = computed(() => lines.value.find(l => l.id === selLineId.value) || null)

// ── Date/burn-rate calculations ──────────────────────────────────────────────
const today = new Date()

const yearStart = computed(() => {
  const d = current.value?.period?.[0]
  return d ? new Date(d) : null
})
const yearEnd = computed(() => {
  const d = current.value?.period?.[1]
  return d ? new Date(d) : null
})
const daysTotal = computed(() => {
  if (!yearStart.value || !yearEnd.value) return null
  return Math.max(1, Math.round((yearEnd.value - yearStart.value) / 86400000))
})
const daysElapsed = computed(() => {
  if (!yearStart.value || !daysTotal.value) return null
  const elapsed = Math.round((today - yearStart.value) / 86400000)
  return Math.max(0, Math.min(daysTotal.value, elapsed))
})
const daysRemaining = computed(() => {
  if (!yearEnd.value) return null
  const rem = Math.round((yearEnd.value - today) / 86400000)
  return Math.max(0, rem)
})
const yearProgress = computed(() => {
  if (!daysTotal.value || daysElapsed.value === null) return null
  return Math.round((daysElapsed.value / daysTotal.value) * 100)
})
const dailyBurnCents = computed(() => {
  if (!daysElapsed.value || daysElapsed.value === 0) return null
  return summary.value.actual_cents / daysElapsed.value
})
const dailyBurnFormatted = computed(() => {
  if (!dailyBurnCents.value) return '—'
  return formatTZS(Math.round(dailyBurnCents.value)) + '/day'
})
const projectedSpendCents = computed(() => {
  if (!dailyBurnCents.value || !daysTotal.value) return null
  return Math.round(dailyBurnCents.value * daysTotal.value)
})

// ── Health status ────────────────────────────────────────────────────────────
const usedPct = computed(() => (summary.value.planned_cents > 0 ? Math.round(summary.value.actual_cents / summary.value.planned_cents * 100) : 0))

const healthStatus = computed(() => {
  if (summary.value.actual_cents > summary.value.planned_cents) return 'over'
  if (yearProgress.value !== null && usedPct.value > yearProgress.value + 10) return 'at-risk'
  return 'on-track'
})
const healthLabel = computed(() => ({ over: '🔴 Over Budget', 'at-risk': '🟠 At Risk', 'on-track': '🟢 On Track' }[healthStatus.value]))
const healthClass = computed(() => ({ over: 'bud-health--red', 'at-risk': 'bud-health--amber', 'on-track': 'bud-health--green' }[healthStatus.value]))
const utilisationBarClass = computed(() => usedPct.value > 100 ? 'bud-bar--red' : usedPct.value >= 80 ? 'bud-bar--amber' : 'bud-bar--green')

// ── Formatters & helpers ─────────────────────────────────────────────────────
function formatTZS(cents) { return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('en-US', { maximumFractionDigits: 0 }) }
const statusLabel = (s) => t('budgets.statuses.' + (['active', 'closed'].includes(s) ? s : 'draft'))
const statusPillClass = (s) => ({ active: 'xg-pill--green', closed: 'xg-pill--gray', draft: 'xg-pill--amber' }[s] || 'xg-pill--gray')
const lineName = (l) => (l.kind === 'refunds' ? t('budgetx.line.refundsName') : (l.category || '—'))
const stateOf  = (l) => (l.over ? 'over' : l.percent >= 80 ? 'warn' : 'ok')
const statePill = (l) => ({ over: 'xg-pill--red', warn: 'xg-pill--amber', ok: 'xg-pill--green' }[stateOf(l)])
const barClass  = (l) => ({ over: 'bg-danger', warn: 'bg-warning', ok: 'bg-success' }[stateOf(l)])
const rowClass  = (l) => (l.over ? 'xg-row--bad' : '')
const sourcePill = (s) => ({ expenses: 'xg-pill--blue', refunds: 'xg-pill--amber', manual: 'xg-pill--gray' }[s])

// ── Stat cards ───────────────────────────────────────────────────────────────
const cards = computed(() => [
  { label: t('budgetx.cards.planned'),   value: formatTZS(summary.value.planned_cents),  tone: 'blue'  },
  { label: t('budgetx.cards.spent'),     value: formatTZS(summary.value.actual_cents),   tone: summary.value.actual_cents > summary.value.planned_cents ? 'red' : 'gray' },
  { label: t('budgetx.cards.remaining'), value: formatTZS(summary.value.planned_cents - summary.value.actual_cents), tone: summary.value.actual_cents > summary.value.planned_cents ? 'red' : 'green' },
  { label: t('budgetx.cards.used'),      value: usedPct.value + '%', tone: usedPct.value > 100 ? 'red' : usedPct.value >= 80 ? 'amber' : 'green' },
  { label: 'Projected spend',            value: projectedSpendCents.value ? formatTZS(projectedSpendCents.value) : '—', tone: projectedSpendCents.value > summary.value.planned_cents ? 'red' : 'green' },
  { label: t('budgetx.cards.over'),      value: summary.value.over_count, tone: summary.value.over_count ? 'red' : 'gray' },
])

// ── Grid columns ─────────────────────────────────────────────────────────────
const columns = computed(() => [
  { key: 'line',           label: t('budgetx.col.line'),      w: 'minmax(190px, 2fr)' },
  { key: 'source',         label: t('budgetx.col.source'),    w: 'minmax(150px, 1.3fr)' },
  { key: 'planned_cents',  label: t('budgetx.col.planned'),   w: 'minmax(130px, 1.1fr)', align: 'end' },
  { key: 'actual_cents',   label: t('budgetx.col.actual'),    w: 'minmax(130px, 1.1fr)', align: 'end' },
  { key: 'remaining_cents',label: t('budgetx.col.remaining'), w: 'minmax(130px, 1.1fr)', align: 'end' },
  { key: 'percent',        label: t('budgetx.col.used'),      w: 'minmax(150px, 1.2fr)' },
  { key: 'state',          label: t('budgetx.col.status'),    w: 'minmax(110px, 1fr)'  },
])

const lineMenu = () => [
  { label: '✏️ ' + t('budgetx.editLine'),   run: openLine },
  { label: '🗑️ ' + t('budgetx.deleteLine'), run: (l) => { lineToDelete.value = l }, danger: true },
]

// ── Print ────────────────────────────────────────────────────────────────────
const printColumns = computed(() => [
  { label: t('budgetx.col.line'),      value: l => lineName(l) + (l.description ? ' — ' + l.description : '') },
  { label: t('budgetx.col.source'),    value: l => t('budgetx.source.' + l.source) },
  { label: t('budgetx.col.planned'),   value: l => formatTZS(l.planned_cents),   align: 'end' },
  { label: t('budgetx.col.actual'),    value: l => formatTZS(l.actual_cents),    align: 'end' },
  { label: t('budgetx.col.remaining'), value: l => formatTZS(l.remaining_cents), align: 'end' },
  { label: t('budgetx.col.used'),      value: l => l.percent + '%',              align: 'end' },
  { label: t('budgetx.col.status'),    value: l => t('budgetx.state.' + stateOf(l)) },
])
const printLines  = computed(() => (current.value ? [`${current.value.academic_year || ''} · ${current.value.period?.[0] || ''} → ${current.value.period?.[1] || ''}`] : []))
const printFooter = computed(() => [[t('common.total'), '', formatTZS(summary.value.planned_cents), formatTZS(summary.value.actual_cents), formatTZS(summary.value.planned_cents - summary.value.actual_cents), usedPct.value + '%', '']])

// ── Budget CRUD ───────────────────────────────────────────────────────────────
const showForm = ref(false)
const editing  = ref(null)
const form     = ref({ name: '', academic_year_id: '' })
const formError   = ref('')
const formTouched = ref(false)
const budgetToDelete = ref(null)
const confirmActivate = ref(false)
const confirmClose    = ref(false)

function pickBudget() { selLineId.value = null }
function openCreate() { editing.value = null; form.value = { name: '', academic_year_id: '' }; formError.value = ''; formTouched.value = false; showForm.value = true }
function openEdit()   { editing.value = current.value; form.value = { name: current.value.name, academic_year_id: current.value.academic_year_id || '' }; formError.value = ''; formTouched.value = false; showForm.value = true }

async function reload(keepId) {
  await store.fetchBudgets()
  selectedBudgetId.value = store.budgets.some(b => b.id === keepId) ? keepId : (store.budgets.find(b => b.status === 'active') || store.budgets[0])?.id ?? null
}

async function saveForm() {
  formTouched.value = true
  if (!form.value.name || (!editing.value && !form.value.academic_year_id)) return
  saving.value = true; formError.value = ''
  try {
    if (editing.value) {
      await store.updateBudget(editing.value.id, { name: form.value.name })
      await reload(editing.value.id)
    } else {
      const made = await store.createBudget({ name: form.value.name, academic_year_id: form.value.academic_year_id })
      await reload(made?.id)
    }
    showForm.value = false
    successMsg.value = t('budgets.messages.saved')
  } catch (e) {
    formError.value = e?.response?.data?.message || t('budgets.errors.save')
  } finally { saving.value = false }
}

async function doActivate() {
  saving.value = true
  try { await store.activateBudget(current.value.id); confirmActivate.value = false; successMsg.value = t('budgets.messages.activated'); await reload(current.value.id) }
  catch (e) { store.error = e?.response?.data?.message || t('common.error') }
  finally { saving.value = false }
}
async function doClose() {
  saving.value = true
  try { await store.closeBudget(current.value.id); confirmClose.value = false; successMsg.value = t('budgets.messages.closed'); await reload(current.value.id) }
  catch (e) { store.error = e?.response?.data?.message || t('common.error') }
  finally { saving.value = false }
}
async function doDeleteBudget() {
  saving.value = true
  try { await store.deleteBudget(budgetToDelete.value.id); budgetToDelete.value = null; successMsg.value = t('budgets.messages.deleted'); await reload(null) }
  catch (e) { budgetToDelete.value = null; store.error = e?.response?.data?.message || t('budgets.errors.delete') }
  finally { saving.value = false }
}

// ── Line CRUD ─────────────────────────────────────────────────────────────────
const showLine  = ref(false)
const lineForm  = ref({})
const lineError = ref('')
const lineToDelete = ref(null)
const digits   = (raw) => { const d = String(raw ?? '').replace(/[^0-9]/g, ''); return d === '' ? '' : Number(d) }
const grouped  = (v)   => (v === '' || v == null ? '' : Number(v).toLocaleString('en-US'))
const plannedDisplay = computed({ get: () => grouped(lineForm.value.planned), set: (v) => { lineForm.value.planned = digits(v) } })
const actualDisplay  = computed({ get: () => grouped(lineForm.value.actual),  set: (v) => { lineForm.value.actual  = digits(v) } })

function openLine(l) {
  lineError.value = ''
  lineForm.value = l
    ? { id: l.id, type: l.kind === 'refunds' ? 'refunds' : (l.expense_category_id ? 'expense' : 'manual'), expense_category_id: l.expense_category_id || '', category: l.category, description: l.description || '', planned: Math.round(l.planned_cents / 100), actual: Math.round((l.source === 'manual' ? l.actual_cents : 0) / 100) }
    : { id: null, type: 'expense', expense_category_id: '', category: '', description: '', planned: '', actual: '' }
  showLine.value = true
}

async function saveLine() {
  const f = lineForm.value
  if (f.type === 'expense' && !f.expense_category_id) { lineError.value = t('budgetx.line.needCategory'); return }
  if (f.type === 'manual'  && !f.category.trim())      { lineError.value = t('budgetx.line.needCategory'); return }
  if (f.planned === '' || f.planned == null)            { lineError.value = t('budgetx.line.needPlanned');  return }
  const cat = expenses.categories.find(c => c.id === f.expense_category_id)
  const item = {
    ...(f.id ? { id: f.id } : {}),
    kind: f.type === 'refunds' ? 'refunds' : 'expense',
    expense_category_id: f.type === 'expense' ? f.expense_category_id : null,
    category: f.type === 'refunds' ? t('budgetx.line.refundsName') : f.type === 'expense' ? (cat?.name || '') : f.category.trim(),
    description: f.description || (f.type === 'manual' ? f.category.trim() : (f.type === 'refunds' ? t('budgetx.line.refundsName') : (cat?.name || ''))),
    planned_cents: Math.round(f.planned * 100),
    ...(f.type === 'manual' ? { actual_cents: Math.round((f.actual || 0) * 100) } : {}),
  }
  saving.value = true; lineError.value = ''
  try {
    await store.updateBudget(current.value.id, { items: [item] })
    await reload(current.value.id)
    showLine.value = false
  } catch (e) {
    lineError.value = e?.response?.data?.message || Object.values(e?.response?.data?.errors || {})[0]?.[0] || t('common.error')
  } finally { saving.value = false }
}

async function doDeleteLine() {
  saving.value = true
  try { await store.deleteLine(current.value.id, lineToDelete.value.id); lineToDelete.value = null; selLineId.value = null; await reload(current.value.id) }
  catch (e) { lineToDelete.value = null; store.error = e?.response?.data?.message || t('common.error') }
  finally { saving.value = false }
}

onMounted(async () => {
  try { await expenses.fetchCategories() } catch {}
  try { await reload(null) } catch {}
  try { const { data } = await api.get('/academic-years'); academicYears.value = data.data || data } catch {}
})
</script>

<style scoped>
/* ── Health panel ─────────────────────────────────────────────────────────── */
.bud-health-panel {
  background: var(--cui-body-bg, #fff);
  border: 1px solid var(--cui-border-color, #dee2e6);
  border-radius: 10px;
  padding: 1rem 1.25rem;
}

.bud-health-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-bottom: 0.9rem;
}

.bud-health-title {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  flex-wrap: wrap;
}

.bud-budget-name {
  font-weight: 700;
  font-size: 1rem;
  color: var(--cui-body-color, #212529);
}

.bud-year-badge {
  font-size: 0.78rem;
  color: var(--cui-secondary-color, #6c757d);
}

.bud-health-badge {
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.3rem 0.75rem;
  border-radius: 20px;
  white-space: nowrap;
}
.bud-health--green { background: #d1fae5; color: #065f46; }
.bud-health--amber { background: #fef3c7; color: #92400e; }
.bud-health--red   { background: #fee2e2; color: #991b1b; }

/* ── Bar fill colours (shared) ────────────────────────────────────────────── */
.bud-bar-fill {
  height: 100%;
  border-radius: 4px;
  transition: width 0.4s ease;
}
.bud-bar--gray  { background: #94a3b8; }
.bud-bar--green { background: #22c55e; }
.bud-bar--amber { background: #f59e0b; }
.bud-bar--red   { background: #ef4444; }

/* ── Inline progress bars inside the metrics row ─────────────────────────── */
.bud-metric--bars {
  flex: 1;
  min-width: 180px;
  border-right: none !important;
}

.bud-inline-bar-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 3px;
}
.bud-inline-bar-row:last-child { margin-bottom: 0; }

.bud-inline-label {
  width: 80px;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--cui-secondary-color, #6c757d);
  flex-shrink: 0;
}

.bud-inline-track {
  flex: 1;
  height: 5px;
  border-radius: 3px;
  background: var(--cui-tertiary-bg, #e9ecef);
  overflow: hidden;
}

.bud-inline-pct {
  width: 34px;
  text-align: right;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--cui-body-color, #212529);
}

/* ── Key metrics row ──────────────────────────────────────────────────────── */
.bud-metrics {
  display: flex;
  gap: 0;
  flex-wrap: wrap;
  border-top: 1px solid var(--cui-border-color, #dee2e6);
  padding-top: 0.75rem;
}

.bud-metric {
  display: flex;
  flex-direction: column;
  padding: 0 1.2rem 0 0;
  margin-right: 1.2rem;
  border-right: 1px solid var(--cui-border-color, #dee2e6);
}
.bud-metric:last-child { border-right: none; margin-right: 0; }

.bud-metric-label {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--cui-secondary-color, #6c757d);
  margin-bottom: 2px;
}

.bud-metric-value {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--cui-body-color, #212529);
}

.bud-metric--alert .bud-metric-value { color: #dc2626; }

@media (max-width: 576px) {
  .bud-metrics { gap: 0.75rem; }
  .bud-metric  { border-right: none; padding-right: 0; margin-right: 0; border-bottom: 1px solid var(--cui-border-color, #dee2e6); padding-bottom: 0.5rem; }
  .bud-metric:last-child { border-bottom: none; }
  .bud-bar-label { width: 80px; }
}
</style>
