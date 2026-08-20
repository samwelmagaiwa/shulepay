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

  async function fetchSchools(userSchoolId = null) {
    loading.value = true
    try {
      const res = await api.get('/schools')
      const list = res.data.data ?? res.data
      schools.value = list

      // Default to the user's own school on first load
      if (!activeSchoolId.value && userSchoolId) {
        setActive(userSchoolId)
      } else if (!activeSchoolId.value && list.length > 0) {
        setActive(list[0].id)
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
  }

  return { schools, loading, activeSchoolId, activeSchool, fetchSchools, setActive }
})
