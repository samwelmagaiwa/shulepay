<template>
  <div class="xg-stats">
    <div v-for="(c, i) in cards" :key="i" class="xg-stat" :class="`xg-stat--${c.tone || 'gray'}`" :title="c.title">
      <div class="xg-stat-label">{{ c.label }}</div>
      <div class="xg-stat-value">{{ c.value }}<small v-if="c.sub"> · {{ c.sub }}</small></div>
    </div>
  </div>
</template>

<script setup>
// Summary cards on top of a list page: [{ label, value, tone: gray|red|green|amber|blue, sub?, title? }]
defineProps({ cards: { type: Array, required: true } })
</script>
