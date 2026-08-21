import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import api from '@/services/api'
import { useSchoolStore } from '@/stores/school'

export const useBrandingStore = defineStore('branding', () => {
  const appName    = ref(localStorage.getItem('branding_name')    || 'ShulePay')
  const appTagline = ref(localStorage.getItem('branding_tagline') || 'nexoryaTECH')
  const logoUrl    = ref(localStorage.getItem('branding_logo')    || null)
  const loaded     = ref(false)
  const _watching  = ref(false)

  // ── Core fetch ─────────────────────────────────────────────────────────────
  // Fetches branding for the given schoolId (or active school if omitted).
  // Called on login, on school switch, and on app boot.
  async function fetchBranding(schoolId) {
    try {
      const sid = schoolId ?? _activeId()
      const params = sid ? { school_id: sid } : {}
      const res = await api.get('/branding', { params })

      _apply(res.data)
    } catch {
      // Use cached values from localStorage
    } finally {
      loaded.value = true
    }
  }

  // ── Reactive watcher ───────────────────────────────────────────────────────
  // Sets up a single watcher on the school store's activeSchoolId so that
  // branding re-fetches automatically whenever the user switches school.
  // Safe to call multiple times — only installs once.
  function watchSchool() {
    if (_watching.value) return
    _watching.value = true

    try {
      const schoolStore = useSchoolStore()
      watch(
        () => schoolStore.activeSchoolId,
        (id) => {
          if (id) fetchBranding(id)
        },
        { immediate: false },
      )
    } catch {
      // Store not ready yet; fetchBranding() will be called explicitly on login
    }
  }

  // ── Update (owner / superadmin) ────────────────────────────────────────────
  async function updateBranding(formData) {
    const res = await api.post('/branding', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    _apply(res.data)
    return res.data
  }

  // ── Delete logo ────────────────────────────────────────────────────────────
  async function deleteLogo() {
    await api.delete('/branding/logo')
    logoUrl.value = null
    localStorage.removeItem('branding_logo')
  }

  // ── Helpers ────────────────────────────────────────────────────────────────
  function _activeId() {
    try {
      return useSchoolStore().activeSchoolId || null
    } catch {
      return null
    }
  }

  function _apply(data) {
    appName.value    = data.app_name    || 'ShulePay'
    appTagline.value = data.app_tagline || 'nexoryaTECH'
    logoUrl.value    = data.logo_url    || null

    localStorage.setItem('branding_name',    appName.value)
    localStorage.setItem('branding_tagline', appTagline.value)
    if (logoUrl.value) localStorage.setItem('branding_logo', logoUrl.value)
    else               localStorage.removeItem('branding_logo')
  }

  return {
    appName, appTagline, logoUrl, loaded,
    fetchBranding, watchSchool, updateBranding, deleteLogo,
  }
})
