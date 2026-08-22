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
  gap: 12px;
  padding: 8px 0;
  border-bottom: 1px solid rgba(128, 128, 128, 0.15);
}
.ir-label {
  color: #6c757d !important;
  flex-shrink: 0;
  max-width: 50%;
  font-size: .8rem;
  font-weight: 500;
  line-height: 1.4;
}
.ir-value {
  color: #0f172a !important;
  font-weight: 700;
  font-size: .9rem;
  text-align: right;
  word-break: break-word;
  min-width: 0;
  line-height: 1.4;
}
:root[data-coreui-theme="dark"] .ir-label,
:root[data-bs-theme="dark"] .ir-label,
[data-theme="dark"] .ir-label { color: #94a3b8 !important; }

:root[data-coreui-theme="dark"] .ir-value,
:root[data-bs-theme="dark"] .ir-value,
[data-theme="dark"] .ir-value { color: #f1f5f9 !important; }

@media (prefers-color-scheme: dark) {
  .ir-label { color: #94a3b8 !important; }
  .ir-value { color: #f1f5f9 !important; }
}
@media (max-width: 480px) {
  .ir-label { font-size: .75rem; }
  .ir-value  { font-size: .85rem; }
}
</style>
