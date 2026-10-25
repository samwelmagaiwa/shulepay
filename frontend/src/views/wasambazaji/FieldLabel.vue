<template>
  <label class="form-label fw-semibold mb-1">
    {{ text }}
    <span v-if="required" class="text-danger" aria-hidden="true">*</span>
    <small v-else class="text-muted fw-normal">({{ t('purchasing.common.optional') }})</small>
  </label>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

// A form label that says whether the field must be filled in or may be left empty.
defineProps({ text: { type: String, required: true }, required: { type: Boolean, default: false } })
const { t } = useI18n()
</script>
