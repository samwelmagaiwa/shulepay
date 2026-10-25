<template>
  <div>
    <Teleport to="#sup-toolbar" defer>
        <CButton color="secondary" variant="outline" style="min-height:40px;" :title="t('printing.print')" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormSelect v-model="filters.supplier_id" style="max-width:220px;min-height:40px;" @change="load">
          <option value="">{{ t('purchasing.common.all') }}</option>
          <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </CFormSelect>
        <CFormCheck v-model="filters.expiring" :label="t('purchasing.doc.expiringOnly')" @change="load" />
        <CButton color="success" style="min-height:40px;" @click="openNew">
          <CIcon icon="cilPlus" class="me-1" />{{ t('purchasing.doc.add') }}
        </CButton>
    </Teleport>

    <div v-if="error" class="alert alert-danger py-2">{{ error }}</div>
    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <CCard v-else>
      <CCardBody class="p-0">
        <CTable responsive hover class="sup-grid mb-0 align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('purchasing.bill.supplier') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.doc.type') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.doc.reference') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.doc.expiry') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.status') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(d, i) in rows" :key="d.id">
              <CTableDataCell>{{ d.supplier?.name }}</CTableDataCell>
              <CTableDataCell>{{ t('purchasing.doc.types.' + d.type) }}<div class="small text-muted">{{ d.title }}</div></CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">{{ d.reference_number || '—' }}</CTableDataCell>
              <CTableDataCell>
                {{ d.expiry_date ? String(d.expiry_date).slice(0, 10) : t('purchasing.doc.noExpiry') }}
                <div v-if="d.days_left !== null" class="small" :class="d.days_left < 0 ? 'text-danger' : 'text-muted'">
                  {{ d.days_left < 0 ? t('purchasing.doc.expiredAgo', { n: Math.abs(d.days_left) }) : t('purchasing.doc.daysLeft', { n: d.days_left }) }}
                </div>
              </CTableDataCell>
              <CTableDataCell><CBadge :color="color(d.status)">{{ t('purchasing.doc.status.' + d.status) }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown v-if="d.file_url || access.can.document_delete" :placement="menuPlacement(i, rows.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('purchasing.common.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem v-if="d.file_url" :href="d.file_url" target="_blank" rel="noopener">{{ t('purchasing.doc.open') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.document_delete" @click="confirmDelete = d">{{ t('purchasing.doc.delete') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!rows.length"><CTableDataCell colspan="6" class="text-center text-muted py-4">{{ t('purchasing.doc.noDocs') }}</CTableDataCell></CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <CModal :visible="showForm" scrollable backdrop="static" class="modal-fullscreen-sm-down" @close="showForm = false">
      <CModalHeader><CModalTitle>{{ t('purchasing.doc.add') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div v-if="formError" class="alert alert-danger py-2">{{ formError }}</div>
        <CRow class="g-3">
          <CCol md="12">
            <label class="form-label fw-semibold">{{ t('purchasing.bill.supplier') }} *</label>
            <CFormSelect v-model="form.supplier_id" :invalid="!!errs.supplier_id">
              <option value="">—</option>
              <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <label class="form-label fw-semibold">{{ t('purchasing.doc.type') }} *</label>
            <CFormSelect v-model="form.type">
              <option v-for="ty in types" :key="ty" :value="ty">{{ t('purchasing.doc.types.' + ty) }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6"><label class="form-label">{{ t('purchasing.doc.reference') }}</label><CFormInput v-model="form.reference_number" /></CCol>
          <CCol md="12"><label class="form-label">{{ t('purchasing.doc.title') }}</label><CFormInput v-model="form.title" /></CCol>
          <CCol md="6"><label class="form-label">{{ t('purchasing.doc.issue') }}</label><CFormInput v-model="form.issue_date" type="date" /></CCol>
          <CCol md="6"><label class="form-label">{{ t('purchasing.doc.expiry') }}</label><CFormInput v-model="form.expiry_date" type="date" /></CCol>
          <CCol md="12"><label class="form-label">{{ t('purchasing.doc.file') }}</label>
            <input type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" @change="file = $event.target.files[0] || null"></CCol>
        </CRow>
      </CModalBody>
      <CModalFooter class="gap-2">
        <CButton color="secondary" @click="showForm = false">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="primary" :disabled="saving" @click="save"><CSpinner v-if="saving" size="sm" class="me-1" />{{ t('purchasing.common.save') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete: ask first -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete = null">
      <CModalHeader><CModalTitle>{{ t('purchasing.doc.confirmDeleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody v-if="confirmDelete">
        <p class="mb-2">{{ t('purchasing.doc.confirmDeleteBody') }}</p>
        <div class="border rounded p-2 bg-light">
          <div class="fw-semibold">{{ confirmDelete.supplier?.name }}</div>
          <div class="small text-muted">
            {{ t('purchasing.doc.types.' + confirmDelete.type) }}<span v-if="confirmDelete.title"> — {{ confirmDelete.title }}</span>
          </div>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="confirmDelete = null">{{ t('purchasing.common.no') }}</CButton>
        <CButton color="danger" :disabled="deleting" @click="doDelete">
          <CSpinner v-if="deleting" size="sm" class="me-1" />{{ t('purchasing.common.yes') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('purchasing.tabs.documents')" :lines="printLines" :columns="printColumns" :rows="rows" />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, apiError, access } from '@/services/purchasing'

const { t } = useI18n()

const types = ['tin_certificate', 'business_licence', 'tax_clearance', 'contract', 'insurance', 'other']
const rows = ref([])
const suppliers = ref([])
const loading = ref(false)
const error = ref('')
const saving = ref(false)
const showForm = ref(false)
const confirmDelete = ref(null)
const deleting = ref(false)
const formError = ref('')
const file = ref(null)
const filters = reactive({ supplier_id: '', expiring: false })
const form = reactive({ supplier_id: '', type: 'tax_clearance', title: '', reference_number: '', issue_date: '', expiry_date: '' })
const errs = reactive({})

const color = (st) => ({ valid: 'success', expiring: 'warning', expired: 'danger' }[st] || 'secondary')

async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = await purchasing.documents({ supplier_id: filters.supplier_id || undefined, expiring: filters.expiring ? 1 : undefined })
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  } finally {
    loading.value = false
  }
}

function openNew() {
  Object.keys(errs).forEach(k => delete errs[k])
  formError.value = ''
  file.value = null
  Object.assign(form, { supplier_id: filters.supplier_id || '', type: 'tax_clearance', title: '', reference_number: '', issue_date: '', expiry_date: '' })
  showForm.value = true
}

async function save() {
  Object.keys(errs).forEach(k => delete errs[k])
  if (!form.supplier_id) { errs.supplier_id = t('purchasing.common.fixErrors'); return }
  saving.value = true
  formError.value = ''
  const body = new FormData()
  for (const [k, v] of Object.entries(form)) if (v !== '' && v !== null) body.append(k, v)
  if (file.value) body.append('file', file.value)
  try {
    await purchasing.addDocument(body)
    showForm.value = false
    await load()
  } catch (e) {
    formError.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    saving.value = false
  }
}

async function doDelete() {
  const d = confirmDelete.value
  if (!d) return
  deleting.value = true
  try {
    await purchasing.deleteDocument(d.id)
    await load()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    deleting.value = false
    confirmDelete.value = null
  }
}

onMounted(async () => {
  load()
  try { suppliers.value = (await purchasing.suppliers({ per_page: 500 })).data } catch { /* picker stays empty */ }
})

// ── Print view: the table as it is on screen, with the school's header ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('purchasing.bill.supplier'), value: (r) => r.supplier?.name },
  { label: t('purchasing.doc.type'), value: (r) => t('purchasing.doc.types.' + r.type) + (r.title ? ` — ${r.title}` : '') },
  { label: t('purchasing.doc.reference'), value: (r) => r.reference_number },
  { label: t('purchasing.doc.expiry'), value: (r) => (r.expiry_date ? String(r.expiry_date).slice(0, 10) : t('purchasing.doc.noExpiry')) },
  { label: t('purchasing.common.status'), value: (r) => t('purchasing.doc.status.' + r.status) },
])
const printLines = computed(() => {
  const lines = []
  const who = suppliers.value.find((x) => String(x.id) === String(filters.supplier_id))?.name
  if (who) lines.push(t('purchasing.bill.supplier') + ': ' + who)
  if (filters.expiring) lines.push(t('purchasing.doc.expiringOnly'))
  return lines
})
</script>
