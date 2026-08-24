<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

// ── State ─────────────────────────────────────────────────────────────────────
const users       = ref([])
const schools     = ref([])
const allRoles    = ref([])
const allPermGrps = ref({})   // { Module: [perm, ...] }
const loading     = ref(false)
const error       = ref('')

// filters
const filterSearch = ref('')
const filterRole   = ref('')
const filterActive = ref('')

// create / edit
const showForm   = ref(false)
const editTarget = ref(null)
const saving     = ref(false)
const formError  = ref('')
const form       = ref(blankForm())
const formPerms  = ref(new Set())

// deactivate
const showDeactivate   = ref(false)
const deactivateTarget = ref(null)
const deactivateReason = ref('')
const deactivating     = ref(false)

// permissions editor (standalone)
const showPerms     = ref(false)
const permsTarget   = ref(null)
const editorPerms   = ref(new Set())
const savingPerms   = ref(false)

// restrict permissions
const showRestrict    = ref(false)
const restrictTarget  = ref(null)
const restrictedPerms = ref(new Set())   // perms that ARE forbidden
const savingRestrict  = ref(false)

// delete confirm
const confirmDelete = ref(null)

function blankForm() {
  return { name: '', email: '', phone: '', school_id: '', role: '', permissions: [] }
}

// ── Computed ──────────────────────────────────────────────────────────────────
const filtered = computed(() => {
  let list = users.value
  if (filterSearch.value) {
    const q = filterSearch.value.toLowerCase()
    list = list.filter(u => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q))
  }
  if (filterRole.value)   list = list.filter(u => u.roles?.some(r => r.name === filterRole.value))
  if (filterActive.value !== '') list = list.filter(u => String(u.is_active) === filterActive.value)
  return list
})

const totalActive   = computed(() => users.value.filter(u => u.is_active).length)
const totalFrozen   = computed(() => users.value.filter(u => !u.is_active).length)

