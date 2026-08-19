<script setup>
import { onBeforeMount } from 'vue'
import { useColorModes } from '@coreui/vue'
import { useThemeStore } from '@/stores/theme.js'

const { isColorModeSet, setColorMode } = useColorModes('coreui-free-vue-admin-template-theme')
const currentTheme = useThemeStore()

onBeforeMount(() => {
  const urlParams = new URLSearchParams(window.location.search)
  const theme = urlParams.get('theme')
  if (theme?.match(/^[A-Za-z0-9\s]+/)) {
    setColorMode(theme.match(/^[A-Za-z0-9\s]+/)[0])
    return
  }
  if (isColorModeSet()) return
  setColorMode(currentTheme.theme)
})
</script>

<template>
  <router-view />
</template>

<style lang="scss">
@use 'styles/style';
</style>
