<template>
  <div class="d-flex justify-content-between align-items-center border-bottom py-2 mb-1">
    <span class="info-row-label small fw-medium">{{ label }}</span>
    <span class="fw-semibold info-row-value">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
<style scoped>
.info-row-label {
  color: #6c757d;
}
@media (prefers-color-scheme: dark) {
  .info-row-label {
    color: #a0aec0 !important;
  }
  .info-row-value {
    color: #f7fafc !important;
  }
}
</style>
