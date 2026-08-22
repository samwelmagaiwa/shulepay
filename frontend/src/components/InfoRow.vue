<template>
  <div style="display:flex; justify-content:space-between; align-items:baseline; gap:12px; padding:8px 0; border-bottom:1px solid rgba(0,0,0,0.08);">
    <span :style="labelStyle">{{ label }}</span>
    <span :style="valueStyle">{{ value }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

defineProps({ label: String, value: [String, Number] })

const isDark = computed(() =>
  document.documentElement.getAttribute('data-coreui-theme') === 'dark' ||
  document.documentElement.getAttribute('data-bs-theme') === 'dark' ||
  document.documentElement.getAttribute('data-theme') === 'dark' ||
  window.matchMedia('(prefers-color-scheme: dark)').matches
)

const labelStyle = computed(() => ({
  color: isDark.value ? '#94a3b8' : '#6c757d',
  flexShrink: '0',
  maxWidth: '50%',
  fontSize: '.8rem',
  fontWeight: '500',
  lineHeight: '1.4',
}))

const valueStyle = computed(() => ({
  color: isDark.value ? '#f1f5f9' : '#0d1117',
  fontWeight: '800',
  fontSize: '.9rem',
  textAlign: 'right',
  wordBreak: 'break-word',
  minWidth: '0',
  lineHeight: '1.4',
}))
</script>
