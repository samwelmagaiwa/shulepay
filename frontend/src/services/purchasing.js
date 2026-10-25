import { reactive } from 'vue'
import api from '@/services/api'

// Thin wrappers over the supplier / purchasing API so views stay free of URLs.

const get = (url, params) => api.get(url, { params }).then(r => r.data)
const post = (url, body, config) => api.post(url, body, config).then(r => r.data)
const put = (url, body) => api.put(url, body).then(r => r.data)
const del = (url) => api.delete(url).then(r => r.data)

/** What the signed-in user may do in the supplier module (the server decides; screens just hide the rest). */
export const access = reactive({ loaded: false, isOwner: false, can: {} })

export async function loadAccess() {
  try {
    const d = await get('/suppliers/permissions')
    access.isOwner = !!d.is_owner
    access.can = d.can || {}
    access.loaded = true
  } catch {
    // keep what we had; the server still refuses anything not allowed
  }
}

export const purchasing = {
  overview: () => get('/suppliers/overview'),

  // Tanzania regions and their districts (shared location data), for the supplier address
  regions: () => get('/locations/regions').then(d => d.data || []),
  districts: (stateId) => get('/locations/districts', { state_id: stateId }).then(d => d.data || []),

  suppliers: (params) => get('/suppliers', params),
  supplier: (id) => get(`/suppliers/${id}`),
  createSupplier: (body) => post('/suppliers', body),
  updateSupplier: (id, body) => put(`/suppliers/${id}`, body),
  deleteSupplier: (id) => del(`/suppliers/${id}`),
  setSupplierStatus: (id, status) => post(`/suppliers/${id}/status`, { status }),
  mergeSupplier: (id, fromId) => post(`/suppliers/${id}/merge`, { from_id: fromId }),
  statement: (id, params) => get(`/suppliers/${id}/statement`, params),
  whtSuggestion: (id, amountCents) => get(`/suppliers/${id}/wht-suggestion`, { amount_cents: amountCents }),

  categories: () => get('/suppliers/categories'),
  addCategory: (name) => post('/suppliers/categories', { name }),
  updateCategory: (id, body) => put(`/suppliers/categories/${id}`, body),
  deleteCategory: (id) => del(`/suppliers/categories/${id}`),
  settings: () => get('/suppliers/settings'),
  saveSettings: (body) => put('/suppliers/settings', body),

  bills: (params) => get('/supplier-bills', params),
  bill: (id) => get(`/supplier-bills/${id}`),
  createBill: (body) => post('/supplier-bills', body),
  updateBill: (id, body) => put(`/supplier-bills/${id}`, body),
  approveBill: (id) => post(`/supplier-bills/${id}/approve`),
  rejectBill: (id, reason) => post(`/supplier-bills/${id}/reject`, { reason }),
  cancelBill: (id, reason) => post(`/supplier-bills/${id}/cancel`, { reason }),
  attachBill: (id, file) => {
    const form = new FormData()
    form.append('file', file)
    return post(`/supplier-bills/${id}/attachment`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },

  payments: (params) => get('/supplier-payments', params),
  createPayment: (body) => post('/supplier-payments', body),
  approvePayment: (id) => post(`/supplier-payments/${id}/approve`),
  rejectPayment: (id, reason) => post(`/supplier-payments/${id}/reject`, { reason }),
  voidPayment: (id, reason) => post(`/supplier-payments/${id}/void`, { reason }),

  receipts: (params) => get('/goods-receipts', params),
  receipt: (id) => get(`/goods-receipts/${id}`),
  createReceipt: (body) => post('/goods-receipts', body),
  voidReceipt: (id, reason) => post(`/goods-receipts/${id}/void`, { reason }),
  stockItems: () => get('/inventory/items', { type: 'consumable', per_page: 500 }),

  documents: (params) => get('/suppliers/documents', params),
  addDocument: (form) => post('/suppliers/documents', form, { headers: { 'Content-Type': 'multipart/form-data' } }),
  deleteDocument: (id) => del(`/suppliers/documents/${id}`),

  aging: () => get('/suppliers/reports/aging'),
  spend: (params) => get('/suppliers/reports/spend', params),
  tax: (params) => get('/suppliers/reports/tax', params),

  importSuppliers: (file, dryRun) => {
    const form = new FormData()
    form.append('file', file)
    if (dryRun) form.append('dry_run', '1')
    return post('/suppliers/import', form, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  vendors: () => get('/suppliers/vendors'),
  linkVendor: (name, supplierId) => post('/suppliers/vendors/link', { name, supplier_id: supplierId || undefined }),
}

/** Download a CSV/PDF endpoint with auth, then hand the browser the file. */
export async function downloadFile(url, filename, params) {
  const { data } = await api.get(url, { params, responseType: 'blob' })
  const href = URL.createObjectURL(data)
  const a = document.createElement('a')
  a.href = href
  a.download = filename
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(href), 1000)
}

/** The first validation message in an API error, else the message, else the fallback. */
export function apiError(e, fallback) {
  const errors = e?.response?.data?.errors
  if (errors) {
    const first = Object.values(errors)[0]
    if (first?.[0]) return first[0]
  }
  return e?.response?.data?.message || fallback
}

export const formatMoney = (cents) => 'TZS ' + Number((cents || 0) / 100).toLocaleString('en-US', { maximumFractionDigits: 0 })
export const toCents = (tzs) => Math.round(Number(tzs || 0) * 100)
export const toTzs = (cents) => Math.round((cents || 0) / 100)
export const today = () => new Date().toISOString().split('T')[0]
