import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useInventoryStore = defineStore('inventory', () => {
  // ── Consumables ────────────────────────────────────────────────────────────
  const items        = ref([])
  const loading      = ref(false)
  const error        = ref('')
  const summaryStats = ref({})

  // ── Fixed assets ───────────────────────────────────────────────────────────
  const assets      = ref([])
  const assetsLoading = ref(false)
  const assetsError   = ref('')
  const currentPage   = ref(1)
  const lastPage      = ref(1)
  const total         = ref(0)

  // ── Consumable actions ─────────────────────────────────────────────────────

  async function fetchItems(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/inventory/items', { params: { ...params, type: 'consumable' } })
      items.value = data
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia bidhaa'
    } finally {
      loading.value = false
    }
  }

  async function createItem(payload) {
    const { data } = await api.post('/inventory/items', { ...payload, type: 'consumable' })
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
    const idx = items.value.findIndex(i => i.id === itemId)
    if (idx !== -1 && data.item) items.value[idx] = data.item
    return data
  }

  async function fetchTransactions(itemId) {
    const { data } = await api.get(`/inventory/items/${itemId}/transactions`)
    return data
  }

  async function fetchStaffUsage(itemId) {
    const { data } = await api.get(`/inventory/items/${itemId}/staff-usage`)
    return data
  }

  async function fetchSummary() {
    try {
      const { data } = await api.get('/inventory/summary')
      summaryStats.value = data
    } catch {
      summaryStats.value = {}
    }
  }

  // ── Fixed asset actions ────────────────────────────────────────────────────

  async function fetchAssets(params = {}) {
    assetsLoading.value = true
    assetsError.value   = ''
    try {
      const { data } = await api.get('/assets', { params })
      assets.value      = data.data || data
      currentPage.value = data.meta?.current_page ?? 1
      lastPage.value    = data.meta?.last_page ?? 1
      total.value       = data.meta?.total ?? (data.data?.length ?? 0)
    } catch (e) {
      assetsError.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia mali'
    } finally {
      assetsLoading.value = false
    }
  }

  async function nextTag(schoolId) {
    const { data } = await api.get('/assets/next-tag', { params: { school_id: schoolId } })
    return data.asset_tag
  }

  async function createAsset(payload) {
    const headers = payload instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {}
    const { data } = await api.post('/assets', payload, { headers })
    return data
  }

  async function updateAsset(id, payload) {
    if (payload instanceof FormData) {
      payload.append('_method', 'PUT')
      const { data } = await api.post(`/assets/${id}`, payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      return data
    }
    const { data } = await api.put(`/assets/${id}`, payload)
    return data
  }

  async function disposeAsset(id, payload) {
    const { data } = await api.post(`/assets/${id}/dispose`, payload)
    return data
  }

  async function deleteAsset(id) {
    await api.delete(`/assets/${id}`)
    assets.value = assets.value.filter(a => a.id !== id)
  }

  return {
    // Consumables
    items, loading, error, summaryStats,
    fetchItems, createItem, updateItem, deleteItem,
    recordTransaction, fetchTransactions, fetchStaffUsage, fetchSummary,
    // Fixed assets
    assets, assetsLoading, assetsError, currentPage, lastPage, total,
    fetchAssets, nextTag, createAsset, updateAsset, disposeAsset, deleteAsset,
  }
})
