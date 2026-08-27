<script setup>
import { CChart } from '@coreui/vue-chartjs'
import { CIcon } from '@coreui/icons-vue'
import {
  cilBed,
  cilPeople,
  cilMedicalCross,
  cilClock,
  cilXCircle,
  cilChartLine,
  cilHistory,
  cilHospital,
  cilFile,
  cilUser,
  cilChevronBottom,
  cilChevronTop,
  cilInfo,
  cilPrint,
} from '@coreui/icons'
import { useDashboardStore } from '@/stores/dashboard'
import { useI18n } from 'vue-i18n'
import { ref, computed, watch } from 'vue'
import api from '@/services/api'

const { t } = useI18n()

const dashboard = useDashboardStore()

const getValue = (key) => {
  return (dashboard.realStats?.[key] || 0).toLocaleString()
}


const getPrevValue = (key) => {
  return (dashboard.previousStats?.[key] || 0).toLocaleString()
}

// ── Students with discount card (formerly "Absent Today") ─────────────────
const showDiscountList  = ref(false)
const discountedByClass = ref([])
const isDiscountLoading = ref(false)

const totalDiscounted = computed(() =>
  discountedByClass.value.reduce((s, c) => s + (c.discounted || 0), 0)
)

const toggleDiscountList = async () => {
  showDiscountList.value = !showDiscountList.value
  if (showDiscountList.value) {
    await loadDiscountedByClass()
  }
}

const loadDiscountedByClass = async () => {
  isDiscountLoading.value = true
  try {
    discountedByClass.value = await dashboard.fetchDiscountedByClass()
  } catch (e) {
    console.error('fetchDiscountedByClass error', e)
    discountedByClass.value = []
  } finally {
    isDiscountLoading.value = false
  }
}

// Auto-load on mount so the count shows immediately
loadDiscountedByClass()

// Refresh when school changes
watch(
  () => [dashboard.selectedDay, dashboard.selectedWeek, dashboard.selectedMonth, dashboard.selectedYear, dashboard.selectedRange],
  () => {
    discountedByClass.value = []
    if (showDiscountList.value) loadDiscountedByClass()
  },
  { deep: true },
)

