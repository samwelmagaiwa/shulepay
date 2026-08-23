<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()

// ── State ─────────────────────────────────────────────────────────────────────
const users   = ref([])
const schools = ref([])
const loading = ref(false)
const error   = ref('')

const showForm   = ref(false)
const editTarget = ref(null)
const saving     = ref(false)
const formError  = ref('')

const search     = ref('')
const filterRole = ref('')

const form = ref(resetForm())

function resetForm() {
  return { name: '', email: '', password: '', phone: '', role: '', school_id: '' }
}

// ── Role definitions ──────────────────────────────────────────────────────────
const ALL_ROLES = [
  { value: 'owner',        label: 'Owner',            icon: '👑', levels: ['primary','secondary'] },
  { value: 'accountant',   label: 'Accountant',       icon: '💼', levels: ['primary','secondary'] },
  { value: 'teacher_pri',  label: 'Class Teacher',    icon: '📚', levels: ['primary'] },
  { value: 'teacher_sec',  label: 'Class Teacher',    icon: '📚', levels: ['secondary'] },
  { value: 'academic_pri', label: 'Academic Teacher', icon: '🎓', levels: ['primary'] },
  { value: 'academic_sec', label: 'Academic Teacher', icon: '🎓', levels: ['secondary'] },
  { value: 'head_teacher', label: 'Head Teacher',     icon: '🏅', levels: ['primary'] },
  { value: 'headmaster',   label: 'Headmaster',       icon: '🏫', levels: ['secondary'] },
]

// ── Available roles based on selected school level ────────────────────────────
const selectedSchool = computed(() =>
  schools.value.find(s => s.id == (form.value.school_id || auth.user?.school_id))
)

const availableRoles = computed(() => {
  const level = selectedSchool.value?.level ?? 'primary'
  return ALL_ROLES.filter(r => r.levels.includes(level))
})

// Roles that should NOT appear in staff management (guardians, parents, unassigned)
const EXCLUDED_ROLES = ['parent', 'guardian']

// ── Filtered list ─────────────────────────────────────────────────────────────
const filtered = computed(() => {
  let list = users.value.filter(u => {
    const roleNames = u.roles?.map(r => r.name) ?? []
    // exclude parent/guardian accounts and users with no staff role
    return roleNames.length > 0 && !roleNames.every(r => EXCLUDED_ROLES.includes(r))
  })
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
  }
  if (filterRole.value) {
    list = list.filter(u => u.roles?.some(r => r.name === filterRole.value))
  }
  return list
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function roleLabel(roleName) {
  return ALL_ROLES.find(r => r.value === roleName)?.label ?? roleName
}

function roleIcon(roleName) {
  return ALL_ROLES.find(r => r.value === roleName)?.icon ?? '👤'
}
function roleBadgeColor(roleName) {
  const colors = {
    owner: 'success', accountant: 'info',
    teacher_pri: 'primary', teacher_sec: 'primary',
    academic_pri: 'warning', academic_sec: 'warning',
    head_teacher: 'dark', headmaster: 'danger',
  }
  return colors[roleName] ?? 'secondary'
}
function schoolName(schoolId) {
  return schools.value.find(s => s.id == schoolId)?.name ?? '—'
}

// ── Data loading ──────────────────────────────────────────────────────────────
async function fetchData() {
  loading.value = true
  error.value   = ''
  try {
    const [usersRes, schoolsRes] = await Promise.all([
      api.get('/users'),
      api.get('/schools'),
    ])
    users.value   = usersRes.data.data ?? usersRes.data
    schools.value = schoolsRes.data.data ?? schoolsRes.data
  } catch (e) {
    error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

// ── Create / Edit ──────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  form.value       = resetForm()
  form.value.school_id = auth.user?.school_id ?? ''
  formError.value  = ''
  showForm.value   = true
}

function openEdit(user) {
  editTarget.value = user
  form.value = {
    name:      user.name,
    email:     user.email,
    password:  '',
    phone:     user.phone ?? '',
    role:      user.roles?.[0]?.name ?? '',
    school_id: user.school_id ?? '',
  }
  formError.value = ''
  showForm.value  = true
}

async function saveUser() {
  saving.value    = true
  formError.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.password) delete payload.password

    if (editTarget.value) {
      await api.put(`/users/${editTarget.value.id}`, payload)
      showForm.value = false
      await fetchData()
    } else {
      await api.post('/users', payload)
      showForm.value = false
      router.push('/superadmin/roles')
    }
  } catch (e) {
    const errs = e?.response?.data?.errors
    if (errs) {
      formError.value = Object.values(errs).flat().join(' | ')
    } else {
      formError.value = e?.response?.data?.message || 'Hitilafu wakati wa kuhifadhi'
    }
  } finally {
    saving.value = false
  }
}