// ── Load ──────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  error.value   = ''
  try {
    const [usersRes, schoolsRes, rolesRes] = await Promise.all([
      api.get('/superadmin/users'),
      api.get('/schools'),
      api.get('/superadmin/roles'),
    ])
    users.value    = usersRes.data.data ?? usersRes.data
    schools.value  = schoolsRes.data.data ?? schoolsRes.data
    allRoles.value = rolesRes.data.roles ?? []
    allPermGrps.value = rolesRes.data.all_permissions ?? {}
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load data'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ── Create / Edit ──────────────────────────────────────────────────────────────
function openCreate() {
  editTarget.value = null
  form.value       = blankForm()
  formPerms.value  = new Set()
  formError.value  = ''
  showForm.value   = true
}

function openEdit(user) {
  editTarget.value = user
  form.value = {
    name:      user.name,
    email:     user.email,
    phone:     user.phone ?? '',
    school_id: user.school_id ?? '',
    role:      user.roles?.[0]?.name ?? '',
    permissions: [],
  }
  formPerms.value = new Set(user.permissions?.map(p => p.name) ?? [])
  formError.value = ''
  showForm.value  = true
}

async function saveUser() {
  saving.value    = true
  formError.value = ''
  try {
    const payload = {
      ...form.value,
      permissions: [...formPerms.value],
    }
    delete payload.password

    if (editTarget.value) {
      await api.put(`/superadmin/users/${editTarget.value.id}`, payload)
    } else {
      await api.post('/superadmin/users', payload)
    }
    showForm.value = false
    await load()
  } catch (e) {
    const errs = e?.response?.data?.errors
    formError.value = errs
      ? Object.values(errs).flat().join(' | ')
      : (e?.response?.data?.message || 'Failed to save')
  } finally {
    saving.value = false
  }
}

function toggleFormPerm(perm) {
  const s = new Set(formPerms.value)
  s.has(perm) ? s.delete(perm) : s.add(perm)
  formPerms.value = s
}

// ── Deactivate ─────────────────────────────────────────────────────────────────
function openDeactivate(user) {
  deactivateTarget.value = user
  deactivateReason.value = ''
  showDeactivate.value   = true
}

async function doDeactivate() {
  if (!deactivateTarget.value) return
  deactivating.value = true
  try {
    await api.post(`/superadmin/users/${deactivateTarget.value.id}/deactivate`, {
      reason: deactivateReason.value || null,
    })
    showDeactivate.value = false
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to deactivate'
    showDeactivate.value = false
  } finally {
    deactivating.value = false
  }
}

async function doReactivate(user) {
  try {
    await api.post(`/superadmin/users/${user.id}/reactivate`)
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to reactivate'
  }
}

// ── Standalone permissions editor ─────────────────────────────────────────────
function openPermsEditor(user) {
  permsTarget.value = user
  editorPerms.value = new Set(user.permissions?.map(p => p.name) ?? [])
  showPerms.value   = true
}

function toggleEditorPerm(perm) {
  const s = new Set(editorPerms.value)
  s.has(perm) ? s.delete(perm) : s.add(perm)
  editorPerms.value = s
}

function toggleEditorModule(perms) {
  const s   = new Set(editorPerms.value)
  const all = perms.every(p => s.has(p))
  perms.forEach(p => all ? s.delete(p) : s.add(p))
  editorPerms.value = s
}

async function saveEditorPerms() {
  if (!permsTarget.value) return
  savingPerms.value = true
  try {
    await api.put(`/superadmin/users/${permsTarget.value.id}/permissions`, {
      permissions: [...editorPerms.value],
    })
    showPerms.value = false
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save permissions'
  } finally {
    savingPerms.value = false
  }
}

// ── Restrict Permissions ──────────────────────────────────────────────────────
function openRestrict(user) {
  restrictTarget.value  = user
  restrictedPerms.value = new Set(user.forbidden_permissions ?? [])
  showRestrict.value    = true
}

function toggleRestrict(perm) {
  const s = new Set(restrictedPerms.value)
  s.has(perm) ? s.delete(perm) : s.add(perm)
  restrictedPerms.value = s
}

function toggleRestrictModule(perms) {
  const s   = new Set(restrictedPerms.value)
  const all = perms.every(p => s.has(p))
  perms.forEach(p => all ? s.delete(p) : s.add(p))
  restrictedPerms.value = s
}

async function saveRestrict() {
  if (!restrictTarget.value) return
  savingRestrict.value = true
  try {
    await api.put(`/superadmin/users/${restrictTarget.value.id}/restrict`, {
      forbidden_permissions: [...restrictedPerms.value],
    })
    showRestrict.value = false
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save restrictions'
    showRestrict.value = false
  } finally {
    savingRestrict.value = false
  }
}

// All permissions a user effectively has (role + direct), used as restrict candidates
function userAllPerms(user) {
  const set = new Set()
  const rolePermsMap = allPermGrps.value
  const role = user.roles?.[0]?.name
  if (role) {
    Object.values(rolePermsMap).flat().forEach(p => set.add(p))
  }
  user.permissions?.forEach(p => set.add(p.name))
  return set
}

// ── Delete ────────────────────────────────────────────────────────────────────
async function doDelete() {
  if (!confirmDelete.value) return
  try {
    await api.delete(`/superadmin/users/${confirmDelete.value.id}`)
    confirmDelete.value = null
    await load()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to delete'
    confirmDelete.value = null
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function roleBadgeColor(name) {
  const m = { superadmin:'danger', owner:'success', accountant:'info',
    teacher:'primary', head_teacher:'primary', headmaster:'dark',
    academic_teacher:'warning', parent:'secondary' }
  return m[name] ?? 'secondary'
}
function schoolName(id) { return schools.value.find(s => s.id == id)?.name ?? '—' }
function moduleActive(perms, set) { return perms.filter(p => set.has(p)).length }
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0 fw-bold">👤 User Management</h4>
        <p class="text-muted small mb-0">Create users, assign roles & permissions, freeze/unfreeze accounts</p>
      </div>
      <CButton color="success" @click="openCreate" style="min-height:44px;">+ Add User</CButton>
    </div>

    <!-- Summary cards -->
    <CRow class="g-3 mb-3">
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm">
          <CCardBody class="p-3 text-center">
            <div class="fw-bold fs-4">{{ users.length }}</div>
            <div class="text-muted small">Total Users</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm" style="border-left:3px solid #198754 !important;">
          <CCardBody class="p-3 text-center">
            <div class="fw-bold fs-4 text-success">{{ totalActive }}</div>
            <div class="text-muted small">Active</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm" style="border-left:3px solid #dc3545 !important;">
          <CCardBody class="p-3 text-center">
            <div class="fw-bold fs-4 text-danger">{{ totalFrozen }}</div>
            <div class="text-muted small">Frozen</div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol xs="6" md="3">
        <CCard class="border-0 shadow-sm">
          <CCardBody class="p-3 text-center">
            <div class="fw-bold fs-4">{{ schools.length }}</div>
            <div class="text-muted small">Schools</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <!-- Filters -->
    <CRow class="g-2 mb-3">
      <CCol md="4">
        <CFormInput v-model="filterSearch" placeholder="Search by name or email..." />
      </CCol>
      <CCol md="3">
        <CFormSelect v-model="filterRole">
          <option value="">All Roles</option>
          <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
        </CFormSelect>
      </CCol>
      <CCol md="3">
        <CFormSelect v-model="filterActive">
          <option value="">All Status</option>
          <option value="true">Active</option>
          <option value="false">Frozen</option>
        </CFormSelect>
      </CCol>
    </CRow>

    <CAlert v-if="error" color="danger" dismissible @close="error=''">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-5"><CSpinner /></div>

    <!-- Table -->
    <CCard v-else class="border-0 shadow-sm">
      <CCardBody class="p-0">
        <div class="table-responsive">
          <CTable hover class="align-middle mb-0">
            <CTableHead class="table-dark">
              <CTableRow>
                <CTableHeaderCell>User</CTableHeaderCell>
                <CTableHeaderCell>Role</CTableHeaderCell>
                <CTableHeaderCell>School</CTableHeaderCell>
                <CTableHeaderCell class="text-center">Permissions</CTableHeaderCell>
                <CTableHeaderCell class="text-center">Status</CTableHeaderCell>
                <CTableHeaderCell class="text-center">Actions</CTableHeaderCell>
              </CTableRow>
            </CTableHead>
            <CTableBody>
              <CTableRow
                v-for="u in filtered" :key="u.id"
                :style="!u.is_active ? 'opacity:.65; background:rgba(220,53,69,.04);' : ''"
              >
                <CTableDataCell>
                  <div class="fw-semibold">{{ u.name }}</div>
                  <div class="text-muted small">{{ u.email }}</div>
                  <div v-if="u.phone" class="text-muted" style="font-size:.75rem;">{{ u.phone }}</div>
                </CTableDataCell>
                <CTableDataCell>
                  <CBadge
                    v-for="r in u.roles" :key="r.name"
                    :color="roleBadgeColor(r.name)"
                    class="me-1"
                  >{{ r.name }}</CBadge>
                  <span v-if="!u.roles?.length" class="text-muted">—</span>
                </CTableDataCell>
                <CTableDataCell>{{ schoolName(u.school_id) }}</CTableDataCell>
                <CTableDataCell class="text-center">
                  <CButton size="sm" color="primary" variant="ghost" @click="openPermsEditor(u)">
                    🔑 {{ u.permissions?.length ?? 0 }}
                  </CButton>
                  <div v-if="u.forbidden_permissions?.length" class="mt-1">
                    <CBadge color="danger" style="font-size:.68rem;">
                      🚫 {{ u.forbidden_permissions.length }} restricted
                    </CBadge>
                  </div>
                </CTableDataCell>
                <CTableDataCell class="text-center">
                  <CBadge :color="u.is_active ? 'success' : 'danger'" class="px-3 py-1">
                    {{ u.is_active ? '✓ Active' : '🔒 Frozen' }}
                  </CBadge>
                  <div v-if="!u.is_active && u.deactivation_reason" class="text-muted mt-1" style="font-size:.7rem; max-width:120px; margin:0 auto; white-space:normal;">
                    {{ u.deactivation_reason }}
                  </div>
                </CTableDataCell>
                <CTableDataCell>
                  <div class="d-flex gap-1 justify-content-center flex-wrap">
                    <CButton size="sm" color="warning" variant="ghost" title="Edit" @click="openEdit(u)">✏️</CButton>
                    <CButton
                      v-if="u.is_active && !u.roles?.some(r=>r.name==='superadmin')"
                      size="sm" color="danger" variant="ghost"
                      title="Freeze account"
                      @click="openDeactivate(u)"
                    >🔒</CButton>
                    <CButton
                      v-if="!u.is_active"
                      size="sm" color="success" variant="ghost"
                      title="Unfreeze account"
                      @click="doReactivate(u)"
                    >🔓</CButton>
                    <CButton
                      v-if="!u.roles?.some(r=>r.name==='superadmin')"
                      size="sm" color="warning" variant="ghost"
                      title="Restrict permissions"
                      @click="openRestrict(u)"
                    >🚫</CButton>
                    <CButton
                      v-if="!u.roles?.some(r=>r.name==='superadmin')"
                      size="sm" color="danger" variant="ghost"
                      title="Delete user"
                      @click="confirmDelete = u"
                    >🗑️</CButton>
                  </div>
                </CTableDataCell>
              </CTableRow>
              <CTableRow v-if="!filtered.length">
                <CTableDataCell colspan="6" class="text-center text-muted py-4">No users found</CTableDataCell>
              </CTableRow>
            </CTableBody>
          </CTable>
        </div>
      </CCardBody>
    </CCard>

    <!-- ── Create / Edit User Modal ─────────────────────────────────────────── -->
    <CModal :visible="showForm" @close="showForm=false" size="xl" backdrop="static" scrollable>
      <CModalHeader>
        <CModalTitle>{{ editTarget ? `Edit: ${editTarget.name}` : 'Add New User' }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert v-if="formError" color="danger" class="small py-2">{{ formError }}</CAlert>

        <CRow class="g-3 mb-3">
          <CCol md="6">
            <CFormLabel class="fw-semibold">Full Name <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.name" placeholder="Full name" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">Phone</CFormLabel>
            <CFormInput v-model="form.phone" placeholder="+255 7xx xxx xxx" />
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">Email <span class="text-danger">*</span></CFormLabel>
            <CFormInput v-model="form.email" type="email" />
          </CCol>
          <CCol v-if="!editTarget" md="6">
            <CFormLabel class="fw-semibold">Password</CFormLabel>
            <CFormInput value="SCHOOL" type="text" readonly class="bg-light text-muted fw-bold" />
            <div class="text-muted small mt-1">User will be prompted to change this on first login</div>
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">School</CFormLabel>
            <CFormSelect v-model="form.school_id">
              <option value="">— No school —</option>
              <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <CFormLabel class="fw-semibold">Role <span class="text-danger">*</span></CFormLabel>
            <CFormSelect v-model="form.role">
              <option value="">— Select role —</option>
              <option v-for="r in allRoles" :key="r.id" :value="r.name">{{ r.name }}</option>
            </CFormSelect>
          </CCol>
        </CRow>

        <!-- Direct permissions (optional overrides) -->
        <div class="fw-semibold mb-2">Direct Permissions
          <span class="text-muted fw-normal small ms-1">(optional — overrides role defaults)</span>
        </div>
        <CRow class="g-2">
          <CCol v-for="(perms, module) in allPermGrps" :key="module" xs="12" sm="6" lg="4">
            <div class="border rounded-3 overflow-hidden">
              <div
                class="d-flex align-items-center justify-content-between px-3 py-2 fw-semibold small"
                style="cursor:pointer; background:#f8f9fa;"
                :style="moduleActive(perms, formPerms) === perms.length ? 'background:#e9f5ee; color:#007f3e;' : ''"
                @click="perms.forEach(p => { const s=new Set(formPerms); const all=perms.every(x=>s.has(x)); perms.forEach(x=>all?s.delete(x):s.add(x)); formPerms=s })"
              >
                {{ module }}
                <span class="text-muted" style="font-size:.72rem;">{{ moduleActive(perms, formPerms) }}/{{ perms.length }}</span>
              </div>
              <div class="p-2">
                <div
                  v-for="perm in perms" :key="perm"
                  class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                  style="cursor:pointer; font-size:.8rem;"
                  :style="formPerms.has(perm) ? 'background:rgba(0,127,62,.08);' : ''"
                  @click="toggleFormPerm(perm)"
                >
                  <div class="rounded-circle flex-shrink-0"
                    style="width:14px;height:14px;border:2px solid #dee2e6;transition:all .15s;"
                    :style="formPerms.has(perm) ? 'background:#007f3e;border-color:#007f3e;' : ''"
                  />
                  <span :class="formPerms.has(perm) ? 'fw-semibold text-dark' : 'text-muted'">
                    {{ perm.split('.')[1]?.replace(/_/g,' ') }}
                  </span>
                </div>
              </div>
            </div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showForm=false">Cancel</CButton>
        <CButton color="success" :disabled="saving || !form.name || !form.email || !form.role" @click="saveUser">
          <CSpinner v-if="saving" size="sm" class="me-1" />
          {{ editTarget ? 'Save Changes' : 'Create User' }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Freeze / Deactivate Modal ─────────────────────────────────────────── -->
    <CModal :visible="showDeactivate" @close="showDeactivate=false" size="sm" backdrop="static">
      <CModalHeader style="border-bottom:2px solid #dc3545;">
        <CModalTitle class="text-danger">🔒 Freeze Account</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <p>You are about to <strong>freeze</strong> the account of <strong>{{ deactivateTarget?.name }}</strong>.</p>
        <p class="text-danger small mb-3">
          ⚠ All active sessions and tokens will be revoked immediately. The user will not be able to log in until reactivated.
        </p>
        <CFormLabel class="fw-semibold">Reason <span class="text-muted fw-normal">(optional)</span></CFormLabel>
        <CFormTextarea v-model="deactivateReason" rows="3" placeholder="e.g. Suspended pending investigation" />
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showDeactivate=false">Cancel</CButton>
        <CButton color="danger" :disabled="deactivating" @click="doDeactivate">
          <CSpinner v-if="deactivating" size="sm" class="me-1" />
          Freeze Account
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Permissions Editor Modal ───────────────────────────────────────────── -->
    <CModal :visible="showPerms" @close="showPerms=false" size="xl" scrollable>
      <CModalHeader>
        <CModalTitle>🔑 Permissions — {{ permsTarget?.name }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <p class="text-muted small mb-3">
          Direct permissions are <em>added on top of</em> the user's role permissions.
          Currently <strong>{{ editorPerms.size }}</strong> direct permission(s) assigned.
        </p>
        <CRow class="g-2">
          <CCol v-for="(perms, module) in allPermGrps" :key="module" xs="12" sm="6" lg="4">
            <div class="border rounded-3 overflow-hidden">
              <div
                class="d-flex align-items-center justify-content-between px-3 py-2 fw-semibold small"
                style="cursor:pointer;"
                :style="moduleActive(perms, editorPerms) === perms.length
                  ? 'background:#007f3e;color:#fff;'
                  : moduleActive(perms, editorPerms) > 0
                    ? 'background:#e9f5ee;color:#007f3e;'
                    : 'background:#f8f9fa;'"
                @click="toggleEditorModule(perms)"
              >
                {{ module }}
                <span style="font-size:.72rem;">{{ moduleActive(perms, editorPerms) }}/{{ perms.length }}</span>
              </div>
              <div class="p-2">
                <div
                  v-for="perm in perms" :key="perm"
                  class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                  style="cursor:pointer;font-size:.8rem;"
                  :style="editorPerms.has(perm) ? 'background:rgba(0,127,62,.08);' : ''"
                  @click="toggleEditorPerm(perm)"
                >
                  <div class="rounded-circle flex-shrink-0"
                    style="width:14px;height:14px;border:2px solid #dee2e6;"
                    :style="editorPerms.has(perm) ? 'background:#007f3e;border-color:#007f3e;' : ''"
                  />
                  <span :class="editorPerms.has(perm) ? 'fw-semibold text-dark' : 'text-muted'">
                    {{ perm.split('.')[1]?.replace(/_/g,' ') }}
                  </span>
                </div>
              </div>
            </div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showPerms=false">Cancel</CButton>
        <CButton color="success" :disabled="savingPerms" @click="saveEditorPerms">
          <CSpinner v-if="savingPerms" size="sm" class="me-1" />
          Save Permissions
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Restrict Permissions Modal ──────────────────────────────────────────── -->
    <CModal :visible="showRestrict" @close="showRestrict=false" size="xl" scrollable backdrop="static">
      <CModalHeader style="border-bottom:2px solid #dc3545;">
        <CModalTitle>🚫 Restrict Permissions — {{ restrictTarget?.name }}</CModalTitle>
      </CModalHeader>
      <CModalBody>
        <CAlert color="warning" class="small py-2 mb-3">
          <strong>Warning:</strong> Toggling a permission here <em>blocks</em> the user from using it,
          even if their role normally grants it. Superadmin accounts cannot be restricted.
          Currently <strong>{{ restrictedPerms.size }}</strong> permission(s) blocked.
        </CAlert>
        <CRow class="g-2">
          <CCol v-for="(perms, module) in allPermGrps" :key="module" xs="12" sm="6" lg="4">
            <div class="border rounded-3 overflow-hidden">
              <div
                class="d-flex align-items-center justify-content-between px-3 py-2 fw-semibold small"
                style="cursor:pointer;"
                :style="perms.every(p => restrictedPerms.has(p))
                  ? 'background:#dc3545;color:#fff;'
                  : perms.some(p => restrictedPerms.has(p))
                    ? 'background:#fff3f3;color:#dc3545;'
                    : 'background:#f8f9fa;'"
                @click="toggleRestrictModule(perms)"
              >
                {{ module }}
                <span style="font-size:.72rem;">
                  {{ perms.filter(p => restrictedPerms.has(p)).length }}/{{ perms.length }} blocked
                </span>
              </div>
              <div class="p-2">
                <div
                  v-for="perm in perms" :key="perm"
                  class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                  style="cursor:pointer;font-size:.8rem;"
                  :style="restrictedPerms.has(perm) ? 'background:rgba(220,53,69,.08);' : ''"
                  @click="toggleRestrict(perm)"
                >
                  <div class="rounded-circle flex-shrink-0"
                    style="width:14px;height:14px;border:2px solid #dee2e6;transition:all .15s;"
                    :style="restrictedPerms.has(perm) ? 'background:#dc3545;border-color:#dc3545;' : ''"
                  />
                  <span :class="restrictedPerms.has(perm) ? 'fw-semibold text-danger' : 'text-muted'">
                    {{ perm.split('.')[1]?.replace(/_/g,' ') ?? perm }}
                  </span>
                  <CBadge v-if="restrictedPerms.has(perm)" color="danger" class="ms-auto" style="font-size:.65rem;">blocked</CBadge>
                </div>
              </div>
            </div>
          </CCol>
        </CRow>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showRestrict=false">Cancel</CButton>
        <CButton color="danger" :disabled="savingRestrict" @click="saveRestrict">
          <CSpinner v-if="savingRestrict" size="sm" class="me-1" />
          Apply Restrictions
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- ── Delete Confirm Modal ───────────────────────────────────────────────── -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete=null" size="sm">
      <CModalHeader><CModalTitle>Delete User</CModalTitle></CModalHeader>
      <CModalBody>
        Permanently delete <strong>{{ confirmDelete?.name }}</strong>?
        All their tokens and data associations will be removed. This cannot be undone.
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="confirmDelete=null">Cancel</CButton>
        <CButton color="danger" @click="doDelete">Delete Permanently</CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
