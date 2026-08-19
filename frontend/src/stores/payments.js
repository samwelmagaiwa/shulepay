import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const usePaymentsStore = defineStore('payments', () => {
  const payments   = ref([])
  const pagination = ref({})
  const loading    = ref(false)

  async function fetchPayments(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/payments', { params })
      payments.value   = data.data
      pagination.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function recordPayment(payload) {
    loading.value = true
    try {
      const { data } = await api.post('/payments', payload)
      // Controller uses response()->json() which omits the data wrapper;
      // fall back to raw data if data.data is absent.
      return data.data ?? data
    } finally {
      loading.value = false
    }
  }

  return { payments, pagination, loading, fetchPayments, recordPayment }
})
