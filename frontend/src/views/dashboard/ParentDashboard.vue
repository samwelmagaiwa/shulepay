<template>
  <CContainer fluid class="p-2 p-md-3">

    <!-- Welcome banner -->
    <div class="mb-4 p-3 rounded-3"
         style="background:linear-gradient(135deg,#7c3aed 0%,#a855f7 100%); color:#fff;">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center fw-bold"
             style="width:52px;height:52px;font-size:1.3rem;color:#7c3aed;flex-shrink:0;">
          {{ initials }}
        </div>
        <div>
          <div class="fw-bold" style="font-size:1.2rem;">{{ t('dashboard.welcome') }}, {{ auth.user?.name }}</div>
          <div style="opacity:.85; font-size:.9rem;">{{ todayDate }} &bull; {{ t('dashboard.parent') }}</div>
        </div>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else-if="!children.length" class="text-center py-5 text-muted">
      <div style="font-size:3rem;">👶</div>
      <div>{{ t('dashboard.noChildren') }}</div>
    </div>

    <div v-else>
      <!-- Summary cards across all children -->
      <CRow class="g-3 mb-4">
        <CCol xs="6" md="3">
          <CCard class="h-100 text-center" style="border-left:4px solid #7c3aed;">
            <CCardBody class="p-3">
              <div style="font-size:2rem;">👶</div>
              <div class="fw-bold fs-4">{{ children.length }}</div>
              <div class="text-muted small">{{ t('dashboard.myChildren') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="h-100 text-center" style="border-left:4px solid #dc3545;">
            <CCardBody class="p-3">
              <div style="font-size:2rem;">💰</div>
              <div class="fw-bold fs-4" style="font-size:1.1rem !important;">{{ fmtTzs(totalBalance) }}</div>
              <div class="text-muted small">{{ t('dashboard.totalBalance') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="h-100 text-center" style="border-left:4px solid #198754;">
            <CCardBody class="p-3">
              <div style="font-size:2rem;">✅</div>
              <div class="fw-bold fs-4">{{ clearedCount }}</div>
              <div class="text-muted small">{{ t('dashboard.feesCleared') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="6" md="3">
          <CCard class="h-100 text-center" style="border-left:4px solid #d97706;">
            <CCardBody class="p-3">
              <div style="font-size:2rem;">⏳</div>
              <div class="fw-bold fs-4">{{ pendingCount }}</div>
              <div class="text-muted small">{{ t('dashboard.feesPending') }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <!-- Per-child cards -->
      <CRow class="g-3">
        <CCol v-for="child in children" :key="child.id" xs="12" md="6" lg="4">
          <CCard class="h-100">
            <CCardHeader class="d-flex align-items-center gap-2 fw-semibold">
              <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                   style="width:36px;height:36px;font-size:.85rem;background:#7c3aed;flex-shrink:0;">
                {{ childInitials(child) }}
              </div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="text-truncate">{{ child.full_name }}</div>
                <div class="text-muted" style="font-size:.75rem;">
                  {{ child.school_class?.name }} &bull; {{ child.admission_number }}
                </div>
              </div>
            </CCardHeader>
            <CCardBody class="p-3">
              <!-- Invoice info -->
              <div v-if="child._invoice">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">{{ t('common.total') }}</span>
                  <span class="small">{{ fmtTzs(child._invoice.total_tzs) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">{{ t('invoices.amountPaid') }}</span>
                  <span class="small text-success">{{ fmtTzs(child._invoice.paid_tzs) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted small fw-semibold">{{ t('payments.balanceDue') }}</span>
                  <span class="fw-bold" :style="{ color: child._invoice.balance_tzs === 0 ? '#198754' : '#dc3545' }">
                    {{ fmtTzs(child._invoice.balance_tzs) }}
                  </span>
                </div>
                <CBadge v-if="child._invoice.balance_tzs === 0" color="success" class="w-100 py-1">
                  {{ t('dashboard.allClear') }} ✅
                </CBadge>
                <CBadge v-else color="danger" class="w-100 py-1">
                  {{ t('dashboard.balanceOwing') }}
                </CBadge>
              </div>
              <div v-else class="text-muted small text-center py-2">{{ t('dashboard.noInvoice') }}</div>
            </CCardBody>
            <CCardFooter class="p-2">
              <CButton size="sm" color="outline-primary" class="w-100" @click="downloadStatement(child)">
                <CSpinner v-if="child._downloading" size="sm" class="me-1" />
                📄 {{ t('dashboard.downloadStatement') }}
              </CButton>
            </CCardFooter>
          </CCard>
        </CCol>
      </CRow>
    </div>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const { t } = useI18n()
const auth = useAuthStore()

const children = ref([])
const loading = ref(true)

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
})

const todayDate = computed(() => new Date().toLocaleDateString('en-TZ', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))

const totalBalance   = computed(() => children.value.reduce((a, c) => a + (c._invoice?.balance_tzs || 0), 0))
const clearedCount   = computed(() => children.value.filter(c => c._invoice && c._invoice.balance_tzs === 0).length)
const pendingCount   = computed(() => children.value.filter(c => c._invoice && c._invoice.balance_tzs > 0).length)

function childInitials(child) {
  return (child.full_name || '').split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

function fmtTzs(tzs) {
  if (!tzs && tzs !== 0) return '—'
  return 'TZS ' + Math.round(tzs).toLocaleString('en-TZ', { maximumFractionDigits: 0 })
}

async function downloadStatement(child) {
  child._downloading = true
  try {
    const resp = await api.get(`/parent/statement/pdf`, {
      params: { student_id: child.id },
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `statement-${child.admission_number}.pdf`
    a.click()
    window.URL.revokeObjectURL(url)
  } catch { /* silent */ } finally {
    child._downloading = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/parent/children')
    const list = Array.isArray(data) ? data : (data.data || [])
    // fetch statement for each child to get balance info
    const enriched = await Promise.all(list.map(async child => {
      try {
        const { data: stmt } = await api.get('/parent/statement', { params: { student_id: child.id } })
        const invoices = Array.isArray(stmt) ? stmt : (stmt.data || stmt.invoices || [])
        const latestInv = invoices[0] || null
        child._invoice = latestInv ? {
          total_tzs:   Math.round((latestInv.total_amount_cents || 0) / 100),
          paid_tzs:    Math.round((latestInv.paid_cents || 0) / 100),
          balance_tzs: Math.round((latestInv.balance_due_cents || 0) / 100),
        } : null
      } catch {
        child._invoice = null
      }
      child._downloading = false
      return child
    }))
    children.value = enriched
  } catch { /* no data */ } finally {
    loading.value = false
  }
})
</script>
