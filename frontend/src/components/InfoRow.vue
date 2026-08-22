<template>
  <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.1);">
    <span style="color:#6c757d; font-size:.8rem; font-weight:500; flex-shrink:0; max-width:50%;">{{ label }}</span>
    <span style="color:#000000; font-weight:900; font-size:.92rem; text-align:right; word-break:break-word; min-width:0;">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
