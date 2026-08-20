<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { useAuthStore }    from '@/stores/auth'
import { useSchoolsStore } from '@/stores/schools'

const { t } = useI18n()
const auth         = useAuthStore()
const schoolsStore = useSchoolsStore()

const isSuperAdmin     = computed(() => auth.isSuperAdmin)
const isOwner          = computed(() => auth.isOwner)
const showSchoolPicker = computed(() => isSuperAdmin.value || isOwner.value)

// ─── Filter State ─────────────────────────────────────────────────────────────
const selectedSchoolId = ref('')
const classes          = ref([])
const loadingClasses   = ref(false)
const selectedClass    = ref('')
const selectedDate     = ref(new Date().toISOString().slice(0, 10))

// ─── Register State ───────────────────────────────────────────────────────────
const registerData  = ref(null)
const students      = ref([])
const loading       = ref(false)
const submitting    = ref(false)
const loadError     = ref('')
const submitSuccess = ref('')
const submitError   = ref('')

// ─── Summary ──────────────────────────────────────────────────────────────────
const totalStudents = computed(() => students.value.length)
const presentCount  = computed(() => students.value.filter(s => s.status === 'present').length)
const lateCount     = computed(() => students.value.filter(s => s.status === 'late').length)
const absentCount   = computed(() => students.value.filter(s => s.status === 'absent').length)

// ─── Load Classes ─────────────────────────────────────────────────────────────
async function fetchClasses(schoolId) {
  loadingClasses.value = true
  classes.value        = []
  selectedClass.value  = ''
  try {
    const params = schoolId ? { school_id: schoolId } : {}
    const { data } = await api.get('/school-classes', { params })
    classes.value = data.data || data
  } catch {
    classes.value = []
  } finally {
    loadingClasses.value = false
  }
}

async function onSchoolChange(schoolId) {
  selectedSchoolId.value = schoolId
  students.value         = []
  registerData.value     = null
  await fetchClasses(schoolId)
}

onMounted(async () => {
  if (showSchoolPicker.value) {
    await schoolsStore.fetchSchools()
  } else {
    await fetchClasses(null)
  }
})

// ─── Load Register ────────────────────────────────────────────────────────────
async function loadRegister() {
  if (!selectedClass.value) return
  loading.value       = true
  loadError.value     = ''
  submitSuccess.value = ''
  submitError.value   = ''
  try {
    const { data } = await api.get('/attendance/register', {
      params: { school_class_id: selectedClass.value, date: selectedDate.value },
    })
    registerData.value = data
    students.value = data.students.map(s => ({
      ...s,
      status: s.status ?? 'present',
    }))
  } catch (e) {
    loadError.value = e?.response?.data?.message || t('attendance.loadError')
    students.value  = []
  } finally {
    loading.value = false
  }
}

function setStatus(studentId, status) {
  const s = students.value.find(s => s.student_id === studentId)
  if (s) s.status = status
}

function statusColor(status) {
  return { present: '#198754', late: '#fd7e14', absent: '#dc3545' }[status] || '#6c757d'
}

function statusBg(status) {
  return { present: 'rgba(25,135,84,.08)', late: 'rgba(253,126,20,.08)', absent: 'rgba(220,53,69,.08)' }[status] || 'transparent'
}

