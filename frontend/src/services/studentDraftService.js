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

  // Save/update a draft (creates new or updates existing)
  async saveDraft(schoolId, draftData) {
    try {
      const payload = {
        school_id: schoolId,
        ...draftData,
      }

      // Check if draft exists for this user + school combination
      const drafts = await this.getDrafts(schoolId)
      const existing = drafts.length > 0 ? drafts[0] : null

      let res
      if (existing) {
        res = await api.put(`/student-drafts/${existing.id}`, payload)
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

  // Create a debounced auto-save function
  createAutoSaveFn(schoolId, onSave) {
    let timeoutId = null

    return (draftData) => {
      if (timeoutId) {
        clearTimeout(timeoutId)
      }

      timeoutId = setTimeout(async () => {
        try {
          const saved = await this.saveDraft(schoolId, draftData)
          if (onSave) {
            onSave(saved)
          }
        } catch (e) {
          console.error('Auto-save failed:', e)
        }
      }, DEBOUNCE_DELAY)
    }
  },
}
