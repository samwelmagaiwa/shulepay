<template>
  <div style="display:flex; justify-content:space-between; align-items:baseline; gap:16px; padding:10px 0; border-bottom:1px solid rgba(128,128,128,0.15);">
    <span style="color:#8a94a6; font-size:.78rem; font-weight:500; letter-spacing:.02em; flex-shrink:0; max-width:45%; text-transform:uppercase;">{{ label }}</span>
    <span style="color:#1a1a2e; font-weight:700; font-size:.93rem; text-align:right; word-break:break-word; min-width:0; letter-spacing:-.01em;">{{ value }}</span>
  </div>
</template>
<script setup>
defineProps({ label: String, value: [String, Number] })
</script>
