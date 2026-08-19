import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useInventoryStore = defineStore('inventory', () => {
  const items       = ref([])
  const loading     = ref(false)
  const error       = ref('')
  const summaryStats = ref({})

  async function fetchItems() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/inventory/items')
      items.value = data
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia bidhaa'
    } finally {
      loading.value = false
    }
  }

  async function createItem(payload) {
    const { data } = await api.post('/inventory/items', payload)
    items.value.unshift(data)
    return data
  }

  async function updateItem(id, payload) {
    const { data } = await api.put(`/inventory/items/${id}`, payload)
    const idx = items.value.findIndex(i => i.id === id)
    if (idx !== -1) items.value[idx] = data
    return data
  }

  async function deleteItem(id) {
    await api.delete(`/inventory/items/${id}`)
    items.value = items.value.filter(i => i.id !== id)
  }

  async function recordTransaction(itemId, payload) {
    const { data } = await api.post(`/inventory/items/${itemId}/transaction`, payload)
    // Update item quantity in local state
    const idx = items.value.findIndex(i => i.id === itemId)
    if (idx !== -1 && data.item) items.value[idx] = data.item
    return data
  }

  async function fetchTransactions(itemId) {
    const { data } = await api.get(`/inventory/items/${itemId}/transactions`)
    return data
  }

  async function fetchSummary() {
    try {
      const { data } = await api.get('/inventory/summary')
      summaryStats.value = data
    } catch (e) {
      summaryStats.value = {}
    }
  }

  return {
    items, loading, error, summaryStats,
    fetchItems, createItem, updateItem, deleteItem,
    recordTransaction, fetchTransactions,
    fetchSummary,
  }
})
