<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

// ── State ─────────────────────────────────────────────────────────────────────
const roles          = ref([])
const allPermissions = ref({})   // { Module: [perm, ...] }
const loading        = ref(false)
const error          = ref('')

// selected role for permission editing
const activeRole     = ref(null)
const activePerms    = ref(new Set())
const savingPerms    = ref(false)
const permSaved      = ref(false)

// users in the selected role
const roleUsers         = ref([])
const roleUsersLoading  = ref(false)

// per-user permission override modal
const userPermModal     = ref(false)
const selectedUser      = ref(null)
const userPerms             = ref(new Set())   // direct permissions on this user
const userPermsBase         = ref(new Set())   // permissions inherited from role
const userForbidden         = ref(new Set())   // role-granted perms explicitly denied for this user
const userForbiddenSnapshot = ref(new Set())
const savingUserPerms   = ref(false)
const userPermSaved     = ref(false)
const userPermError     = ref('')

// multi-school access panel (inside user modal)
const allSchools           = ref([])        // all active schools from API
const userGrantedSchools   = ref([])        // schools this user can already access
const schoolAccessLoading  = ref(false)
const schoolGranting       = ref(null)      // schoolId currently being saved
// Always show the school panel — let superadmin manage access directly
const showSchoolPanel = computed(() => true)

// create role form
const showCreate     = ref(false)
const newRoleName    = ref('')
const creating       = ref(false)
const createError    = ref('')

// delete confirm
const confirmDelete  = ref(null)

// ── Load ──────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await api.get('/superadmin/roles')
    roles.value          = data.roles
    allPermissions.value = data.all_permissions
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load roles'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ── Select role ───────────────────────────────────────────────────────────────
async function selectRole(role) {
  activeRole.value  = role
  activePerms.value = new Set(role.permissions)
  permSaved.value   = false
  await loadRoleUsers(role.name)
}

async function loadRoleUsers(roleName) {
  roleUsersLoading.value = true
  roleUsers.value = []
  try {
    const { data } = await api.get('/superadmin/users', { params: { role: roleName, per_page: 100 } })
    roleUsers.value = data.data ?? data
  } catch {
    roleUsers.value = []
  } finally {
    roleUsersLoading.value = false
  }
}

// ── Role permission toggles ───────────────────────────────────────────────────
function togglePerm(perm) {
  const s = new Set(activePerms.value)
  s.has(perm) ? s.delete(perm) : s.add(perm)
  activePerms.value = s
  permSaved.value   = false
}

function toggleModule(perms) {
  const s   = new Set(activePerms.value)
  const all = perms.every(p => s.has(p))
  perms.forEach(p => all ? s.delete(p) : s.add(p))
  activePerms.value = s
  permSaved.value   = false
}

function moduleState(perms) {
  const count = perms.filter(p => activePerms.value.has(p)).length
  if (count === 0) return 'none'
  if (count === perms.length) return 'all'
  return 'partial'
}

async function savePermissions() {
  if (!activeRole.value) return
  savingPerms.value = true
  try {
    const { data } = await api.put(`/superadmin/roles/${activeRole.value.id}/permissions`, {
      permissions: [...activePerms.value],
    })
    const idx = roles.value.findIndex(r => r.id === activeRole.value.id)
    if (idx >= 0) roles.value[idx].permissions = data.permissions
    activeRole.value = { ...activeRole.value, permissions: data.permissions }
    permSaved.value  = true
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to save permissions'
  } finally {
    savingPerms.value = false
  }
}

// ── Per-user permission modal ─────────────────────────────────────────────────
async function openUserPerms(user) {
  selectedUser.value        = user
  userPermError.value       = ''
  userPermSaved.value       = false
  userGrantedSchools.value  = []
  allSchools.value          = []

  userPermsBase.value = new Set(activeRole.value?.permissions ?? [])

  const directNames  = (user.permissions ?? []).map(p => typeof p === 'string' ? p : p.name)
  const forbidNames  = (user.forbidden_permissions ?? [])

  userPerms.value             = new Set(directNames)
  userForbidden.value         = new Set(forbidNames)
  userPermsSnapshot.value     = new Set(directNames)
  userForbiddenSnapshot.value = new Set(forbidNames)

  userPermModal.value = true
  loadSchoolAccessData(user.id)
}

async function loadSchoolAccessData(userId) {
  schoolAccessLoading.value = true
  try {
    const [schoolsRes, accessRes] = await Promise.all([
      api.get('/auth/schools'),
      api.get(`/user-school-access/${userId}`),
    ])
    allSchools.value = schoolsRes.data
    userGrantedSchools.value = accessRes.data.accessible_schools.map(s => s.id)
  } catch {
    allSchools.value = []
    userGrantedSchools.value = []
  } finally {
    schoolAccessLoading.value = false
  }
}

