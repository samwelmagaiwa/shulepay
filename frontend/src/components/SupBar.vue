<template>
  <!-- The tabs and the page's controls on ONE row that stays put while the data scrolls (same bar as /wasambazaji). -->
  <div class="sup-bar d-flex flex-wrap align-items-center gap-2" :style="{ top: barTop + 'px' }">
    <CNav v-if="tabs.length" variant="tabs" class="flex-nowrap sup-tabs">
      <CNavItem v-for="tab in tabs" :key="tab.key">
        <CNavLink :active="modelValue === tab.key" class="text-nowrap" style="cursor:pointer;" @click="$emit('update:modelValue', tab.key)">
          {{ tab.label }}
          <CBadge v-if="tab.badge" :color="tab.badgeColor || 'warning'" class="ms-1">{{ tab.badge }}</CBadge>
        </CNavLink>
      </CNavItem>
    </CNav>
    <div class="sup-toolbar ms-auto d-flex flex-wrap align-items-center justify-content-end gap-2">
      <slot />
    </div>
  </div>
  <div class="mb-3"></div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

defineProps({
  /** [{ key, label, badge?, badgeColor? }] */
  tabs: { type: Array, default: () => [] },
  modelValue: { type: String, default: '' },
})
defineEmits(['update:modelValue'])

// The app header is sticky too; the bar sticks just under it, whatever height the header has (it differs on phones).
const barTop = ref(0)
let observer = null
function measure() {
  const header = document.querySelector('.header-sticky, .header')
  barTop.value = header ? Math.round(header.getBoundingClientRect().height) : 0
}

onMounted(() => {
  measure()
  const header = document.querySelector('.header-sticky, .header')
  if (header && 'ResizeObserver' in window) {
    observer = new ResizeObserver(measure)
    observer.observe(header)
  }
  window.addEventListener('resize', measure)
})
onBeforeUnmount(() => {
  observer?.disconnect()
  window.removeEventListener('resize', measure)
})
</script>

<style scoped>
.sup-bar {
  position: sticky;
  z-index: 1030; /* under the app header (1040), over the page content */
  background: var(--cui-body-bg, #fff);
  padding-top: 0.5rem;
  border-bottom: 1px solid var(--cui-border-color, #dee2e6);
}
/* On a phone the tabs + filters would fill the screen, so they scroll with the page there. */
@media (max-width: 767.98px) {
  .sup-bar { position: static; }
}
.sup-tabs { border-bottom: 0; flex: 0 0 auto; }
.sup-tabs .nav-link { padding: 0.45rem 0.7rem; font-size: 0.9rem; white-space: nowrap; }
.sup-toolbar { flex: 1 1 auto; padding-bottom: 0.5rem; }
</style>
