import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useInvoicesStore = defineStore('invoices', () => {
  const invoices   = ref([])
  const invoice    = ref(null)
  const pagination = ref({})
  const loading    = ref(false)

  async function fetchInvoices(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/invoices', { params })
      invoices.value   = data.data
      pagination.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function fetchInvoice(id) {
    const { data } = await api.get(`/invoices/${id}`)
    invoice.value = data.data
    return data.data
  }

  async function generateInvoices(payload) {
    const { data } = await api.post('/invoices/generate', payload)
    return data
  }

  return { invoices, invoice, pagination, loading, fetchInvoices, fetchInvoice, generateInvoices }
})