async function toggleSchoolAccess(school) {
  if (!selectedUser.value) return
  const alreadyGranted = userGrantedSchools.value.includes(school.id)
  schoolGranting.value = school.id
  try {
    if (alreadyGranted) {
      await api.delete(`/user-school-access/${selectedUser.value.id}/${school.id}`)
      userGrantedSchools.value = userGrantedSchools.value.filter(id => id !== school.id)
      // If no schools remain, the backend auto-revokes multi_school — reflect in UI
      if (userGrantedSchools.value.length === 0) {
        userPerms.value = new Set([...userPerms.value].filter(p => p !== 'multi_school'))
      }
    } else {
      const { data } = await api.post('/user-school-access', {
        user_id: selectedUser.value.id,
        school_id: school.id,
      })
      userGrantedSchools.value = data.accessible_schools.map(s => s.id)
      // Backend auto-grants multi_school — reflect in UI
      if (!userPermsBase.value.has('multi_school')) {
        userPerms.value = new Set([...userPerms.value, 'multi_school'])
      }
    }
  } catch (e) {
    userPermError.value = e?.response?.data?.message || 'Failed to update school access'
  } finally {
    schoolGranting.value = null
  }
}

function userHasPerm(perm) {
  if (userForbidden.value.has(perm)) return false
  return userPermsBase.value.has(perm) || userPerms.value.has(perm)
}

// Four states: 'role' | 'direct' | 'forbidden' | 'none'
function permSource(perm) {
  if (userForbidden.value.has(perm))   return 'forbidden'
  if (userPermsBase.value.has(perm))   return 'role'
  if (userPerms.value.has(perm))       return 'direct'
  return 'none'
}

function toggleUserPerm(perm) {
  const source = permSource(perm)
  if (source === 'role') {
    userForbidden.value = new Set([...userForbidden.value, perm])
  } else if (source === 'forbidden') {
    userForbidden.value = new Set([...userForbidden.value].filter(p => p !== perm))
  } else if (source === 'direct') {
    userPerms.value = new Set([...userPerms.value].filter(p => p !== perm))
    if (perm === 'multi_school' && allSchools.value.length === 0) {
      loadSchoolAccessData(selectedUser.value.id)
    }
  } else {
    userPerms.value = new Set([...userPerms.value, perm])
    if (perm === 'multi_school' && allSchools.value.length === 0) {
      loadSchoolAccessData(selectedUser.value.id)
    }
  }
  userPermSaved.value = false
}

function userModuleState(perms) {
  const active = perms.filter(p => userHasPerm(p)).length
  const forbidden = perms.filter(p => userForbidden.value.has(p)).length
  if (forbidden > 0 && forbidden === perms.length) return 'all-forbidden'
  if (forbidden > 0) return 'partial-forbidden'
  if (active === 0) return 'none'
  if (active === perms.length) return 'all'
  return 'partial'
}

function toggleUserModule(perms) {
  const anyForbidden = perms.some(p => userForbidden.value.has(p))
  if (anyForbidden) {
    userForbidden.value = new Set([...userForbidden.value].filter(p => !perms.includes(p)))
    userPermSaved.value = false
    return
  }
  const allActive = perms.every(p => userHasPerm(p))
  if (allActive) {
    const newForbid = new Set(userForbidden.value)
    const newDirect = new Set(userPerms.value)
    perms.forEach(p => {
      if (userPermsBase.value.has(p)) newForbid.add(p)
      else newDirect.delete(p)
    })
    userForbidden.value = newForbid
    userPerms.value = newDirect
  } else {
    const newForbid = new Set([...userForbidden.value].filter(p => !perms.includes(p)))
    const newDirect = new Set(userPerms.value)
    perms.forEach(p => { if (!userPermsBase.value.has(p)) newDirect.add(p) })
    userForbidden.value = newForbid
    userPerms.value = newDirect
  }
  userPermSaved.value = false
}

function resetToRoleDefaults() {
  userPerms.value     = new Set()
  userForbidden.value = new Set()
  userPermSaved.value = false
}

// Snapshot of direct perms at modal open — used for dirty-detection
const userPermsSnapshot = ref(new Set())

const userPermsChanged = computed(() => {
  const curPerms   = [...userPerms.value].sort().join(',')
  const origPerms  = [...userPermsSnapshot.value].sort().join(',')
  const curForbid  = [...userForbidden.value].sort().join(',')
  const origForbid = [...userForbiddenSnapshot.value].sort().join(',')
  return curPerms !== origPerms || curForbid !== origForbid
})

