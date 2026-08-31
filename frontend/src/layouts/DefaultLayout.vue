<script setup>
import { CContainer } from '@coreui/vue'
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import SessionTimeoutModal from '@/components/SessionTimeoutModal.vue'
import { useIdleSession } from '@/composables/useIdleSession'
import { useSchoolStore } from '@/stores/school'

const { showWarning, secondsLeft, stayLoggedIn, forceLogout } = useIdleSession()

// Most pages fetch their data once in onMounted and never react to the
// school switcher — only a handful opt in with their own watcher, and
// Dashboard.vue (the busiest page) doesn't even do that. Keying router-view
// on JUST the active school (nothing route-related) forces Vue to unmount
// and remount the CURRENT page whenever it changes, re-running that
// onMounted fetch for every page automatically — exactly what a manual
// browser refresh already does, without needing every view to remember to
// watch activeSchoolId itself. Deliberately excludes the route path/params:
// some pages navigate between the same component with different params
// (e.g. a detail page by id) and rely on staying mounted across that, with
// their own watcher on the param — tying the key to the school alone leaves
// that behavior untouched.
const schoolStore = useSchoolStore()
</script>

<template>
  <div class="wrapper d-flex flex-column min-vh-100">
    <AppHeader />
    <div class="body flex-grow-1">
      <CContainer class="dashboard-shell px-2 px-lg-3" fluid>
        <router-view :key="schoolStore.activeSchoolId" />
      </CContainer>
    </div>
    <AppFooter />

    <SessionTimeoutModal
      :visible="showWarning"
      :seconds-left="secondsLeft"
      @stay="stayLoggedIn"
      @logout="forceLogout"
    />
  </div>
</template>

<style scoped>
.dashboard-shell {
  max-width: 100%;
}
</style>
