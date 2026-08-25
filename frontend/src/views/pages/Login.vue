<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const { t } = useI18n()
const auth         = useAuthStore()
const email        = ref('')
const password     = ref('')
const isLoading    = ref(false)
const errorMessage = ref('')

const schools      = ref([])
const schoolsLoaded = ref(false)

// ── Dynamic branding for login page ──────────────────────────────────────────
// Branding only — the user no longer picks a school here. With a single school we
// show its branding; with several there is no way to know which one the visitor
// belongs to before they authenticate, so fall back to the product default.
const loginBranding = computed(() => {
  const only = schools.value.length === 1 ? schools.value[0] : null
  return only?.branding ?? { app_name: 'ShulePay', app_tagline: 'nexoryaTECH', logo_url: null }
})

const loginLogoError = ref(false)
watch(loginBranding, () => { loginLogoError.value = false })

onMounted(async () => {
  try {
    const { data } = await api.get('/auth/schools')
    schools.value = data
    // Auto-select if only one school
    // Fetched for branding only — see loginBranding above.
  } catch {
    schools.value = []
  } finally {
    schoolsLoaded.value = true
  }
})

async function handleLogin() {
  errorMessage.value = ''

  if (!email.value || !password.value) {
    errorMessage.value = 'Tafadhali jaza barua pepe na neno la siri.'
    return
  }

  isLoading.value = true

  try {
    // No school_id: the backend resolves the user's school from their own record.
    await auth.login(email.value, password.value)
    window.location.href = '/dashibodi'
  } catch (e) {
    const errors = e?.response?.data?.errors
    errorMessage.value = errors?.email?.[0]
                      || e?.response?.data?.message
                      || e?.message
                      || t('auth.errorInvalid')
    isLoading.value = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-background">
      <!-- School scene illustration -->
      <svg viewBox="0 0 1440 900" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
        <!-- Sky gradient -->
        <defs>
          <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#0a2240"/>
            <stop offset="100%" stop-color="#0d3d26"/>
          </linearGradient>
          <linearGradient id="ground" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#0a3d1a"/>
            <stop offset="100%" stop-color="#062410"/>
          </linearGradient>
          <radialGradient id="sun" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#fcd116" stop-opacity="0.9"/>
            <stop offset="100%" stop-color="#f5a800" stop-opacity="0"/>
          </radialGradient>
          <filter id="glow">
            <feGaussianBlur stdDeviation="6" result="blur"/>
            <feMerge><feMergeNode in="blur"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
        </defs>

        <!-- Background sky -->
        <rect width="1440" height="900" fill="url(#sky)"/>

        <!-- Stars -->
        <circle cx="120" cy="60" r="1.5" fill="white" opacity="0.6"/>
        <circle cx="280" cy="40" r="1" fill="white" opacity="0.5"/>
        <circle cx="450" cy="80" r="1.5" fill="white" opacity="0.7"/>
        <circle cx="650" cy="30" r="1" fill="white" opacity="0.4"/>
        <circle cx="900" cy="55" r="1.5" fill="white" opacity="0.6"/>
        <circle cx="1100" cy="45" r="1" fill="white" opacity="0.5"/>
        <circle cx="1300" cy="70" r="1.5" fill="white" opacity="0.7"/>
        <circle cx="200" cy="110" r="1" fill="white" opacity="0.3"/>
        <circle cx="760" cy="90" r="1" fill="white" opacity="0.4"/>
        <circle cx="1200" cy="110" r="1.5" fill="white" opacity="0.5"/>

        <!-- Rising sun glow -->
        <ellipse cx="720" cy="420" rx="320" ry="180" fill="url(#sun)" opacity="0.25"/>

        <!-- Ground -->
        <rect x="0" y="640" width="1440" height="260" fill="url(#ground)"/>
        <!-- Grass top -->
        <ellipse cx="720" cy="640" rx="800" ry="30" fill="#0f4a20" opacity="0.8"/>

        <!-- ─── SCHOOL BUILDING (centre) ─── -->
        <!-- Main body -->
        <rect x="520" y="300" width="400" height="340" fill="#1a4a6e" rx="4"/>
        <!-- Roof / pediment -->
        <polygon points="500,300 720,180 940,300" fill="#133858"/>
        <!-- Roof ridge line -->
        <line x1="720" y1="180" x2="720" y2="300" stroke="#fcd116" stroke-width="3" opacity="0.6"/>
        <!-- Flagpole -->
        <line x1="720" y1="120" x2="720" y2="182" stroke="#c8a800" stroke-width="4"/>
        <!-- Flag -->
        <rect x="720" y="120" width="60" height="36" fill="#007f3e" rx="2"/>
        <rect x="720" y="120" width="20" height="36" fill="#fcd116"/>
        <circle cx="720" cy="120" r="5" fill="#fcd116"/>
        <!-- School sign board -->
        <rect x="600" y="230" width="240" height="50" fill="#fcd116" rx="6"/>
        <text x="720" y="248" text-anchor="middle" font-size="10" font-weight="700" fill="#0d3d26" letter-spacing="1">MAGRETH PRIMARY</text>
        <text x="720" y="264" text-anchor="middle" font-size="9" font-weight="600" fill="#0d3d26">SCHOOL</text>
        <!-- Windows top row -->
        <rect x="560" y="320" width="60" height="50" fill="#a8d8f0" rx="4" opacity="0.7"/>
        <rect x="660" y="320" width="60" height="50" fill="#a8d8f0" rx="4" opacity="0.7"/>
        <rect x="760" y="320" width="60" height="50" fill="#fcd116" rx="4" opacity="0.5"/>
        <rect x="860" y="320" width="60" height="50" fill="#a8d8f0" rx="4" opacity="0.7"/>
        <!-- Window cross bars -->
        <line x1="590" y1="320" x2="590" y2="370" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="560" y1="345" x2="620" y2="345" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="690" y1="320" x2="690" y2="370" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="660" y1="345" x2="720" y2="345" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="790" y1="320" x2="790" y2="370" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="760" y1="345" x2="820" y2="345" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="890" y1="320" x2="890" y2="370" stroke="#1a4a6e" stroke-width="2"/>
        <line x1="860" y1="345" x2="920" y2="345" stroke="#1a4a6e" stroke-width="2"/>
        <!-- Windows bottom row -->
        <rect x="560" y="400" width="60" height="50" fill="#a8d8f0" rx="4" opacity="0.7"/>
        <rect x="860" y="400" width="60" height="50" fill="#a8d8f0" rx="4" opacity="0.7"/>
        <!-- Main door -->
        <rect x="670" y="490" width="100" height="150" fill="#0d2d45" rx="6"/>
        <rect x="690" y="510" width="28" height="60" fill="#a8d8f0" rx="3" opacity="0.5"/>
        <rect x="722" y="510" width="28" height="60" fill="#a8d8f0" rx="3" opacity="0.5"/>
        <!-- Door handle -->
        <circle cx="718" cy="575" r="4" fill="#fcd116"/>
        <!-- Steps -->
        <rect x="650" y="635" width="140" height="8" fill="#133858" rx="2"/>
        <rect x="660" y="628" width="120" height="8" fill="#1a4a6e" rx="2"/>

        <!-- ─── LEFT WING / CLASSROOM ─── -->
        <rect x="280" y="390" width="240" height="250" fill="#15405e" rx="4"/>
        <rect x="300" y="420" width="55" height="45" fill="#a8d8f0" rx="3" opacity="0.7"/>
        <rect x="375" y="420" width="55" height="45" fill="#a8d8f0" rx="3" opacity="0.7"/>
        <rect x="450" y="420" width="55" height="45" fill="#fcd116" rx="3" opacity="0.4"/>
        <line x1="327" y1="420" x2="327" y2="465" stroke="#15405e" stroke-width="2"/>
        <line x1="300" y1="442" x2="355" y2="442" stroke="#15405e" stroke-width="2"/>
        <line x1="402" y1="420" x2="402" y2="465" stroke="#15405e" stroke-width="2"/>
        <line x1="375" y1="442" x2="430" y2="442" stroke="#15405e" stroke-width="2"/>
        <!-- Left wing door -->
        <rect x="360" y="530" width="80" height="110" fill="#0d2d45" rx="4"/>
        <rect x="365" y="540" width="32" height="50" fill="#a8d8f0" rx="2" opacity="0.4"/>
        <rect x="400" y="540" width="32" height="50" fill="#a8d8f0" rx="2" opacity="0.4"/>

        <!-- ─── RIGHT WING / ADMIN ─── -->
        <rect x="920" y="390" width="240" height="250" fill="#15405e" rx="4"/>
        <rect x="940" y="420" width="55" height="45" fill="#a8d8f0" rx="3" opacity="0.7"/>
        <rect x="1015" y="420" width="55" height="45" fill="#fcd116" rx="3" opacity="0.4"/>
        <rect x="1090" y="420" width="55" height="45" fill="#a8d8f0" rx="3" opacity="0.7"/>
        <line x1="967" y1="420" x2="967" y2="465" stroke="#15405e" stroke-width="2"/>
        <line x1="940" y1="442" x2="995" y2="442" stroke="#15405e" stroke-width="2"/>
        <line x1="1042" y1="420" x2="1042" y2="465" stroke="#15405e" stroke-width="2"/>
        <line x1="1015" y1="442" x2="1070" y2="442" stroke="#15405e" stroke-width="2"/>
        <!-- Right wing door -->
        <rect x="1000" y="530" width="80" height="110" fill="#0d2d45" rx="4"/>
        <rect x="1005" y="540" width="32" height="50" fill="#a8d8f0" rx="2" opacity="0.4"/>
        <rect x="1040" y="540" width="32" height="50" fill="#a8d8f0" rx="2" opacity="0.4"/>

        <!-- ─── TREES ─── -->
        <!-- Left trees -->
        <rect x="190" y="560" width="12" height="80" fill="#5c3a1e"/>
        <ellipse cx="196" cy="540" rx="38" ry="45" fill="#0b5e2e"/>
        <ellipse cx="196" cy="525" rx="25" ry="30" fill="#0f7a3c"/>

        <rect x="105" y="570" width="10" height="70" fill="#5c3a1e"/>
        <ellipse cx="110" cy="550" rx="30" ry="38" fill="#0b5e2e"/>
        <ellipse cx="110" cy="537" rx="20" ry="26" fill="#0f7a3c"/>

        <!-- Right trees -->
        <rect x="1238" y="560" width="12" height="80" fill="#5c3a1e"/>
        <ellipse cx="1244" cy="540" rx="38" ry="45" fill="#0b5e2e"/>
        <ellipse cx="1244" cy="525" rx="25" ry="30" fill="#0f7a3c"/>

        <rect x="1325" y="570" width="10" height="70" fill="#5c3a1e"/>
        <ellipse cx="1330" cy="550" rx="30" ry="38" fill="#0b5e2e"/>
        <ellipse cx="1330" cy="537" rx="20" ry="26" fill="#0f7a3c"/>

        <!-- Middle trees -->
        <rect x="248" y="580" width="10" height="60" fill="#5c3a1e"/>
        <ellipse cx="253" cy="562" rx="28" ry="34" fill="#0b5e2e"/>

        <rect x="1182" y="580" width="10" height="60" fill="#5c3a1e"/>
        <ellipse cx="1187" cy="562" rx="28" ry="34" fill="#0b5e2e"/>

        <!-- ─── PATH / WALKWAY ─── -->
        <polygon points="640,640 800,640 760,900 680,900" fill="#1a5c30" opacity="0.5"/>
        <line x1="720" y1="640" x2="720" y2="900" stroke="#fcd116" stroke-width="2" stroke-dasharray="12,8" opacity="0.4"/>

        <!-- ─── FENCE ─── -->
        <rect x="100" y="630" width="1240" height="8" fill="#a07830" opacity="0.8"/>
        <!-- Fence posts left side -->
        <rect x="100" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="160" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="220" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="280" y="610" width="8" height="28" fill="#c8a850"/>
        <!-- Fence posts right side -->
        <rect x="1152" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="1212" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="1272" y="610" width="8" height="28" fill="#c8a850"/>
        <rect x="1332" y="610" width="8" height="28" fill="#c8a850"/>
        <!-- Gate posts -->
        <rect x="630" y="595" width="12" height="45" fill="#c8a850"/>
        <rect x="798" y="595" width="12" height="45" fill="#c8a850"/>
        <!-- Gate top -->
        <rect x="630" y="593" width="180" height="8" fill="#fcd116" opacity="0.7" rx="3"/>

        <!-- ─── CHILDREN (simplified stick figures with uniforms) ─── -->
        <!-- Child 1 - left, walking -->
        <circle cx="360" cy="658" r="12" fill="#f5c8a0"/>
        <rect x="353" y="670" width="15" height="25" fill="#007f3e" rx="2"/>
        <rect x="356" y="695" width="5" height="20" fill="#1a3660"/>
        <rect x="364" y="695" width="5" height="20" fill="#1a3660"/>
        <line x1="353" y1="677" x2="342" y2="692" stroke="#f5c8a0" stroke-width="4" stroke-linecap="round"/>
        <line x1="368" y1="677" x2="375" y2="690" stroke="#f5c8a0" stroke-width="4" stroke-linecap="round"/>
        <!-- Backpack -->
        <rect x="369" y="673" width="10" height="16" fill="#fcd116" rx="2"/>

        <!-- Child 2 - left group -->
        <circle cx="400" cy="655" r="11" fill="#c8956a"/>
        <rect x="393" y="666" width="14" height="23" fill="#1a3660" rx="2"/>
        <rect x="396" y="689" width="5" height="18" fill="#007f3e"/>
        <rect x="404" y="689" width="5" height="18" fill="#007f3e"/>
        <line x1="393" y1="673" x2="383" y2="686" stroke="#c8956a" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="407" y1="673" x2="416" y2="685" stroke="#c8956a" stroke-width="3.5" stroke-linecap="round"/>

        <!-- Child 3 - right side -->
        <circle cx="1080" cy="658" r="12" fill="#f5c8a0"/>
        <rect x="1073" y="670" width="15" height="25" fill="#007f3e" rx="2"/>
        <rect x="1076" y="695" width="5" height="20" fill="#1a3660"/>
        <rect x="1084" y="695" width="5" height="20" fill="#1a3660"/>
        <line x1="1073" y1="677" x2="1063" y2="691" stroke="#f5c8a0" stroke-width="4" stroke-linecap="round"/>
        <line x1="1088" y1="677" x2="1097" y2="690" stroke="#f5c8a0" stroke-width="4" stroke-linecap="round"/>
        <!-- Book in hand -->
        <rect x="1097" y="685" width="12" height="9" fill="#fcd116" rx="1"/>

        <!-- Child 4 - right group -->
        <circle cx="1120" cy="660" r="11" fill="#c8956a"/>
        <rect x="1113" y="671" width="14" height="23" fill="#1a3660" rx="2"/>
        <rect x="1116" y="694" width="5" height="18" fill="#007f3e"/>
        <rect x="1124" y="694" width="5" height="18" fill="#007f3e"/>
        <line x1="1113" y1="678" x2="1102" y2="692" stroke="#c8956a" stroke-width="3.5" stroke-linecap="round"/>
        <line x1="1127" y1="678" x2="1136" y2="692" stroke="#c8956a" stroke-width="3.5" stroke-linecap="round"/>

        <!-- ─── TEACHER figure left of entrance ─── -->
        <circle cx="560" cy="648" r="14" fill="#d4956a"/>
        <rect x="549" y="662" width="22" height="30" fill="#1a3660" rx="3"/>
        <rect x="553" y="692" width="6" height="22" fill="#0d2244"/>
        <rect x="562" y="692" width="6" height="22" fill="#0d2244"/>
        <line x1="549" y1="670" x2="535" y2="688" stroke="#d4956a" stroke-width="5" stroke-linecap="round"/>
        <line x1="571" y1="670" x2="583" y2="682" stroke="#d4956a" stroke-width="5" stroke-linecap="round"/>
        <!-- clipboard -->
        <rect x="583" y="676" width="14" height="18" fill="white" rx="2" opacity="0.8"/>
        <line x1="586" y1="681" x2="595" y2="681" stroke="#1a3660" stroke-width="1.5"/>
        <line x1="586" y1="685" x2="595" y2="685" stroke="#1a3660" stroke-width="1.5"/>
        <line x1="586" y1="689" x2="592" y2="689" stroke="#1a3660" stroke-width="1.5"/>

        <!-- ─── BOOKS stack (decorative, near fence) ─── -->
        <rect x="155" y="620" width="40" height="10" fill="#fcd116" rx="2"/>
        <rect x="158" y="612" width="34" height="10" fill="#007f3e" rx="2"/>
        <rect x="161" y="604" width="28" height="10" fill="#1a3660" rx="2"/>

        <!-- ─── BENCH left yard ─── -->
        <rect x="180" y="700" width="80" height="8" fill="#8B5E3C" rx="3"/>
        <rect x="188" y="708" width="8" height="20" fill="#6B4423"/>
        <rect x="244" y="708" width="8" height="20" fill="#6B4423"/>

        <!-- ─── BENCH right yard ─── -->
        <rect x="1180" y="700" width="80" height="8" fill="#8B5E3C" rx="3"/>
        <rect x="1188" y="708" width="8" height="20" fill="#6B4423"/>
        <rect x="1244" y="708" width="8" height="20" fill="#6B4423"/>

        <!-- ─── Floating coins/money particles ─── -->
        <circle cx="80" cy="300" r="14" fill="none" stroke="#fcd116" stroke-width="2.5" opacity="0.35"/>
        <text x="80" y="305" text-anchor="middle" font-size="11" fill="#fcd116" opacity="0.35">$</text>
        <circle cx="1370" cy="280" r="14" fill="none" stroke="#fcd116" stroke-width="2.5" opacity="0.35"/>
        <text x="1370" y="285" text-anchor="middle" font-size="11" fill="#fcd116" opacity="0.35">$</text>
        <circle cx="1400" cy="420" r="10" fill="none" stroke="#fcd116" stroke-width="2" opacity="0.25"/>
        <text x="1400" y="425" text-anchor="middle" font-size="9" fill="#fcd116" opacity="0.25">$</text>
        <circle cx="50" cy="430" r="10" fill="none" stroke="#fcd116" stroke-width="2" opacity="0.25"/>
        <text x="50" y="435" text-anchor="middle" font-size="9" fill="#fcd116" opacity="0.25">$</text>

        <!-- ─── Light rays from above (subtle) ─── -->
        <line x1="720" y1="0" x2="500" y2="640" stroke="#fcd116" stroke-width="1" opacity="0.04"/>
        <line x1="720" y1="0" x2="720" y2="640" stroke="#fcd116" stroke-width="1.5" opacity="0.06"/>
        <line x1="720" y1="0" x2="940" y2="640" stroke="#fcd116" stroke-width="1" opacity="0.04"/>

        <!-- Horizon glow -->
        <ellipse cx="720" cy="640" rx="600" ry="40" fill="#fcd116" opacity="0.04"/>
      </svg>
    </div>
    <div class="login-overlay"></div>

    <div class="login-card-wrapper">
      <div class="login-card">
        <div class="login-header">
          <div class="logo-wrapper">
            <!-- Dynamic logo: school-specific image if available, fallback to SVG -->
            <img
              v-if="loginBranding.logo_url && !loginLogoError"
              :src="loginBranding.logo_url"
              class="brand-logo"
              :alt="loginBranding.app_name"
              style="border-radius:50%; object-fit:contain; background:rgba(255,255,255,.12);"
              @error="loginLogoError = true"
            />
            <svg v-else viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="brand-logo" aria-label="ShulePay logo">
              <circle cx="50" cy="50" r="48" fill="#007f3e"/>
              <rect x="22" y="35" width="26" height="34" rx="3" fill="white" opacity="0.95"/>
              <rect x="25" y="38" width="20" height="2" rx="1" fill="#007f3e"/>
              <rect x="25" y="43" width="20" height="2" rx="1" fill="#007f3e"/>
              <rect x="25" y="48" width="14" height="2" rx="1" fill="#007f3e"/>
              <rect x="52" y="35" width="26" height="34" rx="3" fill="white" opacity="0.95"/>
              <rect x="55" y="38" width="20" height="2" rx="1" fill="#007f3e"/>
              <rect x="55" y="43" width="20" height="2" rx="1" fill="#007f3e"/>
              <rect x="55" y="48" width="14" height="2" rx="1" fill="#007f3e"/>
              <rect x="47" y="33" width="6" height="38" rx="1" fill="#fcd116"/>
              <circle cx="72" cy="28" r="12" fill="#fcd116"/>
              <text x="72" y="33" text-anchor="middle" font-size="13" font-weight="bold" fill="#007f3e">$</text>
              <text x="50" y="82" text-anchor="middle" font-size="9" font-weight="700" fill="white" letter-spacing="1">SHULEPAY</text>
            </svg>
          </div>
          <h2 class="brand-name">
            {{ loginBranding.app_name }}
            <span v-if="loginBranding.app_tagline" class="brand-tagline">— {{ loginBranding.app_tagline }}</span>
          </h2>
          <h1 class="welcome-text">{{ t('auth.title') }}</h1>
          <p class="subtitle">{{ t('auth.subtitle') }}</p>
        </div>

        <form @submit.prevent="handleLogin" class="login-form">
          <div v-if="errorMessage" class="alert alert-danger mb-4">
            {{ errorMessage }}
          </div>

          <div class="form-group mb-4">
            <label class="form-label">{{ t('auth.emailLabel') }}</label>
            <div class="input-wrapper">
              <input
                v-model="email"
                type="email"
                :placeholder="t('auth.emailPlaceholder')"
                required
                autocomplete="email"
                class="form-input"
                :disabled="isLoading"
              />
            </div>
          </div>

          <div class="form-group mb-4">
            <label class="form-label">{{ t('auth.passwordLabel') }}</label>
            <div class="input-wrapper">
              <input
                v-model="password"
                type="password"
                :placeholder="t('auth.passwordPlaceholder')"
                required
                autocomplete="current-password"
                class="form-input"
                :disabled="isLoading"
              />
            </div>
          </div>

          <!-- No school selector here by design.
               The school a user belongs to is already on their account, and the
               backend returns school_id / accessible_school_ids on login. Asking
               at this point was redundant for single-school staff, impossible for
               parents (whose school_id is null, so canAccessSchool() rejected every
               option and locked them out), and any "which school is this email in?"
               hint before authentication would leak account existence and role.
               Multi-school users pick their school from the header switcher after
               logging in. -->

          <button type="submit" class="submit-btn" :disabled="isLoading">
            <span v-if="!isLoading">{{ t('auth.login') }}</span>
            <div v-else class="luxury-loader"></div>
          </button>
        </form>

        <div class="login-footer">
          <p>&copy; {{ new Date().getFullYear() }} {{ t('auth.footer') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.login-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: linear-gradient(160deg, #0a2a1a 0%, #0d3d26 40%, #1a3660 100%);
}
.login-background {
  position: absolute; inset: 0;
  z-index: 1;
}
.login-background svg {
  width: 100%; height: 100%;
  object-fit: cover;
}
.login-overlay {
  position: absolute; inset: 0;
  background: linear-gradient(135deg, rgba(10,42,26,.55) 0%, rgba(26,54,96,.45) 100%);
  z-index: 2;
}
.login-card-wrapper {
  position: relative; z-index: 10;
  width: 100%; max-width: 460px; padding: 20px;
  animation: fadeIn .6s ease-out;
}
.login-card {
  background: rgba(255,255,255,.1);
  backdrop-filter: blur(20px);
  border-radius: 24px;
  border: 1px solid rgba(255,255,255,.2);
  padding: 3rem 2.5rem;
  box-shadow: 0 25px 50px rgba(0,0,0,.5);
}
.login-header { text-align: center; margin-bottom: 2rem; }
.logo-wrapper {
  width: 90px; height: 90px;
  margin: 0 auto 1.2rem;
}
.brand-logo { width: 90px; height: 90px; }
.brand-name {
  color: #fcd116; font-size: 1rem; font-weight: 700;
  margin-bottom: .4rem; word-break: break-word;
}
.brand-tagline { font-size: .75rem; opacity: .8; font-weight: 600; }
.welcome-text { color: white; font-size: 1.6rem; font-weight: 700; margin-bottom: .5rem; }
.subtitle { color: rgba(255,255,255,.7); font-size: .9rem; }
.form-label { color: white; font-weight: 500; font-size: .9rem; margin-bottom: .4rem; display: block; }
.input-wrapper { position: relative; }
.form-input {
  width: 100%; padding: .9rem 1.2rem;
  background: rgba(0,0,0,.3);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 12px; color: white; font-size: 1rem;
  outline: none; transition: border-color .2s, box-shadow .2s;
}
.form-input::placeholder { color: rgba(255,255,255,.4); }
.form-input:focus {
  border-color: #007f3e;
  box-shadow: 0 0 0 3px rgba(0,127,62,.25);
}
.form-input:disabled { opacity: .6; cursor: not-allowed; }
.submit-btn {
  width: 100%; padding: 1rem;
  background: linear-gradient(135deg, #007f3e, #005c3e);
  color: white; border: none; border-radius: 14px;
  font-size: 1.05rem; font-weight: 600; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 8px 20px rgba(0,127,62,.35);
  transition: transform .2s, box-shadow .2s;
}
.submit-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(0,127,62,.45);
}
.submit-btn:disabled { opacity: .7; cursor: wait; }
.luxury-loader {
  width: 22px; height: 22px;
  border: 3px solid rgba(255,255,255,.3);
  border-top-color: white; border-radius: 50%;
  animation: spin 1s linear infinite;
}
.alert { padding: .8rem 1rem; border-radius: 10px; font-size: .9rem; }
.alert-danger {
  background: rgba(220,53,69,.15);
  border: 1px solid rgba(220,53,69,.3);
  color: #ff6b6b;
}
.login-footer { margin-top: 2rem; text-align: center; color: rgba(255,255,255,.35); font-size: .75rem; }
.input-error { border-color: rgba(220,53,69,.6) !important; box-shadow: 0 0 0 3px rgba(220,53,69,.2) !important; }
.field-error { color: #ff6b6b; font-size: .82rem; margin-top: .3rem; }
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 480px) { .login-card { padding: 2rem 1.25rem; } }
</style>
