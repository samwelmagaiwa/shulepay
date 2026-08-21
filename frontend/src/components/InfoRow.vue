<template>
  <div class="border-bottom py-2 mb-1">
    <div class="info-row-label" style="font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px;">{{ label }}</div>
    <div class="info-row-value fw-semibold">{{ value }}</div>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
<style scoped>
.info-row-label {
  color: #6c757d;
}
.info-row-value {
  color: #212529;
  word-break: break-word;
}
@media (prefers-color-scheme: dark) {
  .info-row-label { color: #a0aec0; }
  .info-row-value { color: #f7fafc; }
}
</style>
