<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { getRoleLabel } from '@/utils/roles'
import { moduleLabel, permissionLabel } from '@/utils/permissions'

const { t } = useI18n()

// ── State ─────────────────────────────────────────────────────────────────────
const roles          = ref([])
const allPermissions = ref({})   // { Module: [perm, ...] }
const loading        = ref(false)
const error          = ref('')

// selected role for permission editing
const activeRole     = ref(null)
const activePerms    = ref(new Set())

// Permissions whose meaning is inverted: holding one REMOVES an ability. They
// are named *_restricted so the screen can tell them apart without a hardcoded
// list, and so a new one is styled correctly the moment it is added.
const isRestriction = (perm) => String(perm).endsWith('_restricted')

// 'invoices.edit_restricted' would otherwise render as 'edit restricted', which
// reads as a state rather than an instruction. 'restrict editing' is the action
// the tick actually performs.
const permLabel = permissionLabel
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
const userPermsSnapshot     = ref(new Set())   // snapshot at modal open for dirty-detection
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
    error.value = e?.response?.data?.message || t('rolesAdmin.loadFailed')
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
    error.value = e?.response?.data?.message || t('rolesAdmin.saveFailed')
  } finally {
    savingPerms.value = false
  }
}

// ── Per-user permission modal ─────────────────────────────────────────────────
function openUserPerms(user) {
  try {
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
  } catch (e) {
    console.error('[openUserPerms] error:', e)
    userPermError.value = t('rolesAdmin.openFailed')
    userPermModal.value = true
  }
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
    userPermError.value = e?.response?.data?.message || t('rolesAdmin.schoolAccessFailed')
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
    userPermError.value = t('rolesAdmin.waitForSchool')
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
      userPermError.value = t('rolesAdmin.conflict', { perms: conflict.join(', ') })
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
      || t('rolesAdmin.saveUserFailed')
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
  if (!newRoleName.value.trim()) { createError.value = t('rolesAdmin.nameRequired'); return }
  const slug = newRoleName.value.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^a-z_]/g, '')
  if (!slug) { createError.value = t('rolesAdmin.nameInvalid'); return }

  creating.value = true
  try {
    const { data } = await api.post('/superadmin/roles', { name: slug })
    roles.value.push(data)
    showCreate.value  = false
    newRoleName.value = ''
    selectRole(data)
  } catch (e) {
    const errs = e?.response?.data?.errors?.name
    createError.value = errs ? errs[0] : (e?.response?.data?.message || t('rolesAdmin.createFailed'))
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
    error.value = e?.response?.data?.message || t('rolesAdmin.deleteFailed')
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
  <!-- Full-height layout: header row + two-column body, each column scrolls independently -->
  <div class="d-flex flex-column" style="height:calc(100vh - 56px); padding:0.5rem 1rem 0.5rem;">

    <!-- Header row: title + both action buttons -->
    <div class="d-flex align-items-center justify-content-between mb-2 flex-shrink-0">
      <h6 class="mb-0 fw-bold">{{ t('nav.rolesPermissions') }}</h6>
      <div class="d-flex align-items-center gap-2">
        <CAlert v-if="error" color="danger" class="mb-0 py-1 px-3 small" dismissible @close="error = ''">{{ error }}</CAlert>
        <CButton color="success" size="sm" @click="showCreate = true; newRoleName = ''; createError = ''">
          {{ t('rolesAdmin.createRole') }}
        </CButton>
        <CButton
          v-if="activeRole"
          color="success" size="sm"
          :disabled="savingPerms"
          @click="savePermissions"
          style="min-width:140px;"
        >
          <CSpinner v-if="savingPerms" size="sm" class="me-1" />
          {{ t('userMgmt.savePermissions') }}
        </CButton>
        <CAlert v-if="permSaved" color="success" class="mb-0 py-1 px-3 small">{{ t('rolesAdmin.saved') }}</CAlert>
      </div>
    </div>

    <div v-if="loading" class="text-center py-5 flex-grow-1"><CSpinner /></div>

    <CRow v-else class="g-3 flex-grow-1 overflow-hidden">

      <!-- Left: Roles list + Users in role — independently scrollable -->
      <CCol md="4" lg="3" class="d-flex flex-column h-100" style="overflow:hidden;">

        <!-- Roles list — scrolls internally, shrinks to make room for users card -->
        <CCard class="border-0 shadow-sm mb-3 d-flex flex-column" style="flex:1 1 0; min-height:0; overflow:hidden;">
          <CCardHeader class="fw-bold bg-transparent border-bottom flex-shrink-0">
            {{ t('rolesAdmin.roles') }} <CBadge color="secondary" class="ms-1">{{ roles.length }}</CBadge>
          </CCardHeader>
          <CCardBody class="p-0" style="overflow-y:auto; flex:1 1 0;">
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
                <div class="fw-semibold small">{{ getRoleLabel(role.name) }}</div>
                <div class="text-muted" style="font-size:.72rem;">
                  {{ t('rolesAdmin.permissionCount', { count: permCount(role) }, permCount(role)) }}
                  <span v-if="isSystem(role)" class="ms-1 text-warning">{{ t('rolesAdmin.system') }}</span>
                </div>
              </div>
              <div class="d-flex align-items-center gap-1">
                <CBadge :color="roleBadgeColor(role.name)" class="px-2" style="font-size:.7rem;">
                  {{ getRoleLabel(role.name) }}
                </CBadge>
                <CButton
                  v-if="!isSystem(role)"
                  size="sm" color="danger" variant="ghost"
                  style="padding:2px 6px;"
                  @click.stop="confirmDelete = role"
                >✕</CButton>
              </div>
            </div>
            <div v-if="!roles.length" class="text-center text-muted py-4 small">{{ t('rolesAdmin.noRoles') }}</div>
          </CCardBody>
        </CCard>

        <!-- Users in selected role — always visible at bottom, scrolls internally -->
        <CCard v-if="activeRole" class="border-0 shadow-sm flex-shrink-0" style="max-height:45%; overflow:hidden;">
          <CCardHeader class="bg-transparent border-bottom d-flex align-items-center justify-content-between">
            <span class="fw-bold small"><i18n-t keypath="rolesAdmin.usersWithRole" tag="span"><template #role><code>{{ activeRole.name }}</code></template></i18n-t></span>
            <CSpinner v-if="roleUsersLoading" size="sm" />
            <CBadge v-else color="secondary">{{ roleUsers.length }}</CBadge>
          </CCardHeader>
          <CCardBody class="p-0" style="overflow-y:auto; max-height:calc(45vh - 60px);">
            <div v-if="!roleUsersLoading && !roleUsers.length" class="text-center text-muted py-3 small">
              {{ t('rolesAdmin.noUsersInRole') }}
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
                    {{ u.school?.name ?? t('rolesAdmin.noSchool') }}
                    <span v-if="userDirectCount(u) > 0" class="ms-1 text-success">
                      {{ t('rolesAdmin.extraCount', { count: userDirectCount(u) }) }}
                    </span>
                  </div>
                </div>
              </div>
              <CButton
                size="sm" color="primary" variant="outline"
                style="font-size:.7rem; padding:2px 8px; white-space:nowrap;"
                @click="openUserPerms(u)"
              >
                {{ t('rolesAdmin.manage') }}
              </CButton>
            </div>
          </CCardBody>
        </CCard>

      </CCol>

      <!-- Right: Role Permissions editor — independently scrollable -->
      <CCol md="8" lg="9" class="h-100 d-flex flex-column" style="overflow:hidden;">
        <CCard class="border-0 shadow-sm h-100 d-flex flex-column" style="overflow:hidden;">
          <template v-if="!activeRole">
            <CCardBody class="text-center text-muted py-5">
              <div class="display-6 mb-2">🔐</div>
              <div>{{ t('rolesAdmin.selectRoleHint') }}</div>
            </CCardBody>
          </template>

          <template v-else>
            <CCardHeader class="bg-transparent border-bottom flex-shrink-0">
              <span class="fw-bold">{{ getRoleLabel(activeRole.name) }}</span>
              <span class="text-muted ms-2 small">{{ t('rolesAdmin.selectedOf', { done: activePermCount, total: totalPermCount }) }}</span>
            </CCardHeader>

            <CCardBody style="overflow-y:auto; flex:1 1 0;">

              <!-- Multi-School Access — always pinned first, full-width, prominent -->
              <div class="mb-3 rounded-3 overflow-hidden" style="border:2px solid #0d6efd;">
                <div class="d-flex align-items-center justify-content-between px-3 py-2"
                  style="background:linear-gradient(135deg,#0d6efd18,#0d6efd08);">
                  <div class="d-flex align-items-center gap-2">
                    <span style="font-size:1.2rem;">🏫</span>
                    <div>
                      <div class="fw-bold text-primary">{{ t('permissionLabels.multi_school') }}</div>
                      <div class="text-muted" style="font-size:.72rem;">
                        {{ t('rolesAdmin.multiSchoolDesc') }}
                      </div>
                    </div>
                  </div>
                  <div
                    class="d-flex align-items-center gap-2 px-3 py-2 rounded-3"
                    style="cursor:pointer; border:2px solid #0d6efd; transition:all .15s; min-width:180px;"
                    :style="activePerms.has('multi_school')
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
                      {{ activePerms.has('multi_school') ? t('rolesAdmin.enabledForRole') : t('rolesAdmin.enableForRole') }}
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
                      <span class="fw-semibold small">{{ moduleLabel(module) }}</span>
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
                        <!-- A restriction ticked green next to 'view' and
                             'generate' would read as another thing granted. Red,
                             with a lock and the word 'restrict', says the
                             opposite at a glance. -->
                        <div
                          class="rounded-circle flex-shrink-0"
                          style="width:16px; height:16px; border:2px solid #dee2e6; transition:all .15s;"
                          :style="activePerms.has(perm)
                            ? (isRestriction(perm) ? 'background:#c0292b; border-color:#c0292b;' : 'background:#007f3e; border-color:#007f3e;')
                            : (isRestriction(perm) ? 'border-color:#e4a1a2;' : '')"
                        >
                          <svg v-if="activePerms.has(perm)" viewBox="0 0 12 12" width="12" height="12">
                            <polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/>
                          </svg>
                        </div>
                        <span
                          :class="activePerms.has(perm)
                            ? (isRestriction(perm) ? 'fw-semibold text-danger' : 'fw-semibold text-dark')
                            : (isRestriction(perm) ? 'text-danger' : 'text-muted')"
                          :title="isRestriction(perm) ? t('permissionLabels.restrictedHint') : ''"
                        >
                          {{ permLabel(perm) }}
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
    <CModal :visible="userPermModal" @close="userPermModal = false" size="xl" backdrop="static">
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
                {{ selectedUser?.school?.name ?? t('rolesAdmin.noSchool') }} &middot;
                {{ t('rolesAdmin.roleLabel', { role: getRoleLabel(activeRole?.name) }) }}
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
            <span class="text-muted">{{ t('rolesAdmin.legendRole') }}</span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <div style="width:14px;height:14px;background:#0d6efd;border-radius:50%;"></div>
            <span class="text-muted">{{ t('rolesAdmin.legendExtra') }}</span>
          </div>
          <div class="d-flex align-items-center gap-1">
            <div style="width:14px;height:14px;background:#dc3545;border-radius:50%;"></div>
            <span class="text-muted">{{ t('rolesAdmin.legendDenied') }}</span>
          </div>
          <div class="ms-auto d-flex align-items-center gap-3">
            <span v-if="userExtraCount > 0" class="fw-semibold text-primary">{{ t('rolesAdmin.extraCount', { count: userExtraCount }) }}</span>
            <span v-if="userForbiddenCount > 0" class="fw-semibold text-danger">{{ t('rolesAdmin.deniedCount', { count: userForbiddenCount }) }}</span>
            <CButton size="sm" color="secondary" variant="ghost"
              style="font-size:.72rem; padding:2px 10px; white-space:nowrap;"
              :disabled="userExtraCount === 0 && userForbiddenCount === 0"
              @click="resetToRoleDefaults"
            >{{ t('rolesAdmin.resetToRole') }}</CButton>
          </div>
        </div>

        <CAlert v-if="userPermError" color="danger" class="m-3 py-2 small">{{ userPermError }}</CAlert>

        <div style="max-height:60vh; overflow-y:auto;" class="p-3">

          <!-- ── Multi-School Access Panel — always visible, full-width ─────── -->
          <div class="mb-3 rounded-3 overflow-hidden" style="border:2px solid #0d6efd;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2"
              style="background:linear-gradient(135deg,#0d6efd18,#0d6efd08);">
              <div>
                <div class="fw-bold text-primary">{{ t('rolesAdmin.schoolAccessTitle') }}</div>
                <div class="text-muted" style="font-size:.72rem;">
                  {{ t('rolesAdmin.schoolAccessDesc') }}
                </div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <CSpinner v-if="schoolAccessLoading" size="sm" />
                <span v-else class="badge text-primary px-2 py-1" style="background:#0d6efd20; font-size:.75rem; border-radius:20px;">
                  {{ userGrantedSchools.length > 0 ? t('rolesAdmin.extraSchools', { count: userGrantedSchools.length }, userGrantedSchools.length) : t('rolesAdmin.primarySchoolOnly') }}
                </span>
              </div>
            </div>
            <div class="p-3">
              <div v-if="schoolAccessLoading" class="text-center py-2 text-muted small">{{ t('rolesAdmin.loadingSchools') }}</div>
              <div v-else-if="!allSchools.length" class="text-muted small">{{ t('rolesAdmin.noActiveSchools') }}</div>
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
                    <div class="text-muted" style="font-size:.68rem; text-transform:capitalize;">{{ school.level === 'primary' ? t('schools.primary') : school.level === 'secondary' ? t('schools.secondary') : (school.level ?? '—') }}</div>
                  </div>
                  <span
                    v-if="school.id === selectedUser?.school_id"
                    class="badge text-success ms-1"
                    style="background:#007f3e20; font-size:.6rem;"
                  >{{ t('rolesAdmin.primaryBadge') }}</span>
                  <span
                    v-else-if="userGrantedSchools.includes(school.id)"
                    class="badge text-primary ms-1"
                    style="background:#0d6efd20; font-size:.6rem;"
                  >{{ t('rolesAdmin.grantedBadge') }}</span>
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
                  <span class="fw-semibold small">{{ moduleLabel(module) }}</span>
                  <div class="d-flex align-items-center gap-2">
                    <small class="opacity-75">
                      {{ perms.filter(p => userHasPerm(p)).length }}/{{ perms.length }}
                      <span v-if="perms.some(p => userForbidden.value.has(p))" class="text-danger ms-1">
                        ({{ t('rolesAdmin.deniedCount', { count: perms.filter(p => userForbidden.value.has(p)).length }) }})
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
                      {{ permLabel(perm) }}
                    </span>
                    <!-- Source badge -->
                    <span v-if="permSource(perm) !== 'none'" class="badge ms-auto" style="font-size:.6rem; white-space:nowrap;"
                      :style="permSource(perm) === 'forbidden' ? 'background:rgba(220,53,69,.15);color:#dc3545;' :
                              permSource(perm) === 'direct'    ? 'background:rgba(13,110,253,.15);color:#0d6efd;' :
                                                                 'background:rgba(0,127,62,.15);color:#007f3e;'"
                    >{{ permSource(perm) === 'forbidden' ? t('rolesAdmin.sourceDenied') : permSource(perm) === 'direct' ? t('rolesAdmin.sourceUser') : t('rolesAdmin.sourceRole') }}</span>
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
            {{ t('rolesAdmin.schoolBusy') }}
          </span>
          <template v-else-if="userPermsChanged">
            <span v-if="directPermsToSave.length > 0" class="text-primary">
              {{ t('rolesAdmin.extraToSave', { count: directPermsToSave.length }) }}
            </span>
            <span v-if="userForbiddenCount > 0" class="text-danger">
              {{ t('rolesAdmin.deniedCount', { count: userForbiddenCount }) }}
            </span>
            <span v-if="directPermsToSave.length === 0 && userForbiddenCount === 0" class="text-muted">
              {{ t('rolesAdmin.resetToRoleShort') }}
            </span>
          </template>
          <span v-else class="text-muted">{{ t('rolesAdmin.legendShort') }}</span>
        </div>
        <CAlert v-if="userPermSaved && !userPermsChanged" color="success" class="mb-0 py-1 px-3 small">{{ t('rolesAdmin.saved') }}</CAlert>
        <CButton color="secondary" variant="ghost" @click="userPermModal = false; userPermSaved = false">{{ t('common.close') }}</CButton>
        <CButton
          color="primary"
          :disabled="savingUserPerms || !!schoolGranting || schoolAccessLoading || !userPermsChanged"
          @click="saveUserPermissions"
          style="min-width:160px;"
        >
          <CSpinner v-if="savingUserPerms" size="sm" class="me-1"/>
          {{ userPermsChanged ? t('rolesAdmin.saveUserPerms') : t('rolesAdmin.noChanges') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Create Role Modal -->
    <CModal :visible="showCreate" @close="showCreate = false" size="sm" backdrop="static">
      <CModalHeader><CModalTitle>{{ t('rolesAdmin.createTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        <CAlert v-if="createError" color="danger" class="py-2 small">{{ createError }}</CAlert>
        <CFormLabel class="fw-semibold">{{ t('rolesAdmin.roleName') }}</CFormLabel>
        <CFormInput
          v-model="newRoleName"
          :placeholder="t('rolesAdmin.roleNamePlaceholder')"
          @keyup.enter="createRole"
          autofocus
        />
        <div class="text-muted small mt-1">
          {{ t('rolesAdmin.roleNameHint') }} <code>{{ newRoleName.trim().toLowerCase().replace(/\s+/g,'_').replace(/[^a-z_]/g,'') || '—' }}</code>
        </div>
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="showCreate = false">{{ t('common.cancel') }}</CButton>
        <CButton color="success" :disabled="creating || !newRoleName.trim()" @click="createRole">
          <CSpinner v-if="creating" size="sm" class="me-1" />
          {{ t('rolesAdmin.createAction') }}
        </CButton>
      </CModalFooter>
    </CModal>

    <!-- Delete Confirm Modal -->
    <CModal :visible="!!confirmDelete" @close="confirmDelete = null" size="sm">
      <CModalHeader><CModalTitle>{{ t('rolesAdmin.deleteTitle') }}</CModalTitle></CModalHeader>
      <CModalBody>
        {{ t('rolesAdmin.deleteBody', { name: confirmDelete?.name }) }}
      </CModalBody>
      <CModalFooter>
        <CButton color="secondary" variant="ghost" @click="confirmDelete = null">{{ t('common.cancel') }}</CButton>
        <CButton color="danger" @click="deleteRole">{{ t('common.delete') }}</CButton>
      </CModalFooter>
    </CModal>

  </div>
</template>
