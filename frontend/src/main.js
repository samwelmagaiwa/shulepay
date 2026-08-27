import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import CoreuiVue from '@coreui/vue'
import CIcon from '@coreui/icons-vue'
import { iconsSet as icons } from '@/assets/icons'
import { useAuthStore } from '@/stores/auth'
import { useBrandingStore } from '@/stores/branding'
import { useSchoolStore } from '@/stores/school'
import { i18n } from '@/i18n'
import { installModalGuard } from '@/utils/modalGuard'

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
const school = useSchoolStore()

if (auth.token && !auth.user) {
  auth.fetchMe().catch(() => auth.logout())
}
// Load branding and school list if logged in (non-blocking)
if (auth.token) {
  branding.watchSchool()          // installs watcher; fires immediately for current activeSchoolId
  school.fetchSchools(auth.user?.school_id)
}

// CoreUI's CModal can leave an invisible full-screen backdrop behind and a
// permanent scroll lock on <body>, which reads to the user as "the UI froze".
// See utils/modalGuard.js.
installModalGuard(router)

app.mount('#app')
