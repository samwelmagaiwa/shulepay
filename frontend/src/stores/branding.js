import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import api from '@/services/api'

// Module-level flag — survives store resets, only installs the watcher once
let _watcherInstalled = false

export const useBrandingStore = defineStore('branding', () => {
  const appName    = ref(localStorage.getItem('branding_name')    || 'ShulePay')
  const appTagline = ref(localStorage.getItem('branding_tagline') || 'nexoryaTECH')
  const logoUrl    = ref(localStorage.getItem('branding_logo')    || null)
  const loaded     = ref(false)

  // ── Internal apply ─────────────────────────────────────────────────────────
  function _apply(data) {
    appName.value    = data.app_name    || 'ShulePay'
    appTagline.value = data.app_tagline || 'nexoryaTECH'
    logoUrl.value    = data.logo_url    || null

    localStorage.setItem('branding_name',    appName.value)
    localStorage.setItem('branding_tagline', appTagline.value)
    if (logoUrl.value) localStorage.setItem('branding_logo', logoUrl.value)
    else               localStorage.removeItem('branding_logo')
  }

  // ── Core fetch ─────────────────────────────────────────────────────────────
  async function fetchBranding(schoolId) {
    try {
      const params = schoolId ? { school_id: Number(schoolId) } : {}
      const res    = await api.get('/branding', { params })
      _apply(res.data)
    } catch {
      // fallback: keep cached localStorage values
    } finally {
      loaded.value = true
    }
  }

  // ── Reactive watcher ───────────────────────────────────────────────────────
  // Must be called once after both stores are ready (called from main.js & auth.js).
  // Uses a module-level flag so it truly only runs once per page lifecycle.
  function watchSchool() {
    if (_watcherInstalled) return
    _watcherInstalled = true

    // Lazy import to avoid circular dependency at module load time
    import('@/stores/school').then(({ useSchoolStore }) => {
      const schoolStore = useSchoolStore()
      watch(
        () => schoolStore.activeSchoolId,
        (id, prev) => {
          if (id && id !== prev) fetchBranding(id)
        },
        { immediate: true },   // fires right away with current activeSchoolId
      )
    })
  }

  // ── Update branding (owner / superadmin save) ──────────────────────────────
  async function updateBranding(formData) {
    const res = await api.post('/branding', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    _apply(res.data)
    return res.data
  }

  // ── Remove logo ────────────────────────────────────────────────────────────
  async function deleteLogo() {
    await api.delete('/branding/logo')
    logoUrl.value = null
    localStorage.removeItem('branding_logo')
  }

  return {
    appName, appTagline, logoUrl, loaded,
    fetchBranding, watchSchool, updateBranding, deleteLogo,
  }
})
