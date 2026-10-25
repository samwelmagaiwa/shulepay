<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { dateLocale } from '@/utils/dates'

const { t } = useI18n()
const route  = useRoute()
const router = useRouter()

const iso = (d) => {
  const x = new Date(d)
  return new Date(x.getTime() - x.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
}
const today = iso(new Date())

// ─── Filters ──────────────────────────────────────────────────────────────────
const preset  = ref('week')
const from    = ref(today)
const to      = ref(today)
const classId = ref(String(route.query.class || ''))
const classes = ref([])
const term    = ref(null)

const presets = ['today', 'week', 'month', 'term', 'custom']

function applyPreset(p) {
  preset.value = p
  const now = new Date()
  if (p === 'today') { from.value = today; to.value = today }
  if (p === 'week') {
    const monday = new Date(now)
    monday.setDate(now.getDate() - ((now.getDay() + 6) % 7))
    from.value = iso(monday); to.value = today
  }
  if (p === 'month') { from.value = iso(new Date(now.getFullYear(), now.getMonth(), 1)); to.value = today }
  if (p === 'term' && term.value) {
    from.value = String(term.value.start_date).slice(0, 10)
    to.value   = String(term.value.end_date).slice(0, 10) < today ? String(term.value.end_date).slice(0, 10) : today
  }
}

// ─── Data ─────────────────────────────────────────────────────────────────────
const tab      = ref('days')
const loading  = ref(false)
const error    = ref('')
const overview = ref(null)
const pupils   = ref([])
const away     = ref([])

const singleDay = computed(() => overview.value && overview.value.from === overview.value.to)
const totals    = computed(() => overview.value?.totals)
const lowRate   = computed(() => overview.value?.low_rate ?? 80)
const lowCount  = computed(() => pupils.value.filter((p) => p.low).length)
const missing   = computed(() => (overview.value?.classes || []).reduce((n, c) => n + c.days_missing, 0))
const empty     = computed(() => overview.value && totals.value.records === 0)

let seq = 0
async function load() {
  const mine = ++seq
  loading.value = true
  error.value = ''
  const params = { from: from.value, to: to.value }
  if (classId.value) params.school_class_id = classId.value
  try {
    const [o, p, a] = await Promise.all([
      api.get('/attendance/history', { params }),
      api.get('/attendance/history/pupils', { params }),
      from.value === to.value ? api.get('/attendance/history/absentees', { params }) : Promise.resolve(null),
    ])
    if (mine !== seq) return
    overview.value = o.data.data
    pupils.value   = p.data.data.pupils
    away.value     = a ? a.data.data.absentees : []
    // The server may have swapped or shortened the dates — show what it actually used.
    from.value = overview.value.from
    to.value   = overview.value.to
  } catch (e) {
    if (mine !== seq) return
    error.value = e?.response?.data?.message || t('attendance.loadError')
  } finally {
    if (mine === seq) loading.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get('/school-classes')
    classes.value = data.data || data
  } catch { classes.value = [] }
  try {
    const { data } = await api.get('/terms')
    const list = data.data || data
    term.value = list.find((x) => x.is_current) || null
  } catch { term.value = null }
  applyPreset('week')
  await load()
})

watch([classId], load)
watch([from, to], () => { if (preset.value === 'custom') load() })

function choose(p) {
  applyPreset(p)
  if (p !== 'custom') load()
}

// ─── Presentation ─────────────────────────────────────────────────────────────
const fmtDate = (d) => new Date(d + 'T00:00:00').toLocaleDateString(dateLocale(), { weekday: 'short', day: 'numeric', month: 'short' })
const rateText  = (r) => (r === null || r === undefined ? '—' : `${r}%`)
const rateClass = (r) => (r === null || r === undefined ? 'text-muted' : r < lowRate.value ? 'text-danger fw-bold' : r < 90 ? 'text-warning fw-bold' : 'text-success fw-bold')

function openRegister(cls, date) {
  router.push({ path: '/mahudhurio', query: { class: cls || classId.value || undefined, date } })
}
function printPage() { window.print() }
function openDay(date) {
  from.value = date; to.value = date; preset.value = 'custom'
  load()
}
</script>

