import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// 30 minutes of no interaction anywhere in the app triggers the warning;
// the user then has this long to respond before being logged out.
const IDLE_THRESHOLD_MS = 30 * 60 * 1000
const WARNING_DURATION_S = 60

// click/keydown/touchstart/mousemove/scroll all count as "the user is here" —
// deliberately not e.g. focus/blur, which fire on tab-switching rather than
// actual interaction.
const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart']

/**
 * Mounted once (in DefaultLayout, the shell every authenticated route renders
 * inside) so idle tracking applies app-wide rather than per-page.
 */
export function useIdleSession() {
  const router = useRouter()
  const auth = useAuthStore()

  const showWarning = ref(false)
  const secondsLeft = ref(WARNING_DURATION_S)

  let lastActivity = Date.now()
  let checkTimer = null
  let countdownTimer = null

  function recordActivity() {
    // Once the warning is up, only the explicit "Stay logged in" action may
    // reset the clock — otherwise the mouse merely passing over the modal's
    // backdrop (a window-level mousemove) would silently defeat the prompt.
    if (showWarning.value) return
    lastActivity = Date.now()
  }

  function stopCountdown() {
    if (countdownTimer) clearInterval(countdownTimer)
    countdownTimer = null
  }

  function startCountdown() {
    secondsLeft.value = WARNING_DURATION_S
    stopCountdown()
    countdownTimer = setInterval(() => {
      secondsLeft.value -= 1
      if (secondsLeft.value <= 0) {
        forceLogout()
      }
    }, 1000)
  }

  function checkIdle() {
    if (showWarning.value) return
    if (Date.now() - lastActivity >= IDLE_THRESHOLD_MS) {
      showWarning.value = true
      startCountdown()
    }
  }

  function stayLoggedIn() {
    stopCountdown()
    showWarning.value = false
    lastActivity = Date.now()
  }

  async function forceLogout() {
    stopCountdown()
    showWarning.value = false
    try {
      await auth.logout()
    } catch {
      // Logout should still send the user to the login screen even if the
      // network call to invalidate the token server-side fails.
    }
    router.push('/login')
  }

  onMounted(() => {
    ACTIVITY_EVENTS.forEach(evt => window.addEventListener(evt, recordActivity, { passive: true }))
    checkTimer = setInterval(checkIdle, 1000)
  })

  onUnmounted(() => {
    ACTIVITY_EVENTS.forEach(evt => window.removeEventListener(evt, recordActivity))
    if (checkTimer) clearInterval(checkTimer)
    stopCountdown()
  })

  return { showWarning, secondsLeft, stayLoggedIn, forceLogout }
}
