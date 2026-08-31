import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useExpensesStore = defineStore('expenses', () => {
  const expenses = ref([])
  const categories = ref([])
  const loading = ref(false)
  const error = ref('')
  const pagination = ref({})

  async function fetchExpenses(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/expenses', { params })
      expenses.value = data.data || data
      pagination.value = data.meta || {}
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia data'
    } finally {
      loading.value = false
    }
  }

  async function fetchCategories() {
    try {
      const { data } = await api.get('/expense-categories')
      categories.value = data.data || data
    } catch (e) {
      categories.value = []
    }
  }

  // Categories are a plain CRUD table. The backend has had all four verbs since
  // the feature shipped; only the GET was ever wired up, which left Category a
  // required field with an unfillable dropdown and no way in.
  async function createCategory(payload) {
    const { data } = await api.post('/expense-categories', payload)
    await fetchCategories()
    return data
  }

  async function updateCategory(id, payload) {
    const { data } = await api.put(`/expense-categories/${id}`, payload)
    await fetchCategories()
    return data
  }

  async function deleteCategory(id) {
    const { data } = await api.delete(`/expense-categories/${id}`)
    await fetchCategories()
    return data
  }

  async function createExpense(payload) {
    const { data } = await api.post('/expenses', payload)
    return data
  }

  async function approveExpense(id) {
    const { data } = await api.post(`/expenses/${id}/approve`)
    return data
  }

  async function deleteExpense(id) {
    await api.delete(`/expenses/${id}`)
  }

  return {
    expenses, categories, loading, error, pagination,
    fetchExpenses, fetchCategories, createExpense, approveExpense, deleteExpense,
    createCategory, updateCategory, deleteCategory,
  }
})