// ── School Access (multi-school) ──────────────────────────────────────────────
const showAccessModal  = ref(false)
const accessTarget     = ref(null)   // the User object being managed
const accessData       = ref(null)   // { primary_school_id, has_multi_school, accessible_schools }
const accessLoading    = ref(false)
const accessSaving     = ref(false)
const grantSchoolId    = ref('')

async function openAccess(user) {
  accessTarget.value  = user
  showAccessModal.value = true
  accessLoading.value = true
  accessData.value    = null
  grantSchoolId.value = ''
  try {
    const { data } = await api.get(`/user-school-access/${user.id}`)
    accessData.value = data
  } finally {
    accessLoading.value = false
  }
}

async function grantAccess() {
  if (!grantSchoolId.value) return
  accessSaving.value = true
  try {
    const { data } = await api.post('/user-school-access', {
      user_id: accessTarget.value.id,
      school_id: grantSchoolId.value,
    })
    accessData.value = { ...accessData.value, ...data }
    grantSchoolId.value = ''
    await fetchData()
  } catch (e) {
    alert(e?.response?.data?.message || 'Hitilafu wakati wa kutoa ruhusa')
  } finally {
    accessSaving.value = false
  }
}

async function revokeAccess(schoolId) {
  accessSaving.value = true
  try {
    const { data } = await api.delete(`/user-school-access/${accessTarget.value.id}/${schoolId}`)
    accessData.value = { ...accessData.value, ...data }
    await fetchData()
  } catch (e) {
    alert(e?.response?.data?.message || 'Hitilafu wakati wa kuondoa ruhusa')
  } finally {
    accessSaving.value = false
  }
}

// Schools the user doesn't already have access to (excluding primary)
const grantableSchools = computed(() => {
  if (!accessData.value) return schools.value
  const already = new Set([
    accessData.value.primary_school_id,
    ...accessData.value.accessible_schools.map(s => s.id),
  ])
  return schools.value.filter(s => !already.has(s.id))
})

// ── Delete ────────────────────────────────────────────────────────────────────
const confirmDelete = ref(null)

