<template>
  <CContainer fluid class="p-2 p-md-4">
    <CRow class="align-items-center mb-3 g-2">
      <CCol>
        <h4 class="fw-bold mb-0 fs-5">{{ t('audit.title') }}</h4>
        <p class="text-muted mb-0 small">{{ t('audit.subtitle') }}</p>
      </CCol>
    </CRow>

    <!-- Filters -->
    <CCard class="mb-3">
      <CCardBody class="p-2">
        <CRow class="g-2">
          <CCol xs="6" sm="4" md="2">
            <CFormInput type="date" v-model="filters.date_from" @change="load" style="min-height:44px;"
                        placeholder="Tarehe ya kwanza" />
          </CCol>
          <CCol xs="6" sm="4" md="2">
            <CFormInput type="date" v-model="filters.date_to" @change="load" style="min-height:44px;"
                        placeholder="Tarehe ya mwisho" />
          </CCol>
          <CCol xs="12" sm="4" md="3">
            <CFormSelect v-model="filters.action" @update:modelValue="load" style="min-height:44px;">
              <option value="">{{ t('audit.filters.allActions') }}</option>
              <option value="create">Kuunda</option>
              <option value="update">Kubadilisha</option>
              <option value="delete">Kufuta</option>
              <option value="login">Kuingia</option>
              <option value="logout">Kutoka</option>
              <option value="payment">Malipo</option>
            </CFormSelect>
          </CCol>
          <CCol xs="12" sm="6" md="3">
            <CFormInput v-model="filters.user" :placeholder="t('audit.user')" @input="debouncedLoad"
                        style="min-height:44px;" />
          </CCol>
          <CCol xs="12" sm="6" md="2">
            <CButton color="secondary" variant="outline" @click="resetFilters"
                     style="min-height:44px; width:100%;">
              {{ t('common.reset') }}
            </CButton>
          </CCol>
        </CRow>
      </CCardBody>
    </CCard>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <div v-else>
      <!-- Mobile cards -->
      <div class="d-md-none">
        <div v-if="!logs.length" class="text-center text-muted py-5">{{ t('audit.noEntries') }}</div>
        <div v-for="log in logs" :key="log.id" class="mb-2 border rounded p-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold">{{ log.user?.name || log.user_email }}</div>
              <div class="small text-muted">{{ formatDate(log.created_at) }}</div>
            </div>
            <CBadge :color="actionColor(log.action)">{{ log.action }}</CBadge>
          </div>
          <div class="mt-1 small">
            <span class="text-muted">Kitu: </span>{{ shortType(log.subject_type) }} #{{ log.subject_id }}
          </div>
          <div v-if="log.ip" class="small text-muted">IP: {{ log.ip }}</div>
        </div>
      </div>

      <!-- Desktop table -->
      <CCard class="d-none d-md-block">
        <CCardBody class="p-0">
          <CTable responsive hover small class="mb-0">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('audit.date') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('audit.user') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('audit.action') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('audit.model') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-lg-table-cell">{{ t('audit.details') }}</CTableHeaderCell>
                <CTableHeaderCell class="d-none d-xl-table-cell">IP</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="log in logs" :key="log.id">
                <CTableDataCell class="small text-nowrap">{{ formatDate(log.created_at) }}</CTableDataCell>
                <CTableDataCell>
                  <div class="fw-semibold small">{{ log.user?.name || '—' }}</div>
                  <div class="small text-muted">{{ log.user?.role }}</div>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge :color="actionColor(log.action)">{{ log.action }}</CBadge>
                </CTableDataCell>
                <CTableDataCell class="small">
                  {{ shortType(log.subject_type) }}
                  <span v-if="log.subject_id" class="text-muted">#{{ log.subject_id }}</span>
                  <span v-else class="text-muted">—</span>
                </CTableDataCell>
                <CTableDataCell class="d-none d-lg-table-cell" style="max-width:300px;">
                  <div v-if="log.changes?.after" class="small" style="font-family:monospace; white-space:pre-wrap; max-height:60px; overflow:hidden;">
                    {{ formatChanges(log.changes.after) }}
                  </div>
                  <span v-else class="text-muted small">—</span>
                </CTableDataCell>
                <CTableDataCell class="d-none d-xl-table-cell small text-muted">
                  {{ log.ip || '—' }}
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!logs.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">
                  {{ t('audit.noEntries') }}
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>
      </CCard>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="d-flex justify-content-center mt-3">
        <CPagination>
          <CPaginationItem :disabled="pagination.current_page <= 1"
                           @click="goPage(pagination.current_page - 1)">&laquo;</CPaginationItem>
          <CPaginationItem v-for="p in pageNumbers" :key="p"
                           :active="p === pagination.current_page"
                           @click="goPage(p)">{{ p }}</CPaginationItem>
          <CPaginationItem :disabled="pagination.current_page >= pagination.last_page"
                           @click="goPage(pagination.current_page + 1)">&raquo;</CPaginationItem>
        </CPagination>
      </div>
    </div>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const { t } = useI18n()

const logs       = ref([])
const loading    = ref(false)
const pagination = ref({ current_page: 1, last_page: 1, total: 0 })
const filters    = ref({ date_from: '', date_to: '', action: '', user: '' })
let   debTimer   = null

const pageNumbers = computed(() => {
  const total = pagination.value.last_page || 1
  const cur   = pagination.value.current_page || 1
  const pages = []
  for (let i = Math.max(1, cur - 2); i <= Math.min(total, cur + 2); i++) pages.push(i)
  return pages
})

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleString('sw-TZ', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit',
  })
}

function shortType(type) {
  if (!type) return '—'
  // "App\Models\Invoice" → "Invoice"
  return type.split('\\').pop()
}

function actionColor(action) {
  return {
    create: 'success', update: 'warning', delete: 'danger',
    login: 'info', logout: 'secondary', payment: 'primary',
  }[action] || 'secondary'
}

function formatChanges(val) {
  if (!val) return '—'
  if (typeof val === 'string') {
    try { return JSON.stringify(JSON.parse(val), null, 1) } catch { return val }
  }
  return JSON.stringify(val, null, 1)
}

async function load(page = pagination.value.current_page) {
  loading.value = true
  try {
    const params = { page, per_page: 50 }
    if (filters.value.date_from) params.date_from = filters.value.date_from
    if (filters.value.date_to)   params.date_to   = filters.value.date_to
    if (filters.value.action)    params.action    = filters.value.action
    if (filters.value.user)      params.user      = filters.value.user
    const { data } = await api.get('/audit-logs', { params })
    logs.value       = data.data || data
    pagination.value = data.meta || pagination.value
  } catch (e) { console.error(e) } finally { loading.value = false }
}

function debouncedLoad() {
  clearTimeout(debTimer)
  debTimer = setTimeout(() => load(1), 350)
}

function goPage(p) {
  pagination.value.current_page = p
  load(p)
}

function resetFilters() {
  filters.value = { date_from: '', date_to: '', action: '', user: '' }
  load(1)
}

onMounted(() => { try { load(1) } catch {} })
</script>
