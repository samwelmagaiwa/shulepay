<script setup>
import { ref, onMounted } from 'vue'
import { useInventoryStore } from '@/stores/inventory'

const store = useInventoryStore()

// ─── Add/Edit Item Modal ─────────────────────────────────────────────────────
const showItemModal = ref(false)
const editingItem   = ref(null)
const itemForm = ref({
  name: '', description: '', unit: '', category_id: '',
  quantity: 0, reorder_level: 5, unit_cost_cents: 0, location: '',
})
const itemSaving = ref(false)
const itemError  = ref('')

function openAddItem() {
  editingItem.value = null
  itemForm.value = {
    name: '', description: '', unit: '', category_id: '',
    quantity: 0, reorder_level: 5, unit_cost_cents: 0, location: '',
  }
  itemError.value = ''
  showItemModal.value = true
}

function openEditItem(item) {
  editingItem.value = item
  itemForm.value = {
    name: item.name, description: item.description || '', unit: item.unit,
    category_id: item.category_id || '', quantity: item.quantity,
    reorder_level: item.reorder_level, unit_cost_cents: item.unit_cost_cents,
    location: item.location || '',
  }
  itemError.value = ''
  showItemModal.value = true
}

async function saveItem() {
  itemSaving.value = true
  itemError.value = ''
  try {
    if (editingItem.value) {
      await store.updateItem(editingItem.value.id, itemForm.value)
    } else {
      await store.createItem(itemForm.value)
    }
    showItemModal.value = false
    await store.fetchSummary()
  } catch (e) {
    itemError.value = e?.response?.data?.message || 'Hitilafu wakati wa kuhifadhi bidhaa'
  } finally {
    itemSaving.value = false
  }
}

async function deleteItem(item) {
  if (!confirm(`Futa "${item.name}"?`)) return
  await store.deleteItem(item.id)
  await store.fetchSummary()
}

// ─── Transaction Panel ───────────────────────────────────────────────────────
const selectedItem       = ref(null)
const showTxnPanel       = ref(false)
const itemTransactions   = ref([])
const txnLoading         = ref(false)
const txnForm = ref({
  type: 'in', quantity: 1, notes: '',
  reference: '', transaction_date: new Date().toISOString().slice(0, 10),
})
const txnSaving = ref(false)
const txnError  = ref('')

async function openItemPanel(item) {
  selectedItem.value = item
  showTxnPanel.value = true
  txnLoading.value = true
  txnForm.value = {
    type: 'in', quantity: 1, notes: '',
    reference: '', transaction_date: new Date().toISOString().slice(0, 10),
  }
  try {
    itemTransactions.value = await store.fetchTransactions(item.id)
  } finally {
    txnLoading.value = false
  }
}

async function recordTransaction() {
  txnSaving.value = true
  txnError.value = ''
  try {
    const result = await store.recordTransaction(selectedItem.value.id, txnForm.value)
    // Update local selected item's quantity
    if (result.item) selectedItem.value = result.item
    itemTransactions.value = await store.fetchTransactions(selectedItem.value.id)
    await store.fetchSummary()
    txnForm.value = {
      type: 'in', quantity: 1, notes: '',
      reference: '', transaction_date: new Date().toISOString().slice(0, 10),
    }
  } catch (e) {
    txnError.value = e?.response?.data?.message || 'Hitilafu wakati wa kurekodi muamala'
  } finally {
    txnSaving.value = false
  }
}

function formatMoney(cents) {
  return 'TZS ' + ((cents || 0) / 100).toLocaleString()
}

function txnTypeBadge(type) {
  return { in: 'success', out: 'danger', adjustment: 'warning' }[type] || 'secondary'
}

function txnTypeLabel(type) {
  return { in: 'Ingiza', out: 'Toa', adjustment: 'Marekebisho' }[type] || type
}

onMounted(async () => {
  await Promise.all([store.fetchItems(), store.fetchSummary()])
})
</script>

