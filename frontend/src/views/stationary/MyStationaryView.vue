<template>
  <CContainer fluid class="py-3 px-3">

    <!-- Tab bar + Request button on same row -->
    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <div class="d-flex gap-1 flex-wrap">
        <button
          v-for="tab in tabs" :key="tab.value"
          class="btn btn-sm"
          :class="activeTab === tab.value ? 'btn-success' : 'btn-outline-secondary'"
          @click="activeTab = tab.value">
          {{ tab.label }}
          <span v-if="tab.count" class="badge ms-1"
            :class="activeTab === tab.value ? 'bg-white text-success' : 'bg-secondary'">
            {{ tab.count }}
          </span>
        </button>
      </div>
      <button class="btn btn-success btn-sm ms-auto" @click="openRequest">
        + {{ t('stationary.request') }}
      </button>
    </div>

    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-4"><CSpinner size="sm" /> {{ t('common.loading') }}</div>

    <!-- Requests table -->
    <CCard v-else class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <div class="table-responsive">
          <CTable hover small class="align-middle mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('stationary.item') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('stationary.qty') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('stationary.reason') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('common.status') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('common.date') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="r in filtered" :key="r.id">
                <CTableDataCell class="fw-semibold">{{ r.item_name }}</CTableDataCell>
                <CTableDataCell class="text-center">
                  {{ r.quantity_requested }} {{ r.unit }}
                  <span v-if="r.quantity_provided" class="text-success small d-block">
                    ✓ {{ r.quantity_provided }} {{ t('stationary.given') }}
                  </span>
                </CTableDataCell>
                <CTableDataCell class="text-muted small" style="max-width:200px; white-space:pre-wrap;">
                  {{ r.reason || '—' }}
                </CTableDataCell>
                <CTableDataCell class="text-center">
                  <CBadge :color="statusColor(r.status)" class="px-2">{{ t(`stationary.${r.status}`) }}</CBadge>
                  <div v-if="r.status === 'rejected' && r.rejection_reason" class="text-danger small mt-1">
                    {{ r.rejection_reason }}
                  </div>
                </CTableDataCell>
                <CTableDataCell class="text-end text-muted small">{{ fmtDate(r.created_at) }}</CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!filtered.length">
                <CTableDataCell colspan="5" class="text-center text-muted py-4">
                  {{ t('stationary.noRequests') }}
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

    <!-- Request modal -->
    <CModal :visible="showForm" @close="showForm=false" backdrop="static" size="sm">
      <CModalHeader><CModalTitle>📎 {{ t('stationary.request') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="mb-2 py-2">{{ formError }}</CAlert>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('stationary.item') }} <span class="text-danger">*</span></CFormLabel>
          <CFormSelect v-model="form.item_id" size="sm">
            <option value="">— {{ t('stationary.selectItem') }} —</option>
            <option v-for="i in items" :key="i.id" :value="i.id">
              {{ i.name }} ({{ i.quantity }} {{ i.unit }} {{ t('stationary.inStock') }})
            </option>
          </CFormSelect>
          <div v-if="!items.length" class="text-muted small mt-1">{{ t('stationary.noStock') }}</div>
        </div>

        <div class="mb-3">
          <CFormLabel class="fw-semibold small">{{ t('stationary.qty') }} <span class="text-danger">*</span></CFormLabel>
          <CFormInput v-model.number="form.quantity_requested" type="number" min="0.5" step="0.5" size="sm" />
        </div>

        <div class="mb-1">
          <CFormLabel class="fw-semibold small">{{ t('stationary.reasonLabel') }}</CFormLabel>
          <CFormTextarea v-model="form.reason" rows="2" size="sm" :placeholder="t('stationary.reasonPlaceholder')" />
        </div>
      </CModalBody>
      <CModalFooter class="py-2">
        <CButton color="secondary" variant="ghost" size="sm" @click="showForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" size="sm"
          :disabled="saving || !form.item_id || !form.quantity_requested"
          @click="submit">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ t('stationary.sendRequest') }}
        </CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const requests = ref([])
const items    = ref([])
const loading  = ref(true)
const error    = ref('')
const activeTab = ref('all')

const showForm  = ref(false)
const saving    = ref(false)
const formError = ref('')
const form      = ref({ item_id: '', quantity_requested: 1, reason: '' })

const tabs = computed(() => {
  const counts = { pending: 0, approved: 0, provided: 0, rejected: 0 }
  requests.value.forEach(r => { if (counts[r.status] !== undefined) counts[r.status]++ })
  return [
    { value: 'all',      label: t('common.all') },
    { value: 'pending',  label: t('stationary.pending'),  count: counts.pending  },
    { value: 'approved', label: t('stationary.approved'), count: counts.approved },
    { value: 'provided', label: t('stationary.provided'), count: counts.provided },
    { value: 'rejected', label: t('stationary.rejected'), count: counts.rejected },
  ]
})

const filtered = computed(() =>
  activeTab.value === 'all' ? requests.value : requests.value.filter(r => r.status === activeTab.value)
)

function statusColor(s) {
  return { pending: 'warning', approved: 'info', provided: 'success', rejected: 'danger' }[s] ?? 'secondary'
}
function fmtDate(d) {
  return d ? new Date(d).toLocaleDateString('sw-TZ', { day: 'numeric', month: 'short', year: 'numeric' }) : '—'
}

async function load() {
  loading.value = true
  try {
    const [reqRes, itemRes] = await Promise.all([api.get('/stationary'), api.get('/stationary/items')])
    requests.value = reqRes.data
    items.value    = itemRes.data
  } catch (e) {
    error.value = e?.response?.data?.message || t('common.loadError')
  } finally {
    loading.value = false
  }
}

function openRequest() {
  form.value = { item_id: '', quantity_requested: 1, reason: '' }
  formError.value = ''
  showForm.value = true
}

async function submit() {
  saving.value = true
  formError.value = ''
  try {
    const { data } = await api.post('/stationary', form.value)
    requests.value.unshift(data)
    showForm.value = false
  } catch (e) {
    const errs = e?.response?.data?.errors
    formError.value = errs ? Object.values(errs).flat().join(' | ') : (e?.response?.data?.message || t('common.saveError'))
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>
