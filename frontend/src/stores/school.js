import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useSchoolStore = defineStore('school', () => {
  const schools = ref([])
  const loading = ref(false)

  const activeSchoolId = ref(
    parseInt(localStorage.getItem('active_school_id')) || null,
  )

  const activeSchool = computed(() =>
    schools.value.find((s) => s.id === activeSchoolId.value) ?? null,
  )

  async function fetchSchools() {
    loading.value = true
    try {
      const res = await api.get('/schools')
      const list = res.data.data ?? res.data
      schools.value = list

      // If no active school yet, default to first in the accessible list
      if (!activeSchoolId.value && list.length > 0) {
        setActive(list[0].id)
        return  // setActive already applies branding
      }

      // If active school is no longer accessible, reset to first
      if (activeSchoolId.value && list.length > 0 && !list.find((s) => s.id === activeSchoolId.value)) {
        setActive(list[0].id)
        return  // setActive already applies branding
      }

      // Active school is in the list — apply its branding now that we have the data.
      // This covers login (setActive fired before fetchSchools completed) and page refresh.
      if (activeSchoolId.value) {
        const active = list.find((s) => s.id === activeSchoolId.value)
        if (active?.branding) {
          import('@/stores/branding').then(({ useBrandingStore }) => {
            useBrandingStore().applyFromSchool(active.branding)
          })
        }
      }
    } catch {
      // silently skip — user might not have access yet
    } finally {
      loading.value = false
    }
  }

  function setActive(id) {
    activeSchoolId.value = id ? Number(id) : null
    if (id) {
      localStorage.setItem('active_school_id', id)
    } else {
      localStorage.removeItem('active_school_id')
    }

    if (id) {
      import('@/stores/branding').then(({ useBrandingStore }) => {
        const brandingStore = useBrandingStore()
        // Apply branding instantly from the already-loaded school object (no API round-trip)
        const school = schools.value.find((s) => s.id === Number(id))
        if (school?.branding) {
          brandingStore.applyFromSchool(school.branding)
        }
        // Then refresh from the API in the background to pick up any recent changes
        brandingStore.fetchBranding(Number(id))
      })
    }
  }

  return { schools, loading, activeSchoolId, activeSchool, fetchSchools, setActive }
})
