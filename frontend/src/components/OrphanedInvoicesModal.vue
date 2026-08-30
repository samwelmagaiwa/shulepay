<script setup>
/**
 * Invoices whose student has been deleted.
 *
 * Deleting a student no longer destroys their invoices, so they collect here
 * instead of disappearing. Clearing one is permanent and takes its payments
 * with it, which is exactly why it is a deliberate action on a screen that
 * shows what each row is worth, rather than a side effect of deleting a name.
 */
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const props = defineProps({ visible: Boolean })
const emit = defineEmits(['update:visible', 'purged'])

const { t } = useI18n()

const rows = ref([])
const totals = ref({ billed: 0, paid: 0 })
const selected = ref([])
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const confirmPurge = ref(false)

const fmt = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

const allSelected = computed(
  () => rows.value.length > 0 && selected.value.length === rows.value.length,
)

// What the selection is actually worth — the number that should give someone
// pause before confirming, so it is shown on the button's own dialog.
const selectedPaid = computed(() =>
  rows.value
    .filter((r) => selected.value.includes(r.id))
    .reduce((sum, r) => sum + (r.paid_cents || 0), 0),
)

function toggleAll() {
  selected.value = allSelected.value ? [] : rows.value.map((r) => r.id)
}

async function load() {
  loading.value = true
  error.value = ''
  selected.value = []
  try {
    const { data } = await api.get('/invoices/orphaned')
    rows.value = data.rows || []
    totals.value = { billed: data.total_billed_cents || 0, paid: data.total_paid_cents || 0 }
  } catch (e) {
    error.value = e?.response?.data?.message || t('orphanInvoices.loadFailed')
    rows.value = []
  } finally {
    loading.value = false
  }
}

async function purge() {
  busy.value = true
  error.value = ''
  try {
    const { data } = await api.delete('/invoices/orphaned', { data: { ids: selected.value } })
    confirmPurge.value = false
    await load()
    emit('purged', data)
  } catch (e) {
    error.value = e?.response?.data?.message || t('orphanInvoices.purgeFailed')
  } finally {
    busy.value = false
  }
}

watch(() => props.visible, (open) => { if (open) load() })
</script>

<template>
  <CModal :visible="visible" size="xl" alignment="center"
          @close="emit('update:visible', false)">
    <CModalHeader>
      <CModalTitle>{{ t('orphanInvoices.title') }}</CModalTitle>
    </CModalHeader>

    <CModalBody>
      <CAlert v-if="error" color="danger" class="py-2 small">{{ error }}</CAlert>

      <p class="text-medium-emphasis small">{{ t('orphanInvoices.help') }}</p>

      <div v-if="loading" class="text-center py-4"><CSpinner /></div>

      <div v-else-if="!rows.length" class="text-center text-muted py-4">
        {{ t('orphanInvoices.none') }}
      </div>

      <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-2 small">
          <span>
            {{ t('orphanInvoices.summary', { count: rows.length }) }}
            <strong class="ms-1">{{ fmt(totals.billed) }}</strong>
            <span v-if="totals.paid > 0" class="text-warning ms-2">
              {{ t('orphanInvoices.includingPaid', { paid: fmt(totals.paid) }) }}
            </span>
          </span>
          <CButton size="sm" color="secondary" variant="outline" @click="toggleAll">
            {{ allSelected ? t('orphanInvoices.clearSelection') : t('orphanInvoices.selectAll') }}
          </CButton>
        </div>

        <CTable small hover responsive style="font-size:.83rem;">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell style="width:1%"></CTableHeaderCell>
              <CTableHeaderCell>{{ t('orphanInvoices.student') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('orphanInvoices.invoiceNo') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('orphanInvoices.term') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('orphanInvoices.billed') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('orphanInvoices.paid') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('orphanInvoices.deletedOn') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="r in rows" :key="r.id">
              <CTableDataCell>
                <CFormCheck :value="r.id" v-model="selected" />
              </CTableDataCell>
              <CTableDataCell>{{ r.student_name }}</CTableDataCell>
              <CTableDataCell>{{ r.invoice_number }}</CTableDataCell>
              <CTableDataCell>{{ r.term }}</CTableDataCell>
              <CTableDataCell class="text-end">{{ fmt(r.total_cents) }}</CTableDataCell>
              <CTableDataCell class="text-end"
                              :class="r.paid_cents > 0 ? 'text-warning fw-semibold' : 'text-muted'">
                {{ fmt(r.paid_cents) }}
              </CTableDataCell>
              <CTableDataCell class="small text-muted">{{ r.student_deleted_at || '—' }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </template>
    </CModalBody>

    <CModalFooter class="gap-2">
      <CButton color="secondary" variant="ghost" @click="emit('update:visible', false)">
        {{ t('common.close') }}
      </CButton>
      <CButton color="danger" :disabled="!selected.length || busy" @click="confirmPurge = true">
        {{ t('orphanInvoices.deleteSelected', { count: selected.length }) }}
      </CButton>
    </CModalFooter>
  </CModal>

  <!-- Second confirmation, because this is irreversible and takes payment
       records with it. The amount already collected is repeated here so the
       decision is made against the figure, not against a row count. -->
  <CModal :visible="confirmPurge" size="sm" alignment="center" @close="confirmPurge = false">
    <CModalHeader><CModalTitle>{{ t('orphanInvoices.confirmTitle') }}</CModalTitle></CModalHeader>
    <CModalBody class="small">
      {{ t('orphanInvoices.confirmBody', { count: selected.length }) }}
      <CAlert v-if="selectedPaid > 0" color="warning" class="py-2 mt-2 mb-0">
        {{ t('orphanInvoices.confirmPaid', { paid: fmt(selectedPaid) }) }}
      </CAlert>
    </CModalBody>
    <CModalFooter class="gap-2">
      <CButton color="secondary" @click="confirmPurge = false">{{ t('common.cancel') }}</CButton>
      <CButton color="danger" :disabled="busy" @click="purge">
        <CSpinner v-if="busy" size="sm" class="me-1" />{{ t('common.delete') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
