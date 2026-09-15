<script setup>
/**
 * Students behind the dashboard's "Not linked to a class" fee figure. Read only.
 *
 * These are payments on invoices billed for an academic year in which the
 * student has no enrollment, so they cannot be placed in any class. Each row
 * shows the years billed beside the years the student is actually enrolled in,
 * because that mismatch is almost always the thing to fix.
 */
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'

const props = defineProps({ visible: Boolean })
const emit = defineEmits(['update:visible'])

const { t } = useI18n()

const students = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref('')
const open = ref(null)

const fmt = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

async function load() {
  loading.value = true
  error.value = ''
  open.value = null
  try {
    const { data } = await api.get('/dashboard/unassigned-fees')
    students.value = data.students || []
    total.value = data.total_cents || 0
  } catch (e) {
    error.value = e?.response?.status === 423
      ? t('dashboardLock.locked')
      : (e?.response?.data?.message || t('unassignedFees.loadFailed'))
    students.value = []
  } finally {
    loading.value = false
  }
}

watch(() => props.visible, (v) => { if (v) load() })
</script>

<template>
  <CModal :visible="visible" size="xl" alignment="center" scrollable @close="emit('update:visible', false)">
    <CModalHeader>
      <CModalTitle>{{ t('unassignedFees.title') }}</CModalTitle>
    </CModalHeader>

    <CModalBody>
      <p class="text-medium-emphasis small">{{ t('unassignedFees.help') }}</p>

      <CAlert v-if="error" color="danger" class="py-2 small">{{ error }}</CAlert>
      <div v-if="loading" class="text-center py-4"><CSpinner /></div>

      <div v-else-if="!error && !students.length" class="text-center text-muted py-4">
        {{ t('unassignedFees.none') }}
      </div>

      <template v-else-if="!error">
        <div class="d-flex justify-content-between small mb-2">
          <span>{{ t('unassignedFees.count', { count: students.length }) }}</span>
          <strong>{{ t('unassignedFees.total') }}: {{ fmt(total) }}</strong>
        </div>

        <CTable small hover responsive class="mb-0" style="font-size:.85rem;">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('unassignedFees.student') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('unassignedFees.billedFor') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('unassignedFees.enrolledIn') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-end">{{ t('unassignedFees.amount') }}</CTableHeaderCell>
              <CTableHeaderCell style="width:1%"></CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <template v-for="s in students" :key="s.student_id">
              <CTableRow>
                <CTableDataCell>
                  <RouterLink :to="`/wanafunzi/${s.student_id}`" class="fw-semibold">{{ s.student_name }}</RouterLink>
                  <CBadge v-if="s.student_deleted" color="secondary" class="ms-1">{{ t('unassignedFees.deleted') }}</CBadge>
                </CTableDataCell>
                <CTableDataCell class="text-danger fw-semibold">{{ s.billed_years.join(', ') }}</CTableDataCell>
                <CTableDataCell>
                  <span v-if="!s.enrollments.length" class="text-danger">{{ t('unassignedFees.noEnrollment') }}</span>
                  <div v-for="(e, i) in s.enrollments" :key="i" class="small">
                    {{ e.year }} · {{ e.class || '—' }}
                    <span class="text-muted">({{ e.admission_number }}, {{ e.status }})</span>
                  </div>
                </CTableDataCell>
                <CTableDataCell class="text-end fw-semibold">{{ fmt(s.total_cents) }}</CTableDataCell>
                <CTableDataCell>
                  <CButton size="sm" color="secondary" variant="ghost"
                           @click="open = open === s.student_id ? null : s.student_id">
                    {{ open === s.student_id ? '▲' : '▼' }}
                  </CButton>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="open === s.student_id">
                <CTableDataCell colspan="5" class="bg-light">
                  <div v-for="(p, i) in s.payments" :key="i" class="d-flex justify-content-between small py-1">
                    <span>{{ p.invoice_number }} · {{ p.year }} · {{ p.paid_at }}</span>
                    <span>{{ fmt(p.amount_cents) }}</span>
                  </div>
                </CTableDataCell>
              </CTableRow>
            </template>
          </CTableBody>
        </CTable>
      </template>
    </CModalBody>

    <CModalFooter>
      <CButton color="secondary" variant="ghost" @click="emit('update:visible', false)">
        {{ t('common.close') }}
      </CButton>
    </CModalFooter>
  </CModal>
</template>