// Only the truly direct (non-role-inherited) perms — what we actually send
const directPermsToSave = computed(() => [...userPerms.value].filter(p => !userPermsBase.value.has(p)))

async function saveUserPermissions() {
  if (!selectedUser.value) return
  if (schoolGranting.value || schoolAccessLoading.value) {
    userPermError.value = 'Subiri operesheni ya shule ikamilike kwanza.'
    return
  }
  if (!userPermsChanged.value) return

  savingUserPerms.value = true
  userPermError.value   = ''
  try {
    const directToSave = [...userPerms.value].filter(p => !userPermsBase.value.has(p))
    const forbidToSave = [...userForbidden.value]

    // Conflict check: cannot grant and deny the same perm
    const conflict = directToSave.filter(p => forbidToSave.includes(p))
    if (conflict.length) {
      userPermError.value = `Mgongano: ruhusa moja haiwezi kupewa na kukatazwa kwa wakati mmoja: ${conflict.join(', ')}`
      return
    }

    const removingMultiSchool =
      ((userPermsSnapshot.value.has('multi_school') || userPermsBase.value.has('multi_school'))
        && !directToSave.includes('multi_school')
        && !userPermsBase.value.has('multi_school'))
      || (userPermsBase.value.has('multi_school') && forbidToSave.includes('multi_school'))

    const { data } = await api.put(`/superadmin/users/${selectedUser.value.id}/permissions`, {
      permissions: directToSave,
      forbidden_permissions: forbidToSave,
    })

    if (removingMultiSchool) userGrantedSchools.value = []

    const freshDirect = (data.permissions ?? []).map(p => typeof p === 'string' ? p : p.name)
    const freshForbid = data.forbidden_permissions ?? []

    const idx = roleUsers.value.findIndex(u => u.id === selectedUser.value.id)
    if (idx >= 0) {
      roleUsers.value[idx].permissions           = data.permissions
      roleUsers.value[idx].forbidden_permissions = freshForbid
    }
    selectedUser.value = { ...selectedUser.value, permissions: data.permissions, forbidden_permissions: freshForbid }

    userPerms.value             = new Set(freshDirect)
    userForbidden.value         = new Set(freshForbid)
    userPermsSnapshot.value     = new Set(freshDirect)
    userForbiddenSnapshot.value = new Set(freshForbid)

    userPermSaved.value = true
  } catch (e) {
    userPermError.value = e?.response?.data?.message
      || e?.response?.data?.errors?.permissions?.[0]
      || 'Imeshindwa kuhifadhi. Jaribu tena.'
  } finally {
    savingUserPerms.value = false
  }
}

const userExtraCount     = computed(() => directPermsToSave.value.length)
const userForbiddenCount = computed(() => userForbidden.value.size)

const allPermsFlat = computed(() => Object.values(allPermissions.value).flat())
const activePermCount = computed(() => activePerms.value.size)
const totalPermCount  = computed(() => allPermsFlat.value.length)

// Put 'Access' (multi_school) first, rest alphabetical
const sortedPermissions = computed(() => {
  const entries = Object.entries(allPermissions.value)
  const access  = entries.filter(([mod]) => mod === 'Access')
  const rest    = entries.filter(([mod]) => mod !== 'Access').sort(([a], [b]) => a.localeCompare(b))
  return [...access, ...rest]
})

// Multi-school users across all loaded role users
const multiSchoolUsers = computed(() =>
  roleUsers.value.filter(u =>
    (u.permissions ?? []).some(p => (typeof p === 'string' ? p : p.name) === 'multi_school') ||
    (activeRole.value?.permissions ?? []).includes('multi_school')
  )
)

// ── Create role ───────────────────────────────────────────────────────────────
async function createRole() {
  createError.value = ''
  if (!newRoleName.value.trim()) { createError.value = 'Role name is required'; return }
  const slug = newRoleName.value.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z_]/g, '')
  if (!slug) { createError.value = 'Use only letters and underscores'; return }

  creating.value = true
  try {
    const { data } = await api.post('/superadmin/roles', { name: slug })
    roles.value.push(data)
    showCreate.value  = false
    newRoleName.value = ''
    selectRole(data)
  } catch (e) {
    const errs = e?.response?.data?.errors?.name
    createError.value = errs ? errs[0] : (e?.response?.data?.message || 'Failed to create role')
  } finally {
    creating.value = false
  }
}

