import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useGuardiansStore = defineStore('guardians', () => {
  const guardians  = ref([])
  const pagination = ref({})
  const loading    = ref(false)

  async function fetchGuardians(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/guardians', { params })
      guardians.value  = data.data || data
      pagination.value = data.meta || {}
    } finally {
      loading.value = false
    }
  }

  async function createGuardian(payload) {
    const { data } = await api.post('/guardians', payload)
    guardians.value.unshift(data)
    return data
  }

  async function updateGuardian(id, payload) {
    const { data } = await api.put(`/guardians/${id}`, payload)
    const idx = guardians.value.findIndex(g => g.id === id)
    if (idx !== -1) guardians.value[idx] = data
    return data
  }

  async function deleteGuardian(id) {
    await api.delete(`/guardians/${id}`)
    guardians.value = guardians.value.filter(g => g.id !== id)
  }

  return { guardians, pagination, loading, fetchGuardians, createGuardian, updateGuardian, deleteGuardian }
})
