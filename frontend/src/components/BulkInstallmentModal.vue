<template>
  <CModal :visible="visible" @close="$emit('close')" size="xl" backdrop="static" class="modal-fullscreen-sm-down">
    <CModalHeader style="border-bottom:2px solid #007f3e;">
      <CModalTitle class="fw-bold">📋 {{ t('installments.bulkTitle') }}</CModalTitle>
    </CModalHeader>

    <CModalBody class="p-0">
      <!-- Config row -->
      <div class="px-3 px-md-4 pt-3 pb-3 border-bottom" style="background:#f8fffe;">
        <CRow class="g-3 align-items-end">

          <!-- School -->
          <CCol xs="12" sm="6" md="2">
            <label class="form-label fw-semibold small mb-1">
              Shule <span class="text-danger">*</span>
            </label>
            <CFormSelect v-model="form.school_id" :disabled="saving || loadingSchools" size="sm"
                         @update:modelValue="onSchoolChange">
              <option value="">— Chagua Shule —</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
            <div v-if="loadingSchools" class="text-muted" style="font-size:.7rem; margin-top:2px;">
              <CSpinner size="sm" /> Inapakia shule...
            </div>
          </CCol>

          <!-- Class — filtered by school -->
          <CCol xs="12" sm="6" md="2">
            <label class="form-label fw-semibold small mb-1">
              {{ t('common.class') }} <span class="text-danger">*</span>
            </label>
            <CFormSelect v-model="form.school_class_id" :disabled="saving || !form.school_id || loadingClasses"
                         size="sm" @update:modelValue="onClassOrTermChange">
              <option value="">{{ loadingClasses ? 'Inapakia...' : t('installments.selectClass') }}</option>
              <option v-for="c in filteredClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
            <div v-if="form.school_id && !loadingClasses && filteredClasses.length === 0"
                 class="text-warning" style="font-size:.7rem; margin-top:2px;">
              Hakuna madarasa kwa shule hii
            </div>
          </CCol>

          <!-- Term — filtered by school's academic years -->
          <CCol xs="12" sm="6" md="2">
            <label class="form-label fw-semibold small mb-1">
              {{ t('invoices.term') }} <span class="text-danger">*</span>
              <span v-if="activeYearName" class="text-muted fw-normal ms-1">({{ activeYearName }})</span>
            </label>
            <CFormSelect v-model="form.term_id" :disabled="saving || !form.school_id || loadingTerms"
                         size="sm" @update:modelValue="onClassOrTermChange">
              <option value="">{{ loadingTerms ? 'Inapakia...' : t('installments.selectTerm') }}</option>
              <option v-for="term in filteredTerms" :key="term.id" :value="term.id">{{ term.name }}</option>
            </CFormSelect>
            <div v-if="form.school_id && !loadingTerms && filteredTerms.length === 0"
                 class="text-warning" style="font-size:.7rem; margin-top:2px;">
              Hakuna mihula kwa shule hii
            </div>
          </CCol>

          <!-- Total installments -->
          <CCol xs="6" sm="4" md="2">
            <label class="form-label fw-semibold small mb-1">
              {{ t('installments.totalInstallments') }} <span class="text-danger">*</span>
            </label>
            <CFormInput type="number" v-model.number="form.total_installments"
                        min="2" max="12" :disabled="saving" size="sm"
                        @change="recalcPreview" />
          </CCol>

          <!-- Days between -->
          <CCol xs="6" sm="4" md="2">
            <label class="form-label fw-semibold small mb-1">
              {{ t('installments.intervalDays') }} <span class="text-danger">*</span>
            </label>
            <CFormInput type="number" v-model.number="form.interval_days"
                        min="7" max="90" :disabled="saving" size="sm"
                        @change="recalcPreview" />
          </CCol>

          <!-- Start date -->
          <CCol xs="12" sm="4" md="2">
            <label class="form-label fw-semibold small mb-1">
              {{ t('installments.startDate') }} <span class="text-danger">*</span>
            </label>
            <CFormInput type="date" v-model="form.start_date" :disabled="saving" size="sm"
                        @change="recalcPreview" />
          </CCol>
        </CRow>

        <!-- Inline config hint -->
        <div v-if="!form.school_id" class="mt-2 small text-muted">
          ⬆ Chagua shule kwanza ili kupata madarasa na mihula inayohusiana.
        </div>
        <div v-else-if="form.school_id && (!form.school_class_id || !form.term_id)"
             class="mt-2 small text-muted">
          ⬆ Kisha chagua darasa na muhula ili kuona wanafunzi wanaostahili.
        </div>
      </div>

      <!-- Preview area -->
      <div class="p-3 p-md-4">

        <!-- Loading preview -->
        <div v-if="previewLoading" class="text-center py-4">
          <CSpinner color="primary" size="sm" class="me-2" />
          <span class="text-muted small">Inapakia wanafunzi wanaostahili...</span>
        </div>

        <!-- Prompt: no school selected -->
        <div v-else-if="!form.school_id" class="text-center py-5 text-muted">
          <div style="font-size:2.5rem;">🏫</div>
          <div class="mt-2">Chagua shule ili kuendelea</div>
        </div>

        <!-- Prompt: school selected but not class+term -->
        <div v-else-if="!form.school_class_id || !form.term_id"
             class="text-center py-5 text-muted">
          <div style="font-size:2.5rem;">📚</div>
          <div class="mt-2">Chagua darasa na muhula ili kuona wanafunzi wanaostahili</div>
        </div>

        <!-- No student records at all for this class+term -->
        <div v-else-if="preview.length === 0 && readOnlyRows.length === 0 && !previewLoading"
             class="rounded p-4 text-center"
             style="background:rgba(108,117,125,.06); border:1px dashed #adb5bd;">
          <div style="font-size:2rem;">📭</div>
          <div class="fw-semibold mt-2">Hakuna rekodi za wanafunzi</div>
          <div class="small text-muted mt-1">
            Hakuna ankara zilizoundwa kwa darasa hili katika muhula huu. Unda ankara kwanza.
          </div>
        </div>

        <!-- Preview table (eligible students only) or read-only-only view -->
        <div v-else-if="preview.length > 0 || readOnlyRows.length > 0">
          <!-- Summary bar -->
          <div class="d-flex gap-3 flex-wrap mb-3">
            <div class="rounded px-3 py-2 d-flex flex-column align-items-center"
                 style="background:rgba(0,127,62,.08); min-width:120px;">
              <div class="fw-bold fs-5" style="color:#007f3e;">{{ preview.length }}</div>
              <div class="small text-muted">Wanafunzi</div>
            </div>
            <div class="rounded px-3 py-2 d-flex flex-column align-items-center"
                 style="background:rgba(220,53,69,.08); min-width:120px;">
              <div class="fw-bold fs-5 text-danger">{{ formatMoney(totalBalance) }}</div>
              <div class="small text-muted">Jumla Deni</div>
            </div>
            <div class="rounded px-3 py-2 d-flex flex-column align-items-center"
                 style="background:rgba(13,110,253,.08); min-width:120px;">
              <div class="fw-bold fs-5 text-primary">~{{ formatMoney(avgInstallmentAmount) }}</div>
              <div class="small text-muted">Kila Kipande (wastani)</div>
            </div>
            <div class="rounded px-3 py-2 d-flex flex-column align-items-center"
                 style="background:#f8f9fa; min-width:120px;">
              <div class="fw-bold fs-5">{{ form.total_installments }}×</div>
              <div class="small text-muted">Awamu</div>
            </div>
          </div>

          <!-- Student table -->
          <div class="border rounded overflow-hidden">
            <div class="d-flex justify-content-between align-items-center px-3 py-2"
                 style="background:#f8fffe; border-bottom:1px solid #dee2e6;">
              <span class="fw-semibold small" style="color:#007f3e;">Wanafunzi wanaostahili</span>
              <span class="badge bg-success">{{ preview.length }}</span>
            </div>
            <!-- Desktop table -->
            <div class="d-none d-md-block">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="small">#</th>
                    <th class="small">Mwanafunzi</th>
                    <th class="small">Ankara</th>
                    <th class="small text-end">Jumla Ada</th>
                    <th class="small text-end">Deni Linalobaki</th>
                    <th class="small text-center" v-for="n in form.total_installments" :key="n">
                      Awamu {{ n }}
                    </th>
                    <th class="small text-end">Mwisho</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in preview" :key="row.invoice_id">
                    <td class="small text-muted">{{ idx + 1 }}</td>
                    <td>
                      <div class="fw-semibold small">{{ row.student_name }}</div>
                      <div class="text-muted" style="font-size:.7rem;">{{ row.admission_number }}</div>
                    </td>
                    <td class="small text-muted">{{ row.invoice_number }}</td>
                    <td class="small text-end">{{ formatMoney(row.total_amount_cents) }}</td>
                    <td class="small text-end fw-semibold text-danger">{{ formatMoney(row.balance_due_cents) }}</td>
                    <td class="small text-center" v-for="n in form.total_installments" :key="n">
                      {{ formatMoney(row.installment_amount_cents) }}
                      <div class="text-muted" style="font-size:.65rem;">{{ row.due_dates[n-1] }}</div>
                    </td>
                    <td class="small text-end text-muted">{{ row.last_due_date }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Mobile cards -->
            <div class="d-md-none p-2">
              <div v-for="row in preview" :key="row.invoice_id" class="mb-2 p-2 rounded border">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-semibold small">{{ row.student_name }}</div>
                    <div class="text-muted" style="font-size:.72rem;">{{ row.invoice_number }}</div>
                  </div>
                  <span class="badge bg-danger">{{ formatMoney(row.balance_due_cents) }}</span>
                </div>
                <div class="mt-1 small text-muted">
                  {{ form.total_installments }} awamu × {{ formatMoney(row.installment_amount_cents) }}
                  · Mwisho: {{ row.last_due_date }}
                </div>
              </div>
            </div>
          </div>

          <!-- Due dates schedule -->
          <div v-if="preview.length > 0" class="mt-3 p-3 rounded" style="background:#f8fffe; border:1px solid #e0f2ec;">
            <div class="fw-semibold small mb-2" style="color:#007f3e;">📅 Ratiba ya Malipo</div>
            <div class="d-flex flex-wrap gap-2">
              <div v-for="(date, idx) in scheduleDates" :key="idx"
                   class="rounded px-2 py-1 small"
                   style="background:#fff; border:1px solid #dee2e6;">
                <span class="fw-semibold">Awamu {{ idx + 1 }}</span>
                <span class="text-muted ms-1">{{ date }}</span>
              </div>
            </div>
          </div>

          <!-- Read-only: students with paid/no-balance invoices -->
          <div v-if="readOnlyRows.length > 0" class="mt-3 border rounded overflow-hidden">
            <div class="d-flex justify-content-between align-items-center px-3 py-2"
                 style="background:#f8f9fa; border-bottom:1px solid #dee2e6;">
              <span class="fw-semibold small text-muted">Wanafunzi (Ankara Zimeshalipwa / Hana Deni)</span>
              <span class="badge bg-secondary">{{ readOnlyRows.length }}</span>
            </div>
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th class="small">#</th>
                  <th class="small">Mwanafunzi</th>
                  <th class="small">Ankara</th>
                  <th class="small text-end">Jumla Ada</th>
                  <th class="small text-end">Deni Linalobaki</th>
                  <th class="small">Hali</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in readOnlyRows" :key="row.invoice_id" class="text-muted">
                  <td class="small">{{ idx + 1 }}</td>
                  <td>
                    <div class="small">{{ row.student_name }}</div>
                    <div style="font-size:.7rem;">{{ row.admission_number }}</div>
                  </td>
                  <td class="small">{{ row.invoice_number }}</td>
                  <td class="small text-end">{{ formatMoney(row.total_amount_cents) }}</td>
                  <td class="small text-end">{{ formatMoney(row.balance_due_cents) }}</td>
                  <td class="small">
                    <span class="badge bg-success-subtle text-success border border-success-subtle">Amelipa</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- No eligible students but some exist (all paid) -->
          <div v-if="preview.length === 0 && readOnlyRows.length > 0"
               class="mt-3 rounded p-3 text-center"
               style="background:rgba(25,135,84,.06); border:1px dashed #198754;">
            <div style="font-size:1.5rem;">✅</div>
            <div class="fw-semibold mt-1 text-success">Wanafunzi wote wameshalipa</div>
            <div class="small text-muted">Hakuna ankara zenye deni kwa darasa hili katika muhula huu.</div>
          </div>
        </div>

        <CAlert v-if="error" color="danger" class="mt-3 mb-0">{{ error }}</CAlert>
        <CAlert v-if="successMsg" color="success" class="mt-3 mb-0">{{ successMsg }}</CAlert>
      </div>
    </CModalBody>

    <CModalFooter style="border-top:2px solid #f0f0f0;">
      <CButton color="secondary" variant="outline" @click="$emit('close')" :disabled="saving">
        {{ t('common.cancel') }}
      </CButton>
      <CButton color="success" :disabled="saving || !canSubmit" @click="submit"
               style="min-width:160px; background:#007f3e; border-color:#007f3e;">
        <CSpinner v-if="saving" size="sm" class="me-1" />
        <span v-else>✓ Unda Awamu ({{ preview.length }} wanafunzi)</span>
      </CButton>
    </CModalFooter>
  </CModal>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const props = defineProps({ visible: Boolean })
const emit  = defineEmits(['close', 'done'])

// ── Form state ──────────────────────────────────────────────────────────────
const today = new Date().toISOString().slice(0, 10)
const form = ref({
  school_id:          '',
  school_class_id:    '',
  term_id:            '',
  total_installments: 3,
  start_date:         today,
  interval_days:      30,
})

// ── Lookup lists ─────────────────────────────────────────────────────────────
const schools         = ref([])
const filteredClasses = ref([])
const filteredTerms   = ref([])
const loadingSchools  = ref(false)
const loadingClasses  = ref(false)
const activeYearName = ref('')
const loadingTerms    = ref(false)

// ── Preview / submit state ───────────────────────────────────────────────────
const saving        = ref(false)
const error         = ref('')
const successMsg    = ref('')
const previewLoading= ref(false)
const preview       = ref([])      // eligible: balance_due > 0
const readOnlyRows  = ref([])      // paid / zero-balance invoices

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatMoney(cents) {
  return 'TZS ' + Number((cents || 0) / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

function addDaysToDate(dateStr, days) {
  const d = new Date(dateStr)
  d.setDate(d.getDate() + days)
  return d.toISOString().slice(0, 10)
}

// ── Computed ──────────────────────────────────────────────────────────────────
const scheduleDates = computed(() => {
  if (!form.value.start_date || !form.value.total_installments) return []
  return Array.from({ length: form.value.total_installments }, (_, i) =>
    addDaysToDate(form.value.start_date, i * (form.value.interval_days || 30))
  )
})

const totalBalance = computed(() =>
  preview.value.reduce((s, r) => s + (r.balance_due_cents || 0), 0)
)

const avgInstallmentAmount = computed(() => {
  if (!preview.value.length || !form.value.total_installments) return 0
  return Math.ceil(totalBalance.value / preview.value.length / form.value.total_installments)
})

const canSubmit = computed(() =>
  preview.value.length > 0 &&
  form.value.school_id &&
  form.value.school_class_id &&
  form.value.term_id &&
  form.value.total_installments >= 2 &&
  form.value.start_date &&
  form.value.interval_days >= 7
)

// ── Data loaders ──────────────────────────────────────────────────────────────
async function loadSchools() {
  loadingSchools.value = true
  try {
    const { data } = await api.get('/schools')
    schools.value = data.data ?? data
  } catch {
    schools.value = []
  } finally {
    loadingSchools.value = false
  }
}

async function loadClassesForSchool(schoolId) {
  if (!schoolId) { filteredClasses.value = []; return }
  loadingClasses.value = true
  try {
    const { data } = await api.get('/school-classes', { params: { school_id: schoolId } })
    filteredClasses.value = data.data ?? data
  } catch {
    filteredClasses.value = []
  } finally {
    loadingClasses.value = false
  }
}

async function loadTermsForSchool(schoolId) {
  if (!schoolId) { filteredTerms.value = []; return }
  loadingTerms.value = true
  try {
    // Get academic years for this school (they include terms via with('terms'))
    const { data } = await api.get('/academic-years', { params: { school_id: schoolId } })
    const years = data.data ?? data
    // One academic year only. Flattening every year listed FIRST..FOURTH TERM
    // once per year with nothing to tell them apart — five identical-looking
    // options, four of them for years nobody is billing. The year name was
    // already being collected here and then dropped by the <option>, which is
    // why the repeats were indistinguishable rather than merely redundant.
    //
    // Installment plans are raised against outstanding invoices, which belong to
    // the year being billed now, so the current year is the only sensible
    // default. Falling back to the newest year keeps the field usable at a
    // school that has not flagged one as current.
    const year = years.find(y => y.is_current)
      || [...years].sort((a, b) => String(b.name).localeCompare(String(a.name)))[0]

    if (!year) { filteredTerms.value = []; activeYearName.value = ''; return }

    activeYearName.value = year.name || ''

    let terms = year.terms || []
    if (!terms.length) {
      try {
        const tr = await api.get('/terms', { params: { academic_year_id: year.id } })
        terms = tr.data.data ?? tr.data
      } catch { terms = [] }
    }

    // Ordered by term number so FIRST..FOURTH read in sequence rather than in
    // whatever order the API returned them.
    filteredTerms.value = [...terms].sort((a, b) => (a.number || 0) - (b.number || 0))
  } catch {
    filteredTerms.value = []
    activeYearName.value = ''
  } finally {
    loadingTerms.value = false
  }
}

// ── Event handlers ────────────────────────────────────────────────────────────
async function onSchoolChange() {
  // Reset downstream selections
  form.value.school_class_id = ''
  form.value.term_id         = ''
  filteredClasses.value      = []
  filteredTerms.value        = []
  preview.value              = []
  readOnlyRows.value         = []
  error.value                = ''
  successMsg.value           = ''

  if (!form.value.school_id) return

  // Load classes and terms in parallel for the selected school
  await Promise.all([
    loadClassesForSchool(form.value.school_id),
    loadTermsForSchool(form.value.school_id),
  ])
}

function onClassOrTermChange() {
  loadPreview()
}

// ── Preview ───────────────────────────────────────────────────────────────────
function buildPreviewRows(invoices) {
  return invoices.map(inv => {
    const balance = inv.balance_due_cents || 0
    const n = form.value.total_installments || 3
    const perInstallment = Math.ceil(balance / n)
    const dueDates = Array.from({ length: n }, (_, i) =>
      addDaysToDate(form.value.start_date, i * (form.value.interval_days || 30))
    )
    return {
      invoice_id:               inv.id,
      invoice_number:           inv.invoice_number,
      student_name:             inv.student?.full_name || '—',
      admission_number:         inv.student?.admission_number || '',
      total_amount_cents:       inv.total_amount_cents,
      balance_due_cents:        balance,
      installment_amount_cents: perInstallment,
      due_dates:                dueDates,
      last_due_date:            dueDates[n - 1] || '—',
    }
  })
}

async function loadPreview() {
  if (!form.value.school_class_id || !form.value.term_id) {
    preview.value = []
    readOnlyRows.value = []
    return
  }
  previewLoading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/invoices', {
      params: {
        school_id:       form.value.school_id || undefined,
        school_class_id: form.value.school_class_id,
        term_id:         form.value.term_id,
        per_page:        500,
      },
    })
    const all = data.data || []
    const eligible = all.filter(inv => (inv.balance_due_cents || 0) > 0)
    const paid     = all.filter(inv => (inv.balance_due_cents || 0) <= 0)
    preview.value = buildPreviewRows(eligible)
    readOnlyRows.value = paid.map(inv => ({
      invoice_id:         inv.id,
      invoice_number:     inv.invoice_number,
      student_name:       inv.student?.full_name || '—',
      admission_number:   inv.student?.admission_number || '',
      total_amount_cents: inv.total_amount_cents,
      balance_due_cents:  inv.balance_due_cents || 0,
    }))
  } catch {
    preview.value = []
    readOnlyRows.value = []
  } finally {
    previewLoading.value = false
  }
}

