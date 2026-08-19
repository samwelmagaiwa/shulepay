<template>
  <CCard>
    <CCardBody class="d-flex align-items-center">
      <div :class="`text-${color} me-3`" style="font-size:2rem">
        <CIcon :icon="icon" size="xl" />
      </div>
      <div>
        <div class="text-muted small">{{ title }}</div>
        <div class="fw-bold fs-5">{{ value ?? '—' }}</div>
      </div>
    </CCardBody>
  </CCard>
</template>
<script setup>
defineProps({ title: String, value: [String, Number], icon: String, color: { type: String, default: 'primary' } })
</script>
