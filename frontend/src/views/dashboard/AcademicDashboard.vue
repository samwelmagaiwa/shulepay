<template>
  <CContainer fluid class="p-2 p-md-3">

    <!-- Welcome banner -->
    <div class="mb-4 p-3 rounded-3"
         style="background:linear-gradient(135deg,#14532d 0%,#16a34a 100%); color:#fff;">
      <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center fw-bold"
             style="width:52px;height:52px;font-size:1.3rem;color:#14532d;flex-shrink:0;">
          {{ initials }}
        </div>
        <div>
          <div class="fw-bold" style="font-size:1.2rem;">{{ t('dashboard.welcome') }}, {{ auth.user?.name }}</div>
          <div style="opacity:.85; font-size:.9rem;">{{ todayDate }} &bull; {{ t('dashboard.academicTeacher') }}</div>
        </div>
      </div>
    </div>

    <!-- Stat cards -->
    <CRow class="g-3 mb-4">
      <CCol xs="6" md="3">
        <CCard class="h-100 text-center" style="border-left:4px solid #16a34a;">
          <CCardBody class="p-3">
            <div style="font-size:2rem;">🏫</div>
            <div class="fw-bold fs-4">{{ totals.classes }}</div>
            <div class="text-muted small">{{ t('dashboard.activeClasses') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100 text-center" style="border-left:4px solid #2563eb;">
          <CCardBody class="p-3">
            <div style="font-size:2rem;">👥</div>
            <div class="fw-bold fs-4">{{ totals.students }}</div>
            <div class="text-muted small">{{ t('dashboard.totalStudents') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100 text-center" style="border-left:4px solid #198754;">
          <CCardBody class="p-3">
            <div style="font-size:2rem;">✅</div>
            <div class="fw-bold fs-4">{{ totals.present }}</div>
            <div class="text-muted small">{{ t('dashboard.presentToday') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="h-100 text-center" style="border-left:4px solid #d97706;">
          <CCardBody class="p-3">
            <div style="font-size:2rem;">📊</div>
            <div class="fw-bold fs-4">{{ totals.rate }}%</div>
            <div class="text-muted small">{{ t('dashboard.attendanceRate') }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <CRow class="g-3">
      <!-- Quick actions -->
      <CCol xs="12" md="4">
        <CCard class="h-100">
          <CCardHeader class="fw-semibold">⚡ {{ t('dashboard.quickActions') }}</CCardHeader>
          <CCardBody class="d-flex flex-column gap-2 p-3">
            <CButton color="success" @click="$router.push('/mahudhurio')" class="w-100" style="min-height:44px;">
              📋 {{ t('nav.attendance') }}
            </CButton>
            <CButton color="outline-secondary" @click="$router.push('/arifa')" class="w-100" style="min-height:44px;">
              🔔 {{ t('nav.notifications') }}
            </CButton>
          </CCardBody>
        </CCard>
      </CCol>

      <!-- School-wide attendance by class -->
      <CCol xs="12" md="8">
        <CCard class="h-100">
          <CCardHeader class="d-flex justify-content-between align-items-center fw-semibold">
            <span>📊 {{ t('dashboard.classAttendanceOverview') }}</span>
            <span class="text-muted small">{{ todayShort }}</span>
          </CCardHeader>
          <CCardBody class="p-3">
            <div v-if="loading" class="text-center py-4"><CSpinner color="success" /></div>
            <div v-else-if="!summary.length" class="text-muted text-center py-4">
              {{ t('dashboard.noAttendanceToday') }}
            </div>
            <CTable v-else hover small class="mb-0">
              <CTableHead class="table-light">
                <CTableRow>
                  <CTableHeaderCell>{{ t('common.class') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('dashboard.present') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('dashboard.absent') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">{{ t('dashboard.total') }}</CTableHeaderCell>
                  <CTableHeaderCell class="text-center">%</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="row in summary" :key="row.class_id">
                  <CTableDataCell class="fw-semibold">{{ row.class_name }}</CTableDataCell>
                  <CTableDataCell class="text-center text-success">{{ row.present }}</CTableDataCell>
                  <CTableDataCell class="text-center text-danger">{{ row.absent }}</CTableDataCell>
                  <CTableDataCell class="text-center">{{ row.present + row.absent }}</CTableDataCell>
                  <CTableDataCell class="text-center">
                    <CBadge :color="row.rate >= 80 ? 'success' : row.rate >= 60 ? 'warning' : 'danger'">
                      {{ row.rate }}%
                    </CBadge>
                  </CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>
  </CContainer>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const { t } = useI18n()
const auth = useAuthStore()

const summary = ref([])
const loading = ref(true)

const initials = computed(() => {
  const name = auth.user?.name || ''
  return name.split(' ').map(p => p[0]).join('').toUpperCase().slice(0, 2)
})

const todayDate = computed(() => new Date().toLocaleDateString('en-TZ', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }))
const todayShort = computed(() => new Date().toLocaleDateString('en-TZ', { day: 'numeric', month: 'short', year: 'numeric' }))

const totals = computed(() => {
  const present = summary.value.reduce((a, r) => a + r.present, 0)
  const absent  = summary.value.reduce((a, r) => a + r.absent, 0)
  const total = present + absent
  return {
    classes:  summary.value.length,
    students: total,
    present,
    rate: total ? Math.round(present / total * 100) : 0,
  }
})

onMounted(async () => {
  try {
    const { data } = await api.get('/attendance/summary', { params: { date: new Date().toISOString().slice(0, 10) } })
    const rows = Array.isArray(data) ? data : (data.data || [])
    summary.value = rows.map(r => ({
      class_id:   r.school_class_id || r.class_id,
      class_name: r.class_name || r.school_class?.name || '—',
      present:    r.present_count ?? r.present ?? 0,
      absent:     r.absent_count  ?? r.absent  ?? 0,
      rate:       r.attendance_rate ?? (r.present_count && r.total ? Math.round(r.present_count / r.total * 100) : 0),
    }))
  } catch { /* no data */ } finally {
    loading.value = false
  }
})
</script>
