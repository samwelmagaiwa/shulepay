import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useTransportStore = defineStore('transport', () => {
  const vehicles      = ref([])
  const routes        = ref([])
  const subscriptions = ref([])
  const maintenance   = ref([])
  const summaryStats  = ref({})
  const loading       = ref(false)
  const error         = ref('')

  async function fetchVehicles(params = {}) {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/transport/vehicles', { params })
      vehicles.value = data
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu wakati wa kupakia magari'
    } finally {
      loading.value = false
    }
  }

  async function createVehicle(payload) {
    const { data } = await api.post('/transport/vehicles', payload)
    vehicles.value.unshift(data)
    return data
  }

  async function updateVehicle(id, payload) {
    const { data } = await api.put(`/transport/vehicles/${id}`, payload)
    const idx = vehicles.value.findIndex(v => v.id === id)
    if (idx !== -1) vehicles.value[idx] = data
    return data
  }

  async function deleteVehicle(id) {
    await api.delete(`/transport/vehicles/${id}`)
    vehicles.value = vehicles.value.filter(v => v.id !== id)
  }

  async function fetchRoutes(params = {}) {
    try {
      const { data } = await api.get('/transport/routes', { params })
      routes.value = data
    } catch (e) {
      routes.value = []
    }
  }

  async function createRoute(payload) {
    const { data } = await api.post('/transport/routes', payload)
    routes.value.unshift(data)
    return data
  }

  async function updateRoute(id, payload) {
    const { data } = await api.put(`/transport/routes/${id}`, payload)
    const idx = routes.value.findIndex(r => r.id === id)
    if (idx !== -1) routes.value[idx] = data
    return data
  }

  async function fetchSubscriptions(params = {}) {
    try {
      const { data } = await api.get('/transport/subscriptions', { params })
      subscriptions.value = data
    } catch (e) {
      subscriptions.value = []
    }
  }

  async function subscribe(payload) {
    const { data } = await api.post('/transport/subscriptions', payload)
    subscriptions.value.unshift(data)
    return data
  }

  async function unsubscribe(id) {
    await api.delete(`/transport/subscriptions/${id}`)
    subscriptions.value = subscriptions.value.filter(s => s.id !== id)
  }

  async function fetchMaintenance(vehicleId) {
    try {
      const { data } = await api.get(`/transport/vehicles/${vehicleId}/maintenance`)
      maintenance.value = data
    } catch (e) {
      maintenance.value = []
    }
  }

  async function addMaintenance(vehicleId, payload) {
    const { data } = await api.post(`/transport/vehicles/${vehicleId}/maintenance`, payload)
    maintenance.value.unshift(data)
    return data
  }

  async function fetchSummary(params = {}) {
    try {
      const { data } = await api.get('/transport/summary', { params })
      summaryStats.value = data
    } catch (e) {
      summaryStats.value = {}
    }
  }

  return {
    vehicles, routes, subscriptions, maintenance, summaryStats, loading, error,
    fetchVehicles, createVehicle, updateVehicle, deleteVehicle,
    fetchRoutes, createRoute, updateRoute,
    fetchSubscriptions, subscribe, unsubscribe,
    fetchMaintenance, addMaintenance,
    fetchSummary,
  }
})
