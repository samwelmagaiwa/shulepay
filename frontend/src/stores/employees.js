import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useEmployeesStore = defineStore('employees', () => {
  const employees = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchEmployees(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/employees', { params })
      employees.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  const activeEmployees = ref([])

  async function fetchActiveEmployees(schoolId) {
    try {
      const params = schoolId ? { school_id: schoolId } : {}
      const { data } = await api.get('/employees/active', { params })
      activeEmployees.value = data
    } catch (e) {
      // non-fatal
    }
  }

  async function createEmployee(payload) {
    const { data } = await api.post('/employees', payload)
    return data
  }

  async function updateEmployee(id, payload) {
    const { data } = await api.put(`/employees/${id}`, payload)
    return data
  }

  async function deleteEmployee(id) {
    await api.delete(`/employees/${id}`)
  }

  return { employees, activeEmployees, loading, error, pagination, fetchEmployees, fetchActiveEmployees, createEmployee, updateEmployee, deleteEmployee }
})