// ─── Submit ───────────────────────────────────────────────────────────────────
async function submitRegister() {
  if (!registerData.value) return
  submitting.value    = true
  submitSuccess.value = ''
  submitError.value   = ''
  try {
    const records = students.value.map(s => ({
      student_id: s.student_id,
      status:     s.status,
      remarks:    s.remarks || null,
    }))
    const { data } = await api.post('/attendance/bulk-mark', {
      school_class_id: registerData.value.class.id,
      date:            registerData.value.date,
      records,
    })
    submitSuccess.value = t('attendance.submitSuccess', {
      present: data.saved - data.absent_count - data.late_count,
      late:    data.late_count,
      absent:  data.absent_count,
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    submitError.value = e?.response?.data?.message || t('attendance.submitError')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="mahudhurio-page">

    <!-- ── Page Header ─────────────────────────────────────────────────────── -->
    <div class="page-header px-3 pt-3 pb-2 d-flex align-items-center gap-2">
      <span style="font-size:1.4rem;">📋</span>
      <h5 class="mb-0 fw-bold">{{ t('attendance.title') }}</h5>
    </div>

    <!-- ── Alerts ──────────────────────────────────────────────────────────── -->
    <div class="px-3">
      <CAlert v-if="submitSuccess" color="success" dismissible @close="submitSuccess = ''" class="mb-2 small">
        ✓ {{ submitSuccess }}
      </CAlert>
      <CAlert v-if="submitError" color="danger" dismissible @close="submitError = ''" class="mb-2 small">
        {{ submitError }}
      </CAlert>
    </div>

    <!-- ── Filter Card ─────────────────────────────────────────────────────── -->
    <div class="px-3 mb-3">
      <div class="filter-card rounded-3 p-3 border bg-white shadow-sm">
        <CRow class="g-2">

          <!-- School picker -->
          <CCol v-if="showSchoolPicker" xs="12" sm="6" lg="3">
            <label class="form-label fw-semibold small mb-1">{{ t('common.school') }}</label>
            <CFormSelect
              :modelValue="selectedSchoolId"
              @update:modelValue="onSchoolChange"
              size="sm"
            >
              <option value="">— {{ t('common.selectSchool') }} —</option>
              <option v-for="s in schoolsStore.schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>

          <!-- Class picker -->
          <CCol xs="12" sm="6" :lg="showSchoolPicker ? 3 : 5">
            <label class="form-label fw-semibold small mb-1">{{ t('attendance.selectClass') }}</label>
            <CFormSelect
              v-model="selectedClass"
              :disabled="(showSchoolPicker && !selectedSchoolId) || loadingClasses"
              size="sm"
            >
              <option value="">
                {{ loadingClasses ? t('common.loading') : `— ${t('attendance.selectClass')} —` }}
              </option>
              <option v-for="cls in classes" :key="cls.id" :value="cls.id">{{ cls.name }}</option>
            </CFormSelect>
          </CCol>

          <!-- Date -->
          <CCol xs="8" sm="6" :lg="showSchoolPicker ? 3 : 4">
            <label class="form-label fw-semibold small mb-1">{{ t('attendance.date') }}</label>
            <CFormInput v-model="selectedDate" type="date" size="sm" />
          </CCol>

          <!-- Load button -->
          <CCol xs="4" sm="6" :lg="showSchoolPicker ? 3 : 3" class="d-flex align-items-end">
            <CButton
              color="primary"
              class="w-100"
              size="sm"
              :disabled="!selectedClass || loading"
              @click="loadRegister"
            >
              <CSpinner v-if="loading" size="sm" class="me-1" style="width:12px;height:12px;" />
              <span v-else>📥</span>
              <span class="d-none d-sm-inline ms-1">{{ t('attendance.loadRegister') }}</span>
              <span class="d-sm-none ms-1">{{ t('attendance.load') }}</span>
            </CButton>
          </CCol>
        </CRow>

        <CAlert v-if="loadError" color="danger" class="mt-2 mb-0 small py-2">{{ loadError }}</CAlert>
      </div>
    </div>

    <!-- ── Summary Strip ───────────────────────────────────────────────────── -->
    <div v-if="students.length > 0" class="summary-strip px-3 mb-2">
      <div class="d-flex gap-2 overflow-x-auto pb-1" style="scrollbar-width:none;">
        <div class="summary-chip flex-shrink-0">
          <span class="chip-num">{{ totalStudents }}</span>
          <span class="chip-lbl">{{ t('attendance.total') }}</span>
        </div>
        <div class="summary-chip flex-shrink-0 chip-present">
          <span class="chip-num">{{ presentCount }}</span>
          <span class="chip-lbl">{{ t('attendance.present') }}</span>
        </div>
        <div class="summary-chip flex-shrink-0 chip-late">
          <span class="chip-num">{{ lateCount }}</span>
          <span class="chip-lbl">{{ t('attendance.late') }}</span>
        </div>
        <div class="summary-chip flex-shrink-0 chip-absent">
          <span class="chip-num">{{ absentCount }}</span>
          <span class="chip-lbl">{{ t('attendance.absent') }}</span>
        </div>
        <div class="ms-auto flex-shrink-0 d-flex align-items-center">
          <span class="text-muted small">{{ registerData?.class?.name }} · {{ registerData?.date }}</span>
        </div>
      </div>
    </div>

    <!-- ── DESKTOP: Table view (≥ 768px) ─────────────────────────────────── -->
    <div v-if="students.length > 0" class="d-none d-md-block px-3" style="padding-bottom:90px;">
      <div class="bg-white rounded-3 border shadow-sm overflow-hidden">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark" style="position:sticky;top:0;z-index:10;">
            <tr>
              <th style="width:48px;" class="ps-3">#</th>
              <th>{{ t('students.fullName') }}</th>
              <th style="width:160px;">{{ t('students.admissionNo') }}</th>
              <th class="text-center" style="width:320px;">{{ t('attendance.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(s, i) in students"
              :key="s.student_id"
              :style="{ backgroundColor: statusBg(s.status) }"
            >
              <td class="ps-3 fw-semibold text-muted">{{ i + 1 }}</td>
              <td>
                <div class="fw-semibold">{{ s.full_name }}</div>
              </td>
              <td><code class="text-muted">{{ s.admission_number }}</code></td>
              <td class="text-center py-2">
                <div class="d-flex gap-1 justify-content-center">
                  <button class="att-btn-sm" :class="s.status==='present'?'att-present-active':'att-present'" @click="setStatus(s.student_id,'present')">✓ {{ t('attendance.present') }}</button>
                  <button class="att-btn-sm" :class="s.status==='late'?'att-late-active':'att-late'"       @click="setStatus(s.student_id,'late')">⏰ {{ t('attendance.late') }}</button>
                  <button class="att-btn-sm" :class="s.status==='absent'?'att-absent-active':'att-absent'" @click="setStatus(s.student_id,'absent')">✗ {{ t('attendance.absent') }}</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── MOBILE: Card view (< 768px) ────────────────────────────────────── -->
    <div v-if="students.length > 0" class="d-md-none student-list px-3" style="padding-bottom:90px;">
      <div
        v-for="(s, i) in students"
        :key="s.student_id"
        class="student-card rounded-3 mb-2 px-3 py-2 border"
        :style="{ backgroundColor: statusBg(s.status) }"
      >
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="student-avatar flex-shrink-0"
               :style="{ background: statusColor(s.status) + '20', color: statusColor(s.status) }">
            {{ i + 1 }}
          </div>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate" style="font-size:.92rem;">{{ s.full_name }}</div>
            <div class="text-muted" style="font-size:.75rem;">{{ s.admission_number }}</div>
          </div>
          <span class="status-dot" :style="{ background: statusColor(s.status) }"></span>
        </div>
        <div class="d-flex gap-2">
          <button class="att-btn flex-fill" :class="s.status==='present'?'att-present-active':'att-present'" @click="setStatus(s.student_id,'present')">✓ {{ t('attendance.present') }}</button>
          <button class="att-btn flex-fill" :class="s.status==='late'?'att-late-active':'att-late'"       @click="setStatus(s.student_id,'late')">⏰ {{ t('attendance.late') }}</button>
          <button class="att-btn flex-fill" :class="s.status==='absent'?'att-absent-active':'att-absent'" @click="setStatus(s.student_id,'absent')">✗ {{ t('attendance.absent') }}</button>
        </div>
      </div>
    </div>

    <!-- ── Empty State ─────────────────────────────────────────────────────── -->
    <div v-else-if="!loading && !students.length && !loadError"
         class="text-center text-muted py-5 px-3">
      <div style="font-size:3rem;">📋</div>
      <div class="mt-2 small">{{ t('attendance.hint') }}</div>
    </div>

    <!-- ── Loading State ───────────────────────────────────────────────────── -->
    <div v-if="loading" class="text-center py-5">
      <CSpinner color="primary" />
      <div class="text-muted small mt-2">{{ t('common.loading') }}</div>
    </div>

    <!-- ── Submit FAB — teleported to body to escape overflow context ──── -->
    <Teleport to="body">
      <div v-if="students.length > 0" class="submit-fab">
        <div class="submit-inner px-3 py-2 d-flex align-items-center gap-3">
          <div class="text-muted small flex-grow-1">
            ✓ {{ presentCount }} &nbsp;⏰ {{ lateCount }} &nbsp;✗ {{ absentCount }}
            / {{ totalStudents }}
          </div>
          <CButton
            color="success"
            :disabled="submitting"
            @click="submitRegister"
            class="submit-btn"
          >
            <CSpinner v-if="submitting" size="sm" class="me-1" style="width:14px;height:14px;" />
            📨 {{ t('attendance.submit') }}
          </CButton>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<style scoped>
.mahudhurio-page {
  min-height: 100vh;
  background: #f5f6fa;
}

/* ── Page header ── */
.page-header {
  background: #fff;
  border-bottom: 1px solid #e9ecef;
  margin-bottom: 0;
}

/* ── Filter card ── */
.filter-card {
  background: #fff;
}

/* ── Summary chips ── */
.summary-strip {
  overflow: hidden;
}
.summary-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  background: #fff;
  border: 1px solid #dee2e6;
  border-radius: 10px;
  padding: 6px 14px;
  min-width: 64px;
}
.chip-num {
  font-size: 1.2rem;
  font-weight: 700;
  line-height: 1;
}
.chip-lbl {
  font-size: .65rem;
  color: #6c757d;
  margin-top: 2px;
}
.chip-present { border-color: #198754; }
.chip-present .chip-num { color: #198754; }
.chip-late    { border-color: #fd7e14; }
.chip-late    .chip-num { color: #fd7e14; }
.chip-absent  { border-color: #dc3545; }
.chip-absent  .chip-num { color: #dc3545; }

/* ── Student card ── */
.student-card {
  background: #fff;
  transition: background .15s;
}

.student-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: .85rem;
  flex-shrink: 0;
}

.status-dot {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

/* ── Attendance buttons ── */
.att-btn {
  border: none;
  border-radius: 8px;
  padding: 10px 4px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .15s;
  text-align: center;
  -webkit-tap-highlight-color: transparent;
  min-height: 44px; /* WCAG touch target */
}

/* Present */
.att-present        { background: rgba(25,135,84,.08); color: #198754; }
.att-present:active { background: rgba(25,135,84,.2); }
.att-present-active { background: #198754; color: #fff; box-shadow: 0 2px 6px rgba(25,135,84,.35); }

/* Late */
.att-late        { background: rgba(253,126,20,.08); color: #fd7e14; }
.att-late:active { background: rgba(253,126,20,.2); }
.att-late-active { background: #fd7e14; color: #fff; box-shadow: 0 2px 6px rgba(253,126,20,.35); }

/* Absent */
.att-absent        { background: rgba(220,53,69,.08); color: #dc3545; }
.att-absent:active { background: rgba(220,53,69,.2); }
.att-absent-active { background: #dc3545; color: #fff; box-shadow: 0 2px 6px rgba(220,53,69,.35); }

/* submit-fab moved to non-scoped block below */

/* ── Desktop compact buttons (table view) ── */
.att-btn-sm {
  border: none;
  border-radius: 6px;
  padding: 5px 12px;
  font-size: .78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all .12s;
  min-width: 80px;
}

/* ── Responsive tweaks ── */
@media (max-width: 576px) {
  .page-header h5 { font-size: 1rem; }
  .att-btn { font-size: .72rem; padding: 10px 2px; }
  .summary-chip { min-width: 54px; padding: 5px 10px; }
}
</style>

<!-- Non-scoped: teleported FAB renders in <body>, outside this component's scope -->
<style>
.submit-fab {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 1050;
  background: rgba(255,255,255,.96);
  border-top: 1px solid #dee2e6;
  backdrop-filter: blur(8px);
  box-shadow: 0 -4px 16px rgba(0,0,0,.08);
}
.submit-inner {
  max-width: 700px;
  margin: 0 auto;
}
.submit-btn {
  min-width: 150px;
  font-weight: 600;
  padding: 10px 20px;
  border-radius: 10px;
}
</style>
