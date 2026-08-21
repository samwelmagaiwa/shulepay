<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useColorModes } from '@coreui/vue'
import { useI18n } from 'vue-i18n'
import { setLocale } from '@/i18n'
import AppHeaderDropdownAccnt from '@/components/AppHeaderDropdownAccnt.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { useBrandingStore } from '@/stores/branding'
import { useSchoolStore } from '@/stores/school'
import { useDashboardStore } from '@/stores/dashboard'

const auth          = useAuthStore()
const branding      = useBrandingStore()
const schoolStore   = useSchoolStore()
const dashboard     = useDashboardStore()
const router        = useRouter()
const route         = useRoute()
const notifications = useNotificationsStore()

// Active-group detection for dropdown toggles
const isStudentsActive  = computed(() => ['/wanafunzi', '/walezi', '/wanafunzi/clearance', '/admin/bulk-import'].some(p => route.path.startsWith(p)))
const isFinanceActive   = computed(() => ['/ada-madeni', '/installments', '/malipo', '/ada'].some(p => route.path.startsWith(p)))
const isExpensesActive  = computed(() => ['/matumizi', '/payroll', '/wasambazaji', '/mali', '/bajeti'].some(p => route.path.startsWith(p)))
const isSchuleActive    = computed(() => ['/mahudhurio', '/usafiri', '/inventory'].some(p => route.path.startsWith(p)))
const isAdminActive     = computed(() => ['/admin/schools', '/admin/wafanyakazi', '/bajeti', '/admin/rollover', '/admin/terms', '/admin/academic-years', '/admin/branding', '/audit', '/superadmin'].some(p => route.path.startsWith(p)))
const { t, locale } = useI18n()
const { colorMode, setColorMode } = useColorModes('shulepay-theme')

notifications.startPolling()

const headerClass = ref('mb-0 p-0')
const mobileNavOpen = ref(false)

if (typeof window !== 'undefined') {
  document.addEventListener('scroll', () => {
    headerClass.value = document.documentElement.scrollTop > 0 ? 'mb-0 p-0 shadow-sm' : 'mb-0 p-0'
  })
}

// School switcher — visible whenever the user has access to more than one school
const showSchoolSwitcher = computed(
  () => schoolStore.schools.length > 1,
)

function switchSchool(id) {
  schoolStore.setActive(id)   // branding watcher fires automatically
  dashboard.fetchStats()
}

function switchLang(lang) {
  setLocale(lang)
}

function go(path) {
  router.push(path)
  mobileNavOpen.value = false
}
</script>

