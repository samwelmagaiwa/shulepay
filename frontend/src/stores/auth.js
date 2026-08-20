import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'
import { useBrandingStore } from '@/stores/branding'
import { useSchoolStore } from '@/stores/school'

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

  const isAuthenticated    = computed(() => !!token.value && token.value !== 'undefined')
  const role               = computed(() => user.value?.role ?? null)
  const isSuperAdmin       = computed(() => role.value === 'superadmin')
  const isAccountant       = computed(() => role.value === 'accountant' || role.value === 'superadmin')
  const isOwner            = computed(() => role.value === 'owner' || role.value === 'superadmin')
  const isParent           = computed(() => role.value === 'parent')
  const isTeacher          = computed(() => role.value === 'teacher')
  const isHeadTeacher      = computed(() => role.value === 'head_teacher')
  const isHeadmaster       = computed(() => role.value === 'headmaster')
  const isAcademicTeacher  = computed(() => role.value === 'academic_teacher')
  const isStaff            = computed(() => ['teacher', 'head_teacher', 'headmaster', 'academic_teacher'].includes(role.value))

  async function login(email, password) {
    const res = await api.post('/auth/login', { email, password })

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

    // Load branding and school list (non-blocking)
    useBrandingStore().fetchBranding()
    useSchoolStore().fetchSchools(u.school_id)

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
    isAuthenticated, role,
    isAccountant, isOwner, isParent, isSuperAdmin,
    isTeacher, isHeadTeacher, isHeadmaster, isAcademicTeacher, isStaff,
    login, logout, fetchMe,
  }
})
