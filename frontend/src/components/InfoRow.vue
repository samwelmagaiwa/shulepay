<template>
  <div class="ir-row">
    <span class="ir-label">{{ label }}</span>
    <span class="ir-value">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
<style>
.ir-row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 8px;
  padding: 6px 0;
  font-size: .82rem;
  border-bottom: 1px solid rgba(128,128,128,.12);
}
.ir-label {
  color: #8a94a6;
  flex-shrink: 0;
  max-width: 52%;
  font-weight: 400;
  font-style: italic;
  letter-spacing: .01em;
}
.ir-value {
  color: #1e293b;
  font-weight: 500;
  text-align: right;
  word-break: break-word;
  min-width: 0;
  letter-spacing: .015em;
}
[data-theme="dark"] .ir-label,
[data-bs-theme="dark"] .ir-label { color: #94a3b8; }
[data-theme="dark"] .ir-value,
[data-bs-theme="dark"] .ir-value { color: #e2e8f0; }
@media (prefers-color-scheme: dark) {
  .ir-label { color: #94a3b8; }
  .ir-value { color: #e2e8f0; }
}
</style>