<template>
  <!-- Mobile Offcanvas Nav -->
  <COffcanvas placement="start" :visible="mobileNavOpen" @hide="mobileNavOpen=false">
    <COffcanvasHeader class="border-bottom">
      <COffcanvasTitle class="fw-bold" style="color:#007f3e;">{{ branding.appName }}</COffcanvasTitle>
      <CCloseButton @click="mobileNavOpen=false" />
    </COffcanvasHeader>
    <COffcanvasBody class="p-2">
      <!-- Mobile school selector -->
      <div v-if="showSchoolSwitcher" class="px-2 pb-2 mb-1 border-bottom">
        <div class="text-muted small fw-bold text-uppercase mb-1" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.selectSchool') }}</div>
        <div class="d-flex flex-column gap-1">
          <button
            v-for="s in schoolStore.schools"
            :key="s.id"
            type="button"
            class="btn btn-sm text-start d-flex align-items-center gap-2"
            :class="schoolStore.activeSchoolId === s.id ? 'btn-success' : 'btn-ghost-secondary'"
            @click="switchSchool(s.id); mobileNavOpen=false"
          >
            <span>🏫</span>
            <span class="flex-grow-1">{{ s.name }}</span>
            <span v-if="schoolStore.activeSchoolId === s.id" class="ms-auto">✓</span>
          </button>
        </div>
      </div>
      <nav class="d-flex flex-column gap-1">
        <RouterLink class="btn btn-ghost-secondary text-start" to="/dashibodi" @click="mobileNavOpen=false">
          {{ t('nav.dashboard') }}
        </RouterLink>

        <div class="text-muted small fw-bold px-2 mt-2 mb-1 text-uppercase" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.students') }}</div>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/wanafunzi" @click="mobileNavOpen=false">{{ t('nav.studentList') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/walezi" @click="mobileNavOpen=false">{{ t('nav.guardians') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/wanafunzi/clearance" @click="mobileNavOpen=false">{{ t('nav.clearance') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/bulk-import" @click="mobileNavOpen=false">{{ t('nav.bulkImport') }}</RouterLink>

        <div class="text-muted small fw-bold px-2 mt-2 mb-1 text-uppercase" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.finance') }}</div>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/ada-madeni" @click="mobileNavOpen=false">{{ t('nav.invoices') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/installments" @click="mobileNavOpen=false">{{ t('nav.installments') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/malipo/refunds" @click="mobileNavOpen=false">{{ t('nav.refunds') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/ada/muundo" @click="mobileNavOpen=false">{{ t('nav.feeStructures') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/malipo/rekodi" @click="mobileNavOpen=false">{{ t('nav.paymentRecord') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/admin/terms" @click="mobileNavOpen=false">📅 {{ t('nav.terms') }}</RouterLink>

        <div class="text-muted small fw-bold px-2 mt-2 mb-1 text-uppercase" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.expenses') }}</div>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/matumizi" @click="mobileNavOpen=false">{{ t('nav.expenses') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/matumizi/petty-cash" @click="mobileNavOpen=false">{{ t('nav.pettyCash') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/payroll" @click="mobileNavOpen=false">{{ t('nav.payroll') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/payroll/employees" @click="mobileNavOpen=false">{{ t('nav.employees') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/wasambazaji" @click="mobileNavOpen=false">{{ t('nav.suppliers') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/mali" @click="mobileNavOpen=false">{{ t('nav.assets') }}</RouterLink>

        <div class="text-muted small fw-bold px-2 mt-2 mb-1 text-uppercase" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.schoolLife') }}</div>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/mahudhurio" @click="mobileNavOpen=false">📋 {{ t('nav.attendance') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/usafiri" @click="mobileNavOpen=false">🚌 {{ t('nav.transport') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/inventory" @click="mobileNavOpen=false">📦 {{ t('nav.inventory') }}</RouterLink>
        <RouterLink class="btn btn-ghost-secondary text-start position-relative" to="/arifa" @click="mobileNavOpen=false">
          🔔 {{ t('nav.notifications') }}
          <CBadge v-if="notifications.unreadCount > 0" color="danger" class="position-absolute top-0 end-0">{{ notifications.unreadCount }}</CBadge>
        </RouterLink>

        <div class="text-muted small fw-bold px-2 mt-2 mb-1 text-uppercase" style="font-size:.65rem; letter-spacing:.05em;">{{ t('nav.other') }}</div>
        <RouterLink class="btn btn-ghost-secondary text-start" to="/ripoti" @click="mobileNavOpen=false">{{ t('nav.reports') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/schools" @click="mobileNavOpen=false">{{ t('nav.schools') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/wafanyakazi" @click="mobileNavOpen=false">👥 {{ t('nav.staff') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/audit" @click="mobileNavOpen=false">{{ t('nav.audit') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/branding" @click="mobileNavOpen=false">🎨 {{ t('nav.branding') }}</RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" class="btn btn-ghost-secondary text-start" to="/superadmin/users" @click="mobileNavOpen=false">👤 {{ t('nav.userManagement') }}</RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" class="btn btn-ghost-secondary text-start" to="/superadmin/roles" @click="mobileNavOpen=false">🔐 {{ t('nav.rolesPermissions') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/bajeti" @click="mobileNavOpen=false">{{ t('nav.budgets') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/academic-years" @click="mobileNavOpen=false">📅 {{ t('nav.academicYears') }}</RouterLink>
        <RouterLink v-if="auth.isOwner" class="btn btn-ghost-secondary text-start" to="/admin/rollover" @click="mobileNavOpen=false">{{ t('nav.rollover') }}</RouterLink>
      </nav>
    </COffcanvasBody>
  </COffcanvas>

  <CHeader position="sticky" :class="headerClass" style="z-index:1040; flex-direction:column;">
    <CContainer class="border-bottom px-3" fluid
      style="flex-wrap:wrap; gap:4px; min-height:56px; align-items:center;">

      <!-- Hamburger (mobile only) -->
      <CButton class="d-md-none me-2 p-1" variant="ghost" color="secondary" @click="mobileNavOpen=true" style="min-height:44px; min-width:44px;">
        <CIcon icon="cilMenu" size="lg" />
      </CButton>

      <!-- Brand -->
      <RouterLink to="/dashibodi"
        class="navbar-brand d-flex align-items-center me-4 text-decoration-none">
        <img
          v-if="branding.logoUrl"
          :src="branding.logoUrl"
          alt="Logo"
          style="height:36px; width:36px; object-fit:contain; border-radius:50%; margin-right:8px; flex-shrink:0;"
          @error="branding.logoUrl = null"
        />
        <svg v-else viewBox="0 0 40 40" width="36" height="36" style="margin-right:8px; flex-shrink:0;">
          <circle cx="20" cy="20" r="19" fill="#007f3e"/>
          <rect x="8"  y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
          <rect x="21" y="13" width="11" height="16" rx="2" fill="white" opacity=".95"/>
          <rect x="18" y="11" width="4"  height="20" rx="1" fill="#fcd116"/>
          <circle cx="29" cy="11" r="5" fill="#fcd116"/>
          <text x="29" y="14.5" text-anchor="middle" font-size="6" font-weight="bold" fill="#007f3e">$</text>
        </svg>
        <div class="d-flex flex-column lh-1">
          <span class="fw-bold" style="color:#007f3e; font-size:1.05rem;">{{ branding.appName }}</span>
          <span style="color:#003082; font-size:0.6rem; font-weight:700; letter-spacing:1px; text-transform:uppercase;">{{ branding.appTagline }}</span>
        </div>
      </RouterLink>

      <!-- Desktop Nav links -->
      <CHeaderNav class="me-auto d-none d-md-flex gap-1">
        <CNavItem>
          <RouterLink to="/dashibodi" class="nav-link fw-semibold px-2" style="font-size:.9rem;">
            {{ t('nav.dashboard') }}
          </RouterLink>
        </CNavItem>

        <!-- Wanafunzi dropdown -->
        <CDropdown v-if="auth.isAccountant || auth.isOwner || auth.isHeadmaster || auth.isHeadTeacher" variant="nav-item" :caret="false">
          <CDropdownToggle class="fw-semibold px-2" :class="{ 'nav-active': isStudentsActive }" style="font-size:.9rem;">
            {{ t('nav.students') }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:220px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem @click="router.push('/wanafunzi')" style="cursor:pointer;">
              <CIcon icon="cilPeople" class="me-2" /> {{ t('nav.studentList') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/walezi')" style="cursor:pointer;">
              <CIcon icon="cilUser" class="me-2" /> {{ t('nav.guardians') }}
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem @click="router.push('/wanafunzi/clearance')" style="cursor:pointer;">
              <CIcon icon="cilCheckAlt" class="me-2" /> {{ t('nav.clearance') }}
            </CDropdownItem>
            <CDropdownItem v-if="auth.isOwner" @click="router.push('/admin/bulk-import')" style="cursor:pointer;">
              <CIcon icon="cilCloudDownload" class="me-2" /> {{ t('nav.bulkImport') }}
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <!-- Fedha dropdown -->
        <CDropdown v-if="auth.isAccountant || auth.isOwner" variant="nav-item" :caret="false">
          <CDropdownToggle class="fw-semibold px-2" :class="{ 'nav-active': isFinanceActive }" style="font-size:.9rem;">
            {{ t('nav.finance') }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:240px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem @click="router.push('/ada-madeni')" style="cursor:pointer;">
              <CIcon icon="cilList" class="me-2" /> {{ t('nav.invoices') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/installments')" style="cursor:pointer;">
              <CIcon icon="cilCalendar" class="me-2" /> {{ t('nav.installments') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/malipo/refunds')" style="cursor:pointer;">
              <CIcon icon="cilTransfer" class="me-2" /> {{ t('nav.refunds') }}
            </CDropdownItem>
            <CDropdownItem v-if="auth.isAccountant" @click="router.push('/malipo/rekodi')" style="cursor:pointer;">
              <CIcon icon="cilDollar" class="me-2" /> {{ t('nav.paymentRecord') }}
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem @click="router.push('/ada/muundo')" style="cursor:pointer;">
              <CIcon icon="cilSettings" class="me-2" /> {{ t('nav.feeStructures') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/terms')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/terms') }">
              📅 {{ t('nav.terms') }}
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <!-- Gharama dropdown -->
        <CDropdown v-if="auth.isAccountant || auth.isOwner" variant="nav-item" :caret="false">
          <CDropdownToggle class="fw-semibold px-2" :class="{ 'nav-active': isExpensesActive }" style="font-size:.9rem;">
            {{ t('nav.expenses') }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:220px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem @click="router.push('/matumizi')" style="cursor:pointer;">
              <CIcon icon="cilClipboard" class="me-2" /> {{ t('nav.expenses') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/matumizi/petty-cash')" style="cursor:pointer;">
              <CIcon icon="cilWallet" class="me-2" /> {{ t('nav.pettyCash') }}
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem @click="router.push('/payroll')" style="cursor:pointer;">
              <CIcon icon="cilDollar" class="me-2" /> {{ t('nav.payroll') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/payroll/employees')" style="cursor:pointer;">
              <CIcon icon="cilPeople" class="me-2" /> {{ t('nav.employees') }}
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem @click="router.push('/wasambazaji')" style="cursor:pointer;">
              <CIcon icon="cilBriefcase" class="me-2" /> {{ t('nav.suppliers') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/mali')" style="cursor:pointer;">
              <CIcon icon="cilBuilding" class="me-2" /> {{ t('nav.assets') }}
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <!-- Shule dropdown -->
        <CDropdown variant="nav-item" :caret="false">
          <CDropdownToggle class="fw-semibold px-2" :class="{ 'nav-active': isSchuleActive }" style="font-size:.9rem;">
            {{ t('nav.schoolLife') }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:220px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem @click="router.push('/mahudhurio')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/mahudhurio') }">
              📋 {{ t('nav.attendance') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/usafiri')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/usafiri') }">
              🚌 {{ t('nav.transport') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/inventory')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/inventory') }">
              📦 {{ t('nav.inventory') }}
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <!-- Ripoti -->
        <CNavItem v-if="auth.isAccountant || auth.isOwner || auth.isHeadmaster || auth.isHeadTeacher">
          <RouterLink to="/ripoti" class="nav-link fw-semibold px-2" style="font-size:.9rem;">
            {{ t('nav.reports') }}
          </RouterLink>
        </CNavItem>

        <!-- Usimamizi (owner only) -->
        <CDropdown v-if="auth.isOwner" variant="nav-item" :caret="false">
          <CDropdownToggle class="fw-semibold px-2" :class="{ 'nav-active': isAdminActive }" style="font-size:.9rem;">
            {{ t('nav.admin') }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:220px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem @click="router.push('/admin/schools')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/schools') }">
              <CIcon icon="cilBuilding" class="me-2" /> {{ t('nav.schools') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/wafanyakazi')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/wafanyakazi') }">
              👥 {{ t('nav.staff') }}
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem @click="router.push('/bajeti')" style="cursor:pointer;">
              <CIcon icon="cilLibrary" class="me-2" /> {{ t('nav.budgets') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/academic-years')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/academic-years') }">
              📅 {{ t('nav.academicYears') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/terms')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/terms') }">
              📋 {{ t('nav.terms') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/rollover')" style="cursor:pointer;">
              <CIcon icon="cilReload" class="me-2" /> {{ t('nav.rollover') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/audit')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/audit') }">
              <CIcon icon="cilHistory" class="me-2" /> {{ t('nav.audit') }}
            </CDropdownItem>
            <CDropdownItem @click="router.push('/admin/branding')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/admin/branding') }">
              🎨 {{ t('nav.branding') }}
            </CDropdownItem>
            <template v-if="auth.isSuperAdmin">
              <CDropdownDivider />
              <CDropdownItem @click="router.push('/superadmin/users')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/superadmin/users') }">
                👤 {{ t('nav.userManagement') }}
              </CDropdownItem>
              <CDropdownItem @click="router.push('/superadmin/roles')" style="cursor:pointer;" :class="{ 'active': route.path.startsWith('/superadmin/roles') }">
                🔐 {{ t('nav.rolesPermissions') }}
              </CDropdownItem>
            </template>
          </CDropdownMenu>
        </CDropdown>
      </CHeaderNav>

      <!-- School selector (owner / accountant / superadmin, desktop) -->
      <CDropdown
        v-if="showSchoolSwitcher"
        variant="nav-item"
        placement="bottom-end"
        class="d-none d-md-flex me-1"
      >
        <CDropdownToggle
          :caret="false"
          class="school-switcher-btn d-flex align-items-center gap-2"
        >
          <span class="school-icon">🏫</span>
          <span class="school-name">
            {{ schoolStore.activeSchool?.name ?? t('nav.selectSchool') }}
          </span>
          <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor" opacity=".6">
            <path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
          </svg>
        </CDropdownToggle>
        <CDropdownMenu style="min-width:200px; border-radius:12px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.14); padding:6px;">
          <template v-for="s in schoolStore.schools" :key="s.id">
            <CDropdownItem
              component="button"
              type="button"
              :active="schoolStore.activeSchoolId === s.id"
              class="school-option d-flex align-items-center gap-2"
              @click="switchSchool(s.id)"
            >
              <span style="font-size:.75rem; opacity:.6;">🏫</span>
              <span class="flex-grow-1">{{ s.name }}</span>
              <span v-if="schoolStore.activeSchoolId === s.id" style="color:#007f3e; font-size:.75rem;">✓</span>
            </CDropdownItem>
          </template>
        </CDropdownMenu>
      </CDropdown>

      <!-- Right controls -->
      <CHeaderNav class="ms-auto d-flex align-items-center gap-2">

        <!-- Notification Bell -->
        <RouterLink to="/arifa" class="nav-link position-relative px-2" title="Arifa">
          🔔
          <CBadge
            v-if="notifications.unreadCount > 0"
            color="danger"
            class="position-absolute top-0 start-75 translate-middle"
            style="font-size:.65rem; min-width:18px;"
          >
            {{ notifications.unreadCount }}
          </CBadge>
        </RouterLink>

        <!-- Language switcher -->
        <CDropdown variant="nav-item" placement="bottom-end">
          <CDropdownToggle :caret="false" class="d-flex align-items-center gap-1" style="font-size:.85rem; font-weight:600;">
            {{ locale === 'sw' ? '🇹🇿 SW' : '🇬🇧 EN' }}
          </CDropdownToggle>
          <CDropdownMenu style="min-width:140px; border-radius:10px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem component="button" type="button" :active="locale === 'sw'" @click="switchLang('sw')" class="d-flex align-items-center gap-2">
              🇹🇿 Kiswahili
            </CDropdownItem>
            <CDropdownItem component="button" type="button" :active="locale === 'en'" @click="switchLang('en')" class="d-flex align-items-center gap-2">
              🇬🇧 English
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <!-- Dark mode -->
        <CDropdown variant="nav-item" placement="bottom-end">
          <CDropdownToggle :caret="false">
            <CIcon v-if="colorMode === 'dark'"  icon="cilMoon"     size="lg" />
            <CIcon v-else-if="colorMode === 'light'" icon="cilSun" size="lg" />
            <CIcon v-else icon="cilContrast" size="lg" />
          </CDropdownToggle>
          <CDropdownMenu style="border-radius:10px; border:none; box-shadow:0 8px 24px rgba(0,0,0,.12);">
            <CDropdownItem component="button" type="button" :active="colorMode === 'light'" @click="setColorMode('light')" class="d-flex align-items-center">
              <CIcon class="me-2" icon="cilSun" size="lg" /> {{ t('common.themeLight') }}
            </CDropdownItem>
            <CDropdownItem component="button" type="button" :active="colorMode === 'dark'" @click="setColorMode('dark')" class="d-flex align-items-center">
              <CIcon class="me-2" icon="cilMoon" size="lg" /> {{ t('common.themeDark') }}
            </CDropdownItem>
            <CDropdownItem component="button" type="button" :active="colorMode === 'auto'" @click="setColorMode('auto')" class="d-flex align-items-center">
              <CIcon class="me-2" icon="cilContrast" size="lg" /> {{ t('common.themeAuto') }}
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>

        <div class="vr mx-1" style="height:24px; opacity:.25;"></div>
        <AppHeaderDropdownAccnt />
      </CHeaderNav>
    </CContainer>
  </CHeader>
</template>

<style scoped>
/* School switcher pill */
.school-switcher-btn {
  background: rgba(0, 127, 62, 0.08);
  border: 1.5px solid rgba(0, 127, 62, 0.2);
  border-radius: 20px;
  padding: 4px 12px;
  font-size: .82rem;
  font-weight: 600;
  color: #007f3e;
  gap: 6px;
  transition: background .15s, border-color .15s;
  cursor: pointer;
}
.school-switcher-btn:hover {
  background: rgba(0, 127, 62, 0.14);
  border-color: rgba(0, 127, 62, 0.4);
}
.school-switcher-btn .school-name {
  max-width: 160px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.school-option {
  border-radius: 8px;
  font-size: .88rem;
  padding: 7px 10px;
}

.navbar-brand:hover { opacity: .85; }

/* Direct RouterLink active (Dashboard, Reports) */
.nav-link.router-link-active {
  color: #007f3e !important;
  font-weight: 700;
}

/* Dropdown toggle active — parent menu is active */
.nav-active {
  color: #007f3e !important;
  font-weight: 700;
  position: relative;
}
.nav-active::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 60%;
  height: 2px;
  background: #007f3e;
  border-radius: 2px;
}

/* Active item inside dropdown menu */
:deep(.dropdown-item.active),
:deep(.dropdown-item:active) {
  background-color: rgba(0, 127, 62, 0.12) !important;
  color: #007f3e !important;
  font-weight: 600;
}

/* Mobile offcanvas active link */
.btn.router-link-active {
  background-color: rgba(0, 127, 62, 0.1) !important;
  color: #007f3e !important;
  font-weight: 700;
}

@media (max-width: 767px) {
  .btn { min-height: 40px; }
}
</style>