// ── Delete role ───────────────────────────────────────────────────────────────
async function deleteRole() {
  if (!confirmDelete.value) return
  try {
    await api.delete(`/superadmin/roles/${confirmDelete.value.id}`)
    if (activeRole.value?.id === confirmDelete.value.id) { activeRole.value = null; roleUsers.value = [] }
    roles.value = roles.value.filter(r => r.id !== confirmDelete.value.id)
    confirmDelete.value = null
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to delete role'
    confirmDelete.value = null
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const SYSTEM_ROLES = ['superadmin', 'owner', 'accountant', 'parent',
                      'teacher', 'head_teacher', 'headmaster', 'academic_teacher']

function isSystem(role) { return SYSTEM_ROLES.includes(role.name) }

function roleBadgeColor(name) {
  const map = {
    superadmin: 'danger', owner: 'success', accountant: 'info',
    teacher: 'primary', head_teacher: 'primary', headmaster: 'dark',
    academic_teacher: 'warning', parent: 'secondary',
  }
  return map[name] ?? 'secondary'
}

function permCount(role) { return role.permissions?.length ?? 0 }

function userDirectCount(user) {
  return (user.permissions ?? []).length
}

function initials(name) {
  return (name || '?').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase()
}
</script>

<template>
  <CContainer fluid class="pt-2 pb-3">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-end mb-3">
      <CButton color="success" @click="showCreate = true; newRoleName = ''; createError = ''">
        + Create Role
      </CButton>
    </div>

    <CAlert v-if="error" color="danger" dismissible @close="error = ''">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-5"><CSpinner /></div>

    <CRow v-else class="g-3">

      <!-- Left: Roles list + Users in role -->
      <CCol md="4" lg="3">

        <!-- Roles list -->
        <CCard class="border-0 shadow-sm mb-3">
          <CCardHeader class="fw-bold bg-transparent border-bottom">
            Roles <CBadge color="secondary" class="ms-1">{{ roles.length }}</CBadge>
          </CCardHeader>
          <CCardBody class="p-0">
            <div
              v-for="role in roles" :key="role.id"
              class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
              style="cursor:pointer; transition:background .15s;"
              :style="activeRole?.id === role.id
                ? 'background:rgba(0,127,62,.08); border-left:3px solid #007f3e;'
                : 'border-left:3px solid transparent;'"
              @click="selectRole(role)"
            >
              <div>
                <div class="fw-semibold small">{{ role.name }}</div>
                <div class="text-muted" style="font-size:.72rem;">
                  {{ permCount(role) }} permission{{ permCount(role) !== 1 ? 's' : '' }}
                  <span v-if="isSystem(role)" class="ms-1 text-warning">⚙ system</span>
                </div>
              </div>
              <div class="d-flex align-items-center gap-1">
                <CBadge :color="roleBadgeColor(role.name)" class="px-2" style="font-size:.7rem;">
                  {{ role.name }}
                </CBadge>
                <CButton
                  v-if="!isSystem(role)"
                  size="sm" color="danger" variant="ghost"
                  style="padding:2px 6px;"
                  @click.stop="confirmDelete = role"
                >✕</CButton>
              </div>
            </div>
            <div v-if="!roles.length" class="text-center text-muted py-4 small">No roles found</div>
          </CCardBody>
        </CCard>

        <!-- Users in selected role -->
        <CCard v-if="activeRole" class="border-0 shadow-sm">
          <CCardHeader class="bg-transparent border-bottom d-flex align-items-center justify-content-between">
            <span class="fw-bold small">Users with <code>{{ activeRole.name }}</code></span>
            <CSpinner v-if="roleUsersLoading" size="sm" />
            <CBadge v-else color="secondary">{{ roleUsers.length }}</CBadge>
          </CCardHeader>
          <CCardBody class="p-0" style="max-height:340px; overflow-y:auto;">
            <div v-if="!roleUsersLoading && !roleUsers.length" class="text-center text-muted py-3 small">
              No users with this role
            </div>
            <div
              v-for="u in roleUsers" :key="u.id"
              class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom"
            >
              <div class="d-flex align-items-center gap-2">
                <!-- Avatar -->
                <div
                  class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                  style="width:32px; height:32px; background:#e9f5ee; color:#007f3e; font-size:.7rem; font-weight:700;"
                >{{ initials(u.name) }}</div>
                <div>
                  <div class="fw-semibold" style="font-size:.82rem;">{{ u.name }}</div>
                  <div class="text-muted" style="font-size:.7rem;">
                    {{ u.school?.name ?? 'No school' }}
                    <span v-if="userDirectCount(u) > 0" class="ms-1 text-success">
                      +{{ userDirectCount(u) }} extra
                    </span>
                  </div>
                </div>
              </div>
              <CButton
                size="sm" color="primary" variant="outline"
                style="font-size:.7rem; padding:2px 8px; white-space:nowrap;"
                @click="openUserPerms(u)"
              >
                Manage
              </CButton>
            </div>
          </CCardBody>
        </CCard>

      </CCol>

      <!-- Right: Role Permissions editor -->
      <CCol md="8" lg="9">
        <CCard class="border-0 shadow-sm">
          <template v-if="!activeRole">
            <CCardBody class="text-center text-muted py-5">
              <div class="display-6 mb-2">🔐</div>
              <div>Select a role on the left to manage its permissions</div>
            </CCardBody>
          </template>

          <template v-else>
            <CCardHeader class="bg-transparent d-flex align-items-center justify-content-between flex-wrap gap-2">
              <div>
                <span class="fw-bold">{{ activeRole.name }}</span>
                <span class="text-muted ms-2 small">
                  {{ activePermCount }} / {{ totalPermCount }} permissions selected
                </span>
              </div>
              <div class="d-flex align-items-center gap-2">
                <CAlert v-if="permSaved" color="success" class="mb-0 py-1 px-3 small">Saved ✓</CAlert>
                <CButton color="success" size="sm" :disabled="savingPerms" @click="savePermissions" style="min-width:140px;">
                  <CSpinner v-if="savingPerms" size="sm" class="me-1" />
                  Save Permissions
                </CButton>
              </div>
            </CCardHeader>

            <CCardBody style="max-height:72vh; overflow-y:auto;">

              <!-- Multi-School Access — always pinned first, full-width, prominent -->
              <div class="mb-3 rounded-3 overflow-hidden" style="border:2px solid #0d6efd;">
                <div class="d-flex align-items-center justify-content-between px-3 py-2"
                  style="background:linear-gradient(135deg,#0d6efd18,#0d6efd08);">
                  <div class="d-flex align-items-center gap-2">
                    <span style="font-size:1.2rem;">🏫</span>
                    <div>
                      <div class="fw-bold text-primary">Multi-School Access</div>
                      <div class="text-muted" style="font-size:.72rem;">
                        Users with this permission can be granted access to multiple schools and switch between them after login.
                      </div>
                    </div>
                  </div>
                  <div
                    class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                    style="cursor:pointer; border:2px solid #0d6efd; transition:all .15s; min-width:180px;"
                    :style="activePerms.value?.has('multi_school') || activePerms.has('multi_school')
                      ? 'background:#0d6efd; color:#fff;'
                      : 'background:#fff; color:#0d6efd;'"
                    @click="togglePerm('multi_school')"
                  >
                    <div
                      class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                      style="width:20px; height:20px; border:2px solid currentColor; transition:all .15s;"
                      :style="activePerms.has('multi_school') ? 'background:#fff; border-color:#fff;' : ''"
                    >
                      <svg v-if="activePerms.has('multi_school')" viewBox="0 0 12 12" width="12" height="12">
                        <polyline points="2,6 5,9 10,3" stroke="#0d6efd" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                      </svg>
                    </div>
                    <span class="fw-semibold">
                      {{ activePerms.has('multi_school') ? 'Enabled for this role' : 'Enable for this role' }}
                    </span>
                  </div>
                </div>
              </div>

              <CRow class="g-3">
                <CCol v-for="[module, perms] in sortedPermissions.filter(([m]) => m !== 'Access')" :key="module" xs="12" sm="6" lg="4">
                  <div class="border rounded-3 overflow-hidden h-100">
                    <div
                      class="d-flex align-items-center justify-content-between px-3 py-2"
                      :style="moduleState(perms) === 'all'
                        ? 'background:#007f3e; color:#fff;'
                        : moduleState(perms) === 'partial'
                          ? 'background:#e9f5ee; color:#007f3e;'
                          : 'background:#f8f9fa;'"
                      style="cursor:pointer;"
                      @click="toggleModule(perms)"
                    >
                      <span class="fw-semibold small">{{ module }}</span>
                      <div class="d-flex align-items-center gap-1">
                        <small class="opacity-75">{{ perms.filter(p => activePerms.has(p)).length }}/{{ perms.length }}</small>
                      </div>
                    </div>
                    <div class="p-2">
                      <div
                        v-for="perm in perms" :key="perm"
                        class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                        style="cursor:pointer; font-size:.82rem; transition:background .1s;"
                        :style="activePerms.has(perm) ? 'background:rgba(0,127,62,.08);' : ''"
                        @click="togglePerm(perm)"
                      >
                        <div
                          class="rounded-circle flex-shrink-0"
                          style="width:16px; height:16px; border:2px solid #dee2e6; transition:all .15s;"
                          :style="activePerms.has(perm) ? 'background:#007f3e; border-color:#007f3e;' : ''"
                        >
                          <svg v-if="activePerms.has(perm)" viewBox="0 0 12 12" width="12" height="12">
                            <polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/>
                          </svg>
                        </div>
                        <span :class="activePerms.has(perm) ? 'fw-semibold text-dark' : 'text-muted'">
                          {{ perm.split('.')[1]?.replace(/_/g,' ') ?? perm }}
                        </span>
                      </div>
                    </div>
                  </div>
                </CCol>
              </CRow>
            </CCardBody>
          </template>
        </CCard>
      </CCol>
    </CRow>

    <!-- ══ User Permission Override Modal ══════════════════════════════════════ -->
    <CModal :visible="userPermModal" @close="userPermModal = false" size="xl" backdrop="static" scrollable>
      <CModalHeader class="border-bottom">
        <CModalTitle>
          <div class="d-flex align-items-center gap-2">
            <div
              class="rounded-circle d-flex align-items-center justify-content-center"
              style="width:36px; height:36px; background:#e9f5ee; color:#007f3e; font-weight:700; font-size:.8rem;"
            >{{ initials(selectedUser?.name) }}</div>
            <div>
              <div class="fw-bold" style="font-size:1rem;">{{ selectedUser?.name }}</div>
              <div class="text-muted small fw-normal">
                {{ selectedUser?.school?.name ?? 'No school' }} &middot;
                Role: <code>{{ activeRole?.name }}</code>
              </div>
            </div>
          </div>
        </CModalTitle>
      </CModalHeader>

      <CModalBody class="p-0">
        <!-- Legend -->
        <div class="d-flex align-items-center gap-3 px-4 py-2 border-bottom bg-light flex-wrap" style="font-size:.78rem;">
          <div class="d-flex align-items-center gap-1">
            <div style="width:14px;height:14px;background:#007f3e;border-radius:50%;"></div>
            <span class="text-muted">Role permission</span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <div style="width:14px;height:14px;background:#0d6efd;border-radius:50%;"></div>
            <span class="text-muted">Extra (user only)</span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <div style="width:14px;height:14px;background:#dc3545;border-radius:50%;"></div>
            <span class="text-muted">Denied (overrides role)</span>
          </div>
          <div class="ms-auto d-flex align-items-center gap-3">
            <span v-if="userExtraCount > 0" class="fw-semibold text-primary">+{{ userExtraCount }} extra</span>
            <span v-if="userForbiddenCount > 0" class="fw-semibold text-danger">{{ userForbiddenCount }} denied</span>
            <CButton size="sm" color="secondary" variant="ghost"
              style="font-size:.72rem; padding:2px 10px; white-space:nowrap;"
              :disabled="userExtraCount === 0 && userForbiddenCount === 0"
              @click="resetToRoleDefaults"
            >↺ Reset to role defaults</CButton>
          </div>
        </div>

        <CAlert v-if="userPermError" color="danger" class="m-3 py-2 small">{{ userPermError }}</CAlert>

        <div style="max-height:60vh; overflow-y:auto;" class="p-3">

          <!-- ── Multi-School Access Panel — always visible, full-width ─────── -->
          <div class="mb-3 rounded-3 overflow-hidden" style="border:2px solid #0d6efd;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2"
              style="background:linear-gradient(135deg,#0d6efd18,#0d6efd08);">
              <div>
                <div class="fw-bold text-primary">🏫 Multi-School Access</div>
                <div class="text-muted" style="font-size:.72rem;">
                  Toggle schools below. Primary school is always accessible — only grant extras.
                  Granting any school automatically gives the <code>multi_school</code> permission.
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <CSpinner v-if="schoolAccessLoading" size="sm" />
                <span v-else class="badge text-primary px-2 py-1" style="background:#0d6efd20; font-size:.75rem; border-radius:20px;">
                  {{ userGrantedSchools.length > 0 ? `${userGrantedSchools.length} extra school${userGrantedSchools.length !== 1 ? 's' : ''} granted` : 'Primary school only' }}
                </span>
              </div>
            </div>
            <div class="p-3">
              <div v-if="schoolAccessLoading" class="text-center py-2 text-muted small">Loading schools…</div>
              <div v-else-if="!allSchools.length" class="text-muted small">No active schools found.</div>
              <div v-else class="d-flex flex-wrap gap-2">
                <div
                  v-for="school in allSchools" :key="school.id"
                  class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                  :style="school.id === selectedUser?.school_id
                    ? 'background:#e9f5ee; border:2px solid #007f3e; cursor:default; min-width:180px; user-select:none;'
                    : userGrantedSchools.includes(school.id)
                      ? 'background:#0d6efd12; border:2px solid #0d6efd88; cursor:pointer; min-width:180px; user-select:none; transition:all .15s;'
                      : 'background:#f8f9fa; border:2px solid #dee2e6; cursor:pointer; min-width:180px; user-select:none; transition:all .15s;'"
                  @click="school.id !== selectedUser?.school_id && toggleSchoolAccess(school)"
                >
                  <CSpinner v-if="schoolGranting === school.id" size="sm" />
                  <div
                    v-else
                    class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center"
                    style="width:20px; height:20px; border:2px solid; transition:all .15s;"
                    :style="school.id === selectedUser?.school_id
                      ? 'background:#007f3e; border-color:#007f3e;'
                      : userGrantedSchools.includes(school.id)
                        ? 'background:#0d6efd; border-color:#0d6efd;'
                        : 'background:transparent; border-color:#adb5bd;'"
                  >
                    <svg v-if="school.id === selectedUser?.school_id || userGrantedSchools.includes(school.id)"
                      viewBox="0 0 12 12" width="11" height="11">
                      <polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2.5" fill="none" stroke-linecap="round"/>
                    </svg>
                  </div>
                  <div class="flex-grow-1">
                    <div class="fw-semibold" style="font-size:.84rem;">{{ school.name }}</div>
                    <div class="text-muted" style="font-size:.68rem; text-transform:capitalize;">{{ school.level ?? '—' }}</div>
                  </div>
                  <span
                    v-if="school.id === selectedUser?.school_id"
                    class="badge text-success ms-1"
                    style="background:#007f3e20; font-size:.6rem;"
                  >primary</span>
                  <span
                    v-else-if="userGrantedSchools.includes(school.id)"
                    class="badge text-primary ms-1"
                    style="background:#0d6efd20; font-size:.6rem;"
                  >granted</span>
                </div>
              </div>
            </div>
          </div>

          <CRow class="g-3">
            <CCol v-for="[module, perms] in sortedPermissions.filter(([m]) => m !== 'Access')" :key="module" xs="12" sm="6" lg="4">
              <div class="border rounded-3 overflow-hidden h-100">
                <!-- Module header -->
                <div
                  class="d-flex align-items-center justify-content-between px-3 py-2"
                  style="cursor:pointer;"
                  :style="userModuleState(perms) === 'all'
                    ? 'background:#007f3e; color:#fff;'
                    : userModuleState(perms).startsWith('partial') || userModuleState(perms) === 'all-forbidden'
                      ? 'background:#fef3f2; color:#dc3545;'
                      : 'background:#f8f9fa;'"
                  @click="toggleUserModule(perms)"
                >
                  <span class="fw-semibold small">{{ module }}</span>
                  <div class="d-flex align-items-center gap-2">
                    <small class="opacity-75">
                      {{ perms.filter(p => userHasPerm(p)).length }}/{{ perms.length }}
                      <span v-if="perms.some(p => userForbidden.has(p))" class="text-danger ms-1">
                        ({{ perms.filter(p => userForbidden.has(p)).length }} denied)
                      </span>
                    </small>
                  </div>
                </div>
                <!-- Permissions -->
                <div class="p-2">
                  <div
                    v-for="perm in perms" :key="perm"
                    class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                    style="cursor:pointer; font-size:.82rem; transition:background .1s; user-select:none;"
                    :style="permSource(perm) === 'forbidden' ? 'background:rgba(220,53,69,.08);' :
                            permSource(perm) === 'direct'    ? 'background:rgba(13,110,253,.08);' :
                            permSource(perm) === 'role'      ? 'background:rgba(0,127,62,.06);' : ''"
                    @click="toggleUserPerm(perm)"
                  >
                    <!-- Indicator circle -->
                    <div
                      class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center"
                      style="width:16px; height:16px; border:2px solid; transition:all .15s;"
                      :style="permSource(perm) === 'forbidden' ? 'background:#dc3545; border-color:#dc3545;' :
                              permSource(perm) === 'direct'    ? 'background:#0d6efd; border-color:#0d6efd;' :
                              permSource(perm) === 'role'      ? 'background:#007f3e; border-color:#007f3e;' :
                                                                 'background:transparent; border-color:#dee2e6;'"
                    >
                      <!-- X for forbidden -->
                      <svg v-if="permSource(perm) === 'forbidden'" viewBox="0 0 12 12" width="10" height="10">
                        <line x1="3" y1="3" x2="9" y2="9" stroke="white" stroke-width="2" stroke-linecap="round"/>
                        <line x1="9" y1="3" x2="3" y2="9" stroke="white" stroke-width="2" stroke-linecap="round"/>
                      </svg>
                      <!-- Check for role or direct -->
                      <svg v-else-if="userHasPerm(perm)" viewBox="0 0 12 12" width="10" height="10">
                        <polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/>
                      </svg>
                    </div>
                    <!-- Label -->
                    <span :class="permSource(perm) === 'forbidden' ? 'text-danger text-decoration-line-through' :
                                  userHasPerm(perm) ? 'fw-semibold text-dark' : 'text-muted'" style="flex:1;">
                      {{ perm === 'multi_school' ? 'Multi-School Access' : perm.split('.')[1]?.replace(/_/g,' ') ?? perm }}
                    </span>
                    <!-- Source badge -->
                    <span v-if="permSource(perm) !== 'none'" class="badge ms-auto" style="font-size:.6rem; white-space:nowrap;"
                      :style="permSource(perm) === 'forbidden' ? 'background:rgba(220,53,69,.15);color:#dc3545;' :
                              permSource(perm) === 'direct'    ? 'background:rgba(13,110,253,.15);color:#0d6efd;' :
                                                                 'background:rgba(0,127,62,.15);color:#007f3e;'"
                    >{{ permSource(perm) === 'forbidden' ? 'denied' : permSource(perm) === 'direct' ? 'user' : 'role' }}</span>
                  </div>
                </div>
              </div>
            </CCol>
          </CRow>
        </div>
      </CModalBody>

      <CModalFooter class="border-top">
        <div class="me-auto text-muted small d-flex align-items-center gap-3 flex-wrap">
          <span v-if="schoolGranting || schoolAccessLoading" class="text-warning fw-semibold">
            ⏳ School operation in progress…
          </span>
          <template v-else-if="userPermsChanged">
            <span v-if="directPermsToSave.length > 0" class="text-primary">
              +{{ directPermsToSave.length }} extra to save
            </span>
            <span v-if="userForbiddenCount > 0" class="text-danger">
              {{ userForbiddenCount }} denied
            </span>
            <span v-if="directPermsToSave.length === 0 && userForbiddenCount === 0" class="text-muted">
              Reset to role defaults
            </span>
          </template>
          <span v-else class="text-muted">🟢 role &nbsp;🔵 user &nbsp;🔴 denied</span>
        </div>
        <CAlert v-if="userPermSaved && !userPermsChanged" color="success" class="mb-0 py-1 px-3 small">Saved ✓</CAlert>
        <CButton color="secondary" variant="ghost" @click="userPermModal = false; userPermSaved = false">Close</CButton>
        <CButton
          color="primary"
          :disabled="savingUserPerms || !!schoolGranting || schoolAccessLoading || !userPermsChanged"
          @click="saveUserPermissions"
          style="min-width:160px;"
        >
          <CSpinner v-if="savingUserPerms" size="sm" class="me-1"/>
          {{ userPermsChanged ? 'Save User Permissions' : 'No Changes' }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Create Role Modal -->
    <CModal :visible="showCreate" @close="showCreate = false" size="sm" backdrop="static">
      <CModalHeader><CModalTitle>Create New Role</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="createError" color="danger" class="py-2 small">{{ createError }}</CAlert>
        <CFormLabel class="fw-semibold">Role Name</CFormLabel>
        <CFormInput
          v-model="newRoleName"
          placeholder="e.g. librarian"
          @keyup.enter="createRole"
          autofocus
        />
        <div class="text-muted small mt-1">
          Lowercase letters and underscores only.
          Preview: <code>{{ newRoleName.trim().toLowerCase().replace(/\s+/g,'_').replace(/[^a-z_]/g,'') || '—' }}</code>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showCreate = false">Cancel</CButton>
        <CButton color="success" :disabled="creating || !newRoleName.trim()" @click="createRole">
          <CSpinner v-if="creating" size="sm" class="me-1" />
          Create Role
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete = null" size="sm">
      <CModalHeader><CModalTitle>Delete Role</CModalTitle></CModalHeader>
      <CModalBody>
        Delete role <strong>{{ confirmDelete?.name }}</strong>?
        Any users with this role will lose their access.
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="confirmDelete = null">Cancel</CButton>
        <CButton color="danger" @click="deleteRole">Delete</CButton>
      </CModalFooter>
    </CModal>

  </CContainer>
</template>
