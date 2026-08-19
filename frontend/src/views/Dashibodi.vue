<template>
  <CContainer fluid class="px-3 py-3">
    <CRow class="mb-3 align-items-center">
      <CCol>
        <h4 class="fw-bold mb-0">{{ t('dashboard.title') }}</h4>
        <p class="text-muted mb-0 small">{{ t('dashboard.subtitle') }}</p>
      </CCol>
    </CRow>

    <div v-if="store.loading" class="text-center py-5">
      <CSpinner color="primary" />
    </div>

    <CAlert v-else-if="store.error" color="danger">{{ store.error }}</CAlert>

    <template v-else>
      <CRow class="g-3 mb-3">
        <CCol xs="12" sm="6" md="3">
          <CCard class="border-top border-success border-3 h-100">
            <CCardBody class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:48px;height:48px;flex-shrink:0;">
                <CIcon icon="cilPeople" class="text-success" size="xl" />
              </div>
              <div>
                <div class="fs-4 fw-bold text-success">{{ store.stats?.total_students ?? '—' }}</div>
                <div class="text-muted small">{{ t('dashboard.students') }}</div>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" sm="6" md="3">
          <CCard class="border-top border-primary border-3 h-100">
            <CCardBody class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary bg-opacity-10" style="width:48px;height:48px;flex-shrink:0;">
                <CIcon icon="cilDollar" class="text-primary" size="xl" />
              </div>
              <div>
                <div class="fs-5 fw-bold text-primary">{{ fmtCents(store.stats?.total_collected_cents) }}</div>
                <div class="text-muted small">{{ t('dashboard.collections') }}</div>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" sm="6" md="3">
          <CCard class="border-top border-danger border-3 h-100">
            <CCardBody class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:48px;height:48px;flex-shrink:0;">
                <CIcon icon="cilWarning" class="text-danger" size="xl" />
              </div>
              <div>
                <div class="fs-5 fw-bold text-danger">{{ fmtCents(store.stats?.total_outstanding_cents) }}</div>
                <div class="text-muted small">{{ t('dashboard.debt') }}</div>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" sm="6" md="3">
          <CCard class="border-top border-warning border-3 h-100">
            <CCardBody class="d-flex align-items-center gap-3">
              <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10" style="width:48px;height:48px;flex-shrink:0;">
                <CIcon icon="cilCash" class="text-warning" size="xl" />
              </div>
              <div>
                <div class="fs-5 fw-bold text-warning">{{ fmtCents(store.stats?.total_expenses_cents) }}</div>
                <div class="text-muted small">{{ t('dashboard.monthlyExpenses') }}</div>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <CRow class="g-3">
        <CCol xs="12" lg="8">
          <CCard>
            <CCardHeader><strong>{{ t('dashboard.recentPayments') }}</strong></CCardHeader>
            <CCardBody class="p-0">
              <CTable hover responsive class="mb-0">
                <CTableHead>
                  <CTableRow>
                    <CTableHeaderCell>{{ t('common.student') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('common.amount') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('common.method') }}</CTableHeaderCell>
                    <CTableHeaderCell>{{ t('common.date') }}</CTableHeaderCell>
                  </CTableRow>
                </CTableHead>
                <CTableBody>
                  <CTableRow v-if="!recentPayments.length">
                    <CTableDataCell colspan="4" class="text-center text-muted py-4">{{ t('dashboard.noPayments') }}</CTableDataCell>
                  </CTableRow>
                  <CTableRow v-for="p in recentPayments" :key="p.id">
                    <CTableDataCell>{{ p.student_name || p.student || '—' }}</CTableDataCell>
                    <CTableDataCell>{{ fmtCents(p.amount_cents ?? p.amount) }}</CTableDataCell>
                    <CTableDataCell>{{ methodLabel(p.method) }}</CTableDataCell>
                    <CTableDataCell>{{ p.paid_at ? new Date(p.paid_at).toLocaleDateString('sw-TZ') : '—' }}</CTableDataCell>
                  </CTableRow>
                </CTableBody>
              </CTable>
            </CCardBody>
          </CCard>
        </CCol>

        <CCol xs="12" lg="4">
          <CCard class="h-100">
            <CCardHeader><strong>{{ t('dashboard.collectionRate') }}</strong></CCardHeader>
            <CCardBody class="d-flex flex-column justify-content-center gap-2">
              <div class="d-flex justify-content-between small text-muted mb-1">
                <span>{{ t('dashboard.collectionRate') }}</span>
                <span class="fw-bold text-dark">{{ collectionRate }}%</span>
              </div>
              <CProgress :value="collectionRate" color="primary" height="18" class="rounded-pill">
                <CProgressBar>{{ collectionRate }}%</CProgressBar>
              </CProgress>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </template>
  </CContainer>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDashboardStore } from '@/stores/dashboard'

const { t } = useI18n()
const store = useDashboardStore()

function methodLabel(m) {
  const map = { cash: 'common.cash', mpesa: 'common.mpesa', bank: 'common.bank', cheque: 'common.cheque' }
  return map[m] ? t(map[m]) : (m || '—')
}

function fmtCents(cents) {
  if (cents == null) return '—'
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

const recentPayments = computed(() => (store.stats?.recent_payments || []).slice(0, 5))
const collectionRate = computed(() => {
  const r = store.stats?.collection_rate
  if (r == null) return 0
  return Math.min(100, Math.max(0, Math.round(r)))
})

onMounted(async () => {
  try {
    await store.fetchStats()
  } catch {}
})
</script>
