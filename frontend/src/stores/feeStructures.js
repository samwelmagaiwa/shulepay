import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useFeeStructuresStore = defineStore('feeStructures', () => {
  const structures = ref([])
  const loading    = ref(false)

  async function fetchStructures(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/fee-structures', { params })
      structures.value = Array.isArray(data) ? data : (data.data || [])
    } finally {
      loading.value = false
    }
  }

  async function createStructure(payload) {
    const { data } = await api.post('/fee-structures', payload)
    structures.value.unshift(data)
    return data
  }

  async function updateStructure(id, payload) {
    const { data } = await api.put(`/fee-structures/${id}`, payload)
    const idx = structures.value.findIndex(s => s.id === id)
    if (idx !== -1) structures.value[idx] = data
    return data
  }

  async function deleteStructure(id) {
    await api.delete(`/fee-structures/${id}`)
    structures.value = structures.value.filter(s => s.id !== id)
  }

  return { structures, loading, fetchStructures, createStructure, updateStructure, deleteStructure }
})
