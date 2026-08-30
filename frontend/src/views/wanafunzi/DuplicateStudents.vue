<script setup>
/**
 * Students registered more than once — read only.
 *
 * Deliberately offers no merge or delete button. Which record of a pair is
 * authoritative, and whether the money was received twice or merely recorded
 * twice, is a judgement about the school's books; this page presents the
 * evidence for that judgement rather than pre-empting it.
 */
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useSchoolStore } from '@/stores/school'

const { t } = useI18n()
const schoolStore = useSchoolStore()

const groups = ref([])
const summary = ref({ group_count: 0, student_count: 0, duplicated_paid_cents: 0 })
const loading = ref(false)
const error = ref('')

const fmt = (c) => 'TZS ' + Math.round((c || 0) / 100).toLocaleString()

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/students/duplicates')
    groups.value = data.groups || []
    summary.value = {
      group_count: data.group_count || 0,
      student_count: data.student_count || 0,
      duplicated_paid_cents: data.duplicated_paid_cents || 0,
    }
  } catch (e) {
    error.value = e?.response?.data?.message || t('duplicates.loadFailed')
  } finally {
    loading.value = false
  }
}

onMounted(load)

// The backend scopes duplicate groups to the active school (X-School-Id) —
// without this, switching schools in the nav left the page showing whatever
// school was active when it first loaded, same as every other list page here.
watch(() => schoolStore.activeSchoolId, load)
</script>

<template>
  <CContainer fluid class="px-2 px-md-3 pt-2 pb-3">
    <!-- No page heading: the nav item already names the screen, and the summary
         cards are the first thing worth reading. Refresh sits alongside them
         rather than in a title bar of its own. -->
    <CAlert v-if="error" color="danger" class="py-2">{{ error }}</CAlert>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <template v-else>
      <!-- Summary. The money figure is the one that matters: it is what the
           books may be overstating if a payment was recorded against both
           records rather than received twice. -->
      <div class="d-flex justify-content-end mb-2">
        <CButton size="sm" color="secondary" variant="outline" :disabled="loading" @click="load">
          {{ t('common.refresh') }}
        </CButton>
      </div>

      <CRow class="g-2 mb-3">
        <CCol xs="12" md="4">
          <CCard class="h-100" style="border-left:4px solid #6366f1;">
            <CCardBody class="py-2">
              <div class="text-muted small">{{ t('duplicates.groupsCard') }}</div>
              <div class="fs-5 fw-bold" style="color:#6366f1;">{{ summary.group_count }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" md="4">
          <CCard class="h-100" style="border-left:4px solid #0ea5e9;">
            <CCardBody class="py-2">
              <div class="text-muted small">{{ t('duplicates.recordsCard') }}</div>
              <div class="fs-5 fw-bold" style="color:#0ea5e9;">{{ summary.student_count }}</div>
            </CCardBody>
          </CCard>
        </CCol>
        <CCol xs="12" md="4">
          <CCard class="h-100" style="border-left:4px solid #dc3545;">
            <CCardBody class="py-2">
              <div class="text-muted small">{{ t('duplicates.paidCard') }}</div>
              <div class="fs-5 fw-bold text-danger">{{ fmt(summary.duplicated_paid_cents) }}</div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <CCard v-if="!groups.length">
        <CCardBody class="text-center text-muted py-5">{{ t('duplicates.none') }}</CCardBody>
      </CCard>

      <!-- One card per duplicated child, its records side by side so the
           differences (or the lack of them) are read at a glance. -->
      <CCard v-for="g in groups" :key="g.name + g.date_of_birth" class="mb-3">
        <CCardHeader class="d-flex align-items-center justify-content-between flex-wrap gap-2 bg-white">
          <div>
            <span class="fw-bold">{{ g.name }}</span>
            <span class="text-muted small ms-2">{{ t('duplicates.born') }} {{ g.date_of_birth }}</span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <CBadge color="secondary" shape="rounded-pill">
              {{ t('duplicates.recordsBadge', { count: g.count }) }}
            </CBadge>
            <CBadge v-if="g.identical_amounts" color="danger" shape="rounded-pill">
              {{ t('duplicates.identicalBadge') }}
            </CBadge>
          </div>
        </CCardHeader>

        <CCardBody class="p-0">
          <CTable small hover responsive class="mb-0" style="font-size:.85rem;">
            <CTableHead class="table-light">
              <CTableRow>
                <CTableHeaderCell>{{ t('duplicates.recordNo') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('duplicates.admission') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('duplicates.class') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('duplicates.invoices') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('duplicates.billed') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-end">{{ t('duplicates.paid') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('duplicates.registeredAt') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="s in g.students" :key="s.id">
                <CTableDataCell class="text-muted">#{{ s.id }}</CTableDataCell>
                <CTableDataCell class="fw-semibold">{{ s.admission_number || '—' }}</CTableDataCell>
                <CTableDataCell>{{ s.class || '—' }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ s.invoice_count }}</CTableDataCell>
                <CTableDataCell class="text-end">{{ fmt(s.billed_cents) }}</CTableDataCell>
                <CTableDataCell class="text-end"
                                :class="s.paid_cents > 0 ? 'text-success fw-semibold' : 'text-muted'">
                  {{ fmt(s.paid_cents) }}
                </CTableDataCell>
                <CTableDataCell class="small text-muted">{{ s.registered_at }}</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </CCardBody>

        <CCardFooter v-if="g.identical_amounts" class="bg-white small text-danger">
          {{ t('duplicates.identicalNote', { paid: fmt(g.duplicated_paid_cents) }) }}
        </CCardFooter>
      </CCard>
    </template>
  </CContainer>
</template>
