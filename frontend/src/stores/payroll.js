import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const usePayrollStore = defineStore('payroll', () => {
  const payroll = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchPayroll(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/payroll', { params })
      payroll.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  async function createPayrollEntry(payload) {
    const { data } = await api.post('/payroll', payload)
    return data
  }

  async function markPaid(id) {
    const { data } = await api.post(`/payroll/${id}/mark-paid`)
    return data
  }

  async function bulkGenerate(payload) {
    const { data } = await api.post('/payroll/bulk-generate', payload)
    return data
  }

  return { payroll, loading, error, pagination, fetchPayroll, createPayrollEntry, markPaid, bulkGenerate }
})
