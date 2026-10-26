<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import SupBar from '@/components/SupBar.vue'
import SupCards from '@/components/SupCards.vue'
import PrintSheet from '@/components/PrintSheet.vue'
import { useExamFlow } from '@/stores/examFlow'
import { useNotify } from '@/composables/useNotify'
import { menuPlacement } from '@/utils/menu'

// Past registers: day by day, class by class and pupil by pupil. A late pupil counts as attending.
const { t } = useI18n()
const router = useRouter()
const notify = useNotify()
const { guide } = useExamFlow()

const iso = (d) => {
  const x = new Date(d)
  return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`
}
const today = () => iso(new Date())
function weekStart() {
  const d = new Date()
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7))
  return iso(d)
}

const tab = ref('days')
const preset = ref('week')
const from = ref(weekStart())
const to = ref(today())
const classId = ref('')
const classes = ref([])
const term = ref(null)

const overview = ref(null)
const pupils = ref([])
const lowRate = ref(80)
const loading = ref(false)
let firstLoad = true

const asRows = (d) => (Array.isArray(d) ? d : d?.data ?? [])
const classLabel = (c) => [c.name, c.stream].filter(Boolean).join(' ')

function applyPreset(p) {
  const now = new Date()
  if (p === 'today') { from.value = today(); to.value = today() }
  else if (p === 'week') { from.value = weekStart(); to.value = today() }
  else if (p === 'month') { from.value = iso(new Date(now.getFullYear(), now.getMonth(), 1)); to.value = today() }
  else if (p === 'term' && term.value) { from.value = iso(term.value.start_date); to.value = iso(new Date(Math.min(new Date(term.value.end_date), now))) }
}
watch(preset, (p) => { if (p !== 'custom') applyPreset(p) })

async function load() {
  loading.value = true
  try {
    const params = { from: from.value, to: to.value, ...(classId.value ? { school_class_id: classId.value } : {}) }
    const [o, p] = await Promise.all([api.get('/attendance/history', { params }), api.get('/attendance/history/pupils', { params })])
    overview.value = o.data.data
    pupils.value = p.data.data.pupils
    lowRate.value = p.data.data.low_rate
    if (firstLoad && overview.value.totals.records === 0) {
      guide({ title: t('attendx.empty.title'), text: t('attendx.empty.text'), to: '/mahudhurio', yesLabel: t('attendx.empty.go'), noLabel: t('attendx.empty.stay') })
    }
    firstLoad = false
  } catch (e) {
    await notify.fromError(e)
  } finally {
    loading.value = false
  }
}
watch([from, to, classId], () => { if (from.value && to.value) load() })

const rateColor = (rate) => (rate === null ? 'secondary' : rate >= 90 ? 'success' : rate >= lowRate.value ? 'info' : rate >= 60 ? 'warning' : 'danger')
const fmtRate = (rate) => (rate === null || rate === undefined ? '—' : `${rate}%`)
const fmtDay = (d) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' })

const totals = computed(() => overview.value?.totals ?? { present: 0, late: 0, absent: 0, records: 0, rate: null })
const registersMissing = computed(() => (overview.value?.classes ?? []).reduce((n, c) => n + c.days_missing, 0))
const lowPupils = computed(() => pupils.value.filter((p) => p.low))

const cards = computed(() => [
  { label: t('attendx.cards.rate'), value: fmtRate(totals.value.rate), warn: totals.value.rate !== null && totals.value.rate < lowRate.value, cls: totals.value.rate !== null && totals.value.rate < lowRate.value ? 'text-danger' : 'text-success', hint: t('attendx.cards.rateHint') },
  { label: t('attendx.status.present'), value: totals.value.present, cls: 'text-success' },
  { label: t('attendx.status.late'), value: totals.value.late },
  { label: t('attendx.status.absent'), value: totals.value.absent, cls: totals.value.absent ? 'text-danger' : '' },
  { label: t('attendx.cards.missing'), value: registersMissing.value, warn: registersMissing.value > 0, hint: t('attendx.cards.missingHint') },
  { label: t('attendx.cards.low', { rate: lowRate.value }), value: lowPupils.value.length, warn: lowPupils.value.length > 0 },
])

const tabList = computed(() => [
  { key: 'days', label: t('attendx.tabs.days') },
  { key: 'classes', label: t('attendx.tabs.classes'), badge: registersMissing.value, badgeColor: 'warning' },
  { key: 'pupils', label: t('attendx.tabs.pupils'), badge: lowPupils.value.length, badgeColor: 'danger' },
])

// ── who was away on one day ─────────────────────────────────────────────────
const dayOpen = ref(null)
const dayRows = ref([])
const dayLoading = ref(false)

async function openDay(row) {
  dayOpen.value = row
  dayRows.value = []
  dayLoading.value = true
  try {
    const params = { from: row.date, ...(classId.value ? { school_class_id: classId.value } : {}) }
    dayRows.value = (await api.get('/attendance/history/absentees', { params })).data.data.absentees
  } catch (e) {
    dayOpen.value = null
    await notify.fromError(e)
  } finally {
    dayLoading.value = false
  }
}

// ── print view: the table of the tab on screen ──────────────────────────────
const printing = ref(false)
const period = computed(() => `${from.value} → ${to.value}`)
const printModel = computed(() => {
  const o = overview.value
  const className = classes.value.find((c) => String(c.id) === String(classId.value))
  const lines = [period.value, className ? `${t('attendx.class')}: ${classLabel(className)}` : t('attendx.allClasses'), `${t('attendx.cards.rate')}: ${fmtRate(totals.value.rate)}`]
  if (tab.value === 'classes') {
    return {
      title: t('attendx.tabs.classes'), lines,
      columns: [
        { label: t('attendx.class'), value: (r) => r.label },
        { label: t('attendx.daysMarked'), value: (r) => r.days_marked, align: 'center' },
        { label: t('attendx.daysMissing'), value: (r) => r.days_missing, align: 'center' },
        { label: t('attendx.status.present'), value: (r) => r.present, align: 'center' },
        { label: t('attendx.status.late'), value: (r) => r.late, align: 'center' },
        { label: t('attendx.status.absent'), value: (r) => r.absent, align: 'center' },
        { label: t('attendx.cards.rate'), value: (r) => fmtRate(r.rate), align: 'center', bold: true },
      ],
      rows: o?.classes ?? [],
    }
  }
  if (tab.value === 'pupils') {
    return {
      title: t('attendx.tabs.pupils'), lines,
      columns: [
        { label: t('attendx.pupil'), value: (r) => r.name },
        { label: t('attendx.class'), value: (r) => r.class },
        { label: t('attendx.daysRecorded'), value: (r) => r.days, align: 'center' },
        { label: t('attendx.status.present'), value: (r) => r.present, align: 'center' },
        { label: t('attendx.status.late'), value: (r) => r.late, align: 'center' },
        { label: t('attendx.status.absent'), value: (r) => r.absent, align: 'center' },
        { label: t('attendx.cards.rate'), value: (r) => fmtRate(r.rate), align: 'center', bold: true },
      ],
      rows: pupils.value,
    }
  }
  return {
    title: t('attendx.tabs.days'), lines,
    columns: [
      { label: t('attendx.date'), value: (r) => fmtDay(r.date) },
      { label: t('attendx.registers'), value: (r) => `${r.classes_marked} / ${r.classes_total}`, align: 'center' },
      { label: t('attendx.status.present'), value: (r) => r.present, align: 'center' },
      { label: t('attendx.status.late'), value: (r) => r.late, align: 'center' },
      { label: t('attendx.status.absent'), value: (r) => r.absent, align: 'center' },
      { label: t('attendx.cards.rate'), value: (r) => fmtRate(r.rate), align: 'center', bold: true },
    ],
    rows: o?.days ?? [],
  }
})

onMounted(async () => {
  try {
    const [c, tr] = await Promise.all([api.get('/school-classes'), api.get('/terms')])
    classes.value = asRows(c.data)
    term.value = asRows(tr.data).find((x) => x.is_current) ?? null
  } catch (e) {
    await notify.fromError(e)
  }
  await load()
})
</script>

<template>
  <CContainer fluid class="p-2 p-md-3 sup-page">
    <SupCards :cards="cards" />

    <SupBar v-model="tab" :tabs="tabList">
      <CButton color="secondary" variant="outline" @click="router.push('/mahudhurio')">‹ {{ t('attendx.register') }}</CButton>
      <CFormSelect v-model="preset" :aria-label="t('attendx.period')">
        <option value="today">{{ t('attendx.presets.today') }}</option>
        <option value="week">{{ t('attendx.presets.week') }}</option>
        <option value="month">{{ t('attendx.presets.month') }}</option>
        <option v-if="term" value="term">{{ t('attendx.presets.term') }}</option>
        <option value="custom">{{ t('attendx.presets.custom') }}</option>
      </CFormSelect>
      <CFormInput v-model="from" type="date" :max="to" :aria-label="t('attendx.from')" style="max-width:150px !important;" @input="preset = 'custom'" />
      <CFormInput v-model="to" type="date" :min="from" :aria-label="t('attendx.to')" style="max-width:150px !important;" @input="preset = 'custom'" />
      <CFormSelect v-model="classId" :aria-label="t('attendx.class')">
        <option value="">{{ t('attendx.allClasses') }}</option>
        <option v-for="c in classes" :key="c.id" :value="c.id">{{ classLabel(c) }}</option>
      </CFormSelect>
      <CButton color="secondary" variant="outline" :disabled="!overview" @click="printing = true">🖨 {{ t('printing.print') }}</CButton>
    </SupBar>

    <div v-if="overview?.not_marked?.length" class="alert alert-warning d-flex flex-wrap align-items-center gap-2 py-2 small">
      <span>{{ t('attendx.notMarked', { classes: overview.not_marked.join(', ') }) }}</span>
      <CButton size="sm" color="warning" class="ms-auto" @click="router.push('/mahudhurio')">{{ t('attendx.takeRegister') }}</CButton>
    </div>

    <div v-if="loading" class="text-center py-5"><CSpinner color="primary" /></div>

    <CCard v-else-if="overview">
      <CCardBody class="p-0">
        <!-- Day by day -->
        <CTable v-if="tab === 'days'" responsive hover align="middle" class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('attendx.date') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center d-none d-md-table-cell">{{ t('attendx.registers') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.present') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.late') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.absent') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.cards.rate') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center sup-actions">{{ t('exams.list.actions') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="(d, i) in overview.days" :key="d.date">
              <CTableDataCell class="fw-semibold">{{ fmtDay(d.date) }}</CTableDataCell>
              <CTableDataCell class="text-center d-none d-md-table-cell">
                <span :class="d.classes_marked < d.classes_total ? 'text-warning fw-semibold' : ''">{{ d.classes_marked }} / {{ d.classes_total }}</span>
              </CTableDataCell>
              <CTableDataCell class="text-center text-success">{{ d.present }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ d.late }}</CTableDataCell>
              <CTableDataCell class="text-center" :class="d.absent ? 'text-danger fw-semibold' : ''">{{ d.absent }}</CTableDataCell>
              <CTableDataCell class="text-center"><CBadge :color="rateColor(d.rate)">{{ fmtRate(d.rate) }}</CBadge></CTableDataCell>
              <CTableDataCell class="text-center sup-actions">
                <CDropdown :placement="menuPlacement(i, overview.days.length)">
                  <CDropdownToggle color="secondary" variant="outline" :caret="false" class="sup-dots" :aria-label="t('exams.list.actions')">⋮</CDropdownToggle>
                  <CDropdownMenu>
                    <CDropdownItem @click="openDay(d)">{{ t('attendx.whoWasAway') }}</CDropdownItem>
                    <CDropdownItem @click="router.push({ path: '/mahudhurio', query: { date: d.date, class: classId || undefined } })">{{ t('attendx.openRegister') }}</CDropdownItem>
                  </CDropdownMenu>
                </CDropdown>
              </CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!overview.days.length">
              <CTableDataCell colspan="7" class="text-center text-body-secondary py-4">{{ t('attendx.noRegisters') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>

        <!-- Class by class -->
        <CTable v-else-if="tab === 'classes'" responsive hover align="middle" class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('attendx.class') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.daysMarked') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.daysMissing') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.present') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.late') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.absent') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.cards.rate') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="c in overview.classes" :key="c.id">
              <CTableDataCell class="fw-semibold">{{ c.label }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ c.days_marked }}</CTableDataCell>
              <CTableDataCell class="text-center" :class="c.days_missing ? 'text-warning fw-semibold' : ''">{{ c.days_missing }}</CTableDataCell>
              <CTableDataCell class="text-center text-success">{{ c.present }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ c.late }}</CTableDataCell>
              <CTableDataCell class="text-center" :class="c.absent ? 'text-danger fw-semibold' : ''">{{ c.absent }}</CTableDataCell>
              <CTableDataCell class="text-center"><CBadge :color="rateColor(c.rate)">{{ fmtRate(c.rate) }}</CBadge></CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>

        <!-- Pupil by pupil, weakest first -->
        <CTable v-else responsive hover align="middle" class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('attendx.pupil') }}</CTableHeaderCell>
              <CTableHeaderCell class="d-none d-md-table-cell">{{ t('attendx.class') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.daysRecorded') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.present') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.late') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.status.absent') }}</CTableHeaderCell>
              <CTableHeaderCell class="text-center">{{ t('attendx.cards.rate') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="p in pupils" :key="`${p.student_id}-${p.class}`">
              <CTableDataCell class="fw-semibold">{{ p.name }}</CTableDataCell>
              <CTableDataCell class="d-none d-md-table-cell">{{ p.class }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ p.days }}</CTableDataCell>
              <CTableDataCell class="text-center text-success">{{ p.present }}</CTableDataCell>
              <CTableDataCell class="text-center">{{ p.late }}</CTableDataCell>
              <CTableDataCell class="text-center" :class="p.absent ? 'text-danger fw-semibold' : ''">{{ p.absent }}</CTableDataCell>
              <CTableDataCell class="text-center"><CBadge :color="rateColor(p.rate)">{{ fmtRate(p.rate) }}</CBadge></CTableDataCell>
            </CTableRow>
            <CTableRow v-if="!pupils.length">
              <CTableDataCell colspan="7" class="text-center text-body-secondary py-4">{{ t('attendx.noRegisters') }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <!-- Who was away on the chosen day -->
    <CModal :visible="!!dayOpen" alignment="center" scrollable @close="dayOpen = null">
      <CModalHeader><CModalTitle>{{ t('attendx.whoWasAway') }} — {{ dayOpen ? fmtDay(dayOpen.date) : '' }}</CModalTitle></CModalHeader>
      <CModalBody>
        <div v-if="dayLoading" class="text-center py-4"><CSpinner color="primary" /></div>
        <div v-else-if="!dayRows.length" class="text-center text-body-secondary py-3">{{ t('attendx.nobodyAway') }}</div>
        <CTable v-else small class="sup-grid mb-0">
          <CTableHead class="table-light">
            <CTableRow>
              <CTableHeaderCell>{{ t('attendx.pupil') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('attendx.class') }}</CTableHeaderCell>
              <CTableHeaderCell>{{ t('attendx.statusLabel') }}</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-for="r in dayRows" :key="`${r.student_id}-${r.status}`">
              <CTableDataCell>{{ r.name }}<div v-if="r.remarks" class="small text-body-secondary">{{ r.remarks }}</div></CTableDataCell>
              <CTableDataCell>{{ r.class }}</CTableDataCell>
              <CTableDataCell><CBadge :color="r.status === 'absent' ? 'danger' : 'warning'">{{ t(`attendx.status.${r.status}`) }}</CBadge></CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CModalBody>
      <CModalFooter><CButton color="secondary" variant="outline" @click="dayOpen = null">{{ t('examx.flow.ok') }}</CButton></CModalFooter>
    </CModal>

    <PrintSheet v-model="printing" :title="printModel.title" :lines="printModel.lines" :columns="printModel.columns" :rows="printModel.rows" :landscape="tab === 'pupils' ? false : false" />
  </CContainer>
</template>
