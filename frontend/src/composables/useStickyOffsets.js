import { ref, onMounted, onUnmounted } from 'vue'

/**
 * Live heights for a list page's pinned areas: the app header (which can wrap
 * to two rows) and the page's own sticky bar (which wraps on narrow screens).
 * Bind `top: headerH` on the bar and `--grid-head-top: headerH + barH` on the
 * grid so the column headers pin directly beneath it.
 */
export function useStickyOffsets() {
  const stickyBar = ref(null)
  const headerH = ref(0)
  const barH = ref(0)
  let obs = null

  function measure() {
    headerH.value = document.querySelector('.header')?.offsetHeight || 0
    barH.value = stickyBar.value?.offsetHeight || 0
  }

  onMounted(() => {
    measure()
    obs = new ResizeObserver(measure)
    const hdr = document.querySelector('.header')
    if (hdr) obs.observe(hdr)
    if (stickyBar.value) obs.observe(stickyBar.value)
  })
  onUnmounted(() => obs?.disconnect())

  return { stickyBar, headerH, barH }
}
