import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import { i18n } from '@/i18n'

export const useRefundsStore = defineStore('refunds', () => {
  const refunds = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchRefunds(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/refunds', { params })
      refunds.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || i18n.global.t('errors.loadData')
    } finally {
      loading.value = false
    }
  }

  async function createRefund(payload) {
    const { data } = await api.post('/refunds', payload)
    return data
  }

  async function deleteRefund(id) {
    await api.delete(`/refunds/${id}`)
  }

  return { refunds, loading, error, pagination, fetchRefunds, createRefund, deleteRefund }
})
