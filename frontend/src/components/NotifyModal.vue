<script setup>
import { computed } from 'vue'
import { useNotify } from '@/composables/useNotify'

// The single notification / confirmation card behind useNotify() — mounted once in the layout.
const { state, accept, dismiss } = useNotify()

const ICON = { success: '✓', error: '✕', warning: '!', info: 'i' }
const COLOR = { success: 'success', error: 'danger', warning: 'warning', info: 'primary' }
const color = computed(() => COLOR[state.kind] ?? 'primary')
</script>

<template>
  <CModal :visible="state.visible" alignment="center" :backdrop="state.confirmMode ? 'static' : true" @close="dismiss">
    <CModalBody class="p-4">
      <div class="d-flex gap-3">
        <span
          class="notify-icon rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0 text-white fw-bold"
          :class="`bg-${color}`"
          aria-hidden="true"
        >{{ ICON[state.kind] }}</span>
        <div class="flex-grow-1" style="min-width:0;">
          <h5 v-if="state.title" class="mb-1">{{ state.title }}</h5>
          <p v-if="state.message" class="mb-0" style="white-space:pre-line;">{{ state.message }}</p>
          <ul v-if="state.items.length" class="mt-2 mb-0 ps-3 small">
            <li v-for="(item, i) in state.items" :key="i">{{ item }}</li>
          </ul>
        </div>
      </div>
    </CModalBody>
    <CModalFooter>
      <CButton v-if="state.confirmMode" color="secondary" variant="outline" @click="dismiss">{{ state.cancelText }}</CButton>
      <CButton :color="state.danger ? 'danger' : 'primary'" @click="accept">{{ state.confirmText }}</CButton>
    </CModalFooter>
  </CModal>
</template>

<style scoped>
.notify-icon { width: 36px; height: 36px; font-size: 1.05rem; line-height: 1; }
</style>
