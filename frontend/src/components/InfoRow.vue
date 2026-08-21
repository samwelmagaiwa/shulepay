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
  align-items: baseline;
  gap: 8px;
  padding: 5px 0;
  font-size: .85rem;
}
.info-label {
  color: #6c757d;
  flex-shrink: 0;
  max-width: 50%;
}
.info-value {
  color: #1a1a1a;
  font-weight: 600;
  text-align: right;
  word-break: break-word;
  min-width: 0;
}
@media (prefers-color-scheme: dark) {
  .info-label { color: #9ca3af; }
  .info-value { color: #f3f4f6; }
}
</style>
