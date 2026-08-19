import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useSuppliersStore = defineStore('suppliers', () => {
  const suppliers = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchSuppliers(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/suppliers', { params })
      suppliers.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  async function createSupplier(payload) {
    const { data } = await api.post('/suppliers', payload)
    return data
  }

  async function updateSupplier(id, payload) {
    const { data } = await api.put(`/suppliers/${id}`, payload)
    return data
  }

  async function createPayment(payload) {
    const { data } = await api.post('/supplier-payments', payload)
    return data
  }

  return { suppliers, loading, error, pagination, fetchSuppliers, createSupplier, updateSupplier, createPayment }
})
