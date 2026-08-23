<template>
  <div class="loading-overlay">
    <div class="loader-wrap">
      <svg class="ring-svg" viewBox="0 0 220 220" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <filter id="glow">
            <feGaussianBlur stdDeviation="3" result="coloredBlur"/>
            <feMerge>
              <feMergeNode in="coloredBlur"/>
              <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </defs>
        <!-- Tick segments -->
        <g v-for="i in 60" :key="i"
           :transform="`rotate(${(i-1)*6}, 110, 110)`"
           :opacity="tickOpacity(i)"
           filter="url(#glow)">
          <rect
            :x="108"
            :y="14"
            :width="4"
            :height="tickHeight(i)"
            :rx="2"
            :fill="tickFill(i)"
          />
        </g>
      </svg>

      <div class="center-text">
        <div class="pct">{{ displayPct }}%</div>
        <div class="label">{{ t('common.loading') }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()

const displayPct = ref(0)
let timer = null

onMounted(() => {
  timer = setInterval(() => {
    if (displayPct.value < 90) {
      displayPct.value += Math.ceil(Math.random() * 3)
      if (displayPct.value > 90) displayPct.value = 90
    }
  }, 120)
})

onUnmounted(() => clearInterval(timer))

const TOTAL = 60

function tickOpacity(i) {
  const active = Math.round((displayPct.value / 100) * TOTAL)
  return i <= active ? 1 : 0.15
}

function tickHeight(i) {
  const active = Math.round((displayPct.value / 100) * TOTAL)
  if (i <= active) return i % 5 === 0 ? 16 : 11
  return i % 5 === 0 ? 14 : 9
}

function tickFill(i) {
  const active = Math.round((displayPct.value / 100) * TOTAL)
  return i <= active ? '#00ff41' : '#1a4a1a'
}
</script>

<style scoped>
.loading-overlay {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: #000;
  display: flex;
  align-items: center;
  justify-content: center;
}

.loader-wrap {
  position: relative;
  width: min(260px, 80vw);
  height: min(260px, 80vw);
}

.ring-svg {
  width: 100%;
  height: 100%;
  display: block;
}

.center-text {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.pct {
  font-family: 'Courier New', monospace;
  font-size: clamp(28px, 8vw, 40px);
  font-weight: 700;
  color: #00ff41;
  text-shadow: 0 0 10px #00ff41, 0 0 24px #00cc33;
  line-height: 1;
}

.label {
  font-family: 'Courier New', monospace;
  font-size: clamp(11px, 3vw, 14px);
  font-weight: 600;
  letter-spacing: 4px;
  color: #00cc33;
  text-shadow: 0 0 8px #00cc33;
}
</style>