function recalcPreview() {
  if (!preview.value.length) return
  preview.value = preview.value.map(row => {
    const n = form.value.total_installments || 3
    const perInstallment = Math.ceil(row.balance_due_cents / n)
    const dueDates = Array.from({ length: n }, (_, i) =>
      addDaysToDate(form.value.start_date, i * (form.value.interval_days || 30))
    )
    return { ...row, installment_amount_cents: perInstallment, due_dates: dueDates, last_due_date: dueDates[n - 1] || '—' }
  })
}

// ── Reset when modal opens ────────────────────────────────────────────────────
watch(() => props.visible, async (v) => {
  if (!v) return
  form.value = {
    school_id:          '',
    school_class_id:    '',
    term_id:            '',
    total_installments: 3,
    start_date:         today,
    interval_days:      30,
  }
  filteredClasses.value = []
  filteredTerms.value   = []
  preview.value         = []
  readOnlyRows.value    = []
  error.value           = ''
  successMsg.value      = ''
  await loadSchools()
})

// ── Submit ────────────────────────────────────────────────────────────────────
async function submit() {
  error.value      = ''
  successMsg.value = ''
  saving.value     = true
  try {
    const { data } = await api.post('/installments/bulk-by-class', {
      school_id:          form.value.school_id,
      school_class_id:    form.value.school_class_id,
      term_id:            form.value.term_id,
      total_installments: form.value.total_installments,
      start_date:         form.value.start_date,
      interval_days:      form.value.interval_days,
    })
    successMsg.value = data.message
    emit('done')
    setTimeout(() => emit('close'), 1800)
  } catch (e) {
    const msgs = e?.response?.data?.errors
      ? Object.values(e.response.data.errors).flat().join(' ')
      : null
    error.value = msgs || e?.response?.data?.message || 'Hitilafu imetokea. Jaribu tena.'
  } finally {
    saving.value = false
  }
}
</script>
