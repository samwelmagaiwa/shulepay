<template>
  <!-- At-a-glance numbers across the top of a page (same cards as /wasambazaji) -->
  <CRow class="g-2 mb-3">
    <CCol v-for="card in cards" :key="card.label" xs="6" md="4" :xl="xl">
      <div class="border rounded p-2 h-100" :class="card.warn && card.value ? 'border-warning' : ''">
        <div class="small text-muted">{{ card.label }}</div>
        <div class="fw-bold text-truncate" :class="card.cls" :title="String(card.value)">{{ card.value }}</div>
        <div v-if="card.hint" class="small text-muted text-truncate">{{ card.hint }}</div>
      </div>
    </CCol>
  </CRow>
</template>

<script setup>
defineProps({
  /** [{ label, value, cls?, warn?, hint? }] */
  cards: { type: Array, required: true },
  xl: { type: String, default: '2' },
})
</script>
