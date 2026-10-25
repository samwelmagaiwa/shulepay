import { defineStore } from 'pinia'
import api from '@/services/api'

/**
 * Thin wrappers over the exam API. They return the server's JSON untouched and
 * let errors propagate: each view decides how to present them (useNotify).
 */
export const useExamsStore = defineStore('exams', () => {
  const unwrap = (res) => res.data

  // ── Exams ────────────────────────────────────────────────────────────────
  const listExams = (params = {}) => api.get('/exams', { params }).then(unwrap)
  const getReadiness = () => api.get('/exams/readiness').then(unwrap)
  const getExam = (id) => api.get(`/exams/${id}`).then(unwrap)
  const createExam = (payload) => api.post('/exams', payload).then(unwrap)
  const updateExam = (id, payload) => api.put(`/exams/${id}`, payload).then(unwrap)
  const deleteExam = (id) => api.delete(`/exams/${id}`).then(unwrap)
  const openExam = (id) => api.post(`/exams/${id}/open`).then(unwrap)
  const publishExam = (id, payload = {}) => api.post(`/exams/${id}/publish`, payload).then(unwrap)
  const unpublishExam = (id, reason) => api.post(`/exams/${id}/unpublish`, { reason }).then(unwrap)
  const getProgress = (id) => api.get(`/exams/${id}/progress`).then(unwrap)
  const remindTeachers = (id) => api.post(`/exams/${id}/remind`).then(unwrap)
  const getMarkChanges = (id, subjectId) => api.get(`/exams/${id}/mark-changes`, { params: subjectId ? { subject_id: subjectId } : {} }).then(unwrap)

  // ── Classes ──────────────────────────────────────────────────────────────
  const createClass = (payload) => api.post('/school-classes', payload).then(unwrap)
  const updateClass = (id, payload) => api.put(`/school-classes/${id}`, payload).then(unwrap)
  const deleteClass = (id) => api.delete(`/school-classes/${id}`).then(unwrap)

  // ── Teachers ─────────────────────────────────────────────────────────────
  const getMyAssignments = () => api.get('/exams/my-assignments').then(unwrap)
  const listTeachers = () => api.get('/teacher-assignments').then(unwrap)
  const saveTeacherAssignments = (userId, assignments) =>
    api.put(`/teacher-assignments/${userId}`, { assignments }).then(unwrap)

  // ── Setup ────────────────────────────────────────────────────────────────
  const listSubjects = (params = {}) => api.get('/subjects', { params }).then(unwrap)
  const createSubject = (payload) => api.post('/subjects', payload).then(unwrap)
  const updateSubject = (id, payload) => api.put(`/subjects/${id}`, payload).then(unwrap)
  const deleteSubject = (id) => api.delete(`/subjects/${id}`).then(unwrap)

  const listClassSubjects = () => api.get('/class-subjects').then(unwrap)
  const syncClassSubjects = (classId, subjectIds) =>
    api.put(`/class-subjects/${classId}`, { subject_ids: subjectIds }).then(unwrap)

  const getGradingScale = () => api.get('/grading-scale').then(unwrap)
  const saveGradingScale = (bands) => api.put('/grading-scale', { bands }).then(unwrap)

  // ── Marks and results ────────────────────────────────────────────────────
  const getMarksGrid = (examId, classId, subjectId) =>
    api.get(`/exams/${examId}/marks`, { params: { school_class_id: classId, subject_id: subjectId } }).then(unwrap)
  const saveMarks = (examId, classId, subjectId, entries) =>
    api.put(`/exams/${examId}/marks`, { school_class_id: classId, subject_id: subjectId, entries }).then(unwrap)

  const submitMarks = (examId, classId, subjectId) =>
    api.post(`/exams/${examId}/marks/submit`, { school_class_id: classId, subject_id: subjectId }).then(unwrap)
  const reopenMarks = (examId, classId, subjectId) =>
    api.post(`/exams/${examId}/marks/reopen`, { school_class_id: classId, subject_id: subjectId }).then(unwrap)
  const importMarks = (examId, classId, subjectId, file) => {
    const form = new FormData()
    form.append('school_class_id', classId)
    form.append('subject_id', subjectId)
    form.append('file', file)
    return api.post(`/exams/${examId}/marks/import`, form).then(unwrap)
  }
  /** The marks sheet as a CSV download. */
  async function downloadMarksTemplate(examId, classId, subjectId) {
    const res = await api.get(`/exams/${examId}/marks/template`, { params: { school_class_id: classId, subject_id: subjectId }, responseType: 'blob' })
    const name = /filename="?([^";]+)"?/i.exec(res.headers?.['content-disposition'] || '')?.[1] || 'marks.csv'
    const href = window.URL.createObjectURL(new Blob([res.data], { type: 'text/csv' }))
    const link = document.createElement('a')
    link.href = href
    link.download = name
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(href)
  }

  const getAnalysis = (examId, classId) => api.get(`/exams/${examId}/analysis`, { params: { school_class_id: classId } }).then(unwrap)
  const getCombined = (classId, exams) => api.get('/exams/combined', { params: { school_class_id: classId, exams } }).then(unwrap)
  const getStudentHistory = (studentId) => api.get(`/students/${studentId}/exam-history`).then(unwrap)
  const getCommentBands = () => api.get('/exam-comment-bands').then(unwrap)
  const saveCommentBands = (bands) => api.put('/exam-comment-bands', { bands }).then(unwrap)
  const getCommentStatus = (examId) => api.get(`/exams/${examId}/comments/status`).then(unwrap)
  const applyComments = (examId, payload) => api.post(`/exams/${examId}/comments/apply`, payload).then(unwrap)
  const submitComments = (examId, classId, stage) => api.post(`/exams/${examId}/comments/submit`, { school_class_id: classId, stage }).then(unwrap)
  const reopenComments = (examId, classId, stage) => api.post(`/exams/${examId}/comments/reopen`, { school_class_id: classId, stage }).then(unwrap)
  const getComments = (examId, classId) => api.get(`/exams/${examId}/comments`, { params: { school_class_id: classId } }).then(unwrap)
  const saveComments = (examId, classId, entries) => api.put(`/exams/${examId}/comments`, { school_class_id: classId, entries }).then(unwrap)

  const getClassResults = (examId, classId) =>
    api.get(`/exams/${examId}/class-results`, { params: { school_class_id: classId } }).then(unwrap)
  const getReportCard = (examId, studentId) =>
    api.get(`/exams/${examId}/students/${studentId}/report-card`).then(unwrap)

  // ── Parent portal ────────────────────────────────────────────────────────
  const parentExams = (studentId) => api.get('/parent/exam-results', { params: { student_id: studentId } }).then(unwrap)
  const parentReportCard = (examId, studentId) =>
    api.get(`/parent/exam-results/${examId}`, { params: { student_id: studentId } }).then(unwrap)

  /** Fetch a PDF and hand it to the browser as a download. */
  async function downloadPdf(url, params, fallbackName) {
    const res = await api.get(url, { params, responseType: 'blob' })
    const disposition = res.headers?.['content-disposition'] || ''
    const name = /filename="?([^";]+)"?/i.exec(disposition)?.[1] || fallbackName

    const href = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href = href
    link.download = name
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(href)
  }

  /**
   * Open a PDF in a new tab (where the browser's own viewer can print it). The tab is opened first, in
   * answer to the click, so a pop-up blocker does not stop it while the document is being made.
   */
  async function openPdf(url, params) {
    const tab = window.open('', '_blank')
    try {
      const res = await api.get(url, { params, responseType: 'blob' })
      const href = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
      if (tab) tab.location.href = href
      else window.location.href = href
    } catch (e) {
      tab?.close()
      throw e
    }
  }

  return {
    openPdf, listExams, getReadiness, getExam, createExam, updateExam, deleteExam, openExam, publishExam, unpublishExam,
    getProgress, remindTeachers, getMarkChanges, submitMarks, reopenMarks, importMarks, downloadMarksTemplate,
    getAnalysis, getCombined, getStudentHistory, getComments, saveComments,
    getCommentBands, saveCommentBands, getCommentStatus, applyComments, submitComments, reopenComments,
    createClass, updateClass, deleteClass,
    getMyAssignments, listTeachers, saveTeacherAssignments,
    listSubjects, createSubject, updateSubject, deleteSubject,
    listClassSubjects, syncClassSubjects, getGradingScale, saveGradingScale,
    getMarksGrid, saveMarks, getClassResults, getReportCard,
    parentExams, parentReportCard, downloadPdf,
  }
})
