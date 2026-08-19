import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAssetsStore = defineStore('assets', () => {
  const assets      = ref([])
  const loading     = ref(false)
  const error       = ref('')
  const pagination  = ref({})
  const currentPage = ref(1)
  const lastPage    = ref(1)
  const total       = ref(0)

  async function fetchAssets(params = {}) {
    loading.value = true
    error.value   = ''
    try {
      const { data } = await api.get('/assets', { params })
      assets.value      = data.data || data
      pagination.value  = data.meta || {}
      currentPage.value = data.meta?.current_page ?? 1
      lastPage.value    = data.meta?.last_page ?? 1
      total.value       = data.meta?.total ?? (data.data?.length ?? 0)
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  async function nextTag(schoolId) {
    const { data } = await api.get('/assets/next-tag', { params: { school_id: schoolId } })
    return data.asset_tag
  }

  async function createAsset(payload) {
    const { data } = await api.post('/assets', payload, {
      headers: payload instanceof FormData ? { 'Content-Type': 'multipart/form-data' } : {},
    })
    return data
  }

  async function updateAsset(id, payload) {
    // For multipart (photo upload) use POST with _method=PUT
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
  }

  return {
    assets, loading, error, pagination,
    currentPage, lastPage, total,
    fetchAssets, nextTag, createAsset, updateAsset, disposeAsset, deleteAsset,
  }
})
