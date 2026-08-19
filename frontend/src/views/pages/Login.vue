<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth         = useAuthStore()
const email        = ref('')
const password     = ref('')
const isLoading    = ref(false)
const errorMessage = ref('')

async function handleLogin() {
  if (!email.value || !password.value) {
    errorMessage.value = 'Tafadhali jaza barua pepe na neno la siri.'
    return
  }

  isLoading.value    = true
  errorMessage.value = ''

  try {
    await auth.login(email.value, password.value)

    // Use hard redirect so the app boots fresh with the token already in localStorage
    window.location.href = '/dashibodi'
  } catch (e) {
    const msg = e?.response?.data?.errors?.email?.[0]
             || e?.response?.data?.message
             || e?.message
             || t('auth.errorInvalid')
    errorMessage.value = msg
    isLoading.value    = false
  }
}
</script>

<template>
  <div class="login-container">
    <div class="login-background"></div>
    <div class="login-overlay"></div>

    <div class="login-card-wrapper">
      <div class="login-card">
        <div class="login-header">
          <div class="logo-wrapper">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" class="brand-logo" aria-label="ShulePay logo">
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
          <h2 class="brand-name">ShulePay — nexoryaTECH</h2>
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

          <div class="form-group mb-5">
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
  background-color: #000;
}
.login-background {
  position: absolute; inset: 0;
  background: url('@/assets/images/login-bg.png') no-repeat center center / cover;
  filter: brightness(0.4);
  z-index: 1;
}
.login-overlay {
  position: absolute; inset: 0;
  background: radial-gradient(circle at center, rgba(0,48,130,.2), rgba(0,127,62,.4));
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
  color: #fcd116; font-size: .85rem; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase; margin-bottom: .4rem;
}
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
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
@media (max-width: 480px) { .login-card { padding: 2rem 1.25rem; } }
</style>
