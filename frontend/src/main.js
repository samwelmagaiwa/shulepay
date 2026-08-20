import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import CoreuiVue from '@coreui/vue'
import CIcon from '@coreui/icons-vue'
import { iconsSet as icons } from '@/assets/icons'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { i18n } from '@/i18n'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(CoreuiVue)
app.use(i18n)
app.provide('icons', icons)
app.component('CIcon', CIcon)

// Restore auth state from localStorage (non-blocking)
const auth = useAuthStore()
const branding = useBrandingStore()

if (auth.token && !auth.user) {
  auth.fetchMe().catch(() => auth.logout())
}
// Load branding if logged in (non-blocking; falls back to cached localStorage values)
if (auth.token) {
  branding.fetchBranding()
}

app.mount('#app')
