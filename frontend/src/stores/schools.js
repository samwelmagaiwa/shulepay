import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useSchoolsStore = defineStore('schools', () => {
  const schools        = ref([])
  const activeSchoolId = ref('')
  const loading        = ref(false)
  const error          = ref(null)

  async function fetchSchools(params = {}) {
    loading.value = true
    error.value   = null
    try {
      const { data } = await api.get('/schools', { params })
      schools.value = data.data || data
      if (!activeSchoolId.value && schools.value.length > 0) {
        activeSchoolId.value = String(schools.value[0].id)
      }
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load schools'
    } finally {
      loading.value = false
    }
  }

  async function createSchool(payload) {
    const { data } = await api.post('/schools', payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const school = data.data || data
    schools.value.push(school)
    return school
  }

  async function updateSchool(id, payload) {
    // Laravel doesn't process multipart PUT — use POST + _method spoof
    payload.append('_method', 'PUT')
    const { data } = await api.post(`/schools/${id}`, payload, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    const school = data.data || data
    const idx = schools.value.findIndex(s => s.id === id)
    if (idx !== -1) schools.value[idx] = school
    return school
  }

  async function toggleStatus(id) {
    const { data } = await api.patch(`/schools/${id}/toggle-status`)
    const school = data.data || data
    const idx = schools.value.findIndex(s => s.id === id)
    if (idx !== -1) schools.value[idx] = school
    return school
  }

  async function deleteSchool(id) {
    await api.delete(`/schools/${id}`)
    schools.value = schools.value.filter(s => s.id !== id)
  }

  function setActiveSchool(id) {
    activeSchoolId.value = String(id)
  }

  return {
    schools, activeSchoolId, loading, error,
    fetchSchools, createSchool, updateSchool, toggleStatus, deleteSchool, setActiveSchool,
  }
})
