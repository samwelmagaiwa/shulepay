<template>
  <div class="info-row border-bottom">
    <span class="info-label">{{ label }}</span>
    <span class="info-value">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
<style scoped>
.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  padding: 7px 0;
  font-size: .875rem;
  line-height: 1.4;
}
.info-label {
  color: #6c757d !important;
  flex-shrink: 0;
  max-width: 55%;
  font-weight: 400;
}
.info-value {
  color: #0d0d0d !important;
  font-weight: 700;
  text-align: right;
  word-break: break-word;
  min-width: 0;
  font-size: .875rem;
}
@media (prefers-color-scheme: dark) {
  .info-label { color: #9ca3af !important; }
  .info-value { color: #ffffff !important; }
}
</style>
