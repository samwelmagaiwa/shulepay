import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useBrandingStore = defineStore('branding', () => {
  const appName    = ref(localStorage.getItem('branding_name')    || 'ShulePay')
  const appTagline = ref(localStorage.getItem('branding_tagline') || 'nexoryaTECH')
  const logoUrl    = ref(localStorage.getItem('branding_logo')    || null)
  const loaded     = ref(false)

  async function fetchBranding() {
    try {
      const res = await api.get('/branding')
      appName.value    = res.data.app_name    || 'ShulePay'
      appTagline.value = res.data.app_tagline || 'nexoryaTECH'
      logoUrl.value    = res.data.logo_url    || null
      localStorage.setItem('branding_name',    appName.value)
      localStorage.setItem('branding_tagline', appTagline.value)
      if (logoUrl.value) localStorage.setItem('branding_logo', logoUrl.value)
      else localStorage.removeItem('branding_logo')
    } catch {
      // Use cached values
    } finally {
      loaded.value = true
    }
  }

  async function updateBranding(formData) {
    const res = await api.post('/branding', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    appName.value    = res.data.app_name    || 'ShulePay'
    appTagline.value = res.data.app_tagline || 'nexoryaTECH'
    logoUrl.value    = res.data.logo_url    || null
    localStorage.setItem('branding_name',    appName.value)
    localStorage.setItem('branding_tagline', appTagline.value)
    if (logoUrl.value) localStorage.setItem('branding_logo', logoUrl.value)
    else localStorage.removeItem('branding_logo')
    return res.data
  }

  async function deleteLogo() {
    await api.delete('/branding/logo')
    logoUrl.value = null
    localStorage.removeItem('branding_logo')
  }

  return { appName, appTagline, logoUrl, loaded, fetchBranding, updateBranding, deleteLogo }
})
