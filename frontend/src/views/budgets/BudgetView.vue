<template>
  <CContainer fluid class="px-3 py-3">
    <!-- Stat cards -->
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
      <CCard class="d-none d-md-block" style="overflow:visible;">
        <CCardBody class="p-0" style="overflow:visible;">
          <CTable hover class="mb-0">
            <CTableHead>
              <CTableRow>
                <CTableHeaderCell>{{ t('budgets.name') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('budgets.academicYear') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">
                  <CButton color="primary" size="sm" @click="openCreate">
                    <CIcon icon="cilPlus" class="me-1" /> {{ t('budgets.add') }}
                  </CButton>
                </CTableHeaderCell>
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
                <CTableDataCell class="text-center" style="overflow:visible; position:relative;">
                  <CDropdown alignment="end">
                    <CDropdownToggle color="info" variant="outline" size="sm" :caret="false" style="min-height:36px; min-width:36px;">
                      <CIcon icon="cilOptions" />
                    </CDropdownToggle>
                    <CDropdownMenu>
                      <CDropdownItem @click="openDetail(b)" style="cursor:pointer;">
                        <CIcon icon="cilInfo" class="me-2 text-info" /> {{ t('common.view') }}
                      </CDropdownItem>
                      <CDropdownItem @click="openEdit(b)" style="cursor:pointer;">
                        <CIcon icon="cilPencil" class="me-2 text-primary" /> {{ t('common.edit') }}
                      </CDropdownItem>
                      <CDropdownItem v-if="b.status === 'draft'" @click="doActivate(b)" style="cursor:pointer;">
                        <CIcon icon="cilCheck" class="me-2 text-success" /> {{ t('budgets.activate') }}
                      </CDropdownItem>
                      <CDropdownItem v-if="b.status === 'active'" @click="doClose(b)" style="cursor:pointer;">
                        <CIcon icon="cilLockLocked" class="me-2 text-warning" /> {{ t('budgets.close') }}
                      </CDropdownItem>
                      <CDropdownDivider />
                      <CDropdownItem @click="doDelete(b)" style="cursor:pointer;" class="text-danger">
                        <CIcon icon="cilTrash" class="me-2" /> {{ t('common.delete') }}
                      </CDropdownItem>
                    </CDropdownMenu>
                  </CDropdown>
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Mobile Cards -->
      <div class="d-md-none">
        <div class="d-flex justify-content-end mb-2">
          <CButton color="primary" size="sm" @click="openCreate" style="min-height:44px;">
            <CIcon icon="cilPlus" class="me-1" /> {{ t('budgets.add') }}
          </CButton>
        </div>
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
            <CDropdown>
              <CDropdownToggle color="info" variant="outline" size="sm" :caret="false" style="min-height:44px; min-width:44px;">
                <CIcon icon="cilOptions" />
              </CDropdownToggle>
              <CDropdownMenu>
                <CDropdownItem @click="openDetail(b)" style="cursor:pointer;">
                  <CIcon icon="cilInfo" class="me-2 text-info" /> {{ t('common.view') }}
                </CDropdownItem>
                <CDropdownItem @click="openEdit(b)" style="cursor:pointer;">
                  <CIcon icon="cilPencil" class="me-2 text-primary" /> {{ t('common.edit') }}
                </CDropdownItem>
                <CDropdownItem v-if="b.status === 'draft'" @click="doActivate(b)" style="cursor:pointer;">
                  <CIcon icon="cilCheck" class="me-2 text-success" /> {{ t('budgets.activate') }}
                </CDropdownItem>
                <CDropdownItem v-if="b.status === 'active'" @click="doClose(b)" style="cursor:pointer;">
                  <CIcon icon="cilLockLocked" class="me-2 text-warning" /> {{ t('budgets.close') }}
                </CDropdownItem>
                <CDropdownDivider />
                <CDropdownItem @click="doDelete(b)" style="cursor:pointer;" class="text-danger">
                  <CIcon icon="cilTrash" class="me-2" /> {{ t('common.delete') }}
                </CDropdownItem>
              </CDropdownMenu>
            </CDropdown>
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
    <!-- Delete Confirm Modal -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete=null" size="sm">
      <CModalHeader style="border-bottom:2px solid #dc3545;">
        <CModalTitle class="text-danger">{{ t('common.confirmDelete') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <p>{{ t('common.confirmDeleteMsg', { name: confirmDelete?.name }) }}</p>
      </CModalBody>
      <CModalFooter class="border-top-0">
        <CButton color="light" @click="confirmDelete=null" :disabled="deleting">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" @click="confirmDoDelete" :disabled="deleting">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('common.yesDelete') }}
        </CButton>
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

const showForm      = ref(false)
const confirmDelete = ref(null)
const deleting      = ref(false)
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

function doDelete(b) {
  confirmDelete.value = b
}

async function confirmDoDelete() {
  if (!confirmDelete.value) return
  const target = confirmDelete.value
  confirmDelete.value = null
  deleting.value = true
  try {
    await store.deleteBudget(target.id)
    successMsg.value = t('budgets.messages.deleted')
    await store.fetchBudgets()
  } catch (e) {
    store.error = e?.response?.data?.message || t('budgets.errors.delete')
  } finally {
    deleting.value = false
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