<template>
  <div class="attendance-history px-3 pt-3 pb-4">

    <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
      <span style="font-size:1.4rem;">📈</span>
      <h5 class="mb-0 fw-bold">{{ t('nav.attendanceHistory') }}</h5>
      <button v-if="overview && !empty" class="btn btn-outline-secondary btn-sm ms-auto d-print-none" @click="printPage">🖨 {{ t('common.print') }}</button>
      <RouterLink class="btn btn-outline-primary btn-sm d-print-none" to="/mahudhurio">📋 {{ t('attendx.register') }}</RouterLink>
    </div>

    <!-- Filters -->
    <div class="border rounded-3 p-3 mb-3 bg-white shadow-sm d-print-none">
      <div class="d-flex flex-wrap gap-2 align-items-end">
        <div class="btn-group btn-group-sm" role="group" :aria-label="t('attendx.period')">
          <button v-for="p in presets" :key="p" type="button" class="btn"
                  :class="preset === p ? 'btn-primary' : 'btn-outline-primary'"
                  :disabled="p === 'term' && !term" @click="choose(p)">
            {{ t('attendx.presets.' + p) }}
          </button>
        </div>
        <div v-if="preset === 'custom'" class="d-flex gap-2 align-items-end">
          <div>
            <label class="form-label small fw-semibold mb-1">{{ t('attendx.from') }}</label>
            <input v-model="from" type="date" class="form-control form-control-sm" :max="today">
          </div>
          <div>
            <label class="form-label small fw-semibold mb-1">{{ t('attendx.to') }}</label>
            <input v-model="to" type="date" class="form-control form-control-sm" :max="today">
          </div>
        </div>
        <div class="ms-auto" style="min-width:180px;">
          <label class="form-label small fw-semibold mb-1">{{ t('attendx.class') }}</label>
          <select v-model="classId" class="form-select form-select-sm">
            <option value="">{{ t('attendx.allClasses') }}</option>
            <option v-for="c in classes" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
        </div>
      </div>
      <div v-if="overview" class="small text-muted mt-2">
        {{ overview.from === overview.to ? fmtDate(overview.from) : `${fmtDate(overview.from)} → ${fmtDate(overview.to)}` }}
      </div>
    </div>

    <CAlert v-if="error" color="danger" class="small">{{ error }}</CAlert>
    <div v-if="loading && !overview" class="text-center py-5"><CSpinner color="primary" /></div>

    <template v-if="overview">
      <div :style="{ opacity: loading ? .55 : 1 }">

        <!-- Nothing recorded -->
        <div v-if="empty" class="text-center border rounded-3 bg-white py-5 px-3">
          <div style="font-size:2rem;">📋</div>
          <h6 class="fw-bold mt-2">{{ t('attendx.empty.title') }}</h6>
          <p class="text-muted small mb-3">{{ t('attendx.empty.text') }}</p>
          <button class="btn btn-primary btn-sm" @click="openRegister(classId, to)">{{ t('attendx.empty.go') }}</button>
        </div>

        <template v-else>
          <!-- Summary cards -->
          <div class="row g-2 mb-3">
            <div class="col-12 col-md-4">
              <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">{{ t('attendx.cards.rate') }}</div>
                <div class="fs-3" :class="rateClass(totals.rate)">{{ rateText(totals.rate) }}</div>
                <div class="small text-muted">{{ t('attendx.cards.rateHint') }} · {{ totals.records }} {{ t('attendx.daysRecorded').toLowerCase() }}</div>
              </div>
            </div>
            <div class="col-6 col-md-4">
              <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">{{ t('attendx.cards.missing') }}</div>
                <div class="fs-3" :class="missing ? 'text-danger fw-bold' : 'text-success fw-bold'">{{ missing }}</div>
                <div class="small text-muted">{{ t('attendx.cards.missingHint') }}</div>
              </div>
            </div>
            <div class="col-6 col-md-4">
              <div class="border rounded-3 p-3 bg-white h-100">
                <div class="small text-muted">{{ t('attendx.cards.low', { rate: lowRate }) }}</div>
                <div class="fs-3" :class="lowCount ? 'text-danger fw-bold' : 'text-success fw-bold'">{{ lowCount }}</div>
                <div class="small text-muted">
                  {{ t('attendx.status.present') }} {{ totals.present }} · {{ t('attendx.status.late') }} {{ totals.late }} · {{ t('attendx.status.absent') }} {{ totals.absent }}
                </div>
              </div>
            </div>
          </div>

          <CAlert v-if="overview.not_marked.length" color="warning" class="small py-2">
            {{ t('attendx.notMarked', { classes: overview.not_marked.join(', ') }) }}
          </CAlert>

          <!-- One day: who was away -->
          <div v-if="singleDay" class="border rounded-3 bg-white mb-3">
            <div class="px-3 py-2 fw-semibold border-bottom">{{ t('attendx.whoWasAway') }}</div>
            <div v-if="!away.length" class="p-3 small text-muted">{{ t('attendx.nobodyAway') }}</div>
            <div v-else class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr>
                  <th>{{ t('attendx.pupil') }}</th><th>{{ t('attendx.class') }}</th>
                  <th>{{ t('attendx.statusLabel') }}</th><th>{{ t('attendx.remarks') }}</th>
                </tr></thead>
                <tbody>
                  <tr v-for="r in away" :key="r.student_id + r.date">
                    <td class="fw-semibold">{{ r.name }}</td>
                    <td>{{ r.class }}</td>
                    <td><span :class="r.status === 'absent' ? 'text-danger' : 'text-warning'" class="fw-semibold">{{ t('attendx.status.' + r.status) }}</span></td>
                    <td class="text-muted">{{ r.remarks || '—' }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Tabs -->
          <ul class="nav nav-tabs mb-2">
            <li v-for="k in ['days', 'classes', 'pupils']" :key="k" class="nav-item">
              <button class="nav-link" :class="{ active: tab === k }" @click="tab = k">{{ t('attendx.tabs.' + k) }}</button>
            </li>
          </ul>

          <div class="table-responsive border rounded-3 bg-white">
            <table v-if="tab === 'days'" class="table table-hover align-middle mb-0">
              <thead><tr>
                <th>{{ t('attendx.date') }}</th><th class="text-end">{{ t('attendx.status.present') }}</th>
                <th class="text-end">{{ t('attendx.status.late') }}</th><th class="text-end">{{ t('attendx.status.absent') }}</th>
                <th class="text-end">{{ t('attendx.cards.rate') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="d in overview.days" :key="d.date" style="cursor:pointer;" @click="openDay(d.date)">
                  <td class="fw-semibold">{{ fmtDate(d.date) }}</td>
                  <td class="text-end">{{ d.present }}</td><td class="text-end">{{ d.late }}</td><td class="text-end">{{ d.absent }}</td>
                  <td class="text-end" :class="rateClass(d.rate)">{{ rateText(d.rate) }}</td>
                </tr>
              </tbody>
            </table>

            <table v-else-if="tab === 'classes'" class="table table-hover align-middle mb-0">
              <thead><tr>
                <th>{{ t('attendx.class') }}</th><th class="text-end">{{ t('attendx.status.present') }}</th>
                <th class="text-end">{{ t('attendx.status.late') }}</th><th class="text-end">{{ t('attendx.status.absent') }}</th>
                <th class="text-end">{{ t('attendx.cards.rate') }}</th>
                <th class="text-end">{{ t('attendx.daysMarked') }}</th><th class="text-end">{{ t('attendx.daysMissing') }}</th><th></th>
              </tr></thead>
              <tbody>
                <tr v-for="c in overview.classes" :key="c.id">
                  <td class="fw-semibold">{{ c.label }}</td>
                  <td class="text-end">{{ c.present }}</td><td class="text-end">{{ c.late }}</td><td class="text-end">{{ c.absent }}</td>
                  <td class="text-end" :class="rateClass(c.rate)">{{ rateText(c.rate) }}</td>
                  <td class="text-end">{{ c.days_marked }}</td>
                  <td class="text-end" :class="c.days_missing ? 'text-danger fw-bold' : ''">{{ c.days_missing }}</td>
                  <td class="text-end"><button class="btn btn-outline-primary btn-sm" @click="openRegister(c.id, overview.to)">{{ t('attendx.openRegister') }}</button></td>
                </tr>
              </tbody>
            </table>

            <table v-else class="table align-middle mb-0">
              <thead><tr>
                <th>{{ t('attendx.pupil') }}</th><th>{{ t('attendx.class') }}</th>
                <th class="text-end">{{ t('attendx.daysRecorded') }}</th>
                <th class="text-end">{{ t('attendx.status.present') }}</th><th class="text-end">{{ t('attendx.status.late') }}</th><th class="text-end">{{ t('attendx.status.absent') }}</th>
                <th class="text-end">{{ t('attendx.cards.rate') }}</th>
              </tr></thead>
              <tbody>
                <tr v-for="p in pupils" :key="p.id" :class="{ 'table-danger': p.low }">
                  <td class="fw-semibold">{{ p.name }}</td><td>{{ p.class }}</td>
                  <td class="text-end">{{ p.days }}</td>
                  <td class="text-end">{{ p.present }}</td><td class="text-end">{{ p.late }}</td><td class="text-end">{{ p.absent }}</td>
                  <td class="text-end" :class="rateClass(p.rate)">{{ rateText(p.rate) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
