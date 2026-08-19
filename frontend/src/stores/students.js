import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/services/api'

export const useStudentsStore = defineStore('students', () => {
  const students   = ref([])
  const student    = ref(null)
  const pagination = ref({})
  const loading    = ref(false)

  async function fetchStudents(params = {}) {
    loading.value = true
    try {
      const { data } = await api.get('/students', { params })
      students.value   = data.data
      pagination.value = data.meta
    } finally {
      loading.value = false
    }
  }

  async function fetchStudent(id) {
    loading.value = true
    try {
      const { data } = await api.get(`/students/${id}`)
      student.value = data.data
    } finally {
      loading.value = false
    }
  }

  async function createStudent(payload) {
    const { data } = await api.post('/students', payload)
    return data.data
  }

  async function updateStudent(id, payload) {
    const { data } = await api.put(`/students/${id}`, payload)
    return data.data
  }

  async function deleteStudent(id) {
    await api.delete(`/students/${id}`)
    students.value = students.value.filter(s => s.id !== id)
  }

  return { students, student, pagination, loading, fetchStudents, fetchStudent, createStudent, updateStudent, deleteStudent }
})
