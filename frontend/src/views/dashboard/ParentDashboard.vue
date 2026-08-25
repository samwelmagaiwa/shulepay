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
              <div class="fw-bold fs-4" style="font-size:1.1rem !important;">{{ fmtCents(totalBalance) }}</div>
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
                <div class="text-truncate">{{ child.name }}</div>
                <div class="text-muted" style="font-size:.75rem;">
                  {{ child.school_class }} &bull; {{ child.admission_number }}
                </div>
              </div>
            </CCardHeader>
            <CCardBody class="p-3">
              <div v-if="child.invoice_count">
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">{{ t('common.total') }}</span>
                  <span class="small">{{ fmtCents(child.billed_cents) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted small">{{ t('invoices.amountPaid') }}</span>
                  <span class="small text-success">{{ fmtCents(child.paid_cents) }}</span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                  <span class="text-muted small fw-semibold">{{ t('payments.balanceDue') }}</span>
                  <span class="fw-bold" :style="{ color: child.balance_cents === 0 ? '#198754' : '#dc3545' }">
                    {{ fmtCents(child.balance_cents) }}
                  </span>
                </div>

                <!-- Per-term breakdown: which term is actually outstanding -->
                <div v-if="child.terms.length" class="mb-3">
                  <div class="text-muted text-uppercase mb-1" style="font-size:.65rem; letter-spacing:.05em;">
                    {{ t('dashboard.perTerm') }}
                  </div>
                  <div v-for="tr in child.terms" :key="tr.invoice_id"
                       class="d-flex justify-content-between align-items-center py-1"
                       style="border-bottom:1px dotted var(--cui-border-color, #e0e0e0);">
                    <span class="small text-truncate" style="max-width:52%;">{{ tr.term || '—' }}</span>
                    <span class="small fw-semibold"
                          :class="tr.balance_cents === 0 ? 'text-success' : 'text-danger'">
                      {{ tr.balance_cents === 0 ? '✓' : fmtCents(tr.balance_cents) }}
                    </span>
                  </div>
                </div>

                <CBadge v-if="child.balance_cents === 0" color="success" class="w-100 py-1">
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

      <!-- Recent payments across this parent's children only -->
      <CCard v-if="recentPayments.length" class="mt-4">
        <CCardHeader class="fw-semibold">🧾 {{ t('dashboard.recentPayments') }}</CCardHeader>
        <CCardBody class="p-0">
          <div class="table-responsive">
            <CTable hover class="align-middle mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>{{ t('students.paidDate') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.student') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
                  <CTableHeaderCell>{{ t('payments.method') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-end">{{ t('students.paidAmount') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('payments.receipt') }}</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="p in recentPayments" :key="p.id">
                  <CTableDataCell class="small">{{ p.paid_at || '—' }}</CTableDataCell>
                  <CTableDataCell class="small">{{ p.student_name }}</CTableDataCell>
                  <CTableDataCell class="small text-muted">{{ p.term || '—' }}</CTableDataCell>
                  <CTableDataCell class="small">{{ p.method_label }}</CTableDataCell>
                  <CTableDataCell class="text-end fw-semibold text-success">
                    {{ fmtCents(p.amount_cents) }}
                  </CTableDataCell>
                  <CTableDataCell class="text-center">
                    <CButton v-if="p.receipt_id" size="sm" color="success" variant="outline"
                             :disabled="printingId === p.receipt_id"
                             @click="printReceipt(p.receipt_id)"
                             :title="p.receipt_number">
                      <CSpinner v-if="printingId === p.receipt_id" size="sm" />
                      <span v-else>🖨</span>
                    </CButton>
                    <span v-else class="text-muted small">—</span>
                  </CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </div>
        </CCardBody>
      </CCard>

      <CAlert v-if="actionError" color="danger" dismissible class="mt-3 py-2"
              @close="actionError = ''">
        {{ actionError }}
      </CAlert>
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

const children       = ref([])
const recentPayments = ref([])
const summary        = ref(null)
const loading        = ref(true)
const actionError    = ref('')
const printingId     = ref(null)

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
})

const todayDate = computed(() =>
  new Date().toLocaleDateString('en-TZ', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

// Totals come from the server, which computes them from the same invoices it
// scoped to this parent — no client-side re-derivation to drift out of sync.
const totalBalance = computed(() => summary.value?.total_balance_cents ?? 0)
const clearedCount = computed(() => summary.value?.cleared_count ?? 0)
const pendingCount = computed(() => summary.value?.pending_count ?? 0)

function childInitials(child) {
  return (child.name || '').split(' ').filter(Boolean).map(p => p[0]).join('').toUpperCase().slice(0, 2)
}

function fmtCents(cents) {
  if (cents === null || cents === undefined) return '—'
  return 'TZS ' + Math.round(cents / 100).toLocaleString('en-TZ', { maximumFractionDigits: 0 })
}

async function downloadStatement(child) {
  child._downloading = true
  actionError.value = ''
  try {
    const resp = await api.get('/parent/statement/pdf', {
      params: { student_id: child.id },
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `statement-${child.admission_number || child.id}.pdf`
    document.body.appendChild(a)
    a.click()
    a.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    actionError.value = e?.response?.data?.message || t('common.loadFailed')
  } finally {
    child._downloading = false
  }
}

let printFrame = null

// Parents use their own scoped receipt route; the staff one is role-gated.
// Fetched via axios so the auth token is attached (a bare iframe src is not).
async function printReceipt(receiptId) {
  if (!receiptId) return
  printingId.value = receiptId
  actionError.value = ''
  try {
    const { data } = await api.get(`/parent/receipts/${receiptId}`, { responseType: 'blob' })
    if (data.type && !data.type.includes('pdf')) throw new Error('Not a PDF')

    if (printFrame) printFrame.remove()
    const url = URL.createObjectURL(data)
    const frame = document.createElement('iframe')
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
    frame.src = url
    frame.onload = () => {
      try { frame.contentWindow.focus(); frame.contentWindow.print() }
      catch { window.open(url, '_blank', 'noopener') }
    }
    document.body.appendChild(frame)
    printFrame = frame
  } catch (e) {
    actionError.value = e?.response?.data?.message || t('payments.receiptPrintFailed')
  } finally {
    printingId.value = null
  }
}

onMounted(async () => {
  try {
    // One request for the whole page. This previously issued a /parent/children
    // call plus a /parent/statement call per child.
    const { data } = await api.get('/parent/dashboard')
    summary.value        = data.summary || null
    children.value       = (data.children || []).map(c => ({ ...c, _downloading: false }))
    recentPayments.value = data.recent_payments || []
  } catch (e) {
    actionError.value = e?.response?.data?.message || t('common.loadFailed')
  } finally {
    loading.value = false
  }
})
</script>
