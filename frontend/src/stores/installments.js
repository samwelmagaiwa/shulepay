import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useInstallmentsStore = defineStore('installments', () => {
  const installments = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchInstallments(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/installments', { params })
      installments.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  async function createInstallment(payload) {
    const { data } = await api.post('/installments', payload)
    return data
  }

  async function markPaid(id, installmentNumber, customAmountCents = null) {
    const payload = { installment_number: installmentNumber }
    if (customAmountCents !== null) payload.paid_amount_cents = customAmountCents
    const { data } = await api.post(`/installments/${id}/mark-paid`, payload)
    return data
  }

  return { installments, loading, error, pagination, fetchInstallments, createInstallment, markPaid }
})
