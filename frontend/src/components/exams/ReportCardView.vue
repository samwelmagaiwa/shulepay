<script setup>
import { useI18n } from 'vue-i18n'

defineProps({
  /** A report card as returned by the API (staff or parent endpoint). */
  card: { type: Object, required: true },
})

const { t } = useI18n()

// 82.50 -> "82.5", 70 -> "70", null -> em dash.
const fmt = (n) => (n === null || n === undefined ? '—' : Number(Number(n).toFixed(2)))
</script>

<template>
  <div class="report-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
      <div>
        <div class="fs-5 fw-bold">{{ card.student.full_name }}</div>
        <div class="text-body-secondary small">
          {{ card.student.class }}
          <template v-if="card.student.admission_number"> · {{ card.student.admission_number }}</template>
        </div>
      </div>
      <div class="text-sm-end">
        <div class="fw-semibold">{{ card.exam.name }}</div>
        <div class="text-body-secondary small">
          {{ [card.exam.term, card.exam.academic_year].filter(Boolean).join(' · ') }}
        </div>
      </div>
    </div>

    <CRow class="g-2 mb-3 text-center">
      <CCol xs="6" md="3">
        <div class="tile">
          <div class="tile-value">{{ fmt(card.summary.total) }}</div>
          <div class="tile-label">{{ t('exams.parent.total') }}</div>
        </div>
      </CCol>
      <CCol xs="6" md="3">
        <div class="tile">
          <div class="tile-value">{{ fmt(card.summary.average) }}</div>
          <div class="tile-label">{{ t('exams.parent.average') }}</div>
        </div>
      </CCol>
      <CCol xs="6" md="3">
        <div class="tile">
          <div class="tile-value">{{ card.summary.grade ?? '—' }}</div>
          <div class="tile-label">{{ t('exams.parent.grade') }}</div>
        </div>
      </CCol>
      <CCol xs="6" md="3">
        <div class="tile">
          <div class="tile-value">
            {{ card.summary.position ?? '—' }}
            <small v-if="card.summary.position" class="text-body-secondary fs-6">
              {{ t('exams.parent.outOf', { total: card.summary.class_size }) }}
            </small>
          </div>
          <div class="tile-label">{{ t('exams.parent.position') }}</div>
        </div>
      </CCol>
    </CRow>

    <CTable small responsive hover class="mb-3 align-middle">
      <CTableHead>
        <CTableRow>
          <CTableHeaderCell>{{ t('exams.parent.subject') }}</CTableHeaderCell>
          <CTableHeaderCell class="text-center">{{ t('exams.parent.marks') }}</CTableHeaderCell>
          <CTableHeaderCell class="text-center">{{ t('exams.parent.grade') }}</CTableHeaderCell>
          <CTableHeaderCell class="text-center">{{ t('exams.parent.position') }}</CTableHeaderCell>
          <CTableHeaderCell class="text-center d-none d-sm-table-cell">{{ t('exams.parent.classAvg') }}</CTableHeaderCell>
          <CTableHeaderCell class="d-none d-md-table-cell">{{ t('exams.results.remark') }}</CTableHeaderCell>
        </CTableRow>
      </CTableHead>
      <CTableBody>
        <CTableRow v-for="s in card.subjects" :key="s.subject_id">
          <CTableDataCell class="fw-semibold">{{ s.name }}</CTableDataCell>
          <template v-if="s.absent">
            <CTableDataCell colspan="2" class="text-center text-warning fst-italic">{{ t('exams.parent.absent') }}</CTableDataCell>
          </template>
          <template v-else>
            <CTableDataCell class="text-center">{{ fmt(s.marks) }}</CTableDataCell>
            <CTableDataCell class="text-center fw-bold">{{ s.grade ?? '—' }}</CTableDataCell>
          </template>
          <CTableDataCell class="text-center">{{ s.position ?? '—' }}</CTableDataCell>
          <CTableDataCell class="text-center d-none d-sm-table-cell">{{ fmt(s.class_average) }}</CTableDataCell>
          <CTableDataCell class="d-none d-md-table-cell text-body-secondary">{{ s.remark ?? '' }}</CTableDataCell>
        </CTableRow>
      </CTableBody>
    </CTable>

    <div v-if="card.summary.remark || card.summary.division" class="mb-2 small">
      <template v-if="card.summary.remark"><strong>{{ t('exams.results.remark') }}:</strong> {{ card.summary.remark }}</template>
      <span v-if="card.summary.division" class="ms-2">
        · <strong>{{ card.summary.division.label }}</strong> ({{ t('examx.card.points', { count: card.summary.division.points }) }})
      </span>
    </div>
    <div v-if="card.exam.out_of && card.exam.out_of !== 100" class="mb-2 small text-body-secondary">
      {{ t('examx.results.percentNote', { total: card.exam.out_of }) }}
    </div>
    <div v-if="card.attendance" class="mb-2 small">
      <strong>{{ t('examx.card.attendance') }}:</strong>
      {{ t('examx.card.attendanceLine', { present: card.attendance.present + card.attendance.late, days: card.attendance.days, absent: card.attendance.absent, percent: card.attendance.percent }) }}
    </div>
    <div v-if="card.comments && (card.comments.teacher || card.comments.head)" class="mb-3 small">
      <div v-if="card.comments.teacher"><strong>{{ t('examx.comments.teacher') }}:</strong> {{ card.comments.teacher }}</div>
      <div v-if="card.comments.head"><strong>{{ t('examx.comments.head') }}:</strong> {{ card.comments.head }}</div>
    </div>
    <div v-if="card.exam.next_term_opens" class="mb-3 small">
      <strong>{{ t('examx.card.nextTerm') }}:</strong> {{ card.exam.next_term_opens }}
    </div>

    <div class="d-flex flex-wrap gap-2 small text-body-secondary">
      <span v-for="b in card.legend" :key="b.grade" class="legend-chip">
        <strong>{{ b.grade }}</strong> {{ fmt(b.from) }}–{{ fmt(b.to) }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.tile {
  border: 1px solid var(--cui-border-color, #dfe3ea);
  border-radius: 0.6rem;
  padding: 0.6rem 0.4rem;
  height: 100%;
}
.tile-value {
  font-size: 1.35rem;
  font-weight: 700;
  line-height: 1.2;
}
.tile-label {
  font-size: 0.72rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--cui-secondary-color, #6b7280);
}
.legend-chip {
  border: 1px solid var(--cui-border-color, #dfe3ea);
  border-radius: 999px;
  padding: 0.1rem 0.6rem;
}
</style>
