<template>
  <CContainer fluid class="sup-page p-2 p-md-3">
    <!-- At-a-glance numbers -->
    <CRow class="g-2 mb-3">
      <CCol v-for="card in cards" :key="card.key" xs="6" md="4" xl="2">
        <div class="border rounded p-2 h-100" :class="card.warn && card.value > 0 ? 'border-warning' : ''">
          <div class="small text-muted">{{ t('purchasing.overview.' + card.key) }}</div>
          <div class="fw-bold" :class="card.cls">{{ card.money ? formatMoney(card.value) : card.value }}</div>
        </div>
      </CCol>
    </CRow>

    <!-- Tabs and the active tab's filters / action button share one row that stays put while the data scrolls -->
    <div ref="barEl" class="sup-bar d-flex flex-wrap align-items-center gap-2" :style="{ top: barTop + 'px' }">
      <CNav variant="tabs" class="flex-nowrap sup-tabs">
        <CNavItem v-for="tab in tabs" :key="tab">
          <CNavLink :active="active === tab" class="text-nowrap" @click="selectTab(tab)">
            {{ t('purchasing.tabs.' + tab) }}
            <CBadge v-if="badge(tab)" color="warning" class="ms-1">{{ badge(tab) }}</CBadge>
          </CNavLink>
        </CNavItem>
      </CNav>
      <!-- the active tab teleports its toolbar here -->
      <div id="sup-toolbar" class="sup-toolbar ms-auto d-flex flex-wrap align-items-center justify-content-end gap-2"></div>
    </div>
    <div class="mb-3"></div>

    <SuppliersTab v-if="active === 'suppliers'" :key="tabKey" @created="onSupplierCreated" @changed="loadOverview" @open-bill="goBill" @open-pay="goPay" @open-receive="goReceive" />
    <BillsTab v-else-if="active === 'bills'" :key="tabKey" :preset="preset" @created="onBillCreated" @changed="loadOverview" />
    <PaymentsTab v-else-if="active === 'payments'" :key="tabKey" :preset="preset" @created="onPaymentCreated" @changed="loadOverview" />
    <GoodsReceiptsTab v-else-if="active === 'receipts'" :preset="preset" @changed="loadOverview" />
    <DocumentsTab v-else-if="active === 'documents'" />
    <ReportsTab v-else-if="active === 'reports'" />
    <SettingsTab v-else-if="active === 'settings'" @changed="onSettingsChanged" />

    <NextStepDialog :step="nextStep" :busy="flowBusy" :error="flowError" @yes="flowYes" @no="flowNo" />
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { purchasing, formatMoney, loadAccess, access } from '@/services/purchasing'
import SuppliersTab from './SuppliersTab.vue'
import BillsTab from './BillsTab.vue'
import PaymentsTab from './PaymentsTab.vue'
import GoodsReceiptsTab from './GoodsReceiptsTab.vue'
import DocumentsTab from './DocumentsTab.vue'
import ReportsTab from './ReportsTab.vue'
import SettingsTab from './SettingsTab.vue'
import NextStepDialog from './NextStepDialog.vue'

const { t } = useI18n()

const tabs = ['suppliers', 'bills', 'payments', 'receipts', 'documents', 'reports', 'settings']
const route = useRoute()
// A notification can open a specific tab (?tab=bills).
const active = ref(tabs.includes(route.query.tab) ? route.query.tab : 'suppliers')
// Lets "New bill" / "Pay" / "Receive goods" on a supplier row open the right tab with that supplier chosen.
const preset = ref(null)

const overview = ref({})
const cards = computed(() => [
  { key: 'totalOwed', value: overview.value.total_owed_cents || 0, money: true, cls: 'text-danger' },
  { key: 'overdue', value: overview.value.overdue_cents || 0, money: true, cls: 'text-danger', warn: true },
  { key: 'dueThisWeek', value: overview.value.due_this_week_cents || 0, money: true },
  { key: 'billsAwaiting', value: overview.value.bills_awaiting_approval || 0, warn: true },
  { key: 'paymentsAwaiting', value: overview.value.payments_awaiting_approval || 0, warn: true },
  { key: 'activeSuppliers', value: overview.value.active_suppliers || 0 },
])

function badge(tab) {
  if (tab === 'bills') return overview.value.bills_awaiting_approval || 0
  if (tab === 'payments') return overview.value.payments_awaiting_approval || 0
  return 0
}

// Access switches are part of the settings: reload both after a save so the menus follow at once.
function onSettingsChanged() {
  loadAccess()
  loadOverview()
}

