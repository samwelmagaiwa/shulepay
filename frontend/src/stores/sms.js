import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useSmsStore = defineStore('sms', () => {
  const logs = ref([])
  const loading = ref(false)
  const error = ref('')

  async function sendBlast(payload) {
    const { data } = await api.post('/sms/blast', payload)
    return data
  }

  async function fetchLogs() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await api.get('/sms/logs')
      logs.value = data.data || data
    } catch (e) {
      error.value = e?.response?.data?.message || 'Hitilafu'
    } finally {
      loading.value = false
    }
  }

  return { logs, loading, error, sendBlast, fetchLogs }
})
