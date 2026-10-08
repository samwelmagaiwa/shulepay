import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'
import { i18n } from '@/i18n'

export const useBudgetsStore = defineStore('budgets', () => {
  const budgets = ref([])
  const loading = ref(false)
  const error   = ref('')

  async function fetchBudgets() {
    loading.value = true
    error.value   = ''
    try {
      const { data } = await api.get('/budgets')
      budgets.value = data.data || data
    } catch (e) {
      error.value = e?.response?.data?.message || i18n.global.t('errors.loadBudgets')
    } finally {
      loading.value = false
    }
  }

  async function createBudget(payload) {
    const { data } = await api.post('/budgets', payload)
    return data
  }

  async function updateBudget(id, payload) {
    const { data } = await api.put(`/budgets/${id}`, payload)
    return data
  }

  async function activateBudget(id) {
    const { data } = await api.post(`/budgets/${id}/activate`)
    return data
  }

  async function closeBudget(id) {
    const { data } = await api.post(`/budgets/${id}/close`)
    return data
  }

  async function deleteBudget(id) {
    await api.delete(`/budgets/${id}`)
  }

  return { budgets, loading, error, fetchBudgets, createBudget, updateBudget, activateBudget, closeBudget, deleteBudget }
})