async function loadOverview() {
  try { overview.value = await purchasing.overview() } catch { /* the strip is optional */ }
}

// Opening a tab by hand must not re-open the "New ..." form a row action asked for earlier.
function selectTab(tab) {
  preset.value = null
  active.value = tab
}

function go(tab, supplier) {
  preset.value = { supplier, openNew: true, at: Date.now() }
  active.value = tab
}
// ── Guided flow: supplier -> bill -> approval -> payment. After each step a card asks whether to carry on. ──
const nextStep = ref(null)   // what the card shows now
const flowBusy = ref(false)
const flowError = ref('')
const tabKey = ref(0)        // bumped to reload the tab behind the card
let onYes = null

function ask(step, yes) { nextStep.value = step; onYes = yes; flowError.value = '' }
function endFlow() { nextStep.value = null; onYes = null; flowError.value = '' }
function refreshTab() { preset.value = null; tabKey.value++ }
const flowNo = () => { endFlow(); refreshTab() }
async function flowYes() {
  flowBusy.value = true
  try { await onYes?.() } finally { flowBusy.value = false }
}

function onSupplierCreated(s) {
  ask({ current: 'supplier', finished: true, title: t('purchasing.flow.supplierTitle'), text: t('purchasing.flow.supplierText', { name: s.name }) },
    () => { endFlow(); go('bills', s) })
}

function onBillCreated(bill) {
  const supplier = bill.supplier || { id: bill.supplier_id, name: '' }
  const num = bill.bill_number
  if (!access.can.bill_approve) {
    ask({ current: 'bill', finished: true, info: true, title: t('purchasing.flow.sentTitle'), text: t('purchasing.flow.sentText', { number: num }) }, null)
    return
  }
  ask({ current: 'bill', finished: true, title: t('purchasing.flow.approveTitle'), text: t('purchasing.flow.approveText', { number: num }) }, async () => {
    try {
      await purchasing.approveBill(bill.id)
    } catch (e) {
      flowError.value = e?.response?.data?.message || t('purchasing.common.saveFailed')
      return
    }
    loadOverview()
    ask({ current: 'approve', finished: true, title: t('purchasing.flow.payTitle'), text: t('purchasing.flow.payText', { number: num }) },
      () => { endFlow(); go('payments', supplier) })
  })
}

function onPaymentCreated(payment) {
  if (!String(payment.status || '').includes('pending')) {
    ask({ current: 'pay', finished: true, info: true, title: t('purchasing.flow.doneTitle'), text: t('purchasing.flow.doneText') }, null)
  } else {
    ask({ current: 'pay', finished: true, info: true, title: t('purchasing.flow.sentTitle'), text: t('purchasing.flow.paymentSentText') }, null)
  }
}

const goBill = (s) => go('bills', s)
const goPay = (s) => go('payments', s)
const goReceive = (s) => go('receipts', s)

// The app header is sticky too; the bar sticks just under it, whatever height the header has (it differs on phones).
const barEl = ref(null)
const barTop = ref(0)
let headerObserver = null
function measureHeader() {
  const header = document.querySelector('.header-sticky, .header')
  barTop.value = header ? Math.round(header.getBoundingClientRect().height) : 0
}

onMounted(() => {
  loadAccess()
  loadOverview()
  measureHeader()
  const header = document.querySelector('.header-sticky, .header')
  if (header && 'ResizeObserver' in window) {
    headerObserver = new ResizeObserver(measureHeader)
    headerObserver.observe(header)
  }
  window.addEventListener('resize', measureHeader)
})
onBeforeUnmount(() => {
  headerObserver?.disconnect()
  window.removeEventListener('resize', measureHeader)
})
</script>

<style scoped>
.sup-bar {
  position: sticky;
  z-index: 1030; /* under the app header (1040), over the page content */
  background: var(--cui-body-bg, #fff);
  padding-top: 0.5rem;
  border-bottom: 1px solid var(--cui-border-color, #dee2e6);
}
/* On a phone the tabs + filters would fill the screen, so they scroll with the page there. */
@media (max-width: 767.98px) {
  .sup-bar { position: static; }
}
.sup-tabs { border-bottom: 0; flex: 0 0 auto; }
.sup-tabs .nav-link { padding: 0.45rem 0.7rem; font-size: 0.9rem; white-space: nowrap; }

.sup-toolbar { flex: 1 1 auto; padding-bottom: 0.5rem; }
</style>

