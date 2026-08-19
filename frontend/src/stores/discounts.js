import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useDiscountsStore = defineStore('discounts', () => {
  const discounts = ref([])
  const loading   = ref(false)

  async function fetchDiscounts(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/discounts', { params })
      discounts.value = data.data || data
    } finally {
      loading.value = false
    }
  }

  async function applyDiscount(payload) {
    const { data } = await api.post('/discounts', payload)
    discounts.value.unshift(data)
    return data
  }

  async function removeDiscount(id) {
    await api.delete(`/discounts/${id}`)
    discounts.value = discounts.value.filter(d => d.id !== id)
  }

  return { discounts, loading, fetchDiscounts, applyDiscount, removeDiscount }
})
