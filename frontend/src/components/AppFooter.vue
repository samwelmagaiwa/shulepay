<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBrandingStore } from '@/stores/branding'
const { t } = useI18n()
const branding = useBrandingStore()

const today = computed(() => {
  const date = new Date()
  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  return `${day}/${month}/${year}`
})
</script>

<template>
  <CFooter class="px-4">
    <div>
      <a href="#" target="_blank"></a>
      <span class="ms-1"
        >&copy; {{ new Date().getFullYear() }} {{ branding.appName }}. {{ t('footer.today') }}: {{ today }}</span
      >
    </div>
    <div class="ms-auto">
      <span class="me-1">{{ t('footer.madeBy') }}</span>
      <a href="https://nexoryatech.com" target="_blank">{{ branding.appTagline }}</a>
    </div>
  </CFooter>
</template>
