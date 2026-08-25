import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

// Eloquent auto-snake-cases relation names when serializing a raw model to
// JSON (feeItems -> fee_items), but the UI reads `items` everywhere. Normalize
// here once rather than at every call site or relying on the backend to shape
// the response differently.
function normalize(structure) {
  if (!structure) return structure
  return { ...structure, items: structure.fee_items ?? structure.items ?? [] }
}

export const useFeeStructuresStore = defineStore('feeStructures', () => {
  const structures = ref([])
  const loading    = ref(false)

  async function fetchStructures(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/fee-structures', { params })
      const list = Array.isArray(data) ? data : (data.data || [])
      structures.value = list.map(normalize)
    } finally {
      loading.value = false
    }
  }

  async function createStructure(payload) {
    const { data } = await api.post('/fee-structures', payload)
    // Full-tuition mode creates one structure per installment and returns
    // { message, structures: [...] } instead of a single structure object.
    if (Array.isArray(data?.structures)) {
      const created = data.structures.map(normalize)
      structures.value.unshift(...created)
      return created
    }
    const created = normalize(data)
    structures.value.unshift(created)
    return created
  }

  async function updateStructure(id, payload) {
    const { data } = await api.put(`/fee-structures/${id}`, payload)
    const updated = normalize(data)
    const idx = structures.value.findIndex(s => s.id === id)
    if (idx !== -1) structures.value[idx] = updated
    return updated
  }

  async function deleteStructure(id) {
    await api.delete(`/fee-structures/${id}`)
    structures.value = structures.value.filter(s => s.id !== id)
  }

  return { structures, loading, fetchStructures, createStructure, updateStructure, deleteStructure }
})
