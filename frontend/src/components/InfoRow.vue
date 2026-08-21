<template>
  <div style="display:flex; justify-content:space-between; align-items:center; gap:8px; padding:7px 0; font-size:.875rem; border-bottom:1px solid #dee2e6;">
    <span style="color:#6c757d; flex-shrink:0; max-width:55%; font-weight:400;">{{ label }}</span>
    <span style="color:#111 !important; font-weight:700; text-align:right; word-break:break-word; min-width:0;">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