// ── Outstanding debts → Excel export ───────────────────────────────────────
const isExportingDebts = ref(false)
const exportOutstandingDebts = async () => {
  isExportingDebts.value = true
  try {
    const response = await api.get('/reports/outstanding-debts/excel', { responseType: 'blob' })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `outstanding_debts_${new Date().toISOString().slice(0, 10)}.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    console.error('exportOutstandingDebts error', e)
  } finally {
    isExportingDebts.value = false
  }
}

// ── Legacy pending list (kept to avoid breaking other callers) ────────────
const showPendingList = ref(false)
const pendingPatients = ref([])
const isListLoading   = ref(false)
const togglePendingList = () => {}
const fetchPendingPatients = () => {}
</script>

<template>
  <div class="metrics-grid mb-0 px-0">
    <CRow
      :gutter="3"
      class="row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-lg-6 g-3 px-0 mx-0 metrics-row"
    >
      <!-- Total OPD -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-indigo"
          style="border-left: 4px solid #6366f1; border-top: 1px solid #6366f1"
        >
          <div class="stat-card-header mb-1">
            <div class="stat-icon-wrapper" style="background-color: rgba(99, 102, 241, 0.15)">
              <CIcon :icon="cilPeople" class="stat-icon" style="color: #6366f1" />
            </div>
            <div class="stat-main-info">
              <h3 class="stat-value" style="color: #6366f1">{{ getValue('total_patients') }}</h3>
              <span class="stat-label">{{ t('dashboard.cardTotalStudents') }}</span>
            </div>
          </div>
          <div
            v-if="dashboard.compLabel"
            class="stat-card-footer mt-auto pt-1"
          >
            <div class="stat-comparison">
              <span class="prev-value text-muted">{{ getPrevValue('total_patients') }}</span>
              <span class="prev-label ms-1">{{ dashboard.compLabel }}</span>
            </div>
          </div>
        </div>
      </CCol>

      <!-- Total Emergency -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-rose"
          style="
            border-left: 4px solid #f43f5e;
            border-top: 1px solid #f43f5e;
            position: relative;
            overflow: hidden;
          "
        >
          <div class="stat-card-header mb-1" style="position: relative; z-index: 2">
            <div
              class="stat-icon-wrapper"
              style="background-color: rgba(244, 63, 94, 0.15)"
            >
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                class="stat-icon"
              >
                <rect x="10" y="2" width="4" height="20" rx="1" fill="#f43f5e" />
                <rect x="2" y="10" width="20" height="4" rx="1" fill="#f43f5e" />
              </svg>
            </div>
            <div class="stat-main-info">
              <div class="d-flex align-items-center mb-0">
                <h3 class="stat-value mb-0" style="color: #f43f5e">
                  TZS {{ getValue('emergency_visits') }}
                </h3>
              </div>
              <span class="stat-label">{{ t('dashboard.cardDebt') }}</span>
            </div>
            <button
              type="button"
              class="stat-print-btn"
              :title="t('dashboard.printOutstandingDebts', 'Print outstanding debts to Excel')"
              :disabled="isExportingDebts"
              @click.stop="exportOutstandingDebts"
            >
              <span v-if="isExportingDebts" class="spinner-border spinner-border-sm" style="width:0.9rem;height:0.9rem;border-width:2px;"></span>
              <CIcon v-else :icon="cilPrint" size="sm" style="color:#f43f5e" />
            </button>
          </div>
          <div
            v-if="dashboard.compLabel"
            class="stat-card-footer mt-auto pt-1"
            style="position: relative; z-index: 2"
          >
            <div class="stat-comparison">
              <span class="prev-value text-muted">{{ getPrevValue('emergency_visits') }}</span>
              <span class="prev-label ms-1">{{ dashboard.compLabel }}</span>
            </div>
          </div>
        </div>
      </CCol>

      <!-- New Visits -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-sky"
          style="border-left: 4px solid #0ea5e9; border-top: 1px solid #0ea5e9"
        >
          <div class="stat-card-header mb-1">
            <div class="stat-icon-wrapper" style="background-color: rgba(14, 165, 233, 0.15)">
              <CIcon :icon="cilClock" class="stat-icon" style="color: #0ea5e9" />
            </div>
            <div class="stat-main-info">
              <h3 class="stat-value" style="color: #0ea5e9">{{ getValue('new_visits') }}</h3>
              <span class="stat-label">{{ t('dashboard.cardSponsoredFree') }}</span>
            </div>
          </div>
          <div
            v-if="dashboard.compLabel"
            class="stat-card-footer mt-auto pt-1"
          >
            <div class="stat-comparison">
              <span class="prev-value text-muted">{{ getPrevValue('new_visits') }}</span>
              <span class="prev-label ms-1">{{ dashboard.compLabel }}</span>
            </div>
          </div>
        </div>
      </CCol>

      <!-- Followups -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-violet"
          style="border-left: 4px solid #a855f7; border-top: 1px solid #a855f7"
        >
          <div class="stat-card-header mb-1">
            <div class="stat-icon-wrapper" style="background-color: rgba(168, 85, 247, 0.15)">
              <CIcon :icon="cilUser" class="stat-icon" style="color: #a855f7" />
            </div>
            <div class="stat-main-info">
              <h3 class="stat-value" style="color: #a855f7">{{ getValue('followups') }}</h3>
              <span class="stat-label">{{ t('dashboard.cardTodayCollect') }}</span>
            </div>
          </div>
          <div
            v-if="dashboard.compLabel"
            class="stat-card-footer mt-auto pt-1"
          >
            <div class="stat-comparison">
              <span class="prev-value text-muted">{{ getPrevValue('followups') }}</span>
              <span class="prev-label ms-1">{{ dashboard.compLabel }}</span>
            </div>
          </div>
        </div>
      </CCol>

      <!-- Total Consulted -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-emerald"
          style="border-left: 4px solid #10b981; border-top: 1px solid #10b981"
        >
          <div class="stat-card-header mb-1">
            <div class="stat-icon-wrapper" style="background-color: rgba(16, 185, 129, 0.15)">
              <CIcon :icon="cilFile" class="stat-icon" style="color: #10b981" />
            </div>
            <div class="stat-main-info">
              <h3 class="stat-value" style="color: #10b981">
                {{ getValue('consulted') }} | TZS {{ getValue('consulted_amount') }}
              </h3>
              <span class="stat-label">{{ t('dashboard.cardPaidInvoices') }}</span>
            </div>
          </div>
          <div
            v-if="dashboard.compLabel"
            class="stat-card-footer mt-auto pt-1"
          >
            <div class="stat-comparison">
              <span class="prev-value text-muted">{{ getPrevValue('consulted') }}</span>
              <span class="prev-label ms-1">{{ dashboard.compLabel }}</span>
            </div>
          </div>
        </div>
      </CCol>

      <!-- Students with Discount -->
      <CCol class="metric-col">
        <div
          class="stat-card premium-shadow shadow-red red-theme-active"
          :class="{ expanded: showDiscountList }"
        >
          <div class="notch-border top red"></div>
          <div class="notch-border bottom red"></div>

          <div class="stat-card-header mb-1" @click="toggleDiscountList" style="cursor:pointer;">
            <div class="stat-icon-wrapper" style="background:rgba(239,68,68,0.12);">
              <!-- Discount tag -->
              <svg viewBox="0 0 24 24" class="stat-icon" style="color:#ef4444;" fill="currentColor">
                <path d="M20.6 12.3 12.7 20.2c-.6.6-1.6.6-2.2 0L3.2 12.9c-.3-.3-.5-.7-.5-1.1V4.5c0-.8.7-1.5 1.5-1.5h7.3c.4 0 .8.2 1.1.5l7.9 7.9c.7.6.7 1.6.1 2.2ZM7.5 8.5A1.5 1.5 0 1 0 7.5 5.5A1.5 1.5 0 1 0 7.5 8.5Z"/>
              </svg>
            </div>
            <div class="stat-main-info">
              <h3 class="stat-value" style="color:#ef4444;">
                <span v-if="isDiscountLoading" class="spinner-border spinner-border-sm" style="width:1rem;height:1rem;border-width:2px;"></span>
                <span v-else>{{ totalDiscounted }}</span>
                <CIcon :icon="showDiscountList ? cilChevronTop : cilChevronBottom" size="sm" class="ms-1 dropdown-arrow" />
              </h3>
              <span class="stat-label">{{ t('dashboard.cardDiscounted', 'STUDENTS WITH DISCOUNT') }}</span>
            </div>
          </div>

          <!-- Per-class discounted-students breakdown dropdown -->
          <div v-if="showDiscountList" class="pending-list-container">
            <div v-if="isDiscountLoading" class="text-center py-2">
              <div class="spinner-border spinner-border-sm text-danger" role="status"></div>
            </div>
            <div v-else-if="discountedByClass.length === 0" class="no-data-text">
              {{ t('dashboard.noDiscountedStudents', 'No students with a discount') }}
            </div>
            <div v-else class="patient-list">
              <!-- Total row -->
              <div class="patient-item absent-total-row">
                <span class="mr-number fw-bold">Jumla</span>
                <span class="absent-count-badge absent-count-total">{{ totalDiscounted }}</span>
              </div>
              <!-- Per class rows -->
              <div v-for="cls in discountedByClass" :key="cls.class_id" class="patient-item">
                <span class="mr-number">{{ cls.class_name || '—' }}</span>
                <span class="absent-count-badge">{{ cls.discounted }}</span>
              </div>
            </div>
          </div>
        </div>
      </CCol>
    </CRow>
  </div>
</template>

<style scoped>
.dropdown-arrow {
  transition: transform 0.3s ease;
  font-size: 0.8rem;
  vertical-align: middle;
  opacity: 0.7;
}

.stat-card.expanded {
  border-bottom: 3px solid #ec4899;
  z-index: 1051; /* Higher than siblings when open */
}

.stat-card-footer {
  border-top: 1px solid rgba(0, 0, 0, 0.05);
  line-height: 1;
}

.stat-main-info {
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
  min-width: 0;
}

.stat-value {
  font-size: clamp(0.95rem, 1.6vw, 1.5rem);
  font-weight: 850;
  margin-bottom: 0;
  line-height: 1.1;
  word-break: break-word;
  overflow-wrap: anywhere;
}

.stat-print-btn {
  margin-left: auto;
  align-self: flex-start;
  background: rgba(244, 63, 94, 0.1);
  border: none;
  border-radius: 6px;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s ease;
}

.stat-print-btn:hover:not(:disabled) {
  background: rgba(244, 63, 94, 0.2);
}

.stat-print-btn:disabled {
  cursor: wait;
  opacity: 0.7;
}

.stat-label {
  font-size: 0.75rem;
  font-weight: 750;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.2px;
  line-height: 1.2;
  word-break: break-word;
  white-space: normal;
}

.stat-comparison {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.prev-value {
  font-size: 0.9rem; /* Increased from 0.85rem */
  font-weight: 700;
}

.prev-label {
  font-size: 0.65rem; /* Increased */
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.stat-card {
  position: relative; /* Base for absolute positioning */
  transition: all 0.3s ease;
}

.pending-list-container {
  position: absolute;
  top: 100%; /* Position right below the card */
  left: 0;
  right: 0;
  z-index: 1050; /* Bootstrap dropdown level */
  max-height: 250px;
  overflow-y: auto;
  padding: 0.5rem;
  background: white; /* Solid white to cover backgrounds */
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-top: none;
  border-radius: 0 0 8px 8px;
  box-shadow:
    0 10px 15px -3px rgba(0, 0, 0, 0.1),
    0 4px 6px -2px rgba(0, 0, 0, 0.05);
  animation: slideDown 0.2s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.patient-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.patient-item {
  display: flex;
  align-items: center;
  font-size: 0.85rem; /* Increased from 0.75rem */
  font-weight: 600;
  color: #4b5563;
  padding: 4px 8px;
  background: white;
  border-radius: 4px;
  border: 1px solid rgba(0, 0, 0, 0.03);
}

.mr-number {
  flex-grow: 1;
  font-family: 'Monaco', 'Consolas', monospace;
}

.visit-time {
  font-size: 0.65rem;
  color: #9ca3af;
}

.no-data-text {
  font-size: 0.75rem;
  color: #9ca3af;
  text-align: center;
  padding: 1rem;
}

.absent-count-badge {
  font-size: 0.72rem;
  font-weight: 700;
  background: rgba(239, 68, 68, 0.12);
  color: #ef4444;
  border-radius: 10px;
  padding: 1px 8px;
  min-width: 28px;
  text-align: center;
  flex-shrink: 0;
}

.absent-count-total {
  background: #ef4444;
  color: #fff;
}

.absent-total-row {
  border-bottom: 1px solid rgba(239, 68, 68, 0.15);
  margin-bottom: 2px;
  padding-bottom: 4px;
}

/* Custom Scrollbar */
.pending-list-container::-webkit-scrollbar {
  width: 4px;
}
.pending-list-container::-webkit-scrollbar-track {
  background: transparent;
}
.pending-list-container::-webkit-scrollbar-thumb {
  background: #ec4899;
  border-radius: 10px;
}
/* Colored Shadow Effects - More pronounced default state */
.shadow-indigo {
  box-shadow:
    0 6px 20px -4px rgba(99, 102, 241, 0.4),
    0 4px 12px -2px rgba(99, 102, 241, 0.2) !important;
}
.shadow-indigo:hover {
  box-shadow: 0 12px 30px -5px rgba(99, 102, 241, 0.6) !important;
}

.shadow-rose {
  box-shadow:
    0 6px 20px -4px rgba(244, 63, 94, 0.4),
    0 4px 12px -2px rgba(244, 63, 94, 0.2) !important;
}
.shadow-rose:hover {
  box-shadow: 0 12px 30px -5px rgba(244, 63, 94, 0.6) !important;
}

.shadow-sky {
  box-shadow:
    0 6px 20px -4px rgba(14, 165, 233, 0.4),
    0 4px 12px -2px rgba(14, 165, 233, 0.2) !important;
}
.shadow-sky:hover {
  box-shadow: 0 12px 30px -5px rgba(14, 165, 233, 0.6) !important;
}

.shadow-violet {
  box-shadow:
    0 6px 20px -4px rgba(168, 85, 247, 0.4),
    0 4px 12px -2px rgba(168, 85, 247, 0.2) !important;
}
.shadow-violet:hover {
  box-shadow: 0 12px 30px -5px rgba(168, 85, 247, 0.6) !important;
}

.shadow-emerald {
  box-shadow:
    0 6px 20px -4px rgba(16, 185, 129, 0.4),
    0 4px 12px -2px rgba(16, 185, 129, 0.2) !important;
}
.shadow-emerald:hover {
  box-shadow: 0 12px 30px -5px rgba(16, 185, 129, 0.6) !important;
}

.shadow-orange {
  box-shadow:
    0 6px 20px -4px rgba(249, 115, 22, 0.4),
    0 4px 12px -2px rgba(249, 115, 22, 0.2) !important;
}
.shadow-orange:hover {
  box-shadow: 0 12px 30px -5px rgba(249, 115, 22, 0.6) !important;
}

.shadow-red {
  box-shadow:
    0 6px 20px -4px rgba(239, 68, 68, 0.4),
    0 4px 12px -2px rgba(239, 68, 68, 0.2) !important;
}
.shadow-red:hover {
  box-shadow: 0 12px 30px -5px rgba(239, 68, 68, 0.6) !important;
}

.status-info-icon.mirror-design {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 38px;
  height: 38px;
  background: white;
  color: #ff0000;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 10;
  box-shadow: 0 0 15px rgba(255, 0, 0, 0.2);
  border: 1.5px solid #ff0000;
}

.status-info-icon.mirror-design :deep(svg) {
  width: 20px;
  height: 20px;
}

.notch-border {
  position: absolute;
  z-index: 5;
}

.notch-border.orange { background: #f97316; }
.notch-border.red    { background: #ef4444; }

.notch-border.top {
  top: 0;
  left: 0;
  width: 70%;
  height: 4px;
  border-radius: 0 0 4px 0;
  clip-path: polygon(0 0, 100% 0, 95% 100%, 0 100%);
}

.notch-border.bottom {
  bottom: 0;
  left: 0;
  width: 35%;
  height: 8px;
  border-radius: 0 4px 0 0;
  clip-path: polygon(0 0, 90% 0, 100% 100%, 0 100%);
}

.red-theme-active {
  background-color: white !important;
  border-left: 4px solid #ef4444 !important;
  border-top: 1px solid #fee2e2 !important;
  border-right: 1px solid #fee2e2 !important;
  border-bottom: 1px solid #fee2e2 !important;
  position: relative;
}

.orange-theme-active {
  background-color: white !important;
  border-left: 4px solid #f97316 !important;
  border-top: 1px solid #ffedd5 !important;
  border-right: 1px solid #ffedd5 !important;
  border-bottom: 1px solid #ffedd5 !important;
  position: relative;
}

.stat-card.expanded {
  overflow: visible !important;
}

.stat-card:not(.expanded) {
  overflow: hidden !important;
}

.decorative-curve-image {
  position: absolute;
  top: 0;
  right: 0;
  width: 100%;
  height: 100%;
  background: radial-gradient(circle at 80% 20%, rgba(255, 0, 0, 0.05) 0%, transparent 50%),
              radial-gradient(circle at 90% 60%, rgba(255, 0, 0, 0.03) 0%, transparent 40%);
  pointer-events: none;
}

.vertical-dots {
  position: absolute;
  right: 12px;
  bottom: 45px;
  height: 40px;
  width: 4px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  z-index: 5;
}

.vertical-dots::before {
  content: "";
  height: 100%;
  width: 100%;
  background-image: radial-gradient(#ff0000 1.5px, transparent 1.5px);
  background-size: 4px 8px;
}

.design-mirror-footer {
  background: #f1f3f7 !important;
  border-radius: 18px !important;
  padding: 12px 20px !important;
  margin: 10px !important;
  display: flex;
  align-items: center;
}

.design-mirror-prev {
  font-size: 1.5rem !important;
  font-weight: 800 !important;
  color: #2d3748 !important;
}

.design-mirror-label {
  font-size: 0.85rem !important;
  font-weight: 600 !important;
  color: #718096 !important;
  text-transform: uppercase;
  margin-left: 8px;
}

.orange-text-shadow {
  text-shadow: 0 2px 8px rgba(249, 115, 22, 0.15);
}

.orange-theme-active::before {
  content: "";
  position: absolute;
  top: -10%;
  right: -5%;
  width: 250px;
  height: 150%;
  background: radial-gradient(ellipse at 100% 0%, rgba(249, 115, 22, 0.04) 0%, transparent 70%);
  z-index: 0;
  pointer-events: none;
}

.orange-theme-active::after {
  content: "";
  position: absolute;
  top: 0;
  right: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, transparent 80%, rgba(249, 115, 22, 0.02) 100%);
  pointer-events: none;
}

.larger-icon {
  width: 72px !important;
  height: 72px !important;
  border-radius: 16px !important;
  position: relative;
}

.glass-morphism-orange {
  background: rgba(249, 115, 22, 0.05) !important;
  backdrop-filter: blur(4px);
  border: 1px solid rgba(249, 115, 22, 0.2) !important;
  box-shadow: 
    0 8px 32px 0 rgba(249, 115, 22, 0.1),
    inset 0 0 0 1px rgba(255, 255, 255, 0.4) !important;
}

.custom-icon-container {
  position: relative;
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
}

.main-icon {
  width: 44px !important;
  height: 44px !important;
}

.icon-overlay-no {
  position: absolute;
  top: -4px;
  right: -4px;
  background: white;
  color: #ff0000;
  border-radius: 50%;
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  z-index: 5;
}

.icon-overlay-no :deep(svg) {
  width: 14px !important;
  height: 14px !important;
}

.stat-card-header {
  position: relative;
  z-index: 2;
}

.stat-card-footer {
  position: relative;
  z-index: 2;
}
.shadow-cyan {
  box-shadow:
    0 6px 20px -4px rgba(6, 182, 212, 0.4),
    0 4px 12px -2px rgba(6, 182, 212, 0.2) !important;
}
.shadow-cyan:hover {
  box-shadow: 0 12px 30px -5px rgba(6, 182, 212, 0.6) !important;
}

.shadow-purple {
  box-shadow:
    0 6px 20px -4px rgba(102, 16, 242, 0.4),
    0 4px 12px -2px rgba(102, 16, 242, 0.2) !important;
}
.shadow-purple:hover {
  box-shadow: 0 12px 30px -5px rgba(102, 16, 242, 0.6) !important;
}

/* Emergency Card Enhancements */
.emergency-card {
  background: linear-gradient(135deg, #ffffff 0%, #fff5f5 100%);
  transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.emergency-glow {
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle at center, rgba(220, 53, 69, 0.03) 0%, transparent 70%);
  pointer-events: none;
  z-index: 1;
}

.live-dot {
  width: 6px;
  height: 6px;
  background-color: #dc3545;
  border-radius: 50%;
  margin-right: 4px;
  box-shadow: 0 0 0 rgba(220, 53, 69, 0.4);
  animation: blink 1.5s infinite;
}

.emergency-icon-pulse {
  animation: heartbeat 2s infinite ease-in-out;
}

@keyframes blink {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
  }
}

@keyframes heartbeat {
  0% {
    transform: scale(1);
  }
  14% {
    transform: scale(1.1);
  }
  28% {
    transform: scale(1);
  }
  42% {
    transform: scale(1.1);
  }
  70% {
    transform: scale(1);
  }
}

@media (max-width: 991.98px) {
}
.metric-col {
  transition: all 0.3s ease;
}

.emergency-card:hover {
  box-shadow: 0 12px 24px rgba(220, 53, 69, 0.15) !important;
}

/* Metrics Grid & Compact Card Styles */
.metrics-grid {
  width: 100%;
  overflow: visible;
}

.stat-card {
  height: 100%;
  padding: 0.7rem 0.6rem !important;
  background: white;
  border-radius: 12px;
  display: flex;
  flex-direction: column;
  gap: 0.35rem !important;
  min-height: 105px !important;
}

.stat-card-header {
  display: flex;
  align-items: center;
  gap: 0.4rem !important;
}

.stat-icon-wrapper {
  flex-shrink: 0;
  width: 32px !important;
  height: 32px !important;
  border-radius: 6px !important;
  display: flex;
  align-items: center;
  justify-content: center;
}

.stat-icon {
  width: 16px !important;
  height: 16px !important;
}
</style>
