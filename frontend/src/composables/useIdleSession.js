import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

// Fallback used until the configured policy loads (and permanently if the
// request fails) — must mirror SessionSettingController::DEFAULTS on the
// backend so behavior never silently regresses on a network hiccup.
const DEFAULT_IDLE_MINUTES = 30
const DEFAULT_WARNING_SECONDS = 60

// Sanity bounds mirroring the backend's own validation (SessionSettingController
// MIN/MAX_*) — a malformed or tampered response is clamped rather than trusted
// outright, since this value controls when every user gets signed out.
const MIN_IDLE_MINUTES = 5
const MAX_IDLE_MINUTES = 240
const MIN_WARNING_SECONDS = 15
const MAX_WARNING_SECONDS = 300

// click/keydown/touchstart/mousemove/scroll all count as "the user is here" —
// deliberately not e.g. focus/blur, which fire on tab-switching rather than
// actual interaction.
const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart']

function clamp(value, min, max, fallback) {
  const n = Number(value)
  if (!Number.isFinite(n) || n < min || n > max) return fallback
  return n
}

/**
 * Mounted once (in DefaultLayout, the shell every authenticated route renders
 * inside) so idle tracking applies app-wide rather than per-page. The
 * threshold/countdown are loaded from the admin-configurable policy at
 * /session-settings; until that resolves (or if it fails) the built-in
 * defaults apply, so the feature is never silently disabled.
 */
export function useIdleSession() {
  const router = useRouter()
  const auth = useAuthStore()

  const showWarning = ref(false)
  const secondsLeft = ref(DEFAULT_WARNING_SECONDS)

  let idleThresholdMs = DEFAULT_IDLE_MINUTES * 60 * 1000
  let warningDurationS = DEFAULT_WARNING_SECONDS
  let lastActivity = Date.now()
  let checkTimer = null
  let countdownTimer = null

  async function loadPolicy() {
    try {
      const { data } = await api.get('/session-settings')
      const idleMinutes = clamp(data?.idle_minutes, MIN_IDLE_MINUTES, MAX_IDLE_MINUTES, DEFAULT_IDLE_MINUTES)
      const warningSeconds = clamp(data?.warning_seconds, MIN_WARNING_SECONDS, MAX_WARNING_SECONDS, DEFAULT_WARNING_SECONDS)
      idleThresholdMs = idleMinutes * 60 * 1000
      warningDurationS = warningSeconds
    } catch {
      // Keep the built-in defaults — a user should never end up with no
      // idle timeout at all just because this one request failed.
    }
  }

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
    secondsLeft.value = warningDurationS
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
    if (Date.now() - lastActivity >= idleThresholdMs) {
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

  onMounted(async () => {
    await loadPolicy()
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