<template>
  <CContainer fluid class="py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0 fw-bold">📦 Hesabu ya Mali (Inventory)</h4>
      <CButton color="success" @click="openAddItem">+ Ongeza Bidhaa</CButton>
    </div>

    <!-- Summary Cards -->
    <CRow class="mb-4 g-3">
      <CCol xs="12" md="4">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold text-primary">{{ store.summaryStats.total_items ?? 0 }}</div>
            <div class="small text-muted">Bidhaa Zote</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="12" md="4">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-2 fw-bold" :class="(store.summaryStats.low_stock_count ?? 0) > 0 ? 'text-danger' : 'text-success'">
              {{ store.summaryStats.low_stock_count ?? 0 }}
            </div>
            <div class="small text-muted">
              Bidhaa Zinazokwisha
              <CBadge v-if="(store.summaryStats.low_stock_count ?? 0) > 0" color="danger" class="ms-1">!</CBadge>
            </div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="12" md="4">
        <CCard class="border-0 shadow-sm h-100">
          <CCardBody class="text-center">
            <div class="fs-5 fw-bold text-info">{{ formatMoney(store.summaryStats.total_value_cents) }}</div>
            <div class="small text-muted">Thamani ya Jumla</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <!-- Items Table -->
    <CCard class="border-0 shadow-sm">
      <CCardBody>
        <div v-if="store.loading" class="text-center py-4">
          <CSpinner />
        </div>
        <CTable v-else responsive hover class="align-middle">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>#</CTableHeaderCell>
              <CTableHeaderCell>Jina la Bidhaa</CTableHeaderCell>
              <CTableHeaderCell>Kitengo</CTableHeaderCell>
              <CTableHeaderCell>Idadi</CTableHeaderCell>
              <CTableHeaderCell>Kiwango cha Chini</CTableHeaderCell>
              <CTableHeaderCell>Bei/Kitengo</CTableHeaderCell>
              <CTableHeaderCell>Thamani Jumla</CTableHeaderCell>
              <CTableHeaderCell></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow
              v-for="(item, i) in store.items"
              :key="item.id"
              :class="item.is_low_stock ? 'table-warning' : ''"
              style="cursor:pointer;"
            >
              <CTableDataCell>{{ i + 1 }}</CTableDataCell>
              <CTableDataCell>
                <span class="fw-semibold">{{ item.name }}</span>
                <span v-if="item.is_low_stock" class="ms-2 text-danger" title="Inakwisha">⚠️</span>
              </CTableDataCell>
              <CTableDataCell>{{ item.unit }}</CTableDataCell>
              <CTableDataCell :class="item.is_low_stock ? 'text-danger fw-bold' : ''">
                {{ Number(item.quantity).toLocaleString() }}
              </CTableDataCell>
              <CTableDataCell>{{ Number(item.reorder_level).toLocaleString() }}</CTableDataCell>
              <CTableDataCell>{{ formatMoney(item.unit_cost_cents) }}</CTableDataCell>
              <CTableDataCell>{{ formatMoney(item.total_value_cents) }}</CTableDataCell>
              <CTableDataCell>
                <CButtonGroup size="sm">
                  <CButton color="info" variant="ghost" @click="openItemPanel(item)">📋 Muamala</CButton>
                  <CButton color="primary" variant="ghost" @click.stop="openEditItem(item)">✏️</CButton>
                  <CButton color="danger" variant="ghost" @click.stop="deleteItem(item)">🗑️</CButton>
                </CButtonGroup>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="store.items.length === 0">
              <CTableDataCell colspan="8" class="text-center text-muted py-4">Hakuna bidhaa zilizosajiliwa</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Add/Edit Item Modal -->
    <CModal :visible="showItemModal" @close="showItemModal = false">
      <CModalHeader>
        <CModalTitle>{{ editingItem ? 'Hariri Bidhaa' : 'Ongeza Bidhaa Mpya' }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="itemError" color="danger">{{ itemError }}</CAlert>
        <CRow class="g-3">
          <CCol xs="12">
            <CFormLabel>Jina la Bidhaa *</CFormLabel>
            <CFormInput v-model="itemForm.name" />
          </CCol>
          <CCol md="6">
            <CFormLabel>Kitengo *</CFormLabel>
            <CFormInput v-model="itemForm.unit" placeholder="Vipande, kg, Lita..." />
          </CCol>
          <CCol md="6">
            <CFormLabel>Mahali</CFormLabel>
            <CFormInput v-model="itemForm.location" placeholder="Ghalani A..." />
          </CCol>
          <CCol md="4">
            <CFormLabel>Idadi ya Sasa</CFormLabel>
            <CFormInput v-model.number="itemForm.quantity" type="number" step="0.01" />
          </CCol>
          <CCol md="4">
            <CFormLabel>Kiwango cha Chini</CFormLabel>
            <CFormInput v-model.number="itemForm.reorder_level" type="number" step="0.01" />
          </CCol>
          <CCol md="4">
            <CFormLabel>Bei/Kitengo (Shilingi)</CFormLabel>
            <CFormInput v-model.number="itemForm.unit_cost_cents" type="number" />
          </CCol>
          <CCol xs="12">
            <CFormLabel>Maelezo</CFormLabel>
            <CFormTextarea v-model="itemForm.description" rows="2" />
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showItemModal = false">Funga</CButton>
        <CButton color="success" :disabled="itemSaving" @click="saveItem">
          <CSpinner v-if="itemSaving" size="sm" class="me-1" />
          Hifadhi
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Transaction Panel Modal -->
    <CModal :visible="showTxnPanel" @close="showTxnPanel = false" size="lg">
      <CModalHeader>
        <CModalTitle>📋 {{ selectedItem?.name }} — Historia ya Muamala</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <!-- Item stats -->
        <CRow class="mb-3 g-2" v-if="selectedItem">
          <CCol xs="4">
            <CCard class="border-0 bg-light text-center py-2">
              <div class="fw-bold fs-5">{{ Number(selectedItem.quantity).toLocaleString() }}</div>
              <div class="small text-muted">Idadi ya Sasa</div>
            </CCard>
          </CCol>
          <CCol xs="4">
            <CCard class="border-0 bg-light text-center py-2">
              <div class="fw-bold fs-5">{{ selectedItem.unit }}</div>
              <div class="small text-muted">Kitengo</div>
            </CCard>
          </CCol>
          <CCol xs="4">
            <CCard class="border-0 bg-light text-center py-2">
              <div class="fw-bold fs-5" :class="selectedItem.is_low_stock ? 'text-danger' : 'text-success'">
                {{ selectedItem.is_low_stock ? 'INAKWISHA' : 'INATOSHA' }}
              </div>
              <div class="small text-muted">Hali</div>
            </CCard>
          </CCol>
        </CRow>

        <!-- New Transaction Form -->
        <CCard class="border-primary mb-3">
          <CCardHeader class="fw-semibold">Rekodi Muamala Mpya</CCardHeader>
          <CCardBody>
            <CAlert v-if="txnError" color="danger">{{ txnError }}</CAlert>
            <CRow class="g-2">
              <CCol md="4">
                <CFormLabel>Aina *</CFormLabel>
                <CFormSelect v-model="txnForm.type">
                  <option value="in">Ingiza (Stock In)</option>
                  <option value="out">Toa (Stock Out)</option>
                  <option value="adjustment">Marekebisho</option>
                </CFormSelect>
              </CCol>
              <CCol md="4">
                <CFormLabel>Idadi *</CFormLabel>
                <CFormInput v-model.number="txnForm.quantity" type="number" step="0.01" min="0.01" />
              </CCol>
              <CCol md="4">
                <CFormLabel>Tarehe *</CFormLabel>
                <CFormInput v-model="txnForm.transaction_date" type="date" />
              </CCol>
              <CCol md="6">
                <CFormLabel>Kumbukumbu</CFormLabel>
                <CFormInput v-model="txnForm.reference" placeholder="LPO-001..." />
              </CCol>
              <CCol md="6">
                <CFormLabel>Maelezo</CFormLabel>
                <CFormInput v-model="txnForm.notes" />
              </CCol>
              <CCol xs="12" class="text-end">
                <CButton color="success" :disabled="txnSaving" @click="recordTransaction">
                  <CSpinner v-if="txnSaving" size="sm" class="me-1" />
                  Rekodi
                </CButton>
              </CCol>
            </CRow>
          </CCardBody>
        </CCard>

        <!-- Transaction History -->
        <div v-if="txnLoading" class="text-center py-3"><CSpinner /></div>
        <CTable v-else-if="itemTransactions.length > 0" responsive small>
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>Tarehe</CTableHeaderCell>
              <CTableHeaderCell>Aina</CTableHeaderCell>
              <CTableHeaderCell>Idadi</CTableHeaderCell>
              <CTableHeaderCell>Maelezo</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="txn in itemTransactions" :key="txn.id">
              <CTableDataCell>{{ txn.transaction_date }}</CTableDataCell>
              <CTableDataCell>
                <CBadge :color="txnTypeBadge(txn.type)">{{ txnTypeLabel(txn.type) }}</CBadge>
              </CTableDataCell>
              <CTableDataCell>{{ Number(txn.quantity).toLocaleString() }}</CTableDataCell>
              <CTableDataCell>{{ txn.notes || txn.reference || '-' }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
        <div v-else class="text-center text-muted py-3">Hakuna historia ya muamala</div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" @click="showTxnPanel = false">Funga</CButton>
      </CModalFooter>
    </CModal>
  </CContainer>
</template>
