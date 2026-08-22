<template>
  <CContainer fluid>

    <!-- Toolbar: filters + add button in one row -->
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <CFormSelect v-model="filters.school_id" @update:modelValue="loadData" style="max-width:200px;">
        <option value="">{{ t('common.all') }} {{ t('common.school') }}</option>
        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
      </CFormSelect>
      <CFormSelect v-model="filters.academic_year_id" @update:modelValue="loadData" style="max-width:160px;">
        <option value="">{{ t('common.all') }} {{ t('common.year') }}</option>
        <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
      </CFormSelect>
      <CFormSelect v-model="filters.term_id" @update:modelValue="loadData" style="max-width:160px;">
        <option value="">{{ t('common.all') }} {{ t('common.term') }}</option>
        <option v-for="term in terms" :key="term.id" :value="term.id">{{ term.name }}</option>
      </CFormSelect>
      <CFormSelect v-model="filters.school_class_id" @update:modelValue="loadData" style="max-width:160px;">
        <option value="">{{ t('common.all') }} {{ t('common.class') }}</option>
        <option v-for="c in schoolClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
      </CFormSelect>
      <CButton color="primary" class="ms-auto flex-shrink-0" @click="openAdd">
        <CIcon icon="cilPlus" class="me-1" /> {{ t('fees.add') }}
      </CButton>
    </div>

    <!-- Table -->
    <CCard>
      <CCardBody class="p-0">
        <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>
        <div v-else-if="!store.structures.length" class="text-center text-muted py-5">
          {{ t('fees.noFees') }}
        </div>
        <CTable v-else responsive hover class="mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('common.school') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.class') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.year') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('common.term') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('fees.itemName') }} ({{ t('common.amount') }})</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('fees.total') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="s in store.structures" :key="s.id">
              <CTableDataCell>{{ s.school?.name || '—' }}</CTableDataCell>
              <CTableDataCell>{{ s.school_class?.name || '—' }}</CTableDataCell>
              <CTableDataCell>{{ s.academic_year?.name || '—' }}</CTableDataCell>
              <CTableDataCell>
                {{ s.term?.name || '—' }}
                <CBadge v-if="s.fee_mode === 'full_tuition'" color="info" class="ms-1" style="font-size:0.7rem;">
                  Awamu {{ s.installment_number }}/{{ s.installments_count }}
                </CBadge>
              </CTableDataCell>
              <CTableDataCell>
                <div v-for="item in s.items" :key="item.id" class="small">
                  {{ item.name }} — {{ formatMoney(item.amount_cents) }}
                  <CBadge v-if="item.is_optional" color="light" text-color="muted" class="ms-1">{{ t('fees.optional') }}</CBadge>
                </div>
              </CTableDataCell>
              <CTableDataCell class="text-end fw-bold">
                {{ formatMoney(s.items?.reduce((sum, i) => sum + i.amount_cents, 0) || 0) }}
              </CTableDataCell>
              <CTableDataCell style="position:relative; min-width:56px;">
                <CButton size="sm" color="secondary" variant="ghost" @click.stop="activeRow = activeRow?.id === s.id ? null : s">👁️</CButton>
                <div
                  v-if="activeRow?.id === s.id"
                  style="position:absolute; top:100%; right:0; background:#fff; border:1px solid #dee2e6; border-radius:6px; box-shadow:0 2px 8px rgba(0,0,0,.12); padding:4px; display:flex; flex-direction:column; gap:2px; z-index:10; min-width:140px;"
                  @click.stop
                >
                  <CButton size="sm" color="primary" variant="ghost" class="text-start" @click="openEdit(s); activeRow = null">
                    ✏️ {{ t('common.edit') }}
                  </CButton>
                  <CButton size="sm" color="danger" variant="ghost" class="text-start" @click="confirmDelete(s); activeRow = null">
                    🗑️ {{ t('common.delete') }}
                  </CButton>
                </div>
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Add/Edit Modal -->
    <CModal :visible="showModal" @close="closeModal" size="lg">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('common.edit') : t('fees.add') }} — {{ t('fees.title') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>

        <!-- Fee Mode Toggle (add only) -->
        <div v-if="!editing" class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:#f8f9fa; border:1px solid #dee2e6;">
          <span class="fw-semibold" :class="feeMode === 'per_term' ? 'text-primary' : 'text-muted'">{{ t('fees.modePerTerm') }}</span>
          <div class="form-check form-switch mb-0">
            <input
              class="form-check-input"
              type="checkbox"
              role="switch"
              id="feeModeToggle"
              style="width:3rem; height:1.5rem; cursor:pointer;"
              :checked="feeMode === 'full_tuition'"
              @change="feeMode = $event.target.checked ? 'full_tuition' : 'per_term'"
            />
          </div>
          <span class="fw-semibold" :class="feeMode === 'full_tuition' ? 'text-primary' : 'text-muted'">{{ t('fees.modeFullYear') }}</span>
        </div>

        <!-- School / Class / Year selectors (always shown in add mode) -->
        <CRow class="g-3 mb-3" v-if="!editing">
          <CCol sm="6">
            <label class="form-label">{{ t('common.school') }} *</label>
            <CFormSelect v-model="form.school_id" @update:modelValue="onModalSchoolChange">
              <option value="">{{ t('common.select') }}</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('common.class') }} *</label>
            <CFormSelect v-model="form.school_class_id" :disabled="!form.school_id || loadingModalClasses">
              <option value="">
                {{ loadingModalClasses ? t('fees.loadingClasses') : (form.school_id ? t('common.select') : t('fees.selectSchoolFirst')) }}
              </option>
              <option v-for="c in modalClasses" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol sm="6">
            <label class="form-label">{{ t('common.year') }} *</label>
            <CFormSelect v-model="form.academic_year_id" @update:modelValue="onYearChange">
              <option value="">{{ t('common.select') }}</option>
              <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
            </CFormSelect>
          </CCol>

          <!-- Term selector — only for per_term mode -->
          <CCol sm="6" v-if="feeMode === 'per_term'">
            <label class="form-label">{{ t('common.term') }} *</label>
            <CFormSelect v-model="form.term_id">
              <option value="">{{ t('common.select') }}</option>
              <option v-for="term in modalTerms" :key="term.id" :value="term.id">{{ term.name }}</option>
            </CFormSelect>
          </CCol>
        </CRow>

        <!-- ── FULL TUITION MODE ─────────────────────────────────────── -->
        <template v-if="feeMode === 'full_tuition' && !editing">
          <div class="border rounded p-3 mb-3" style="background:#f0f7ff;">
            <div class="fw-bold mb-3 text-primary">{{ t('fees.fullTuitionSection') }}</div>
            <CRow class="g-3">
              <CCol sm="6">
                <label class="form-label">{{ t('fees.fullTuitionAmount') }} *</label>
                <CFormInput
                  v-model.number="fullTuitionAmount"
                  type="number"
                  min="0"
                  step="100"
                  placeholder="Mfano: 1200000"
                />
                <div class="form-text">{{ t('fees.fullTuitionAmountHint') }}</div>
              </CCol>
              <CCol sm="6">
                <label class="form-label">{{ t('fees.installmentsCount') }} *</label>
                <CFormSelect v-model.number="installmentsCount">
                  <option v-for="n in validInstallments" :key="n" :value="n">
                    {{ t('fees.installmentEvery', { n, months: 12 / n }) }}
                  </option>
                </CFormSelect>
                <div class="form-text">{{ t('fees.installmentsDivisor') }}</div>
              </CCol>
            </CRow>

            <!-- Installment preview -->
            <div v-if="fullTuitionAmount > 0" class="mt-3 p-3 rounded" style="background:#fff; border:1px solid #cce5ff;">
              <div class="fw-semibold mb-2 text-primary">{{ t('fees.installmentSummary') }}</div>
              <CRow class="g-2">
                <CCol sm="4" class="text-center">
                  <div class="small text-muted">{{ t('fees.perInstallment') }}</div>
                  <div class="fw-bold fs-5 text-success">{{ formatMoney(perInstallmentCents) }}</div>
                </CCol>
                <CCol sm="4" class="text-center">
                  <div class="small text-muted">{{ t('fees.installmentCount') }}</div>
                  <div class="fw-bold fs-5">{{ installmentsCount }}</div>
                </CCol>
                <CCol sm="4" class="text-center">
                  <div class="small text-muted">{{ t('fees.intervalMonths') }}</div>
                  <div class="fw-bold fs-5">{{ t('fees.everyNMonths', { n: 12 / installmentsCount }) }}</div>
                </CCol>
              </CRow>
              <hr class="my-2" />
              <div class="d-flex flex-wrap gap-2 mt-1">
                <div
                  v-for="i in installmentsCount"
                  :key="i"
                  class="badge text-bg-primary py-2 px-3"
                  style="font-size:0.85rem;"
                >
                  {{ t('fees.installmentN', { n: i }) }}: {{ formatMoney(perInstallmentCents) }}
                </div>
              </div>
              <div class="mt-2 small text-muted">
                {{ t('fees.annualTotal') }}: <strong>{{ formatMoney(perInstallmentCents * installmentsCount) }}</strong>
                <span v-if="perInstallmentCents * installmentsCount !== fullTuitionAmount * 100" class="text-warning ms-2">
                  ({{ t('fees.roundingDiff', { amount: formatMoney(fullTuitionAmount * 100 - perInstallmentCents * installmentsCount) }) }})
                </span>
              </div>
            </div>

            <CAlert v-if="!form.academic_year_id" color="warning" class="mt-3 mb-0 py-2">
              {{ t('fees.selectYearFirst') }}
            </CAlert>
            <CAlert v-else-if="modalTerms.length < installmentsCount" color="danger" class="mt-3 mb-0 py-2">
              {{ t('fees.notEnoughTerms', { count: modalTerms.length, needed: installmentsCount }) }}
            </CAlert>
            <div v-else-if="modalTerms.length > 0" class="mt-3 small text-muted">
              {{ t('fees.installmentsForTerms', { count: installmentsCount, terms: modalTerms.slice(0, installmentsCount).map(tm => tm.name).join(', ') }) }}
            </div>
          </div>
        </template>

        <!-- ── PER TERM MODE — fee items ────────────────────────────── -->
        <template v-else>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>{{ t('fees.itemName') }}</strong>
            <CButton size="sm" color="success" variant="outline" @click="addItem">
              <CIcon icon="cilPlus" class="me-1" />{{ t('fees.addItem') }}
            </CButton>
          </div>
          <div v-for="(item, idx) in form.items" :key="idx" class="d-flex gap-2 mb-2 align-items-center">
            <CFormInput v-model="item.name" :placeholder="t('fees.itemName')" class="flex-grow-1" />
            <CFormInput v-model.number="item.amount_cents" type="number" min="0" style="width:140px" :placeholder="t('fees.itemAmount')" />
            <CFormCheck v-model="item.is_optional" :label="t('fees.optional')" />
            <CButton size="sm" color="danger" variant="ghost" @click="removeItem(idx)" :disabled="form.items.length === 1">
              <CIcon icon="cilTrash" />
            </CButton>
          </div>
          <div class="text-end mt-2 text-muted small">
            {{ t('fees.total') }}: <strong>{{ formatMoney(form.items.reduce((s, i) => s + (i.amount_cents || 0), 0)) }}</strong>
          </div>
        </template>

        <CAlert v-if="modalError" color="danger" class="mt-3">{{ modalError }}</CAlert>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="closeModal">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="saveStructure">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ t('common.save') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="showDeleteModal" @close="showDeleteModal = false" size="sm" class="modal-fullscreen-sm-down">
      <CModalHeader><CModalTitle>🗑️ {{ t('common.delete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="mb-1 text-muted small">{{ t('common.confirmDelete') || 'Are you sure you want to delete?' }}</p>
        <p class="fw-bold mb-0">{{ deleteTarget?.school_class?.name }} — {{ deleteTarget?.term?.name }}</p>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showDeleteModal = false" style="min-height:44px;">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete" style="min-height:44px;">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.delete') }}
        </CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFeeStructuresStore } from '@/stores/feeStructures'
import { useSchoolsStore }       from '@/stores/schools'
import { useSchoolStore }        from '@/stores/school'
import api from '@/services/api'

const { t } = useI18n()
const store       = useFeeStructuresStore()
const schoolsStore = useSchoolsStore()
const schoolStore  = useSchoolStore()

const filters      = ref({ school_id: schoolStore.activeSchoolId || '', academic_year_id: '', term_id: '', school_class_id: '' })
const showModal       = ref(false)
const editing         = ref(null)
const activeRow       = ref(null)
const showDeleteModal = ref(false)
const deleteTarget    = ref(null)
const deleting        = ref(false)

function closeActions() { activeRow.value = null }
onMounted(() => document.addEventListener('click', closeActions))
onUnmounted(() => document.removeEventListener('click', closeActions))
const saving       = ref(false)
const modalError   = ref('')
const academicYears    = ref([])
const terms            = ref([])
const schoolClasses    = ref([])
const modalClasses     = ref([])
const modalTerms       = ref([])   // terms for the selected year in modal
const loadingModalClasses = ref(false)

// Full tuition mode state
const feeMode          = ref('per_term')
const fullTuitionAmount = ref(0)   // in TZS (whole units, not cents)
const installmentsCount = ref(3)
const validInstallments = [1, 2, 3, 4, 6, 12]

const perInstallmentCents = computed(() => {
  if (!fullTuitionAmount.value || installmentsCount.value < 1) return 0
  return Math.round((fullTuitionAmount.value * 100) / installmentsCount.value)
})

const schools = computed(() => schoolsStore.schools)

watch(() => schoolStore.activeSchoolId, (id) => {
  filters.value.school_id = id || ''
  loadData()
})

const emptyForm = () => ({
  school_id: '', school_class_id: '', academic_year_id: '', term_id: '',
  items: [{ name: '', amount_cents: 0, is_optional: false }],
})
const form = ref(emptyForm())

function formatMoney(cents) {
  return 'TZS ' + Number(cents / 100).toLocaleString('sw-TZ', { minimumFractionDigits: 0 })
}

function addItem() {
  form.value.items.push({ name: '', amount_cents: 0, is_optional: false })
}
function removeItem(idx) {
  form.value.items.splice(idx, 1)
}

async function onModalSchoolChange() {
  form.value.school_class_id  = ''
  form.value.academic_year_id = ''
  form.value.term_id          = ''
  modalClasses.value = []
  modalTerms.value = []
  if (!form.value.school_id) return

  loadingModalClasses.value = true
  try {
    const r = await api.get('/school-classes', { params: { school_id: form.value.school_id } })
    modalClasses.value = r.data.data ?? r.data
  } catch {}
  finally { loadingModalClasses.value = false }
}

async function onYearChange() {
  form.value.term_id = ''
  modalTerms.value = []
  if (!form.value.academic_year_id) return

  const year = academicYears.value.find(y => y.id == form.value.academic_year_id)
  if (year?.terms?.length) {
    modalTerms.value = year.terms
    return
  }
  // Fetch terms if not embedded in the year object
  try {
    const r = await api.get('/terms', { params: { academic_year_id: form.value.academic_year_id } })
    modalTerms.value = r.data.data ?? r.data
  } catch {}
}

function openAdd() {
  editing.value  = null
  form.value     = emptyForm()
  modalClasses.value = []
  modalTerms.value = []
  modalError.value = ''
  feeMode.value = 'per_term'
  fullTuitionAmount.value = 0
  installmentsCount.value = 3
  showModal.value = true
}

async function openEdit(s) {
  editing.value  = s
  modalError.value = ''
  feeMode.value = 'per_term'
  form.value = {
    school_id: s.school_id, school_class_id: s.school_class_id,
    academic_year_id: s.academic_year_id, term_id: s.term_id,
    items: s.items.map(i => ({ id: i.id, name: i.name, amount_cents: i.amount_cents, is_optional: !!i.is_optional })),
  }
  if (s.school_id) {
    try {
      const r = await api.get('/school-classes', { params: { school_id: s.school_id } })
      modalClasses.value = r.data.data ?? r.data
    } catch { modalClasses.value = [] }
  }
  showModal.value = true
}

function closeModal() {
  showModal.value = false
}

async function saveStructure() {
  modalError.value = ''
  saving.value = true
  try {
    if (editing.value) {
      await store.updateStructure(editing.value.id, { items: form.value.items })
    } else if (feeMode.value === 'full_tuition') {
      await store.createStructure({
        fee_mode: 'full_tuition',
        school_id: form.value.school_id,
        school_class_id: form.value.school_class_id,
        academic_year_id: form.value.academic_year_id,
        full_tuition_cents: Math.round(fullTuitionAmount.value * 100),
        installments_count: installmentsCount.value,
      })
    } else {
      await store.createStructure({ ...form.value, fee_mode: 'per_term' })
    }
    closeModal()
    await loadData()
  } catch (e) {
    modalError.value = e?.response?.data?.message || t('common.error')
  } finally {
    saving.value = false
  }
}

function confirmDelete(s) {
  deleteTarget.value = s
  showDeleteModal.value = true
}

async function doDelete() {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await store.deleteStructure(deleteTarget.value.id)
    showDeleteModal.value = false
    await loadData()
  } catch (e) {
    alert(e?.response?.data?.message || t('common.error'))
  } finally {
    deleting.value = false
  }
}

async function loadData() {
  const params = {}
  if (filters.value.school_id)        params.school_id        = filters.value.school_id
  if (filters.value.academic_year_id) params.academic_year_id = filters.value.academic_year_id
  if (filters.value.term_id)          params.term_id          = filters.value.term_id
  if (filters.value.school_class_id)  params.school_class_id  = filters.value.school_class_id
  await store.fetchStructures(params)
}

onMounted(async () => {
  try { await schoolsStore.fetchSchools() } catch {}
  try {
    const [years, classes] = await Promise.all([
      api.get('/academic-years'),
      api.get('/school-classes'),
    ])
    academicYears.value = years.data.data || years.data
    schoolClasses.value = classes.data.data || classes.data
    academicYears.value.forEach(y => {
      if (y.terms) terms.value.push(...y.terms)
    })
  } catch {}
  await loadData()
})
</script>

<style scoped>
:deep(.table-responsive) { overflow: visible !important; }
:deep(.card) { overflow: visible !important; }
</style>