async function deleteUser() {
  if (!confirmDelete.value) return
  const targetId = confirmDelete.value.id
  confirmDelete.value = null
  try {
    await api.delete(`/users/${targetId}`)
  } catch (e) {
    const status = e?.response?.status
    if (status !== 404 && status !== 204) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kufuta'
    }
  } finally {
    await fetchData()
  }
}
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- Toolbar -->
    <CCard class="border-0 shadow-sm mb-3">
      <CCardBody class="py-2">
        <div class="d-flex align-items-center gap-2 flex-wrap">

          <!-- Role legend badges (can wrap) -->
          <div class="d-flex flex-wrap gap-1 align-items-center flex-grow-1">
            <span v-for="r in ALL_ROLES" :key="r.value"
              class="badge rounded-pill px-2 py-1"
              :class="'bg-' + roleBadgeColor(r.value)"
              style="font-size:.74rem; cursor:default; white-space:nowrap;">
              {{ r.icon }} {{ roleLabel(r.value) }}
              <span v-if="r.levels.length === 1" class="opacity-75 ms-1">
                ({{ r.levels[0] === 'primary' ? t('common.primary') : t('common.secondary') }})
              </span>
            </span>
          </div>

          <!-- Right controls — never wrap -->
          <div class="d-flex align-items-center gap-2 flex-shrink-0">
            <CFormInput
              v-model="search"
              :placeholder="t('staff.searchPlaceholder')"
              size="sm"
              style="width:200px;"
            />
            <CFormSelect v-model="filterRole" size="sm" style="width:160px;">
              <option value="">— {{ t('staff.allRoles') }} —</option>
              <option v-for="r in ALL_ROLES" :key="r.value" :value="r.value">
                {{ r.icon }} {{ roleLabel(r.value) }}
              </option>
            </CFormSelect>
            <CButton color="success" size="sm" @click="openCreate" class="text-nowrap">
              + {{ t('staff.add') }}
            </CButton>
          </div>

        </div>
      </CCardBody>
    </CCard>

    <!-- Error -->
    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-5"><CSpinner /></div>

    <!-- Table -->
    <CCard v-else class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <div class="table-responsive">
          <CTable hover class="align-middle mb-0">
            <CTableHead class="table-dark">
              <CTableRow>
                <CTableHeaderCell>{{ t('common.name') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.email') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.phone') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('staff.role') }}</CTableHeaderCell>
                <CTableHeaderCell>{{ t('common.school') }}</CTableHeaderCell>
                <CTableHeaderCell class="text-center">{{ t('common.actions') }}</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow v-for="u in filtered" :key="u.id">
                <CTableDataCell class="fw-semibold">{{ u.name }}</CTableDataCell>
                <CTableDataCell class="text-muted">{{ u.email }}</CTableDataCell>
                <CTableDataCell>{{ u.phone ?? '—' }}</CTableDataCell>
                <CTableDataCell>
                  <template v-if="u.roles?.length">
                    <CBadge
                      v-for="r in u.roles" :key="r.name"
                      :color="roleBadgeColor(r.name)"
                      class="me-1 px-2 py-1"
                      style="font-size:.78rem;"
                    >
                      {{ roleIcon(r.name) }} {{ roleLabel(r.name) }}
                    </CBadge>
                  </template>
                  <span v-else class="text-muted">—</span>
                </CTableDataCell>
                <CTableDataCell>
                  {{ schoolName(u.school_id) }}
                  <CBadge v-if="u.multi_school_count > 0" color="info" class="ms-1" style="font-size:.7rem;">
                    +{{ u.multi_school_count }} {{ t('staff.extraSchools') }}
                  </CBadge>
                </CTableDataCell>
                <CTableDataCell class="text-center">
                  <div class="d-flex gap-2 justify-content-center">
                    <CButton v-if="auth.isOwner || auth.isSuperAdmin"
                      size="sm" color="info" variant="ghost"
                      :title="t('staff.manageAccess')"
                      @click="openAccess(u)">🏫</CButton>
                    <CButton size="sm" color="warning" variant="ghost" @click="openEdit(u)">✏️</CButton>
                    <CButton size="sm" color="danger" variant="ghost" @click="confirmDelete = u">🗑️</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!filtered.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">
                  {{ t('staff.noResults') }}
                </CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

    <!-- Create / Edit Modal -->
    <CModal :visible="showForm" @close="showForm=false" size="lg" backdrop="static">
      <CModalHeader>
        <CModalTitle>{{ editTarget ? t('staff.edit') : t('staff.add') }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="mb-3">{{ formError }}</CAlert>

        <!-- School selector (superadmin only) -->
        <div v-if="auth.isSuperAdmin" class="mb-3">
          <CFormLabel class="fw-semibold">{{ t('common.school') }} <span class="text-danger">*</span></CFormLabel>
          <CFormSelect v-model="form.school_id" @change="form.role=''">
            <option value="">— {{ t('common.selectSchool') }} —</option>
            <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
          </CFormSelect>
        </div>

        <CRow class="g-3">
          <CCol md="6">
            <CFormLabel class="fw-semibold">{{ t('staff.fullName') }} <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.name" :placeholder="t('staff.fullNamePlaceholder')" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">{{ t('common.phone') }}</CFormLabel>
            <CFormInput v-model="form.phone" placeholder="+255 7xx xxx xxx" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">{{ t('common.email') }} <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.email" type="email" placeholder="email@shule.tz" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">
              {{ t('staff.password') }}
              <span v-if="editTarget" class="text-muted fw-normal">({{ t('staff.passwordHint') }})</span>
              <span v-else class="text-danger">*</span>
            </CFormLabel>
            <CFormInput v-model="form.password" type="password" :placeholder="t('staff.passwordPlaceholder')" />
          </CCol>
          <CCol md="12">
            <CFormLabel class="fw-semibold">{{ t('staff.role') }} <span class="text-danger">*</span></CFormLabel>
            <CRow class="g-2">
              <CCol v-for="r in availableRoles" :key="r.value" xs="6" md="4">
                <div
                  class="border rounded-3 p-3 text-center"
                  style="cursor:pointer; transition:all .2s;"
                  :style="form.role === r.value
                    ? 'border-color:#007f3e !important; background:rgba(0,127,62,.08);'
                    : 'border-color:#dee2e6;'"
                  @click="form.role = r.value"
                >
                  <div class="fs-3 mb-1">{{ r.icon }}</div>
                  <div class="fw-semibold small">{{ roleLabel(r.value) }}</div>
                  <div v-if="r.levels.length === 1" class="text-muted" style="font-size:.7rem;">
                    {{ r.levels[0] === 'primary' ? t('staff.primaryOnly') : t('staff.secondaryOnly') }}
                  </div>
                </div>
              </CCol>
            </CRow>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showForm=false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="saving || !form.name || !form.email || !form.role" @click="saveUser">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editTarget ? t('common.saveChanges') : t('staff.add') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- School Access Modal -->
    <CModal :visible="showAccessModal" @close="showAccessModal=false" backdrop="static">
      <CModalHeader>
        <CModalTitle>🏫 {{ t('staff.manageAccess') }} — {{ accessTarget?.name }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <div v-if="accessLoading" class="text-center py-4"><CSpinner /></div>
        <template v-else-if="accessData">
          <!-- Primary school (always, cannot revoke) -->
          <p class="fw-semibold mb-2">{{ t('staff.primarySchool') }}</p>
          <div class="d-flex align-items-center gap-2 mb-3">
            <CBadge color="success" class="px-3 py-2" style="font-size:.85rem;">
              🏠 {{ schoolName(accessData.primary_school_id) }}
            </CBadge>
            <span class="text-muted small">{{ t('staff.cannotRevoke') }}</span>
          </div>

          <!-- Extra granted schools -->
          <p class="fw-semibold mb-2">{{ t('staff.extraAccess') }}</p>
          <div v-if="accessData.accessible_schools.length === 0" class="text-muted small mb-3">
            {{ t('staff.noExtraSchools') }}
          </div>
          <div v-else class="d-flex flex-wrap gap-2 mb-3">
            <div v-for="s in accessData.accessible_schools" :key="s.id"
              class="d-flex align-items-center gap-2 border rounded px-3 py-1">
              <span>{{ s.name }}</span>
              <CButton size="sm" color="danger" variant="ghost"
                :disabled="accessSaving"
                @click="revokeAccess(s.id)">✕</CButton>
            </div>
          </div>

          <!-- Grant new school -->
          <div v-if="grantableSchools.length > 0">
            <p class="fw-semibold mb-2">{{ t('staff.grantSchool') }}</p>
            <div class="d-flex gap-2">
              <CFormSelect v-model="grantSchoolId" size="sm" class="flex-grow-1">
                <option value="">— {{ t('common.selectSchool') }} —</option>
                <option v-for="s in grantableSchools" :key="s.id" :value="s.id">{{ s.name }}</option>
              </CFormSelect>
              <CButton color="success" size="sm"
                :disabled="!grantSchoolId || accessSaving"
                @click="grantAccess">
                <CSpinner v-if="accessSaving" size="sm" class="me-1" />
                {{ t('staff.grant') }}
              </CButton>
            </div>
          </div>
          <div v-else class="text-muted small mt-2">{{ t('staff.allSchoolsGranted') }}</div>
        </template>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showAccessModal=false">{{ t('common.close') }}</CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete=null" size="sm">
      <CModalHeader><CModalTitle>{{ t('common.confirmDelete') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('common.confirmDeleteMsg', { name: confirmDelete?.name }) }}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="confirmDelete=null">{{ t('common.no') }}</CButton>
        <CButton color="danger" @click="deleteUser">{{ t('common.yesDelete') }}</CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
