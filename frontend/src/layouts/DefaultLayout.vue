<script setup>
import { CContainer } from '@coreui/vue'
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import SessionTimeoutModal from '@/components/SessionTimeoutModal.vue'
import { useIdleSession } from '@/composables/useIdleSession'

const { showWarning, secondsLeft, stayLoggedIn, forceLogout } = useIdleSession()
</script>

<template>
  <div class="wrapper d-flex flex-column min-vh-100">
    <AppHeader />
    <div class="body flex-grow-1">
      <CContainer class="dashboard-shell px-2 px-lg-3" fluid>
        <router-view />
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
