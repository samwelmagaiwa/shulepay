import api from './api'

const DEBOUNCE_DELAY = 1500 // Auto-save after 1.5 seconds of inactivity

export default {
  // Get all drafts for current user and school
  async getDrafts(schoolId = null) {
    try {
      const params = schoolId ? { school_id: schoolId } : {}
      const res = await api.get('/student-drafts', { params })
      return res.data.data || []
    } catch (e) {
      console.error('Failed to fetch drafts:', e)
      return []
    }
  },

  // Get a specific draft
  async getDraft(draftId) {
    try {
      const res = await api.get(`/student-drafts/${draftId}`)
      return res.data.data || null
    } catch (e) {
      console.error('Failed to fetch draft:', e)
      return null
    }
  },

  // Convert form data to API format (convert fees to cents)
  convertToApiFormat(draftData) {
    const converted = { ...draftData }

    // Convert currency fields to cents
    if (converted.total_tuition_fee !== undefined && converted.total_tuition_fee !== null) {
      converted.total_tuition_fee_cents = Math.round((converted.total_tuition_fee || 0) * 100)
    }
    if (converted.discount_amount !== undefined && converted.discount_amount !== null) {
      converted.discount_amount_cents = Math.round((converted.discount_amount || 0) * 100)
    }
    if (converted.opening_balance !== undefined && converted.opening_balance !== null) {
      converted.opening_balance_cents = Math.round((converted.opening_balance || 0) * 100)
    }
    if (converted.lumpsum_total_charged !== undefined && converted.lumpsum_total_charged !== null) {
      converted.lumpsum_total_charged_cents = Math.round((converted.lumpsum_total_charged || 0) * 100)
    }
    if (converted.lumpsum_total_paid !== undefined && converted.lumpsum_total_paid !== null) {
      converted.lumpsum_total_paid_cents = Math.round((converted.lumpsum_total_paid || 0) * 100)
    }

    // Remove photo from draft (not sent to API during auto-save)
    delete converted.photo

    // In lumpsum mode, don't send payment_history (use lumpsum fields instead)
    if (converted.migration_mode === 'lumpsum') {
      converted.payment_history = []
    }

    // Don't send empty payment_history arrays
    if (!converted.payment_history || converted.payment_history.length === 0) {
      delete converted.payment_history
    }

    return converted
  },

  // Save/update a draft (creates new or updates existing)
  async saveDraft(schoolId, draftData) {
    try {
      const payload = {
        school_id: schoolId,
        ...this.convertToApiFormat(draftData),
      }

      // Check if draft exists for this user + school combination
      const drafts = await this.getDrafts(schoolId)
      const existing = drafts.length > 0 ? drafts[0] : null

      let res
      if (existing) {
        try {
          res = await api.put(`/student-drafts/${existing.id}`, payload)
        } catch (putError) {
          // If 403 (unauthorized), fall back to creating a new draft
          if (putError.response?.status === 403) {
            res = await api.post('/student-drafts', payload)
          } else {
            throw putError
          }
        }
      } else {
        res = await api.post('/student-drafts', payload)
      }

      return res.data.data || null
    } catch (e) {
      console.error('Failed to save draft:', e)
      throw e
    }
  },

  // Delete a draft
  async deleteDraft(draftId) {
    try {
      await api.delete(`/student-drafts/${draftId}`)
      return true
    } catch (e) {
      console.error('Failed to delete draft:', e)
      return false
    }
  },

  // Create a debounced auto-save function with guards
  createAutoSaveFn(schoolId, onSave) {
    let timeoutId = null
    let lastSavedHash = null

    return (draftData) => {
      // Don't save if school_id is missing
      if (!schoolId) {
        return
      }

      // Don't save if draftData is empty or minimal
      if (!draftData || Object.keys(draftData).length === 0) {
        return
      }

      // Don't save if only basic fields are filled
      const requiredFields = ['first_name', 'last_name']
      const hasRequiredFields = requiredFields.some(f => draftData[f])
      if (!hasRequiredFields) {
        return
      }

      // Skip if data hasn't changed
      const dataHash = JSON.stringify(draftData)
      if (dataHash === lastSavedHash) {
        return
      }

      if (timeoutId) {
        clearTimeout(timeoutId)
      }

      timeoutId = setTimeout(async () => {
        try {
          const saved = await this.saveDraft(schoolId, draftData)
          lastSavedHash = dataHash
          if (onSave) {
            onSave(saved)
          }
        } catch (e) {
          // Silently fail for draft auto-saves to avoid console spam
          // 502/503 errors are infrastructure issues, not data problems
          // 403 is handled by fallback to POST, other errors are transient
          if (process.env.NODE_ENV === 'development') {
            console.debug('Draft auto-save failed:', {
              status: e.response?.status,
              message: e.message,
            })
          }
        }
      }, DEBOUNCE_DELAY)
    }
  },
}
