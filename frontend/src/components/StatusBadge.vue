<template>
  <CBadge :color="color">{{ label }}</CBadge>
</template>
<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const props = defineProps({ status: String })

const colorMap = {
  paid:        'success',
  partial:     'warning',
  unpaid:      'danger',
  active:      'success',
  sponsored:   'info',
  half_sponsored: 'info',
  orphaned:    'warning',
  transferred: 'info',
  graduated:   'primary',
  dropped:     'secondary',
  pending:     'warning',
  approved:    'success',
  rejected:    'danger',
  completed:   'success',
  draft:       'secondary',
  closed:      'dark',
  in_use:      'primary',
  under_repair:'warning',
}

const color = computed(() => colorMap[props.status] ?? 'secondary')
const label = computed(() => {
  const key = `statusBadge.${props.status}`
  const val = t(key)
  return val === key ? (props.status ?? '') : val
})
</script>
