<template>
  <CContainer fluid class="px-3 py-3">
    <CRow class="mb-3 align-items-center">
      <CCol>
        <h4 class="fw-bold mb-0">{{ t('budgets.title') }}</h4>
        <p class="text-muted mb-0 small">{{ t('budgets.subtitle') }}</p>
      </CCol>
      <CCol xs="auto">
        <CButton color="primary" @click="openCreate" style="min-height:44px;">
          <CIcon icon="cilPlus" class="me-1" /> {{ t('budgets.add') }}
        </CButton>
      </CCol>
    </CRow>

    <CRow class="g-3 mb-3">
      <CCol xs="12" sm="4">
        <CCard class="border-top border-primary border-3">
          <CCardBody class="text-center">
            <div class="fs-3 fw-bold text-primary">{{ store.budgets.length }}</div>
            <div class="text-muted small">{{ t('budgets.allBudgets') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="12" sm="4">
        <CCard class="border-top border-success border-3">
          <CCardBody class="text-center">
            <div class="fs-3 fw-bold text-success">{{ activeBudgets }}</div>
            <div class="text-muted small">{{ t('budgets.active') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="12" sm="4">
        <CCard class="border-top border-secondary border-3">
          <CCardBody class="text-center">
            <div class="fs-3 fw-bold text-secondary">{{ closedBudgets }}</div>
            <div class="text-muted small">{{ t('budgets.closed') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <CAlert v-if="store.error" color="danger" dismissible @close="store.error=''">{{ store.error }}</CAlert>
    <CAlert v-if="successMsg" color="success" dismissible @close="successMsg=''">{{ successMsg }}</CAlert>

    <div v-if="store.loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <template v-else>
      <!-- Desktop Table -->
      <CCard class="d-none d-md-block">
        <CCardBody class="p-0">
          <CTable hover responsive class="mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>{{ t('budgets.name') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('budgets.academicYear') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-if="!store.budgets.length">
                <CTableDataCell colspan="4" class="text-center text-muted py-4">{{ t('budgets.noBudgets') }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-for="b in store.budgets" :key="b.id">
                <CTableDataCell class="fw-semibold">{{ b.name }}</CTableDataCell>
                <CTableDataCell>{{ b.academic_year?.name || b.academic_year_id || '—' }}</CTableDataCell>
                <CTableDataCell><CBadge :color="statusColor(b.status)">{{ statusLabel(b.status) }}</CBadge></CTableDataCell>
                <CTableDataCell>
                  <div class="d-flex gap-1 flex-wrap">
                    <CButton size="sm" color="info" variant="outline" @click="openDetail(b)" style="min-height:36px;" title="Angalia">
                      <CIcon icon="cilInfo" />
                    </CButton>
                    <CButton size="sm" color="primary" variant="outline" @click="openEdit(b)" style="min-height:36px;" title="Hariri">
                      <CIcon icon="cilPencil" />
                    </CButton>
                    <CButton v-if="b.status === 'draft'" size="sm" color="success" variant="outline" @click="doActivate(b)" style="min-height:36px;" title="Washa">
                      <CIcon icon="cilCheck" />
                    </CButton>
                    <CButton v-if="b.status === 'active'" size="sm" color="warning" variant="outline" @click="doClose(b)" style="min-height:36px;" title="Funga">
                      <CIcon icon="cilLockLocked" />
                    </CButton>
                    <CButton size="sm" color="danger" variant="outline" @click="doDelete(b)" style="min-height:36px;" title="Futa">
                      <CIcon icon="cilTrash" />
                    </CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Mobile Cards -->
      <div class="d-md-none">
        <div v-if="!store.budgets.length" class="text-center text-muted py-4">{{ t('budgets.noBudgets') }}</div>
        <CCard v-for="b in store.budgets" :key="b.id" class="mb-2">
          <CCardBody>
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="fw-bold">{{ b.name }}</div>
                <div class="text-muted small">{{ b.academic_year?.name || b.academic_year_id || '—' }}</div>
              </div>
              <CBadge :color="statusColor(b.status)">{{ statusLabel(b.status) }}</CBadge>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <CButton size="sm" color="info" variant="outline" @click="openDetail(b)" style="min-height:44px;">
                <CIcon icon="cilInfo" />
              </CButton>
              <CButton size="sm" color="primary" variant="outline" @click="openEdit(b)" style="min-height:44px;">
                <CIcon icon="cilPencil" />
              </CButton>
              <CButton v-if="b.status === 'draft'" size="sm" color="success" variant="outline" @click="doActivate(b)" style="min-height:44px;">
                <CIcon icon="cilCheck" />
              </CButton>
              <CButton v-if="b.status === 'active'" size="sm" color="warning" variant="outline" @click="doClose(b)" style="min-height:44px;">
                <CIcon icon="cilLockLocked" />
              </CButton>
              <CButton size="sm" color="danger" variant="outline" @click="doDelete(b)" style="min-height:44px;">
                <CIcon icon="cilTrash" />
              </CButton>
            </div>
          </CCardBody>
        </CCard>
      </div>
    </template>

    <!-- Create/Edit Modal -->
    <CModal :visible="showForm" @close="showForm=false" class="modal-fullscreen-sm-down" size="lg">
      <CModalHeader>
        <CModalTitle>{{ editing ? t('budgets.editTitle') : t('budgets.addTitle') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger">{{ formError }}</CAlert>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgets.form.name') }} <span class="text-danger">*</span></label>
          <CFormInput v-model="form.name" :placeholder="t('budgets.form.namePlaceholder')" :invalid="!form.name && formTouched" />
          <div v-if="!form.name && formTouched" class="invalid-feedback d-block">{{ t('budgets.form.nameRequired') }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgets.form.academicYear') }}</label>
          <CFormSelect v-model="form.academic_year_id">
            <option value="">{{ t('budgets.form.selectYear') }}</option>
            <option v-for="y in academicYears" :key="y.id" :value="y.id">{{ y.name }}</option>
          </CFormSelect>
        </div>
        <div v-if="editing" class="mb-3">
          <label class="form-label fw-semibold">{{ t('budgets.form.status') }}</label>
          <div><CBadge :color="statusColor(form.status)">{{ statusLabel(form.status) }}</CBadge></div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="saveForm" style="min-height:44px;">
          <CSpinner v-if="saving" size="sm" class="me-1" />{{ editing ? t('common.save') : t('budgets.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Detail Modal -->
    <CModal :visible="showDetail" @close="showDetail=false" class="modal-fullscreen-sm-down" size="lg">
      <CModalHeader>
        <CModalTitle>{{ detailItem?.name }}</CModalTitle>
      </CModalHeader>
      <CModalBody v-if="detailItem">
        <CRow class="mb-3">
          <CCol xs="6"><span class="text-muted small">{{ t('budgets.form.academicYear') }}</span><div class="fw-semibold">{{ detailItem.academic_year?.name || '—' }}</div></CCol>
          <CCol xs="6"><span class="text-muted small">{{ t('budgets.form.status') }}</span><div><CBadge :color="statusColor(detailItem.status)">{{ statusLabel(detailItem.status) }}</CBadge></div></CCol>
        </CRow>
        <div v-if="detailItem.budget_items?.length">
          <strong class="d-block mb-2">{{ t('budgets.detail.items') }}</strong>
          <CTable small hover responsive>
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>{{ t('budgets.detail.heading') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('budgets.detail.type') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('budgets.detail.amount') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="item in detailItem.budget_items" :key="item.id">
                <CTableDataCell>{{ item.name || item.title }}</CTableDataCell>
                <CTableDataCell>{{ item.category || item.type || '—' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ fmtCents(item.amount_cents ?? item.amount) }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
        <p v-else class="text-muted">{{ t('budgets.detail.noItems') }}</p>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="outline" @click="showDetail=false">{{ t('common.close') }}</CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBudgetsStore } from '@/stores/budgets'
import api from '@/services/api'

const { t } = useI18n()
const store = useBudgetsStore()

const showForm   = ref(false)
const showDetail = ref(false)
const editing    = ref(null)
const detailItem = ref(null)
const saving     = ref(false)
const formError  = ref('')
const formTouched = ref(false)
const successMsg = ref('')
const academicYears = ref([])

const form = ref({ name: '', academic_year_id: '', status: 'draft' })

const activeBudgets = computed(() => store.budgets.filter(b => b.status === 'active').length)
const closedBudgets = computed(() => store.budgets.filter(b => b.status === 'closed').length)

function statusColor(s) {
  if (s === 'active') return 'success'
  if (s === 'closed') return 'secondary'
  return 'warning'
}
function statusLabel(s) {
  if (s === 'active') return t('budgets.statuses.active')
  if (s === 'closed') return t('budgets.statuses.closed')
  return t('budgets.statuses.draft')
}
function fmtCents(cents) {
  if (cents == null) return '—'
  return 'TZS ' + Math.round((cents || 0) / 100).toLocaleString('sw-TZ', { maximumFractionDigits: 0 })
}

function openCreate() {
  editing.value = null
  form.value = { name: '', academic_year_id: '', status: 'draft' }
  formError.value = ''
  formTouched.value = false
  showForm.value = true
}

function openEdit(b) {
  editing.value = b
  form.value = { name: b.name, academic_year_id: b.academic_year_id || '', status: b.status }
  formError.value = ''
  formTouched.value = false
  showForm.value = true
}

function openDetail(b) {
  detailItem.value = b
  showDetail.value = true
}

async function saveForm() {
  formTouched.value = true
  if (!form.value.name) return
  saving.value = true
  formError.value = ''
  try {
    const payload = { name: form.value.name, academic_year_id: form.value.academic_year_id || null }
    if (editing.value) {
      await store.updateBudget(editing.value.id, payload)
      successMsg.value = t('budgets.messages.saved')
    } else {
      await store.createBudget(payload)
      successMsg.value = t('budgets.messages.added')
    }
    showForm.value = false
    await store.fetchBudgets()
  } catch (e) {
    formError.value = e?.response?.data?.message || t('budgets.errors.save')
  } finally {
    saving.value = false
  }
}

async function doActivate(b) {
  try {
    await store.activateBudget(b.id)
    successMsg.value = t('budgets.messages.activated')
    await store.fetchBudgets()
  } catch (e) {
    store.error = e?.response?.data?.message || t('common.error')
  }
}

async function doClose(b) {
  try {
    await store.closeBudget(b.id)
    successMsg.value = t('budgets.messages.closed')
    await store.fetchBudgets()
  } catch (e) {
    store.error = e?.response?.data?.message || t('common.error')
  }
}

async function doDelete(b) {
  if (!confirm(`${t('common.delete')} "${b.name}"?`)) return
  try {
    await store.deleteBudget(b.id)
    successMsg.value = t('budgets.messages.deleted')
    await store.fetchBudgets()
  } catch (e) {
    store.error = e?.response?.data?.message || t('budgets.errors.delete')
  }
}

onMounted(async () => {
  try { await store.fetchBudgets() } catch {}
  try {
    const { data } = await api.get('/academic-years')
    academicYears.value = data.data || data
  } catch {}
})
</script>
