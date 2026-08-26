import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/services/api'

export const useDashboardStore = defineStore('dashboard', () => {
  const stats   = ref(null)
  const loading = ref(false)
  const error   = ref(null)

  // Period selection state (default: today)
  const selectedPeriod = ref('day')
  const selectedDay    = ref(new Date())
  const selectedWeek   = ref(null)
  const selectedMonth  = ref(null)
  const selectedYear   = ref(null)
  const selectedRange  = ref(null)

  // Offline / sync state (stubs — ShulePay is always online)
  const remoteApiAvailable  = ref(null)
  const isOfflineUIReported = ref(false)
  const isUsingCachedData   = ref(false)
  const offlineTimerCountdown = ref(null)
  const futureDateWarning   = ref(null)
  const isSyncing           = ref(false)

  // ── Derived booleans ──────────────────────────────────────────────────────
  const isLoading     = computed(() => loading.value)
  const isInitialized = computed(() => stats.value !== null)
  const isTrendsLoading = computed(() => loading.value)

  const isTodaySelected = computed(() => {
    if (selectedPeriod.value !== 'day') return false
    const d = selectedDay.value
    if (!d) return true
    const today = new Date()
    if (d instanceof Date) {
      return (
        d.getFullYear() === today.getFullYear() &&
        d.getMonth()    === today.getMonth() &&
        d.getDate()     === today.getDate()
      )
    }
    return true
  })

  const compLabel = computed(() => {
    if (selectedPeriod.value === 'day') return 'Jana'
    if (selectedPeriod.value === 'week') return 'Wiki Iliyopita'
    if (selectedPeriod.value === 'month') return 'Mwezi Uliopita'
    if (selectedPeriod.value === 'year') return 'Mwaka Uliopita'
    return 'Kipindi Kilichopita'
  })

  // ── Stats mapped to the shape SocialStatsWidgets expects ─────────────────
  const realStats = computed(() => {
    const s = stats.value
    if (!s) return null
    return {
      total_patients:   s.total_students   || 0,
      // "Outstanding Debt" card (SocialStatsWidgets) reads this — it must be the
      // actual TZS amount still owed, not a count of invoices. That count is a
      // different metric and stays available separately as `pending` below.
      emergency_visits: Math.round((s.total_outstanding_cents || 0) / 100), // TZS
      new_visits:       s.new_students     || 0,
      followups:        Math.round((s.today_collections || 0) / 100), // TZS
      consulted:        s.paid_invoices    || 0,
      pending:          (s.unpaid_invoices || 0) + (s.partial_invoices || 0),
    }
  })

  // Yesterday's stats (mapped from yesterday_collections; other values not available)
  const previousStats = computed(() => {
    const s = stats.value
    if (!s) return null
    return {
      total_patients:   s.total_students   || 0,
      emergency_visits: 0,
      new_visits:       0,
      followups:        Math.round((s.yesterday_collections || 0) / 100),
      consulted:        0,
      pending:          0,
    }
  })

  // ── Pie chart data ────────────────────────────────────────────────────────
  // DashboardPieCharts.vue expects { gender, visit_type, age_groups }
  const pieStats = computed(() => {
    const s = stats.value
    if (!s) return null

    // Map method_breakdown → gender-style chart (cash/mpesa/bank/cheque)
    const methods = {}
    ;(s.method_breakdown || []).forEach(m => {
      methods[m.method] = m.count
    })

    // Map class_breakdown → age_groups
    const cb = s.class_breakdown || {}
    // Helper: sum all keys whose normalized name matches any of the patterns
    const sumKeys = (...patterns) => {
      let total = 0
      for (const [k, v] of Object.entries(cb)) {
        const norm = k.toLowerCase().replace(/[\s_-]/g, '')
        if (patterns.some(p => norm === p.toLowerCase().replace(/[\s_-]/g, ''))) {
          total += v || 0
        }
      }
      return total
    }
    const ageGroups = {
      neonate:    sumKeys('chekechea', 'pp1', 'pp2', 'nursery', 'kindergarten'),
      infant:     sumKeys('std1', 'std2', 'std3', 'darasa1', 'darasa2', 'darasa3'),
      child:      sumKeys('std4', 'std5', 'std6', 'std7', 'darasa4', 'darasa5', 'darasa6', 'darasa7'),
      adolescent: sumKeys('form1', 'form2', 'kidato1', 'kidato2'),
      adult:      sumKeys('form3', 'form4', 'kidato3', 'kidato4'),
      elderly:    sumKeys('form5', 'form6', 'kidato5', 'kidato6'),
    }

    return {
      gender: {
        male:      methods['cash']   || 0,
        female:    methods['mpesa']  || 0,
        no_gender: methods['bank']   || 0,
        unknown:   methods['cheque'] || 0,
      },
      visit_type: {
        new:      s.paid_invoices   || 0,
        followup: (s.unpaid_invoices || 0) + (s.partial_invoices || 0),
      },
      age_groups: ageGroups,
    }
  })

  // ── Service trend data ────────────────────────────────────────────────────
  // ServiceTrendChart.vue expects Chart.js multi-dataset bar+line structure
  const serviceTrendData = computed(() => {
    const s = stats.value
    if (!s) return { labels: [], datasets: [] }

    const wt = s.weekly_trend || []
    const labels = wt.map(d => d.date)
    const amounts = wt.map(d => Math.round((d.amount || 0) / 100)) // TZS

    return {
      labels,
      dates: wt.map(d => d.full_date || d.date),
      datasets: [
        {
          type: 'line',
          label: 'Mwelekeo wa Jumla',
          data: amounts,
          borderColor: '#1e293b',
          backgroundColor: '#1e293b',
          tension: 0.4,
          fill: false,
          pointRadius: 4,
        },
        {
          type: 'bar',
          label: 'Wanafunzi Wote',
          data: amounts.map(() => 0), // student daily count not available
          backgroundColor: '#3b82f6',
        },
        {
          type: 'bar',
          label: 'Madeni Yanayodai',
          data: amounts.map(() => 0),
          backgroundColor: '#dc3545',
        },
        {
          type: 'bar',
          label: 'Yaliyolipwa',
          data: amounts,
          backgroundColor: '#16a34a',
        },
        {
          type: 'bar',
          label: 'Bado Hawajalipa',
          data: amounts.map(() => 0),
          backgroundColor: '#ec4899',
        },
        {
          type: 'bar',
          label: 'Wanafunzi Wapya',
          data: amounts.map(() => 0),
          backgroundColor: '#06b6d4',
        },
        {
          type: 'bar',
          label: 'Makusanyo ya Leo',
          data: amounts,
          backgroundColor: '#6610f2',
        },
      ],
    }
  })

  // ── Clinic/School bar chart ───────────────────────────────────────────────
  // DashboardClinicBarChart.vue expects realClinics array with:
  // { clinic_name, total_visits, previous_visits, consulted, pending,
  //   previous_consulted, previous_pending, trend, interpretation, comparison_dates }
  const realClinics = computed(() => {
    const s = stats.value
    if (!s) return []

    return (s.school_breakdown || []).map(school => {
      const total = school.count || 0
      return {
        clinic_name:        school.school || 'Unknown',
        total_visits:       total,
        previous_visits:    school.previous_count || 0,
        consulted:          school.paid_count      || 0,
        pending:            school.unpaid_count    || 0,
        previous_consulted: school.prev_paid_count || 0,
        previous_pending:   school.prev_unpaid_count || 0,
        trend:              school.trend            || 0,
        interpretation:     school.trend > 0 ? 'Imeongezeka' : school.trend < 0 ? 'Imepungua' : 'Sawa',
        comparison_dates:   'vs Kipindi Kilichopita',
      }
    })
  })

  // ── Referral stats (school invoice distribution) ──────────────────────────
  // ServiceTrendChart.vue expects referralStats: [{ name, code, count }]
  const referralStats = computed(() => {
    const s = stats.value
    if (!s) return []

    if (s.school_breakdown && s.school_breakdown.length) {
      return s.school_breakdown.map(school => ({
        name:  school.school || 'Unknown',
        code:  school.code   || '',
        count: school.count  || 0,
      }))
    }
    return []
  })

  // ── Legacy stubs (Dashboard.vue patientCategories uses these) ────────────
  const metrics = computed(() => [])
  const clinics  = computed(() => [])

  // ── Actions ───────────────────────────────────────────────────────────────
  async function fetchStats() {
    loading.value = true
    error.value   = null
    try {
      const { data } = await api.get('/dashboard/stats')
      stats.value = data
    } catch (e) {
      error.value = e?.response?.data?.message || 'Failed to load dashboard'
    } finally {
      loading.value = false
    }
  }

  async function fetchPendingPatients() {
    // ShulePay doesn't have a pending-patients endpoint; return empty array
    return []
  }

  async function fetchAbsentByClass(date) {
    const { data } = await api.get('/attendance/summary', {
      params: { from_date: date, to_date: date },
    })
    // Return array sorted by class name, only classes with at least 1 absent
    return (data || [])
      .filter(c => c.absent > 0)
      .sort((a, b) => (a.class_name || '').localeCompare(b.class_name || ''))
  }

  function setBreakdownMode(_enabled) {
    // No-op: breakdown mode is not wired to a separate endpoint in ShulePay
  }

  function calculateDateRange() {
    const fmt = (d) => {
      const y = d.getFullYear()
      const m = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${y}-${m}-${day}`
    }
    const today = new Date()
    return { start_date: fmt(today), end_date: fmt(today) }
  }

  function stopPulse() {
    // No-op
  }

  return {
    // Raw state
    stats, loading, error,
    // Period selection
    selectedPeriod, selectedDay, selectedWeek, selectedMonth, selectedYear, selectedRange,
    // Computed booleans
    isLoading, isInitialized, isTrendsLoading, isTodaySelected,
    // Offline stubs
    remoteApiAvailable, isOfflineUIReported, isUsingCachedData,
    offlineTimerCountdown, futureDateWarning, isSyncing,
    // Computed data
    realStats, previousStats, compLabel,
    pieStats, serviceTrendData, realClinics, referralStats,
    // Legacy stubs
    metrics, clinics,
    // Actions
    fetchStats, fetchPendingPatients, fetchAbsentByClass, setBreakdownMode, calculateDateRange, stopPulse,
  }
})
