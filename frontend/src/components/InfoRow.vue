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
  align-items: center;
  gap: 8px;
  padding: 7px 0;
  font-size: .875rem;
  border-bottom: 1px solid rgba(128,128,128,.2);
}
.ir-label {
  color: #6c757d;
  flex-shrink: 0;
  max-width: 55%;
  font-weight: 400;
}
.ir-value {
  color: #111827;
  font-weight: 700;
  text-align: right;
  word-break: break-word;
  min-width: 0;
}
/* dark mode via CoreUI data-theme */
[data-theme="dark"] .ir-label,
[data-bs-theme="dark"] .ir-label { color: #9ca3af; }
[data-theme="dark"] .ir-value,
[data-bs-theme="dark"] .ir-value { color: #f9fafb; }
/* dark mode via system preference */
@media (prefers-color-scheme: dark) {
  .ir-label { color: #9ca3af; }
  .ir-value { color: #f9fafb; }
}
</style>
