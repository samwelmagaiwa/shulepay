import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  // Read from localStorage once at init — guard against 'undefined' string
  function readLS(key) {
    const v = localStorage.getItem(key)
    return (!v || v === 'undefined' || v === 'null') ? null : v
  }

  const token = ref(readLS('shulepay_token'))
  const user = ref((() => {
    try { return JSON.parse(readLS('shulepay_user')) } catch { return null }
  })())

  const isAuthenticated       = computed(() => !!token.value && token.value !== 'undefined')
  const role                  = computed(() => user.value?.role ?? null)
  const permissions           = computed(() => user.value?.permissions ?? [])
  const isSuperAdmin          = computed(() => role.value === 'superadmin')
  const hasPermission         = (perm) => isSuperAdmin.value || permissions.value.includes(perm)
  const hasMultiSchool        = computed(() => isSuperAdmin.value || hasPermission('multi_school'))
  const accessibleSchoolIds   = computed(() => user.value?.accessible_school_ids ?? null)
  const isAccountant       = computed(() => role.value === 'accountant' || role.value === 'superadmin')
  const isOwner            = computed(() => role.value === 'owner' || role.value === 'superadmin')
  const isParent           = computed(() => role.value === 'parent')
  const isTeacher          = computed(() => ['teacher', 'teacher_pri', 'teacher_sec'].includes(role.value))
  const isHeadTeacher      = computed(() => role.value === 'head_teacher')
  const isHeadmaster       = computed(() => role.value === 'headmaster')
  const isAcademicTeacher  = computed(() => role.value === 'academic_teacher')
  const isAcademicPri      = computed(() => role.value === 'academic_pri')
  const isAcademicSec      = computed(() => role.value === 'academic_sec')
  const isAcademicStaff    = computed(() => ['academic_teacher', 'academic_pri', 'academic_sec', 'headmaster'].includes(role.value))
  const isStaff            = computed(() => ['teacher', 'teacher_pri', 'teacher_sec', 'head_teacher', 'headmaster', 'academic_teacher', 'academic_pri', 'academic_sec'].includes(role.value))
  const mustChangePassword = computed(() => !!user.value?.must_change_password)

  async function login(email, password, schoolId = null) {
    const payload = { email, password }
    if (schoolId) payload.school_id = schoolId
    const res = await api.post('/auth/login', payload)

    // Backend returns { requires_2fa: true, user_id } when 2FA is enabled.
    // Relay this shape to the caller so the login component can redirect to the
    // OTP verification screen — do NOT throw; it is not an error.
    if (res.data.requires_2fa) {
      return { requires_2fa: true, user_id: res.data.user_id }
    }

    const { token: t, user: u } = res.data

    if (!t) throw new Error('Seva haikutoa tokeni sahihi.')

    // Persist to localStorage FIRST
    localStorage.setItem('shulepay_token', t)
    localStorage.setItem('shulepay_user', JSON.stringify(u))

    // Then update reactive state
    token.value = t
    user.value = u

    // Force the login-selected school as active (override any stale localStorage value)
    // Use lazy imports to avoid circular dependencies
    const { useSchoolStore: getSchoolStore } = await import('@/stores/school')
    const schoolStore = getSchoolStore()
    if (u.school_id) schoolStore.setActive(u.school_id)

    // Set active school first, then start watcher (immediate:true fires for it)
    const { useBrandingStore: getBrandingStore } = await import('@/stores/branding')
    const brandingStore = getBrandingStore()
    brandingStore.watchSchool()   // installs once; immediate watcher fires for activeSchoolId
    schoolStore.fetchSchools()

    return { token: t, user: u }
  }

  function logout() {
    api.post('/auth/logout').catch(() => { })
    token.value = null
    user.value = null
    localStorage.removeItem('shulepay_token')
    localStorage.removeItem('shulepay_user')
  }

  async function fetchMe() {
    const res = await api.get('/auth/me')
    user.value = res.data
    localStorage.setItem('shulepay_user', JSON.stringify(res.data))
    return res.data
  }

  return {
    token, user,
    isAuthenticated, role, permissions, hasPermission,
    hasMultiSchool, accessibleSchoolIds,
    isAccountant, isOwner, isParent, isSuperAdmin,
    isTeacher, isHeadTeacher, isHeadmaster, isAcademicTeacher,
    isAcademicPri, isAcademicSec, isAcademicStaff, isStaff,
    mustChangePassword,
    login, logout, fetchMe,
  }
})
