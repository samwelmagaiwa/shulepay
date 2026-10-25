<template>
  <div>
    <Teleport to="#sup-toolbar" defer>
        <CButton color="secondary" variant="outline" style="min-height:40px;" :title="t('printing.print')" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
        <CFormInput v-model="filters.search" :placeholder="t('purchasing.common.search')" style="max-width:220px;min-height:40px;" @input="debouncedLoad" />
        <CFormSelect v-model="filters.status" style="max-width:170px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.supplier.allStatuses') }}</option>
          <option v-for="s in ['active', 'on_hold', 'inactive']" :key="s" :value="s">{{ t('purchasing.supplier.status.' + s) }}</option>
        </CFormSelect>
        <CFormSelect v-model="filters.category_id" style="max-width:200px;min-height:40px;" @change="reload">
          <option value="">{{ t('purchasing.supplier.allCategories') }}</option>
          <option v-for="c in categories.filter(c => c.is_active)" :key="c.id" :value="c.id">{{ c.label }}</option>
        </CFormSelect>
        <CFormCheck v-model="filters.owing" :label="t('purchasing.supplier.owingOnly')" @change="reload" />
        <CButton color="success" style="min-height:40px;" @click="openAdd">
          <CIcon icon="cilPlus" class="me-1" />{{ t('purchasing.supplier.add') }}
        </CButton>
    </Teleport>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>
    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>
    <CCard v-else>
      <CCardBody class="p-0">
        <CTable responsive hover class="sup-grid mb-0 align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('purchasing.supplier.name') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('purchasing.supplier.category') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('purchasing.supplier.phone') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('purchasing.supplier.balance') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end d-none d-md-table-cell">{{ t('purchasing.supplier.overdue') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('purchasing.common.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(s, i) in rows" :key="s.id">
              <CTableDataCell>
                <a href="#" class="fw-semibold text-decoration-none" @click.prevent="openDetail(s)">{{ s.name }}</a>
                <div class="small text-muted">
                  {{ s.code }}
                  <CBadge v-if="s.status !== 'active'" :color="s.status === 'on_hold' ? 'warning' : 'secondary'" class="ms-1">
                    {{ t('purchasing.supplier.status.' + s.status) }}
                  </CBadge>
                </div>
              </CTableDataCell>
              <CTableDataCell class="d-none d-lg-table-cell small">{{ s.category?.label || '—' }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell small">{{ s.phone || '—' }}</CTableDataCell>
              <CTableDataCell class="text-end fw-bold" :class="s.balance_cents > 0 ? 'text-danger' : 'text-success'">{{ formatMoney(s.balance_cents) }}</CTableDataCell>
              <CTableDataCell class="text-end d-none d-md-table-cell" :class="s.overdue_cents > 0 ? 'text-danger' : 'text-muted'">
                {{ s.overdue_cents > 0 ? formatMoney(s.overdue_cents) : '—' }}
              </CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown :placement="menuPlacement(i, rows.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('purchasing.common.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem @click="openDetail(s)">{{ t('purchasing.common.view') }}</CDropdownItem>
                    <CDropdownItem @click="openEdit(s)">{{ t('purchasing.common.edit') }}</CDropdownItem>
                    <CDropdownItem :disabled="s.status !== 'active'" @click="$emit('open-bill', s)">{{ t('purchasing.supplier.newBill') }}</CDropdownItem>
                    <CDropdownItem v-if="s.balance_cents > 0" @click="$emit('open-pay', s)">{{ t('purchasing.supplier.pay') }}</CDropdownItem>
                    <CDropdownItem @click="$emit('open-receive', s)">{{ t('purchasing.supplier.receive') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.supplier_deactivate && s.status === 'active'" @click="setStatus(s, 'on_hold')">{{ t('purchasing.supplier.hold') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.supplier_deactivate && s.status === 'active'" @click="setStatus(s, 'inactive')">{{ t('purchasing.supplier.deactivate') }}</CDropdownItem>
                    <CDropdownItem v-else-if="access.can.supplier_deactivate" @click="setStatus(s, 'active')">{{ t('purchasing.supplier.reactivate') }}</CDropdownItem>
                    <CDropdownItem v-if="access.can.supplier_merge" @click="openMerge(s)">{{ t('purchasing.supplier.merge') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!rows.length">
              <CTableDataCell colspan="6" class="text-center text-muted py-4">{{ t('purchasing.supplier.noSuppliers') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <div v-if="meta.last_page > 1" class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
      <small class="text-medium-emphasis">{{ meta.current_page }} / {{ meta.last_page }} · {{ meta.total }}</small>
      <CPagination size="sm" class="mb-0">
        <CPaginationItem :disabled="meta.current_page <= 1" @click="goPage(meta.current_page - 1)">‹</CPaginationItem>
        <CPaginationItem :disabled="meta.current_page >= meta.last_page" @click="goPage(meta.current_page + 1)">›</CPaginationItem>
      </CPagination>
    </div>

    <SupplierFormModal v-model:visible="showForm" :supplier="editing" :categories="categories" @saved="onSaved" />
    <SupplierDetailModal v-model:visible="showDetail" :supplier-id="detailId" @changed="onSaved" />

    <CModal :visible="showMerge" @close="showMerge = false">
      <CModalHeader><CModalTitle>{{ t('purchasing.supplier.merge') }} — {{ mergeTarget?.name }}</CModalTitle></CModalHeader>
      <CModalBody>
        <p class="text-muted small">{{ t('purchasing.supplier.mergeHelp') }}</p>
        <label class="form-label fw-semibold">{{ t('purchasing.supplier.mergePick') }}</label>
        <CFormSelect v-model="mergeFrom">
          <option value="">—</option>
          <option v-for="s in rows.filter(r => r.id !== mergeTarget?.id)" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
        </CFormSelect>
        <div v-if="mergeError" class="text-danger small mt-2">{{ mergeError }}</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showMerge = false">{{ t('purchasing.common.cancel') }}</CButton>
        <CButton color="danger" :disabled="!mergeFrom || merging" @click="doMerge">{{ t('purchasing.supplier.mergeConfirm') }}</CButton>
      </CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="t('purchasing.tabs.suppliers')" :lines="printLines" :columns="printColumns" :rows="rows" />
  </div>
</template>

<script setup>
import PrintSheet from '@/components/PrintSheet.vue'
import { menuPlacement } from '@/utils/menu'
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing, formatMoney, apiError, access } from '@/services/purchasing'
import SupplierFormModal from './SupplierFormModal.vue'
import SupplierDetailModal from './SupplierDetailModal.vue'

const emit = defineEmits(['changed', 'created', 'open-bill', 'open-pay', 'open-receive'])
const { t } = useI18n()

const rows = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const categories = ref([])
const loading = ref(false)
const error = ref('')
const page = ref(1)
const filters = reactive({ search: '', status: '', category_id: '', owing: false })

const showForm = ref(false)
const editing = ref(null)
const showDetail = ref(false)
const detailId = ref(null)

const showMerge = ref(false)
const mergeTarget = ref(null)
const mergeFrom = ref('')
const mergeError = ref('')
const merging = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await purchasing.suppliers({
      page: page.value,
      search: filters.search || undefined,
      status: filters.status || undefined,
      category_id: filters.category_id || undefined,
      owing: filters.owing ? 1 : undefined,
    })
    rows.value = res.data
    meta.value = res.meta || meta.value
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.loadFailed'))
  } finally {
    loading.value = false
  }
}

let timer = null
function debouncedLoad() {
  clearTimeout(timer)
  timer = setTimeout(reload, 300)
}
function reload() { page.value = 1; load() }
function goPage(p) { page.value = p; load() }

function openAdd() { editing.value = null; showForm.value = true }
function openEdit(s) { editing.value = s; showForm.value = true }
function openDetail(s) { detailId.value = s.id; showDetail.value = true }
function onSaved(saved) {
  const wasNew = !editing.value
  load()
  emit('changed')
  if (wasNew && saved?.id) emit('created', saved)
}

async function setStatus(s, status) {
  try {
    await purchasing.setSupplierStatus(s.id, status)
    onSaved()
  } catch (e) {
    error.value = apiError(e, t('purchasing.common.saveFailed'))
  }
}

function openMerge(s) {
  mergeTarget.value = s
  mergeFrom.value = ''
  mergeError.value = ''
  showMerge.value = true
}

async function doMerge() {
  merging.value = true
  mergeError.value = ''
  try {
    await purchasing.mergeSupplier(mergeTarget.value.id, mergeFrom.value)
    showMerge.value = false
    onSaved()
  } catch (e) {
    mergeError.value = apiError(e, t('purchasing.common.saveFailed'))
  } finally {
    merging.value = false
  }
}

onMounted(async () => {
  load()
  try { categories.value = await purchasing.categories() } catch { /* the filter just stays short */ }
})

// ── Print view: the table as it is on screen, with the school's header ──
const printing = ref(false)
const printColumns = computed(() => [
  { label: t('purchasing.supplier.name'), value: (r) => r.name + (r.code ? ` (${r.code})` : '') },
  { label: t('purchasing.supplier.category'), value: (r) => r.category?.label },
  { label: t('purchasing.supplier.phone'), value: (r) => r.phone },
  { label: t('purchasing.supplier.balance'), value: (r) => formatMoney(r.balance_cents), align: 'end' },
  { label: t('purchasing.supplier.overdue'), value: (r) => (r.overdue_cents > 0 ? formatMoney(r.overdue_cents) : '—'), align: 'end' },
  { label: t('purchasing.common.status'), value: (r) => t('purchasing.supplier.status.' + r.status) },
])
const printLines = computed(() => {
  const lines = []
  if (filters.search) lines.push(t('purchasing.common.search') + ': ' + filters.search)
  if (filters.owing) lines.push(t('purchasing.supplier.owingOnly'))
  return lines
})
</script>
