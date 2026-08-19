<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

// ── State ─────────────────────────────────────────────────────────────────────
const roles          = ref([])
const allPermissions = ref({})   // { Module: [perm, ...] }
const loading        = ref(false)
const error          = ref('')

// selected role for permission editing
const activeRole     = ref(null)   // full role object
const activePerms    = ref(new Set())
const savingPerms    = ref(false)
const permSaved      = ref(false)

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
function selectRole(role) {
  activeRole.value  = role
  activePerms.value = new Set(role.permissions)
  permSaved.value   = false
}

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
    // update local role
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
    if (activeRole.value?.id === confirmDelete.value.id) activeRole.value = null
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

const activePermCount = computed(() => activePerms.value.size)
const totalPermCount  = computed(() => Object.values(allPermissions.value).flat().length)
</script>

<template>
  <CContainer fluid class="py-3">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <div>
        <h4 class="mb-0 fw-bold">🔐 Roles & Permissions</h4>
        <p class="text-muted small mb-0">Create roles and control what each role can access</p>
      </div>
      <CButton color="success" @click="showCreate = true; newRoleName = ''; createError = ''">
        + Create Role
      </CButton>
    </div>

    <CAlert v-if="error" color="danger" dismissible @close="error = ''">{{ error }}</CAlert>
    <div v-if="loading" class="text-center py-5"><CSpinner /></div>

    <CRow v-else class="g-3">

      <!-- Left: Roles list -->
      <CCol md="4" lg="3">
        <CCard class="border-0 shadow-sm h-100">
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

            <div v-if="!roles.length" class="text-center text-muted py-4 small">
              No roles found
            </div>
          </CCardBody>
        </CCard>
      </CCol>

      <!-- Right: Permissions editor -->
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
                <CButton
                  color="success"
                  size="sm"
                  :disabled="savingPerms"
                  @click="savePermissions"
                  style="min-width:120px;"
                >
                  <CSpinner v-if="savingPerms" size="sm" class="me-1" />
                  Save Permissions
                </CButton>
              </div>
            </CCardHeader>

            <CCardBody style="max-height:72vh; overflow-y:auto;">
              <CRow class="g-3">
                <CCol
                  v-for="(perms, module) in allPermissions"
                  :key="module"
                  xs="12" sm="6" lg="4"
                >
                  <div class="border rounded-3 overflow-hidden h-100">
                    <!-- Module header -->
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
                        <small class="opacity-75">
                          {{ perms.filter(p => activePerms.has(p)).length }}/{{ perms.length }}
                        </small>
                        <CIcon
                          :icon="moduleState(perms) === 'all' ? 'cilCheckAlt' : 'cilMinus'"
                          size="sm"
                        />
                      </div>
                    </div>

                    <!-- Permissions list -->
                    <div class="p-2">
                      <div
                        v-for="perm in perms"
                        :key="perm"
                        class="d-flex align-items-center gap-2 px-2 py-1 rounded mb-1"
                        style="cursor:pointer; font-size:.82rem; transition:background .1s;"
                        :style="activePerms.has(perm) ? 'background:rgba(0,127,62,.08);' : ''"
                        @click="togglePerm(perm)"
                      >
                        <div
                          class="rounded-circle flex-shrink-0"
                          style="width:16px; height:16px; border:2px solid #dee2e6; transition:all .15s;"
                          :style="activePerms.has(perm)
                            ? 'background:#007f3e; border-color:#007f3e;'
                            : ''"
                        >
                          <svg v-if="activePerms.has(perm)" viewBox="0 0 12 12" width="12" height="12">
                            <polyline points="2,6 5,9 10,3" stroke="white" stroke-width="2" fill="none" stroke-linecap="round"/>
                          </svg>
                        </div>
                        <span :class="activePerms.has(perm) ? 'fw-semibold text-dark' : 'text-muted'">
                          {{ perm.split('.')[1]?.replace(/_/g,' ') }}
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
