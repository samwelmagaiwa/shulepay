import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
  transformResponse: [
    (data) => {
      if (typeof data === 'string') {
        // Strip UTF-8 BOM that XAMPP PHP outputs before parsing
        data = data.replace(/^﻿/, '')
        try { return JSON.parse(data) } catch { return data }
      }
      return data
    },
  ],
})

api.interceptors.request.use(config => {
  const token = localStorage.getItem('shulepay_token')
  if (token) config.headers.Authorization = `Bearer ${token}`

  const schoolId = localStorage.getItem('active_school_id')
  if (schoolId && Number(schoolId) > 0) {
    config.headers['X-School-Id'] = schoolId
  }

  return config
})

api.interceptors.response.use(
  res => res,
  err => {
    // Only force-logout on 401 if this was an authenticated request
    if (err.response?.status === 401) {
      const hadToken = !!localStorage.getItem('shulepay_token')
      localStorage.removeItem('shulepay_token')
      localStorage.removeItem('shulepay_user')
      // Avoid redirect loop if already on login
      if (hadToken && !window.location.pathname.includes('/login')) {
        window.location.href = '/login'
      }
    }
    return Promise.reject(err)
  }
)

export default api
