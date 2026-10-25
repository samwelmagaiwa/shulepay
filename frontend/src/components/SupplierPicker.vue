<template>
  <div>
    <CFormSelect :model-value="modelValue === '' || modelValue == null ? '' : String(modelValue)" style="min-height:44px;" :aria-label="t('purchasing.picker.label')" @change="pick($event.target.value)">
      <option value="">{{ t('purchasing.picker.none') }}</option>
      <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
    </CFormSelect>
    <!-- No supplier chosen: the old free-text name is still allowed -->
    <CFormInput v-if="allowName && !modelValue" class="mt-1" style="min-height:44px;" :model-value="name"
                :placeholder="t('purchasing.picker.typed')" @input="$emit('update:name', $event.target.value)" />
  </div>
</template>

<script>
// One shared list for every picker on the page, fetched once.
let cache = null
</script>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { purchasing } from '@/services/purchasing'

defineProps({
  modelValue: { type: [Number, String], default: '' },
  name: { type: String, default: '' },
  allowName: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue', 'update:name'])
const { t } = useI18n()

const suppliers = ref([])

function pick(value) {
  const id = value === '' ? '' : Number(value)
  emit('update:modelValue', id)
  const chosen = suppliers.value.find(s => s.id === id)
  if (chosen) emit('update:name', chosen.name)
}

onMounted(async () => {
  try {
    cache ??= purchasing.suppliers({ usable: 1, per_page: 500 }).then(r => r.data)
    suppliers.value = await cache
  } catch {
    cache = null // try again next time; the select just stays empty
  }
})
</script>
