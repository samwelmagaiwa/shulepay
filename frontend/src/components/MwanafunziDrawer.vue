<template>
  <div class="drawer-overlay" @click.self="$emit('close')">
    <div class="drawer-panel">

      <!-- Header -->
      <div class="d-flex justify-content-between align-items-start pb-3 mb-0">
        <div>
          <h5 class="fw-bold mb-0" style="color:#007f3e;">{{ student.full_name }}</h5>
          <div class="small text-muted mt-1 d-flex gap-2 flex-wrap">
            <span v-if="student.admission_number">📋 {{ student.admission_number }}</span>
            <span v-if="student.school_class">· {{ student.school_class?.name }}</span>
          </div>
        </div>
        <CButton color="secondary" variant="ghost" size="sm" @click="$emit('close')" style="font-size:1.1rem; padding:4px 10px;">✕</CButton>
      </div>

      <!-- Financial summary cards -->
      <div class="row g-2 mb-0 py-3 border-top border-bottom">
        <div class="col-4">
          <div class="rounded p-2 text-center" style="background:#f8f9fa;">
            <div style="font-size:.62rem; color:#6c757d; font-weight:600; text-transform:uppercase; letter-spacing:.05em;">Jumla</div>
            <div style="font-weight:700; font-size:.85rem; letter-spacing:-.01em;">{{ formatMoney(totalInvoiced) }}</div>
          </div>
        </div>
        <div class="col-4">
          <div class="rounded p-2 text-center" style="background:rgba(25,135,84,.08);">
            <div style="font-size:.62rem; color:#198754; font-weight:600; text-transform:uppercase; letter-spacing:.05em;">Imelipwa</div>
            <div class="text-success" style="font-weight:700; font-size:.85rem; letter-spacing:-.01em;">{{ formatMoney(totalPaid) }}</div>
          </div>
        </div>
        <div class="col-4">
          <div class="rounded p-2 text-center"
               :style="totalOutstanding > 0 ? 'background:rgba(220,53,69,.08)' : 'background:rgba(25,135,84,.08)'">
            <div :style="`font-size:.62rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:${totalOutstanding>0?'#dc3545':'#198754'}`">Deni</div>
            <div :class="totalOutstanding > 0 ? 'text-danger' : 'text-success'" style="font-weight:700; font-size:.85rem; letter-spacing:-.01em;">
              {{ totalOutstanding > 0 ? formatMoney(totalOutstanding) : '✓ Sifuri' }}
            </div>
          </div>
        </div>
      </div>

      <!-- Jump links. Everything below renders at once — one click on the
           magnifier should show the whole picture, not four tabs to click
           through — so these only scroll to a section. -->
      <div class="d-flex gap-1 pt-3 mb-3 overflow-auto" style="flex-wrap:nowrap;">
        <button v-for="tab in tabs" :key="tab.key"
          class="btn btn-sm btn-outline-secondary px-2 text-nowrap"
          style="font-size:.75rem;"
          @click="scrollTo(tab.key)">
          {{ tab.label }}
          <span v-if="tab.badge" class="ms-1 badge rounded-pill"
                style="background:#007f3e; color:white">{{ tab.badge }}</span>
        </button>
      </div>

      <!-- Maelezo -->
      <div :ref="el => sections.maelezo = el">
        <div class="section-head">{{ t('students.details') }}</div>
        <InfoRow :label="t('students.admissionNo')" :value="info.admission_number || '—'" />
        <InfoRow :label="t('common.class')" :value="info.school_class?.name || '—'" />
        <InfoRow :label="t('students.gender')" :value="genderLabel" />
        <InfoRow :label="t('common.status')" :value="info.status || '—'" />
        <InfoRow v-if="info.date_of_birth" :label="t('students.dob')" :value="info.date_of_birth" />
        <InfoRow v-if="info.school?.name" label="Shule" :value="info.school.name" />
      </div>

      <!-- Ankara + the payments made against each -->
      <div :ref="el => sections.ankara = el" class="mt-4">
        <div class="section-head">Ankara &amp; Malipo</div>
        <div v-if="detailLoading" class="text-center py-4"><CSpinner size="sm" /></div>
        <div v-else-if="!invoices.length" class="text-center text-muted py-4 small">Hakuna ankara.</div>
        <div v-else>
          <div v-for="inv in invoices" :key="inv.id"
               class="mb-2 p-2 rounded border"
               :class="inv.status === 'paid' ? 'border-success' : inv.status === 'partial' ? 'border-warning' : 'border-danger'">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small">{{ inv.invoice_number }}</div>
                <div class="text-muted" style="font-size:.72rem;">{{ inv.term?.name }}</div>
              </div>
              <span class="badge"
                    :class="inv.status === 'paid' ? 'bg-success' : inv.status === 'partial' ? 'bg-warning text-dark' : 'bg-danger'">
                {{ inv.status === 'paid' ? 'Imelipwa' : inv.status === 'partial' ? 'Sehemu' : 'Haijalipiwa' }}
              </span>
            </div>
            <div class="d-flex justify-content-between mt-1 small">
              <span>Jumla: <strong>{{ formatMoney(inv.total_amount_cents) }}</strong></span>
              <span :class="inv.balance_due_cents > 0 ? 'text-danger' : 'text-success'">
                Deni: <strong>{{ inv.balance_due_cents > 0 ? formatMoney(inv.balance_due_cents) : '✓ 0' }}</strong>
              </span>
            </div>
            <!-- Payments recorded against this invoice -->
            <div v-if="paymentsByInvoice[inv.id]?.length" class="mt-2 pt-2" style="border-top:1px dashed #dee2e6;">
              <div v-for="p in paymentsByInvoice[inv.id]" :key="p.id"
                   class="d-flex justify-content-between align-items-center py-1" style="font-size:.72rem;">
                <span class="text-muted">
                  {{ formatDate(p.paid_at) }} · {{ methodLabel(p.method) }}
                  <span v-if="p.receipt?.receipt_number" class="ms-1">· {{ p.receipt.receipt_number }}</span>
                </span>
                <span class="text-success fw-semibold">+{{ formatMoney(p.amount_cents) }}</span>
              </div>
            </div>
            <div v-else class="mt-2 pt-2 text-muted" style="border-top:1px dashed #dee2e6; font-size:.72rem;">
              Hakuna malipo bado.
            </div>

            <!-- Installment mini-indicator if this invoice has a plan -->
            <div v-if="installmentsByInvoice[inv.id]" class="mt-2">
              <div class="d-flex justify-content-between align-items-center mb-1" style="font-size:.68rem; color:#6c757d;">
                <span>Awamu: {{ installmentsByInvoice[inv.id].paid_count }}/{{ installmentsByInvoice[inv.id].total }}</span>
                <span style="color:#007f3e; cursor:pointer; text-decoration:underline;"
                      @click="scrollTo('awamu')">Angalia Awamu →</span>
              </div>
              <div class="progress" style="height:4px;">
                <div class="progress-bar bg-success" role="progressbar"
                     :style="`width:${installmentsByInvoice[inv.id].pct}%`"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Malipo yasiyo na ankara -->
      <div v-if="unlinkedPayments.length" :ref="el => sections.malipo = el" class="mt-4">
        <div class="section-head">Malipo Mengine</div>
        <div v-if="detailLoading" class="text-center py-4"><CSpinner size="sm" /></div>
        <div>
          <div v-for="p in unlinkedPayments" :key="p.id"
               class="mb-2 p-2 rounded border d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold small text-success">+{{ formatMoney(p.amount_cents) }}</div>
              <div class="text-muted" style="font-size:.72rem;">
                {{ formatDate(p.paid_at) }} · {{ methodLabel(p.method) }}
              </div>
              <div v-if="p.reference_number" class="text-muted" style="font-size:.70rem;">Kumb: {{ p.reference_number }}</div>
            </div>
            <div class="text-end">
              <div class="text-muted" style="font-size:.70rem;">{{ p.receipt?.receipt_number || p.invoice?.invoice_number }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Awamu -->
      <div v-if="installments.length" :ref="el => sections.awamu = el" class="mt-4">
        <div class="section-head">Awamu</div>
        <div v-if="detailLoading" class="text-center py-4"><CSpinner size="sm" /></div>
        <div v-else-if="!installments.length" class="text-center text-muted py-4 small">
          <div style="font-size:1.5rem; margin-bottom:4px;">📋</div>
          Hakuna mipango ya awamu.
        </div>
        <div v-else>
          <!-- Plan groups -->
          <div v-for="group in installmentGroups" :key="group.invoice_id" class="mb-3">
            <!-- Group header -->
            <div class="d-flex justify-content-between align-items-start p-2 rounded-top"
                 style="background:#f8fffe; border:1px solid #e0f2ec; border-bottom:none;">
              <div>
                <div class="fw-semibold small" style="color:#007f3e;">{{ group.invoice_number }}</div>
                <div class="text-muted" style="font-size:.7rem;">
                  Awamu {{ group.paid_count }}/{{ group.total }} •
                  {{ formatMoney(group.amount_each) }} kila moja
                </div>
              </div>
              <span class="badge" :class="group.all_paid ? 'bg-success' : 'bg-warning text-dark'">
                {{ group.all_paid ? '✓ Imekamilika' : 'Inaendelea' }}
              </span>
            </div>
            <!-- Progress bar -->
            <div style="border:1px solid #e0f2ec; border-top:none; border-bottom:none; padding:6px 8px;">
              <div class="d-flex justify-content-between mb-1" style="font-size:.68rem; color:#6c757d;">
                <span>Maendeleo</span>
                <span>{{ Math.round(group.paid_count / group.total * 100) }}%</span>
              </div>
              <div class="progress" style="height:6px;">
                <div class="progress-bar"
                     :class="group.all_paid ? 'bg-success' : 'bg-warning'"
                     :style="`width:${Math.round(group.paid_count / group.total * 100)}%`"></div>
              </div>
            </div>
            <!-- Installment rows -->
            <div style="border:1px solid #e0f2ec; border-top:none; border-radius:0 0 6px 6px; overflow:hidden;">
              <div v-for="item in group.items" :key="item.id"
                   class="d-flex justify-content-between align-items-center px-2 py-2"
                   :style="installmentRowStyle(item)"
                   style="border-top:1px solid rgba(0,0,0,.05);">
                <div style="flex:1;">
                  <div class="d-flex align-items-center gap-2">
                    <!-- Status icon -->
                    <span v-if="item.status === 'paid'" style="font-size:.8rem;">✅</span>
                    <span v-else-if="item.status === 'partial'" style="font-size:.8rem;">🔶</span>
                    <span v-else style="font-size:.8rem;">⏳</span>
                    <div>
                      <div class="fw-semibold" style="font-size:.75rem;">
                        Awamu #{{ item.installment_number }}
                      </div>
                      <div class="text-muted" style="font-size:.67rem;">Tarehe: {{ item.due_date }}</div>
                    </div>
                  </div>
                </div>
                <div class="text-end" style="min-width:110px;">
                  <!-- Amount paid vs expected -->
                  <div v-if="item.status === 'paid'" class="text-success fw-semibold" style="font-size:.75rem;">
                    ✓ {{ formatMoney(item.paid_amount_cents) }}
                  </div>
                  <div v-else-if="item.status === 'partial'">
                    <div class="text-warning fw-semibold" style="font-size:.73rem;">
                      {{ formatMoney(item.paid_amount_cents) }} / {{ formatMoney(item.installment_amount_cents) }}
                    </div>
                    <div class="text-danger" style="font-size:.67rem;">
                      Inakosekana: {{ formatMoney(item.installment_amount_cents - item.paid_amount_cents) }}
                    </div>
                  </div>
                  <div v-else>
                    <div class="fw-semibold" style="font-size:.75rem;">{{ formatMoney(item.installment_amount_cents) }}</div>
                    <div :class="isOverdue(item.due_date) ? 'text-danger' : 'text-muted'" style="font-size:.67rem;">
                      {{ isOverdue(item.due_date) ? '⚠ Imechelewa' : 'Inasubiri' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer: Full Details link -->
      <div class="mt-4 pt-3 border-top">
        <RouterLink :to="`/wanafunzi/${student.id}`" class="btn btn-sm w-100 fw-semibold py-2"
                    style="background:#007f3e; color:white; border-color:#007f3e;">
          {{ t('students.fullDetails') }} &rarr;
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import InfoRow from '@/components/InfoRow.vue'

const { t } = useI18n()
const props = defineProps({ student: Object })
defineEmits(['close'])

const activeTab = ref('maelezo')
const fullStudent = ref(null)
const sections = ref({})

function scrollTo(key) {
  sections.value[key]?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// Merge the richer record over the list summary, so fields missing from the
// invoice payload fill in once the full student arrives.
const info = computed(() => ({ ...(props.student || {}), ...(fullStudent.value || {}) }))

// Each invoice's own payments, so a debt and what has been paid against it read
// together rather than in two separate tabs.
const paymentsByInvoice = computed(() => {
  const map = {}
  for (const p of payments.value) {
    const key = p.invoice_id ?? p.invoice?.id
    if (!key) continue
    ;(map[key] ||= []).push(p)
  }
  return map
})

// Payments that could not be tied back to an invoice in this list.
const unlinkedPayments = computed(() =>
  payments.value.filter(p => !(p.invoice_id ?? p.invoice?.id)),
)

const detailLoading = ref(false)
const invoices     = ref([])
const payments     = ref([])
const installments = ref([])

const genderLabel = computed(() => {
  const g = info.value?.gender
  return g === 'male' || g === 'me' ? t('students.male')
       : g === 'female' || g === 'ke' ? t('students.female') : '—'
})

const totalInvoiced    = computed(() => invoices.value.reduce((s, i) => s + (i.total_amount_cents || 0), 0))
const totalPaid        = computed(() => invoices.value.reduce((s, i) => s + (i.paid_cents || 0), 0))
const totalOutstanding = computed(() => invoices.value.reduce((s, i) => s + (i.balance_due_cents || 0), 0))

// Installments grouped by invoice
const installmentGroups = computed(() => {
  const map = {}
  installments.value.forEach(item => {
    const key = item.invoice_id
    if (!map[key]) {
      map[key] = {
        invoice_id: key,
        invoice_number: item.invoice_number || '—',
        total: 0,
        paid_count: 0,
        amount_each: item.installment_amount_cents || 0,
        all_paid: false,
        items: [],
      }
    }
    map[key].items.push(item)
    map[key].total++
    if (item.status === 'paid') map[key].paid_count++
  })
  return Object.values(map).map(g => {
    g.items.sort((a, b) => a.installment_number - b.installment_number)
    g.all_paid = g.paid_count >= g.total && g.total > 0
    return g
  })
})

// Index installments by invoice_id for the Ankara tab mini-indicator
const installmentsByInvoice = computed(() => {
  const map = {}
  installmentGroups.value.forEach(g => {
    map[g.invoice_id] = {
      paid_count: g.paid_count,
      total: g.total,
      pct: Math.round((g.paid_count / g.total) * 100),
    }
  })
  return map
})

const tabs = computed(() => [
  { key: 'maelezo', label: 'Maelezo' },
  { key: 'ankara',  label: 'Ankara', badge: invoices.value.length || null },
  { key: 'malipo',  label: 'Malipo',  badge: payments.value.length || null },
  { key: 'awamu',   label: 'Awamu', badge: installmentGroups.value.length || null },
])

function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('sw-TZ')
}

function methodLabel(method) {
  return { cash: 'Cash', mpesa: 'M-Pesa', bank: 'Benki', cheque: 'Hundi' }[method] || method || '—'
}

function isOverdue(dueDateStr) {
  if (!dueDateStr) return false
  return new Date(dueDateStr) < new Date()
}

function installmentRowStyle(item) {
  if (item.status === 'paid')    return 'background:rgba(25,135,84,.05);'
  if (item.status === 'partial') return 'background:rgba(255,193,7,.08);'
  if (isOverdue(item.due_date))  return 'background:rgba(220,53,69,.05);'
  return ''
}

async function loadDetails() {
  if (!props.student?.id) return
  detailLoading.value = true
  try {
    // The student object handed in by the invoice list is InvoiceResource's nested
    // summary — it carries name, admission number and class but not gender, status
    // or date of birth, which is why those rows used to render as em-dashes. Fetch
    // the full record alongside the financials.
    const [stuRes, invRes, payRes, instRes] = await Promise.all([
      api.get(`/students/${props.student.id}`).catch(() => null),
      api.get('/invoices',      { params: { student_id: props.student.id, per_page: 100 } }),
      api.get('/payments',      { params: { student_id: props.student.id, per_page: 100 } }),
      api.get('/installments',  { params: { student_id: props.student.id, per_page: 100 } }),
    ])
    fullStudent.value  = stuRes ? (stuRes.data?.data ?? stuRes.data) : null
    invoices.value     = invRes.data?.data || []
    payments.value     = payRes.data?.data || []
    installments.value = instRes.data?.data || []
  } catch {
    invoices.value = []; payments.value = []; installments.value = []
  } finally {
    detailLoading.value = false
  }
}

watch(() => props.student?.id, (id) => {
  if (id) {
    fullStudent.value = null
    loadDetails()
  }
}, { immediate: true })
</script>

<style scoped>
.section-head {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #007f3e;
  padding-bottom: 4px;
  margin-bottom: 8px;
  border-bottom: 2px solid rgba(0, 127, 62, .18);
}

.drawer-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1050;
  display: flex; justify-content: flex-end;
  backdrop-filter: blur(3px);
}
.drawer-panel {
  background: #ffffff !important;
  color: #1a1a2e !important;
  width: 440px; max-width: 100%; height: 100%;
  padding: 1.25rem; overflow-y: auto;
  box-shadow: -6px 0 32px rgba(0,0,0,0.22);
}
/* Force all text inside drawer to be dark regardless of device theme */
.drawer-panel :deep(*) {
  color: inherit;
}
</style>
